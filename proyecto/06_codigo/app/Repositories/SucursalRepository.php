<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Sucursal;
use App\Core\Database;
use PDO;

final class SucursalRepository
{
    /**
     * @param string|null $q contiene, case-insensitive (collation ai_ci), o null
     * @return list<Sucursal>
     */
    public function listar(?string $q, int $limit, int $offset): array
    {
        [$where, $params] = $this->filtros($q);
        $stmt = Database::pdo()->prepare(
            "SELECT id, codigo, nombre, estado, created_at FROM ops_stores{$where}"
            . ' ORDER BY id ASC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            static fn (array $row): Sucursal => Sucursal::fromRow($row),
            $stmt->fetchAll()
        );
    }

    public function contar(?string $q): int
    {
        [$where, $params] = $this->filtros($q);
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM ops_stores{$where}");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function buscarPorId(int $id): ?Sucursal
    {
        $stmt = Database::pdo()->prepare(
            "SELECT id, codigo, nombre, estado, created_at FROM ops_stores WHERE id = :id AND estado = 'activa'"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return Sucursal::fromRow($row);
    }

    public function existeId(int $id): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM ops_stores WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    /** Unicidad (codigo): el índice único uq_ops_stores_codigo no filtra estado. */
    public function existeCodigo(string $codigo): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM ops_stores WHERE codigo = :codigo');
        $stmt->bindValue(':codigo', $codigo);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    /** Unicidad (codigo) excluyendo el propio registro (para UPDATE). */
    public function existeCodigoExcluyendo(string $codigo, int $excludeId): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM ops_stores WHERE codigo = :codigo AND id <> :id');
        $stmt->bindValue(':codigo', $codigo);
        $stmt->bindValue(':id', $excludeId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    public function insertar(string $codigo, string $nombre): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO ops_stores (codigo, nombre) VALUES (:codigo, :nombre)'
        );
        $stmt->bindValue(':codigo', $codigo);
        $stmt->bindValue(':nombre', $nombre);
        $stmt->execute();
        return (int) Database::pdo()->lastInsertId();
    }

    public function actualizar(int $id, string $codigo, string $nombre): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ops_stores SET codigo = :codigo, nombre = :nombre WHERE id = :id'
        );
        $stmt->bindValue(':codigo', $codigo);
        $stmt->bindValue(':nombre', $nombre);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function inactivar(int $id): void
    {
        $stmt = Database::pdo()->prepare("UPDATE ops_stores SET estado = 'inactiva' WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * @return array{0:string,1:array<string,string>}
     */
    private function filtros(?string $q): array
    {
        $conds = ["estado = 'activa'"];
        $params = [];
        if ($q !== null) {
            $conds[] = 'nombre LIKE :q';
            $params[':q'] = '%' . $q . '%';
        }
        return [' WHERE ' . implode(' AND ', $conds), $params];
    }
}
