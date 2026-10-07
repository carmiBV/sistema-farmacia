<?php
declare(strict_types=1);

/**
 * CP-BACK-09 - Libro Oficial de Medicamentos Controlados y Saldos.
 *
 * RF-045 (asiento en transferencias de controlados), RF-046 (doble autorizacion
 * de ajustes), RF-055 (saldo permanente por producto y sucursal), RF-071
 * (reportes por periodo), RN-05/RN-06, RNF-021 (misma transaccion),
 * RNF-024 (conciliacion saldo vs asientos), RNF-046 (append-only).
 *
 * Requiere: servidor corriendo y seed previo con tools/init_admin.php
 * (ADMIN_USER / ADMIN_PASSWORD + permiso control.manage).
 * Conexion directa a BD via .env (solo SELECT).
 *
 * Uso: php tools/verify_cp_back09.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_cp_back09.php <base-url>\n");
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
 * @param string|array|null $json
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

function dbVal(string $sql, array $params = []): mixed
{
    $stmt = \App\Support\Database::pdo()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    return $rows === [] ? null : array_values($rows[0])[0] ?? null;
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

/** @return array<string,mixed>|null */
function asientoDe(int $productId, string $tipo): ?array
{
    $stmt = \App\Support\Database::pdo()->prepare(
        'SELECT * FROM ctrl_ledger_entries WHERE product_id = ? AND tipo = ? ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$productId, $tipo]);
    $r = $stmt->fetch();
    return $r === false ? null : $r;
}

function saldoDe(int $storeId, int $productId): int
{
    return (int)dbVal(
        'SELECT saldo FROM ctrl_balances WHERE store_id = ? AND product_id = ?',
        [$storeId, $productId]
    );
}

function recvBody(string $key, array $items): array
{
    return ['idempotency_key' => $key, 'items' => $items];
}

$sufijo = substr(md5((string)random_int(0, PHP_INT_MAX)), 0, 8);
$authUrl = "{$base}/api/v1/auth";
$catUrl = "{$base}/api/v1/catalog";
$opsUrl = "{$base}/api/v1/ops";
$purUrl = "{$base}/api/v1/purchases";
$invUrl = "{$base}/api/v1/inventory";
$salesUrl = "{$base}/api/v1/sales";
$ctrlUrl = "{$base}/api/v1/control";
$passFix = 'Fix!Pass123';

// ---------------------------------------------------------------- 1. 401
$r = call('GET', "{$ctrlUrl}/ledger");
check($r['status'] === 401 && errorCode($r) === 'UNAUTHENTICATED', 'GET /control/ledger sin token -> 401 UNAUTHENTICATED');
$r = call('GET', "{$ctrlUrl}/balances");
check($r['status'] === 401, 'GET /control/balances sin token -> 401');
$r = call('GET', "{$ctrlUrl}/reconciliation");
check($r['status'] === 401, 'GET /control/reconciliation sin token -> 401');
$r = call('POST', "{$ctrlUrl}/adjustments", []);
check($r['status'] === 401, 'POST /control/adjustments sin token -> 401');

// -------------------------------------------------- 2. Login + RBAC
$r = call('POST', "{$authUrl}/login", ['usuario' => $adminUser, 'password' => $adminPass]);
check($r['status'] === 200, 'login admin -> 200');
$token = (string)($r['json']['data']['token'] ?? '');
check(in_array('control.manage', $r['json']['data']['user']['permisos'] ?? [], true),
    'payload admin incluye control.manage (seed)');

$r = call('POST', "{$authUrl}/users", ['usuario' => "ctl_{$sufijo}", 'password' => $passFix, 'roles' => []], $token);
check($r['status'] === 201, 'crear usuario sin roles -> 201');
$r = call('POST', "{$authUrl}/login", ['usuario' => "ctl_{$sufijo}", 'password' => $passFix]);
check($r['status'] === 200, 'login usuario sin roles -> 200');
$fixToken = (string)($r['json']['data']['token'] ?? '');

$r = call('GET', "{$ctrlUrl}/ledger", null, $fixToken);
check($r['status'] === 200, 'GET /control/ledger con token sin permiso -> 200');
$r = call('GET', "{$ctrlUrl}/balances", null, $fixToken);
check($r['status'] === 200, 'GET /control/balances con token sin permiso -> 200');
$r = call('POST', "{$ctrlUrl}/adjustments", [], $fixToken);
check($r['status'] === 403 && errorCode($r) === 'FORBIDDEN', 'POST /control/adjustments sin permiso -> 403 FORBIDDEN');

// ------------------------------------------------------- 3. Fixtures
$r = call('POST', "{$catUrl}/suppliers",
    ['identificacion' => "ID{$sufijo}", 'nombre' => "Distrib CP9 {$sufijo}", 'contacto' => 'cp9@test.local'], $token);
check($r['status'] === 201, 'POST supplier (fixture) -> 201');
$provId = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$catUrl}/products", [
    'sku' => "CTL{$sufijo}",
    'nombre' => "Clonazepam CP9 {$sufijo}",
    'principio_activo' => 'Clonazepam',
    'presentacion' => 'Caja x 30 tabletas',
    'concentracion' => '2 mg',
    'condicion_venta' => 'controlado',
], $token);
check($r['status'] === 201 && (int)($r['json']['data']['id'] ?? 0) > 0,
    'POST product CONTROLADO -> 201');
