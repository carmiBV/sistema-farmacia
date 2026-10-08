<?php
declare(strict_types=1);

namespace App\Validators;

use App\Core\AppException;

/**
 * Validación de entradas de ventas POS (CP-BACK-08).
 * RF-050 (medios de pago), RF-060 (devoluciones), RN-01/RN-09/RN-10.
 */
final class VentaValidator
{
    /** @var list<string> */
    public const MEDIOS_PAGO = ['efectivo', 'tarjeta', 'otros'];

    /** @var list<string> */
    public const ESTADOS_ORDEN = ['pendiente', 'pagada', 'anulada', 'devuelta'];

    /** @var list<string> */
    public const CONDICIONES = ['vendible', 'no_vendible'];

    /**
     * POST /sales/orders (RF-050).
     *
     * @return array{store_id:int,register_id:int,paciente_id:?int,
     *   items:list<array{lot_id:int,cantidad:int,precio_unitario:float}>,
     *   idempotency_key:?string}
     */
    public function validarOrden(array $input): array
    {
        return [
            'store_id' => $this->enteroObligatorio($input, 'store_id'),
            'register_id' => $this->enteroObligatorio($input, 'register_id'),
            'paciente_id' => $this->entero($input, 'paciente_id'),
            'items' => $this->items($input),
            'idempotency_key' => $this->idempotencyKey($input),
        ];
    }

    /**
     * POST /sales/orders/{id}/payments (RF-050).
     *
     * @return array{medio:string,monto:float,idempotency_key:?string}
     */
    public function validarPago(array $input): array
    {
        $medio = isset($input['medio']) ? (string) $input['medio'] : '';
        if (!in_array($medio, self::MEDIOS_PAGO, true)) {
            throw new AppException('VALIDATION_ERROR', "Campo 'medio' inválido (efectivo|tarjeta|otros).", 400);
        }
        return [
            'medio' => $medio,
            'monto' => $this->dinero($input, 'monto', false),
            'idempotency_key' => $this->idempotencyKey($input),
        ];
    }

    /**
     * POST /sales/returns (RF-060, RN-10).
     *
     * @return array{order_id:int,motivo:string,
     *   items:list<array{order_item_id:int,cantidad:int,condicion:string}>}
     */
    public function validarDevolucion(array $input): array
    {
        return [
            'order_id' => $this->enteroObligatorio($input, 'order_id'),
            'motivo' => $this->textoObligatorio($input, 'motivo', 500),
            'items' => $this->itemsDevolucion($input),
        ];
    }

    // ------------------------------------------------------------- helpers

    private function entero(array $input, string $key): ?int
    {
        if (!isset($input[$key]) || $input[$key] === '' || $input[$key] === null) {
            return null;
        }
        return $this->enteroObligatorio($input, $key);
    }

    private function enteroObligatorio(array $input, string $key): int
    {
        if (!isset($input[$key]) || $input[$key] === '' || $input[$key] === null) {
            throw new AppException('VALIDATION_ERROR', "Campo '{$key}' obligatorio.", 400);
        }
        $v = $input[$key];
        if (is_string($v) && preg_match('/^-?\d+$/', $v) === 1) {
            $v = (int) $v;
        }
        if (!is_int($v) || $v < 1) {
            throw new AppException('VALIDATION_ERROR', "Campo '{$key}' debe ser un entero positivo.", 400);
        }
        return $v;
    }

    private function textoObligatorio(array $input, string $key, int $max): string
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

    /** Dinero: entero o decimal > 0 (o >= 0 si $ceroPermitido), redondeado a 2. */
    private function dinero(array $input, string $key, bool $ceroPermitido): float
    {
        if (!isset($input[$key]) || $input[$key] === '' || $input[$key] === null) {
            throw new AppException('VALIDATION_ERROR', "Campo '{$key}' obligatorio.", 400);
        }
        $v = $input[$key];
        if (is_string($v) && is_numeric($v)) {
            $v = (float) $v;
        }
        if (!is_int($v) && !is_float($v)) {
            throw new AppException('VALIDATION_ERROR', "Campo '{$key}' debe ser numérico.", 400);
        }
        $v = round((float) $v, 2);
        if ($ceroPermitido ? $v < 0.0 : $v <= 0.0) {
            throw new AppException('VALIDATION_ERROR', "Campo '{$key}' fuera de rango.", 400);
        }
        return $v;
    }

    private function idempotencyKey(array $input): ?string
    {
        if (!isset($input['idempotency_key']) || $input['idempotency_key'] === '' || $input['idempotency_key'] === null) {
            return null;
        }
        if (!is_string($input['idempotency_key']) || mb_strlen($input['idempotency_key']) > 255) {
            throw new AppException('VALIDATION_ERROR', "Campo 'idempotency_key' inválido.", 400);
        }
        return $input['idempotency_key'];
    }

    /**
     * Ítems de la orden: lote no repetido (RN-01), cantidad >= 1.
     *
     * @return list<array{lot_id:int,cantidad:int,precio_unitario:float}>
     */
    private function items(array $input): array
    {
        if (!isset($input['items']) || !is_array($input['items']) || $input['items'] === []) {
            throw new AppException('VALIDATION_ERROR', "Campo 'items' obligatorio y no vacío.", 400);
        }
        $out = [];
        $vistos = [];
        foreach ($input['items'] as $i => $it) {
            if (!is_array($it)) {
                throw new AppException('VALIDATION_ERROR', "items[{$i}] inválido.", 400);
            }
            $lotId = $this->enteroObligatorio($it, 'lot_id');
            if (isset($vistos[$lotId])) {
                throw new AppException('VALIDATION_ERROR', "Lote repetido en items[{$i}].", 400);
            }
            $vistos[$lotId] = true;
            $out[] = [
                'lot_id' => $lotId,
                'cantidad' => $this->enteroObligatorio($it, 'cantidad'),
                'precio_unitario' => $this->dinero($it, 'precio_unitario', true),
            ];
        }
        return $out;
    }

    /**
     * Ítems de la devolución: item de orden no repetido (doble submit).
     *
     * @return list<array{order_item_id:int,cantidad:int,condicion:string}>
     */
    private function itemsDevolucion(array $input): array
    {
        if (!isset($input['items']) || !is_array($input['items']) || $input['items'] === []) {
            throw new AppException('VALIDATION_ERROR', "Campo 'items' obligatorio y no vacío.", 400);
        }
        $out = [];
        $vistos = [];
        foreach ($input['items'] as $i => $it) {
            if (!is_array($it)) {
                throw new AppException('VALIDATION_ERROR', "items[{$i}] inválido.", 400);
            }
            $itemId = $this->enteroObligatorio($it, 'order_item_id');
            if (isset($vistos[$itemId])) {
                throw new AppException('VALIDATION_ERROR', "order_item_id repetido en items[{$i}].", 400);
            }
            $vistos[$itemId] = true;
            $cond = isset($it['condicion']) ? (string) $it['condicion'] : '';
            if (!in_array($cond, self::CONDICIONES, true)) {
                throw new AppException('VALIDATION_ERROR', "Campo 'condicion' inválido (vendible|no_vendible).", 400);
            }
            $out[] = [
                'order_item_id' => $itemId,
                'cantidad' => $this->enteroObligatorio($it, 'cantidad'),
                'condicion' => $cond,
            ];
        }
        return $out;
    }
}
