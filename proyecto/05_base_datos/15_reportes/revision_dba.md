# Paso 14 — Revisión DBA

- **Workflow:** 02_database_workflow · **Paso:** 14 (revisión adversarial de diseño)
- **Skills utilizados:** `database-schema-designer`, `databases`
- **Alcance revisado:** `01_requisitos_datos` → `12_migraciones` (Pasos 01–13)
- **Método:** rastreo RF/RNF/RN/CA → artefacto; reglas de integridad ejecutables; índices ↔ consultas; transacciones ↔ concurrencia; pendientes declarados (no inventados).

## 1. Hallazgos de la revisión (corregidos en esta pasada)

| # | Sev. | Hallazgo | Artefacto | Corrección aplicada |
|---|---|---|---|---|
| F1 | Medio | Índice FEFO I-01 con orden `(product_id, fecha_vencimiento, estado)`: igualdad (`estado`) tras rango → plan subóptimo | `10_indices_rendimiento` | Reordenado a `(product_id, estado, fecha_vencimiento)` |
| F2 | Medio | CHECK tautológico `stock_reserved <= stock_available + stock_reserved` (siempre verdadero) en `inventory_stock` | `03_modelo_logico`, `06_integridad` | Eliminado; la regla real queda en lock de transacción (Paso 12) |
| F3 | Medio | `UNIQUE(order_id, lot_id, rx_item_id)` no impide ítems duplicados cuando `rx_item_id IS NULL` (MySQL trata NULLs como distintos) | `03_modelo_logico` | Columna generada `rx_item_id_key = IFNULL(rx_item_id, 0)` + UNIQUE sobre `(order_id, lot_id, rx_item_id_key)` |
| F4 | Bajo | CHECK de `system_config` depende de convención de claves no documentada | `03_modelo_logico` | Convención documentada: parámetros por sucursal = `store.<clave>`; globales sin prefijo |
| F5 | Bajo | `inventory_movements.cantidad <> 0` permite cantidad negativa junto a `signo` (redundancia inconsistente) | `03_modelo_logico`, `06_integridad` | `cantidad > 0`; la dirección vive solo en `signo` |
| F10 | **Alto** | CHECK bicondicional de `inventory_lots` (`(estado='liberado') = (liberado_por AND liberado_at AND motivo_liberacion)`) bloquea `liberado → retirado` y `liberado → cuarentena`: con los campos de liberación ya poblados evalúa `FALSE = TRUE` → violación. Impedía el **retiro de lotes (recall)**, exigido por RN-12/RF-061 y por AGENTS.md (dominio farmacéutico). | `03_modelo_logico`, `06_integridad` | Reescrito como **implicación**: `estado <> 'liberado' OR (liberado_por IS NOT NULL AND liberado_at IS NOT NULL AND motivo_liberacion IS NOT NULL)`. Conserva RF-032 (toda liberación queda registrada) y habilita el recall. Detectado en Paso 15; registrado aquí (corrección de diseño, **no** altera RF/RNF/RN — RC-02). |
| F11 | Medio | `07_seguridad §1` prohibía UPDATE sobre `sales_orders` y `payments_transactions`, lo que entraba en conflicto con las transacciones aprobadas **T-1** (`UPDATE sales_orders.total`/`estado`) y **T-2b** (`UPDATE payments_transactions.status`) de `11_transacciones_concurrencia §1`. | `07_seguridad`, `13_sql/V1.2.0` | La prohibición **dura** es DELETE (RNF-023: los registros confirmados no se eliminan). Append-only real = `audit_operations`, `audit_pii_access`, `inventory_movements`, `ctrl_ledger_entries` (SELECT+INSERT). Excepción de purga: `idempotency_keys`, `outbox_events`. Matriz por tabla en `V1.2.0`. |
| F12 | Bajo | `UNIQUE` con columna nullable no impide duplicados cuando esa columna es `NULL` en MySQL. Afecta a `inventory_alerts(tipo, product_id, store_id, fecha_generada)` con `store_id IS NULL` (alerta global: un reintento crea filas duplicadas) y a `catalog_prices(product_id, store_id, vigente_desde)` con `store_id IS NULL` (precio global). | `03_modelo_logico`, `06_integridad`, `13_sql/V1.0.0`, `13_sql/README` | **CERRADO el 2026-10-02 (§7).** Se adopta la columna generada `store_id_key BIGINT GENERATED ALWAYS AS (IFNULL(store_id,0)) STORED` con `UNIQUE` sobre ella en ambas tablas: mismo patrón ya aprobado en F3, sin cambios de RF/RNF/RN (RC-02) ni de alcance (DB-P09 sigue abierto como decisión de negocio). |
| F13 | Bajo | `03_modelo_logico` anota `ON DELETE CASCADE` en `purchase_order_items.order_id`, mientras `06_integridad §1` y `05_modelo_fisico §3` exigen RESTRICT para tablas transaccionales. | `03_modelo_logico`, `13_sql/V1.0.0` | Se aplica **RESTRICT** en `V1.0.0` (RN-10 / RNF-023: ninguna fila transaccional se pierde por borrado en cascada). Prevalece la regla de integridad sobre la anotación puntual. |

