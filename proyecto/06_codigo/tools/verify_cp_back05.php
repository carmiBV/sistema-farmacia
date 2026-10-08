<?php
declare(strict_types=1);

/**
 * Verificación CP-BACK-05 — Compras y Recepciones
 *
 * Cobertura: 401 sin token, RBAC purchases.manage (lectura 200 / escritura 403),
 * órdenes (201/400/409/404, PUT reemplazo en borrador, emitir, cancelación
 * lógica), recepciones (RN-09 idempotencia, RF-031, ITEM_NOT_IN_ORDER,
 * EXCEEDS_PENDING, DUPLICATE_LOT, ORDER_NOT_ISSUED) y confirmación T-3 con
 * checks directos de BD: lote en cuarentena (RF-032), stock, movimiento de
 * entrada (CA-10), cantidad_recibida, outbox_events pendiente y RN-07/cierre.
 *
 * Requiere: servidor corriendo y seed previo con tools/init_admin.php
 * (ADMIN_USER / ADMIN_PASSWORD + permiso purchases.manage). Conexión directa
 * a BD vía .env (solo SELECT).
 *
 * Uso: php tools/verify_cp_back05.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_cp_back05.php <base-url>\n");
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

// --- Conexión directa a BD (solo SELECT) ---
$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
\App\Core\Env::load($root . '/.env');

/** @return list<array<string,mixed>> */
function dbRows(string $sql, array $params = []): array
{
    $stmt = \App\Core\Database::pdo()->prepare($sql);
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

/** Body de recepción (RF-031: lote + vencimiento + cantidad). */
function recvBody(string $key, int $productId, string $lote, int $cant): array
{
    return [
        'idempotency_key' => $key,
        'items' => [[
            'product_id' => $productId,
            'numero_lote' => $lote,
            'fecha_vencimiento' => '2027-06-30',
            'cantidad' => $cant,
        ]],
    ];
}

$sufijo = substr(md5((string)random_int(0, PHP_INT_MAX)), 0, 8);
$purUrl = "{$base}/api/v1/purchases";
$catUrl = "{$base}/api/v1/catalog";
$opsUrl = "{$base}/api/v1/ops";
$numeroA = "OC{$sufijo}A";
$numeroB = "OC{$sufijo}B";
$numeroC = "OC{$sufijo}C";

// --- 1. Autenticación ---
$r = call('GET', "{$purUrl}/orders");
check($r['status'] === 401 && errorCode($r) === 'UNAUTHENTICATED', 'GET /purchases/orders sin token -> 401 UNAUTHENTICATED');

$r = call('POST', "{$purUrl}/orders", ['numero' => "X{$sufijo}", 'supplier_id' => 1, 'items' => []]);
check($r['status'] === 401, 'POST /purchases/orders sin token -> 401');

$r = call('POST', "{$purUrl}/orders/1/receptions", ['items' => []]);
check($r['status'] === 401, 'POST /purchases/orders/1/receptions sin token -> 401');

$r = call('GET', "{$purUrl}/receptions");
check($r['status'] === 401, 'GET /purchases/receptions sin token -> 401');

// --- 2. Login admin (permiso purchases.manage) ---
$r = call('POST', "{$base}/api/v1/auth/login", ['usuario' => $adminUser, 'password' => $adminPass]);
check($r['status'] === 200, 'login admin -> 200');
$token = (string)($r['json']['data']['token'] ?? '');
check(in_array('purchases.manage', $r['json']['data']['user']['permisos'] ?? [], true),
    'payload incluye permiso purchases.manage (seed)');

// --- 3. RBAC: usuario sin permiso lee pero no escribe ---
$r = call('POST', "{$base}/api/v1/auth/users", ['usuario' => "pch_{$sufijo}", 'password' => 'Fix!Pass123', 'roles' => []], $token);
check($r['status'] === 201, 'crear usuario sin roles -> 201');
$r = call('POST', "{$base}/api/v1/auth/login", ['usuario' => "pch_{$sufijo}", 'password' => 'Fix!Pass123']);
check($r['status'] === 200, 'login usuario sin roles -> 200');
$fixToken = (string)($r['json']['data']['token'] ?? '');

$r = call('GET', "{$purUrl}/orders", null, $fixToken);
check($r['status'] === 200, 'GET orders con token sin permiso -> 200 (lectura con token)');

$r = call('POST', "{$purUrl}/orders", ['numero' => "R{$sufijo}", 'supplier_id' => 1, 'items' => []], $fixToken);
check($r['status'] === 403 && errorCode($r) === 'FORBIDDEN', 'POST orders sin permiso -> 403 FORBIDDEN');

$r = call('PUT', "{$purUrl}/orders/1", ['numero' => "R{$sufijo}", 'supplier_id' => 1, 'items' => []], $fixToken);
check($r['status'] === 403, 'PUT orders sin permiso -> 403');

$r = call('DELETE', "{$purUrl}/orders/1", null, $fixToken);
check($r['status'] === 403, 'DELETE orders sin permiso -> 403');

$r = call('POST', "{$purUrl}/orders/1/emitir", null, $fixToken);
check($r['status'] === 403, 'POST emitir sin permiso -> 403');

$r = call('POST', "{$purUrl}/orders/1/receptions", ['items' => []], $fixToken);
check($r['status'] === 403, 'POST recepción sin permiso -> 403');

$r = call('POST', "{$purUrl}/receptions/1/confirmar", ['store_id' => 1], $fixToken);
check($r['status'] === 403, 'POST confirmar sin permiso -> 403');

$r = call('POST', "{$purUrl}/receptions/1/rechazar", null, $fixToken);
check($r['status'] === 403, 'POST rechazar sin permiso -> 403');

// --- 4. Fixtures (proveedor, 2 productos, sucursal) ---
$r = call('POST', "{$catUrl}/suppliers",
    ['identificacion' => "ID{$sufijo}", 'nombre' => "Distrib CP5 {$sufijo}", 'contacto' => 'cp5@test.local'], $token);
check($r['status'] === 201 && (int)($r['json']['data']['id'] ?? 0) > 0, 'POST supplier (fixture) -> 201 con id');
$provId = (int)($r['json']['data']['id'] ?? 0);

$producto = [
    'sku' => "SKU{$sufijo}",
    'nombre' => "Paracetamol CP5 {$sufijo}",
    'principio_activo' => 'Paracetamol',
    'presentacion' => 'Caja x 10 tabletas',
    'concentracion' => '500 mg',
    'condicion_venta' => 'libre',
];
$r = call('POST', "{$catUrl}/products", $producto, $token);
check($r['status'] === 201 && (int)($r['json']['data']['id'] ?? 0) > 0, 'POST product1 (fixture) -> 201 con id');
$prodId = (int)($r['json']['data']['id'] ?? 0);

$producto2 = $producto;
$producto2['sku'] = "SKU2{$sufijo}";
$producto2['nombre'] = "Ibuprofeno CP5 {$sufijo}";
$producto2['principio_activo'] = 'Ibuprofeno';
$r = call('POST', "{$catUrl}/products", $producto2, $token);
check($r['status'] === 201 && (int)($r['json']['data']['id'] ?? 0) > 0, 'POST product2 (fixture) -> 201 con id');
$prodId2 = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$opsUrl}/stores", ['codigo' => "ST{$sufijo}", 'nombre' => "Suc CP5 {$sufijo}"], $token);
check($r['status'] === 201 && (int)($r['json']['data']['id'] ?? 0) > 0, 'POST store (fixture) -> 201 con id');
$storeId = (int)($r['json']['data']['id'] ?? 0);

