<?php
declare(strict_types=1);

/**
 * Verificación CP-BACK-03 — Configuración Operativa y Sucursales
 *
 * Cobertura: 401 sin token, 403 RBAC sin permiso, CRUD de sucursales
 * (201/200/404/409/400/204), CRUD de cajas, turnos (409 doble apertura,
 * 409 sin turno, ciclo abierto→cerrado), system_config (upsert, 404,
 * validación por value_type, prefijo store.*).
 *
 * Requiere: servidor corriendo y seed previo con tools/init_admin.php
 * (ADMIN_USER / ADMIN_PASSWORD + permiso ops.config.manage).
 *
 * Uso: php tools/verify_cp_back03.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_cp_back03.php <base-url>\n");
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

$sufijo = substr(md5((string)random_int(0, PHP_INT_MAX)), 0, 8);
$opsUrl = "{$base}/api/v1/ops";
$codStore = "ST{$sufijo}";
$codCaja = "CJ{$sufijo}";
$codStore2 = "ST2{$sufijo}";

// --- 1. Autenticación ---
$r = call('GET', "{$opsUrl}/stores");
check($r['status'] === 401 && errorCode($r) === 'UNAUTHENTICATED', 'GET /ops/stores sin token -> 401 UNAUTHENTICATED');

$r = call('GET', "{$opsUrl}/registers/1/shift");
check($r['status'] === 401, 'GET shift sin token -> 401');

$r = call('PUT', "{$opsUrl}/config/test.cp3.s{$sufijo}", ['config_value' => 'x', 'value_type' => 'texto']);
check($r['status'] === 401, 'PUT config sin token -> 401');

// --- 2. Login admin (con permiso ops.config.manage) ---
$r = call('POST', "{$base}/api/v1/auth/login", ['usuario' => $adminUser, 'password' => $adminPass]);
check($r['status'] === 200, 'login admin -> 200');
$token = (string)($r['json']['data']['token'] ?? '');
check(in_array('ops.config.manage', $r['json']['data']['user']['permisos'] ?? [], true),
    'payload incluye permiso ops.config.manage (seed)');

// --- 3. RBAC: usuario sin permiso puede leer pero no escribir ---
$r = call('POST', "{$base}/api/v1/auth/users", ['usuario' => "ops_{$sufijo}", 'password' => 'Fix!Pass123', 'roles' => []], $token);
check($r['status'] === 201, 'crear usuario sin roles -> 201');
$fixId = (int)($r['json']['data']['id'] ?? 0);
$r = call('POST', "{$base}/api/v1/auth/login", ['usuario' => "ops_{$sufijo}", 'password' => 'Fix!Pass123']);
check($r['status'] === 200, 'login usuario sin roles -> 200');
$fixToken = (string)($r['json']['data']['token'] ?? '');

$r = call('GET', "{$opsUrl}/stores", null, $fixToken);
check($r['status'] === 200, 'GET stores con token sin permiso -> 200 (lectura con token)');

$r = call('POST', "{$opsUrl}/stores", ['codigo' => 'X1', 'nombre' => 'x'], $fixToken);
check($r['status'] === 403 && errorCode($r) === 'FORBIDDEN', 'POST stores sin permiso -> 403 FORBIDDEN');

$r = call('PUT', "{$opsUrl}/config/test.cp3.x", ['config_value' => '1', 'value_type' => 'texto'], $fixToken);
check($r['status'] === 403, 'PUT config sin permiso -> 403');

$r = call('DELETE', "{$opsUrl}/stores/1", null, $fixToken);
check($r['status'] === 403, 'DELETE stores sin permiso -> 403');

// --- 4. CRUD sucursales ---
$r = call('POST', "{$opsUrl}/stores", ['codigo' => $codStore, 'nombre' => "Sucursal {$sufijo}"], $token);
check($r['status'] === 201 && ($r['json']['data']['codigo'] ?? '') === $codStore
    && ($r['json']['data']['estado'] ?? '') === 'activa',
    'POST stores -> 201 con codigo y estado=activa');
$storeId = (int)($r['json']['data']['id'] ?? 0);
check($storeId > 0, 'store creado con id > 0');

$r = call('POST', "{$opsUrl}/stores", ['codigo' => $codStore, 'nombre' => 'dup'], $token);
check($r['status'] === 409 && errorCode($r) === 'DUPLICATE_CODE', 'store código duplicado -> 409 DUPLICATE_CODE');

$r = call('POST', "{$opsUrl}/stores", ['codigo' => "bad cod {$sufijo}", 'nombre' => 'x'], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'código con espacio -> 400 VALIDATION_ERROR');

$r = call('POST', "{$opsUrl}/stores", ['codigo' => $codStore2], $token);
check($r['status'] === 400, 'POST store sin nombre -> 400');

$r = call('GET', "{$opsUrl}/stores/{$storeId}", null, $token);
check($r['status'] === 200 && ($r['json']['data']['nombre'] ?? '') === "Sucursal {$sufijo}",
    'GET store por id -> 200');

$r = call('GET', "{$opsUrl}/stores/99999999", null, $token);
check($r['status'] === 404 && errorCode($r) === 'NOT_FOUND', 'GET store inexistente -> 404 NOT_FOUND');

$r = call('GET', "{$opsUrl}/stores/abc", null, $token);
check($r['status'] === 400, 'GET store id no numérico -> 400');

$r = call('PUT', "{$opsUrl}/stores/{$storeId}", ['codigo' => $codStore, 'nombre' => "Sucursal {$sufijo} V2"], $token);
check($r['status'] === 200 && ($r['json']['data']['nombre'] ?? '') === "Sucursal {$sufijo} V2",
    'PUT store -> 200 actualizado');

// --- 5. CRUD cajas ---
$r = call('POST', "{$opsUrl}/registers", ['store_id' => $storeId, 'codigo' => $codCaja], $token);
check($r['status'] === 201 && ($r['json']['data']['codigo'] ?? '') === $codCaja
    && (int)($r['json']['data']['store_id'] ?? 0) === $storeId,
    'POST registers -> 201 con store_id');
$cajaId = (int)($r['json']['data']['id'] ?? 0);
check($cajaId > 0, 'caja creada con id > 0');

$r = call('POST', "{$opsUrl}/registers", ['store_id' => $storeId, 'codigo' => $codCaja], $token);
check($r['status'] === 409 && errorCode($r) === 'DUPLICATE_CODE', 'caja duplicada en misma sucursal -> 409');

$r = call('POST', "{$opsUrl}/registers", ['store_id' => 99999999, 'codigo' => "C9{$sufijo}"], $token);
check($r['status'] === 409 && errorCode($r) === 'NOT_FOUND', 'register con store_id inexistente -> 409 NOT_FOUND');

$r = call('POST', "{$opsUrl}/registers", ['codigo' => "C10{$sufijo}"], $token);
check($r['status'] === 400, 'register sin store_id -> 400');

$r = call('GET', "{$opsUrl}/registers?store_id={$storeId}", null, $token);
check($r['status'] === 200 && ($r['json']['meta']['total'] ?? 0) >= 1, 'GET registers?store_id filtra -> 200 con resultados');

$r = call('PUT', "{$opsUrl}/registers/{$cajaId}", ['store_id' => $storeId, 'codigo' => $codCaja . 'V'], $token);
check($r['status'] === 200 && ($r['json']['data']['codigo'] ?? '') === $codCaja . 'V', 'PUT register -> 200');

// --- 6. Turnos ---
$r = call('GET', "{$opsUrl}/registers/{$cajaId}/shift", null, $token);
check($r['status'] === 200 && ($r['json']['data'] ?? null) === null, 'GET shift sin turno -> 200 data=null');

$r = call('POST', "{$opsUrl}/registers/{$cajaId}/shift/close", null, $token);
check($r['status'] === 409 && errorCode($r) === 'SHIFT_NOT_OPEN', 'close sin turno -> 409 SHIFT_NOT_OPEN');

$r = call('POST', "{$opsUrl}/registers/{$cajaId}/shift/open", null, $token);
check($r['status'] === 200 && ($r['json']['data']['estado'] ?? '') === 'abierta'
    && (int)($r['json']['data']['abierto_por'] ?? 0) > 0,
    'open -> 200 turno abierto con abierto_por');

$r = call('POST', "{$opsUrl}/registers/{$cajaId}/shift/open", null, $token);
check($r['status'] === 409 && errorCode($r) === 'SHIFT_ALREADY_OPEN', 'doble open -> 409 SHIFT_ALREADY_OPEN');

$r = call('GET', "{$opsUrl}/registers/{$cajaId}/shift", null, $token);
check($r['status'] === 200 && ($r['json']['data']['estado'] ?? '') === 'abierta', 'GET shift -> 200 turno abierto');

$r = call('POST', "{$opsUrl}/registers/{$cajaId}/shift/close", null, $token);
check($r['status'] === 200 && ($r['json']['data']['estado'] ?? '') === 'cerrada'
    && (int)($r['json']['data']['cerrado_por'] ?? 0) > 0,
    'close -> 200 turno cerrado con cerrado_por');

$r = call('GET', "{$opsUrl}/registers/{$cajaId}/shift", null, $token);
check($r['status'] === 200 && ($r['json']['data'] ?? null) === null, 'GET shift tras close -> data=null');

$r = call('POST', "{$opsUrl}/registers/99999999/shift/open", null, $token);
check($r['status'] === 404, 'open caja inexistente -> 404');

// --- 7. system_config ---
$key = "test.cp3.s{$sufijo}";
$r = call('GET', "{$opsUrl}/config/{$key}", null, $token);
check($r['status'] === 404 && errorCode($r) === 'NOT_FOUND', 'GET config inexistente -> 404');

$r = call('PUT', "{$opsUrl}/config/{$key}", ['config_value' => 'hola', 'value_type' => 'texto'], $token);
check($r['status'] === 200 && ($r['json']['data']['config_value'] ?? '') === 'hola'
    && ($r['json']['data']['value_type'] ?? '') === 'texto'
    && (int)($r['json']['data']['updated_by'] ?? 0) > 0,
    'PUT config (upsert create) -> 200 con updated_by');

$r = call('PUT', "{$opsUrl}/config/{$key}", ['config_value' => '42.5', 'value_type' => 'numero'], $token);
check($r['status'] === 200 && ($r['json']['data']['value_type'] ?? '') === 'numero',
    'PUT config (upsert update) -> 200 value_type actualizado');

$r = call('GET', "{$opsUrl}/config/{$key}", null, $token);
check($r['status'] === 200 && ($r['json']['data']['config_value'] ?? '') === '42.5', 'GET config -> 200 valor persistido');

$r = call('PUT', "{$opsUrl}/config/{$key}", ['config_value' => 'no-num', 'value_type' => 'numero'], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'value_type=numero con texto -> 400');

$r = call('PUT', "{$opsUrl}/config/{$key}", ['config_value' => 'x', 'value_type' => 'booleano'], $token);
check($r['status'] === 400, 'value_type=booleano con "x" -> 400');

$r = call('PUT', "{$opsUrl}/config/{$key}", ['config_value' => '{oops', 'value_type' => 'json'], $token);
check($r['status'] === 400, 'value_type=json inválido -> 400');

$r = call('PUT', "{$opsUrl}/config/{$key}", ['config_value' => 'x', 'value_type' => 'otro'], $token);
check($r['status'] === 400, 'value_type desconocido -> 400');

$r = call('PUT', "{$opsUrl}/config/" . rawurlencode("bad key {$sufijo}"), ['config_value' => 'x', 'value_type' => 'texto'], $token);
check($r['status'] === 400, 'clave con espacios -> 400');

$r = call('PUT', "{$opsUrl}/config/store.cp3_{$sufijo}", ['config_value' => 'x', 'value_type' => 'texto'], $token);
check($r['status'] === 400, 'clave store.* sin store_id -> 400 (regla CHECK)');

$r = call('PUT', "{$opsUrl}/config/store.cp3_{$sufijo}", ['config_value' => 'x', 'value_type' => 'texto', 'store_id' => 99999999], $token);
check($r['status'] === 409 && errorCode($r) === 'NOT_FOUND', 'clave store.* con store_id inexistente -> 409');

$r = call('PUT', "{$opsUrl}/config/store.cp3_{$sufijo}", ['config_value' => 'x', 'value_type' => 'texto', 'store_id' => $storeId], $token);
check($r['status'] === 200 && (int)($r['json']['data']['store_id'] ?? 0) === $storeId,
    'clave store.* con store_id válido -> 200');

$r = call('GET', "{$opsUrl}/config?store_id={$storeId}", null, $token);
check($r['status'] === 200 && ($r['json']['meta']['total'] ?? 0) >= 1, 'GET config?store_id -> 200 con filas');

// --- 8. Inactivación lógica (DELETE -> 204 y luego 404) ---
$r = call('DELETE', "{$opsUrl}/registers/{$cajaId}", null, $token);
check($r['status'] === 204, 'DELETE register -> 204 sin cuerpo');

$r = call('GET', "{$opsUrl}/registers/{$cajaId}", null, $token);
check($r['status'] === 404, 'GET register inactivado -> 404 (borrado lógico)');

$r = call('DELETE', "{$opsUrl}/stores/{$storeId}", null, $token);
check($r['status'] === 204, 'DELETE store -> 204 sin cuerpo');

$r = call('GET', "{$opsUrl}/stores/{$storeId}", null, $token);
check($r['status'] === 404, 'GET store inactivada -> 404 (borrado lógico)');

$r = call('DELETE', "{$opsUrl}/stores/{$storeId}", null, $token);
check($r['status'] === 404, 'DELETE repetido -> 404');

// --- 9. Limpieza: desactivar fixture de usuario (sin DELETE FROM) ---
$root = dirname(__DIR__);
spl_autoload_register(static function (string $cls) use ($root): void {
    if (str_starts_with($cls, 'App\\')) {
        $f = $root . '/src/' . str_replace('\\', '/', substr($cls, 4)) . '.php';
        if (is_file($f)) {
            require $f;
        }
    }
});
\App\Support\Env::load($root . '/.env');
\App\Support\Database::pdo()
    ->prepare("UPDATE auth_users SET estado = 'inactivo' WHERE id = ? OR usuario LIKE 'ops\\_%'")
    ->execute([$fixId]);
echo "FIXTURE inactivados: ops_* (incluye ops_{$sufijo})\n";
// ponytail: las claves test.cp3.* creadas en system_config quedan como
// residuo de prueba (sin DELETE FROM en CP-BACK-03) — reportado como supuesto.

echo $fail === 0 ? "RESULTADO: PASS\n" : "RESULTADO: FAIL ({$fail})\n";
exit($fail === 0 ? 0 : 1);
