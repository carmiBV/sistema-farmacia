<?php
declare(strict_types=1);

/**
 * CP-INT-02 - Integracion de Catalogos y Sucursales (E2E, HTTP + BD).
 *
 * Flujo de punta a punta: creacion/edicion de sucursales, cajas, categorias
 * jerarquicas (parent_id_key), productos con categorias, precios y promociones.
 * Inmutabilidad con borrado logico: tras DELETE (204) la fila PERSISTE con
 * estado inactivo en BD y el GET responde 404; la uq (nombre, parent_id_key)
 * de categorias sigue cubriendo filas inactivas. Precios/promociones no tienen
 * DELETE (append-only con cierre de vigencia por PUT).
 *
 * Requiere: servidor corriendo y seed previo con tools/init_admin.php.
 * Uso: php tools/verify_int02.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_int02.php <base-url>\n");
    exit(2);
}

$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = $root . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
\App\Core\Env::load($root . '/.env');

$adminUser = getenv('ADMIN_USER') ?: 'admin';
$adminPass = getenv('ADMIN_PASSWORD');
if ($adminPass === false || $adminPass === '') {
    fwrite(STDERR, "Defina ADMIN_PASSWORD.\n");
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

/** @return array{status:int,json:?array,body:string} */
function call(string $method, string $url, array|string|null $json = null, ?string $token = null): array
{
    $headers = '';
    if ($token !== null && $token !== '') {
        $headers .= "Authorization: Bearer {$token}\r\n";
    }
    $opts = ['http' => ['timeout' => 20, 'ignore_errors' => true, 'method' => $method]];
    if ($json !== null) {
        $headers .= "Content-Type: application/json\r\n";
        $opts['http']['content'] = is_string($json) ? $json : json_encode($json, JSON_UNESCAPED_UNICODE);
    }
    if ($headers !== '') {
        $opts['http']['header'] = $headers;
    }
    $body = (string)file_get_contents($url, false, stream_context_create($opts));
    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) {
            $status = (int)$m[1];
        }
    }
    return ['status' => $status, 'json' => json_decode($body, true), 'body' => $body];
}

function errorCode(array $r): string
{
    return (string)($r['json']['error']['code'] ?? '');
}

function dbVal(string $sql, array $params = []): mixed
{
    $stmt = \App\Core\Database::pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

$api = "{$base}/api/v1";
$r = call('POST', "{$api}/auth/login", ['usuario' => $adminUser, 'password' => $adminPass]);
$token = (string)($r['json']['data']['token'] ?? '');
check($token !== '', 'sesion iniciada (login -> token)');
$sufijo = substr(md5((string)random_int(0, PHP_INT_MAX)), 0, 8);

// --- 1. sucursales ---
$r = call('POST', "{$api}/ops/stores", ['codigo' => "CS{$sufijo}", 'nombre' => "Sucursal Int {$sufijo}"], $token);
check($r['status'] === 201, 'sucursal: creacion -> 201');
$storeId = (int)($r['json']['data']['id'] ?? 0);
$r = call('PUT', "{$api}/ops/stores/{$storeId}", ['codigo' => "CS{$sufijo}", 'nombre' => "Sucursal Int Editada {$sufijo}"], $token);
check($r['status'] === 200 && str_contains((string)($r['json']['data']['nombre'] ?? ''), 'Editada'), 'sucursal: edicion -> 200');

// --- 2. cajas ---
$r = call('POST', "{$api}/ops/registers", ['store_id' => $storeId, 'codigo' => "CC{$sufijo}"], $token);
check($r['status'] === 201, 'caja: creacion -> 201');
$cajaId = (int)($r['json']['data']['id'] ?? 0);
$r = call('PUT', "{$api}/ops/registers/{$cajaId}", ['store_id' => $storeId, 'codigo' => "CC{$sufijo}B"], $token);
check($r['status'] === 200, 'caja: edicion -> 200');

// --- 3. categorias jerarquicas (parent_id_key) ---
$r = call('POST', "{$api}/catalog/categories", ['nombre' => "IntCat{$sufijo}"], $token);
check($r['status'] === 201, 'categoria raiz: creacion -> 201');
$catRaiz = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$api}/catalog/categories", ['nombre' => "IntSub{$sufijo}", 'parent_id' => $catRaiz], $token);
check($r['status'] === 201 && ($r['json']['data']['parent_id'] ?? null) === $catRaiz, 'categoria hija con parent_id -> 201');
$subId = (int)($r['json']['data']['id'] ?? 0);
$r = call('PUT', "{$api}/catalog/categories/{$subId}", ['nombre' => "IntSub{$sufijo}B", 'parent_id' => null], $token);
$datosSub = $r['json']['data'] ?? [];
check($r['status'] === 200 && array_key_exists('parent_id', $datosSub) && $datosSub['parent_id'] === null,
    'categoria hija movida a raiz -> 200 (parent_id null explícito)');
$r = call('POST', "{$api}/catalog/categories", ['nombre' => "IntSub{$sufijo}B"], $token);
check($r['status'] === 409 && errorCode($r) === 'DUPLICATE_NAME', 'unicidad por rama (parent_id_key): duplicado en raiz -> 409');