// --- 5. Orden A: borrador, validaciones, PUT, cancelación ---
$ordenA = ['numero' => $numeroA, 'supplier_id' => $provId,
    'items' => [['product_id' => $prodId, 'cantidad_pedida' => 1]]];
$r = call('POST', "{$purUrl}/orders", $ordenA, $token);
check($r['status'] === 201 && ($r['json']['data']['numero'] ?? '') === $numeroA
    && ($r['json']['data']['estado'] ?? '') === 'borrador', 'POST order A valida -> 201 borrador');
$idA = (int)($r['json']['data']['id'] ?? 0);
check($idA > 0 && count($r['json']['data']['items'] ?? []) === 1, 'order A id>0 y 1 item en data.items');

$mal = $ordenA;
unset($mal['supplier_id']);
$r = call('POST', "{$purUrl}/orders", $mal, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'POST order sin supplier_id -> 400 VALIDATION_ERROR');

$mal = $ordenA;
$mal['numero'] = "OC{$sufijo}M";
$mal['items'] = [];
$r = call('POST', "{$purUrl}/orders", $mal, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'POST order items vacíos -> 400 VALIDATION_ERROR');

$mal = $ordenA;
$mal['numero'] = "OC{$sufijo}P";
$mal['items'] = [['product_id' => 99999999, 'cantidad_pedida' => 1]];
$r = call('POST', "{$purUrl}/orders", $mal, $token);
check($r['status'] === 409 && errorCode($r) === 'PRODUCT_NOT_FOUND', 'POST order product inexistente -> 409 PRODUCT_NOT_FOUND');

