<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Models\Producto;
use App\Services\ProductoService;

final class ProductoController
{
    public function __construct(
        private readonly ProductoService $service = new ProductoService(),
    ) {
    }

    /** GET /api/v1/catalog/products — lista paginada con filtros q/category_id. */
    public function index(): void
    {
        $result = $this->service->listar($_GET);
        Response::json([
            'success' => true,
            'data' => array_map(
                static fn (Producto $p): array => $p->toArray(),
                $result['data']
            ),
            'meta' => $result['meta'],
        ]);
    }

    /** GET /api/v1/catalog/products/{id} — 200 | 400 formato | 404 inexistente. */
    public function show(string $id): void
    {
        Response::json([
            'success' => true,
            'data' => $this->service->obtener($id)->toArray(),
        ]);
    }

    /** POST /api/v1/catalog/products — 201 creado | 400 validación | 409 SKU duplicado. */
    public function store(): void
    {
        $nueva = $this->service->crear(Request::jsonBody());
        Response::json(['success' => true, 'data' => $nueva->toArray()], 201);
    }

    /** PUT /api/v1/catalog/products/{id} — 200 | 400 | 404 | 409 SKU duplicado. */
    public function update(string $id): void
    {
        $actualizado = $this->service->actualizar($id, Request::jsonBody());
        Response::json(['success' => true, 'data' => $actualizado->toArray()]);
    }

    /** DELETE /api/v1/catalog/products/{id} — 204 sin cuerpo | 400 | 404 (borrado lógico). */
    public function destroy(string $id): void
    {
        $this->service->inactivar($id);
        Response::noContent();
    }

    /** GET /api/v1/catalog/products/{id}/categories — 200 | 400 | 404. */
    public function categorias(string $id): void
    {
        Response::json([
            'success' => true,
            'data' => $this->service->obtenerCategorias($id),
        ]);
    }

    /** PUT /api/v1/catalog/products/{id}/categories — 200 | 400 | 404 | 409 categoría inexistente. */
    public function sincronizarCategorias(string $id): void
    {
        Response::json([
            'success' => true,
            'data' => $this->service->sincronizarCategorias($id, Request::jsonBody()),
        ]);
    }
}
