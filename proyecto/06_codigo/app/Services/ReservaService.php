<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Repositories\InventarioRepository;
use App\Validators\InventarioValidator;

/**
 * CP-BACK-06 · Reservas de stock FEFO (DB-P01): no tocan inventory_stock;
 * sólo registran intención con expiración. Cancelación/confirmación por CAS.
 */
final class ReservaService
{
    public function __construct(
        private readonly InventarioRepository $repo = new InventarioRepository(),
        private readonly InventarioValidator $validator = new InventarioValidator(),
    ) {
    }

    /** @param array<string,mixed> $q */
    public function listar(array $q): array
    {
        $filtros = [
            'status' => $this->validator->enumParam($q, 'status', InventarioValidator::ESTADOS_RESERVA),
            'store_id' => $this->validator->entero($q, 'store_id', 1, PHP_INT_MAX),
            'product_id' => $this->validator->entero($q, 'product_id', 1, PHP_INT_MAX),
        ];
        $pag = $this->validator->paginacion($q);
        try {
            $total = $this->repo->contarReservas($filtros);
            $items = $this->repo->listarReservas($filtros, $pag['limit'], ($pag['page'] - 1) * $pag['limit']);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return ['data' => $items, 'meta' => ['total' => $total, 'page' => $pag['page'], 'limit' => $pag['limit']]];
    }

    /** POST /inventory/reservations: valida stock FEFO agregado (RN-03). */
    public function crear(array $input): array
    {
        $d = $this->validator->validarReserva($input);
        if (!$this->repo->storeExiste($d['store_id'])) {
            throw new AppException('NOT_FOUND', 'Sucursal no encontrada.', 404);
        }
        if (!$this->repo->productoExiste($d['product_id'])) {
            throw new AppException('NOT_FOUND', 'Producto no encontrado.', 404);
        }
        if ($this->repo->stockDisponibleFEFO($d['store_id'], $d['product_id']) < $d['qty']) {
            throw new AppException('INSUFFICIENT_STOCK', 'Stock FEFO insuficiente para la reserva.', 409);
        }
        $expiresAt = date('Y-m-d H:i:s', time() + $d['ttl_minutos'] * 60);
        try {
            $id = $this->repo->insertarReserva($d['store_id'], $d['product_id'], $d['qty'], $d['order_id'], $expiresAt);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return $this->repo->buscarReserva($id);
    }

    /** POST /inventory/reservations/{id}/confirmar (CAS pending → confirmed). */
    public function confirmar(string $rawId): array
    {
        return $this->cambiarEstado($rawId, 'confirmed', 'RESERVATION_NOT_PENDING', 'La reserva no está pendiente.');
    }

    /** POST /inventory/reservations/{id}/cancelar (CAS pending → cancelled). */
    public function cancelar(string $rawId): array
    {
        return $this->cambiarEstado($rawId, 'cancelled', 'RESERVATION_NOT_PENDING', 'La reserva no está pendiente.');
    }

    private function cambiarEstado(string $rawId, string $nuevo, string $codigo, string $mensaje): array
    {
        $id = $this->idEntero($rawId);
        $r = $this->repo->buscarReserva($id);
        if ($r === null) {
            throw new AppException('NOT_FOUND', 'Reserva no encontrada.', 404);
        }
        try {
            $ok = $r['status'] === 'pending' && $this->repo->cambiarEstadoReserva($id, 'pending', $nuevo);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        if (!$ok) {
            throw new AppException($codigo, $mensaje, 409);
        }
        return $this->repo->buscarReserva($id);
    }

    private function idEntero(string $rawId): int
    {
        $id = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new AppException('VALIDATION_ERROR', "Parámetro 'id' inválido.", 400);
        }
        return $id;
    }
}
