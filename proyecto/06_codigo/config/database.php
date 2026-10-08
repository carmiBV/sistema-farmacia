<?php
declare(strict_types=1);

// Configuración de conexión PDO (Singleton en App\Core\Database).
// Las credenciales provienen de .env — NUNCA se escriben en este archivo (RNF-034).
// Estructura obligatoria: config/database.php (CORREGIR_ARQUITECTURA_API.md).

use App\Core\Env;

return [
    'driver' => Env::get('DB_ENGINE', 'mysql'),
    'host' => Env::get('DB_HOST', '127.0.0.1'),
    'port' => Env::get('DB_PORT', '3306'),
    'database' => Env::get('DB_NAME', ''),
    'username' => Env::get('DB_USER', ''),
    'password' => Env::get('DB_PASSWORD', ''),
    'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
];
