<?php
declare(strict_types=1);

/**
 * CP-FRONT-08 - Recetas Medicas y Medicamentos Controlados (/recetas-medicas,
 * /libro-controlados).
 *
 * Verifica estructura de ambas pantallas, navegacion y regresion. La logica
 * (registro/dispensacion de recetas, libro y ajustes) se prueba en
 * tests/recetas_flow.test.mjs.
 *
 * Uso: php tools/verify_cp_front08.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_cp_front08.php <base-url>\n");
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

// --- /recetas-medicas ---
$r = get("{$base}/recetas-medicas");
check($r['status'] === 200, 'GET /recetas-medicas -> 200');
check(str_contains($r['body'], 'id="rx-prescriptor"') && str_contains($r['body'], 'id="rx-paciente"')
    && str_contains($r['body'], 'id="rx-fecha"') && str_contains($r['body'], 'id="rx-referencia"'),
    'registro de receta vinculado a prescriptor/paciente');
check(str_contains($r['body'], 'id="rx-item-producto"') && str_contains($r['body'], 'id="rx-item-cantidad"')
    && str_contains($r['body'], 'id="rx-items-lista"'), 'editor de items recetados');
check(str_contains($r['body'], 'id="rx-detalle-tabla"') && str_contains($r['body'], 'id="rx-dispensar"'),
    'detalle con dispensacion por item');
check(str_contains($r['body'], '/assets/js/recetas.js'), 'JS de recetas servido');

// --- /libro-controlados ---
$r = get("{$base}/libro-controlados");
check($r['status'] === 200, 'GET /libro-controlados -> 200');
check(str_contains($r['body'], 'id="led-tipo"') && str_contains($r['body'], 'id="led-desde"')
    && str_contains($r['body'], 'id="led-tabla"'), 'asientos del libro con filtros por periodo (RF-071)');
check(str_contains($r['body'], 'id="sal-tabla"') && str_contains($r['body'], 'id="sal-conciliar"'),
    'saldos con conciliacion (RNF-024)');
check(str_contains($r['body'], 'id="aj-form"') && str_contains($r['body'], 'id="aj-autorizador"')
    && str_contains($r['body'], 'id="aj-motivo"'), 'ajuste con doble autorizacion (RF-046)');
check(str_contains($r['body'], '/assets/js/controlados.js'), 'JS del libro servido');

// --- navegacion del layout ---
check(str_contains($r['body'], 'href="/recetas-medicas"') && str_contains($r['body'], 'href="/libro-controlados"'),
    'nav enlaza las pantallas de salud');
check(str_contains($r['body'], 'class="sidebar"') && str_contains($r['body'], 'aria-current="page"')
    && str_contains($r['body'], 'id="header-user"'), 'layout compartido intacto');

// --- regresion ---
$r = get("{$base}/dashboard");
check($r['status'] === 200 && str_contains($r['body'], 'id="w-ventas-dato"'), 'regresion /dashboard');
$r = get("{$base}/inventario-lotes-fefo");
check($r['status'] === 200 && str_contains($r['body'], 'id="lot-tabla"'), 'regresion /inventario-lotes-fefo');
$r = get("{$base}/login");
check($r['status'] === 200 && str_contains($r['body'], 'id="login-form"'), 'regresion /login');
$r = get("{$base}/ruta-que-no-existe");
check($r['status'] === 404, 'ruta web inexistente -> 404');
$r = get("{$base}/api/v1/control/ledger");
check($r['status'] === 401 && str_contains($r['body'], 'UNAUTHENTICATED'), 'API intacta: /control/ledger sin token -> 401');

echo 'RESULTADO: ' . ($fail === 0 ? 'PASS' : "FAIL ({$fail})") . "\n";
exit($fail === 0 ? 0 : 1);
