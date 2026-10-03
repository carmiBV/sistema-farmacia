# Paso 04 — Modelo lógico

- **Workflow:** 02_database_workflow · **Paso:** 04
- **Skill utilizado:** `database-schema-designer`
- **Entrada:** `02_modelo_conceptual/` (entidades y relaciones)
- **Notación:** `tabla(col TIPO [NOT NULL] [UNIQUE] [CHECK ...], ..., PK, FK, IX)` — tipos lógicos; el mapeo a tipos físicos MySQL es del Paso 07.
- **Convención:** `id` = surrogate PK (BIGINT o UUID, decisión en Paso 07); timestamps `created_at/updated_at` UTC (RNF-047); tablas transaccionales **sin UPDATE/DELETE** salvo indicación (RNF-023).

## 1. Identidad (`auth_`)

```
auth_roles(id PK, nombre VARCHAR NOT NULL UNIQUE, descripcion VARCHAR NULL)

auth_permissions(id PK, clave VARCHAR NOT NULL UNIQUE, descripcion VARCHAR NULL)

auth_role_permissions(role_id PK/FK→auth_roles, permission_id PK/FK→auth_permissions)
  FK ON DELETE CASCADE (solo catálogo, no transaccional)

auth_users(id PK, usuario VARCHAR NOT NULL UNIQUE, password_hash VARCHAR NOT NULL,
           estado ENUM(activo,bloqueado,inactivo) NOT NULL DEFAULT 'activo',
           mfa_habilitado BOOL NOT NULL DEFAULT false,
           ultimo_acceso_at DATETIME NULL, created_at NOT NULL)
  -- hash Argon2id (§15); jamés texto plano (RNF-033)

auth_user_roles(user_id PK/FK→auth_users, role_id PK/FK→auth_roles)

token_blacklist(id PK, token_hash CHAR(64) NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL, created_at NOT NULL)
  -- IX(expires_at) para purga; hash, no token en claro (§15)
```

## 2. Operación y configuración (`ops_`, `system_config`)

```
ops_stores(id PK, codigo VARCHAR NOT NULL UNIQUE, nombre VARCHAR NOT NULL,
           estado ENUM(activa,inactiva) NOT NULL DEFAULT 'activa', created_at NOT NULL)

ops_registers(id PK, store_id NOT NULL FK→ops_stores, codigo VARCHAR NOT NULL,
              estado ENUM(activa,inactiva) NOT NULL DEFAULT 'activa')
  UNIQUE(store_id, codigo)

system_config(config_key PK VARCHAR, config_value VARCHAR NOT NULL,
              value_type ENUM(texto,numero,booleano,json) NOT NULL,
              store_id NULL FK→ops_stores,   -- NULL = alcance global (§21, pendiente de validar)
              updated_at NOT NULL, updated_by NOT NULL FK→auth_users)
  CHECK (store_id IS NOT NULL OR config_key NOT LIKE 'store.%')
  -- convención: parámetros por sucursal = config_key 'store.<clave>'; globales sin prefijo
  -- valores RF-100; los parámetros con valor null del cliente NO se filan hasta definir
```

## 3. Catálogo y terceros (`catalog_`)

