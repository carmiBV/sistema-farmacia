<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Recepcion;
use App\Services\RecepcionService;

final class RecepcionController
{
    public function __construct(
        private readonly RecepcionService $service = new RecepcionService(),
    ) {
    }

    /** GET /api/v1/purchases/receptions — 200 | 400. */
    public function index(): void
    {
        $result = $this->service->listar($_GET);
        Response::json([
            'success' => true,
            'data' => array_map(
                static fn (Recepcion $r): array => $r->toArray(),
                $result['data']
            ),
            'meta' => $result['meta'],
        ]);
    }

    /** GET /api/v1/purchases/receptions/{id} — 200 | 400 | 404. */
    public function show(string $id): void
    {
        $reception = $this->service->obtener($id);
        Response::json([
            'success' => true,
            'data' => $reception->toArray() + ['items' => $this->service->items($reception->id)],
        ]);
    }

    /** POST /api/v1/purchases/orders/{orderId}/receptions — 201 | 200 (idempotente) | 400 | 404 | 409. */
    public function store(string $orderId): void
    {
        $resultado = $this->service->crear($orderId, Request::jsonBody(), Auth::user()['id']);
        $data = $resultado['reception']->toArray() + ['items' => $resultado['items']];
        Response::json(
            ['success' => true, 'data' => $data, 'meta' => ['idempotent_reused' => $resultado['reused']]],
            $resultado['reused'] ? 200 : 201
        );
    }

    /** POST /api/v1/purchases/receptions/{id}/confirmar — 200 | 400 | 404 | 409. */
    public function confirmar(string $id): void
    {
        $resultado = $this->service->confirmar($id, Request::jsonBody(), Auth::user()['id']);
        Response::json([
            'success' => true,
            'data' => $resultado['reception']->toArray()
                + ['items' => $resultado['items']],
        ]);
    }

    /** POST /api/v1/purchases/receptions/{id}/rechazar — 200 | 400 | 404 | 409. */
    public function rechazar(string $id): void
    {
        $resultado = $this->service->rechazar($id, Auth::user()['id']);
        Response::json([
            'success' => true,
            'data' => $resultado['reception']->toArray()
                + ['items' => $resultado['items']],
        ]);
    }
}
