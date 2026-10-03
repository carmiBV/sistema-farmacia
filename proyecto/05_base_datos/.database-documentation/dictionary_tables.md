# Diccionario de datos (live) - farmacia_doc

- Fuente: `information_schema` leido de servidor MySQL vivo (127.0.0.1:33061), 2026-10-03 03:15:00.
- Tier: **T2** (verificado contra servidor vivo). Motor: InnoDB / utf8mb4.
- Conteos: 42 tablas, 42 PK, 84 FK, 27 UNIQUE, 37 CHECK, 149 grupos de indice, 2 triggers.

| Tabla | Columnas | Filas est. | Descripcion |
|---|---:|---:|---|
| `audit_operations` | 9 | 0 |  |
| `audit_pii_access` | 7 | 0 |  |
| `auth_permissions` | 3 | 0 |  |
| `auth_role_permissions` | 2 | 0 |  |
| `auth_roles` | 3 | 5 |  |
| `auth_user_roles` | 2 | 0 |  |
| `auth_users` | 7 | 0 |  |
| `catalog_categories` | 4 | 0 |  |
| `catalog_patients` | 8 | 0 |  |
| `catalog_prescribers` | 5 | 0 |  |
| `catalog_prices` | 7 | 0 |  |
| `catalog_product_categories` | 2 | 0 |  |
| `catalog_products` | 9 | 0 |  |
| `catalog_promotions` | 6 | 0 |  |
| `catalog_suppliers` | 5 | 0 |  |
| `ctrl_balances` | 5 | 0 |  |
| `ctrl_ledger_entries` | 12 | 0 |  |
| `idempotency_keys` | 8 | 0 |  |
| `inventory_alerts` | 9 | 0 |  |
| `inventory_incidents` | 9 | 0 |  |
| `inventory_lots` | 11 | 0 |  |
| `inventory_movements` | 16 | 0 |  |
| `inventory_reservations` | 8 | 0 |  |
| `inventory_stock` | 7 | 0 |  |
| `inventory_transfer_items` | 5 | 0 |  |
| `inventory_transfers` | 10 | 0 |  |
| `ops_registers` | 4 | 0 |  |
| `ops_stores` | 5 | 0 |  |
| `outbox_events` | 9 | 0 |  |
| `payments_transactions` | 10 | 0 |  |
| `purchase_order_items` | 5 | 0 |  |
| `purchase_orders` | 7 | 0 |  |
| `purchase_reception_items` | 7 | 0 |  |
| `purchase_receptions` | 8 | 0 |  |
| `rx_prescription_items` | 6 | 0 |  |
| `rx_prescriptions` | 6 | 0 |  |
| `sales_order_items` | 9 | 0 |  |
| `sales_orders` | 12 | 0 |  |
| `sales_return_items` | 7 | 0 |  |
| `sales_returns` | 9 | 0 |  |
| `system_config` | 6 | 0 |  |
| `token_blacklist` | 4 | 0 |  |

## `audit_operations`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 16384.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `usuario_id` | `bigint unsigned` | YES | NULL |  |  |
| 3 | `accion` | `varchar(100)` | NO | NULL |  |  |
| 4 | `entidad` | `varchar(100)` | NO | NULL |  |  |
| 5 | `entidad_id` | `bigint unsigned` | YES | NULL |  |  |
| 6 | `valores_antes` | `json` | YES | NULL |  |  |
| 7 | `valores_despues` | `json` | YES | NULL |  |  |
| 8 | `motivo` | `varchar(500)` | YES | NULL |  |  |
| 9 | `created_at` | `datetime(6)` | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- FK `fk_audit_operations_user`: `usuario_id` -> `auth_users`(`id`) ON DELETE RESTRICT

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_audit_operations_user` | 1 | `usuario_id` | NO | BTREE |  |
| `ix_ao_entidad_created` | 1 | `entidad` | NO | BTREE |  |
| `ix_ao_entidad_created` | 2 | `entidad_id` | NO | BTREE |  |
| `ix_ao_entidad_created` | 3 | `created_at` | NO | BTREE |  |
| `PRIMARY` | 1 | `id` | SI | BTREE |  |

## `audit_pii_access`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 49152.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `usuario_id` | `bigint unsigned` | NO | NULL |  |  |
| 3 | `accion` | `enum('consulta','creacion','modificacion','exportacion')` | NO | NULL |  |  |
| 4 | `paciente_id` | `bigint unsigned` | YES | NULL |  |  |
| 5 | `prescription_id` | `bigint unsigned` | YES | NULL |  |  |
| 6 | `motivo` | `varchar(500)` | YES | NULL |  |  |
| 7 | `created_at` | `datetime(6)` | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- FK `fk_audit_pii_paciente`: `paciente_id` -> `catalog_patients`(`id`) ON DELETE RESTRICT
- FK `fk_audit_pii_prescription`: `prescription_id` -> `rx_prescriptions`(`id`) ON DELETE RESTRICT
- FK `fk_audit_pii_user`: `usuario_id` -> `auth_users`(`id`) ON DELETE RESTRICT
- **CHECK** `chk_apii_objetivo`: `((`paciente_id` is not null) or (`prescription_id` is not null))`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_audit_pii_user` | 1 | `usuario_id` | NO | BTREE |  |
| `ix_apii_paciente_created` | 1 | `paciente_id` | NO | BTREE |  |
| `ix_apii_paciente_created` | 2 | `created_at` | NO | BTREE |  |
| `ix_apii_prescription_created` | 1 | `prescription_id` | NO | BTREE |  |
| `ix_apii_prescription_created` | 2 | `created_at` | NO | BTREE |  |
| `PRIMARY` | 1 | `id` | SI | BTREE |  |

## `auth_permissions`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 16384.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `clave` | `varchar(100)` | NO | NULL |  |  |
| 3 | `descripcion` | `varchar(255)` | YES | NULL |  |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- **UNIQUE** `uq_auth_permissions_clave`: `clave`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `PRIMARY` | 1 | `id` | SI | BTREE |  |
| `uq_auth_permissions_clave` | 1 | `clave` | SI | BTREE |  |

### Referenciada por

- FK `fk_arp_permission` referenciada por `auth_role_permissions`

## `auth_role_permissions`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 16384.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `role_id` | `bigint unsigned` | NO | NULL |  |  |
| 2 | `permission_id` | `bigint unsigned` | NO | NULL |  |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- FK `fk_arp_permission`: `permission_id` -> `auth_permissions`(`id`) ON DELETE CASCADE
- FK `fk_arp_role`: `role_id` -> `auth_roles`(`id`) ON DELETE CASCADE

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_arp_permission` | 1 | `permission_id` | NO | BTREE |  |
| `PRIMARY` | 1 | `role_id` | SI | BTREE |  |
| `PRIMARY` | 2 | `permission_id` | SI | BTREE |  |

## `auth_roles`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 5; data_length: 16384; index_length: 16384.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `nombre` | `varchar(100)` | NO | NULL |  |  |
| 3 | `descripcion` | `varchar(255)` | YES | NULL |  |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- **UNIQUE** `uq_auth_roles_nombre`: `nombre`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `PRIMARY` | 1 | `id` | SI | BTREE |  |
| `uq_auth_roles_nombre` | 1 | `nombre` | SI | BTREE |  |

### Referenciada por

- FK `fk_arp_role` referenciada por `auth_role_permissions`
- FK `fk_aur_role` referenciada por `auth_user_roles`

## `auth_user_roles`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 16384.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `user_id` | `bigint unsigned` | NO | NULL |  |  |
| 2 | `role_id` | `bigint unsigned` | NO | NULL |  |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- FK `fk_aur_role`: `role_id` -> `auth_roles`(`id`) ON DELETE CASCADE
- FK `fk_aur_user`: `user_id` -> `auth_users`(`id`) ON DELETE CASCADE

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_aur_role` | 1 | `role_id` | NO | BTREE |  |
| `PRIMARY` | 1 | `user_id` | SI | BTREE |  |
| `PRIMARY` | 2 | `role_id` | SI | BTREE |  |

