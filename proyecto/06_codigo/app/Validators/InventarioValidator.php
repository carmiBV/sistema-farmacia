<?php
declare(strict_types=1);

namespace App\Validators;

use App\Core\AppException;

/**
 * Validación de entradas de inventario (CP-BACK-06).
 * RF-044 (alertas), RF-046/RF-047 (incidentes y ajustes), RN-02/RN-03/RN-08.
 */
final class InventarioValidator
{
    /** @var list<string> */
    public const ESTADOS_LOTE = ['cuarentena', 'liberado', 'retirado'];

    /** @var list<string> */
    public const ESTADOS_TRANSFERENCIA = ['solicitada', 'despachada', 'recibida', 'cerrada', 'rechazada'];

    /** @var list<string> */
    public const TIPOS_ALERTA = ['stock_minimo', 'vencimiento'];

    /** @var list<string> */
    public const ESTADOS_ALERTA = ['abierta', 'resuelta'];

    /** @var list<string> */
    public const ESTADOS_INCIDENTE = ['abierto', 'ajustado', 'descartado'];

    /** @var list<string> */
    public const ESTADOS_RESERVA = ['pending', 'confirmed', 'expired', 'cancelled'];

    /** @var list<string> */
    public const TIPOS_MOVIMIENTO = [
        'entrada',
        'salida',
        'ajuste_baja',
        'ajuste_incremento',
        'devolucion',
        'transferencia_salida',
        'transferencia_entrada',
        'compensatorio',
    ];

    /** @return array{page:int,limit:int} */
    public function paginacion(array $query): array
    {
        return [
            'page' => $this->entero($query, 'page', 1, 1_000_000) ?? 1,
            'limit' => $this->entero($query, 'limit', 1, 100) ?? 25,
        ];
    }

    /** Entero opcional: null si ausente/vacío, VALIDATION_ERROR si inválido. */
    public function entero(array $input, string $key, int $min, int $max): ?int
    {
        if (!isset($input[$key]) || $input[$key] === '' || $input[$key] === null) {
            return null;
        }
        return $this->enteroObligatorio($input, $key, $min, $max);
    }

    public function enteroObligatorio(array $input, string $key, int $min, int $max): int
    {
        if (!isset($input[$key]) || $input[$key] === '' || $input[$key] === null) {
            throw new AppException('VALIDATION_ERROR', "Campo '{$key}' obligatorio.", 400);
        }
        $v = $input[$key];
        if (is_string($v) && preg_match('/^-?\d+$/', $v) === 1) {
            $v = (int) $v;
        }
        if (!is_int($v)) {
            throw new AppException('VALIDATION_ERROR', "Campo '{$key}' debe ser un entero.", 400);
        }
        if ($v < $min || $v > $max) {
            throw new AppException('VALIDATION_ERROR', "Campo '{$key}' fuera de rango ({$min}..{$max}).", 400);
        }
        return $v;
    }

    public function textoObligatorio(array $input, string $key, int $max): string
    {
        if (!isset($input[$key]) || !is_string($input[$key]) || trim($input[$key]) === '') {
            throw new AppException('VALIDATION_ERROR', "Campo '{$key}' obligatorio.", 400);
        }
        $v = trim($input[$key]);
        if (mb_strlen($v) > $max) {
            throw new AppException('VALIDATION_ERROR', "Campo '{$key}' excede {$max} caracteres.", 400);
        }
        return $v;
    }

    /** @param list<string> $allowed */
    public function enumParam(array $input, string $key, array $allowed): ?string
    {
        if (!isset($input[$key]) || $input[$key] === '') {
            return null;
        }
        $v = (string) $input[$key];
        if (!in_array($v, $allowed, true)) {
            throw new AppException('VALIDATION_ERROR', "Parámetro '{$key}' inválido.", 400);
        }
        return $v;
    }

    /** POST /inventory/batches/{id}/liberar (RF-032, RN-03). */
    public function validarLiberacion(array $input): string
    {
        return $this->textoObligatorio($input, 'motivo', 500);
    }

