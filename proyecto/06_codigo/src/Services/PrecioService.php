<?php
declare(strict_types=1);

namespace App\Services;

use App\Http\AppException;
use App\Models\Precio;
use App\Repositories\PrecioRepository;
use App\Validators\CatalogValidator;

final class PrecioService
{
    public function __construct(
        private readonly PrecioRepository $repo = new PrecioRepository(),
        private readonly CatalogValidator $validator = new CatalogValidator(),
    ) {
    }

    /**
     * @param array<string,mixed> $query parámetros de la URL (page, limit, product_id, store_id)
     * @return array{data:list<Precio>,meta:array{total:int,page:int,limit:int}}
     * @throws AppException VALIDATION_ERROR (400) | DATABASE_ERROR (500)
     */
    public function listar(array $query): array
    {
        $page = $this->entero($query, 'page', 1, 1, 1_000_000);
        $limit = $this->entero($query, 'limit', 20, 1, 100);
        $productId = $this->enteroOpcional($query, 'product_id');
        $storeId = $this->enteroOpcional($query, 'store_id');

        try {
            $total = $this->repo->contar($productId, $storeId);
            $items = $this->repo->listar($productId, $storeId, $limit, ($page - 1) * $limit);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        return [
            'data' => $items,
            'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit],
        ];
    }

    /**
     * @param array<string,mixed> $input body PriceCreate
     * @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (409) | DUPLICATE_PRICE (409) | DATABASE_ERROR (500)
     */
    public function crear(array $input): Precio
    {
        $datos = $this->validator->validarPrecio($input);

        try {
            if (!$this->repo->productoExiste($datos['product_id'])) {
                throw new AppException('NOT_FOUND', 'El producto indicado no existe.', 409);
            }
            if ($datos['store_id'] !== null && !$this->repo->sucursalExiste($datos['store_id'])) {
                throw new AppException('NOT_FOUND', 'La sucursal indicada no existe.', 409);
            }
            if ($this->repo->existeCombinacion($datos['product_id'], $datos['store_id'], $datos['vigente_desde'])) {
                throw new AppException(
                    'DUPLICATE_PRICE',
                    'Ya existe un precio para ese producto/sucursal en esa fecha.',
                    409
                );
            }
            $id = $this->repo->insertar($datos);
            $nueva = $this->repo->buscarPorId($id);
        } catch (AppException $e) {
            throw $e;
        } catch (\PDOException $e) {
            $driverCode = (int) ($e->errorInfo[1] ?? 0);
            if ($driverCode === 1062) {
                throw new AppException(
                    'DUPLICATE_PRICE',
                    'Ya existe un precio para ese producto/sucursal en esa fecha.',
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
    public function obtener(string $rawId): Precio
    {
        $id = $this->idEntero($rawId);
        try {
            $precio = $this->repo->buscarPorId($id);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        if ($precio === null) {
            throw new AppException('NOT_FOUND', 'Precio no encontrado.', 404);
        }
        return $precio;
    }

    /**
     * Actualización completa; para "dar de baja" se cierra la vigencia
     * (vigente_hasta) en lugar de eliminar el registro (nunca DELETE FROM).
     *
     * @param array<string,mixed> $input body PriceUpdate
     * @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (404/409) | DUPLICATE_PRICE (409) | DATABASE_ERROR (500)
     */
    public function actualizar(string $rawId, array $input): Precio
    {
        $id = $this->idEntero($rawId);
        $datos = $this->validator->validarPrecio($input);

        try {
            $actual = $this->repo->buscarPorId($id);
            if ($actual === null) {
                throw new AppException('NOT_FOUND', 'Precio no encontrado.', 404);
            }
            if (!$this->repo->productoExiste($datos['product_id'])) {
                throw new AppException('NOT_FOUND', 'El producto indicado no existe.', 409);
            }
            if ($datos['store_id'] !== null && !$this->repo->sucursalExiste($datos['store_id'])) {
                throw new AppException('NOT_FOUND', 'La sucursal indicada no existe.', 409);
            }
            if ($this->repo->existeCombinacion($datos['product_id'], $datos['store_id'], $datos['vigente_desde'], $id)) {
                throw new AppException(
                    'DUPLICATE_PRICE',
                    'Ya existe un precio para ese producto/sucursal en esa fecha.',
                    409
                );
            }
            $this->repo->actualizar($id, $datos);
            $resultado = $this->repo->buscarPorId($id);
        } catch (AppException $e) {
            throw $e;
        } catch (\PDOException $e) {
            $driverCode = (int) ($e->errorInfo[1] ?? 0);
            if ($driverCode === 1062) {
                throw new AppException(
                    'DUPLICATE_PRICE',
                    'Ya existe un precio para ese producto/sucursal en esa fecha.',
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
    private function enteroOpcional(array $query, string $key): ?int
    {
        if (!array_key_exists($key, $query)) {
            return null;
        }
        return $this->entero($query, $key, 0, 0, PHP_INT_MAX);
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
