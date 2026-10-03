# 13_sql · Scripts SQL (Paso 15)

- **Workflow:** 02_database_workflow · **Paso:** 15 (generación SQL)
- **Motor:** MySQL 8.4 LTS / InnoDB (`05_modelo_fisico`)
- **Skills utilizados:** `database-schema-designer` + `databases`
- **Puerta de entrada cumplida:** `15_reportes/revision_dba.md` → `STATUS: APPROVED` ·
  `ADR-001` → `Aceptado` · `08_final_recommendation.md` → `Aprobado` (CAM-006)

## 1. Estado

| | |
|---|---|
| Generados | ✅ 5 scripts Flyway |
| Ejecutados | ⚠️ **validados en instancia efímera de pruebas** (2026-10-02, 54/54 PASS — `14_pruebas/resultados_pruebas.md`). **Sin desplegar en base real**: Pasos 16–17 pendientes |
| Conexión a base real | ❌ ninguna. Sin credenciales en el repo (RNF-034) |
| Credenciales | ❌ ninguna. V1.2.0 usa placeholders de Flyway |

## 2. Contenido

| Script | Contenido | Fuente |
|---|---|---|
| `V1.0.0__esquema_base.sql` | 42 tablas, PK/FK/UNIQUE/CHECK/ENUM/timestamps | Paso 04 + 06 + 07 |
| `V1.1.0__indices.sql` | índices de acceso I-01…I-23 | Paso 11 |
| `V1.2.0__grants.sql` | usuarios `app_rw`, `app_ro`, `svc_clinico`, `migrator` | Paso 09 |
| `V1.3.0__datos_iniciales.sql` | 5 roles de RF-001. **Sin** `system_config` | Paso 13 §1 F4 |
| `V1.4.0__triggers_auditoria.sql` | `trg_movements_sign`, `trg_ledger_vs_stock` | Paso 10a §3 |

Orden estricto V1.0.0 → V1.1.0 → V1.2.0 → V1.3.0 → V1.4.0 (Flyway por nombre).

## 3. Cobertura de índices (§12)

| Grupo | Índices |
|---|---|
| Creados en `V1.1.0` | I-01, I-03, I-04, I-05, I-07, I-08, I-11, I-12, I-13, I-14, I-15a/b, I-16a/b, I-17, I-18, I-19, I-20, I-23 |
| Creados en `V1.0.0` junto a la tabla | I-02 (`ix_inventory_stock_lot_store`), I-21 (`ix_token_blacklist_expires`) |
| Satisfechos por el índice automático de la FK | I-06, I-09, I-10, I-22 |
| Ninguno | I-01…I-23 sin justificar de consulta |

## 4. Hallazgos detectados al generar y cómo se resolvieron

Registrados en `15_reportes/revision_dba.md` §1 (F10–F13) y §7 (F14–F15). **No alteran RF/RNF/RN** (RC-02).

| ID | Sev. | Problema | Resolución en SQL |
|---|---|---|---|
| **F10** | Alto | CHECK bicondicional de `inventory_lots` hacía imposible `liberado → retirado` (recall, RN-12/RF-061). | Reescrito como implicación en `03_modelo_logico`, `06_integridad` y `V1.0.0`. |
| **F11** | Medio | `07_seguridad §1` prohibía UPDATE en `sales_orders`/`payments_transactions`, incompatible con T-1 y T-2b (Paso 12). | Prohibición dura = **DELETE**; append-only real (`audit_*`, `inventory_movements`, `ctrl_ledger_entries`) = SELECT+INSERT. Matriz en `V1.2.0`. |
| **F12** | Bajo | `UNIQUE` con columna nullable no impide duplicados cuando la columna es `NULL` en MySQL. Afecta `inventory_alerts(tipo,product_id,store_id,fecha_generada)` (alertas globales, `store_id NULL`) y `catalog_prices(product_id,store_id,vigente_desde)` (precio global). | **Cerrado.** Columna generada `store_id_key BIGINT GENERATED ALWAYS AS (IFNULL(store_id,0)) STORED` y `UNIQUE` sobre ella en ambas tablas (patrón ya aprobado en F3). Sin cambios en RF/RNF/RN. |
| **F13** | Bajo | `03_modelo_logico` anota `ON DELETE CASCADE` en `purchase_order_items.order_id`; `06_integridad §1` y `05_modelo_fisico §3` exigen RESTRICT en transaccionales. | Se aplica **RESTRICT** (RN-10 / RNF-023: ninguna fila transaccional se pierde por cascada). |
| **F14** | Bajo | Dos categorías **raíz** con el mismo `nombre` eludían `UNIQUE(nombre, parent_id)` en MySQL (los `NULL` se distinguen). Defecto clase F12 detectado por barrido de los 27 `UNIQUE`. | `parent_id_key GENERATED ALWAYS AS (IFNULL(parent_id,0)) STORED` + `UNIQUE(nombre, parent_id_key)` en `V1.0.0` y `03_modelo_logico`. |
| **F15** | Bajo | `UNIQUE(reception_item_id)` no restringe lotes con `reception_item_id IS NULL`: se admiten lotes sin trazabilidad de recepción (CA-10). | **Cerrado (opción 1, `revision_dba §8`).** `reception_item_id BIGINT UNSIGNED NOT NULL` en `V1.0.0` y `03_modelo_logico`: sin recepción no hay lote. Sin cambios RF/RNF/RN. |

