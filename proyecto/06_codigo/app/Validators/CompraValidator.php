<?php
declare(strict_types=1);

namespace App\Validators;

use App\Core\AppException;

/**
 * Validación de bodies de compras: purchase_orders (create/update) y
 * purchase_receptions (create) + confirmación. RNF-025: todo se valida
 * en servidor; nada se confía del cliente.
 */
final class CompraValidator
{
    /**
     * @param array<string,mixed> $input body PurchaseOrderCreate/PurchaseOrderUpdate
     * @return array{numero:string|null,supplier_id:int,items:list<array{product_id:int,cantidad_pedida:int}>}
     * @throws AppException VALIDATION_ERROR (400)
     */
    public function validarOrden(array $input): array
    {
        return [
            'numero' => $this->numero($input),
            'supplier_id' => $this->enteroObligatorio($input, 'supplier_id'),
            'items' => $this->itemsOrden($input),
        ];
    }

    /**
     * @param array<string,mixed> $input body PurchaseReceptionCreate
     * @return array{idempotency_key:string|null,items:list<array{product_id:int,numero_lote:string,fecha_vencimiento:string,cantidad:int}>}
     * @throws AppException VALIDATION_ERROR (400)
     */
    public function validarRecepcion(array $input): array
    {
        return [
            'idempotency_key' => $this->idempotencyKey($input),
            'items' => $this->itemsRecepcion($input),
        ];
    }

    /**
     * @param array<string,mixed> $input body ReceptionConfirm
     * @return array{store_id:int}
     * @throws AppException VALIDATION_ERROR (400)
     */
    public function validarConfirmacion(array $input): array
    {
        return ['store_id' => $this->enteroObligatorio($input, 'store_id')];
    }

    /** Número opcional: ausente/vacío => null (lo genera el service). */
    /** @param array<string,mixed> $input */
    private function numero(array $input): ?string
    {
        if (!array_key_exists('numero', $input) || $input['numero'] === null) {
            return null;
        }
        $numero = $input['numero'];
        if (!is_string($numero)) {
            throw new AppException('VALIDATION_ERROR', "El campo 'numero' debe ser texto.", 400);
        }
        $numero = trim($numero);
        if ($numero === '') {
            return null;
        }
        if (mb_strlen($numero) > 50) {
            throw new AppException(
                'VALIDATION_ERROR',
                "El campo 'numero' debe tener hasta 50 caracteres.",
                400
            );
        }
        return $numero;
    }

    /** @param array<string,mixed> $input */
    private function idempotencyKey(array $input): ?string
    {
        if (!array_key_exists('idempotency_key', $input) || $input['idempotency_key'] === null) {
            return null;
        }
        $key = $input['idempotency_key'];
        if (!is_string($key)) {
            throw new AppException(
                'VALIDATION_ERROR',
                "El campo 'idempotency_key' debe ser texto.",
                400
            );
        }
        $key = trim($key);
        if ($key === '') {
            return null;
        }
        if (mb_strlen($key) > 255) {
            throw new AppException(
                'VALIDATION_ERROR',
                "El campo 'idempotency_key' debe tener hasta 255 caracteres.",
                400
            );
        }
        return $key;
    }

    /** @param array<string,mixed> $input */
    private function enteroObligatorio(array $input, string $key): int
    {
        if (!array_key_exists($key, $input)) {
            throw new AppException('VALIDATION_ERROR', "El campo '{$key}' es obligatorio.", 400);
        }
        $value = $input[$key];
        if (!is_int($value) || $value < 1) {
            throw new AppException(
                'VALIDATION_ERROR',
                "El campo '{$key}' debe ser un entero mayor que 0.",
                400
            );
        }
        return $value;
    }

    /**
     * @param array<string,mixed> $input
     * @return list<array{product_id:int,cantidad_pedida:int}>
     */
    private function itemsOrden(array $input): array
    {
        if (!array_key_exists('items', $input) || !is_array($input['items'])) {
            throw new AppException('VALIDATION_ERROR', "El campo 'items' es obligatorio.", 400);
        }
        if ($input['items'] === []) {
            throw new AppException('VALIDATION_ERROR', "El campo 'items' no puede estar vacío.", 400);
        }
        $items = [];
        $vistos = [];
        foreach ($input['items'] as $raw) {
            if (!is_array($raw)) {
                throw new AppException('VALIDATION_ERROR', "Cada item debe ser un objeto.", 400);
            }
            $productId = $this->enteroObligatorio($raw, 'product_id');
            $cantidad = $this->enteroObligatorio($raw, 'cantidad_pedida');
            if (isset($vistos[$productId])) {
                throw new AppException(
                    'VALIDATION_ERROR',
                    "El producto {$productId} está repetido en 'items'.",
                    400
                );
            }
            $vistos[$productId] = true;
            $items[] = ['product_id' => $productId, 'cantidad_pedida' => $cantidad];
        }
        return $items;
    }

    /**
     * @param array<string,mixed> $input
     * @return list<array{product_id:int,numero_lote:string,fecha_vencimiento:string,cantidad:int}>
     */
    private function itemsRecepcion(array $input): array
    {
        if (!array_key_exists('items', $input) || !is_array($input['items'])) {
            throw new AppException('VALIDATION_ERROR', "El campo 'items' es obligatorio.", 400);
        }
        if ($input['items'] === []) {
            throw new AppException('VALIDATION_ERROR', "El campo 'items' no puede estar vacío.", 400);
        }
        $items = [];
        $vistos = [];
        foreach ($input['items'] as $raw) {
            if (!is_array($raw)) {
                throw new AppException('VALIDATION_ERROR', "Cada item debe ser un objeto.", 400);
            }
            $productId = $this->enteroObligatorio($raw, 'product_id');
            if (isset($vistos[$productId])) {
                throw new AppException(
                    'VALIDATION_ERROR',
                    "El producto {$productId} está repetido en 'items'.",
                    400
                );
            }
            $vistos[$productId] = true;
            $items[] = [
                'product_id' => $productId,
                'numero_lote' => $this->numeroLote($raw),
                'fecha_vencimiento' => $this->fecha($raw),
                'cantidad' => $this->enteroObligatorio($raw, 'cantidad'),
            ];
        }
        return $items;
    }

    /** @param array<string,mixed> $item */
    private function numeroLote(array $item): string
    {
        if (!array_key_exists('numero_lote', $item) || !is_string($item['numero_lote'])) {
            throw new AppException(
                'VALIDATION_ERROR',
                "El campo 'numero_lote' es obligatorio y debe ser texto.",
                400
            );
        }
        $lote = trim($item['numero_lote']);
        if ($lote === '' || mb_strlen($lote) > 50) {
            throw new AppException(
                'VALIDATION_ERROR',
                "El campo 'numero_lote' debe tener entre 1 y 50 caracteres.",
                400
            );
        }
        return $lote;
    }

    /** @param array<string,mixed> $item */
    private function fecha(array $item): string
    {
        if (!array_key_exists('fecha_vencimiento', $item) || !is_string($item['fecha_vencimiento'])) {
            throw new AppException(
                'VALIDATION_ERROR',
                "El campo 'fecha_vencimiento' es obligatorio (YYYY-MM-DD).",
                400
            );
        }
        $fecha = trim($item['fecha_vencimiento']);
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if ($dt === false || $dt->format('Y-m-d') !== $fecha) {
            throw new AppException(
                'VALIDATION_ERROR',
                "El campo 'fecha_vencimiento' debe ser una fecha válida (YYYY-MM-DD).",
                400
            );
        }
        return $fecha;
    }
}
