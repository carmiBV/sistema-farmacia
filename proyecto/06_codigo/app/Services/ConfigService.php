<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Repositories\ConfigRepository;
use App\Repositories\SucursalRepository;
use App\Validators\OpsValidator;

final class ConfigService
{
    public function __construct(
        private readonly ConfigRepository $repo = new ConfigRepository(),
        private readonly SucursalRepository $sucursales = new SucursalRepository(),
        private readonly OpsValidator $validator = new OpsValidator(),
    ) {
    }

    /**
     * @param array<string,mixed> $query parámetros de la URL (page, limit, store_id)
     * @return array{data:list<array<string,mixed>>,meta:array{total:int,page:int,limit:int}}
     * @throws AppException VALIDATION_ERROR (400) | DATABASE_ERROR (500)
     */
    public function listar(array $query): array
    {
        $page = $this->entero($query, 'page', 1, 1, 1_000_000);
        $limit = $this->entero($query, 'limit', 20, 1, 100);
        $storeId = array_key_exists('store_id', $query)
            ? $this->entero($query, 'store_id', 0, 1, PHP_INT_MAX)
            : null;

        try {
            $todas = $this->repo->listar($storeId);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        // ponytail: paginación en memoria — la tabla de parámetros es chica
        // (decenas de filas). Si supera miles, mover el OFFSET al SQL.
        $total = count($todas);
        $items = array_slice($todas, ($page - 1) * $limit, $limit);

        return [
            'data' => $items,
            'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit],
        ];
    }

    /**
     * @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (404) | DATABASE_ERROR (500)
     * @return array<string,mixed>
     */
    public function obtener(string $rawKey): array
    {
        $key = $this->clave($rawKey);
        try {
            $row = $this->repo->buscar($key);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        if ($row === null) {
            throw new AppException('NOT_FOUND', 'Parámetro no encontrado.', 404);
        }
        return $row;
    }

    /**
     * Upsert de un parámetro (PUT idempotente).
     *
     * @param array<string,mixed> $input body ConfigUpdate
     * @return array<string,mixed>
     * @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (409 store inexistente) | DATABASE_ERROR (500)
     */
    public function guardar(string $rawKey, array $input, int $userId): array
    {
        $key = $this->clave($rawKey);
        $datos = $this->validator->validarConfig($input);

        // Regla CHECK de la BD: las claves store.* exigen store_id.
        $esClaveDeStore = str_starts_with($key, 'store.');
        if ($esClaveDeStore && $datos['store_id'] === null) {
            throw new AppException(
                'VALIDATION_ERROR',
                "La clave '{$key}' requiere un 'store_id'.",
                400
            );
        }

        if ($datos['store_id'] !== null && !$this->sucursales->existeId($datos['store_id'])) {
            throw new AppException('NOT_FOUND', 'La sucursal indicada no existe.', 409);
        }

        try {
            $this->repo->guardar($key, $datos['config_value'], $datos['value_type'], $datos['store_id'], $userId);
            $row = $this->repo->buscar($key);
        } catch (\PDOException $e) {
            $driverCode = (int) ($e->errorInfo[1] ?? 0);
            if ($driverCode === 3819) {
                // CHECK constraint (p. ej. store.* sin store_id): no filtrar detalle del driver.
                throw new AppException('VALIDATION_ERROR', 'La combinación clave/valor no cumple las reglas de la base.', 400);
            }
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        if ($row === null) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return $row;
    }

    /** 400 si la clave no cumple el patrón 1-100 caracteres. */
    private function clave(string $raw): string
    {
        $key = trim($raw);
        if ($key === '' || mb_strlen($key) > 100 || preg_match('/^[A-Za-z0-9_.-]+$/', $key) !== 1) {
            throw new AppException(
                'VALIDATION_ERROR',
                "Clave inválida (1-100 caracteres: letras, dígitos, '.', '_' o '-').",
                400
            );
        }
        return $key;
    }

    /** @param array<string,mixed> $query */
    private function entero(array $query, string $key, int $def, int $min, int $max): int
    {
        if (!array_key_exists($key, $query)) {
            return $def;
        }
        $raw = $query[$key];
        if (!is_string($raw) || preg_match('/^\d+$/', $raw) !== 1) {
            throw new AppException('VALIDATION_ERROR', "Parámetro '{$key}' inválido.", 400);
        }
        $value = (int) $raw;
        if ($value < $min || $value > $max) {
            throw new AppException('VALIDATION_ERROR', "Parámetro '{$key}' fuera de rango.", 400);
        }
        return $value;
    }
}
