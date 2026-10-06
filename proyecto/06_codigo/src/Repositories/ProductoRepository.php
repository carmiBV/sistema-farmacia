<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Producto;
use App\Support\Database;
use PDO;

final class ProductoRepository
{
    private const COLS = 'id, sku, nombre, principio_activo, presentacion,'
        . ' concentracion, condicion_venta, estado, created_at';

    /**
     * @param string|null $q          contiene, case-insensitive (collation ai_ci), o null
     * @param int|null    $categoryId null = sin filtro · n = solo productos con esa categoría
     * @return list<Producto>
     */
    public function listar(?string $q, ?int $categoryId, int $limit, int $offset): array
    {
        [$where, $params] = $this->filtros($q, $categoryId);
        $stmt = Database::pdo()->prepare(
            'SELECT ' . self::COLS . " FROM catalog_products{$where}"
            . ' ORDER BY id ASC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            static fn (array $row): Producto => Producto::fromRow($row),
            $stmt->fetchAll()
        );
    }

    public function contar(?string $q, ?int $categoryId): int
    {
        [$where, $params] = $this->filtros($q, $categoryId);
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM catalog_products' . $where);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function buscarPorId(int $id): ?Producto
    {
        $stmt = Database::pdo()->prepare(
            'SELECT ' . self::COLS . " FROM catalog_products WHERE id = :id AND estado = 'activo'"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return Producto::fromRow($row);
    }

    /** Existencia sin filtro estado (p. ej. tras un DELETE lógico). */
    public function existeId(int $id): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM catalog_products WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    /** Unicidad (sku): el índice único no filtra estado. */
    public function existeSku(string $sku): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM catalog_products WHERE sku = :sku');
        $stmt->bindValue(':sku', $sku);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    /** Unicidad (sku) excluyendo el propio registro (para UPDATE). */
    public function existeSkuExcluyendo(string $sku, int $excludeId): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM catalog_products WHERE sku = :sku AND id <> :id');
        $stmt->bindValue(':sku', $sku);
        $stmt->bindValue(':id', $excludeId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    public function insertar(
        string $sku,
        string $nombre,
        string $principioActivo,
        string $presentacion,
        string $concentracion,
        string $condicionVenta,
    ): int {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO catalog_products (sku, nombre, principio_activo, presentacion, concentracion, condicion_venta)'
            . ' VALUES (:sku, :nombre, :principio_activo, :presentacion, :concentracion, :condicion_venta)'
        );
        $stmt->bindValue(':sku', $sku);
        $stmt->bindValue(':nombre', $nombre);
        $stmt->bindValue(':principio_activo', $principioActivo);
        $stmt->bindValue(':presentacion', $presentacion);
        $stmt->bindValue(':concentracion', $concentracion);
        $stmt->bindValue(':condicion_venta', $condicionVenta);
        $stmt->execute();
        return (int) Database::pdo()->lastInsertId();
    }

    public function actualizar(
        int $id,
        string $sku,
        string $nombre,
        string $principioActivo,
        string $presentacion,
        string $concentracion,
        string $condicionVenta,
    ): void {
        $stmt = Database::pdo()->prepare(
            'UPDATE catalog_products SET sku = :sku, nombre = :nombre, principio_activo = :principio_activo,'
            . ' presentacion = :presentacion, concentracion = :concentracion, condicion_venta = :condicion_venta'
            . ' WHERE id = :id'
        );
        $stmt->bindValue(':sku', $sku);
        $stmt->bindValue(':nombre', $nombre);
        $stmt->bindValue(':principio_activo', $principioActivo);
        $stmt->bindValue(':presentacion', $presentacion);
        $stmt->bindValue(':concentracion', $concentracion);
        $stmt->bindValue(':condicion_venta', $condicionVenta);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function inactivar(int $id): void
    {
        $stmt = Database::pdo()->prepare("UPDATE catalog_products SET estado = 'inactivo' WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // ── Subrecurso: catalog_product_categories ──────────────────────────────

    /** @return list<int> */
    public function categoriasDeProducto(int $productId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT category_id FROM catalog_product_categories WHERE product_id = :pid ORDER BY category_id'
        );
        $stmt->bindValue(':pid', $productId, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(
            static fn (array $row): int => (int) $row['category_id'],
            $stmt->fetchAll()
        );
    }

    /** Sin filtro estado, consistente con el chequeo de existencia de catálogo. */
    public function categoriaExiste(int $categoryId): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM catalog_categories WHERE id = :id');
        $stmt->bindValue(':id', $categoryId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Nombres de categorías para el lookup de la respuesta; las inexistentes
     * simplemente no aparecen en el mapa.
     *
     * @param list<int> $ids
     * @return array<int,string> id => nombre
     */
    public function nombresCategorias(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(', ', array_map(static fn (int $i): string => ':c' . $i, array_keys($ids)));
        $stmt = Database::pdo()->prepare("SELECT id, nombre FROM catalog_categories WHERE id IN ({$placeholders})");
        foreach (array_keys($ids) as $position) {
            $stmt->bindValue(':c' . $position, $ids[$position], PDO::PARAM_INT);
        }
        $stmt->execute();

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['id']] = (string) $row['nombre'];
        }
        return $map;
    }

    /**
     * Reemplazo atómico del set de categorías del producto.
     *
     * ponytail: tabla de unión sin columna estado; el reemplazo del set es DELETE+INSERT, upgrade path = columna estado si se exige borrado lógico de la relación.
     *
     * @param list<int> $categoryIds
     * @throws \PDOException relanzada sin tragar (la convierte el Service)
     */
    public function reemplazarCategorias(int $productId, array $categoryIds): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare('DELETE FROM catalog_product_categories WHERE product_id = :pid');
            $del->bindValue(':pid', $productId, PDO::PARAM_INT);
            $del->execute();

            $ins = $pdo->prepare(
                'INSERT INTO catalog_product_categories (product_id, category_id) VALUES (:pid, :cid)'
            );
            foreach ($categoryIds as $categoryId) {
                $ins->bindValue(':pid', $productId, PDO::PARAM_INT);
                $ins->bindValue(':cid', $categoryId, PDO::PARAM_INT);
                $ins->execute();
            }
            $pdo->commit();
        } catch (\PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @return array{0:string,1:array<string,string>}
     */
    private function filtros(?string $q, ?int $categoryId): array
    {
        $conds = ["estado = 'activo'"];
        $params = [];
        if ($q !== null) {
            // EMULATE_PREPARES=false: cada placeholder debe ser único.
            $conds[] = '(nombre LIKE :q1 OR sku LIKE :q2 OR principio_activo LIKE :q3)';
            $params[':q1'] = '%' . $q . '%';
            $params[':q2'] = '%' . $q . '%';
            $params[':q3'] = '%' . $q . '%';
        }
        if ($categoryId !== null) {
            $conds[] = 'EXISTS (SELECT 1 FROM catalog_product_categories cpc'
                . ' WHERE cpc.product_id = catalog_products.id AND cpc.category_id = :category_id)';
            $params[':category_id'] = $categoryId;
        }
        return [' WHERE ' . implode(' AND ', $conds), $params];
    }
}
