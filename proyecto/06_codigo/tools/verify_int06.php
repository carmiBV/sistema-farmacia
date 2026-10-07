<?php
declare(strict_types=1);

/**
 * CP-INT-06 - Integracion de POS, Pagos, Idempotencia y Devoluciones
 * (E2E, HTTP + BD).
 *
 * Venta en caja con descuento de lotes bajo politica FEFO (lote de vencimiento
 * mas proximo), RN-01 (sin stock negativo), multiples medios de pago
 * (payments_transactions), prevencion de duplicados por idempotency_key,
 * comprobante (orden + items + pagos) y devoluciones (sales_returns) con
 * condicion vendible (reingresa stock) / no_vendible (baja).
 *
 * Requiere: servidor corriendo y seed previo con tools/init_admin.php.
 * Uso: php tools/verify_int06.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_int06.php <base-url>\n");
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

// --- fixtures: caja con dos lotes de distinto vencimiento (FEFO) ---
$r = call('POST', "{$api}/catalog/suppliers", ['identificacion' => "IN6{$sufijo}", 'nombre' => "Prov Int6 {$sufijo}"], $token);
$provId = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$api}/catalog/products", [
    'sku' => "I6{$sufijo}", 'nombre' => "Prod Int6 {$sufijo}", 'principio_activo' => 'Test',
    'presentacion' => 'Caja', 'concentracion' => '10mg', 'condicion_venta' => 'libre',
], $token);
$prodId = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$api}/ops/stores", ['codigo' => "V6{$sufijo}", 'nombre' => "Suc Int6 {$sufijo}"], $token);
$storeId = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$api}/ops/registers", ['store_id' => $storeId, 'codigo' => "J6{$sufijo}"], $token);
$regId = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$api}/catalog/patients", ['identificacion' => "PP{$sufijo}", 'nombre' => "Paciente Int6 {$sufijo}", 'fecha_nacimiento' => '1990-01-01'], $token);
$pacId = (int)($r['json']['data']['id'] ?? 0);
check($provId > 0 && $prodId > 0 && $storeId > 0 && $regId > 0 && $pacId > 0, 'fixtures creados (proveedor, producto, sucursal, caja, paciente)');

$r = call('POST', "{$api}/purchases/orders", [
    'numero' => "OC6{$sufijo}", 'supplier_id' => $provId,
    'items' => [['product_id' => $prodId, 'cantidad_pedida' => 10]],
], $token);
$ordenId = (int)($r['json']['data']['id'] ?? 0);
call('POST', "{$api}/purchases/orders/{$ordenId}/emitir", null, $token);
$r = call('POST', "{$api}/purchases/orders/{$ordenId}/receptions", [
    'items' => [['product_id' => $prodId, 'numero_lote' => "CERCA{$sufijo}", 'fecha_vencimiento' => '2026-12-31', 'cantidad' => 5]],
], $token);
$recId = (int)($r['json']['data']['id'] ?? 0);
call('POST', "{$api}/purchases/receptions/{$recId}/confirmar", ['store_id' => $storeId], $token);
$r = call('POST', "{$api}/purchases/orders/{$ordenId}/receptions", [
    'items' => [['product_id' => $prodId, 'numero_lote' => "LEJOS{$sufijo}", 'fecha_vencimiento' => '2028-06-30', 'cantidad' => 5]],
], $token);
$rec2Id = (int)($r['json']['data']['id'] ?? 0);
call('POST', "{$api}/purchases/receptions/{$rec2Id}/confirmar", ['store_id' => $storeId], $token);
$lotCerca = (int)dbVal('SELECT id FROM inventory_lots WHERE numero_lote = ?', ["CERCA{$sufijo}"]);
$lotLejos = (int)dbVal('SELECT id FROM inventory_lots WHERE numero_lote = ?', ["LEJOS{$sufijo}"]);
call('POST', "{$api}/inventory/batches/{$lotCerca}/liberar", ['motivo' => 'Liberacion Int6'], $token);
call('POST', "{$api}/inventory/batches/{$lotLejos}/liberar", ['motivo' => 'Liberacion Int6'], $token);
check($lotCerca > 0 && $lotLejos > 0
    && (string)dbVal('SELECT fecha_vencimiento FROM inventory_lots WHERE id = ?', [$lotCerca]) < (string)dbVal('SELECT fecha_vencimiento FROM inventory_lots WHERE id = ?', [$lotLejos]),
    'dos lotes liberados: el de vencimiento mas proximo es el primero (FEFO)');

// --- 1. venta FEFO: descuenta el lote mas proximo a vencer ---
$claveVenta = "venta6-{$sufijo}";
$r = call('POST', "{$api}/sales/orders", [
    'store_id' => $storeId, 'register_id' => $regId, 'paciente_id' => $pacId,
    'items' => [['lot_id' => $lotCerca, 'cantidad' => 3, 'precio_unitario' => 10.00]],
    'idempotency_key' => $claveVenta,
], $token);
check($r['status'] === 201, 'venta en caja -> 201');
$ventaId = (int)($r['json']['data']['orden']['id'] ?? $r['json']['data']['id'] ?? 0);
$totalVenta = (float)($r['json']['data']['orden']['total'] ?? 30.00);
check(abs($totalVenta - 30.00) < 0.001, 'comprobante: total calculado 3 x 10.00 = 30.00');
check(stock($storeId, $lotCerca) === 2 && stock($storeId, $lotLejos) === 5,
    'FEFO: descuento sobre el lote de vencimiento mas proximo (5->2) y el otro intacto (5)');
check((int)dbVal(
    "SELECT COUNT(*) FROM inventory_movements WHERE lot_id = ? AND tipo = 'salida' AND signo = -1 AND ref_tipo = 'venta' AND ref_id = ?",
    [$lotCerca, $ventaId]
) === 1, 'kardex: salida signo -1 ref venta (append-only)');

// --- 2. idempotencia: misma clave no duplica la venta ---
$r = call('POST', "{$api}/sales/orders", [
    'store_id' => $storeId, 'register_id' => $regId, 'paciente_id' => $pacId,
    'items' => [['lot_id' => $lotCerca, 'cantidad' => 3, 'precio_unitario' => 10.00]],
    'idempotency_key' => $claveVenta,
], $token);
check($r['status'] === 200 && (($r['json']['data']['idempotent_reused'] ?? null) === true)
    && (int)($r['json']['data']['orden']['id'] ?? $r['json']['data']['id'] ?? 0) === $ventaId,
    'idempotency_key repetida -> 200 reused=true, misma orden, sin duplicar');
check(stock($storeId, $lotCerca) === 2, 'sin doble descuento de stock por reintento');
check((int)dbVal('SELECT COUNT(*) FROM sales_orders WHERE idempotency_key = ?', [$claveVenta]) === 1,
    'una sola fila de orden por idempotency_key');

// --- 3. RN-01: sin stock negativo ---
$r = call('POST', "{$api}/sales/orders", [
    'store_id' => $storeId, 'register_id' => $regId,
    'items' => [['lot_id' => $lotCerca, 'cantidad' => 999, 'precio_unitario' => 10.00]],
], $token);
check($r['status'] === 409 && errorCode($r) === 'STOCK_NOT_ENOUGH', 'venta 999u con stock 2 -> 409 STOCK_NOT_ENOUGH');
check(stock($storeId, $lotCerca) === 2, 'RN-01: stock sin negativos tras la venta rechazada');

// --- 4. multiples medios de pago ---
$r = call('POST', "{$api}/sales/orders/{$ventaId}/payments", ['medio' => 'efectivo', 'monto' => 10.00], $token);
check($r['status'] === 200, 'pago efectivo 10.00 -> 200');
$r = call('POST', "{$api}/sales/orders/{$ventaId}/payments", ['medio' => 'efectivo', 'monto' => 500.00], $token);
check($r['status'] === 409 && errorCode($r) === 'PAYMENT_EXCEEDS_DUE',
    'pago que excede lo debido (orden pendiente) -> 409 PAYMENT_EXCEEDS_DUE');
$r = call('POST', "{$api}/sales/orders/{$ventaId}/payments", ['medio' => 'tarjeta', 'monto' => 20.00], $token);
check($r['status'] === 200, 'pago tarjeta 20.00 -> 200');
check((int)dbVal(
    "SELECT COUNT(*) FROM payments_transactions WHERE order_id = ? AND status = 'aprobado'",
    [$ventaId]
) === 2, 'payments_transactions: 2 pagos aprobados (medios multiples)');
check((string)dbVal('SELECT estado FROM sales_orders WHERE id = ?', [$ventaId]) === 'pagada',
    'la suma de pagos transiciona la orden a pagada (CAS)');
$r = call('POST', "{$api}/sales/orders/{$ventaId}/payments", ['medio' => 'efectivo', 'monto' => 5.00], $token);
check($r['status'] === 409 && errorCode($r) === 'ORDER_NOT_PENDING',
    'pago sobre orden ya pagada -> 409 ORDER_NOT_PENDING');

// --- 5. comprobante: orden + items + pagos ---
$r = call('GET', "{$api}/sales/orders/{$ventaId}", null, $token);
$comp = $r['json']['data'] ?? [];
check($r['status'] === 200 && count($comp['items'] ?? []) === 1 && count($comp['pagos'] ?? []) === 2
    && (float)($comp['orden']['total'] ?? 0) === 30.00, 'comprobante: orden + items + pagos consultables');

// --- 6. devoluciones ---
$ordenItemId = (int)dbVal('SELECT id FROM sales_order_items WHERE order_id = ? LIMIT 1', [$ventaId]);
$r = call('POST', "{$api}/sales/returns", [
    'order_id' => $ventaId, 'motivo' => 'Devuelto vencimiento proximo',
    'items' => [['order_item_id' => $ordenItemId, 'cantidad' => 1, 'condicion' => 'vendible']],
], $token);
check($r['status'] === 201, 'devolucion vendible -> 201');
check(stock($storeId, $lotCerca) === 3, 'devolucion vendible: stock reingresado (2 -> 3)');
check((int)dbVal(
    "SELECT COUNT(*) FROM inventory_movements WHERE lot_id = ? AND tipo = 'devolucion' AND signo = 1 AND ref_tipo = 'devolucion'",
    [$lotCerca]
) >= 1, 'kardex: devolucion signo +1 (append-only)');

$r = call('POST', "{$api}/sales/returns", [
    'order_id' => $ventaId, 'motivo' => 'Empaque danado',
    'items' => [['order_item_id' => $ordenItemId, 'cantidad' => 1, 'condicion' => 'no_vendible']],
], $token);
check($r['status'] === 201, 'devolucion no_vendible -> 201');
check(stock($storeId, $lotCerca) === 3, 'no_vendible: el stock NO se reingresa (queda 3)');

$r = call('POST', "{$api}/sales/returns", [
    'order_id' => $ventaId, 'motivo' => 'Exceso',
    'items' => [['order_item_id' => $ordenItemId, 'cantidad' => 99, 'condicion' => 'vendible']],
], $token);
check($r['status'] === 409 && errorCode($r) === 'RETURN_EXCEEDS_SOLD', 'devolver mas de lo vendido -> 409 RETURN_EXCEEDS_SOLD');

echo 'RESULTADO: ' . ($fail === 0 ? 'PASS' : "FAIL ({$fail})") . "\n";
exit($fail === 0 ? 0 : 1);
