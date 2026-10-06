<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

/**
 * Repositorio de ventas POS: órdenes, pagos y devoluciones (CP-BACK-08).
 * Append-only para pagos/devoluciones; cambios de estado por CAS (RN-09).
 * Prohibido DELETE FROM.
 */
final class VentaRepository
{
    // ------------------------------------------------------------ catálogo

    public function storeExiste(int $id): bool
    {
        return $this->existe('ops_stores', $id);
    }

    /** RN-01: la caja debe pertenecer a la sucursal de la orden. */
    public function registerExisteEnStore(int $registerId, int $storeId): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM ops_registers WHERE id = ? AND store_id = ?'
        );
        $stmt->execute([$registerId, $storeId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function pacienteExiste(int $id): bool
    {
        return $this->existe('catalog_patients', $id);
    }

    // ------------------------------------------------------------- órdenes

    public function insertarOrden(
        string $numero,
        int $storeId,
        int $registerId,
        ?int $pacienteId,
        int $userId,
        float $total,
        ?string $idempotencyKey
    ): int {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO sales_orders
                (numero, channel, store_id, register_id, paciente_id, usuario_id,
                 estado, total, idempotency_key, created_at)
             VALUES (?, "pos", ?, ?, ?, ?, "pendiente", ?, ?, NOW(6))'
        );
        $stmt->execute([$numero, $storeId, $registerId, $pacienteId, $userId, $total, $idempotencyKey]);
        return (int) Database::pdo()->lastInsertId();
    }

    public function insertarItemOrden(
        int $orderId,
        int $lotId,
        int $productId,
        int $cantidad,
        float $precio,
        float $subtotal
    ): void {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO sales_order_items
                (order_id, lot_id, product_id, cantidad, precio_unitario, subtotal)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$orderId, $lotId, $productId, $cantidad, $precio, $subtotal]);
    }

    /** @return array<string,mixed>|null */
    public function buscarOrden(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM sales_orders WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : self::castOrden($row);
    }

    /** Lock de fila para pagar/devolver en caliente (serializa carreras). */
    public function buscarOrdenBloqueada(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM sales_orders WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : self::castOrden($row);
    }

    /** @return array<string,mixed>|null */
    public function buscarOrdenPorIdempotencyKey(string $key): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM sales_orders WHERE idempotency_key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row === false ? null : self::castOrden($row);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function itemsOrden(int $orderId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM sales_order_items WHERE order_id = ? ORDER BY id'
        );
        $stmt->execute([$orderId]);
        return self::cast($stmt->fetchAll(), ['id', 'order_id', 'lot_id', 'product_id', 'cantidad', 'rx_item_id']);
    }

    /**
     * Ítem con lo mínimo para validar/anular (lote, producto, cantidad, precio).
     *
     * @return array{id:int,order_id:int,lot_id:int,product_id:int,cantidad:int,precio_unitario:float}|null
     */
    public function itemOrden(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, order_id, lot_id, product_id, cantidad, precio_unitario
               FROM sales_order_items WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return [
            'id' => (int) $row['id'],
            'order_id' => (int) $row['order_id'],
            'lot_id' => (int) $row['lot_id'],
            'product_id' => (int) $row['product_id'],
            'cantidad' => (int) $row['cantidad'],
            'precio_unitario' => (float) $row['precio_unitario'],
        ];
    }

    /** @param array<string,string> $filtros ya validados */
    public function listarOrdenes(array $filtros, int $limit, int $offset): array
    {
        [$where, $params] = $this->where($filtros, ['store_id' => 'store_id', 'estado' => 'estado']);
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM sales_orders{$where} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return self::cast($stmt->fetchAll(), ['id', 'store_id', 'register_id', 'paciente_id', 'usuario_id']);
    }

    /** @param array<string,string> $filtros ya validados */
    public function contarOrdenes(array $filtros): int
    {
        [$where, $params] = $this->where($filtros, ['store_id' => 'store_id', 'estado' => 'estado']);
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM sales_orders{$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** Cambio de estado atómico (CAS): false si otro hilo ya lo cambió. */
    public function cambiarEstadoOrden(int $id, string $desde, string $hasta): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE sales_orders SET estado = ? WHERE id = ? AND estado = ?'
        );
        $stmt->execute([$hasta, $id, $desde]);
        return $stmt->rowCount() > 0;
    }

    /** CAS con conjunto de estados (anular admite pendiente|pagada). */
    public function cambiarEstadoOrdenDesde(int $id, array $desde, string $hasta): bool
    {
        $marks = implode(', ', array_fill(0, count($desde), '?'));
        $stmt = Database::pdo()->prepare(
            "UPDATE sales_orders SET estado = ? WHERE id = ? AND estado IN ({$marks})"
        );
        $stmt->execute([$hasta, $id, ...$desde]);
        return $stmt->rowCount() > 0;
    }

    // -------------------------------------------------------------- pagos

    /** @return array{id:int,order_id:int,medio:string,amount:float,status:string}|null */
    public function buscarPagoPorIdempotencyKey(string $key): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, order_id, medio, amount, status FROM payments_transactions WHERE idempotency_key = ?'
        );
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return [
            'id' => (int) $row['id'],
            'order_id' => (int) $row['order_id'],
            'medio' => (string) $row['medio'],
            'amount' => (float) $row['amount'],
            'status' => (string) $row['status'],
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function pagosDeOrden(int $orderId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, order_id, medio, amount, status, created_at
               FROM payments_transactions WHERE order_id = ? ORDER BY id'
        );
        $stmt->execute([$orderId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'order_id' => (int) $row['order_id'],
                'medio' => (string) $row['medio'],
                'amount' => (float) $row['amount'],
                'status' => (string) $row['status'],
                'created_at' => (string) $row['created_at'],
            ];
        }
        return $out;
    }

    public function sumarPagado(int $orderId): float
    {
        $stmt = Database::pdo()->prepare(
            "SELECT IFNULL(SUM(amount), 0) FROM payments_transactions
              WHERE order_id = ? AND status = 'aprobado'"
        );
        $stmt->execute([$orderId]);
        return round((float) $stmt->fetchColumn(), 2);
    }

    public function insertarPago(
        int $orderId,
        string $medio,
        float $amount,
        string $status,
        ?string $idempotencyKey
    ): int {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO payments_transactions
                (order_id, medio, amount, status, idempotency_key, created_at)
             VALUES (?, ?, ?, ?, ?, NOW(6))'
        );
        $stmt->execute([$orderId, $medio, $amount, $status, $idempotencyKey]);
        return (int) Database::pdo()->lastInsertId();
    }

    // --------------------------------------------------------- devoluciones

    /** Cantidad ya devuelta de un ítem de orden (evita doble devolución). */
    public function devueltoDeItem(int $orderItemId): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT IFNULL(SUM(cantidad), 0) FROM sales_return_items WHERE order_item_id = ?'
        );
        $stmt->execute([$orderItemId]);
        return (int) $stmt->fetchColumn();
    }

    public function insertarDevolucion(
        int $orderId,
        int $storeId,
        int $userId,
        string $motivo,
        float $monto,
        string $estadoEval
    ): int {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO sales_returns
                (order_id, store_id, usuario_id, motivo, monto, estado_evaluacion, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(6))'
        );
        $stmt->execute([$orderId, $storeId, $userId, $motivo, $monto, $estadoEval]);
        return (int) Database::pdo()->lastInsertId();
    }

    public function insertarItemDevolucion(
        int $returnId,
        int $orderItemId,
        int $lotId,
        int $productId,
        int $cantidad,
        string $condicion
    ): void {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO sales_return_items
                (return_id, order_item_id, lot_id, product_id, cantidad, condicion)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$returnId, $orderItemId, $lotId, $productId, $cantidad, $condicion]);
    }

    /** @return array<string,mixed>|null */
    public function buscarDevolucion(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM sales_returns WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return [
            'id' => (int) $row['id'],
            'order_id' => (int) $row['order_id'],
            'store_id' => (int) $row['store_id'],
            'usuario_id' => (int) $row['usuario_id'],
            'motivo' => (string) $row['motivo'],
            'monto' => (float) $row['monto'],
            'estado_evaluacion' => (string) $row['estado_evaluacion'],
            'reembolso_estado' => (string) $row['reembolso_estado'],
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function itemsDevolucion(int $returnId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM sales_return_items WHERE return_id = ? ORDER BY id'
        );
        $stmt->execute([$returnId]);
        return self::cast($stmt->fetchAll(), ['id', 'return_id', 'order_item_id', 'lot_id', 'product_id', 'cantidad']);
    }

    /** @param array<string,string> $filtros ya validados */
    public function listarDevoluciones(array $filtros, int $limit, int $offset): array
    {
        [$where, $params] = $this->where($filtros, ['order_id' => 'order_id', 'store_id' => 'store_id']);
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM sales_returns{$where} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return self::cast($stmt->fetchAll(), ['id', 'order_id', 'store_id', 'usuario_id']);
    }

    /** @param array<string,string> $filtros ya validados */
    public function contarDevoluciones(array $filtros): int
    {
        [$where, $params] = $this->where($filtros, ['order_id' => 'order_id', 'store_id' => 'store_id']);
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM sales_returns{$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    // ------------------------------------------------------------- privados

    private function existe(string $tabla, int $id): bool
    {
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM {$tabla} WHERE id = ?");
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @param array<string,string> $map clave de filtros => expresión SQL
     * @return array{0:string,1:list<mixed>}
     */
    private function where(array $filtros, array $map): array
    {
        $parts = [];
        $params = [];
        foreach ($map as $key => $expr) {
            if (!isset($filtros[$key]) || $filtros[$key] === '') {
                continue;
            }
            $parts[] = "{$expr} = ?";
            $params[] = $filtros[$key];
        }
        return [$parts === [] ? '' : ' WHERE ' . implode(' AND ', $parts), $params];
    }

    /** @param list<string> $enteros @return array<string,mixed> */
    private static function castUno(array $row, array $enteros): array
    {
        foreach ($enteros as $k) {
            if (array_key_exists($k, $row) && $row[$k] !== null) {
                $row[$k] = (int) $row[$k];
            }
        }
        if (array_key_exists('total', $row) && $row['total'] !== null) {
            $row['total'] = (float) $row['total'];
        }
        if (array_key_exists('monto', $row) && $row['monto'] !== null) {
            $row['monto'] = (float) $row['monto'];
        }
        return $row;
    }

    /** @param list<string> $enteros @return list<array<string,mixed>> */
    private static function cast(array $rows, array $enteros): array
    {
        return array_map(static fn (array $r): array => self::castUno($r, $enteros), $rows);
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private static function castOrden(array $row): array
    {
        return self::castUno($row, ['id', 'store_id', 'register_id', 'paciente_id', 'usuario_id', 'quimico_verificador_id']);
    }
}
