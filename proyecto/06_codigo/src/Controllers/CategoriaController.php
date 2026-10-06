<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Categoria;
use App\Services\CategoriaService;

final class CategoriaController
{
    public function __construct(
        private readonly CategoriaService $service = new CategoriaService(),
    ) {
    }

    /** GET /api/v1/catalog/categories — lista paginada con filtros q/parent_id. */
    public function index(): void
    {
        $result = $this->service->listar($_GET);
        Response::json([
            'success' => true,
            'data' => array_map(
                static fn (Categoria $c): array => $c->toArray(),
                $result['data']
            ),
            'meta' => $result['meta'],
        ]);
    }

    /** GET /api/v1/catalog/categories/{id} — 200 | 400 formato | 404 inexistente. */
    public function show(string $id): void
    {
        Response::json([
            'success' => true,
            'data' => $this->service->obtener($id)->toArray(),
        ]);
    }

    /** POST /api/v1/catalog/categories — 201 creado | 400 validación | 409 duplicado/padre inexistente. */
    public function store(): void
    {
        $nueva = $this->service->crear(Request::jsonBody());
        Response::json(['success' => true, 'data' => $nueva->toArray()], 201);
    }

    /** PUT /api/v1/catalog/categories/{id} — 200 | 400 | 404 | 409 duplicado/ciclo/padre inexistente. */
    public function update(string $id): void
    {
        $actualizada = $this->service->actualizar($id, Request::jsonBody());
        Response::json(['success' => true, 'data' => $actualizada->toArray()]);
    }

    /** DELETE /api/v1/catalog/categories/{id} — 204 sin cuerpo | 400 | 404 (borrado lógico). */
    public function destroy(string $id): void
    {
        $this->service->inactivar($id);
        Response::noContent();
    }
}
