<?php
declare(strict_types=1);

/**
 * Verificación CP-BACK-04 — Módulo Catálogo (7 recursos)
 *
 * Cobertura: 401 sin token, 403 RBAC sin permiso, CRUD productos
 * (201/409/400/404) + subrecurso categorías (200/409), precios
 * (201/409 DUPLICATE_PRICE/400/404 y DELETE 404 por ruta inexistente),
 * promociones (201/400/409), proveedores/pacientes/prescriptores
 * (201/409 DUPLICATE_IDENTIFICATION/400/204->404) y anonimización.
 *
 * Requiere: servidor corriendo y seed previo con tools/init_admin.php
 * (ADMIN_USER / ADMIN_PASSWORD + permiso catalog.manage).
 *
 * Uso: php tools/verify_cp_back04.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_cp_back04.php <base-url>\n");
    exit(2);
}

$adminUser = getenv('ADMIN_USER') ?: 'admin';
$adminPass = getenv('ADMIN_PASSWORD');
if ($adminPass === false || $adminPass === '') {
    fwrite(STDERR, "Defina ADMIN_PASSWORD (la misma del seed init_admin.php).\n");
    exit(2);
}

$fail = 0;

function check(bool $ok, string $label): bool
{
    global $fail;
    echo ($ok ? 'PASS' : 'FAIL') . ": {$label}\n";
    if (!$ok) {
        $fail++;
    }
    return $ok;
}

/** @return array{status:int,json:?array,body:string} */
function call(string $method, string $url, ?array $json = null, ?string $token = null): array
{
    $headers = '';
    if ($json !== null) {
        $headers .= "Content-Type: application/json\r\n";
    }
    if ($token !== null) {
        $headers .= "Authorization: Bearer {$token}\r\n";
    }
    $opts = ['http' => ['timeout' => 15, 'ignore_errors' => true, 'method' => $method]];
    if ($headers !== '') {
        $opts['http']['header'] = $headers;
    }
    if ($json !== null) {
        $opts['http']['content'] = json_encode($json, JSON_UNESCAPED_UNICODE);
    }
    $body = file_get_contents($url, false, stream_context_create($opts));
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) {
        $status = (int)$m[1];
    }
    $raw = $body === false ? '' : $body;
    return ['status' => $status, 'json' => $raw === '' ? null : json_decode($raw, true), 'body' => $raw];
}

function errorCode(array $r): string
{
    return (string)($r['json']['error']['code'] ?? '');
}

$sufijo = substr(md5((string)random_int(0, PHP_INT_MAX)), 0, 8);
$catUrl = "{$base}/api/v1/catalog";
$opsUrl = "{$base}/api/v1/ops";
$sku = "SKU{$sufijo}";
$ident = "ID{$sufijo}";

// --- 1. Autenticación ---
$r = call('GET', "{$catUrl}/products");
check($r['status'] === 401 && errorCode($r) === 'UNAUTHENTICATED', 'GET /catalog/products sin token -> 401 UNAUTHENTICATED');

$r = call('POST', "{$catUrl}/products", ['sku' => $sku, 'nombre' => 'x']);
check($r['status'] === 401, 'POST /catalog/products sin token -> 401');

$r = call('POST', "{$catUrl}/suppliers", ['identificacion' => $ident, 'nombre' => 'x']);
check($r['status'] === 401, 'POST /catalog/suppliers sin token -> 401');

// --- 2. Login admin (permiso catalog.manage) ---
$r = call('POST', "{$base}/api/v1/auth/login", ['usuario' => $adminUser, 'password' => $adminPass]);
check($r['status'] === 200, 'login admin -> 200');
$token = (string)($r['json']['data']['token'] ?? '');
check(in_array('catalog.manage', $r['json']['data']['user']['permisos'] ?? [], true),
    'payload incluye permiso catalog.manage (seed)');

// --- 3. RBAC: usuario sin permiso lee pero no escribe ---
$r = call('POST', "{$base}/api/v1/auth/users", ['usuario' => "cat_{$sufijo}", 'password' => 'Fix!Pass123', 'roles' => []], $token);
check($r['status'] === 201, 'crear usuario sin roles -> 201');
$r = call('POST', "{$base}/api/v1/auth/login", ['usuario' => "cat_{$sufijo}", 'password' => 'Fix!Pass123']);
check($r['status'] === 200, 'login usuario sin roles -> 200');
$fixToken = (string)($r['json']['data']['token'] ?? '');

