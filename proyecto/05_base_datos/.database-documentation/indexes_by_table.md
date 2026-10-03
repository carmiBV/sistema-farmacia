# Indices por tabla (live) - farmacia_doc

- Fuente: `information_schema.statistics` (127.0.0.1:33061), 2026-10-03 03:15:00. Tier **T2**.
- 149 grupos de indice (tabla + nombre de indice) en 42 tablas.


## `audit_operations`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_audit_operations_user` | `usuario_id` | NO | BTREE |  |
| `ix_ao_entidad_created` | `entidad`, `entidad_id`, `created_at` | NO | BTREE |  |
| `PRIMARY` | `id` | SI | BTREE |  |

## `audit_pii_access`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_audit_pii_user` | `usuario_id` | NO | BTREE |  |
| `ix_apii_paciente_created` | `paciente_id`, `created_at` | NO | BTREE |  |
| `ix_apii_prescription_created` | `prescription_id`, `created_at` | NO | BTREE |  |
| `PRIMARY` | `id` | SI | BTREE |  |

## `auth_permissions`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `PRIMARY` | `id` | SI | BTREE |  |
| `uq_auth_permissions_clave` | `clave` | SI | BTREE |  |

## `auth_role_permissions`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_arp_permission` | `permission_id` | NO | BTREE |  |
| `PRIMARY` | `role_id`, `permission_id` | SI | BTREE |  |

## `auth_roles`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `PRIMARY` | `id` | SI | BTREE |  |
| `uq_auth_roles_nombre` | `nombre` | SI | BTREE |  |

## `auth_user_roles`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_aur_role` | `role_id` | NO | BTREE |  |
| `PRIMARY` | `user_id`, `role_id` | SI | BTREE |  |

## `auth_users`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `PRIMARY` | `id` | SI | BTREE |  |
| `uq_auth_users_usuario` | `usuario` | SI | BTREE |  |

## `catalog_categories`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_catalog_categories_parent` | `parent_id` | NO | BTREE |  |
| `PRIMARY` | `id` | SI | BTREE |  |
| `uq_catalog_categories_nombre_parent` | `nombre`, `parent_id_key` | SI | BTREE |  |

## `catalog_patients`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `PRIMARY` | `id` | SI | BTREE |  |
| `uq_catalog_patients_identificacion` | `identificacion` | SI | BTREE |  |

## `catalog_prescribers`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `PRIMARY` | `id` | SI | BTREE |  |
| `uq_catalog_prescribers_identificacion` | `identificacion` | SI | BTREE |  |

## `catalog_prices`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_catalog_prices_store` | `store_id` | NO | BTREE |  |
| `PRIMARY` | `id` | SI | BTREE |  |
| `uq_catalog_prices` | `product_id`, `store_id_key`, `vigente_desde` | SI | BTREE |  |

## `catalog_product_categories`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_cpc_category` | `category_id` | NO | BTREE |  |
| `PRIMARY` | `product_id`, `category_id` | SI | BTREE |  |

## `catalog_products`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `PRIMARY` | `id` | SI | BTREE |  |
| `uq_catalog_products_sku` | `sku` | SI | BTREE |  |

## `catalog_promotions`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_catalog_promotions_category` | `category_id` | NO | BTREE |  |
| `fk_catalog_promotions_product` | `product_id` | NO | BTREE |  |
| `PRIMARY` | `id` | SI | BTREE |  |

## `catalog_suppliers`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `PRIMARY` | `id` | SI | BTREE |  |
| `uq_catalog_suppliers_identificacion` | `identificacion` | SI | BTREE |  |

## `ctrl_balances`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_ctrl_balances_product` | `product_id` | NO | BTREE |  |
| `PRIMARY` | `store_id`, `product_id` | SI | BTREE |  |

## `ctrl_ledger_entries`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_cle_autorizador` | `autorizador_id` | NO | BTREE |  |
| `fk_cle_product` | `product_id` | NO | BTREE |  |
| `fk_cle_usuario` | `usuario_id` | NO | BTREE |  |
| `ix_cle_ref` | `ref_tipo`, `ref_id` | NO | BTREE |  |
| `ix_cle_store_product_created` | `store_id`, `product_id`, `created_at` | NO | BTREE |  |
| `PRIMARY` | `id` | SI | BTREE |  |

## `idempotency_keys`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `ix_ik_expires` | `expires_at` | NO | BTREE |  |
| `PRIMARY` | `id` | SI | BTREE |  |
| `uq_idempotency_keys_key` | `idempotency_key` | SI | BTREE |  |

