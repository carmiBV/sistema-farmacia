<?php
declare(strict_types=1);

namespace App\Core;

final class Env
{
    /** @var array<string,string> */
    private static array $values = [];

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return; // ponytail: sin .env se leen variables de entorno reales
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            if ($key !== '' && getenv($key) === false) {
                self::$values[$key] = trim($value, " \t\"'");
            }
        }
    }

    public static function get(string $key, ?string $default = null): string
    {
        if (array_key_exists($key, self::$values)) {
            return self::$values[$key]; // presente pero vacío es válido (p. ej. contraseña local)
        }
        $value = getenv($key);
        if ($value === false || $value === null || $value === '') {
            if ($default !== null) {
                return $default;
            }
            throw new \RuntimeException("Falta variable requerida: {$key}");
        }
        return (string) $value;
    }
}
