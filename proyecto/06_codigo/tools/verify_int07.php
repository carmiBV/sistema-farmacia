<?php
declare(strict_types=1);

/**
 * CP-INT-07 - Auditoria, Proteccion PII y Seguridad Global (E2E, HTTP + BD).
 *
 * Verifica logs inmutables de operaciones (RF-090), registro explicito de
 * acceso a datos de pacientes (RF-091), redaccion de PII (RNF-050),
 * sanitizacion contra inyeccion SQL y XSS, ausencia de CSRF por diseno
 * (tokens Bearer sin cookies ambientales) y .env fuera del control de versiones.
 *
 * Requiere: servidor corriendo, seed previo y git disponible en la raiz del repo.
 * Uso: php tools/verify_int07.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_int07.php <base-url>\n");
    exit(2);
}

$root = dirname(__DIR__);
$repo = dirname($root, 2);
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

/** @return array{status:int,json:?array,body:string,headers:string} */
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
    $all = '';
    foreach ($http_response_header ?? [] as $h) {
        $all .= $h . "\n";
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) {
            $status = (int)$m[1];
        }
    }
    return ['status' => $status, 'json' => json_decode($body, true), 'body' => $body, 'headers' => $all];
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
check(stripos($r['headers'], 'Set-Cookie:') === false,
    'CSRF: el login no emite cookies (token Bearer sin credenciales ambientales)');
$sufijo = substr(md5((string)random_int(0, PHP_INT_MAX)), 0, 8);

// --- 1. RF-090: log inmutable de operaciones ---
$r = call('POST', "{$api}/catalog/categories", ['nombre' => "Aud{$sufijo}"], $token);
$catId = (int)($r['json']['data']['id'] ?? 0);
check($catId > 0, 'mutacion de negocio ejecutada (categoria creada)');
check((int)dbVal(
    "SELECT COUNT(*) FROM audit_operations WHERE accion = 'creacion' AND entidad = '/api/v1/catalog/categories'
       AND usuario_id IS NOT NULL AND valores_despues LIKE ?",
    ['%' . "Aud{$sufijo}" . '%']
) === 1, 'RF-090: audit_operations registra la mutacion (usuario, accion, entidad, valores)');
check(dbVal(
    "SELECT valores_despues FROM audit_operations WHERE entidad = '/api/v1/catalog/categories' ORDER BY id DESC LIMIT 1"
) !== null, 'RF-090: valores_despues capturado');
call('DELETE', "{$api}/catalog/categories/{$catId}", null, $token);
check((int)dbVal(
    "SELECT COUNT(*) FROM audit_operations WHERE accion = 'eliminacion' AND entidad_id = ?",
    [$catId]
) === 1, 'RF-090: entidad_id se captura en rutas con {id} (borrado logico registrado)');

$r = call('DELETE', "{$api}/audit/operations/{$catId}", null, $token);
check($r['status'] === 404, 'audit_operations: sin endpoint de borrado (inmutable)');
$antes = (int)dbVal('SELECT COUNT(*) FROM audit_operations');
call('POST', "{$api}/catalog/categories", ['nombre' => "Aud2{$sufijo}"], $token);
check((int)dbVal('SELECT COUNT(*) FROM audit_operations') === $antes + 1,
    'los logs solo crecen (append-only) con cada mutacion');

// --- 2. RF-091: acceso a datos de pacientes ---
$r = call('POST', "{$api}/catalog/patients", [
    'identificacion' => "PI{$sufijo}", 'nombre' => "Paciente PII {$sufijo}",
    'fecha_nacimiento' => '1990-05-05', 'contacto' => 'pi@local.test',
], $token);
$pacId = (int)($r['json']['data']['id'] ?? 0);
check((int)dbVal('SELECT COUNT(*) FROM audit_pii_access WHERE paciente_id = ? AND accion = ?', [$pacId, 'creacion']) === 1,
    'RF-091: acceso registrado al crear el paciente');