$prodCtrl = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$catUrl}/products", [
    'sku' => "LIB{$sufijo}",
    'nombre' => "Paracetamol CP9 {$sufijo}",
    'principio_activo' => 'Paracetamol',
    'presentacion' => 'Caja x 10 tabletas',
    'concentracion' => '500 mg',
    'condicion_venta' => 'libre',
], $token);
check($r['status'] === 201, 'POST product libre (control negativo) -> 201');
$prodLibre = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$opsUrl}/stores", ['codigo' => "CS{$sufijo}", 'nombre' => "Suc Ctrl CP9 {$sufijo}"], $token);
check($r['status'] === 201, 'POST store -> 201');
$storeId = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$opsUrl}/registers", ['store_id' => $storeId, 'codigo' => "CC{$sufijo}", 'nombre' => 'Caja ctrl'], $token);
check($r['status'] === 201, 'POST register -> 201');
$regId = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$purUrl}/orders", [
    'numero' => "OC{$sufijo}", 'supplier_id' => $provId,
    'items' => [
        ['product_id' => $prodCtrl, 'cantidad_pedida' => 10],
        ['product_id' => $prodLibre, 'cantidad_pedida' => 5],
    ],
], $token);
check($r['status'] === 201, 'POST orden de compra (fixture) -> 201');
$idOrd = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$purUrl}/orders/{$idOrd}/emitir", null, $token);
check($r['status'] === 200, 'emitir OC -> 200');

$loteCtrl = "C9{$sufijo}A";
$loteLibre = "C9{$sufijo}B";
$r = call('POST', "{$purUrl}/orders/{$idOrd}/receptions", recvBody("{$sufijo}r1", [
    ['product_id' => $prodCtrl, 'numero_lote' => $loteCtrl, 'fecha_vencimiento' => '2027-12-31', 'cantidad' => 10],
    ['product_id' => $prodLibre, 'numero_lote' => $loteLibre, 'fecha_vencimiento' => '2027-12-31', 'cantidad' => 5],
]), $token);
check($r['status'] === 201, 'POST recepcion (10 ctrl + 5 libre) -> 201');
$recId = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$purUrl}/receptions/{$recId}/confirmar", ['store_id' => $storeId], $token);
check($r['status'] === 200, 'confirmar recepcion -> 200');
$items = $r['json']['data']['items'] ?? [];
$lotCtrl = (int)($items[0]['lot_id'] ?? 0);
$lotLibre = (int)($items[1]['lot_id'] ?? 0);
check($lotCtrl > 0 && $lotLibre > 0, 'lotes creados (lotCtrl, lotLibre)');

$r = call('POST', "{$invUrl}/batches/{$lotCtrl}/liberar", ['motivo' => 'Liberacion CP9'], $token);
check($r['status'] === 200, 'liberar lotCtrl -> 200');
$r = call('POST', "{$invUrl}/batches/{$lotLibre}/liberar", ['motivo' => 'Liberacion CP9'], $token);
check($r['status'] === 200, 'liberar lotLibre -> 200');