## `auth_users`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 16384.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `usuario` | `varchar(100)` | NO | NULL |  |  |
| 3 | `password_hash` | `varchar(255)` | NO | NULL |  |  |
| 4 | `estado` | `enum('activo','bloqueado','inactivo')` | NO | activo |  |  |
| 5 | `mfa_habilitado` | `tinyint(1)` | NO | 0 |  |  |
| 6 | `ultimo_acceso_at` | `datetime(6)` | YES | NULL |  |  |
| 7 | `created_at` | `datetime(6)` | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- **UNIQUE** `uq_auth_users_usuario`: `usuario`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `PRIMARY` | 1 | `id` | SI | BTREE |  |
| `uq_auth_users_usuario` | 1 | `usuario` | SI | BTREE |  |

### Referenciada por

- FK `fk_audit_operations_user` referenciada por `audit_operations`
- FK `fk_audit_pii_user` referenciada por `audit_pii_access`
- FK `fk_aur_user` referenciada por `auth_user_roles`
- FK `fk_cle_autorizador` referenciada por `ctrl_ledger_entries`
- FK `fk_cle_usuario` referenciada por `ctrl_ledger_entries`
- FK `fk_inventory_alerts_resuelta` referenciada por `inventory_alerts`
- FK `fk_inventory_incidents_user` referenciada por `inventory_incidents`
- FK `fk_inventory_lots_liberado_por` referenciada por `inventory_lots`
- FK `fk_im_autorizador` referenciada por `inventory_movements`
- FK `fk_im_proponente` referenciada por `inventory_movements`
- FK `fk_im_usuario` referenciada por `inventory_movements`
- FK `fk_it_despachado` referenciada por `inventory_transfers`
- FK `fk_it_recibido` referenciada por `inventory_transfers`
- FK `fk_it_solicitado` referenciada por `inventory_transfers`
- FK `fk_purchase_orders_user` referenciada por `purchase_orders`
- FK `fk_purchase_receptions_confirmacion` referenciada por `purchase_receptions`
- FK `fk_purchase_receptions_recepcion` referenciada por `purchase_receptions`
- FK `fk_so_quimico` referenciada por `sales_orders`
- FK `fk_so_usuario` referenciada por `sales_orders`
- FK `fk_sr_usuario` referenciada por `sales_returns`
- FK `fk_system_config_user` referenciada por `system_config`

## `catalog_categories`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 32768.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `nombre` | `varchar(150)` | NO | NULL |  |  |
| 3 | `parent_id` | `bigint unsigned` | YES | NULL |  |  |
| 4 | `parent_id_key` | `bigint unsigned` | YES | NULL | STORED GENERATED |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- **UNIQUE** `uq_catalog_categories_nombre_parent`: `nombre, parent_id_key`
- FK `fk_catalog_categories_parent`: `parent_id` -> `catalog_categories`(`id`) ON DELETE RESTRICT

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_catalog_categories_parent` | 1 | `parent_id` | NO | BTREE |  |
| `PRIMARY` | 1 | `id` | SI | BTREE |  |
| `uq_catalog_categories_nombre_parent` | 1 | `nombre` | SI | BTREE |  |
| `uq_catalog_categories_nombre_parent` | 2 | `parent_id_key` | SI | BTREE |  |

### Referenciada por

- FK `fk_catalog_categories_parent` referenciada por `catalog_categories`
- FK `fk_cpc_category` referenciada por `catalog_product_categories`
- FK `fk_catalog_promotions_category` referenciada por `catalog_promotions`

## `catalog_patients`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 16384.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `identificacion` | `varchar(30)` | NO | NULL |  |  |
| 3 | `nombre` | `varchar(200)` | NO | NULL |  |  |
| 4 | `fecha_nacimiento` | `date` | YES | NULL |  |  |
| 5 | `contacto` | `varchar(255)` | YES | NULL |  |  |
| 6 | `estado` | `enum('activo','anonimizado')` | NO | activo |  |  |
| 7 | `created_at` | `datetime(6)` | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED |  |
| 8 | `anonimizado_at` | `datetime(6)` | YES | NULL |  |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- **UNIQUE** `uq_catalog_patients_identificacion`: `identificacion`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `PRIMARY` | 1 | `id` | SI | BTREE |  |
| `uq_catalog_patients_identificacion` | 1 | `identificacion` | SI | BTREE |  |

### Referenciada por

- FK `fk_audit_pii_paciente` referenciada por `audit_pii_access`
- FK `fk_rx_prescriptions_patient` referenciada por `rx_prescriptions`
- FK `fk_so_paciente` referenciada por `sales_orders`

## `catalog_prescribers`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 16384.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `identificacion` | `varchar(30)` | NO | NULL |  |  |
| 3 | `nombre` | `varchar(200)` | NO | NULL |  |  |
| 4 | `especialidad` | `varchar(150)` | YES | NULL |  |  |
| 5 | `estado` | `enum('activo','inactivo','anonimizado')` | NO | activo |  |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- **UNIQUE** `uq_catalog_prescribers_identificacion`: `identificacion`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `PRIMARY` | 1 | `id` | SI | BTREE |  |
| `uq_catalog_prescribers_identificacion` | 1 | `identificacion` | SI | BTREE |  |

### Referenciada por

- FK `fk_rx_prescriptions_prescriber` referenciada por `rx_prescriptions`

## `catalog_prices`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 32768.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `product_id` | `bigint unsigned` | NO | NULL |  |  |
| 3 | `store_id` | `bigint unsigned` | YES | NULL |  |  |
| 4 | `store_id_key` | `bigint unsigned` | YES | NULL | STORED GENERATED |  |
| 5 | `precio` | `decimal(12,2)` | NO | NULL |  |  |
| 6 | `vigente_desde` | `date` | NO | NULL |  |  |
| 7 | `vigente_hasta` | `date` | YES | NULL |  |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- **UNIQUE** `uq_catalog_prices`: `product_id, store_id_key, vigente_desde`
- FK `fk_catalog_prices_product`: `product_id` -> `catalog_products`(`id`) ON DELETE RESTRICT
- FK `fk_catalog_prices_store`: `store_id` -> `ops_stores`(`id`) ON DELETE RESTRICT
- **CHECK** `chk_catalog_prices_precio`: `(`precio` >= 0)`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_catalog_prices_store` | 1 | `store_id` | NO | BTREE |  |
| `PRIMARY` | 1 | `id` | SI | BTREE |  |
| `uq_catalog_prices` | 1 | `product_id` | SI | BTREE |  |
| `uq_catalog_prices` | 2 | `store_id_key` | SI | BTREE |  |
| `uq_catalog_prices` | 3 | `vigente_desde` | SI | BTREE |  |

