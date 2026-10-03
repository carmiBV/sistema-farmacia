# Paso 19 — Pruebas

- **Workflow:** 02_database_workflow · **Paso:** 19 (validación ejecutada de V1.0.0–V1.4.0)
- **Fecha:** 2026-10-02
- **Alcance:** migraciones, esquema (PK/FK/UNIQUE/CHECK), auditoría (triggers), permisos (RBAC) y transacciones.
- **Método:** instancia MySQL **efímera local** (Laragon MySQL 8.4.3, `mysqld` en `127.0.0.1:33061`, datadir temporal borrado al terminar), BD de prueba `farmacia_pru`, cliente `mysql` (sin Flyway ni Docker). Scripts aplicados en orden V1.0.0 → V1.4.0 con `--default-character-set=utf8mb4`. Suite de 54 pruebas automatizadas con verificación de código de salida y mensaje de error esperado por prueba.
- **Nota de seguridad (RNF-034):** solo se usaron credenciales de prueba efímeras generadas para esta instancia temporal; no se leyó ni se imprimió ningún secreto real ni de `.env`. La suite de pruebas es un artefacto temporal: **no se versiona**; este informe conserva el SQL ejecutado.

## 1. Migraciones (M1–M6)

| # | Prueba | Esperado | Observado | Resultado |
|---|---|---|---|---|
| M1 | `V1.0.0__esquema_base.sql` aplicada | exit 0 | exit 0 | PASS |
| M2 | `V1.1.0__indices.sql` aplicada | exit 0 | exit 0 | PASS |
| M3 | Sustitución de placeholders de `V1.2.0` + grants aplicados | sin `${...}` pendientes; exit 0 | sin pendientes; exit 0 | PASS |
| M4 | `V1.3.0__datos_iniciales.sql` aplicada | exit 0 | exit 0 | PASS |
| M5 | `V1.4.0__triggers_auditoria.sql` aplicada (DELIMITER `//`) | exit 0 | exit 0 | PASS |
| M6 | Re-ejecución de `V1.3.0` (idempotencia `INSERT IGNORE`) | exit 0 y `COUNT(auth_roles)=5` | exit 0; valor=5 | PASS |

## 2. Estructura del esquema (E1–E8)

```sql
SELECT COUNT(*) FROM information_schema.tables
 WHERE table_schema='farmacia_pru' AND table_type='BASE TABLE';          -- E1
SELECT COUNT(*) FROM information_schema.referential_constraints
 WHERE constraint_schema='farmacia_pru';                                  -- E2
SELECT COUNT(*) FROM information_schema.table_constraints
 WHERE constraint_schema='farmacia_pru' AND constraint_type='CHECK';      -- E3
SELECT COUNT(*) FROM information_schema.table_constraints
 WHERE constraint_schema='farmacia_pru' AND constraint_type='UNIQUE';     -- E4
SELECT COUNT(*) FROM information_schema.triggers
 WHERE trigger_schema='farmacia_pru';                                     -- E5
SELECT IS_NULLABLE FROM information_schema.columns
 WHERE table_schema='farmacia_pru' AND table_name='inventory_lots'
   AND column_name='reception_item_id';                                   -- E6 (F15)
SELECT COUNT(*) FROM information_schema.columns
 WHERE table_schema='farmacia_pru' AND table_name='catalog_categories'
   AND column_name='parent_id_key' AND generation_expression<>'';         -- E7a (F14)
SELECT COUNT(*) FROM information_schema.columns
 WHERE table_schema='farmacia_pru' AND table_name='sales_order_items'
   AND column_name='rx_item_id_key' AND generation_expression<>'';        -- E7b (F12)
SELECT COUNT(DISTINCT user) FROM mysql.user
 WHERE user IN ('app_rw','app_ro','svc_clinico','migrator');              -- E8
```

| # | Prueba | Esperado | Observado | Resultado |
|---|---|---|---|---|
| E1 | Tablas base | 42 | 42 | PASS |
| E2 | FK | 84 | 84 | PASS |
| E3 | CHECK | 37 | 37 | PASS |
| E4 | UNIQUE | 27 | 27 | PASS |
| E5 | Triggers | 2 | 2 | PASS |
| E6 | `inventory_lots.reception_item_id` NOT NULL (F15) | NO | NO | PASS |
| E7a | `catalog_categories.parent_id_key` columna generada (F14) | 1 | 1 | PASS |
| E7b | `sales_order_items.rx_item_id_key` columna generada (F12) | 1 | 1 | PASS |
| E8 | Usuarios de roles creados | 4 | 4 | PASS |

