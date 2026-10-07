<?php
declare(strict_types=1);

/**
 * CP-FRONT-03 - Usuarios/roles RBAC y configuracion de sucursales, cajas y
 * parametros (/autenticacion-usuarios, /configuracion-sucursales).
 *
 * Verifica estructura de ambas pantallas, navegacion del layout compartido y
 * regresion de login/dashboard/API. La logica de datos se prueba en
 * tests/admin_flow.test.mjs.
 *
 * Uso: php tools/verify_cp_front03.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_cp_front03.php <base-url>\n");
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

// --- /autenticacion-usuarios ---
$r = get("{$base}/autenticacion-usuarios");
check($r['status'] === 200, 'GET /autenticacion-usuarios -> 200');
check(str_contains($r['body'], 'id="u-form"') && str_contains($r['body'], 'id="u-usuario"')
    && str_contains($r['body'], 'id="u-password"') && str_contains($r['body'], 'id="u-roles"'),
    'formulario de creacion de usuario con roles');
check(str_contains($r['body'], 'id="u-asignar"') && str_contains($r['body'], 'id="u-asignar-roles"'),
    'formulario de asignacion de roles');
check(str_contains($r['body'], 'id="r-form"') && str_contains($r['body'], 'id="r-permisos"')
    && str_contains($r['body'], 'id="r-tabla"'), 'gestion de roles con permisos');
check(str_contains($r['body'], 'id="u-tabla"') && str_contains($r['body'], '/assets/js/usuarios.js'),
    'tabla de usuarios y JS de la pantalla');

// --- /configuracion-sucursales ---
$r = get("{$base}/configuracion-sucursales");
check($r['status'] === 200, 'GET /configuracion-sucursales -> 200');
check(str_contains($r['body'], 'id="s-form"') && str_contains($r['body'], 'id="s-codigo"')
    && str_contains($r['body'], 'id="s-tabla"'), 'gestion de sucursales');
check(str_contains($r['body'], 'id="c-form"') && str_contains($r['body'], 'id="c-store"')
    && str_contains($r['body'], 'id="c-tabla"'), 'gestion de cajas registradoras');
check(str_contains($r['body'], 'id="p-form"') && str_contains($r['body'], 'id="p-type"')
    && str_contains($r['body'], 'id="p-tabla"'), 'gestion de parametros (value_type)');
check(str_contains($r['body'], '/assets/js/configuracion.js'), 'JS de la pantalla servido');

// --- layout compartido y navegacion ---
check(str_contains($r['body'], 'class="sidebar"') && str_contains($r['body'], 'aria-current="page"'),
    'layout compartido con sidebar y pagina actual marcada');
check(str_contains($r['body'], 'href="/autenticacion-usuarios"') && str_contains($r['body'], 'href="/configuracion-sucursales"')
    && str_contains($r['body'], 'href="/dashboard"'), 'navegacion enlaza las pantallas implementadas');
check(str_contains($r['body'], 'id="header-user"') && str_contains($r['body'], 'role="alert"'),
    'header con usuario activo y caja de errores accesible');

// --- regresion ---
$r = get("{$base}/dashboard");
check($r['status'] === 200 && str_contains($r['body'], 'id="w-ventas-dato"')
    && str_contains($r['body'], 'id="store-select"') && str_contains($r['body'], '/assets/js/dashboard.js'),
    'regresion /dashboard (widgets + selector de sucursal + JS)');
$r = get("{$base}/login");
check($r['status'] === 200 && str_contains($r['body'], 'id="login-form"'), 'regresion /login');
$r = get("{$base}/ruta-que-no-existe");
check($r['status'] === 404, 'ruta web inexistente -> 404');
$r = get("{$base}/api/v1/auth/users");
check($r['status'] === 401 && str_contains($r['body'], 'UNAUTHENTICATED'), 'API intacta: /auth/users sin token -> 401');

echo 'RESULTADO: ' . ($fail === 0 ? 'PASS' : "FAIL ({$fail})") . "\n";
exit($fail === 0 ? 0 : 1);