// --- 4. productos con categorias ---
$r = call('POST', "{$api}/catalog/products", [
    'sku' => "SKU{$sufijo}", 'nombre' => "Producto Int {$sufijo}", 'principio_activo' => 'Test',
    'presentacion' => 'Caja', 'concentracion' => '10mg', 'condicion_venta' => 'libre',
], $token);
check($r['status'] === 201, 'producto: creacion -> 201');
$prodId = (int)($r['json']['data']['id'] ?? 0);
$r = call('PUT', "{$api}/catalog/products/{$prodId}/categories", ['category_ids' => [$catRaiz]], $token);
check($r['status'] === 200, 'producto: vinculo de categorias (PUT subrecurso) -> 200');
$r = call('GET', "{$api}/catalog/products/{$prodId}/categories", null, $token);
check($r['status'] === 200 && in_array($catRaiz, array_map('intval', array_column($r['json']['data']['categories'] ?? [], 'id')), true),
    'producto: categorias consultables');
$r = call('PUT', "{$api}/catalog/products/{$prodId}", [
    'sku' => "SKU{$sufijo}", 'nombre' => "Producto Int Editado {$sufijo}", 'principio_activo' => 'Test',
    'presentacion' => 'Caja', 'concentracion' => '10mg', 'condicion_venta' => 'libre',
], $token);
check($r['status'] === 200 && str_contains((string)($r['json']['data']['nombre'] ?? ''), 'Editado'), 'producto: edicion -> 200');

// --- 5. precios y promociones (append-only, sin DELETE) ---
$r = call('POST', "{$api}/catalog/prices", [
    'product_id' => $prodId, 'precio' => 10.50, 'vigente_desde' => '2026-10-01', 'vigente_hasta' => '2026-12-31',
], $token);
check($r['status'] === 201, 'precio: creacion con vigencia -> 201');
$precioId = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$api}/catalog/prices", [
    'product_id' => $prodId, 'precio' => -5, 'vigente_desde' => '2026-10-01',
], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'precio negativo -> 400');
$r = call('PUT', "{$api}/catalog/prices/{$precioId}", [
    'product_id' => $prodId, 'precio' => 10.50, 'vigente_desde' => '2026-10-01', 'vigente_hasta' => '2026-10-31',
], $token);
check($r['status'] === 200, 'precio: cierre de vigencia por PUT -> 200 (sin DELETE)');
$r = call('DELETE', "{$api}/catalog/prices/{$precioId}", null, $token);
check($r['status'] === 404, 'precio: DELETE sin ruta -> 404 (append-only)');

$r = call('POST', "{$api}/catalog/promotions", [
    'product_id' => $prodId, 'descuento_pct' => 10, 'desde' => '2026-10-01', 'hasta' => '2026-10-31',
], $token);
check($r['status'] === 201, 'promocion: creacion por producto -> 201');
$promoId = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$api}/catalog/promotions", ['descuento_pct' => 5, 'desde' => '2026-10-01', 'hasta' => '2026-10-31'], $token);
check($r['status'] === 400, 'promocion sin alcance -> 400');
$r = call('PUT', "{$api}/catalog/promotions/{$promoId}", [
    'product_id' => $prodId, 'descuento_pct' => 12, 'desde' => '2026-10-01', 'hasta' => '2026-11-30',
], $token);
check($r['status'] === 200, 'promocion: edicion -> 200');

// --- 6. borrado logico con persistencia en BD (inmutabilidad) ---
$r = call('DELETE', "{$api}/catalog/products/{$prodId}", null, $token);
check($r['status'] === 204, 'producto: DELETE logico -> 204');
$r = call('GET', "{$api}/catalog/products/{$prodId}", null, $token);
check($r['status'] === 404, 'producto: GET tras DELETE -> 404');
check((string)dbVal('SELECT estado FROM catalog_products WHERE id = ?', [$prodId]) === 'inactivo',
    'producto: fila persiste en BD con estado inactivo');

$r = call('DELETE', "{$api}/catalog/categories/{$subId}", null, $token);
check($r['status'] === 204, 'categoria: DELETE logico -> 204');
check((string)dbVal('SELECT estado FROM catalog_categories WHERE id = ?', [$subId]) === 'inactivo',
    'categoria: fila persiste en BD con estado inactivo');
$r = call('POST', "{$api}/catalog/categories", ['nombre' => "IntSub{$sufijo}B"], $token);
check($r['status'] === 409 && errorCode($r) === 'DUPLICATE_NAME',
    'categoria: la uq (nombre, parent_id_key) cubre filas inactivas -> 409');

$r = call('DELETE', "{$api}/ops/registers/{$cajaId}", null, $token);
check($r['status'] === 204, 'caja: DELETE logico -> 204');
check((string)dbVal('SELECT estado FROM ops_registers WHERE id = ?', [$cajaId]) === 'inactiva',
    'caja: fila persiste en BD con estado inactiva');

$r = call('DELETE', "{$api}/ops/stores/{$storeId}", null, $token);
check($r['status'] === 204, 'sucursal: DELETE logico -> 204');
$r = call('GET', "{$api}/ops/stores/{$storeId}", null, $token);
check($r['status'] === 404, 'sucursal: GET tras DELETE -> 404');
check((string)dbVal('SELECT estado FROM ops_stores WHERE id = ?', [$storeId]) === 'inactiva',
    'sucursal: fila persiste en BD con estado inactiva');

echo 'RESULTADO: ' . ($fail === 0 ? 'PASS' : "FAIL ({$fail})") . "\n";
exit($fail === 0 ? 0 : 1);
