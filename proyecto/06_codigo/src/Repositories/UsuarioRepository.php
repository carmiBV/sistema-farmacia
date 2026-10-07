<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Usuario;
use App\Support\Database;
use PDO;

final class UsuarioRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    public function findByUsuario(string $usuario): ?Usuario
    {
        $stmt = $this->pdo->prepare('SELECT * FROM auth_users WHERE usuario = ?');
        $stmt->execute([$usuario]);
        $row = $stmt->fetch();
        return $row === false ? null : Usuario::fromRow($row);
    }

    public function findById(int $id): ?Usuario
    {
        $stmt = $this->pdo->prepare('SELECT * FROM auth_users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : Usuario::fromRow($row);
    }

    /** @return list<string> */
    public function rolesDe(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.nombre FROM auth_roles r
               JOIN auth_user_roles ur ON ur.role_id = r.id
              WHERE ur.user_id = ? ORDER BY r.nombre'
        );
        $stmt->execute([$userId]);
        return array_column($stmt->fetchAll(), 'nombre');
    }

    /** @return list<string> */
    public function permisosDe(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT p.clave FROM auth_permissions p
               JOIN auth_role_permissions rp ON rp.permission_id = p.id
               JOIN auth_user_roles ur ON ur.role_id = rp.role_id
              WHERE ur.user_id = ? ORDER BY p.clave'
        );
        $stmt->execute([$userId]);
        return array_column($stmt->fetchAll(), 'clave');
    }

    public function actualizarUltimoAcceso(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE auth_users SET ultimo_acceso_at = NOW(6) WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function actualizarPasswordHash(int $id, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare('UPDATE auth_users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$passwordHash, $id]);
    }

    /**@return array{id:int,usuario:string,estado:string,roles:list<string>} */
    public function crear(string $usuario, string $passwordHash): Usuario
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO auth_users (usuario, password_hash, estado) VALUES (?, ?, 'activo')"
        );
        $stmt->execute([$usuario, $passwordHash]);
        $id = (int)$this->pdo->lastInsertId();
        return $this->findById($id) ?? throw new \RuntimeException('Usuario no persistido.');
    }

    /**
     * Reemplaza los roles del usuario por la lista dada (tabla asociativa sin
     * historial ni estado: el borrado de filas es la única forma de desvincular;
     * en una transacción para no dejar el usuario sin roles si algo falla).
     *
     * @param list<int> $roleIds
     */
    public function asignarRoles(int $userId, array $roleIds): void
    {
        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare('DELETE FROM auth_user_roles WHERE user_id = ?');
            $del->execute([$userId]);
            $ins = $this->pdo->prepare('INSERT INTO auth_user_roles (user_id, role_id) VALUES (?, ?)');
            foreach ($roleIds as $roleId) {
                $ins->execute([$userId, $roleId]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Listado para gestión de usuarios (sin hashes).
     *
     * @return list<array{id:int,usuario:string,estado:string,roles:list<string>}>
     */
    public function listar(): array
    {
        $rows = $this->pdo->query('SELECT id, usuario, estado FROM auth_users ORDER BY id')->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id' => (int)$row['id'],
                'usuario' => (string)$row['usuario'],
                'estado' => (string)$row['estado'],
                'roles' => $this->rolesDe((int)$row['id']),
            ];
        }
        return $result;
    }
}