## 2. Hallazgos no bloqueantes (registrados, fuera de este workflow)

| # | Hallazgo | Estado |
|---|---|---|
| F6 | Estrategia de backup/restore no tiene artefacto propio en `05_base_datos` | Cubierto por §17 de decisiones + `plan_migraciones` §2/§3 (backup previo); prueba de restauración queda en operación (Paso 17+ / DevOps) |
| F7 | RF-091 (registrar *cada* consulta PII) no es aplicable a nivel de BD en MySQL (sin triggers de SELECT) | Dependencia de aplicación documentada en `07_seguridad` §4 |
| F8 | Discrepancias D-1..D-5 (citas de RF/RNF/§ inexistentes en decisiones) | Escaladas al dueño de `decisiones_dase_Datos.md`; no alteran RF/RNF |
| F9 | Pendientes cliente: RPO/RTO, retenções, modo degradado (RNF-043), trazabilidad (RNF-048), 25k txn/h, método químico, DB-P01..DB-P12 | Todos declarados como supuesto/abierto; el esquema los soporta paramétricamente sin inventar valores |

## 3. Verificación de trazabilidad

- RF-001..RF-100: todos presentes en `01_requisitos_datos` con entidad/tabla asignada.
- RN-01..RN-13: todos con mecanismo ejecutable (CHECK/lock/app) en `06_integridad`.
- CA-01..CA-12: CA-01 (diagramas), CA-02 (3 alternativas, Paso 06), CA-03/07 (verificación en Paso 19), CA-04 (motor por requisitos), CA-05/06 (§5 Paso 11), CA-08 (seguridad Paso 09; recuperación vía §17), CA-10 (vistas de trazabilidad), CA-11 (PII + retención paramétrica) — cobiertos.
- Sin contenido fuera de alcance (ventas web/domicilio/seguros/móvil: excluidos, D-4).

## 4. Criterios de aprobación

- [x] Ningún requisito alterado silenciosamente (D-1..D-5 solo registrados)
- [x] Ningún valor no inventable inventado (F9)
- [x] Hallazgos de diseño corregidos (F1–F5)
- [x] Índices todos justificados por consulta (§12)
- [x] Sin credenciales/secretos en artefactos (RNF-034)
- [x] Sin SQL ejecutable, migraciones ni infraestructura creada (workflow §17)

## 5. STATUS

```
STATUS: APPROVED
```

**Condiciones de vigencia:**
1. El SQL (Paso 15) se genera solo tras aprobación explícita del responsable y con ADR-001/recomendación final aprobados (AGENTS.md). **Cumplido el 2026-10-02 — ver §6.**
2. Cambios en RF/RNF/RN o parámetros `null` → nueva pasada de revisión (§26).
3. F6–F9 permanecen abiertos fuera de este workflow y deben cerrarse antes de producción (RNF-041).

## 6. Ejecución del Paso 15 (2026-10-02)

**Puerta de entrada verificada antes de generar SQL:**

| Condición | Origen | Estado |
|---|---|---|
| `STATUS: APPROVED` | §5 de este documento | ✅ |
| Aprobación explícita del responsable | `registro_cambios.md` CAM-006 | ✅ 2026-10-02 |
| ADR-001 aprobado | `03_resultados/adr/ADR-001-arquitectura-inicial.md` | ✅ `Aceptado` |
| Recomendación final aprobada | `03_resultados/08_final_recommendation.md` | ✅ `Aprobado` |

**Skills utilizados:** `database-schema-designer` + `databases`.

**Salida:** `13_sql/` — `V1.0.0`, `V1.1.0`, `V1.2.0`, `V1.3.0`, `V1.4.0` + `README.md`.

**Hallazgos de esta pasada:** F10 (corregido en diseño y SQL), F11 (resuelto en `V1.2.0`),
F13 (resuelto en `V1.0.0`), **F12 (cerrado en §7)**.

