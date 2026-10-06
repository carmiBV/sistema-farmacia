<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Auth;
use App\Http\Request;
use App\Http\Response;
use App\Services\ConfigService;

final class ConfigController
{
    public function __construct(
        private readonly ConfigService $service = new ConfigService(),
    ) {
    }

    /** GET /api/v1/ops/config — lista paginada con filtro store_id. */
    public function index(): void
    {
        $result = $this->service->listar($_GET);
        Response::json([
            'success' => true,
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }

    /** GET /api/v1/ops/config/{key} — 200 | 400 clave inválida | 404 inexistente. */
    public function show(string $key): void
    {
        Response::json([
            'success' => true,
            'data' => $this->service->obtener($key),
        ]);
    }

    /** PUT /api/v1/ops/config/{key} — 200 upsert | 400 validación | 409 sucursal inexistente. */
    public function update(string $key): void
    {
        $guardado = $this->service->guardar($key, Request::jsonBody(), Auth::user()['id']);
        Response::json(['success' => true, 'data' => $guardado]);
    }
}