## `catalog_product_categories`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 16384.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `product_id` | `bigint unsigned` | NO | NULL |  |  |
| 2 | `category_id` | `bigint unsigned` | NO | NULL |  |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- FK `fk_cpc_category`: `category_id` -> `catalog_categories`(`id`) ON DELETE CASCADE
- FK `fk_cpc_product`: `product_id` -> `catalog_products`(`id`) ON DELETE CASCADE

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_cpc_category` | 1 | `category_id` | NO | BTREE |  |
| `PRIMARY` | 1 | `product_id` | SI | BTREE |  |
| `PRIMARY` | 2 | `category_id` | SI | BTREE |  |

## `catalog_products`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 16384.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `sku` | `varchar(50)` | NO | NULL |  |  |
| 3 | `nombre` | `varchar(255)` | NO | NULL |  |  |
| 4 | `principio_activo` | `varchar(255)` | NO | NULL |  |  |
| 5 | `presentacion` | `varchar(100)` | NO | NULL |  |  |
| 6 | `concentracion` | `varchar(100)` | NO | NULL |  |  |
| 7 | `condicion_venta` | `enum('libre','receta','controlado')` | NO | NULL |  |  |
| 8 | `estado` | `enum('activo','inactivo')` | NO | activo |  |  |
| 9 | `created_at` | `datetime(6)` | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- **UNIQUE** `uq_catalog_products_sku`: `sku`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `PRIMARY` | 1 | `id` | SI | BTREE |  |
| `uq_catalog_products_sku` | 1 | `sku` | SI | BTREE |  |

### Referenciada por

- FK `fk_catalog_prices_product` referenciada por `catalog_prices`
- FK `fk_cpc_product` referenciada por `catalog_product_categories`
- FK `fk_catalog_promotions_product` referenciada por `catalog_promotions`
- FK `fk_ctrl_balances_product` referenciada por `ctrl_balances`
- FK `fk_cle_product` referenciada por `ctrl_ledger_entries`
- FK `fk_inventory_alerts_product` referenciada por `inventory_alerts`
- FK `fk_inventory_lots_product` referenciada por `inventory_lots`
- FK `fk_im_product` referenciada por `inventory_movements`
- FK `fk_ir_product` referenciada por `inventory_reservations`
- FK `fk_poi_product` referenciada por `purchase_order_items`
- FK `fk_pri_product` referenciada por `purchase_reception_items`
- FK `fk_rxpi_product` referenciada por `rx_prescription_items`
- FK `fk_soi_product` referenciada por `sales_order_items`
- FK `fk_sri_product` referenciada por `sales_return_items`

## `catalog_promotions`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 32768.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `product_id` | `bigint unsigned` | YES | NULL |  |  |
| 3 | `category_id` | `bigint unsigned` | YES | NULL |  |  |
| 4 | `descuento_pct` | `decimal(5,2)` | NO | NULL |  |  |
| 5 | `desde` | `date` | NO | NULL |  |  |
| 6 | `hasta` | `date` | NO | NULL |  |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- FK `fk_catalog_promotions_category`: `category_id` -> `catalog_categories`(`id`) ON DELETE RESTRICT
- FK `fk_catalog_promotions_product`: `product_id` -> `catalog_products`(`id`) ON DELETE RESTRICT
- **CHECK** `chk_catalog_promotions_alcance`: `((`product_id` is not null) or (`category_id` is not null))`
- **CHECK** `chk_catalog_promotions_pct`: `((`descuento_pct` > 0) and (`descuento_pct` <= 100))`
- **CHECK** `chk_catalog_promotions_rango`: `(`hasta` >= `desde`)`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_catalog_promotions_category` | 1 | `category_id` | NO | BTREE |  |
| `fk_catalog_promotions_product` | 1 | `product_id` | NO | BTREE |  |
| `PRIMARY` | 1 | `id` | SI | BTREE |  |

## `catalog_suppliers`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 16384.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `identificacion` | `varchar(30)` | NO | NULL |  |  |
| 3 | `nombre` | `varchar(200)` | NO | NULL |  |  |
| 4 | `contacto` | `varchar(255)` | YES | NULL |  |  |
| 5 | `estado` | `enum('activo','inactivo')` | NO | activo |  |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- **UNIQUE** `uq_catalog_suppliers_identificacion`: `identificacion`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `PRIMARY` | 1 | `id` | SI | BTREE |  |
| `uq_catalog_suppliers_identificacion` | 1 | `identificacion` | SI | BTREE |  |

### Referenciada por

- FK `fk_purchase_orders_supplier` referenciada por `purchase_orders`

## `ctrl_balances`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 16384.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `store_id` | `bigint unsigned` | NO | NULL |  |  |
| 2 | `product_id` | `bigint unsigned` | NO | NULL |  |  |
| 3 | `saldo` | `int` | NO | NULL |  |  |
| 4 | `version` | `bigint` | NO | 0 |  |  |
| 5 | `updated_at` | `datetime(6)` | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED on update CURRENT_TIMESTAMP(6) |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- FK `fk_ctrl_balances_product`: `product_id` -> `catalog_products`(`id`) ON DELETE RESTRICT
- FK `fk_ctrl_balances_store`: `store_id` -> `ops_stores`(`id`) ON DELETE RESTRICT
- **CHECK** `chk_ctrl_balances_saldo`: `(`saldo` >= 0)`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_ctrl_balances_product` | 1 | `product_id` | NO | BTREE |  |
| `PRIMARY` | 1 | `store_id` | SI | BTREE |  |
| `PRIMARY` | 2 | `product_id` | SI | BTREE |  |

## `ctrl_ledger_entries`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 65536.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `store_id` | `bigint unsigned` | NO | NULL |  |  |
| 3 | `product_id` | `bigint unsigned` | NO | NULL |  |  |
| 4 | `tipo` | `enum('entrada','salida','ajuste','devolucion','transferencia_out','transferencia_in')` | NO | NULL |  |  |
| 5 | `cantidad` | `int` | NO | NULL |  |  |
| 6 | `saldo_resultante` | `int` | NO | NULL |  |  |
| 7 | `usuario_id` | `bigint unsigned` | NO | NULL |  |  |
| 8 | `autorizador_id` | `bigint unsigned` | YES | NULL |  |  |
| 9 | `motivo` | `varchar(500)` | NO | NULL |  |  |
| 10 | `ref_tipo` | `enum('venta','recepcion','transferencia','devolucion','ajuste')` | NO | NULL |  |  |
| 11 | `ref_id` | `bigint unsigned` | NO | NULL |  |  |
| 12 | `created_at` | `datetime(6)` | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- FK `fk_cle_autorizador`: `autorizador_id` -> `auth_users`(`id`) ON DELETE RESTRICT
- FK `fk_cle_product`: `product_id` -> `catalog_products`(`id`) ON DELETE RESTRICT
- FK `fk_cle_store`: `store_id` -> `ops_stores`(`id`) ON DELETE RESTRICT
- FK `fk_cle_usuario`: `usuario_id` -> `auth_users`(`id`) ON DELETE RESTRICT
- **CHECK** `chk_cle_ajuste_autorizado`: `((`tipo` <> _utf8mb4\\'ajuste\\') or (`autorizador_id` is not null))`
- **CHECK** `chk_cle_cantidad`: `(`cantidad` > 0)`
- **CHECK** `chk_cle_doble_autorizacion`: `((`autorizador_id` is null) or (`autorizador_id` <> `usuario_id`))`
- **CHECK** `chk_cle_saldo`: `(`saldo_resultante` >= 0)`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_cle_autorizador` | 1 | `autorizador_id` | NO | BTREE |  |
| `fk_cle_product` | 1 | `product_id` | NO | BTREE |  |
| `fk_cle_usuario` | 1 | `usuario_id` | NO | BTREE |  |
| `ix_cle_ref` | 1 | `ref_tipo` | NO | BTREE |  |
| `ix_cle_ref` | 2 | `ref_id` | NO | BTREE |  |
| `ix_cle_store_product_created` | 1 | `store_id` | NO | BTREE |  |
| `ix_cle_store_product_created` | 2 | `product_id` | NO | BTREE |  |
| `ix_cle_store_product_created` | 3 | `created_at` | NO | BTREE |  |
| `PRIMARY` | 1 | `id` | SI | BTREE |  |

## `idempotency_keys`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 16384.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `idempotency_key` | `varchar(255)` | NO | NULL |  |  |
| 3 | `operacion` | `varchar(100)` | NO | NULL |  |  |
| 4 | `request_hash` | `char(64)` | NO | NULL |  |  |
| 5 | `respuesta_ref` | `varchar(255)` | YES | NULL |  |  |
| 6 | `estado` | `enum('en_proceso','completado')` | NO | NULL |  |  |
| 7 | `created_at` | `datetime(6)` | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED |  |
| 8 | `expires_at` | `datetime(6)` | NO | NULL |  |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- **UNIQUE** `uq_idempotency_keys_key`: `idempotency_key`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `ix_ik_expires` | 1 | `expires_at` | NO | BTREE |  |
| `PRIMARY` | 1 | `id` | SI | BTREE |  |
| `uq_idempotency_keys_key` | 1 | `idempotency_key` | SI | BTREE |  |

