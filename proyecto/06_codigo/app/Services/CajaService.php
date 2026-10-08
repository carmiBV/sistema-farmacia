<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Models\Caja;
use App\Repositories\CajaRepository;
use App\Repositories\ConfigRepository;
use App\Repositories\SucursalRepository;
use App\Validators\OpsValidator;

final class CajaService
{
    public function __construct(
        private readonly CajaRepository $repo = new CajaRepository(),
        private readonly SucursalRepository $sucursales = new SucursalRepository(),
        private readonly ConfigRepository $config = new ConfigRepository(),
        private readonly OpsValidator $validator = new OpsValidator(),
    ) {
    }

    /**
     * @param array<string,mixed> $query parámetros de la URL (page, limit, q, store_id)
     * @return array{data:list<Caja>,meta:array{total:int,page:int,limit:int}}
     * @throws AppException VALIDATION_ERROR (400) | DATABASE_ERROR (500)
     */
    public function listar(array $query): array
    {
        $page = $this->entero($query, 'page', 1, 1, 1_000_000);
        $limit = $this->entero($query, 'limit', 20, 1, 100);
        $storeId = array_key_exists('store_id', $query)
            ? $this->entero($query, 'store_id', 0, 1, PHP_INT_MAX)
            : null;
        $q = $this->texto($query);

        try {
            $total = $this->repo->contar($q, $storeId);
            $items = $this->repo->listar($q, $storeId, $limit, ($page - 1) * $limit);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        return [
            'data' => $items,
            'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit],
        ];
    }

    /**
     * @param array<string,mixed> $input body RegisterCreate
     * @throws AppException VALIDATION_ERROR (400) | DUPLICATE_CODE/NOT_FOUND (409) | DATABASE_ERROR (500)
     */
    public function crear(array $input): Caja
    {
        $datos = $this->validator->validarCaja($input);

        try {
            if (!$this->sucursales->existeId($datos['store_id'])) {
                throw new AppException('NOT_FOUND', 'La sucursal indicada no existe.', 409);
            }
            if ($this->repo->existeCodigoEnStore($datos['store_id'], $datos['codigo'])) {
                throw new AppException('DUPLICATE_CODE', 'Ya existe una caja con ese código en la sucursal.', 409);
            }
            $id = $this->repo->insertar($datos['store_id'], $datos['codigo']);
            $nueva = $this->repo->buscarPorId($id);
        } catch (AppException $e) {
            throw $e;
        } catch (\PDOException $e) {
            $driverCode = (int) ($e->errorInfo[1] ?? 0);
            if ($driverCode === 1062) {
                throw new AppException('DUPLICATE_CODE', 'Ya existe una caja con ese código en la sucursal.', 409);
            }
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        if ($nueva === null) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return $nueva;
    }

    /** @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (404) | DATABASE_ERROR (500) */
    public function obtener(string $rawId): Caja
    {
        $id = $this->idEntero($rawId);
        try {
            $caja = $this->repo->buscarPorId($id);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        if ($caja === null) {
            throw new AppException('NOT_FOUND', 'Caja no encontrada.', 404);
        }
        return $caja;
    }

    /**
     * @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (404) |
     *         DUPLICATE_CODE (409) | DATABASE_ERROR (500)
     */
    public function actualizar(string $rawId, array $input): Caja
    {
        $id = $this->idEntero($rawId);
        $datos = $this->validator->validarCaja($input);

        try {
            $actual = $this->repo->buscarPorId($id);
            if ($actual === null) {
                throw new AppException('NOT_FOUND', 'Caja no encontrada.', 404);
            }
            if (!$this->sucursales->existeId($datos['store_id'])) {
                throw new AppException('NOT_FOUND', 'La sucursal indicada no existe.', 409);
            }
            if ($this->repo->existeCodigoEnStoreExcluyendo($datos['store_id'], $datos['codigo'], $id)) {
                throw new AppException('DUPLICATE_CODE', 'Ya existe una caja con ese código en la sucursal.', 409);
            }
            $this->repo->actualizar($id, $datos['store_id'], $datos['codigo']);
            $resultado = $this->repo->buscarPorId($id);
        } catch (AppException $e) {
            throw $e;
        } catch (\PDOException $e) {
            $driverCode = (int) ($e->errorInfo[1] ?? 0);
            if ($driverCode === 1062) {
                throw new AppException('DUPLICATE_CODE', 'Ya existe una caja con ese código en la sucursal.', 409);
            }
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        if ($resultado === null) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return $resultado;
    }

    /** Borrado lógico: estado='inactiva', nunca DELETE FROM.
     *  @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (404) | DATABASE_ERROR (500) */
    public function inactivar(string $rawId): void
    {
        $id = $this->idEntero($rawId);
        try {
            if ($this->repo->buscarPorId($id) === null) {
                throw new AppException('NOT_FOUND', 'Caja no encontrada.', 404);
            }
            $this->repo->inactivar($id);
        } catch (AppException $e) {
            throw $e;
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
    }

    // ── Turnos ──────────────────────────────────────────────────────────────
    // ponytail: el turno se guarda como fila JSON en system_config
    // (register.shift.<id>) — sin tabla ni historial. Si hace falta
    // auditoría de turnos, crear tabla ops_shifts y mover la lógica ahí.

    /** Estado actual del turno: array abierto o null. */
    public function turnoActual(string $rawId): ?array
    {
        $caja = $this->obtener($rawId);
        $turno = $this->config->buscar('register.shift.' . $caja->id);
        if ($turno === null) {
            return null;
        }
        $estado = json_decode($turno['config_value'], true);
        if (!is_array($estado) || ($estado['estado'] ?? null) !== 'abierta') {
            return null;
        }
        return $estado;
    }

    /** @return array<string,mixed> turno recién abierto */
    public function abrirTurno(string $rawId, int $userId): array
    {
        $caja = $this->obtener($rawId);
        $this->verificarSucursalActiva($caja);

        if ($this->turnoActual($rawId) !== null) {
            throw new AppException('SHIFT_ALREADY_OPEN', 'La caja ya tiene un turno abierto.', 409);
        }

        $estado = [
            'estado' => 'abierta',
            'abierto_por' => $userId,
            'abierto_at' => gmdate('c'),
            'cerrado_por' => null,
            'cerrado_at' => null,
        ];
        $this->config->guardar(
            'register.shift.' . $caja->id,
            json_encode($estado, JSON_UNESCAPED_UNICODE),
            'json',
            $caja->store_id,
            $userId
        );
        return $estado;
    }

    /** @return array<string,mixed> turno cerrado */
    public function cerrarTurno(string $rawId, int $userId): array
    {
        $caja = $this->obtener($rawId);
        $abierto = $this->turnoActual($rawId);
        if ($abierto === null) {
            throw new AppException('SHIFT_NOT_OPEN', 'La caja no tiene un turno abierto.', 409);
        }

        $abierto['estado'] = 'cerrada';
        $abierto['cerrado_por'] = $userId;
        $abierto['cerrado_at'] = gmdate('c');
        $this->config->guardar(
            'register.shift.' . $caja->id,
            json_encode($abierto, JSON_UNESCAPED_UNICODE),
            'json',
            $caja->store_id,
            $userId
        );
        return $abierto;
    }

    private function verificarSucursalActiva(Caja $caja): void
    {
        if ($this->sucursales->buscarPorId($caja->store_id) === null) {
            throw new AppException('NOT_FOUND', 'La sucursal de la caja no está activa.', 409);
        }
    }

    /** 400 si no es entero >= 1. */
    private function idEntero(string $rawId): int
    {
        $id = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new AppException('VALIDATION_ERROR', "Parámetro 'id' inválido.", 400);
        }
        return $id;
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

    /** @param array<string,mixed> $query */
    private function texto(array $query): ?string
    {
        if (!array_key_exists('q', $query)) {
            return null;
        }
        $raw = $query['q'];
        if (!is_string($raw) || mb_strlen($raw) > 20) {
            throw new AppException('VALIDATION_ERROR', "Parámetro 'q' inválido.", 400);
        }
        $trim = trim($raw);
        return $trim === '' ? null : $trim;
    }
}
