<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Models\Producto;
use App\Repositories\ProductoRepository;
use App\Validators\ProductoValidator;

final class ProductoService
{
    public function __construct(
        private readonly ProductoRepository $repo = new ProductoRepository(),
        private readonly ProductoValidator $validator = new ProductoValidator(),
    ) {
    }

    /**
     * @param array<string,mixed> $query parámetros de la URL (page, limit, q, category_id)
     * @return array{data:list<Producto>,meta:array{total:int,page:int,limit:int}}
     * @throws AppException VALIDATION_ERROR (400) | DATABASE_ERROR (500)
     */
    public function listar(array $query): array
    {
        $page = $this->entero($query, 'page', 1, 1, 1_000_000);
        $limit = $this->entero($query, 'limit', 20, 1, 100);
        $categoryId = array_key_exists('category_id', $query)
            ? $this->entero($query, 'category_id', 0, 0, PHP_INT_MAX)
            : null;
        $q = $this->texto($query);

        try {
            $total = $this->repo->contar($q, $categoryId);
            $items = $this->repo->listar($q, $categoryId, $limit, ($page - 1) * $limit);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        return [
            'data' => $items,
            'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit],
        ];
    }

    /**
     * @param array<string,mixed> $input body ProductCreate (category_ids opcional)
     * @throws AppException VALIDATION_ERROR (400) | DUPLICATE_SKU (409) | DATABASE_ERROR (500)
     */
    public function crear(array $input): Producto
    {
        $datos = $this->validator->validar($input);

        try {
            if ($this->repo->existeSku($datos['sku'])) {
                throw new AppException('DUPLICATE_SKU', 'Ya existe un producto con ese SKU.', 409);
            }
            $id = $this->repo->insertar(
                $datos['sku'],
                $datos['nombre'],
                $datos['principio_activo'],
                $datos['presentacion'],
                $datos['concentracion'],
                $datos['condicion_venta']
            );
            if (array_key_exists('category_ids', $input)) {
                $this->repo->reemplazarCategorias($id, $this->validator->validarCategoryIds($input));
            }
            $nueva = $this->repo->buscarPorId($id);
        } catch (AppException $e) {
            throw $e;
        } catch (\PDOException $e) {
            $driverCode = (int) ($e->errorInfo[1] ?? 0);
            if ($driverCode === 1062) {
                throw new AppException('DUPLICATE_SKU', 'Ya existe un producto con ese SKU.', 409);
            }
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        if ($nueva === null) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return $nueva;
    }

    /** @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (404) | DATABASE_ERROR (500) */
    public function obtener(string $rawId): Producto
    {
        $id = $this->idEntero($rawId);
        try {
            $producto = $this->repo->buscarPorId($id);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        if ($producto === null) {
            throw new AppException('NOT_FOUND', 'Producto no encontrado.', 404);
        }
        return $producto;
    }

    /**
     * @param array<string,mixed> $input body ProductUpdate (category_ids opcional)
     * @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (404) |
     *         DUPLICATE_SKU (409) | DATABASE_ERROR (500)
     */
    public function actualizar(string $rawId, array $input): Producto
    {
        $id = $this->idEntero($rawId);
        $datos = $this->validator->validar($input);

        try {
            $actual = $this->repo->buscarPorId($id);
            if ($actual === null) {
                throw new AppException('NOT_FOUND', 'Producto no encontrado.', 404);
            }
            if ($this->repo->existeSkuExcluyendo($datos['sku'], $id)) {
                throw new AppException('DUPLICATE_SKU', 'Ya existe un producto con ese SKU.', 409);
            }
            $this->repo->actualizar(
                $id,
                $datos['sku'],
                $datos['nombre'],
                $datos['principio_activo'],
                $datos['presentacion'],
                $datos['concentracion'],
                $datos['condicion_venta']
            );
            if (array_key_exists('category_ids', $input)) {
                $this->repo->reemplazarCategorias($id, $this->validator->validarCategoryIds($input));
            }
            $resultado = $this->repo->buscarPorId($id);
        } catch (AppException $e) {
            throw $e;
        } catch (\PDOException $e) {
            $driverCode = (int) ($e->errorInfo[1] ?? 0);
            if ($driverCode === 1062) {
                throw new AppException('DUPLICATE_SKU', 'Ya existe un producto con ese SKU.', 409);
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
                throw new AppException('NOT_FOUND', 'Producto no encontrado.', 404);
            }
            $this->repo->inactivar($id);
        } catch (AppException $e) {
            throw $e;
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
    }

    /**
     * @return array{product_id:int,categories:list<array{id:int,nombre:string}>}
     * @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (404) | DATABASE_ERROR (500)
     */
    public function obtenerCategorias(string $rawId): array
    {
        $producto = $this->obtener($rawId);

        try {
            $ids = $this->repo->categoriasDeProducto($producto->id);
            $nombres = $this->repo->nombresCategorias($ids);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        return [
            'product_id' => $producto->id,
            'categories' => $this->armarCategorias($ids, $nombres),
        ];
    }

    /**
     * @param array<string,mixed> $input body con category_ids
     * @return array{product_id:int,categories:list<array{id:int,nombre:string}>}
     * @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (404) |
     *         NOT_FOUND categoria inexistente (409) | DATABASE_ERROR (500)
     */
    public function sincronizarCategorias(string $rawId, array $input): array
    {
        $producto = $this->obtener($rawId);
        $ids = $this->validator->validarCategoryIds($input);

        try {
            foreach ($ids as $categoryId) {
                if (!$this->repo->categoriaExiste($categoryId)) {
                    throw new AppException('NOT_FOUND', 'La categoría indicada no existe.', 409);
                }
            }
            $this->repo->reemplazarCategorias($producto->id, $ids);
            $nombres = $this->repo->nombresCategorias($ids);
        } catch (AppException $e) {
            throw $e;
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        return [
            'product_id' => $producto->id,
            'categories' => $this->armarCategorias($ids, $nombres),
        ];
    }

    /**
     * Solo se devuelven categorías que siguen existiendo (se saltan las inexistentes).
     *
     * @param list<int>        $ids
     * @param array<int,string> $nombres
     * @return list<array{id:int,nombre:string}>
     */
    private function armarCategorias(array $ids, array $nombres): array
    {
        $categories = [];
        foreach ($ids as $id) {
            if (isset($nombres[$id])) {
                $categories[] = ['id' => $id, 'nombre' => $nombres[$id]];
            }
        }
        return $categories;
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
