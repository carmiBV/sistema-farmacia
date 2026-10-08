<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Repositories\CtrlRepository;
use App\Repositories\InventarioRepository;
use App\Repositories\UsuarioRepository;
use App\Core\Database;
use App\Validators\CtrlValidator;

/**
 * Libro Oficial de Medicamentos Controlados (CP-BACK-09).
 *
 * RF-055: saldo permanente por producto y sucursal (ctrl_balances) materializado
 * desde asientos append-only (ctrl_ledger_entries).
 * RF-046 / RN-06: los ajustes exigen doble autorizacion (autorizador != proponente).
 * RF-071: listado de asientos filtrable por periodo.
 * RNF-024: conciliacion automatica saldo vs suma de asientos.
 *
 * El asiento por cada movimiento de inventario de un producto controlado lo
 * escribe InventarioRepository::insertarMovimiento (hook), de modo que la
 * entrada/salida de stock y el libro comparten la misma transaccion.
 */
final class CtrlService
{
    public function __construct(
        private readonly CtrlRepository $repo = new CtrlRepository(),
        private readonly InventarioRepository $inv = new InventarioRepository(),
        private readonly UsuarioRepository $usuarios = new UsuarioRepository(),
        private readonly CtrlValidator $validator = new CtrlValidator(),
    ) {
    }

    /** @return array{data:list<array<string,mixed>>,meta:array{total:int,page:int,limit:int}} */
    public function listarAsientos(array $q): array
    {
        $f = $this->validator->filtrosAsientos($q);
        $page = $f['page'];
        $limit = $f['limit'];
        $filtros = array_filter([
            'store_id' => $f['store_id'],
            'product_id' => $f['product_id'],
            'tipo' => $f['tipo'],
            'ref_tipo' => $f['ref_tipo'],
            'desde' => $f['desde'],
            'hasta' => $f['hasta'],
        ], static fn ($v) => $v !== null);

        try {
            $data = $this->repo->listarAsientos($filtros, $limit, ($page - 1) * $limit);
            $total = $this->repo->contarAsientos($filtros);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return ['data' => $data, 'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit]];
    }

    /** @return array<string,mixed> */
    public function obtenerAsiento(string $rawId): array
    {
        $asiento = $this->repo->buscarAsiento($this->idEntero($rawId));
        if ($asiento === null) {
            throw new AppException('LEDGER_ENTRY_NOT_FOUND', 'Asiento no encontrado.', 404);
        }
        return $asiento;
    }

    /** @return array{data:list<array<string,mixed>>,meta:array{total:int,page:int,limit:int}} */
    public function listarSaldos(array $q): array
    {
        $f = $this->validator->filtrosSaldos($q);
        $page = $f['page'];
        $limit = $f['limit'];
        $filtros = array_filter([
            'store_id' => $f['store_id'],
            'product_id' => $f['product_id'],
        ], static fn ($v) => $v !== null);

        try {
            $data = $this->repo->listarSaldos($filtros, $limit, ($page - 1) * $limit);
            $total = $this->repo->contarSaldos($filtros);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return ['data' => $data, 'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit]];
    }

    /**
     * Conciliacion automatica de saldos (RNF-024): solo lectura, reporta
     * divergencias sin alterar el libro (append-only).
     *
     * @return array{total:int,conciliados:int,desbalanceados:int,diferencias:list<array<string,int|bool>>}
     */
    public function conciliacion(): array
    {
        try {
            return $this->repo->conciliacion();
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
    }

    /**
     * Ajuste de controlados con doble autorizacion (RF-046, RN-06).
     * Escribe el movimiento de inventario (proponente + autorizador) y, por el
     * hook, el asiento del libro en la misma transaccion.
     *
     * @return array{movimiento_id:int,asiento:array<string,mixed>,saldo:int}
     */
    public function ajustar(array $input, int $userId): array
    {
        $d = $this->validator->validarAjuste($input);

        if (!$this->repo->productoExiste($d['product_id'])) {
            throw new AppException('PRODUCT_NOT_FOUND', 'Producto no encontrado.', 404);
        }
        if (!$this->repo->productoEsControlado($d['product_id'])) {
            throw new AppException(
                'PRODUCT_NOT_CONTROLLED',
                'Solo los medicamentos controlados tienen asiento en el libro.',
                409
            );
        }

        $lote = $this->inv->buscarLote($d['lot_id']);
        if ($lote === null) {
            throw new AppException('LOT_NOT_FOUND', 'Lote no encontrado.', 404);
        }
        if ((int) $lote['product_id'] !== $d['product_id']) {
            throw new AppException('LOT_NOT_FOR_PRODUCT', 'El lote no pertenece al producto.', 409);
        }

        if ($d['autorizador_id'] === $userId) {
            throw new AppException(
                'DOUBLE_AUTH_REQUIRED',
                'El autorizador debe ser distinto del usuario que propone el ajuste.',
                409
            );
        }
        $autorizador = $this->usuarios->findById($d['autorizador_id']);
        if ($autorizador === null) {
            throw new AppException('USER_NOT_FOUND', 'Autorizador no encontrado.', 404);
        }
        if ($autorizador->estado !== 'activo') {
            throw new AppException('USER_NOT_FOUND', 'Autorizador no encontrado.', 404);
        }

        $baja = $d['direccion'] === 'baja';
        $pdo = Database::pdo();
        $enTx = false;
        try {
            $pdo->beginTransaction();
            $enTx = true;

            if ($baja && !$this->inv->descontarStock($d['store_id'], $d['lot_id'], $d['cantidad'])) {
                throw new AppException('STOCK_NOT_ENOUGH', 'Stock insuficiente para el ajuste.', 409);
            }
            if (!$baja) {
                $this->inv->sumarStock($d['store_id'], $d['lot_id'], $d['cantidad']);
            }

            $movId = $this->inv->insertarMovimiento([
                'store_id' => $d['store_id'],
                'lot_id' => $d['lot_id'],
                'product_id' => $d['product_id'],
                'tipo' => $baja ? 'ajuste_baja' : 'ajuste_incremento',
                'cantidad' => $d['cantidad'],
                'signo' => $baja ? -1 : 1,
                'usuario_id' => $userId,
                'motivo' => $d['motivo'],
                'ref_tipo' => 'manual',
                'ref_id' => 0,
                'proponente_id' => $userId,
                'autorizador_id' => $d['autorizador_id'],
            ]);

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
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        $asiento = $this->repo->buscarAsientoPorRef('ajuste', $movId);
        if ($asiento === null) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return [
            'movimiento_id' => $movId,
            'asiento' => $asiento,
            'saldo' => $this->repo->saldoDe($d['store_id'], $d['product_id']),
        ];
    }

    private function idEntero(string $raw): int
    {
        $v = trim($raw);
        if ($v === '' || preg_match('/^-?\d+$/', $v) !== 1 || (int) $v < 1) {
            throw new AppException('VALIDATION_ERROR', 'Identificador invalido.', 400);
        }
        return (int) $v;
    }
}
