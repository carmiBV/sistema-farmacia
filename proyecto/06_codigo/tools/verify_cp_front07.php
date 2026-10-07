<?php
declare(strict_types=1);

/**
 * CP-FRONT-07 - Inventarios y Trazabilidad FEFO (/inventario-lotes-fefo,
 * /inventario-stock-movimientos, /inventario-transferencias,
 * /inventario-alertas-incidentes).
 *
 * Verifica estructura de las 4 pantallas, navegacion y regresion. La logica
 * (semaforo FEFO, ciclo de transferencias, alertas/incidentes) se prueba en
 * tests/inventario_flow.test.mjs.
 *
 * Uso: php tools/verify_cp_front07.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_cp_front07.php <base-url>\n");
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

// --- /inventario-lotes-fefo ---
$r = get("{$base}/inventario-lotes-fefo");
check($r['status'] === 200, 'GET /inventario-lotes-fefo -> 200');
check(str_contains($r['body'], 'id="lot-estado"') && str_contains($r['body'], 'id="lot-tabla"'),
    'lotes con filtro de estado y tabla');
check(str_contains($r['body'], '/assets/js/lotes.js'), 'JS de lotes servido');

// --- /inventario-stock-movimientos ---
$r = get("{$base}/inventario-stock-movimientos");
check($r['status'] === 200, 'GET /inventario-stock-movimientos -> 200');
check(str_contains($r['body'], 'id="stock-store"') && str_contains($r['body'], 'id="stock-tabla"'),
    'stock por sucursal/lote');
check(str_contains($r['body'], 'id="mov-tipo"') && str_contains($r['body'], 'id="mov-tabla"'),
    'kardex de movimientos con filtro de tipo');
check(str_contains($r['body'], '/assets/js/stock.js'), 'JS de stock servido');

// --- /inventario-transferencias ---
$r = get("{$base}/inventario-transferencias");
check($r['status'] === 200, 'GET /inventario-transferencias -> 200');
check(str_contains($r['body'], 'id="tr-origen"') && str_contains($r['body'], 'id="tr-destino"')
    && str_contains($r['body'], 'id="tr-idempotency"'), 'transferencia con origen/destino e idempotencia');
check(str_contains($r['body'], 'id="tr-item-lote"') && str_contains($r['body'], 'id="tr-items-lista"'),
    'editor de items por lote');
check(str_contains($r['body'], 'id="tr-despachar"') && str_contains($r['body'], 'id="tr-recibir"')
    && str_contains($r['body'], 'id="tr-cerrar"') && str_contains($r['body'], 'id="tr-rechazar"'),
    'ciclo completo de la transferencia');
check(str_contains($r['body'], '/assets/js/transferencias.js'), 'JS de transferencias servido');

// --- /inventario-alertas-incidentes ---
$r = get("{$base}/inventario-alertas-incidentes");
check($r['status'] === 200, 'GET /inventario-alertas-incidentes -> 200');
check(str_contains($r['body'], 'id="al-tipo"') && str_contains($r['body'], 'id="al-evaluar"')
    && str_contains($r['body'], 'id="al-tabla"'), 'alertas con evaluacion y filtros');
check(str_contains($r['body'], 'id="inc-form"') && str_contains($r['body'], 'id="inc-consumo"')
    && str_contains($r['body'], 'id="inc-tabla"'), 'incidentes de consumo');
check(str_contains($r['body'], '/assets/js/alertas.js'), 'JS de alertas servido');

// --- navegacion del layout ---
check(str_contains($r['body'], 'href="/inventario-lotes-fefo"') && str_contains($r['body'], 'href="/inventario-transferencias"')
    && str_contains($r['body'], 'href="/inventario-alertas-incidentes"'), 'nav enlaza las pantallas de inventario');
check(str_contains($r['body'], 'class="sidebar"') && str_contains($r['body'], 'aria-current="page"')
    && str_contains($r['body'], 'id="header-user"'), 'layout compartido intacto');

// --- regresion ---
$r = get("{$base}/dashboard");
check($r['status'] === 200 && str_contains($r['body'], 'id="w-ventas-dato"'), 'regresion /dashboard');
$r = get("{$base}/compras-recepciones");
check($r['status'] === 200 && str_contains($r['body'], 'id="rec-orden"'), 'regresion /compras-recepciones');
$r = get("{$base}/login");
check($r['status'] === 200 && str_contains($r['body'], 'id="login-form"'), 'regresion /login');
$r = get("{$base}/ruta-que-no-existe");
check($r['status'] === 404, 'ruta web inexistente -> 404');
$r = get("{$base}/api/v1/inventory/stocks");
check($r['status'] === 401 && str_contains($r['body'], 'UNAUTHENTICATED'), 'API intacta: /inventory/stocks sin token -> 401');

echo 'RESULTADO: ' . ($fail === 0 ? 'PASS' : "FAIL ({$fail})") . "\n";
exit($fail === 0 ? 0 : 1);
