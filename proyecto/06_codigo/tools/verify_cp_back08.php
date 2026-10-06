<?php
declare(strict_types=1);

/**
 * CP-BACK-08 - Motor de Ventas POS, Pagos y Devoluciones.
 *
 * RF-050 (medios de pago efectivo/tarjeta/otros), RF-060 (devoluciones con
 * reingreso/baja de lotes), RN-01 (stock por lote sin negativos), RN-09
 * (idempotencia), RN-10 (la orden original queda intacta).
 *
 * Requiere: servidor corriendo y seed previo con tools/init_admin.php
 * (ADMIN_USER / ADMIN_PASSWORD + permiso sales.manage).
 * Conexion directa a BD via .env (solo SELECT).
 *
 * Uso: php tools/verify_cp_back08.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_cp_back08.php <base-url>\n");
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
    echo ($ok ? 'PASS' : 'FAIL') . ": {$label}\n";
    if (!$ok) {
        $GLOBALS['fail']++;
    }
    return $ok;
}

/**
 * @param string|array|null $json array se serializa; string se envia crudo
 * @return array{status:int,json:?array,body:string}
 */
function call(string $method, string $url, string|array|null $json = null, ?string $token = null): array
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

// --- Conexion directa a BD (solo SELECT) ---
$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = $root . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
\App\Support\Env::load($root . '/.env');

