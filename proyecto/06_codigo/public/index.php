<?php
declare(strict_types=1);

// Front Controller: SOLO bootstrap y despacho (MVC).
// Sin SQL/PDO, sin CRUD ni lógica de negocio, sin validaciones ni autenticación
// (esas responsabilidades viven en app/ — CORREGIR_ARQUITECTURA_API.md).
// Matriz: bootstrap + autoloader (PSR-4 App\ -> app/) + cargar config/rutas + ejecutar Router.

$root = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = $root . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

\App\Core\Env::load($root . '/.env');

// config/app.php y config/database.php se cargan bajo demanda por las capas.
$router = new \App\Core\Router();
require $root . '/routes/api.php';
require $root . '/routes/web.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!str_starts_with($path, '/api/') && $path !== '/' && !str_contains($path, '..') && is_file(__DIR__ . $path)) {
    return false; // asset estático: lo sirve php -S (router) o el docroot
}

try {
    $router->dispatch($method, $path);
} catch (\App\Core\AppException $e) {
    if (!str_starts_with($path, '/api/')) {
        \App\Controllers\Web\PageController::noEncontrado();
        exit;
    }
    \App\Core\Response::error($e->errorCode, $e->getMessage(), $e->status);
} catch (\Throwable $e) {
    // Sin stack traces ni datos sensibles en la respuesta.
    \App\Core\Response::error('INTERNAL_ERROR', 'Error interno.', 500);
}
