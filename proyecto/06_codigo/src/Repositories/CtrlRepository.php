<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

/**
 * Repositorio del Libro Oficial de Controlados (RF-055) y saldos permanentes.
 * `ctrl_ledger_entries` es append-only (RNF-046): solo INSERT, nunca UPDATE/DELETE.
 * `ctrl_balances` materializa el saldo por (sucursal, producto), conciliable con
 * la suma de asientos (RNF-024).
 */
final class CtrlRepository
{
    /**
     * Saldo reconstruido desde el libro: el ultimo saldo_resultante de la cadena
     * de asientos equivale a la suma firmada de todos ellos (cada asiento la
     * fija bajo lock en insertarAsiento). Se compara contra ctrl_balances.saldo
     * para conciliar las dos copias del saldo (RNF-024).
     */
    private const SALDO_CALCULADO = 'IFNULL((
                 SELECT l.saldo_resultante
                   FROM ctrl_ledger_entries l
                  WHERE l.store_id = b.store_id AND l.product_id = b.product_id
                  ORDER BY l.id DESC LIMIT 1
               ), 0) AS saldo_calculado';

    public function productoEsControlado(int $productId): bool
    {
        $stmt = Database::pdo()->prepare(
            "SELECT condicion_venta FROM catalog_products WHERE id = ? AND estado = 'activo'"
        );
        $stmt->execute([$productId]);
        $v = $stmt->fetchColumn();
        return $v !== false && (string) $v === 'controlado';
    }

    public function productoExiste(int $productId): bool
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM catalog_products WHERE id = ?');
        $stmt->execute([$productId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Saldo materializado en ctrl_balances (0 si nunca hubo asientos). */
    public function saldoDe(int $storeId, int $productId): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT saldo FROM ctrl_balances WHERE store_id = ? AND product_id = ?'
        );
        $stmt->execute([$storeId, $productId]);
        $v = $stmt->fetchColumn();
        return $v === false ? 0 : (int) $v;
    }

    /**
     * Saldo reconstruido desde el libro: la cadena de saldo_resultante de los
     * asientos es la suma firmada acumulada (el tipo 'ajuste' no guarda signo
     * explicito, pero cada asiento fija su saldo_resultante bajo lock).
     */
    public function saldoCalculado(int $storeId, int $productId): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT saldo_resultante FROM ctrl_ledger_entries
              WHERE store_id = ? AND product_id = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$storeId, $productId]);
        $v = $stmt->fetchColumn();
        return $v === false ? 0 : (int) $v;
    }

    /**
     * Asiento append-only + saldo permanente en una sola transaccion logica
     * (se ejecuta dentro de la transaccion del llamador).
     *
     * @param array{store_id:int,product_id:int,tipo:string,cantidad:int,signo:int,
     *   usuario_id:int,autorizador_id:?int,motivo:string,ref_tipo:string,ref_id:int} $e
     * @throws \App\Http\AppException BALANCE_NEGATIVE (409) si el saldo quedaria < 0
     */
    public function insertarAsiento(array $e): int
    {
        $pdo = Database::pdo();

        // Lock del saldo actual: serializa asientos concurrentes del par (sucursal, producto).
        $stmt = $pdo->prepare(
            'SELECT saldo FROM ctrl_balances WHERE store_id = ? AND product_id = ? FOR UPDATE'
        );
        $stmt->execute([$e['store_id'], $e['product_id']]);
        $previo = (int) ($stmt->fetchColumn() ?: 0);

        $nuevo = $previo + ($e['signo'] * $e['cantidad']);
        if ($nuevo < 0) {
            throw new \App\Http\AppException(
                'BALANCE_NEGATIVE',
                'El saldo del libro de controlados no puede quedar negativo.',
                409
            );
        }

        $ins = $pdo->prepare(
            'INSERT INTO ctrl_ledger_entries
                (store_id, product_id, tipo, cantidad, saldo_resultante, usuario_id,
                 autorizador_id, motivo, ref_tipo, ref_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $e['store_id'],
            $e['product_id'],
            $e['tipo'],
            $e['cantidad'],
            $nuevo,
            $e['usuario_id'],
            $e['autorizador_id'],
            $e['motivo'],
            $e['ref_tipo'],
            $e['ref_id'],
        ]);
        $asientoId = (int) $pdo->lastInsertId();

        $bal = $pdo->prepare(
            'INSERT INTO ctrl_balances (store_id, product_id, saldo, version)
             VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE saldo = ?, version = version + 1, updated_at = NOW(6)'
        );
        $bal->execute([$e['store_id'], $e['product_id'], $nuevo, $nuevo]);

        return $asientoId;
    }

    /** @return array<string,mixed>|null */
    public function buscarAsiento(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM ctrl_ledger_entries WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : self::cast($row);
    }

    /** Asiento generado por un movimiento concreto (para devolverlo al cliente). */
    public function buscarAsientoPorRef(string $refTipo, int $refId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM ctrl_ledger_entries WHERE ref_tipo = ? AND ref_id = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$refTipo, $refId]);
        $row = $stmt->fetch();
        return $row === false ? null : self::cast($row);
    }

    /** @param array<string,string> $filtros ya validados */
    public function listarAsientos(array $filtros, int $limit, int $offset): array
    {
        [$where, $params] = $this->whereAsientos($filtros);
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM ctrl_ledger_entries{$where} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return array_map(self::cast(...), $stmt->fetchAll());
    }

    /** @param array<string,string> $filtros ya validados */
    public function contarAsientos(array $filtros): int
    {
        [$where, $params] = $this->whereAsientos($filtros);
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM ctrl_ledger_entries{$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Saldos con su conciliacion: saldo materializado vs suma de asientos.
     *
     * @param array<string,string> $filtros
     * @return list<array{store_id:int,product_id:int,saldo:int,saldo_calculado:int,conciliado:bool}>
     */
    public function listarSaldos(array $filtros, int $limit, int $offset): array
    {
        [$where, $params] = $this->whereSaldos($filtros);
        $sql = 'SELECT b.store_id, b.product_id, b.saldo, ' . self::SALDO_CALCULADO . '
                  FROM ctrl_balances b'
            . $where
            . ' ORDER BY b.store_id, b.product_id'
            . " LIMIT {$limit} OFFSET {$offset}";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $saldo = (int) $r['saldo'];
            $calc = (int) $r['saldo_calculado'];
            $out[] = [
                'store_id' => (int) $r['store_id'],
                'product_id' => (int) $r['product_id'],
                'saldo' => $saldo,
                'saldo_calculado' => $calc,
                'conciliado' => $saldo === $calc,
            ];
        }
        return $out;
    }

    /** @param array<string,string> $filtros */
    public function contarSaldos(array $filtros): int
    {
        [$where, $params] = $this->whereSaldos($filtros);
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM ctrl_balances b{$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @return array{total:int,conciliados:int,desbalanceados:int,diferencias:list<array<string,int|bool>>} */
    public function conciliacion(): array
    {
        $sql = 'SELECT b.store_id, b.product_id, b.saldo, ' . self::SALDO_CALCULADO . '
                  FROM ctrl_balances b';
        $total = 0;
        $dif = [];
        foreach (Database::pdo()->query($sql) as $r) {
            $total++;
            $saldo = (int) $r['saldo'];
            $calc = (int) $r['saldo_calculado'];
            if ($saldo !== $calc) {
                $dif[] = [
                    'store_id' => (int) $r['store_id'],
                    'product_id' => (int) $r['product_id'],
                    'saldo' => $saldo,
                    'saldo_calculado' => $calc,
                    'conciliado' => false,
                ];
            }
        }
        return [
            'total' => $total,
            'conciliados' => $total - count($dif),
            'desbalanceados' => count($dif),
            'diferencias' => $dif,
        ];
    }

    // ------------------------------------------------------------ privados

    /** @return array{0:string,1:list<mixed>} */
    private function whereAsientos(array $f): array
    {
        $parts = [];
        $params = [];
        foreach (['store_id' => 'store_id', 'product_id' => 'product_id', 'tipo' => 'tipo', 'ref_tipo' => 'ref_tipo'] as $k => $col) {
            if (!isset($f[$k]) || $f[$k] === '') {
                continue;
            }
            $parts[] = "{$col} = ?";
            $params[] = $f[$k];
        }
        if (isset($f['desde'])) {
            $parts[] = 'created_at >= ?';
            $params[] = $f['desde'] . ' 00:00:00';
        }
        if (isset($f['hasta'])) {
            $parts[] = 'created_at < ?';
            $params[] = date('Y-m-d', strtotime($f['hasta'] . ' +1 day')) . ' 00:00:00';
        }
        return [$parts === [] ? '' : ' WHERE ' . implode(' AND ', $parts), $params];
    }

    /** @return array{0:string,1:list<mixed>} */
    private function whereSaldos(array $f): array
    {
        $parts = [];
        $params = [];
        foreach (['store_id' => 'b.store_id', 'product_id' => 'b.product_id'] as $k => $col) {
            if (!isset($f[$k]) || $f[$k] === '') {
                continue;
            }
            $parts[] = "{$col} = ?";
            $params[] = $f[$k];
        }
        return [$parts === [] ? '' : ' WHERE ' . implode(' AND ', $parts), $params];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private static function cast(array $row): array
    {
        foreach (['id', 'store_id', 'product_id', 'cantidad', 'saldo_resultante', 'usuario_id', 'autorizador_id', 'ref_id'] as $k) {
            if (array_key_exists($k, $row) && $row[$k] !== null) {
                $row[$k] = (int) $row[$k];
            }
        }
        return $row;
    }
}
