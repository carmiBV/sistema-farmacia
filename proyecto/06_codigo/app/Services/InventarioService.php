<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Repositories\ConfigRepository;
use App\Repositories\InventarioRepository;
use App\Core\Database;
use App\Validators\InventarioValidator;

/**
 * CP-BACK-06 · Inventario y trazabilidad FEFO: liberación de lotes (RF-032),
 * alertas RF-044, incidentes RF-047 con doble autorización RF-046 y listados.
 * RN-02 (sin stock negativo), RN-03 (lotes bloqueados), RN-08 (transferencias).
 * Nunca DELETE FROM; kárdex append-only.
 */
final class InventarioService
{
    public function __construct(
        private readonly InventarioRepository $repo = new InventarioRepository(),
        private readonly InventarioValidator $validator = new InventarioValidator(),
        private readonly ConfigRepository $config = new ConfigRepository(),
    ) {
    }

    // ------------------------------------------------------------ listados

    /** @param array<string,mixed> $q */
    public function listarStocks(array $q): array
    {
        return $this->paginar(
            $q,
            [
                'store_id' => $this->validator->entero($q, 'store_id', 1, PHP_INT_MAX),
                'product_id' => $this->validator->entero($q, 'product_id', 1, PHP_INT_MAX),
                'lot_id' => $this->validator->entero($q, 'lot_id', 1, PHP_INT_MAX),
                'estado' => $this->validator->enumParam($q, 'estado', InventarioValidator::ESTADOS_LOTE),
            ],
            fn (array $f): int => $this->repo->contarStocks($f),
            fn (array $f, int $l, int $o): array => $this->repo->listarStocks($f, $l, $o)
        );
    }

    /** @param array<string,mixed> $q */
    public function listarMovimientos(array $q): array
    {
        return $this->paginar(
            $q,
            [
                'store_id' => $this->validator->entero($q, 'store_id', 1, PHP_INT_MAX),
                'lot_id' => $this->validator->entero($q, 'lot_id', 1, PHP_INT_MAX),
                'product_id' => $this->validator->entero($q, 'product_id', 1, PHP_INT_MAX),
                'tipo' => $this->validator->enumParam($q, 'tipo', InventarioValidator::TIPOS_MOVIMIENTO),
            ],
            fn (array $f): int => $this->repo->contarMovimientos($f),
            fn (array $f, int $l, int $o): array => $this->repo->listarMovimientos($f, $l, $o)
        );
    }

    /** @param array<string,mixed> $q */
    public function listarLotes(array $q): array
    {
        return $this->paginar(
            $q,
            [
                'product_id' => $this->validator->entero($q, 'product_id', 1, PHP_INT_MAX),
                'lot_id' => $this->validator->entero($q, 'lot_id', 1, PHP_INT_MAX),
                'estado' => $this->validator->enumParam($q, 'estado', InventarioValidator::ESTADOS_LOTE),
            ],
            fn (array $f): int => $this->repo->contarLotes($f),
            fn (array $f, int $l, int $o): array => $this->repo->listarLotes($f, $l, $o)
        );
    }

    /** @param array<string,mixed> $q */
    public function listarAlertas(array $q): array
    {
        return $this->paginar(
            $q,
            [
                'tipo' => $this->validator->enumParam($q, 'tipo', InventarioValidator::TIPOS_ALERTA),
                'estado' => $this->validator->enumParam($q, 'estado', InventarioValidator::ESTADOS_ALERTA),
                'product_id' => $this->validator->entero($q, 'product_id', 1, PHP_INT_MAX),
                'store_id' => $this->validator->entero($q, 'store_id', 1, PHP_INT_MAX),
            ],
            fn (array $f): int => $this->repo->contarAlertas($f),
            fn (array $f, int $l, int $o): array => $this->repo->listarAlertas($f, $l, $o)
        );
    }

    /** @param array<string,mixed> $q */
    public function listarIncidentes(array $q): array
    {
        return $this->paginar(
            $q,
            [
                'estado' => $this->validator->enumParam($q, 'estado', InventarioValidator::ESTADOS_INCIDENTE),
                'store_id' => $this->validator->entero($q, 'store_id', 1, PHP_INT_MAX),
                'lot_id' => $this->validator->entero($q, 'lot_id', 1, PHP_INT_MAX),
            ],
            fn (array $f): int => $this->repo->contarIncidentes($f),
            fn (array $f, int $l, int $o): array => $this->repo->listarIncidentes($f, $l, $o)
        );
    }

