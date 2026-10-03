-- =====================================================================
-- Flyway V1.2.0__grants.sql
-- Workflow 02 · Paso 15 · Usuarios y mínimo privilegio (Paso 09)
--
-- ⚠ SIN CREDENCIALES EN EL REPOSITORIO (RNF-034, AGENTS.md, §14).
--   Las contraseñas NO están en este archivo. Se inyectan como placeholders
--   de Flyway resueltos desde variables de entorno / Docker Secrets:
--
--     FLYWAY_PLACEHOLDER_APP_RW_PASSWORD=...
--     FLYWAY_PLACEHOLDER_APP_RO_PASSWORD=...
--     FLYWAY_PLACEHOLDER_MIGRATOR_PASSWORD=...
--     FLYWAY_PLACEHOLDER_SVC_CLINICO_PASSWORD=...
--     FLYWAY_PLACEHOLDER_DB_NAME=<nombre de la base>
--
--   Si no se definen, Flyway detiene la migración con
--   "Found non-resolvable placeholders" y NO se aplica nada.
--
--   `dba_admin` NO se crea aquí: su credencial se provisiona fuera del
--   repositorio (operación), nunca en un archivo versionado.
--
-- Fuente: 07_seguridad §1 (matriz de usuarios), §3, §4, §5.
--
-- ⚠ EJECUCIÓN: este script crea usuarios y concede privilegios, así que
--   necesita `CREATE USER` + `GRANT OPTION` a nivel global. `migrator` no los
--   tiene (07_seguridad §5: sin GRANT). Se aplica con la cuenta de
--   administración provisionada FUERA del repositorio (`dba_admin`), no con
--   `migrator`. Las migraciones puramente DDL (V1.0.0, V1.1.0, V1.4.0) sí
--   corren con `migrator`.
--
-- RECONCILIACIÓN F11 (ver 15_reportes/revision_dba.md):
--   07_seguridad §1 prohibía UPDATE sobre las transaccionales. Eso entraría
--   en conflicto con las transacciones aprobadas T-1 (UPDATE sales_orders.total
--   y estado) y T-2b (UPDATE payments_transactions.status) de
--   11_transacciones_concurrencia §1. Prevalence: la prohibición dura es
--   DELETE (RNF-023: los registros confirmados no se eliminan); las tablas
--   append-only conservan además SELECT+INSERT únicamente.
-- =====================================================================

-- ---------------------------------------------------------------------
-- app_rw · backend (SELECT/INSERT/UPDATE de negocio; sin DELETE)
-- ---------------------------------------------------------------------
CREATE USER IF NOT EXISTS 'app_rw'@'%' IDENTIFIED BY '${app_rw_password}';

-- Append-only (RNF-023 / RNF-046): sin UPDATE, sin DELETE.
GRANT SELECT, INSERT ON `${db_name}`.audit_operations     TO 'app_rw'@'%';
GRANT SELECT, INSERT ON `${db_name}`.audit_pii_access     TO 'app_rw'@'%';
GRANT SELECT, INSERT ON `${db_name}`.inventory_movements  TO 'app_rw'@'%';
GRANT SELECT, INSERT ON `${db_name}`.ctrl_ledger_entries  TO 'app_rw'@'%';

-- Gestión de purga (§6): única excepción de DELETE autorizada.
GRANT SELECT, INSERT, UPDATE, DELETE ON `${db_name}`.idempotency_keys TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON `${db_name}`.outbox_events     TO 'app_rw'@'%';

-- auth_
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.auth_roles              TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.auth_permissions        TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.auth_role_permissions   TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.auth_users              TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.auth_user_roles         TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.token_blacklist         TO 'app_rw'@'%';

-- ops_
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.ops_stores              TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.ops_registers           TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.system_config           TO 'app_rw'@'%';

-- catalog_
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.catalog_categories      TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.catalog_products        TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.catalog_product_categories TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.catalog_prices          TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.catalog_promotions      TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.catalog_suppliers       TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.catalog_patients        TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.catalog_prescribers     TO 'app_rw'@'%';

-- purchase_
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.purchase_orders         TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.purchase_order_items    TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.purchase_receptions     TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.purchase_reception_items TO 'app_rw'@'%';

-- inventory_
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.inventory_lots          TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.inventory_stock         TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.inventory_transfers     TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.inventory_transfer_items TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.inventory_reservations  TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.inventory_alerts        TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.inventory_incidents     TO 'app_rw'@'%';

-- rx_
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.rx_prescriptions        TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.rx_prescription_items   TO 'app_rw'@'%';

-- sales_
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.sales_orders            TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.sales_order_items       TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.payments_transactions   TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.sales_returns           TO 'app_rw'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.sales_return_items      TO 'app_rw'@'%';

-- ctrl_
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.ctrl_balances           TO 'app_rw'@'%';

