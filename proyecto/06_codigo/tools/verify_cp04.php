<?php
declare(strict_types=1);

/**
 * Verificación CP-API-04 — GET /api/v1/catalog/categories/{id}
 *
 * Casos: 200 existente, 404 inexistente, 400 formato inválido (no numérico,
 * 0, fuera de rango), 404 sin coincidencia de ruta.
 *
 * Uso: php tools/verify_cp04.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_cp04.php <base-url>\n");
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

// 200: usar una categoría existente (siembra de CP-API-03).
$lista = call("{$base}/api/v1/catalog/categories");
$existe = $lista['json']['data'][0] ?? null;
if (!check($existe !== null && isset($existe['id']), 'fixture: hay categorías en la BD (semilla CP-03)')) {
    echo "RESULTADO: FAIL\n";
    exit(1);
}
$id = (int) $existe['id'];

$ok = call("{$base}/api/v1/catalog/categories/{$id}");
check($ok['status'] === 200, "GET /{$id} -> 200");
check(($ok['json']['success'] ?? null) === true, 'success=true');
$data = $ok['json']['data'] ?? null;
check(is_array($data) && ($data['id'] ?? null) === $id, "data.id = {$id}");
check(isset($data['nombre']) && is_string($data['nombre']) && $data['nombre'] !== '', 'data.nombre string no vacío');
check(array_key_exists('parent_id', $data ?? []) && ($data['parent_id'] === null || is_int($data['parent_id'])), 'parent_id int|null');
check(!array_key_exists('parent_id_key', $data ?? []), 'no se expone parent_id_key');

// 404: id inexistente
$nf = call("{$base}/api/v1/catalog/categories/999999");
check($nf['status'] === 404, 'GET id inexistente -> 404');
check(($nf['json']['success'] ?? null) === false, 'success=false');
check(($nf['json']['error']['code'] ?? null) === 'NOT_FOUND', 'error.code=NOT_FOUND');

// 400: formato inválido
foreach (['abc' => 'no numérico', '0' => 'menor que minimum 1', '99999999999999999999' => 'fuera de rango int'] as $mal => $desc) {
    $bad = call("{$base}/api/v1/catalog/categories/{$mal}");
    check($bad['status'] === 400, "GET id '{$mal}' ({$desc}) -> 400");
    check(($bad['json']['error']['code'] ?? null) === 'VALIDATION_ERROR', "id '{$mal}' error.code=VALIDATION_ERROR");
}

// Ruta sin id -> 404 del router
$noRuta = call("{$base}/api/v1/catalog/categories/");
check($noRuta['status'] === 404 && ($noRuta['json']['error']['code'] ?? null) === 'NOT_FOUND', 'ruta sin id -> 404 NOT_FOUND');

echo $fail === 0 ? "RESULTADO: PASS\n" : "RESULTADO: FAIL ({$fail})\n";
exit($fail === 0 ? 0 : 1);