## `inventory_alerts`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 65536.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `tipo` | `enum('stock_minimo','vencimiento')` | NO | NULL |  |  |
| 3 | `product_id` | `bigint unsigned` | NO | NULL |  |  |
| 4 | `store_id` | `bigint unsigned` | YES | NULL |  |  |
| 5 | `store_id_key` | `bigint unsigned` | YES | NULL | STORED GENERATED |  |
| 6 | `fecha_generada` | `datetime(6)` | NO | NULL |  |  |
| 7 | `estado` | `enum('abierta','resuelta')` | NO | abierta |  |  |
| 8 | `resuelta_por` | `bigint unsigned` | YES | NULL |  |  |
| 9 | `resuelta_at` | `datetime(6)` | YES | NULL |  |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- **UNIQUE** `uq_inventory_alerts`: `tipo, product_id, store_id_key, fecha_generada`
- FK `fk_inventory_alerts_product`: `product_id` -> `catalog_products`(`id`) ON DELETE RESTRICT
- FK `fk_inventory_alerts_resuelta`: `resuelta_por` -> `auth_users`(`id`) ON DELETE RESTRICT
- FK `fk_inventory_alerts_store`: `store_id` -> `ops_stores`(`id`) ON DELETE RESTRICT

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_inventory_alerts_product` | 1 | `product_id` | NO | BTREE |  |
| `fk_inventory_alerts_resuelta` | 1 | `resuelta_por` | NO | BTREE |  |
| `fk_inventory_alerts_store` | 1 | `store_id` | NO | BTREE |  |
| `ix_ia_tipo_estado_fecha` | 1 | `tipo` | NO | BTREE |  |
| `ix_ia_tipo_estado_fecha` | 2 | `estado` | NO | BTREE |  |
| `ix_ia_tipo_estado_fecha` | 3 | `fecha_generada` | NO | BTREE |  |
| `PRIMARY` | 1 | `id` | SI | BTREE |  |
| `uq_inventory_alerts` | 1 | `tipo` | SI | BTREE |  |
| `uq_inventory_alerts` | 2 | `product_id` | SI | BTREE |  |
| `uq_inventory_alerts` | 3 | `store_id_key` | SI | BTREE |  |
| `uq_inventory_alerts` | 4 | `fecha_generada` | SI | BTREE |  |

## `inventory_incidents`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 65536.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `store_id` | `bigint unsigned` | NO | NULL |  |  |
| 3 | `lot_id` | `bigint unsigned` | NO | NULL |  |  |
| 4 | `stock_registrado` | `int` | NO | NULL |  |  |
| 5 | `consumo_declarado` | `int` | NO | NULL |  |  |
| 6 | `estado` | `enum('abierto','ajustado','descartado')` | NO | abierto |  |  |
| 7 | `abierto_por` | `bigint unsigned` | NO | NULL |  |  |
| 8 | `created_at` | `datetime(6)` | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED |  |
| 9 | `movimiento_ajuste_id` | `bigint unsigned` | YES | NULL |  |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- FK `fk_inventory_incidents_lot`: `lot_id` -> `inventory_lots`(`id`) ON DELETE RESTRICT
- FK `fk_inventory_incidents_mov`: `movimiento_ajuste_id` -> `inventory_movements`(`id`) ON DELETE RESTRICT
- FK `fk_inventory_incidents_store`: `store_id` -> `ops_stores`(`id`) ON DELETE RESTRICT
- FK `fk_inventory_incidents_user`: `abierto_por` -> `auth_users`(`id`) ON DELETE RESTRICT

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_inventory_incidents_lot` | 1 | `lot_id` | NO | BTREE |  |
| `fk_inventory_incidents_mov` | 1 | `movimiento_ajuste_id` | NO | BTREE |  |
| `fk_inventory_incidents_user` | 1 | `abierto_por` | NO | BTREE |  |
| `ix_ii_store_estado` | 1 | `store_id` | NO | BTREE |  |
| `ix_ii_store_estado` | 2 | `estado` | NO | BTREE |  |
| `PRIMARY` | 1 | `id` | SI | BTREE |  |

## `inventory_lots`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 49152.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `product_id` | `bigint unsigned` | NO | NULL |  |  |
| 3 | `numero_lote` | `varchar(50)` | NO | NULL |  |  |
| 4 | `fecha_vencimiento` | `date` | NO | NULL |  |  |
| 5 | `estado` | `enum('cuarentena','liberado','retirado')` | NO | cuarentena |  |  |
| 6 | `reception_item_id` | `bigint unsigned` | NO | NULL |  |  |
| 7 | `liberado_por` | `bigint unsigned` | YES | NULL |  |  |
| 8 | `liberado_at` | `datetime(6)` | YES | NULL |  |  |
| 9 | `motivo_liberacion` | `varchar(500)` | YES | NULL |  |  |
| 10 | `created_at` | `datetime(6)` | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED |  |
| 11 | `retirado_at` | `datetime(6)` | YES | NULL |  |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- **UNIQUE** `uq_inventory_lots_producto_lote`: `product_id, numero_lote`
- **UNIQUE** `uq_inventory_lots_reception`: `reception_item_id`
- FK `fk_inventory_lots_liberado_por`: `liberado_por` -> `auth_users`(`id`) ON DELETE RESTRICT
- FK `fk_inventory_lots_product`: `product_id` -> `catalog_products`(`id`) ON DELETE RESTRICT
- FK `fk_inventory_lots_reception`: `reception_item_id` -> `purchase_reception_items`(`id`) ON DELETE RESTRICT
- **CHECK** `chk_inventory_lots_liberacion`: `((`estado` <> _utf8mb4\\'liberado\\') or ((`liberado_por` is not null) and (`liberado_at` is not null) and (`motivo_liberacion` is not null)))`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_inventory_lots_liberado_por` | 1 | `liberado_por` | NO | BTREE |  |
| `ix_inventory_lots_fefo` | 1 | `product_id` | NO | BTREE |  |
| `ix_inventory_lots_fefo` | 2 | `estado` | NO | BTREE |  |
| `ix_inventory_lots_fefo` | 3 | `fecha_vencimiento` | NO | BTREE |  |
| `PRIMARY` | 1 | `id` | SI | BTREE |  |
| `uq_inventory_lots_producto_lote` | 1 | `product_id` | SI | BTREE |  |
| `uq_inventory_lots_producto_lote` | 2 | `numero_lote` | SI | BTREE |  |
| `uq_inventory_lots_reception` | 1 | `reception_item_id` | SI | BTREE |  |

### Referenciada por

- FK `fk_inventory_incidents_lot` referenciada por `inventory_incidents`
- FK `fk_im_lot` referenciada por `inventory_movements`
- FK `fk_inventory_stock_lot` referenciada por `inventory_stock`
- FK `fk_iti_lot` referenciada por `inventory_transfer_items`
- FK `fk_pri_lot` referenciada por `purchase_reception_items`
- FK `fk_soi_lot` referenciada por `sales_order_items`
- FK `fk_sri_lot` referenciada por `sales_return_items`

## `inventory_movements`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 131072.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `store_id` | `bigint unsigned` | NO | NULL |  |  |
| 3 | `lot_id` | `bigint unsigned` | NO | NULL |  |  |
| 4 | `product_id` | `bigint unsigned` | NO | NULL |  |  |
| 5 | `tipo` | `enum('entrada','salida','ajuste_baja','ajuste_incremento','devolucion','transferencia_salida','transferencia_entrada','compensatorio')` | NO | NULL |  |  |
| 6 | `cantidad` | `int` | NO | NULL |  |  |
| 7 | `signo` | `smallint` | NO | NULL |  |  |
| 8 | `usuario_id` | `bigint unsigned` | NO | NULL |  |  |
| 9 | `motivo` | `varchar(500)` | NO | NULL |  |  |
| 10 | `proponente_id` | `bigint unsigned` | YES | NULL |  |  |
| 11 | `autorizador_id` | `bigint unsigned` | YES | NULL |  |  |
| 12 | `ref_tipo` | `enum('venta','recepcion','transferencia','devolucion','incidente','manual')` | YES | NULL |  |  |
| 13 | `ref_id` | `bigint unsigned` | YES | NULL |  |  |
| 14 | `movimiento_ref_id` | `bigint unsigned` | YES | NULL |  |  |
| 15 | `idempotency_key` | `varchar(255)` | YES | NULL |  |  |
| 16 | `created_at` | `datetime(6)` | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- **UNIQUE** `uq_im_idem`: `idempotency_key`
- FK `fk_im_autorizador`: `autorizador_id` -> `auth_users`(`id`) ON DELETE RESTRICT
- FK `fk_im_lot`: `lot_id` -> `inventory_lots`(`id`) ON DELETE RESTRICT
- FK `fk_im_product`: `product_id` -> `catalog_products`(`id`) ON DELETE RESTRICT
- FK `fk_im_proponente`: `proponente_id` -> `auth_users`(`id`) ON DELETE RESTRICT
- FK `fk_im_ref`: `movimiento_ref_id` -> `inventory_movements`(`id`) ON DELETE RESTRICT
- FK `fk_im_store`: `store_id` -> `ops_stores`(`id`) ON DELETE RESTRICT
- FK `fk_im_usuario`: `usuario_id` -> `auth_users`(`id`) ON DELETE RESTRICT
- **CHECK** `chk_im_cantidad`: `(`cantidad` > 0)`
- **CHECK** `chk_im_doble_autorizacion`: `((`autorizador_id` is null) or (`proponente_id` is null) or (`autorizador_id` <> `proponente_id`))`
- **CHECK** `chk_im_ref`: `((`ref_tipo` is not null) or (`tipo` in (_utf8mb4\\'ajuste_incremento\\',_utf8mb4\\'ajuste_baja\\')))`
- **CHECK** `chk_im_signo`: `(`signo` in (-(1),1))`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_im_autorizador` | 1 | `autorizador_id` | NO | BTREE |  |
| `fk_im_product` | 1 | `product_id` | NO | BTREE |  |
| `fk_im_proponente` | 1 | `proponente_id` | NO | BTREE |  |
| `fk_im_ref` | 1 | `movimiento_ref_id` | NO | BTREE |  |
| `fk_im_usuario` | 1 | `usuario_id` | NO | BTREE |  |
| `ix_im_lot_created` | 1 | `lot_id` | NO | BTREE |  |
| `ix_im_lot_created` | 2 | `created_at` | NO | BTREE |  |
| `ix_im_ref` | 1 | `ref_tipo` | NO | BTREE |  |
| `ix_im_ref` | 2 | `ref_id` | NO | BTREE |  |
| `ix_im_store_created` | 1 | `store_id` | NO | BTREE |  |
| `ix_im_store_created` | 2 | `created_at` | NO | BTREE |  |
| `PRIMARY` | 1 | `id` | SI | BTREE |  |
| `uq_im_idem` | 1 | `idempotency_key` | SI | BTREE |  |

