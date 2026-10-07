<?php
declare(strict_types=1);

/**
 * CP-BACK-11 etapa 3 - Borrado logico e inmutabilidad append-only.
 *
 * Verificacion estatica del codigo de aplicacion (src/ + public/) y sondas
 * directas a BD (solo lectura):
 *   1. DELETE FROM solo sobre la lista blanca (junction, purge de blacklist,
 *      reasignacion de roles y reemplazo de items de ordenes en borrador,
 *      excepcion aprobada por el cliente el 2026-10-07). Todo lo demas usa
 *      borrado logico estado='inactivo'.
 *   2. Tablas append-only sin UPDATE/DELETE en la aplicacion (RNF-046).
 *   3. Sin DDL destructivo en codigo de aplicacion.
 *   4. Columna estado presente en las tablas de borrado logico + triggers de
 *      consistencia (V1.4.0) instalados.
 *
 * Uso: php tools/verify_cp_back11.php
 */

$root = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = $root . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

\App\Support\Env::load($root . '/.env');

$fail = 0;

function check(bool $ok, string $label): void
{
    global $fail;
    echo ($ok ? 'PASS' : 'FAIL') . ": {$label}\n";
    if (!$ok) {
        $fail++;
    }
}

// --- 1. recolectar codigo de aplicacion ---
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src'));
foreach ($it as $f) {
    if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
        $files[] = $f->getPathname();
    }
}
$files[] = $root . '/public/index.php';

/** Sin comentarios: evita falsos positivos con frases como "sin DELETE FROM". */
function sqlSource(string $code): string
{
    $code = preg_replace('/\/\*.*?\*\//s', ' ', $code) ?? $code;
    $code = preg_replace('/^\s*(\/\/|#).*$/m', ' ', $code) ?? $code;
    return $code;
}

// junction de productos, purge de tokens expirados y reasignacion de roles:
// formas de desvincular filas (tablas asociativas/purge).
// purchase_order_items: reemplazo de items de ordenes en BORRADOR (CP-BACK-05);
// excepcion APROBADA por el cliente el 2026-10-07 al validar CP-BACK-11
// (la orden nunca se borra; emitida/recibida no permite reemplazo).
$deleteOk = ['catalog_product_categories', 'token_blacklist', 'auth_user_roles', 'purchase_order_items'];

// kardex, libro de controlados, auditoria y recetas: solo INSERT (RNF-046).
$immutable = ['inventory_movements', 'ctrl_ledger_entries', 'audit_operations', 'audit_pii_access', 'rx_prescriptions'];

$badDelete = [];
$badImmutable = [];
$badDdl = [];

foreach ($files as $path) {
    $src = sqlSource((string)file_get_contents($path));
    $rel = str_replace([$root . '\\', $root . '/'], '', $path);

    if (preg_match_all('/\bDELETE\s+FROM\s+[`"\[]?(\w+)/i', $src, $m)) {
        foreach ($m[1] as $t) {
            if (!in_array(strtolower($t), $deleteOk, true)) {
                $badDelete[] = "{$rel}: DELETE FROM {$t}";
            }
        }
    }

    $src2 = preg_replace('/\bON\s+DUPLICATE\s+KEY\s+UPDATE\b/is', ' ', $src) ?? $src;
    if (preg_match_all('/\bUPDATE\s+[`"\[]?(\w+)/i', $src2, $m)) {
        foreach ($m[1] as $t) {
            if (in_array(strtolower($t), $immutable, true)) {
                $badImmutable[] = "{$rel}: UPDATE {$t}";
            }
        }
    }
    if (preg_match_all('/\bDELETE\s+FROM\s+[`"\[]?(\w+)/i', $src2, $m)) {
        foreach ($m[1] as $t) {
            if (in_array(strtolower($t), $immutable, true)) {
                $badImmutable[] = "{$rel}: DELETE FROM {$t}";
            }
        }
    }

    if (preg_match('/\b(TRUNCATE\s+TABLE|DROP\s+TABLE|ALTER\s+TABLE)\b/i', $src2, $m)) {
        $badDdl[] = "{$rel}: {$m[1]}";
    }
}

check($badDelete === [], 'DELETE FROM solo en lista blanca (junction/purge/roles)');
foreach ($badDelete as $x) {
    echo "  DETALLE: {$x}\n";
}
check($badImmutable === [], 'sin UPDATE/DELETE sobre tablas append-only (RNF-046)');
foreach ($badImmutable as $x) {
    echo "  DETALLE: {$x}\n";
}
check($badDdl === [], 'sin DDL destructivo en codigo de aplicacion');
foreach ($badDdl as $x) {
    echo "  DETALLE: {$x}\n";
}

// --- 2. sondas directas a BD (solo lectura) ---
try {
    $pdo = \App\Support\Database::pdo();

    $suaves = [
        'catalog_categories', 'auth_users', 'ops_stores', 'ops_registers',
        'catalog_products', 'catalog_suppliers', 'catalog_patients', 'catalog_prescribers',
    ];
    foreach ($suaves as $tabla) {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$tabla}` LIKE 'estado'");
        $stmt->execute();
        check($stmt->fetch() !== false, "{$tabla} conserva columna estado (borrado logico)");
    }

    $triggers = [];
    foreach ($pdo->query('SHOW TRIGGERS')->fetchAll() as $row) {
        $triggers[] = (string)($row['Trigger'] ?? $row[0] ?? '');
    }
    foreach (['trg_movements_sign', 'trg_ledger_vs_stock'] as $trg) {
        check(in_array($trg, $triggers, true), "trigger {$trg} instalado (V1.4.0)");
    }

    $info = (int)$pdo->query(
        "SELECT (SELECT COUNT(*) FROM catalog_categories WHERE estado='inactivo')
              + (SELECT COUNT(*) FROM auth_users WHERE estado='inactivo')"
    )->fetchColumn();
    echo "INFO: filas con estado='inactivo' presentes en BD (borrado logico): {$info}\n";
} catch (\PDOException $e) {
    check(false, 'sondas de BD ejecutadas: ' . $e->getMessage());
}

echo 'RESULTADO: ' . ($fail === 0 ? 'PASS' : "FAIL ({$fail})") . "\n";
exit($fail === 0 ? 0 : 1);
