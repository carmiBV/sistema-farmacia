# Documentacion de la base real - Paso 18

**Estado: VERIFIED (drift = 0)** | **Fecha:** 2026-10-03 03:16:24 | **Skill:** `database-documentation`

Salida del Paso 18 (`.agents/workflows/02_database_workflow.md`). Artefactos en
`proyecto/05_base_datos/.database-documentation/` (21 artefactos + `README.md` + `gen_doc.ps1`).

## 1. Fuente inspeccionada

- Instancia efimera **MySQL 8.4.3 (Laragon)** en `127.0.0.1:33061`, sin contrasena de root (efimera), BD `farmacia_doc`.
- Esquema construido aplicando `13_sql/V1.0.0` -> `V1.1.0` -> `V1.2.0` (placeholders Flyway sustituidos por valores de prueba efimeros) -> `V1.3.0` -> `V1.4.0`, todo `exit=0`.
- Lectura via `information_schema` + `SHOW CREATE TABLE` (tier T2, servidor vivo) y parseo de `13_sql` (tier T3).
- **No es un despliegue de produccion:** los Pasos 16 (conexion) y 17 (despliegue) siguen pendientes de decision del usuario. Se apago la instancia y se borro el datadir efimero al cerrar el paso.

## 2. ERD obtenido de la base real

Diagrama generado desde las 84 FK reales leidas en vivo (aristas columna->columna):