### Referenciada por

- FK `fk_inventory_incidents_mov` referenciada por `inventory_incidents`
- FK `fk_im_ref` referenciada por `inventory_movements`

## `inventory_reservations`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 49152.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `order_id` | `bigint unsigned` | YES | NULL |  |  |
| 3 | `product_id` | `bigint unsigned` | NO | NULL |  |  |
| 4 | `store_id` | `bigint unsigned` | NO | NULL |  |  |
| 5 | `qty` | `int` | NO | NULL |  |  |
| 6 | `status` | `enum('pending','confirmed','expired','cancelled')` | NO | pending |  |  |
| 7 | `expires_at` | `datetime(6)` | NO | NULL |  |  |
| 8 | `created_at` | `datetime(6)` | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- FK `fk_ir_order`: `order_id` -> `sales_orders`(`id`) ON DELETE RESTRICT
- FK `fk_ir_product`: `product_id` -> `catalog_products`(`id`) ON DELETE RESTRICT
- FK `fk_ir_store`: `store_id` -> `ops_stores`(`id`) ON DELETE RESTRICT
- **CHECK** `chk_ir_qty`: `(`qty` > 0)`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_ir_order` | 1 | `order_id` | NO | BTREE |  |
| `fk_ir_product` | 1 | `product_id` | NO | BTREE |  |
| `ix_ir_store_product_status_expires` | 1 | `store_id` | NO | BTREE |  |
| `ix_ir_store_product_status_expires` | 2 | `product_id` | NO | BTREE |  |
| `ix_ir_store_product_status_expires` | 3 | `status` | NO | BTREE |  |
| `ix_ir_store_product_status_expires` | 4 | `expires_at` | NO | BTREE |  |
| `PRIMARY` | 1 | `id` | SI | BTREE |  |

## `inventory_stock`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 16384.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `store_id` | `bigint unsigned` | NO | NULL |  |  |
| 2 | `lot_id` | `bigint unsigned` | NO | NULL |  |  |
| 3 | `stock_available` | `int` | NO | 0 |  |  |
| 4 | `stock_reserved` | `int` | NO | 0 |  |  |
| 5 | `stock_sold` | `int` | NO | 0 |  |  |
| 6 | `version` | `bigint` | NO | 0 |  |  |
| 7 | `updated_at` | `datetime(6)` | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED on update CURRENT_TIMESTAMP(6) |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- FK `fk_inventory_stock_lot`: `lot_id` -> `inventory_lots`(`id`) ON DELETE RESTRICT
- FK `fk_inventory_stock_store`: `store_id` -> `ops_stores`(`id`) ON DELETE RESTRICT
- **CHECK** `chk_inventory_stock_available`: `(`stock_available` >= 0)`
- **CHECK** `chk_inventory_stock_reserved`: `(`stock_reserved` >= 0)`
- **CHECK** `chk_inventory_stock_sold`: `(`stock_sold` >= 0)`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `ix_inventory_stock_lot_store` | 1 | `lot_id` | NO | BTREE |  |
| `ix_inventory_stock_lot_store` | 2 | `store_id` | NO | BTREE |  |
| `PRIMARY` | 1 | `store_id` | SI | BTREE |  |
| `PRIMARY` | 2 | `lot_id` | SI | BTREE |  |

## `inventory_transfer_items`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 32768.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `transfer_id` | `bigint unsigned` | NO | NULL |  |  |
| 3 | `lot_id` | `bigint unsigned` | NO | NULL |  |  |
| 4 | `cantidad_despachada` | `int` | YES | NULL |  |  |
| 5 | `cantidad_recibida` | `int` | YES | NULL |  |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- **UNIQUE** `uq_iti_transfer_lote`: `transfer_id, lot_id`
- FK `fk_iti_lot`: `lot_id` -> `inventory_lots`(`id`) ON DELETE RESTRICT
- FK `fk_iti_transfer`: `transfer_id` -> `inventory_transfers`(`id`) ON DELETE RESTRICT
- **CHECK** `chk_iti_despachada`: `((`cantidad_despachada` is null) or (`cantidad_despachada` > 0))`
- **CHECK** `chk_iti_recibida`: `((`cantidad_recibida` is null) or (`cantidad_recibida` >= 0))`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_iti_lot` | 1 | `lot_id` | NO | BTREE |  |
| `PRIMARY` | 1 | `id` | SI | BTREE |  |
| `uq_iti_transfer_lote` | 1 | `transfer_id` | SI | BTREE |  |
| `uq_iti_transfer_lote` | 2 | `lot_id` | SI | BTREE |  |

