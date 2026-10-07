<?php
declare(strict_types=1);

/**
 * CP-FRONT-11 - Responsive, Accesibilidad WCAG y suite de integracion.
 *
 * Auditoria estructural WCAG 2.2 AA sobre las 21 pantallas (parseo DOM real):
 *   - idioma declarado (3.1.1), meta viewport, un solo h1 (1.3.1)
 *   - todo control de formulario con etiqueta asociada (1.3.1 / 3.3.2)
 *   - botones con nombre accesible (4.1.2), imagenes con alt (1.1.1)
 *   - th con scope (1.3.1), region de errores role=alert (4.1.3)
 *   - skip-link al contenido (2.4.1) y CSS responsive (@media)
 *
 * Uso: php tools/verify_cp_front11.php http://127.0.0.1:8000
 */

$base = $argv[1] ?? '';
if ($base === '') {
    fwrite(STDERR, "Uso: php verify_cp_front11.php <base-url>\n");
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

$paginas = [
    '/login', '/dashboard', '/autenticacion-usuarios', '/configuracion-sucursales',
    '/catalogo-categorias', '/catalogo-productos', '/catalogo-precios-promociones',
    '/gestion-proveedores', '/gestion-pacientes-prescriptores',
    '/compras-ordenes', '/compras-recepciones',
    '/inventario-lotes-fefo', '/inventario-stock-movimientos',
    '/inventario-transferencias', '/inventario-alertas-incidentes',
    '/recetas-medicas', '/ventas-pos', '/ventas-pagos-transacciones',
    '/ventas-devoluciones', '/libro-controlados', '/auditoria-operaciones-pii',
];

libxml_use_internal_errors(true);

foreach ($paginas as $ruta) {
    $html = (string)file_get_contents($base . $ruta, false,
        stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true]]));
    if ($html === '') {
        check(false, "{$ruta}: pagina vacia");
        continue;
    }
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8">' . $html);
    $xp = new DOMXPath($dom);

    $errores = [];

    $htmlEl = $xp->query('//html')->item(0);
    if (!$htmlEl || trim((string)$htmlEl->getAttribute('lang')) === '') {
        $errores[] = 'sin lang en <html>';
    }
    if ($xp->query('//meta[contains(@name,"viewport")]')->length === 0) {
        $errores[] = 'sin meta viewport';
    }
    if ($xp->query('//h1')->length !== 1) {
        $errores[] = 'h1 != 1';
    }

    foreach ($xp->query('//input[not(@type="hidden")] | //select | //textarea') as $ctrl) {
        $id = (string)$ctrl->getAttribute('id');
        $etiquetado = ($id !== '' && $xp->query('//label[@for=' . xpathStr($id) . ']')->length > 0)
            || $xp->query('ancestor::label', $ctrl)->length > 0
            || trim((string)$ctrl->getAttribute('aria-label')) !== ''
            || trim((string)$ctrl->getAttribute('aria-labelledby')) !== '';
        if (!$etiquetado) {
            $errores[] = 'control sin etiqueta: ' . $ctrl->nodeName . '#' . $id;
        }
    }

    foreach ($xp->query('//button') as $btn) {
        $nombre = trim((string)$btn->textContent) . (string)$btn->getAttribute('aria-label');
        if (trim($nombre) === '') {
            $errores[] = 'boton sin nombre accesible';
        }
    }

    foreach ($xp->query('//img[not(@alt)]') as $img) {
        $errores[] = 'img sin alt';
    }

    foreach ($xp->query('//th') as $th) {
        if (trim((string)$th->getAttribute('scope')) === '') {
            $errores[] = 'th sin scope';
        }
    }

    if ($xp->query('//*[@role="alert"]')->length === 0) {
        $errores[] = 'sin region de errores role=alert';
    }
    if ($ruta !== '/login' && $xp->query('//a[contains(@class,"skip-link")]')->length === 0) {
        $errores[] = 'sin skip-link';
    }

    check($errores === [], "{$ruta}: WCAG estructural" . ($errores !== [] ? ' (' . implode('; ', array_slice($errores, 0, 4)) . ')' : ''));
}

function xpathStr(string $v): string
{
    return "'" . str_replace("'", "\\'", $v) . "'";
}

// --- responsive (CSS) ---
$css = (string)file_get_contents($base . '/assets/css/app.css');
check(str_contains($css, '@media'), 'app.css con reglas responsive (@media)');
check(str_contains($css, 'focus-visible'), 'app.css con indicador de foco visible (2.4.7)');
check(str_contains($css, 'min-height: 40px'), 'objetivos tactiles >= 40px en botones mini (2.5.8)');

echo 'RESULTADO: ' . ($fail === 0 ? 'PASS' : "FAIL ({$fail})") . "\n";
exit($fail === 0 ? 0 : 1);
