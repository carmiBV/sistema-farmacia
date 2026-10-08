<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class RoleRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    public function findIdPorNombre(string $nombre): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM auth_roles WHERE nombre = ?');
        $stmt->execute([$nombre]);
        $row = $stmt->fetch();
        return $row === false ? null : (int)$row['id'];
    }

    /**
     * @param list<int> $ids
     * @return list<int> ids realmente existentes
     */
    public function filtrarExistentes(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT id FROM auth_roles WHERE id IN ({$ph})");
        $stmt->execute($ids);
        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }

    /**@return list<array{id:int,nombre:string,descripcion:?string,permisos:list<string>}> */
    public function listar(): array
    {
        $roles = $this->pdo->query('SELECT id, nombre, descripcion FROM auth_roles ORDER BY nombre')->fetchAll();
        $perms = $this->pdo->query(
            'SELECT rp.role_id, p.clave FROM auth_role_permissions rp
               JOIN auth_permissions p ON p.id = rp.permission_id
              ORDER BY p.clave'
        )->fetchAll();

        $porRol = [];
        foreach ($perms as $p) {
            $porRol[(int)$p['role_id']][] = (string)$p['clave'];
        }

        $result = [];
        foreach ($roles as $r) {
            $result[] = [
                'id' => (int)$r['id'],
                'nombre' => (string)$r['nombre'],
                'descripcion' => $r['descripcion'] !== null ? (string)$r['descripcion'] : null,
                'permisos' => $porRol[(int)$r['id']] ?? [],
            ];
        }
        return $result;
    }

    public function crear(string $nombre, ?string $descripcion): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO auth_roles (nombre, descripcion) VALUES (?, ?)');
        $stmt->execute([$nombre, $descripcion]);
        return (int)$this->pdo->lastInsertId();
    }

    /** Crea la permission si no existe y devuelve su id (upsert). */
    public function upsertPermiso(string $clave, ?string $descripcion = null): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM auth_permissions WHERE clave = ?');
        $stmt->execute([$clave]);
        $row = $stmt->fetch();
        if ($row !== false) {
            return (int)$row['id'];
        }
        $ins = $this->pdo->prepare('INSERT INTO auth_permissions (clave, descripcion) VALUES (?, ?)');
        $ins->execute([$clave, $descripcion]);
        return (int)$this->pdo->lastInsertId();
    }

    public function vincularPermiso(int $roleId, int $permissionId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO auth_role_permissions (role_id, permission_id) VALUES (?, ?)'
        );
        $stmt->execute([$roleId, $permissionId]);
    }
}
