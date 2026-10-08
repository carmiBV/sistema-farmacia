<?php
declare(strict_types=1);

/**
 * CP-BACK-07 — Recetas médicas (RF-052 registro, RF-053 saldo global, RF-003
 * prescriptores, RN-13): CRUD de recetas, validación de prescriptor/paciente/
 * producto (condición de venta bajo receta), listados con filtros y
 * dispensación con CAS sobre el saldo (sin stock/kárdex: Opción A, CP-08).
 *
 * Requiere: servidor corriendo y seed previo con tools/init_admin.php
 * (ADMIN_USER / ADMIN_PASSWORD + permiso rx.manage).
 * Conexión directa a BD vía .env (solo SELECT).
 *
 * Uso: php tools/verify_cp_back07.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_cp_back07.php <base-url>\n");
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

/** Saldo/versión de un ítem de receta. @return array{disp:int,saldo:int,ver:int} */
function itemEstado(int $itemId): array
{
    $row = dbRows(
        'SELECT cantidad_dispensada, cantidad_prescrita, version FROM rx_prescription_items WHERE id = ?',
        [$itemId]
    )[0] ?? [];
    $disp = (int)($row['cantidad_dispensada'] ?? -1);
    $presc = (int)($row['cantidad_prescrita'] ?? -1);
    return ['disp' => $disp, 'saldo' => $presc - $disp, 'ver' => (int)($row['version'] ?? -1)];
}

$sufijo = substr(md5((string)random_int(0, PHP_INT_MAX)), 0, 8);
$authUrl = "{$base}/api/v1/auth";
$catUrl = "{$base}/api/v1/catalog";
$rxUrl = "{$base}/api/v1/rx/prescriptions";
$passFix = 'Fix!Pass123';
$hoy = date('Y-m-d');

// --- 1. Autenticación: 401 sin token ---
$r = call('GET', "{$rxUrl}");
check($r['status'] === 401 && errorCode($r) === 'UNAUTHENTICATED', 'GET /rx/prescriptions sin token -> 401 UNAUTHENTICATED');
$r = call('GET', "{$rxUrl}/1");
check($r['status'] === 401, 'GET /rx/prescriptions/1 sin token -> 401');
$r = call('POST', "{$rxUrl}", []);
check($r['status'] === 401, 'POST /rx/prescriptions sin token -> 401');
$r = call('POST', "{$rxUrl}/1/dispensar", []);
check($r['status'] === 401, 'POST /rx/prescriptions/1/dispensar sin token -> 401');

// --- 2. Login admin y usuario sin roles ---
$r = call('POST', "{$authUrl}/login", ['usuario' => $adminUser, 'password' => $adminPass]);
check($r['status'] === 200, 'login admin -> 200');
$token = (string)($r['json']['data']['token'] ?? '');
$permAdmin = $r['json']['data']['user']['permisos'] ?? [];
check(in_array('rx.manage', $permAdmin, true), 'payload admin incluye rx.manage (seed CP-BACK-07)');

$r = call('POST', "{$authUrl}/users", ['usuario' => "rxfix_{$sufijo}", 'password' => $passFix, 'roles' => []], $token);
check($r['status'] === 201, 'crear usuario sin roles -> 201');
$r = call('POST', "{$authUrl}/login", ['usuario' => "rxfix_{$sufijo}", 'password' => $passFix]);
check($r['status'] === 200, 'login usuario sin roles -> 200');
$fixToken = (string)($r['json']['data']['token'] ?? '');

// --- 3. RBAC: token sin rx.manage lee pero no escribe ---
$r = call('GET', "{$rxUrl}", null, $fixToken);
check($r['status'] === 200, 'GET prescriptions con token sin permiso -> 200 (lectura con token)');
$r = call('GET', "{$rxUrl}/1", null, $fixToken);
check($r['status'] === 200 || $r['status'] === 404, 'GET prescriptions/1 con token sin permiso -> 200/404 (no 403)');
$r = call('POST', "{$rxUrl}", [], $fixToken);
check($r['status'] === 403 && errorCode($r) === 'FORBIDDEN', 'POST prescriptions sin permiso -> 403 FORBIDDEN');
$r = call('POST', "{$rxUrl}/1/dispensar", [], $fixToken);
check($r['status'] === 403, 'POST dispensar sin permiso -> 403');