Los conteos coinciden con `13_sql/README.md` y `15_reportes/revision_dba.md`.

## 3. Fixtures

Sentencia única de datos base (usuarios, sucursal, proveedor, categoría, paciente, productos libre/controlado, orden de compra, recepción con 6 ítems de lote, 3 lotes con `reception_item_id`, enlace `purchase_reception_items.lot_id`, liberación de lotes, stock inicial), devolviendo los 15 ids usados por el resto de pruebas:

```sql
INSERT INTO auth_users(usuario,password_hash) VALUES ('quim_test','hash_prueba'),('cae_test','hash_prueba');
SET @uid  = (SELECT id FROM auth_users WHERE usuario='quim_test');
SET @uid2 = (SELECT id FROM auth_users WHERE usuario='cae_test');
INSERT INTO ops_stores(codigo,nombre) VALUES ('S1','Sucursal Centro');
SET @sid = LAST_INSERT_ID();
INSERT INTO ops_registers(store_id,codigo) VALUES (@sid,'C1');
INSERT INTO catalog_suppliers(identificacion,nombre) VALUES ('900111222-3','Distri Salud');
INSERT INTO catalog_categories(nombre) VALUES ('Analgesicos');
INSERT INTO catalog_patients(identificacion,nombre) VALUES ('12345678','Paciente Prueba');
INSERT INTO catalog_products(sku,nombre,principio_activo,presentacion,concentracion,condicion_venta)
VALUES ('SKU-LIB','Paracetamol 500','Paracetamol','Caja','500mg','libre'),
       ('SKU-CTL','Tramadol 50','Tramadol','Caja','50mg','controlado');
SET @pid  = (SELECT id FROM catalog_products WHERE sku='SKU-LIB');
SET @pidc = (SELECT id FROM catalog_products WHERE sku='SKU-CTL');
SET @patid = (SELECT id FROM catalog_patients WHERE identificacion='12345678');
SET @supid = (SELECT id FROM catalog_suppliers WHERE identificacion='900111222-3');
INSERT INTO purchase_orders(numero,supplier_id,creado_por) VALUES ('PO-1',@supid,@uid);
SET @oid = LAST_INSERT_ID();
INSERT INTO purchase_order_items(order_id,product_id,cantidad_pedida) VALUES (@oid,@pid,500),(@oid,@pidc,50);
INSERT INTO purchase_receptions(order_id,recepcionado_por) VALUES (@oid,@uid);
SET @rid = LAST_INSERT_ID();
INSERT INTO purchase_reception_items(reception_id,product_id,numero_lote,fecha_vencimiento,cantidad)
VALUES (@rid,@pid,'L1','2027-06-30',100),(@rid,@pid,'L2','2027-08-30',100),
       (@rid,@pid,'L3','2027-10-30',100),(@rid,@pid,'L4','2027-12-30',100),
       (@rid,@pid,'L5','2028-02-28',100),(@rid,@pidc,'LC1','2027-09-30',50);
SET @pri1 = (SELECT id FROM purchase_reception_items WHERE numero_lote='L1');
SET @pri2 = (SELECT id FROM purchase_reception_items WHERE numero_lote='L2');
SET @pri3 = (SELECT id FROM purchase_reception_items WHERE numero_lote='L3');
SET @pri4 = (SELECT id FROM purchase_reception_items WHERE numero_lote='L4');
SET @pri5 = (SELECT id FROM purchase_reception_items WHERE numero_lote='L5');
SET @pric = (SELECT id FROM purchase_reception_items WHERE numero_lote='LC1');
INSERT INTO inventory_lots(product_id,numero_lote,fecha_vencimiento,reception_item_id)
VALUES (@pid,'L1','2027-06-30',@pri1),(@pid,'L2','2027-08-30',@pri2),(@pidc,'LC1','2027-09-30',@pric);
SET @lot1 = (SELECT id FROM inventory_lots WHERE numero_lote='L1');
SET @lotc = (SELECT id FROM inventory_lots WHERE numero_lote='LC1');
UPDATE purchase_reception_items SET lot_id=@lot1 WHERE id=@pri1;
UPDATE purchase_reception_items SET lot_id=(SELECT id FROM inventory_lots WHERE numero_lote='L2') WHERE id=@pri2;
UPDATE purchase_reception_items SET lot_id=@lotc WHERE id=@pric;
UPDATE inventory_lots SET estado='liberado', liberado_por=@uid, liberado_at=UTC_TIMESTAMP(6),
       motivo_liberacion='Liberacion de prueba' WHERE id IN (@lot1,@lotc);
INSERT INTO inventory_stock(store_id,lot_id,stock_available) VALUES (@sid,@lot1,100),(@sid,@lotc,50);
SELECT CONCAT(@uid,'|',@uid2,'|',@sid,'|',@pid,'|',@pidc,'|',@patid,'|',@rid,'|',
              @pri1,'|',@pri2,'|',@pri3,'|',@pri4,'|',@pri5,'|',@pric,'|',@lot1,'|',@lotc);
```

