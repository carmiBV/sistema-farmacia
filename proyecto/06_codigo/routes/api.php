<?php
declare(strict_types=1);

// Registro de endpoints de la API (/api/v1/*) — capa de rutas MVC (CORREGIR_ARQUITECTURA_API.md).
// Permisos (4to parametro de add): null = publica, '' = requiere token, 'clave' = requiere permiso.

/** @var \App\Core\Router $router */

$router->add('GET', '/api/v1/health', static fn() => (new \App\Controllers\Api\HealthController())->check());
$router->add('GET', '/api/v1/catalog/categories', static fn() => (new \App\Controllers\Api\CategoriaController())->index());
$router->add('GET', '/api/v1/catalog/categories/{id}', static fn(array $p) => (new \App\Controllers\Api\CategoriaController())->show($p['id'] ?? ''));
$router->add('POST', '/api/v1/catalog/categories', static fn() => (new \App\Controllers\Api\CategoriaController())->store());
$router->add('PUT', '/api/v1/catalog/categories/{id}', static fn(array $p) => (new \App\Controllers\Api\CategoriaController())->update($p['id'] ?? ''));
$router->add('DELETE', '/api/v1/catalog/categories/{id}', static fn(array $p) => (new \App\Controllers\Api\CategoriaController())->destroy($p['id'] ?? ''));

// CP-BACK-02: autenticación y RBAC. 4º parámetro: null = pública,
// '' = requiere token -> 'clave' = requiere permiso.
$router->add('POST', '/api/v1/auth/login', static fn() => (new \App\Controllers\Api\AuthController())->login());
$router->add('POST', '/api/v1/auth/logout', static fn() => (new \App\Controllers\Api\AuthController())->logout(), '');
$router->add('GET', '/api/v1/auth/me', static fn() => (new \App\Controllers\Api\AuthController())->me(), '');
$router->add('GET', '/api/v1/auth/users', static fn() => (new \App\Controllers\Api\UsuarioController())->index(), 'auth.users.manage');
$router->add('POST', '/api/v1/auth/users', static fn() => (new \App\Controllers\Api\UsuarioController())->store(), 'auth.users.manage');
$router->add('PUT', '/api/v1/auth/users/{id}/roles', static fn(array $p) => (new \App\Controllers\Api\UsuarioController())->asignarRoles($p['id'] ?? ''), 'auth.users.manage');
$router->add('GET', '/api/v1/auth/roles', static fn() => (new \App\Controllers\Api\UsuarioController())->roles(), 'auth.users.manage');
$router->add('POST', '/api/v1/auth/roles', static fn() => (new \App\Controllers\Api\UsuarioController())->crearRol(), 'auth.users.manage');

// CP-BACK-03: operaciones (sucursales, cajas, parámetros, turnos).
// '' = requiere token · 'ops.config.manage' = escritura (permiso seedeado).
$router->add('GET', '/api/v1/ops/stores', static fn() => (new \App\Controllers\Api\SucursalController())->index(), '');
$router->add('POST', '/api/v1/ops/stores', static fn() => (new \App\Controllers\Api\SucursalController())->store(), 'ops.config.manage');
$router->add('GET', '/api/v1/ops/stores/{id}', static fn(array $p) => (new \App\Controllers\Api\SucursalController())->show($p['id'] ?? ''), '');
$router->add('PUT', '/api/v1/ops/stores/{id}', static fn(array $p) => (new \App\Controllers\Api\SucursalController())->update($p['id'] ?? ''), 'ops.config.manage');
$router->add('DELETE', '/api/v1/ops/stores/{id}', static fn(array $p) => (new \App\Controllers\Api\SucursalController())->destroy($p['id'] ?? ''), 'ops.config.manage');

$router->add('GET', '/api/v1/ops/registers', static fn() => (new \App\Controllers\Api\CajaController())->index(), '');
$router->add('POST', '/api/v1/ops/registers', static fn() => (new \App\Controllers\Api\CajaController())->store(), 'ops.config.manage');
$router->add('GET', '/api/v1/ops/registers/{id}', static fn(array $p) => (new \App\Controllers\Api\CajaController())->show($p['id'] ?? ''), '');
$router->add('PUT', '/api/v1/ops/registers/{id}', static fn(array $p) => (new \App\Controllers\Api\CajaController())->update($p['id'] ?? ''), 'ops.config.manage');
$router->add('DELETE', '/api/v1/ops/registers/{id}', static fn(array $p) => (new \App\Controllers\Api\CajaController())->destroy($p['id'] ?? ''), 'ops.config.manage');
$router->add('GET', '/api/v1/ops/registers/{id}/shift', static fn(array $p) => (new \App\Controllers\Api\CajaController())->shiftShow($p['id'] ?? ''), '');
$router->add('POST', '/api/v1/ops/registers/{id}/shift/open', static fn(array $p) => (new \App\Controllers\Api\CajaController())->shiftOpen($p['id'] ?? ''), '');
$router->add('POST', '/api/v1/ops/registers/{id}/shift/close', static fn(array $p) => (new \App\Controllers\Api\CajaController())->shiftClose($p['id'] ?? ''), '');

