<?php
declare(strict_types=1);

namespace App\Http;

final class Response
{
    /** @param array<string,mixed> $payload */
    public static function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** 204 sin cuerpo (contrato: DELETE /categories/{id}). */
    public static function noContent(): void
    {
        http_response_code(204);
    }

    /** @param array<string,mixed> $details */
    public static function error(string $code, string $message, int $status, array $details = []): void
    {
        $error = ['code' => $code, 'message' => $message];
        if ($details !== []) {
            $error['details'] = $details;
        }
        self::json(['success' => false, 'error' => $error], $status);
    }
}