**STATUS se mantiene `APPROVED`:** no se alteró RF/RNF/RN (RC-02), ningún parámetro
`null` fue materializado (RC-03) y no se nombró ningún producto/stack adicional (RC-01).

**Autorización de despliegue concedida el 2026-10-02** (`deploy_authorization: GRANTED`,
fase de pruebas; ver `14_pruebas/resultados_pruebas.md` — 54/54 PASS en instancia efímera).
**Sigue prohibido sin autorización explícita adicional:** `DROP DATABASE` / `DROP TABLE` /
`TRUNCATE` (workflow §17, AGENTS.md).

## 7. Ronda adicional de revisión (2026-10-02)

**Trigger:** la condición 2 de §5 exige nueva pasada ante cambios en RF/RNF/RN o en
parámetros `null`. Concurrieron dos hechos posteriores a §6:

| Hecho | Origen | Efecto sobre el diseño |
|---|---|---|
| **CAM-005** cerró parámetros antes en `null`: RPO 5 min, RTO 60 min, prueba de restauración 90 d, disponibilidad 99,9 % (horario comercial), retenciones 5/5/10/5 años, 25.000 txn/h, 50.000 consultas/h, ventana de idempotencia 7 d, bloqueo de recetas y controlados sin red, PIN de 4 dígitos y doble autorización con credenciales completas | `00_contexto/registro_cambios.md` CAM-005; `03_resultados/10_cliente_quimico_validation.md` (minuta firmada) | **Ninguno.** El esquema ya soporta estos valores *paramétricamente* (`system_config`, `idempotency_keys.expires_at`, `seguridad`); no se materializa ningún valor en SQL. Se mantiene RC-03. Queda pendiente de diseño la **siembra** de `system_config` (convención de claves, F4) — no es un cambio de esquema. |
| **F12 cerrado** (§1) | este documento | `catalog_prices` e `inventory_alerts` añaden `store_id_key` generada; `UNIQUE` pasa a referenciarla. Cambio de esquema **aditivo y no destructivo**: sin `DROP`/`ALTER` sobre columnas existentes, apto para aplicación limpia en V1.0.0. |

**Barrido automatizado de los 27 `UNIQUE` de `V1.0.0` contra columnas `NULL`able**
(los que dieron origen a F12). Resultado: 8 coincidencias, clasificadas:

| # | Hallazgo | Restricción | Clasificación | Acción |
|---|---|---|---|---|
| **F14** | Dos categorías **raíz** con el mismo `nombre` pasaban el `UNIQUE(nombre, parent_id)` porque MySQL trata los `NULL` como distintos. | `catalog_categories` | **Defecto clase F12** (duplicado real de catálogo, RF-020) | **Corregido** con `parent_id_key GENERATED ALWAYS AS (IFNULL(parent_id,0)) STORED` (patrón F3/F12) en `V1.0.0` y `03_modelo_logico`. Sin cambios RF/RNF/RN. |
| **F15** | `UNIQUE(reception_item_id)` no restringe lotes con `reception_item_id IS NULL`, dejando lotes sin trazabilidad de recepción (CA-10). | `inventory_lots` | **Debilidad de diseño**, no duplicado dañino | **CERRADO el 2026-10-02 (§8).** Se adopta la **opción 1** de `06_integridad §7`: `reception_item_id NOT NULL` en `03_modelo_logico` y `V1.0.0`. Sin cambios RF/RNF/RN (RC-02). |
| — | `idempotency_key` en 5 tablas y `reference` en `payments_transactions` | — | **Intencional**: `NULL` = «sin valor», no debe deduplicarse | Documentado como excepción en `06_integridad §2` para evitar que se «arreglen» después. |

**Skill utilizado:** `database-schema-designer` + `databases`.

**Verificación de la ronda:**

