# Paso 08 — Integridad

- **Workflow:** 02_database_workflow · **Paso:** 08
- **Skills utilizados:** `database-schema-designer` + `databases` (MySQL)
- **Regla §7:** cada restricción trazada a RF/RNF/RN/decisión. Sin restricción "huérfana".
- **Alcance:** diseño; el SQL es del Paso 15 (requiere `STATUS: APPROVED`).

## 1. PK y FK

| Tabla | PK | FK (con `ON DELETE` explícito) | Fuente |
|---|---|---|---|
| auth_* | id | roles→permisos/user_roles: CASCADE solo en asociativas | RF-001 |
| ops_stores / ops_registers | id | registers.store → RESTRICT | RF-010 |
| system_config | config_key | store_id → RESTRICT (NULL = global) | RF-100, §21 |
| catalog_products | id | precios/promos/lotes/ítems → RESTRICT | RF-020 |
| catalog_patients / prescribers | id | recetas/ventas → RESTRICT | RF-002/003, RN-13 |
| purchase_* | id | reception_items.reception → RESTRICT; reception.order → RESTRICT | RF-030/031 |
| inventory_lots | id | stock/movimientos/transfer_items/ventas → RESTRICT | RF-040, RN-01 |
| inventory_stock | (store_id, lot_id) | store, lot → RESTRICT | RF-040 |
| inventory_movements | id | movimiento_ref_id → RESTRICT (compensatorio) | RN-10, RNF-023 |
| inventory_transfers/… | id | store origen/destino → RESTRICT; items.lote → RESTRICT | RF-045, RN-08 |
| sales_orders | id | items/pagos/devoluciones → RESTRICT; paciente → RESTRICT | RF-050, RN-13 |
| rx_prescriptions | id | paciente/prescriptor → RESTRICT | RF-052 |
| rx_prescription_items | id | prescription → RESTRICT; ventas Ítem → RESTRICT | RF-053 |
| ctrl_* | id / (store, product) | producto/sucursal → RESTRICT | RF-055 |
| audit_* | id | usuario → **RESTRICT** (no borrar usuario con historial) | RF-090/091, RNF-046 |
| idempotency_keys / outbox_events | id | — | RN-09, §16 |

**Por qué RESTRICT:** ninguna fila transaccional puede perderse por borrado en cascada (RN-10, RNF-023). Los únicos CASCADE son asociativas de catálogo que no contienen datos transaccionales.

**DEC-02 (ref_tipo/ref_id sin FK):** aceptado y documentado en Paso 04 §11/Paso 05 §4.7; control por validación de aplicación + pruebas (Paso 19). Alternativa (columnas FK dedicadas) queda como evolución si se detecta corrupción en pruebas.

## 2. UNIQUE (trazabilidad)

| Restricción | Tabla | Fuente |
|---|---|---|
| `usuario` | auth_users | RF-001 |
| `token_hash` | token_blacklist | §15 |
| `codigo` (sucursal) / `(store_id, codigo)` (caja) | ops_stores / ops_registers | RF-010 |
| `config_key` | system_config | RF-100, §21 |
| `sku` | catalog_products | RF-020 |
| `identificacion` | suppliers, patients, prescribers | RF-002/003/030, RN-13 |
| `(product_id, numero_lote)` | inventory_lots | RF-040, RN-12 (identidad de lote) |
| `numero` | purchase_orders, sales_orders | §6 (idempotencia natural) |
| `reference` | payments_transactions | §6 |
| `idempotency_key` | receptions, transfers, sales_orders, payments, movements | RN-09, RNF-022, CA-07 |
| `reception_item_id` | inventory_lots | trazabilidad lote→recepción (CA-10) |
| `(purchase…items)` únicas por hijo | detalle | 2NF |
| `token_hash`, `(tipo,product,store_id_key,fecha_generada)` alerts | idem | sin duplicados por reintento, **también con `store_id IS NULL`** (F12) |
| `(product_id,store_id_key,vigente_desde)` precios | idem | precio global (`store_id IS NULL`) sin duplicados por reintento (F12) |

## 3. CHECK y reglas de negocio ejecutables en MySQL 8

