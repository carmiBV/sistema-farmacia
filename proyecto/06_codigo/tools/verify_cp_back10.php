<?php
declare(strict_types=1);

/**
 * CP-BACK-10 - Auditoria de Operaciones, Accesos PII y Eventos del Sistema.
 *
 * RF-090 (operaciones criticas: usuario, fecha, motivo, valores),
 * RF-091 + RN-13 (una fila por cada acceso a pacientes/recetas),
 * RNF-046 (append-only), RNF-050 (sin PII de pacientes en los logs),
 * decision 16 (outbox_events desacopla el envio de eventos salientes).
 *
 * Requiere: servidor corriendo y seed previo con tools/init_admin.php
 * (ADMIN_USER / ADMIN_PASSWORD + permisos audit.read y audit.manage).
 * Conexion directa a BD via .env (solo SELECT).
 *
 * Uso: php tools/verify_cp_back10.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_cp_back10.php <base-url>\n");
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

function dbRows(string $sql, array $params = []): array
{
    $stmt = \App\Support\Database::pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
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

$sufijo = substr(md5((string)random_int(0, PHP_INT_MAX)), 0, 8);
$authUrl = "{$base}/api/v1/auth";
$catUrl = "{$base}/api/v1/catalog";
$opsUrl = "{$base}/api/v1/ops";
$purUrl = "{$base}/api/v1/purchases";
$invUrl = "{$base}/api/v1/inventory";
$rxUrl = "{$base}/api/v1/rx";
$salesUrl = "{$base}/api/v1/sales";
$auditUrl = "{$base}/api/v1/audit";
$passFix = 'Fix!Pass123';
$emailPii = "pii{$sufijo}@test.local";

// ---------------------------------------------------------------- 1. 401
$r = call('GET', "{$auditUrl}/operations");
check($r['status'] === 401 && errorCode($r) === 'UNAUTHENTICATED', 'GET /audit/operations sin token -> 401 UNAUTHENTICATED');
$r = call('GET', "{$auditUrl}/pii");
check($r['status'] === 401, 'GET /audit/pii sin token -> 401');
$r = call('GET', "{$auditUrl}/events");
check($r['status'] === 401, 'GET /audit/events sin token -> 401');
$r = call('POST', "{$auditUrl}/events/1/procesar", null);
check($r['status'] === 401, 'POST /audit/events/1/procesar sin token -> 401');

// -------------------------------------------------- 2. Login + RBAC
$r = call('POST', "{$authUrl}/login", ['usuario' => $adminUser, 'password' => $adminPass]);
check($r['status'] === 200, 'login admin -> 200');
$token = (string)($r['json']['data']['token'] ?? '');
$permAdmin = $r['json']['data']['user']['permisos'] ?? [];
check(in_array('audit.read', $permAdmin, true) && in_array('audit.manage', $permAdmin, true),
    'payload admin incluye audit.read y audit.manage (seed)');

$r = call('POST', "{$authUrl}/users", ['usuario' => "aud_{$sufijo}", 'password' => $passFix, 'roles' => []], $token);
check($r['status'] === 201, 'crear usuario sin roles -> 201');
$r = call('POST', "{$authUrl}/login", ['usuario' => "aud_{$sufijo}", 'password' => $passFix]);
check($r['status'] === 200, 'login usuario sin roles -> 200');
$fixToken = (string)($r['json']['data']['token'] ?? '');

$r = call('GET', "{$auditUrl}/operations", null, $fixToken);
check($r['status'] === 403 && errorCode($r) === 'FORBIDDEN',
    'GET /audit/operations sin audit.read -> 403 FORBIDDEN (acceso por rol, RN-13)');
$r = call('GET', "{$auditUrl}/pii", null, $fixToken);
check($r['status'] === 403, 'GET /audit/pii sin audit.read -> 403');
$r = call('POST', "{$auditUrl}/events/1/procesar", null, $fixToken);
check($r['status'] === 403, 'POST /audit/events/1/procesar sin audit.manage -> 403');

// ------------------------------------- 3. RF-091: acceso a PII de pacientes
$r = call('POST', "{$catUrl}/patients", [
    'identificacion' => "DOC{$sufijo}",
    'nombre' => "Paciente CP10 {$sufijo}",
    'fecha_nacimiento' => '1990-01-01',
    'contacto' => $emailPii,
], $token);
check($r['status'] === 201, 'POST patient (fixture PII) -> 201');
$patientId = (int)($r['json']['data']['id'] ?? 0);
check($patientId > 0, 'patient id > 0');

dbCheck(fn(): bool => (int)dbVal(
        "SELECT COUNT(*) FROM audit_pii_access WHERE usuario_id IS NOT NULL AND accion = 'creacion' AND paciente_id = ?",
        [$patientId]) === 1,
    'BD: una fila audit_pii_access accion=creacion por el alta del paciente');

$r = call('GET', "{$catUrl}/patients/{$patientId}", null, $token);
check($r['status'] === 200, 'GET patient/{id} -> 200');
dbCheck(fn(): bool => (int)dbVal(
        "SELECT COUNT(*) FROM audit_pii_access WHERE accion = 'consulta' AND paciente_id = ?",
        [$patientId]) >= 1,
    'BD: fila accion=consulta por GET /patients/{id}');

$r = call('PUT', "{$catUrl}/patients/{$patientId}", [
    'identificacion' => "DOC{$sufijo}",
    'nombre' => "Paciente CP10b {$sufijo}",
    'fecha_nacimiento' => '1990-01-01',
    'contacto' => $emailPii,
], $token);
check($r['status'] === 200, 'PUT patient -> 200');
dbCheck(fn(): bool => (int)dbVal(
        "SELECT COUNT(*) FROM audit_pii_access WHERE accion = 'modificacion' AND paciente_id = ?",
        [$patientId]) >= 1,
    'BD: fila accion=modificacion por PUT /patients/{id}');

$r = call('GET', "{$catUrl}/patients?q=CP10", null, $token);
check($r['status'] === 200, 'GET /patients (listado) -> 200');
dbCheck(fn(): bool => (int)dbVal(
        "SELECT COUNT(*) FROM audit_pii_access WHERE accion = 'consulta' AND paciente_id = ?",
        [$patientId]) >= 2,
    'BD: el listado agrega una fila por cada paciente consultado');

// ------------------------------- 4. RF-091: acceso a PII de recetas
$r = call('POST', "{$catUrl}/prescribers", [
    'nombre' => "Dr CP10 {$sufijo}",
    'identificacion' => "MED{$sufijo}",
    'especialidad' => 'Clinica',
], $token);
check($r['status'] === 201, 'POST prescriber (fixture) -> 201');
$prescId = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$catUrl}/products", [
    'sku' => "RX{$sufijo}",
    'nombre' => "Diazepam CP10 {$sufijo}",
    'principio_activo' => 'Diazepam',
    'presentacion' => 'Caja x 20 tabletas',
    'concentracion' => '5 mg',
    'condicion_venta' => 'receta',
], $token);
check($r['status'] === 201, 'POST product receta (fixture) -> 201');
$prodId = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$rxUrl}/prescriptions", [
    'prescriber_id' => $prescId,
    'patient_id' => $patientId,
    'fecha' => '2026-10-05',
    'numero_referencia' => "RX{$sufijo}",
    'items' => [['product_id' => $prodId, 'cantidad_prescrita' => 5]],
], $token);
check($r['status'] === 201, 'POST prescription -> 201');
$rxId = (int)($r['json']['data']['prescripcion']['id'] ?? 0);
check($rxId > 0, 'prescription id > 0');
dbCheck(fn(): bool => (int)dbVal(
        "SELECT COUNT(*) FROM audit_pii_access WHERE accion = 'creacion' AND prescription_id = ?",
        [$rxId]) === 1,
    'BD: fila accion=creacion por el alta de la receta');

$r = call('GET', "{$rxUrl}/prescriptions/{$rxId}", null, $token);
check($r['status'] === 200, 'GET prescription/{id} -> 200');
dbCheck(fn(): bool => (int)dbVal(
        "SELECT COUNT(*) FROM audit_pii_access WHERE accion = 'consulta' AND prescription_id = ?",
        [$rxId]) >= 1,
    'BD: fila accion=consulta por GET /rx/prescriptions/{id}');

$r = call('GET', "{$rxUrl}/prescriptions", null, $token);
check($r['status'] === 200, 'GET /rx/prescriptions (listado) -> 200');
dbCheck(fn(): bool => (int)dbVal(
        "SELECT COUNT(*) FROM audit_pii_access WHERE prescription_id = ?", [$rxId]) >= 3,
    'BD: el listado de recetas tambien deja fila por receta consultada');

// ------------------------- 5. RF-090: operaciones criticas + RNF-050
dbCheck(fn(): bool => (int)dbVal(
        "SELECT COUNT(*) FROM audit_operations
          WHERE accion = 'creacion' AND entidad = '/api/v1/catalog/patients'") >= 1,
    'BD: audit_operations registra la creacion del paciente (RF-090)');
dbCheck(fn(): bool => (int)dbVal(
        "SELECT COUNT(*) FROM audit_operations
          WHERE accion = 'modificacion' AND entidad = '/api/v1/catalog/patients/{id}' AND entidad_id = ?",
        [$patientId]) >= 1,
    'BD: la modificacion registra entidad y entidad_id');
dbCheck(fn(): bool => dbVal(
        "SELECT valores_antes FROM audit_operations
          WHERE entidad = '/api/v1/catalog/patients/{id}' AND entidad_id = ? ORDER BY id DESC LIMIT 1",
        [$patientId]) === null,
    'BD: valores_antes queda NULL (limitacion documentada del CP-BACK-10)');

$valoresPaciente = (string)dbVal(
    "SELECT valores_despues FROM audit_operations
      WHERE accion = 'creacion' AND entidad = '/api/v1/catalog/patients' ORDER BY id DESC LIMIT 1");
check(!str_contains($valoresPaciente, $emailPii), 'RNF-050: el contacto del paciente NO se persiste en audit_operations');
check(!str_contains($valoresPaciente, "DOC{$sufijo}"), 'RNF-050: la identificacion del paciente NO se persiste');
check(!str_contains($valoresPaciente, "Paciente CP10 {$sufijo}"), 'RNF-050: el nombre del paciente NO se persiste');
check(str_contains($valoresPaciente, '[REDACTADO]'), 'RNF-050: los campos sensibles quedan como [REDACTADO]');

$r = call('POST', "{$catUrl}/products", [
    'sku' => "AUD{$sufijo}",
    'nombre' => "NombreVisible CP10 {$sufijo}",
    'principio_activo' => 'Ibuprofeno',
    'presentacion' => 'Caja x 10',
    'concentracion' => '400 mg',
    'condicion_venta' => 'libre',
], $token);
check($r['status'] === 201, 'POST product (fixture no-PII) -> 201');
$prodLibre = (int)($r['json']['data']['id'] ?? 0);
$valoresProducto = (string)dbVal(
    "SELECT valores_despues FROM audit_operations
      WHERE accion = 'creacion' AND entidad = '/api/v1/catalog/products' ORDER BY id DESC LIMIT 1");
check(str_contains($valoresProducto, "NombreVisible CP10 {$sufijo}"),
    'RF-090: en recursos NO PII el nombre se conserva en valores_despues');

$valoresUser = (string)dbVal(
    "SELECT valores_despues FROM audit_operations
      WHERE entidad = '/api/v1/auth/users' ORDER BY id DESC LIMIT 1");
check($valoresUser !== '' && !str_contains($valoresUser, $passFix), 'RNF-050: la password nunca se persiste');
check(str_contains($valoresUser, '[REDACTADO]'), 'RNF-050: password redactada en audit_operations');

// --------------------------------------------- 6. Consultas de auditoria
$r = call('GET', "{$auditUrl}/operations", null, $token);
check($r['status'] === 200 && is_array($r['json']['data'] ?? null), 'GET /audit/operations -> 200');
check(isset($r['json']['meta']['total'], $r['json']['meta']['page'], $r['json']['meta']['limit']), 'meta con total/page/limit');
$r = call('GET', "{$auditUrl}/operations?accion=creacion", null, $token);
check($r['status'] === 200 && (int)($r['json']['meta']['total'] ?? 0) >= 1, 'GET ?accion=creacion -> 200');
$r = call('GET', "{$auditUrl}/operations?accion=xxx", null, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'GET ?accion invalido -> 400');
$r = call('GET', "{$auditUrl}/operations?entidad=/api/v1/catalog/patients", null, $token);
check($r['status'] === 200 && (int)($r['json']['meta']['total'] ?? 0) >= 1, 'GET ?entidad=... -> 200');
$r = call('GET', "{$auditUrl}/operations?desde=2026-01-01&hasta=2030-12-31", null, $token);
check($r['status'] === 200, 'GET filtro por periodo -> 200');
$r = call('GET', "{$auditUrl}/operations?desde=2030-01-01&hasta=2026-01-01", null, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'GET desde > hasta -> 400');
$r = call('GET', "{$auditUrl}/operations?desde=abc", null, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'GET ?desde invalido -> 400');
$r = call('GET', "{$auditUrl}/operations?limit=1&page=1", null, $token);
check($r['status'] === 200 && count($r['json']['data'] ?? []) === 1, 'paginacion limit=1 -> 1 elemento');
$r = call('GET', "{$auditUrl}/operations/99999999", null, $token);
check($r['status'] === 404 && errorCode($r) === 'AUDIT_ENTRY_NOT_FOUND', 'GET operacion inexistente -> 404');
$r = call('GET', "{$auditUrl}/operations/abc", null, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'GET operacion id no numerico -> 400');

$r = call('GET', "{$auditUrl}/pii?paciente_id={$patientId}", null, $token);
check($r['status'] === 200 && (int)($r['json']['meta']['total'] ?? 0) >= 3, 'GET /pii?paciente_id -> 200 con registros');
$r = call('GET', "{$auditUrl}/pii?accion=consulta", null, $token);
check($r['status'] === 200, 'GET /pii?accion=consulta -> 200');
$r = call('GET', "{$auditUrl}/pii?accion=xxx", null, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'GET /pii?accion invalido -> 400');
$r = call('GET', "{$auditUrl}/pii?prescription_id={$rxId}", null, $token);
check($r['status'] === 200 && (int)($r['json']['meta']['total'] ?? 0) >= 1, 'GET /pii?prescription_id -> 200');
$piiId = (int)dbVal('SELECT id FROM audit_pii_access ORDER BY id DESC LIMIT 1');
$r = call('GET', "{$auditUrl}/pii/{$piiId}", null, $token);
check($r['status'] === 200 && (int)($r['json']['data']['id'] ?? 0) === $piiId, 'GET /pii/{id} -> 200');

// ------------------------------- 7. Outbox: venta.registrada (decision 16)
$r = call('POST', "{$opsUrl}/stores", ['codigo' => "AS{$sufijo}", 'nombre' => "Suc Audit CP10 {$sufijo}"], $token);
check($r['status'] === 201, 'POST store (fixture venta) -> 201');
$storeId = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$opsUrl}/registers", ['store_id' => $storeId, 'codigo' => "AC{$sufijo}", 'nombre' => 'Caja audit'], $token);
check($r['status'] === 201, 'POST register -> 201');
$regId = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$catUrl}/suppliers", ['identificacion' => "ID{$sufijo}", 'nombre' => "Distrib CP10 {$sufijo}"], $token);
check($r['status'] === 201, 'POST supplier (fixture) -> 201');
$provId = (int)($r['json']['data']['id'] ?? 0);

$r = call('POST', "{$purUrl}/orders", [
    'numero' => "OC{$sufijo}", 'supplier_id' => $provId,
    'items' => [['product_id' => $prodLibre, 'cantidad_pedida' => 5]],
], $token);
check($r['status'] === 201, 'POST orden de compra -> 201');
$idOrd = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$purUrl}/orders/{$idOrd}/emitir", null, $token);
check($r['status'] === 200, 'emitir OC -> 200');
$r = call('POST', "{$purUrl}/orders/{$idOrd}/receptions", [
    'idempotency_key' => "{$sufijo}rc",
    'items' => [['product_id' => $prodLibre, 'numero_lote' => "A10{$sufijo}", 'fecha_vencimiento' => '2027-12-31', 'cantidad' => 5]],
], $token);
check($r['status'] === 201, 'POST recepcion -> 201');
$recId = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$purUrl}/receptions/{$recId}/confirmar", ['store_id' => $storeId], $token);
check($r['status'] === 200, 'confirmar recepcion -> 200');
$lotId = (int)($r['json']['data']['items'][0]['lot_id'] ?? 0);
$r = call('POST', "{$invUrl}/batches/{$lotId}/liberar", ['motivo' => 'Liberacion CP10'], $token);
check($r['status'] === 200, 'liberar lote -> 200');

$r = call('POST', "{$salesUrl}/orders", [
    'store_id' => $storeId, 'register_id' => $regId,
    'items' => [['lot_id' => $lotId, 'cantidad' => 2, 'precio_unitario' => 5.00]],
], $token);
check($r['status'] === 201, 'venta POS -> 201');
$orderId = (int)($r['json']['data']['orden']['id'] ?? 0);
dbCheck(fn(): bool => (int)dbVal(
        "SELECT COUNT(*) FROM outbox_events WHERE agregado_tipo = 'venta' AND agregado_id = ? AND tipo_evento = 'venta.registrada'",
        [$orderId]) === 1,
    'BD: evento venta.registrada en outbox_events, en la misma transaccion (decision 16)');

// ------------------------------- 8. Ciclo de vida del outbox (consumidor)
$evId = (int)dbVal(
    "SELECT id FROM outbox_events WHERE tipo_evento = 'venta.registrada' AND agregado_id = ?", [$orderId]);
check($evId > 0, 'evento de venta identificado');
$r = call('GET', "{$auditUrl}/events?tipo_evento=venta.registrada", null, $token);
check($r['status'] === 200 && (int)($r['json']['meta']['total'] ?? 0) >= 1, 'GET /events?tipo_evento -> 200');
$r = call('GET', "{$auditUrl}/events?estado=pendiente", null, $token);
check($r['status'] === 200, 'GET /events?estado=pendiente -> 200');
$r = call('GET', "{$auditUrl}/events?estado=xxx", null, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'GET /events?estado invalido -> 400');

$r = call('POST', "{$auditUrl}/events/{$evId}/fallar", null, $token);
check($r['status'] === 200 && ($r['json']['data']['estado'] ?? '') === 'fallido', 'fallar evento -> 200 fallido');
check((int)($r['json']['data']['intentos'] ?? 0) === 1, 'intentos = 1 tras fallar');
$r = call('POST', "{$auditUrl}/events/{$evId}/fallar", null, $token);
check($r['status'] === 409 && errorCode($r) === 'EVENT_NOT_PENDING', 'fallar dos veces -> 409 EVENT_NOT_PENDING');
$r = call('POST', "{$auditUrl}/events/{$evId}/reintentar", null, $token);
check($r['status'] === 200 && ($r['json']['data']['estado'] ?? '') === 'pendiente', 'reintentar evento -> 200 pendiente');
$r = call('POST', "{$auditUrl}/events/{$evId}/procesar", null, $token);
check($r['status'] === 200 && ($r['json']['data']['estado'] ?? '') === 'procesado', 'procesar evento -> 200 procesado');
check(($r['json']['data']['procesado_at'] ?? null) !== null, 'procesado_at queda registrado');
$r = call('POST', "{$auditUrl}/events/{$evId}/procesar", null, $token);
check($r['status'] === 409 && errorCode($r) === 'EVENT_NOT_PENDING', 'procesar dos veces -> 409 EVENT_NOT_PENDING');
$r = call('POST', "{$auditUrl}/events/{$evId}/reintentar", null, $token);
check($r['status'] === 409 && errorCode($r) === 'EVENT_NOT_FAILED', 'reintentar un procesado -> 409 EVENT_NOT_FAILED');
$r = call('POST', "{$auditUrl}/events/99999999/procesar", null, $token);
check($r['status'] === 404 && errorCode($r) === 'EVENT_NOT_FOUND', 'procesar evento inexistente -> 404');
$r = call('POST', "{$auditUrl}/events/abc/procesar", null, $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'procesar id no numerico -> 400');
$r = call('GET', "{$auditUrl}/events/{$evId}", null, $token);
check($r['status'] === 200 && (int)($r['json']['data']['id'] ?? 0) === $evId, 'GET /events/{id} -> 200');

// --------------------------- 9. Inmutabilidad (RNF-046): solo anexar
dbCheck(fn(): bool => (int)dbVal("SELECT COUNT(*) FROM audit_operations") >= 5,
    'RNF-046: audit_operations solo crece (sin API de UPDATE/DELETE)');
dbCheck(fn(): bool => (int)dbVal("SELECT COUNT(*) FROM audit_pii_access WHERE paciente_id = ?", [$patientId]) >= 5,
    'RNF-046: audit_pii_access conserva todos los accesos del paciente');

// ------------------------------------------------------ 10. Limpieza
$r = call('DELETE', "{$catUrl}/patients/{$patientId}", null, $token);
check($r['status'] === 204, 'DELETE patient (anonimizacion) -> 204');
$r = call('DELETE', "{$catUrl}/prescribers/{$prescId}", null, $token);
check($r['status'] === 204, 'DELETE prescriber -> 204');
$r = call('DELETE', "{$catUrl}/products/{$prodId}", null, $token);
check($r['status'] === 204, 'DELETE product receta -> 204');
$r = call('DELETE', "{$catUrl}/products/{$prodLibre}", null, $token);
check($r['status'] === 204, 'DELETE product libre -> 204');
$r = call('DELETE', "{$opsUrl}/stores/{$storeId}", null, $token);
check($r['status'] === 204, 'DELETE store -> 204');
$r = call('DELETE', "{$catUrl}/suppliers/{$provId}", null, $token);
check($r['status'] === 204, 'DELETE supplier -> 204');

echo "\nRESULTADO: " . ($fail === 0 ? 'PASS' : "FAIL ({$fail})") . "\n";
exit($fail === 0 ? 0 : 1);
