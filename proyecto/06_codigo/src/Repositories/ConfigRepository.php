<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

/**
 * Acceso a `system_config` como filas asociativas (sin modelo de dominio):
 * es una tabla clave-valor heterogénea, mapearla a una clase añade
 * boilerplate sin valor — los consumidores sólo leen campos concretos.
 */
final class ConfigRepository
{
    /** @return list<array{config_key:string,config_value:string,value_type:string,store_id:?int,updated_at:string,updated_by:int}> */
    public function listar(?int $storeId): array
    {
        $sql = 'SELECT config_key, config_value, value_type, store_id, updated_at, updated_by FROM system_config';
        $stmt = Database::pdo();
        if ($storeId === null) {
            $rows = $stmt->query($sql . ' ORDER BY config_key ASC')->fetchAll();
        } else {
            $s = $stmt->prepare($sql . ' WHERE store_id = :store ORDER BY config_key ASC');
            $s->bindValue(':store', $storeId, PDO::PARAM_INT);
            $s->execute();
            $rows = $s->fetchAll();
        }
        return array_map([$this, 'fila'], $rows);
    }

    /** @return array{config_key:string,config_value:string,value_type:string,store_id:?int,updated_at:string,updated_by:int}|null */
    public function buscar(string $key): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT config_key, config_value, value_type, store_id, updated_at, updated_by
             FROM system_config WHERE config_key = :key'
        );
        $stmt->bindValue(':key', $key);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $this->fila($row);
    }

    /** UPSERT idempotente por PK config_key (INSERT ... ON DUPLICATE KEY UPDATE). */
    public function guardar(string $key, string $value, string $type, ?int $storeId, int $userId): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO system_config (config_key, config_value, value_type, store_id, updated_by)
             VALUES (:key, :value, :type, :store, :user)
             ON DUPLICATE KEY UPDATE
               config_value = VALUES(config_value),
               value_type = VALUES(value_type),
               store_id = VALUES(store_id),
               updated_by = VALUES(updated_by)'
        );
        $stmt->bindValue(':key', $key);
        $stmt->bindValue(':value', $value);
        $stmt->bindValue(':type', $type);
        $stmt->bindValue(':store', $storeId, $storeId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':user', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /** @param array<string,mixed> $row */
    private function fila(array $row): array
    {
        return [
            'config_key' => (string) $row['config_key'],
            'config_value' => (string) $row['config_value'],
            'value_type' => (string) $row['value_type'],
            'store_id' => $row['store_id'] === null ? null : (int) $row['store_id'],
            'updated_at' => (string) $row['updated_at'],
            'updated_by' => (int) $row['updated_by'],
        ];
    }
}
