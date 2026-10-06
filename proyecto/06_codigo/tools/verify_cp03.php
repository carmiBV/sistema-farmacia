<?php
declare(strict_types=1);

/**
 * Verificación CP-API-03 — GET /api/v1/catalog/categories
 *
 * Modos (2.º argumento):
 *   empty  -> exige tabla vacía (total=0, data=[]). Ejecutar ANTES de sembrar.
 *   datos  -> si falta, siembra 2 categorías (Analgésicos raíz / Ibuprofeno hija)
 *             vía PDO directo y valida datos, paginación, filtros y 400.
 *   error  -> servidor lanzado con DB_NAME inexistente -> 500 DATABASE_ERROR.
 *
 * Uso: php tools/verify_cp03.php http://127.0.0.1:8000 <empty|datos|error>
 */

$base = $argv[1] ?? '';
$modo = $argv[2] ?? '';
if ($base === '' || !in_array($modo, ['empty', 'datos', 'error'], true)) {
    fwrite(STDERR, "Uso: php verify_cp03.php <base-url> <empty|datos|error>\n");
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

/** @return array{status:int,json:?array} */
function call(string $url): array
{
    $ctx = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $ctx);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }
    return ['status' => $status, 'json' => $body === false ? null : json_decode($body, true)];
}

$r = call("{$base}/api/v1/catalog/categories");

if ($modo === 'error') {
    check($r['status'] === 500, 'BD inexistente -> 500');
    check(($r['json']['success'] ?? null) === false, 'success=false');
    check(($r['json']['error']['code'] ?? null) === 'DATABASE_ERROR', 'error.code=DATABASE_ERROR');
    $raw = json_encode($r['json'] ?? [], JSON_UNESCAPED_UNICODE);
    check(!str_contains((string) $raw, 'SQLSTATE'), 'sin SQLSTATE/detalle del driver');
    check(!isset($r['json']['trace']) && !isset($r['json']['exception']), 'sin stack traces');
} elseif ($modo === 'empty') {
    check($r['status'] === 200, 'GET categorías -> 200');
    check(($r['json']['success'] ?? null) === true, 'success=true');
    check(($r['json']['data'] ?? null) === [], 'colección vacía data=[]');
    $meta = $r['json']['meta'] ?? null;
    check(($meta['total'] ?? null) === 0 && ($meta['page'] ?? null) === 1 && ($meta['limit'] ?? null) === 20, 'meta total=0 page=1 limit=20');
} else { // datos
    // Semilla (idempotente) directa contra la BD.
    $root = dirname(__DIR__);
    spl_autoload_register(static function (string $class) use ($root): void {
        $prefix = 'App\\';
        if (str_starts_with($class, $prefix)) {
            $file = $root . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });
    \App\Support\Env::load($root . '/.env');
    $pdo = \App\Support\Database::pdo();

    $rootRow = $pdo->query("SELECT id FROM catalog_categories WHERE nombre = 'Analgésicos' AND parent_id IS NULL LIMIT 1")->fetch();
    if ($rootRow === false) {
        $pdo->exec("INSERT INTO catalog_categories (nombre, parent_id) VALUES ('Analgésicos', NULL)");
        $rootId = (int) $pdo->lastInsertId();
    } else {
        $rootId = (int) $rootRow['id'];
    }
    $hija = $pdo->prepare("SELECT id FROM catalog_categories WHERE nombre = 'Ibuprofeno' AND parent_id = ? LIMIT 1");
    $hija->execute([$rootId]);
    if ($hija->fetch() === false) {
        $pdo->prepare("INSERT INTO catalog_categories (nombre, parent_id) VALUES ('Ibuprofeno', ?)")->execute([$rootId]);
    }
    unset($hija);

    // Listar DESPUÉS de sembrar (el GET inicial es previo a la siembra).
    $r = call("{$base}/api/v1/catalog/categories");

    check($r['status'] === 200, 'GET categorías -> 200');
    check(($r['json']['success'] ?? null) === true, 'success=true');
    $data = $r['json']['data'] ?? [];
    $meta = $r['json']['meta'] ?? [];
    check(($meta['total'] ?? 0) >= 2, 'meta.total >= 2');
    check(count($data) >= 2, 'data con al menos 2 elementos');

    $porNombre = [];
    foreach ($data as $row) {
        $porNombre[$row['nombre'] ?? '?'] = $row;
        check(!array_key_exists('parent_id_key', $row), 'no se expone parent_id_key');
        check(
            array_key_exists('parent_id', $row) && ($row['parent_id'] === null || is_int($row['parent_id'])),
            'parent_id es int|null'
        );
    }
    $rootOut = $porNombre['Analgésicos'] ?? null;
    check($rootOut !== null && $rootOut['parent_id'] === null, "Analgésicos con parent_id=null");
    check(
        isset($porNombre['Ibuprofeno']) && $porNombre['Ibuprofeno']['parent_id'] === $rootId,
        'Ibuprofeno.parent_id = id de Analgésicos'
    );

    // Paginación
    $p = call("{$base}/api/v1/catalog/categories?limit=1&page=2");
    check($p['status'] === 200 && count($p['json']['data'] ?? []) === 1, 'limit=1&page=2 -> 1 elemento');
    check(($p['json']['meta']['page'] ?? null) === 2 && ($p['json']['meta']['limit'] ?? null) === 1, 'meta page=2 limit=1');

    // Filtro q (case-insensitive)
    $q = call("{$base}/api/v1/catalog/categories?q=IBU");
    check($q['status'] === 200 && ($q['json']['meta']['total'] ?? 0) >= 1, 'q=IBU -> al menos 1');
    check(($q['json']['data'][0]['nombre'] ?? '') === 'Ibuprofeno', 'q=IBU encuentra Ibuprofeno');

    // Filtro raíz: parent_id=0 -> IS NULL
    $roots = call("{$base}/api/v1/catalog/categories?parent_id=0");
    $todosNulos = $roots['status'] === 200;
    foreach ($roots['json']['data'] ?? [] as $row) {
        $todosNulos = $todosNulos && $row['parent_id'] === null;
    }
    check($todosNulos, 'parent_id=0 -> solo categorías raíz');

    // Validación de parámetros
    $bad = call("{$base}/api/v1/catalog/categories?page=0");
    check($bad['status'] === 400, 'page=0 -> 400');
    check(($bad['json']['error']['code'] ?? null) === 'VALIDATION_ERROR', 'error.code=VALIDATION_ERROR');
}

echo $fail === 0 ? "RESULTADO: PASS\n" : "RESULTADO: FAIL ({$fail})\n";
exit($fail === 0 ? 0 : 1);
