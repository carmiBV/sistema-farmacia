<?php
declare(strict_types=1);

namespace App\Validators;

use App\Core\AppException;

final class CategoriaValidator
{
    /**
     * Valida el body de CategoryCreate/CategoryUpdate del contrato: nombre requerido
     * (1-150), parent_id opcional entero >= 1. La existencia del padre, la unicidad
     * (nombre, parent_id_key) y los ciclos jerárquicos los resuelve el Service.
     *
     * @param array<string,mixed> $input
     * @return array{nombre:string,parent_id:int|null}
     * @throws AppException VALIDATION_ERROR (400)
     */
    public function validar(array $input): array
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

        $parentId = null;
        if (array_key_exists('parent_id', $input) && $input['parent_id'] !== null) {
            $pid = $input['parent_id'];
            if (!is_int($pid) || $pid < 1) {
                throw new AppException('VALIDATION_ERROR', "El campo 'parent_id' debe ser un entero mayor o igual a 1.", 400);
            }
            $parentId = $pid;
        }

        return ['nombre' => $nombre, 'parent_id' => $parentId];
    }
}
