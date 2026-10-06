<?php
declare(strict_types=1);

/**
 * CP-BACK-06 — Inventario y trazabilidad FEFO (RF-044, RF-046, RF-047, RF-100,
 * RN-02, RN-03, RN-08, CAM-002-f): liberación de lotes desde cuarentena,
 * listados y filtros, umbrales de alertas en config, evaluación/resolución de
 * alertas, transferencias con idempotencia y FEFO, incidentes con doble
 * autorización y reservas de stock.
 *
 * Requiere: servidor corriendo y seed previo con tools/init_admin.php
 * (ADMIN_USER / ADMIN_PASSWORD + permisos inventory.manage e inventory.adjust).
 * Conexión directa a BD vía .env (solo SELECT).
 *
 * Uso: php tools/verify_cp_back06.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_cp_back06.php <base-url>\n");
    exit(2);
}

$adminUser = getenv('ADMIN_USER') ?: 'admin';
$adminPass = getenv('ADMIN_PASSWORD');
if ($adminPass === false || $adminPass === '') {
    fwrite(STDERR, "Defina ADMIN_PASSWORD (la misma del seed init_admin.php).\n");
    exit(2);
}

global $fail;
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
 * @param string|array|null $json array se serializa; string se envía crudo
 *                                (para probar cuerpos no-JSON)
 * @return array{status:int,json:?array,body:string}
 */
