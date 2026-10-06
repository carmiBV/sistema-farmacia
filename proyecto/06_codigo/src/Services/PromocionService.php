<?php
declare(strict_types=1);

namespace App\Services;

use App\Http\AppException;
use App\Models\Promocion;
use App\Repositories\PromocionRepository;
use App\Validators\CatalogValidator;

final class PromocionService
{
    public function __construct(
        private readonly PromocionRepository $repo = new PromocionRepository(),
        private readonly CatalogValidator $validator = new CatalogValidator(),
    ) {
    }

    /**
     * @param array<string,mixed> $query parámetros de la URL (page, limit, product_id, category_id)
     * @return array{data:list<Promocion>,meta:array{total:int,page:int,limit:int}}
     * @throws AppException VALIDATION_ERROR (400) | DATABASE_ERROR (500)
     */
    public function listar(array $query): array
    {
        $page = $this->entero($query, 'page', 1, 1, 1_000_000);
        $limit = $this->entero($query, 'limit', 20, 1, 100);
        $productId = $this->enteroOpcional($query, 'product_id');
        $categoryId = $this->enteroOpcional($query, 'category_id');

        try {
            $total = $this->repo->contar($productId, $categoryId);
            $items = $this->repo->listar($productId, $categoryId, $limit, ($page - 1) * $limit);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        return [
            'data' => $items,
            'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit],
        ];
    }

    /**
     * @param array<string,mixed> $input body PromotionCreate
     * @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (409) | DATABASE_ERROR (500)
     */
    public function crear(array $input): Promocion
    {
        $datos = $this->validator->validarPromocion($input);

        try {
            $this->verificarAlcance($datos['product_id'], $datos['category_id']);
            $id = $this->repo->insertar($datos);
            $nueva = $this->repo->buscarPorId($id);
        } catch (AppException $e) {
            throw $e;
        } catch (\PDOException) {
            // Sin uq sobre (product_id, category_id, desde): no hay 1062 esperado.
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        if ($nueva === null) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return $nueva;
    }

    /** @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (404) | DATABASE_ERROR (500) */
    public function obtener(string $rawId): Promocion
    {
        $id = $this->idEntero($rawId);
        try {
            $promocion = $this->repo->buscarPorId($id);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        if ($promocion === null) {
            throw new AppException('NOT_FOUND', 'Promoción no encontrada.', 404);
        }
        return $promocion;
    }

    /**
     * Actualización completa; para "dar de baja" se cierra la vigencia (hasta)
     * en lugar de eliminar el registro (nunca DELETE FROM).
     *
     * @param array<string,mixed> $input body PromotionUpdate
     * @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (404/409) | DATABASE_ERROR (500)
     */
    public function actualizar(string $rawId, array $input): Promocion
    {
        $id = $this->idEntero($rawId);
        $datos = $this->validator->validarPromocion($input);

        try {
            $actual = $this->repo->buscarPorId($id);
            if ($actual === null) {
                throw new AppException('NOT_FOUND', 'Promoción no encontrada.', 404);
            }
            $this->verificarAlcance($datos['product_id'], $datos['category_id']);
            $this->repo->actualizar($id, $datos);
            $resultado = $this->repo->buscarPorId($id);
        } catch (AppException $e) {
            throw $e;
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        if ($resultado === null) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return $resultado;
    }

    /**
     * 409 NOT_FOUND con el mensaje exacto del referente inexistente.
     * `existeAlcance` da la respuesta agregada; los métodos granulares
     * permiten elegir qué mensaje emitir.
     */
    private function verificarAlcance(?int $productId, ?int $categoryId): void
    {
        if ($this->repo->existeAlcance($productId, $categoryId)) {
            return;
        }
        if ($productId !== null && !$this->repo->productoExiste($productId)) {
            throw new AppException('NOT_FOUND', 'El producto indicado no existe.', 409);
        }
        throw new AppException('NOT_FOUND', 'La categoría indicada no existe.', 409);
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