// --- 4. Fixtures: paciente, prescriptor, productos ---
$r = call('POST', "{$catUrl}/patients",
    ['identificacion' => "P{$sufijo}", 'nombre' => "Paciente CP7 {$sufijo}"], $token);
check($r['status'] === 201 && (int)($r['json']['data']['id'] ?? 0) > 0, 'POST patient fixture -> 201 con id');
$patId = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$catUrl}/patients",
    ['identificacion' => "PA{$sufijo}", 'nombre' => "Paciente Anon CP7 {$sufijo}"], $token);
check($r['status'] === 201, 'POST patient2 fixture -> 201');
$pat2Id = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$catUrl}/prescribers",
    ['identificacion' => "M{$sufijo}", 'nombre' => "Medico CP7 {$sufijo}", 'especialidad' => 'General'], $token);
check($r['status'] === 201 && (int)($r['json']['data']['id'] ?? 0) > 0, 'POST prescriber fixture -> 201 con id');
$precId = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$catUrl}/prescribers",
    ['identificacion' => "MA{$sufijo}", 'nombre' => "Medico Inactivo CP7 {$sufijo}"], $token);
check($r['status'] === 201, 'POST prescriber2 fixture -> 201');
$prec2Id = (int)($r['json']['data']['id'] ?? 0);

/** @return int id del producto fixture */
function crearProducto(string $catUrl, string $suf, string $sku, string $condicion, string $token): int
{
    $r = call('POST', "{$catUrl}/products", [
        'sku' => $sku,
        'nombre' => "Prod CP7 {$suf} {$condicion}",
        'principio_activo' => 'Principio CP7',
        'presentacion' => 'Caja x 10',
        'concentracion' => '500 mg',
        'condicion_venta' => $condicion,
    ], $token);
    check($r['status'] === 201 && (int)($r['json']['data']['id'] ?? 0) > 0,
        "POST product {$condicion} {$sku} -> 201 con id");
    return (int)($r['json']['data']['id'] ?? 0);
}

$prodRx = crearProducto($catUrl, $sufijo, "SKUR{$sufijo}", 'receta', $token);
$prodRx2 = crearProducto($catUrl, $sufijo, "SKUR2{$sufijo}", 'receta', $token);
$prodLibre = crearProducto($catUrl, $sufijo, "SKUL{$sufijo}", 'libre', $token);
$prodInac = crearProducto($catUrl, $sufijo, "SKUI{$sufijo}", 'receta', $token);
$r = call('DELETE', "{$catUrl}/products/{$prodInac}", null, $token);
check($r['status'] === 204, 'DELETE product fixture (queda inactivo) -> 204');

// Prescriptor y paciente inactivos para 409 (borrado lógico)
$r = call('DELETE', "{$catUrl}/prescribers/{$prec2Id}", null, $token);
check($r['status'] === 204, 'DELETE prescriber2 fixture -> 204 (estado inactivo)');
$r = call('DELETE', "{$catUrl}/patients/{$pat2Id}", null, $token);
check($r['status'] === 204, 'DELETE patient2 fixture -> 204 (anonimizado)');

$crearOk = [
    'prescriber_id' => $precId, 'patient_id' => $patId, 'fecha' => $hoy,
    'numero_referencia' => "RX{$sufijo}",
    'items' => [['product_id' => $prodRx, 'cantidad_prescrita' => 5]],
];