| Restricción | Fuente | Nota |
|---|---|---|
| `stock_available >= 0`, `stock_reserved >= 0`, `stock_sold >= 0` | RN-02, RNF-020, RF-047 | **corazón anti-stock-negativo** |
| `cantidad_dispensada BETWEEN 0 AND cantidad_prescrita` | RF-053 | saldo global ≥ 0 |
| `saldo >= 0` (ctrl_balances), `saldo_resultante >= 0` | RF-055, RN-05 | |
| `cantidad > 0` en movimientos (dirección en `signo`) | RN-06, RN-10 | reserva vs disponible: lock en misma transacción (Paso 12) |
| cantidades > 0 en ítems; `total/precio/subtotal >= 0` | RF-031/050 | |
| `cantidad_recibida <= cantidad_pedida` | RF-031 | |
| `estado='liberado' → (liberado_por, liberado_at, motivo_liberacion NOT NULL)` | RF-032, CAM-002-b, F10 | **implicación**, no bicondicional: `estado <> 'liberado' OR (… NOT NULL)`. La bicondicional aprobada en Paso 04 hacía imposible `liberado → retirado`/`→ cuarentena` (recall, RN-12/RF-061): con `liberado_por` ya poblado, `FALSE = TRUE` → violación. Corregido en F10. |
| `store_origen <> store_destino` (transferencias) | RN-08 | |
| `ref_tipo IS NOT NULL OR tipo IN (ajustes manuales)` (movimientos) | RN-06 | |
| `paciente_id IS NOT NULL OR ...` (ventas con devolución) | RF-060 | simplificado: devolución exige venta con paciente |
| `audit_pii_access`: paciente o receta no nulos | RF-091, RN-13 | |
| `descuento_pct > 0 AND <= 100`; `hasta >= desde` | RF-020 | |
| ENUM de estados (lote, transferencia, reservas) | RN-03, RN-08, §4 | conjunto cerrado |

### 3.1 Reglas que MySQL CHECK **no** puede expresar (se aplica en servidor, Paso 12)

| Regla | Fuente | Mecanismo de aplicación |
|---|---|---|
| Doble autorización en ajustes de controlados: `autorizador <> proponente` y ambos NOT NULL cuando el producto es controlado | RF-046, RN-06 | transacción verifica `catalog_products.condicion_venta`; CHECK genérico `autorizador IS NULL OR autorizador <> proponente` + regla de dominio en app |
| FEFO: no salir de lote vencido/cuarentena/retirado | RF-043, RN-03 | selección de lote en app + `estado`/`fecha_vencimiento`; pruebas Paso 19 |
| `quimico_verificador_id NOT NULL` si algún ítem con condición ≠ libre | RF-054, RNF-030 | servidor antes del COMMIT |
| Transiciones de estado coherentes (transferencia, recepción) | RN-07/08 | máquina de estados en servidor + auditoría |
| Devolución no reingresa a vendible sin evaluación | RN-11, RF-060 | solo la evaluación posterior genera movimiento `entrada` |
| Asiento de libro en cada movimiento de controlado (incl. transferencias origen/destino) | RF-045, RF-055 | misma transacción (RNF-021) |
| Idempotencia efectiva (mismo resultado en reintento) | RN-09 | `idempotency_keys` + UNIQUE + relectura |

**Principio (§7):** no se delega toda la integridad a la aplicación: lo expresable está en BD (§3); lo no expresable queda listado con mecanismo y prueba (§3.1).

## 4. NOT NULL y DEFAULT

| Campo | Regla | Fuente |
|---|---|---|
| `lote, vencimiento, cantidad` en recepción | NOT NULL | RF-031, RNF-025 |
| `motivo` en movimientos y asientos | NOT NULL | RN-06, RF-090 |
| `lot_id`, `store_id` en movimientos/ventas ítems | NOT NULL | RN-01 |
| `usuario_id` en ventas/movimientos/asientos/auditoría | NOT NULL | RF-090, RN-06 |
| `created_at` | NOT NULL DEFAULT CURRENT_TIMESTAMP(6) | RNF-047 (UTC) |
| estados | NOT NULL DEFAULT (borrador/recibida/pending…) | RN-07/08 |
| `stock_*` | NOT NULL DEFAULT 0 | RF-040 |

