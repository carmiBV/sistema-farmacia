<?php
declare(strict_types=1);

/**
 * Verificación CP-API-06 — PUT /api/v1/catalog/categories/{id}
 *
 * Cubre: 200 (rename, mover a raíz, re-padre, conservar parent_id si se omite),
 * 404 inexistente, 400 (id inválido, nombre faltante/vacío/>150, parent_id 0,
 * cuerpo malformado), 409 (DUPLICATE_NAME en misma rama, HIERARCHY_CYCLE
 * self/descendiente, NOT_FOUND padre inexistente) e integración GET.
 *
 * Nota: sin DELETE FROM (regla del repo); fixtures con nombre único por
 * ejecución quedan como datos de prueba en la BD local de desarrollo.
 *
 * Uso: php tools/verify_cp06.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_cp06.php <base-url>\n");
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
function call(string $method, string $url, array $json = null): array
{
    $opts = ['http' => ['timeout' => 15, 'ignore_errors' => true, 'method' => $method]];
    if ($json !== null) {
        $opts['http']['header'] = "Content-Type: application/json\r\n";
        $opts['http']['content'] = json_encode($json, JSON_UNESCAPED_UNICODE);
    }
    $body = file_get_contents($url, false, stream_context_create($opts));
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }
    return ['status' => $status, 'json' => $body === false ? null : json_decode($body, true)];
}

$catUrl = "{$base}/api/v1/catalog/categories";
$sufijo = substr(md5((string) random_int(0, PHP_INT_MAX)), 0, 8);
$rootNombre = "CP06_R_{$sufijo}";
$childNombre = "CP06_C_{$sufijo}";
$dupNombre = "CP06_D_{$sufijo}";

// Fixtures vía API (CP-API-05)
$mk = static fn(array $b) => call('POST', $catUrl, $b);
$rRoot = $mk(['nombre' => $rootNombre]);
$rChild = $mk(['nombre' => $childNombre]);
$rDup = $mk(['nombre' => $dupNombre]);
if (!check(
    $rRoot['status'] === 201 && $rChild['status'] === 201 && $rDup['status'] === 201,
    'fixtures creadas (3x POST 201)'
)) {
    echo "RESULTADO: FAIL\n";
    exit(1);
}
$idRoot = $rRoot['json']['data']['id'];
$idChild = $rChild['json']['data']['id'];
$idDup = $rDup['json']['data']['id'];

$put = static fn($id, array $b) => call('PUT', "{$catUrl}/{$id}", $b);

// --- 200 ---
$r = $put($idRoot, ['nombre' => "{$rootNombre}_A"]);
check($r['status'] === 200, 'PUT rename -> 200');
check(($r['json']['data']['nombre'] ?? null) === "{$rootNombre}_A", 'data.nombre actualizado');
check(array_key_exists('parent_id', $r['json']['data'] ?? []) && $r['json']['data']['parent_id'] === null, 'parent_id conserva null');

$r = $put($idChild, ['nombre' => $childNombre, 'parent_id' => null]);
check($r['status'] === 200 && $r['json']['data']['parent_id'] === null, 'PUT parent_id=null -> 200, mover a raíz');

$r = $put($idChild, ['nombre' => $childNombre, 'parent_id' => $idRoot]);
check($r['status'] === 200 && $r['json']['data']['parent_id'] === $idRoot, 'PUT re-padre -> 200, parent_id=idRoot');

$r = $put($idChild, ['nombre' => "{$childNombre}_B"]);
check($r['status'] === 200 && $r['json']['data']['parent_id'] === $idRoot, 'PUT sin parent_id -> conserva padre actual');

// --- 409 ---
$r = $put($idChild, ['nombre' => $childNombre, 'parent_id' => $idChild]);
check($r['status'] === 409, 'PUT parent_id=self -> 409');
check(($r['json']['error']['code'] ?? null) === 'HIERARCHY_CYCLE', 'self: error.code=HIERARCHY_CYCLE');

$r = $put($idRoot, ['nombre' => "{$rootNombre}_A", 'parent_id' => $idChild]);
check($r['status'] === 409 && ($r['json']['error']['code'] ?? null) === 'HIERARCHY_CYCLE', 'PUT padre=descendiente -> 409 HIERARCHY_CYCLE');

$r = $put($idRoot, ['nombre' => "{$rootNombre}_A", 'parent_id' => 999999]);
check($r['status'] === 409 && ($r['json']['error']['code'] ?? null) === 'NOT_FOUND', 'PUT padre inexistente -> 409 NOT_FOUND');

$r = $put($idRoot, ['nombre' => $dupNombre]);
check($r['status'] === 409 && ($r['json']['error']['code'] ?? null) === 'DUPLICATE_NAME', 'PUT duplicado misma rama -> 409 DUPLICATE_NAME');

$r = $put($idRoot, ['nombre' => $dupNombre, 'parent_id' => $idDup]);
check($r['status'] === 200, 'PUT mismo nombre en OTRA rama -> 200');

// --- 404 ---
$r = $put('999999', ['nombre' => 'X']);
check($r['status'] === 404 && ($r['json']['error']['code'] ?? null) === 'NOT_FOUND', 'PUT id inexistente -> 404 NOT_FOUND');

// --- 400 ---
$invalidos = [
    'id no numérico' => ['abc', ['nombre' => 'X']],
    'sin nombre' => [(string) $idRoot, []],
    'nombre vacío' => [(string) $idRoot, ['nombre' => '']],
    'nombre >150' => [(string) $idRoot, ['nombre' => str_repeat('a', 151)]],
    'parent_id 0' => [(string) $idRoot, ['nombre' => 'X', 'parent_id' => 0]],
];
foreach ($invalidos as $desc => [$id, $body]) {
    $r = $put($id, $body);
    check($r['status'] === 400 && ($r['json']['error']['code'] ?? null) === 'VALIDATION_ERROR', "PUT {$desc} -> 400 VALIDATION_ERROR");
}

$opts = [
    'http' => [
        'timeout' => 15,
        'ignore_errors' => true,
        'method' => 'PUT',
        'header' => "Content-Type: application/json\r\n",
        'content' => '{mal json',
    ],
];
$body = file_get_contents("{$catUrl}/{$idRoot}", false, stream_context_create($opts));
$status = 0;
if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) {
    $status = (int) $m[1];
}
$json = $body === false ? null : json_decode($body, true);
check($status === 400 && ($json['error']['code'] ?? null) === 'VALIDATION_ERROR', 'PUT cuerpo malformado -> 400 VALIDATION_ERROR');

// --- Integración: el último estado se lee por GET ---
$get = call('GET', "{$catUrl}/{$idRoot}");
check($get['status'] === 200 && ($get['json']['data']['nombre'] ?? null) === $dupNombre
    && ($get['json']['data']['parent_id'] ?? null) === $idDup, 'GET tras PUT -> 200 con nombre y parent_id esperados');

echo $fail === 0 ? "RESULTADO: PASS\n" : "RESULTADO: FAIL ({$fail})\n";
exit($fail === 0 ? 0 : 1);