## 5. Limitaciones conocidas

1. **Carga de lotes sin recepción rechazada** por `reception_item_id NOT NULL` (F15 cerrado):
   cualquier siembra o migración de lotes externos exige una ronda de revisión (§26 de
   `revision_dba.md`).
2. **`ref_tipo`/`ref_id` sin FK** (DEC-02): referencias polimórficas validadas solo por aplicación. Evolución prevista a columnas FK dedicadas si las pruebas (Paso 19) detectan corrupción.
3. **RF-091** (registrar *cada* consulta PII) no es ejecutable en BD: MySQL no tiene triggers de SELECT. Dependencia de aplicación en `07_seguridad §4`.
4. **`V1.3.0` no siembra parámetros**: RF-100, RNF-043 y RNF-048 siguen `null` (pendientes del cliente, RC-03). RPO/RTO, retenciones, disponibilidad y 25.000 txn/h **sí quedaron aprobados** en `02_configuracion/perfil_carga.yaml` por **CAM-005** (2026-10-02), pero **no se siembran** en `system_config`: falta fijar la convención de claves (`store.<clave>` vs global, `06_integridad §2`/F4) y eso exige una pasada de revisión (§26 de `revision_dba.md`).
5. **`V1.4.0` trigger `trg_ledger_vs_stock`** depende del orden de escritura de `11_transacciones_concurrencia §4` (movimientos antes que libro). Si la aplicación invierte el orden, el trigger bloquea legítimamente → es una red de seguridad, no un bug.
6. **`V1.4.0` usa `DELIMITER //`**: el cuerpo del trigger contiene `;` y el parser por defecto de Flyway separaría en `;` cortando el `END IF` (flyway/flyway#2666). La documentación de Flyway para MySQL declara que respeta `DELIMITER`. Si el runner de turno no lo interpretara, aplicar **solo** `V1.4.0` con el cliente `mysql` y dejar la migración marcada como aplicada manualmente.

## 6. Cómo se aplica (Pasos 16–17; autorización concedida 2026-10-02, pendientes decisiones operativas)

1. **Paso 16 — Conexión:** credenciales **solo** desde `.env` / Docker Secrets.
   Nunca en scripts, reportes ni commits. `V1.2.0` necesita
   `FLYWAY_PLACEHOLDER_APP_RW_PASSWORD`, `…_APP_RO_PASSWORD`,
   `…_MIGRATOR_PASSWORD`, `…_SVC_CLINICO_PASSWORD` y `…_DB_NAME`.
2. **Paso 17 — Despliegue:** con `migrator` para DDL; `V1.2.0` con la cuenta de
   administración externa (necesita `CREATE USER` + `GRANT OPTION`).
   Backup/restaurable previo obligatorio (`plan_migraciones §1` regla 5).
   **Prohibido** `DROP DATABASE`, `DROP TABLE`, `TRUNCATE` sin autorización
   explícita (workflow §17, AGENTS.md).
3. **Paso 18 — Verificación de base real:** `database-documentation`
   (ERD, diccionario, drift) → `15_reportes/documentacion_real.md`.
4. **Paso 19 — Pruebas:** ✅ ejecutado el 2026-10-02 en instancia efímera
   (54/54 PASS, ver `14_pruebas/resultados_pruebas.md`). Re-ejecutar la suite
   tras el despliegue real (Paso 17) contra la base verificada (Paso 18).

## 7. Salida

→ Paso 16 (conexión): `deploy_authorization` **GRANTED** el 2026-10-02; faltan
decisiones operativas (motor Docker vs local, credenciales en `.env`).
**F12 y F15 cerrados** (ver §4); `revision_dba.md` §7
y §8 registran la ronda adicional de revisión exigida por F9/F12 y el cierre de F15.
