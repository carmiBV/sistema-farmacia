<?php
declare(strict_types=1);

namespace App\Validators;

use App\Http\AppException;

/**
 * Validación de bodies de ops_stores / ops_registers / system_config.
 * Un solo validator compartido: los tres recursos son operacionales y
 * su validación es trivial (patrón CategoriaValidator).
 */
final class OpsValidator
{
    /**
     * @param array<string,mixed> $input body StoreCreate/StoreUpdate
     * @return array{codigo:string,nombre:string}
     * @throws AppException VALIDATION_ERROR (400)
     */
    public function validarSucursal(array $input): array
    {
        return [
            'codigo' => $this->codigo($input),
            'nombre' => $this->nombre($input),
        ];
    }

    /**
     * @param array<string,mixed> $input body RegisterCreate/RegisterUpdate
     * @return array{store_id:int,codigo:string}
     * @throws AppException VALIDATION_ERROR (400)
     */
    public function validarCaja(array $input): array
    {
        if (!array_key_exists('store_id', $input)) {
            throw new AppException('VALIDATION_ERROR', "El campo 'store_id' es obligatorio.", 400);
        }
        $storeId = $input['store_id'];
        if (!is_int($storeId) || $storeId < 1) {
            throw new AppException(
                'VALIDATION_ERROR',
                "El campo 'store_id' debe ser un entero mayor o igual a 1.",
                400
            );
        }
        return ['store_id' => $storeId, 'codigo' => $this->codigo($input)];
    }

    /**
     * @param array<string,mixed> $input body ConfigUpdate
     * @return array{config_value:string,value_type:string,store_id:int|null}
     * @throws AppException VALIDATION_ERROR (400)
     */
    public function validarConfig(array $input): array
    {
        if (!array_key_exists('config_value', $input)) {
            throw new AppException('VALIDATION_ERROR', "El campo 'config_value' es obligatorio.", 400);
        }
        $value = $input['config_value'];
        if (!is_string($value) || mb_strlen($value) > 4000) {
            throw new AppException(
                'VALIDATION_ERROR',
                "El campo 'config_value' debe ser texto de hasta 4000 caracteres.",
                400
            );
        }

        if (!array_key_exists('value_type', $input)) {
            throw new AppException('VALIDATION_ERROR', "El campo 'value_type' es obligatorio.", 400);
        }
        $type = $input['value_type'];
        if (!is_string($type) || !in_array($type, ['texto', 'numero', 'booleano', 'json'], true)) {
            throw new AppException(
                'VALIDATION_ERROR',
                "El campo 'value_type' debe ser texto|numero|booleano|json.",
                400
            );
        }

        // Coherencia valor ↔ tipo: falla en validación (400), no en la base.
        if ($type === 'numero' && !is_numeric(trim($value))) {
            throw new AppException('VALIDATION_ERROR', "value_type=numero requiere un valor numérico.", 400);
        }
        if ($type === 'booleano' && !in_array(trim($value), ['true', 'false'], true)) {
            throw new AppException('VALIDATION_ERROR', "value_type=booleano requiere 'true' o 'false'.", 400);
        }
        if ($type === 'json' && json_decode($value, true) === null && trim($value) !== 'null') {
            throw new AppException('VALIDATION_ERROR', 'value_type=json requiere JSON válido.', 400);
        }

        $storeId = null;
        if (array_key_exists('store_id', $input) && $input['store_id'] !== null) {
            $sid = $input['store_id'];
            if (!is_int($sid) || $sid < 1) {
                throw new AppException(
                    'VALIDATION_ERROR',
                    "El campo 'store_id' debe ser un entero mayor o igual a 1.",
                    400
                );
            }
            $storeId = $sid;
        }

        return ['config_value' => $value, 'value_type' => $type, 'store_id' => $storeId];
    }

    /** @param array<string,mixed> $input */
    private function codigo(array $input): string
    {
        if (!array_key_exists('codigo', $input)) {
            throw new AppException('VALIDATION_ERROR', "El campo 'codigo' es obligatorio.", 400);
        }
        $codigo = $input['codigo'];
        if (!is_string($codigo)) {
            throw new AppException('VALIDATION_ERROR', "El campo 'codigo' debe ser texto.", 400);
        }
        $codigo = trim($codigo);
        if ($codigo === '' || strlen($codigo) > 20 || preg_match('/^[A-Za-z0-9_-]+$/', $codigo) !== 1) {
            throw new AppException(
                'VALIDATION_ERROR',
                "El campo 'codigo' debe tener entre 1 y 20 caracteres (letras, dígitos, '-' o '_').",
                400
            );
        }
        return $codigo;
    }

    /** @param array<string,mixed> $input */
    private function nombre(array $input): string
    {
        if (!array_key_exists('nombre', $input)) {
            throw new AppException('VALIDATION_ERROR', "El campo 'nombre' es obligatorio.", 400);
        }
        $nombre = $input['nombre'];
        if (!is_string($nombre)) {
            throw new AppException('VALIDATION_ERROR', "El campo 'nombre' debe ser texto.", 400);
        }
        $nombre = trim($nombre);
        if ($nombre === '' || mb_strlen($nombre) > 150) {
            throw new AppException('VALIDATION_ERROR', "El campo 'nombre' debe tener entre 1 y 150 caracteres.", 400);
        }
        return $nombre;
    }
}
