<?php
declare(strict_types=1);

/**
 * Verificación CP-BACK-02 — Autenticación y Seguridad (JWT + RBAC)
 *
 * Cobertura: 401 sin token, credenciales inválidas, login OK, token
 * manipulado, logout + reuso, RBAC 403/200, duplicado 409, validación 400,
 * throttle 429 y cuenta inactiva 403.
 *
 * Requiere: servidor corriendo y seed previo con tools/init_admin.php
 * (variables de entorno ADMIN_USER / ADMIN_PASSWORD).
 *
 * Uso: php tools/verify_cp_back02.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_cp_back02.php <base-url>\n");
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
$authUrl = "{$base}/api/v1/auth";
$fixturePass = 'Fix!Pass123';

// --- 1. Sin token -> 401 ---
$r = call('GET', "{$authUrl}/me");
check($r['status'] === 401 && errorCode($r) === 'UNAUTHENTICATED', 'GET /auth/me sin token -> 401 UNAUTHENTICATED');

$r = call('POST', "{$authUrl}/logout");
check($r['status'] === 401 && errorCode($r) === 'UNAUTHENTICATED', 'POST /auth/logout sin token -> 401');

$r = call('GET', "{$authUrl}/users");
check($r['status'] === 401, 'GET /auth/users sin token -> 401 (ruta protegida)');

// --- 2. Credenciales inválidas -> 401 (mensaje genérico, no filtra existencia) ---
$r = call('POST', "{$authUrl}/login", ['usuario' => $adminUser, 'password' => 'wrong_' . $sufijo]);
check($r['status'] === 401 && errorCode($r) === 'INVALID_CREDENTIALS', 'login password mala -> 401 INVALID_CREDENTIALS');

$r = call('POST', "{$authUrl}/login", ['usuario' => "noexiste_{$sufijo}", 'password' => 'wrong']);
check($r['status'] === 401 && errorCode($r) === 'INVALID_CREDENTIALS', 'login usuario inexistente -> 401 mismo código (no filtra)');

// --- 3. Login OK -> 200 con token + payload ---
$r = call('POST', "{$authUrl}/login", ['usuario' => $adminUser, 'password' => $adminPass]);
check($r['status'] === 200 && ($r['json']['success'] ?? null) === true, 'login admin -> 200 success=true');
$token = (string)($r['json']['data']['token'] ?? '');
check($token !== '' && ($r['json']['data']['token_type'] ?? '') === 'Bearer'
    && is_int($r['json']['data']['expires_in'] ?? null) && ($r['json']['data']['expires_in'] ?? 0) > 0,
    'respuesta contiene token, token_type=Bearer e expires_in');
$adminPayload = $r['json']['data']['user'] ?? [];
check(is_array($adminPayload) && isset($adminPayload['id'], $adminPayload['usuario'])
    && in_array('admin', $adminPayload['roles'] ?? [], true)
    && in_array('auth.users.manage', $adminPayload['permisos'] ?? [], true),
    'payload user con id, usuario, rol admin y permiso auth.users.manage');
check(!str_contains($r['body'], '$argon') && !str_contains($r['body'], '$2y$'),
    'respuesta no expone hash de contraseña');

// --- 4. Token manipulado -> 401 ---
$parts = explode('.', $token);
$fake = $parts[0] . '.' . base64_encode('{"sub":1,"usuario":"' . $adminUser . '","exp":9999999999}') . '.' . $parts[2];
$r = call('GET', "{$authUrl}/me", null, $fake);
check($r['status'] === 401 && errorCode($r) === 'UNAUTHENTICATED', 'token con payload alterado -> 401');

$r = call('GET', "{$authUrl}/me", null, $token . 'x');
check($r['status'] === 401, 'token con firma alterada -> 401');

// --- 5. /auth/me con token OK ---
$r = call('GET', "{$authUrl}/me", null, $token);
check($r['status'] === 200 && ($r['json']['data']['usuario'] ?? '') === $adminUser, 'GET /auth/me con token -> 200');

// --- 6. RBAC: usuario creado sin rol -> 403 en rutas con permiso ---
$uFix = "u_{$sufijo}";
$r = call('POST', "{$authUrl}/users", ['usuario' => $uFix, 'password' => $fixturePass, 'roles' => []], $token);
check($r['status'] === 201 && ($r['json']['data']['usuario'] ?? '') === $uFix
    && ($r['json']['data']['roles'] ?? []) === [],
    'POST /auth/users sin roles -> 201 (201, roles vacíos)');
$fixtureId = (int)($r['json']['data']['id'] ?? 0);
check($fixtureId > 0, 'usuario fixture con id > 0');

$r = call('POST', "{$authUrl}/login", ['usuario' => $uFix, 'password' => $fixturePass]);
check($r['status'] === 200, 'login fixture sin rol -> 200');
$fixToken = (string)($r['json']['data']['token'] ?? '');

$r = call('GET', "{$authUrl}/users", null, $fixToken);
check($r['status'] === 403 && errorCode($r) === 'FORBIDDEN', 'usuario sin permiso -> 403 FORBIDDEN');

$r = call('POST', "{$authUrl}/roles", ['nombre' => "rol_x_{$sufijo}"], $fixToken);
check($r['status'] === 403, 'POST /auth/roles sin permiso -> 403');

// --- 7. Asignación de rol (PUT) habilita el permiso en la siguiente llamada ---
$roles = call('GET', "{$authUrl}/roles", null, $token);
$adminRoleId = 0;
foreach (($roles['json']['data'] ?? []) as $rol) {
    if (($rol['nombre'] ?? '') === 'admin') {
        $adminRoleId = (int)$rol['id'];
    }
}
check($roles['status'] === 200 && $adminRoleId > 0, 'GET /auth/roles admin -> 200 con rol admin');

$r = call('PUT', "{$authUrl}/users/{$fixtureId}/roles", ['roles' => [$adminRoleId]], $token);
check($r['status'] === 200 && in_array('admin', $r['json']['data']['roles'] ?? [], true),
    'PUT /auth/users/{id}/roles -> 200 con rol admin asignado');

$r = call('GET', "{$authUrl}/users", null, $fixToken);
check($r['status'] === 200, 'mismo usuario ahora -> 200 (permiso efectivo por consulta)');

// --- 8. Logout + reuso del token ---
$r = call('POST', "{$authUrl}/logout", null, $fixToken);
check($r['status'] === 200 && ($r['json']['data']['revocado'] ?? null) === true, 'logout -> 200 revocado=true');
$r = call('GET', "{$authUrl}/me", null, $fixToken);
check($r['status'] === 401 && errorCode($r) === 'UNAUTHENTICATED', 'token revocado reutilizado -> 401');
$r = call('POST', "{$authUrl}/logout", null, $fixToken);
check($r['status'] === 401, 'logout repetido con token revocado -> 401');

// --- 9. Validación y duplicados ---
$r = call('POST', "{$authUrl}/users", ['usuario' => $uFix, 'password' => $fixturePass], $token);
check($r['status'] === 409 && errorCode($r) === 'DUPLICATE_NAME', 'usuario duplicado -> 409 DUPLICATE_NAME');

$r = call('POST', "{$authUrl}/users", ['usuario' => "corto_{$sufijo}", 'password' => '123'], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'password < 8 caracteres -> 400');

$r = call('POST', "{$authUrl}/users", ['usuario' => "norol_{$sufijo}", 'password' => $fixturePass, 'roles' => ['no_existe']], $token);
check($r['status'] === 400 && errorCode($r) === 'VALIDATION_ERROR', 'rol inexistente -> 400 VALIDATION_ERROR');

$r = call('POST', "{$authUrl}/login", ['usuario' => $adminUser], $token);
check($r['status'] === 400, 'login sin password -> 400');

$r = call('PUT', "{$authUrl}/users/abc/roles", ['roles' => [$adminRoleId]], $token);
check($r['status'] === 400, 'PUT id no numérico -> 400');

$r = call('PUT', "{$authUrl}/users/99999999/roles", ['roles' => [$adminRoleId]], $token);
check($r['status'] === 404, 'PUT usuario inexistente -> 404');

// --- 10. Throttle: 5 fallos -> 429 en el 6º intento ---
$rlUser = "rl_{$sufijo}";
for ($i = 1; $i <= 5; $i++) {
    call('POST', "{$authUrl}/login", ['usuario' => $rlUser, 'password' => "bad{$i}"]);
}
$r = call('POST', "{$authUrl}/login", ['usuario' => $rlUser, 'password' => 'cualquiera']);
check($r['status'] === 429 && errorCode($r) === 'RATE_LIMITED', '6º intento fallido -> 429 RATE_LIMITED');

// --- 11. Cuenta inactiva -> 403 (inserción directa solo de prueba) ---
$root = dirname(__DIR__);
spl_autoload_register(static function (string $cls) use ($root): void {
    if (str_starts_with($cls, 'App\\')) {
        $f = $root . '/app/' . str_replace('\\', '/', substr($cls, 4)) . '.php';
        if (is_file($f)) {
            require $f;
        }
    }
});
\App\Core\Env::load($root . '/.env');
$pdo = \App\Core\Database::pdo();
$disUser = "dis_{$sufijo}";
$alg = in_array('argon2id', password_algos(), true) ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
$pdo->prepare("INSERT INTO auth_users (usuario, password_hash, estado) VALUES (?, ?, 'inactivo')")
    ->execute([$disUser, password_hash($fixturePass, $alg)]);
$r = call('POST', "{$authUrl}/login", ['usuario' => $disUser, 'password' => $fixturePass]);
check($r['status'] === 403 && errorCode($r) === 'ACCOUNT_DISABLED', 'login cuenta inactiva -> 403 ACCOUNT_DISABLED');

// --- 12. Limpieza: desactivar fixtures (borrado lógico; sin DELETE FROM) ---
$pdo->prepare("UPDATE auth_users SET estado = 'inactivo' WHERE usuario IN (?, ?)")
    ->execute([$uFix, $disUser]);
echo "FIXTURES inactivadas: {$uFix}, {$disUser}\n";

echo $fail === 0 ? "RESULTADO: PASS\n" : "RESULTADO: FAIL ({$fail})\n";
exit($fail === 0 ? 0 : 1);
