<?php
declare(strict_types=1);

// Front Controller: SOLO bootstrap y despacho. Sin lógica de negocio ni SQL.
// decisiones_backend.md: Front Controller → Router → Controllers → Validators → Services → Repositories.

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

$router = new \App\Http\Router();
$router->add('GET', '/api/v1/health', static fn() => (new \App\Controllers\HealthController())->check());
$router->add('GET', '/api/v1/catalog/categories', static fn() => (new \App\Controllers\CategoriaController())->index());
$router->add('GET', '/api/v1/catalog/categories/{id}', static fn(array $p) => (new \App\Controllers\CategoriaController())->show($p['id'] ?? ''));
$router->add('POST', '/api/v1/catalog/categories', static fn() => (new \App\Controllers\CategoriaController())->store());
$router->add('PUT', '/api/v1/catalog/categories/{id}', static fn(array $p) => (new \App\Controllers\CategoriaController())->update($p['id'] ?? ''));
$router->add('DELETE', '/api/v1/catalog/categories/{id}', static fn(array $p) => (new \App\Controllers\CategoriaController())->destroy($p['id'] ?? ''));

// CP-BACK-02: autenticación y RBAC. 4º parámetro: null = pública,
// '' = requiere token -> 'clave' = requiere permiso.
$router->add('POST', '/api/v1/auth/login', static fn() => (new \App\Controllers\AuthController())->login());
$router->add('POST', '/api/v1/auth/logout', static fn() => (new \App\Controllers\AuthController())->logout(), '');
$router->add('GET', '/api/v1/auth/me', static fn() => (new \App\Controllers\AuthController())->me(), '');
$router->add('GET', '/api/v1/auth/users', static fn() => (new \App\Controllers\UsuarioController())->index(), 'auth.users.manage');
$router->add('POST', '/api/v1/auth/users', static fn() => (new \App\Controllers\UsuarioController())->store(), 'auth.users.manage');
$router->add('PUT', '/api/v1/auth/users/{id}/roles', static fn(array $p) => (new \App\Controllers\UsuarioController())->asignarRoles($p['id'] ?? ''), 'auth.users.manage');
$router->add('GET', '/api/v1/auth/roles', static fn() => (new \App\Controllers\UsuarioController())->roles(), 'auth.users.manage');
$router->add('POST', '/api/v1/auth/roles', static fn() => (new \App\Controllers\UsuarioController())->crearRol(), 'auth.users.manage');

// CP-BACK-03: operaciones (sucursales, cajas, parámetros, turnos).
// '' = requiere token · 'ops.config.manage' = escritura (permiso seedeado).
$router->add('GET', '/api/v1/ops/stores', static fn() => (new \App\Controllers\SucursalController())->index(), '');
$router->add('POST', '/api/v1/ops/stores', static fn() => (new \App\Controllers\SucursalController())->store(), 'ops.config.manage');
$router->add('GET', '/api/v1/ops/stores/{id}', static fn(array $p) => (new \App\Controllers\SucursalController())->show($p['id'] ?? ''), '');
$router->add('PUT', '/api/v1/ops/stores/{id}', static fn(array $p) => (new \App\Controllers\SucursalController())->update($p['id'] ?? ''), 'ops.config.manage');
$router->add('DELETE', '/api/v1/ops/stores/{id}', static fn(array $p) => (new \App\Controllers\SucursalController())->destroy($p['id'] ?? ''), 'ops.config.manage');

$router->add('GET', '/api/v1/ops/registers', static fn() => (new \App\Controllers\CajaController())->index(), '');
$router->add('POST', '/api/v1/ops/registers', static fn() => (new \App\Controllers\CajaController())->store(), 'ops.config.manage');
$router->add('GET', '/api/v1/ops/registers/{id}', static fn(array $p) => (new \App\Controllers\CajaController())->show($p['id'] ?? ''), '');
$router->add('PUT', '/api/v1/ops/registers/{id}', static fn(array $p) => (new \App\Controllers\CajaController())->update($p['id'] ?? ''), 'ops.config.manage');
$router->add('DELETE', '/api/v1/ops/registers/{id}', static fn(array $p) => (new \App\Controllers\CajaController())->destroy($p['id'] ?? ''), 'ops.config.manage');
$router->add('GET', '/api/v1/ops/registers/{id}/shift', static fn(array $p) => (new \App\Controllers\CajaController())->shiftShow($p['id'] ?? ''), '');
$router->add('POST', '/api/v1/ops/registers/{id}/shift/open', static fn(array $p) => (new \App\Controllers\CajaController())->shiftOpen($p['id'] ?? ''), '');
$router->add('POST', '/api/v1/ops/registers/{id}/shift/close', static fn(array $p) => (new \App\Controllers\CajaController())->shiftClose($p['id'] ?? ''), '');

