-- =====================================================================
-- Flyway V1.0.0__esquema_base.sql
-- Workflow 02 · Paso 15 · Sistema de gestión de farmacia
-- Motor: MySQL 8.4 LTS (mínimo 8.0.16 por CHECK enforced) / InnoDB
-- Fuente: 03_modelo_logico (columnas) + 05_modelo_fisico (tipos/ON DELETE)
--         + 06_integridad (PK/FK/UNIQUE/CHECK) + 07_seguridad (grants aparte)
-- Aprobación: 15_reportes/revision_dba.md -> STATUS: APPROVED (Paso 14)
--              ADR-001 Aceptado · 08_final_recommendation Aprobado (CAM-006)
--
-- REGLAS DE ESTE SCRIPT
--   * Sin credenciales ni secretos (RNF-034). Los usuarios van en V1.2.0
--     con placeholders de Flyway.
--   * Sin DROP/TRUNCATE (workflow §17).
--   * ON DELETE: RESTRICT en todo salvo las 3 asociativas de catálogo
--     (06_integridad §1). Ver README §"Reconciliaciones".
--   * Timestamps en DATETIME(6) UTC, nunca TIMESTAMP (límite 2038).
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1 · auth_  (RF-001, RNF-030..033)
-- ---------------------------------------------------------------------

