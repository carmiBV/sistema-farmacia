<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Models\Recepcion;
use App\Repositories\OrdenCompraRepository;
use App\Repositories\RecepcionRepository;
use App\Core\Database;
use App\Validators\CompraValidator;

/**
 * Casos de uso de recepciones (RF-031, RF-032, RN-07, RN-09, RNF-023):
 * creación con idempotencia opcional, confirmación transaccional T-3
 * (lote en cuarentena + stock + movimiento entrada + outbox) y rechazo.
 */
final class RecepcionService
{
    public function __construct(
        private readonly RecepcionRepository $repo = new RecepcionRepository(),
        private readonly OrdenCompraRepository $ordenRepo = new OrdenCompraRepository(),
        private readonly CompraValidator $validator = new CompraValidator(),
    ) {
    }

    /**
     * @param array<string,mixed> $query order_id, estado, page, limit
     * @return array{data:list<Recepcion>,meta:array{total:int,page:int,limit:int}}
     * @throws AppException VALIDATION_ERROR (400) | DATABASE_ERROR (500)
     */
    public function listar(array $query): array
    {
        $page = $this->entero($query, 'page', 1, 1, 1_000_000);
        $limit = $this->entero($query, 'limit', 20, 1, 100);
        $filters = [
            'order_id' => $this->texto($query, 'order_id'),
            'estado' => $this->texto($query, 'estado'),
        ];
        try {
            $total = $this->repo->contar($filters);
            $items = $this->repo->listar($filters, $limit, ($page - 1) * $limit);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return ['data' => $items, 'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit]];
    }

    /**
     * Crea la recepción con su detalle. RN-09: idempotencia solo cuando la
     * clave viene poblada; una clave repetida devuelve la recepción original.
     *
     * @param array<string,mixed> $input body PurchaseReceptionCreate
     * @return array{reception:Recepcion,items:list<array<string,mixed>>,reused:bool}
     * @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (404)
     *         | ORDER_NOT_ISSUED (409) | ITEM_NOT_IN_ORDER (400)
     *         | EXCEEDS_PENDING (400) | DUPLICATE_LOT (409) | DATABASE_ERROR (500)
     */
    public function crear(string $rawOrderId, array $input, int $userId): array
    {
        $orderId = $this->idEntero($rawOrderId, 'id');
        $datos = $this->validator->validarRecepcion($input);
        $key = $datos['idempotency_key'];

        try {
            if ($key !== null) {
                $duplicada = $this->repo->buscarPorIdempotencyKey($key);
                if ($duplicada !== null) {
                    return $this->respuesta($duplicada, reused: true);
                }
            }

            $orden = $this->ordenRepo->buscarPorId($orderId);
            if ($orden === null) {
                throw new AppException('NOT_FOUND', 'Orden no encontrada.', 404);
            }
            if ($orden->estado !== 'emitida') {
                throw new AppException(
                    'ORDER_NOT_ISSUED',
                    'Solo las órdenes emitidas pueden recibir.',
                    409
                );
            }

            $pendientes = [];
            foreach ($this->ordenRepo->items($orderId) as $item) {
                $pendientes[$item['product_id']] = $item['cantidad_pedida'] - $item['cantidad_recibida'];
            }
            foreach ($datos['items'] as $item) {
                if (!array_key_exists($item['product_id'], $pendientes)) {
                    throw new AppException(
                        'ITEM_NOT_IN_ORDER',
                        'El producto ' . $item['product_id'] . ' no está en la orden.',
                        400
                    );
                }
                if ($item['cantidad'] > $pendientes[$item['product_id']]) {
                    throw new AppException(
                        'EXCEEDS_PENDING',
                        'La cantidad del producto ' . $item['product_id'] . ' excede lo pendiente.',
                        400
                    );
                }
                if ($this->repo->loteExiste($item['product_id'], $item['numero_lote'])) {
                    throw new AppException(
                        'DUPLICATE_LOT',
                        'El lote ' . $item['numero_lote'] . ' ya existe para el producto '
                            . $item['product_id'] . '.',
                        409
                    );
                }
            }

            $pdo = Database::pdo();
            $pdo->beginTransaction();
            $receptionId = $this->repo->insertar($orderId, $key, $userId);
            $this->repo->insertarItems($receptionId, $datos['items']);
            $pdo->commit();
        } catch (AppException $e) {
            if (Database::pdo()->inTransaction()) {
                Database::pdo()->rollBack();
            }
            throw $e;
        } catch (\PDOException $e) {
            if (Database::pdo()->inTransaction()) {
                Database::pdo()->rollBack();
            }
            // RN-09: carrera entre peticiones con la misma idempotency_key.
            if ((int) ($e->errorInfo[1] ?? 0) === 1062 && $key !== null) {
                $duplicada = $this->repo->buscarPorIdempotencyKey($key);
                if ($duplicada !== null) {
                    return $this->respuesta($duplicada, reused: true);
                }
            }
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        $reception = $this->repo->buscarPorId($receptionId);
        if ($reception === null) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return $this->respuesta($reception, reused: false);
    }

    /** @return array{reception:Recepcion,items:list<array<string,mixed>>,reused:bool} */
    private function respuesta(Recepcion $reception, bool $reused): array
    {
        return [
            'reception' => $reception,
            'items' => $this->items($reception->id),
            'reused' => $reused,
        ];
    }

    /** @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (404) */
    public function obtener(string $rawId): Recepcion
    {
        $reception = $this->repo->buscarPorId($this->idEntero($rawId, 'id'));
        if ($reception === null) {
            throw new AppException('NOT_FOUND', 'Recepción no encontrada.', 404);
        }
        return $reception;
    }

    /**
     * Confirmación transaccional (T-3): valida, crea lotes en cuarentena
     * (RF-032), suma stock, registra la entrada (CA-10), incrementa lo
     * recibido y solo al final marca la recepción y la orden.
     *
     * @param array<string,mixed> $input body ReceptionConfirm {store_id}
     * @return array{reception:Recepcion,items:list<array<string,mixed>>}
     * @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (404)
     *         | RECEPTION_NOT_PENDING (409) | EXCEEDS_PENDING (400)
     *         | DUPLICATE_LOT (409) | DATABASE_ERROR (500)
     */
    public function confirmar(string $rawId, array $input, int $userId): array
    {
        $id = $this->idEntero($rawId, 'id');
        $storeId = $this->validator->validarConfirmacion($input)['store_id'];
        $pdo = Database::pdo();
        $enTx = false;

        try {
            $reception = $this->repo->buscarPorId($id);
            if ($reception === null) {
                throw new AppException('NOT_FOUND', 'Recepción no encontrada.', 404);
            }
            if (!$this->repo->storeExiste($storeId)) {
                throw new AppException('NOT_FOUND', 'La sucursal no existe.', 404);
            }

            $pdo->beginTransaction();
            $enTx = true;
            $estado = $this->repo->estadoBloqueado($id);
            if ($estado === null) {
                throw new AppException('NOT_FOUND', 'Recepción no encontrada.', 404);
            }
            if ($estado !== 'recibida') {
                throw new AppException(
                    'RECEPTION_NOT_PENDING',
                    'La recepción no está pendiente de confirmación.',
                    409
                );
            }

            $pendientes = [];
            foreach ($this->ordenRepo->itemsBloqueados($reception->orderId) as $fila) {
                $pendientes[$fila['product_id']] = $fila['cantidad_pedida'] - $fila['cantidad_recibida'];
            }

            foreach ($this->repo->items($id) as $item) {
                if (!array_key_exists($item['product_id'], $pendientes)) {
                    throw new AppException(
                        'ITEM_NOT_IN_ORDER',
                        'El producto ' . $item['product_id'] . ' no está en la orden.',
                        400
                    );
                }
                if ($item['cantidad'] > $pendientes[$item['product_id']]) {
                    throw new AppException(
                        'EXCEEDS_PENDING',
                        'La cantidad del producto ' . $item['product_id'] . ' excede lo pendiente.',
                        400
                    );
                }
                if ($this->repo->loteExiste($item['product_id'], $item['numero_lote'])) {
                    throw new AppException(
                        'DUPLICATE_LOT',
                        'El lote ' . $item['numero_lote'] . ' ya existe.',
                        409
                    );
                }

                $lotId = $this->repo->insertarLote(
                    $item['product_id'],
                    $item['numero_lote'],
                    $item['fecha_vencimiento'],
                    $item['id'],
                );
                $this->repo->asignarLot($item['id'], $lotId);
                $this->repo->insertarStock($storeId, $lotId, $item['cantidad']);
                $this->repo->insertarMovimientoEntrada(
                    $storeId,
                    $lotId,
                    $item['product_id'],
                    $item['cantidad'],
                    $userId,
                    $id,
                );
                $this->ordenRepo->sumarRecibido($reception->orderId, $item['product_id'], $item['cantidad']);
                $pendientes[$item['product_id']] -= $item['cantidad'];
            }

            if (!$this->repo->marcar($id, 'recibida', 'confirmada', $userId)) {
                throw new AppException(
                    'RECEPTION_NOT_PENDING',
                    'La recepción no está pendiente de confirmación.',
                    409
                );
            }
            $this->cerrarOrdenSiCompleta($reception->orderId);
            $this->repo->insertarOutbox($id, [
                'recepcion_id' => $id,
                'order_id' => $reception->orderId,
                'store_id' => $storeId,
                'confirmado_por' => $userId,
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
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                throw new AppException('DUPLICATE_LOT', 'El lote ya existe.', 409);
            }
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        $confirmada = $this->repo->buscarPorId($id);
        if ($confirmada === null) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return ['reception' => $confirmada, 'items' => $this->items($id)];
    }

    /**
     * @return array{reception:Recepcion,items:list<array<string,mixed>>}
     * @throws AppException NOT_FOUND (404) | RECEPTION_NOT_PENDING (409) | DATABASE_ERROR (500)
     */
    public function rechazar(string $rawId, int $userId): array
    {
        $id = $this->idEntero($rawId, 'id');
        try {
            $reception = $this->repo->buscarPorId($id);
            if ($reception === null) {
                throw new AppException('NOT_FOUND', 'Recepción no encontrada.', 404);
            }
            if ($reception->estado !== 'recibida') {
                throw new AppException(
                    'RECEPTION_NOT_PENDING',
                    'Solo las recepciones en estado recibida pueden rechazarse.',
                    409
                );
            }
            if (!$this->repo->marcar($id, 'recibida', 'rechazada', null)) {
                throw new AppException(
                    'RECEPTION_NOT_PENDING',
                    'Solo las recepciones en estado recibida pueden rechazarse.',
                    409
                );
            }
        } catch (AppException $e) {
            throw $e;
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        $rechazada = $this->repo->buscarPorId($id);
        if ($rechazada === null) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return ['reception' => $rechazada, 'items' => $this->items($id)];
    }

    /**
     * RN-07: si todo lo pedido quedó recibido, la orden pasa a 'recibida'
     * (guard condicionado: nada se pierde si otra confirmación llega antes).
     */
    private function cerrarOrdenSiCompleta(int $orderId): void
    {
        foreach ($this->ordenRepo->items($orderId) as $item) {
            if ($item['cantidad_recibida'] < $item['cantidad_pedida']) {
                return;
            }
        }
        $this->ordenRepo->cambiarEstado($orderId, 'emitida', 'recibida');
    }

    /** @return list<array<string,mixed>> */
    public function items(int $receptionId): array
    {
        try {
            return $this->repo->items($receptionId);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
    }

    private function idEntero(string $raw, string $param): int
    {
        $id = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new AppException('VALIDATION_ERROR', "Parámetro '{$param}' inválido.", 400);
        }
        return $id;
    }

    /** @param array<string,mixed> $query */
    private function entero(array $query, string $key, int $def, int $min, int $max): int
    {
        if (!array_key_exists($key, $query)) {
            return $def;
        }
        $raw = $query[$key];
        if (!is_string($raw) || preg_match('/^\d+$/', $raw) !== 1) {
            throw new AppException('VALIDATION_ERROR', "Parámetro '{$key}' inválido.", 400);
        }
        $value = (int) $raw;
        if ($value < $min || $value > $max) {
            throw new AppException('VALIDATION_ERROR', "Parámetro '{$key}' fuera de rango.", 400);
        }
        return $value;
    }

    /** @param array<string,mixed> $query */
    private function texto(array $query, string $key): ?string
    {
        if (!array_key_exists($key, $query)) {
            return null;
        }
        $raw = $query[$key];
        if (!is_string($raw) || mb_strlen($raw) > 150) {
            throw new AppException('VALIDATION_ERROR', "Parámetro '{$key}' inválido.", 400);
        }
        $trim = trim($raw);
        return $trim === '' ? null : $trim;
    }
}
