# Paso 07 — Modelo físico (MySQL 8.x / InnoDB)

- **Workflow:** 02_database_workflow · **Paso:** 07
- **Skills utilizados:** `databases` (motor MySQL) — `postgresql-table-design`/`sql-server` no aplican
- **Motor:** MySQL **8.4 LTS** objetivo (mínimo 8.0.16 por CHECK enforced; decisión §2 "8.x")
- **Base:** `03_modelo_logico/modelo_logico.md`; aquí solo tipos, motor y ajustes del motor.

## 1. Parámetros de servidor (referencia mínima, `databases` skill)

| Parámetro | Valor | Por qué |
|---|---|---|
| `default_storage_engine` | `InnoDB` | §2 |
| `sql_mode` | `STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION` (+NO_ZERO_DATE) | regla 3 skill: strict mode ON |
| `character-set-server` / `collation` | `utf8mb4` / `utf8mb4_0900_ai_ci` | regla 7 skill |
| `time_zone` | `+00:00` (UTC) | RNF-047 |
| `log_bin` | `ON` (ROW) | PITR §17 |
| `require_secure_transport` | `ON` | §14, RNF-032 |
| `innodb_buffer_pool_size` | ~70% RAM | skill MySQL |
| `innodb_flush_log_at_trx_commit` | `1` | durabilidad (RNF-021) |
| `innodb_file_per_table` | `ON` | operación |
| `innodb_print_all_deadlocks` | `ON` | diagnóstico concurrencia §4 |
| `max_connections` | ≥ pool total (50–100/instancia app, §19) + margen DBA | §19 |
| slow query log | habilitado, umbral a medir | Paso 11 |
| `default_authentication_plugin` | `caching_sha2_password` | skill |

Secretos (credenciales) solo en `.env`/Docker Secrets (§14) — nunca en este documento.

## 2. Mapeo de tipos lógicos → MySQL

| Tipo lógico | MySQL | Notas |
|---|---|---|
| id surrogate | `BIGINT UNSIGNED AUTO_INCREMENT` | PK InnoDB (fila indexada inline); alternativa UUID rechazada: más ancho, peor por rango |
| VARCHAR corto (códigos, SKU, estados) | `VARCHAR(n)` con `n` real | estricto: sin truncado silencioso |
| TEXT (motivo, nombre) | `VARCHAR(255..1000)` donde hay límite conocido; `TEXT` solo si el límite no existe | evitar TEXT para CHECK |
| Entidades de dinero/precio | `DECIMAL(12,2)` | nunca FLOAT (dinero) |
| Cantidades de unidades | `INT` | unidades enteras; **supuesto**: si el cliente exige fraccionado (líquidos controlados), todas las columnas de cantidad migran juntas a `DECIMAL(12,3)` — validar con cliente |
| Fecha | `DATE` | vencimientos, fechas de receta |
| Marca de tiempo | `DATETIME(6)` (NO `TIMESTAMP`: límite 2038) | skill: DATETIME en MySQL |
| Booleano | `TINYINT(1)` o `ENUM('si','no')` | consistencia por tabla |
| Conjunto cerrado | `ENUM(...)` | estados; cambios = ALTER (documentado en migraciones) |
| JSON | `JSON` | auditoría (§9), payload outbox (§16) |
| Hash token / sha | `CHAR(64)` (hex SHA-256) | §15 |
| Referencia polimórfica | `BIGINT NULL` + `ENUM(...)` `ref_tipo` | sin FK (Paso 04 §11) |

**Charset/collation por columna:** default `utf8mb4_0900_ai_ci`; claves naturales de identificación (documento, lote) con collation `utf8mb4_0900_bin` si se exige distinción exacta (decidir en integridad, Paso 08).

## 3. Convenciones de objeto

- **Una sola base** por instancia (§3); nombre desde `DB_NAME` (env).
- **Prefijos de dominio** en tablas (§3): `auth_`, `ops_`, `catalog_`, `purchase_`, `inventory_`, `sales_`, `rx_`, `ctrl_`, `audit_`, `sys_`.
- **FK:** siempre con `ON DELETE` explícito (skill):
  - `RESTRICT` en toda FK desde/ hacia tablas transaccionales y maestras (default implícito, se hace explícito por skill).
  - `ON DELETE CASCADE` solo en asociativas de catálogo (`auth_role_permissions`, `auth_user_roles`, `catalog_product_categories`) y en hijos de detalle puro (`purchase_reception_items` cuando su padre se permita borrar — hoy no se borra: RESTRICT de hecho).
- **InnoDB crea el índice para cada FK automáticamente** → no duplicar índices FK (a diferencia de PostgreSQL); el Paso 11 lista solo índices de acceso.
- **UPDATE/DELETE:** prohibidos por grants en tablas transaccionales (`inventory_movements`, `ctrl_ledger_entries`, `audit_*`, `sales_orders`, `sales_order_items`, `payments_transactions`, `sales_returns`, `outbox_events`) — RNF-023/RNF-046. Excepción operacional controlada: `outbox_events`/`idempotency_keys` (purga por expiración, §6), `inventory_alerts` (actualización de estado).
- **Timestamps:** `created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)`; `updated_at ... DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)` en tablas mutables.