```
catalog_categories(id PK, nombre VARCHAR NOT NULL, parent_id NULL FK→catalog_categories)
  -- F14: UNIQUE efectiva para categorías raíz (parent_id NULL), que MySQL distinguiría:
  --   parent_id_key BIGINT GENERATED ALWAYS AS (IFNULL(parent_id, 0)) STORED
  --   UNIQUE(nombre, parent_id_key)

catalog_products(id PK, sku VARCHAR NOT NULL UNIQUE, nombre VARCHAR NOT NULL,
                 principio_activo VARCHAR NOT NULL, presentacion VARCHAR NOT NULL,
                 concentracion VARCHAR NOT NULL,
                 condicion_venta ENUM(libre,receta,controlado) NOT NULL,
                 estado ENUM(activo,inactivo) NOT NULL DEFAULT 'activo', created_at NOT NULL)

catalog_product_categories(product_id FK→catalog_products, category_id FK→catalog_categories)
  PK(product_id, category_id)

catalog_prices(id PK, product_id NOT NULL FK→catalog_products,
               store_id NULL FK→ops_stores,          -- NULL = precio global; DB-P09 pendiente
               precio DECIMAL(12,2) NOT NULL CHECK (precio >= 0),
               vigente_desde DATE NOT NULL, vigente_hasta DATE NULL)
  -- UNIQUE efectiva incluso con store_id NULL (MySQL distingue NULLs) — F12:
  --   store_id_key BIGINT GENERATED ALWAYS AS (IFNULL(store_id, 0)) STORED
  --   UNIQUE(product_id, store_id_key, vigente_desde)

catalog_promotions(id PK, product_id NULL FK→catalog_products,
                   category_id NULL FK→catalog_categories,
                   descuento_pct DECIMAL(5,2) NOT NULL CHECK (descuento_pct > 0 AND descuento_pct <= 100),
                   desde DATE NOT NULL, hasta DATE NOT NULL CHECK (hasta >= desde))
  CHECK (product_id IS NOT NULL OR category_id IS NOT NULL)

catalog_suppliers(id PK, identificacion VARCHAR NOT NULL UNIQUE,
                  nombre VARCHAR NOT NULL, contacto VARCHAR NULL,
                  estado ENUM(activo,inactivo) NOT NULL DEFAULT 'activo')

catalog_patients(id PK, identificacion VARCHAR NOT NULL UNIQUE, nombre VARCHAR NOT NULL,
                 fecha_nacimiento DATE NULL, contacto VARCHAR NULL,
                 estado ENUM(activo,anonimizado) NOT NULL DEFAULT 'activo',
                 created_at NOT NULL, anonimizado_at DATETIME NULL)
  -- PII (RN-13): acceso por rol + audit_pii_access; anonimización por RNF-039 sin borrar recetas/movimientos (RN-10)

catalog_prescribers(id PK, identificacion VARCHAR NOT NULL UNIQUE, nombre VARCHAR NOT NULL,
                    especialidad VARCHAR NULL,
                    estado ENUM(activo,inactivo,anonimizado) NOT NULL DEFAULT 'activo')
  -- PII igual que pacientes
```

## 4. Compras y recepción (`purchase_`)

```
purchase_orders(id PK, numero VARCHAR NOT NULL UNIQUE, supplier_id NOT NULL FK→catalog_suppliers,
                estado ENUM(borrador,emitida,recibida,cancelada) NOT NULL DEFAULT 'borrador',
                creado_por FK→auth_users, created_at NOT NULL, updated_at NOT NULL)

purchase_order_items(id PK, order_id NOT NULL FK→purchase_orders ON DELETE CASCADE,
                     product_id NOT NULL FK→catalog_products,
                     cantidad_pedida INT NOT NULL CHECK (cantidad_pedida > 0),
                     cantidad_recibida INT NOT NULL DEFAULT 0 CHECK (cantidad_recibida >= 0))
  CHECK (cantidad_recibida <= cantidad_pedida)

purchase_receptions(id PK, order_id NOT NULL FK→purchase_orders,
                    estado ENUM(recibida,confirmada,rechazada) NOT NULL DEFAULT 'recibida',
                    recepcionado_por FK→auth_users, confirmado_por NULL FK→auth_users,
                    created_at NOT NULL, confirmado_at DATETIME NULL,
                    idempotency_key NULL)
  UNIQUE(idempotency_key)
  -- RN-07: inventario solo al pasar a 'confirmada'

purchase_reception_items(id PK, reception_id NOT NULL FK→purchase_receptions,
                         product_id NOT NULL FK→catalog_products,
                         numero_lote VARCHAR NOT NULL,      -- RF-031 NOT NULL
                         fecha_vencimiento DATE NOT NULL,   -- RF-031 NOT NULL
                         cantidad INT NOT NULL CHECK (cantidad > 0),
                         lot_id NULL FK→inventory_lots)     -- poblado al confirmar
  -- RNF-025: validación en servidor; RN-09 vía idempotency_key en la recepción
```

## 5. Inventario (`inventory_`)

