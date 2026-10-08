<?php
declare(strict_types=1);

// Registro de páginas web (21 pantallas del sistema). Las vistas viven en
// app/Views/; el layout compartido en app/Views/layouts/app.php.
// La API NO se declara aquí (ver routes/api.php).

use App\Controllers\Web\PageController;

/** @var \App\Core\Router $router */

$router->add('GET', '/', static fn() => (new PageController())->render('login', 'Iniciar sesión'));
$router->add('GET', '/login', static fn() => (new PageController())->render('login', 'Iniciar sesión'));
$router->add('GET', '/dashboard', static fn() => (new PageController())->render('dashboard', 'Dashboard', true));
$router->add('GET', '/autenticacion-usuarios', static fn() => (new PageController())->render('usuarios', 'Usuarios y Permisos'));
$router->add('GET', '/configuracion-sucursales', static fn() => (new PageController())->render('configuracion', 'Configuración y Sucursales'));
$router->add('GET', '/catalogo-categorias', static fn() => (new PageController())->render('categorias', 'Categorías'));
$router->add('GET', '/catalogo-productos', static fn() => (new PageController())->render('productos', 'Productos'));
$router->add('GET', '/catalogo-precios-promociones', static fn() => (new PageController())->render('precios', 'Precios y Promociones'));
$router->add('GET', '/gestion-proveedores', static fn() => (new PageController())->render('proveedores', 'Proveedores'));
$router->add('GET', '/gestion-pacientes-prescriptores', static fn() => (new PageController())->render('pacientes', 'Pacientes y Prescriptores'));
$router->add('GET', '/compras-ordenes', static fn() => (new PageController())->render('ordenes', 'Órdenes de Compra'));
$router->add('GET', '/compras-recepciones', static fn() => (new PageController())->render('recepciones', 'Recepciones'));
$router->add('GET', '/inventario-lotes-fefo', static fn() => (new PageController())->render('lotes', 'Lotes FEFO'));
$router->add('GET', '/inventario-stock-movimientos', static fn() => (new PageController())->render('stock', 'Stock y Movimientos'));
$router->add('GET', '/inventario-transferencias', static fn() => (new PageController())->render('transferencias', 'Transferencias'));
$router->add('GET', '/inventario-alertas-incidentes', static fn() => (new PageController())->render('alertas', 'Alertas e Incidentes'));
$router->add('GET', '/recetas-medicas', static fn() => (new PageController())->render('recetas', 'Recetas Médicas'));
$router->add('GET', '/libro-controlados', static fn() => (new PageController())->render('controlados', 'Libro de Controlados'));
$router->add('GET', '/ventas-pos', static fn() => (new PageController())->render('pos', 'Punto de Venta'));
$router->add('GET', '/ventas-pagos-transacciones', static fn() => (new PageController())->render('pagos', 'Ventas y Pagos'));
$router->add('GET', '/ventas-devoluciones', static fn() => (new PageController())->render('devoluciones', 'Devoluciones'));
$router->add('GET', '/auditoria-operaciones-pii', static fn() => (new PageController())->render('auditoria', 'Auditoría y PII'));
