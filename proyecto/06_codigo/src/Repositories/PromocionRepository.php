<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Promocion;
use App\Support\Database;
use PDO;

/**
 * Acceso a `catalog_promotions`.
 *
 * Sin filtro `estado`: la tabla no lo tiene. Sin DELETE en ningún método:
 * la baja se cierra con `hasta` desde el Service (update).
 */
final class PromocionRepository
{
    /** @return list<Promocion> */
    public function listar(?int $productId, ?int $categoryId, int $limit, int $offset): array
    {
        [$where, $params] = $this->filtros($productId, $categoryId);
        $stmt = Database::pdo()->prepare(
            'SELECT id, product_id, category_id, descuento_pct, desde, hasta'
            . " FROM catalog_promotions{$where} ORDER BY id ASC LIMIT :limit OFFSET :offset"
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
            static fn (array $row): Promocion => Promocion::fromRow($row),
            $rows
        );
    }

    public function contar(?int $productId, ?int $categoryId): int
    {
        [$where, $params] = $this->filtros($productId, $categoryId);
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM catalog_promotions{$where}");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function buscarPorId(int $id): ?Promocion
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, product_id, category_id, descuento_pct, desde, hasta'
            . ' FROM catalog_promotions WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();

        return $row === false ? null : Promocion::fromRow($row);
    }

    /**
     * Alcance referencial: producto activo o categoría activa. Lo usa el
     * Service para decidir el 409 NOT_FOUND con el mensaje exacto del
     * referente inexistente.
     *
     * Nota: `product_id` es nullable en el body (basta con un alcance), por
     * eso el parámetro también es nullable aunque el contrato lo muestre como
     * int. Supuesto a validar en CP.
     */
    public function existeAlcance(?int $productId, ?int $categoryId): bool
    {
        $productoOk = $productId === null || $this->productoExiste($productId);
        $categoriaOk = $categoryId === null || $this->categoriaExiste($categoryId);

        return $productoOk && $categoriaOk;
    }

    public function productoExiste(int $id): bool
    {
        $stmt = Database::pdo()->prepare(
            "SELECT 1 FROM catalog_products WHERE id = :id AND estado = 'activo'"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }

    public function categoriaExiste(int $id): bool
    {
        $stmt = Database::pdo()->prepare(
            "SELECT 1 FROM catalog_categories WHERE id = :id AND estado = 'activo'"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }

    /** @param array<string,mixed> $datos validados por CatalogValidator */
    public function insertar(array $datos): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO catalog_promotions (product_id, category_id, descuento_pct, desde, hasta)'
            . ' VALUES (:product_id, :category_id, :descuento_pct, :desde, :hasta)'
        );
        $stmt->bindValue(':product_id', $datos['product_id'], $datos['product_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':category_id', $datos['category_id'], $datos['category_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':descuento_pct', (string) $datos['descuento_pct']);
        $stmt->bindValue(':desde', (string) $datos['desde']);
        $stmt->bindValue(':hasta', (string) $datos['hasta']);
        $stmt->execute();

        return (int) Database::pdo()->lastInsertId();
    }

    /** @param array<string,mixed> $datos validados por CatalogValidator */
    public function actualizar(int $id, array $datos): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE catalog_promotions SET product_id = :product_id, category_id = :category_id,'
            . ' descuento_pct = :descuento_pct, desde = :desde, hasta = :hasta WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':product_id', $datos['product_id'], $datos['product_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':category_id', $datos['category_id'], $datos['category_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':descuento_pct', (string) $datos['descuento_pct']);
        $stmt->bindValue(':desde', (string) $datos['desde']);
        $stmt->bindValue(':hasta', (string) $datos['hasta']);
        $stmt->execute();
    }

    /**
     * Filtros opcionales del query string de listado.
     *
     * @return array{0:string,1:array<string,mixed>}
     */
    private function filtros(?int $productId, ?int $categoryId): array
    {
        $conds = [];
        $params = [];
        if ($productId !== null) {
            $conds[] = 'product_id = :product';
            $params[':product'] = $productId;
        }
        if ($categoryId !== null) {
            $conds[] = 'category_id = :category';
            $params[':category'] = $categoryId;
        }

        return [$conds === [] ? '' : ' WHERE ' . implode(' AND ', $conds), $params];
    }
}