/** @return list<array<string,mixed>> */
function dbRows(string $sql, array $params = []): array
{
    $stmt = \App\Support\Database::pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function dbVal(string $sql, array $params = []): mixed
{
    $rows = dbRows($sql, $params);
    if ($rows === []) {
        return null;
    }
    return array_values($rows[0])[0] ?? null;
}

/** @param callable():bool $fn */
function dbCheck(callable $fn, string $label): void
{
    try {
        $ok = $fn();
    } catch (\Throwable $e) {
        $ok = false;
        $label .= ' [BD: ' . $e->getMessage() . ']';
    }
    check((bool)$ok, $label);
}

function stockDe(int $storeId, int $lotId): int
{
    return (int)dbVal(
        'SELECT stock_available FROM inventory_stock WHERE store_id = ? AND lot_id = ?',
        [$storeId, $lotId]
    );
}

/** Cuerpo de recepcion (RF-031): lote + vencimiento + cantidad. */
function recvBody(string $key, int $productId, string $lote, int $cant, string $vence): array
{
    return [
        'idempotency_key' => $key,
        'items' => [[
            'product_id' => $productId,
            'numero_lote' => $lote,
            'fecha_vencimiento' => $vence,
            'cantidad' => $cant,
        ]],
    ];
}

$sufijo = substr(md5((string)random_int(0, PHP_INT_MAX)), 0, 8);
$authUrl = "{$base}/api/v1/auth";
$catUrl = "{$base}/api/v1/catalog";
$opsUrl = "{$base}/api/v1/ops";
$purUrl = "{$base}/api/v1/purchases";
$invUrl = "{$base}/api/v1/inventory";
$salesUrl = "{$base}/api/v1/sales";
$passFix = 'Fix!Pass123';

// ---------------------------------------------------------------- 1. 401
$r = call('GET', "{$salesUrl}/orders");
check($r['status'] === 401 && errorCode($r) === 'UNAUTHENTICATED', 'GET /sales/orders sin token -> 401 UNAUTHENTICATED');
$r = call('POST', "{$salesUrl}/orders", []);
check($r['status'] === 401, 'POST /sales/orders sin token -> 401');
$r = call('POST', "{$salesUrl}/orders/1/payments", []);
check($r['status'] === 401, 'POST /sales/orders/1/payments sin token -> 401');
$r = call('POST', "{$salesUrl}/orders/1/anular", null);
check($r['status'] === 401, 'POST /sales/orders/1/anular sin token -> 401');
$r = call('GET', "{$salesUrl}/returns");
check($r['status'] === 401, 'GET /sales/returns sin token -> 401');
$r = call('POST', "{$salesUrl}/returns", []);
check($r['status'] === 401, 'POST /sales/returns sin token -> 401');

// -------------------------------------------------- 2. Login + RBAC
$r = call('POST', "{$authUrl}/login", ['usuario' => $adminUser, 'password' => $adminPass]);
check($r['status'] === 200, 'login admin -> 200');
$token = (string)($r['json']['data']['token'] ?? '');
check(in_array('sales.manage', $r['json']['data']['user']['permisos'] ?? [], true),
    'payload admin incluye sales.manage (seed)');

$r = call('POST', "{$authUrl}/users", ['usuario' => "pos_{$sufijo}", 'password' => $passFix, 'roles' => []], $token);
check($r['status'] === 201, 'crear usuario sin roles -> 201');
$r = call('POST', "{$authUrl}/login", ['usuario' => "pos_{$sufijo}", 'password' => $passFix]);
check($r['status'] === 200, 'login usuario sin roles -> 200');
$fixToken = (string)($r['json']['data']['token'] ?? '');

$r = call('GET', "{$salesUrl}/orders", null, $fixToken);
check($r['status'] === 200, 'GET /sales/orders con token sin permiso -> 200');
$r = call('GET', "{$salesUrl}/returns", null, $fixToken);
check($r['status'] === 200, 'GET /sales/returns con token sin permiso -> 200');

$r = call('POST', "{$salesUrl}/orders", [], $fixToken);
check($r['status'] === 403 && errorCode($r) === 'FORBIDDEN', 'POST /sales/orders sin permiso -> 403 FORBIDDEN');
$r = call('POST', "{$salesUrl}/orders/1/payments", [], $fixToken);
check($r['status'] === 403, 'POST /sales/orders/1/payments sin permiso -> 403');
$r = call('POST', "{$salesUrl}/orders/1/anular", null, $fixToken);
check($r['status'] === 403, 'POST /sales/orders/1/anular sin permiso -> 403');
$r = call('POST', "{$salesUrl}/returns", [], $fixToken);
check($r['status'] === 403, 'POST /sales/returns sin permiso -> 403');

// -------------------------------------------------- 3. Fixtures
$r = call('POST', "{$catUrl}/suppliers",
    ['identificacion' => "ID{$sufijo}", 'nombre' => "Distrib CP8 {$sufijo}", 'contacto' => 'cp8@test.local'], $token);
check($r['status'] === 201 && (int)($r['json']['data']['id'] ?? 0) > 0, 'POST supplier (fixture) -> 201 con id');
$provId = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$catUrl}/products", [
    'sku' => "SKU{$sufijo}",
    'nombre' => "Paracetamol CP8 {$sufijo}",
    'principio_activo' => 'Paracetamol',
    'presentacion' => 'Caja x 10 tabletas',
    'concentracion' => '500 mg',
    'condicion_venta' => 'libre',
], $token);
check($r['status'] === 201 && (int)($r['json']['data']['id'] ?? 0) > 0, 'POST product (fixture) -> 201 con id');
$prodId = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$opsUrl}/stores", ['codigo' => "PS{$sufijo}", 'nombre' => "Suc POS CP8 {$sufijo}"], $token);
check($r['status'] === 201 && (int)($r['json']['data']['id'] ?? 0) > 0, 'POST store -> 201 con id');
$storeId = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$opsUrl}/stores", ['codigo' => "PZ{$sufijo}", 'nombre' => "Suc Otra CP8 {$sufijo}"], $token);
$storeIdOtra = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$opsUrl}/registers", ['store_id' => $storeId, 'codigo' => "CJ{$sufijo}", 'nombre' => 'Caja 1'], $token);
check($r['status'] === 201 && (int)($r['json']['data']['id'] ?? 0) > 0, 'POST register en store -> 201 con id');
$regId = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$opsUrl}/registers", ['store_id' => $storeIdOtra, 'codigo' => "CY{$sufijo}", 'nombre' => 'Caja otra'], $token);
$regOtra = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$purUrl}/orders", [
    'numero' => "OC{$sufijo}", 'supplier_id' => $provId,
    'items' => [['product_id' => $prodId, 'cantidad_pedida' => 15]],
], $token);
check($r['status'] === 201, 'POST orden de compra (fixture) -> 201');
$idOrd = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$purUrl}/orders/{$idOrd}/emitir", null, $token);
check($r['status'] === 200 && ($r['json']['data']['estado'] ?? '') === 'emitida', 'emitir OC -> 200 emitida');