$mal = $ordenA;
$mal['numero'] = "OC{$sufijo}S";
$mal['supplier_id'] = 99999999;
$r = call('POST', "{$purUrl}/orders", $mal, $token);
check($r['status'] === 409 && errorCode($r) === 'SUPPLIER_NOT_FOUND', 'POST order supplier inexistente -> 409 SUPPLIER_NOT_FOUND');

$r = call('POST', "{$purUrl}/orders", $ordenA, $token);
check($r['status'] === 409 && errorCode($r) === 'DUPLICATE_NUMERO', 'POST order numero duplicado -> 409 DUPLICATE_NUMERO');

$r = call('GET', "{$purUrl}/orders?q={$numeroA}", null, $token);
check($r['status'] === 200 && in_array($idA, array_column($r['json']['data'] ?? [], 'id'), true)
    && isset($r['json']['meta']['total']), 'GET orders?q=numero filtra y pagina -> 200 con meta');

$r = call('GET', "{$purUrl}/orders?estado=borrador", null, $token);
check($r['status'] === 200 && in_array($idA, array_column($r['json']['data'] ?? [], 'id'), true),
    'GET orders?estado=borrador filtra');

$r = call('GET', "{$purUrl}/orders?estado=xyz", null, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'GET orders?estado=xyz -> 400 VALIDATION_ERROR');

$r = call('GET', "{$purUrl}/orders/{$idA}", null, $token);
check($r['status'] === 200 && ($r['json']['data']['numero'] ?? '') === $numeroA
    && (int)($r['json']['data']['items'][0]['product_id'] ?? 0) === $prodId, 'GET order por id -> 200 con items');

$r = call('GET', "{$purUrl}/orders/99999999", null, $token);
check($r['status'] === 404 && errorCode($r) === 'NOT_FOUND', 'GET order inexistente -> 404 NOT_FOUND');

$putA = ['numero' => $numeroA, 'supplier_id' => $provId,
    'items' => [['product_id' => $prodId, 'cantidad_pedida' => 2]]];
$r = call('PUT', "{$purUrl}/orders/{$idA}", $putA, $token);
check($r['status'] === 200 && (int)($r['json']['data']['items'][0]['cantidad_pedida'] ?? 0) === 2,
    'PUT order borrador -> 200 y cantidad_pedida refleja 2 (reemplazo completo)');

$r = call('POST', "{$purUrl}/orders/{$idA}/receptions", recvBody("{$sufijo}kA", $prodId, "LA{$sufijo}", 1), $token);
check($r['status'] === 409 && errorCode($r) === 'ORDER_NOT_ISSUED',
    'recepción sobre orden borrador -> 409 ORDER_NOT_ISSUED (RN-07: solo tras emitir)');

$r = call('DELETE', "{$purUrl}/orders/{$idA}", null, $token);
check($r['status'] === 204, 'DELETE order A -> 204 (cancelación lógica)');

$r = call('GET', "{$purUrl}/orders/{$idA}", null, $token);
check($r['status'] === 200 && ($r['json']['data']['estado'] ?? '') === 'cancelada',
    'GET order A tras DELETE -> 200 estado cancelada (fila visible, RNF-023)');

$r = call('DELETE', "{$purUrl}/orders/{$idA}", null, $token);
check($r['status'] === 409 && errorCode($r) === 'ORDER_NOT_CANCELLABLE',
    'DELETE order A cancelada -> 409 ORDER_NOT_CANCELLABLE');

// --- 6. Orden B: emisión, recepción idempotente, confirmación T-3 ---
$ordenB = ['numero' => $numeroB, 'supplier_id' => $provId,
    'items' => [['product_id' => $prodId, 'cantidad_pedida' => 10]]];
$r = call('POST', "{$purUrl}/orders", $ordenB, $token);
check($r['status'] === 201 && (int)($r['json']['data']['items'][0]['cantidad_pedida'] ?? 0) === 10,
    'POST order B -> 201 con cantidad_pedida 10');
$idB = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$purUrl}/orders/{$idB}/emitir", null, $token);
check($r['status'] === 200 && ($r['json']['data']['estado'] ?? '') === 'emitida',
    'POST emitir -> 200 estado emitida');