```
inventory_lots(id PK, product_id NOT NULL FK→catalog_products,
               numero_lote VARCHAR NOT NULL, fecha_vencimiento DATE NOT NULL,
               estado ENUM(cuarentena,liberado,retirado) NOT NULL DEFAULT 'cuarentena',
               reception_item_id NOT NULL FK→purchase_reception_items UNIQUE,  -- F15: sin recepción no hay lote (CA-10)
               liberado_por NULL FK→auth_users, liberado_at DATETIME NULL,
               motivo_liberacion VARCHAR NULL, created_at NOT NULL, retirado_at DATETIME NULL)
  UNIQUE(product_id, numero_lote)
  CHECK (estado <> 'liberado' OR (liberado_por IS NOT NULL AND liberado_at IS NOT NULL
                                  AND motivo_liberacion IS NOT NULL))   -- RF-032 (F10: implicación, no bicondicional)
  -- IX FEFO: (product_id, fecha_vencimiento, estado)

inventory_stock(store_id NOT NULL FK→ops_stores, lot_id NOT NULL FK→inventory_lots,
                stock_available INT NOT NULL DEFAULT 0 CHECK (stock_available >= 0),   -- RN-02
                stock_reserved  INT NOT NULL DEFAULT 0 CHECK (stock_reserved  >= 0),
                stock_sold      INT NOT NULL DEFAULT 0 CHECK (stock_sold      >= 0),
                version BIGINT NOT NULL DEFAULT 0,     -- optimista (§4/DEC-02)
                updated_at NOT NULL)
  PK(store_id, lot_id)
  -- invariantes de no negatividad viven en los CHECK de stock_* (RN-02)
  -- disponibilidad total por lote = SUM(stock_available) por producto (consultas FEFO)

inventory_movements(id PK,
                    store_id NOT NULL FK→ops_stores, lot_id NOT NULL FK→inventory_lots,
                    product_id NOT NULL FK→catalog_products,
                    tipo ENUM(entrada,salida,ajuste_baja,ajuste_incremento,devolucion,
                              transferencia_salida,transferencia_entrada,compensatorio)
                         NOT NULL,
                    cantidad INT NOT NULL CHECK (cantidad > 0),
                    signo SMALLINT NOT NULL CHECK (signo IN (-1, 1)),   -- delta = signo*cantidad
                    usuario_id NOT NULL FK→auth_users,
                    motivo VARCHAR NOT NULL,                       -- RN-06
                    proponente_id NULL FK→auth_users,
                    autorizador_id NULL FK→auth_users,             -- RN-06 doble autorización
                    ref_tipo ENUM(venta,recepcion,transferencia,devolucion,incidente,manual) NULL,
                    ref_id BIGINT NULL,                            -- sin FK (polimorfismo, §3 diagrama)
                    movimiento_ref_id NULL FK→inventory_movements, -- compensatorio (RN-10)
                    idempotency_key NULL, created_at NOT NULL)
  UNIQUE(idempotency_key)
  CHECK (ref_tipo IS NOT NULL OR tipo IN ('ajuste_incremento','ajuste_baja'))
  -- controlados con ajuste: CHECK aplicado con product.condicion_venta en servidor (Paso 08):
  --   autorizador_id NOT NULL AND autorizador_id <> proponente_id (RF-046)
  -- append-only: sin UPDATE/DELETE (RNF-023); reconstrucción de saldo (RNF-024)

inventory_transfers(id PK, store_origen_id NOT NULL FK→ops_stores,
                    store_destino_id NOT NULL FK→ops_stores,
                    estado ENUM(solicitada,despachada,recibida,cerrada,rechazada)
                         NOT NULL DEFAULT 'solicitada',
                    solicitado_por FK→auth_users, despachado_por NULL FK→auth_users,
                    recibido_por NULL FK→auth_users,
                    created_at NOT NULL, updated_at NOT NULL,
                    idempotency_key NULL UNIQUE)
  CHECK (store_origen_id <> store_destino_id)
  CHECK (fecha de transición coherente con estado — validado en aplicación + audit)  -- RN-08

inventory_transfer_items(id PK, transfer_id NOT NULL FK→inventory_transfers,
                         lot_id NOT NULL FK→inventory_lots,
                         cantidad_despachada INT NULL CHECK (cantidad_despachada > 0),
                         cantidad_recibida INT NULL CHECK (cantidad_recibida >= 0))
  UNIQUE(transfer_id, lot_id)
  -- DB-P03 (máximo ítems) pendiente: sin CHECK aún
  -- despacho: baja en origen + asiento libro (controlados) en origen;
  -- recepción: alta en destino + asiento en destino (RF-045, CAM-002-c)

inventory_reservations(id PK, order_id NULL FK→sales_orders,
                       product_id NOT NULL FK→catalog_products,
                       store_id NOT NULL FK→ops_stores,
                       qty INT NOT NULL CHECK (qty > 0),
                       status ENUM(pending,confirmed,expired,cancelled) NOT NULL DEFAULT 'pending',
                       expires_at NOT NULL, created_at NOT NULL)
  -- §4; reserva a nivel producto+sucursal; lote se asigna al confirmar (FEFO, RN-03)
  -- DB-P01/DB-P02 abiertas: efecto sobre POS y expiración sin confirmar
  -- alcance: capacidad inicial sin canal web (D-4)

inventory_alerts(id PK, tipo ENUM(stock_minimo,vencimiento) NOT NULL,
                 product_id NOT NULL FK→catalog_products,
                 store_id NULL FK→ops_stores,      -- NULL = todas las sucursales
                 fecha_generada NOT NULL,
                 estado ENUM(abierta,resuelta) NOT NULL DEFAULT 'abierta',
                 resuelta_por NULL FK→auth_users, resuelta_at DATETIME NULL)
  -- UNIQUE efectiva aunque store_id sea NULL (alerta global) — F12:
  --   store_id_key BIGINT GENERATED ALWAYS AS (IFNULL(store_id, 0)) STORED
  --   UNIQUE(tipo, product_id, store_id_key, fecha_generada)   -- sin duplicados por reintento
  -- umbrales leídos de system_config (RF-044/RF-100)

inventory_incidents(id PK, store_id NOT NULL FK→ops_stores, lot_id NOT NULL FK→inventory_lots,
                    stock_registrado INT NOT NULL, consumo_declarado INT NOT NULL,
                    estado ENUM(abierto,ajustado,descartado) NOT NULL DEFAULT 'abierto',
                    abierto_por FK→auth_users, created_at NOT NULL,
                    movimiento_ajuste_id NULL FK→inventory_movements)
  -- RF-047: no se admite stock negativo; el ajuste autorizado cierra el incidente
```

