<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Conexión PDO (Singleton). La configuración vive en config/database.php y las
 * credenciales en .env (matriz de responsabilidades: Database.php = configuración
 * y retorno del objeto de conexión PDO).
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $cfg = require dirname(__DIR__, 2) . '/config/database.php';
            $dsn = sprintf(
                '%s:host=%s;port=%s;dbname=%s;charset=%s',
                $cfg['driver'],
                $cfg['host'],
                $cfg['port'],
                $cfg['database'],
                $cfg['charset']
            );
            self::$pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return self::$pdo;
    }
}
