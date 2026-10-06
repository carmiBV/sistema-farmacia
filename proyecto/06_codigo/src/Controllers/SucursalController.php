<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Sucursal;
use App\Services\SucursalService;

final class SucursalController
{
    public function __construct(
        private readonly SucursalService $service = new SucursalService(),
    ) {
    }

    /** GET /api/v1/ops/stores — lista paginada con filtro q. */
    public function index(): void
    {
        $result = $this->service->listar($_GET);
        Response::json([
            'success' => true,
            'data' => array_map(
                static fn (Sucursal $s): array => $s->toArray(),
                $result['data']
            ),
            'meta' => $result['meta'],
        ]);
    }

    /** GET /api/v1/ops/stores/{id} — 200 | 400 | 404. */
    public function show(string $id): void
    {
        Response::json([
            'success' => true,
            'data' => $this->service->obtener($id)->toArray(),
        ]);
    }

    /** POST /api/v1/ops/stores — 201 creado | 400 | 409 código duplicado. */
    public function store(): void
    {
        $nueva = $this->service->crear(Request::jsonBody());
        Response::json(['success' => true, 'data' => $nueva->toArray()], 201);
    }

    /** PUT /api/v1/ops/stores/{id} — 200 | 400 | 404 | 409 duplicado. */
    public function update(string $id): void
    {
        $actualizada = $this->service->actualizar($id, Request::jsonBody());
        Response::json(['success' => true, 'data' => $actualizada->toArray()]);
    }

    /** DELETE /api/v1/ops/stores/{id} — 204 sin cuerpo | 400 | 404 (borrado lógico). */
    public function destroy(string $id): void
    {
        $this->service->inactivar($id);
        Response::noContent();
    }
}
