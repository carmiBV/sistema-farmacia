<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Categoria;
use App\Support\Database;
use PDO;

final class CategoriaRepository
{
    /**
     * @param string|null $q        contiene, case-insensitive (collation ai_ci), o null
     * @param int|null    $parentId null = sin filtro · 0 = raíz (parent_id IS NULL) · n = hijo
     * @return list<Categoria>
     */
    public function listar(?string $q, ?int $parentId, int $limit, int $offset): array
    {
        [$where, $params] = $this->filtros($q, $parentId);
        $stmt = Database::pdo()->prepare(
            "SELECT id, nombre, parent_id FROM catalog_categories{$where}"
            . ' ORDER BY id ASC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            static fn (array $row): Categoria => Categoria::fromRow($row),
            $stmt->fetchAll()
        );
    }

    public function contar(?string $q, ?int $parentId): int
    {
        [$where, $params] = $this->filtros($q, $parentId);
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM catalog_categories{$where}");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function buscarPorId(int $id): ?Categoria
    {
        $stmt = Database::pdo()->prepare('SELECT id, nombre, parent_id FROM catalog_categories WHERE id = :id AND estado = \'activo\'');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return Categoria::fromRow($row);
    }

    public function padreExiste(int $id): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM catalog_categories WHERE id = :id AND estado = \'activo\'');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    /** Unicidad (nombre, parent_id_key): IFNULL(parent_id, 0). */
    public function existeNombre(string $nombre, ?int $parentId): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT 1 FROM catalog_categories WHERE nombre = :nombre AND IFNULL(parent_id, 0) = :key'
        );
        $stmt->bindValue(':nombre', $nombre);
        $stmt->bindValue(':key', $parentId ?? 0, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    public function insertar(string $nombre, ?int $parentId): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO catalog_categories (nombre, parent_id) VALUES (:nombre, :parent_id)'
        );
        $stmt->bindValue(':nombre', $nombre);
        $stmt->bindValue(':parent_id', $parentId, $parentId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->execute();
        return (int) Database::pdo()->lastInsertId();
    }

    /** Unicidad por rama excluyendo el propio registro (para UPDATE). */
    public function existeNombreExcluyendo(string $nombre, ?int $parentId, int $excludeId): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT 1 FROM catalog_categories
             WHERE nombre = :nombre AND IFNULL(parent_id, 0) = :key AND id <> :id'
        );
        $stmt->bindValue(':nombre', $nombre);
        $stmt->bindValue(':key', $parentId ?? 0, PDO::PARAM_INT);
        $stmt->bindValue(':id', $excludeId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    /** Sube por la cadena de padres desde $startId hasta encontrar $ancestorId. */
    public function esDescendiente(int $startId, int $ancestorId): bool
    {
        $stmt = Database::pdo()->prepare('SELECT parent_id FROM catalog_categories WHERE id = :id');
        $current = $startId;
        for ($guard = 0; $guard < 64; $guard++) {
            $stmt->bindValue(':id', $current, PDO::PARAM_INT);
            $stmt->execute();
            $parentId = $stmt->fetchColumn();
            if ($parentId === false) {
                return false;
            }
            if ((int) $parentId === $ancestorId) {
                return true;
            }
            $current = (int) $parentId;
        }
        return false; // Datos corruptos con ciclo preexistente: tope de profundidad.
    }

    public function actualizar(int $id, string $nombre, ?int $parentId): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE catalog_categories SET nombre = :nombre, parent_id = :parent_id WHERE id = :id'
        );
        $stmt->bindValue(':nombre', $nombre);
        $stmt->bindValue(':parent_id', $parentId, $parentId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function inactivar(int $id): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE catalog_categories SET estado = \'inactivo\' WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Los checks de unicidad NO filtran estado: deben coincidir con el índice
     * único (nombre, parent_id_key), que incluye filas inactivas.
     *
     * @return array{0:string,1:array<string,string>}
     */
    private function filtros(?string $q, ?int $parentId): array
    {
        $conds = ["estado = 'activo'"];
        $params = [];
        if ($q !== null) {
            $conds[] = 'nombre LIKE :q';
            $params[':q'] = '%' . $q . '%';
        }
        if ($parentId === 0) {
            $conds[] = 'parent_id IS NULL'; // convención: parent_id=0 → raíz (no se expone parent_id_key)
        } elseif ($parentId !== null) {
            $conds[] = 'parent_id = :parent';
            $params[':parent'] = $parentId;
        }
        return [' WHERE ' . implode(' AND ', $conds), $params];
    }
}