$router->add('GET', '/api/v1/ops/config', static fn() => (new \App\Controllers\ConfigController())->index(), '');
$router->add('GET', '/api/v1/ops/config/{key}', static fn(array $p) => (new \App\Controllers\ConfigController())->show($p['key'] ?? ''), '');
$router->add('PUT', '/api/v1/ops/config/{key}', static fn(array $p) => (new \App\Controllers\ConfigController())->update($p['key'] ?? ''), 'ops.config.manage');

// CP-BACK-04: catálogo (productos, precios, promociones, directorios).
// '' = requiere token · 'catalog.manage' = escritura (permiso seedeado).
$router->add('GET', '/api/v1/catalog/products', static fn() => (new \App\Controllers\ProductoController())->index(), '');
$router->add('POST', '/api/v1/catalog/products', static fn() => (new \App\Controllers\ProductoController())->store(), 'catalog.manage');
$router->add('GET', '/api/v1/catalog/products/{id}', static fn(array $p) => (new \App\Controllers\ProductoController())->show($p['id'] ?? ''), '');
$router->add('PUT', '/api/v1/catalog/products/{id}', static fn(array $p) => (new \App\Controllers\ProductoController())->update($p['id'] ?? ''), 'catalog.manage');
$router->add('DELETE', '/api/v1/catalog/products/{id}', static fn(array $p) => (new \App\Controllers\ProductoController())->destroy($p['id'] ?? ''), 'catalog.manage');
$router->add('GET', '/api/v1/catalog/products/{id}/categories', static fn(array $p) => (new \App\Controllers\ProductoController())->categorias($p['id'] ?? ''), '');
$router->add('PUT', '/api/v1/catalog/products/{id}/categories', static fn(array $p) => (new \App\Controllers\ProductoController())->sincronizarCategorias($p['id'] ?? ''), 'catalog.manage');

$router->add('GET', '/api/v1/catalog/prices', static fn() => (new \App\Controllers\PrecioController())->index(), '');
$router->add('POST', '/api/v1/catalog/prices', static fn() => (new \App\Controllers\PrecioController())->store(), 'catalog.manage');
$router->add('GET', '/api/v1/catalog/prices/{id}', static fn(array $p) => (new \App\Controllers\PrecioController())->show($p['id'] ?? ''), '');
$router->add('PUT', '/api/v1/catalog/prices/{id}', static fn(array $p) => (new \App\Controllers\PrecioController())->update($p['id'] ?? ''), 'catalog.manage');

$router->add('GET', '/api/v1/catalog/promotions', static fn() => (new \App\Controllers\PromocionController())->index(), '');
$router->add('POST', '/api/v1/catalog/promotions', static fn() => (new \App\Controllers\PromocionController())->store(), 'catalog.manage');
$router->add('GET', '/api/v1/catalog/promotions/{id}', static fn(array $p) => (new \App\Controllers\PromocionController())->show($p['id'] ?? ''), '');
$router->add('PUT', '/api/v1/catalog/promotions/{id}', static fn(array $p) => (new \App\Controllers\PromocionController())->update($p['id'] ?? ''), 'catalog.manage');

$router->add('GET', '/api/v1/catalog/suppliers', static fn() => (new \App\Controllers\ProveedorController())->index(), '');
$router->add('POST', '/api/v1/catalog/suppliers', static fn() => (new \App\Controllers\ProveedorController())->store(), 'catalog.manage');
$router->add('GET', '/api/v1/catalog/suppliers/{id}', static fn(array $p) => (new \App\Controllers\ProveedorController())->show($p['id'] ?? ''), '');
$router->add('PUT', '/api/v1/catalog/suppliers/{id}', static fn(array $p) => (new \App\Controllers\ProveedorController())->update($p['id'] ?? ''), 'catalog.manage');
$router->add('DELETE', '/api/v1/catalog/suppliers/{id}', static fn(array $p) => (new \App\Controllers\ProveedorController())->destroy($p['id'] ?? ''), 'catalog.manage');