// --- 5. Validación de creación (400) ---
$r = call('POST', "{$rxUrl}", [], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'POST body vacio -> 400 VALIDATION_ERROR');
$r = call('POST', "{$rxUrl}", 'abc', $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', "POST cuerpo crudo 'abc' -> 400 VALIDATION_ERROR");

$bad = $crearOk;
unset($bad['patient_id']);
$r = call('POST', "{$rxUrl}", $bad, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'POST sin patient_id -> 400');

$bad = $crearOk;
$bad['fecha'] = '2026-13-45';
$r = call('POST', "{$rxUrl}", $bad, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', "POST fecha '2026-13-45' -> 400");
$bad = $crearOk;
$bad['fecha'] = 'no-fecha';
$r = call('POST', "{$rxUrl}", $bad, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', "POST fecha 'no-fecha' -> 400");

$bad = $crearOk;
$bad['items'] = [];
$r = call('POST', "{$rxUrl}", $bad, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'POST items vacios -> 400');
$bad = $crearOk;
$bad['items'] = [['product_id' => $prodRx, 'cantidad_prescrita' => 5],
    ['product_id' => $prodRx, 'cantidad_prescrita' => 1]];
$r = call('POST', "{$rxUrl}", $bad, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'POST producto duplicado en items -> 400');
$bad = $crearOk;
$bad['items'] = [['product_id' => 'abc', 'cantidad_prescrita' => 1]];
$r = call('POST', "{$rxUrl}", $bad, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', "POST product_id 'abc' -> 400");
$bad = $crearOk;
$bad['items'] = [['product_id' => $prodRx, 'cantidad_prescrita' => 0]];
$r = call('POST', "{$rxUrl}", $bad, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'POST cantidad_prescrita 0 -> 400');
$bad = $crearOk;
$bad['items'] = [];
for ($i = 0; $i <= 500; $i++) {
    $bad['items'][] = ['product_id' => $prodRx, 'cantidad_prescrita' => 1];
}
$r = call('POST', "{$rxUrl}", $bad, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'POST con 501 items -> 400');

// --- 6. Referencias inexistentes (404) ---
$bad = $crearOk;
$bad['prescriber_id'] = 99999999;
$r = call('POST', "{$rxUrl}", $bad, $token);
check($r['status'] === 404 && errorCode($r) === 'PRESCRIBER_NOT_FOUND', 'POST prescriber_id inexistente -> 404 PRESCRIBER_NOT_FOUND');
$bad = $crearOk;
$bad['patient_id'] = 99999999;
$r = call('POST', "{$rxUrl}", $bad, $token);
check($r['status'] === 404 && errorCode($r) === 'PATIENT_NOT_FOUND', 'POST patient_id inexistente -> 404 PATIENT_NOT_FOUND');
$bad = $crearOk;
$bad['items'] = [['product_id' => 99999999, 'cantidad_prescrita' => 1]];
$r = call('POST', "{$rxUrl}", $bad, $token);
check($r['status'] === 404 && errorCode($r) === 'PRODUCT_NOT_FOUND', 'POST product_id inexistente -> 404 PRODUCT_NOT_FOUND');

// --- 7. Estados y condición de venta (409) ---
$bad = $crearOk;
$bad['prescriber_id'] = $prec2Id;
$r = call('POST', "{$rxUrl}", $bad, $token);
check($r['status'] === 409 && errorCode($r) === 'PRESCRIBER_NOT_ACTIVE', 'POST prescriptor inactivo -> 409 PRESCRIBER_NOT_ACTIVE');
$bad = $crearOk;
$bad['patient_id'] = $pat2Id;
$r = call('POST', "{$rxUrl}", $bad, $token);
check($r['status'] === 409 && errorCode($r) === 'PATIENT_NOT_ACTIVE', 'POST paciente anonimizado -> 409 PATIENT_NOT_ACTIVE');
$bad = $crearOk;
$bad['items'] = [['product_id' => $prodInac, 'cantidad_prescrita' => 1]];
$r = call('POST', "{$rxUrl}", $bad, $token);
check($r['status'] === 409 && errorCode($r) === 'PRODUCT_INACTIVE', 'POST producto inactivo -> 409 PRODUCT_INACTIVE');
$bad = $crearOk;
$bad['items'] = [['product_id' => $prodLibre, 'cantidad_prescrita' => 1]];
$r = call('POST', "{$rxUrl}", $bad, $token);
check($r['status'] === 409 && errorCode($r) === 'PRODUCT_NOT_UNDER_RX', 'POST producto libre -> 409 PRODUCT_NOT_UNDER_RX');

// --- 8. Creación correcta (RF-052) ---
$r = call('POST', "{$rxUrl}", $crearOk, $token);
$rx1 = $r['json']['data'] ?? [];
$rx1Id = (int)($rx1['prescripcion']['id'] ?? 0);
check($r['status'] === 201 && $rx1Id > 0, 'POST receta valida -> 201 con prescripcion.id');
check((int)($rx1['prescripcion']['prescriber_id'] ?? 0) === $precId
    && (int)($rx1['prescripcion']['patient_id'] ?? 0) === $patId
    && (string)($rx1['prescripcion']['fecha'] ?? '') === $hoy
    && (string)($rx1['prescripcion']['numero_referencia'] ?? '') === "RX{$sufijo}",
    'respuesta refleja prescriber_id, patient_id, fecha y numero_referencia');
$it1 = $rx1['items'][0] ?? [];
$item1Id = (int)($it1['id'] ?? 0);
check(count($rx1['items'] ?? []) === 1
    && (int)($it1['product_id'] ?? 0) === $prodRx
    && (int)($it1['cantidad_prescrita'] ?? 0) === 5
    && (int)($it1['cantidad_dispensada'] ?? -1) === 0
    && (int)($it1['saldo'] ?? -1) === 5
    && (int)($it1['version'] ?? -1) === 0,
    'items[0]: producto, prescrita 5, dispensada 0, saldo 5, version 0');

dbCheck(fn (): bool => (int)dbVal(
    'SELECT COUNT(*) FROM rx_prescriptions WHERE id = ? AND prescriber_id = ? AND patient_id = ? AND fecha = ?',
    [$rx1Id, $precId, $patId, $hoy]
) === 1, 'DB: receta insertada con prescriber/patient/fecha correctos');
dbCheck(fn (): bool => (int)dbVal(
    'SELECT COUNT(*) FROM rx_prescription_items WHERE prescription_id = ? AND product_id = ? AND cantidad_dispensada = 0',
    [$rx1Id, $prodRx]
) === 1, 'DB: item insertado con cantidad_dispensada = 0');

// --- 9. Listados y filtros ---
$r = call('GET', "{$rxUrl}", null, $token);
check($r['status'] === 200 && isset($r['json']['meta']['total']), 'GET prescriptions -> 200 con meta.total');
$r = call('GET', "{$rxUrl}?patient_id={$patId}", null, $token);
$ids = array_column($r['json']['data'] ?? [], 'id');
check($r['status'] === 200 && in_array($rx1Id, $ids, true)
    && array_diff($ids, [$rx1Id]) === [],
    'GET ?patient_id filtra solo recetas de ese paciente');
$r = call('GET', "{$rxUrl}?prescriber_id={$precId}", null, $token);
check($r['status'] === 200 && in_array($rx1Id, array_column($r['json']['data'] ?? [], 'id'), true),
    'GET ?prescriber_id filtra por prescriptor');
$r = call('GET', "{$rxUrl}?patient_id=abc", null, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', "GET ?patient_id=abc -> 400 VALIDATION_ERROR");
$r = call('GET', "{$rxUrl}?limit=1000", null, $token);
check($r['status'] === 400, 'GET ?limit=1000 -> 400');
$r = call('GET', "{$rxUrl}?page=0", null, $token);
check($r['status'] === 400, 'GET ?page=0 -> 400');

// --- 10. Detalle ---
$r = call('GET', "{$rxUrl}/{$rx1Id}", null, $token);
check($r['status'] === 200 && isset($r['json']['data']['prescripcion'], $r['json']['data']['items']),
    'GET detalle -> 200 con prescripcion e items');
$r = call('GET', "{$rxUrl}/99999999", null, $token);
check($r['status'] === 404 && errorCode($r) === 'PRESCRIPTION_NOT_FOUND', 'GET receta inexistente -> 404 PRESCRIPTION_NOT_FOUND');
$r = call('GET', "{$rxUrl}/abc", null, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', "GET id 'abc' -> 400 VALIDATION_ERROR");

// --- 11. Dispensación con CAS (RF-053) ---
$r = call('POST', "{$rxUrl}/99999999/dispensar", ['items' => [['rx_item_id' => 1, 'cantidad' => 1]]], $token);
check($r['status'] === 404 && errorCode($r) === 'PRESCRIPTION_NOT_FOUND', 'dispensar receta inexistente -> 404');
$r = call('POST', "{$rxUrl}/{$rx1Id}/dispensar", ['items' => []], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'dispensar items vacios -> 400');
$r = call('POST', "{$rxUrl}/{$rx1Id}/dispensar", ['items' => [['rx_item_id' => 99999999, 'cantidad' => 1]]], $token);
check($r['status'] === 400 && errorCode($r) === 'RX_ITEM_NOT_IN_PRESCRIPTION', 'dispensar rx_item_id ajeno -> 400 RX_ITEM_NOT_IN_PRESCRIPTION');
$r = call('POST', "{$rxUrl}/{$rx1Id}/dispensar", ['items' => [['rx_item_id' => $item1Id, 'cantidad' => 0]]], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'dispensar cantidad 0 -> 400');

// Receta multi-producto (2 items) para probar rollback de la Tx
$r = call('POST', "{$rxUrl}", [
    'prescriber_id' => $precId, 'patient_id' => $patId, 'fecha' => $hoy,
    'items' => [['product_id' => $prodRx, 'cantidad_prescrita' => 2],
        ['product_id' => $prodRx2, 'cantidad_prescrita' => 3]],
], $token);
$rx2 = $r['json']['data'] ?? [];
$rx2Id = (int)($rx2['prescripcion']['id'] ?? 0);
$itemA = (int)($rx2['items'][0]['id'] ?? 0);
$itemB = (int)($rx2['items'][1]['id'] ?? 0);
check($r['status'] === 201 && $rx2Id > 0 && $itemA > 0 && $itemB > 0,
    'POST receta multi-item (sin numero_referencia) -> 201 con 2 items');

// dispensar parcial: 2 de 5
$r = call('POST', "{$rxUrl}/{$rx1Id}/dispensar", ['items' => [['rx_item_id' => $item1Id, 'cantidad' => 2]]], $token);
$it = ($r['json']['data']['items'][0] ?? []);
check($r['status'] === 200 && (int)($it['cantidad_dispensada'] ?? -1) === 2
    && (int)($it['saldo'] ?? -1) === 3 && (int)($it['version'] ?? -1) === 1,
    'dispensar parcial 2/5 -> 200: dispensada 2, saldo 3, version 1');
dbCheck(fn (): bool => itemEstado($item1Id) === ['disp' => 2, 'saldo' => 3, 'ver' => 1],
    'DB: item1 dispensada=2, saldo=3, version=1 (CAS)');

// exceder saldo: 4 de 3 -> 409 y la Tx no deja residuo
$r = call('POST', "{$rxUrl}/{$rx1Id}/dispensar", ['items' => [['rx_item_id' => $item1Id, 'cantidad' => 4]]], $token);
check($r['status'] === 409 && errorCode($r) === 'RX_SALDO_INSUFICIENTE', 'dispensar 4 con saldo 3 -> 409 RX_SALDO_INSUFICIENTE');
dbCheck(fn (): bool => itemEstado($item1Id) === ['disp' => 2, 'saldo' => 3, 'ver' => 1],
    'DB tras 409: item1 sin cambios (rollback)');

// completar saldo
$r = call('POST', "{$rxUrl}/{$rx1Id}/dispensar", ['items' => [['rx_item_id' => $item1Id, 'cantidad' => 3]]], $token);
check($r['status'] === 200 && (int)($r['json']['data']['items'][0]['saldo'] ?? -1) === 0,
    'dispensar restante 3/3 -> 200 con saldo 0');
$r = call('POST', "{$rxUrl}/{$rx1Id}/dispensar", ['items' => [['rx_item_id' => $item1Id, 'cantidad' => 1]]], $token);
check($r['status'] === 409 && errorCode($r) === 'RX_SALDO_INSUFICIENTE', 'dispensar 1 con saldo 0 -> 409');

// dispensación multi-item correcta
$r = call('POST', "{$rxUrl}/{$rx2Id}/dispensar", ['items' => [
    ['rx_item_id' => $itemA, 'cantidad' => 1], ['rx_item_id' => $itemB, 'cantidad' => 2]]], $token);
check($r['status'] === 200, 'dispensar multi-item (A:1, B:2) -> 200');
dbCheck(fn (): bool => itemEstado($itemA) === ['disp' => 1, 'saldo' => 1, 'ver' => 1]
    && itemEstado($itemB) === ['disp' => 2, 'saldo' => 1, 'ver' => 1],
    'DB: A disp=1, B disp=2 tras dispensación multi-item');

// atomicidad: A cabe pero B no -> 409 y A queda como estaba (rollback de la Tx)
$r = call('POST', "{$rxUrl}/{$rx2Id}/dispensar", ['items' => [
    ['rx_item_id' => $itemA, 'cantidad' => 1], ['rx_item_id' => $itemB, 'cantidad' => 5]]], $token);
check($r['status'] === 409 && errorCode($r) === 'RX_SALDO_INSUFICIENTE',
    'multi-item parcialmente valido -> 409 RX_SALDO_INSUFICIENTE');
dbCheck(fn (): bool => itemEstado($itemA) === ['disp' => 1, 'saldo' => 1, 'ver' => 1]
    && itemEstado($itemB) === ['disp' => 2, 'saldo' => 1, 'ver' => 1],
    'DB tras 409 multi-item: A y B sin cambios (rollback de la Tx)');

// --- 12. Integridad final del saldo (RF-053, chk_rxpi_saldo) ---
dbCheck(fn (): bool => (int)dbVal(
    'SELECT COUNT(*) FROM rx_prescription_items
     WHERE cantidad_dispensada < 0 OR cantidad_dispensada > cantidad_prescrita'
) === 0, 'DB final: sin saldo negativo ni dispensado > prescrito');
dbCheck(fn (): bool => (int)dbVal(
    'SELECT COUNT(*) FROM rx_prescription_items i
     JOIN rx_prescriptions r ON r.id = i.prescription_id
     WHERE i.product_id IS NULL OR r.id IS NULL'
) === 0, 'DB final: items huerfanos = 0');
dbCheck(fn (): bool => (int)dbVal(
    "SELECT COUNT(*) FROM inventory_movements WHERE ref_tipo = 'rx'"
) === 0, 'DB: sin movimientos de stock con ref_tipo rx (Opcion A: CP-08)');

// --- 13. Limpieza de fixtures (borrado lógico 204; productos quedan con items de rx) ---
// patient2/prescriber2 ya se borraron en la seccion de 409
$r = call('DELETE', "{$catUrl}/patients/{$patId}", null, $token);
check($r['status'] === 204, 'DELETE patient fixture -> 204');
$r = call('DELETE', "{$catUrl}/prescribers/{$precId}", null, $token);
check($r['status'] === 204, 'DELETE prescriber fixture -> 204');

echo "\nRESULTADO: " . ($fail === 0 ? 'PASS' : "FAIL ({$fail})") . "\n";
exit($fail === 0 ? 0 : 1);