function call(string $method, string $url, string|array|null $json = null, ?string $token = null): array
{
    $headers = '';
    if ($token !== null && $token !== '') {
        $headers .= "Authorization: Bearer {$token}\r\n";
    }
    $opts = ['http' => ['timeout' => 15, 'ignore_errors' => true, 'method' => $method]];
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

// --- Conexión directa a BD (solo SELECT) ---
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

function stockVal(int $storeId, int $lotId): int
{
    return (int)dbVal(
        'SELECT stock_available FROM inventory_stock WHERE store_id = ? AND lot_id = ?',
        [$storeId, $lotId]
    );
}

/** Cuerpo de recepción (RF-031): lote + vencimiento + cantidad. */
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
$invUrl = "{$base}/api/v1/inventory";
$opsUrl = "{$base}/api/v1/ops";
$catUrl = "{$base}/api/v1/catalog";
$purUrl = "{$base}/api/v1/purchases";
$passFix = 'Fix!Pass123';

// --- 1. Autenticación: 401 sin token ---
$r = call('GET', "{$invUrl}/stocks");
check($r['status'] === 401 && errorCode($r) === 'UNAUTHENTICATED', 'GET /inventory/stocks sin token -> 401 UNAUTHENTICATED');

$r = call('GET', "{$invUrl}/transfers");
check($r['status'] === 401, 'GET /inventory/transfers sin token -> 401');

$r = call('POST', "{$invUrl}/transfers", []);
check($r['status'] === 401, 'POST /inventory/transfers sin token -> 401');

$r = call('POST', "{$invUrl}/incidents", []);
check($r['status'] === 401, 'POST /inventory/incidents sin token -> 401');

$r = call('POST', "{$invUrl}/reservations", []);
check($r['status'] === 401, 'POST /inventory/reservations sin token -> 401');

// --- 2. Login admin y usuarios de prueba ---
$r = call('POST', "{$authUrl}/login", ['usuario' => $adminUser, 'password' => $adminPass]);
check($r['status'] === 200, 'login admin -> 200');
$token = (string)($r['json']['data']['token'] ?? '');
$permAdmin = $r['json']['data']['user']['permisos'] ?? [];
check(in_array('inventory.manage', $permAdmin, true) && in_array('inventory.adjust', $permAdmin, true),
    'payload admin incluye inventory.manage e inventory.adjust (seed)');

$r = call('POST', "{$authUrl}/users", ['usuario' => "fix_{$sufijo}", 'password' => $passFix, 'roles' => []], $token);
check($r['status'] === 201, 'crear usuario sin roles -> 201');
$r = call('POST', "{$authUrl}/login", ['usuario' => "fix_{$sufijo}", 'password' => $passFix]);
check($r['status'] === 200, 'login usuario sin roles -> 200');
$fixToken = (string)($r['json']['data']['token'] ?? '');

$r = call('POST', "{$authUrl}/users", ['usuario' => "adj_{$sufijo}", 'password' => $passFix, 'roles' => ['admin']], $token);
check($r['status'] === 201, 'crear usuario rol admin -> 201');
$r = call('POST', "{$authUrl}/login", ['usuario' => "adj_{$sufijo}", 'password' => $passFix]);
check($r['status'] === 200, 'login usuario rol admin -> 200');
$token2 = (string)($r['json']['data']['token'] ?? '');
check(in_array('inventory.adjust', $r['json']['data']['user']['permisos'] ?? [], true),
    'payload usuario2 incluye inventory.adjust (rol admin)');

// --- 3. RBAC: token sin permiso lee pero no escribe ---
$r = call('GET', "{$invUrl}/stocks", null, $fixToken);
check($r['status'] === 200, 'GET stocks con token sin permiso -> 200 (lectura con token)');
$r = call('GET', "{$invUrl}/transfers", null, $fixToken);
check($r['status'] === 200, 'GET transfers con token sin permiso -> 200');
$r = call('GET', "{$invUrl}/alerts", null, $fixToken);
check($r['status'] === 200, 'GET alerts con token sin permiso -> 200');
$r = call('GET', "{$invUrl}/reservations", null, $fixToken);
check($r['status'] === 200, 'GET reservations con token sin permiso -> 200');

$r = call('POST', "{$invUrl}/incidents", ['store_id' => 1, 'lot_id' => 1, 'consumo_declarado' => 1], $fixToken);
check($r['status'] === 403 && errorCode($r) === 'FORBIDDEN', 'POST incidents sin permiso -> 403 FORBIDDEN');
$r = call('POST', "{$invUrl}/transfers", [], $fixToken);
check($r['status'] === 403, 'POST transfers sin permiso -> 403');
$r = call('POST', "{$invUrl}/reservations", [], $fixToken);
check($r['status'] === 403, 'POST reservations sin permiso -> 403');
$r = call('POST', "{$invUrl}/batches/1/liberar", ['motivo' => 'x'], $fixToken);
check($r['status'] === 403, 'POST batches/1/liberar sin permiso -> 403');
$r = call('POST', "{$invUrl}/alerts/evaluar", [], $fixToken);
check($r['status'] === 403, 'POST alerts/evaluar sin permiso -> 403');
$r = call('POST', "{$invUrl}/incidents/1/ajustar", null, $fixToken);
check($r['status'] === 403, 'POST incidents/1/ajustar sin permiso (inventory.adjust) -> 403');
$r = call('PUT', "{$opsUrl}/config/alerta.stock_minimo", ['config_value' => '1', 'value_type' => 'numero'], $fixToken);
check($r['status'] === 403, 'PUT config sin permiso -> 403');

// --- 4. Fixtures: proveedor, producto, 2 sucursales, 2 lotes (FEFO) ---
$r = call('POST', "{$catUrl}/suppliers",
    ['identificacion' => "ID{$sufijo}", 'nombre' => "Distrib CP6 {$sufijo}", 'contacto' => 'cp6@test.local'], $token);
check($r['status'] === 201 && (int)($r['json']['data']['id'] ?? 0) > 0, 'POST supplier (fixture) -> 201 con id');
$provId = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$catUrl}/products", [
    'sku' => "SKU{$sufijo}",
    'nombre' => "Paracetamol CP6 {$sufijo}",
    'principio_activo' => 'Paracetamol',
    'presentacion' => 'Caja x 10 tabletas',
    'concentracion' => '500 mg',
    'condicion_venta' => 'libre',
], $token);
check($r['status'] === 201 && (int)($r['json']['data']['id'] ?? 0) > 0, 'POST product (fixture) -> 201 con id');
$prodId = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$opsUrl}/stores", ['codigo' => "SA{$sufijo}", 'nombre' => "Suc A CP6 {$sufijo}"], $token);
check($r['status'] === 201 && (int)($r['json']['data']['id'] ?? 0) > 0, 'POST store A -> 201 con id');
$storeA = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$opsUrl}/stores", ['codigo' => "SB{$sufijo}", 'nombre' => "Suc B CP6 {$sufijo}"], $token);
check($r['status'] === 201 && (int)($r['json']['data']['id'] ?? 0) > 0, 'POST store B -> 201 con id');
$storeB = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$purUrl}/orders", [
    'numero' => "OC{$sufijo}", 'supplier_id' => $provId,
    'items' => [['product_id' => $prodId, 'cantidad_pedida' => 10]],
], $token);
check($r['status'] === 201, 'POST order fixture -> 201');
$idOrd = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$purUrl}/orders/{$idOrd}/emitir", null, $token);
check($r['status'] === 200 && ($r['json']['data']['estado'] ?? '') === 'emitida', 'POST emitir -> 200 emitida');