## `inventory_alerts`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_inventory_alerts_product` | `product_id` | NO | BTREE |  |
| `fk_inventory_alerts_resuelta` | `resuelta_por` | NO | BTREE |  |
| `fk_inventory_alerts_store` | `store_id` | NO | BTREE |  |
| `ix_ia_tipo_estado_fecha` | `tipo`, `estado`, `fecha_generada` | NO | BTREE |  |
| `PRIMARY` | `id` | SI | BTREE |  |
| `uq_inventory_alerts` | `tipo`, `product_id`, `store_id_key`, `fecha_generada` | SI | BTREE |  |

## `inventory_incidents`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_inventory_incidents_lot` | `lot_id` | NO | BTREE |  |
| `fk_inventory_incidents_mov` | `movimiento_ajuste_id` | NO | BTREE |  |
| `fk_inventory_incidents_user` | `abierto_por` | NO | BTREE |  |
| `ix_ii_store_estado` | `store_id`, `estado` | NO | BTREE |  |
| `PRIMARY` | `id` | SI | BTREE |  |

## `inventory_lots`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_inventory_lots_liberado_por` | `liberado_por` | NO | BTREE |  |
| `ix_inventory_lots_fefo` | `product_id`, `estado`, `fecha_vencimiento` | NO | BTREE |  |
| `PRIMARY` | `id` | SI | BTREE |  |
| `uq_inventory_lots_producto_lote` | `product_id`, `numero_lote` | SI | BTREE |  |
| `uq_inventory_lots_reception` | `reception_item_id` | SI | BTREE |  |

## `inventory_movements`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_im_autorizador` | `autorizador_id` | NO | BTREE |  |
| `fk_im_product` | `product_id` | NO | BTREE |  |
| `fk_im_proponente` | `proponente_id` | NO | BTREE |  |
| `fk_im_ref` | `movimiento_ref_id` | NO | BTREE |  |
| `fk_im_usuario` | `usuario_id` | NO | BTREE |  |
| `ix_im_lot_created` | `lot_id`, `created_at` | NO | BTREE |  |
| `ix_im_ref` | `ref_tipo`, `ref_id` | NO | BTREE |  |
| `ix_im_store_created` | `store_id`, `created_at` | NO | BTREE |  |
| `PRIMARY` | `id` | SI | BTREE |  |
| `uq_im_idem` | `idempotency_key` | SI | BTREE |  |

## `inventory_reservations`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_ir_order` | `order_id` | NO | BTREE |  |
| `fk_ir_product` | `product_id` | NO | BTREE |  |
| `ix_ir_store_product_status_expires` | `store_id`, `product_id`, `status`, `expires_at` | NO | BTREE |  |
| `PRIMARY` | `id` | SI | BTREE |  |

## `inventory_stock`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `ix_inventory_stock_lot_store` | `lot_id`, `store_id` | NO | BTREE |  |
| `PRIMARY` | `store_id`, `lot_id` | SI | BTREE |  |

## `inventory_transfer_items`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_iti_lot` | `lot_id` | NO | BTREE |  |
| `PRIMARY` | `id` | SI | BTREE |  |
| `uq_iti_transfer_lote` | `transfer_id`, `lot_id` | SI | BTREE |  |

## `inventory_transfers`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_it_despachado` | `despachado_por` | NO | BTREE |  |
| `fk_it_recibido` | `recibido_por` | NO | BTREE |  |
| `fk_it_solicitado` | `solicitado_por` | NO | BTREE |  |
| `ix_it_destino_estado` | `store_destino_id`, `estado` | NO | BTREE |  |
| `ix_it_origen_estado` | `store_origen_id`, `estado` | NO | BTREE |  |
| `PRIMARY` | `id` | SI | BTREE |  |
| `uq_it_idem` | `idempotency_key` | SI | BTREE |  |

## `ops_registers`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `PRIMARY` | `id` | SI | BTREE |  |
| `uq_ops_registers_store_codigo` | `store_id`, `codigo` | SI | BTREE |  |

## `ops_stores`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `PRIMARY` | `id` | SI | BTREE |  |
| `uq_ops_stores_codigo` | `codigo` | SI | BTREE |  |

## `outbox_events`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `ix_oe_estado_created` | `estado`, `created_at` | NO | BTREE |  |
| `PRIMARY` | `id` | SI | BTREE |  |