```mermaid
auth_roles ||--o{ auth_role_permissions : "fk_arp_role"
auth_permissions ||--o{ auth_role_permissions : "fk_arp_permission"
auth_users ||--o{ auth_user_roles : "fk_aur_user"
auth_roles ||--o{ auth_user_roles : "fk_aur_role"
ops_stores ||--o{ ops_registers : "fk_ops_registers_store"
ops_stores ||--o{ system_config : "fk_system_config_store"
auth_users ||--o{ system_config : "fk_system_config_user"
catalog_categories ||--o{ catalog_categories : "fk_catalog_categories_parent"
catalog_products ||--o{ catalog_product_categories : "fk_cpc_product"
catalog_categories ||--o{ catalog_product_categories : "fk_cpc_category"
catalog_products ||--o{ catalog_prices : "fk_catalog_prices_product"
ops_stores ||--o{ catalog_prices : "fk_catalog_prices_store"
catalog_products ||--o{ catalog_promotions : "fk_catalog_promotions_product"
catalog_categories ||--o{ catalog_promotions : "fk_catalog_promotions_category"
catalog_suppliers ||--o{ purchase_orders : "fk_purchase_orders_supplier"
auth_users ||--o{ purchase_orders : "fk_purchase_orders_user"
purchase_orders ||--o{ purchase_order_items : "fk_poi_order"
catalog_products ||--o{ purchase_order_items : "fk_poi_product"
purchase_orders ||--o{ purchase_receptions : "fk_purchase_receptions_order"
auth_users ||--o{ purchase_receptions : "fk_purchase_receptions_recepcion"
auth_users ||--o{ purchase_receptions : "fk_purchase_receptions_confirmacion"
purchase_receptions ||--o{ purchase_reception_items : "fk_pri_reception"
catalog_products ||--o{ purchase_reception_items : "fk_pri_product"
catalog_products ||--o{ inventory_lots : "fk_inventory_lots_product"
purchase_reception_items ||--o{ inventory_lots : "fk_inventory_lots_reception"
auth_users ||--o{ inventory_lots : "fk_inventory_lots_liberado_por"
ops_stores ||--o{ inventory_stock : "fk_inventory_stock_store"
inventory_lots ||--o{ inventory_stock : "fk_inventory_stock_lot"
ops_stores ||--o{ inventory_movements : "fk_im_store"
inventory_lots ||--o{ inventory_movements : "fk_im_lot"
catalog_products ||--o{ inventory_movements : "fk_im_product"
auth_users ||--o{ inventory_movements : "fk_im_usuario"
auth_users ||--o{ inventory_movements : "fk_im_proponente"
auth_users ||--o{ inventory_movements : "fk_im_autorizador"
inventory_movements ||--o{ inventory_movements : "fk_im_ref"
ops_stores ||--o{ inventory_transfers : "fk_it_origen"
ops_stores ||--o{ inventory_transfers : "fk_it_destino"
auth_users ||--o{ inventory_transfers : "fk_it_solicitado"
auth_users ||--o{ inventory_transfers : "fk_it_despachado"
auth_users ||--o{ inventory_transfers : "fk_it_recibido"
inventory_transfers ||--o{ inventory_transfer_items : "fk_iti_transfer"
inventory_lots ||--o{ inventory_transfer_items : "fk_iti_lot"
catalog_products ||--o{ inventory_alerts : "fk_inventory_alerts_product"
ops_stores ||--o{ inventory_alerts : "fk_inventory_alerts_store"
auth_users ||--o{ inventory_alerts : "fk_inventory_alerts_resuelta"
ops_stores ||--o{ inventory_incidents : "fk_inventory_incidents_store"
inventory_lots ||--o{ inventory_incidents : "fk_inventory_incidents_lot"
auth_users ||--o{ inventory_incidents : "fk_inventory_incidents_user"
inventory_movements ||--o{ inventory_incidents : "fk_inventory_incidents_mov"
catalog_prescribers ||--o{ rx_prescriptions : "fk_rx_prescriptions_prescriber"
catalog_patients ||--o{ rx_prescriptions : "fk_rx_prescriptions_patient"
rx_prescriptions ||--o{ rx_prescription_items : "fk_rxpi_prescription"
catalog_products ||--o{ rx_prescription_items : "fk_rxpi_product"
ops_stores ||--o{ sales_orders : "fk_so_store"
ops_registers ||--o{ sales_orders : "fk_so_register"
catalog_patients ||--o{ sales_orders : "fk_so_paciente"
auth_users ||--o{ sales_orders : "fk_so_usuario"
auth_users ||--o{ sales_orders : "fk_so_quimico"
sales_orders ||--o{ sales_order_items : "fk_soi_order"
inventory_lots ||--o{ sales_order_items : "fk_soi_lot"
catalog_products ||--o{ sales_order_items : "fk_soi_product"
rx_prescription_items ||--o{ sales_order_items : "fk_soi_rx_item"
sales_orders ||--o{ payments_transactions : "fk_pt_order"
sales_orders ||--o{ sales_returns : "fk_sr_order"
ops_stores ||--o{ sales_returns : "fk_sr_store"
auth_users ||--o{ sales_returns : "fk_sr_usuario"
sales_returns ||--o{ sales_return_items : "fk_sri_return"
sales_order_items ||--o{ sales_return_items : "fk_sri_order_item"
inventory_lots ||--o{ sales_return_items : "fk_sri_lot"
catalog_products ||--o{ sales_return_items : "fk_sri_product"
sales_orders ||--o{ inventory_reservations : "fk_ir_order"
catalog_products ||--o{ inventory_reservations : "fk_ir_product"
ops_stores ||--o{ inventory_reservations : "fk_ir_store"
ops_stores ||--o{ ctrl_ledger_entries : "fk_cle_store"
catalog_products ||--o{ ctrl_ledger_entries : "fk_cle_product"
auth_users ||--o{ ctrl_ledger_entries : "fk_cle_usuario"
auth_users ||--o{ ctrl_ledger_entries : "fk_cle_autorizador"
ops_stores ||--o{ ctrl_balances : "fk_ctrl_balances_store"
catalog_products ||--o{ ctrl_balances : "fk_ctrl_balances_product"
auth_users ||--o{ audit_operations : "fk_audit_operations_user"
auth_users ||--o{ audit_pii_access : "fk_audit_pii_user"
catalog_patients ||--o{ audit_pii_access : "fk_audit_pii_paciente"
rx_prescriptions ||--o{ audit_pii_access : "fk_audit_pii_prescription"
inventory_lots ||--o{ purchase_reception_items : "fk_pri_lot"
```

Grafico equivalente (aristas `hija --> padre` sin repetir): `er_edges.txt` (76 pares unicos de 84 FK; las repetidas son multiples FK entre las mismas tablas).

## 3. Diccionario de datos

- `dictionary_tables.md` (T2): 42 tablas con columnas, tipos, nulabilidad, PK, FK, CHECK, UNIQUE e indice por cada una.
- `indexes_by_table.md` (T2): 149 grupos de indice por tabla (42 `PRIMARY` + 19 `ADD INDEX` de `V1.1.0` + indices InnoDB que MySQL crea automaticamente para cada FK).
- `introspection.tsv` / `live_columns_full.tsv` (T2): crudo de `information_schema`.`columns` (293 columnas).
- `modelo_fisico.sql` (T3): consolidado `V1.0.0 + V1.1.0 + V1.4.0`; excluye `V1.2.0` (grants -> credenciales, RNF-034) y `V1.3.0` (datos semilla).

### 3.1 Columnas por tabla