- **FX — PASS:** 15 ids devueltos; cadena FK completa aceptada (orden de compra → recepción → ítems → lotes → stock; ciclo `lot_id` de `purchase_reception_items` poblado).

Las pruebas siguientes se ejecutaron con los ids resultantes de los fixtures; se muestran con las mismas variables de sesión.

## 4. Integridad (I1–I16)

| # | Prueba | SQL (condensado) | Esperado | Observado | Resultado |
|---|---|---|---|---|---|
| I1 | FK inexistente rechazada | `INSERT INTO inventory_stock(store_id,lot_id,stock_available) VALUES (@sid,999999,10)` | ERROR 1452 | ERROR 1452 | PASS |
| I2 | DELETE de store con hijos restringido | `DELETE FROM ops_stores WHERE id=@sid` | ERROR 1451 | ERROR 1451 | PASS |
| I3 | UNIQUE `(product_id,numero_lote)` | `INSERT INTO inventory_lots(product_id,numero_lote,fecha_vencimiento,reception_item_id) VALUES (@pid,'L1','2027-06-30',@pri3)` | ERROR 1062 | ERROR 1062 `Duplicate entry '1-L1'` | PASS |
| I4 | UNIQUE `reception_item_id` (F15, doble uso de recepción) | `INSERT INTO inventory_lots(...) VALUES (@pid,'Z1','2028-03-01',@pri1)` | ERROR 1062 | ERROR 1062 `key 'uq_inventory_lots_reception'` | PASS |
| I5 | F15 `reception_item_id NOT NULL` | `INSERT INTO inventory_lots(product_id,numero_lote,fecha_vencimiento,reception_item_id) VALUES (@pid,'Z0','2027-05-05',NULL)` | ERROR 1048 | ERROR 1048 | PASS |
| I6 | F14 dos categorías raíz con el mismo nombre | `INSERT INTO catalog_categories(nombre) VALUES ('Analgesicos')` | ERROR 1062 | ERROR 1062 `Duplicate entry 'Analgesicos-0'` | PASS |
| I7 | `stock_available` no negativo | `UPDATE inventory_stock SET stock_available=-1 WHERE store_id=@sid AND lot_id=@lot1` | ERROR 3819 | ERROR 3819 `chk_inventory_stock_available` | PASS |
| I8 | `chk_im_cantidad` cantidad=0 | `INSERT INTO inventory_movements(...) VALUES (@sid,@lot1,@pid,'entrada',0,1,@uid,'prueba','recepcion',@rid)` | ERROR 3819 | ERROR 3819 `chk_im_cantidad` | PASS |
| I9 | `chk_im_signo` signo fuera de catálogo | `... VALUES (@sid,@lot1,@pid,'entrada',1,5,@uid,...)` | ERROR 3819 | ERROR 3819 `chk_im_signo` | PASS |
| I10 | Doble autorización de ajuste (mismo actor) | `... 'ajuste_baja',1,-1,@uid,'doble autorizacion',@uid,@uid` (proponente=autorizador) | ERROR 3819 | ERROR 3819 `chk_im_doble_autorizacion` | PASS |
| I11 | Lote `liberado` sin actores | `INSERT INTO inventory_lots(...) VALUES (@pid,'Z2','2027-05-06',@pri4,'liberado')` | ERROR 3819 | ERROR 3819 `chk_inventory_lots_liberacion` | PASS |
| I12a | F10 liberación válida (RF-032) | `INSERT INTO inventory_lots(...) VALUES (@pid,'Z3','2027-05-07',@pri5,'liberado',@uid,UTC_TIMESTAMP(6),'Liberacion valida')` | exit 0 | exit 0 | PASS |
| I12b | F10 `liberado → retirado` (recall, RN-12/RF-061) | `UPDATE inventory_lots SET estado='retirado', retirado_at=UTC_TIMESTAMP(6) WHERE numero_lote='Z3'` | exit 0 | exit 0 | PASS |
| I13 | Ledger de ajuste sin autorizador | `INSERT INTO ctrl_ledger_entries(...) VALUES (@sid,@pidc,'ajuste',1,50,@uid,'ajuste sin autorizador','ajuste',99)` | ERROR 3819 | ERROR 3819 `chk_cle_ajuste_autorizado` | PASS |
| I14 | Ledger con mismo actor (doble autorización) | `... ,@uid,@uid,'doble autorizacion','ajuste',99)` | ERROR 3819 | ERROR 3819 `chk_cle_doble_autorizacion` | PASS |
| I15 | Movimiento sin `ref_tipo`/`ref_id` (RN trazabilidad) | `INSERT INTO inventory_movements(...) VALUES (@sid,@lot1,@pid,'entrada',1,1,@uid,'sin referencia')` | ERROR 3819 | ERROR 3819 `chk_im_ref` | PASS |
| I16a | Movimiento con `idempotency_key` | `... idempotency_key='idem-1'` | exit 0 | exit 0 | PASS |
| I16b | `idempotency_key` duplicada (RF unicidad) | `... idempotency_key='idem-1'` | ERROR 1062 | ERROR 1062 `uq_im_idem` | PASS |