$r = call('POST', "{$purUrl}/orders/{$idB}/emitir", null, $token);
check($r['status'] === 409 && errorCode($r) === 'ORDER_NOT_DRAFT', 're-emitir -> 409 ORDER_NOT_DRAFT');

$r = call('PUT', "{$purUrl}/orders/{$idB}", $ordenB, $token);
check($r['status'] === 409 && errorCode($r) === 'ORDER_NOT_DRAFT', 'PUT sobre orden emitida -> 409 ORDER_NOT_DRAFT');

$r = call('POST', "{$purUrl}/orders/99999999/receptions", recvBody("{$sufijo}k404", $prodId, "LZ{$sufijo}", 1), $token);
check($r['status'] === 404 && errorCode($r) === 'NOT_FOUND', 'recepción sobre orden inexistente -> 404 NOT_FOUND');

$lote1 = "L{$sufijo}1";
$r1 = call('POST', "{$purUrl}/orders/{$idB}/receptions", recvBody("{$sufijo}k1", $prodId, $lote1, 4), $token);
check($r1['status'] === 201 && ($r1['json']['meta']['idempotent_reused'] ?? null) === false
    && ($r1['json']['data']['estado'] ?? '') === 'recibida',
    'POST recepción k1 cant 4 -> 201, idempotent_reused false, estado recibida');
$rec1Id = (int)($r1['json']['data']['id'] ?? 0);
$it1 = $r1['json']['data']['items'][0] ?? [];
check($rec1Id > 0 && (int)($it1['id'] ?? 0) > 0 && array_key_exists('lot_id', $it1)
    && $it1['lot_id'] === null && ($it1['numero_lote'] ?? '') === $lote1 && (int)($it1['cantidad'] ?? 0) === 4,
    'rec1 items: id>0, numero_lote, cantidad 4, lot_id null (sin stock hasta confirmar)');

$r = call('POST', "{$purUrl}/orders/{$idB}/receptions", recvBody("{$sufijo}k1", $prodId, $lote1, 4), $token);
check($r['status'] === 200 && ($r['json']['meta']['idempotent_reused'] ?? null) === true
    && (int)($r['json']['data']['id'] ?? 0) === $rec1Id,
    'mismo idempotency_key k1 -> 200 idempotent_reused true, misma recepción (RN-09)');

$r = call('POST', "{$purUrl}/orders/{$idB}/receptions", recvBody("{$sufijo}k2", $prodId2, "LX{$sufijo}", 1), $token);
check($r['status'] === 400 && errorCode($r) === 'ITEM_NOT_IN_ORDER', 'producto fuera de la orden -> 400 ITEM_NOT_IN_ORDER');

$r = call('POST', "{$purUrl}/orders/{$idB}/receptions", recvBody("{$sufijo}k2b", $prodId, "LY{$sufijo}", 999), $token);
check($r['status'] === 400 && errorCode($r) === 'EXCEEDS_PENDING', 'cantidad excede pendiente -> 400 EXCEEDS_PENDING');

$sinLote = ['idempotency_key' => "{$sufijo}k2c",
    'items' => [['product_id' => $prodId, 'fecha_vencimiento' => '2027-06-30', 'cantidad' => 1]]];
$r = call('POST', "{$purUrl}/orders/{$idB}/receptions", $sinLote, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'recepción sin numero_lote -> 400 (RF-031)');

$r = call('GET', "{$purUrl}/receptions?order_id={$idB}", null, $token);
check($r['status'] === 200 && in_array($rec1Id, array_column($r['json']['data'] ?? [], 'id'), true)
    && isset($r['json']['meta']['total']), 'GET receptions?order_id=filtra con meta');

$r = call('GET', "{$purUrl}/receptions?estado=recibida", null, $token);
check($r['status'] === 200 && in_array($rec1Id, array_column($r['json']['data'] ?? [], 'id'), true),
    'GET receptions?estado=recibida filtra');

$r = call('GET', "{$purUrl}/receptions?estado=xyz", null, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'GET receptions?estado=xyz -> 400');

$r = call('GET', "{$purUrl}/receptions/99999999", null, $token);
check($r['status'] === 404 && errorCode($r) === 'NOT_FOUND', 'GET recepción inexistente -> 404');

$r = call('GET', "{$purUrl}/receptions/{$rec1Id}", null, $token);
check($r['status'] === 200 && ($r['json']['data']['items'][0]['numero_lote'] ?? '') === $lote1,
    'GET recepción por id -> 200 con items');

