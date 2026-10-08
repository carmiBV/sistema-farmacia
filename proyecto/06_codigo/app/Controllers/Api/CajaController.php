<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Caja;
use App\Services\CajaService;

final class CajaController
{
    public function __construct(
        private readonly CajaService $service = new CajaService(),
    ) {
    }

    /** GET /api/v1/ops/registers — lista paginada con filtros q/store_id. */
    public function index(): void
    {
        $result = $this->service->listar($_GET);
        Response::json([
            'success' => true,
            'data' => array_map(
                static fn (Caja $c): array => $c->toArray(),
                $result['data']
            ),
            'meta' => $result['meta'],
        ]);
    }

    /** GET /api/v1/ops/registers/{id} — 200 | 400 | 404. */
    public function show(string $id): void
    {
        Response::json([
            'success' => true,
            'data' => $this->service->obtener($id)->toArray(),
        ]);
    }

    /** POST /api/v1/ops/registers — 201 creado | 400 | 409 duplicado/sucursal inexistente. */
    public function store(): void
    {
        $nueva = $this->service->crear(Request::jsonBody());
        Response::json(['success' => true, 'data' => $nueva->toArray()], 201);
    }

    /** PUT /api/v1/ops/registers/{id} — 200 | 400 | 404 | 409. */
    public function update(string $id): void
    {
        $actualizada = $this->service->actualizar($id, Request::jsonBody());
        Response::json(['success' => true, 'data' => $actualizada->toArray()]);
    }

    /** DELETE /api/v1/ops/registers/{id} — 204 | 400 | 404 (borrado lógico). */
    public function destroy(string $id): void
    {
        $this->service->inactivar($id);
        Response::noContent();
    }

    // ── Turnos ──────────────────────────────────────────────────────────────

    /** GET /api/v1/ops/registers/{id}/shift — 200 con turno abierto o data:null. */
    public function shiftShow(string $id): void
    {
        Response::json([
            'success' => true,
            'data' => $this->service->turnoActual($id),
        ]);
    }

    /** POST /api/v1/ops/registers/{id}/shift/open — 200 | 404 | 409 turno ya abierto. */
    public function shiftOpen(string $id): void
    {
        $turno = $this->service->abrirTurno($id, Auth::user()['id']);
        Response::json(['success' => true, 'data' => $turno]);
    }

    /** POST /api/v1/ops/registers/{id}/shift/close — 200 | 404 | 409 sin turno abierto. */
    public function shiftClose(string $id): void
    {
        $turno = $this->service->cerrarTurno($id, Auth::user()['id']);
        Response::json(['success' => true, 'data' => $turno]);
    }
}
