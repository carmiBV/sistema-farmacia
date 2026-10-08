<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\VentaService;

/**
 * API Ventas POS: ordenes, pagos y devoluciones (CP-BACK-08).
 * RF-050 (efectivo/tarjeta/otros), RF-060 (devoluciones), RNF-022 (POS).
 */
final class VentaController
{
    private readonly VentaService $service;

    public function __construct()
    {
        $this->service = new VentaService();
    }

    /** GET /sales/orders (lectura con token). */
    public function index(): void
    {
        $r = $this->service->listar($_GET);
        Response::json(['success' => true, 'data' => $r['data'], 'meta' => $r['meta']]);
    }

    /** GET /sales/orders/{id}. */
    public function show(string $id): void
    {
        Response::json(['success' => true, 'data' => $this->service->obtener($id)]);
    }

    /**
     * POST /sales/orders -> 201 (o 200 si la clave de idempotencia reutilizo).
     * Requiere permiso 'sales.manage'.
     */
    public function store(): void
    {
        $r = $this->service->crear(Request::jsonBody(), (int) Auth::user()['id']);
        Response::json(['success' => true, 'data' => $r], $r['idempotent_reused'] ? 200 : 201);
    }

    /** POST /sales/orders/{id}/payments -> 200 (idempotente). */
    public function pagar(string $id): void
    {
        $r = $this->service->pagar($id, Request::jsonBody(), (int) Auth::user()['id']);
        Response::json(['success' => true, 'data' => $r]);
    }

    /** POST /sales/orders/{id}/anular -> 200 (repone stock, movimiento compensatorio). */
    public function anular(string $id): void
    {
        $r = $this->service->anular($id, (int) Auth::user()['id']);
        Response::json(['success' => true, 'data' => $r]);
    }

    /** GET /sales/returns (lectura con token). */
    public function indexDevoluciones(): void
    {
        $r = $this->service->listarDevoluciones($_GET);
        Response::json(['success' => true, 'data' => $r['data'], 'meta' => $r['meta']]);
    }

    /** POST /sales/returns -> 201. Requiere permiso 'sales.manage'. */
    public function storeDevolucion(): void
    {
        $r = $this->service->crearDevolucion(Request::jsonBody(), (int) Auth::user()['id']);
        Response::json(['success' => true, 'data' => $r], 201);
    }

    /** GET /sales/returns/{id}. */
    public function showDevolucion(string $id): void
    {
        Response::json(['success' => true, 'data' => $this->service->obtenerDevolucion($id)]);
    }
}