## `payments_transactions`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_pt_order` | `order_id` | NO | BTREE |  |
| `PRIMARY` | `id` | SI | BTREE |  |
| `uq_pt_idem` | `idempotency_key` | SI | BTREE |  |
| `uq_pt_reference` | `reference` | SI | BTREE |  |

## `purchase_order_items`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_poi_order` | `order_id` | NO | BTREE |  |
| `fk_poi_product` | `product_id` | NO | BTREE |  |
| `PRIMARY` | `id` | SI | BTREE |  |

## `purchase_orders`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_purchase_orders_supplier` | `supplier_id` | NO | BTREE |  |
| `fk_purchase_orders_user` | `creado_por` | NO | BTREE |  |
| `PRIMARY` | `id` | SI | BTREE |  |
| `uq_purchase_orders_numero` | `numero` | SI | BTREE |  |

## `purchase_reception_items`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_pri_lot` | `lot_id` | NO | BTREE |  |
| `fk_pri_product` | `product_id` | NO | BTREE |  |
| `fk_pri_reception` | `reception_id` | NO | BTREE |  |
| `PRIMARY` | `id` | SI | BTREE |  |

## `purchase_receptions`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_purchase_receptions_confirmacion` | `confirmado_por` | NO | BTREE |  |
| `fk_purchase_receptions_order` | `order_id` | NO | BTREE |  |
| `fk_purchase_receptions_recepcion` | `recepcionado_por` | NO | BTREE |  |
| `PRIMARY` | `id` | SI | BTREE |  |
| `uq_purchase_receptions_idem` | `idempotency_key` | SI | BTREE |  |

## `rx_prescription_items`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_rxpi_prescription` | `prescription_id` | NO | BTREE |  |
| `fk_rxpi_product` | `product_id` | NO | BTREE |  |
| `PRIMARY` | `id` | SI | BTREE |  |

## `rx_prescriptions`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_rx_prescriptions_prescriber` | `prescriber_id` | NO | BTREE |  |
| `ix_rxp_patient_fecha` | `patient_id`, `fecha` | NO | BTREE |  |
| `PRIMARY` | `id` | SI | BTREE |  |

## `sales_order_items`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_soi_lot` | `lot_id` | NO | BTREE |  |
| `fk_soi_product` | `product_id` | NO | BTREE |  |
| `fk_soi_rx_item` | `rx_item_id` | NO | BTREE |  |
| `PRIMARY` | `id` | SI | BTREE |  |
| `uq_soi_orden_lote_rx` | `order_id`, `lot_id`, `rx_item_id_key` | SI | BTREE |  |

## `sales_orders`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_so_quimico` | `quimico_verificador_id` | NO | BTREE |  |
| `fk_so_register` | `register_id` | NO | BTREE |  |
| `fk_so_usuario` | `usuario_id` | NO | BTREE |  |
| `ix_so_paciente_created` | `paciente_id`, `created_at` | NO | BTREE |  |
| `ix_so_store_created` | `store_id`, `created_at` | NO | BTREE |  |
| `PRIMARY` | `id` | SI | BTREE |  |
| `uq_sales_orders_idem` | `idempotency_key` | SI | BTREE |  |
| `uq_sales_orders_numero` | `numero` | SI | BTREE |  |

## `sales_return_items`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_sri_lot` | `lot_id` | NO | BTREE |  |
| `fk_sri_order_item` | `order_item_id` | NO | BTREE |  |
| `fk_sri_product` | `product_id` | NO | BTREE |  |
| `PRIMARY` | `id` | SI | BTREE |  |
| `uq_sri_return_item` | `return_id`, `order_item_id` | SI | BTREE |  |

## `sales_returns`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_sr_order` | `order_id` | NO | BTREE |  |
| `fk_sr_store` | `store_id` | NO | BTREE |  |
| `fk_sr_usuario` | `usuario_id` | NO | BTREE |  |
| `PRIMARY` | `id` | SI | BTREE |  |

## `system_config`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `fk_system_config_store` | `store_id` | NO | BTREE |  |
| `fk_system_config_user` | `updated_by` | NO | BTREE |  |
| `PRIMARY` | `config_key` | SI | BTREE |  |

## `token_blacklist`

| Indice | Columnas | Unico | Tipo | Comentario |
|---|---|---|---|---|
| `ix_token_blacklist_expires` | `expires_at` | NO | BTREE |  |
| `PRIMARY` | `id` | SI | BTREE |  |
| `uq_token_blacklist_hash` | `token_hash` | SI | BTREE |  |
