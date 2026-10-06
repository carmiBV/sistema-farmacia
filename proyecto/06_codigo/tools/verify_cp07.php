<?php
declare(strict_types=1);

/**
 * Verificación CP-API-07 — DELETE /api/v1/catalog/categories/{id} (borrado lógico)
 *
 * Cubre: 204 sin cuerpo, 400 id inválido, 404 inexistente / ya inactiva,
 * desaparición del listado, y bloqueo de reutilización de nombre (índice único
 * cubre filas inactivas — comportamiento documentado en openapi.yaml).
 *
 * Requiere migración V1.5.0 (columna `estado`). Sin DELETE FROM (regla del repo);
 * fixtures con nombre único por ejecución quedan en la BD local de desarrollo.
 *
 * Uso: php tools/verify_cp07.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_cp07.php <base-url>\n");
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
    $raw = $body === false ? '' : $body;
    return ['status' => $status, 'json' => $raw === '' ? null : json_decode($raw, true), 'body' => $raw];
}

$catUrl = "{$base}/api/v1/catalog/categories";
$sufijo = substr(md5((string) random_int(0, PHP_INT_MAX)), 0, 8);
$rootNombre = "CP07_R_{$sufijo}";
$childNombre = "CP07_C_{$sufijo}";

// Fixtures vía API (CP-API-05)
$rRoot = call('POST', $catUrl, ['nombre' => $rootNombre]);
$rChild = call('POST', $catUrl, ['nombre' => $childNombre]);
if (!check($rRoot['status'] === 201 && $rChild['status'] === 201, 'fixtures creadas (2x POST 201)')) {
    echo "RESULTADO: FAIL\n";
    exit(1);
}
$idRoot = $rRoot['json']['data']['id'];
$idChild = $rChild['json']['data']['id'];

$del = static fn(string $id) => call('DELETE', "{$catUrl}/{$id}");
$get = static fn(string $id) => call('GET', "{$catUrl}/{$id}");
$put = static fn(string $id) => call('PUT', "{$catUrl}/{$id}", ['nombre' => 'X']);

// --- 204: borrado lógico sin cuerpo ---
$r = $del((string) $idChild);
check($r['status'] === 204, 'DELETE -> 204');
check($r['body'] === '', '204 sin cuerpo');

// --- El registro inactivado desaparece de la API ---
check($get((string) $idChild)['status'] === 404, 'GET tras DELETE -> 404');
check($put((string) $idChild)['status'] === 404, 'PUT tras DELETE -> 404');

$lista = call('GET', $catUrl . '?q=' . urlencode($childNombre));
check($lista['status'] === 200 && ($lista['json']['meta']['total'] ?? -1) === 0
    && ($lista['json']['data'] ?? []) === [], 'lista filtrada no incluye la categoría inactivada');

$listaRoot = call('GET', $catUrl . '?q=' . urlencode($rootNombre));
check($listaRoot['status'] === 200 && ($listaRoot['json']['meta']['total'] ?? -1) >= 1, 'categoría activa sigue en la lista');

// --- 404: segunda eliminación (ya inactiva) y id inexistente ---
$r = $del((string) $idChild);
check($r['status'] === 404 && ($r['json']['error']['code'] ?? null) === 'NOT_FOUND', 'DELETE repetido -> 404 NOT_FOUND');
$r = $del('999999');
check($r['status'] === 404 && ($r['json']['error']['code'] ?? null) === 'NOT_FOUND', 'DELETE id inexistente -> 404 NOT_FOUND');

// --- 400: id inválido ---
$r = $del('abc');
check($r['status'] === 400 && ($r['json']['error']['code'] ?? null) === 'VALIDATION_ERROR', 'DELETE id no numérico -> 400 VALIDATION_ERROR');

// --- Nombre no se reutiliza: uq (nombre, parent_id_key) cubre filas inactivas ---
$r = call('POST', $catUrl, ['nombre' => $childNombre]);
check($r['status'] === 409 && ($r['json']['error']['code'] ?? null) === 'DUPLICATE_NAME',
    'POST del nombre inactivado -> 409 DUPLICATE_NAME (limitación documentada)');

// --- La fila sigue en BD (borrado lógico, no físico): DELETE NO la elimina ---
// Se valida por API: no existe endpoint que la muestre, pero el 409 anterior
// prueba que la fila persiste con el nombre reservado.
check($r['status'] === 409, 'la fila inactiva persiste en BD (prueba indirecta)');

// --- Limpieza lógica de la raíz fixture ---
$r = $del((string) $idRoot);
check($r['status'] === 204, 'DELETE raíz fixture -> 204');
check($get((string) $idRoot)['status'] === 404, 'GET raíz tras DELETE -> 404');

echo $fail === 0 ? "RESULTADO: PASS\n" : "RESULTADO: FAIL ({$fail})\n";
exit($fail === 0 ? 0 : 1);