CREATE TABLE auth_roles (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(100) NOT NULL,
  descripcion VARCHAR(255) NULL,
  CONSTRAINT uq_auth_roles_nombre UNIQUE (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE auth_permissions (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clave       VARCHAR(100) NOT NULL,
  descripcion VARCHAR(255) NULL,
  CONSTRAINT uq_auth_permissions_clave UNIQUE (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE auth_role_permissions (
  role_id       BIGINT UNSIGNED NOT NULL,
  permission_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_arp_role FOREIGN KEY (role_id)
    REFERENCES auth_roles (id) ON DELETE CASCADE,
  CONSTRAINT fk_arp_permission FOREIGN KEY (permission_id)
    REFERENCES auth_permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE auth_users (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario          VARCHAR(100) NOT NULL,
  password_hash    VARCHAR(255) NOT NULL,
  estado           ENUM('activo','bloqueado','inactivo') NOT NULL DEFAULT 'activo',
  mfa_habilitado   TINYINT(1)   NOT NULL DEFAULT 0,
  ultimo_acceso_at DATETIME(6)  NULL,
  created_at       DATETIME(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT uq_auth_users_usuario UNIQUE (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE auth_user_roles (
  user_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, role_id),
  CONSTRAINT fk_aur_user FOREIGN KEY (user_id)
    REFERENCES auth_users (id) ON DELETE CASCADE,
  CONSTRAINT fk_aur_role FOREIGN KEY (role_id)
    REFERENCES auth_roles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Hash SHA-256 del token, nunca el token en claro (§15)
CREATE TABLE token_blacklist (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token_hash CHAR(64)     NOT NULL,
  expires_at DATETIME(6)  NOT NULL,
  created_at DATETIME(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT uq_token_blacklist_hash UNIQUE (token_hash),
  KEY ix_token_blacklist_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- 2 · ops_  (RF-010)
-- ---------------------------------------------------------------------

CREATE TABLE ops_stores (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo     VARCHAR(20) NOT NULL,
  nombre     VARCHAR(150) NOT NULL,
  estado     ENUM('activa','inactiva') NOT NULL DEFAULT 'activa',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT uq_ops_stores_codigo UNIQUE (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE ops_registers (
  id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id BIGINT UNSIGNED NOT NULL,
  codigo   VARCHAR(20) NOT NULL,
  estado   ENUM('activa','inactiva') NOT NULL DEFAULT 'activa',
  CONSTRAINT uq_ops_registers_store_codigo UNIQUE (store_id, codigo),
  CONSTRAINT fk_ops_registers_store FOREIGN KEY (store_id)
    REFERENCES ops_stores (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- RF-100 · §21. Convención de claves: parámetro por sucursal = 'store.<clave>';
-- globales sin prefijo. Los valores con pendiente del cliente NO se insertan
-- hasta que RF-100 reciba valores aprobados (V1.3.0 y posteriores).
CREATE TABLE system_config (
  config_key   VARCHAR(100) NOT NULL,
  config_value VARCHAR(4000) NOT NULL,
  value_type   ENUM('texto','numero','booleano','json') NOT NULL,
  store_id     BIGINT UNSIGNED NULL,
  updated_at   DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
                                      ON UPDATE CURRENT_TIMESTAMP(6),
  updated_by   BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (config_key),
  CONSTRAINT chk_system_config_alcance CHECK (
    store_id IS NOT NULL OR config_key NOT LIKE 'store.%'
  ),
  CONSTRAINT fk_system_config_store FOREIGN KEY (store_id)
    REFERENCES ops_stores (id) ON DELETE RESTRICT,
  CONSTRAINT fk_system_config_user FOREIGN KEY (updated_by)
    REFERENCES auth_users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- 3 · catalog_  (RF-002/003/020/030, RN-13)
-- ---------------------------------------------------------------------

CREATE TABLE catalog_categories (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(150) NOT NULL,
  parent_id     BIGINT UNSIGNED NULL,
  -- F14: parent_id_key evita dos categorías RAÍZ con el mismo nombre
  --      (MySQL trata NULLs como distintos en UNIQUE). Patrón F3/F12.
  parent_id_key BIGINT UNSIGNED GENERATED ALWAYS AS (IFNULL(parent_id, 0)) STORED,
  CONSTRAINT uq_catalog_categories_nombre_parent UNIQUE (nombre, parent_id_key),
  CONSTRAINT fk_catalog_categories_parent FOREIGN KEY (parent_id)
    REFERENCES catalog_categories (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE catalog_products (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sku              VARCHAR(50) NOT NULL,
  nombre           VARCHAR(255) NOT NULL,
  principio_activo VARCHAR(255) NOT NULL,
  presentacion     VARCHAR(100) NOT NULL,
  concentracion    VARCHAR(100) NOT NULL,
  condicion_venta  ENUM('libre','receta','controlado') NOT NULL,
  estado           ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  created_at       DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT uq_catalog_products_sku UNIQUE (sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE catalog_product_categories (
  product_id  BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (product_id, category_id),
  CONSTRAINT fk_cpc_product FOREIGN KEY (product_id)
    REFERENCES catalog_products (id) ON DELETE CASCADE,
  CONSTRAINT fk_cpc_category FOREIGN KEY (category_id)
    REFERENCES catalog_categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- DB-P09: store_id NULL = precio global (pendiente de confirmación).
-- F12 CERRADO: store_id_key hace efectivo el UNIQUE aunque store_id sea NULL
--     (MySQL trata NULLs como distintos en UNIQUE). Patrón F3; store_id_key no
--     colisiona con ids reales (AUTO_INCREMENT inicia en 1).
CREATE TABLE catalog_prices (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id    BIGINT UNSIGNED NOT NULL,
  store_id      BIGINT UNSIGNED NULL,
  store_id_key  BIGINT UNSIGNED GENERATED ALWAYS AS (IFNULL(store_id, 0)) STORED,
  precio        DECIMAL(12,2) NOT NULL,
  vigente_desde DATE NOT NULL,
  vigente_hasta DATE NULL,
  CONSTRAINT chk_catalog_prices_precio CHECK (precio >= 0),
  CONSTRAINT uq_catalog_prices UNIQUE (product_id, store_id_key, vigente_desde),
  CONSTRAINT fk_catalog_prices_product FOREIGN KEY (product_id)
    REFERENCES catalog_products (id) ON DELETE RESTRICT,
  CONSTRAINT fk_catalog_prices_store FOREIGN KEY (store_id)
    REFERENCES ops_stores (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE catalog_promotions (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id    BIGINT UNSIGNED NULL,
  category_id   BIGINT UNSIGNED NULL,
  descuento_pct DECIMAL(5,2) NOT NULL,
  desde         DATE NOT NULL,
  hasta         DATE NOT NULL,
  CONSTRAINT chk_catalog_promotions_pct CHECK (descuento_pct > 0 AND descuento_pct <= 100),
  CONSTRAINT chk_catalog_promotions_rango CHECK (hasta >= desde),
  CONSTRAINT chk_catalog_promotions_alcance CHECK (
    product_id IS NOT NULL OR category_id IS NOT NULL
  ),
  CONSTRAINT fk_catalog_promotions_product FOREIGN KEY (product_id)
    REFERENCES catalog_products (id) ON DELETE RESTRICT,
  CONSTRAINT fk_catalog_promotions_category FOREIGN KEY (category_id)
    REFERENCES catalog_categories (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE catalog_suppliers (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identificacion VARCHAR(30) NOT NULL,
  nombre         VARCHAR(200) NOT NULL,
  contacto       VARCHAR(255) NULL,
  estado         ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  CONSTRAINT uq_catalog_suppliers_identificacion UNIQUE (identificacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- PII (RN-13). Nunca se borra fila: anonimización (RNF-039) o UPDATE de estado.
CREATE TABLE catalog_patients (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identificacion   VARCHAR(30) NOT NULL,
  nombre           VARCHAR(200) NOT NULL,
  fecha_nacimiento DATE NULL,
  contacto         VARCHAR(255) NULL,
  estado           ENUM('activo','anonimizado') NOT NULL DEFAULT 'activo',
  created_at       DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  anonimizado_at   DATETIME(6) NULL,
  CONSTRAINT uq_catalog_patients_identificacion UNIQUE (identificacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE catalog_prescribers (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identificacion VARCHAR(30) NOT NULL,
  nombre         VARCHAR(200) NOT NULL,
  especialidad   VARCHAR(150) NULL,
  estado         ENUM('activo','inactivo','anonimizado') NOT NULL DEFAULT 'activo',
  CONSTRAINT uq_catalog_prescribers_identificacion UNIQUE (identificacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- 4 · purchase_  (RF-030/031, RN-07)
-- ---------------------------------------------------------------------

CREATE TABLE purchase_orders (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  numero      VARCHAR(50) NOT NULL,
  supplier_id BIGINT UNSIGNED NOT NULL,
  estado      ENUM('borrador','emitida','recibida','cancelada') NOT NULL DEFAULT 'borrador',
  creado_por  BIGINT UNSIGNED NOT NULL,
  created_at  DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at  DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
                                     ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT uq_purchase_orders_numero UNIQUE (numero),
  CONSTRAINT fk_purchase_orders_supplier FOREIGN KEY (supplier_id)
    REFERENCES catalog_suppliers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_purchase_orders_user FOREIGN KEY (creado_por)
    REFERENCES auth_users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ON DELETE RESTRICT (06_integridad §1) — ver README §Reconciliaciones F13:
-- el Paso 04 anotaba CASCADE para order_id; prevalece la regla de no perder
-- filas transaccionales (RN-10 / RNF-023).
CREATE TABLE purchase_order_items (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id          BIGINT UNSIGNED NOT NULL,
  product_id        BIGINT UNSIGNED NOT NULL,
  cantidad_pedida   INT NOT NULL,
  cantidad_recibida INT NOT NULL DEFAULT 0,
  CONSTRAINT chk_poi_pedida CHECK (cantidad_pedida > 0),
  CONSTRAINT chk_poi_recibida CHECK (cantidad_recibida >= 0),
  CONSTRAINT chk_poi_recibida_le_pedida CHECK (cantidad_recibida <= cantidad_pedida),
  CONSTRAINT fk_poi_order FOREIGN KEY (order_id)
    REFERENCES purchase_orders (id) ON DELETE RESTRICT,
  CONSTRAINT fk_poi_product FOREIGN KEY (product_id)
    REFERENCES catalog_products (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE purchase_receptions (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id         BIGINT UNSIGNED NOT NULL,
  estado           ENUM('recibida','confirmada','rechazada') NOT NULL DEFAULT 'recibida',
  recepcionado_por BIGINT UNSIGNED NOT NULL,
  confirmado_por   BIGINT UNSIGNED NULL,
  created_at       DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  confirmado_at    DATETIME(6) NULL,
  idempotency_key  VARCHAR(255) NULL,
  CONSTRAINT uq_purchase_receptions_idem UNIQUE (idempotency_key),
  CONSTRAINT fk_purchase_receptions_order FOREIGN KEY (order_id)
    REFERENCES purchase_orders (id) ON DELETE RESTRICT,
  CONSTRAINT fk_purchase_receptions_recepcion FOREIGN KEY (recepcionado_por)
    REFERENCES auth_users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_purchase_receptions_confirmacion FOREIGN KEY (confirmado_por)
    REFERENCES auth_users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- RF-031: numero_lote y fecha_vencimiento NOT NULL en la recepción.
-- lot_id se puebla al confirmar (RN-07); su FK se añade en el ALTER posterior
-- porque forma un ciclo con inventory_lots.reception_item_id.
CREATE TABLE purchase_reception_items (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reception_id      BIGINT UNSIGNED NOT NULL,
  product_id        BIGINT UNSIGNED NOT NULL,
  numero_lote       VARCHAR(50) NOT NULL,
  fecha_vencimiento DATE NOT NULL,
  cantidad          INT NOT NULL,
  lot_id            BIGINT UNSIGNED NULL,
  CONSTRAINT chk_pri_cantidad CHECK (cantidad > 0),
  CONSTRAINT fk_pri_reception FOREIGN KEY (reception_id)
    REFERENCES purchase_receptions (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pri_product FOREIGN KEY (product_id)
    REFERENCES catalog_products (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- 5 · inventory_  (RF-040/043/045/047, RN-01..RN-04, RN-12)
-- ---------------------------------------------------------------------

CREATE TABLE inventory_lots (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id         BIGINT UNSIGNED NOT NULL,
  numero_lote        VARCHAR(50) NOT NULL,
  fecha_vencimiento  DATE NOT NULL,
  estado             ENUM('cuarentena','liberado','retirado') NOT NULL DEFAULT 'cuarentena',
  -- F15 (opción 1 de 06_integridad §7): NOT NULL — todo lote nace de una
  -- recepción (CA-10). El ciclo con purchase_reception_items.lot_id sigue
  -- resoluble: este FK se puebla al confirmar (RN-07), tras existir el ítem.
  reception_item_id  BIGINT UNSIGNED NOT NULL,
  liberado_por       BIGINT UNSIGNED NULL,
  liberado_at        DATETIME(6) NULL,
  motivo_liberacion  VARCHAR(500) NULL,
  created_at         DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  retirado_at        DATETIME(6) NULL,
  -- RF-032 · F10: implicación, no bicondicional. Una bicondicional
  -- (estado='liberado') = (...) impedía liberado -> retirado (recall, RN-12)
  -- porque liberado_por ya estaba poblado.
  CONSTRAINT chk_inventory_lots_liberacion CHECK (
    estado <> 'liberado'
    OR (liberado_por IS NOT NULL AND liberado_at IS NOT NULL
        AND motivo_liberacion IS NOT NULL)
  ),
  CONSTRAINT uq_inventory_lots_producto_lote UNIQUE (product_id, numero_lote),
  CONSTRAINT uq_inventory_lots_reception UNIQUE (reception_item_id),
  CONSTRAINT fk_inventory_lots_product FOREIGN KEY (product_id)
    REFERENCES catalog_products (id) ON DELETE RESTRICT,
  CONSTRAINT fk_inventory_lots_reception FOREIGN KEY (reception_item_id)
    REFERENCES purchase_reception_items (id) ON DELETE RESTRICT,
  CONSTRAINT fk_inventory_lots_liberado_por FOREIGN KEY (liberado_por)
    REFERENCES auth_users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Ciclo purchase_reception_items.lot_id <-> inventory_lots.reception_item_id
ALTER TABLE purchase_reception_items
  ADD CONSTRAINT fk_pri_lot FOREIGN KEY (lot_id)
    REFERENCES inventory_lots (id) ON DELETE RESTRICT;

-- RN-02 · RF-047 — corazón anti-stock-negativo.
-- (lot_id, store_id) satisface también el índice de acceso I-02 y el índice
-- que InnoDB exige para la FK lot_id, evitando un índice duplicado.
CREATE TABLE inventory_stock (
  store_id        BIGINT UNSIGNED NOT NULL,
  lot_id          BIGINT UNSIGNED NOT NULL,
  stock_available INT NOT NULL DEFAULT 0,
  stock_reserved  INT NOT NULL DEFAULT 0,
  stock_sold      INT NOT NULL DEFAULT 0,
  version         BIGINT NOT NULL DEFAULT 0,
  updated_at      DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
                                    ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (store_id, lot_id),
  KEY ix_inventory_stock_lot_store (lot_id, store_id),
  CONSTRAINT chk_inventory_stock_available CHECK (stock_available >= 0),
  CONSTRAINT chk_inventory_stock_reserved  CHECK (stock_reserved  >= 0),
  CONSTRAINT chk_inventory_stock_sold      CHECK (stock_sold      >= 0),
  CONSTRAINT fk_inventory_stock_store FOREIGN KEY (store_id)
    REFERENCES ops_stores (id) ON DELETE RESTRICT,
  CONSTRAINT fk_inventory_stock_lot FOREIGN KEY (lot_id)
    REFERENCES inventory_lots (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- RNF-023: append-only. Sin UPDATE/DELETE por grants (V1.2.0).
CREATE TABLE inventory_movements (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id          BIGINT UNSIGNED NOT NULL,
  lot_id            BIGINT UNSIGNED NOT NULL,
  product_id        BIGINT UNSIGNED NOT NULL,
  tipo              ENUM('entrada','salida','ajuste_baja','ajuste_incremento',
                         'devolucion','transferencia_salida','transferencia_entrada',
                         'compensatorio') NOT NULL,
  cantidad          INT NOT NULL,
  signo             SMALLINT NOT NULL,
  usuario_id        BIGINT UNSIGNED NOT NULL,
  motivo            VARCHAR(500) NOT NULL,
  proponente_id     BIGINT UNSIGNED NULL,
  autorizador_id    BIGINT UNSIGNED NULL,
  ref_tipo          ENUM('venta','recepcion','transferencia','devolucion',
                         'incidente','manual') NULL,
  ref_id            BIGINT UNSIGNED NULL,
  movimiento_ref_id BIGINT UNSIGNED NULL,
  idempotency_key   VARCHAR(255) NULL,
  created_at        DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT chk_im_cantidad CHECK (cantidad > 0),
  CONSTRAINT chk_im_signo CHECK (signo IN (-1, 1)),
  CONSTRAINT chk_im_ref CHECK (ref_tipo IS NOT NULL OR tipo IN ('ajuste_incremento','ajuste_baja')),
  -- RF-046 / RNF-031: el autorizador nunca es el proponente.
  CONSTRAINT chk_im_doble_autorizacion CHECK (
    autorizador_id IS NULL OR proponente_id IS NULL OR autorizador_id <> proponente_id
  ),
  CONSTRAINT uq_im_idem UNIQUE (idempotency_key),
  CONSTRAINT fk_im_store FOREIGN KEY (store_id)
    REFERENCES ops_stores (id) ON DELETE RESTRICT,
  CONSTRAINT fk_im_lot FOREIGN KEY (lot_id)
    REFERENCES inventory_lots (id) ON DELETE RESTRICT,
  CONSTRAINT fk_im_product FOREIGN KEY (product_id)
    REFERENCES catalog_products (id) ON DELETE RESTRICT,
  CONSTRAINT fk_im_usuario FOREIGN KEY (usuario_id)
    REFERENCES auth_users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_im_proponente FOREIGN KEY (proponente_id)
    REFERENCES auth_users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_im_autorizador FOREIGN KEY (autorizador_id)
    REFERENCES auth_users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_im_ref FOREIGN KEY (movimiento_ref_id)
    REFERENCES inventory_movements (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE inventory_transfers (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_origen_id  BIGINT UNSIGNED NOT NULL,
  store_destino_id BIGINT UNSIGNED NOT NULL,
  estado           ENUM('solicitada','despachada','recibida','cerrada','rechazada')
                   NOT NULL DEFAULT 'solicitada',
  solicitado_por   BIGINT UNSIGNED NOT NULL,
  despachado_por   BIGINT UNSIGNED NULL,
  recibido_por     BIGINT UNSIGNED NULL,
  created_at       DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at       DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
                                      ON UPDATE CURRENT_TIMESTAMP(6),
  idempotency_key  VARCHAR(255) NULL,
  -- RN-08
  CONSTRAINT chk_it_distintas CHECK (store_origen_id <> store_destino_id),
  CONSTRAINT uq_it_idem UNIQUE (idempotency_key),
  CONSTRAINT fk_it_origen FOREIGN KEY (store_origen_id)
    REFERENCES ops_stores (id) ON DELETE RESTRICT,
  CONSTRAINT fk_it_destino FOREIGN KEY (store_destino_id)
    REFERENCES ops_stores (id) ON DELETE RESTRICT,
  CONSTRAINT fk_it_solicitado FOREIGN KEY (solicitado_por)
    REFERENCES auth_users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_it_despachado FOREIGN KEY (despachado_por)
    REFERENCES auth_users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_it_recibido FOREIGN KEY (recibido_por)
    REFERENCES auth_users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE inventory_transfer_items (
  id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transfer_id          BIGINT UNSIGNED NOT NULL,
  lot_id               BIGINT UNSIGNED NOT NULL,
  cantidad_despachada  INT NULL,
  cantidad_recibida    INT NULL,
  CONSTRAINT chk_iti_despachada CHECK (cantidad_despachada IS NULL OR cantidad_despachada > 0),
  CONSTRAINT chk_iti_recibida   CHECK (cantidad_recibida   IS NULL OR cantidad_recibida   >= 0),
  CONSTRAINT uq_iti_transfer_lote UNIQUE (transfer_id, lot_id),
  CONSTRAINT fk_iti_transfer FOREIGN KEY (transfer_id)
    REFERENCES inventory_transfers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_iti_lot FOREIGN KEY (lot_id)
    REFERENCES inventory_lots (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Umbrales leídos de system_config (RF-044/RF-100).
-- F12 CERRADO: store_id_key hace efectivo el UNIQUE aunque store_id sea NULL
--     (alerta global), evitando duplicados por reintento. Patrón F3.
CREATE TABLE inventory_alerts (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo            ENUM('stock_minimo','vencimiento') NOT NULL,
  product_id      BIGINT UNSIGNED NOT NULL,
  store_id        BIGINT UNSIGNED NULL,
  store_id_key    BIGINT UNSIGNED GENERATED ALWAYS AS (IFNULL(store_id, 0)) STORED,
  fecha_generada  DATETIME(6) NOT NULL,
  estado          ENUM('abierta','resuelta') NOT NULL DEFAULT 'abierta',
  resuelta_por    BIGINT UNSIGNED NULL,
  resuelta_at     DATETIME(6) NULL,
  CONSTRAINT uq_inventory_alerts UNIQUE (tipo, product_id, store_id_key, fecha_generada),
  CONSTRAINT fk_inventory_alerts_product FOREIGN KEY (product_id)
    REFERENCES catalog_products (id) ON DELETE RESTRICT,
  CONSTRAINT fk_inventory_alerts_store FOREIGN KEY (store_id)
    REFERENCES ops_stores (id) ON DELETE RESTRICT,
  CONSTRAINT fk_inventory_alerts_resuelta FOREIGN KEY (resuelta_por)
    REFERENCES auth_users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- RF-047: no se admite stock negativo; el ajuste autorizado cierra el incidente.
CREATE TABLE inventory_incidents (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id            BIGINT UNSIGNED NOT NULL,
  lot_id              BIGINT UNSIGNED NOT NULL,
  stock_registrado    INT NOT NULL,
  consumo_declarado   INT NOT NULL,
  estado              ENUM('abierto','ajustado','descartado') NOT NULL DEFAULT 'abierto',
  abierto_por         BIGINT UNSIGNED NOT NULL,
  created_at          DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  movimiento_ajuste_id BIGINT UNSIGNED NULL,
  CONSTRAINT fk_inventory_incidents_store FOREIGN KEY (store_id)
    REFERENCES ops_stores (id) ON DELETE RESTRICT,
  CONSTRAINT fk_inventory_incidents_lot FOREIGN KEY (lot_id)
    REFERENCES inventory_lots (id) ON DELETE RESTRICT,
  CONSTRAINT fk_inventory_incidents_user FOREIGN KEY (abierto_por)
    REFERENCES auth_users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_inventory_incidents_mov FOREIGN KEY (movimiento_ajuste_id)
    REFERENCES inventory_movements (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- 6 · rx_  (RF-052/053, RN-13)
-- ---------------------------------------------------------------------

CREATE TABLE rx_prescriptions (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prescriber_id      BIGINT UNSIGNED NOT NULL,
  patient_id         BIGINT UNSIGNED NOT NULL,
  fecha              DATE NOT NULL,
  numero_referencia  VARCHAR(100) NULL,
  created_at         DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_rx_prescriptions_prescriber FOREIGN KEY (prescriber_id)
    REFERENCES catalog_prescribers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_rx_prescriptions_patient FOREIGN KEY (patient_id)
    REFERENCES catalog_patients (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- RF-053: saldo global por receta (no por sucursal), unicidad cruzada
-- garantizada actualizando esta fila dentro de la misma Tx (RNF-021).
CREATE TABLE rx_prescription_items (
  id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prescription_id      BIGINT UNSIGNED NOT NULL,
  product_id           BIGINT UNSIGNED NOT NULL,
  cantidad_prescrita   INT NOT NULL,
  cantidad_dispensada  INT NOT NULL DEFAULT 0,
  version              BIGINT NOT NULL DEFAULT 0,
  CONSTRAINT chk_rxpi_prescrita CHECK (cantidad_prescrita > 0),
  CONSTRAINT chk_rxpi_saldo CHECK (cantidad_dispensada >= 0
                                   AND cantidad_dispensada <= cantidad_prescrita),
  CONSTRAINT fk_rxpi_prescription FOREIGN KEY (prescription_id)
    REFERENCES rx_prescriptions (id) ON DELETE RESTRICT,
  CONSTRAINT fk_rxpi_product FOREIGN KEY (product_id)
    REFERENCES catalog_products (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- 7 · sales_  (RF-050/051/054/060, D-4 sin canal web)
-- ---------------------------------------------------------------------

CREATE TABLE sales_orders (
  id                     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  numero                 VARCHAR(50) NOT NULL,
  channel                ENUM('pos') NOT NULL DEFAULT 'pos',
  store_id               BIGINT UNSIGNED NOT NULL,
  register_id            BIGINT UNSIGNED NOT NULL,
  paciente_id            BIGINT UNSIGNED NULL,
  usuario_id             BIGINT UNSIGNED NOT NULL,
  quimico_verificador_id BIGINT UNSIGNED NULL,
  estado                 ENUM('pendiente','pagada','anulada','devuelta')
                         NOT NULL DEFAULT 'pendiente',
  total                  DECIMAL(12,2) NOT NULL DEFAULT 0,
  idempotency_key        VARCHAR(255) NULL,
  created_at             DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT chk_so_total CHECK (total >= 0),
  CONSTRAINT chk_so_devuelta_con_paciente CHECK (paciente_id IS NOT NULL OR estado <> 'devuelta'),
  CONSTRAINT uq_sales_orders_numero UNIQUE (numero),
  CONSTRAINT uq_sales_orders_idem UNIQUE (idempotency_key),
  CONSTRAINT fk_so_store FOREIGN KEY (store_id)
    REFERENCES ops_stores (id) ON DELETE RESTRICT,
  CONSTRAINT fk_so_register FOREIGN KEY (register_id)
    REFERENCES ops_registers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_so_paciente FOREIGN KEY (paciente_id)
    REFERENCES catalog_patients (id) ON DELETE RESTRICT,
  CONSTRAINT fk_so_usuario FOREIGN KEY (usuario_id)
    REFERENCES auth_users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_so_quimico FOREIGN KEY (quimico_verificador_id)
    REFERENCES auth_users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- RN-01: ítem siempre contra un lote.
-- F3: rx_item_id_key hace efectivo el UNIQUE aunque rx_item_id sea NULL
--     (MySQL trata NULLs como distintos en UNIQUE).
CREATE TABLE sales_order_items (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id        BIGINT UNSIGNED NOT NULL,
  lot_id          BIGINT UNSIGNED NOT NULL,
  product_id      BIGINT UNSIGNED NOT NULL,
  cantidad        INT NOT NULL,
  precio_unitario DECIMAL(12,2) NOT NULL,
  subtotal        DECIMAL(12,2) NOT NULL,
  rx_item_id      BIGINT UNSIGNED NULL,
  rx_item_id_key  BIGINT UNSIGNED GENERATED ALWAYS AS (IFNULL(rx_item_id, 0)) STORED,
  CONSTRAINT chk_soi_cantidad CHECK (cantidad > 0),
  CONSTRAINT chk_soi_precio CHECK (precio_unitario >= 0),
  CONSTRAINT chk_soi_subtotal CHECK (subtotal >= 0),
  CONSTRAINT uq_soi_orden_lote_rx UNIQUE (order_id, lot_id, rx_item_id_key),
  CONSTRAINT fk_soi_order FOREIGN KEY (order_id)
    REFERENCES sales_orders (id) ON DELETE RESTRICT,
  CONSTRAINT fk_soi_lot FOREIGN KEY (lot_id)
    REFERENCES inventory_lots (id) ON DELETE RESTRICT,
  CONSTRAINT fk_soi_product FOREIGN KEY (product_id)
    REFERENCES catalog_products (id) ON DELETE RESTRICT,
  CONSTRAINT fk_soi_rx_item FOREIGN KEY (rx_item_id)
    REFERENCES rx_prescription_items (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- §5: saga con outbox; nunca Tx SQL abierta esperando gateway.
CREATE TABLE payments_transactions (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id        BIGINT UNSIGNED NOT NULL,
  medio           VARCHAR(50) NOT NULL,
  proveedor       VARCHAR(100) NULL,
  reference       VARCHAR(255) NULL,
  amount          DECIMAL(12,2) NOT NULL,
  status          ENUM('pendiente','aprobado','rechazado','reembolsado') NOT NULL,
  idempotency_key VARCHAR(255) NULL,
  created_at      DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  actualizado_at  DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
                                         ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT chk_pt_amount CHECK (amount > 0),
  CONSTRAINT uq_pt_reference UNIQUE (reference),
  CONSTRAINT uq_pt_idem UNIQUE (idempotency_key),
  CONSTRAINT fk_pt_order FOREIGN KEY (order_id)
    REFERENCES sales_orders (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- §22: transacción nueva vinculada; el original queda intacto (RN-10).
CREATE TABLE sales_returns (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id           BIGINT UNSIGNED NOT NULL,
  store_id           BIGINT UNSIGNED NOT NULL,
  usuario_id         BIGINT UNSIGNED NOT NULL,
  motivo             VARCHAR(500) NOT NULL,
  monto              DECIMAL(12,2) NOT NULL,
  estado_evaluacion  ENUM('pendiente','reingresado','descartado') NOT NULL DEFAULT 'pendiente',
  reembolso_estado   ENUM('none','pendiente','confirmado','fallido') NOT NULL DEFAULT 'none',
  created_at         DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT chk_sr_monto CHECK (monto >= 0),
  CONSTRAINT fk_sr_order FOREIGN KEY (order_id)
    REFERENCES sales_orders (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sr_store FOREIGN KEY (store_id)
    REFERENCES ops_stores (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sr_usuario FOREIGN KEY (usuario_id)
    REFERENCES auth_users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE sales_return_items (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  return_id     BIGINT UNSIGNED NOT NULL,
  order_item_id BIGINT UNSIGNED NOT NULL,
  lot_id        BIGINT UNSIGNED NOT NULL,
  product_id    BIGINT UNSIGNED NOT NULL,
  cantidad      INT NOT NULL,
  condicion     ENUM('vendible','no_vendible') NOT NULL DEFAULT 'no_vendible',
  CONSTRAINT chk_sri_cantidad CHECK (cantidad > 0),
  CONSTRAINT uq_sri_return_item UNIQUE (return_id, order_item_id),
  CONSTRAINT fk_sri_return FOREIGN KEY (return_id)
    REFERENCES sales_returns (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sri_order_item FOREIGN KEY (order_item_id)
    REFERENCES sales_order_items (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sri_lot FOREIGN KEY (lot_id)
    REFERENCES inventory_lots (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sri_product FOREIGN KEY (product_id)
    REFERENCES catalog_products (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Reserva a nivel producto+sucursal; el lote se asigna al confirmar (FEFO, RN-03).
-- DB-P01/DB-P02 abiertos (efecto sobre POS y expiración sin confirmar).
CREATE TABLE inventory_reservations (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id   BIGINT UNSIGNED NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  store_id   BIGINT UNSIGNED NOT NULL,
  qty        INT NOT NULL,
  status     ENUM('pending','confirmed','expired','cancelled') NOT NULL DEFAULT 'pending',
  expires_at DATETIME(6) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT chk_ir_qty CHECK (qty > 0),
  CONSTRAINT fk_ir_order FOREIGN KEY (order_id)
    REFERENCES sales_orders (id) ON DELETE RESTRICT,
  CONSTRAINT fk_ir_product FOREIGN KEY (product_id)
    REFERENCES catalog_products (id) ON DELETE RESTRICT,
  CONSTRAINT fk_ir_store FOREIGN KEY (store_id)
    REFERENCES ops_stores (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- 8 · ctrl_  (RF-045/055/071, RN-05/RN-06)
-- ---------------------------------------------------------------------

-- RNF-046: append-only. Sin UPDATE/DELETE por grants (V1.2.0).
CREATE TABLE ctrl_ledger_entries (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id          BIGINT UNSIGNED NOT NULL,
  product_id        BIGINT UNSIGNED NOT NULL,
  tipo              ENUM('entrada','salida','ajuste','devolucion',
                         'transferencia_out','transferencia_in') NOT NULL,
  cantidad          INT NOT NULL,
  saldo_resultante  INT NOT NULL,
  usuario_id        BIGINT UNSIGNED NOT NULL,
  autorizador_id    BIGINT UNSIGNED NULL,
  motivo            VARCHAR(500) NOT NULL,
  ref_tipo          ENUM('venta','recepcion','transferencia','devolucion','ajuste') NOT NULL,
  ref_id            BIGINT UNSIGNED NOT NULL,
  created_at        DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT chk_cle_cantidad CHECK (cantidad > 0),
  CONSTRAINT chk_cle_saldo CHECK (saldo_resultante >= 0),
  -- RF-046 / RN-06: todo ajuste del libro exige autorizador distinto del usuario.
  CONSTRAINT chk_cle_ajuste_autorizado CHECK (tipo <> 'ajuste' OR autorizador_id IS NOT NULL),
  CONSTRAINT chk_cle_doble_autorizacion CHECK (
    autorizador_id IS NULL OR autorizador_id <> usuario_id
  ),
  CONSTRAINT fk_cle_store FOREIGN KEY (store_id)
    REFERENCES ops_stores (id) ON DELETE RESTRICT,
  CONSTRAINT fk_cle_product FOREIGN KEY (product_id)
    REFERENCES catalog_products (id) ON DELETE RESTRICT,
  CONSTRAINT fk_cle_usuario FOREIGN KEY (usuario_id)
    REFERENCES auth_users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_cle_autorizador FOREIGN KEY (autorizador_id)
    REFERENCES auth_users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- RF-055: saldo permanente conciliable con SUM(asientos) (RNF-024).
CREATE TABLE ctrl_balances (
  store_id   BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  saldo      INT NOT NULL,
  version    BIGINT NOT NULL DEFAULT 0,
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
                                 ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (store_id, product_id),
  CONSTRAINT chk_ctrl_balances_saldo CHECK (saldo >= 0),
  CONSTRAINT fk_ctrl_balances_store FOREIGN KEY (store_id)
    REFERENCES ops_stores (id) ON DELETE RESTRICT,
  CONSTRAINT fk_ctrl_balances_product FOREIGN KEY (product_id)
    REFERENCES catalog_products (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- 9 · audit_  (RF-090/091, RN-13, RNF-046/RNF-050)
-- ---------------------------------------------------------------------

-- Solo INSERT: sin UPDATE/DELETE por grants (V1.2.0).
CREATE TABLE audit_operations (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id     BIGINT UNSIGNED NULL,
  accion         VARCHAR(100) NOT NULL,
  entidad        VARCHAR(100) NOT NULL,
  entidad_id     BIGINT UNSIGNED NULL,
  valores_antes  JSON NULL,
  valores_despues JSON NULL,
  motivo         VARCHAR(500) NULL,
  created_at     DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_audit_operations_user FOREIGN KEY (usuario_id)
    REFERENCES auth_users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- RF-091: una fila por acceso a PII. El registro de cada SELECT es
-- responsabilidad de la aplicación (MySQL no tiene triggers de SELECT).
CREATE TABLE audit_pii_access (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id     BIGINT UNSIGNED NOT NULL,
  accion         ENUM('consulta','creacion','modificacion','exportacion') NOT NULL,
  paciente_id    BIGINT UNSIGNED NULL,
  prescription_id BIGINT UNSIGNED NULL,
  motivo         VARCHAR(500) NULL,
  created_at     DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT chk_apii_objetivo CHECK (
    paciente_id IS NOT NULL OR prescription_id IS NOT NULL
  ),
  CONSTRAINT fk_audit_pii_user FOREIGN KEY (usuario_id)
    REFERENCES auth_users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_audit_pii_paciente FOREIGN KEY (paciente_id)
    REFERENCES catalog_patients (id) ON DELETE RESTRICT,
  CONSTRAINT fk_audit_pii_prescription FOREIGN KEY (prescription_id)
    REFERENCES rx_prescriptions (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- 10 · sys_  (RN-09/CA-07 idempotencia, §16 outbox)
-- ---------------------------------------------------------------------

CREATE TABLE idempotency_keys (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  idempotency_key VARCHAR(255) NOT NULL,
  operacion       VARCHAR(100) NOT NULL,
  request_hash    CHAR(64) NOT NULL,
  respuesta_ref   VARCHAR(255) NULL,
  estado          ENUM('en_proceso','completado') NOT NULL,
  created_at      DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  expires_at      DATETIME(6) NOT NULL,
  CONSTRAINT uq_idempotency_keys_key UNIQUE (idempotency_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE outbox_events (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  agregado_tipo VARCHAR(50) NOT NULL,
  agregado_id   BIGINT UNSIGNED NOT NULL,
  tipo_evento   VARCHAR(100) NOT NULL,
  payload       JSON NOT NULL,
  estado        ENUM('pendiente','procesado','fallido') NOT NULL DEFAULT 'pendiente',
  created_at    DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  procesado_at  DATETIME(6) NULL,
  intentos      INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

SET FOREIGN_KEY_CHECKS = 1;