$router->add('GET', '/api/v1/ops/config', static fn() => (new \App\Controllers\Api\ConfigController())->index(), '');
$router->add('GET', '/api/v1/ops/config/{key}', static fn(array $p) => (new \App\Controllers\Api\ConfigController())->show($p['key'] ?? ''), '');
$router->add('PUT', '/api/v1/ops/config/{key}', static fn(array $p) => (new \App\Controllers\Api\ConfigController())->update($p['key'] ?? ''), 'ops.config.manage');

// CP-BACK-04: catálogo (productos, precios, promociones, directorios).
// '' = requiere token · 'catalog.manage' = escritura (permiso seedeado).
$router->add('GET', '/api/v1/catalog/products', static fn() => (new \App\Controllers\Api\ProductoController())->index(), '');
$router->add('POST', '/api/v1/catalog/products', static fn() => (new \App\Controllers\Api\ProductoController())->store(), 'catalog.manage');
$router->add('GET', '/api/v1/catalog/products/{id}', static fn(array $p) => (new \App\Controllers\Api\ProductoController())->show($p['id'] ?? ''), '');
$router->add('PUT', '/api/v1/catalog/products/{id}', static fn(array $p) => (new \App\Controllers\Api\ProductoController())->update($p['id'] ?? ''), 'catalog.manage');
$router->add('DELETE', '/api/v1/catalog/products/{id}', static fn(array $p) => (new \App\Controllers\Api\ProductoController())->destroy($p['id'] ?? ''), 'catalog.manage');
$router->add('GET', '/api/v1/catalog/products/{id}/categories', static fn(array $p) => (new \App\Controllers\Api\ProductoController())->categorias($p['id'] ?? ''), '');
$router->add('PUT', '/api/v1/catalog/products/{id}/categories', static fn(array $p) => (new \App\Controllers\Api\ProductoController())->sincronizarCategorias($p['id'] ?? ''), 'catalog.manage');

$router->add('GET', '/api/v1/catalog/prices', static fn() => (new \App\Controllers\Api\PrecioController())->index(), '');
$router->add('POST', '/api/v1/catalog/prices', static fn() => (new \App\Controllers\Api\PrecioController())->store(), 'catalog.manage');
$router->add('GET', '/api/v1/catalog/prices/{id}', static fn(array $p) => (new \App\Controllers\Api\PrecioController())->show($p['id'] ?? ''), '');
$router->add('PUT', '/api/v1/catalog/prices/{id}', static fn(array $p) => (new \App\Controllers\Api\PrecioController())->update($p['id'] ?? ''), 'catalog.manage');

$router->add('GET', '/api/v1/catalog/promotions', static fn() => (new \App\Controllers\Api\PromocionController())->index(), '');
$router->add('POST', '/api/v1/catalog/promotions', static fn() => (new \App\Controllers\Api\PromocionController())->store(), 'catalog.manage');
$router->add('GET', '/api/v1/catalog/promotions/{id}', static fn(array $p) => (new \App\Controllers\Api\PromocionController())->show($p['id'] ?? ''), '');
$router->add('PUT', '/api/v1/catalog/promotions/{id}', static fn(array $p) => (new \App\Controllers\Api\PromocionController())->update($p['id'] ?? ''), 'catalog.manage');

$router->add('GET', '/api/v1/catalog/suppliers', static fn() => (new \App\Controllers\Api\ProveedorController())->index(), '');
$router->add('POST', '/api/v1/catalog/suppliers', static fn() => (new \App\Controllers\Api\ProveedorController())->store(), 'catalog.manage');
$router->add('GET', '/api/v1/catalog/suppliers/{id}', static fn(array $p) => (new \App\Controllers\Api\ProveedorController())->show($p['id'] ?? ''), '');
$router->add('PUT', '/api/v1/catalog/suppliers/{id}', static fn(array $p) => (new \App\Controllers\Api\ProveedorController())->update($p['id'] ?? ''), 'catalog.manage');
$router->add('DELETE', '/api/v1/catalog/suppliers/{id}', static fn(array $p) => (new \App\Controllers\Api\ProveedorController())->destroy($p['id'] ?? ''), 'catalog.manage');

