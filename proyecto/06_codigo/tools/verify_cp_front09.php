<?php
declare(strict_types=1);

/**
 * CP-FRONT-09 - Punto de Venta, Pagos y Devoluciones (/ventas-pos,
 * /ventas-pagos-transacciones, /ventas-devoluciones).
 *
 * Verifica estructura de las 3 pantallas (carrito FEFO, cobro con multiples
 * medios, comprobante, devoluciones por item con condicion), navegacion y
 * regresion. La logica se prueba en tests/ventas_flow.test.mjs.
 *
 * Uso: php tools/verify_cp_front09.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_cp_front09.php <base-url>\n");
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

// --- /ventas-pos ---
$r = get("{$base}/ventas-pos");
check($r['status'] === 200, 'GET /ventas-pos -> 200');
check(str_contains($r['body'], 'id="pos-codigo"'), 'busqueda por codigo de barras/SKU');
check(str_contains($r['body'], 'id="pos-store"') && str_contains($r['body'], 'id="pos-register"')
    && str_contains($r['body'], 'id="pos-paciente"'), 'contexto de caja (sucursal, caja, paciente)');
check(str_contains($r['body'], 'id="pos-tabla"') && str_contains($r['body'], 'id="pos-total"'),
    'carrito con calculo de importes');
check(str_contains($r['body'], 'id="pago-medio"') && str_contains($r['body'], 'id="pago-agregar"')
    && str_contains($r['body'], 'id="pos-cobrar"'), 'cobro con multiples medios de pago');
check(str_contains($r['body'], 'id="comprobante"') && str_contains($r['body'], 'id="comprobante-imprimir"'),
    'emision de comprobante con impresion');
check(str_contains($r['body'], '/assets/js/pos.js'), 'JS del POS servido');

// --- /ventas-pagos-transacciones ---
$r = get("{$base}/ventas-pagos-transacciones");
check($r['status'] === 200, 'GET /ventas-pagos-transacciones -> 200');
check(str_contains($r['body'], 'id="v-estado"') && str_contains($r['body'], 'id="v-tabla"'),
    'listado de ventas con filtros');
check(str_contains($r['body'], 'id="v-pagos-tabla"') && str_contains($r['body'], 'id="v-pago-form"')
    && str_contains($r['body'], 'id="v-anular"'), 'pagos acumulativos y anulacion');
check(str_contains($r['body'], '/assets/js/pagos.js'), 'JS de pagos servido');

// --- /ventas-devoluciones ---
$r = get("{$base}/ventas-devoluciones");
check($r['status'] === 200, 'GET /ventas-devoluciones -> 200');
check(str_contains($r['body'], 'id="dv-order-id"') && str_contains($r['body'], 'id="dv-items-tabla"'),
    'carga de venta y sus items');
check(str_contains($r['body'], 'id="dv-motivo"') && str_contains($r['body'], 'id="dv-tabla"'),
    'registro de devolucion con motivo');
check(str_contains($r['body'], '/assets/js/devoluciones.js'), 'JS de devoluciones servido');

// --- navegacion del layout ---
check(str_contains($r['body'], 'href="/ventas-pos"') && str_contains($r['body'], 'href="/ventas-devoluciones"'),
    'nav enlaza las pantallas de ventas');
check(str_contains($r['body'], 'class="sidebar"') && str_contains($r['body'], 'aria-current="page"')
    && str_contains($r['body'], 'id="header-user"'), 'layout compartido intacto');

// --- regresion ---
$r = get("{$base}/dashboard");
check($r['status'] === 200 && str_contains($r['body'], 'id="w-ventas-dato"'), 'regresion /dashboard');
$r = get("{$base}/recetas-medicas");
check($r['status'] === 200 && str_contains($r['body'], 'id="rx-form"'), 'regresion /recetas-medicas');
$r = get("{$base}/login");
check($r['status'] === 200 && str_contains($r['body'], 'id="login-form"'), 'regresion /login');
$r = get("{$base}/ruta-que-no-existe");
check($r['status'] === 404, 'ruta web inexistente -> 404');
$r = get("{$base}/api/v1/sales/orders");
check($r['status'] === 401 && str_contains($r['body'], 'UNAUTHENTICATED'), 'API intacta: /sales/orders sin token -> 401');

echo 'RESULTADO: ' . ($fail === 0 ? 'PASS' : "FAIL ({$fail})") . "\n";
exit($fail === 0 ? 0 : 1);