## 6. Venta, pago y devolución (`sales_`)

```
sales_orders(id PK, numero VARCHAR NOT NULL UNIQUE,           -- §6 UNIQUE
             channel ENUM(pos) NOT NULL DEFAULT 'pos',        -- D-4: sin web en alcance
             store_id NOT NULL FK→ops_stores, register_id NOT NULL FK→ops_registers,
             paciente_id NULL FK→catalog_patients,
             usuario_id NOT NULL FK→auth_users,
             quimico_verificador_id NULL FK→auth_users,        -- RF-054/RNF-030
             estado ENUM(pendiente,pagada,anulada,devuelta) NOT NULL DEFAULT 'pendiente',
             total DECIMAL(12,2) NOT NULL CHECK (total >= 0),
             idempotency_key NULL UNIQUE, created_at NOT NULL)
  CHECK (paciente_id IS NOT NULL OR estado <> 'devuelta')
  -- quimico_verificador_id NOT NULL cuando ítems con condición <> 'libre' (validación servidor, Paso 08)

sales_order_items(id PK, order_id NOT NULL FK→sales_orders,
                  lot_id NOT NULL FK→inventory_lots,          -- RN-01
                  product_id NOT NULL FK→catalog_products,
                  cantidad INT NOT NULL CHECK (cantidad > 0),
                  precio_unitario DECIMAL(12,2) NOT NULL CHECK (precio_unitario >= 0),
                  subtotal DECIMAL(12,2) NOT NULL CHECK (subtotal >= 0),
                  rx_item_id NULL FK→rx_prescription_items)   -- consume saldo de receta (RF-053)
  -- UNIQUE efectiva incluso con rx_item_id NULL (MySQL distingue NULLs):
  --   rx_item_id_key INT GENERATED ALWAYS AS (IFNULL(rx_item_id, 0)) STORED
  --   UNIQUE(order_id, lot_id, rx_item_id_key)
  -- descuento del lote en la MISMA transacción de confirmación (RF-051, RNF-021)

payments_transactions(id PK, order_id NOT NULL FK→sales_orders,
                      medio VARCHAR NOT NULL,                  -- paramétrico (RF-050, a confirmar)
                      proveedor NULL VARCHAR,                  -- terceros → RNF-042
                      reference VARCHAR NULL UNIQUE,
                      amount DECIMAL(12,2) NOT NULL CHECK (amount > 0),
                      status ENUM(pendiente,aprobado,rechazado,reembolsado) NOT NULL,
                      idempotency_key NULL UNIQUE,
                      created_at NOT NULL, actualizado_at NOT NULL)
  -- saga con outbox (§5): nunca transacción SQL abierta esperando gateway

sales_returns(id PK, order_id NOT NULL FK→sales_orders,
              store_id NOT NULL FK→ops_stores, usuario_id NOT NULL FK→auth_users,
              motivo VARCHAR NOT NULL, monto DECIMAL(12,2) NOT NULL CHECK (monto >= 0),
              estado_evaluacion ENUM(pendiente,reingresado,descartado) NOT NULL DEFAULT 'pendiente',
              reembolso_estado ENUM(none,pendiente,confirmado,fallido) NOT NULL DEFAULT 'none',
              created_at NOT NULL)
  -- §22: transacción nueva vinculada; original intacto (RN-10)
  -- RN-11: 'reingresado' solo tras evaluación → movimiento de inventario posterior

sales_return_items(id PK, return_id NOT NULL FK→sales_returns,
                   order_item_id NOT NULL FK→sales_order_items,
                   lot_id NOT NULL FK→inventory_lots,
                   product_id NOT NULL FK→catalog_products,
                   cantidad INT NOT NULL CHECK (cantidad > 0),
                   condicion ENUM(vendible,no_vendible) NOT NULL DEFAULT 'no_vendible')
  UNIQUE(return_id, order_item_id)
  CHECK (cantidad <= cantidad dispensada del ítem — servidor, Paso 08)
```

