<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

final class AuthController
{
    public function __construct(
        private readonly AuthService $service = new AuthService(),
    ) {
    }

    /** POST /api/v1/auth/login — 200 | 400 validación | 401 credenciales | 403 cuenta | 429 throttle. */
    public function login(): void
    {
        Response::json(['success' => true, 'data' => $this->service->login(Request::jsonBody())]);
    }

    /** POST /api/v1/auth/logout — 200 | 401 token inválido/expirado/ya revocado. */
    public function logout(): void
    {
        $token = Auth::bearer();
        if ($token === null) {
            throw new \App\Core\AppException('UNAUTHENTICATED', 'Se requiere token de acceso.', 401);
        }
        $this->service->logout($token);
        Response::json(['success' => true, 'data' => ['revocado' => true]]);
    }

    /** GET /api/v1/auth/me — 200 con roles y permisos | 401 sin token/inválido | 403 cuenta inactiva. */
    public function me(): void
    {
        Response::json(['success' => true, 'data' => Auth::user()]);
    }
}
