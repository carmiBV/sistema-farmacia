<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Promocion;
use App\Services\PromocionService;

final class PromocionController
{
    public function __construct(
        private readonly PromocionService $service = new PromocionService(),
    ) {
    }

    /** GET /api/v1/catalog/promotions — lista paginada con filtros product_id/category_id. */
    public function index(): void
    {
        $result = $this->service->listar($_GET);
        Response::json([
            'success' => true,
            'data' => array_map(
                static fn (Promocion $p): array => $p->toArray(),
                $result['data']
            ),
            'meta' => $result['meta'],
        ]);
    }

    /** GET /api/v1/catalog/promotions/{id} — 200 | 400 | 404. */
    public function show(string $id): void
    {
        Response::json([
            'success' => true,
            'data' => $this->service->obtener($id)->toArray(),
        ]);
    }

    /** POST /api/v1/catalog/promotions — 201 creado | 400 | 409 referencias. */
    public function store(): void
    {
        $nueva = $this->service->crear(Request::jsonBody());
        Response::json(['success' => true, 'data' => $nueva->toArray()], 201);
    }

    /** PUT /api/v1/catalog/promotions/{id} — 200 | 400 | 404 | 409 (cierra vigencia para dar de baja). */
    public function update(string $id): void
    {
        $actualizada = $this->service->actualizar($id, Request::jsonBody());
        Response::json(['success' => true, 'data' => $actualizada->toArray()]);
    }
}