## 7. Recetas (`rx_`)

```
rx_prescriptions(id PK, prescriber_id NOT NULL FK→catalog_prescribers,
                 patient_id NOT NULL FK→catalog_patients,     -- PII
                 fecha DATE NOT NULL, numero_referencia VARCHAR NULL,
                 created_at NOT NULL)

rx_prescription_items(id PK, prescription_id NOT NULL FK→rx_prescriptions,
                      product_id NOT NULL FK→catalog_products,
                      cantidad_prescrita INT NOT NULL CHECK (cantidad_prescrita > 0),
                      cantidad_dispensada INT NOT NULL DEFAULT 0
                         CHECK (cantidad_dispensada >= 0),
                      version BIGINT NOT NULL DEFAULT 0)       -- optimista
  CHECK (cantidad_dispensada <= cantidad_prescrita)            -- RF-053 saldo ≥ 0
  -- saldo GLOBAL por receta (no por sucursal): RF-053; unicidad cruzada sucursales
  --   garantizada por actualizar esta fila en la misma Tx de dispensación (RNF-021)
  -- P-17/S-9: numeração de receta pendiente (numero_referencia nullable)
```

## 8. Libro de controlados (`ctrl_`)

```
ctrl_ledger_entries(id PK, store_id NOT NULL FK→ops_stores,
                    product_id NOT NULL FK→catalog_products,
                    tipo ENUM(entrada,salida,ajuste,devolucion,transferencia_out,
                              transferencia_in) NOT NULL,
                    cantidad INT NOT NULL CHECK (cantidad > 0),
                    saldo_resultante INT NOT NULL CHECK (saldo_resultante >= 0),
                    usuario_id NOT NULL FK→auth_users,
                    autorizador_id NULL FK→auth_users,          -- RN-06
                    motivo VARCHAR NOT NULL,
                    ref_tipo ENUM(venta,recepcion,transferencia,devolucion,ajuste) NOT NULL,
                    ref_id BIGINT NOT NULL, created_at NOT NULL)
  -- append-only (RNF-046); asiento en despacho y recepción de transferencias (RF-045)
  -- CHECK: si tipo='ajuste' → autorizador_id NOT NULL (Paso 08, con RF-046)
  -- IX(store_id, product_id, created_at) para reportes RF-071

ctrl_balances(store_id NOT NULL FK→ops_stores, product_id NOT NULL FK→catalog_products,
              saldo INT NOT NULL CHECK (saldo >= 0),
              version BIGINT NOT NULL DEFAULT 0, updated_at NOT NULL)
  PK(store_id, product_id)
  -- RF-055 saldo permanente; conciliable con SUM(asientos) (RNF-024)
```