    /** @param array<string,mixed> $query @param array<string,mixed> $filtros */
    private function paginar(array $query, array $filtros, \Closure $contar, \Closure $listar): array
    {
        $pag = $this->validator->paginacion($query);
        try {
            $total = $contar($filtros);
            $items = $listar($filtros, $pag['limit'], ($pag['page'] - 1) * $pag['limit']);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return ['data' => $items, 'meta' => ['total' => $total, 'page' => $pag['page'], 'limit' => $pag['limit']]];
    }

    // -------------------------------------------------------------- lotes

    /** POST /inventory/batches/{id}/liberar (RF-032, RN-03): solo cuarentena → liberado. */
    public function liberar(string $rawId, array $input, int $userId): array
    {
        $id = $this->idEntero($rawId);
        $motivo = $this->validator->validarLiberacion($input);
        if ($this->repo->buscarLote($id) === null) {
            throw new AppException('NOT_FOUND', 'Lote no encontrado.', 404);
        }
        try {
            $ok = $this->repo->liberarLote($id, $motivo, $userId);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        if (!$ok) {
            throw new AppException('LOT_NOT_QUARANTINE', 'El lote no está en cuarentena.', 409);
        }
        return $this->repo->buscarLote($id);
    }

    // ------------------------------------------------------------ alertas

    /**
     * POST /inventory/alerts/evaluar (RF-044): genera alertas de stock mínimo
     * y vencimiento. Umbrales de system_config; dedup por aplicación (sin
     * constraint único en inventory_alerts).
     */
    public function evaluarAlertas(): array
    {
        $umbral = $this->umbral('alerta.stock_minimo', 10);
        $dias = $this->umbral('alerta.dias_vencimiento', 30);
        $creadas = 0;
        try {
            foreach ($this->repo->candidatasStockMinimo($umbral) as $c) {
                $productId = (int) $c['product_id'];
                $storeId = (int) $c['store_id'];
                if (!$this->repo->alertaAbiertaExiste('stock_minimo', $productId, $storeId)) {
                    $this->repo->insertarAlerta('stock_minimo', $productId, $storeId);
                    $creadas++;
                }
            }
            foreach ($this->repo->candidatasVencimiento($dias) as $c) {
                $productId = (int) $c['product_id'];
                if (!$this->repo->alertaAbiertaExiste('vencimiento', $productId, null)) {
                    $this->repo->insertarAlerta('vencimiento', $productId, null);
                    $creadas++;
                }
            }
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return ['creadas' => $creadas, 'umbral_stock_minimo' => $umbral, 'dias_vencimiento' => $dias];
    }

    /** POST /inventory/alerts/{id}/resolver (RF-044). */
    public function resolverAlerta(string $rawId, int $userId): array
    {
        $id = $this->idEntero($rawId);
        $alerta = $this->repo->buscarAlerta($id);
        if ($alerta === null) {
            throw new AppException('NOT_FOUND', 'Alerta no encontrada.', 404);
        }
        try {
            $ok = $alerta['estado'] === 'abierta' && $this->repo->resolverAlerta($id, $userId);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        if (!$ok) {
            throw new AppException('ALERT_NOT_OPEN', 'La alerta no está abierta.', 409);
        }
        return $this->repo->buscarAlerta($id);
    }

    /** Config numérica con default (RF-044); valor no numérico → default. */
    private function umbral(string $key, int $default): int
    {
        $row = $this->config->buscar($key);
        if ($row === null || !is_numeric($row['config_value'])) {
            return $default;
        }
        return max(0, (int) (float) $row['config_value']);
    }

    // ---------------------------------------------------------- incidentes

    /** POST /inventory/incidents (RF-047): consumo declarado vs stock registrado. */
    public function crearIncidente(array $input, int $userId): array
    {
        $d = $this->validator->validarIncidente($input);
        if (!$this->repo->storeExiste($d['store_id'])) {
            throw new AppException('NOT_FOUND', 'Sucursal no encontrada.', 404);
        }
        if ($this->repo->buscarLote($d['lot_id']) === null) {
            throw new AppException('NOT_FOUND', 'Lote no encontrado.', 404);
        }
        $stock = $this->repo->stockDisponible($d['store_id'], $d['lot_id']);
        if ($d['consumo_declarado'] > $stock) {
            throw new AppException('EXCEEDS_STOCK', 'El consumo declarado excede el stock registrado.', 409);
        }
        try {
            $id = $this->repo->insertarIncidente(
                $d['store_id'],
                $d['lot_id'],
                $stock,
                $d['consumo_declarado'],
                $userId
            );
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return $this->repo->buscarIncidente($id);
    }

    /**
     * POST /inventory/incidents/{id}/ajustar (RF-046 doble autorización):
     * proponente = abierto_por, autorizador = usuario actual. En la misma
     * transacción: CAS abierto→ajustado, descuento condicionado (RN-02) y
     * movimiento ajuste_baja (kárdex append-only).
     */
    public function ajustarIncidente(string $rawId, int $userId): array
    {
        $id = $this->idEntero($rawId);
        $inc = $this->repo->buscarIncidente($id);
        if ($inc === null) {
            throw new AppException('NOT_FOUND', 'Incidente no encontrado.', 404);
        }
        if ($inc['estado'] !== 'abierto') {
            throw new AppException('INCIDENT_NOT_OPEN', 'El incidente no está abierto.', 409);
        }
        if ((int) $inc['abierto_por'] === $userId) {
            throw new AppException(
                'DOUBLE_AUTH_REQUIRED',
                'El ajuste requiere doble autorización: otro usuario debe autorizar (RF-046).',
                409
            );
        }
        $consumo = (int) $inc['consumo_declarado'];
        if ($consumo > (int) $inc['stock_registrado']) {
            throw new AppException('EXCEEDS_STOCK', 'El consumo declarado excede el stock registrado.', 409);
        }
        $storeId = (int) $inc['store_id'];
        $lotId = (int) $inc['lot_id'];
        $lote = $this->repo->buscarLote($lotId);
        if ($lote === null) {
            throw new AppException('NOT_FOUND', 'Lote no encontrado.', 404);
        }
        $proponente = (int) $inc['abierto_por'];

        $pdo = Database::pdo();
        $enTx = false;
        try {
            $pdo->beginTransaction();
            $enTx = true;
            if (!$this->repo->cambiarEstadoIncidente($id, 'abierto', 'ajustado')) {
                throw new AppException('INCIDENT_NOT_OPEN', 'El incidente no está abierto.', 409);
            }
            if (!$this->repo->descontarStock($storeId, $lotId, $consumo)) {
                throw new AppException('STOCK_NOT_ENOUGH', 'Stock insuficiente para el ajuste.', 409);
            }
            $movId = $this->repo->insertarMovimiento([
                'store_id' => $storeId,
                'lot_id' => $lotId,
                'product_id' => (int) $lote['product_id'],
                'tipo' => 'ajuste_baja',
                'cantidad' => $consumo,
                'signo' => -1,
                'usuario_id' => $userId,
                'motivo' => 'Ajuste por incidente RF-047 (doble autorización RF-046)',
                'ref_tipo' => 'incidente',
                'ref_id' => $id,
                'proponente_id' => $proponente,
                'autorizador_id' => $userId,
            ]);
            $this->repo->asignarMovimientoAjuste($id, $movId);
            $pdo->commit();
            $enTx = false;
        } catch (AppException $e) {
            if ($enTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } catch (\PDOException) {
            if ($enTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return $this->repo->buscarIncidente($id);
    }

    /** POST /inventory/incidents/{id}/descartar. */
    public function descartarIncidente(string $rawId): array
    {
        $id = $this->idEntero($rawId);
        $inc = $this->repo->buscarIncidente($id);
        if ($inc === null) {
            throw new AppException('NOT_FOUND', 'Incidente no encontrado.', 404);
        }
        try {
            $ok = $inc['estado'] === 'abierto' && $this->repo->cambiarEstadoIncidente($id, 'abierto', 'descartado');
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        if (!$ok) {
            throw new AppException('INCIDENT_NOT_OPEN', 'El incidente no está abierto.', 409);
        }
        return $this->repo->buscarIncidente($id);
    }

    // -------------------------------------------------------------- extra

    private function idEntero(string $rawId): int
    {
        $id = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new AppException('VALIDATION_ERROR', "Parámetro 'id' inválido.", 400);
        }
        return $id;
    }
}