// ------------------------------- 4. Asiento automatico de recepcion (RF-055)
dbCheck(fn(): bool => (int)dbVal(
        'SELECT COUNT(*) FROM ctrl_ledger_entries WHERE product_id = ? AND tipo = ? AND cantidad = 10 AND ref_tipo = ?',
        [$prodCtrl, 'entrada', 'recepcion']) === 1,
    'BD: asiento de ENTRADA (10 u, ref recepcion) en el libro del controlado');
dbCheck(fn(): bool => (int)dbVal(
        'SELECT saldo_resultante FROM ctrl_ledger_entries WHERE product_id = ? ORDER BY id DESC LIMIT 1',
        [$prodCtrl]) === 10,
    'BD: saldo_resultante = 10 tras la recepcion');
dbCheck(fn(): bool => saldoDe($storeId, $prodCtrl) === 10, 'BD: ctrl_balances.saldo = 10 (saldo permanente)');
dbCheck(fn(): bool => (int)dbVal(
        'SELECT COUNT(*) FROM ctrl_ledger_entries WHERE product_id = ?', [$prodLibre]) === 0,
    'BD: el producto libre NO genera asientos (alcance del libro)');

// --------------------------------------------------------- 5. Lecturas
$r = call('GET', "{$ctrlUrl}/ledger", null, $token);
check($r['status'] === 200 && (int)($r['json']['meta']['total'] ?? 0) >= 1, 'GET /control/ledger -> 200 con asientos');
$r = call('GET', "{$ctrlUrl}/ledger?product_id={$prodCtrl}", null, $token);
check($r['status'] === 200 && (int)($r['json']['meta']['total'] ?? 0) === 1, 'GET ?product_id -> 1 asiento');
$r = call('GET', "{$ctrlUrl}/ledger?tipo=entrada", null, $token);
check($r['status'] === 200 && (int)($r['json']['meta']['total'] ?? 0) >= 1, 'GET ?tipo=entrada -> 200');
$r = call('GET', "{$ctrlUrl}/ledger?tipo=xxx", null, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'GET ?tipo invalido -> 400');
$r = call('GET', "{$ctrlUrl}/ledger?ref_tipo=recepcion", null, $token);
check($r['status'] === 200, 'GET ?ref_tipo=recepcion -> 200');
$r = call('GET', "{$ctrlUrl}/ledger?desde=2026-01-01&hasta=2030-12-31", null, $token);
check($r['status'] === 200 && (int)($r['json']['meta']['total'] ?? 0) >= 1, 'GET filtro por periodo (RF-071) -> 200');
$r = call('GET', "{$ctrlUrl}/ledger?desde=2030-01-01&hasta=2026-01-01", null, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'GET desde > hasta -> 400');
$r = call('GET', "{$ctrlUrl}/ledger?desde=abc", null, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'GET ?desde invalido -> 400');
$r = call('GET', "{$ctrlUrl}/ledger?limit=1&page=1", null, $token);
check($r['status'] === 200 && count($r['json']['data'] ?? []) === 1, 'paginacion limit=1 -> 1 elemento');

$r = call('GET', "{$ctrlUrl}/balances?product_id={$prodCtrl}", null, $token);
$bal = $r['json']['data'][0] ?? [];
check($r['status'] === 200 && ($bal['saldo'] ?? null) === 10, 'GET /balances -> saldo = 10');
check(($bal['saldo_calculado'] ?? null) === 10 && ($bal['conciliado'] ?? null) === true,
    'GET /balances -> saldo_calculado = 10 y conciliado = true');

$r = call('GET', "{$ctrlUrl}/reconciliation", null, $token);
$rec = $r['json']['data'] ?? [];
check($r['status'] === 200 && ($rec['desbalanceados'] ?? -1) === 0, 'GET /reconciliation -> 0 desbalanceados (RNF-024)');
check(($rec['conciliados'] ?? 0) >= 1, 'GET /reconciliation -> conciliados >= 1');

$r = call('GET', "{$ctrlUrl}/ledger/99999999", null, $token);
check($r['status'] === 404 && errorCode($r) === 'LEDGER_ENTRY_NOT_FOUND', 'GET asiento inexistente -> 404');
$r = call('GET', "{$ctrlUrl}/ledger/abc", null, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'GET asiento id no numerico -> 400');

