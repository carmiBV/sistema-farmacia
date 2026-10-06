<?php
declare(strict_types=1);

namespace App\Services;

use App\Http\AppException;
use App\Models\OrdenCompra;
use App\Repositories\OrdenCompraRepository;
use App\Validators\CompraValidator;

/**
 * Casos de uso de órdenes de compra (RF-030). Estados:
 * borrador -> emitida -> recibida; cancelada desde borrador/emitida sin recepciones.
 */
final class OrdenCompraService
{
    private const ESTADOS = ['borrador', 'emitida', 'recibida', 'cancelada'];

    public function __construct(
        private readonly OrdenCompraRepository $repo = new OrdenCompraRepository(),
        private readonly CompraValidator $validator = new CompraValidator(),
    ) {
    }

    /**
     * @param array<string,mixed> $query q, supplier_id, estado, page, limit
     * @return array{data:list<OrdenCompra>,meta:array{total:int,page:int,limit:int}}
     * @throws AppException VALIDATION_ERROR (400) | DATABASE_ERROR (500)
     */
    public function listar(array $query): array
    {
        $page = $this->entero($query, 'page', 1, 1, 1_000_000);
        $limit = $this->entero($query, 'limit', 20, 1, 100);
        $q = $this->texto($query, 'q');

        $filters = ['q' => $q, 'supplier_id' => $this->texto($query, 'supplier_id'),
            'estado' => $this->texto($query, 'estado')];
        try {
            $total = $this->repo->contar($filters);
            $items = $this->repo->listar($filters, $limit, ($page - 1) * $limit);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        return [
            'data' => $items,
            'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit],
        ];
    }

    /**
     * @param array<string,mixed> $input body PurchaseOrderCreate
     * @throws AppException VALIDATION_ERROR (400) | SUPPLIER_NOT_FOUND (409)
     *         | PRODUCT_NOT_FOUND (409) | DUPLICATE_NUMERO (409) | DATABASE_ERROR (500)
     */
    public function crear(array $input, int $userId): OrdenCompra
    {
        $datos = $this->validator->validarOrden($input);
        $numero = $datos['numero'] ?? ('OC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))));

        try {
            if ($this->repo->existeNumero($numero)) {
                throw new AppException('DUPLICATE_NUMERO', 'Ya existe una orden con ese número.', 409);
            }
            $this->validarReferencias($datos['supplier_id'], $datos['items']);
            $id = $this->repo->insertar($numero, $datos['supplier_id'], $userId);
            $this->repo->reemplazarItems($id, $datos['items']);
            $orden = $this->repo->buscarPorId($id);
        } catch (AppException $e) {
            throw $e;
        } catch (\PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                throw new AppException('DUPLICATE_NUMERO', 'Ya existe una orden con ese número.', 409);
            }
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        if ($orden === null) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return $orden;
    }

    /** @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (404) | DATABASE_ERROR (500) */
    public function obtener(string $rawId): OrdenCompra
    {
        try {
            $orden = $this->repo->buscarPorId($this->idEntero($rawId));
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        if ($orden === null) {
            throw new AppException('NOT_FOUND', 'Orden no encontrada.', 404);
        }
        return $orden;
    }

    /**
     * Reemplazo completo de la orden (solo borrador).
     *
     * @param array<string,mixed> $input body PurchaseOrderUpdate
     * @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (404)
     *         | ORDER_NOT_DRAFT (409) | DUPLICATE_NUMERO (409) | SUPPLIER_NOT_FOUND (409)
     *         | PRODUCT_NOT_FOUND (409) | DATABASE_ERROR (500)
     */
    public function actualizar(string $rawId, array $input): OrdenCompra
    {
        $id = $this->idEntero($rawId);
        $datos = $this->validator->validarOrden($input);
        $numero = $datos['numero'] ?? ('OC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))));

        try {
            $actual = $this->repo->buscarPorId($id);
            if ($actual === null) {
                throw new AppException('NOT_FOUND', 'Orden no encontrada.', 404);
            }
            if ($actual->estado !== 'borrador') {
                throw new AppException(
                    'ORDER_NOT_DRAFT',
                    'Solo las órdenes en borrador pueden modificarse.',
                    409
                );
            }
            if ($this->repo->existeNumero($numero) && $numero !== $actual->numero) {
                throw new AppException('DUPLICATE_NUMERO', 'Ya existe una orden con ese número.', 409);
            }
            $this->validarReferencias($datos['supplier_id'], $datos['items']);
            $this->repo->actualizar($id, $numero, $datos['supplier_id']);
            $this->repo->reemplazarItems($id, $datos['items']);
            $orden = $this->repo->buscarPorId($id);
        } catch (AppException $e) {
            throw $e;
        } catch (\PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                throw new AppException('DUPLICATE_NUMERO', 'Ya existe una orden con ese número.', 409);
            }
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        if ($orden === null) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return $orden;
    }

    /** borador -> emitida. Requiere al menos un item. */
    /** @throws AppException VALIDATION_ERROR (400) | NOT_FOUND (404) | ORDER_NOT_DRAFT (409) | DATABASE_ERROR (500) */
    public function emitir(string $rawId): OrdenCompra
    {
        $id = $this->idEntero($rawId);
        try {
            $orden = $this->repo->buscarPorId($id);
            if ($orden === null) {
                throw new AppException('NOT_FOUND', 'Orden no encontrada.', 404);
            }
            if ($orden->estado !== 'borrador') {
                throw new AppException(
                    'ORDER_NOT_DRAFT',
                    'Solo las órdenes en borrador pueden emitirse.',
                    409
                );
            }
            if ($this->repo->items($id) === []) {
                throw new AppException('VALIDATION_ERROR', 'La orden no tiene items.', 400);
            }
            if (!$this->repo->cambiarEstado($id, 'borrador', 'emitida')) {
                throw new AppException(
                    'ORDER_NOT_DRAFT',
                    'Solo las órdenes en borrador pueden emitirse.',
                    409
                );
            }
            $emitida = $this->repo->buscarPorId($id);
        } catch (AppException $e) {
            throw $e;
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }

        if ($emitida === null) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return $emitida;
    }

    /**
     * Cancelación lógica: estado='cancelada' (la fila nunca se borra, RNF-023).
     *
     * @throws AppException NOT_FOUND (404) | ORDER_NOT_CANCELLABLE (409)
     *         | ORDER_HAS_RECEPTIONS (409) | DATABASE_ERROR (500)
     */
    public function cancelar(string $rawId): void
    {
        $id = $this->idEntero($rawId);
        try {
            $orden = $this->repo->buscarPorId($id);
            if ($orden === null) {
                throw new AppException('NOT_FOUND', 'Orden no encontrada.', 404);
            }
            if (!in_array($orden->estado, ['borrador', 'emitida'], true)) {
                throw new AppException(
                    'ORDER_NOT_CANCELLABLE',
                    'Solo órdenes en borrador o emitidas pueden cancelarse.',
                    409
                );
            }
            if ($this->repo->tieneRecepciones($id)) {
                throw new AppException(
                    'ORDER_HAS_RECEPTIONS',
                    'La orden tiene recepciones y no puede cancelarse.',
                    409
                );
            }
            if (!$this->repo->cambiarEstado($id, $orden->estado, 'cancelada')) {
                throw new AppException(
                    'ORDER_NOT_CANCELLABLE',
                    'Solo órdenes en borrador o emitidas pueden cancelarse.',
                    409
                );
            }
        } catch (AppException $e) {
            throw $e;
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
    }

    /**
     * @return list<array{product_id:int,cantidad_pedida:int,cantidad_recibida:int}>
     */
    public function items(int $orderId): array
    {
        try {
            return $this->repo->items($orderId);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
    }

    /**
     * @param list<array{product_id:int,cantidad_pedida:int}> $items
     * @throws AppException SUPPLIER_NOT_FOUND (409) | PRODUCT_NOT_FOUND (409)
     */
    private function validarReferencias(int $supplierId, array $items): void
    {
        if (!$this->repo->existeSupplier($supplierId)) {
            throw new AppException('SUPPLIER_NOT_FOUND', 'El proveedor no existe.', 409);
        }
        foreach ($items as $item) {
            if (!$this->repo->existeProduct($item['product_id'])) {
                throw new AppException(
                    'PRODUCT_NOT_FOUND',
                    'El producto ' . $item['product_id'] . ' no existe.',
                    409
                );
            }
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
    private function texto(array $query, string $key): ?string
    {
        if (!array_key_exists($key, $query)) {
            return null;
        }
        $raw = $query[$key];
        if (!is_string($raw) || mb_strlen($raw) > 150) {
            throw new AppException('VALIDATION_ERROR', "Parámetro '{$key}' inválido.", 400);
        }
        $trim = trim($raw);
        return $trim === '' ? null : $trim;
    }
}