| Tabla | Columnas |
|---|---:|
| `audit_operations` | 9 |
| `audit_pii_access` | 7 |
| `auth_permissions` | 3 |
| `auth_role_permissions` | 2 |
| `auth_roles` | 3 |
| `auth_user_roles` | 2 |
| `auth_users` | 7 |
| `catalog_categories` | 4 |
| `catalog_patients` | 8 |
| `catalog_prescribers` | 5 |
| `catalog_prices` | 7 |
| `catalog_product_categories` | 2 |
| `catalog_products` | 9 |
| `catalog_promotions` | 6 |
| `catalog_suppliers` | 5 |
| `ctrl_balances` | 5 |
| `ctrl_ledger_entries` | 12 |
| `idempotency_keys` | 8 |
| `inventory_alerts` | 9 |
| `inventory_incidents` | 9 |
| `inventory_lots` | 11 |
| `inventory_movements` | 16 |
| `inventory_reservations` | 8 |
| `inventory_stock` | 7 |
| `inventory_transfer_items` | 5 |
| `inventory_transfers` | 10 |
| `ops_registers` | 4 |
| `ops_stores` | 5 |
| `outbox_events` | 9 |
| `payments_transactions` | 10 |
| `purchase_order_items` | 5 |
| `purchase_orders` | 7 |
| `purchase_reception_items` | 7 |
| `purchase_receptions` | 8 |
| `rx_prescription_items` | 6 |
| `rx_prescriptions` | 6 |
| `sales_order_items` | 9 |
| `sales_orders` | 12 |
| `sales_return_items` | 7 |
| `sales_returns` | 9 |
| `system_config` | 6 |
| `token_blacklist` | 4 |
| **Total** | **293** |

## 4. Comparacion diseno vs esquema real

| Objeto | Live (T2) | Modelo (T3) | Match |
|---|---:|---:|---|
| Tablas | 42 | 42 | OK |
| FK | 84 | 84 | OK |
| CHECK | 37 | 37 | OK |
| UNIQUE | 27 | 27 | OK |
| PK | 42 | 42 | OK |
| Triggers | 2 | 2 (V1.4.0) | OK |
| Grupos de indice | 149 | (V1.1.0 + auto) | informativo |

| Diferencia de identidad | Valor |
|---|---:|
| Tablas distintas (nombre) | 0 |
| Nombres FK distintos | 0 |
| Aristas columna->columna distintas | 0 |
| Nombres CHECK distintos | 0 |
| Nombres UNIQUE distintos | 0 |

Detalle maquina de cada conjunto: `README.md` y `diffs` al pie de `gen_doc.ps1`.

## 5. Deteccion de drift

**Drift = 0.** El esquema vivo coincide con el diseno en los cinco conjuntos comparables
(tablas, nombres FK, aristas, CHECK, UNIQUE), mas PK 42/42 y triggers 2/2.

- `fk_pri_lot` existe en el servidor aunque no este en ningun `CREATE TABLE`: esta como `ALTER TABLE purchase_reception_items ADD CONSTRAINT ...` en `V1.0.0`; el parser la detecta y por eso ambas cuentas dan 84.
- `uq_catalog_categories_nombre_parent` usa la columna generada `parent_id_key` (`IFNULL(parent_id,0)`), hallazgo F14 ya cerrado; existe igual en vivo y en el modelo.
- 27 UNIQUE con 8 columnas nulas por disenio (idempotency_key, reference): `NULL` significa "sin valor" y no se deduplica. Documentado en `06_integridad`.
- Indices: 149 grupos en vivo. La diferencia frente a los 19 `ADD INDEX` de `V1.1.0` son los indices InnoDB que el motor crea automaticamente para sostener cada FK (nombres `fk_*`); no es drift, es comportamiento del motor.

## 6. Trazabilidad

| Salida Paso 18 | Artefacto |
|---|---|
| ERD de la base real | `mermaid_rel.txt`, `er_edges.txt` |
| Diccionario de datos | `dictionary_tables.md`, `indexes_by_table.md` |
| Comparacion diseno vs real | `README.md` (gate + diffs), `model_*` vs `live_*` |
| Deteccion de drift | `README.md` (0 diferencias) |

Skills acreditables: `database-documentation`, `database-schema-designer`, `databases`.

## 7. Pendientes

- Paso 16 (conexion) y Paso 17 (despliegue) en la base real: decision del usuario (motor Docker vs local; credenciales solo en `.env`, RNF-034).
- Re-ejecutar las 54 pruebas de `14_pruebas/resultados_pruebas.md` contra la base real tras el Paso 17.
- Paso 20: `15_reportes/validacion_final.md`.
- F6-F9, DB-P09/DB-P12 siguen abiertos fuera de este workflow (RNF-041).