## 5. Defaults peligrosos evitados

- Ninguna cantidad con DEFAULT que oculte error de aplicación (DEFAULT 0 solo donde el valor inicial semántico es 0: stock inicial, dispensado).
- `channel DEFAULT 'pos'` (D-4) y `condicion_venta` **sin** DEFAULT (obligatoria).

## 6. Integridad referencial × retención/anonimización

- RNF-039/RN-10: anonimizar `catalog_patients` no borra filas → FKs nunca se rompen.
- `ON DELETE RESTRICT` en pacientes/prescriptores lo garantiza estructuralmente.

## 7. Pendientes que afectan integridad

- DB-P09: si precios son globales, `store_id` en `catalog_prices` pasa a NOT NULL fijo → CHECK/UNIQUE se rehacen (migración).
- DB-P12: si una entidad maestra exige borrado físico, quitar RESTRICT implicaría revisión (§26).
- Fraccionamiento de unidades (supuesto Paso 07 §2) altera CHECK de cantidades si procede.

**Regla general aplicada (F3, F12):** MySQL trata los `NULL` como distintos en `UNIQUE`, así que
un índice único que incluya una columna nullable **no** impide duplicados cuando esa columna es
`NULL`. En `sales_order_items`, `catalog_prices` e `inventory_alerts` se usa una **columna
generada `GENERATED ALWAYS AS (IFNULL(<col>, 0)) STORED`** y el `UNIQUE` se declara sobre ella.
`0` no colisiona con ids reales (`AUTO_INCREMENT` inicia en 1). Aplica a `rx_item_id` (F3) y a
`store_id` de precios y de alertas (F12). Todo `UNIQUE` futuro con columna nullable debe seguir
este patrón.

**Excepciones intencionales (no corregir).** Un `UNIQUE` sobre columna nullable es correcto
cuando el `NULL` significa «sin valor» y **no** debe deduplicarse. Verificado el 2026-10-02
barrido completo de los 27 `UNIQUE` de `V1.0.0`:

| Restricción | Por qué `NULL` debe repetirse |
|---|---|
| `idempotency_key` en `purchase_receptions`, `inventory_movements`, `inventory_transfers`, `sales_orders`, `payments_transactions` | `NULL` = el cliente no envió clave; cada operación sin clave es distinta. Si se deduplicaran los `NULL` no se podría registrar ninguna segunda operación. La protección RN-09 actúa **solo cuando la clave viene poblada**. |
| `payments_transactions.reference` | `NULL` = pago sin referencia externa (efectivo). Deben admitirse muchos. |

**F15 (RESUELTO el 2026-10-02 — se adopta la opción 1).** `UNIQUE(reception_item_id)` en
`inventory_lots` pretendía garantizar «1 ítem de recepción → 1 lote» (CA-10, trazabilidad), pero
con `reception_item_id IS NULL` la restricción no aplica y se admitían lotes **sin** enlace a
recepción. Decisión: **(a) `reception_item_id NOT NULL`** en `inventory_lots`
(`03_modelo_logico`, `V1.0.0`). Todo lote nace de una recepción confirmada (RN-07) y la columna
ya figuraba como «origen recepción» en `02_modelo_conceptual`; el `UNIQUE` pasa a garantizar la
relación 1:1 sin excepciones. La reserva de la opción (a) —«imposible para migración/semillas»—
no aplica: `V1.3.0` solo siembra roles, no hay datos previos cargados y los scripts aún no se
han ejecutado; cualquier carga futura de lotes sin recepción queda rechazada por diseño y exigiría
revisión (§26). El ciclo `purchase_reception_items.lot_id ↔ inventory_lots.reception_item_id`
sigue roto por el mismo motivo: `lot_id` se puebla al confirmar, con el ítem ya existente.
Opciones (b) `CHECK … origen='seed'` y (c) regla de aplicación quedan descartadas.
`inventory_lots.reception_item_id` sale de la lista de excepciones intencionales de arriba.

## 8. Salida

- → Paso 09: `07_seguridad/seguridad.md`.