## 9. Auditoría (`audit_`)

```
audit_operations(id PK, usuario_id NULL FK→auth_users, accion VARCHAR NOT NULL,
                 entidad VARCHAR NOT NULL, entidad_id BIGINT NULL,
                 valores_antes JSON NULL, valores_despues JSON NULL,
                 motivo VARCHAR NULL, created_at NOT NULL)
  -- RF-090; solo INSERT (RNF-046); sin PII de paciente en valores (RNF-050)

audit_pii_access(id PK, usuario_id NOT NULL FK→auth_users,
                 accion ENUM(consulta,creacion,modificacion,exportacion) NOT NULL,
                 paciente_id NULL FK→catalog_patients,
                 prescription_id NULL FK→rx_prescriptions,
                 motivo VARCHAR NULL, created_at NOT NULL)
  -- RF-091/RN-13: una fila por acceso a PII
  -- CHECK: paciente_id IS NOT NULL OR prescription_id IS NOT NULL
```

## 10. Soporte técnico (`sys_`)

```
idempotency_keys(id PK, idempotency_key VARCHAR NOT NULL UNIQUE,
                 operacion VARCHAR NOT NULL, request_hash CHAR(64) NOT NULL,
                 respuesta_ref VARCHAR NULL, estado ENUM(en_proceso,completado) NOT NULL,
                 created_at NOT NULL, expires_at NOT NULL)
  -- RN-09/CA-07: cobro, dispensación, recepción, transferencia
  -- retención propuesta 30 d (§6) → purga por expires_at (pendiente de validación)

outbox_events(id PK, agregado_tipo VARCHAR NOT NULL, agregado_id BIGINT NOT NULL,
              tipo_evento VARCHAR NOT NULL, payload JSON NOT NULL,
              estado ENUM(pendiente,procesado,fallido) NOT NULL DEFAULT 'pendiente',
              created_at NOT NULL, procesado_at DATETIME NULL, intentos INT NOT NULL DEFAULT 0)
  -- §16: insert en la MISMA transacción que el evento de dominio; IX(estado, created_at)
```

## 11. Índices lógicos requeridos por regla (detalle en Paso 11)

| Índice lógico | Justificación |
|---|---|
| `inventory_lots(product_id, fecha_vencimiento, estado)` | FEFO (RN-03, RF-043) |
| `inventory_movements(lot_id, created_at)` | trazabilidad por lote (CA-10, RNF-045) |
| `sales_order_items(lot_id)` | recall: dispensaciones por lote (RN-12) |
| `ctrl_ledger_entries(store_id, product_id, created_at)` | saldo/reporte por período (RF-071) |
| `audit_pii_access(paciente_id, created_at)`, `(prescription_id, created_at)` | RF-091 |
| FKs hacia `inventory_movements`, `sales_orders`, `audit_operations` | rendimiento y retención |

## 12. Pendientes que condicionan el lógico

- DB-P01/P02/P03 (reservas, transferencias), DB-P08 (clientes), DB-P09 (alcance de precios → `store_id` nullable), DB-P12 (soft delete por entidad maestra: aquí se optó por `estado` en catálogo y `anonimizado` en PII — decisión provisional a confirmar).
- Referencia polimórfica `ref_tipo/ref_id` sin FK: alternativa con columnas FK dedicadas se evalúa en Paso 08 (integridad) — se mantiene la versión simple mientras `ref_id` sea de solo lectura por app.

## 13. Salida

- Este documento → Paso 05 (`04_normalizacion/informe_normalizacion.md`).
