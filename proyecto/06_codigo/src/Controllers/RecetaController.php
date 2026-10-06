<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\RecetaService;
use App\Support\Audit;

final class RecetaController
{
    public function __construct(
        private readonly RecetaService $service = new RecetaService(),
    ) {
    }

    /** GET /api/v1/rx/prescriptions - 200. */
    public function index(): void
    {
        $result = $this->service->listar($_GET);
        foreach ($result['data'] as $fila) {
            // RF-091: una fila por cada receta consultada (chk_apii_objetivo).
            Audit::pii('consulta', self::entero($fila['patient_id'] ?? null), self::entero($fila['id'] ?? null), 'Listado de recetas');
        }
        Response::json(['success' => true, 'data' => $result['data'], 'meta' => $result['meta']]);
    }

    /** GET /api/v1/rx/prescriptions/{id} - 200 | 400 | 404. */
    public function show(string $id): void
    {
        $data = $this->service->obtener($id);
        self::registrar('consulta', $id, $data, 'Consulta de receta');
        Response::json(['success' => true, 'data' => $data]);
    }

    /** POST /api/v1/rx/prescriptions - 201 | 400 | 404 | 409. */
    public function store(): void
    {
        $data = $this->service->crear(Request::jsonBody());
        self::registrar('creacion', (string) ($data['prescripcion']['id'] ?? ''), $data, 'Alta de receta');
        Response::json(['success' => true, 'data' => $data], 201);
    }

    /** POST /api/v1/rx/prescriptions/{id}/dispensar - 200 | 400 | 404 | 409. */
    public function dispensar(string $id): void
    {
        $data = $this->service->dispensar($id, Request::jsonBody());
        self::registrar('modificacion', $id, $data, 'Dispensacion de receta');
        Response::json(['success' => true, 'data' => $data]);
    }

    /**
     * RF-091 / RN-13: una fila por acceso, con paciente y receta como objetivos.
     *
     * @param array{prescripcion?:array<string,mixed>} $data
     */
    private static function registrar(string $accion, string $id, array $data, string $motivo): void
    {
        $pres = $data['prescripcion'] ?? [];
        $prescriptionId = self::entero($pres['id'] ?? null) ?? self::entero($id);
        $patientId = self::entero($pres['patient_id'] ?? ($pres['paciente_id'] ?? null));
        Audit::pii($accion, $patientId, $prescriptionId, $motivo);
    }

    private static function entero(mixed $v): ?int
    {
        return is_numeric($v) ? (int) $v : null;
    }
}