$r = call('GET', "{$catUrl}/products", null, $fixToken);
check($r['status'] === 200, 'GET products con token sin permiso -> 200 (lectura con token)');

$r = call('POST', "{$catUrl}/products", ['sku' => 'X', 'nombre' => 'x'], $fixToken);
check($r['status'] === 403 && errorCode($r) === 'FORBIDDEN', 'POST products sin permiso -> 403 FORBIDDEN');

$r = call('PUT', "{$catUrl}/products/1", ['nombre' => 'x'], $fixToken);
check($r['status'] === 403, 'PUT products sin permiso -> 403');

$r = call('DELETE', "{$catUrl}/products/1", null, $fixToken);
check($r['status'] === 403, 'DELETE products sin permiso -> 403');

$r = call('PUT', "{$catUrl}/products/1/categories", ['category_ids' => [1]], $fixToken);
check($r['status'] === 403, 'PUT subrecurso categories sin permiso -> 403');

// --- 4. CRUD productos ---
$producto = [
    'sku' => $sku,
    'nombre' => "Paracetamol {$sufijo}",
    'principio_activo' => 'Paracetamol',
    'presentacion' => 'Caja x 10 tabletas',
    'concentracion' => '500 mg',
    'condicion_venta' => 'libre',
];
$r = call('POST', "{$catUrl}/products", $producto, $token);
check($r['status'] === 201 && ($r['json']['data']['sku'] ?? '') === $sku
    && ($r['json']['data']['estado'] ?? '') === 'activo', 'POST product valido -> 201 con sku/estado');
$prodId = (int)($r['json']['data']['id'] ?? 0);
check($prodId > 0, 'producto creado con id > 0');

$r = call('POST', "{$catUrl}/products", $producto, $token);
check($r['status'] === 409 && errorCode($r) === 'DUPLICATE_SKU', 'POST product sku duplicado -> 409 DUPLICATE_SKU');

$malo = $producto;
$malo['condicion_venta'] = 'libretest';
$r = call('POST', "{$catUrl}/products", $malo, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'POST product condicion_venta invalida -> 400 VALIDATION_ERROR');

$malo = $producto;
unset($malo['nombre']);
$r = call('POST', "{$catUrl}/products", $malo, $token);
check($r['status'] === 400, 'POST product sin nombre -> 400');

$r = call('GET', "{$catUrl}/products?q={$sku}", null, $token);
check($r['status'] === 200 && ($r['json']['data'][0]['sku'] ?? '') === $sku
    && isset($r['json']['meta']['total']), 'GET products?q=sku filtra y pagina -> 200 con meta');

$r = call('GET', "{$catUrl}/products/{$prodId}", null, $token);
check($r['status'] === 200 && ($r['json']['data']['id'] ?? 0) === $prodId, 'GET product por id -> 200');

$r = call('GET', "{$catUrl}/products/99999999", null, $token);
check($r['status'] === 404 && errorCode($r) === 'NOT_FOUND', 'GET product inexistente -> 404 NOT_FOUND');

$putProd = $producto;
$putProd['nombre'] = "Paracetamol {$sufijo} v2";
$r = call('PUT', "{$catUrl}/products/{$prodId}", $putProd, $token);
check($r['status'] === 200 && str_contains((string)($r['json']['data']['nombre'] ?? ''), 'v2'),
    'PUT product 200 refleja cambio (reemplazo completo)');

// SKU duplicado vía PUT (otro producto)
$r = call('POST', "{$catUrl}/products", $producto + ['nombre' => 'Otro'], $token);
check($r['status'] === 409, 'POST segundo product mismo sku -> 409');

// --- 5. Subrecurso categorías del producto ---
$r = call('POST', "{$catUrl}/categories", ['nombre' => "Cat CP4 {$sufijo}"], $token);
check($r['status'] === 201, 'POST category (fixture) -> 201');
$catId = (int)($r['json']['data']['id'] ?? 0);
check($catId > 0, 'category creada con id > 0');

$r = call('PUT', "{$catUrl}/products/{$prodId}/categories", ['category_ids' => [$catId]], $token);
check($r['status'] === 200, 'PUT categories [{cat}] -> 200');

