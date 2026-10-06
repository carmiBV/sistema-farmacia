<?php
declare(strict_types=1);

namespace App\Services;

use App\Http\AppException;
use App\Repositories\InventarioRepository;
use App\Support\Database;
use App\Validators\InventarioValidator;

/**
 * CP-BACK-06 · Transferencias entre sucursales (RN-08, FEFO): crear con
 * idempotencia, despachar con descuento condicionado (RN-02), recibir con
 * suma en destino, cerrar y rechazar. Kárdex append-only; nunca DELETE FROM.
 */
final class TransferenciaService
{
    public function __construct(
        private readonly InventarioRepository $repo = new InventarioRepository(),
        private readonly InventarioValidator $validator = new InventarioValidator(),
    ) {
    }

    /** @param array<string,mixed> $q */
    public function listar(array $q): array
    {
        $filtros = [
            'store_origen_id' => $this->validator->entero($q, 'store_origen_id', 1, PHP_INT_MAX),
            'store_destino_id' => $this->validator->entero($q, 'store_destino_id', 1, PHP_INT_MAX),
            'estado' => $this->validator->enumParam($q, 'estado', InventarioValidator::ESTADOS_TRANSFERENCIA),
        ];
        $pag = $this->validator->paginacion($q);
        try {
            $total = $this->repo->contarTransferencias($filtros);
            $items = $this->repo->listarTransferencias($filtros, $pag['limit'], ($pag['page'] - 1) * $pag['limit']);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return ['data' => $items, 'meta' => ['total' => $total, 'page' => $pag['page'], 'limit' => $pag['limit']]];
    }

    /** GET /inventory/transfers/{id}. */
    public function obtener(string $rawId): array
    {
        $id = $this->idEntero($rawId);
        $t = $this->repo->buscarTransferencia($id);
        if ($t === null) {
            throw new AppException('NOT_FOUND', 'Transferencia no encontrada.', 404);
        }
        return ['transferencia' => $t, 'items' => $this->repo->itemsTransferencia($id)];
    }

    /**
     * POST /inventory/transfers (RN-08): crea solicitud con ítems; el stock
     * NO se toca hasta despachar. Idempotencia por idempotency_key.
     */
    public function crear(array $input, int $userId): array
    {
        $d = $this->validator->validarTransferencia($input);
        if ($d['idempotency_key'] !== null) {
            $previa = $this->repo->buscarTransferenciaPorIdempotencyKey($d['idempotency_key']);
            if ($previa !== null) {
                return $this->respuesta($previa, true);
            }
        }
        if (!$this->repo->storeExiste($d['store_origen_id'])) {
            throw new AppException('NOT_FOUND', 'Sucursal de origen no encontrada.', 404);
        }
        if (!$this->repo->storeExiste($d['store_destino_id'])) {
            throw new AppException('NOT_FOUND', 'Sucursal de destino no encontrada.', 404);
        }
        foreach ($d['items'] as $item) {
            if ($this->repo->buscarLote($item['lot_id']) === null) {
                throw new AppException('NOT_FOUND', "Lote {$item['lot_id']} no encontrado.", 404);
            }
        }

        $pdo = Database::pdo();
        $enTx = false;
        $tid = 0;
        try {
            $pdo->beginTransaction();
            $enTx = true;
            foreach ($d['items'] as $item) {
                if ($this->repo->stockDisponible($d['store_origen_id'], $item['lot_id'], true) < $item['cantidad']) {
                    throw new AppException(
                        'STOCK_NOT_ENOUGH',
                        "Stock insuficiente en origen para el lote {$item['lot_id']}.",
                        409
                    );
                }
            }
            $tid = $this->repo->insertarTransferencia(
                $d['store_origen_id'],
                $d['store_destino_id'],
                $d['idempotency_key'],
                $userId
            );
            $this->repo->insertarItemsTransferencia($tid, $d['items']);
            $pdo->commit();
            $enTx = false;
        } catch (AppException $e) {
            if ($enTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } catch (\PDOException $e) {
            if ($enTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ((int) $e->getCode() === 1062 && $d['idempotency_key'] !== null) {
                $previa = $this->repo->buscarTransferenciaPorIdempotencyKey($d['idempotency_key']);
                if ($previa !== null) {
                    return $this->respuesta($previa, true);
                }
            }
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return $this->respuesta($this->repo->buscarTransferencia($tid), false);
    }

    /** POST /inventory/transfers/{id}/despachar: FEFO por lote, descuento condicionado. */
    public function despachar(string $rawId, int $userId): array
    {
        $id = $this->idEntero($rawId);
        $t = $this->repo->buscarTransferencia($id);
        if ($t === null) {
            throw new AppException('NOT_FOUND', 'Transferencia no encontrada.', 404);
        }
        $origen = (int) $t['store_origen_id'];
        $hoy = date('Y-m-d');

        $pdo = Database::pdo();
        $enTx = false;
        try {
            $pdo->beginTransaction();
            $enTx = true;
            if (!$this->repo->cambiarEstadoTransferencia($id, 'solicitada', 'despachada', 'despachado_por', $userId)) {
                throw new AppException('TRANSFER_NOT_PENDING', 'La transferencia no está solicitada.', 409);
            }
            foreach ($this->repo->itemsTransferencia($id) as $item) {
                $lotId = (int) $item['lot_id'];
                if ($item['estado'] !== 'liberado') {
                    throw new AppException('LOT_NOT_RELEASED', "El lote {$lotId} no está liberado.", 409);
                }
                if ($item['fecha_vencimiento'] !== null && (string) $item['fecha_vencimiento'] < $hoy) {
                    throw new AppException('LOT_EXPIRED', "El lote {$lotId} está vencido.", 409);
                }
                $cant = (int) $item['cantidad_despachada'];
                if (!$this->repo->descontarStock($origen, $lotId, $cant)) {
                    throw new AppException('STOCK_NOT_ENOUGH', "Stock insuficiente para el lote {$lotId}.", 409);
                }
                $this->repo->insertarMovimiento([
                    'store_id' => $origen,
                    'lot_id' => $lotId,
                    'product_id' => (int) $item['product_id'],
                    'tipo' => 'transferencia_salida',
                    'cantidad' => $cant,
                    'signo' => -1,
                    'usuario_id' => $userId,
                    'motivo' => "Despacho de transferencia {$id}",
                    'ref_tipo' => 'transferencia',
                    'ref_id' => $id,
                ]);
            }
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
        return $this->respuesta($this->repo->buscarTransferencia($id), false, $this->repo->itemsTransferencia($id));
    }

    /** POST /inventory/transfers/{id}/recibir: recibida ≤ despachada por lote (RN-08). */
    public function recibir(string $rawId, array $input, int $userId): array
    {
        $id = $this->idEntero($rawId);
        $d = $this->validator->validarRecepcionTransfer($input);
        $t = $this->repo->buscarTransferencia($id);
        if ($t === null) {
            throw new AppException('NOT_FOUND', 'Transferencia no encontrada.', 404);
        }
        $destino = (int) $t['store_destino_id'];
        $dbItems = [];
        foreach ($this->repo->itemsTransferencia($id) as $it) {
            $dbItems[(int) $it['lot_id']] = $it;
        }
        foreach ($d['items'] as $item) {
            $lotId = $item['lot_id'];
            if (!isset($dbItems[$lotId])) {
                throw new AppException('ITEM_NOT_IN_TRANSFER', "El lote {$lotId} no pertenece a la transferencia.", 400);
            }
            if ($item['cantidad_recibida'] > (int) $dbItems[$lotId]['cantidad_despachada']) {
                throw new AppException(
                    'EXCEEDS_DESPACHADA',
                    "La cantidad recibida del lote {$lotId} excede la despachada.",
                    409
                );
            }
        }

        $pdo = Database::pdo();
        $enTx = false;
        try {
            $pdo->beginTransaction();
            $enTx = true;
            if (!$this->repo->cambiarEstadoTransferencia($id, 'despachada', 'recibida', 'recibido_por', $userId)) {
                throw new AppException('TRANSFER_NOT_DESPACHADA', 'La transferencia no está despachada.', 409);
            }
            foreach ($d['items'] as $item) {
                $lotId = $item['lot_id'];
                $this->repo->marcarRecibidaItem($id, $item);
                if ($item['cantidad_recibida'] > 0) {
                    $this->repo->sumarStock($destino, $lotId, $item['cantidad_recibida']);
                    $this->repo->insertarMovimiento([
                        'store_id' => $destino,
                        'lot_id' => $lotId,
                        'product_id' => (int) $dbItems[$lotId]['product_id'],
                        'tipo' => 'transferencia_entrada',
                        'cantidad' => $item['cantidad_recibida'],
                        'signo' => 1,
                        'usuario_id' => $userId,
                        'motivo' => "Recepción de transferencia {$id}",
                        'ref_tipo' => 'transferencia',
                        'ref_id' => $id,
                    ]);
                }
            }
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
        return $this->respuesta($this->repo->buscarTransferencia($id), false, $this->repo->itemsTransferencia($id));
    }

    /** POST /inventory/transfers/{id}/cerrar. */
    public function cerrar(string $rawId): array
    {
        $id = $this->idEntero($rawId);
        if ($this->repo->buscarTransferencia($id) === null) {
            throw new AppException('NOT_FOUND', 'Transferencia no encontrada.', 404);
        }
        if (!$this->repo->cambiarEstadoTransferencia($id, 'recibida', 'cerrada', '', 0)) {
            throw new AppException('TRANSFER_NOT_RECIBIDA', 'La transferencia no está recibida.', 409);
        }
        return $this->respuesta($this->repo->buscarTransferencia($id), false, $this->repo->itemsTransferencia($id));
    }

    /** POST /inventory/transfers/{id}/rechazar. */
    public function rechazar(string $rawId): array
    {
        $id = $this->idEntero($rawId);
        if ($this->repo->buscarTransferencia($id) === null) {
            throw new AppException('NOT_FOUND', 'Transferencia no encontrada.', 404);
        }
        if (!$this->repo->cambiarEstadoTransferencia($id, 'solicitada', 'rechazada', '', 0)) {
            throw new AppException('TRANSFER_NOT_PENDING', 'La transferencia no está solicitada.', 409);
        }
        return $this->respuesta($this->repo->buscarTransferencia($id), false, $this->repo->itemsTransferencia($id));
    }

    /**
     * @param array<string,mixed>|null $t
     * @param list<array<string,mixed>>|null $items
     * @return array<string,mixed>
     */
    private function respuesta(?array $t, bool $reused, ?array $items = null): array
    {
        if ($t === null) {
            throw new AppException('NOT_FOUND', 'Transferencia no encontrada.', 404);
        }
        return [
            'transferencia' => $t,
            'items' => $items ?? $this->repo->itemsTransferencia((int) $t['id']),
            'idempotent_reused' => $reused,
        ];
    }

    private function idEntero(string $rawId): int
    {
        $id = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new AppException('VALIDATION_ERROR', "Parámetro 'id' inválido.", 400);
        }
        return $id;
    }
}
