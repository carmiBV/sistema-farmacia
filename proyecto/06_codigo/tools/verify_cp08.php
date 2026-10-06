<?php
declare(strict_types=1);

/**
 * Verificación CP-API-08 — Suite CRUD completa de /api/v1/catalog/categories
 *
 * Recorrido de integración de las 5 operaciones del contrato (health, GET lista,
 * GET {id}, POST, PUT, DELETE) con verificación cruzada del estado en cada paso,
 * más los caminos de error principales de cada operación.
 *
 * Uso: php tools/verify_cp08.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_cp08.php <base-url>\n");
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
$raiz = "CP08_R_{$sufijo}";
$hija = "CP08_H_{$sufijo}";

// --- health ---
$h = call('GET', "{$base}/api/v1/health");
check($h['status'] === 200 && ($h['json']['success'] ?? null) === true, 'health -> 200 success=true');

// --- 1. CREATE (POST) ---
$r = call('POST', $catUrl, ['nombre' => $raiz]);
check($r['status'] === 201 && ($r['json']['data']['nombre'] ?? null) === $raiz
    && array_key_exists('parent_id', $r['json']['data'] ?? []) && $r['json']['data']['parent_id'] === null,
    'POST raíz -> 201 con data{id,nombre,parent_id:null}');
$idR = $r['json']['data']['id'] ?? 0;

$r = call('POST', $catUrl, ['nombre' => $hija, 'parent_id' => $idR]);
check($r['status'] === 201 && ($r['json']['data']['parent_id'] ?? null) === $idR, 'POST hija (parent_id) -> 201');
$idH = $r['json']['data']['id'] ?? 0;
check($idH > 0 && $idR > 0 && $idH !== $idR, 'ids autoincrementales distintos');

// --- 2. READ (GET lista + GET id) ---
$lista = call('GET', $catUrl . '?q=' . urlencode($sufijo) . '&limit=100');
$idsLista = array_column($lista['json']['data'] ?? [], 'id');
check($lista['status'] === 200 && in_array($idR, $idsLista, true) && in_array($idH, $idsLista, true),
    'GET lista contiene ambas categorías');
check(!array_key_exists('parent_id_key', $lista['json']['data'][0] ?? []),
    'lista no expone parent_id_key');

$g = call('GET', "{$catUrl}/{$idR}");
check($g['status'] === 200 && ($g['json']['data']['nombre'] ?? null) === $raiz, 'GET {id} raíz -> 200');
$gH = call('GET', "{$catUrl}/{$idH}");
check($gH['status'] === 200 && ($gH['json']['data']['parent_id'] ?? null) === $idR, 'GET {id} hija -> 200 con parent_id');

$filtroRaiz = call('GET', $catUrl . '?parent_id=0&limit=100');
check($filtroRaiz['status'] === 200 && in_array($idR, array_column($filtroRaiz['json']['data'] ?? [], 'id'), true),
    'filtro parent_id=0 incluye la raíz');

// --- 3. UPDATE (PUT) ---
$u = call('PUT', "{$catUrl}/{$idR}", ['nombre' => "{$raiz}_v2"]);
check($u['status'] === 200 && ($u['json']['data']['nombre'] ?? null) === "{$raiz}_v2", 'PUT rename -> 200');
$g2 = call('GET', "{$catUrl}/{$idR}");
check(($g2['json']['data']['nombre'] ?? null) === "{$raiz}_v2", 'GET refleja el rename (persistencia)');

$u = call('PUT', "{$catUrl}/{$idH}", ['nombre' => $hija, 'parent_id' => null]);
check($u['status'] === 200 && ($u['json']['data']['parent_id'] ?? null) === null, 'PUT mover hija a raíz (parent_id=null) -> 200');
$u = call('PUT', "{$catUrl}/{$idH}", ['nombre' => $hija, 'parent_id' => $idR]);
check($u['status'] === 200 && ($u['json']['data']['parent_id'] ?? null) === $idR, 'PUT re-padre hija -> 200');

// --- 4. DELETE (borrado lógico) ---
$d = call('DELETE', "{$catUrl}/{$idH}");
check($d['status'] === 204 && $d['body'] === '', 'DELETE hija -> 204 sin cuerpo');
check(call('GET', "{$catUrl}/{$idH}")['status'] === 404, 'GET hija tras DELETE -> 404');
check(call('PUT', "{$catUrl}/{$idH}", ['nombre' => 'X'])['status'] === 404, 'PUT hija tras DELETE -> 404');
check(call('DELETE', "{$catUrl}/{$idH}")['status'] === 404, 'DELETE repetido -> 404');
$lista2 = call('GET', $catUrl . '?q=' . urlencode($hija));
check(($lista2['json']['meta']['total'] ?? -1) === 0, 'lista no incluye inactivada');

// --- 5. Caminos de error cruzados ---
check(call('POST', $catUrl, ['nombre' => $hija, 'parent_id' => $idR])['status'] === 409,
    'POST nombre de inactiva en MISMA rama -> 409 (uq cubre inactivas)');
$rOtraRama = call('POST', $catUrl, ['nombre' => $hija]);
check($rOtraRama['status'] === 201,
    'POST mismo nombre en OTRA rama -> 201 (uq es por rama)');
$idNueva = $rOtraRama['json']['data']['id'] ?? 0;
check(call('GET', "{$catUrl}/999999")['status'] === 404, 'GET inexistente -> 404');
check(call('POST', $catUrl, ['nombre' => ''])['status'] === 400, 'POST nombre vacío -> 400');
check(call('GET', $catUrl . '?limit=0')['status'] === 400, 'GET limit=0 -> 400');
check(call('DELETE', "{$catUrl}/abc")['status'] === 400, 'DELETE id abc -> 400');

// --- 6. Limpieza lógica de fixtures ---
check(call('DELETE', "{$catUrl}/{$idNueva}")['status'] === 204, 'DELETE fixture extra -> 204');
check(call('DELETE', "{$catUrl}/{$idR}")['status'] === 204, 'DELETE raíz fixture -> 204');
check(call('GET', "{$catUrl}/{$idR}")['status'] === 404, 'GET raíz tras DELETE -> 404');

echo $fail === 0 ? "RESULTADO: PASS\n" : "RESULTADO: FAIL ({$fail})\n";
exit($fail === 0 ? 0 : 1);