$lote1 = "P8{$sufijo}A";
$lote2 = "P8{$sufijo}B";
$lote3 = "P8{$sufijo}C";

$r = call('POST', "{$purUrl}/orders/{$idOrd}/receptions", recvBody("{$sufijo}p1", $prodId, $lote1, 6, '2027-12-31'), $token);
check($r['status'] === 201, 'POST recepcion lot1 (6 u) -> 201');
$rec1 = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$purUrl}/receptions/{$rec1}/confirmar", ['store_id' => $storeId], $token);
check($r['status'] === 200, 'confirmar recepcion lot1 -> 200');
$lot1 = (int)($r['json']['data']['items'][0]['lot_id'] ?? 0);
check($lot1 > 0, 'lot1 id asignado (>0)');

$r = call('POST', "{$purUrl}/orders/{$idOrd}/receptions", recvBody("{$sufijo}p2", $prodId, $lote2, 4, '2027-06-30'), $token);
check($r['status'] === 201, 'POST recepcion lot2 (4 u) -> 201');
$rec2 = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$purUrl}/receptions/{$rec2}/confirmar", ['store_id' => $storeId], $token);
check($r['status'] === 200, 'confirmar recepcion lot2 -> 200');
$lot2 = (int)($r['json']['data']['items'][0]['lot_id'] ?? 0);
check($lot2 > 0, 'lot2 id asignado (>0)');

$r = call('POST', "{$purUrl}/orders/{$idOrd}/receptions", recvBody("{$sufijo}p3", $prodId, $lote3, 5, '2020-01-01'), $token);
check($r['status'] === 201, 'POST recepcion lot3 vencido (5 u, 2020-01-01) -> 201');
$rec3 = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$purUrl}/receptions/{$rec3}/confirmar", ['store_id' => $storeId], $token);
check($r['status'] === 200, 'confirmar recepcion lot3 -> 200');
$lot3 = (int)($r['json']['data']['items'][0]['lot_id'] ?? 0);
check($lot3 > 0, 'lot3 id asignado (>0)');

$r = call('POST', "{$invUrl}/batches/{$lot1}/liberar", ['motivo' => 'Liberacion CP8'], $token);
check($r['status'] === 200 && ($r['json']['data']['estado'] ?? '') === 'liberado', 'liberar lot1 -> 200 liberado');
$r = call('POST', "{$invUrl}/batches/{$lot3}/liberar", ['motivo' => 'Liberacion CP8 (vencido)'], $token);
check($r['status'] === 200 && ($r['json']['data']['estado'] ?? '') === 'liberado', 'liberar lot3 (vencido) -> 200 liberado');

dbCheck(fn(): bool => stockDe($storeId, $lot1) === 6 && stockDe($storeId, $lot2) === 4 && stockDe($storeId, $lot3) === 5,
    'BD: stock inicial lot1=6 lot2=4 lot3=5');

