<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\OrdenCompra;
use App\Support\Database;

/**
 * Acceso a purchase_orders / purchase_order_items (RF-030).
 * Solo consultas preparadas; sin DELETE FROM en recepciones confirmadas.
 */
final class OrdenCompraRepository
{
    private const ESTADOS = ['borrador', 'emitida', 'recibida', 'cancelada'];

    /**
     * @param array<string,mixed> $filters q, supplier_id, estado
     * @return list<OrdenCompra>
     */
    public function listar(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->filtros($filters);
        $sql = "SELECT * FROM purchase_orders {$where} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return array_map(OrdenCompra::fromRow(...), $stmt->fetchAll());
    }

    /** @param array<string,mixed> $filters */
    public function contar(array $filters): int
    {
        [$where, $params] = $this->filtros($filters);
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM purchase_orders {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function buscarPorId(int $id): ?OrdenCompra
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM purchase_orders WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : OrdenCompra::fromRow($row);
    }

    public function existeNumero(string $numero): bool
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM purchase_orders WHERE numero = ?');
        $stmt->execute([$numero]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function existeSupplier(int $id): bool
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM catalog_suppliers WHERE id = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function existeProduct(int $id): bool
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM catalog_products WHERE id = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function insertar(string $numero, int $supplierId, int $creadoPor): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO purchase_orders (numero, supplier_id, creado_por) VALUES (?, ?, ?)'
        );
        $stmt->execute([$numero, $supplierId, $creadoPor]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** @param list<array{product_id:int,cantidad_pedida:int}> $items */
    public function reemplazarItems(int $orderId, array $items): void
    {
        $pdo = Database::pdo();
        // ponytail: borrador sin recepciones (reemplazo de set). DELETE solo
        // aquí; las recepciones confirmadas nunca se borran (RNF-023).
        $pdo->prepare('DELETE FROM purchase_order_items WHERE order_id = ?')->execute([$orderId]);
        $stmt = $pdo->prepare(
            'INSERT INTO purchase_order_items (order_id, product_id, cantidad_pedida) VALUES (?, ?, ?)'
        );
        foreach ($items as $item) {
            $stmt->execute([$orderId, $item['product_id'], $item['cantidad_pedida']]);
        }
    }

    /**
     * @return list<array{product_id:int,cantidad_pedida:int,cantidad_recibida:int}>
     */
    public function items(int $orderId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT product_id, cantidad_pedida, cantidad_recibida
             FROM purchase_order_items WHERE order_id = ? ORDER BY id'
        );
        $stmt->execute([$orderId]);
        return array_map(static fn (array $r): array => [
            'product_id' => (int) $r['product_id'],
            'cantidad_pedida' => (int) $r['cantidad_pedida'],
            'cantidad_recibida' => (int) $r['cantidad_recibida'],
        ], $stmt->fetchAll());
    }

    /** Pendiente de un producto en la orden; null si el producto no está. */
    public function pendiente(int $orderId, int $productId): ?int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT cantidad_pedida - cantidad_recibida AS pendiente
             FROM purchase_order_items WHERE order_id = ? AND product_id = ?'
        );
        $stmt->execute([$orderId, $productId]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (int) $value;
    }

    /** Filas de items bloqueadas para validación dentro de la transacción (T-3). */
    public function itemsBloqueados(int $orderId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT product_id, cantidad_pedida, cantidad_recibida
             FROM purchase_order_items WHERE order_id = ? FOR UPDATE'
        );
        $stmt->execute([$orderId]);
        return array_map(static fn (array $r): array => [
            'product_id' => (int) $r['product_id'],
            'cantidad_pedida' => (int) $r['cantidad_pedida'],
            'cantidad_recibida' => (int) $r['cantidad_recibida'],
        ], $stmt->fetchAll());
    }

    public function actualizar(int $id, string $numero, int $supplierId): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE purchase_orders SET numero = ?, supplier_id = ? WHERE id = ?'
        );
        $stmt->execute([$numero, $supplierId, $id]);
    }

    /** Cambio de estado guard condicionado; false si el estado actual no es $esperado. */
    public function cambiarEstado(int $id, string $esperado, string $nuevo): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE purchase_orders SET estado = ? WHERE id = ? AND estado = ?'
        );
        $stmt->execute([$nuevo, $id, $esperado]);
        return $stmt->rowCount() > 0;
    }

    public function tieneRecepciones(int $orderId): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM purchase_receptions WHERE order_id = ?'
        );
        $stmt->execute([$orderId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Confirmación de recepción: suma cantidad_recibida (validado contra CHECK). */
    public function sumarRecibido(int $orderId, int $productId, int $cantidad): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE purchase_order_items
             SET cantidad_recibida = cantidad_recibida + ?
             WHERE order_id = ? AND product_id = ?'
        );
        $stmt->execute([$cantidad, $orderId, $productId]);
    }

    /** @return array<string,mixed>|null */
    public function item(int $orderId, int $productId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, cantidad_pedida, cantidad_recibida
             FROM purchase_order_items WHERE order_id = ? AND product_id = ?'
        );
        $stmt->execute([$orderId, $productId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{0:string,1:list<int|string>}
     */
    private function filtros(array $filters): array
    {
        $where = '';
        $params = [];
        if (isset($filters['q']) && is_string($filters['q']) && $filters['q'] !== '') {
            $where .= ' WHERE numero LIKE ?';
            $params[] = '%' . $filters['q'] . '%';
        }
        if (isset($filters['supplier_id']) && $filters['supplier_id'] !== '') {
            $where .= $where === '' ? ' WHERE' : ' AND';
            $where .= ' supplier_id = ?';
            $params[] = (int) $filters['supplier_id'];
        }
        if (isset($filters['estado']) && $filters['estado'] !== '') {
            $estado = (string) $filters['estado'];
            if (!in_array($estado, self::ESTADOS, true)) {
                throw new \App\Http\AppException('VALIDATION_ERROR', "Parámetro 'estado' inválido.", 400);
            }
            $where .= $where === '' ? ' WHERE' : ' AND';
            $where .= ' estado = ?';
            $params[] = $estado;
        }
        return [$where, $params];
    }
}
