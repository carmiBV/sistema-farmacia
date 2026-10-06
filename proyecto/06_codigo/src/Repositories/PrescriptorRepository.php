<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Prescriptor;
use App\Support\Database;
use PDO;

final class PrescriptorRepository
{
    /**
     * @param string|null $q contiene, case-insensitive (collation ai_ci), o null
     * @return list<Prescriptor>
     */
    public function listar(?string $q, int $limit, int $offset): array
    {
        [$where, $params] = $this->filtros($q);
        $stmt = Database::pdo()->prepare(
            "SELECT id, identificacion, nombre, especialidad, estado FROM catalog_prescribers{$where}"
            . ' ORDER BY id ASC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            static fn (array $row): Prescriptor => Prescriptor::fromRow($row),
            $stmt->fetchAll()
        );
    }

    public function contar(?string $q): int
    {
        [$where, $params] = $this->filtros($q);
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM catalog_prescribers{$where}");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function buscarPorId(int $id): ?Prescriptor
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, identificacion, nombre, especialidad, estado FROM catalog_prescribers'
            . " WHERE id = :id AND estado = 'activo'"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return Prescriptor::fromRow($row);
    }

    public function existeId(int $id): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM catalog_prescribers WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    /** Unicidad (identificacion): el índice único uq_catalog_prescribers_identificacion no filtra estado. */
    public function existeIdentificacion(string $identificacion): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM catalog_prescribers WHERE identificacion = :identificacion');
        $stmt->bindValue(':identificacion', $identificacion);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    /** Unicidad (identificacion) excluyendo el propio registro (para UPDATE). */
    public function existeIdentificacionExcluyendo(string $identificacion, int $excludeId): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT 1 FROM catalog_prescribers WHERE identificacion = :identificacion AND id <> :id'
        );
        $stmt->bindValue(':identificacion', $identificacion);
        $stmt->bindValue(':id', $excludeId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    public function insertar(string $identificacion, string $nombre, ?string $especialidad): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO catalog_prescribers (identificacion, nombre, especialidad)'
            . ' VALUES (:identificacion, :nombre, :especialidad)'
        );
        $stmt->bindValue(':identificacion', $identificacion);
        $stmt->bindValue(':nombre', $nombre);
        $stmt->bindValue(':especialidad', $especialidad);
        $stmt->execute();
        return (int) Database::pdo()->lastInsertId();
    }

    public function actualizar(int $id, string $identificacion, string $nombre, ?string $especialidad): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE catalog_prescribers SET identificacion = :identificacion, nombre = :nombre,'
            . ' especialidad = :especialidad WHERE id = :id'
        );
        $stmt->bindValue(':identificacion', $identificacion);
        $stmt->bindValue(':nombre', $nombre);
        $stmt->bindValue(':especialidad', $especialidad);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function inactivar(int $id): void
    {
        $stmt = Database::pdo()->prepare(
            "UPDATE catalog_prescribers SET estado = 'inactivo' WHERE id = :id"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * @return array{0:string,1:array<string,string>}
     */
    private function filtros(?string $q): array
    {
        $conds = ["estado = 'activo'"];
        $params = [];
        if ($q !== null) {
            $conds[] = '(nombre LIKE :q1 OR identificacion LIKE :q2)';
            $params[':q1'] = '%' . $q . '%';
            $params[':q2'] = '%' . $q . '%';
        }
        return [' WHERE ' . implode(' AND ', $conds), $params];
    }
}