// ------------------------------------- 6. Ajustes con doble autorizacion
$r = call('POST', "{$ctrlUrl}/adjustments", null, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'ajuste sin cuerpo -> 400');
$r = call('POST', "{$ctrlUrl}/adjustments", ['product_id' => $prodCtrl, 'lot_id' => $lotCtrl, 'cantidad' => 1,
    'direccion' => 'baja', 'motivo' => 'x', 'autorizador_id' => 999], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'ajuste sin store_id -> 400');
$r = call('POST', "{$ctrlUrl}/adjustments", ['store_id' => $storeId, 'product_id' => $prodCtrl, 'lot_id' => $lotCtrl,
    'cantidad' => 1, 'direccion' => 'arriba', 'motivo' => 'x', 'autorizador_id' => 999], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'ajuste direccion invalida -> 400');
$r = call('POST', "{$ctrlUrl}/adjustments", ['store_id' => $storeId, 'product_id' => $prodCtrl, 'lot_id' => $lotCtrl,
    'cantidad' => 0, 'direccion' => 'baja', 'motivo' => 'x', 'autorizador_id' => 999], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'ajuste cantidad 0 -> 400');
$r = call('POST', "{$ctrlUrl}/adjustments", ['store_id' => $storeId, 'product_id' => $prodCtrl, 'lot_id' => $lotCtrl,
    'cantidad' => 1, 'direccion' => 'baja', 'motivo' => '  ', 'autorizador_id' => 999], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'ajuste motivo vacio -> 400');
$r = call('POST', "{$ctrlUrl}/adjustments", ['store_id' => $storeId, 'product_id' => $prodCtrl, 'lot_id' => $lotCtrl,
    'cantidad' => 1, 'direccion' => 'baja', 'motivo' => 'x'], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'ajuste sin autorizador_id -> 400');

$r = call('POST', "{$ctrlUrl}/adjustments", ['store_id' => $storeId, 'product_id' => 99999999, 'lot_id' => $lotCtrl,
    'cantidad' => 1, 'direccion' => 'baja', 'motivo' => 'x', 'autorizador_id' => 1], $token);
check($r['status'] === 404 && errorCode($r) === 'PRODUCT_NOT_FOUND', 'ajuste producto inexistente -> 404');
$r = call('POST', "{$ctrlUrl}/adjustments", ['store_id' => $storeId, 'product_id' => $prodLibre, 'lot_id' => $lotLibre,
    'cantidad' => 1, 'direccion' => 'baja', 'motivo' => 'x', 'autorizador_id' => 1], $token);
check($r['status'] === 409 && errorCode($r) === 'PRODUCT_NOT_CONTROLLED', 'ajuste de producto NO controlado -> 409');
$r = call('POST', "{$ctrlUrl}/adjustments", ['store_id' => $storeId, 'product_id' => $prodCtrl, 'lot_id' => 99999999,
    'cantidad' => 1, 'direccion' => 'baja', 'motivo' => 'x', 'autorizador_id' => 1], $token);
check($r['status'] === 404 && errorCode($r) === 'LOT_NOT_FOUND', 'ajuste lote inexistente -> 404');
$r = call('POST', "{$ctrlUrl}/adjustments", ['store_id' => $storeId, 'product_id' => $prodCtrl, 'lot_id' => $lotLibre,
    'cantidad' => 1, 'direccion' => 'baja', 'motivo' => 'x', 'autorizador_id' => 1], $token);
check($r['status'] === 409 && errorCode($r) === 'LOT_NOT_FOR_PRODUCT', 'ajuste lote de otro producto -> 409');

$myId = (int)dbVal('SELECT id FROM auth_users WHERE usuario = ?', [$adminUser]);
$r = call('POST', "{$ctrlUrl}/adjustments", ['store_id' => $storeId, 'product_id' => $prodCtrl, 'lot_id' => $lotCtrl,
    'cantidad' => 1, 'direccion' => 'baja', 'motivo' => 'x', 'autorizador_id' => $myId], $token);
