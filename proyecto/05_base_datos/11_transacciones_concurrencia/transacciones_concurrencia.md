# Paso 12 — Transacciones y concurrencia

- **Workflow:** 02_database_workflow · **Paso:** 12
- **Skills utilizados:** `databases` (MySQL/InnoDB)
- **Fuentes:** RNF-005/020/021/022, decisiones §4 (híbrida), §5 (ACID+Saga), §6 (idempotencia), CA-03, CA-07.

## 1. Fronteras transaccionales (RNF-021)

| # | Transacción | Contenido (una sola Tx InnoDB) | Fuente |
|---|---|---|---|
| T-1 | **Confirmar dispensación/venta** | validar lote apto (FEFO) → `UPDATE inventory_stock` (`-available`, `+sold`) con `WHERE version=?` → INSERT `inventory_movements(salida)` → INSERT/UPDATE ítems de receta (`cantidad_dispensada`, `version+1`) → INSERT asientos `ctrl_ledger_entries` + UPDATE `ctrl_balances` si producto controlado → UPDATE `sales_orders` total → INSERT `outbox_events` | RF-051, RF-053, RF-055, RN-01/04/05, §16 |
| T-2 | **Cobro** | INSERT `payments_transactions` + INSERT `idempotency_keys` + INSERT `outbox_events`; si hay gateway: **fin de Tx aquí**, respuesta externa después (saga) | §5, §6, RNF-042 |
| T-3 | **Confirmar recepción** | INSERT `inventory_lots` (o upsert por `product_id, numero_lote`) → `inventory_stock +available` → `inventory_movements(entrada)` → `ctrl_ledger(entrada)` si controlado → `purchase_receptions.estado='confirmada'` → outbox | RN-07, RF-031/032/055 |
| T-4 | **Despachar transferencia** | `FOR UPDATE` sobre stock de origen por lote → `disponible -` → `movements(transferencia_salida)` → `transfer_items.cantidad_despachada` → `estado='desparchada'` → asiento libro **origen** (controlados) | RF-045, RN-08, CAM-002-c |
| T-5 | **Recibir transferencia** | `FOR UPDATE` stock destino → `+available` → `movements(transferencia_entrada)` → `cantidad_recibida` → `estado='recibida'` → asiento libro **destino** | idem |
| T-6 | **Devolución** | insert `sales_returns/items` → según evaluación: movimiento `devolucion` (NO a `available`) o, tras evaluación, `entrada` separada + `estado_evaluacion` | RN-11, §22 |
| T-7 | **Incidente RF-047** | leer `inventory_stock` vs `SUM(movimientos)` → INSERT `inventory_incidents` → ajuste autorizado: `movements(ajuste_*)` + `ctrl_ledger(ajuste)` con proponente/autorizador → cerrar incidente | RF-047, RF-046 |
| T-8 | **Outbox worker** | UPDATE estado de `outbox_events` (transacción corta; el efecto externo queda fuera) | §16 |

**Prohibido:** mantener Tx abierta esperando gateway/pago externo (§5 regla).

## 2. Nivel de aislamiento

- **READ COMMITTED** como nivel de trabajo de aplicación: menos gap locks que REPEATABLE READ (default de InnoDB), menor riesgo de deadlock con 48 cajas concurrentes; la correctez no depende del nivel sino de:
  - bloqueos explícitos `SELECT … FOR UPDATE` en los saldos (§4 regla);
  - checks `>= 0` en BD (última línea de defensa);
  - versión optimista en escrituras de baja contención.
- Justificación con RNF-005/020: la carrera crítica (dos cajas descuentan el mismo lote) se resuelve con lock/optimismo, no con SERIALIZABLE global (más costo, sin requisito que lo pida).

## 3. Mecanismos por operación

