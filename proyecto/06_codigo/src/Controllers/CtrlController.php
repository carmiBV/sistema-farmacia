<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Auth;
use App\Http\Request;
use App\Http\Response;
use App\Services\CtrlService;

/**
 * API del Libro Oficial de Medicamentos Controlados (CP-BACK-09).
 * RF-045/RF-046/RF-055/RF-071, RN-05/RN-06, RNF-024 (conciliacion).
 */
final class CtrlController
{
    private readonly CtrlService $service;

    public function __construct()
    {
        $this->service = new CtrlService();
    }

    /** GET /control/ledger (lectura con token; filtros por periodo para RF-071). */
    public function indexAsientos(): void
    {
        $r = $this->service->listarAsientos($_GET);
        Response::json(['success' => true, 'data' => $r['data'], 'meta' => $r['meta']]);
    }

    /** GET /control/ledger/{id}. */
    public function showAsiento(string $id): void
    {
        Response::json(['success' => true, 'data' => $this->service->obtenerAsiento($id)]);
    }

    /** GET /control/balances - saldo permanente por sucursal y producto (RF-055). */
    public function saldos(): void
    {
        $r = $this->service->listarSaldos($_GET);
        Response::json(['success' => true, 'data' => $r['data'], 'meta' => $r['meta']]);
    }

    /** GET /control/reconciliation - conciliacion automatica de saldos (RNF-024). */
    public function conciliacion(): void
    {
        Response::json(['success' => true, 'data' => $this->service->conciliacion()]);
    }

    /** POST /control/adjustments -> 201. Doble autorizacion (RF-046). */
    public function ajustar(): void
    {
        $r = $this->service->ajustar(Request::jsonBody(), (int) Auth::user()['id']);
        Response::json(['success' => true, 'data' => $r], 201);
    }
}
