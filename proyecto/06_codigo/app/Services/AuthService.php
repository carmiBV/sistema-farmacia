<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\AppException;
use App\Repositories\TokenBlacklistRepository;
use App\Repositories\UsuarioRepository;
use App\Core\Env;
use App\Core\Jwt;
use App\Core\Throttle;
use App\Validators\AuthValidator;
use PDOException;

final class AuthService
{
    private const MAX_INTENTOS = 5;
    private const VENTANA_SEG = 300;

    public function __construct(
        private readonly AuthValidator $validator = new AuthValidator(),
        private readonly UsuarioRepository $usuarios = new UsuarioRepository(),
        private readonly TokenBlacklistRepository $blacklist = new TokenBlacklistRepository(),
    ) {
    }

    /**
     * Login: throttle anti-fuerza bruta, verificación de contraseña y emisión JWT.
     *
     * @param array<string,mixed> $input
     * @return array{token:string,token_type:string,expires_in:int,user:array<string,mixed>}
     * @throws AppException VALIDATION_ERROR (400), RATE_LIMITED (429),
     *                      INVALID_CREDENTIALS (401), ACCOUNT_DISABLED (403)
     */
    public function login(array $input): array
    {
        $datos = $this->validator->validarLogin($input);
        $throttleKey = 'login:' . $datos['usuario'];

        if (Throttle::excedido($throttleKey, self::MAX_INTENTOS, self::VENTANA_SEG)) {
            throw new AppException('RATE_LIMITED', 'Demasiados intentos de acceso. Intente más tarde.', 429);
        }

        try {
            $usuario = $this->usuarios->findByUsuario($datos['usuario']);
            $passwordOk = $usuario !== null && password_verify($datos['password'], $usuario->passwordHash);

            if (!$passwordOk) {
                Throttle::registrarFallo($throttleKey, self::VENTANA_SEG);
                throw new AppException('INVALID_CREDENTIALS', 'Usuario o contraseña incorrectos.', 401);
            }
            if (!$usuario->estaActivo()) {
                throw new AppException('ACCOUNT_DISABLED', 'La cuenta está bloqueada o inactiva.', 403);
            }

            Throttle::limpiar($throttleKey);
            $this->usuarios->actualizarUltimoAcceso($usuario->id);

            $ttl = (int)Env::get('JWT_TTL_SECONDS', '3600');
            if ($ttl <= 0) {
                $ttl = 3600;
            }
            $token = Jwt::issue(['sub' => $usuario->id, 'usuario' => $usuario->usuario], $ttl);

            return [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => $ttl,
                'user' => $this->payloadUsuario($usuario->id, $usuario->usuario),
            ];
        } catch (PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error de base de datos.', 500);
        }
    }

    /**
     * Revoca el token vigente: lo registra en token_blacklist con su hash
     * sha-256 (nunca el token en claro, §15 decisiones_datos).
     *
     * @throws AppException UNAUTHENTICATED (401) si el token es inválido/expirado
     */
    public function logout(string $token): void
    {
        $claims = Jwt::verify($token); // firma + exp
        $hash = Jwt::hash($token);

        try {
            if ($this->blacklist->estaRevocado($hash)) {
                throw new AppException('UNAUTHENTICATED', 'Token ya revocado.', 401);
            }
            $expiresAt = gmdate('Y-m-d H:i:s', (int)$claims['exp']);
            $this->blacklist->revocar($hash, $expiresAt);
        } catch (PDOException $e) {
            if ($e->getCode() === '1062') {
                throw new AppException('UNAUTHENTICATED', 'Token ya revocado.', 401);
            }
            throw new AppException('DATABASE_ERROR', 'Error de base de datos.', 500);
        }
    }

    /**
     * Valida un token presentado: firma, expiración, blacklist, usuario activo.
     *
     * @return array{id:int,usuario:string,roles:list<string>,permisos:list<string>}
     * @throws AppException UNAUTHENTICATED (401), ACCOUNT_DISABLED (403)
     */
    public function usuarioAutenticado(string $token): array
    {
        $claims = Jwt::verify($token);
        $hash = Jwt::hash($token);

        try {
            if ($this->blacklist->estaRevocado($hash)) {
                throw new AppException('UNAUTHENTICATED', 'Token revocado.', 401);
            }
        } catch (PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error de base de datos.', 500);
        }

        $usuario = $this->usuarios->findById((int)$claims['sub']);
        if ($usuario === null) {
            throw new AppException('UNAUTHENTICATED', 'Token inválido.', 401);
        }
        if (!$usuario->estaActivo()) {
            throw new AppException('ACCOUNT_DISABLED', 'La cuenta está bloqueada o inactiva.', 403);
        }

        return $this->payloadUsuario($usuario->id, $usuario->usuario);
    }

    /**@return array{id:int,usuario:string,roles:list<string>,permisos:list<string>} */
    private function payloadUsuario(int $id, string $usuario): array
    {
        return [
            'id' => $id,
            'usuario' => $usuario,
            'roles' => $this->usuarios->rolesDe($id),
            'permisos' => $this->usuarios->permisosDe($id),
        ];
    }
}