$lote1 = "L{$sufijo}1";
$lote2 = "L{$sufijo}2";
$r1 = call('POST', "{$purUrl}/orders/{$idOrd}/receptions", recvBody("{$sufijo}r1", $prodId, $lote1, 6, '2026-10-20'), $token);
check($r1['status'] === 201, 'POST recepción L1 (cant 6, vence 2026-10-20) -> 201');
$rec1Id = (int)($r1['json']['data']['id'] ?? 0);
$r = call('POST', "{$purUrl}/receptions/{$rec1Id}/confirmar", ['store_id' => $storeA], $token);
check($r['status'] === 200 && ($r['json']['data']['estado'] ?? '') === 'confirmada', 'confirmar rec1 en store A -> 200');
$lot1 = (int)($r['json']['data']['items'][0]['lot_id'] ?? 0);
check($lot1 > 0, 'rec1: items[0].lot_id asignado (>0)');

$r2 = call('POST', "{$purUrl}/orders/{$idOrd}/receptions", recvBody("{$sufijo}r2", $prodId, $lote2, 4, '2027-06-30'), $token);
check($r2['status'] === 201, 'POST recepción L2 (cant 4, vence 2027-06-30) -> 201');
$rec2Id = (int)($r2['json']['data']['id'] ?? 0);
$r = call('POST', "{$purUrl}/receptions/{$rec2Id}/confirmar", ['store_id' => $storeA], $token);
check($r['status'] === 200, 'confirmar rec2 en store A -> 200');
$lot2 = (int)($r['json']['data']['items'][0]['lot_id'] ?? 0);
check($lot2 > 0, 'rec2: items[0].lot_id asignado (>0)');

