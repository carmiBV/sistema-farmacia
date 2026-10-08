<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\OrdenCompra;
use App\Services\OrdenCompraService;

final class OrdenCompraController
{
    public function __construct(
        private readonly OrdenCompraService $service = new OrdenCompraService(),
    ) {
    }

    /** GET /api/v1/purchases/orders — 200 | 400. */
    public function index(): void
    {
        $result = $this->service->listar($_GET);
        Response::json([
            'success' => true,
            'data' => array_map(
                static fn (OrdenCompra $o): array => $o->toArray(),
                $result['data']
            ),
            'meta' => $result['meta'],
        ]);
    }

    /** POST /api/v1/purchases/orders — 201 | 400 | 409. */
    public function store(): void
    {
        $orden = $this->service->crear(Request::jsonBody(), Auth::user()['id']);
        $data = $orden->toArray();
        $data['items'] = $this->service->items($orden->id);
        Response::json(['success' => true, 'data' => $data], 201);
    }

    /** GET /api/v1/purchases/orders/{id} — 200 | 400 | 404. */
    public function show(string $id): void
    {
        $orden = $this->service->obtener($id);
        $data = $orden->toArray();
        $data['items'] = $this->service->items($orden->id);
        Response::json(['success' => true, 'data' => $data]);
    }

    /** PUT /api/v1/purchases/orders/{id} — 200 | 400 | 404 | 409. */
    public function update(string $id): void
    {
        $orden = $this->service->actualizar($id, Request::jsonBody());
        $data = $orden->toArray();
        $data['items'] = $this->service->items($orden->id);
        Response::json(['success' => true, 'data' => $data]);
    }

    /** POST /api/v1/purchases/orders/{id}/emitir — 200 | 400 | 404 | 409. */
    public function emitir(string $id): void
    {
        $orden = $this->service->emitir($id);
        Response::json(['success' => true, 'data' => $orden->toArray()]);
    }

    /** DELETE /api/v1/purchases/orders/{id} — 204 cancelación lógica | 404 | 409. */
    public function destroy(string $id): void
    {
        $this->service->cancelar($id);
        Response::noContent();
    }
}
