<?php
declare(strict_types=1);

namespace App\Validators;

use App\Core\AppException;

/**
 * Validación de bodies de catalog_products y su subrecurso de categorías.
 */
final class ProductoValidator
{
    private const CONDICIONES_VENTA = ['libre', 'receta', 'controlado'];

    /**
     * @param array<string,mixed> $input body ProductCreate/ProductUpdate
     * @return array{sku:string,nombre:string,principio_activo:string,presentacion:string,concentracion:string,condicion_venta:string}
     * @throws AppException VALIDATION_ERROR (400)
     */
    public function validar(array $input): array
    {
        return [
            'sku' => $this->sku($input),
            'nombre' => $this->texto($input, 'nombre', 255),
            'principio_activo' => $this->texto($input, 'principio_activo', 255),
            'presentacion' => $this->texto($input, 'presentacion', 100),
            'concentracion' => $this->texto($input, 'concentracion', 100),
            'condicion_venta' => $this->condicionVenta($input),
        ];
    }

    /**
     * Lista de categorías: array vacío permitido, deduplicada conservando el orden.
     *
     * @param array<string,mixed> $input body con clave category_ids
     * @return list<int>
     * @throws AppException VALIDATION_ERROR (400)
     */
    public function validarCategoryIds(array $input): array
    {
        if (!array_key_exists('category_ids', $input)) {
            throw new AppException('VALIDATION_ERROR', "El campo 'category_ids' es obligatorio.", 400);
        }
        $raw = $input['category_ids'];
        if (!is_array($raw)) {
            throw new AppException('VALIDATION_ERROR', "El campo 'category_ids' debe ser una lista.", 400);
        }

        $ids = [];
        foreach ($raw as $value) {
            if (!is_int($value) || $value < 1) {
                throw new AppException(
                    'VALIDATION_ERROR',
                    "El campo 'category_ids' debe contener enteros mayores o iguales a 1.",
                    400
                );
            }
            $ids[$value] = $value; // dedupe: las claves se insertan en orden de aparición
        }
        return array_values($ids);
    }

    /** @param array<string,mixed> $input */
    private function sku(array $input): string
    {
        $sku = $this->texto($input, 'sku', 50);
        if (preg_match('/^[A-Za-z0-9._-]+$/', $sku) !== 1) {
            throw new AppException(
                'VALIDATION_ERROR',
                "El campo 'sku' solo admite letras, dígitos, '.', '_' o '-'.",
                400
            );
        }
        return $sku;
    }

    /** @param array<string,mixed> $input */
    private function condicionVenta(array $input): string
    {
        if (!array_key_exists('condicion_venta', $input)) {
            throw new AppException('VALIDATION_ERROR', "El campo 'condicion_venta' es obligatorio.", 400);
        }
        $valor = $input['condicion_venta'];
        if (!is_string($valor) || !in_array($valor, self::CONDICIONES_VENTA, true)) {
            throw new AppException(
                'VALIDATION_ERROR',
                "El campo 'condicion_venta' debe ser libre|receta|controlado.",
                400
            );
        }
        return $valor;
    }

    /** @param array<string,mixed> $input */
    private function texto(array $input, string $key, int $max): string
    {
        if (!array_key_exists($key, $input)) {
            throw new AppException('VALIDATION_ERROR', "El campo '{$key}' es obligatorio.", 400);
        }
        $valor = $input[$key];
        if (!is_string($valor)) {
            throw new AppException('VALIDATION_ERROR', "El campo '{$key}' debe ser texto.", 400);
        }
        $valor = trim($valor);
        if ($valor === '' || mb_strlen($valor) > $max) {
            throw new AppException(
                'VALIDATION_ERROR',
                "El campo '{$key}' debe tener entre 1 y {$max} caracteres.",
                400
            );
        }
        return $valor;
    }
}
