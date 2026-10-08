<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Paciente;
use App\Core\Database;
use PDO;

final class PacienteRepository
{
    /**
     * @param string|null $q contiene, case-insensitive (collation ai_ci), o null
     * @return list<Paciente>
     */
    public function listar(?string $q, int $limit, int $offset): array
    {
        [$where, $params] = $this->filtros($q);
        $stmt = Database::pdo()->prepare(
            'SELECT id, identificacion, nombre, fecha_nacimiento, contacto, estado, created_at, anonimizado_at'
            . " FROM catalog_patients{$where}"
            . ' ORDER BY id ASC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            static fn (array $row): Paciente => Paciente::fromRow($row),
            $stmt->fetchAll()
        );
    }

    public function contar(?string $q): int
    {
        [$where, $params] = $this->filtros($q);
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM catalog_patients{$where}");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function buscarPorId(int $id): ?Paciente
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, identificacion, nombre, fecha_nacimiento, contacto, estado, created_at, anonimizado_at'
            . " FROM catalog_patients WHERE id = :id AND estado = 'activo'"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return Paciente::fromRow($row);
    }

    public function existeId(int $id): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM catalog_patients WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    /** Unicidad (identificacion): el índice único uq_catalog_patients_identificacion no filtra estado. */
    public function existeIdentificacion(string $identificacion): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM catalog_patients WHERE identificacion = :identificacion');
        $stmt->bindValue(':identificacion', $identificacion);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    /** Unicidad (identificacion) excluyendo el propio registro (para UPDATE). */
    public function existeIdentificacionExcluyendo(string $identificacion, int $excludeId): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT 1 FROM catalog_patients WHERE identificacion = :identificacion AND id <> :id'
        );
        $stmt->bindValue(':identificacion', $identificacion);
        $stmt->bindValue(':id', $excludeId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    public function insertar(string $identificacion, string $nombre, ?string $fechaNacimiento, ?string $contacto): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO catalog_patients (identificacion, nombre, fecha_nacimiento, contacto)'
            . ' VALUES (:identificacion, :nombre, :fecha_nacimiento, :contacto)'
        );
        $stmt->bindValue(':identificacion', $identificacion);
        $stmt->bindValue(':nombre', $nombre);
        $stmt->bindValue(':fecha_nacimiento', $fechaNacimiento);
        $stmt->bindValue(':contacto', $contacto);
        $stmt->execute();
        return (int) Database::pdo()->lastInsertId();
    }

    public function actualizar(
        int $id,
        string $identificacion,
        string $nombre,
        ?string $fechaNacimiento,
        ?string $contacto
    ): void {
        $stmt = Database::pdo()->prepare(
            'UPDATE catalog_patients SET identificacion = :identificacion, nombre = :nombre,'
            . ' fecha_nacimiento = :fecha_nacimiento, contacto = :contacto WHERE id = :id'
        );
        $stmt->bindValue(':identificacion', $identificacion);
        $stmt->bindValue(':nombre', $nombre);
        $stmt->bindValue(':fecha_nacimiento', $fechaNacimiento);
        $stmt->bindValue(':contacto', $contacto);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function anonimizar(int $id): void
    {
        // ponytail: anonimización in-place (minimización de PII); upgrade path = tabla previa de hash si la normativa exige conservar el identificador original sin exponerlo.
        $stmt = Database::pdo()->prepare(
            "UPDATE catalog_patients SET estado='anonimizado', anonimizado_at = NOW(6),"
            . " nombre='[ANONIMIZADO]', identificacion = CONCAT('ANON-', id),"
            . ' contacto = NULL, fecha_nacimiento = NULL WHERE id = :id'
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