## 5. Triggers de auditoría (T1–T5)

```sql
-- T1 (rechazo esperado) / T2 / T3 — trg_movements_sign (V1.4.0)
INSERT INTO inventory_movements(store_id,lot_id,product_id,tipo,cantidad,signo,usuario_id,motivo,ref_tipo,ref_id)
VALUES (@sid,@lot1,@pid,'salida',1, 1,@uid,'incoherente','venta',1);   -- T1: signo incoherente
VALUES (@sid,@lot1,@pid,'entrada',1, 1,@uid,'trigger ok','recepcion',@rid); -- T2: aceptado
VALUES (@sid,@lot1,@pid,'salida',1,-1,@uid,'venta ok','venta',1);      -- T3: aceptado
-- T4 (rechazo esperado) / T5 — trg_ledger_vs_stock
INSERT INTO ctrl_ledger_entries(store_id,product_id,tipo,cantidad,saldo_resultante,usuario_id,motivo,ref_tipo,ref_id)
VALUES (@sid,@pidc,'salida',1,0,@uid,'sin movimiento','venta',555);    -- T4: sin movimiento previo
-- T5a: movimiento de venta previo (ref_id=556) y T5b: ledger con el mismo ref_id
INSERT INTO inventory_movements(...) VALUES (@sid,@lotc,@pidc,'salida',1,-1,@uid,'venta tramadol','venta',556);
INSERT INTO ctrl_ledger_entries(...) VALUES (@sid,@pidc,'salida',1,0,@uid,'venta tramadol','venta',556);
```

| # | Prueba | Esperado | Observado | Resultado |
|---|---|---|---|---|
| T1 | `salida` con `signo=1` rechazada por trigger | ERROR 1644 (45000) con `incoherentes` | `ERROR 1644 ... cantidad o signo incoherentes con el tipo de movimiento` | PASS |
| T2 | `entrada` coherente aceptada | exit 0 | exit 0 | PASS |
| T3 | `salida` con `signo=-1` aceptada | exit 0 | exit 0 | PASS |
| T4 | Ledger de salida sin movimiento de inventario | ERROR 1644 con `sin movimiento` | `ERROR 1644 ... asiento de salida sin movimiento de inventario` | PASS |
| T5a | Movimiento de venta previo aceptado | exit 0 | exit 0 | PASS |
| T5b | Ledger de salida con movimiento previo aceptado | exit 0 | exit 0 | PASS |