$router->add('GET', '/api/v1/catalog/patients', static fn() => (new \App\Controllers\Api\PacienteController())->index(), '');
$router->add('POST', '/api/v1/catalog/patients', static fn() => (new \App\Controllers\Api\PacienteController())->store(), 'catalog.manage');
$router->add('GET', '/api/v1/catalog/patients/{id}', static fn(array $p) => (new \App\Controllers\Api\PacienteController())->show($p['id'] ?? ''), '');
$router->add('PUT', '/api/v1/catalog/patients/{id}', static fn(array $p) => (new \App\Controllers\Api\PacienteController())->update($p['id'] ?? ''), 'catalog.manage');
$router->add('DELETE', '/api/v1/catalog/patients/{id}', static fn(array $p) => (new \App\Controllers\Api\PacienteController())->destroy($p['id'] ?? ''), 'catalog.manage');

$router->add('GET', '/api/v1/catalog/prescribers', static fn() => (new \App\Controllers\Api\PrescriptorController())->index(), '');
$router->add('POST', '/api/v1/catalog/prescribers', static fn() => (new \App\Controllers\Api\PrescriptorController())->store(), 'catalog.manage');
$router->add('GET', '/api/v1/catalog/prescribers/{id}', static fn(array $p) => (new \App\Controllers\Api\PrescriptorController())->show($p['id'] ?? ''), '');
$router->add('PUT', '/api/v1/catalog/prescribers/{id}', static fn(array $p) => (new \App\Controllers\Api\PrescriptorController())->update($p['id'] ?? ''), 'catalog.manage');
$router->add('DELETE', '/api/v1/catalog/prescribers/{id}', static fn(array $p) => (new \App\Controllers\Api\PrescriptorController())->destroy($p['id'] ?? ''), 'catalog.manage');

// CP-BACK-05: compras (órdenes y recepciones).
// '' = requiere token · 'purchases.manage' = escritura (permiso seedeado).
$router->add('GET', '/api/v1/purchases/orders', static fn() => (new \App\Controllers\Api\OrdenCompraController())->index(), '');
$router->add('POST', '/api/v1/purchases/orders', static fn() => (new \App\Controllers\Api\OrdenCompraController())->store(), 'purchases.manage');
$router->add('GET', '/api/v1/purchases/orders/{id}', static fn(array $p) => (new \App\Controllers\Api\OrdenCompraController())->show($p['id'] ?? ''), '');
$router->add('PUT', '/api/v1/purchases/orders/{id}', static fn(array $p) => (new \App\Controllers\Api\OrdenCompraController())->update($p['id'] ?? ''), 'purchases.manage');
$router->add('POST', '/api/v1/purchases/orders/{id}/emitir', static fn(array $p) => (new \App\Controllers\Api\OrdenCompraController())->emitir($p['id'] ?? ''), 'purchases.manage');
$router->add('DELETE', '/api/v1/purchases/orders/{id}', static fn(array $p) => (new \App\Controllers\Api\OrdenCompraController())->destroy($p['id'] ?? ''), 'purchases.manage');

$router->add('GET', '/api/v1/purchases/receptions', static fn() => (new \App\Controllers\Api\RecepcionController())->index(), '');
$router->add('POST', '/api/v1/purchases/orders/{id}/receptions', static fn(array $p) => (new \App\Controllers\Api\RecepcionController())->store($p['id'] ?? ''), 'purchases.manage');
$router->add('GET', '/api/v1/purchases/receptions/{id}', static fn(array $p) => (new \App\Controllers\Api\RecepcionController())->show($p['id'] ?? ''), '');
$router->add('POST', '/api/v1/purchases/receptions/{id}/confirmar', static fn(array $p) => (new \App\Controllers\Api\RecepcionController())->confirmar($p['id'] ?? ''), 'purchases.manage');
$router->add('POST', '/api/v1/purchases/receptions/{id}/rechazar', static fn(array $p) => (new \App\Controllers\Api\RecepcionController())->rechazar($p['id'] ?? ''), 'purchases.manage');

