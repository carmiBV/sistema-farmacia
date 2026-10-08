<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Repositories\InventarioRepository;
use App\Repositories\VentaRepository;
use App\Validators\InventarioValidator;
use App\Validators\VentaValidator;
use App\Core\Database;

/**
 * Servicio de ventas POS (CP-BACK-08): órdenes, pagos y devoluciones.
 * RF-050 (medios de pago), RF-060 (devoluciones), RN-01 (stock por lote),
 * RN-09 (CAS), RN-10 (orden original intacta).
 * Transacción + CAS + lock de fila; idempotencia por idempotency_key.
 */
final class VentaService
{
    private const MAX_INTENTOS_NUMERO = 3;

    public function __construct(
        private readonly VentaRepository $repo = new VentaRepository(),
        private readonly InventarioRepository $inv = new InventarioRepository(),
        private readonly InventarioValidator $validator = new InventarioValidator(),
        private readonly VentaValidator $ventas = new VentaValidator(),
    ) {
    }

    // -------------------------------------------------------------- órdenes

    /** @return array{data:list<array<string,mixed>>,meta:array{total:int,page:int,limit:int}} */
    public function listar(array $query): array
    {
        $filtros = array_filter([
            'store_id' => $this->validator->entero($query, 'store_id', 1, PHP_INT_MAX),
            'estado' => $this->validator->enumParam($query, 'estado', VentaValidator::ESTADOS_ORDEN),
        ], static fn ($v) => $v !== null);
        $pag = $this->validator->paginacion($query);
        $page = $pag['page'];
        $limit = $pag['limit'];

        try {
            $data = $this->repo->listarOrdenes($filtros, $limit, ($page - 1) * $limit);
            $total = $this->repo->contarOrdenes($filtros);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        return ['data' => $data, 'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit]];
    }

    /** @return array{orden:array<string,mixed>,items:list<array<string,mixed>>,pagos:list<array<string,mixed>>} */
    public function obtener(string $rawId): array
    {
        $id = $this->idEntero($rawId);
        $orden = $this->repo->buscarOrden($id);
        if ($orden === null) {
            throw new AppException('ORDER_NOT_FOUND', 'Orden no encontrada.', 404);
        }
        return [
            'orden' => $orden,
            'items' => $this->repo->itemsOrden($id),
            'pagos' => $this->repo->pagosDeOrden($id),
        ];
    }

    /**
     * Crear orden POS con descuento de stock atómico (RN-01: sin stock negativo).
     *
     * @return array{orden:array<string,mixed>,items:list<array<string,mixed>>,idempotent_reused:bool}
     */
    public function crear(array $input, int $userId): array
    {
        $d = $this->ventas->validarOrden($input);

        if ($d['idempotency_key'] !== null) {
            $previa = $this->repo->buscarOrdenPorIdempotencyKey($d['idempotency_key']);
            if ($previa !== null) {
                return $this->respuestaOrden($previa, true);
            }
        }

        $this->validarReferencias($d);
        [$items, $total] = $this->validarLotes($d);

        $pdo = Database::pdo();
        $numero = $this->generarNumero();
        $intento = 0;

        while (true) {
            $enTx = false;
            try {
                $pdo->beginTransaction();
                $enTx = true;

                $tid = $this->repo->insertarOrden(
                    $numero,
                    $d['store_id'],
                    $d['register_id'],
                    $d['paciente_id'],
                    $userId,
                    $total,
                    $d['idempotency_key']
                );

                foreach ($items as $it) {
                    $this->repo->insertarItemOrden(
                        $tid,
                        $it['lot']['id'],
                        $it['lot']['product_id'],
                        $it['cantidad'],
                        $it['precio_unitario'],
                        $it['subtotal']
                    );
                    if (!$this->inv->descontarStock($d['store_id'], $it['lot']['id'], $it['cantidad'])) {
                        throw new AppException(
                            'STOCK_NOT_ENOUGH',
                            'Stock insuficiente para uno de los lotes.',
                            409
                        );
                    }
                    $this->inv->insertarMovimiento([
                        'store_id' => $d['store_id'],
                        'lot_id' => $it['lot']['id'],
                        'product_id' => $it['lot']['product_id'],
                        'tipo' => 'salida',
                        'cantidad' => $it['cantidad'],
                        'signo' => -1,
                        'usuario_id' => $userId,
                        'motivo' => "Venta {$tid}",
                        'ref_tipo' => 'venta',
                        'ref_id' => $tid,
                    ]);
                }

                // Evento saliente en la misma transaccion (outbox, decision 16):
                // el envio externo queda desacoplado del commit (RNF-021).
                \App\Core\Audit::evento('venta', $tid, 'venta.registrada', [
                    'order_id' => $tid,
                    'store_id' => $d['store_id'],
                    'register_id' => $d['register_id'],
                    'total' => $total,
                    'items' => count($items),
                ]);

                $pdo->commit();
                $enTx = false;
                break;
            } catch (AppException $e) {
                if ($enTx && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            } catch (\PDOException $e) {
                if ($enTx && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                    if ($d['idempotency_key'] !== null) {
                        $previa = $this->repo->buscarOrdenPorIdempotencyKey($d['idempotency_key']);
                        if ($previa !== null) {
                            return $this->respuestaOrden($previa, true);
                        }
                    }
                    if (++$intento < self::MAX_INTENTOS_NUMERO) {
                        $numero = $this->generarNumero();
                        continue;
                    }
                }
                throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
            }
        }

        return $this->respuestaOrden($this->repo->buscarOrden($tid), false);
    }

    // --------------------------------------------------------------- pagos

    /**
     * Registrar pago; estado de orden por suma acumulada (RF-050).
     * Lock FOR UPDATE serializa la carrera entre pagos concurrentes.
     *
     * @return array{pago:array<string,mixed>,orden:array<string,mixed>,idempotent_reused:bool}
     */
    public function pagar(string $rawId, array $input, int $userId): array
    {
        $id = $this->idEntero($rawId);
        $d = $this->ventas->validarPago($input);
        unset($userId); // pago no modifica kárdex; usuario queda en la orden

        if ($d['idempotency_key'] !== null) {
            $previo = $this->repo->buscarPagoPorIdempotencyKey($d['idempotency_key']);
            if ($previo !== null) {
                return $this->respuestaPagoReusada($previo, $id);
            }
        }

        $pdo = Database::pdo();
        $enTx = false;
        try {
            $pdo->beginTransaction();
            $enTx = true;

            $orden = $this->repo->buscarOrdenBloqueada($id);
            if ($orden === null) {
                throw new AppException('ORDER_NOT_FOUND', 'Orden no encontrada.', 404);
            }
            if ($orden['estado'] !== 'pendiente') {
                throw new AppException('ORDER_NOT_PENDING', 'La orden no está pendiente de pago.', 409);
            }

            $pagado = $this->repo->sumarPagado($id);
            $pendiente = round((float) $orden['total'] - $pagado, 2);
            if ($d['monto'] > $pendiente) {
                throw new AppException(
                    'PAYMENT_EXCEEDS_DUE',
                    "El monto excede el saldo pendiente ({$pendiente}).",
                    409
                );
            }

            $pagoId = $this->repo->insertarPago($id, $d['medio'], $d['monto'], 'aprobado', $d['idempotency_key']);

            if (round($pagado + $d['monto'], 2) >= (float) $orden['total']) {
                if (!$this->repo->cambiarEstadoOrden($id, 'pendiente', 'pagada')) {
                    throw new AppException('ORDER_NOT_PENDING', 'La orden ya no está pendiente de pago.', 409);
                }
            }

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
            if ((int) ($e->errorInfo[1] ?? 0) === 1062 && $d['idempotency_key'] !== null) {
                $previo = $this->repo->buscarPagoPorIdempotencyKey($d['idempotency_key']);
                if ($previo !== null) {
                    return $this->respuestaPagoReusada($previo, $id);
                }
            }
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        $pago = [
            'id' => $pagoId,
            'order_id' => $id,
            'medio' => $d['medio'],
            'amount' => $d['monto'],
            'status' => 'aprobado',
        ];
        return ['pago' => $pago, 'orden' => $this->repo->buscarOrden($id), 'idempotent_reused' => false];
    }

    // -------------------------------------------------------------- anular

    /**
     * Anular orden y reponer stock (compensatorio, RN-10/auditoría).
     * Sin reembolso automático (DB-P04 pendiente con el cliente).
     *
     * @return array{orden:array<string,mixed>,items:list<array<string,mixed>>}
     */
    public function anular(string $rawId, int $userId): array
    {
        $id = $this->idEntero($rawId);
        $pdo = Database::pdo();
        $enTx = false;
        try {
            $pdo->beginTransaction();
            $enTx = true;

            $orden = $this->repo->buscarOrdenBloqueada($id);
            if ($orden === null) {
                throw new AppException('ORDER_NOT_FOUND', 'Orden no encontrada.', 404);
            }
            if (!$this->repo->cambiarEstadoOrdenDesde($id, ['pendiente', 'pagada'], 'anulada')) {
                throw new AppException('ORDER_NOT_CANCELLABLE', 'Solo se pueden anular órdenes pendientes o pagadas.', 409);
            }

            foreach ($this->repo->itemsOrden($id) as $it) {
                $this->inv->sumarStock((int) $orden['store_id'], (int) $it['lot_id'], (int) $it['cantidad']);
                $this->inv->insertarMovimiento([
                    'store_id' => (int) $orden['store_id'],
                    'lot_id' => (int) $it['lot_id'],
                    'product_id' => (int) $it['product_id'],
                    'tipo' => 'compensatorio',
                    'cantidad' => (int) $it['cantidad'],
                    'signo' => 1,
                    'usuario_id' => $userId,
                    'motivo' => "Anulacion de venta {$id}",
                    'ref_tipo' => 'venta',
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
        } catch (\PDOException $e) {
            if ($enTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        return ['orden' => $this->repo->buscarOrden($id), 'items' => $this->repo->itemsOrden($id)];
    }

    // -------------------------------------------------------- devoluciones

    /** @return array{data:list<array<string,mixed>>,meta:array{total:int,page:int,limit:int}} */
    public function listarDevoluciones(array $query): array
    {
        $filtros = array_filter([
            'order_id' => $this->validator->entero($query, 'order_id', 1, PHP_INT_MAX),
            'store_id' => $this->validator->entero($query, 'store_id', 1, PHP_INT_MAX),
        ], static fn ($v) => $v !== null);
        $pag = $this->validator->paginacion($query);

        try {
            $data = $this->repo->listarDevoluciones($filtros, $pag['limit'], ($pag['page'] - 1) * $pag['limit']);
            $total = $this->repo->contarDevoluciones($filtros);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        return ['data' => $data, 'meta' => ['total' => $total, 'page' => $pag['page'], 'limit' => $pag['limit']]];
    }

    /** @return array{devolucion:array<string,mixed>,items:list<array<string,mixed>>} */
    public function obtenerDevolucion(string $rawId): array
    {
        $id = $this->idEntero($rawId);
        $dev = $this->repo->buscarDevolucion($id);
        if ($dev === null) {
            throw new AppException('RETURN_NOT_FOUND', 'Devolución no encontrada.', 404);
        }
        return ['devolucion' => $dev, 'items' => $this->repo->itemsDevolucion($id)];
    }

    /**
     * Registrar devolución (RF-060, RN-10): orden original intacta,
     * monto calculado en servidor, reingreso de 'vendible' al stock.
     *
     * @return array{devolucion:array<string,mixed>,items:list<array<string,mixed>>}
     */
    public function crearDevolucion(array $input, int $userId): array
    {
        $d = $this->ventas->validarDevolucion($input);
        $pdo = Database::pdo();
        $enTx = false;
        try {
            $pdo->beginTransaction();
            $enTx = true;

            $orden = $this->repo->buscarOrdenBloqueada($d['order_id']);
            if ($orden === null) {
                throw new AppException('ORDER_NOT_FOUND', 'Orden no encontrada.', 404);
            }
            if ($orden['estado'] !== 'pagada') {
                throw new AppException('ORDER_NOT_PAID', 'Solo se devuelven órdenes pagadas.', 409);
            }

            $lineas = [];
            $monto = 0.0;
            $reingresa = false;
            foreach ($d['items'] as $it) {
                $item = $this->repo->itemOrden($it['order_item_id']);
                if ($item === null || $item['order_id'] !== $d['order_id']) {
                    throw new AppException('ITEM_NOT_IN_ORDER', 'El ítem no pertenece a la orden.', 409);
                }
                $yaDevuelto = $this->repo->devueltoDeItem($it['order_item_id']);
                if ($it['cantidad'] > $item['cantidad'] - $yaDevuelto) {
                    throw new AppException(
                        'RETURN_EXCEEDS_SOLD',
                        'La cantidad devuelta supera lo vendido.',
                        409
                    );
                }
                if ($it['condicion'] === 'vendible') {
                    $reingresa = true;
                }
                $lineas[] = ['item' => $item, 'cantidad' => $it['cantidad'], 'condicion' => $it['condicion']];
                $monto = round($monto + ($it['cantidad'] * $item['precio_unitario']), 2);
            }

            $estadoEval = $reingresa ? 'reingresado' : 'descartado';
            $rid = $this->repo->insertarDevolucion(
                $d['order_id'],
                (int) $orden['store_id'],
                $userId,
                $d['motivo'],
                $monto,
                $estadoEval
            );

            foreach ($lineas as $ln) {
                $item = $ln['item'];
                $this->repo->insertarItemDevolucion(
                    $rid,
                    $item['id'],
                    $item['lot_id'],
                    $item['product_id'],
                    $ln['cantidad'],
                    $ln['condicion']
                );
                if ($ln['condicion'] === 'vendible') {
                    $this->inv->sumarStock((int) $orden['store_id'], $item['lot_id'], $ln['cantidad']);
                    $this->inv->insertarMovimiento([
                        'store_id' => (int) $orden['store_id'],
                        'lot_id' => $item['lot_id'],
                        'product_id' => $item['product_id'],
                        'tipo' => 'devolucion',
                        'cantidad' => $ln['cantidad'],
                        'signo' => 1,
                        'usuario_id' => $userId,
                        'motivo' => "Devolucion {$rid}",
                        'ref_tipo' => 'devolucion',
                        'ref_id' => $rid,
                    ]);
                }
            }

            // ponytail: la orden no transiciona a 'devuelta' en CP-08; el CHECK
            // chk_so_devuelta_con_paciente exige paciente_id y §22 deja el
            // cambio de estado de la orden para la política RF-100 (pendiente
            // con el cliente). Reingreso y saldos ya quedan registrados.
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

        return ['devolucion' => $this->repo->buscarDevolucion($rid), 'items' => $this->repo->itemsDevolucion($rid)];
    }

    // ------------------------------------------------------------- helpers

    private function idEntero(string $raw): int
    {
        $v = trim($raw);
        if ($v === '' || preg_match('/^-?\d+$/', $v) !== 1 || (int) $v < 1) {
            throw new AppException('VALIDATION_ERROR', 'Identificador inválido.', 400);
        }
        return (int) $v;
    }

    /** @param array{store_id:int,register_id:int,paciente_id:?int} $d */
    private function validarReferencias(array $d): void
    {
        if (!$this->repo->storeExiste($d['store_id'])) {
            throw new AppException('STORE_NOT_FOUND', 'Sucursal no encontrada.', 404);
        }
        if (!$this->repo->registerExisteEnStore($d['register_id'], $d['store_id'])) {
            throw new AppException('REGISTER_NOT_IN_STORE', 'La caja no pertenece a la sucursal.', 409);
        }
        if ($d['paciente_id'] !== null && !$this->repo->pacienteExiste($d['paciente_id'])) {
            throw new AppException('PATIENT_NOT_FOUND', 'Paciente no encontrado.', 404);
        }
    }

    /**
     * Valida cada lote: existe, liberado (RN-03) y no vencido (RN-02).
     *
     * @param array{store_id:int,items:list<array{lot_id:int,cantidad:int,precio_unitario:float}>} $d
     * @return array{0:list<array{lot:array<string,mixed>,cantidad:int,precio_unitario:float,subtotal:float}>,1:float}
     */
    private function validarLotes(array $d): array
    {
        $hoy = date('Y-m-d');
        $items = [];
        $total = 0.0;
        foreach ($d['items'] as $it) {
            $lot = $this->inv->buscarLote($it['lot_id']);
            if ($lot === null) {
                throw new AppException('LOT_NOT_FOUND', "Lote {$it['lot_id']} no encontrado.", 404);
            }
            if ($lot['estado'] !== 'liberado') {
                throw new AppException('LOT_NOT_RELEASED', 'El lote no está liberado.', 409);
            }
            if ($lot['fecha_vencimiento'] < $hoy) {
                throw new AppException('LOT_EXPIRED', 'El lote está vencido.', 409);
            }
            $subtotal = round($it['cantidad'] * $it['precio_unitario'], 2);
            $total = round($total + $subtotal, 2);
            $items[] = [
                'lot' => $lot,
                'cantidad' => $it['cantidad'],
                'precio_unitario' => $it['precio_unitario'],
                'subtotal' => $subtotal,
            ];
        }
        return [$items, $total];
    }

    private function generarNumero(): string
    {
        return 'V-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    /**
     * @param array<string,mixed>|null $orden
     * @return array{orden:array<string,mixed>,items:list<array<string,mixed>>,idempotent_reused:bool}
     */
    private function respuestaOrden(?array $orden, bool $reused): array
    {
        if ($orden === null) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return ['orden' => $orden, 'items' => $this->repo->itemsOrden((int) $orden['id']), 'idempotent_reused' => $reused];
    }

    /**
     * @param array{id:int,order_id:int,medio:string,amount:float,status:string} $pago
     * @return array{pago:array<string,mixed>,orden:array<string,mixed>,idempotent_reused:bool}
     */
    private function respuestaPagoReusada(array $pago, int $id): array
    {
        if ($pago['order_id'] !== $id) {
            throw new AppException(
                'IDEMPOTENCY_CONFLICT',
                'La clave de idempotencia pertenece a otra orden.',
                409
            );
        }
        $orden = $this->repo->buscarOrden($id);
        if ($orden === null) {
            throw new AppException('ORDER_NOT_FOUND', 'Orden no encontrada.', 404);
        }
        return ['pago' => $pago, 'orden' => $orden, 'idempotent_reused' => true];
    }
}