$r = call('GET', "{$catUrl}/products/{$prodId}/categories", null, $token);
$cats = $r['json']['data']['categories'] ?? [];
$ids = array_column($cats, 'id');
check($r['status'] === 200 && ($r['json']['data']['product_id'] ?? 0) === $prodId
    && in_array($catId, $ids, true) && isset($cats[0]['nombre']),
    'GET categories refleja {product_id, categories:[id+nombre]}');

$r = call('PUT', "{$catUrl}/products/{$prodId}/categories", ['category_ids' => [99999999]], $token);
check($r['status'] === 409 && errorCode($r) === 'NOT_FOUND', 'PUT categories con id inexistente -> 409 NOT_FOUND');

$r = call('GET', "{$catUrl}/products?category_id={$catId}", null, $token);
check($r['status'] === 200 && in_array($prodId, array_column($r['json']['data'] ?? [], 'id'), true),
    'GET products?category_id=filtra por categoria -> 200');

$r = call('PUT', "{$catUrl}/products/{$prodId}/categories", ['category_ids' => []], $token);
check($r['status'] === 200, 'PUT categories [] (vacia el set) -> 200');
$r = call('GET', "{$catUrl}/products/{$prodId}/categories", null, $token);
check($r['status'] === 200 && ($r['json']['data']['categories'] ?? null) === [],
    'GET categories tras vaciar -> categories []');

// --- 6. Precios ---
$r = call('POST', "{$opsUrl}/stores", ['codigo' => "ST{$sufijo}", 'nombre' => "Suc CP4 {$sufijo}"], $token);
check($r['status'] === 201, 'POST store (fixture) -> 201');
$storeId = (int)($r['json']['data']['id'] ?? 0);

$precio = ['product_id' => $prodId, 'store_id' => $storeId, 'precio' => '10.50', 'vigente_desde' => '2026-10-01'];
$r = call('POST', "{$catUrl}/prices", $precio, $token);
check($r['status'] === 201 && ($r['json']['data']['precio'] ?? '') !== '', 'POST price 201 (decimal en data)');
$priceId = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$catUrl}/prices", $precio, $token);
check($r['status'] === 409 && errorCode($r) === 'DUPLICATE_PRICE', 'POST price duplicado -> 409 DUPLICATE_PRICE');

$r = call('POST', "{$catUrl}/prices", ['product_id' => $prodId, 'store_id' => null, 'precio' => '10.50', 'vigente_desde' => '2026-10-02'], $token);
check($r['status'] === 201, 'POST price sin store (store_id null) -> 201');

$r = call('POST', "{$catUrl}/prices", ['product_id' => $prodId, 'store_id' => $storeId, 'precio' => '-1', 'vigente_desde' => '2026-10-03'], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'POST price negativo -> 400');

$r = call('POST', "{$catUrl}/prices", ['product_id' => 99999999, 'store_id' => $storeId, 'precio' => '10.50', 'vigente_desde' => '2026-10-04'], $token);
check($r['status'] === 409 && errorCode($r) === 'NOT_FOUND', 'POST price product inexistente -> 409 NOT_FOUND');

$r = call('POST', "{$catUrl}/prices", ['product_id' => $prodId, 'store_id' => 99999999, 'precio' => '10.50', 'vigente_desde' => '2026-10-05'], $token);
check($r['status'] === 409 && errorCode($r) === 'NOT_FOUND', 'POST price store inexistente -> 409 NOT_FOUND');

$r = call('GET', "{$catUrl}/prices?product_id={$prodId}", null, $token);
check($r['status'] === 200 && in_array($priceId, array_column($r['json']['data'] ?? [], 'id'), true)
    && isset($r['json']['meta']['total']), 'GET prices?product_id=filtra con meta');

$r = call('GET', "{$catUrl}/prices/{$priceId}", null, $token);
check($r['status'] === 200, 'GET price por id -> 200');

$r = call('PUT', "{$catUrl}/prices/{$priceId}",
    ['product_id' => $prodId, 'store_id' => $storeId, 'precio' => '12.75', 'vigente_desde' => '2026-10-01'], $token);
check($r['status'] === 200 && (string)($r['json']['data']['precio'] ?? '') === '12.75',
    'PUT price 200 refleja nuevo precio (reemplazo completo)');

