<?php
declare(strict_types=1);

namespace App\Validators;

use App\Core\AppException;

final class AuthValidator
{
    /**
     * @param array<string,mixed> $input
     * @return array{usuario:string,password:string}
     * @throws AppException VALIDATION_ERROR (400)
     */
    public function validarLogin(array $input): array
    {
        $usuario = $this->texto($input, 'usuario', 1, 100);
        $password = $this->texto($input, 'password', 1, 255);

        return ['usuario' => $usuario, 'password' => $password];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{usuario:string,password:string,roles:list<string>}
     * @throws AppException VALIDATION_ERROR (400)
     */
    public function validarCreacionUsuario(array $input): array
    {
        $usuario = $this->texto($input, 'usuario', 3, 100);
        if (preg_match('/^[a-zA-Z0-9._-]+$/', $usuario) !== 1) {
            throw new AppException(
                'VALIDATION_ERROR',
                "El campo 'usuario' solo puede contener letras, números, punto, guion y guion bajo.",
                400
            );
        }
        $password = $this->texto($input, 'password', 8, 255);
        $roles = $this->listaTextos($input, 'roles');

        return ['usuario' => $usuario, 'password' => $password, 'roles' => $roles];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{nombre:string,descripcion:?string,permisos:list<string>}
     * @throws AppException VALIDATION_ERROR (400)
     */
    public function validCreacionRol(array $input): array
    {
        $nombre = $this->texto($input, 'nombre', 1, 100);
        $descripcion = null;
        if (array_key_exists('descripcion', $input) && $input['descripcion'] !== null) {
            $descripcion = $this->texto($input, 'descripcion', 1, 255);
        }
        $permisos = $this->listaTextos($input, 'permisos');
        foreach ($permisos as $clave) {
            if (preg_match('/^[a-zA-Z0-9_.:-]+$/', $clave) !== 1) {
                throw new AppException(
                    'VALIDATION_ERROR',
                    "Clave de permiso inválida: '{$clave}'.",
                    400
                );
            }
        }

        return ['nombre' => $nombre, 'descripcion' => $descripcion, 'permisos' => $permisos];
    }

    /**
     * @param array<string,mixed> $input
     * @return list<int>
     * @throws AppException VALIDATION_ERROR (400)
     */
    public function validarRolesAsignados(array $input): array
    {
        if (!array_key_exists('roles', $input) || !is_array($input['roles'])) {
            throw new AppException('VALIDATION_ERROR', "El campo 'roles' es obligatorio (array de ids).", 400);
        }
        $ids = [];
        foreach ($input['roles'] as $raw) {
            if (!is_int($raw) || $raw < 1) {
                throw new AppException('VALIDATION_ERROR', "Cada 'role' debe ser un id entero >= 1.", 400);
            }
            $ids[] = $raw;
        }
        return $ids;
    }

    /**
     * @param array<string,mixed> $input
     * @throws AppException VALIDATION_ERROR (400)
     */
    private function texto(array $input, string $campo, int $min, int $max): string
    {
        if (!array_key_exists($campo, $input)) {
            throw new AppException('VALIDATION_ERROR', "El campo '{$campo}' es obligatorio.", 400);
        }
        $valor = $input[$campo];
        if (!is_string($valor)) {
            throw new AppException('VALIDATION_ERROR', "El campo '{$campo}' debe ser texto.", 400);
        }
        $valor = trim($valor);
        $largo = mb_strlen($valor);
        if ($largo < $min || $largo > $max) {
            throw new AppException(
                'VALIDATION_ERROR',
                "El campo '{$campo}' debe tener entre {$min} y {$max} caracteres.",
                400
            );
        }
        return $valor;
    }

    /**
     * @param array<string,mixed> $input
     * @return list<string>
     * @throws AppException VALIDATION_ERROR (400)
     */
    private function listaTextos(array $input, string $campo): array
    {
        if (!array_key_exists($campo, $input) || $input[$campo] === null) {
            return [];
        }
        if (!is_array($input[$campo])) {
            throw new AppException('VALIDATION_ERROR', "El campo '{$campo}' debe ser un array.", 400);
        }
        $result = [];
        foreach ($input[$campo] as $valor) {
            if (!is_string($valor) || trim($valor) === '' || mb_strlen(trim($valor)) > 100) {
                throw new AppException('VALIDATION_ERROR', "El campo '{$campo}' contiene un valor inválido.", 400);
            }
            $result[] = trim($valor);
        }
        return $result;
    }
}