check($r['status'] === 409 && errorCode($r) === 'DOUBLE_AUTH_REQUIRED',
    'ajuste con autorizador = proponente -> 409 DOUBLE_AUTH_REQUIRED (RF-046)');
$r = call('POST', "{$ctrlUrl}/adjustments", ['store_id' => $storeId, 'product_id' => $prodCtrl, 'lot_id' => $lotCtrl,
    'cantidad' => 1, 'direccion' => 'baja', 'motivo' => 'x', 'autorizador_id' => 99999999], $token);
check($r['status'] === 404 && errorCode($r) === 'USER_NOT_FOUND', 'ajuste con autorizador inexistente -> 404');

// autorizador valido: usuario ctl_* no sirve (rol vacio pero existe y esta activo)
$authId = (int)dbVal('SELECT id FROM auth_users WHERE usuario = ?', ["ctl_{$sufijo}"]);
check($authId > 0, 'autorizador alterno identificado');

// ponytail: autorizador distinto del proponente (en BD fresca admin es id 1;
// con autorizador == proponente el servicio responde DOUBLE_AUTH_REQUIRED antes de stock)
$r = call('POST', "{$ctrlUrl}/adjustments", ['store_id' => $storeId, 'product_id' => $prodCtrl, 'lot_id' => $lotCtrl,
    'cantidad' => 999, 'direccion' => 'baja', 'motivo' => 'x', 'autorizador_id' => $authId], $token);
check($r['status'] === 409 && errorCode($r) === 'STOCK_NOT_ENOUGH', 'ajuste baja que excede stock -> 409 STOCK_NOT_ENOUGH');

$r = call('POST', "{$ctrlUrl}/adjustments", ['store_id' => $storeId, 'product_id' => $prodCtrl, 'lot_id' => $lotCtrl,
    'cantidad' => 3, 'direccion' => 'baja', 'motivo' => 'Merma verificada por el quimico', 'autorizador_id' => $authId], $token);
check($r['status'] === 201 && ($r['json']['success'] ?? null) === true, 'ajuste de BAJA 3 u -> 201');
$asiento = $r['json']['data']['asiento'] ?? [];
check(($asiento['tipo'] ?? '') === 'ajuste', 'asiento generado con tipo = ajuste');
check((int)($asiento['cantidad'] ?? 0) === 3 && (int)($asiento['saldo_resultante'] ?? 0) === 7, 'asiento: cantidad 3, saldo_resultante 7');
check((int)($asiento['autorizador_id'] ?? 0) === $authId && (int)($asiento['autorizador_id'] ?? 0) !== (int)($asiento['usuario_id'] ?? 0),
    'asiento: autorizador != proponente (chk_cle_doble_autorizacion)');
check(($r['json']['data']['saldo'] ?? null) === 7, 'respuesta incluye saldo = 7');
dbCheck(fn(): bool => saldoDe($storeId, $prodCtrl) === 7, 'BD: ctrl_balances.saldo = 7 tras el ajuste de baja');
dbCheck(fn(): bool => (int)dbVal(
        'SELECT stock_available FROM inventory_stock WHERE store_id = ? AND lot_id = ?',
        [$storeId, $lotCtrl]) === 7,
    'BD: inventory_stock descontado 10 -> 7');
dbCheck(fn(): bool => (int)dbVal(
        "SELECT COUNT(*) FROM inventory_movements WHERE product_id = ? AND tipo = 'ajuste_baja' AND proponente_id IS NOT NULL AND autorizador_id IS NOT NULL",
        [$prodCtrl]) === 1,
    'BD: movimiento de ajuste con proponente y autorizador (RF-046)');

$r = call('POST', "{$ctrlUrl}/adjustments", ['store_id' => $storeId, 'product_id' => $prodCtrl, 'lot_id' => $lotCtrl,
    'cantidad' => 2, 'direccion' => 'incremento', 'motivo' => 'Correccion de conteo', 'autorizador_id' => $authId], $token);
check($r['status'] === 201, 'ajuste de INCREMENTO 2 u -> 201');
check((int)($r['json']['data']['asiento']['saldo_resultante'] ?? 0) === 9, 'asiento: saldo_resultante 9');
dbCheck(fn(): bool => saldoDe($storeId, $prodCtrl) === 9, 'BD: ctrl_balances.saldo = 9');

