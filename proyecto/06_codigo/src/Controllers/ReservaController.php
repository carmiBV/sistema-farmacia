<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\ReservaService;

final class ReservaController
{
    public function __construct(
        private readonly ReservaService $service = new ReservaService(),
    ) {
    }

    /** GET /api/v1/inventory/reservations — 200. */
    public function index(): void
    {
        $result = $this->service->listar($_GET);
        Response::json([
            'success' => true,
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }

    /** POST /api/v1/inventory/reservations — 201 | 400 | 404 | 409. */
    public function store(): void
    {
        Response::json(['success' => true, 'data' => $this->service->crear(Request::jsonBody())], 201);
    }

    /** POST /api/v1/inventory/reservations/{id}/confirmar — 200 | 400 | 404 | 409. */
    public function confirmar(string $id): void
    {
        Response::json(['success' => true, 'data' => $this->service->confirmar($id)]);
    }

    /** POST /api/v1/inventory/reservations/{id}/cancelar — 200 | 400 | 404 | 409. */
    public function cancelar(string $id): void
    {
        Response::json(['success' => true, 'data' => $this->service->cancelar($id)]);
    }
}