// --- 5. Liberación de lote desde cuarentena (RN-03) ---
$r = call('POST', "{$invUrl}/batches/{$lot1}/liberar", ['motivo' => 'Liberacion CP6'], $token);
check($r['status'] === 200 && ($r['json']['data']['estado'] ?? '') === 'liberado', 'liberar lot1 -> 200 estado liberado');
$r = call('POST', "{$invUrl}/batches/{$lot1}/liberar", ['motivo' => 'Liberacion CP6'], $token);
check($r['status'] === 409 && errorCode($r) === 'LOT_NOT_QUARANTINE', 'liberar lot1 de nuevo -> 409 LOT_NOT_QUARANTINE');
$r = call('POST', "{$invUrl}/batches/{$lot2}/liberar", [], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'liberar lot2 sin motivo -> 400 VALIDATION_ERROR');
$r = call('POST', "{$invUrl}/batches/{$lot1}/liberar", 'abc', $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', "liberar con cuerpo crudo 'abc' -> 400 VALIDATION_ERROR");
$r = call('POST', "{$invUrl}/batches/99999999/liberar", ['motivo' => 'x'], $token);
check($r['status'] === 404 && errorCode($r) === 'NOT_FOUND', 'liberar lote inexistente -> 404 NOT_FOUND');

// --- 6. Listados y filtros ---
$r = call('GET', "{$invUrl}/stocks", null, $token);
check($r['status'] === 200 && isset($r['json']['meta']['total']), 'GET stocks -> 200 con meta.total');
$r = call('GET', "{$invUrl}/movements", null, $token);
check($r['status'] === 200, 'GET movements -> 200');
$r = call('GET', "{$invUrl}/batches", null, $token);
check($r['status'] === 200, 'GET batches -> 200');
$r = call('GET', "{$invUrl}/transfers", null, $token);
check($r['status'] === 200 && isset($r['json']['meta']['total']), 'GET transfers -> 200 con meta.total');
$r = call('GET', "{$invUrl}/reservations", null, $token);
check($r['status'] === 200 && isset($r['json']['meta']['total']), 'GET reservations -> 200 con meta.total');
$r = call('GET', "{$invUrl}/alerts", null, $token);
check($r['status'] === 200, 'GET alerts -> 200');
$r = call('GET', "{$invUrl}/incidents", null, $token);
check($r['status'] === 200, 'GET incidents -> 200');

$r = call('GET', "{$invUrl}/stocks?store_id={$storeA}&product_id={$prodId}", null, $token);
check($r['status'] === 200 && isset($r['json']['meta']['total']), 'GET stocks?store_id&product_id filtra -> 200 con meta');
$r = call('GET', "{$invUrl}/stocks?estado=xyz", null, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'GET stocks?estado=xyz -> 400 VALIDATION_ERROR');
$r = call('GET', "{$invUrl}/incidents?estado=xyz", null, $token);
check($r['status'] === 400, 'GET incidents?estado=xyz -> 400');
$r = call('GET', "{$invUrl}/transfers?estado=xyz", null, $token);
check($r['status'] === 400, 'GET transfers?estado=xyz -> 400');
$r = call('GET', "{$invUrl}/reservations?status=xyz", null, $token);
check($r['status'] === 400, 'GET reservations?status=xyz -> 400');
$r = call('GET', "{$invUrl}/alerts?tipo=xyz", null, $token);
check($r['status'] === 400, 'GET alerts?tipo=xyz -> 400');
$r = call('GET', "{$invUrl}/stocks?limit=1000", null, $token);
check($r['status'] === 400, 'GET stocks?limit=1000 -> 400');
$r = call('GET', "{$invUrl}/stocks?page=0", null, $token);
check($r['status'] === 400, 'GET stocks?page=0 -> 400');

// --- 7. Config de umbrales (RN-02, RF-100) ---
$r = call('PUT', "{$opsUrl}/config/alerta.stock_minimo", ['config_value' => '100', 'value_type' => 'numero'], $token);
check($r['status'] === 200, 'PUT config alerta.stock_minimo=100 -> 200 (upsert)');
$r = call('PUT', "{$opsUrl}/config/alerta.dias_vencimiento", ['config_value' => '30', 'value_type' => 'numero'], $token);
check($r['status'] === 200, 'PUT config alerta.dias_vencimiento=30 -> 200 (upsert)');
$r = call('GET', "{$opsUrl}/config/alerta.stock_minimo", null, $token);
check($r['status'] === 200 && (string)($r['json']['data']['config_value'] ?? '') === '100',
    'GET config alerta.stock_minimo -> config_value 100');
$r = call('GET', "{$opsUrl}/config/bad+key", null, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'GET config clave bad+key -> 400 VALIDATION_ERROR');
$r = call('GET', "{$opsUrl}/config/config.{$sufijo}.noexiste", null, $token);
check($r['status'] === 404 && errorCode($r) === 'NOT_FOUND', 'GET config clave válida inexistente -> 404 NOT_FOUND');
$r = call('GET', "{$opsUrl}/config", null, $token);
check($r['status'] === 200 && isset($r['json']['meta']['total']), 'GET config list -> 200 con meta.total');

// --- 8. Evaluación y resolución de alertas (RF-044) ---
$r = call('POST', "{$invUrl}/alerts/evaluar", null, $token);
check($r['status'] === 200 && (int)($r['json']['data']['creadas'] ?? 0) >= 1
    && (int)($r['json']['data']['umbral_stock_minimo'] ?? 0) === 100
    && (int)($r['json']['data']['dias_vencimiento'] ?? 0) === 30,
    'evaluar alertas -> 200, creadas>=1, umbral 100, dias 30');
$r = call('POST', "{$invUrl}/alerts/evaluar", null, $token);
check($r['status'] === 200 && (int)($r['json']['data']['creadas'] ?? -1) === 0,
    'evaluar alertas 2ª vez -> 200, creadas=0 (dedup por aplicación)');

$r = call('GET', "{$invUrl}/alerts?estado=abierta&product_id={$prodId}", null, $token);
check($r['status'] === 200, 'GET alerts?estado=abierta&product_id filtra -> 200');
$alertIds = array_column($r['json']['data'] ?? [], 'id');
$alertId = (int)($alertIds[0] ?? 0);
check($alertId > 0, 'alerta abierta del producto fixture encontrada (id>0)');

$r = call('POST', "{$invUrl}/alerts/{$alertId}/resolver", null, $token);
check($r['status'] === 200 && ($r['json']['data']['estado'] ?? '') === 'resuelta', 'resolver alerta -> 200 estado resuelta');
$r = call('POST', "{$invUrl}/alerts/{$alertId}/resolver", null, $token);
check($r['status'] === 409 && errorCode($r) === 'ALERT_NOT_OPEN', 'resolver alerta de nuevo -> 409 ALERT_NOT_OPEN');
$r = call('POST', "{$invUrl}/alerts/99999999/resolver", null, $token);
check($r['status'] === 404 && errorCode($r) === 'NOT_FOUND', 'resolver alerta inexistente -> 404 NOT_FOUND');

// --- 9. Transferencia T1 (A->B, lot1, cant 2): ciclo completo (RN-08) ---
$t1 = ['store_origen_id' => $storeA, 'store_destino_id' => $storeB,
    'items' => [['lot_id' => $lot1, 'cantidad' => 2]]];
$r = call('POST', "{$invUrl}/transfers",
    ['store_origen_id' => $storeA, 'store_destino_id' => $storeA,
        'items' => [['lot_id' => $lot1, 'cantidad' => 2]]], $token);
check($r['status'] === 400 && errorCode($r) === 'TRANSFER_SAME_STORE', 'transferencia mismo store -> 400 TRANSFER_SAME_STORE');
$r = call('POST', "{$invUrl}/transfers",
    ['store_origen_id' => $storeA, 'store_destino_id' => 99999999,
        'items' => [['lot_id' => $lot1, 'cantidad' => 2]]], $token);
check($r['status'] === 404 && errorCode($r) === 'NOT_FOUND', 'transferencia destino inexistente -> 404 NOT_FOUND');
$r = call('POST', "{$invUrl}/transfers",
    ['store_origen_id' => $storeA, 'store_destino_id' => $storeB,
        'items' => [['lot_id' => 99999999, 'cantidad' => 2]]], $token);
check($r['status'] === 404 && errorCode($r) === 'NOT_FOUND', 'transferencia lote inexistente -> 404 NOT_FOUND');
$r = call('POST', "{$invUrl}/transfers",
    ['store_origen_id' => $storeA, 'store_destino_id' => $storeB,
        'items' => [['lot_id' => $lot1, 'cantidad' => 9999]]], $token);
check($r['status'] === 409 && errorCode($r) === 'STOCK_NOT_ENOUGH', 'transferencia con cant > stock -> 409 STOCK_NOT_ENOUGH');

$claveT = "{$sufijo}T1";
$r = call('POST', "{$invUrl}/transfers", $t1 + ['idempotency_key' => $claveT], $token);
check($r['status'] === 201 && ($r['json']['data']['idempotent_reused'] ?? null) === false,
    'crear T1 -> 201 idempotent_reused false');
$t1Id = (int)($r['json']['data']['transferencia']['id'] ?? 0);
check($t1Id > 0, 'T1 id en data.transferencia.id (>0)');

$r = call('POST', "{$invUrl}/transfers", $t1 + ['idempotency_key' => $claveT], $token);
check($r['status'] === 200 && ($r['json']['data']['idempotent_reused'] ?? null) === true
    && (int)($r['json']['data']['transferencia']['id'] ?? 0) === $t1Id,
    'misma idempotency_key -> 200 idempotent_reused true, mismo id');

$r = call('GET', "{$invUrl}/transfers/{$t1Id}", null, $token);
check($r['status'] === 200 && ($r['json']['data']['transferencia']['estado'] ?? '') === 'solicitada',
    'GET T1 por id -> 200 estado solicitada');

$r = call('POST', "{$invUrl}/transfers/{$t1Id}/recibir",
    ['items' => [['lot_id' => $lot1, 'cantidad_recibida' => 0]]], $token);
check($r['status'] === 409 && errorCode($r) === 'TRANSFER_NOT_DESPACHADA',
    'recibir T1 aún solicitada -> 409 TRANSFER_NOT_DESPACHADA');

$r = call('POST', "{$invUrl}/transfers/{$t1Id}/despachar", null, $token);
check($r['status'] === 200 && ($r['json']['data']['transferencia']['estado'] ?? '') === 'despachada',
    'despachar T1 -> 200 estado despachada');
dbCheck(fn (): bool => stockVal($storeA, $lot1) === 4, 'DB: stock A lot1 = 4 tras despachar (6-2)');

$r = call('POST', "{$invUrl}/transfers/{$t1Id}/recibir",
    ['items' => [['lot_id' => $lot2, 'cantidad_recibida' => 1]]], $token);
check($r['status'] === 400 && errorCode($r) === 'ITEM_NOT_IN_TRANSFER',
    'recibir con lote fuera de la transferencia -> 400 ITEM_NOT_IN_TRANSFER');
$r = call('POST', "{$invUrl}/transfers/{$t1Id}/recibir",
    ['items' => [['lot_id' => $lot1, 'cantidad_recibida' => 999]]], $token);
check($r['status'] === 409 && errorCode($r) === 'EXCEEDS_DESPACHADA',
    'recibir cantidad > despachada -> 409 EXCEEDS_DESPACHADA');
$r = call('POST', "{$invUrl}/transfers/{$t1Id}/recibir", ['items' => []], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'recibir items vacíos -> 400 VALIDATION_ERROR');

$r = call('POST', "{$invUrl}/transfers/{$t1Id}/recibir",
    ['items' => [['lot_id' => $lot1, 'cantidad_recibida' => 2]]], $token);
check($r['status'] === 200 && ($r['json']['data']['transferencia']['estado'] ?? '') === 'recibida',
    'recibir T1 lot1 cant 2 -> 200 estado recibida');
dbCheck(fn (): bool => stockVal($storeB, $lot1) === 2, 'DB: stock B lot1 = 2 tras recibir (RN-08, suma en destino)');

$r = call('POST', "{$invUrl}/transfers/{$t1Id}/cerrar", null, $token);
check($r['status'] === 200 && ($r['json']['data']['transferencia']['estado'] ?? '') === 'cerrada', 'cerrar T1 -> 200 estado cerrada');
$r = call('POST', "{$invUrl}/transfers/{$t1Id}/cerrar", null, $token);
check($r['status'] === 409 && errorCode($r) === 'TRANSFER_NOT_RECIBIDA',
    'cerrar T1 de nuevo -> 409 TRANSFER_NOT_RECIBIDA');

// --- 10. Transferencia T2: lote en cuarentena no se despacha; rechazo ---
$r = call('POST', "{$invUrl}/transfers",
    ['store_origen_id' => $storeA, 'store_destino_id' => $storeB,
        'items' => [['lot_id' => $lot2, 'cantidad' => 1]]], $token);
check($r['status'] === 201, 'crear T2 (lot2 cuarentena, sin key) -> 201');
$t2Id = (int)($r['json']['data']['transferencia']['id'] ?? 0);

$r = call('POST', "{$invUrl}/transfers/{$t2Id}/despachar", null, $token);
check($r['status'] === 409 && errorCode($r) === 'LOT_NOT_RELEASED',
    'despachar T2 con lote en cuarentena -> 409 LOT_NOT_RELEASED');
dbCheck(fn (): bool => stockVal($storeA, $lot2) === 4, 'DB: stock A lot2 = 4 tras despachar fallido (rollback)');

$r = call('POST', "{$invUrl}/transfers/{$t2Id}/rechazar", null, $token);
check($r['status'] === 200 && ($r['json']['data']['transferencia']['estado'] ?? '') === 'rechazada', 'rechazar T2 -> 200 estado rechazada');
$r = call('POST', "{$invUrl}/transfers/{$t2Id}/rechazar", null, $token);
check($r['status'] === 409 && errorCode($r) === 'TRANSFER_NOT_PENDING',
    'rechazar T2 de nuevo -> 409 TRANSFER_NOT_PENDING');

// --- 11. Incidentes con doble autorización (RF-046, RF-047) ---
$r = call('POST', "{$invUrl}/incidents",
    ['store_id' => $storeA, 'lot_id' => $lot1, 'consumo_declarado' => 999], $token);
check($r['status'] === 409 && errorCode($r) === 'EXCEEDS_STOCK', 'incidente consumo > stock -> 409 EXCEEDS_STOCK');
$r = call('POST', "{$invUrl}/incidents",
    ['store_id' => 99999999, 'lot_id' => $lot1, 'consumo_declarado' => 1], $token);
check($r['status'] === 404 && errorCode($r) === 'NOT_FOUND', 'incidente store inexistente -> 404 NOT_FOUND');
$r = call('POST', "{$invUrl}/incidents",
    ['store_id' => $storeA, 'lot_id' => 99999999, 'consumo_declarado' => 1], $token);
check($r['status'] === 404 && errorCode($r) === 'NOT_FOUND', 'incidente lote inexistente -> 404 NOT_FOUND');

$r = call('POST', "{$invUrl}/incidents",
    ['store_id' => $storeA, 'lot_id' => $lot1, 'consumo_declarado' => 1], $token);
check($r['status'] === 201 && ($r['json']['data']['estado'] ?? '') === 'abierto'
    && (int)($r['json']['data']['stock_registrado'] ?? 0) === 4,
    'crear incidente (consumo 1, stock 4) -> 201 abierto con stock_registrado 4');
$inc1 = (int)($r['json']['data']['id'] ?? 0);
check($inc1 > 0, 'incidente id > 0');

$r = call('POST', "{$invUrl}/incidents/{$inc1}/ajustar", null, $token);
check($r['status'] === 409 && errorCode($r) === 'DOUBLE_AUTH_REQUIRED',
    'ajustar por el mismo proponente -> 409 DOUBLE_AUTH_REQUIRED');
$r = call('POST', "{$invUrl}/incidents/{$inc1}/ajustar", null, $fixToken);
check($r['status'] === 403, 'ajustar sin permiso inventory.adjust -> 403');
$r = call('POST', "{$invUrl}/incidents/{$inc1}/ajustar", null, $token2);
check($r['status'] === 200 && ($r['json']['data']['estado'] ?? '') === 'ajustado',
    'ajustar por segundo usuario (doble autorización) -> 200 ajustado');
dbCheck(fn (): bool => stockVal($storeA, $lot1) === 3, 'DB: stock A lot1 = 3 tras ajuste (4-1)');
dbCheck(fn (): bool => (int)dbVal(
    "SELECT COUNT(*) FROM inventory_movements
     WHERE lot_id = ? AND tipo = 'ajuste_baja' AND signo = -1
       AND ref_tipo = 'incidente' AND ref_id = ? AND proponente_id <> autorizador_id",
    [$lot1, $inc1]
) === 1, 'DB: movimiento ajuste_baja -1 ref incidente con proponente <> autorizador');

$r = call('POST', "{$invUrl}/incidents/{$inc1}/ajustar", null, $token2);
check($r['status'] === 409 && errorCode($r) === 'INCIDENT_NOT_OPEN',
    'ajustar incidente ya ajustado -> 409 INCIDENT_NOT_OPEN');

$r = call('POST', "{$invUrl}/incidents",
    ['store_id' => $storeA, 'lot_id' => $lot1, 'consumo_declarado' => 1], $token);
check($r['status'] === 201, 'crear 2º incidente (stock 3) -> 201');
$inc2 = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$invUrl}/incidents/{$inc2}/descartar", null, $token);
check($r['status'] === 200 && ($r['json']['data']['estado'] ?? '') === 'descartado',
    'descartar 2º incidente -> 200 descartado');