$r = call('DELETE', "{$catUrl}/prices/{$priceId}", null, $token);
check($r['status'] === 404, 'DELETE price -> 404 (ruta inexistente: baja por vigencia, supuesto)');

// --- 7. Promociones ---
$promo = ['product_id' => $prodId, 'descuento_pct' => 15, 'desde' => '2026-10-01', 'hasta' => '2026-10-31'];
$r = call('POST', "{$catUrl}/promotions", $promo, $token);
check($r['status'] === 201, 'POST promotion 201');
$promoId = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$catUrl}/promotions", ['descuento_pct' => 10, 'desde' => '2026-10-01', 'hasta' => '2026-10-31'], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'POST promotion sin alcance -> 400');

$r = call('POST', "{$catUrl}/promotions", ['product_id' => $prodId, 'descuento_pct' => 0, 'desde' => '2026-10-01', 'hasta' => '2026-10-31'], $token);
check($r['status'] === 400, 'POST promotion descuento_pct 0 -> 400');

$r = call('POST', "{$catUrl}/promotions", ['product_id' => $prodId, 'descuento_pct' => 15, 'desde' => '2026-11-01', 'hasta' => '2026-10-31'], $token);
check($r['status'] === 400, 'POST promotion hasta < desde -> 400');

$r = call('POST', "{$catUrl}/promotions", ['product_id' => 99999999, 'descuento_pct' => 15, 'desde' => '2026-10-01', 'hasta' => '2026-10-31'], $token);
check($r['status'] === 409 && errorCode($r) === 'NOT_FOUND', 'POST promotion product inexistente -> 409 NOT_FOUND');

$r = call('GET', "{$catUrl}/promotions?product_id={$prodId}", null, $token);
check($r['status'] === 200 && in_array($promoId, array_column($r['json']['data'] ?? [], 'id'), true),
    'GET promotions?product_id=filtra');

$r = call('GET', "{$catUrl}/promotions/{$promoId}", null, $token);
check($r['status'] === 200, 'GET promotion por id -> 200');

$r = call('PUT', "{$catUrl}/promotions/{$promoId}",
    ['product_id' => $prodId, 'descuento_pct' => 15, 'desde' => '2026-10-01', 'hasta' => '2026-12-31'], $token);
check($r['status'] === 200 && ($r['json']['data']['hasta'] ?? '') === '2026-12-31',
    'PUT promotion 200 (cierre de vigencia, reemplazo completo)');

$r = call('GET', "{$catUrl}/promotions?category_id={$catId}", null, $token);
check($r['status'] === 200, 'GET promotions?category_id= -> 200');

$r = call('DELETE', "{$catUrl}/promotions/{$promoId}", null, $token);
check($r['status'] === 404, 'DELETE promotion -> 404 (ruta inexistente: baja por vigencia, supuesto)');

// --- 8. Proveedores ---
$prov = ['identificacion' => $ident, 'nombre' => "Distribuidora {$sufijo}", 'contacto' => 'prov@test.local'];
$r = call('POST', "{$catUrl}/suppliers", $prov, $token);
check($r['status'] === 201 && ($r['json']['data']['identificacion'] ?? '') === $ident, 'POST supplier 201');
$provId = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$catUrl}/suppliers", $prov, $token);
check($r['status'] === 409 && errorCode($r) === 'DUPLICATE_IDENTIFICATION', 'POST supplier duplicado -> 409 DUPLICATE_IDENTIFICATION');

$r = call('POST', "{$catUrl}/suppliers", ['identificacion' => str_repeat('x', 31), 'nombre' => 'x'], $token);
check($r['status'] === 400, 'POST supplier identificacion >30 -> 400');

$r = call('GET', "{$catUrl}/suppliers?q={$ident}", null, $token);
check($r['status'] === 200 && in_array($provId, array_column($r['json']['data'] ?? [], 'id'), true),
    'GET suppliers?q=filtra');

$r = call('PUT', "{$catUrl}/suppliers/{$provId}",
    ['identificacion' => $ident, 'nombre' => "Distribuidora {$sufijo} SA", 'contacto' => 'prov2@test.local'], $token);
check($r['status'] === 200 && str_contains((string)($r['json']['data']['nombre'] ?? ''), 'SA'),
    'PUT supplier 200 refleja cambio (reemplazo completo)');

