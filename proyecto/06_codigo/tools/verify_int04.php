<?php
declare(strict_types=1);

/**
 * CP-INT-04 - Integracion de Inventario, Transferencias y Alertas (E2E, HTTP+BD).
 *
 * Validacion de stock en tiempo real (inventory_stock), kardex append-only
 * (inventory_movements), ciclo inter-sucursal (solicitada -> despachada ->
 * recibida -> cerrada) con RN-02 (sin stock negativo / rollback), RN-08
 * (suma en destino al recibir), RN-09 (idempotencia) y disparo de alertas
 * stock_minimo/vencimiento con umbrales configurables y dedup por producto.
 *
 * Requiere: servidor corriendo y seed previo con tools/init_admin.php.
 * Uso: php tools/verify_int04.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_int04.php <base-url>\n");
    exit(2);
}

$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = $root . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
\App\Support\Env::load($root . '/.env');

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
    $stmt = \App\Support\Database::pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function stock(int $storeId, int $lotId): int
{
    return (int)dbVal('SELECT stock_available FROM inventory_stock WHERE store_id = ? AND lot_id = ?', [$storeId, $lotId]);
}

$api = "{$base}/api/v1";
$r = call('POST', "{$api}/auth/login", ['usuario' => $adminUser, 'password' => $adminPass]);
$token = (string)($r['json']['data']['token'] ?? '');
check($token !== '', 'sesion iniciada (login -> token)');
$sufijo = substr(md5((string)random_int(0, PHP_INT_MAX)), 0, 8);

// --- fixtures: proveedor, producto, sucursales A/B, lote en cuarentena ---
$r = call('POST', "{$api}/catalog/suppliers", ['identificacion' => "IN4{$sufijo}", 'nombre' => "Prov Int4 {$sufijo}"], $token);
$provId = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$api}/catalog/products", [
    'sku' => "I4{$sufijo}", 'nombre' => "Prod Int4 {$sufijo}", 'principio_activo' => 'Test',
    'presentacion' => 'Caja', 'concentracion' => '5mg', 'condicion_venta' => 'libre',
], $token);
$prodId = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$api}/ops/stores", ['codigo' => "A4{$sufijo}", 'nombre' => "Suc A {$sufijo}"], $token);
$storeA = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$api}/ops/stores", ['codigo' => "B4{$sufijo}", 'nombre' => "Suc B {$sufijo}"], $token);
$storeB = (int)($r['json']['data']['id'] ?? 0);
check($provId > 0 && $prodId > 0 && $storeA > 0 && $storeB > 0, 'fixtures creados (proveedor, producto, sucursales A/B)');

$r = call('POST', "{$api}/purchases/orders", [
    'numero' => "OC4{$sufijo}", 'supplier_id' => $provId,
    'items' => [['product_id' => $prodId, 'cantidad_pedida' => 4]],
], $token);
$ordenId = (int)($r['json']['data']['id'] ?? 0);
call('POST', "{$api}/purchases/orders/{$ordenId}/emitir", null, $token);
$r = call('POST', "{$api}/purchases/orders/{$ordenId}/receptions", [
    'items' => [['product_id' => $prodId, 'numero_lote' => "T4{$sufijo}", 'fecha_vencimiento' => '2027-06-30', 'cantidad' => 4]],
], $token);
$recId = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$api}/purchases/receptions/{$recId}/confirmar", ['store_id' => $storeA], $token);
check($r['status'] === 200, 'lote base confirmado en sucursal A');
$lotId = (int)dbVal('SELECT id FROM inventory_lots WHERE numero_lote = ?', ["T4{$sufijo}"]);
check(stock($storeA, $lotId) === 4, 'stock en tiempo real: A = 4');

// --- 1. transferencia con lote en cuarentena -> rollback (RN-02) ---
$r = call('POST', "{$api}/inventory/transfers", [
    'store_origen_id' => $storeA, 'store_destino_id' => $storeB,
    'items' => [['lot_id' => $lotId, 'cantidad' => 2]], 'idempotency_key' => "tq-{$sufijo}",
], $token);
$tCuarentena = (int)($r['json']['data']['id'] ?? ($r['json']['data']['transferencia']['id'] ?? 0));
$r = call('POST', "{$api}/inventory/transfers/{$tCuarentena}/despachar", null, $token);
check($r['status'] === 409 && errorCode($r) === 'LOT_NOT_RELEASED', 'despacho de lote en cuarentena -> 409 LOT_NOT_RELEASED');
check(stock($storeA, $lotId) === 4, 'RN-02: rollback verificado (stock A sin cambio)');

// --- 2. liberar lote (FEFO) ---
$r = call('POST', "{$api}/inventory/batches/{$lotId}/liberar", ['motivo' => 'Liberacion Int4'], $token);
check($r['status'] === 200 && (string)dbVal('SELECT estado FROM inventory_lots WHERE id = ?', [$lotId]) === 'liberado',
    'liberacion del lote -> estado liberado');

// --- 3. stock insuficiente -> 409 al crear y stock intacto (RN-02) ---
$r = call('POST', "{$api}/inventory/transfers", [
    'store_origen_id' => $storeA, 'store_destino_id' => $storeB,
    'items' => [['lot_id' => $lotId, 'cantidad' => 99]], 'idempotency_key' => "tx-{$sufijo}",
], $token);
check($r['status'] === 409 && errorCode($r) === 'STOCK_NOT_ENOUGH',
    'transferencia 99u con stock 4 -> 409 STOCK_NOT_ENOUGH (guard al crear)');
check(stock($storeA, $lotId) === 4, 'RN-02: stock A intacto tras la creacion rechazada');

// --- 4. misma sucursal -> 400 ---
$r = call('POST', "{$api}/inventory/transfers", [
    'store_origen_id' => $storeA, 'store_destino_id' => $storeA,
    'items' => [['lot_id' => $lotId, 'cantidad' => 1]],
], $token);
check($r['status'] === 400 && errorCode($r) === 'TRANSFER_SAME_STORE', 'origen == destino -> 400 TRANSFER_SAME_STORE');

// --- 5. ciclo completo A -> B (RN-08/RN-09) ---
$clave = "tc-{$sufijo}";
$r = call('POST', "{$api}/inventory/transfers", [
    'store_origen_id' => $storeA, 'store_destino_id' => $storeB,
    'items' => [['lot_id' => $lotId, 'cantidad' => 2]], 'idempotency_key' => $clave,
], $token);
check($r['status'] === 201, 'transferencia 2u A->B -> 201');
$tId = (int)($r['json']['data']['id'] ?? ($r['json']['data']['transferencia']['id'] ?? 0));
$r = call('POST', "{$api}/inventory/transfers", [
    'store_origen_id' => $storeA, 'store_destino_id' => $storeB,
    'items' => [['lot_id' => $lotId, 'cantidad' => 2]], 'idempotency_key' => $clave,
], $token);
check($r['status'] === 200, 'RN-09: misma idempotency_key -> 200 (sin duplicar)');

$r = call('POST', "{$api}/inventory/transfers/{$tId}/despachar", null, $token);
check($r['status'] === 200 && stock($storeA, $lotId) === 2, 'despachar -> 200 (stock A 4 -> 2)');
$r = call('POST', "{$api}/inventory/transfers/{$tId}/recibir", [
    'items' => [['lot_id' => $lotId, 'cantidad_recibida' => 2]],
], $token);
check($r['status'] === 200 && stock($storeB, $lotId) === 2, 'RN-08: recibir -> 200 (stock B 0 -> 2)');
$r = call('POST', "{$api}/inventory/transfers/{$tId}/cerrar", null, $token);
check($r['status'] === 200 && (string)dbVal('SELECT estado FROM inventory_transfers WHERE id = ?', [$tId]) === 'cerrada',
    'cerrar -> estado cerrada (ciclo solicitada->despachada->recibida->cerrada)');

// --- 6. kardex append-only ---
$movs = (int)dbVal('SELECT COUNT(*) FROM inventory_movements WHERE lot_id = ?', [$lotId]);
check($movs === 3, "kardex append-only: 3 movimientos acumulados (entrada + salida + entrada) sin sobrescribir");
check((int)dbVal(
    "SELECT COUNT(*) FROM inventory_movements WHERE lot_id = ? AND tipo = 'transferencia_salida' AND signo = -1 AND store_id = ?",
    [$lotId, $storeA]
) === 1, 'kardex: transferencia_salida signo -1 en origen (A)');
check((int)dbVal(
    "SELECT COUNT(*) FROM inventory_movements WHERE lot_id = ? AND tipo = 'transferencia_entrada' AND signo = 1 AND store_id = ?",
    [$lotId, $storeB]
) === 1, 'kardex: transferencia_entrada signo +1 en destino (B)');

// --- 7. stock en tiempo real via API ---
$r = call('GET', "{$api}/inventory/stocks?product_id={$prodId}", null, $token);
$porStore = [];
foreach ($r['json']['data'] ?? [] as $s) {
    $porStore[(int)$s['store_id']] = ($porStore[(int)$s['store_id']] ?? 0) + (int)$s['stock_available'];
}
check(($porStore[$storeA] ?? 0) === 2 && ($porStore[$storeB] ?? 0) === 2,
    'stock en tiempo real via API: A=2, B=2');

// --- 8. alertas: stock_minimo y vencimiento con umbrales y dedup ---
call('PUT', "{$api}/ops/config/alerta.stock_minimo", ['config_value' => '10', 'value_type' => 'numero'], $token);
call('PUT', "{$api}/ops/config/alerta.dias_vencimiento", ['config_value' => '30', 'value_type' => 'numero'], $token);
$en10dias = date('Y-m-d', time() + 10 * 86400);
$r = call('POST', "{$api}/purchases/orders", [
    'numero' => "OC4B{$sufijo}", 'supplier_id' => $provId,
    'items' => [['product_id' => $prodId, 'cantidad_pedida' => 1]],
], $token);
$orden2 = (int)($r['json']['data']['id'] ?? 0);
call('POST', "{$api}/purchases/orders/{$orden2}/emitir", null, $token);
$r = call('POST', "{$api}/purchases/orders/{$orden2}/receptions", [
    'items' => [['product_id' => $prodId, 'numero_lote' => "V4{$sufijo}", 'fecha_vencimiento' => $en10dias, 'cantidad' => 1]],
], $token);
$rec2 = (int)($r['json']['data']['id'] ?? 0);
call('POST', "{$api}/purchases/receptions/{$rec2}/confirmar", ['store_id' => $storeA], $token);

$r = call('POST', "{$api}/inventory/alerts/evaluar", null, $token);
check($r['status'] === 200, 'evaluar alertas -> 200');
$antesVenc = (int)dbVal("SELECT COUNT(*) FROM inventory_alerts WHERE product_id = ? AND tipo = 'vencimiento' AND estado = 'abierta'", [$prodId]);
check($antesVenc === 1, 'alerta de VENCIMIENTO generada (lote con vencimiento en 10 dias <= umbral 30)');
$antesStock = (int)dbVal("SELECT COUNT(*) FROM inventory_alerts WHERE product_id = ? AND store_id = ? AND tipo = 'stock_minimo' AND estado = 'abierta'", [$prodId, $storeA]);
check($antesStock === 1, 'alerta de STOCK_MINIMO generada (3u < umbral 10)');

call('POST', "{$api}/inventory/alerts/evaluar", null, $token);
$despVenc = (int)dbVal("SELECT COUNT(*) FROM inventory_alerts WHERE product_id = ? AND tipo = 'vencimiento' AND estado = 'abierta'", [$prodId]);
$despStock = (int)dbVal("SELECT COUNT(*) FROM inventory_alerts WHERE product_id = ? AND store_id = ? AND tipo = 'stock_minimo' AND estado = 'abierta'", [$prodId, $storeA]);
check($despVenc === $antesVenc && $despStock === $antesStock, 'dedup: segunda evaluacion no duplica alertas abiertas');

$alertaId = (int)dbVal("SELECT id FROM inventory_alerts WHERE product_id = ? AND tipo = 'vencimiento' AND estado = 'abierta' LIMIT 1", [$prodId]);
$r = call('POST', "{$api}/inventory/alerts/{$alertaId}/resolver", null, $token);
check($r['status'] === 200 && (string)dbVal('SELECT estado FROM inventory_alerts WHERE id = ?', [$alertaId]) === 'resuelta',
    'resolver alerta -> estado resuelta');

echo 'RESULTADO: ' . ($fail === 0 ? 'PASS' : "FAIL ({$fail})") . "\n";
exit($fail === 0 ? 0 : 1);
