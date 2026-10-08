<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Recepcion;
use App\Core\Database;

/**
 * Acceso a purchase_receptions / purchase_reception_items y escrituras de
 * confirmación de inventario (T-3, RF-031/RF-032, RN-07, CA-10).
 * Append-only en recepciones: nunca DELETE FROM.
 */
final class RecepcionRepository
{
    private const ESTADOS = ['recibida', 'confirmada', 'rechazada'];

    public function insertar(int $orderId, ?string $idempotencyKey, int $usuarioId): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO purchase_receptions (order_id, recepcionado_por, idempotency_key)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([$orderId, $usuarioId, $idempotencyKey]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** @param list<array{product_id:int,numero_lote:string,fecha_vencimiento:string,cantidad:int}> $items */
    public function insertarItems(int $receptionId, array $items): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO purchase_reception_items
             (reception_id, product_id, numero_lote, fecha_vencimiento, cantidad)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($items as $item) {
            $stmt->execute([
                $receptionId,
                $item['product_id'],
                $item['numero_lote'],
                $item['fecha_vencimiento'],
                $item['cantidad'],
            ]);
        }
    }

    public function buscarPorId(int $id): ?Recepcion
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM purchase_receptions WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : Recepcion::fromRow($row);
    }

    public function buscarPorIdempotencyKey(string $key): ?Recepcion
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM purchase_receptions WHERE idempotency_key = ?'
        );
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row === false ? null : Recepcion::fromRow($row);
    }

    /**
     * @param array<string,mixed> $filters order_id, estado
     * @return list<Recepcion>
     */
    public function listar(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->filtros($filters);
        $sql = "SELECT * FROM purchase_receptions {$where}
                ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return array_map(Recepcion::fromRow(...), $stmt->fetchAll());
    }

    /** @param array<string,mixed> $filters */
    public function contar(array $filters): int
    {
        [$where, $params] = $this->filtros($filters);
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM purchase_receptions {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array{id:int,product_id:int,numero_lote:string,fecha_vencimiento:string,cantidad:int,lot_id:int|null}>
     */
    public function items(int $receptionId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, product_id, numero_lote, fecha_vencimiento, cantidad, lot_id
             FROM purchase_reception_items WHERE reception_id = ? ORDER BY id'
        );
        $stmt->execute([$receptionId]);
        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'product_id' => (int) $r['product_id'],
            'numero_lote' => (string) $r['numero_lote'],
            'fecha_vencimiento' => (string) $r['fecha_vencimiento'],
            'cantidad' => (int) $r['cantidad'],
            'lot_id' => $r['lot_id'] !== null ? (int) $r['lot_id'] : null,
        ], $stmt->fetchAll());
    }

    /** Bloquea la fila (FOR UPDATE) y devuelve su estado dentro de la transacción. */
    public function estadoBloqueado(int $id): ?string
    {
        $stmt = Database::pdo()->prepare(
            'SELECT estado FROM purchase_receptions WHERE id = ? FOR UPDATE'
        );
        $stmt->execute([$id]);
        $estado = $stmt->fetchColumn();
        return $estado === false ? null : (string) $estado;
    }

    /** Guard condicionado: false si ya no estaba 'recibida' (doble confirm/rechazo). */
    public function marcar(int $id, string $esperado, string $nuevo, ?int $usuarioId): bool
    {
        if ($nuevo === 'confirmada') {
            $stmt = Database::pdo()->prepare(
                "UPDATE purchase_receptions
                 SET estado = 'confirmada', confirmado_por = ?, confirmado_at = NOW(6)
                 WHERE id = ? AND estado = 'recibida'"
            );
            $stmt->execute([$usuarioId, $id]);
        } else {
            $stmt = Database::pdo()->prepare(
                "UPDATE purchase_receptions SET estado = ?
                 WHERE id = ? AND estado = 'recibida'"
            );
            $stmt->execute([$nuevo, $id]);
        }
        return $stmt->rowCount() > 0;
    }

    public function loteExiste(int $productId, string $numeroLote): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM inventory_lots WHERE product_id = ? AND numero_lote = ?'
        );
        $stmt->execute([$productId, $numeroLote]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** RF-032: el lote nace en cuarentena, ligado 1:1 a su item de recepción. */
    public function insertarLote(
        int $productId,
        string $numeroLote,
        string $fechaVencimiento,
        int $receptionItemId,
    ): int {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO inventory_lots
             (product_id, numero_lote, fecha_vencimiento, estado, reception_item_id)
             VALUES (?, ?, ?, \'cuarentena\', ?)'
        );
        $stmt->execute([$productId, $numeroLote, $fechaVencimiento, $receptionItemId]);
        return (int) Database::pdo()->lastInsertId();
    }

    public function asignarLot(int $receptionItemId, int $lotId): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE purchase_reception_items SET lot_id = ? WHERE id = ?'
        );
        $stmt->execute([$lotId, $receptionItemId]);
    }

    public function insertarStock(int $storeId, int $lotId, int $cantidad): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO inventory_stock
             (store_id, lot_id, stock_available, stock_reserved, stock_sold, version)
             VALUES (?, ?, ?, 0, 0, 1)'
        );
        $stmt->execute([$storeId, $lotId, $cantidad]);
    }

    public function insertarMovimientoEntrada(
        int $storeId,
        int $lotId,
        int $productId,
        int $cantidad,
        int $usuarioId,
        int $receptionId,
    ): void {
        // Delega en InventarioRepository para que aplique el hook del libro de
        // controlados (RF-055: todo movimiento de un controlado lleva asiento).
        (new InventarioRepository())->insertarMovimiento([
            'store_id' => $storeId,
            'lot_id' => $lotId,
            'product_id' => $productId,
            'tipo' => 'entrada',
            'cantidad' => $cantidad,
            'signo' => 1,
            'usuario_id' => $usuarioId,
            'motivo' => 'Confirmación de recepción #' . $receptionId,
            'ref_tipo' => 'recepcion',
            'ref_id' => $receptionId,
        ]);
    }

    public function storeExiste(int $storeId): bool
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM ops_stores WHERE id = ?');
        $stmt->execute([$storeId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Outbox de la misma transacción T-3 (eventos posteriores, CP de integración). */
    public function insertarOutbox(int $receptionId, array $payload): void
    {
        $stmt = Database::pdo()->prepare(
            "INSERT INTO outbox_events (agregado_tipo, agregado_id, tipo_evento, payload)
             VALUES ('recepcion', ?, 'recepcion.confirmada', ?)"
        );
        $stmt->execute([$receptionId, json_encode($payload, JSON_THROW_ON_ERROR)]);
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{0:string,1:list<int|string>}
     */
    private function filtros(array $filters): array
    {
        $where = '';
        $params = [];
        if (isset($filters['order_id']) && $filters['order_id'] !== '') {
            $where .= ' WHERE order_id = ?';
            $params[] = (int) $filters['order_id'];
        }
        if (isset($filters['estado']) && $filters['estado'] !== '') {
            $estado = (string) $filters['estado'];
            if (!in_array($estado, self::ESTADOS, true)) {
                throw new \App\Core\AppException('VALIDATION_ERROR', "Parámetro 'estado' inválido.", 400);
            }
            $where .= $where === '' ? ' WHERE' : ' AND';
            $where .= ' estado = ?';
            $params[] = $estado;
        }
        return [$where, $params];
    }
}
