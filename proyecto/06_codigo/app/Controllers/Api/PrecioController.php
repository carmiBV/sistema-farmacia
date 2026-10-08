<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Models\Precio;
use App\Services\PrecioService;

final class PrecioController
{
    public function __construct(
        private readonly PrecioService $service = new PrecioService(),
    ) {
    }

    /** GET /api/v1/catalog/prices — lista paginada con filtros product_id/store_id. */
    public function index(): void
    {
        $result = $this->service->listar($_GET);
        Response::json([
            'success' => true,
            'data' => array_map(
                static fn (Precio $p): array => $p->toArray(),
                $result['data']
            ),
            'meta' => $result['meta'],
        ]);
    }

    /** GET /api/v1/catalog/prices/{id} — 200 | 400 | 404. */
    public function show(string $id): void
    {
        Response::json([
            'success' => true,
            'data' => $this->service->obtener($id)->toArray(),
        ]);
    }

    /** POST /api/v1/catalog/prices — 201 creado | 400 | 409 referencias/duplicado. */
    public function store(): void
    {
        $nueva = $this->service->crear(Request::jsonBody());
        Response::json(['success' => true, 'data' => $nueva->toArray()], 201);
    }

    /** PUT /api/v1/catalog/prices/{id} — 200 | 400 | 404 | 409 (cierra vigencia para dar de baja). */
    public function update(string $id): void
    {
        $actualizada = $this->service->actualizar($id, Request::jsonBody());
        Response::json(['success' => true, 'data' => $actualizada->toArray()]);
    }
}