// CP-BACK-06: inventario y trazabilidad FEFO (RF-044/046/047, RN-02/03/08).
// '' = requiere token · 'inventory.manage' = escritura (permiso seedeado).
// 'inventory.adjust' = ajuste de incidente con doble autorización (RF-046).
$router->add('GET', '/api/v1/inventory/stocks', static fn() => (new \App\Controllers\Api\InventarioController())->stocks(), '');
$router->add('GET', '/api/v1/inventory/movements', static fn() => (new \App\Controllers\Api\InventarioController())->movimientos(), '');
$router->add('GET', '/api/v1/inventory/batches', static fn() => (new \App\Controllers\Api\InventarioController())->lotes(), '');
$router->add('GET', '/api/v1/inventory/alerts', static fn() => (new \App\Controllers\Api\InventarioController())->alertas(), '');
$router->add('GET', '/api/v1/inventory/incidents', static fn() => (new \App\Controllers\Api\InventarioController())->incidentes(), '');
$router->add('POST', '/api/v1/inventory/batches/{id}/liberar', static fn(array $p) => (new \App\Controllers\Api\InventarioController())->liberar($p['id'] ?? ''), 'inventory.manage');
$router->add('POST', '/api/v1/inventory/alerts/evaluar', static fn() => (new \App\Controllers\Api\InventarioController())->evaluarAlertas(), 'inventory.manage');
$router->add('POST', '/api/v1/inventory/alerts/{id}/resolver', static fn(array $p) => (new \App\Controllers\Api\InventarioController())->resolverAlerta($p['id'] ?? ''), 'inventory.manage');
$router->add('POST', '/api/v1/inventory/incidents', static fn() => (new \App\Controllers\Api\InventarioController())->crearIncidente(), 'inventory.manage');
$router->add('POST', '/api/v1/inventory/incidents/{id}/ajustar', static fn(array $p) => (new \App\Controllers\Api\InventarioController())->ajustarIncidente($p['id'] ?? ''), 'inventory.adjust');
$router->add('POST', '/api/v1/inventory/incidents/{id}/descartar', static fn(array $p) => (new \App\Controllers\Api\InventarioController())->descartarIncidente($p['id'] ?? ''), 'inventory.manage');

$router->add('GET', '/api/v1/inventory/transfers', static fn() => (new \App\Controllers\Api\TransferenciaController())->index(), '');
$router->add('POST', '/api/v1/inventory/transfers', static fn() => (new \App\Controllers\Api\TransferenciaController())->store(), 'inventory.manage');
$router->add('GET', '/api/v1/inventory/transfers/{id}', static fn(array $p) => (new \App\Controllers\Api\TransferenciaController())->show($p['id'] ?? ''), '');
$router->add('POST', '/api/v1/inventory/transfers/{id}/despachar', static fn(array $p) => (new \App\Controllers\Api\TransferenciaController())->despachar($p['id'] ?? ''), 'inventory.manage');
$router->add('POST', '/api/v1/inventory/transfers/{id}/recibir', static fn(array $p) => (new \App\Controllers\Api\TransferenciaController())->recibir($p['id'] ?? ''), 'inventory.manage');
$router->add('POST', '/api/v1/inventory/transfers/{id}/cerrar', static fn(array $p) => (new \App\Controllers\Api\TransferenciaController())->cerrar($p['id'] ?? ''), 'inventory.manage');
$router->add('POST', '/api/v1/inventory/transfers/{id}/rechazar', static fn(array $p) => (new \App\Controllers\Api\TransferenciaController())->rechazar($p['id'] ?? ''), 'inventory.manage');

$router->add('GET', '/api/v1/inventory/reservations', static fn() => (new \App\Controllers\Api\ReservaController())->index(), '');
$router->add('POST', '/api/v1/inventory/reservations', static fn() => (new \App\Controllers\Api\ReservaController())->store(), 'inventory.manage');
$router->add('POST', '/api/v1/inventory/reservations/{id}/confirmar', static fn(array $p) => (new \App\Controllers\Api\ReservaController())->confirmar($p['id'] ?? ''), 'inventory.manage');
$router->add('POST', '/api/v1/inventory/reservations/{id}/cancelar', static fn(array $p) => (new \App\Controllers\Api\ReservaController())->cancelar($p['id'] ?? ''), 'inventory.manage');

// CP-BACK-07 · Recetas médicas (RF-052 registro, RF-053 saldo).
// '' requiere token sin permiso específico (lectura de recetas para el
// mostrador); 'rx.manage' exige el permiso de dispensación.
$router->add('GET', '/api/v1/rx/prescriptions', static fn() => (new \App\Controllers\Api\RecetaController())->index(), '');
$router->add('POST', '/api/v1/rx/prescriptions', static fn() => (new \App\Controllers\Api\RecetaController())->store(), 'rx.manage');
$router->add('GET', '/api/v1/rx/prescriptions/{id}', static fn(array $p) => (new \App\Controllers\Api\RecetaController())->show($p['id'] ?? ''), '');
$router->add('POST', '/api/v1/rx/prescriptions/{id}/dispensar', static fn(array $p) => (new \App\Controllers\Api\RecetaController())->dispensar($p['id'] ?? ''), 'rx.manage');