$r = call('POST', "{$invUrl}/incidents/{$inc2}/descartar", null, $token);
check($r['status'] === 409 && errorCode($r) === 'INCIDENT_NOT_OPEN',
    'descartar incidente ya descartado -> 409 INCIDENT_NOT_OPEN');
$r = call('POST', "{$invUrl}/incidents/99999999/descartar", null, $token);
check($r['status'] === 404 && errorCode($r) === 'NOT_FOUND', 'descartar incidente inexistente -> 404 NOT_FOUND');
$r = call('GET', "{$invUrl}/incidents?estado=ajustado", null, $token);
check($r['status'] === 200 && in_array($inc1, array_column($r['json']['data'] ?? [], 'id'), true),
    'GET incidents?estado=ajustado filtra y contiene incidente 1');

// --- 12. Reservas de stock (RF-044, DB-P01) ---
$r = call('POST', "{$invUrl}/reservations",
    ['store_id' => $storeA, 'product_id' => $prodId, 'qty' => 0], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'reserva qty 0 -> 400 VALIDATION_ERROR');
$r = call('POST', "{$invUrl}/reservations",
    ['store_id' => 99999999, 'product_id' => $prodId, 'qty' => 1], $token);
check($r['status'] === 404 && errorCode($r) === 'NOT_FOUND', 'reserva store inexistente -> 404 NOT_FOUND');
$r = call('POST', "{$invUrl}/reservations",
    ['store_id' => $storeA, 'product_id' => 99999999, 'qty' => 1], $token);
