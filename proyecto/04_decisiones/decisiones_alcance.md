# Decisiones de Alcance

**Estado:** APROBADO

## Alcance Oficial del Proyecto
Implementar la cobertura completa del sistema de gestión farmacéutica estructurado sobre la base de datos oficial de **42 tablas** y distribuido en **21 pantallas operativas**:

1. **Autenticación y RBAC:** Gestión de usuarios, roles, permisos y sesiones (`auth_users`, `auth_roles`, `auth_user_roles`, `token_blacklist`).
2. **Configuración Operativa:** Sucursales, cajas registradoras y parámetros globales del sistema (`ops_branches`, `ops_cash_registers`, `system_config`).
3. **Catálogos y Precios:** Categorías (`catalog_categories`), productos (`catalog_products`), listas de precios, promociones, proveedores, pacientes y prescriptores.
4. **Inventario y Compras (FEFO):** Órdenes de compra, recepciones con vinculación obligatoria de lotes (`reception_item_id NOT NULL`), stock por sucursal, kárdex append-only, transferencias y alertas de vencimiento (`inventory_*`, `purchase_*`).
5. **Recetas y Controlados:** Dispensación con prescripción médica (`rx_*`) y Libro Oficial de Controlados con saldo permanente (`ctrl_ledger_entries`, `ctrl_balances`).
6. **Punto de Venta (POS), Pagos y Devoluciones:** Interfaz de caja, claves de idempotencia (`idempotency_keys`), transacciones de pago y devoluciones (`sales_*`, `payments_transactions`).
7. **Auditoría y PII:** Trazabilidad inalterable de operaciones y log de consulta a datos sensibles de pacientes (`audit_operations`, `audit_pii_access`).

## Tabla Piloto Inicial
`catalog_categories` (Recurso piloto para el Workflow 03 de API).

## Eliminación y Modificación de Datos
- Todos los módulos operan mediante **borrado lógico** (`estado = 'inactivo'`).
- Queda estrictamente prohibido el uso de `DELETE FROM` o borrados físicos en la base de datos.
- Las tablas de auditoría, kárdex e inventario y el Libro Oficial son de tipo **append-only** inmutables.

## Cobertura de Interfaz
Desarrollo e integración de las 21 pantallas operativas identificadas en la arquitectura frontend (POS táctil, administración de catálogos, libro de controlados, recepciones e inventario).

## Regla de Ejecución
Un checkpoint por interacción. Cada checkpoint termina en `HUMAN_STATUS: PENDING` y el agente se detiene a esperar aprobación.