$r = call('POST', "{$purUrl}/receptions/{$rec1Id}/confirmar", [], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'confirmar sin store_id -> 400 (supuesto: store_id obligatorio)');

$r = call('POST', "{$purUrl}/receptions/{$rec1Id}/confirmar", ['store_id' => 99999999], $token);
check($r['status'] === 404 && errorCode($r) === 'NOT_FOUND', 'confirmar con store inexistente -> 404 NOT_FOUND');

$rConf = call('POST', "{$purUrl}/receptions/{$rec1Id}/confirmar", ['store_id' => $storeId], $token);
check($rConf['status'] === 200 && ($rConf['json']['data']['estado'] ?? '') === 'confirmada',
    'confirmar rec1 -> 200 estado confirmada (T-3)');
$lot1 = (int)($rConf['json']['data']['items'][0]['lot_id'] ?? 0);
check($lot1 > 0, 'confirmar rec1: items[0].lot_id asignado (>0)');

// --- 6b. Checks directos de BD tras confirmar rec1 (RF-032, CA-10, RN-07) ---
dbCheck(fn (): bool => (int)dbVal(
    'SELECT id FROM purchase_reception_items WHERE reception_id = ? AND numero_lote = ?',
    [$rec1Id, $lote1]
) > 0, 'DB: purchase_reception_items fila creada (id>0)');

dbCheck(fn (): bool => dbVal(
    'SELECT estado FROM inventory_lots
     WHERE reception_item_id = (SELECT id FROM purchase_reception_items WHERE reception_id = ? AND numero_lote = ? LIMIT 1)
       AND product_id = ? AND numero_lote = ?',
    [$rec1Id, $lote1, $prodId, $lote1]
) === 'cuarentena', 'DB: inventory_lots en cuarentena vinculado al item (RF-032)');

dbCheck(fn (): bool => (int)dbVal(
    'SELECT stock_available FROM inventory_stock WHERE store_id = ? AND lot_id = ?',
    [$storeId, $lot1]
) === 4, 'DB: inventory_stock.stock_available = 4 (RN-07: solo al confirmar)');

dbCheck(fn (): bool => dbVal(
    "SELECT CONCAT(tipo, '|', signo, '|', ref_tipo, '|', ref_id) FROM inventory_movements WHERE lot_id = ?",
    [$lot1]
) === "entrada|1|recepcion|{$rec1Id}", 'DB: inventory_movements entrada signo 1 ref recepcion (CA-10)');

dbCheck(fn (): bool => (int)dbVal(
    'SELECT cantidad_recibida FROM purchase_order_items WHERE order_id = ? AND product_id = ?',
    [$idB, $prodId]
) === 4, 'DB: purchase_order_items.cantidad_recibida = 4');

dbCheck(fn (): bool => dbVal(
    "SELECT estado FROM outbox_events WHERE agregado_tipo = 'recepcion' AND agregado_id = ?
       AND tipo_evento = 'recepcion.confirmada'",
    [$rec1Id]
) === 'pendiente', 'DB: outbox_events recepcion.confirmada estado pendiente (supuesto T-3)');

// --- 6c. Estados de recepción y cierre de la orden ---
$r = call('POST', "{$purUrl}/receptions/{$rec1Id}/confirmar", ['store_id' => $storeId], $token);
check($r['status'] === 409 && errorCode($r) === 'RECEPTION_NOT_PENDING', 'doble confirmar -> 409 RECEPTION_NOT_PENDING');

$r = call('POST', "{$purUrl}/receptions/{$rec1Id}/rechazar", null, $token);
check($r['status'] === 409 && errorCode($r) === 'RECEPTION_NOT_PENDING', 'rechazar sobre confirmada -> 409 RECEPTION_NOT_PENDING');

$r = call('POST', "{$purUrl}/orders/{$idB}/receptions", recvBody("{$sufijo}k2d", $prodId, $lote1, 1), $token);
check($r['status'] === 409 && errorCode($r) === 'DUPLICATE_LOT', 'lote duplicado (L1 ya existe) -> 409 DUPLICATE_LOT (RF-031)');

$lote2 = "L{$sufijo}2";
$r2 = call('POST', "{$purUrl}/orders/{$idB}/receptions", recvBody("{$sufijo}k3", $prodId, $lote2, 4), $token);
check($r2['status'] === 201, 'POST recepción k2 (cant 4) -> 201');
$rec2Id = (int)($r2['json']['data']['id'] ?? 0);