## `inventory_transfers`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 98304.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `store_origen_id` | `bigint unsigned` | NO | NULL |  |  |
| 3 | `store_destino_id` | `bigint unsigned` | NO | NULL |  |  |
| 4 | `estado` | `enum('solicitada','despachada','recibida','cerrada','rechazada')` | NO | solicitada |  |  |
| 5 | `solicitado_por` | `bigint unsigned` | NO | NULL |  |  |
| 6 | `despachado_por` | `bigint unsigned` | YES | NULL |  |  |
| 7 | `recibido_por` | `bigint unsigned` | YES | NULL |  |  |
| 8 | `created_at` | `datetime(6)` | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED |  |
| 9 | `updated_at` | `datetime(6)` | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED on update CURRENT_TIMESTAMP(6) |  |
| 10 | `idempotency_key` | `varchar(255)` | YES | NULL |  |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- **UNIQUE** `uq_it_idem`: `idempotency_key`
- FK `fk_it_despachado`: `despachado_por` -> `auth_users`(`id`) ON DELETE RESTRICT
- FK `fk_it_destino`: `store_destino_id` -> `ops_stores`(`id`) ON DELETE RESTRICT
- FK `fk_it_origen`: `store_origen_id` -> `ops_stores`(`id`) ON DELETE RESTRICT
- FK `fk_it_recibido`: `recibido_por` -> `auth_users`(`id`) ON DELETE RESTRICT
- FK `fk_it_solicitado`: `solicitado_por` -> `auth_users`(`id`) ON DELETE RESTRICT
- **CHECK** `chk_it_distintas`: `(`store_origen_id` <> `store_destino_id`)`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_it_despachado` | 1 | `despachado_por` | NO | BTREE |  |
| `fk_it_recibido` | 1 | `recibido_por` | NO | BTREE |  |
| `fk_it_solicitado` | 1 | `solicitado_por` | NO | BTREE |  |
| `ix_it_destino_estado` | 1 | `store_destino_id` | NO | BTREE |  |
| `ix_it_destino_estado` | 2 | `estado` | NO | BTREE |  |
| `ix_it_origen_estado` | 1 | `store_origen_id` | NO | BTREE |  |
| `ix_it_origen_estado` | 2 | `estado` | NO | BTREE |  |
| `PRIMARY` | 1 | `id` | SI | BTREE |  |
| `uq_it_idem` | 1 | `idempotency_key` | SI | BTREE |  |

### Referenciada por

- FK `fk_iti_transfer` referenciada por `inventory_transfer_items`

## `ops_registers`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 16384.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `store_id` | `bigint unsigned` | NO | NULL |  |  |
| 3 | `codigo` | `varchar(20)` | NO | NULL |  |  |
| 4 | `estado` | `enum('activa','inactiva')` | NO | activa |  |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- **UNIQUE** `uq_ops_registers_store_codigo`: `store_id, codigo`
- FK `fk_ops_registers_store`: `store_id` -> `ops_stores`(`id`) ON DELETE RESTRICT

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `PRIMARY` | 1 | `id` | SI | BTREE |  |
| `uq_ops_registers_store_codigo` | 1 | `store_id` | SI | BTREE |  |
| `uq_ops_registers_store_codigo` | 2 | `codigo` | SI | BTREE |  |

### Referenciada por

- FK `fk_so_register` referenciada por `sales_orders`

## `ops_stores`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 16384.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `codigo` | `varchar(20)` | NO | NULL |  |  |
| 3 | `nombre` | `varchar(150)` | NO | NULL |  |  |
| 4 | `estado` | `enum('activa','inactiva')` | NO | activa |  |  |
| 5 | `created_at` | `datetime(6)` | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- **UNIQUE** `uq_ops_stores_codigo`: `codigo`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `PRIMARY` | 1 | `id` | SI | BTREE |  |
| `uq_ops_stores_codigo` | 1 | `codigo` | SI | BTREE |  |

### Referenciada por

- FK `fk_catalog_prices_store` referenciada por `catalog_prices`
- FK `fk_ctrl_balances_store` referenciada por `ctrl_balances`
- FK `fk_cle_store` referenciada por `ctrl_ledger_entries`
- FK `fk_inventory_alerts_store` referenciada por `inventory_alerts`
- FK `fk_inventory_incidents_store` referenciada por `inventory_incidents`
- FK `fk_im_store` referenciada por `inventory_movements`
- FK `fk_ir_store` referenciada por `inventory_reservations`
- FK `fk_inventory_stock_store` referenciada por `inventory_stock`
- FK `fk_it_destino` referenciada por `inventory_transfers`
- FK `fk_it_origen` referenciada por `inventory_transfers`
- FK `fk_ops_registers_store` referenciada por `ops_registers`
- FK `fk_so_store` referenciada por `sales_orders`
- FK `fk_sr_store` referenciada por `sales_returns`
- FK `fk_system_config_store` referenciada por `system_config`

## `outbox_events`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 0.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `agregado_tipo` | `varchar(50)` | NO | NULL |  |  |
| 3 | `agregado_id` | `bigint unsigned` | NO | NULL |  |  |
| 4 | `tipo_evento` | `varchar(100)` | NO | NULL |  |  |
| 5 | `payload` | `json` | NO | NULL |  |  |
| 6 | `estado` | `enum('pendiente','procesado','fallido')` | NO | pendiente |  |  |
| 7 | `created_at` | `datetime(6)` | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED |  |
| 8 | `procesado_at` | `datetime(6)` | YES | NULL |  |  |
| 9 | `intentos` | `int` | NO | 0 |  |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `ix_oe_estado_created` | 1 | `estado` | NO | BTREE |  |
| `ix_oe_estado_created` | 2 | `created_at` | NO | BTREE |  |
| `PRIMARY` | 1 | `id` | SI | BTREE |  |

## `payments_transactions`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 49152.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `order_id` | `bigint unsigned` | NO | NULL |  |  |
| 3 | `medio` | `varchar(50)` | NO | NULL |  |  |
| 4 | `proveedor` | `varchar(100)` | YES | NULL |  |  |
| 5 | `reference` | `varchar(255)` | YES | NULL |  |  |
| 6 | `amount` | `decimal(12,2)` | NO | NULL |  |  |
| 7 | `status` | `enum('pendiente','aprobado','rechazado','reembolsado')` | NO | NULL |  |  |
| 8 | `idempotency_key` | `varchar(255)` | YES | NULL |  |  |
| 9 | `created_at` | `datetime(6)` | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED |  |
| 10 | `actualizado_at` | `datetime(6)` | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED on update CURRENT_TIMESTAMP(6) |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- **UNIQUE** `uq_pt_idem`: `idempotency_key`
- **UNIQUE** `uq_pt_reference`: `reference`
- FK `fk_pt_order`: `order_id` -> `sales_orders`(`id`) ON DELETE RESTRICT
- **CHECK** `chk_pt_amount`: `(`amount` > 0)`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_pt_order` | 1 | `order_id` | NO | BTREE |  |
| `PRIMARY` | 1 | `id` | SI | BTREE |  |
| `uq_pt_idem` | 1 | `idempotency_key` | SI | BTREE |  |
| `uq_pt_reference` | 1 | `reference` | SI | BTREE |  |

## `purchase_order_items`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 32768.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `order_id` | `bigint unsigned` | NO | NULL |  |  |
| 3 | `product_id` | `bigint unsigned` | NO | NULL |  |  |
| 4 | `cantidad_pedida` | `int` | NO | NULL |  |  |
| 5 | `cantidad_recibida` | `int` | NO | 0 |  |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- FK `fk_poi_order`: `order_id` -> `purchase_orders`(`id`) ON DELETE RESTRICT
- FK `fk_poi_product`: `product_id` -> `catalog_products`(`id`) ON DELETE RESTRICT
- **CHECK** `chk_poi_pedida`: `(`cantidad_pedida` > 0)`
- **CHECK** `chk_poi_recibida`: `(`cantidad_recibida` >= 0)`
- **CHECK** `chk_poi_recibida_le_pedida`: `(`cantidad_recibida` <= `cantidad_pedida`)`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_poi_order` | 1 | `order_id` | NO | BTREE |  |
| `fk_poi_product` | 1 | `product_id` | NO | BTREE |  |
| `PRIMARY` | 1 | `id` | SI | BTREE |  |

## `purchase_orders`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 49152.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `numero` | `varchar(50)` | NO | NULL |  |  |
| 3 | `supplier_id` | `bigint unsigned` | NO | NULL |  |  |
| 4 | `estado` | `enum('borrador','emitida','recibida','cancelada')` | NO | borrador |  |  |
| 5 | `creado_por` | `bigint unsigned` | NO | NULL |  |  |
| 6 | `created_at` | `datetime(6)` | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED |  |
| 7 | `updated_at` | `datetime(6)` | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED on update CURRENT_TIMESTAMP(6) |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- **UNIQUE** `uq_purchase_orders_numero`: `numero`
- FK `fk_purchase_orders_supplier`: `supplier_id` -> `catalog_suppliers`(`id`) ON DELETE RESTRICT
- FK `fk_purchase_orders_user`: `creado_por` -> `auth_users`(`id`) ON DELETE RESTRICT

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_purchase_orders_supplier` | 1 | `supplier_id` | NO | BTREE |  |
| `fk_purchase_orders_user` | 1 | `creado_por` | NO | BTREE |  |
| `PRIMARY` | 1 | `id` | SI | BTREE |  |
| `uq_purchase_orders_numero` | 1 | `numero` | SI | BTREE |  |

### Referenciada por

- FK `fk_poi_order` referenciada por `purchase_order_items`
- FK `fk_purchase_receptions_order` referenciada por `purchase_receptions`

