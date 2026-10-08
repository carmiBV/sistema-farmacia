<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Precio;
use App\Core\Database;
use PDO;

/**
 * Acceso a `catalog_prices` (append-only de precios por vigencia).
 *
 * Sin filtro `estado`: la tabla no lo tiene. Sin DELETE en ningún método:
 * la baja se cierra con `vigente_hasta` desde el Service (update).
 */
final class PrecioRepository
{
    /** @return list<Precio> */
    public function listar(?int $productId, ?int $storeId, int $limit, int $offset): array
    {
        [$where, $params] = $this->filtros($productId, $storeId);
        $stmt = Database::pdo()->prepare(
            'SELECT id, product_id, store_id, precio, vigente_desde, vigente_hasta'
            . " FROM catalog_prices{$where} ORDER BY id ASC LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        /** @var list<array<string,mixed>> $rows */
        $rows = $stmt->fetchAll();

        return array_map(
            static fn (array $row): Precio => Precio::fromRow($row),
            $rows
        );
    }

    public function contar(?int $productId, ?int $storeId): int
    {
        [$where, $params] = $this->filtros($productId, $storeId);
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM catalog_prices{$where}");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function buscarPorId(int $id): ?Precio
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, product_id, store_id, precio, vigente_desde, vigente_hasta'
            . ' FROM catalog_prices WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();

        return $row === false ? null : Precio::fromRow($row);
    }

    /**
     * Única combinación (product_id, store_id_key, vigente_desde).
     * La uq se define sobre `store_id_key = ifnull(store_id, 0)`, pero como
     * `store_id` es FK a ops_stores (>= 1) el 0 solo lo produce NULL, así que
     * `store_id IS NULL` equivale exactamente a `store_id_key = 0`.
     */
    public function existeCombinacion(int $productId, ?int $storeId, string $vigenteDesde, int $excludeId = 0): bool
    {
        $conds = ['product_id = :product', 'vigente_desde = :desde'];
        if ($storeId === null) {
            $conds[] = 'store_id IS NULL';
        } else {
            $conds[] = 'store_id = :store';
        }
        if ($excludeId > 0) {
            $conds[] = 'id <> :exclude';
        }

        $stmt = Database::pdo()->prepare(
            'SELECT 1 FROM catalog_prices WHERE ' . implode(' AND ', $conds)
        );
        $stmt->bindValue(':product', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':desde', $vigenteDesde);
        if ($storeId !== null) {
            $stmt->bindValue(':store', $storeId, PDO::PARAM_INT);
        }
        if ($excludeId > 0) {
            $stmt->bindValue(':exclude', $excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }

    /** Existencia del producto referenciado (sin filtro de estado, literal del contrato). */
    public function productoExiste(int $id): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM catalog_products WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }

    public function sucursalExiste(int $id): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM ops_stores WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }

    /** @param array<string,mixed> $datos validados por CatalogValidator */
    public function insertar(array $datos): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO catalog_prices (product_id, store_id, precio, vigente_desde, vigente_hasta)'
            . ' VALUES (:product_id, :store_id, :precio, :vigente_desde, :vigente_hasta)'
        );
        $stmt->bindValue(':product_id', $datos['product_id'], PDO::PARAM_INT);
        $stmt->bindValue(':store_id', $datos['store_id'], $datos['store_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':precio', (string) $datos['precio']);
        $stmt->bindValue(':vigente_desde', (string) $datos['vigente_desde']);
        $stmt->bindValue(
            ':vigente_hasta',
            $datos['vigente_hasta'],
            $datos['vigente_hasta'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR
        );
        $stmt->execute();

        return (int) Database::pdo()->lastInsertId();
    }

    /** @param array<string,mixed> $datos validados por CatalogValidator */
    public function actualizar(int $id, array $datos): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE catalog_prices SET product_id = :product_id, store_id = :store_id, precio = :precio,'
            . ' vigente_desde = :vigente_desde, vigente_hasta = :vigente_hasta WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':product_id', $datos['product_id'], PDO::PARAM_INT);
        $stmt->bindValue(':store_id', $datos['store_id'], $datos['store_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':precio', (string) $datos['precio']);
        $stmt->bindValue(':vigente_desde', (string) $datos['vigente_desde']);
        $stmt->bindValue(
            ':vigente_hasta',
            $datos['vigente_hasta'],
            $datos['vigente_hasta'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR
        );
        $stmt->execute();
    }

    /**
     * Filtros opcionales del query string de listado.
     *
     * @return array{0:string,1:array<string,mixed>}
     */
    private function filtros(?int $productId, ?int $storeId): array
    {
        $conds = [];
        $params = [];
        if ($productId !== null) {
            $conds[] = 'product_id = :product';
            $params[':product'] = $productId;
        }
        if ($storeId !== null) {
            $conds[] = 'store_id = :store';
            $params[':store'] = $storeId;
        }

        return [$conds === [] ? '' : ' WHERE ' . implode(' AND ', $conds), $params];
    }
}
