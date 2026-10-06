<?php
declare(strict_types=1);

namespace App\Services;

use App\Http\AppException;
use App\Models\Prescriptor;
use App\Repositories\PrescriptorRepository;
use App\Validators\DirectorioValidator;

final class PrescriptorService
{
    public function __construct(
        private readonly PrescriptorRepository $repo = new PrescriptorRepository(),
        private readonly DirectorioValidator $validator = new DirectorioValidator(),
    ) {
    }

    /**
     * @param array<string,mixed> $query parámetros de la URL (page, limit, q)
     * @return array{data:list<Prescriptor>,meta:array{total:int,page:int,limit:int}}
     * @throws AppException VALIDATION_ERROR (400) | DATABASE_ERROR (500)
     */
    public function listar(array $query): array
    {
        $page = $this->entero($query, 'page', 1, 1, 1_000_000);
        $limit = $this->entero($query, 'limit', 20, 1, 100);
        $q = $this->texto($query);

        try {
            $total = $this->repo->contar($q);
            $items = $this->repo->listar($q, $limit, ($page - 1) * $limit);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        return [
            'data' => $items,
            'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit],
        ];
    }

    /**
     * @param array<string,mixed> $input body PrescriberCreate
     * @throws AppException VALIDATION_ERROR (400) | DUPLICATE_IDENTIFICATION (409) | DATABASE_ERROR (500)
     */
    public function crear(array $input): Prescriptor
    {
        $datos = $this->validator->validarPrescriptor($input);

        try {
            if ($this->repo->existeIdentificacion($datos['identificacion'])) {
                throw new AppException(
                    'DUPLICATE_IDENTIFICATION',
                    'Ya existe un prescriptor con esa identificación.',
                    409
                );
            }
            $id = $this->repo->insertar(
                $datos['identificacion'],
                $datos['nombre'],
                $datos['especialidad']
            );
            $nueva = $this->repo->buscarPorId($id);
        } catch (AppException $e) {
            throw $e;
        } catch (\PDOException $e) {
            $driverCode = (int) ($e->errorInfo[1] ?? 0);
            if ($driverCode === 1062) {
                throw new AppException(
                    'DUPLICATE_IDENTIFICATION',
                    'Ya existe un prescriptor con esa identificación.',
                    409
                );
            }
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        if ($nueva === null) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return $nueva;
    }

    /** @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (404) | DATABASE_ERROR (500) */
    public function obtener(string $rawId): Prescriptor
    {
        $id = $this->idEntero($rawId);
        try {
            $prescriptor = $this->repo->buscarPorId($id);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        if ($prescriptor === null) {
            throw new AppException('NOT_FOUND', 'Prescriptor no encontrado.', 404);
        }
        return $prescriptor;
    }

    /**
     * @param array<string,mixed> $input body PrescriberUpdate
     * @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (404) | DUPLICATE_IDENTIFICATION (409) | DATABASE_ERROR (500)
     */
    public function actualizar(string $rawId, array $input): Prescriptor
    {
        $id = $this->idEntero($rawId);
        $datos = $this->validator->validarPrescriptor($input);

        try {
            $actual = $this->repo->buscarPorId($id);
            if ($actual === null) {
                throw new AppException('NOT_FOUND', 'Prescriptor no encontrado.', 404);
            }
            if ($this->repo->existeIdentificacionExcluyendo($datos['identificacion'], $id)) {
                throw new AppException(
                    'DUPLICATE_IDENTIFICATION',
                    'Ya existe un prescriptor con esa identificación.',
                    409
                );
            }
            $this->repo->actualizar($id, $datos['identificacion'], $datos['nombre'], $datos['especialidad']);
            $resultado = $this->repo->buscarPorId($id);
        } catch (AppException $e) {
            throw $e;
        } catch (\PDOException $e) {
            $driverCode = (int) ($e->errorInfo[1] ?? 0);
            if ($driverCode === 1062) {
                throw new AppException(
                    'DUPLICATE_IDENTIFICATION',
                    'Ya existe un prescriptor con esa identificación.',
                    409
                );
            }
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        if ($resultado === null) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return $resultado;
    }

    /**
     * Borrado lógico: estado='inactivo', nunca DELETE FROM.
     *
     * @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (404) | DATABASE_ERROR (500)
     */
    public function inactivar(string $rawId): void
    {
        $id = $this->idEntero($rawId);
        try {
            if ($this->repo->buscarPorId($id) === null) {
                throw new AppException('NOT_FOUND', 'Prescriptor no encontrado.', 404);
            }
            $this->repo->inactivar($id);
        } catch (AppException $e) {
            throw $e;
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
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
        if (!is_string($raw) || mb_strlen($raw) > 150) {
            throw new AppException('VALIDATION_ERROR', "Parámetro 'q' inválido.", 400);
        }
        $trim = trim($raw);
        return $trim === '' ? null : $trim;
    }
}
