<?php
declare(strict_types=1);
/**
 * Shell compartido del frontend (CP-FRONT-02/03): sidebar + header + contenido.
 * Params: $titulo (string), $script (string, JS de la pagina), $actual (path),
 * $contenido (ruta del fragmento), $conSelector (bool, selector de sucursal).
 */
$enlaces = [
    '/dashboard' => 'Dashboard',
    '/ventas-pos' => 'Punto de Venta',
    '/ventas-pagos-transacciones' => 'Ventas y Pagos',
    '/ventas-devoluciones' => 'Devoluciones',
    '/autenticacion-usuarios' => 'Usuarios y Permisos',
    '/configuracion-sucursales' => 'Configuración y Sucursales',
    '/catalogo-categorias' => 'Categorías',
    '/catalogo-productos' => 'Productos',
    '/catalogo-precios-promociones' => 'Precios y Promociones',
    '/gestion-proveedores' => 'Proveedores',
    '/gestion-pacientes-prescriptores' => 'Pacientes y Prescriptores',
    '/compras-ordenes' => 'Órdenes de Compra',
    '/compras-recepciones' => 'Recepciones',
    '/inventario-lotes-fefo' => 'Lotes FEFO',
    '/inventario-stock-movimientos' => 'Stock y Movimientos',
    '/inventario-transferencias' => 'Transferencias',
    '/inventario-alertas-incidentes' => 'Alertas e Incidentes',
    '/recetas-medicas' => 'Recetas Médicas',
    '/libro-controlados' => 'Libro de Controlados',
    '/auditoria-operaciones-pii' => 'Auditoría y PII',
];
$pendientes = [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($titulo); ?> — Sistema de Farmacia</title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="/assets/js/<?php echo htmlspecialchars($script); ?>.js" defer></script>
</head>
<body class="app-body">
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<div class="app-layout">
    <aside class="sidebar">
        <div class="brand">Sistema de Farmacia</div>
        <nav aria-label="Módulos del sistema">
            <ul class="nav-list">
                <li class="nav-seccion">General</li>
                <?php foreach ($enlaces as $href => $nombre): ?>
                <li><a href="<?php echo htmlspecialchars($href); ?>"
                        <?php echo $actual === $href ? 'aria-current="page"' : ''; ?>><?php echo htmlspecialchars($nombre); ?></a></li>
                <?php endforeach; ?>
                <?php foreach ($pendientes as $seccion => $items): ?>
                <li class="nav-seccion"><?php echo htmlspecialchars($seccion); ?></li>
                <?php foreach ($items as $item): ?>
                <li><span class="nav-pendiente"><?php echo htmlspecialchars($item); ?></span></li>
                <?php endforeach; ?>
                <?php endforeach; ?>
            </ul>
        </nav>
    </aside>

    <div class="app-main">
        <header class="app-header">
            <h1><?php echo htmlspecialchars($titulo); ?></h1>
            <div class="header-right">
                <?php if (!empty($conSelector)): ?>
                <div class="field field-inline">
                    <label for="store-select">Sucursal</label>
                    <select id="store-select">
                        <option value="">Todas</option>
                    </select>
                </div>
                <?php endif; ?>
                <span id="header-user" class="user-chip">…</span>
            </div>
        </header>

        <main class="app-content" id="contenido">
            <div id="app-error" class="alert alert-error" role="alert" hidden></div>
            <?php require $contenido; ?>
        </main>
    </div>
</div>
<script>
// Guard de sesion + usuario activo (CP-FRONT-03): sin sf_token -> /login.
(function () {
    var t = localStorage.getItem('sf_token');
    if (!t) { window.location.replace('/login'); return; }
    try {
        var u = JSON.parse(localStorage.getItem('sf_user') || '{}');
        var c = document.getElementById('header-user');
        if (c && u.usuario) {
            c.textContent = u.usuario + (u.roles && u.roles.length ? ' · ' + u.roles.join(', ') : '');
        }
    } catch (e) { /* sin datos de usuario */ }
})();
</script>
</body>
</html>