$router->add('GET', '/api/v1/catalog/patients', static fn() => (new \App\Controllers\PacienteController())->index(), '');
$router->add('POST', '/api/v1/catalog/patients', static fn() => (new \App\Controllers\PacienteController())->store(), 'catalog.manage');
$router->add('GET', '/api/v1/catalog/patients/{id}', static fn(array $p) => (new \App\Controllers\PacienteController())->show($p['id'] ?? ''), '');
$router->add('PUT', '/api/v1/catalog/patients/{id}', static fn(array $p) => (new \App\Controllers\PacienteController())->update($p['id'] ?? ''), 'catalog.manage');
$router->add('DELETE', '/api/v1/catalog/patients/{id}', static fn(array $p) => (new \App\Controllers\PacienteController())->destroy($p['id'] ?? ''), 'catalog.manage');

$router->add('GET', '/api/v1/catalog/prescribers', static fn() => (new \App\Controllers\PrescriptorController())->index(), '');
$router->add('POST', '/api/v1/catalog/prescribers', static fn() => (new \App\Controllers\PrescriptorController())->store(), 'catalog.manage');
$router->add('GET', '/api/v1/catalog/prescribers/{id}', static fn(array $p) => (new \App\Controllers\PrescriptorController())->show($p['id'] ?? ''), '');
$router->add('PUT', '/api/v1/catalog/prescribers/{id}', static fn(array $p) => (new \App\Controllers\PrescriptorController())->update($p['id'] ?? ''), 'catalog.manage');
$router->add('DELETE', '/api/v1/catalog/prescribers/{id}', static fn(array $p) => (new \App\Controllers\PrescriptorController())->destroy($p['id'] ?? ''), 'catalog.manage');

// CP-BACK-05: compras (órdenes y recepciones).
// '' = requiere token · 'purchases.manage' = escritura (permiso seedeado).
$router->add('GET', '/api/v1/purchases/orders', static fn() => (new \App\Controllers\OrdenCompraController())->index(), '');
$router->add('POST', '/api/v1/purchases/orders', static fn() => (new \App\Controllers\OrdenCompraController())->store(), 'purchases.manage');
$router->add('GET', '/api/v1/purchases/orders/{id}', static fn(array $p) => (new \App\Controllers\OrdenCompraController())->show($p['id'] ?? ''), '');
$router->add('PUT', '/api/v1/purchases/orders/{id}', static fn(array $p) => (new \App\Controllers\OrdenCompraController())->update($p['id'] ?? ''), 'purchases.manage');
$router->add('POST', '/api/v1/purchases/orders/{id}/emitir', static fn(array $p) => (new \App\Controllers\OrdenCompraController())->emitir($p['id'] ?? ''), 'purchases.manage');
$router->add('DELETE', '/api/v1/purchases/orders/{id}', static fn(array $p) => (new \App\Controllers\OrdenCompraController())->destroy($p['id'] ?? ''), 'purchases.manage');

$router->add('GET', '/api/v1/purchases/receptions', static fn() => (new \App\Controllers\RecepcionController())->index(), '');
$router->add('POST', '/api/v1/purchases/orders/{id}/receptions', static fn(array $p) => (new \App\Controllers\RecepcionController())->store($p['id'] ?? ''), 'purchases.manage');
$router->add('GET', '/api/v1/purchases/receptions/{id}', static fn(array $p) => (new \App\Controllers\RecepcionController())->show($p['id'] ?? ''), '');
$router->add('POST', '/api/v1/purchases/receptions/{id}/confirmar', static fn(array $p) => (new \App\Controllers\RecepcionController())->confirmar($p['id'] ?? ''), 'purchases.manage');
$router->add('POST', '/api/v1/purchases/receptions/{id}/rechazar', static fn(array $p) => (new \App\Controllers\RecepcionController())->rechazar($p['id'] ?? ''), 'purchases.manage');

