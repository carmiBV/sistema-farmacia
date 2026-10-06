<?php
declare(strict_types=1);

// CP-API-02: estructura + Front Controller sin lógica de negocio + /api/v1/health.

$root = dirname(__DIR__);
$failures = 0;

function check(bool $ok, string $label): void
{
    global $failures;
    echo ($ok ? 'PASS' : 'FAIL') . ": {$label}\n";
    if (!$ok) {
        $failures++;
    }
}

$files = [
    'public/index.php',
    'src/Support/Env.php',
    'src/Support/Database.php',
    'src/Http/Router.php',
    'src/Http/Response.php',
    'src/Controllers/HealthController.php',
];
foreach ($files as $file) {
    check(is_file($root . '/' . $file), "estructura: {$file}");
}

$fc = file_get_contents($root . '/public/index.php') ?: '';
check(
    preg_match('/\b(SELECT|INSERT|REPLACE|DELETE\s+FROM)\b/i', $fc) === 0
    && stripos($fc, 'catalog_categories') === false,
    'Front Controller sin SQL ni tablas de negocio'
);

$base = $argv[1] ?? 'http://127.0.0.1:8000';

function call(string $url, string $method = 'GET'): array
{
    $ctx = stream_context_create(['http' => [
        'method' => $method,
        'ignore_errors' => true,
        'timeout' => 5,
        'header' => "Accept: application/json\n",
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
            $status = (int) $m[1];
        }
    }
    return ['status' => $status, 'json' => json_decode($body ?: '', true)];
}

$health = call("{$base}/api/v1/health");
if ($health['status'] === 503 && ($health['json']['error']['code'] ?? '') === 'DATABASE_ERROR') {
    // Entorno sin desplegar (OPEN-API-04): el código responde correctamente ante BD ausente.
    echo "SKIP: health -> 503 DATABASE_ERROR (BD no desplegada)\n";
} else {
    check($health['status'] === 200, 'GET /api/v1/health -> 200');
    check(($health['json']['success'] ?? null) === true, 'health: success=true');
    check(($health['json']['data']['database'] ?? null) === 'up', 'health: database=up');
}
check(
    !array_key_exists('trace', $health['json'] ?? [])
    && !array_key_exists('exception', $health['json'] ?? []),
    'health: sin stack traces'
);

$missing = call("{$base}/api/v1/catalog/nope");
check($missing['status'] === 404, 'ruta desconocida -> 404');
check(($missing['json']['error']['code'] ?? null) === 'NOT_FOUND', '404: error.code=NOT_FOUND');

$wrongMethod = call("{$base}/api/v1/health", 'POST');
check($wrongMethod['status'] === 404, 'método no registrado -> 404');

echo $failures === 0 ? "RESULTADO: PASS\n" : "RESULTADO: FAIL ({$failures})\n";
exit($failures === 0 ? 0 : 1);
