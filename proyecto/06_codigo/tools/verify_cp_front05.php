<?php
declare(strict_types=1);

/**
 * CP-FRONT-05 - Proveedores, Pacientes y Prescriptores (/gestion-proveedores,
 * /gestion-pacientes-prescriptores).
 *
 * Verifica estructura de ambas pantallas, navegacion y regresion. La logica de
 * datos se prueba en tests/directorio_flow.test.mjs.
 *
 * Uso: php tools/verify_cp_front05.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_cp_front05.php <base-url>\n");
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

// --- /gestion-proveedores ---
$r = get("{$base}/gestion-proveedores");
check($r['status'] === 200, 'GET /gestion-proveedores -> 200');
check(str_contains($r['body'], 'id="prov-form"') && str_contains($r['body'], 'id="prov-identificacion"')
    && str_contains($r['body'], 'id="prov-nombre"') && str_contains($r['body'], 'id="prov-contacto"'),
    'formulario de proveedor con identificacion, nombre y contacto');
check(str_contains($r['body'], 'id="prov-buscar"') && str_contains($r['body'], 'id="prov-tabla"'),
    'busqueda y tabla de proveedores');
check(str_contains($r['body'], '/assets/js/proveedores.js'), 'JS de proveedores servido');

// --- /gestion-pacientes-prescriptores ---
$r = get("{$base}/gestion-pacientes-prescriptores");
check($r['status'] === 200, 'GET /gestion-pacientes-prescriptores -> 200');
check(str_contains($r['body'], 'id="pac-form"') && str_contains($r['body'], 'id="pac-fecha"')
    && str_contains($r['body'], 'id="pac-tabla"'), 'panel de pacientes con fecha de nacimiento');
check(str_contains($r['body'], 'id="presc-form"') && str_contains($r['body'], 'id="presc-especialidad"')
    && str_contains($r['body'], 'id="presc-tabla"'), 'panel de prescriptores con especialidad');
check(str_contains($r['body'], '/assets/js/pacientes.js'), 'JS del directorio servido');

// --- navegacion del layout ---
check(str_contains($r['body'], 'href="/gestion-proveedores"')
    && str_contains($r['body'], 'href="/gestion-pacientes-prescriptores"'), 'nav enlaza las pantallas de terceros');
check(str_contains($r['body'], 'class="sidebar"') && str_contains($r['body'], 'aria-current="page"')
    && str_contains($r['body'], 'id="header-user"'), 'layout compartido intacto');

// --- regresion ---
$r = get("{$base}/dashboard");
check($r['status'] === 200 && str_contains($r['body'], 'id="w-ventas-dato"'), 'regresion /dashboard');
$r = get("{$base}/catalogo-productos");
check($r['status'] === 200 && str_contains($r['body'], 'id="prod-form"'), 'regresion /catalogo-productos');
$r = get("{$base}/login");
check($r['status'] === 200 && str_contains($r['body'], 'id="login-form"'), 'regresion /login');
$r = get("{$base}/ruta-que-no-existe");
check($r['status'] === 404, 'ruta web inexistente -> 404');
$r = get("{$base}/api/v1/catalog/patients");
check($r['status'] === 401 && str_contains($r['body'], 'UNAUTHENTICATED'), 'API intacta: /catalog/patients sin token -> 401');

echo 'RESULTADO: ' . ($fail === 0 ? 'PASS' : "FAIL ({$fail})") . "\n";
exit($fail === 0 ? 0 : 1);