- [x] Ningún RF/RNF/RN alterado (RC-02) — CAM-005 cierra *parámetros*, no requisitos.
- [x] Ningún `null` materializado en SQL (RC-03) — los valores aprobados viven en `perfil_carga.yaml`.
- [x] F12 cerrado con patrón ya aprobado (F3); sin destrucción de datos.
- [x] Sin credenciales ni secretos añadidos (RNF-034).
- [x] `V1.0.0`, `V1.1.0`, `V1.2.0`, `V1.3.0`, `V1.4.0` re-leídos tras el cambio; conteo de paréntesis y referencias `store_id_key` verificado.
- [x] Trazabilidad F12 → `03_modelo_logico` → `06_integridad §2/§7` → `V1.0.0` → `README §4` → este documento.
- [x] F14 corregido con el mismo patrón que F12; F15 registrado en `06_integridad §7` (cerrado el mismo día, ver §8).
- [x] Excepciones intencionales (`idempotency_key`, `reference`) documentadas en `06_integridad §2` para que no se «corrijan» por error.
- [x] `V1.0.0`: 42 tablas, 84 FK, 37 CHECK, 27 UNIQUE — conteos sin cambio respecto de §6; paréntesis balanceados (504/504).
- [x] Barrido de `UNIQUE` × columna nullable ejecutado y sin restos pendientes de clasificar.

```
STATUS: APPROVED
```

**Condiciones que siguen vigentes:**

1. **Cumplido el 2026-10-02:** `deploy_authorization: GRANTED` (fase de pruebas) habilita
   los Pasos 16–17. La puerta de §6 había habilitado solo la *generación* del SQL; la
   ejecución se validó en instancia efímera (54/54 PASS, `14_pruebas/resultados_pruebas.md`).
   Los Pasos 16–17 quedan pendientes de decisiones operativas (motor, credenciales `.env`)
   y la suite debe re-ejecutarse contra la base real antes del Paso 20.
2. F6–F9 permanecen abiertos fuera de este workflow y deben cerrarse antes de producción (RNF-041).
3. **F15 cerrado el 2026-10-02 (§8).** DB-P09 (alcance global vs. por sucursal de precios)
   sigue abierto: F12/F14 solo garantizan que **no haya duplicados**; no deciden el modelo
   de negocio.
4. Toda siembra de `system_config` con los valores de CAM-005 exige una ronda más (§26).
5. Cualquier carga futura de lotes sin `reception_item_id` (datos externos, semillas de prueba)
   será rechazada por `NOT NULL`: exige revisión (§26) antes de incorporarla.

## 8. Cierre de F15 (2026-10-02)

**Hallazgo:** `UNIQUE(reception_item_id)` no restringe lotes con `reception_item_id IS NULL`
(MySQL trata los `NULL` como distintos) → se admitían lotes sin trazabilidad de recepción (CA-10).

**Decisión (opción 1 de `06_integridad §7`):** `inventory_lots.reception_item_id NOT NULL`.
Toda lote nace de una recepción confirmada (RN-07; «origen recepción» en `02_modelo_conceptual`),
y con la columna obligatoria el `UNIQUE` garantiza 1:1 sin excepciones.

| Artefacto | Cambio |
|---|---|
| `03_modelo_logico` §5 | `reception_item_id NOT NULL FK→purchase_reception_items UNIQUE` |
| `06_integridad` §7 | F15 → RESUELTO; opción (a) adoptada, (b)/(c) descartadas; fila de excepción intencional retirada |
| `13_sql/V1.0.0` | `reception_item_id BIGINT UNSIGNED NOT NULL` (aditivo sobre esquema aún no desplegado en base real; conteos 42/84/37/27 sin cambio — verificados 54/54 PASS en `14_pruebas`) |
| `13_sql/README` §4/§5 | F15 cerrado; limitación 1 retirada |

**Verificación:**

- [x] Ningún RF/RNF/RN alterado (RC-02) — se materializa una decisión de diseño ya prevista en CA-10.
- [x] Ningún `null` materializado en SQL (RC-03).
- [x] Ciclo `purchase_reception_items.lot_id ↔ inventory_lots.reception_item_id` sigue resoluble: `lot_id` se puebla al confirmar, con el ítem ya existente.
- [x] `V1.3.0` no siembra lotes; no hay datos previos cargados en base real (scripts aún no desplegados; solo ejecutados en la instancia efímera de pruebas), por lo que la reserva «imposible para migración/semillas» de la opción (a) no aplica.
- [x] Sin credenciales ni secretos añadidos (RNF-034).
- [x] Trazabilidad F15 → `03_modelo_logico` → `06_integridad §7` → `V1.0.0` → `README §4/§5` → este documento.

```
STATUS: APPROVED
```

**0 hallazgos bloqueantes abiertos.** Permanecen fuera de este workflow F6–F9 y DB-P09/DB-P12
(§2, condición 3), ajenos al diseño del esquema. Paso 14 (revisión DBA) queda **totalmente
completado**; `deploy_authorization: GRANTED` el 2026-10-02 (fase de pruebas); Pasos 16–17
pendientes de decisiones operativas y re-validación de la suite contra la base real.