-- ---------------------------------------------------------------------
-- svc_clinico · acceso a PII (RN-13, RNF-038, RF-091)
-- Único rol con SELECT sobre pacientes/prescriptores/recetas.
-- Debe poder INSERTar en audit_pii_access: sin esa fila el acceso es ilegal.
-- ---------------------------------------------------------------------
CREATE USER IF NOT EXISTS 'svc_clinico'@'%' IDENTIFIED BY '${svc_clinico_password}';

GRANT SELECT, INSERT, UPDATE ON `${db_name}`.catalog_patients    TO 'svc_clinico'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.catalog_prescribers TO 'svc_clinico'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.rx_prescriptions    TO 'svc_clinico'@'%';
GRANT SELECT, INSERT, UPDATE ON `${db_name}`.rx_prescription_items TO 'svc_clinico'@'%';
GRANT SELECT, INSERT        ON `${db_name}`.audit_pii_access     TO 'svc_clinico'@'%';

-- ---------------------------------------------------------------------
-- app_ro · reportes
-- SIN SELECT sobre: auth_users (password_hash), catalog_patients,
-- catalog_prescribers, rx_prescriptions, rx_prescription_items, audit_pii_access
-- (07_seguridad §3 y §4). Lista sujeta a cierre con DB-P07 (tabla resumen).
-- ---------------------------------------------------------------------
CREATE USER IF NOT EXISTS 'app_ro'@'%' IDENTIFIED BY '${app_ro_password}';

GRANT SELECT ON `${db_name}`.ops_stores                 TO 'app_ro'@'%';
GRANT SELECT ON `${db_name}`.ops_registers              TO 'app_ro'@'%';
GRANT SELECT ON `${db_name}`.catalog_products           TO 'app_ro'@'%';
GRANT SELECT ON `${db_name}`.catalog_categories         TO 'app_ro'@'%';
GRANT SELECT ON `${db_name}`.catalog_product_categories TO 'app_ro'@'%';
GRANT SELECT ON `${db_name}`.catalog_prices             TO 'app_ro'@'%';
GRANT SELECT ON `${db_name}`.catalog_promotions         TO 'app_ro'@'%';
GRANT SELECT ON `${db_name}`.catalog_suppliers          TO 'app_ro'@'%';
GRANT SELECT ON `${db_name}`.purchase_orders            TO 'app_ro'@'%';
GRANT SELECT ON `${db_name}`.purchase_order_items       TO 'app_ro'@'%';
GRANT SELECT ON `${db_name}`.purchase_receptions        TO 'app_ro'@'%';
GRANT SELECT ON `${db_name}`.purchase_reception_items   TO 'app_ro'@'%';
GRANT SELECT ON `${db_name}`.inventory_lots             TO 'app_ro'@'%';
GRANT SELECT ON `${db_name}`.inventory_stock            TO 'app_ro'@'%';
GRANT SELECT ON `${db_name}`.inventory_movements        TO 'app_ro'@'%';
GRANT SELECT ON `${db_name}`.inventory_transfers        TO 'app_ro'@'%';
GRANT SELECT ON `${db_name}`.inventory_transfer_items   TO 'app_ro'@'%';
GRANT SELECT ON `${db_name}`.inventory_alerts           TO 'app_ro'@'%';
GRANT SELECT ON `${db_name}`.inventory_incidents        TO 'app_ro'@'%';
GRANT SELECT ON `${db_name}`.sales_orders               TO 'app_ro'@'%';
GRANT SELECT ON `${db_name}`.sales_order_items          TO 'app_ro'@'%';
GRANT SELECT ON `${db_name}`.payments_transactions      TO 'app_ro'@'%';
GRANT SELECT ON `${db_name}`.sales_returns              TO 'app_ro'@'%';
GRANT SELECT ON `${db_name}`.sales_return_items         TO 'app_ro'@'%';
GRANT SELECT ON `${db_name}`.ctrl_ledger_entries        TO 'app_ro'@'%';
GRANT SELECT ON `${db_name}`.ctrl_balances              TO 'app_ro'@'%';

-- ---------------------------------------------------------------------
-- migrator · DDL, solo en ventana de migración (RNF-031, separación de funciones)
-- Solo privilegios de esquema: CREATE/ALTER/INDEX/DROP/TRIGGER/REFERENCES.
-- Sin SELECT ni DML -> no puede leer PII (07_seguridad §5) ni borrar datos
-- por privilegio. Sin GRANT OPTION (no puede conceder privilegios).
-- ---------------------------------------------------------------------
CREATE USER IF NOT EXISTS 'migrator'@'%' IDENTIFIED BY '${migrator_password}';
GRANT CREATE, ALTER, DROP, INDEX, TRIGGER, REFERENCES
      ON `${db_name}`.* TO 'migrator'@'%';

-- ---------------------------------------------------------------------
-- dba_admin · NO se crea aquí. Provisionar fuera del repositorio.
-- ---------------------------------------------------------------------
