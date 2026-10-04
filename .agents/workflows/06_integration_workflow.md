# Workflow 06 — Integración Final, Pruebas E2E y Auditoría

Precondición: Frontend (`05_frontend_workflow`) PASSED + APPROVED.

Alcance: Validación integral E2E de todos los flujos de negocio del sistema de farmacia (42 tablas / 21 módulos), garantizando consistencia transaccional, trazabilidad de lotes, seguridad de datos PII y rendimiento operacional.

Skills: `php-development`, `frontend-engineering`, `error-handling-patterns`, `accessibility-compliance`.

## CP-INT-01
Validación de Autenticación, Sesión y RBAC: Prueba E2E del flujo de inicio de sesión (`/login`), renovación/revocación de tokens en `token_blacklist`, navegación por dashboard según perfil de usuario y cierre de sesión seguro (`/api/v1/auth/logout`). STOP.

## CP-INT-02
Integración de Catálogos y Sucursales: Prueba de punta a punta en la creación y edición de sucursales (`ops_stores`), cajas registradoras (`ops_registers`), categorías jerárquicas con validación `parent_id_key` (`catalog_categories`), productos (`catalog_products`), listas de precios y promociones. Verificar la inmutabilidad de registros con borrado lógico (`estado='inactivo'`). STOP.

## CP-INT-03
Integración de Compras, Recepciones y Generación de Lotes FEFO: Ciclo E2E desde la orden de compra (`purchase_orders`) hasta la recepción física (`purchase_receptions`), verificando la creación automática y obligatoria de lotes (`inventory_lots`) con trazabilidad `reception_item_id NOT NULL` y fechas de vencimiento. STOP.

## CP-INT-04
Integración de Gestión de Inventario, Transferencias y Alertas: Validación de actualización en tiempo real del stock (`inventory_stock`), registro de movimientos kárdex append-only (`inventory_movements`), transferencias inter-sucursales (`inventory_transfers`) y disparadores de alertas de stock mínimo y caducidad FEFO (`inventory_alerts`). STOP.

## CP-INT-05
Integración de Recetas Médicas y Libro Oficial de Controlados: Validación del flujo de dispensación con receta (`rx_prescriptions`), asociación de datos del médico prescriptor/paciente y afectación automática e inmutable del Libro de Controlados (`ctrl_ledger_entries`) y sus saldos (`ctrl_balances`). STOP.

## CP-INT-06
Integración de Punto de Venta (POS), Pagos, Idempotencia y Devoluciones: Prueba E2E de venta en caja con descuento automático de lotes por política FEFO, procesamiento de múltiples medios de pago (`payments_transactions`), prevención de duplicados vía claves de idempotencia (`idempotency_keys`), emisión de comprobantes y flujo de devoluciones (`sales_returns`). STOP.

## CP-INT-07
Auditoría, Protección PII y Seguridad Global: Verificación de logs inmutables de operaciones (`audit_operations`), registro explícito de acceso a datos confidenciales de pacientes (`audit_pii_access`), sanitización contra Inyección SQL, XSS, CSRF y validación de variables de entorno `.env` fuera del control de versiones. STOP.

## CP-INT-08
Cierre, Certificación E2E y Entregables Finales: Ejecución exitosa de la suite completa de pruebas unitarias, de integración y rendimiento. Confirmación de coincidencia total con el esquema oficial de 42 tablas de la base de datos. Terminar en `HUMAN_STATUS: PENDING` para la aprobación final de entrega.