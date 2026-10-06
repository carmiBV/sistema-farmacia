<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Services\AuditService;

/**
 * API de auditoria y eventos salientes (CP-BACK-10).
 * RF-090 (operaciones criticas), RF-091 (acceso a PII), RN-13, RNF-046,
 * decision 16 (outbox desacoplado).
 */
final class AuditController
{
    private readonly AuditService $service;

    public function __construct()
    {
        $this->service = new AuditService();
    }

    /** GET /audit/operations. */
    public function indexOperaciones(): void
    {
        $r = $this->service->listarOperaciones($_GET);
        Response::json(['success' => true, 'data' => $r['data'], 'meta' => $r['meta']]);
    }

    /** GET /audit/operations/{id}. */
    public function showOperacion(string $id): void
    {
        Response::json(['success' => true, 'data' => $this->service->obtenerOperacion($id)]);
    }

    /** GET /audit/pii. */
    public function indexPii(): void
    {
        $r = $this->service->listarAccesosPii($_GET);
        Response::json(['success' => true, 'data' => $r['data'], 'meta' => $r['meta']]);
    }

    /** GET /audit/pii/{id}. */
    public function showPii(string $id): void
    {
        Response::json(['success' => true, 'data' => $this->service->obtenerAccesoPii($id)]);
    }

    /** GET /audit/events. */
    public function indexEventos(): void
    {
        $r = $this->service->listarEventos($_GET);
        Response::json(['success' => true, 'data' => $r['data'], 'meta' => $r['meta']]);
    }

    /** GET /audit/events/{id}. */
    public function showEvento(string $id): void
    {
        Response::json(['success' => true, 'data' => $this->service->obtenerEvento($id)]);
    }

    /** POST /audit/events/{id}/procesar -> 200. */
    public function procesarEvento(string $id): void
    {
        Response::json(['success' => true, 'data' => $this->service->procesarEvento($id)]);
    }

    /** POST /audit/events/{id}/fallar -> 200. */
    public function fallarEvento(string $id): void
    {
        Response::json(['success' => true, 'data' => $this->service->fallarEvento($id)]);
    }

    /** POST /audit/events/{id}/reintentar -> 200. */
    public function reintentarEvento(string $id): void
    {
        Response::json(['success' => true, 'data' => $this->service->reintentarEvento($id)]);
    }
}