## `purchase_reception_items`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 32768.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `reception_id` | `bigint unsigned` | NO | NULL |  |  |
| 3 | `product_id` | `bigint unsigned` | NO | NULL |  |  |
| 4 | `numero_lote` | `varchar(50)` | NO | NULL |  |  |
| 5 | `fecha_vencimiento` | `date` | NO | NULL |  |  |
| 6 | `cantidad` | `int` | NO | NULL |  |  |
| 7 | `lot_id` | `bigint unsigned` | YES | NULL |  |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- FK `fk_pri_lot`: `lot_id` -> `inventory_lots`(`id`) ON DELETE RESTRICT
- FK `fk_pri_product`: `product_id` -> `catalog_products`(`id`) ON DELETE RESTRICT
- FK `fk_pri_reception`: `reception_id` -> `purchase_receptions`(`id`) ON DELETE RESTRICT
- **CHECK** `chk_pri_cantidad`: `(`cantidad` > 0)`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_pri_lot` | 1 | `lot_id` | NO | BTREE |  |
| `fk_pri_product` | 1 | `product_id` | NO | BTREE |  |
| `fk_pri_reception` | 1 | `reception_id` | NO | BTREE |  |
| `PRIMARY` | 1 | `id` | SI | BTREE |  |

### Referenciada por

- FK `fk_inventory_lots_reception` referenciada por `inventory_lots`

## `purchase_receptions`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 65536.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `order_id` | `bigint unsigned` | NO | NULL |  |  |
| 3 | `estado` | `enum('recibida','confirmada','rechazada')` | NO | recibida |  |  |
| 4 | `recepcionado_por` | `bigint unsigned` | NO | NULL |  |  |
| 5 | `confirmado_por` | `bigint unsigned` | YES | NULL |  |  |
| 6 | `created_at` | `datetime(6)` | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED |  |
| 7 | `confirmado_at` | `datetime(6)` | YES | NULL |  |  |
| 8 | `idempotency_key` | `varchar(255)` | YES | NULL |  |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- **UNIQUE** `uq_purchase_receptions_idem`: `idempotency_key`
- FK `fk_purchase_receptions_confirmacion`: `confirmado_por` -> `auth_users`(`id`) ON DELETE RESTRICT
- FK `fk_purchase_receptions_order`: `order_id` -> `purchase_orders`(`id`) ON DELETE RESTRICT
- FK `fk_purchase_receptions_recepcion`: `recepcionado_por` -> `auth_users`(`id`) ON DELETE RESTRICT

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_purchase_receptions_confirmacion` | 1 | `confirmado_por` | NO | BTREE |  |
| `fk_purchase_receptions_order` | 1 | `order_id` | NO | BTREE |  |
| `fk_purchase_receptions_recepcion` | 1 | `recepcionado_por` | NO | BTREE |  |
| `PRIMARY` | 1 | `id` | SI | BTREE |  |
| `uq_purchase_receptions_idem` | 1 | `idempotency_key` | SI | BTREE |  |

### Referenciada por

- FK `fk_pri_reception` referenciada por `purchase_reception_items`

## `rx_prescription_items`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 32768.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `prescription_id` | `bigint unsigned` | NO | NULL |  |  |
| 3 | `product_id` | `bigint unsigned` | NO | NULL |  |  |
| 4 | `cantidad_prescrita` | `int` | NO | NULL |  |  |
| 5 | `cantidad_dispensada` | `int` | NO | 0 |  |  |
| 6 | `version` | `bigint` | NO | 0 |  |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- FK `fk_rxpi_prescription`: `prescription_id` -> `rx_prescriptions`(`id`) ON DELETE RESTRICT
- FK `fk_rxpi_product`: `product_id` -> `catalog_products`(`id`) ON DELETE RESTRICT
- **CHECK** `chk_rxpi_prescrita`: `(`cantidad_prescrita` > 0)`
- **CHECK** `chk_rxpi_saldo`: `((`cantidad_dispensada` >= 0) and (`cantidad_dispensada` <= `cantidad_prescrita`))`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_rxpi_prescription` | 1 | `prescription_id` | NO | BTREE |  |
| `fk_rxpi_product` | 1 | `product_id` | NO | BTREE |  |
| `PRIMARY` | 1 | `id` | SI | BTREE |  |

### Referenciada por

- FK `fk_soi_rx_item` referenciada por `sales_order_items`

## `rx_prescriptions`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 32768.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `prescriber_id` | `bigint unsigned` | NO | NULL |  |  |
| 3 | `patient_id` | `bigint unsigned` | NO | NULL |  |  |
| 4 | `fecha` | `date` | NO | NULL |  |  |
| 5 | `numero_referencia` | `varchar(100)` | YES | NULL |  |  |
| 6 | `created_at` | `datetime(6)` | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- FK `fk_rx_prescriptions_patient`: `patient_id` -> `catalog_patients`(`id`) ON DELETE RESTRICT
- FK `fk_rx_prescriptions_prescriber`: `prescriber_id` -> `catalog_prescribers`(`id`) ON DELETE RESTRICT

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_rx_prescriptions_prescriber` | 1 | `prescriber_id` | NO | BTREE |  |
| `ix_rxp_patient_fecha` | 1 | `patient_id` | NO | BTREE |  |
| `ix_rxp_patient_fecha` | 2 | `fecha` | NO | BTREE |  |
| `PRIMARY` | 1 | `id` | SI | BTREE |  |

### Referenciada por

- FK `fk_audit_pii_prescription` referenciada por `audit_pii_access`
- FK `fk_rxpi_prescription` referenciada por `rx_prescription_items`

## `sales_order_items`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 65536.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `order_id` | `bigint unsigned` | NO | NULL |  |  |
| 3 | `lot_id` | `bigint unsigned` | NO | NULL |  |  |
| 4 | `product_id` | `bigint unsigned` | NO | NULL |  |  |
| 5 | `cantidad` | `int` | NO | NULL |  |  |
| 6 | `precio_unitario` | `decimal(12,2)` | NO | NULL |  |  |
| 7 | `subtotal` | `decimal(12,2)` | NO | NULL |  |  |
| 8 | `rx_item_id` | `bigint unsigned` | YES | NULL |  |  |
| 9 | `rx_item_id_key` | `bigint unsigned` | YES | NULL | STORED GENERATED |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- **UNIQUE** `uq_soi_orden_lote_rx`: `order_id, lot_id, rx_item_id_key`
- FK `fk_soi_lot`: `lot_id` -> `inventory_lots`(`id`) ON DELETE RESTRICT
- FK `fk_soi_order`: `order_id` -> `sales_orders`(`id`) ON DELETE RESTRICT
- FK `fk_soi_product`: `product_id` -> `catalog_products`(`id`) ON DELETE RESTRICT
- FK `fk_soi_rx_item`: `rx_item_id` -> `rx_prescription_items`(`id`) ON DELETE RESTRICT
- **CHECK** `chk_soi_cantidad`: `(`cantidad` > 0)`
- **CHECK** `chk_soi_precio`: `(`precio_unitario` >= 0)`
- **CHECK** `chk_soi_subtotal`: `(`subtotal` >= 0)`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_soi_lot` | 1 | `lot_id` | NO | BTREE |  |
| `fk_soi_product` | 1 | `product_id` | NO | BTREE |  |
| `fk_soi_rx_item` | 1 | `rx_item_id` | NO | BTREE |  |
| `PRIMARY` | 1 | `id` | SI | BTREE |  |
| `uq_soi_orden_lote_rx` | 1 | `order_id` | SI | BTREE |  |
| `uq_soi_orden_lote_rx` | 2 | `lot_id` | SI | BTREE |  |
| `uq_soi_orden_lote_rx` | 3 | `rx_item_id_key` | SI | BTREE |  |

### Referenciada por

- FK `fk_sri_order_item` referenciada por `sales_return_items`

## `sales_orders`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 114688.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `numero` | `varchar(50)` | NO | NULL |  |  |
| 3 | `channel` | `enum('pos')` | NO | pos |  |  |
| 4 | `store_id` | `bigint unsigned` | NO | NULL |  |  |
| 5 | `register_id` | `bigint unsigned` | NO | NULL |  |  |
| 6 | `paciente_id` | `bigint unsigned` | YES | NULL |  |  |
| 7 | `usuario_id` | `bigint unsigned` | NO | NULL |  |  |
| 8 | `quimico_verificador_id` | `bigint unsigned` | YES | NULL |  |  |
| 9 | `estado` | `enum('pendiente','pagada','anulada','devuelta')` | NO | pendiente |  |  |
| 10 | `total` | `decimal(12,2)` | NO | 0.00 |  |  |
| 11 | `idempotency_key` | `varchar(255)` | YES | NULL |  |  |
| 12 | `created_at` | `datetime(6)` | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- **UNIQUE** `uq_sales_orders_idem`: `idempotency_key`
- **UNIQUE** `uq_sales_orders_numero`: `numero`
- FK `fk_so_paciente`: `paciente_id` -> `catalog_patients`(`id`) ON DELETE RESTRICT
- FK `fk_so_quimico`: `quimico_verificador_id` -> `auth_users`(`id`) ON DELETE RESTRICT
- FK `fk_so_register`: `register_id` -> `ops_registers`(`id`) ON DELETE RESTRICT
- FK `fk_so_store`: `store_id` -> `ops_stores`(`id`) ON DELETE RESTRICT
- FK `fk_so_usuario`: `usuario_id` -> `auth_users`(`id`) ON DELETE RESTRICT
- **CHECK** `chk_so_devuelta_con_paciente`: `((`paciente_id` is not null) or (`estado` <> _utf8mb4\\'devuelta\\'))`
- **CHECK** `chk_so_total`: `(`total` >= 0)`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_so_quimico` | 1 | `quimico_verificador_id` | NO | BTREE |  |
| `fk_so_register` | 1 | `register_id` | NO | BTREE |  |
| `fk_so_usuario` | 1 | `usuario_id` | NO | BTREE |  |
| `ix_so_paciente_created` | 1 | `paciente_id` | NO | BTREE |  |
| `ix_so_paciente_created` | 2 | `created_at` | NO | BTREE |  |
| `ix_so_store_created` | 1 | `store_id` | NO | BTREE |  |
| `ix_so_store_created` | 2 | `created_at` | NO | BTREE |  |
| `PRIMARY` | 1 | `id` | SI | BTREE |  |
| `uq_sales_orders_idem` | 1 | `idempotency_key` | SI | BTREE |  |
| `uq_sales_orders_numero` | 1 | `numero` | SI | BTREE |  |

