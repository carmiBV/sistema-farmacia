<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\InventarioService;

final class InventarioController
{
    public function __construct(
        private readonly InventarioService $service = new InventarioService(),
    ) {
    }

    /** GET /api/v1/inventory/stocks — 200. */
    public function stocks(): void
    {
        $this->responder($this->service->listarStocks($_GET));
    }

    /** GET /api/v1/inventory/movements — 200. */
    public function movimientos(): void
    {
        $this->responder($this->service->listarMovimientos($_GET));
    }

    /** GET /api/v1/inventory/batches — 200. */
    public function lotes(): void
    {
        $this->responder($this->service->listarLotes($_GET));
    }

    /** GET /api/v1/inventory/alerts — 200. */
    public function alertas(): void
    {
        $this->responder($this->service->listarAlertas($_GET));
    }

    /** GET /api/v1/inventory/incidents — 200. */
    public function incidentes(): void
    {
        $this->responder($this->service->listarIncidentes($_GET));
    }

    /** POST /api/v1/inventory/batches/{id}/liberar — 200 | 400 | 404 | 409. */
    public function liberar(string $id): void
    {
        $lote = $this->service->liberar($id, Request::jsonBody(), Auth::user()['id']);
        Response::json(['success' => true, 'data' => $lote]);
    }

    /** POST /api/v1/inventory/alerts/evaluar — 200. */
    public function evaluarAlertas(): void
    {
        Response::json(['success' => true, 'data' => $this->service->evaluarAlertas()]);
    }

    /** POST /api/v1/inventory/alerts/{id}/resolver — 200 | 400 | 404 | 409. */
    public function resolverAlerta(string $id): void
    {
        $alerta = $this->service->resolverAlerta($id, Auth::user()['id']);
        Response::json(['success' => true, 'data' => $alerta]);
    }

    /** POST /api/v1/inventory/incidents — 201 | 400 | 404 | 409. */
    public function crearIncidente(): void
    {
        $incidente = $this->service->crearIncidente(Request::jsonBody(), Auth::user()['id']);
        Response::json(['success' => true, 'data' => $incidente], 201);
    }

    /** POST /api/v1/inventory/incidents/{id}/ajustar — 200 | 400 | 404 | 409. */
    public function ajustarIncidente(string $id): void
    {
        $incidente = $this->service->ajustarIncidente($id, Auth::user()['id']);
        Response::json(['success' => true, 'data' => $incidente]);
    }

    /** POST /api/v1/inventory/incidents/{id}/descartar — 200 | 400 | 404 | 409. */
    public function descartarIncidente(string $id): void
    {
        Response::json(['success' => true, 'data' => $this->service->descartarIncidente($id)]);
    }

    /** @param array<string,mixed> $result */
    private function responder(array $result): void
    {
        Response::json([
            'success' => true,
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }
}
