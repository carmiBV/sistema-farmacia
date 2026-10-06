<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Proveedor;
use App\Services\ProveedorService;

final class ProveedorController
{
    public function __construct(
        private readonly ProveedorService $service = new ProveedorService(),
    ) {
    }

    /** GET /api/v1/catalog/suppliers — lista paginada con filtro q. */
    public function index(): void
    {
        $result = $this->service->listar($_GET);
        Response::json([
            'success' => true,
            'data' => array_map(
                static fn (Proveedor $p): array => $p->toArray(),
                $result['data']
            ),
            'meta' => $result['meta'],
        ]);
    }

    /** GET /api/v1/catalog/suppliers/{id} — 200 | 400 | 404. */
    public function show(string $id): void
    {
        Response::json([
            'success' => true,
            'data' => $this->service->obtener($id)->toArray(),
        ]);
    }

    /** POST /api/v1/catalog/suppliers — 201 creado | 400 | 409 identificación duplicada. */
    public function store(): void
    {
        $nueva = $this->service->crear(Request::jsonBody());
        Response::json(['success' => true, 'data' => $nueva->toArray()], 201);
    }

    /** PUT /api/v1/catalog/suppliers/{id} — 200 | 400 | 404 | 409 duplicado. */
    public function update(string $id): void
    {
        $actualizada = $this->service->actualizar($id, Request::jsonBody());
        Response::json(['success' => true, 'data' => $actualizada->toArray()]);
    }

    /** DELETE /api/v1/catalog/suppliers/{id} — 204 sin cuerpo | 400 | 404 (borrado lógico). */
    public function destroy(string $id): void
    {
        $this->service->inactivar($id);
        Response::noContent();
    }
}
