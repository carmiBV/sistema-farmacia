<?php
declare(strict_types=1);

/**
 * CP-INT-05 - Integracion de Recetas Medicas y Libro Oficial de Controlados
 * (E2E, HTTP + BD).
 *
 * Flujo de dispensacion con receta (rx_prescriptions + items con CAS de saldo),
 * asociacion prescriptor/paciente, y AFECTACION AUTOMATICA del Libro Oficial
 * (RF-055: cada movimiento de un producto controlado genera asiento en
 * ctrl_ledger_entries en la misma transaccion) con saldos encadenados en
 * ctrl_balances e inmutabilidad (append-only, conciliacion RNF-024).
 *
 * Requiere: servidor corriendo y seed previo con tools/init_admin.php.
 * Uso: php tools/verify_int05.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_int05.php <base-url>\n");
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

$api = "{$base}/api/v1";
$r = call('POST', "{$api}/auth/login", ['usuario' => $adminUser, 'password' => $adminPass]);
$token = (string)($r['json']['data']['token'] ?? '');
check($token !== '', 'sesion iniciada (login -> token)');
$userId = (int)dbVal('SELECT id FROM auth_users WHERE usuario = ?', [$adminUser]);
$sufijo = substr(md5((string)random_int(0, PHP_INT_MAX)), 0, 8);

// --- fixtures ---
$r = call('POST', "{$api}/catalog/prescribers", ['identificacion' => "MP{$sufijo}", 'nombre' => "Dr Int5 {$sufijo}", 'especialidad' => 'General'], $token);
$prescId = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$api}/catalog/patients", ['identificacion' => "PA{$sufijo}", 'nombre' => "Paciente Int5 {$sufijo}", 'fecha_nacimiento' => '1990-01-01'], $token);
$pacId = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$api}/catalog/products", [
    'sku' => "I5{$sufijo}", 'nombre' => "Controlado Int5 {$sufijo}", 'principio_activo' => 'Test',
    'presentacion' => 'Caja', 'concentracion' => '2mg', 'condicion_venta' => 'controlado',
], $token);
$prodId = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$api}/catalog/suppliers", ['identificacion' => "IN5{$sufijo}", 'nombre' => "Prov Int5 {$sufijo}"], $token);
$provId = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$api}/ops/stores", ['codigo' => "C5{$sufijo}", 'nombre' => "Suc Int5 {$sufijo}"], $token);
$storeId = (int)($r['json']['data']['id'] ?? 0);
check($prescId > 0 && $pacId > 0 && $prodId > 0 && $provId > 0 && $storeId > 0,
    'fixtures creados (prescriptor, paciente, producto controlado, proveedor, sucursal)');

// --- 1. recepcion de controlado -> asiento AUTOMATICO (RF-055) ---
$r = call('POST', "{$api}/purchases/orders", [
    'numero' => "OC5{$sufijo}", 'supplier_id' => $provId,
    'items' => [['product_id' => $prodId, 'cantidad_pedida' => 10]],
], $token);
$ordenId = (int)($r['json']['data']['id'] ?? 0);
call('POST', "{$api}/purchases/orders/{$ordenId}/emitir", null, $token);
$r = call('POST', "{$api}/purchases/orders/{$ordenId}/receptions", [
    'items' => [['product_id' => $prodId, 'numero_lote' => "K5{$sufijo}", 'fecha_vencimiento' => '2027-12-31', 'cantidad' => 10]],
], $token);
$recId = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$api}/purchases/receptions/{$recId}/confirmar", ['store_id' => $storeId], $token);
check($r['status'] === 200, 'recepcion de controlado confirmada');
$lotId = (int)dbVal('SELECT id FROM inventory_lots WHERE numero_lote = ?', ["K5{$sufijo}"]);
check((int)dbVal(
    "SELECT COUNT(*) FROM ctrl_ledger_entries WHERE product_id = ? AND tipo = 'entrada' AND ref_tipo = 'recepcion' AND ref_id = ? AND cantidad = 10",
    [$prodId, $recId]
) === 1, 'RF-055: asiento AUTOMATICO de entrada en el libro por el movimiento del controlado');
check((int)dbVal('SELECT saldo FROM ctrl_balances WHERE store_id = ? AND product_id = ?', [$storeId, $prodId]) === 10,
    'ctrl_balances: saldo actualizado a 10');

call('POST', "{$api}/inventory/batches/{$lotId}/liberar", ['motivo' => 'Liberacion Int5'], $token);

// --- 2. receta con asociacion prescriptor/paciente ---
$r = call('POST', "{$api}/rx/prescriptions", [
    'prescriber_id' => $prescId, 'patient_id' => $pacId, 'fecha' => '2026-10-07',
    'numero_referencia' => "RX5{$sufijo}",
    'items' => [['product_id' => $prodId, 'cantidad_prescrita' => 5]],
], $token);
check($r['status'] === 201, 'receta registrada -> 201');
$rxId = (int)($r['json']['data']['prescripcion']['id'] ?? $r['json']['data']['id'] ?? 0);
$r = call('GET', "{$api}/rx/prescriptions/{$rxId}", null, $token);
$prescData = $r['json']['data']['prescripcion'] ?? [];
check((int)($prescData['prescriber_id'] ?? 0) === $prescId && (int)($prescData['patient_id'] ?? 0) === $pacId
    && ($prescData['prescriber_nombre'] ?? '') !== '' && ($prescData['patient_nombre'] ?? '') !== '',
    'asociacion medico prescriptor / paciente en la receta');
$rxItemId = (int)($r['json']['data']['items'][0]['id'] ?? 0);
check($rxItemId > 0, 'item de receta persistido');

// --- 3. dispensacion con CAS de saldo ---
$r = call('POST', "{$api}/rx/prescriptions/{$rxId}/dispensar", [
    'items' => [['rx_item_id' => $rxItemId, 'cantidad' => 2]],
], $token);
check($r['status'] === 200, 'dispensacion 2/5 -> 200');
check((int)dbVal('SELECT cantidad_dispensada FROM rx_prescription_items WHERE id = ?', [$rxItemId]) === 2
    && (int)dbVal('SELECT cantidad_prescrita - cantidad_dispensada AS s FROM rx_prescription_items WHERE id = ?', [$rxItemId]) === 3,
    'CAS de saldo: dispensada 2, saldo 3 (BD)');

$r = call('POST', "{$api}/rx/prescriptions/{$rxId}/dispensar", [
    'items' => [['rx_item_id' => $rxItemId, 'cantidad' => 99]],
], $token);
check($r['status'] === 409 && errorCode($r) === 'RX_SALDO_INSUFICIENTE', 'dispensar mas del saldo -> 409 RX_SALDO_INSUFICIENTE');
check((int)dbVal('SELECT cantidad_dispensada FROM rx_prescription_items WHERE id = ?', [$rxItemId]) === 2,
    'rollback verificado (la dispensacion rechazada no altera el saldo)');

// --- 4. ajuste con doble autorizacion -> asiento en el libro (RF-046/RF-055) ---
$r = call('POST', "{$api}/auth/users", ['usuario' => "ctl5_{$sufijo}", 'password' => "Pass5{$sufijo}123", 'roles' => ['admin']], $token);
$autorizadorId = (int)($r['json']['data']['id'] ?? 0);
check($autorizadorId > 0, 'usuario autorizador creado');

$r = call('POST', "{$api}/control/adjustments", [
    'store_id' => $storeId, 'product_id' => $prodId, 'lot_id' => $lotId,
    'cantidad' => 1, 'direccion' => 'baja', 'motivo' => 'Merma Int5', 'autorizador_id' => $userId,
], $token);
check($r['status'] === 409 && errorCode($r) === 'DOUBLE_AUTH_REQUIRED',
    'RF-046: autorizador == proponente -> 409 DOUBLE_AUTH_REQUIRED');

$r = call('POST', "{$api}/control/adjustments", [
    'store_id' => $storeId, 'product_id' => $prodId, 'lot_id' => $lotId,
    'cantidad' => 1, 'direccion' => 'baja', 'motivo' => 'Merma Int5', 'autorizador_id' => $autorizadorId,
], $token);
check($r['status'] === 201, 'ajuste con doble autorizacion -> 201');
check((int)dbVal(
    "SELECT COUNT(*) FROM ctrl_ledger_entries WHERE product_id = ? AND tipo = 'ajuste' AND ref_tipo = 'ajuste' AND cantidad = 1",
    [$prodId]
) === 1, 'RF-055: asiento de ajuste automatico en el libro');
check((int)dbVal('SELECT saldo FROM ctrl_balances WHERE store_id = ? AND product_id = ?', [$storeId, $prodId]) === 9,
    'ctrl_balances: saldo 10 -> 9 tras el ajuste (cadena de saldo_resultante)');

// --- 5. inmutabilidad del libro y conciliacion (RNF-024) ---
$totalAsientos = (int)dbVal('SELECT COUNT(*) FROM ctrl_ledger_entries WHERE product_id = ?', [$prodId]);
check($totalAsientos === 2, 'libro append-only: 2 asientos acumulados (entrada + ajuste), ninguno borrado');
$r = call('GET', "{$api}/control/ledger?product_id={$prodId}", null, $token);
check($r['status'] === 200 && count($r['json']['data'] ?? []) === 2, 'libro consultable completo via API');
$r = call('DELETE', "{$api}/control/ledger/1", null, $token);
check($r['status'] === 404, 'el libro no expone DELETE (inmutable)');

$r = call('GET', "{$api}/control/reconciliation", null, $token);
check($r['status'] === 200 && (int)($r['json']['data']['desbalanceados'] ?? -1) === 0,
    'RNF-024: conciliacion sin desbalanceados');
$r = call('GET', "{$api}/control/balances?product_id={$prodId}", null, $token);
$bal = $r['json']['data'][0] ?? [];
check((int)($bal['saldo'] ?? -1) === 9 && (int)($bal['saldo_calculado'] ?? -1) === 9
    && ($bal['conciliado'] ?? null) === true, 'saldos conciliados (saldo = saldo_calculado = 9)');

echo 'RESULTADO: ' . ($fail === 0 ? 'PASS' : "FAIL ({$fail})") . "\n";
exit($fail === 0 ? 0 : 1);
