-- =====================================================================
-- Flyway V1.1.0__indices.sql
-- Workflow 02 · Paso 15 · Índices de acceso (Paso 11: I-01…I-23)
--
-- Regla §12: ningún índice sin consulta/patrón de acceso que lo justifique.
-- InnoDB indexa automáticamente cada columna FK -> NO se duplican aquí.
--
-- Cobertura de I-01…I-23:
--   * Se crean en este script: I-01, I-03, I-04, I-05, I-07, I-08, I-11,
--     I-12, I-13, I-14, I-15a, I-15b, I-16a, I-16b, I-17, I-18, I-19,
--     I-20, I-23.
--   * I-02  -> creado en V1.0.0 como ix_inventory_stock_lot_store
--              (también satisface el índice que InnoDB exige para la FK lot_id).
--   * I-21  -> creado en V1.0.0 como ix_token_blacklist_expires.
--   * I-06, I-09, I-10, I-22 -> satisfechos por el índice automático de la FK
--              (sales_order_items.lot_id, sales_returns.order_id,
--               payments_transactions.order_id, purchase_receptions.order_id).
--              Duplicarlos solo cuesta escritura (§12).
--
-- ALGORITHM=INPLACE, LOCK=NONE: añadir índice no bloquea lecturas/escrituras
-- en InnoDB (Paso 13 §1 F2 / versionamiento §2.4).
-- =====================================================================

-- I-01 · FEFO: igualdad antes que rango/orden (RF-043, RN-03, CA-03)
--   WHERE product_id=? AND estado='liberado' ORDER BY fecha_vencimiento
ALTER TABLE inventory_lots
  ADD INDEX ix_inventory_lots_fefo (product_id, estado, fecha_vencimiento),
  ALGORITHM=INPLACE, LOCK=NONE;

-- I-03 · trazabilidad por lote (CA-10, RNF-045, RNF-024)
ALTER TABLE inventory_movements
  ADD INDEX ix_im_lot_created (lot_id, created_at),
  ALGORITHM=INPLACE, LOCK=NONE;

-- I-04 · movimientos por sucursal/turno (RF-090)
ALTER TABLE inventory_movements
  ADD INDEX ix_im_store_created (store_id, created_at),
  ALGORITHM=INPLACE, LOCK=NONE;

-- I-05 · reversa: dada una venta/recepción, ver sus movimientos (CA-10)
ALTER TABLE inventory_movements
  ADD INDEX ix_im_ref (ref_tipo, ref_id),
  ALGORITHM=INPLACE, LOCK=NONE;

-- I-07 · reportes de ventas por sucursal/fecha (RF-070)
ALTER TABLE sales_orders
  ADD INDEX ix_so_store_created (store_id, created_at),
  ALGORITHM=INPLACE, LOCK=NONE;

-- I-08 · historial de compras del paciente (RNF-048)
--   (paciente_id, created_at) — el índice automático de la FK solo cubre
--   paciente_id; el compuesto es el que responde a la consulta con ORDER BY.
ALTER TABLE sales_orders
  ADD INDEX ix_so_paciente_created (paciente_id, created_at),
  ALGORITHM=INPLACE, LOCK=NONE;

-- I-11 · recetas del paciente (RF-052, RN-13)
ALTER TABLE rx_prescriptions
  ADD INDEX ix_rxp_patient_fecha (patient_id, fecha),
  ALGORITHM=INPLACE, LOCK=NONE;

-- I-12 · saldo por período / reporte regulatorio (RF-055, RF-071)
ALTER TABLE ctrl_ledger_entries
  ADD INDEX ix_cle_store_product_created (store_id, product_id, created_at),
  ALGORITHM=INPLACE, LOCK=NONE;

-- I-13 · dado un venta/transferencia, ver asientos (CA-10)
ALTER TABLE ctrl_ledger_entries
  ADD INDEX ix_cle_ref (ref_tipo, ref_id),
  ALGORITHM=INPLACE, LOCK=NONE;

-- I-14 · auditoría de una entidad (RF-090)
ALTER TABLE audit_operations
  ADD INDEX ix_ao_entidad_created (entidad, entidad_id, created_at),
  ALGORITHM=INPLACE, LOCK=NONE;

-- I-15 · RF-091 por persona y por receta (RN-13)
ALTER TABLE audit_pii_access
  ADD INDEX ix_apii_paciente_created (paciente_id, created_at),
  ALGORITHM=INPLACE, LOCK=NONE;

ALTER TABLE audit_pii_access
  ADD INDEX ix_apii_prescription_created (prescription_id, created_at),
  ALGORITHM=INPLACE, LOCK=NONE;

-- I-16 · bandeja de transferencias pendientes (RF-045, RN-08)
ALTER TABLE inventory_transfers
  ADD INDEX ix_it_origen_estado (store_origen_id, estado),
  ALGORITHM=INPLACE, LOCK=NONE;

ALTER TABLE inventory_transfers
  ADD INDEX ix_it_destino_estado (store_destino_id, estado),
  ALGORITHM=INPLACE, LOCK=NONE;

-- I-17 · incidentes abiertos por sucursal (RF-047)
ALTER TABLE inventory_incidents
  ADD INDEX ix_ii_store_estado (store_id, estado),
  ALGORITHM=INPLACE, LOCK=NONE;

-- I-18 · panel de alertas (RF-044)
ALTER TABLE inventory_alerts
  ADD INDEX ix_ia_tipo_estado_fecha (tipo, estado, fecha_generada),
  ALGORITHM=INPLACE, LOCK=NONE;

-- I-19 · worker: eventos pendientes (§16)
ALTER TABLE outbox_events
  ADD INDEX ix_oe_estado_created (estado, created_at),
  ALGORITHM=INPLACE, LOCK=NONE;

-- I-20 · purga por retención de claves de idempotencia (§6, plazo pendiente)
ALTER TABLE idempotency_keys
  ADD INDEX ix_ik_expires (expires_at),
  ALGORITHM=INPLACE, LOCK=NONE;

-- I-23 · job de expiración de reservas + consulta (§4, DB-P02)
ALTER TABLE inventory_reservations
  ADD INDEX ix_ir_store_product_status_expires (store_id, product_id, status, expires_at),
  ALGORITHM=INPLACE, LOCK=NONE;