## 4. Tablas (referencia por dominio; columnas exactas en el Paso 04)

| Dominio | Tablas | Motor/charset | Notas físicas |
|---|---|---|---|
| auth | auth_roles, auth_permissions, auth_role_permissions, auth_users, auth_user_roles, token_blacklist | InnoDB/utf8mb4 | CASCADE solo en asociativas; `password_hash VARCHAR(255)` (Argon2id, §15); IX `token_blacklist(expires_at)` |
| ops | ops_stores, ops_registers, system_config | InnoDB | `system_config.config_value VARCHAR(4000)` + `value_type`; PK `config_key` |
| catalog | catalog_categories, catalog_products, catalog_product_categories, catalog_prices, catalog_promotions, catalog_suppliers, catalog_patients, catalog_prescribers | InnoDB | `sku`, `identificacion`, `numero_lote`-únicos; CHECKs numéricos reales (≥8.0.16) |
| purchase | purchase_orders, purchase_order_items, purchase_receptions, purchase_reception_items | InnoDB | `purchase_reception_items.lot_id` poblado al confirmar (FK nullable) |
| inventory | inventory_lots, inventory_stock, inventory_movements, inventory_transfers, inventory_transfer_items, inventory_reservations, inventory_alerts, inventory_incidents | InnoDB | `inventory_stock` PK (store_id, lot_id); CHECK `stock_available >= 0`; ENUM de estados |
| sales | sales_orders, sales_order_items, payments_transactions, sales_returns, sales_return_items | InnoDB | `numero` UNIQUE; `idempotency_key` UNIQUE nullable (MySQL permite múltiples NULL en UNIQUE) |
| rx | rx_prescriptions, rx_prescription_items | InnoDB | CHECK `cantidad_dispensada <= cantidad_prescrita` |
| ctrl | ctrl_ledger_entries, ctrl_balances | InnoDB | append-only asientos; PK compuesta en saldos |
| audit | audit_operations, audit_pii_access | InnoDB | solo INSERT (grants); `valores_antes/valores_despues JSON NULL` |
| sys | idempotency_keys, outbox_events | InnoDB | purga por `expires_at`; IX(estado, created_at) |

## 5. Particionamiento (§12) — plan, no implementación

- Umbral: >1.000.000 de filas medido, **previo a aplicar** (§12).
- Candidatas por rango de fecha: `inventory_movements`, `audit_operations`, `sales_order_items`.
- **Restricción MySQL:** toda PK/UNIQUE debe incluir la columna de partición. Por eso:
  - `inventory_movements.id` AUTO_INCREMENT como PK única impide particionar sin rediseño → si se particiona, la PK pasa a `(id, created_at)` (o se omite particionar y se usa índice por fecha). Decidir solo al superar el umbral.
  - `sales_orders.numero UNIQUE` global impide particionar `sales_orders` (no es candidata por volumen: 1 fila/venta ≠ ítems).
- No se particiona nada en la creación inicial (Paso 15).

## 6. Reportes (§13 — tablas resumen, "vistas materializadas" en MySQL)

- Mecanismo: tablas `report_*` refrescadas cada 5 min (propuesta §13; DB-P07 pendiente de confirmación).
- Diseño inicial: **una** tabla resumen `report_sales_daily(store_id, fecha, total_monto, n_ventas, n_dispensaciones)` poblada por job; el resto de reportes (RF-070/071) consultan el OLTP con los índices del Paso 11 hasta medir necesidad (YAGNI, §18 evolución: índices antes que réplica).
- Si la medición lo exige: read replica (§13) o más resúmenes — decisión con DB-P07.

## 7. Capacidades MySQL evaluadas (§20 — solo con requisito)

| Capacidad | Uso | Justificación |
|---|---|---|
| JSON | auditoría y outbox | RF-090, §16 |
| CHECK | RN-02/RN-05/RF-053 | RNF-025 (≥8.0.16) |
| UNIQUE | idempotencia y números naturales | RN-09, §6 |
| binlog ROW | PITR y trazabilidad operativa | §17, §9 |
| Event Scheduler | **no usar**: la purga se hace por job de aplicación (control, errores visibles) | regla §20.1 |
| FULLTEXT | **no usar**: búsqueda de producto por nombre no es RF prioritario | §20.1 |
| generated columns | **no usar** (sin patrón que lo exija aún) | §20.1 |

## 8. Verificación de este paso (`databases` self-check)

- [x] `DATETIME(6)` (no TIMESTAMP 2038)
- [x] `utf8mb4`
- [x] FK con `ON DELETE` explícito
- [x] Índices FK: los genera InnoDB, no se duplican
- [x] strict mode, TLS, buffer pool: §1
- [x] SIN credenciales ni secretos en el documento (RNF-034)
- [x] SIN SQL de creación (prohibido hasta Paso 15 con APPROVED)

## 9. Salida

- → Paso 08: `06_integridad/integridad.md`.
