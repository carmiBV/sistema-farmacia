# Workflow 04 — Backend: Arquitectura Modular y Lógica de Negocio

Precondición: API de categorías (`03_api_pilot_workflow`) PASSED + APPROVED.

Alcance: Cobertura total de los módulos backend del sistema (42 tablas):
- Autenticación, Usuarios y Permisos RBAC (`auth_*`, `token_blacklist`)
- Operaciones y Sucursales (`ops_stores`, `ops_registers`, `system_config`)
- Catálogo y Proveedores (`catalog_*`)
- Compras y Recepciones (`purchase_*`)
- Control de Inventario, Lotes FEFO y Transferencias (`inventory_*`)
- Recetas Médicas (`rx_prescriptions`, `rx_prescription_items`)
- Ventas POS, Pagos y Devoluciones (`sales_*`, `payments_transactions`)
- Libro de Controlados y Balances (`ctrl_*`)
- Auditoría PII, Claves de Idempotencia y Outbox (`audit_*`, `idempotency_keys`, `outbox_events`)

Skills: `php-development`, `error-handling-patterns`, `auth-implementation-patterns`.

## CP-BACK-01
Consolidar el patrón de arquitectura multicapa (Controllers, Services, Repositories, Models, Validators) probado en el piloto de categorías y extenderlo a toda la aplicación. STOP.

## CP-BACK-02
Implementar el módulo de Autenticación y Seguridad: endpoints `/api/v1/auth/login`, `/api/v1/auth/logout`, `/api/v1/auth/me` con verificación de contraseñas seguras, invalidez en `token_blacklist`, gestión de roles/permisos (`auth_users`, `auth_user_roles`, `auth_permissions`) y middleware RBAC. Crear script CLI de inicialización de administrador. STOP.

## CP-BACK-03
Implementar los servicios de Configuración Operativa y Sucursales (`ops_stores`, `ops_registers`, `system_config`): gestión de cajas, apertura/cierre de turnos y parámetros globales del sistema. STOP.

## CP-BACK-04
Implementar el módulo completo de Catálogo: productos (`catalog_products`), relaciones multi-categoría (`catalog_product_categories`), esquemas de precios (`catalog_prices`), promociones (`catalog_promotions`), proveedores (`catalog_suppliers`), pacientes (`catalog_patients`) y prescriptores (`catalog_prescribers`). Incluir validación de unicidad `parent_id_key` y `store_id_key`. STOP.

## CP-BACK-05
Implementar el flujo de Compras y Recepciones (`purchase_orders`, `purchase_receptions`): generación de órdenes, recepción física de mercancía e integración obligatoria 1:1 con la creación de lotes (`inventory_lots`) garantizando `reception_item_id NOT NULL`. STOP.

## CP-BACK-06
Implementar el motor de Inventarios y Trazabilidad FEFO: consulta de stock por sucursal (`inventory_stock`), registro append-only de movimientos (`inventory_movements`), transferencias entre sucursales (`inventory_transfers`), alertas de vencimiento (`inventory_alerts`), registro de incidentes (`inventory_incidents`) y reservas temporales (`inventory_reservations`). STOP.

## CP-BACK-07
Implementar la gestión de Recetas Médicas (`rx_prescriptions`, `rx_prescription_items`): validación de medicamentos de venta bajo receta, vinculación con prescriptores/pacientes y control de dispensación. STOP.

## CP-BACK-08
Implementar el motor de Ventas POS, Pagos y Devoluciones (`sales_orders`, `payments_transactions`, `sales_returns`): procesamiento de transacciones punto de venta, desglose de ítems, validación de idempotencia (`idempotency_keys`), registro de medios de pago y flujo de devoluciones con reingreso/baja de lotes. STOP.

## CP-BACK-09
Implementar el módulo de Control de Medicamentos Controlados y Saldos (`ctrl_ledger_entries`, `ctrl_balances`): libro oficial append-only para sustancias sujetas a fiscalización y conciliación automática de saldos. STOP.

## CP-BACK-10
Implementar Auditoría de Operaciones, Accesos PII y Eventos del Sistema (`audit_operations`, `audit_pii_access`, `outbox_events`): trazabilidad inmutable de acciones críticas, registro de acceso a datos sensibles del paciente y desacoplamiento de eventos salientes. STOP.

## CP-BACK-11
Ejecución de la suite completa de pruebas unitarias y de integración del backend. Verificar el cumplimiento estricto de borrado lógico (`estado='inactivo'`) e inmutabilidad de tablas append-only. Terminar en `HUMAN_STATUS: PENDING`. No iniciar desarrollo de frontend hasta aprobación explícita.