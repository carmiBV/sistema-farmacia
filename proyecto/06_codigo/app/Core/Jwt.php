<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\AppException;

/**
 * JWT HS256 mínimo sobre stdlib (hash_hmac). Sin dependencias externas.
 * La revocación real la resuelve token_blacklist (hash sha-256 del token).
 */
final class Jwt
{
    /**
     * @param array<string,mixed> $claims
     */
    public static function issue(array $claims, int $ttlSeconds): string
    {
        $now = time();
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = array_merge($claims, [
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
        ]);

        $segments = self::b64(json_encode($header, JSON_UNESCAPED_SLASHES))
            . '.' . self::b64(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signature = hash_hmac('sha256', $segments, self::secret(), true);

        return $segments . '.' . self::b64($signature);
    }

    /**
     * Firma y expiración verificadas con hash_equals (comparación constante).
     *
     * @return array<string,mixed> claims decodificados
     * @throws AppException UNAUTHENTICATED (401)
     */
    public static function verify(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3 || in_array('', $parts, true)) {
            throw new AppException('UNAUTHENTICATED', 'Token inválido.', 401);
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;
        $expected = self::b64(hash_hmac('sha256', $headerB64 . '.' . $payloadB64, self::secret(), true));
        if (!hash_equals($expected, $signatureB64)) {
            throw new AppException('UNAUTHENTICATED', 'Token inválido.', 401);
        }

        $claims = json_decode(self::unb64($payloadB64), true);
        if (!is_array($claims) || !isset($claims['exp'], $claims['sub'])) {
            throw new AppException('UNAUTHENTICATED', 'Token inválido.', 401);
        }
        if ((int)$claims['exp'] < time()) {
            throw new AppException('UNAUTHENTICATED', 'Token expirado.', 401);
        }

        return $claims;
    }

    /** Hash sha-256 hex (64 chars) para token_blacklist. */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    private static function secret(): string
    {
        $secret = Env::get('JWT_SECRET', '');
        if ($secret === '' || strlen($secret) < 16) {
            throw new AppException('CONFIG_ERROR', 'Falta configurar JWT_SECRET.', 500);
        }

        return $secret;
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function unb64(string $b64): string
    {
        return (string)base64_decode(strtr($b64, '-_', '+/'), true);
    }
}
