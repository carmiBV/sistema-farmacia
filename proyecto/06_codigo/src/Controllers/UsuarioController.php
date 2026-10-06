<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\UsuarioService;

final class UsuarioController
{
    public function __construct(
        private readonly UsuarioService $service = new UsuarioService(),
    ) {
    }

    /** GET /api/v1/auth/users — 200 (requiere permiso auth.users.manage). */
    public function index(): void
    {
        $lista = $this->service->listarUsuarios();
        Response::json([
            'success' => true,
            'data' => $lista,
            'meta' => ['total' => count($lista)],
        ]);
    }

    /** POST /api/v1/auth/users — 201 | 400 validación/rol inexistente | 409 duplicado. */
    public function store(): void
    {
        $nuevo = $this->service->crearUsuario(Request::jsonBody());
        Response::json(['success' => true, 'data' => $nuevo], 201);
    }

    /** PUT /api/v1/auth/users/{id}/roles — 200 | 400 rol inexistente/mal formato | 404. */
    public function asignarRoles(string $id): void
    {
        $userId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($userId === false) {
            throw new \App\Http\AppException('VALIDATION_ERROR', 'El id debe ser un entero positivo.', 400);
        }
        $resultado = $this->service->asignarRoles((int)$userId, Request::jsonBody());
        Response::json(['success' => true, 'data' => $resultado]);
    }

    /** GET /api/v1/auth/roles — 200 (requiere permiso auth.users.manage). */
    public function roles(): void
    {
        $lista = $this->service->listarRoles();
        Response::json([
            'success' => true,
            'data' => $lista,
            'meta' => ['total' => count($lista)],
        ]);
    }

    /** POST /api/v1/auth/roles — 201 | 400 validación clave | 409 duplicado. */
    public function crearRol(): void
    {
        $nuevo = $this->service->crearRol(Request::jsonBody());
        Response::json(['success' => true, 'data' => $nuevo], 201);
    }
}
