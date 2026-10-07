<?php
declare(strict_types=1);

/**
 * CP-FRONT-06 - Compras y Recepcion de Mercancia (/compras-ordenes,
 * /compras-recepciones).
 *
 * Verifica estructura de ambas pantallas (editor de items, registro de lotes,
 * verificacion contra orden), navegacion y regresion. La logica de datos se
 * prueba en tests/compras_flow.test.mjs.
 *
 * Uso: php tools/verify_cp_front06.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_cp_front06.php <base-url>\n");
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

// --- /compras-ordenes ---
$r = get("{$base}/compras-ordenes");
check($r['status'] === 200, 'GET /compras-ordenes -> 200');
check(str_contains($r['body'], 'id="ord-form"') && str_contains($r['body'], 'id="ord-supplier"')
    && str_contains($r['body'], 'id="ord-numero"'), 'formulario de orden con proveedor y numero opcional');
check(str_contains($r['body'], 'id="ord-item-producto"') && str_contains($r['body'], 'id="ord-item-cantidad"')
    && str_contains($r['body'], 'id="ord-items-lista"'), 'editor de items de la orden');
check(str_contains($r['body'], 'id="ord-estado"') && str_contains($r['body'], 'id="ord-tabla"'),
    'filtros por estado y tabla de ordenes');
check(str_contains($r['body'], 'id="ord-detalle-tabla"') && str_contains($r['body'], '/assets/js/ordenes.js'),
    'detalle de orden y JS servido');

// --- /compras-recepciones ---
$r = get("{$base}/compras-recepciones");
check($r['status'] === 200, 'GET /compras-recepciones -> 200');
check(str_contains($r['body'], 'id="rec-orden"') && str_contains($r['body'], 'id="rec-idempotency"'),
    'selector de orden emitida y clave de idempotencia');
check(str_contains($r['body'], 'id="rec-orden-tabla"'), 'verificacion contra la orden (pedidas/recibidas/pendientes)');
check(str_contains($r['body'], 'id="rec-item-lote"') && str_contains($r['body'], 'id="rec-item-vencimiento"')
    && str_contains($r['body'], 'id="rec-item-cantidad"'), 'registro de lote de fabrica y fecha de vencimiento');
check(str_contains($r['body'], 'id="rec-confirmar-store"') && str_contains($r['body'], 'id="rec-confirmar"')
    && str_contains($r['body'], 'id="rec-rechazar"'), 'confirmacion con sucursal y rechazo');
check(str_contains($r['body'], '/assets/js/recepciones.js'), 'JS de recepciones servido');

// --- navegacion del layout ---
check(str_contains($r['body'], 'href="/compras-ordenes"') && str_contains($r['body'], 'href="/compras-recepciones"'),
    'nav enlaza las pantallas de compras');
check(str_contains($r['body'], 'class="sidebar"') && str_contains($r['body'], 'aria-current="page"')
    && str_contains($r['body'], 'id="header-user"'), 'layout compartido intacto');

// --- regresion ---
$r = get("{$base}/dashboard");
check($r['status'] === 200 && str_contains($r['body'], 'id="w-ventas-dato"'), 'regresion /dashboard');
$r = get("{$base}/gestion-proveedores");
check($r['status'] === 200 && str_contains($r['body'], 'id="prov-form"'), 'regresion /gestion-proveedores');
$r = get("{$base}/login");
check($r['status'] === 200 && str_contains($r['body'], 'id="login-form"'), 'regresion /login');
$r = get("{$base}/ruta-que-no-existe");
check($r['status'] === 404, 'ruta web inexistente -> 404');
$r = get("{$base}/api/v1/purchases/orders");
check($r['status'] === 401 && str_contains($r['body'], 'UNAUTHENTICATED'), 'API intacta: /purchases/orders sin token -> 401');

echo 'RESULTADO: ' . ($fail === 0 ? 'PASS' : "FAIL ({$fail})") . "\n";
exit($fail === 0 ? 0 : 1);
