<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Auth;
use App\Http\Request;
use App\Http\Response;
use App\Services\TransferenciaService;

final class TransferenciaController
{
    public function __construct(
        private readonly TransferenciaService $service = new TransferenciaService(),
    ) {
    }

    /** GET /api/v1/inventory/transfers — 200. */
    public function index(): void
    {
        $result = $this->service->listar($_GET);
        Response::json([
            'success' => true,
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }

    /** GET /api/v1/inventory/transfers/{id} — 200 | 400 | 404. */
    public function show(string $id): void
    {
        Response::json(['success' => true, 'data' => $this->service->obtener($id)]);
    }

    /** POST /api/v1/inventory/transfers — 201 | 200 (idempotente) | 400 | 404 | 409. */
    public function store(): void
    {
        $resultado = $this->service->crear(Request::jsonBody(), Auth::user()['id']);
        Response::json(
            ['success' => true, 'data' => $resultado],
            $resultado['idempotent_reused'] ? 200 : 201
        );
    }

    /** POST /api/v1/inventory/transfers/{id}/despachar — 200 | 400 | 404 | 409. */
    public function despachar(string $id): void
    {
        Response::json(['success' => true, 'data' => $this->service->despachar($id, Auth::user()['id'])]);
    }

    /** POST /api/v1/inventory/transfers/{id}/recibir — 200 | 400 | 404 | 409. */
    public function recibir(string $id): void
    {
        $data = $this->service->recibir($id, Request::jsonBody(), Auth::user()['id']);
        Response::json(['success' => true, 'data' => $data]);
    }

    /** POST /api/v1/inventory/transfers/{id}/cerrar — 200 | 400 | 404 | 409. */
    public function cerrar(string $id): void
    {
        Response::json(['success' => true, 'data' => $this->service->cerrar($id)]);
    }

    /** POST /api/v1/inventory/transfers/{id}/rechazar — 200 | 400 | 404 | 409. */
    public function rechazar(string $id): void
    {
        Response::json(['success' => true, 'data' => $this->service->rechazar($id)]);
    }
}
