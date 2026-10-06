<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Rate limiting mínimo de login sobre archivo temporal.
 * ponytail: contador en sys_get_temp_dir(); si hace falta escalar a múltiples
 * servidores, migrar a tabla MySQL o Redis.
 */
final class Throttle
{
    /** @return bool true si la clave superó $max intentos en la ventana */
    public static function excedido(string $clave, int $max, int $ventanaSeg): bool
    {
        return count(self::lectura($clave, $ventanaSeg)) >= $max;
    }

    public static function registrarFallo(string $clave, int $ventanaSeg): void
    {
        $intentos = self::lectura($clave, $ventanaSeg);
        $intentos[] = time();
        file_put_contents(self::archivo($clave), json_encode($intentos), LOCK_EX);
    }

    public static function limpiar(string $clave): void
    {
        @unlink(self::archivo($clave));
    }

    /** @return list<int> timestamps dentro de la ventana */
    private static function lectura(string $clave, int $ventanaSeg): array
    {
        $archivo = self::archivo($clave);
        if (!is_file($archivo)) {
            return [];
        }
        $datos = json_decode((string)file_get_contents($archivo), true);
        if (!is_array($datos)) {
            return [];
        }
        $desde = time() - $ventanaSeg;

        return array_values(array_filter($datos, static fn($t) => is_int($t) && $t > $desde));
    }

    private static function archivo(string $clave): string
    {
        return sys_get_temp_dir() . '/ph_login_' . hash('sha256', $clave) . '.json';
    }
}