// --- 9. Pacientes (PII + anonimización) ---
$pac = ['identificacion' => $ident . 'P', 'nombre' => "Paciente {$sufijo}", 'fecha_nacimiento' => '1990-05-20'];
$r = call('POST', "{$catUrl}/patients", $pac, $token);
check($r['status'] === 201 && ($r['json']['data']['identificacion'] ?? '') === $ident . 'P', 'POST patient 201');
$pacId = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$catUrl}/patients", $pac, $token);
check($r['status'] === 409 && errorCode($r) === 'DUPLICATE_IDENTIFICATION', 'POST patient duplicado -> 409 DUPLICATE_IDENTIFICATION');

$r = call('POST', "{$catUrl}/patients", ['identificacion' => $ident . 'Q', 'nombre' => 'Y', 'fecha_nacimiento' => '20/05/1990'], $token);
check($r['status'] === 400, 'POST patient fecha_nacimiento formato invalido -> 400');

$r = call('PUT', "{$catUrl}/patients/{$pacId}",
    ['identificacion' => $ident . 'P', 'nombre' => "Paciente {$sufijo}", 'contacto' => '555-0001'], $token);
check($r['status'] === 200 && ($r['json']['data']['contacto'] ?? '') === '555-0001',
    'PUT patient 200 refleja cambio');

// --- 10. Prescriptores ---
$pres = ['identificacion' => $ident . 'R', 'nombre' => "Dr. {$sufijo}", 'especialidad' => 'General'];
$r = call('POST', "{$catUrl}/prescribers", $pres, $token);
check($r['status'] === 201, 'POST prescriber 201');
$presId = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$catUrl}/prescribers", $pres, $token);
check($r['status'] === 409 && errorCode($r) === 'DUPLICATE_IDENTIFICATION', 'POST prescriber duplicado -> 409 DUPLICATE_IDENTIFICATION');

$r = call('PUT', "{$catUrl}/prescribers/{$presId}",
    ['identificacion' => $ident . 'R', 'nombre' => "Dr. {$sufijo}", 'especialidad' => 'Pediatra'], $token);
check($r['status'] === 200 && ($r['json']['data']['especialidad'] ?? '') === 'Pediatra',
    'PUT prescriber 200 refleja cambio');

// --- 11. Borrado lógico (204 -> 404), sin DELETE FROM ---
$r = call('DELETE', "{$catUrl}/products/{$prodId}", null, $token);
check($r['status'] === 204, 'DELETE product -> 204 (borrado logico)');
$r = call('GET', "{$catUrl}/products/{$prodId}", null, $token);
check($r['status'] === 404, 'GET product tras DELETE -> 404');

$r = call('DELETE', "{$catUrl}/suppliers/{$provId}", null, $token);
check($r['status'] === 204, 'DELETE supplier -> 204');
$r = call('GET', "{$catUrl}/suppliers/{$provId}", null, $token);
check($r['status'] === 404, 'GET supplier tras DELETE -> 404');

$r = call('DELETE', "{$catUrl}/prescribers/{$presId}", null, $token);
check($r['status'] === 204, 'DELETE prescriber -> 204');
$r = call('GET', "{$catUrl}/prescribers/{$presId}", null, $token);
check($r['status'] === 404, 'GET prescriber tras DELETE -> 404');

$r = call('DELETE', "{$catUrl}/patients/{$pacId}", null, $token);
check($r['status'] === 204, 'DELETE patient -> 204 (anonimizacion)');
$r = call('GET', "{$catUrl}/patients/{$pacId}", null, $token);
check($r['status'] === 404, 'GET patient tras DELETE -> 404');
$r = call('GET', "{$catUrl}/patients?q={$ident}P", null, $token);
check($r['status'] === 200 && !in_array($pacId, array_column($r['json']['data'] ?? [], 'id'), true),
    'patient anonimizado no aparece por identificacion original');

// --- 12. Limpieza de fixtures ---
$r = call('DELETE', "{$catUrl}/categories/{$catId}", null, $token);
check($r['status'] === 204, 'DELETE category fixture -> 204');
$r = call('DELETE', "{$opsUrl}/stores/{$storeId}", null, $token);
check($r['status'] === 204, 'DELETE store fixture -> 204');

echo "\nRESULTADO: " . ($fail === 0 ? 'PASS' : "FAIL ({$fail})") . "\n";
exit($fail === 0 ? 0 : 1);
