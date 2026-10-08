<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Models\Usuario;
use App\Repositories\RoleRepository;
use App\Repositories\UsuarioRepository;
use App\Validators\AuthValidator;
use PDOException;

final class UsuarioService
{
    public function __construct(
        private readonly AuthValidator $validator = new AuthValidator(),
        private readonly UsuarioRepository $usuarios = new UsuarioRepository(),
        private readonly RoleRepository $roles = new RoleRepository(),
    ) {
    }

    /**
     * @return list<array{id:int,usuario:string,estado:string,roles:list<string>}>
     */
    public function listarUsuarios(): array
    {
        return $this->usuarios->listar();
    }

    /**
     * @param array<string,mixed> $input
     * @return array{id:int,usuario:string,estado:string,roles:list<string>}
     * @throws AppException VALIDATION_ERROR (400), DUPLICATE_NAME (409)
     */
    public function crearUsuario(array $input): array
    {
        $datos = $this->validator->validarCreacionUsuario($input);

        if ($this->usuarios->findByUsuario($datos['usuario']) !== null) {
            throw new AppException('DUPLICATE_NAME', "Ya existe el usuario '{$datos['usuario']}'.", 409);
        }
        $roleIds = $this->resolverRoleIds($datos['roles']);

        $hash = password_hash($datos['password'], $this->algoritmo());
        if ($hash === false) {
            throw new AppException('INTERNAL_ERROR', 'No se pudo generar la contraseña.', 500);
        }

        try {
            $usuario = $this->usuarios->crear($datos['usuario'], $hash);
            if ($roleIds !== []) {
                $this->usuarios->asignarRoles($usuario->id, $roleIds);
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '1062') {
                throw new AppException('DUPLICATE_NAME', "Ya existe el usuario '{$datos['usuario']}'.", 409);
            }
            throw new AppException('DATABASE_ERROR', 'Error de base de datos.', 500);
        }

        return $this->detalle($usuario->id);
    }

    /**
     * @param array<string,mixed> $input
     * @return array{id:int,usuario:string,estado:string,roles:list<string>}
     * @throws AppException NOT_FOUND (404), VALIDATION_ERROR (400)
     */
    public function asignarRoles(int $userId, array $input): array
    {
        $roleIds = $this->validator->validarRolesAsignados($input);
        if ($this->usuarios->findById($userId) === null) {
            throw new AppException('NOT_FOUND', 'Usuario no encontrado.', 404);
        }
        $existentes = $this->roles->filtrarExistentes($roleIds);
        if (count($existentes) !== count(array_unique($roleIds))) {
            throw new AppException('VALIDATION_ERROR', 'Alguno de los roles indicados no existe.', 400);
        }

        $this->usuarios->asignarRoles($userId, $existentes);

        return $this->detalle($userId);
    }

    /**@return list<array{id:int,nombre:string,descripcion:?string,permisos:list<string>}> */
    public function listarRoles(): array
    {
        return $this->roles->listar();
    }

    /**
     * @param array<string,mixed> $input
     * @return array{id:int,nombre:string,descripcion:?string,permisos:list<string>}
     * @throws AppException VALIDATION_ERROR (400), DUPLICATE_NAME (409)
     */
    public function crearRol(array $input): array
    {
        $datos = $this->validator->validCreacionRol($input);

        try {
            $roleId = $this->roles->crear($datos['nombre'], $datos['descripcion']);
        } catch (PDOException $e) {
            if ($e->getCode() === '1062') {
                throw new AppException('DUPLICATE_NAME', "Ya existe el rol '{$datos['nombre']}'.", 409);
            }
            throw new AppException('DATABASE_ERROR', 'Error de base de datos.', 500);
        }

        foreach ($datos['permisos'] as $clave) {
            $permId = $this->roles->upsertPermiso($clave);
            $this->roles->vincularPermiso($roleId, $permId);
        }

        return $this->detalleRol($roleId);
    }

    /**
     * @param list<string> $nombres
     * @return list<int>
     * @throws AppException VALIDATION_ERROR (400)
     */
    private function resolverRoleIds(array $nombres): array
    {
        $ids = [];
        foreach ($nombres as $nombre) {
            $id = $this->roles->findIdPorNombre($nombre);
            if ($id === null) {
                throw new AppException('VALIDATION_ERROR', "El rol '{$nombre}' no existe.", 400);
            }
            $ids[] = $id;
        }
        return $ids;
    }

    /**@return array{id:int,usuario:string,estado:string,roles:list<string>} */
    private function detalle(int $userId): array
    {
        foreach ($this->usuarios->listar() as $fila) {
            if ($fila['id'] === $userId) {
                return $fila;
            }
        }
        throw new AppException('NOT_FOUND', 'Usuario no encontrado.', 404);
    }

    /**@return array{id:int,nombre:string,descripcion:?string,permisos:list<string>} */
    private function detalleRol(int $roleId): array
    {
        foreach ($this->roles->listar() as $fila) {
            if ($fila['id'] === $roleId) {
                return $fila;
            }
        }
        throw new AppException('NOT_FOUND', 'Rol no encontrado.', 404);
    }

    private function algoritmo(): string
    {
        return in_array('argon2id', password_algos(), true) ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }
}
