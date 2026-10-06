<?php
declare(strict_types=1);

namespace App\Http;

final class Request
{
    /** Cache del cuerpo parseado: php://input solo se consume una vez. */
    private static ?array $jsonBody = null;
    private static bool $leido = false;

    /**
     * @return array<string,mixed> cuerpo JSON como objeto
     * @throws AppException VALIDATION_ERROR (400) si el cuerpo falta o no es JSON valido
     */
    public static function jsonBody(): array
    {
        if (self::$leido) {
            return self::$jsonBody ?? throw new AppException('VALIDATION_ERROR', 'Se requiere un cuerpo JSON.', 400);
        }
        self::$leido = true;

        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            throw new AppException('VALIDATION_ERROR', 'Se requiere un cuerpo JSON.', 400);
        }
        if (ltrim($raw)[0] !== '{') {
            throw new AppException('VALIDATION_ERROR', 'El cuerpo debe ser un objeto JSON.', 400);
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new AppException('VALIDATION_ERROR', 'Cuerpo JSON invalido.', 400);
        }
        self::$jsonBody = $data;
        return $data;
    }
}