### Referenciada por

- FK `fk_ir_order` referenciada por `inventory_reservations`
- FK `fk_pt_order` referenciada por `payments_transactions`
- FK `fk_soi_order` referenciada por `sales_order_items`
- FK `fk_sr_order` referenciada por `sales_returns`

## `sales_return_items`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 65536.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `return_id` | `bigint unsigned` | NO | NULL |  |  |
| 3 | `order_item_id` | `bigint unsigned` | NO | NULL |  |  |
| 4 | `lot_id` | `bigint unsigned` | NO | NULL |  |  |
| 5 | `product_id` | `bigint unsigned` | NO | NULL |  |  |
| 6 | `cantidad` | `int` | NO | NULL |  |  |
| 7 | `condicion` | `enum('vendible','no_vendible')` | NO | no_vendible |  |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- **UNIQUE** `uq_sri_return_item`: `return_id, order_item_id`
- FK `fk_sri_lot`: `lot_id` -> `inventory_lots`(`id`) ON DELETE RESTRICT
- FK `fk_sri_order_item`: `order_item_id` -> `sales_order_items`(`id`) ON DELETE RESTRICT
- FK `fk_sri_product`: `product_id` -> `catalog_products`(`id`) ON DELETE RESTRICT
- FK `fk_sri_return`: `return_id` -> `sales_returns`(`id`) ON DELETE RESTRICT
- **CHECK** `chk_sri_cantidad`: `(`cantidad` > 0)`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_sri_lot` | 1 | `lot_id` | NO | BTREE |  |
| `fk_sri_order_item` | 1 | `order_item_id` | NO | BTREE |  |
| `fk_sri_product` | 1 | `product_id` | NO | BTREE |  |
| `PRIMARY` | 1 | `id` | SI | BTREE |  |
| `uq_sri_return_item` | 1 | `return_id` | SI | BTREE |  |
| `uq_sri_return_item` | 2 | `order_item_id` | SI | BTREE |  |

## `sales_returns`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 49152.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `order_id` | `bigint unsigned` | NO | NULL |  |  |
| 3 | `store_id` | `bigint unsigned` | NO | NULL |  |  |
| 4 | `usuario_id` | `bigint unsigned` | NO | NULL |  |  |
| 5 | `motivo` | `varchar(500)` | NO | NULL |  |  |
| 6 | `monto` | `decimal(12,2)` | NO | NULL |  |  |
| 7 | `estado_evaluacion` | `enum('pendiente','reingresado','descartado')` | NO | pendiente |  |  |
| 8 | `reembolso_estado` | `enum('none','pendiente','confirmado','fallido')` | NO | none |  |  |
| 9 | `created_at` | `datetime(6)` | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- FK `fk_sr_order`: `order_id` -> `sales_orders`(`id`) ON DELETE RESTRICT
- FK `fk_sr_store`: `store_id` -> `ops_stores`(`id`) ON DELETE RESTRICT
- FK `fk_sr_usuario`: `usuario_id` -> `auth_users`(`id`) ON DELETE RESTRICT
- **CHECK** `chk_sr_monto`: `(`monto` >= 0)`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_sr_order` | 1 | `order_id` | NO | BTREE |  |
| `fk_sr_store` | 1 | `store_id` | NO | BTREE |  |
| `fk_sr_usuario` | 1 | `usuario_id` | NO | BTREE |  |
| `PRIMARY` | 1 | `id` | SI | BTREE |  |

### Referenciada por

- FK `fk_sri_return` referenciada por `sales_return_items`

## `system_config`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 32768.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `config_key` | `varchar(100)` | NO | NULL |  |  |
| 2 | `config_value` | `varchar(4000)` | NO | NULL |  |  |
| 3 | `value_type` | `enum('texto','numero','booleano','json')` | NO | NULL |  |  |
| 4 | `store_id` | `bigint unsigned` | YES | NULL |  |  |
| 5 | `updated_at` | `datetime(6)` | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED on update CURRENT_TIMESTAMP(6) |  |
| 6 | `updated_by` | `bigint unsigned` | NO | NULL |  |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- FK `fk_system_config_store`: `store_id` -> `ops_stores`(`id`) ON DELETE RESTRICT
- FK `fk_system_config_user`: `updated_by` -> `auth_users`(`id`) ON DELETE RESTRICT
- **CHECK** `chk_system_config_alcance`: `((`store_id` is not null) or (not((`config_key` like _utf8mb4\\'store.%\\'))))`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `fk_system_config_store` | 1 | `store_id` | NO | BTREE |  |
| `fk_system_config_user` | 1 | `updated_by` | NO | BTREE |  |
| `PRIMARY` | 1 | `config_key` | SI | BTREE |  |

## `token_blacklist`

- Motor: InnoDB; collation: utf8mb4_0900_ai_ci; filas estimadas: 0; data_length: 16384; index_length: 32768.

### Columnas

| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |
|---:|---|---|---|---|---|---|
| 1 | `id` | `bigint unsigned` | NO | NULL | auto_increment |  |
| 2 | `token_hash` | `char(64)` | NO | NULL |  |  |
| 3 | `expires_at` | `datetime(6)` | NO | NULL |  |  |
| 4 | `created_at` | `datetime(6)` | NO | CURRENT_TIMESTAMP(6) | DEFAULT_GENERATED |  |

### Claves y restricciones

- **PK** `id, id, id, role_id, id, user_id, id, id, id, id, id, product_id, id, id, id, store_id, id, id, id, id, id, id, id, store_id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, id, config_key, id, permission_id, role_id, category_id, product_id, lot_id`
- **UNIQUE** `uq_token_blacklist_hash`: `token_hash`

### Indices

| Indice | # | Columna | Unico | Tipo | Comentario |
|---|---:|---|---|---|---|
| `ix_token_blacklist_expires` | 1 | `expires_at` | NO | BTREE |  |
| `PRIMARY` | 1 | `id` | SI | BTREE |  |
| `uq_token_blacklist_hash` | 1 | `token_hash` | SI | BTREE |  |
