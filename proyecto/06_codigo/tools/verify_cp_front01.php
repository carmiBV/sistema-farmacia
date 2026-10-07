<?php
declare(strict_types=1);

/**
 * CP-FRONT-01 - Pantalla de login (Views PHP + vanilla JS).
 *
 * Verifica servida de paginas y assets, estructura del formulario, manejo de
 * errores (IDs usados por login.js) y que la API de autenticacion sigue intacta.
 * El flujo interactivo (login -> redireccion por rol) se prueba en navegador.
 *
 * Uso: php tools/verify_cp_front01.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_cp_front01.php <base-url>\n");
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
function get(string $url): array
{
    $ctx = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true, 'method' => 'GET']]);
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

/** @return array{status:int,json:?array} */
function postJson(string $url, ?string $json): array
{
    $ctx = stream_context_create(['http' => [
        'timeout' => 15, 'ignore_errors' => true, 'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => (string)$json,
    ]]);
    $body = (string)file_get_contents($url, false, $ctx);
    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) {
            $status = (int)$m[1];
        }
    }
    return ['status' => $status, 'json' => json_decode($body, true)];
}

// --- paginas ---
$r = get("{$base}/login");
check($r['status'] === 200 && str_contains($r['ctype'], 'text/html'), 'GET /login -> 200 HTML');
check(
    str_contains($r['body'], 'id="login-form"') && str_contains($r['body'], 'id="usuario"')
        && str_contains($r['body'], 'id="password"') && str_contains($r['body'], 'id="login-error"')
        && str_contains($r['body'], 'for="usuario"') && str_contains($r['body'], 'for="password"'),
    'formulario con labels, campos y caja de error'
);
check(str_contains($r['body'], '/assets/js/login.js') && str_contains($r['body'], '/assets/css/app.css'),
    'login referencia JS y CSS');

$r = get("{$base}/");
check($r['status'] === 200 && str_contains($r['body'], 'id="login-form"'), 'GET / -> 200 (misma pantalla de login)');

$r = get("{$base}/dashboard");
check($r['status'] === 200 && str_contains($r['body'], 'Dashboard'), 'GET /dashboard -> 200 (destino de redireccion)');

$r = get("{$base}/ruta-que-no-existe");
check($r['status'] === 404, 'ruta web inexistente -> 404');

// --- assets ---
$r = get("{$base}/assets/js/login.js");
check($r['status'] === 200 && str_contains($r['body'], 'sf_token') && str_contains($r['body'], 'destinoPorRol'),
    'login.js servido con sesion y redireccion por rol');
$r = get("{$base}/assets/css/app.css");
check($r['status'] === 200 && str_contains($r['body'], 'login-card'), 'app.css servido');

// --- la API de auth sigue intacta ---
$r = postJson("{$base}/api/v1/auth/login", 'no-soy-json');
check($r['status'] === 400 && ($r['json']['error']['code'] ?? '') === 'VALIDATION_ERROR',
    'POST /auth/login cuerpo malformado -> 400 VALIDATION_ERROR');
$r = postJson("{$base}/api/v1/auth/login", json_encode(['usuario' => 'no_existe_' . random_int(1, PHP_INT_MAX), 'password' => 'mala']));
check($r['status'] === 401 && ($r['json']['error']['code'] ?? '') === 'INVALID_CREDENTIALS',
    'POST /auth/login credenciales malas -> 401 INVALID_CREDENTIALS');

echo 'RESULTADO: ' . ($fail === 0 ? 'PASS' : "FAIL ({$fail})") . "\n";
exit($fail === 0 ? 0 : 1);