check($r['status'] === 404 && errorCode($r) === 'NOT_FOUND', 'reserva producto inexistente -> 404 NOT_FOUND');
$r = call('POST', "{$invUrl}/reservations",
    ['store_id' => $storeA, 'product_id' => $prodId, 'qty' => 9999], $token);
check($r['status'] === 409 && errorCode($r) === 'INSUFFICIENT_STOCK', 'reserva qty > stock -> 409 INSUFFICIENT_STOCK');

$r1res = call('POST', "{$invUrl}/reservations",
    ['store_id' => $storeA, 'product_id' => $prodId, 'qty' => 1], $token);
check($r1res['status'] === 201 && ($r1res['json']['data']['status'] ?? '') === 'pending',
    'crear reserva r1 -> 201 status pending');
$r1Id = (int)($r1res['json']['data']['id'] ?? 0);
check($r1Id > 0, 'reserva r1 id > 0');

$r = call('GET', "{$invUrl}/reservations?status=pending", null, $token);
check($r['status'] === 200 && in_array($r1Id, array_column($r['json']['data'] ?? [], 'id'), true),
    'GET reservations?status=pending contiene r1');
$r = call('POST', "{$invUrl}/reservations/{$r1Id}/confirmar", null, $token);
check($r['status'] === 200, 'confirmar r1 -> 200');
$r = call('POST', "{$invUrl}/reservations/{$r1Id}/confirmar", null, $token);
check($r['status'] === 409 && errorCode($r) === 'RESERVATION_NOT_PENDING',
    'confirmar r1 de nuevo -> 409 RESERVATION_NOT_PENDING');

