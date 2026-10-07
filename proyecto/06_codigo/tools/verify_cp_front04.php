<?php
declare(strict_types=1);

/**
 * CP-FRONT-04 - Catalogos y Precios (/catalogo-categorias, /catalogo-productos,
 * /catalogo-precios-promociones).
 *
 * Verifica estructura de las 3 pantallas, navegacion y regresion. La logica de
 * datos se prueba en tests/catalog_flow.test.mjs.
 *
 * Uso: php tools/verify_cp_front04.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_cp_front04.php <base-url>\n");
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

// --- /catalogo-categorias ---
$r = get("{$base}/catalogo-categorias");
check($r['status'] === 200, 'GET /catalogo-categorias -> 200');
check(str_contains($r['body'], 'id="cat-form"') && str_contains($r['body'], 'id="cat-nombre"')
    && str_contains($r['body'], 'id="cat-parent"'), 'formulario con jerarquia (categoria padre)');
check(str_contains($r['body'], 'id="cat-buscar"') && str_contains($r['body'], 'id="cat-q"'), 'busqueda por nombre');
check(str_contains($r['body'], 'id="cat-tabla"') && str_contains($r['body'], '/assets/js/categorias.js'),
    'tabla y JS de categorias');

// --- /catalogo-productos ---
$r = get("{$base}/catalogo-productos");
check($r['status'] === 200, 'GET /catalogo-productos -> 200');
check(str_contains($r['body'], 'id="prod-sku"') && str_contains($r['body'], 'id="prod-nombre"')
    && str_contains($r['body'], 'id="prod-condicion"') && str_contains($r['body'], 'id="prod-categorias"'),
    'formulario con sku, condicion de venta y categorias');
check(str_contains($r['body'], 'id="prod-buscar"') && str_contains($r['body'], 'id="prod-tabla"'),
    'busqueda y tabla de productos');
check(str_contains($r['body'], '/assets/js/productos.js'), 'JS de productos servido');

// --- /catalogo-precios-promociones ---
$r = get("{$base}/catalogo-precios-promociones");
check($r['status'] === 200, 'GET /catalogo-precios-promociones -> 200');
check(str_contains($r['body'], 'id="pr-form"') && str_contains($r['body'], 'id="pr-precio"')
    && str_contains($r['body'], 'id="pr-desde"') && str_contains($r['body'], 'id="pr-tabla"'),
    'gestion de precios con vigencias');
check(str_contains($r['body'], 'id="pm-form"') && str_contains($r['body'], 'id="pm-tipo"')
    && str_contains($r['body'], 'id="pm-descuento"') && str_contains($r['body'], 'id="pm-tabla"'),
    'gestion de promociones con alcance producto/categoria');
check(str_contains($r['body'], '/assets/js/precios.js'), 'JS de precios/promociones servido');

// --- navegacion del layout ---
check(str_contains($r['body'], 'href="/catalogo-categorias"') && str_contains($r['body'], 'href="/catalogo-productos"')
    && str_contains($r['body'], 'href="/catalogo-precios-promociones"'), 'nav enlaza las pantallas de catalogo');
check(str_contains($r['body'], 'class="sidebar"') && str_contains($r['body'], 'aria-current="page"')
    && str_contains($r['body'], 'id="header-user"'), 'layout compartido intacto');

// --- regresion ---
$r = get("{$base}/dashboard");
check($r['status'] === 200 && str_contains($r['body'], 'id="w-ventas-dato"'), 'regresion /dashboard');
$r = get("{$base}/autenticacion-usuarios");
check($r['status'] === 200 && str_contains($r['body'], 'id="u-form"'), 'regresion /autenticacion-usuarios');
$r = get("{$base}/login");
check($r['status'] === 200 && str_contains($r['body'], 'id="login-form"'), 'regresion /login');
$r = get("{$base}/ruta-que-no-existe");
check($r['status'] === 404, 'ruta web inexistente -> 404');
$r = get("{$base}/api/v1/catalog/products");
check($r['status'] === 401 && str_contains($r['body'], 'UNAUTHENTICATED'), 'API intacta: /catalog/products sin token -> 401');

echo 'RESULTADO: ' . ($fail === 0 ? 'PASS' : "FAIL ({$fail})") . "\n";
exit($fail === 0 ? 0 : 1);