Nota (verificado): el CHECK `chk_im_cantidad` se evalúa **antes** que el trigger, por lo que la prueba T1 usa `cantidad=1` para llegar al trigger.

## 6. Transacciones (X1)

```sql
START TRANSACTION;
INSERT INTO inventory_movements(store_id,lot_id,product_id,tipo,cantidad,signo,usuario_id,motivo,ref_tipo,ref_id,idempotency_key)
  VALUES (@sid,@lot1,@pid,'entrada',1,1,@uid,'txn','recepcion',@rid,'tx-1');
INSERT INTO inventory_movements(store_id,lot_id,product_id,tipo,cantidad,signo,usuario_id,motivo,ref_tipo,ref_id)
  VALUES (@sid,@lot1,@pid,'salida',1,1,@uid,'txn incoherente','venta',2);   -- falla por trigger (ERROR 1644)
SELECT COUNT(*) FROM inventory_movements WHERE idempotency_key='tx-1';       -- observado: 1 (antes del fallo)
ROLLBACK;
SELECT COUNT(*) FROM inventory_movements WHERE idempotency_key='tx-1';       -- esperado: 0
```

| # | Prueba | Esperado | Observado | Resultado |
|---|---|---|---|---|
| X1 | El error dentro de la transacción no contamina; `ROLLBACK` deja 0 filas | conteos `1` luego `0` | `c1=1 c2=0` | PASS |

**Límite declarado:** X1 valida atomicidad en una sola sesión. La concurrencia multi-conexión (locks, `SELECT ... FOR UPDATE`, deadlock) queda documentada en `12_transacciones_concurrencia` y no se ejecuta en este paso.

## 7. Permisos RBAC (P1–P11)

Conexión como cada usuario con su contraseña de prueba efímera (no reproducida aquí, RNF-034).

| # | Prueba | Esperado | Observado | Resultado |
|---|---|---|---|---|
| P1 | `app_rw` sin DELETE sobre `inventory_movements` | ERROR 1142 | `DELETE command denied to user 'app_rw'@'localhost'` | PASS |
| P2 | `app_rw` sin UPDATE sobre `inventory_movements` | ERROR 1142 | `UPDATE command denied ... 'app_rw'` | PASS |
| P3 | `app_rw` sí puede INSERT en `inventory_movements` | exit 0 | exit 0 | PASS |
| P4 | `app_ro` sí puede SELECT en `catalog_products` | exit 0 | exit 0 | PASS |
| P5 | `app_ro` sin INSERT en catálogo | ERROR 1142 | `INSERT command denied ... 'app_ro'` | PASS |
| P6 | `app_ro` sin SELECT sobre `catalog_patients` (PII) | ERROR 1142 | `SELECT command denied ... 'app_ro' ... 'catalog_patients'` | PASS |
| P7 | `svc_clinico` sí puede leer `catalog_patients` | exit 0 | exit 0 | PASS |
| P8 | `svc_clinico` registra en `audit_pii_access` | exit 0 | exit 0 | PASS |
| P9a | `migrator` puede `CREATE TABLE` | exit 0 | exit 0 | PASS |
| P9b | `migrator` puede `DROP TABLE` | exit 0 | exit 0 | PASS |
| P10 | `migrator` sin SELECT sobre `auth_users` | ERROR 1142 | `SELECT command denied ... 'migrator' ... 'auth_users'` | PASS |
| P11 | `migrator` sin `GRANT OPTION` | rechazo | `SELECT, GRANT command denied ... 'migrator'` | PASS |

Sentencias de P1–P11:

```sql
-- P1/P2 (rechazo), P3 (aceptación) — app_rw
DELETE FROM inventory_movements LIMIT 1;
UPDATE inventory_movements SET motivo='x' LIMIT 1;
INSERT INTO inventory_movements(store_id,lot_id,product_id,tipo,cantidad,signo,usuario_id,motivo,ref_tipo,ref_id)
VALUES (@sid,@lot1,@pid,'entrada',1,1,@uid,'rw insert','recepcion',@rid);
-- P4/P5/P6 — app_ro
SELECT COUNT(*) FROM catalog_products;
INSERT INTO catalog_categories(nombre) VALUES ('No Permitida');
SELECT COUNT(*) FROM catalog_patients;
-- P7/P8 — svc_clinico
SELECT COUNT(*) FROM catalog_patients;
INSERT INTO audit_pii_access(usuario_id,accion,paciente_id,motivo) VALUES (@uid,'consulta',@patid,'prueba de acceso');
-- P9a/P9b/P10/P11 — migrator
CREATE TABLE migrator_probe (id INT);
DROP TABLE migrator_probe;
SELECT usuario FROM auth_users LIMIT 1;
GRANT SELECT ON farmacia_pru.catalog_products TO 'migrator'@'%';
```

## 8. Resultado global

```
=== Fin: PASS=54 FAIL=0 ===
```

| Bloque | Pruebas | PASS | FAIL |
|---|---|---|---|
| Migraciones (M1–M6) | 6 | 6 | 0 |
| Estructura (E1–E8) | 9 | 9 | 0 |
| Fixtures (FX) | 1 | 1 | 0 |
| Integridad (I1–I16) | 17 | 17 | 0 |
| Triggers (T1–T5) | 6 | 6 | 0 |
| Transacciones (X1) | 1 | 1 | 0 |
| Permisos (P1–P11) | 11 | 11 | 0 |
| **Total** | **54** | **54** | **0** |

## 9. Verificación de criterios

- [x] Migraciones V1.0.0–V1.4.0 aplican sin error en orden (M1–M5)
- [x] `V1.3.0` re-ejecutable sin duplicar datos (M6)
- [x] 42 tablas / 84 FK / 37 CHECK / 27 UNIQUE / 2 triggers verificados contra el esquema real (E1–E5)
- [x] Hallazgos F12, F14, F15 verificados en el esquema desplegado (E6, E7a, E7b, I4, I5, I6)
- [x] Trazabilidad por lote recepción → lote → stock probada con cadena FK real (FX, I1)
- [x] RN-07/CA-10 (1 lote por ítem de recepción): UNIQUE + NOT NULL rechazan doble uso (I4, I5)
- [x] RN-12/RF-061 (recall): `liberado → retirado` aceptado (I12b); CHECK de liberación conserva RF-032 (I11, I12a)
- [x] Sin stock negativo (I7); sin movimientos con cantidad 0 ni signo inválido (I8, I9)
- [x] Doble autorización de ajustes exigida por CHECK en movimientos y ledger (I10, I13, I14)
- [x] Auditoría inalterable: append-only de `inventory_movements` verificado por permisos (P1, P2)
- [x] Triggers `trg_movements_sign` y `trg_ledger_vs_stock` activos y con mensajes esperados (T1–T5)
- [x] Atomicidad de transacciones con rollback completo (X1)
- [x] Privacidad de PII: `app_ro` sin acceso a `catalog_patients`, `svc_clinico` con acceso y registro en `audit_pii_access` (P6–P8)
- [x] Segregación de funciones: `migrator` con DDL sin SELECT ni GRANT OPTION (P9–P11)
- [x] Sin credenciales/secretos en este artefacto (RNF-034); solo credenciales de prueba efímeras en instancia temporal
- [x] Instancia, datadir y logs temporales eliminados al cierre; ningún proceso `mysqld` residual
- [x] Ningún RF/RNF/RN alterado (RC-02): la suite solo verifica lo ya aprobado

## 10. STATUS

```
STATUS: APPROVED
```

**Condiciones:**
1. Pruebas ejecutadas en instancia local efímera con datos sintéticos; **no** constituyen despliegue ni acreditan rendimiento (RNF-041: F6–F9/DB-P09 siguen abiertos fuera de este workflow).
2. Concurrencia multi-conexión no se ejecuta aquí (ver `12_transacciones_concurrencia`); queda para el Paso 17+ cuando exista entorno de despliegue.
3. Cualquier cambio en SQL (nueva versión V1.x) requiere re-ejecutar esta suite antes de aprobar el despliegue.
