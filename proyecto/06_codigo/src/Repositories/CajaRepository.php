<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Caja;
use App\Support\Database;
use PDO;

final class CajaRepository
{
    /**
     * @param string|null $q contiene, case-insensitive (collation ai_ci), o null
     * @return list<Caja>
     */
    public function listar(?string $q, ?int $storeId, int $limit, int $offset): array
    {
        [$where, $params] = $this->filtros($q, $storeId);
        $stmt = Database::pdo()->prepare(
            "SELECT id, store_id, codigo, estado FROM ops_registers{$where}"
            . ' ORDER BY id ASC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            static fn (array $row): Caja => Caja::fromRow($row),
            $stmt->fetchAll()
        );
    }

    public function contar(?string $q, ?int $storeId): int
    {
        [$where, $params] = $this->filtros($q, $storeId);
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM ops_registers{$where}");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function buscarPorId(int $id): ?Caja
    {
        $stmt = Database::pdo()->prepare(
            "SELECT id, store_id, codigo, estado FROM ops_registers WHERE id = :id AND estado = 'activa'"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return Caja::fromRow($row);
    }

    /** Unicidad (store_id, codigo): índice único uq_ops_registers_store_codigo, sin filtro de estado. */
    public function existeCodigoEnStore(int $storeId, string $codigo): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM ops_registers WHERE store_id = :store AND codigo = :codigo');
        $stmt->bindValue(':store', $storeId, PDO::PARAM_INT);
        $stmt->bindValue(':codigo', $codigo);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    public function existeCodigoEnStoreExcluyendo(int $storeId, string $codigo, int $excludeId): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT 1 FROM ops_registers WHERE store_id = :store AND codigo = :codigo AND id <> :id'
        );
        $stmt->bindValue(':store', $storeId, PDO::PARAM_INT);
        $stmt->bindValue(':codigo', $codigo);
        $stmt->bindValue(':id', $excludeId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    public function insertar(int $storeId, string $codigo): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO ops_registers (store_id, codigo) VALUES (:store, :codigo)'
        );
        $stmt->bindValue(':store', $storeId, PDO::PARAM_INT);
        $stmt->bindValue(':codigo', $codigo);
        $stmt->execute();
        return (int) Database::pdo()->lastInsertId();
    }

    public function actualizar(int $id, int $storeId, string $codigo): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ops_registers SET store_id = :store, codigo = :codigo WHERE id = :id'
        );
        $stmt->bindValue(':store', $storeId, PDO::PARAM_INT);
        $stmt->bindValue(':codigo', $codigo);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function inactivar(int $id): void
    {
        $stmt = Database::pdo()->prepare("UPDATE ops_registers SET estado = 'inactiva' WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * @return array{0:string,1:array<string,mixed>}
     */
    private function filtros(?string $q, ?int $storeId): array
    {
        $conds = ["estado = 'activa'"];
        $params = [];
        if ($q !== null) {
            $conds[] = 'codigo LIKE :q';
            $params[':q'] = '%' . $q . '%';
        }
        if ($storeId !== null) {
            $conds[] = 'store_id = :store';
            $params[':store'] = $storeId;
        }
        return [' WHERE ' . implode(' AND ', $conds), $params];
    }
}
