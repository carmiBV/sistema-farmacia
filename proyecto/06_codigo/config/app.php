<?php
declare(strict_types=1);

// Configuración global de la aplicación (sin secretos: credenciales en .env, RNF-034).
// Estructura obligatoria: config/app.php (CORREGIR_ARQUITECTURA_API.md).

use App\Core\Env;

return [
    'name' => Env::get('APP_NAME', 'SistemaFarmacia'),
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => Env::get('APP_DEBUG', 'false') === 'true',
    'url' => Env::get('APP_URL', ''),
];
