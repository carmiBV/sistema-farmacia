# Paso 11 — Índices y rendimiento

- **Workflow:** 02_database_workflow · **Paso:** 11
- **Skills utilizados:** `database-schema-designer` + `databases` (MySQL)
- **Regla §12:** ningún índice sin consulta/patrón de acceso que lo justifique. InnoDB **ya indexa** cada columna FK → aquí solo índices de acceso/único (los UNIQUE se listan en Paso 08).

## 1. Índices por consulta/patrón de acceso

| # | Índice (tabla, columnas) | Consulta / patrón | Fuente |
|---|---|---|---|
| I-01 | `inventory_lots(product_id, estado, fecha_vencimiento)` | FEFO: `WHERE product_id=? AND estado='liberado' ORDER BY fecha_vencimiento` (igualdad antes que rango/orden) | RF-043, RN-03, CA-03 |
| I-02 | `inventory_stock(store_id, lot_id)` (PK) + `stock_available>0` | disponibilidad por producto en sucursal: `JOIN inventory_lots ON … WHERE product_id=? AND store_id=?` → índice compuesto `inventory_stock(lot_id, store_id)` ayuda al join inverso | RF-040, RNF-006 |
| I-03 | `inventory_movements(lot_id, created_at)` | trazabilidad por lote (CA-10), conciliación RNF-024 | RNF-045, CA-10 |
| I-04 | `inventory_movements(store_id, created_at)` | movimientos por sucursal/turno, auditoría RF-090 | RF-090 |
| I-05 | `inventory_movements(ref_tipo, ref_id)` | reversa: dado un venta/recepción, ver sus movimientos | CA-10 |
| I-06 | `sales_order_items(lot_id)` | recall: dispensaciones de un lote | RF-061, RN-12 |
| I-07 | `sales_orders(store_id, created_at)` | reportes de ventas por sucursal/fecha (RF-070) | RF-070 |
| I-08 | `sales_orders(paciente_id, created_at)` | historial de compras del paciente (trazabilidad por paciente RNF-048) | RNF-048 |
| I-09 | `sales_returns(order_id)` | devoluciones de una venta (§22) | RF-060 |
| I-10 | `payments_transactions(order_id)` | estado de pago de una venta (saga §5) | RF-050 |
| I-11 | `rx_prescriptions(patient_id, fecha)` | recetas del paciente (consulta clínica) | RF-052, RN-13 |
| I-12 | `ctrl_ledger_entries(store_id, product_id, created_at)` | saldo por período, reporte regulatorio RF-071 | RF-055, RF-071 |
| I-13 | `ctrl_ledger_entries(ref_tipo, ref_id)` | dado un venta/transferencia, ver asientos | CA-10 |
| I-14 | `audit_operations(entidad, entidad_id, created_at)` | auditoría de una entidad (RF-090) | RF-090 |
| I-15 | `audit_pii_access(paciente_id, created_at)` y `(prescription_id, created_at)` | RF-091 por persona/receta | RF-091, RN-13 |
| I-16 | `inventory_transfers(store_origen_id, estado)`, `(store_destino_id, estado)` | bandeja de transferencias pendientes | RF-045, RN-08 |
| I-17 | `inventory_incidents(store_id, estado)` | incidentes abiertos por sucursal (RF-047) | RF-047 |
| I-18 | `inventory_alerts(tipo, estado, fecha_generada)` | panel de alertas (RF-044) | RF-044 |
| I-19 | `outbox_events(estado, created_at)` | worker: eventos pendientes (§16) | §16 |
| I-20 | `idempotency_keys(expires_at)` | purga por retención (§6, 30 d propuestos) | §6 |
| I-21 | `token_blacklist(expires_at)` | purga de tokens (§15) | §15 |
| I-22 | `purchase_receptions(order_id)` | recepciones de una orden | RF-031 |
| I-23 | `inventory_reservations(store_id, product_id, status, expires_at)` | job de expiración + consulta de reserva (§4) | §4, DB-P02 |

## 2. Índices NO creados (y por qué — §12)

| Candidato descartado | Motivo |
|---|---|
| Índices en cada FK | los crea InnoDB; duplicarlos solo cuesta escritura |
| `catalog_products(nombre)` / FULLTEXT | búsqueda por nombre no es RF; §20.1: no habilitar por disponibilidad. Se añade solo con RF de búsqueda |
| `inventory_stock(product_id)` directo | se resuelve vía `inventory_lots` (I-01) y join; medir antes de añadir |
| Índices sobre `JSON` de auditoría | acceso por `entidad_id` (I-14), no por contenido |
| Índices de ordenamiento en reportes | primero el filtro (fecha/sucursal); el orden se evalúa con `EXPLAIN` |

## 3. Patrones de consulta clave y su índice

| CA/RF | Consulta | Índice que la sostiene |
|---|---|---|
| CA-03/RF-043 | elegir lote FEFO en dispensación | I-01 + I-02 |
| CA-10/RN-12 | lote → recepción, stock, dispensaciones | I-03, I-06, `purchase_reception_items.lot_id` (FK) |
| CA-10 | venta → paciente, receta, lote, responsable | PK `sales_orders` + I-11 + FK `rx_item_id` |
| RNF-048 | trazabilidad por paciente/receta con latencia `null` | I-08, I-11, I-15 |
| RF-071 | controlados por período | I-12 |
| RF-047 | stock vs consumo de un lote | I-03 |

## 4. Estrategia de rendimiento global (§12, §18)

1. Transacciones cortas (§5): sin red dentro de la transacción (pagos por outbox/saga).
2. Pool de conexiones 50–100 (§19) antes que subir `max_connections`.
3. Escalación si medición lo exige (§18 orden): queries → índices → pooling → réplica lectura → particionado (>1M filas §12) → más.
4. Reportes: tabla resumen `report_sales_daily` cada 5 min (§13, DB-P07); el OLTP no atiende RF-070 pesado en horario pico sin medir.
5. `EXPLAIN FORMAT=TREE` obligatorio antes de aprobar índices en Paso 19 (skill).

## 5. Capacidad y escenario 10x (CA-05/CA-06, RNF-001/011)

- 25.000 txn/h ≈ 7 txn/s sostenidas (referencia a validar): perfil transaccional corto con índices compuestos; **sin** presión de particionado inicial.
- 10x (250.000 txn/h ≈ 69/s): el diseño escala verticalmente primero (buffer pool, pool de conexiones); la réplica lectura y particionado son los saltos previstos en §18 sin rediseño de dominio (RNF-010).
- `consultas_por_hora_estimadas: null` → no se dimensionan índices de consulta hasta conocer el volumen (supuesto abierto).

## 6. Salida

- → Paso 12: `11_transacciones_concurrencia/transacciones_concurrencia.md`.