$r = call('POST', "{$purUrl}/receptions/{$rec2Id}/rechazar", null, $token);
check($r['status'] === 200 && ($r['json']['data']['estado'] ?? '') === 'rechazada',
    'rechazar rec2 -> 200 estado rechazada (sin efecto en stock)');

$r = call('POST', "{$purUrl}/receptions/{$rec2Id}/rechazar", null, $token);
check($r['status'] === 409 && errorCode($r) === 'RECEPTION_NOT_PENDING', 'doble rechazar -> 409 RECEPTION_NOT_PENDING');

$lote3 = "L{$sufijo}3";
$r3 = call('POST', "{$purUrl}/orders/{$idB}/receptions", recvBody("{$sufijo}k4", $prodId, $lote3, 6), $token);
check($r3['status'] === 201, 'POST recepción k3 (cant 6) -> 201');
$rec3Id = (int)($r3['json']['data']['id'] ?? 0);

$r = call('POST', "{$purUrl}/receptions/{$rec3Id}/confirmar", ['store_id' => $storeId], $token);
check($r['status'] === 200 && ($r['json']['data']['estado'] ?? '') === 'confirmada', 'confirmar rec3 -> 200');

dbCheck(fn (): bool => (int)dbVal(
    'SELECT cantidad_recibida FROM purchase_order_items WHERE order_id = ? AND product_id = ?',
    [$idB, $prodId]
) === 10, 'DB: cantidad_recibida = 10 tras confirmar rec3 (suma incremental)');

$r = call('GET', "{$purUrl}/orders/{$idB}", null, $token);
check($r['status'] === 200 && ($r['json']['data']['estado'] ?? '') === 'recibida',
    'orden B tras 10/10 -> estado recibida (cierre automático)');

$r = call('POST', "{$purUrl}/orders/{$idB}/receptions", recvBody("{$sufijo}k5", $prodId, "L{$sufijo}5", 1), $token);
check($r['status'] === 409 && errorCode($r) === 'ORDER_NOT_ISSUED',
    'recepción sobre orden recibida -> 409 ORDER_NOT_ISSUED');

$r = call('DELETE', "{$purUrl}/orders/{$idB}", null, $token);
check($r['status'] === 409 && errorCode($r) === 'ORDER_NOT_CANCELLABLE',
    'DELETE orden B recibida -> 409 ORDER_NOT_CANCELLABLE');

// --- 7. Orden C: recepción parcial bloquea cancelación (ORDER_HAS_RECEPTIONS) ---
$ordenC = ['numero' => $numeroC, 'supplier_id' => $provId,
    'items' => [['product_id' => $prodId, 'cantidad_pedida' => 1]]];
$r = call('POST', "{$purUrl}/orders", $ordenC, $token);
check($r['status'] === 201, 'POST order C -> 201');
$idC = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$purUrl}/orders/{$idC}/emitir", null, $token);
check($r['status'] === 200 && ($r['json']['data']['estado'] ?? '') === 'emitida', 'emitir order C -> 200');

$r = call('POST', "{$purUrl}/orders/{$idC}/receptions", recvBody("{$sufijo}k6", $prodId, "LC{$sufijo}", 1), $token);
check($r['status'] === 201, 'POST recepción parcial order C -> 201 (aún sin confirmar)');

$r = call('DELETE', "{$purUrl}/orders/{$idC}", null, $token);
check($r['status'] === 409 && errorCode($r) === 'ORDER_HAS_RECEPTIONS',
    'DELETE orden C con recepciones -> 409 ORDER_HAS_RECEPTIONS');

$r = call('GET', "{$purUrl}/orders/{$idC}", null, $token);
check($r['status'] === 200 && ($r['json']['data']['estado'] ?? '') === 'emitida',
    'orden C tras DELETE fallido -> sigue emitida');

// --- 8. Limpieza de fixtures (borrado lógico 204; product1 queda referenciado) ---
$r = call('DELETE', "{$catUrl}/products/{$prodId2}", null, $token);
check($r['status'] === 204, 'DELETE product2 fixture -> 204');
$r = call('DELETE', "{$catUrl}/suppliers/{$provId}", null, $token);
check($r['status'] === 204, 'DELETE supplier fixture -> 204');
$r = call('DELETE', "{$opsUrl}/stores/{$storeId}", null, $token);
check($r['status'] === 204, 'DELETE store fixture -> 204');

echo "\nRESULTADO: " . ($fail === 0 ? 'PASS' : "FAIL ({$fail})") . "\n";
exit($fail === 0 ? 0 : 1);