| Operación | Mecanismo | Fuente |
|---|---|---|
| Descuento de stock por lote | `UPDATE inventory_stock SET stock_available=stock_available-? , version=version+1 WHERE store_id=? AND lot_id=? AND stock_available>=? AND version=?` → si filas afectadas=0 → reintentar (optimista) **o** `SELECT … FOR UPDATE` previo en T-1 cuando el lote se acaba de elegir (FEFO) | §4, RN-02 |
| Reserva (§4) | `FOR UPDATE` breve sobre `inventory_stock` al crear reserva; expiración por job con `SKIP LOCKED` (MySQL 8) para workers múltiples | §4, DB-P01/02 |
| Saldo de receta | UPDATE de `rx_prescription_items` en la misma T-1 con `version` optimista → RF-053 global | RF-053, S-9 |
| Saldo de libro | UPDATE `ctrl_balances` con `version` optimista + asiento en la misma Tx | RF-055 |
| Idempotencia | INSERT `idempotency_keys` **primero** (UNIQUE); si duplicado → devolver resultado almacenado, sin re-ejecutar | RN-09, CA-07, §6 |
| Doble autorización | en T-7: verificar `autorizador <> proponente` y `condicion_venta='controlado'` antes de INSERT; trigger como red | RF-046, RN-06 |
| Devolución vs stock | T-6 nunca escribe `stock_available +` (RN-11) | RN-11 |

## 4. Orden de acceso y deadlocks (§4)

Orden fijo de tablas dentro de cualquier Tx que toque varias:

1. `sales_orders` / `inventory_transfers` / `purchase_receptions` (cabecera)
2. `rx_prescription_items`
3. `inventory_stock` (ordenado por `lot_id` ASC)
4. `inventory_movements`
5. `ctrl_balances` → `ctrl_ledger_entries`
6. `payments_transactions`, `outbox_events`, `idempotency_keys` (si aplica al inicio, ver §6)

- Regla: las cabeceras se insertan/lockean primero que los detalles; los saldos antes que sus logs.
- Diagnóstico: `innodb_print_all_deadlocks=ON` + `SHOW ENGINE INNODB STATUS` (skill).
- Reintentos: backoff con jitter limitado (número de reintentos: parámetro de aplicación; no inventar valor).

## 5. Saga de pagos (§5)

```
T-2 (stock reservado o venta pendiente + outbox)  →  worker consume outbox
   →  llama gateway (SIN Tx)  →  webhook/confirmación  →  T-2b confirma venta
   o bien  →  T-2c compensa (libera reserva/movimiento compensatorio)
```
- `idempotency_key` y `reference` UNIQUE protegen reintento de cobro (CA-07).
- Medios de pago de tercero: timeout/reintentos controlados en aplicación (RNF-042) — fuera de BD.

## 6. Concurrencia multi-sucursal

- Stock es por (sucursal, lote): dos sucursales no compiten por la misma fila (RNF-010).
- **Receta global (RF-053):** la contención entre sucursales se concentra en `rx_prescription_items` (una fila) — actualización corta al final de T-1; si contention real aparece, evolución: bloqueo optimista con reintento (ya previsto con `version`).
- Libro por sucursal: sin cruce entre sucursales salvo transferencias (T-4/T-5 secuenciales por diseño).

## 7. Verificabilidad (CA-03, CA-07)

| Criterio | Verificación (Paso 19) |
|---|---|
| CA-03 sin stock negativo | test de carrera: N hilos descuentan el mismo lote con stock insuficiente → filas afectadas 0, stock final ≥ 0 |
| CA-03 sin lote no apto | dispensación con lote vencido/cuarentena/retirado → rechazada |
| CA-07 idempotencia | misma `idempotency_key` reenviada → un solo efecto en venta/pago/recepción/transferencia |
| RF-053 | dispensación parcial simultánea en dos sucursales → saldo global nunca negativo |
| RF-045 | despacho/recepción de controlado → asientos en ambos libros, una sola Tx por lado |
| Deadlocks | prueba de estrés con 48 cajas virtuales → 0 deadlocks no manejados |

## 8. Salida

- → Paso 13: `12_migraciones/plan_migraciones.md`.
