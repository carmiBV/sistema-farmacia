<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Acceso a inventario FEFO (CP-BACK-06): stock por lote, kárdex append-only,
 * transferencias, alertas RF-044, incidentes RF-047 y reservas DB-P01.
 * Devuelve arrays (sin modelos). Nunca DELETE FROM en tablas de negocio.
 */
final class InventarioRepository
{
    // ---------------------------------------------------------- referencias

    public function storeExiste(int $id): bool
    {
        return $this->existe('ops_stores', $id);
    }

    public function productoExiste(int $id): bool
    {
        return $this->existe('catalog_products', $id);
    }

    private function existe(string $tabla, int $id): bool
    {
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM {$tabla} WHERE id = ?");
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    // --------------------------------------------------------------- lotes

    /** @return array{id:int,product_id:int,numero_lote:string,fecha_vencimiento:string,estado:string}|null */
    public function buscarLote(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, product_id, numero_lote, fecha_vencimiento, estado
             FROM inventory_lots WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return self::castUno($row, ['id', 'product_id']);
    }

    /** RF-032 / RN-03: solo de cuarentena a liberado. */
    public function liberarLote(int $id, string $motivo, int $userId): bool
    {
        $stmt = Database::pdo()->prepare(
            "UPDATE inventory_lots
                SET estado = 'liberado', liberado_por = ?, liberado_at = NOW(6), motivo_liberacion = ?
              WHERE id = ? AND estado = 'cuarentena'"
        );
        $stmt->execute([$userId, $motivo, $id]);
        return $stmt->rowCount() > 0;
    }

    // --------------------------------------------------------------- stock

    public function stockDisponible(int $storeId, int $lotId, bool $lock = false): int
    {
        $sql = 'SELECT stock_available FROM inventory_stock WHERE store_id = ? AND lot_id = ?'
            . ($lock ? ' FOR UPDATE' : '');
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([$storeId, $lotId]);
        $v = $stmt->fetchColumn();
        return $v === false ? 0 : (int) $v;
    }

    /** RN-02: descuento condicionado; false si hay carrera (stock insuficiente). */
    public function descontarStock(int $storeId, int $lotId, int $qty): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE inventory_stock
                SET stock_available = stock_available - ?, version = version + 1
              WHERE store_id = ? AND lot_id = ? AND stock_available >= ?'
        );
        $stmt->execute([$qty, $storeId, $lotId, $qty]);
        return $stmt->rowCount() > 0;
    }

    public function sumarStock(int $storeId, int $lotId, int $qty): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO inventory_stock (store_id, lot_id, stock_available, stock_reserved, stock_sold, version)
             VALUES (?, ?, ?, 0, 0, 1)
             ON DUPLICATE KEY UPDATE stock_available = stock_available + ?, version = version + 1'
        );
        $stmt->execute([$storeId, $lotId, $qty, $qty]);
    }

    /** Disponible agregado FEFO: lotes liberados no vencidos (RN-03). */
    public function stockDisponibleFEFO(int $storeId, int $productId): int
    {
        $stmt = Database::pdo()->prepare(
            "SELECT IFNULL(SUM(s.stock_available), 0)
             FROM inventory_stock s
             JOIN inventory_lots l ON l.id = s.lot_id
             WHERE s.store_id = ? AND l.product_id = ?
               AND l.estado = 'liberado' AND l.fecha_vencimiento >= CURDATE()"
        );
        $stmt->execute([$storeId, $productId]);
        return (int) $stmt->fetchColumn();
    }

    // -------------------------------------------------------------- kárdex

    /**
     * Kárdex append-only (RF-045/RNF-030): insertar nada más.
     * proponente_id/autorizador_id opcionales (RF-046 doble autorización).
     *
     * @param array{store_id:int,lot_id:int,product_id:int,tipo:string,cantidad:int,
     *   signo:int,usuario_id:int,motivo:string,ref_tipo:string,ref_id:int,
     *   proponente_id?:int,autorizador_id?:int} $m
     */
    public function insertarMovimiento(array $m): int
    {
        $conAutorizacion = isset($m['proponente_id'], $m['autorizador_id']);
        $cols = 'store_id, lot_id, product_id, tipo, cantidad, signo, usuario_id, motivo, ref_tipo, ref_id'
            . ($conAutorizacion ? ', proponente_id, autorizador_id' : '');
        $marks = $conAutorizacion ? str_repeat('?, ', 10) . '?, ?' : str_repeat('?, ', 9) . '?';
        $stmt = Database::pdo()->prepare(
            "INSERT INTO inventory_movements ({$cols}) VALUES ({$marks})"
        );
        $params = [
            $m['store_id'],
            $m['lot_id'],
            $m['product_id'],
            $m['tipo'],
            $m['cantidad'],
            $m['signo'],
            $m['usuario_id'],
            $m['motivo'],
            $m['ref_tipo'],
            $m['ref_id'],
        ];
        if ($conAutorizacion) {
            $params[] = $m['proponente_id'];
            $params[] = $m['autorizador_id'];
        }
        $stmt->execute($params);
        $movId = (int) Database::pdo()->lastInsertId();
        $this->registrarAsientoControlado($m, $movId);
        return $movId;
    }

    // ------------------------------------------- libro de controlados (RF-055)

    /** Tipo de asiento y sentido del saldo por movimiento de inventario. */
    private const LEDGER_TIPO = [
        'entrada' => ['entrada', 1],
        'salida' => ['salida', -1],
        'devolucion' => ['devolucion', 1],
        'transferencia_salida' => ['transferencia_out', -1],
        'transferencia_entrada' => ['transferencia_in', 1],
        'ajuste_baja' => ['ajuste', -1],
        'ajuste_incremento' => ['ajuste', 1],
        // RN-10: la anulacion repone unidades -> asiento de devolucion.
        'compensatorio' => ['devolucion', 1],
    ];

    /** ref_tipo de inventario -> ref_tipo del libro (enum propio de ctrl_ledger_entries). */
    private const LEDGER_REF = [
        'venta' => 'venta',
        'recepcion' => 'recepcion',
        'transferencia' => 'transferencia',
        'devolucion' => 'devolucion',
        'incidente' => 'ajuste',
        'manual' => 'ajuste',
    ];

    /**
     * RF-055: todo movimiento de un medicamento controlado lleva asiento en el
     * Libro Oficial, dentro de la MISMA transaccion que el movimiento (RNF-021).
     * El trigger trg_ledger_vs_stock exige que el movimiento ya exista al insertar
     * asientos de tipo 'salida'.
     */
    private function registrarAsientoControlado(array $m, int $movId): void
    {
        $map = self::LEDGER_TIPO[$m['tipo']] ?? null;
        if ($map === null) {
            return;
        }
        $ctrl = new CtrlRepository();
        if (!$ctrl->productoEsControlado((int) $m['product_id'])) {
            return;
        }
        [$tipo, $signo] = $map;
        $ctrl->insertarAsiento([
            'store_id' => (int) $m['store_id'],
            'product_id' => (int) $m['product_id'],
            'tipo' => $tipo,
            'cantidad' => (int) $m['cantidad'],
            'signo' => $signo,
            'usuario_id' => (int) $m['usuario_id'],
            'autorizador_id' => isset($m['autorizador_id']) ? (int) $m['autorizador_id'] : null,
            'motivo' => (string) $m['motivo'],
            'ref_tipo' => self::LEDGER_REF[$m['ref_tipo'] ?? ''] ?? 'ajuste',
            'ref_id' => ((int) ($m['ref_id'] ?? 0)) ?: $movId,
        ]);
    }

    // ------------------------------------------------------------ listados

    /**
     * WHERE generado de filtros ya validados por el service.
     *
     * @param array<string,string> $map clave de query => expresión SQL
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

    /**
     * @param list<string> $enteros
     * @return list<array<string,mixed>>
     */
    private static function cast(array $rows, array $enteros): array
    {
        return array_map(static fn (array $r): array => self::castUno($r, $enteros), $rows);
    }

    /**
     * @param list<string> $enteros
     * @return array<string,mixed>
     */
    private static function castUno(array $row, array $enteros): array
    {
        foreach ($enteros as $k) {
            if (isset($row[$k]) && !is_int($row[$k]) && preg_match('/^-?\d+$/', (string) $row[$k]) === 1) {
                $row[$k] = (int) $row[$k];
            }
        }
        return $row;
    }

    /**
     * @param array<string,mixed> $f store_id, product_id, lot_id, estado
     * @return list<array<string,mixed>>
     */
    public function listarStocks(array $f, int $limit, int $offset): array
    {
        [$where, $params] = $this->where($f, [
            'store_id' => 's.store_id',
            'product_id' => 'l.product_id',
            'lot_id' => 's.lot_id',
            'estado' => 'l.estado',
        ]);
        $stmt = Database::pdo()->prepare(
            "SELECT s.store_id, st.nombre AS store_nombre, s.lot_id, l.numero_lote,
                    l.product_id, p.nombre AS product_nombre, l.fecha_vencimiento,
                    l.estado AS lote_estado, s.stock_available, s.stock_reserved,
                    s.stock_sold, s.version
             FROM inventory_stock s
             JOIN inventory_lots l ON l.id = s.lot_id
             JOIN catalog_products p ON p.id = l.product_id
             JOIN ops_stores st ON st.id = s.store_id
             {$where}
             ORDER BY p.nombre ASC, l.fecha_vencimiento ASC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return self::cast($stmt->fetchAll(), [
            'store_id', 'lot_id', 'product_id', 'stock_available', 'stock_reserved', 'stock_sold', 'version',
        ]);
    }

    /** @param array<string,mixed> $f */
    public function contarStocks(array $f): int
    {
        [$where, $params] = $this->where($f, [
            'store_id' => 's.store_id',
            'product_id' => 'l.product_id',
            'lot_id' => 's.lot_id',
            'estado' => 'l.estado',
        ]);
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*)
             FROM inventory_stock s
             JOIN inventory_lots l ON l.id = s.lot_id
             {$where}"
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @param array<string,mixed> $f store_id, lot_id, product_id, tipo */
    public function listarMovimientos(array $f, int $limit, int $offset): array
    {
        [$where, $params] = $this->where($f, [
            'store_id' => 'm.store_id',
            'lot_id' => 'm.lot_id',
            'product_id' => 'm.product_id',
            'tipo' => 'm.tipo',
        ]);
        $stmt = Database::pdo()->prepare(
            "SELECT m.id, m.store_id, m.lot_id, m.product_id, p.nombre AS product_nombre,
                    m.tipo, m.cantidad, m.signo, m.usuario_id, u.usuario, m.motivo,
                    m.ref_tipo, m.ref_id, m.created_at
             FROM inventory_movements m
             JOIN catalog_products p ON p.id = m.product_id
             JOIN auth_users u ON u.id = m.usuario_id
             {$where}
             ORDER BY m.id DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return self::cast($stmt->fetchAll(), [
            'id', 'store_id', 'lot_id', 'product_id', 'cantidad', 'signo', 'usuario_id', 'ref_id',
        ]);
    }

    /** @param array<string,mixed> $f */
    public function contarMovimientos(array $f): int
    {
        [$where, $params] = $this->where($f, [
            'store_id' => 'm.store_id',
            'lot_id' => 'm.lot_id',
            'product_id' => 'm.product_id',
            'tipo' => 'm.tipo',
        ]);
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM inventory_movements m {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** Lotes con stock agregado por tienda (panel FEFO). @param array<string,mixed> $f product_id, estado */
    public function listarLotes(array $f, int $limit, int $offset): array
    {
        [$where, $params] = $this->where($f, [
            'product_id' => 'l.product_id',
            'estado' => 'l.estado',
            'lot_id' => 'l.id',
        ]);
        $stmt = Database::pdo()->prepare(
            "SELECT l.id, l.product_id, p.nombre AS product_nombre, l.numero_lote,
                    l.fecha_vencimiento, l.estado, l.reception_item_id, l.liberado_por,
                    l.liberado_at, l.motivo_liberacion,
                    IFNULL(st.sa, 0) AS stock_available, IFNULL(st.sr, 0) AS stock_reserved
             FROM inventory_lots l
             JOIN catalog_products p ON p.id = l.product_id
             LEFT JOIN (
                 SELECT lot_id, SUM(stock_available) AS sa, SUM(stock_reserved) AS sr
                 FROM inventory_stock GROUP BY lot_id
             ) st ON st.lot_id = l.id
             {$where}
             ORDER BY l.fecha_vencimiento ASC, l.id ASC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return self::cast($stmt->fetchAll(), [
            'id', 'product_id', 'reception_item_id', 'liberado_por', 'stock_available', 'stock_reserved',
        ]);
    }

    /** @param array<string,mixed> $f */
    public function contarLotes(array $f): int
    {
        [$where, $params] = $this->where($f, [
            'product_id' => 'l.product_id',
            'estado' => 'l.estado',
            'lot_id' => 'l.id',
        ]);
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM inventory_lots l {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @param array<string,mixed> $f store_origen_id, store_destino_id, estado */
    public function listarTransferencias(array $f, int $limit, int $offset): array
    {
        [$where, $params] = $this->where($f, [
            'store_origen_id' => 't.store_origen_id',
            'store_destino_id' => 't.store_destino_id',
            'estado' => 't.estado',
        ]);
        $stmt = Database::pdo()->prepare(
            "SELECT t.id, t.store_origen_id, so.nombre AS origen_nombre,
                    t.store_destino_id, sd.nombre AS destino_nombre, t.estado,
                    t.solicitado_por, t.despachado_por, t.recibido_por,
                    t.created_at, t.updated_at
             FROM inventory_transfers t
             JOIN ops_stores so ON so.id = t.store_origen_id
             JOIN ops_stores sd ON sd.id = t.store_destino_id
             {$where}
             ORDER BY t.id DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return self::cast($stmt->fetchAll(), [
            'id', 'store_origen_id', 'store_destino_id', 'solicitado_por', 'despachado_por', 'recibido_por',
        ]);
    }

    /** @param array<string,mixed> $f */
    public function contarTransferencias(array $f): int
    {
        [$where, $params] = $this->where($f, [
            'store_origen_id' => 't.store_origen_id',
            'store_destino_id' => 't.store_destino_id',
            'estado' => 't.estado',
        ]);
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM inventory_transfers t {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @param array<string,mixed> $f tipo, estado, product_id, store_id */
    public function listarAlertas(array $f, int $limit, int $offset): array
    {
        [$where, $params] = $this->where($f, [
            'tipo' => 'a.tipo',
            'estado' => 'a.estado',
            'product_id' => 'a.product_id',
            'store_id' => 'a.store_id',
        ]);
        $stmt = Database::pdo()->prepare(
            "SELECT a.id, a.tipo, a.product_id, p.nombre AS product_nombre, a.store_id,
                    a.fecha_generada, a.estado, a.resuelta_por, a.resuelta_at
             FROM inventory_alerts a
             JOIN catalog_products p ON p.id = a.product_id
             {$where}
             ORDER BY a.id DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return self::cast($stmt->fetchAll(), ['id', 'product_id', 'store_id', 'resuelta_por']);
    }

    /** @param array<string,mixed> $f */
    public function contarAlertas(array $f): int
    {
        [$where, $params] = $this->where($f, [
            'tipo' => 'a.tipo',
            'estado' => 'a.estado',
            'product_id' => 'a.product_id',
            'store_id' => 'a.store_id',
        ]);
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM inventory_alerts a {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @param array<string,mixed> $f estado, store_id, lot_id */
    public function listarIncidentes(array $f, int $limit, int $offset): array
    {
        [$where, $params] = $this->where($f, [
            'estado' => 'i.estado',
            'store_id' => 'i.store_id',
            'lot_id' => 'i.lot_id',
        ]);
        $stmt = Database::pdo()->prepare(
            "SELECT i.id, i.store_id, st.nombre AS store_nombre, i.lot_id, l.numero_lote,
                    i.stock_registrado, i.consumo_declarado, i.estado, i.abierto_por,
                    u.usuario, i.created_at, i.movimiento_ajuste_id
             FROM inventory_incidents i
             JOIN ops_stores st ON st.id = i.store_id
             JOIN inventory_lots l ON l.id = i.lot_id
             JOIN auth_users u ON u.id = i.abierto_por
             {$where}
             ORDER BY i.id DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return self::cast($stmt->fetchAll(), [
            'id', 'store_id', 'lot_id', 'stock_registrado', 'consumo_declarado', 'abierto_por', 'movimiento_ajuste_id',
        ]);
    }

    /** @param array<string,mixed> $f */
    public function contarIncidentes(array $f): int
    {
        [$where, $params] = $this->where($f, [
            'estado' => 'i.estado',
            'store_id' => 'i.store_id',
            'lot_id' => 'i.lot_id',
        ]);
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM inventory_incidents i {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @param array<string,mixed> $f status, store_id, product_id */
    public function listarReservas(array $f, int $limit, int $offset): array
    {
        [$where, $params] = $this->where($f, [
            'status' => 'r.status',
            'store_id' => 'r.store_id',
            'product_id' => 'r.product_id',
        ]);
        $stmt = Database::pdo()->prepare(
            "SELECT r.id, r.order_id, r.product_id, p.nombre AS product_nombre, r.store_id,
                    r.qty, r.status, r.expires_at, r.created_at
             FROM inventory_reservations r
             JOIN catalog_products p ON p.id = r.product_id
             {$where}
             ORDER BY r.id DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return self::cast($stmt->fetchAll(), ['id', 'order_id', 'product_id', 'store_id', 'qty']);
    }

    /** @param array<string,mixed> $f */
    public function contarReservas(array $f): int
    {
        [$where, $params] = $this->where($f, [
            'status' => 'r.status',
            'store_id' => 'r.store_id',
            'product_id' => 'r.product_id',
        ]);
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM inventory_reservations r {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    // -------------------------------------------------------- transferencias

    /** @return array<string,mixed>|null */
    public function buscarTransferencia(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM inventory_transfers WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return self::castUno($row, ['id', 'store_origen_id', 'store_destino_id', 'solicitado_por', 'despachado_por', 'recibido_por']);
    }

    /** @return array<string,mixed>|null */
    public function buscarTransferenciaPorIdempotencyKey(string $key): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM inventory_transfers WHERE idempotency_key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row === false ? null : self::castUno($row, ['id', 'store_origen_id', 'store_destino_id', 'solicitado_por']);
    }

    public function insertarTransferencia(int $origen, int $destino, ?string $key, int $userId): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO inventory_transfers
                (store_origen_id, store_destino_id, estado, solicitado_por, idempotency_key)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$origen, $destino, 'solicitada', $userId, $key]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** @param list<array{lot_id:int,cantidad:int}> $items Cantidad solicitada en cantidad_despachada (supuesto CP-06). */
    public function insertarItemsTransferencia(int $transferId, array $items): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO inventory_transfer_items (transfer_id, lot_id, cantidad_despachada)
             VALUES (?, ?, ?)'
        );
        foreach ($items as $item) {
            $stmt->execute([$transferId, $item['lot_id'], $item['cantidad']]);
        }
    }

    /** @return list<array<string,mixed>> */
    public function itemsTransferencia(int $id): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT ti.lot_id, l.product_id, l.numero_lote, l.fecha_vencimiento, l.estado,
                    ti.cantidad_despachada, ti.cantidad_recibida
             FROM inventory_transfer_items ti
             JOIN inventory_lots l ON l.id = ti.lot_id
             WHERE ti.transfer_id = ?
             ORDER BY ti.id ASC'
        );
        $stmt->execute([$id]);
        return self::cast($stmt->fetchAll(), [
            'lot_id', 'product_id', 'cantidad_despachada', 'cantidad_recibida',
        ]);
    }

    /**
     * Transición CAS por estado (RN-08). $colUsuario sólo columnas fijas
     * ('', 'despachado_por', 'recibido_por').
     */
    public function cambiarEstadoTransferencia(
        int $id,
        string $esperado,
        string $nuevo,
        string $colUsuario,
        int $userId
    ): bool {
        $sql = 'UPDATE inventory_transfers SET estado = ?';
        $params = [$nuevo];
        if ($colUsuario !== '') {
            $sql .= ", {$colUsuario} = ?";
            $params[] = $userId;
        }
        $sql .= ' WHERE id = ? AND estado = ?';
        $params[] = $id;
        $params[] = $esperado;
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    /** @param array{lot_id:int,cantidad_recibida:int} $item */
    public function marcarRecibidaItem(int $transferId, array $item): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE inventory_transfer_items
                SET cantidad_recibida = ?
              WHERE transfer_id = ? AND lot_id = ?'
        );
        $stmt->execute([$item['cantidad_recibida'], $transferId, $item['lot_id']]);
    }

    // -------------------------------------------------------------- alertas

    /** Dedup: alerta abierta por (tipo, producto, tienda) — F12. */
    public function alertaAbiertaExiste(string $tipo, int $productId, ?int $storeId): bool
    {
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM inventory_alerts
             WHERE tipo = ? AND product_id = ?
               AND IFNULL(store_id, 0) = IFNULL(?, 0)
               AND estado = 'abierta'"
        );
        $stmt->execute([$tipo, $productId, $storeId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function insertarAlerta(string $tipo, int $productId, ?int $storeId): void
    {
        $stmt = Database::pdo()->prepare(
            "INSERT INTO inventory_alerts (tipo, product_id, store_id, fecha_generada, estado)
             VALUES (?, ?, ?, NOW(6), 'abierta')"
        );
        $stmt->execute([$tipo, $productId, $storeId]);
    }

    /** @return array<string,mixed>|null */
    public function buscarAlerta(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, tipo, product_id, store_id, estado, fecha_generada
             FROM inventory_alerts WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : self::castUno($row, ['id', 'product_id', 'store_id']);
    }

    public function resolverAlerta(int $id, int $userId): bool
    {
        $stmt = Database::pdo()->prepare(
            "UPDATE inventory_alerts
                SET estado = 'resuelta', resuelta_por = ?, resuelta_at = NOW(6)
              WHERE id = ? AND estado = 'abierta'"
        );
        $stmt->execute([$userId, $id]);
        return $stmt->rowCount() > 0;
    }

    /** RF-044: (tienda, producto) con total liberado y no vencido bajo el umbral. */
    public function candidatasStockMinimo(int $umbral): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT s.store_id, l.product_id, SUM(s.stock_available) AS total
             FROM inventory_stock s
             JOIN inventory_lots l ON l.id = s.lot_id
             WHERE l.estado = 'liberado' AND l.fecha_vencimiento >= CURDATE()
             GROUP BY s.store_id, l.product_id
             HAVING SUM(s.stock_available) < ?
             ORDER BY s.store_id, l.product_id"
        );
        $stmt->execute([$umbral]);
        return self::cast($stmt->fetchAll(), ['store_id', 'product_id', 'total']);
    }

    /** RF-044: productos con lotes no retirados por vencer en N días y stock > 0 (alerta global store_id NULL). */
    public function candidatasVencimiento(int $dias): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT DISTINCT l.product_id
             FROM inventory_lots l
             JOIN inventory_stock s ON s.lot_id = l.id
             WHERE l.estado <> 'retirado'
               AND l.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
               AND s.stock_available > 0
             ORDER BY l.product_id"
        );
        $stmt->execute([$dias]);
        return self::cast($stmt->fetchAll(), ['product_id']);
    }

    // ---------------------------------------------------------- incidentes

    public function insertarIncidente(int $storeId, int $lotId, int $stock, int $consumo, int $userId): int
    {
        $stmt = Database::pdo()->prepare(
            "INSERT INTO inventory_incidents
                (store_id, lot_id, stock_registrado, consumo_declarado, estado, abierto_por)
             VALUES (?, ?, ?, ?, 'abierto', ?)"
        );
        $stmt->execute([$storeId, $lotId, $stock, $consumo, $userId]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function buscarIncidente(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, store_id, lot_id, stock_registrado, consumo_declarado, estado, movimiento_ajuste_id, abierto_por
             FROM inventory_incidents WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : self::castUno($row, ['id', 'store_id', 'lot_id', 'stock_registrado', 'consumo_declarado', 'movimiento_ajuste_id', 'abierto_por']);
    }

    public function cambiarEstadoIncidente(int $id, string $esperado, string $nuevo): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE inventory_incidents SET estado = ? WHERE id = ? AND estado = ?'
        );
        $stmt->execute([$nuevo, $id, $esperado]);
        return $stmt->rowCount() > 0;
    }

    public function asignarMovimientoAjuste(int $id, int $movId): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE inventory_incidents SET movimiento_ajuste_id = ? WHERE id = ?'
        );
        $stmt->execute([$movId, $id]);
    }

    // ------------------------------------------------------------- reservas

    public function insertarReserva(int $storeId, int $productId, int $qty, ?int $orderId, string $expiresAt): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO inventory_reservations (order_id, product_id, store_id, qty, status, expires_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$orderId, $productId, $storeId, $qty, 'pending', $expiresAt]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function buscarReserva(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, order_id, product_id, store_id, qty, status, expires_at
             FROM inventory_reservations WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : self::castUno($row, ['id', 'order_id', 'product_id', 'store_id', 'qty']);
    }

    /** CAS de estado (pending → confirmed/expired/cancelled). */
    public function cambiarEstadoReserva(int $id, string $esperado, string $nuevo): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE inventory_reservations SET status = ? WHERE id = ? AND status = ?'
        );
        $stmt->execute([$nuevo, $id, $esperado]);
        return $stmt->rowCount() > 0;
    }
}
