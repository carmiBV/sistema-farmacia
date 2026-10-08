<?php
declare(strict_types=1);

/**
 * CP-INT-01 - Autenticacion, Sesion y RBAC (E2E).
 *
 * Flujo end-to-end sobre la pila completa (HTTP + BD):
 *   login -> /auth/me (fuente del dashboard) -> navegacion del dashboard ->
 *   RBAC efectivo (403 sin rol, 200 con rol) -> logout seguro ->
 *   revocacion verificada en token_blacklist -> reuso de token 401 ->
 *   cuenta inactiva 403.
 *
 * Requiere: servidor corriendo y seed previo con tools/init_admin.php
 * (ADMIN_USER / ADMIN_PASSWORD). Conexion directa a BD via .env (solo lectura
 * de verificacion + fixtures de usuario con borrado logico al final).
 *
 * Uso: php tools/verify_int01.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_int01.php <base-url>\n");
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
    fwrite(STDERR, "Defina ADMIN_PASSWORD (la misma del seed init_admin.php).\n");
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

$authUrl = "{$base}/api/v1/auth";
$sufijo = substr(md5((string)random_int(0, PHP_INT_MAX)), 0, 8);
$passFix = 'Fix' . $sufijo . '123';

// --- 1. login y sesion ---
$r = call('POST', "{$authUrl}/login", ['usuario' => $adminUser, 'password' => $adminPass]);
check($r['status'] === 200 && ($r['json']['success'] ?? null) === true, 'login admin -> 200');
$token = (string)($r['json']['data']['token'] ?? '');
check($token !== '' && str_contains($token, '.'), 'token JWT emitido');
$rolesLogin = $r['json']['data']['user']['roles'] ?? [];
check(in_array('admin', $rolesLogin, true), 'login entrega perfil (rol admin) para la navegacion del dashboard');

$r = call('GET', "{$authUrl}/me", null, $token);
check($r['status'] === 200 && ($r['json']['data']['usuario'] ?? '') === $adminUser,
    'GET /auth/me con token -> 200 (fuente de datos del dashboard)');

$r = call('GET', "{$base}/dashboard");
check($r['status'] === 200 && str_contains($r['body'], 'id="w-ventas-dato"'),
    'navegacion al dashboard servida por perfil autenticado');

// --- 2. RBAC efectivo ---
$r = call('POST', "{$authUrl}/users", ['usuario' => "int1_{$sufijo}", 'password' => $passFix, 'roles' => []], $token);
check($r['status'] === 201, 'creacion de usuario de prueba sin roles -> 201');
$userId = (int)($r['json']['data']['id'] ?? 0);
check($userId > 0, 'usuario de prueba con id');

$r = call('POST', "{$authUrl}/login", ['usuario' => "int1_{$sufijo}", 'password' => $passFix]);
check($r['status'] === 200, 'login del usuario sin roles -> 200');
$tokenSinRol = (string)($r['json']['data']['token'] ?? '');

$r = call('GET', "{$authUrl}/users", null, $tokenSinRol);
check($r['status'] === 403 && errorCode($r) === 'FORBIDDEN', 'RBAC: sin rol -> 403 FORBIDDEN');

$adminRoleId = (int)dbVal('SELECT id FROM auth_roles WHERE nombre = ?', ['admin']);
$r = call('PUT', "{$authUrl}/users/{$userId}/roles", ['roles' => [$adminRoleId]], $token);
check($r['status'] === 200, 'asignacion de rol admin al usuario -> 200');

$r = call('POST', "{$authUrl}/login", ['usuario' => "int1_{$sufijo}", 'password' => $passFix]);
$tokenConRol = (string)($r['json']['data']['token'] ?? '');
$r = call('GET', "{$authUrl}/users", null, $tokenConRol);
check($r['status'] === 200, 'RBAC: con rol admin -> 200 (permiso efectivo tras el cambio)');

// --- 3. logout y revocacion verificada en BD ---
$r = call('POST', "{$authUrl}/logout", null, $token);
check($r['status'] === 200 && ($r['json']['data']['revocado'] ?? null) === true, 'logout -> 200 revocado=true');

$hash = hash('sha256', $token);
$enBlacklist = (int)dbVal('SELECT COUNT(*) FROM token_blacklist WHERE token_hash = ?', [$hash]);
check($enBlacklist === 1, 'token_blacklist: hash sha256 del token revocado persistido');

$r = call('GET', "{$authUrl}/me", null, $token);
check($r['status'] === 401 && errorCode($r) === 'UNAUTHENTICATED', 'reuso del token revocado -> 401 UNAUTHENTICATED');

$r = call('POST', "{$authUrl}/logout", null, $token);
check($r['status'] === 401, 'logout repetido con token revocado -> 401');

$r = call('POST', "{$authUrl}/login", ['usuario' => $adminUser, 'password' => $adminPass]);
check($r['status'] === 200, 'login posterior al logout -> 200 (nueva sesion valida)');
$tokenNuevo = (string)($r['json']['data']['token'] ?? '');
$r = call('GET', "{$authUrl}/me", null, $tokenNuevo);
check($r['status'] === 200, 'el token nuevo no queda afectado por la revocacion previa');

// --- 4. cuenta inactiva ---
call('PUT', "{$authUrl}/users/{$userId}/roles", ['roles' => []], $tokenNuevo);
dbVal("UPDATE auth_users SET estado = 'inactivo' WHERE id = ?", [$userId]);
$r = call('POST', "{$authUrl}/login", ['usuario' => "int1_{$sufijo}", 'password' => $passFix]);
check($r['status'] === 403 && errorCode($r) === 'ACCOUNT_DISABLED', 'login de cuenta inactiva -> 403 ACCOUNT_DISABLED');

echo 'RESULTADO: ' . ($fail === 0 ? 'PASS' : "FAIL ({$fail})") . "\n";
exit($fail === 0 ? 0 : 1);