    /**
     * POST /inventory/transfers (RN-08).
     * Cantidad solicitada se persiste en cantidad_despachada (el DDL no tiene
     * columna de "solicitada"; supuesto CP-06).
     *
     * @return array{store_origen_id:int,store_destino_id:int,items:list<array{lot_id:int,cantidad:int}>,idempotency_key:?string}
     */
    public function validarTransferencia(array $input): array
    {
        $origen = $this->enteroObligatorio($input, 'store_origen_id', 1, PHP_INT_MAX);
        $destino = $this->enteroObligatorio($input, 'store_destino_id', 1, PHP_INT_MAX);
        if ($origen === $destino) {
            throw new AppException(
                'TRANSFER_SAME_STORE',
                'La sucursal de origen y destino deben ser distintas.',
                400
            );
        }
        if (!isset($input['items']) || !is_array($input['items']) || $input['items'] === []) {
            throw new AppException('VALIDATION_ERROR', "Campo 'items' obligatorio y no vacío.", 400);
        }
        if (count($input['items']) > 500) {
            throw new AppException('VALIDATION_ERROR', 'Máximo 500 ítems por transferencia.', 400);
        }
        $items = [];
        $vistos = [];
        foreach ($input['items'] as $item) {
            if (!is_array($item)) {
                throw new AppException('VALIDATION_ERROR', 'Cada item debe ser un objeto.', 400);
            }
            $lotId = $this->enteroObligatorio($item, 'lot_id', 1, PHP_INT_MAX);
            $cantidad = $this->enteroObligatorio($item, 'cantidad', 1, 1_000_000_000);
            if (isset($vistos[$lotId])) {
                throw new AppException('VALIDATION_ERROR', "Lote {$lotId} duplicado en items.", 400);
            }
            $vistos[$lotId] = true;
            $items[] = ['lot_id' => $lotId, 'cantidad' => $cantidad];
        }
        return [
            'store_origen_id' => $origen,
            'store_destino_id' => $destino,
            'items' => $items,
            'idempotency_key' => $this->idempotencyKey($input),
        ];
    }

    /**
     * POST /inventory/transfers/{id}/recibir (RN-08).
     * Se listan todos los lotes con su cantidad recibida (<= despachada).
     *
     * @return array{items:list<array{lot_id:int,cantidad_recibida:int}>}
     */
    public function validarRecepcionTransfer(array $input): array
    {
        if (!isset($input['items']) || !is_array($input['items']) || $input['items'] === []) {
            throw new AppException('VALIDATION_ERROR', "Campo 'items' obligatorio y no vacío.", 400);
        }
        $items = [];
        $vistos = [];
        foreach ($input['items'] as $item) {
            if (!is_array($item)) {
                throw new AppException('VALIDATION_ERROR', 'Cada item debe ser un objeto.', 400);
            }
            $lotId = $this->enteroObligatorio($item, 'lot_id', 1, PHP_INT_MAX);
            $recibida = $this->enteroObligatorio($item, 'cantidad_recibida', 0, 1_000_000_000);
            if (isset($vistos[$lotId])) {
                throw new AppException('VALIDATION_ERROR', "Lote {$lotId} duplicado en items.", 400);
            }
            $vistos[$lotId] = true;
            $items[] = ['lot_id' => $lotId, 'cantidad_recibida' => $recibida];
        }
        return ['items' => $items];
    }

    /**
     * POST /inventory/incidents (RF-047): consumo declarado > stock registrado.
     *
     * @return array{store_id:int,lot_id:int,consumo_declarado:int}
     */
    public function validarIncidente(array $input): array
    {
        return [
            'store_id' => $this->enteroObligatorio($input, 'store_id', 1, PHP_INT_MAX),
            'lot_id' => $this->enteroObligatorio($input, 'lot_id', 1, PHP_INT_MAX),
            'consumo_declarado' => $this->enteroObligatorio($input, 'consumo_declarado', 1, 1_000_000_000),
        ];
    }

    /**
     * POST /inventory/reservations (RF-044, DB-P01: no toca inventory_stock).
     *
     * @return array{store_id:int,product_id:int,qty:int,order_id:?int,ttl_minutos:int}
     */
    public function validarReserva(array $input): array
    {
        $orderId = null;
        if (isset($input['order_id']) && $input['order_id'] !== null && $input['order_id'] !== '') {
            $orderId = $this->enteroObligatorio($input, 'order_id', 1, PHP_INT_MAX);
        }
        return [
            'store_id' => $this->enteroObligatorio($input, 'store_id', 1, PHP_INT_MAX),
            'product_id' => $this->enteroObligatorio($input, 'product_id', 1, PHP_INT_MAX),
            'qty' => $this->enteroObligatorio($input, 'qty', 1, 1_000_000_000),
            'order_id' => $orderId,
            'ttl_minutos' => $this->entero($input, 'ttl_minutos', 1, 1440) ?? 15,
        ];
    }

    /** Patrón CompraValidator::idempotencyKey (RN-09): null si ausente/vacío. */
    private function idempotencyKey(array $input): ?string
    {
        if (!array_key_exists('idempotency_key', $input) || $input['idempotency_key'] === null) {
            return null;
        }
        $key = $input['idempotency_key'];
        if (!is_string($key)) {
            throw new AppException('VALIDATION_ERROR', "El campo 'idempotency_key' debe ser texto.", 400);
        }
        $key = trim($key);
        if ($key === '') {
            return null;
        }
        if (mb_strlen($key) > 255) {
            throw new AppException('VALIDATION_ERROR', "El campo 'idempotency_key' debe tener hasta 255 caracteres.", 400);
        }
        return $key;
    }
}