// CP-BACK-08: ventas POS, pagos y devoluciones (RF-050/RF-060, RN-01/09/10).
// '' = requiere token; 'sales.manage' = escritura (permiso seedeado).
$router->add('GET', '/api/v1/sales/orders', static fn() => (new \App\Controllers\Api\VentaController())->index(), '');
$router->add('POST', '/api/v1/sales/orders', static fn() => (new \App\Controllers\Api\VentaController())->store(), 'sales.manage');
$router->add('GET', '/api/v1/sales/orders/{id}', static fn(array $p) => (new \App\Controllers\Api\VentaController())->show($p['id'] ?? ''), '');
$router->add('POST', '/api/v1/sales/orders/{id}/payments', static fn(array $p) => (new \App\Controllers\Api\VentaController())->pagar($p['id'] ?? ''), 'sales.manage');
$router->add('POST', '/api/v1/sales/orders/{id}/anular', static fn(array $p) => (new \App\Controllers\Api\VentaController())->anular($p['id'] ?? ''), 'sales.manage');
$router->add('GET', '/api/v1/sales/returns', static fn() => (new \App\Controllers\Api\VentaController())->indexDevoluciones(), '');
$router->add('POST', '/api/v1/sales/returns', static fn() => (new \App\Controllers\Api\VentaController())->storeDevolucion(), 'sales.manage');
$router->add('GET', '/api/v1/sales/returns/{id}', static fn(array $p) => (new \App\Controllers\Api\VentaController())->showDevolucion($p['id'] ?? ''), '');

// CP-BACK-09: libro oficial de controlados (RF-045/046/055/071, RN-05/06).
// '' = requiere token; 'control.manage' = ajustes con doble autorizacion.
$router->add('GET', '/api/v1/control/ledger', static fn() => (new \App\Controllers\Api\CtrlController())->indexAsientos(), '');
$router->add('GET', '/api/v1/control/ledger/{id}', static fn(array $p) => (new \App\Controllers\Api\CtrlController())->showAsiento($p['id'] ?? ''), '');
$router->add('GET', '/api/v1/control/balances', static fn() => (new \App\Controllers\Api\CtrlController())->saldos(), '');
$router->add('GET', '/api/v1/control/reconciliation', static fn() => (new \App\Controllers\Api\CtrlController())->conciliacion(), '');
$router->add('POST', '/api/v1/control/adjustments', static fn() => (new \App\Controllers\Api\CtrlController())->ajustar(), 'control.manage');

// CP-BACK-10: auditoria de operaciones, accesos PII y eventos salientes
// (RF-090/091, RN-13, RNF-046, decision 16 outbox).
// 'audit.read' = consulta de registros; 'audit.manage' = ciclo del outbox.
$router->add('GET', '/api/v1/audit/operations', static fn() => (new \App\Controllers\Api\AuditController())->indexOperaciones(), 'audit.read');
$router->add('GET', '/api/v1/audit/operations/{id}', static fn(array $p) => (new \App\Controllers\Api\AuditController())->showOperacion($p['id'] ?? ''), 'audit.read');
$router->add('GET', '/api/v1/audit/pii', static fn() => (new \App\Controllers\Api\AuditController())->indexPii(), 'audit.read');
$router->add('GET', '/api/v1/audit/pii/{id}', static fn(array $p) => (new \App\Controllers\Api\AuditController())->showPii($p['id'] ?? ''), 'audit.read');
$router->add('GET', '/api/v1/audit/events', static fn() => (new \App\Controllers\Api\AuditController())->indexEventos(), 'audit.read');
$router->add('GET', '/api/v1/audit/events/{id}', static fn(array $p) => (new \App\Controllers\Api\AuditController())->showEvento($p['id'] ?? ''), 'audit.read');
$router->add('POST', '/api/v1/audit/events/{id}/procesar', static fn(array $p) => (new \App\Controllers\Api\AuditController())->procesarEvento($p['id'] ?? ''), 'audit.manage');
$router->add('POST', '/api/v1/audit/events/{id}/fallar', static fn(array $p) => (new \App\Controllers\Api\AuditController())->fallarEvento($p['id'] ?? ''), 'audit.manage');
$router->add('POST', '/api/v1/audit/events/{id}/reintentar', static fn(array $p) => (new \App\Controllers\Api\AuditController())->reintentarEvento($p['id'] ?? ''), 'audit.manage');
