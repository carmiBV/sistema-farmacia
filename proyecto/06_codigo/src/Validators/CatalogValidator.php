<?php
declare(strict_types=1);

namespace App\Validators;

use App\Http\AppException;
use DateTimeImmutable;

/**
 * Validación de bodies de `catalog_prices` y `catalog_promotions`
 * (PriceCreate/PriceUpdate y PromotionCreate/PromotionUpdate del contrato).
 * Un solo validator para los dos recursos, como OpsValidator.
 *
 * Ninguna operación es destructiva: la baja de un precio o promoción se hace
 * cerrando la vigencia (PUT), nunca con DELETE (prohibido en todo el módulo).
 */
final class CatalogValidator
{
    /**
     * @param array<string,mixed> $input
     * @return array{product_id:int,store_id:int|null,precio:string,vigente_desde:string,vigente_hasta:string|null}
     *
     * @throws AppException VALIDATION_ERROR (400)
     */
    public function validarPrecio(array $input): array
    {
        $productId = $this->enteroObligatorio($input, 'product_id');
        $storeId = $this->enteroOpcional($input, 'store_id');
        $precio = $this->precio($input);
        $desde = $this->fecha($input, 'vigente_desde');
        $hasta = $this->fechaOpcional($input, 'vigente_hasta');

        if ($hasta !== null && $hasta < $desde) {
            throw new AppException(
                'VALIDATION_ERROR',
                "'vigente_hasta' debe ser mayor o igual a 'vigente_desde'.",
                400
            );
        }

        return [
            'product_id' => $productId,
            'store_id' => $storeId,
            'precio' => $precio,
            'vigente_desde' => $desde,
            'vigente_hasta' => $hasta,
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{product_id:int|null,category_id:int|null,descuento_pct:string,desde:string,hasta:string}
     *
     * @throws AppException VALIDATION_ERROR (400)
     */
    public function validarPromocion(array $input): array
    {
        $productId = $this->enteroOpcional($input, 'product_id');
        $categoryId = $this->enteroOpcional($input, 'category_id');

        if ($productId === null && $categoryId === null) {
            throw new AppException(
                'VALIDATION_ERROR',
                "El campo 'product_id' o 'category_id' es obligatorio.",
                400
            );
        }

        $descuento = $this->descuento($input);
        $desde = $this->fecha($input, 'desde');
        $hasta = $this->fecha($input, 'hasta');

        if ($hasta < $desde) {
            throw new AppException('VALIDATION_ERROR', "'hasta' debe ser mayor o igual a 'desde'.", 400);
        }

        return [
            'product_id' => $productId,
            'category_id' => $categoryId,
            'descuento_pct' => $descuento,
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    /** DECIMAL(12,2) >= 0, con el mensaje exacto del contrato. */
    private function precio(array $input): string
    {
        if (!array_key_exists('precio', $input)) {
            throw new AppException('VALIDATION_ERROR', "El campo 'precio' es obligatorio.", 400);
        }

        $numero = $this->numero($input['precio'], 'precio');
        if ($numero < 0) {
            throw new AppException(
                'VALIDATION_ERROR',
                "El campo 'precio' debe ser un número mayor o igual a 0.",
                400
            );
        }
        if ($numero > 9999999999.99) {
            throw new AppException(
                'VALIDATION_ERROR',
                "El campo 'precio' no puede exceder 9999999999.99.",
                400
            );
        }

        return number_format($numero, 2, '.', '');
    }

    /** DECIMAL(5,2) en (0, 100], con el mensaje exacto del contrato. */
    private function descuento(array $input): string
    {
        if (!array_key_exists('descuento_pct', $input)) {
            throw new AppException('VALIDATION_ERROR', "El campo 'descuento_pct' es obligatorio.", 400);
        }

        $numero = $this->numero($input['descuento_pct'], 'descuento_pct');
        if ($numero <= 0 || $numero > 100) {
            throw new AppException(
                'VALIDATION_ERROR',
                "'descuento_pct' debe estar en el rango (0, 100].",
                400
            );
        }

        return number_format($numero, 2, '.', '');
    }

    private function enteroObligatorio(array $input, string $key): int
    {
        if (!array_key_exists($key, $input)) {
            throw new AppException('VALIDATION_ERROR', "El campo '{$key}' es obligatorio.", 400);
        }

        $value = $input[$key];
        if (!is_int($value) || $value < 1) {
            throw new AppException(
                'VALIDATION_ERROR',
                "El campo '{$key}' debe ser un entero mayor o igual a 1.",
                400
            );
        }

        return $value;
    }

    private function enteroOpcional(array $input, string $key): ?int
    {
        if (!array_key_exists($key, $input) || $input[$key] === null) {
            return null;
        }

        return $this->enteroObligatorio($input, $key);
    }

    private function fecha(array $input, string $key): string
    {
        if (!array_key_exists($key, $input)) {
            throw new AppException('VALIDATION_ERROR', "El campo '{$key}' es obligatorio.", 400);
        }

        $raw = $input[$key];
        if (!is_string($raw) || !$this->esFechaValida($raw)) {
            throw new AppException(
                'VALIDATION_ERROR',
                "El campo '{$key}' debe ser una fecha válida en formato Y-m-d.",
                400
            );
        }

        return $raw;
    }

    private function fechaOpcional(array $input, string $key): ?string
    {
        if (!array_key_exists($key, $input) || $input[$key] === null) {
            return null;
        }

        return $this->fecha($input, $key);
    }

    /**
     * Formato estricto `Y-m-d`: rechaza desbordes de calendario
     * (p. ej. 2026-02-30) que DateTime normaliza en silencio.
     */
    private function esFechaValida(string $raw): bool
    {
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if ($fecha === false) {
            return false;
        }

        $errores = DateTimeImmutable::getLastErrors();
        if (is_array($errores) && ($errores['error_count'] > 0 || $errores['warning_count'] > 0)) {
            return false;
        }

        return $fecha->format('Y-m-d') === $raw;
    }

    /** Acepta int|float|string numérico y lo normaliza a decimal con 2 lugares. */
    private function numero(mixed $raw, string $key): float
    {
        $esNumerico = is_int($raw)
            || is_float($raw)
            || (is_string($raw) && is_numeric(trim($raw)));

        if (!$esNumerico) {
            throw new AppException(
                'VALIDATION_ERROR',
                "El campo '{$key}' debe ser numérico.",
                400
            );
        }

        return (float) $raw;
    }
}