$r = call('GET', "{$ctrlUrl}/reconciliation", null, $token);
check(($r['json']['data']['desbalanceados'] ?? -1) === 0, 'conciliacion sigue en 0 desbalanceados');

// ------------------------------ 7. Asiento de salida por venta (RF-055)
$r = call('POST', "{$salesUrl}/orders", [
    'store_id' => $storeId, 'register_id' => $regId,
    'items' => [['lot_id' => $lotCtrl, 'cantidad' => 2, 'precio_unitario' => 5.00]],
], $token);
check($r['status'] === 201, 'venta de controlado -> 201');
$orderId = (int)($r['json']['data']['orden']['id'] ?? 0);
dbCheck(fn(): bool => (int)dbVal(
        'SELECT COUNT(*) FROM ctrl_ledger_entries WHERE product_id = ? AND tipo = ? AND cantidad = 2 AND ref_tipo = ? AND ref_id = ?',
        [$prodCtrl, 'salida', 'venta', $orderId]) === 1,
    'BD: asiento de SALIDA (2 u, ref venta) en el libro');
dbCheck(fn(): bool => saldoDe($storeId, $prodCtrl) === 7, 'BD: ctrl_balances.saldo = 7 tras la venta');

$r = call('POST', "{$salesUrl}/orders", [
    'store_id' => $storeId, 'register_id' => $regId,
    'items' => [['lot_id' => $lotLibre, 'cantidad' => 1, 'precio_unitario' => 5.00]],
], $token);
check($r['status'] === 201, 'venta de producto libre -> 201');
dbCheck(fn(): bool => (int)dbVal(
        'SELECT COUNT(*) FROM ctrl_ledger_entries WHERE product_id = ?', [$prodLibre]) === 0,
    'BD: la venta del producto libre NO genera asiento');

// -------------------- 8. Asientos de devolucion y anulacion (RN-10)
$r = call('POST', "{$salesUrl}/orders/{$orderId}/payments",
    ['medio' => 'efectivo', 'monto' => 10.00], $token);
check($r['status'] === 200 && ($r['json']['data']['orden']['estado'] ?? '') === 'pagada', 'pagar la venta -> 200 pagada');

$itemCtrl = (int)dbVal('SELECT id FROM sales_order_items WHERE order_id = ? ORDER BY id LIMIT 1', [$orderId]);
$r = call('POST', "{$salesUrl}/returns", ['order_id' => $orderId, 'motivo' => 'devolucion parcial',
    'items' => [['order_item_id' => $itemCtrl, 'cantidad' => 1, 'condicion' => 'vendible']]], $token);
check($r['status'] === 201, 'devolucion vendible 1 u -> 201');
dbCheck(fn(): bool => saldoDe($storeId, $prodCtrl) === 8, 'BD: ctrl_balances.saldo = 8 tras la devolucion');

$r = call('POST', "{$salesUrl}/orders/{$orderId}/anular", null, $token);
check($r['status'] === 200, 'anular la venta -> 200');
dbCheck(fn(): bool => saldoDe($storeId, $prodCtrl) === 10, 'BD: ctrl_balances.saldo = 10 tras la anulacion');
dbCheck(fn(): bool => (int)dbVal(
        'SELECT COUNT(*) FROM ctrl_ledger_entries WHERE product_id = ?', [$prodCtrl]) === 6,
    'BD: 6 asientos en el libro del controlado (entrada, 2 ajustes, salida, 2 devoluciones)');

// ---------------- 8b. Asientos en transferencias de controlados (RF-045)
$r = call('POST', "{$opsUrl}/stores", ['codigo' => "CB{$sufijo}", 'nombre' => "Suc Destino CP9 {$sufijo}"], $token);
check($r['status'] === 201, 'POST store destino -> 201');
$storeB = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$invUrl}/transfers", [
    'store_origen_id' => $storeId,
    'store_destino_id' => $storeB,
    'items' => [['lot_id' => $lotCtrl, 'cantidad' => 1]],
], $token);
$trId = (int)($r['json']['data']['id'] ?? ($r['json']['data']['transferencia']['id'] ?? 0));
check($r['status'] === 201 && $trId > 0, 'POST transferencia de controlado -> 201');

