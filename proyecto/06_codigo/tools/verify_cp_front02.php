<?php
declare(strict_types=1);

/**
 * CP-FRONT-02 - Layout principal y dashboard (/dashboard).
 *
 * Verifica estructura del layout (sidebar, header, usuario, selector de
 * sucursal, widgets) y que las rutas previas (login) y la API siguen intactas.
 * La logica de datos (sesion, widgets, estados) se prueba en
 * tests/dashboard_flow.test.mjs.
 *
 * Uso: php tools/verify_cp_front02.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_cp_front02.php <base-url>\n");
    exit(2);
}

$fail = 0;

function check(bool $ok, string $label): void
{
    global $fail;
    echo ($ok ? 'PASS' : 'FAIL') . ": {$label}\n";
    if (!$ok) {
        $fail++;
    }
}

/** @return array{status:int,body:string,ctype:string} */
function get(string $url, ?string $token = null): array
{
    $headers = $token !== null ? "Authorization: Bearer {$token}\r\n" : '';
    $ctx = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true, 'header' => $headers]]);
    $body = (string)file_get_contents($url, false, $ctx);
    $status = 0;
    $ctype = '';
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) {
            $status = (int)$m[1];
        }
        if (stripos($h, 'Content-Type:') === 0) {
            $ctype = trim(substr($h, 13));
        }
    }
    return ['status' => $status, 'body' => $body, 'ctype' => $ctype];
}

// --- layout ---
$r = get("{$base}/dashboard");
check($r['status'] === 200 && str_contains($r['ctype'], 'text/html'), 'GET /dashboard -> 200 HTML');
check(str_contains($r['body'], 'class="sidebar"') && str_contains($r['body'], 'aria-label="Módulos del sistema"'),
    'sidebar con navegacion etiquetada');
check(str_contains($r['body'], 'aria-current="page"'), 'pagina actual marcada en el nav');
check(str_contains($r['body'], 'id="header-user"') && str_contains($r['body'], 'id="store-select"')
    && str_contains($r['body'], 'for="store-select"'), 'header con usuario activo y selector de sucursal etiquetado');
check(str_contains($r['body'], 'id="w-ventas-dato"') && str_contains($r['body'], 'id="w-stock-dato"')
    && str_contains($r['body'], 'id="w-venc-dato"'), 'widgets de ventas, stock minimo y vencimientos');
check(str_contains($r['body'], '/assets/js/dashboard.js') && str_contains($r['body'], '/assets/css/app.css'),
    'dashboard referencia JS y CSS');
check(str_contains($r['body'], 'role="alert"'), 'caja de errores accesible (role=alert)');

$r = get("{$base}/assets/js/dashboard.js");
check($r['status'] === 200 && str_contains($r['body'], 'sf_token') && str_contains($r['body'], 'cargarAlertas')
    && str_contains($r['body'], 'store-select'), 'dashboard.js servido con sesion, widgets y sucursal');

// --- regresion: login y 404 ---
$r = get("{$base}/login");
check($r['status'] === 200 && str_contains($r['body'], 'id="login-form"'), 'GET /login sigue operativo');
$r = get("{$base}/ruta-que-no-existe");
check($r['status'] === 404, 'ruta web inexistente -> 404');

// --- API intacta (sin token -> 401) ---
$r = get("{$base}/api/v1/ops/stores");
check($r['status'] === 401 && str_contains($r['body'], 'UNAUTHENTICATED'), 'GET /ops/stores sin token -> 401 UNAUTHENTICATED');

echo 'RESULTADO: ' . ($fail === 0 ? 'PASS' : "FAIL ({$fail})") . "\n";
exit($fail === 0 ? 0 : 1);
