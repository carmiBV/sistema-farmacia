<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Models\Paciente;
use App\Services\PacienteService;
use App\Core\Audit;

final class PacienteController
{
    public function __construct(
        private readonly PacienteService $service = new PacienteService(),
    ) {
    }

    /** GET /api/v1/catalog/patients - lista paginada con filtro q. */
    public function index(): void
    {
        $result = $this->service->listar($_GET);
        $filas = array_map(static fn (Paciente $p): array => $p->toArray(), $result['data']);
        foreach ($result['data'] as $p) {
            Audit::pii('consulta', (int) $p->id, null, 'Listado de pacientes');
        }
        Response::json(['success' => true, 'data' => $filas, 'meta' => $result['meta']]);
    }

    /** GET /api/v1/catalog/patients/{id} - 200 | 400 | 404. */
    public function show(string $id): void
    {
        $paciente = $this->service->obtener($id);
        Audit::pii('consulta', (int) $paciente->id, null, 'Consulta de paciente');
        Response::json(['success' => true, 'data' => $paciente->toArray()]);
    }

    /** POST /api/v1/catalog/patients - 201 creado | 400 | 409 identificacion duplicada. */
    public function store(): void
    {
        $nueva = $this->service->crear(Request::jsonBody());
        Audit::pii('creacion', (int) $nueva->id, null, 'Alta de paciente');
        Response::json(['success' => true, 'data' => $nueva->toArray()], 201);
    }

    /** PUT /api/v1/catalog/patients/{id} - 200 | 400 | 404 | 409 duplicado. */
    public function update(string $id): void
    {
        $actualizada = $this->service->actualizar($id, Request::jsonBody());
        Audit::pii('modificacion', (int) $actualizada->id, null, 'Modificacion de paciente');
        Response::json(['success' => true, 'data' => $actualizada->toArray()]);
    }

    /** DELETE /api/v1/catalog/patients/{id} - 204 sin cuerpo | 400 | 404 (anonimizacion de PII). */
    public function destroy(string $id): void
    {
        $this->service->anonimizar($id);
        Audit::pii('modificacion', (int) $id, null, 'Anonimizacion de paciente');
        Response::noContent();
    }
}
