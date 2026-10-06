<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

/**
 * Auditoria y eventos salientes (CP-BACK-10).
 *
 * RNF-046: audit_operations y audit_pii_access son append-only (solo INSERT).
 * outbox_events registra eventos en la misma transaccion del cambio de estado
 * (decision 16 de decisiones_dase_Datos.md) para desacoplar el envio externo.
 */
final class AuditRepository
{
    /** @param array<string,mixed> $a */
    public function insertarOperacion(array $a): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO audit_operations
                (usuario_id, accion, entidad, entidad_id, valores_antes, valores_despues, motivo)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $a['usuario_id'],
            $a['accion'],
            $a['entidad'],
            $a['entidad_id'],
            $a['valores_antes'] === null ? null : json_encode($a['valores_antes'], JSON_UNESCAPED_UNICODE),
            $a['valores_despues'] === null ? null : json_encode($a['valores_despues'], JSON_UNESCAPED_UNICODE),
            $a['motivo'],
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** @param array<string,mixed> $a */
    public function insertarAccesoPii(array $a): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO audit_pii_access
                (usuario_id, accion, paciente_id, prescription_id, motivo)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $a['usuario_id'],
            $a['accion'],
            $a['paciente_id'],
            $a['prescription_id'],
            $a['motivo'],
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function buscarOperacion(int $id): ?array
    {
        return $this->buscar('audit_operations', $id);
    }

    /** @return array<string,mixed>|null */
    public function buscarAccesoPii(int $id): ?array
    {
        return $this->buscar('audit_pii_access', $id);
    }

    /** @return array<string,mixed>|null */
    public function buscarEvento(int $id): ?array
    {
        return $this->buscar('outbox_events', $id);
    }

    /** @param array<string,string> $filtros */
    public function listarOperaciones(array $filtros, int $limit, int $offset): array
    {
        return $this->listar('audit_operations', $filtros, [
            'usuario_id' => 'usuario_id', 'accion' => 'accion',
            'entidad' => 'entidad', 'entidad_id' => 'entidad_id',
        ], $limit, $offset);
    }

    /** @param array<string,string> $filtros */
    public function contarOperaciones(array $filtros): int
    {
        return $this->contar('audit_operations', $filtros, [
            'usuario_id' => 'usuario_id', 'accion' => 'accion',
            'entidad' => 'entidad', 'entidad_id' => 'entidad_id',
        ]);
    }

    /** @param array<string,string> $filtros */
    public function listarAccesosPii(array $filtros, int $limit, int $offset): array
    {
        return $this->listar('audit_pii_access', $filtros, [
            'usuario_id' => 'usuario_id', 'accion' => 'accion',
            'paciente_id' => 'paciente_id', 'prescription_id' => 'prescription_id',
        ], $limit, $offset);
    }

    /** @param array<string,string> $filtros */
    public function contarAccesosPii(array $filtros): int
    {
        return $this->contar('audit_pii_access', $filtros, [
            'usuario_id' => 'usuario_id', 'accion' => 'accion',
            'paciente_id' => 'paciente_id', 'prescription_id' => 'prescription_id',
        ]);
    }

    /** @param array<string,string> $filtros */
    public function listarEventos(array $filtros, int $limit, int $offset): array
    {
        return $this->listar('outbox_events', $filtros, [
            'estado' => 'estado', 'tipo_evento' => 'tipo_evento', 'agregado_tipo' => 'agregado_tipo',
        ], $limit, $offset);
    }

    /** @param array<string,string> $filtros */
    public function contarEventos(array $filtros): int
    {
        return $this->contar('outbox_events', $filtros, [
            'estado' => 'estado', 'tipo_evento' => 'tipo_evento', 'agregado_tipo' => 'agregado_tipo',
        ]);
    }

    /** outbox: pendiente|procesado|fallido -> procesado. */
    public function marcarProcesado(int $id): bool
    {
        $stmt = Database::pdo()->prepare(
            "UPDATE outbox_events SET estado = 'procesado', procesado_at = NOW(6)
              WHERE id = ? AND estado = 'pendiente'"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    /** outbox: pendiente -> fallido, acumulando intentos. */
    public function marcarFallido(int $id): bool
    {
        $stmt = Database::pdo()->prepare(
            "UPDATE outbox_events SET estado = 'fallido', intentos = intentos + 1
              WHERE id = ? AND estado = 'pendiente'"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    /** outbox: fallido -> pendiente para reintentar (decision 16). */
    public function reintentar(int $id): bool
    {
        $stmt = Database::pdo()->prepare(
            "UPDATE outbox_events SET estado = 'pendiente'
              WHERE id = ? AND estado = 'fallido'"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    /** Alta del evento dentro de la transaccion del llamador (outbox). */
    public function publicarEvento(string $agregadoTipo, int $agregadoId, string $tipoEvento, array $payload): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO outbox_events (agregado_tipo, agregado_id, tipo_evento, payload)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $agregadoTipo,
            $agregadoId,
            $tipoEvento,
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    // ------------------------------------------------------------ privados

    /** @return array<string,mixed>|null */
    private function buscar(string $tabla, int $id): ?array
    {
        $stmt = Database::pdo()->prepare("SELECT * FROM {$tabla} WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : self::cast($row);
    }

    /** @param array<string,string> $filtros @param array<string,string> $map */
    private function listar(string $tabla, array $filtros, array $map, int $limit, int $offset): array
    {
        [$where, $params] = $this->where($filtros, $map);
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM {$tabla}{$where} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return array_map(self::cast(...), $stmt->fetchAll());
    }

    /** @param array<string,string> $filtros @param array<string,string> $map */
    private function contar(string $tabla, array $filtros, array $map): int
    {
        [$where, $params] = $this->where($filtros, $map);
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM {$tabla}{$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string,string> $filtros
     * @param array<string,string> $map clave de filtro => columna
     * @return array{0:string,1:list<mixed>}
     */
    private function where(array $filtros, array $map): array
    {
        $parts = [];
        $params = [];
        foreach ($map as $key => $col) {
            if (!isset($filtros[$key]) || $filtros[$key] === '') {
                continue;
            }
            $parts[] = "{$col} = ?";
            $params[] = $filtros[$key];
        }
        if (isset($filtros['desde'])) {
            $parts[] = 'created_at >= ?';
            $params[] = $filtros['desde'] . ' 00:00:00';
        }
        if (isset($filtros['hasta'])) {
            $parts[] = 'created_at < ?';
            $params[] = date('Y-m-d', strtotime($filtros['hasta'] . ' +1 day')) . ' 00:00:00';
        }
        return [$parts === [] ? '' : ' WHERE ' . implode(' AND ', $parts), $params];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private static function cast(array $row): array
    {
        foreach (['id', 'usuario_id', 'entidad_id', 'paciente_id', 'prescription_id', 'agregado_id', 'intentos'] as $k) {
            if (array_key_exists($k, $row) && $row[$k] !== null) {
                $row[$k] = (int) $row[$k];
            }
        }
        foreach (['valores_antes', 'valores_despues', 'payload'] as $k) {
            if (isset($row[$k]) && is_string($row[$k])) {
                $row[$k] = json_decode($row[$k], true);
            }
        }
        return $row;
    }
}