// CP-BACK-06: inventario y trazabilidad FEFO (RF-044/046/047, RN-02/03/08).
// '' = requiere token · 'inventory.manage' = escritura (permiso seedeado).
// 'inventory.adjust' = ajuste de incidente con doble autorización (RF-046).
$router->add('GET', '/api/v1/inventory/stocks', static fn() => (new \App\Controllers\InventarioController())->stocks(), '');
$router->add('GET', '/api/v1/inventory/movements', static fn() => (new \App\Controllers\InventarioController())->movimientos(), '');
$router->add('GET', '/api/v1/inventory/batches', static fn() => (new \App\Controllers\InventarioController())->lotes(), '');
$router->add('GET', '/api/v1/inventory/alerts', static fn() => (new \App\Controllers\InventarioController())->alertas(), '');
$router->add('GET', '/api/v1/inventory/incidents', static fn() => (new \App\Controllers\InventarioController())->incidentes(), '');
$router->add('POST', '/api/v1/inventory/batches/{id}/liberar', static fn(array $p) => (new \App\Controllers\InventarioController())->liberar($p['id'] ?? ''), 'inventory.manage');
$router->add('POST', '/api/v1/inventory/alerts/evaluar', static fn() => (new \App\Controllers\InventarioController())->evaluarAlertas(), 'inventory.manage');
$router->add('POST', '/api/v1/inventory/alerts/{id}/resolver', static fn(array $p) => (new \App\Controllers\InventarioController())->resolverAlerta($p['id'] ?? ''), 'inventory.manage');
$router->add('POST', '/api/v1/inventory/incidents', static fn() => (new \App\Controllers\InventarioController())->crearIncidente(), 'inventory.manage');
$router->add('POST', '/api/v1/inventory/incidents/{id}/ajustar', static fn(array $p) => (new \App\Controllers\InventarioController())->ajustarIncidente($p['id'] ?? ''), 'inventory.adjust');
$router->add('POST', '/api/v1/inventory/incidents/{id}/descartar', static fn(array $p) => (new \App\Controllers\InventarioController())->descartarIncidente($p['id'] ?? ''), 'inventory.manage');

$router->add('GET', '/api/v1/inventory/transfers', static fn() => (new \App\Controllers\TransferenciaController())->index(), '');
$router->add('POST', '/api/v1/inventory/transfers', static fn() => (new \App\Controllers\TransferenciaController())->store(), 'inventory.manage');
$router->add('GET', '/api/v1/inventory/transfers/{id}', static fn(array $p) => (new \App\Controllers\TransferenciaController())->show($p['id'] ?? ''), '');
$router->add('POST', '/api/v1/inventory/transfers/{id}/despachar', static fn(array $p) => (new \App\Controllers\TransferenciaController())->despachar($p['id'] ?? ''), 'inventory.manage');
$router->add('POST', '/api/v1/inventory/transfers/{id}/recibir', static fn(array $p) => (new \App\Controllers\TransferenciaController())->recibir($p['id'] ?? ''), 'inventory.manage');
$router->add('POST', '/api/v1/inventory/transfers/{id}/cerrar', static fn(array $p) => (new \App\Controllers\TransferenciaController())->cerrar($p['id'] ?? ''), 'inventory.manage');
$router->add('POST', '/api/v1/inventory/transfers/{id}/rechazar', static fn(array $p) => (new \App\Controllers\TransferenciaController())->rechazar($p['id'] ?? ''), 'inventory.manage');

$router->add('GET', '/api/v1/inventory/reservations', static fn() => (new \App\Controllers\ReservaController())->index(), '');
$router->add('POST', '/api/v1/inventory/reservations', static fn() => (new \App\Controllers\ReservaController())->store(), 'inventory.manage');
$router->add('POST', '/api/v1/inventory/reservations/{id}/confirmar', static fn(array $p) => (new \App\Controllers\ReservaController())->confirmar($p['id'] ?? ''), 'inventory.manage');
$router->add('POST', '/api/v1/inventory/reservations/{id}/cancelar', static fn(array $p) => (new \App\Controllers\ReservaController())->cancelar($p['id'] ?? ''), 'inventory.manage');

// CP-BACK-07 · Recetas médicas (RF-052 registro, RF-053 saldo).
// '' requiere token sin permiso específico (lectura de recetas para el
// mostrador); 'rx.manage' exige el permiso de dispensación.
$router->add('GET', '/api/v1/rx/prescriptions', static fn() => (new \App\Controllers\RecetaController())->index(), '');
$router->add('POST', '/api/v1/rx/prescriptions', static fn() => (new \App\Controllers\RecetaController())->store(), 'rx.manage');
$router->add('GET', '/api/v1/rx/prescriptions/{id}', static fn(array $p) => (new \App\Controllers\RecetaController())->show($p['id'] ?? ''), '');
$router->add('POST', '/api/v1/rx/prescriptions/{id}/dispensar', static fn(array $p) => (new \App\Controllers\RecetaController())->dispensar($p['id'] ?? ''), 'rx.manage');