$r = call('POST', "{$invUrl}/transfers/{$trId}/despachar", null, $token);
check($r['status'] === 200, 'despachar transferencia -> 200');
dbCheck(fn(): bool => (int)dbVal(
        "SELECT COUNT(*) FROM ctrl_ledger_entries
          WHERE store_id = ? AND product_id = ? AND tipo = 'transferencia_out'
            AND ref_tipo = 'transferencia' AND ref_id = ?",
        [$storeId, $prodCtrl, $trId]) === 1,
    'BD: asiento transferencia_out en el ORIGEN (RF-045)');
dbCheck(fn(): bool => saldoDe($storeId, $prodCtrl) === 9, 'BD: saldo origen 10 -> 9');

$r = call('POST', "{$invUrl}/transfers/{$trId}/recibir",
    ['items' => [['lot_id' => $lotCtrl, 'cantidad_recibida' => 1]]], $token);
check($r['status'] === 200, 'recibir transferencia -> 200');
dbCheck(fn(): bool => (int)dbVal(
        "SELECT COUNT(*) FROM ctrl_ledger_entries
          WHERE store_id = ? AND product_id = ? AND tipo = 'transferencia_in'
            AND ref_tipo = 'transferencia' AND ref_id = ?",
        [$storeB, $prodCtrl, $trId]) === 1,
    'BD: asiento transferencia_in en el DESTINO (RF-045)');
dbCheck(fn(): bool => saldoDe($storeB, $prodCtrl) === 1, 'BD: saldo destino 0 -> 1');
dbCheck(fn(): bool => (int)dbVal(
        'SELECT COUNT(*) FROM ctrl_ledger_entries WHERE product_id = ?', [$prodCtrl]) === 8,
    'BD: 8 asientos en el libro del controlado (los 6 previos + salida/entrada de la transferencia)');

// ------------------------------------------------- 9. Conciliacion final
$r = call('GET', "{$ctrlUrl}/reconciliation", null, $token);
$rec = $r['json']['data'] ?? [];
check($r['status'] === 200 && ($rec['desbalanceados'] ?? -1) === 0, 'conciliacion final: 0 desbalanceados');
check(($rec['total'] ?? 0) >= 2, 'conciliacion final: total de pares producto-sucursal >= 2');

$r = call('GET', "{$ctrlUrl}/balances?store_id={$storeId}&product_id={$prodCtrl}", null, $token);
$bal = $r['json']['data'][0] ?? [];
check(($bal['saldo'] ?? null) === 9 && ($bal['saldo_calculado'] ?? null) === 9 && ($bal['conciliado'] ?? null) === true,
    'saldo final origen 9 = suma de asientos (RNF-024)');

$r = call('GET', "{$ctrlUrl}/balances?store_id={$storeB}&product_id={$prodCtrl}", null, $token);
$bal = $r['json']['data'][0] ?? [];
check(($bal['saldo'] ?? null) === 1 && ($bal['saldo_calculado'] ?? null) === 1 && ($bal['conciliado'] ?? null) === true,
    'saldo final destino 1 = suma de asientos (RNF-024)');

// ------------------------------------------------------ 10. Limpieza
$r = call('DELETE', "{$catUrl}/products/{$prodCtrl}", null, $token);
check($r['status'] === 204, 'DELETE product controlado -> 204 (borrado logico)');
$r = call('DELETE', "{$catUrl}/products/{$prodLibre}", null, $token);
check($r['status'] === 204, 'DELETE product libre -> 204');
$r = call('DELETE', "{$opsUrl}/stores/{$storeId}", null, $token);
check($r['status'] === 204, 'DELETE store -> 204');
$r = call('DELETE', "{$opsUrl}/stores/{$storeB}", null, $token);
check($r['status'] === 204, 'DELETE store destino -> 204');
$r = call('DELETE', "{$catUrl}/suppliers/{$provId}", null, $token);
check($r['status'] === 204, 'DELETE supplier -> 204');

echo "\nRESULTADO: " . ($fail === 0 ? 'PASS' : "FAIL ({$fail})") . "\n";
exit($fail === 0 ? 0 : 1);
