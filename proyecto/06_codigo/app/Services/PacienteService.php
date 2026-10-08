<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Models\Paciente;
use App\Repositories\PacienteRepository;
use App\Validators\DirectorioValidator;

final class PacienteService
{
    public function __construct(
        private readonly PacienteRepository $repo = new PacienteRepository(),
        private readonly DirectorioValidator $validator = new DirectorioValidator(),
    ) {
    }

    /**
     * @param array<string,mixed> $query parámetros de la URL (page, limit, q)
     * @return array{data:list<Paciente>,meta:array{total:int,page:int,limit:int}}
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
     * @param array<string,mixed> $input body PatientCreate
     * @throws AppException VALIDATION_ERROR (400) | DUPLICATE_IDENTIFICATION (409) | DATABASE_ERROR (500)
     */
    public function crear(array $input): Paciente
    {
        $datos = $this->validator->validarPaciente($input);

        try {
            if ($this->repo->existeIdentificacion($datos['identificacion'])) {
                throw new AppException(
                    'DUPLICATE_IDENTIFICATION',
                    'Ya existe un paciente con esa identificación.',
                    409
                );
            }
            $id = $this->repo->insertar(
                $datos['identificacion'],
                $datos['nombre'],
                $datos['fecha_nacimiento'],
                $datos['contacto']
            );
            $nueva = $this->repo->buscarPorId($id);
        } catch (AppException $e) {
            throw $e;
        } catch (\PDOException $e) {
            $driverCode = (int) ($e->errorInfo[1] ?? 0);
            if ($driverCode === 1062) {
                throw new AppException(
                    'DUPLICATE_IDENTIFICATION',
                    'Ya existe un paciente con esa identificación.',
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
    public function obtener(string $rawId): Paciente
    {
        $id = $this->idEntero($rawId);
        try {
            $paciente = $this->repo->buscarPorId($id);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        if ($paciente === null) {
            throw new AppException('NOT_FOUND', 'Paciente no encontrado.', 404);
        }
        return $paciente;
    }

    /**
     * @param array<string,mixed> $input body PatientUpdate
     * @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (404) | DUPLICATE_IDENTIFICATION (409) | DATABASE_ERROR (500)
     */
    public function actualizar(string $rawId, array $input): Paciente
    {
        $id = $this->idEntero($rawId);
        $datos = $this->validator->validarPaciente($input);

        try {
            $actual = $this->repo->buscarPorId($id);
            if ($actual === null) {
                throw new AppException('NOT_FOUND', 'Paciente no encontrado.', 404);
            }
            if ($this->repo->existeIdentificacionExcluyendo($datos['identificacion'], $id)) {
                throw new AppException(
                    'DUPLICATE_IDENTIFICATION',
                    'Ya existe un paciente con esa identificación.',
                    409
                );
            }
            $this->repo->actualizar(
                $id,
                $datos['identificacion'],
                $datos['nombre'],
                $datos['fecha_nacimiento'],
                $datos['contacto']
            );
            $resultado = $this->repo->buscarPorId($id);
        } catch (AppException $e) {
            throw $e;
        } catch (\PDOException $e) {
            $driverCode = (int) ($e->errorInfo[1] ?? 0);
            if ($driverCode === 1062) {
                throw new AppException(
                    'DUPLICATE_IDENTIFICATION',
                    'Ya existe un paciente con esa identificación.',
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
     * Borrado lógico de PII: el registro deja de estar disponible en la API
     * (404 tras anonimizar); nunca DELETE FROM. Identificación, nombre,
     * contacto y fecha de nacimiento se ponen a cero en un solo UPDATE.
     *
     * @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (404) | DATABASE_ERROR (500)
     */
    public function anonimizar(string $rawId): void
    {
        $id = $this->idEntero($rawId);
        try {
            if ($this->repo->buscarPorId($id) === null) {
                throw new AppException('NOT_FOUND', 'Paciente no encontrado.', 404);
            }
            $this->repo->anonimizar($id);
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