$r = call('GET', "{$api}/catalog/patients/{$pacId}", null, $token);
check($r['status'] === 200 && (int)dbVal('SELECT COUNT(*) FROM audit_pii_access WHERE paciente_id = ? AND accion = ?', [$pacId, 'consulta']) === 1,
    'RF-091: acceso registrado al consultar el paciente');

// --- 3. RNF-050: redaccion de PII en los logs ---
$despues = (string)dbVal(
    "SELECT valores_despues FROM audit_operations WHERE entidad = '/api/v1/catalog/patients' ORDER BY id DESC LIMIT 1"
);
check(str_contains($despues, 'REDACTADO')
    && !str_contains($despues, "PI{$sufijo}")
    && !str_contains($despues, 'Paciente PII')
    && !str_contains($despues, 'pi@local.test'),
    'RNF-050: valores_despues redactado (sin PII del paciente en el log)');

// --- 4. Inyeccion SQL: entradas maliciosas sin filtrar SQL ni tumbar la BD ---
$malicioso = "'; DROP TABLE catalog_categories; --";
$r = call('GET', "{$api}/catalog/categories?q=" . urlencode($malicioso) . '&limit=5', null, $token);
check($r['status'] === 200 && !str_contains($r['body'], 'SQLSTATE') && !str_contains($r['body'], 'syntax'),
    'SQLi en filtro q: respuesta segura sin fugas de SQL');
$r = call('POST', "{$api}/catalog/categories", ['nombre' => $malicioso . ' ' . $sufijo], $token);
check(in_array($r['status'], [201, 400, 409], true) && !str_contains($r['body'], 'SQLSTATE'),
    'SQLi en nombre: aceptado escapado o rechazado (400/409), sin fuga de SQL');
check((int)dbVal('SELECT COUNT(*) FROM catalog_categories') > 0, 'tablas intactas tras los intentos de SQLi');

// --- 5. XSS: sin reflejo del payload en HTML ---
$payload = '%3Cscript%3Ealert(1)%3C%2Fscript%3E';
$r = call('GET', "{$base}/catalogo-categorias?x={$payload}", null, $token);
check($r['status'] === 200 && !str_contains($r['body'], '<script>alert(1)</script>'),
    'XSS reflejado: el HTML no refleja el payload sin escapar');
$r = call('GET', "{$api}/catalog/categories?q=" . urlencode('<script>alert(1)</script>'), null, $token);
check(!str_contains($r['body'], '<script>alert(1)</script>'),
    'XSS en API: el JSON no devuelve el payload crudo como HTML');

// --- 6. CSRF por diseno: mutaciones exigen Bearer ---
$r = call('POST', "{$api}/ops/stores", ['codigo' => "Z9{$sufijo}", 'nombre' => 'Sin token']);
check($r['status'] === 401, 'CSRF: mutacion sin token Bearer -> 401 (sin cookies ambientales)');

// --- 7. .env fuera del control de versiones (RNF-034) ---
$gitignore = (string)@file_get_contents($repo . '/.gitignore');
check(str_contains($gitignore, '.env'), '.gitignore cubre .env');
$listaGit = (string)shell_exec('git -C ' . escapeshellarg($repo) . ' ls-files');
$trackeados = array_values(array_filter(explode("\n", $listaGit), static fn ($l) => preg_match('/\.env$/', trim($l)) === 1));
check($trackeados === [], 'ningun .env real trackeado en git (solo .env.example)');
check(is_file($root . '/.env.example'), '.env.example presente como plantilla');
$ejemplo = (string)file_get_contents($root . '/.env.example');
check(!preg_match('/=\s*[A-Za-z0-9+\/]{20,}/', $ejemplo) || str_contains($ejemplo, 'cambiar') || str_contains($ejemplo, 'ejemplo'),
    '.env.example sin secretos reales');

echo 'RESULTADO: ' . ($fail === 0 ? 'PASS' : "FAIL ({$fail})") . "\n";
exit($fail === 0 ? 0 : 1);
