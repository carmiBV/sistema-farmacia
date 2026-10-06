<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

/**
 * Acceso a recetas médicas (CP-BACK-07): RF-052 (registro de recetas) y
 * RF-053 (saldo global por receta con CAS, RNF-021). RN-01/RN-051 (stock)
 * quedan para CP-08. Nunca DELETE FROM.
 */
final class RecetaRepository
{
    // ------------------------------------------------------------ referencias

    /** @return string|null estado del paciente; null si no existe */
    public function estadoPaciente(int $id): ?string
    {
        return $this->estadoDe('catalog_patients', $id);
    }

    /** @return string|null estado del prescriptor; null si no existe */
    public function estadoPrescriptor(int $id): ?string
    {
        return $this->estadoDe('catalog_prescribers', $id);
    }

    /** @return array{estado:string,condicion_venta:string}|null */
    public function producto(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT estado, condicion_venta FROM catalog_products WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return ['estado' => (string) $row['estado'], 'condicion_venta' => (string) $row['condicion_venta']];
    }

    private function estadoDe(string $tabla, int $id): ?string
    {
        $stmt = Database::pdo()->prepare("SELECT estado FROM {$tabla} WHERE id = ?");
        $stmt->execute([$id]);
        $v = $stmt->fetchColumn();
        return $v === false ? null : (string) $v;
    }

    // ------------------------------------------------------------- escritura

    public function insertarReceta(int $prescriberId, int $patientId, string $fecha, ?string $numeroRef): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO rx_prescriptions (prescriber_id, patient_id, fecha, numero_referencia)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$prescriberId, $patientId, $fecha, $numeroRef]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** @param list<array{product_id:int,cantidad_prescrita:int}> $items */
    public function insertarItems(int $recetaId, array $items): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO rx_prescription_items (prescription_id, product_id, cantidad_prescrita)
             VALUES (?, ?, ?)'
        );
        foreach ($items as $item) {
            $stmt->execute([$recetaId, $item['product_id'], $item['cantidad_prescrita']]);
        }
    }

    /**
     * RF-053: incremento condicionado del saldo dispensado (CAS sin versión
     * del cliente). false si otro movimiento consumió el saldo o supera lo
     * prescrito (también lo garantiza chk_rxpi_saldo).
     */
    public function aumentarDispensado(int $rxItemId, int $cantidad): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE rx_prescription_items
                SET cantidad_dispensada = cantidad_dispensada + ?, version = version + 1
              WHERE id = ? AND cantidad_dispensada + ? <= cantidad_prescrita'
        );
        $stmt->execute([$cantidad, $rxItemId, $cantidad]);
        return $stmt->rowCount() > 0;
    }

    // ------------------------------------------------------------- lectura

    /** @param array<string,mixed> $f patient_id, prescriber_id */
    public function listar(array $f, int $limit, int $offset): array
    {
        [$where, $params] = $this->where($f);
        $stmt = Database::pdo()->prepare(
            "SELECT r.id, r.prescriber_id, pr.nombre AS prescriber_nombre,
                    r.patient_id, pa.nombre AS patient_nombre,
                    r.fecha, r.numero_referencia, r.created_at
             FROM rx_prescriptions r
             JOIN catalog_prescribers pr ON pr.id = r.prescriber_id
             JOIN catalog_patients pa ON pa.id = r.patient_id
             {$where}
             ORDER BY r.id DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return self::cast($stmt->fetchAll(), ['id', 'prescriber_id', 'patient_id']);
    }

    /** @param array<string,mixed> $f */
    public function contar(array $f): int
    {
        [$where, $params] = $this->where($f);
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM rx_prescriptions r {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @return array<string,mixed>|null */
    public function buscarReceta(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT r.id, r.prescriber_id, pr.nombre AS prescriber_nombre,
                    r.patient_id, pa.nombre AS patient_nombre,
                    r.fecha, r.numero_referencia, r.created_at
             FROM rx_prescriptions r
             JOIN catalog_prescribers pr ON pr.id = r.prescriber_id
             JOIN catalog_patients pa ON pa.id = r.patient_id
             WHERE r.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return self::castUno($row, ['id', 'prescriber_id', 'patient_id']);
    }

    /** @return list<array<string,mixed>> ítems con saldo calculado (RF-053) */
    public function itemsReceta(int $recetaId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT i.id, i.product_id, p.nombre AS product_nombre, p.sku,
                    i.cantidad_prescrita, i.cantidad_dispensada,
                    (i.cantidad_prescrita - i.cantidad_dispensada) AS saldo, i.version
             FROM rx_prescription_items i
             JOIN catalog_products p ON p.id = i.product_id
             WHERE i.prescription_id = ?
             ORDER BY i.id ASC'
        );
        $stmt->execute([$recetaId]);
        return self::cast($stmt->fetchAll(), [
            'id', 'product_id', 'cantidad_prescrita', 'cantidad_dispensada', 'saldo', 'version',
        ]);
    }

    /** @return array{id:int,prescription_id:int,product_id:int,cantidad_prescrita:int,cantidad_dispensada:int}|null */
    public function itemDeReceta(int $recetaId, int $rxItemId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, prescription_id, product_id, cantidad_prescrita, cantidad_dispensada
             FROM rx_prescription_items WHERE id = ? AND prescription_id = ?'
        );
        $stmt->execute([$rxItemId, $recetaId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return self::castUno($row, ['id', 'prescription_id', 'product_id', 'cantidad_prescrita', 'cantidad_dispensada']);
    }

    // -------------------------------------------------------------- helpers

    /** @param array<string,mixed> $f @return array{0:string,1:list<mixed>} */
    private function where(array $f): array
    {
        $map = ['patient_id' => 'r.patient_id', 'prescriber_id' => 'r.prescriber_id'];
        $parts = [];
        $params = [];
        foreach ($map as $key => $expr) {
            if (!isset($f[$key]) || $f[$key] === '') {
                continue;
            }
            $parts[] = "{$expr} = ?";
            $params[] = $f[$key];
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
}