$r2res = call('POST', "{$invUrl}/reservations",
    ['store_id' => $storeA, 'product_id' => $prodId, 'qty' => 1], $token);
check($r2res['status'] === 201, 'crear reserva r2 -> 201');
$r2Id = (int)($r2res['json']['data']['id'] ?? 0);
$r = call('POST', "{$invUrl}/reservations/{$r2Id}/cancelar", null, $token);
check($r['status'] === 200, 'cancelar r2 -> 200');
$r = call('POST', "{$invUrl}/reservations/{$r2Id}/cancelar", null, $token);
check($r['status'] === 409 && errorCode($r) === 'RESERVATION_NOT_PENDING',
    'cancelar r2 de nuevo -> 409 RESERVATION_NOT_PENDING');
$r = call('POST', "{$invUrl}/reservations/99999999/confirmar", null, $token);
check($r['status'] === 404 && errorCode($r) === 'NOT_FOUND', 'confirmar reserva inexistente -> 404 NOT_FOUND');

// --- 13. Estado final de stock (RN-02: sin negativos, FEFO) ---
dbCheck(fn (): bool => stockVal($storeA, $lot1) === 3, 'DB final: stock A lot1 = 3');
dbCheck(fn (): bool => stockVal($storeA, $lot2) === 4, 'DB final: stock A lot2 = 4');
dbCheck(fn (): bool => stockVal($storeB, $lot1) === 2, 'DB final: stock B lot1 = 2');
dbCheck(fn (): bool => (int)dbVal(
    'SELECT COUNT(*) FROM inventory_stock WHERE store_id IN (?, ?) AND stock_available < 0',
    [$storeA, $storeB]
) === 0, 'DB final: sin stock negativo (RN-02)');

// --- 14. Limpieza de fixtures (borrado lógico 204; producto queda con movimientos) ---
$r = call('DELETE', "{$opsUrl}/stores/{$storeA}", null, $token);
check($r['status'] === 204, 'DELETE store A fixture -> 204');
$r = call('DELETE', "{$opsUrl}/stores/{$storeB}", null, $token);
check($r['status'] === 204, 'DELETE store B fixture -> 204');
$r = call('DELETE', "{$catUrl}/suppliers/{$provId}", null, $token);
check($r['status'] === 204, 'DELETE supplier fixture -> 204');

echo "\nRESULTADO: " . ($fail === 0 ? 'PASS' : "FAIL ({$fail})") . "\n";
exit($fail === 0 ? 0 : 1);