// --------------------------------------------- 4. Validaciones 400
$r = call('POST', "{$salesUrl}/orders", null, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', "POST order sin cuerpo -> 400 VALIDATION_ERROR");
$r = call('POST', "{$salesUrl}/orders", '{"store_id":', $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'POST order cuerpo malformado -> 400');
$r = call('POST', "{$salesUrl}/orders", ['register_id' => $regId, 'items' => [['lot_id' => $lot1, 'cantidad' => 1, 'precio_unitario' => 1.0]]], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'POST order sin store_id -> 400');
$r = call('POST', "{$salesUrl}/orders", ['store_id' => $storeId, 'items' => [['lot_id' => $lot1, 'cantidad' => 1, 'precio_unitario' => 1.0]]], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'POST order sin register_id -> 400');
$r = call('POST', "{$salesUrl}/orders", ['store_id' => $storeId, 'register_id' => $regId, 'items' => []], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'POST order items vacio -> 400');
$r = call('POST', "{$salesUrl}/orders", ['store_id' => $storeId, 'register_id' => $regId,
    'items' => [['lot_id' => $lot1, 'cantidad' => 1, 'precio_unitario' => 1.0], ['lot_id' => $lot1, 'cantidad' => 1, 'precio_unitario' => 1.0]]], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'POST order lote repetido en items -> 400');
$r = call('POST', "{$salesUrl}/orders", ['store_id' => $storeId, 'register_id' => $regId,
    'items' => [['lot_id' => $lot1, 'cantidad' => 0, 'precio_unitario' => 1.0]]], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'POST order cantidad 0 -> 400');
$r = call('POST', "{$salesUrl}/orders", ['store_id' => $storeId, 'register_id' => $regId,
    'items' => [['lot_id' => $lot1, 'cantidad' => 1, 'precio_unitario' => -1.0]]], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'POST order precio negativo -> 400');
$r = call('POST', "{$salesUrl}/orders", ['store_id' => $storeId, 'register_id' => $regId,
    'items' => [['lot_id' => $lot1, 'cantidad' => 1, 'precio_unitario' => 1.0]], 'idempotency_key' => str_repeat('k', 256)], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'POST order idempotency_key > 255 -> 400');
$r = call('POST', "{$salesUrl}/orders", ['store_id' => $storeId, 'register_id' => $regId, 'paciente_id' => 'abc',
    'items' => [['lot_id' => $lot1, 'cantidad' => 1, 'precio_unitario' => 1.0]]], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'POST order paciente_id no entero -> 400');

// ------------------------------------------- 5. Referencias 404/409
$body = ['store_id' => 99999999, 'register_id' => $regId, 'items' => [['lot_id' => $lot1, 'cantidad' => 1, 'precio_unitario' => 1.0]]];
$r = call('POST', "{$salesUrl}/orders", $body, $token);
check($r['status'] === 404 && errorCode($r) === 'STORE_NOT_FOUND', 'POST order store inexistente -> 404 STORE_NOT_FOUND');

$body = ['store_id' => $storeId, 'register_id' => $regOtra, 'items' => [['lot_id' => $lot1, 'cantidad' => 1, 'precio_unitario' => 1.0]]];
$r = call('POST', "{$salesUrl}/orders", $body, $token);
check($r['status'] === 409 && errorCode($r) === 'REGISTER_NOT_IN_STORE', 'POST order caja de otra sucursal -> 409 REGISTER_NOT_IN_STORE');

$body = ['store_id' => $storeId, 'register_id' => $regId, 'paciente_id' => 99999999,
    'items' => [['lot_id' => $lot1, 'cantidad' => 1, 'precio_unitario' => 1.0]]];
$r = call('POST', "{$salesUrl}/orders", $body, $token);
check($r['status'] === 404 && errorCode($r) === 'PATIENT_NOT_FOUND', 'POST order paciente inexistente -> 404 PATIENT_NOT_FOUND');

$body = ['store_id' => $storeId, 'register_id' => $regId, 'items' => [['lot_id' => 99999999, 'cantidad' => 1, 'precio_unitario' => 1.0]]];
$r = call('POST', "{$salesUrl}/orders", $body, $token);
check($r['status'] === 404 && errorCode($r) === 'LOT_NOT_FOUND', 'POST order lote inexistente -> 404 LOT_NOT_FOUND');

$body = ['store_id' => $storeId, 'register_id' => $regId, 'items' => [['lot_id' => $lot2, 'cantidad' => 1, 'precio_unitario' => 1.0]]];
$r = call('POST', "{$salesUrl}/orders", $body, $token);
check($r['status'] === 409 && errorCode($r) === 'LOT_NOT_RELEASED', 'POST order lote en cuarentena -> 409 LOT_NOT_RELEASED');

$body = ['store_id' => $storeId, 'register_id' => $regId, 'items' => [['lot_id' => $lot3, 'cantidad' => 1, 'precio_unitario' => 1.0]]];
$r = call('POST', "{$salesUrl}/orders", $body, $token);
check($r['status'] === 409 && errorCode($r) === 'LOT_EXPIRED', 'POST order lote vencido -> 409 LOT_EXPIRED');

$r = call('POST', "{$invUrl}/batches/{$lot2}/liberar", ['motivo' => 'Liberacion CP8'], $token);
check($r['status'] === 200, 'liberar lot2 -> 200 (prepara venta multi-item)');

// --------------------------------------- 6. Venta feliz + idempotencia
$key1 = "ord-{$sufijo}";
$body = [
    'store_id' => $storeId,
    'register_id' => $regId,
    'items' => [
        ['lot_id' => $lot1, 'cantidad' => 2, 'precio_unitario' => 10.50],
        ['lot_id' => $lot2, 'cantidad' => 3, 'precio_unitario' => 2.00],
    ],
    'idempotency_key' => $key1,
];
$r = call('POST', "{$salesUrl}/orders", $body, $token);
check($r['status'] === 201 && ($r['json']['success'] ?? null) === true, 'POST order -> 201 success=true');
$orderId = (int)($r['json']['data']['orden']['id'] ?? 0);
check($orderId > 0, 'order id > 0');
check(($r['json']['data']['idempotent_reused'] ?? null) === false, 'idempotent_reused=false en creacion');
check(abs((float)($r['json']['data']['orden']['total'] ?? 0) - 27.00) < 0.001, 'total = 2*10.50 + 3*2.00 = 27.00');
check(($r['json']['data']['orden']['estado'] ?? '') === 'pendiente', 'estado inicial = pendiente');
check(count($r['json']['data']['items'] ?? []) === 2, '2 items en la respuesta');

dbCheck(fn(): bool => stockDe($storeId, $lot1) === 4, 'BD: stock lot1 6 -> 4 tras la venta');
dbCheck(fn(): bool => stockDe($storeId, $lot2) === 1, 'BD: stock lot2 4 -> 1 tras la venta');
dbCheck(fn(): bool => (int)dbVal(
        "SELECT COUNT(*) FROM inventory_movements
          WHERE ref_tipo = 'venta' AND ref_id = ? AND tipo = 'salida' AND signo = -1", [$orderId]) === 2,
    'BD: 2 movimientos de salida ref_tipo=venta (kardex)');

$r = call('POST', "{$salesUrl}/orders", $body, $token);
check($r['status'] === 200 && ($r['json']['data']['idempotent_reused'] ?? null) === true,
    'reintento con misma idempotency_key -> 200 idempotent_reused=true');
check((int)($r['json']['data']['orden']['id'] ?? 0) === $orderId, 'reintento devuelve la MISMA orden');
dbCheck(fn(): bool => (int)dbVal(
        'SELECT COUNT(*) FROM sales_orders WHERE idempotency_key = ?', [$key1]) === 1,
    'BD: una sola orden para la clave (sin duplicado)');
dbCheck(fn(): bool => stockDe($storeId, $lot1) === 4, 'BD: stock lot1 sin doble descuento (4)');

// ---------------------------------------------- 7. Pagos (RF-050)
$r = call('POST', "{$salesUrl}/returns", ['order_id' => $orderId, 'motivo' => 'x',
    'items' => [['order_item_id' => 1, 'cantidad' => 1, 'condicion' => 'vendible']]], $token);
check($r['status'] === 409 && errorCode($r) === 'ORDER_NOT_PAID', 'devolucion sobre orden pendiente -> 409 ORDER_NOT_PAID');

$r = call('POST', "{$salesUrl}/orders/{$orderId}/payments", ['medio' => 'efectivo', 'monto' => 999.00], $token);
check($r['status'] === 409 && errorCode($r) === 'PAYMENT_EXCEEDS_DUE', 'pago que excede el saldo -> 409 PAYMENT_EXCEEDS_DUE');

$keyPag1 = "pag-{$sufijo}-1";
$r = call('POST', "{$salesUrl}/orders/{$orderId}/payments",
    ['medio' => 'efectivo', 'monto' => 10.00, 'idempotency_key' => $keyPag1], $token);
check($r['status'] === 200 && ($r['json']['data']['idempotent_reused'] ?? null) === false, 'pago parcial 10.00 -> 200');
check(($r['json']['data']['orden']['estado'] ?? '') === 'pendiente', 'orden sigue pendiente tras pago parcial');

$r = call('POST', "{$salesUrl}/orders/{$orderId}/payments",
    ['medio' => 'tarjeta', 'monto' => 17.00, 'idempotency_key' => "pag-{$sufijo}-2"], $token);
check($r['status'] === 200, 'pago por el saldo 17.00 -> 200');
check(($r['json']['data']['orden']['estado'] ?? '') === 'pagada', 'orden pasa a pagada al completar (RF-050)');

$r = call('POST', "{$salesUrl}/orders/{$orderId}/payments", ['medio' => 'otros', 'monto' => 1.00], $token);
check($r['status'] === 409 && errorCode($r) === 'ORDER_NOT_PENDING', 'pago sobre orden pagada -> 409 ORDER_NOT_PENDING');

$r = call('POST', "{$salesUrl}/orders/{$orderId}/payments",
    ['medio' => 'efectivo', 'monto' => 5.00, 'idempotency_key' => $keyPag1], $token);
check($r['status'] === 200 && ($r['json']['data']['idempotent_reused'] ?? null) === true, 'reintento de pago -> 200 idempotent_reused=true');
check((int)($r['json']['data']['pago']['id'] ?? 0) > 0, 'reintento devuelve el MISMO pago');
dbCheck(fn(): bool => (int)dbVal(
        'SELECT COUNT(*) FROM payments_transactions WHERE order_id = ?', [$orderId]) === 2,
    'BD: 2 pagos (sin doble cargo por el reintento)');

$r = call('POST', "{$salesUrl}/orders/{$orderId}/payments", ['medio' => 'bitcoin', 'monto' => 1.00], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'pago medio invalido -> 400');
$r = call('POST', "{$salesUrl}/orders/{$orderId}/payments", ['medio' => 'efectivo', 'monto' => 0], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'pago monto 0 -> 400');
$r = call('POST', "{$salesUrl}/orders/{$orderId}/payments", ['medio' => 'efectivo', 'monto' => 'abc'], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'pago monto no numerico -> 400');
$r = call('POST', "{$salesUrl}/orders/99999999/payments", ['medio' => 'efectivo', 'monto' => 1.00], $token);
check($r['status'] === 404 && errorCode($r) === 'ORDER_NOT_FOUND', 'pago sobre orden inexistente -> 404');

// ------------------------------------------- 8. Devoluciones (RF-060)
$itemsOrd = dbRows('SELECT id, lot_id FROM sales_order_items WHERE order_id = ? ORDER BY id', [$orderId]);
$itemL1 = (int)($itemsOrd[0]['id'] ?? 0);
$itemL2 = (int)($itemsOrd[1]['id'] ?? 0);
check($itemL1 > 0 && $itemL2 > 0, 'items de la orden identificados en BD');

$r = call('POST', "{$salesUrl}/returns", ['order_id' => $orderId, 'items' => [['order_item_id' => $itemL1, 'cantidad' => 1, 'condicion' => 'vendible']]], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'devolucion sin motivo -> 400');
$r = call('POST', "{$salesUrl}/returns", ['order_id' => $orderId, 'motivo' => 'x', 'items' => []], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'devolucion items vacio -> 400');
$r = call('POST', "{$salesUrl}/returns", ['order_id' => $orderId, 'motivo' => 'x',
    'items' => [['order_item_id' => $itemL1, 'cantidad' => 1, 'condicion' => 'rota']]], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'devolucion condicion invalida -> 400');
$r = call('POST', "{$salesUrl}/returns", ['order_id' => 0, 'motivo' => 'x',
    'items' => [['order_item_id' => $itemL1, 'cantidad' => 1, 'condicion' => 'vendible']]], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'devolucion order_id 0 -> 400');

$r = call('POST', "{$salesUrl}/returns", ['order_id' => $orderId, 'motivo' => 'fuera de alcance',
    'items' => [['order_item_id' => 99999999, 'cantidad' => 1, 'condicion' => 'vendible']]], $token);
check($r['status'] === 409 && errorCode($r) === 'ITEM_NOT_IN_ORDER', 'devolucion item ajeno -> 409 ITEM_NOT_IN_ORDER');

$r = call('POST', "{$salesUrl}/returns", ['order_id' => $orderId, 'motivo' => 'exceso',
    'items' => [['order_item_id' => $itemL1, 'cantidad' => 99, 'condicion' => 'vendible']]], $token);
check($r['status'] === 409 && errorCode($r) === 'RETURN_EXCEEDS_SOLD', 'devolucion supera lo vendido -> 409 RETURN_EXCEEDS_SOLD');

$r = call('POST', "{$salesUrl}/returns", ['order_id' => $orderId, 'motivo' => 'cliente se arrepiente',
    'items' => [['order_item_id' => $itemL1, 'cantidad' => 1, 'condicion' => 'vendible']]], $token);
check($r['status'] === 201 && ($r['json']['success'] ?? null) === true, 'devolucion vendible 1 u -> 201');
$devId = (int)($r['json']['data']['devolucion']['id'] ?? 0);
check(abs((float)($r['json']['data']['devolucion']['monto'] ?? 0) - 10.50) < 0.001, 'monto calculado en servidor = 10.50');
check(($r['json']['data']['devolucion']['estado_evaluacion'] ?? '') === 'reingresado', 'estado_evaluacion = reingresado');
dbCheck(fn(): bool => stockDe($storeId, $lot1) === 5, 'BD: stock lot1 4 -> 5 (reingreso vendible)');
dbCheck(fn(): bool => (int)dbVal(
        "SELECT COUNT(*) FROM inventory_movements
          WHERE ref_tipo = 'devolucion' AND ref_id = ? AND tipo = 'devolucion' AND signo = 1", [$devId]) === 1,
    'BD: movimiento de devolucion signo +1 en kardex');

$r = call('POST', "{$salesUrl}/returns", ['order_id' => $orderId, 'motivo' => 'producto danado',
    'items' => [['order_item_id' => $itemL1, 'cantidad' => 1, 'condicion' => 'no_vendible']]], $token);
check($r['status'] === 201, 'devolucion no_vendible 1 u -> 201');
check(($r['json']['data']['devolucion']['estado_evaluacion'] ?? '') === 'descartado', 'estado_evaluacion = descartado');
dbCheck(fn(): bool => stockDe($storeId, $lot1) === 5, 'BD: stock lot1 sin cambio con no_vendible (baja sin reingreso)');

$r = call('POST', "{$salesUrl}/returns", ['order_id' => $orderId, 'motivo' => 'doble submit',
    'items' => [['order_item_id' => $itemL1, 'cantidad' => 1, 'condicion' => 'vendible']]], $token);
check($r['status'] === 409 && errorCode($r) === 'RETURN_EXCEEDS_SOLD', 'devolucion de item ya devuelto -> 409 RETURN_EXCEEDS_SOLD');

$r = call('GET', "{$salesUrl}/returns/{$devId}", null, $token);
check($r['status'] === 200 && count($r['json']['data']['items'] ?? []) === 1, "GET /returns/{id} -> 200 con 1 item");
$r = call('GET', "{$salesUrl}/returns/99999999", null, $token);
check($r['status'] === 404 && errorCode($r) === 'RETURN_NOT_FOUND', 'GET devolucion inexistente -> 404');

// --------------------------------------------------- 9. Anular (RN-10)
$r = call('POST', "{$salesUrl}/orders/{$orderId}/anular", null, $token);
check($r['status'] === 200 && ($r['json']['data']['orden']['estado'] ?? '') === 'anulada', 'anular orden pagada -> 200 anulada');
dbCheck(fn(): bool => stockDe($storeId, $lot1) === 7, 'BD: stock lot1 5 -> 7 (repone las 2 unidades)');
dbCheck(fn(): bool => stockDe($storeId, $lot2) === 4, 'BD: stock lot2 1 -> 4 (repone las 3 unidades)');
dbCheck(fn(): bool => (int)dbVal(
        "SELECT COUNT(*) FROM inventory_movements
          WHERE ref_tipo = 'venta' AND ref_id = ? AND tipo = 'compensatorio' AND signo = 1", [$orderId]) === 2,
    'BD: movimientos compensatorios del kardex (auditoria inalterable)');

$r = call('POST', "{$salesUrl}/orders/{$orderId}/anular", null, $token);
check($r['status'] === 409 && errorCode($r) === 'ORDER_NOT_CANCELLABLE', 'anular dos veces -> 409 ORDER_NOT_CANCELLABLE');
$r = call('POST', "{$salesUrl}/orders/99999999/anular", null, $token);
check($r['status'] === 404 && errorCode($r) === 'ORDER_NOT_FOUND', 'anular orden inexistente -> 404');
$r = call('POST', "{$salesUrl}/orders/abc/anular", null, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'anular id no numerico -> 400');

// ------------------------------------------------- 10. Listados y GET
$r = call('GET', "{$salesUrl}/orders", null, $token);
check($r['status'] === 200 && is_array($r['json']['data'] ?? null), 'GET /sales/orders -> 200');
check(isset($r['json']['meta']['total'], $r['json']['meta']['page'], $r['json']['meta']['limit']), 'meta con total/page/limit');
$r = call('GET', "{$salesUrl}/orders?estado=xxx", null, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'GET ?estado invalido -> 400');
$r = call('GET', "{$salesUrl}/orders?store_id=abc", null, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'GET ?store_id no numerico -> 400');
$r = call('GET', "{$salesUrl}/orders?store_id={$storeId}&estado=anulada", null, $token);
check($r['status'] === 200 && (int)($r['json']['meta']['total'] ?? 0) >= 1, 'GET filtros store_id+estado -> 200');
$r = call('GET', "{$salesUrl}/orders?limit=1&page=1", null, $token);
check($r['status'] === 200 && count($r['json']['data'] ?? []) === 1 && (int)($r['json']['meta']['limit'] ?? 0) === 1, 'paginacion limit=1 -> 1 elemento');

$r = call('GET', "{$salesUrl}/orders/{$orderId}", null, $token);
check($r['status'] === 200 && (int)($r['json']['data']['orden']['id'] ?? 0) === $orderId, 'GET /orders/{id} -> 200');
check(count($r['json']['data']['items'] ?? []) === 2 && count($r['json']['data']['pagos'] ?? []) === 2, 'GET {id} incluye 2 items y 2 pagos');
$r = call('GET', "{$salesUrl}/orders/99999999", null, $token);
check($r['status'] === 404 && errorCode($r) === 'ORDER_NOT_FOUND', 'GET orden inexistente -> 404');
$r = call('GET', "{$salesUrl}/orders/abc", null, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'GET orden id no numerico -> 400');

$r = call('GET', "{$salesUrl}/returns?order_id={$orderId}", null, $token);
check($r['status'] === 200 && (int)($r['json']['meta']['total'] ?? 0) === 2, 'GET /returns?order_id -> 2 devoluciones');
$r = call('GET', "{$salesUrl}/returns?store_id={$storeId}", null, $token);
check($r['status'] === 200, 'GET /returns?store_id -> 200');
$r = call('GET', "{$salesUrl}/returns?order_id=abc", null, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'GET /returns?order_id invalido -> 400');

// ------------------------------------------------------ 11. Limpieza
$r = call('DELETE', "{$catUrl}/products/{$prodId}", null, $token);
check($r['status'] === 204, 'DELETE product fixture -> 204 (borrado logico)');
$r = call('DELETE', "{$opsUrl}/stores/{$storeId}", null, $token);
check($r['status'] === 204, 'DELETE store fixture -> 204');
$r = call('DELETE', "{$opsUrl}/stores/{$storeIdOtra}", null, $token);
check($r['status'] === 204, 'DELETE store auxiliar -> 204');
$r = call('DELETE', "{$catUrl}/suppliers/{$provId}", null, $token);
check($r['status'] === 204, 'DELETE supplier fixture -> 204');

echo "\nRESULTADO: " . ($fail === 0 ? 'PASS' : "FAIL ({$fail})") . "\n";
exit($fail === 0 ? 0 : 1);