// CP-BACK-08: ventas POS, pagos y devoluciones (RF-050/RF-060, RN-01/09/10).
// '' = requiere token; 'sales.manage' = escritura (permiso seedeado).
$router->add('GET', '/api/v1/sales/orders', static fn() => (new \App\Controllers\VentaController())->index(), '');
$router->add('POST', '/api/v1/sales/orders', static fn() => (new \App\Controllers\VentaController())->store(), 'sales.manage');
$router->add('GET', '/api/v1/sales/orders/{id}', static fn(array $p) => (new \App\Controllers\VentaController())->show($p['id'] ?? ''), '');
$router->add('POST', '/api/v1/sales/orders/{id}/payments', static fn(array $p) => (new \App\Controllers\VentaController())->pagar($p['id'] ?? ''), 'sales.manage');
$router->add('POST', '/api/v1/sales/orders/{id}/anular', static fn(array $p) => (new \App\Controllers\VentaController())->anular($p['id'] ?? ''), 'sales.manage');
$router->add('GET', '/api/v1/sales/returns', static fn() => (new \App\Controllers\VentaController())->indexDevoluciones(), '');
$router->add('POST', '/api/v1/sales/returns', static fn() => (new \App\Controllers\VentaController())->storeDevolucion(), 'sales.manage');
$router->add('GET', '/api/v1/sales/returns/{id}', static fn(array $p) => (new \App\Controllers\VentaController())->showDevolucion($p['id'] ?? ''), '');

// CP-BACK-09: libro oficial de controlados (RF-045/046/055/071, RN-05/06).
// '' = requiere token; 'control.manage' = ajustes con doble autorizacion.
$router->add('GET', '/api/v1/control/ledger', static fn() => (new \App\Controllers\CtrlController())->indexAsientos(), '');
$router->add('GET', '/api/v1/control/ledger/{id}', static fn(array $p) => (new \App\Controllers\CtrlController())->showAsiento($p['id'] ?? ''), '');
$router->add('GET', '/api/v1/control/balances', static fn() => (new \App\Controllers\CtrlController())->saldos(), '');
$router->add('GET', '/api/v1/control/reconciliation', static fn() => (new \App\Controllers\CtrlController())->conciliacion(), '');
$router->add('POST', '/api/v1/control/adjustments', static fn() => (new \App\Controllers\CtrlController())->ajustar(), 'control.manage');

// CP-BACK-10: auditoria de operaciones, accesos PII y eventos salientes
// (RF-090/091, RN-13, RNF-046, decision 16 outbox).
// 'audit.read' = consulta de registros; 'audit.manage' = ciclo del outbox.
$router->add('GET', '/api/v1/audit/operations', static fn() => (new \App\Controllers\AuditController())->indexOperaciones(), 'audit.read');
$router->add('GET', '/api/v1/audit/operations/{id}', static fn(array $p) => (new \App\Controllers\AuditController())->showOperacion($p['id'] ?? ''), 'audit.read');
$router->add('GET', '/api/v1/audit/pii', static fn() => (new \App\Controllers\AuditController())->indexPii(), 'audit.read');
$router->add('GET', '/api/v1/audit/pii/{id}', static fn(array $p) => (new \App\Controllers\AuditController())->showPii($p['id'] ?? ''), 'audit.read');
$router->add('GET', '/api/v1/audit/events', static fn() => (new \App\Controllers\AuditController())->indexEventos(), 'audit.read');
$router->add('GET', '/api/v1/audit/events/{id}', static fn(array $p) => (new \App\Controllers\AuditController())->showEvento($p['id'] ?? ''), 'audit.read');
$router->add('POST', '/api/v1/audit/events/{id}/procesar', static fn(array $p) => (new \App\Controllers\AuditController())->procesarEvento($p['id'] ?? ''), 'audit.manage');
$router->add('POST', '/api/v1/audit/events/{id}/fallar', static fn(array $p) => (new \App\Controllers\AuditController())->fallarEvento($p['id'] ?? ''), 'audit.manage');
$router->add('POST', '/api/v1/audit/events/{id}/reintentar', static fn(array $p) => (new \App\Controllers\AuditController())->reintentarEvento($p['id'] ?? ''), 'audit.manage');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $router->dispatch($method, $path);
} catch (\App\Http\AppException $e) {
    \App\Http\Response::error($e->errorCode, $e->getMessage(), $e->status);
} catch (\Throwable $e) {
    // Sin stack traces ni datos sensibles en la respuesta.
    \App\Http\Response::error('INTERNAL_ERROR', 'Error interno.', 500);
}
