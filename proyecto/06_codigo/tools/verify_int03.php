<?php
declare(strict_types=1);

/**
 * CP-INT-03 - Integracion de Compras, Recepciones y Generacion de Lotes FEFO
 * (E2E, HTTP + BD).
 *
 * Ciclo de punta a punta: orden de compra (borrador -> emitida) -> recepcion
 * fisica con lotes de fabrica (numero_lote + fecha_vencimiento) -> confirmacion
 * que crea los lotes en cuarentena de forma AUTOMATICA y OBLIGATORIA con
 * trazabilidad reception_item_id NOT NULL (RF-031/RF-032) -> stock + kardex +
 * outbox (RN-07/CA-10) -> cierre automatico de la orden al 100% (RN-07).
 * RN-09: idempotencia por clave. FEFO: el lote creado expone fecha_vencimiento.
 *
 * Requiere: servidor corriendo y seed previo con tools/init_admin.php.
 * Uso: php tools/verify_int03.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_int03.php <base-url>\n");
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

// --- fixtures: proveedor, producto, sucursal ---
$r = call('POST', "{$api}/catalog/suppliers", ['identificacion' => "IN3{$sufijo}", 'nombre' => "Prov Int {$sufijo}"], $token);
$provId = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$api}/catalog/products", [
    'sku' => "I3{$sufijo}", 'nombre' => "Prod Int3 {$sufijo}", 'principio_activo' => 'Test',
    'presentacion' => 'Caja', 'concentracion' => '5mg', 'condicion_venta' => 'libre',
], $token);
$prodId = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$api}/ops/stores", ['codigo' => "S3{$sufijo}", 'nombre' => "Suc Int3 {$sufijo}"], $token);
$storeId = (int)($r['json']['data']['id'] ?? 0);
check($provId > 0 && $prodId > 0 && $storeId > 0, 'fixtures creados (proveedor, producto, sucursal)');

// --- 1. orden de compra ---
$r = call('POST', "{$api}/purchases/orders", [
    'numero' => "OC3{$sufijo}", 'supplier_id' => $provId,
    'items' => [['product_id' => $prodId, 'cantidad_pedida' => 10]],
], $token);
check($r['status'] === 201, 'orden en borrador -> 201');
$ordenId = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$api}/purchases/orders/{$ordenId}/receptions", [
    'idempotency_key' => "no-emitida-{$sufijo}",
    'items' => [['product_id' => $prodId, 'numero_lote' => "X{$sufijo}", 'fecha_vencimiento' => '2027-06-30', 'cantidad' => 2]],
], $token);
check($r['status'] === 409 && errorCode($r) === 'ORDER_NOT_ISSUED', 'RN-07: recepcion sobre borrador -> 409 ORDER_NOT_ISSUED');

$r = call('POST', "{$api}/purchases/orders/{$ordenId}/emitir", null, $token);
check($r['status'] === 200, 'emitir orden -> 200 (borrador -> emitida)');
check((string)dbVal('SELECT estado FROM purchase_orders WHERE id = ?', [$ordenId]) === 'emitida',
    'orden en estado emitida (BD)');

// --- 2. recepcion parcial con lote de fabrica ---
$claveIdem = "int3-{$sufijo}";
$r = call('POST', "{$api}/purchases/orders/{$ordenId}/receptions", [
    'idempotency_key' => $claveIdem,
    'items' => [['product_id' => $prodId, 'numero_lote' => "L3{$sufijo}", 'fecha_vencimiento' => '2027-06-30', 'cantidad' => 4]],
], $token);
check($r['status'] === 201 && (($r['json']['meta']['idempotent_reused'] ?? null) === false),
    'recepcion 4/10 -> 201 (idempotent_reused=false)');
$recId = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$api}/purchases/orders/{$ordenId}/receptions", [
    'idempotency_key' => $claveIdem,
    'items' => [['product_id' => $prodId, 'numero_lote' => "L3{$sufijo}", 'fecha_vencimiento' => '2027-06-30', 'cantidad' => 4]],
], $token);
check($r['status'] === 200 && (($r['json']['meta']['idempotent_reused'] ?? null) === true),
    'RN-09: misma idempotency_key -> 200 reused=true (sin duplicar)');

$r = call('POST', "{$api}/purchases/orders/{$ordenId}/receptions", [
    'items' => [['product_id' => $prodId, 'numero_lote' => "L3X{$sufijo}", 'fecha_vencimiento' => '2027-06-30', 'cantidad' => 99]],
], $token);
check($r['status'] === 400 && errorCode($r) === 'EXCEEDS_PENDING',
    'exceso sobre lo pendiente -> 400 EXCEEDS_PENDING (status real del modulo de compras)');

$recItemId = (int)dbVal('SELECT id FROM purchase_reception_items WHERE reception_id = ? LIMIT 1', [$recId]);
check($recItemId > 0, 'item de recepcion persistido');
check(dbVal('SELECT lot_id FROM purchase_reception_items WHERE id = ?', [$recItemId]) === null,
    'RN-07: el stock NO se mueve hasta confirmar (item con lot_id NULL)');

// --- 3. confirmacion: creacion AUTOMATICA de lote con trazabilidad estricta ---
$r = call('POST', "{$api}/purchases/receptions/{$recId}/confirmar", ['store_id' => $storeId], $token);
check($r['status'] === 200, 'confirmar recepcion -> 200 (T-3)');

$lotId = (int)dbVal('SELECT lot_id FROM purchase_reception_items WHERE id = ?', [$recItemId]);
check($lotId > 0, 'item de recepcion vinculado al lote (1:1)');
check((int)dbVal('SELECT COUNT(*) FROM inventory_lots WHERE id = ? AND reception_item_id = ?', [$lotId, $recItemId]) === 1,
    'RF-031: inventory_lots.reception_item_id NOT NULL (trazabilidad estricta)');
check((string)dbVal('SELECT numero_lote FROM inventory_lots WHERE id = ?', [$lotId]) === "L3{$sufijo}"
    && (string)dbVal('SELECT fecha_vencimiento FROM inventory_lots WHERE id = ?', [$lotId]) === '2027-06-30',
    'lote con numero de fabrica y fecha de vencimiento (FEFO)');
check((string)dbVal('SELECT estado FROM inventory_lots WHERE id = ?', [$lotId]) === 'cuarentena',
    'RF-032: lote creado en cuarentena');
check((int)dbVal('SELECT stock_available FROM inventory_stock WHERE store_id = ? AND lot_id = ?', [$storeId, $lotId]) === 4,
    'RN-07/RF-032: inventory_stock incrementado (4 u)');
check((int)dbVal(
    "SELECT COUNT(*) FROM inventory_movements WHERE store_id = ? AND lot_id = ? AND tipo = 'entrada' AND signo = 1 AND ref_tipo = 'recepcion' AND ref_id = ?",
    [$storeId, $lotId, $recId]
) === 1, 'CA-10: kardex append-only (entrada signo +1, ref recepcion)');
check((int)dbVal('SELECT cantidad_recibida FROM purchase_order_items WHERE order_id = ? LIMIT 1', [$ordenId]) === 4,
    'cantidad_recibida acumulada en la orden (4/10)');
check((int)dbVal("SELECT COUNT(*) FROM outbox_events WHERE tipo_evento = 'recepcion.confirmada' AND agregado_id = ? AND estado = 'pendiente'", [$recId]) === 1,
    'decision 16: outbox recepcion.confirmada pendiente');

$r = call('POST', "{$api}/purchases/receptions/{$recId}/confirmar", ['store_id' => $storeId], $token);
check($r['status'] === 409 && errorCode($r) === 'RECEPTION_NOT_PENDING', 'doble confirmar -> 409 RECEPTION_NOT_PENDING');

// --- 4. segunda recepcion completa -> cierre automatico de la orden ---
$r = call('POST', "{$api}/purchases/orders/{$ordenId}/receptions", [
    'items' => [['product_id' => $prodId, 'numero_lote' => "L3B{$sufijo}", 'fecha_vencimiento' => '2027-09-30', 'cantidad' => 6]],
], $token);
$rec2Id = (int)($r['json']['data']['id'] ?? 0);
check($rec2Id > 0 && $rec2Id !== $recId, 'segunda recepcion 6/10 -> 201');
$r = call('POST', "{$api}/purchases/receptions/{$rec2Id}/confirmar", ['store_id' => $storeId], $token);
check($r['status'] === 200, 'confirmar segunda recepcion -> 200');
check((int)dbVal('SELECT cantidad_recibida FROM purchase_order_items WHERE order_id = ? LIMIT 1', [$ordenId]) === 10,
    'cantidad_recibida completa (10/10)');
check((string)dbVal('SELECT estado FROM purchase_orders WHERE id = ?', [$ordenId]) === 'recibida',
    'RN-07: cierre automatico de la orden al 100% (estado recibida)');

// --- 5. FEFO: los lotes creados son consultables con su vencimiento ---
$r = call('GET', "{$api}/inventory/batches?product_id={$prodId}&estado=cuarentena", null, $token);
$lotes = array_filter($r['json']['data'] ?? [], static fn ($l) => (int)$l['id'] === $lotId);
check(count($lotes) === 1, 'FEFO: lote consultable en /inventory/batches');
$loteArr = array_values($lotes)[0] ?? [];
check(($loteArr['fecha_vencimiento'] ?? '') === '2027-06-30' && ($loteArr['numero_lote'] ?? '') === "L3{$sufijo}",
    'FEFO: fecha de vencimiento expuesta para la politica de vencimiento mas proximo');

echo 'RESULTADO: ' . ($fail === 0 ? 'PASS' : "FAIL ({$fail})") . "\n";
exit($fail === 0 ? 0 : 1);
