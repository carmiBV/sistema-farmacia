# Reglas Universales para Agentes y Flujo del Proyecto

Este repositorio contiene las reglas operativas, arquitectura y guías de desarrollo para el **Sistema de Gestión Farmacéutica** (control de inventario por lote con algoritmo FEFO, punto de venta POS, recetas médicas, libro de medicamentos controlados y auditoría PII sobre una base de datos de 42 tablas).

---

## 1. Orden Obligatorio de Workflows y Agentes

El ciclo de desarrollo se rige bajo la siguiente secuencia estricta. Ninguna etapa puede iniciarse sin haber completado y registrado los puntos de control (`CP`) de la fase anterior:

1. **`01_requirements_workflow.md`**: Análisis de requisitos, reglas de negocio y arquitectura.
2. **`02_database_workflow.md`**: Diseño físico y scripts de base de datos de 42 tablas (`V1.0.0` a `V1.4.0`).
3. **`03_api_pilot_workflow.md`**: Implementación de la API Piloto sobre `catalog_categories`.
4. **`04_backend_workflow.md`**: Desarrollo backend modular (25 módulos, RBAC, FEFO, POS, Libro Oficial y Auditoría).
5. **`05_frontend_workflow.md`**: Desarrollo de la interfaz de usuario y experiencia POS (21 pantallas operativas).
6. **`06_integration_workflow.md`**: Pruebas E2E de integración, auditoría de seguridad y certificación final.

---

## 2. Reglas Principales de Operación

- **Ejecución por Checkpoints:** En las fases de desarrollo (Workflows 03 al 06), se debe ejecutar **UN solo punto de control (`CP`) por interacción**. Al finalizar, probar, actualizar el archivo de estado `.json` en `.agents/state/`, marcar `HUMAN_STATUS: PENDING` y detenerse a esperar la validación del usuario.
- **Inmutabilidad y Borrado Lógico:** Queda estrictamente prohibida la eliminación física mediante `DELETE FROM` o `DROP TABLE`. Todo borrado debe ser un cambio de estado a `inactivo`.
- **Registros Append-Only:** Los movimientos de inventario (`inventory_movements`), logs de auditoría (`audit_operations`, `audit_pii_access`) y asientos del Libro Oficial (`ctrl_ledger_entries`) son inalterables; cualquier corrección se realiza mediante movimientos compensatorios.
- **Gestión de Secretos:** Prohibido guardar credenciales, tokens o claves en archivos versionados. Toda configuración se consume desde variables de entorno (`.env`).
- **Código Fuente:** Todo el código nuevo de la aplicación se crea e integra exclusivamente en `proyecto/06_codigo/`.

---

## 3. Fuente de Verdad del Proyecto

- `proyecto/00_contexto/`: Alcance, reglas de negocio (RN) y restricciones operativas del dominio farmacéutico.
- `proyecto/01_requisitos/`: Especificación de Requisitos Funcionales (RF) y No Funcionales (RNF).
- `proyecto/02_configuracion/`: Parámetros globales del sistema y reglas de caja POS.
- `proyecto/04_decisiones/`: Acuerdos vigentes (`decisiones_api.md`, `decisiones_backend.md`, `decisiones_frontend.md`, `decisiones_desarrollo_incremental.md`).
- `proyecto/05_base_datos/13_sql/`: Esquema oficial de la base de datos de 42 tablas (migraciones SQL `V1.0.0` a `V1.4.0`).

---

## 4. Stack Tecnológico Oficial

- **Backend:** PHP 8.x puro (Arquitectura MVC multicapa: Controllers, Services, Repositories, Models, Validators con Front Controller en `public/index.php`).
- **Base de Datos:** MySQL 8.x / MariaDB (Motor InnoDB, driver PDO con consultas preparadas).
- **API:** RESTful con respuestas JSON estandarizadas (`/api/v1/...`).
- **Frontend:** HTML5 + CSS3 + JavaScript Vanilla (PHP rendering modular optimizado para pantallas POS táctiles, sin frameworks pesados como React/Vue/Angular en esta etapa).

---

## 5. Alcance Operativo del Dominio Farmacéutico

El sistema abarca el ciclo completo de 42 tablas distribuidas en 21 pantallas operativas de interfaz:

- **Autenticación y RBAC:** Control de acceso, sesiones y roles (`auth_*`, `token_blacklist`).
- **Configuración Operativa:** Sucursales, cajas y parámetros del sistema (`ops_*`, `system_config`).
- **Catálogos Generales:** Categorías (`catalog_categories`), productos (`catalog_products`), listas de precios, promociones, proveedores, pacientes y prescriptores.
- **Inventario y Compras (FEFO):** Órdenes de compra, recepciones con vinculación obligatoria de lotes (`reception_item_id NOT NULL`), stock por sucursal, kárdex append-only y alertas de vencimiento (`inventory_*`, `purchase_*`).
- **Recetas y Controlados:** Dispensación con prescripción médica (`rx_*`) y Libro Oficial de Controlados con saldo permanente (`ctrl_ledger_entries`, `ctrl_balances`).
- **Punto de Venta (POS) y Pagos:** Cobro en caja con claves de idempotencia (`idempotency_keys`), registro de transacciones de pago y devoluciones (`sales_*`, `payments_transactions`).
- **Auditoría y PII:** Trazabilidad inalterable de operaciones y log de consulta a datos sensibles de pacientes (`audit_operations`, `audit_pii_access`).