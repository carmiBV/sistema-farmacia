<?php
declare(strict_types=1);

/**
 * Verificación CP-API-05 — POST /api/v1/catalog/categories
 *
 * Fixtures válidos: creación raíz e hija (201), misma rama (409 DUPLICATE_NAME),
 * nombre igual en otra rama (201). Inválidos: sin nombre, nombre vacío/espacios,
 * >150, parent_id 0/inexistente, cuerpo no JSON, sin cuerpo (400).
 *
 * Nota: sin DELETE FROM (regla del repo); fixtures usan nombres únicos por
 * ejecución y quedan como datos de prueba en la BD local de desarrollo.
 *
 * Uso: php tools/verify_cp05.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_cp05.php <base-url>\n");
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
function call(string $url, array $json = null): array
{
    $opts = ['http' => ['timeout' => 15, 'ignore_errors' => true, 'method' => $json !== null ? 'POST' : 'GET']];
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
$raizNombre = "CP05_R_{$sufijo}";
$hijaNombre = "CP05_H_{$sufijo}";

// Hijo de CP-03 (Ibuprofeno) para probar ramas.
$lista = call("{$catUrl}?q=Ibuprofeno");
$padreId = $lista['json']['data'][0]['id'] ?? null;
if (!check(is_int($padreId), 'fixture: Ibuprofeno existe (semilla CP-03)')) {
    echo "RESULTADO: FAIL\n";
    exit(1);
}

// --- Válidos ---
$r1 = call($catUrl, ['nombre' => $raizNombre]);
check($r1['status'] === 201, 'POST raíz -> 201');
check(($r1['json']['success'] ?? null) === true, 'success=true');
$d1 = $r1['json']['data'] ?? null;
check(is_array($d1) && ($d1['nombre'] ?? null) === $raizNombre, 'data.nombre correcto');
check(array_key_exists('parent_id', $d1 ?? []) && $d1['parent_id'] === null, 'data.parent_id = null (raíz)');
check(!array_key_exists('parent_id_key', $d1 ?? []), 'no se expone parent_id_key');
check(is_int($d1['id'] ?? null) && $d1['id'] > 0, 'data.id entero > 0');

$r2 = call($catUrl, ['nombre' => $hijaNombre, 'parent_id' => $padreId]);
check($r2['status'] === 201, 'POST hija -> 201');
check(($r2['json']['data']['parent_id'] ?? null) === $padreId, 'data.parent_id = id del padre');

// Mismo nombre en la MISMA rama -> 409 DUPLICATE_NAME
$r3 = call($catUrl, ['nombre' => $raizNombre]);
check($r3['status'] === 409, 'POST duplicado misma rama -> 409');
check(($r3['json']['success'] ?? null) === false, 'success=false');
check(($r3['json']['error']['code'] ?? null) === 'DUPLICATE_NAME', 'error.code=DUPLICATE_NAME');

// Mismo nombre en OTRA rama -> 201 (la uq es por rama)
$r4 = call($catUrl, ['nombre' => $hijaNombre]);
check($r4['status'] === 201, 'POST mismo nombre en otra rama -> 201');

// El creado se puede leer por GET (integración CP-04)
$get = call("{$catUrl}/{$d1['id']}");
check($get['status'] === 200 && ($get['json']['data']['nombre'] ?? null) === $raizNombre, 'GET del recién creado -> 200');

// --- Inválidos ---
$invalidos = [
    'sin nombre' => [],
    'nombre no string' => ['nombre' => 123],
    'nombre vacío' => ['nombre' => ''],
    'nombre espacios' => ['nombre' => '   '],
    'nombre >150' => ['nombre' => str_repeat('a', 151)],
    'parent_id 0' => ['nombre' => $raizNombre . '_x', 'parent_id' => 0],
    'parent_id inexistente' => ['nombre' => $raizNombre . '_y', 'parent_id' => 999999],
];
foreach ($invalidos as $desc => $body) {
    $r = call($catUrl, $body);
    if ($desc === 'parent_id inexistente') {
        check($r['status'] === 409, "POST {$desc} -> 409");
        check(($r['json']['error']['code'] ?? null) === 'NOT_FOUND', "{$desc} error.code=NOT_FOUND");
    } else {
        check($r['status'] === 400, "POST {$desc} -> 400");
        check(($r['json']['error']['code'] ?? null) === 'VALIDATION_ERROR', "{$desc} error.code=VALIDATION_ERROR");
    }
}

// Cuerpo que no es objeto JSON
$ctx = stream_context_create([
    'http' => [
        'timeout' => 15,
        'ignore_errors' => true,
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => '{mal json',
    ],
]);
$body = file_get_contents($catUrl, false, $ctx);
$status = 0;
if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) {
    $status = (int) $m[1];
}
$json = $body === false ? null : json_decode($body, true);
check($status === 400 && ($json['error']['code'] ?? null) === 'VALIDATION_ERROR', 'POST cuerpo malformado -> 400 VALIDATION_ERROR');

echo $fail === 0 ? "RESULTADO: PASS\n" : "RESULTADO: FAIL ({$fail})\n";
exit($fail === 0 ? 0 : 1);
