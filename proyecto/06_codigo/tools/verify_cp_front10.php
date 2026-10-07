<?php
declare(strict_types=1);

/**
 * CP-FRONT-10 - Auditoria y Seguridad PII (/auditoria-operaciones-pii).
 *
 * Verifica estructura de la pantalla (operaciones, accesos PII, outbox con
 * ciclo procesar/fallar/reintentar), navegacion y regresion. La logica se
 * prueba en tests/auditoria_flow.test.mjs.
 *
 * Uso: php tools/verify_cp_front10.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_cp_front10.php <base-url>\n");
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

/** @return array{status:int,body:string} */
function get(string $url): array
{
    $ctx = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true]]);
    $body = (string)file_get_contents($url, false, $ctx);
    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) {
            $status = (int)$m[1];
        }
    }
    return ['status' => $status, 'body' => $body];
}

// --- /auditoria-operaciones-pii ---
$r = get("{$base}/auditoria-operaciones-pii");
check($r['status'] === 200, 'GET /auditoria-operaciones-pii -> 200');
check(str_contains($r['body'], 'id="aud-accion"') && str_contains($r['body'], 'id="aud-entidad"')
    && str_contains($r['body'], 'id="aud-desde"') && str_contains($r['body'], 'id="aud-tabla"'),
    'operaciones sensibles con filtros de accion/entidad/periodo (RF-090)');
check(str_contains($r['body'], 'id="aud-detalle-json"'), 'detalle de operacion con valores redactados');
check(str_contains($r['body'], 'id="pii-accion"') && str_contains($r['body'], 'id="pii-tabla"'),
    'accesos PII con filtro de accion (RF-091)');
check(str_contains($r['body'], 'id="evt-estado"') && str_contains($r['body'], 'id="evt-tabla"'),
    'eventos outbox con filtro de estado');
check(str_contains($r['body'], 'id="evt-detalle-json"') && str_contains($r['body'], '/assets/js/auditoria.js'),
    'payload del evento y JS servido');
check(str_contains($r['body'], 'href="/auditoria-operaciones-pii"')
    && str_contains($r['body'], 'aria-current="page"'), 'nav enlaza la pantalla de auditoria');

// --- regresion ---
$r = get("{$base}/dashboard");
check($r['status'] === 200 && str_contains($r['body'], 'id="w-ventas-dato"'), 'regresion /dashboard');
$r = get("{$base}/ventas-pos");
check($r['status'] === 200 && str_contains($r['body'], 'id="pos-codigo"'), 'regresion /ventas-pos');
$r = get("{$base}/login");
check($r['status'] === 200 && str_contains($r['body'], 'id="login-form"'), 'regresion /login');
$r = get("{$base}/ruta-que-no-existe");
check($r['status'] === 404, 'ruta web inexistente -> 404');
$r = get("{$base}/api/v1/audit/operations");
check($r['status'] === 401 && str_contains($r['body'], 'UNAUTHENTICATED'), 'API intacta: /audit/operations sin token -> 401');

echo 'RESULTADO: ' . ($fail === 0 ? 'PASS' : "FAIL ({$fail})") . "\n";
exit($fail === 0 ? 0 : 1);
