<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\AuthService;

/**
 * Middleware de autenticación/autorización para el Router:
 *  - permission = null  → ruta pública
 *  - permission = ''    → requiere token de usuario válido
 *  - permission = clave → requiere token + permiso (RBAC)
 */
final class Auth
{
    /** @var array<string,mixed>|null */
    private static ?array $user = null;
    private static bool $resolved = false;

    public static function bearer(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(\S+)$/i', trim($header), $m) === 1) {
            return $m[1];
        }
        return null;
    }

    /** @throws AppException UNAUTHENTICATED (401) / ACCOUNT_DISABLED (403) */
    public static function user(): array
    {
        if (!self::$resolved) {
            self::$resolved = true;
            $token = self::bearer();
            if ($token === null) {
                throw new AppException('UNAUTHENTICATED', 'Se requiere token de acceso.', 401);
            }
            self::$user = (new AuthService())->usuarioAutenticado($token);
        }
        return self::$user ?? throw new AppException('UNAUTHENTICATED', 'Se requiere token de acceso.', 401);
    }

    /** @throws AppException FORBIDDEN (403) */
    public static function requirePermission(string $clave): array
    {
        $user = self::user();
        $permisos = $user['permisos'] ?? [];
        if (!is_array($permisos) || !in_array($clave, $permisos, true)) {
            throw new AppException('FORBIDDEN', 'Permisos insuficientes para esta operación.', 403);
        }
        return $user;
    }

    public static function guard(?string $permission): void
    {
        if ($permission === null) {
            return;
        }
        if ($permission === '') {
            self::user();
            return;
        }
        self::requirePermission($permission);
    }
}
