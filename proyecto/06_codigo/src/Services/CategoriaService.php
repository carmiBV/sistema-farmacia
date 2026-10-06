<?php
declare(strict_types=1);

namespace App\Services;

use App\Http\AppException;
use App\Models\Categoria;
use App\Repositories\CategoriaRepository;
use App\Validators\CategoriaValidator;

final class CategoriaService
{
    public function __construct(
        private readonly CategoriaRepository $repo = new CategoriaRepository(),
        private readonly CategoriaValidator $validator = new CategoriaValidator(),
    ) {
    }

    /**
     * @param array<string,mixed> $query parámetros de la URL (page, limit, q, parent_id)
     * @return array{
     *     data: list<Categoria>,
     *     meta: array{total:int,page:int,limit:int}
     * }
     * @throws AppException VALIDATION_ERROR (400) | DATABASE_ERROR (500)
     */
    public function listar(array $query): array
    {
        $page = $this->entero($query, 'page', 1, 1, 1_000_000);
        $limit = $this->entero($query, 'limit', 20, 1, 100);
        $parentId = array_key_exists('parent_id', $query)
            ? $this->entero($query, 'parent_id', 0, 0, PHP_INT_MAX)
            : null;
        $q = $this->texto($query);

        try {
            $total = $this->repo->contar($q, $parentId);
            $items = $this->repo->listar($q, $parentId, $limit, ($page - 1) * $limit);
        } catch (\PDOException) {
            // Sin detalle del driver en la respuesta (sin stack traces / credenciales).
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        return [
            'data' => $items,
            'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit],
        ];
    }

    /**
     * @param array<string,mixed> $input body CategoryCreate
     * @return Categoria
     * @throws AppException VALIDATION_ERROR (400) | DUPLICATE_NAME/NOT_FOUND (409) | DATABASE_ERROR (500)
     */
    public function crear(array $input): Categoria
    {
        $datos = $this->validator->validar($input);

        try {
            // Contrato 409: padre inexistente o nombre duplicado en la misma rama.
            if ($datos['parent_id'] !== null && !$this->repo->padreExiste($datos['parent_id'])) {
                throw new AppException('NOT_FOUND', 'La categoría padre indicada no existe.', 409);
            }
            if ($this->repo->existeNombre($datos['nombre'], $datos['parent_id'])) {
                throw new AppException(
                    'DUPLICATE_NAME',
                    'Ya existe una categoría con ese nombre en la misma rama.',
                    409
                );
            }
            $id = $this->repo->insertar($datos['nombre'], $datos['parent_id']);
            $nueva = $this->repo->buscarPorId($id);
        } catch (AppException $e) {
            throw $e;
        } catch (\PDOException $e) {
            // Carrera entre el chequeo y el INSERT: la uq (nombre, parent_id_key) decide.
            $driverCode = (int) ($e->errorInfo[1] ?? 0);
            if ($driverCode === 1062) {
                throw new AppException(
                    'DUPLICATE_NAME',
                    'Ya existe una categoría con ese nombre en la misma rama.',
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

    /**
     * @return Categoria
     * @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (404) | DATABASE_ERROR (500)
     */
    public function obtener(string $rawId): Categoria
    {
        $id = $this->idEntero($rawId);

        try {
            $categoria = $this->repo->buscarPorId($id);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        if ($categoria === null) {
            throw new AppException('NOT_FOUND', 'Categoría no encontrada.', 404);
        }
        return $categoria;
    }

    /**
     * @param array<string,mixed> $input body CategoryUpdate (nombre requerido; parent_id
     *        ausente = conservar actual, null = mover a raíz)
     * @return Categoria
     * @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (404) |
     *         DUPLICATE_NAME/NOT_FOUND/HIERARCHY_CYCLE (409) | DATABASE_ERROR (500)
     */
    public function actualizar(string $rawId, array $input): Categoria
    {
        $id = $this->idEntero($rawId);
        $datos = $this->validator->validar($input);

        try {
            $actual = $this->repo->buscarPorId($id);
            if ($actual === null) {
                throw new AppException('NOT_FOUND', 'Categoría no encontrada.', 404);
            }

            $nuevoPadre = array_key_exists('parent_id', $input) ? $datos['parent_id'] : $actual->parent_id;

            if ($nuevoPadre !== null) {
                if ($nuevoPadre === $id || $this->repo->esDescendiente($nuevoPadre, $id)) {
                    throw new AppException('HIERARCHY_CYCLE', 'El nuevo padre formaría un ciclo jerárquico.', 409);
                }
                if (!$this->repo->padreExiste($nuevoPadre)) {
                    throw new AppException('NOT_FOUND', 'La categoría padre indicada no existe.', 409);
                }
            }
            if ($this->repo->existeNombreExcluyendo($datos['nombre'], $nuevoPadre, $id)) {
                throw new AppException('DUPLICATE_NAME', 'Ya existe una categoría con ese nombre en la misma rama.', 409);
            }

            $this->repo->actualizar($id, $datos['nombre'], $nuevoPadre);
            $resultado = $this->repo->buscarPorId($id);
        } catch (AppException $e) {
            throw $e;
        } catch (\PDOException $e) {
            $driverCode = (int) ($e->errorInfo[1] ?? 0);
            if ($driverCode === 1062) {
                throw new AppException('DUPLICATE_NAME', 'Ya existe una categoría con ese nombre en la misma rama.', 409);
            }
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        if ($resultado === null) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return $resultado;
    }

    /**
     * Borrado lógico (V1.5.0): estado='inactivo', nunca DELETE FROM.
     * El registro inactivo deja de existir en la API (GET/PUT/lista → 404/vacío).
     *
     * @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (404) | DATABASE_ERROR (500)
     */
    public function inactivar(string $rawId): void
    {
        $id = $this->idEntero($rawId);

        try {
            if ($this->repo->buscarPorId($id) === null) {
                throw new AppException('NOT_FOUND', 'Categoría no encontrada.', 404);
            }
            $this->repo->inactivar($id);
        } catch (AppException $e) {
            throw $e;
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
    }

    /** 400 si no es entero >= 1 o fuera de rango. */
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
