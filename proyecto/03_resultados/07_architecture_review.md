# 07 · Revisión adversarial de la arquitectura (paso 7 · architecture-reviewer)

**Agente:** architecture-reviewer · **Fecha:** 2026-09-28
**Alcance revisado:** `03_resultados/01…08*.md`, `adr/ADR-001-arquitectura-inicial.md` e `informe_arquitectura.md`, contrastados con `01_requisitos/RF.md`, `01_requisitos/RNF.md`, `01_requisitos/criterios_aceptacion.md`, `00_contexto/` y `02_configuracion/`.
**RC-01:** verificado con `Select-String` y patrón `\b` sobre la lista de nombres de stack/producto prohibidos (definición de RC-01 en `02_decision_scope.md:111`), aplicada a `03_resultados\*.md` y `03_resultados\adr\*.md` → **0 coincidencias** (excluyendo esta línea, que solo describe la comprobación).
**RC-02:** esta revisión no modifica RF/RNF/RN; solo emite hallazgos con destino de decisión.
**RC-03:** ningún valor `null` fijado; RPO/RTO, retenciones, latencias, p95, 25.000 txn/h y `permitir_bajo_receta_sin_conexion` se mantienen como pendientes del cliente.

## 1. Veredicto global

**APTO CON OBSERVACIONES.** La arquitectura es proporcional (CA-09), sin sesgo de producto (CA-04) y conserva los radios duros farmacéuticos (lote/FEFO, controlados, PII, degradado, auditoría). **CA-12 se cumple en esta ronda: 0 hallazgos bloqueantes.**

**Conteo por severidad:** Bloqueante: **0** · Alto: **4** · Medio: **2** · Bajo: **2** · **Total: 8**.
Los 4 Altos son deuda de trazabilidad/validación, no defectos de diseño: deben cerrarse o registrarse como supuesto con dueño antes de abrir ADR-002 (`09_validation_plan.md:81`).

## 2. Hallazgos

| ID | Requisito afectado | Evidencia (archivo:línea) | Impacto | Sev. | Cambio necesario | Criterio de cierre |
|---|---|---|---|---|---|---|
| **ARR-01** | CA-12, CA-01 | `informe_arquitectura.md:3` (alcance = "los 6 documentos"), `:8` declara CA-12 "cumplida por esta revisión"; `08_final_recommendation.md:76` la marca ⬜ "falta la ronda final"; `09_validation_plan.md:13` exige F0 sobre recomendación + revisión + ADR-001 | Contradicción: el veredicto previo afirmó CA-12 cumplida sobre un alcance que no incluía `07`, `08` ni `ADR-001` (creados después). CA-12 solo puede darse por cumplida con esta ronda, no con la anterior | Alto | solution-leader rectifica la afirmación de `informe_arquitectura.md:8` (o la matiza con su alcance) y respeta F0 como única puerta a ADR-002 | `informe:8` ya no afirma CA-12 sin el alcance completo, y F0 (esta ronda) queda registrada como cerrada |
| **ARR-02** | CA-01 (trazado RF→decisión), RF-031, RF-032, RF-040, RF-044, RF-052, RF-060, RF-071 | Grep de IDs sobre `03–08+ADR` → **13 RF sin ninguna cita:** RF-001/002/003/010/020/030/**031/032/040/044/052/060/071**. Los críticos constan en `01:60` (RF-052), `01:64` (RF-060), `01:66` (RF-031+032), `01:68` (RF-071) | 7 RF críticos (recepción+cuarentena, inventario, stock mínimo, receta obligatoria, devoluciones, reportes regulatorios) no están trazados a ninguna decisión ni pregunta abierta: RF-071 condiciona retención/partición (DEC-04/DEC-05) y RF-060 condiciona el ciclo de vida del lote (RN-11) | Alto | database-specialist añade trazado o pregunta abierta con dueño para RF-031/032/040/044/052/060/071 en `04` (o solution-leader en ADR-001); los 6 RF restantes se listan como "requeridos sin impacto arquitectónico" | Los 13 RF aparecen en una fila de cobertura con estado (decisión, pregunta abierta con dueño o no-arquitectónico) |
| **ARR-03** | CA-01, RNF-043, RF-047, supuesto P-03/P-15 | `08_final_recommendation.md:18` incluye 7-C (cola local + conciliación) en la arquitectura recomendada, pero su §6 (entonces) listaba como condiciones solo P-01/P-02/P-05/P-06; `03_architecture_options.md:314` dice que 7-C es "la condición de entrada" dependiente de P-03 y `:321` que "P-03/P-15 habilitan 7-C"; ADR-001 `:35-37` mantiene P-03/P-15 abiertos | La recomendación final presenta como base un modo degradado cuyo habilitante (P-03, `null`) no figura entre las condiciones de aprobación: si el cliente no responde, 7-C avanza sin su condición registrada como deuda | Alto | solution-leader añade P-03 y P-15 a las condiciones de `08 §6` (o explicita en `08:18` la condición `03:314`), sin fijar sus valores (RC-03) | `08 §6` menciona P-03/P-15 con dueño, o `08:18` cita la condición de habilitación |
| **ARR-04** | CA-03, CA-07, RF-053, RNF-043 | H-04 en `05_security_review.md:123` con severidad **Alto** (exceso de dispensación por receta en 7-C sin conexión; I-14, P-15, D-15); `01:138` (I-14 abierta) y `01:156` (R-11 abierta); en `09_validation_plan.md` solo V-04 (`:26`, concurrencia conectada) y V-07 (`:29`, exige "sin stock negativo" e incidente) — **H-04/I-14/R-11 no tienen ensayo propio** | El plan de validación no prueba la variante de mayor riesgo del modo degradado: dos dispensaciones de la misma receta sin conexión. V-07 como está redactada no fallaría si se produce ese exceso | Alto | solution-leader extiende V-07 (o añade V-12) con criterio: al reconectar, exceso de saldo de receta detectado → incidente + ajuste autorizado (mismo patrón que C1–C4) | Un caso de prueba en `09` detona exceso de saldo de receta en 7-C y exige su detección al reconectar |
| **ARR-05** | CA-01 (redacción de ADR) | `adr/ADR-001-arquitectura-inicial.md:20`: "Se adopta la arquitectura descrita en **ADR-001**" | Autoreferencia circular: el ADR no indica dónde está descrita la arquitectura que adopta; un lector o auditor no puede localizarla desde la decisión | Medio | Dueño del ADR (solution-leader) reemplaza la referencia por la sección que contiene la arquitectura (`08 §3`) o por la descripción propia | La línea de decisión remite a un documento/sección distinto del propio ADR |
| **ARR-06** | CA-01, RNF-004, RNF-006, RNF-061 | Grep de IDs sobre `03–08+ADR` → **RNF-004, RNF-006 y RNF-061 sin ninguna cita**; su contenido aparece disuelto en P-05/P-06 (`02:137`), CA-05/CA-06 (`08:69-70`) y "contenedorizado" (`08:58`) sin enlazar el RNF | Trazado RNF→decisión incompleto (CA-01): un auditor no puede verificar desde el RNF dónde se decide su exigencia | Medio | solution-leader enlaza los tres RNF en el trazado RNF de `09 §4` o en el ADR | Los tres RNF aparecen citados con documento y sección |
| **ARR-07** | RC-03, RNF-039 (retención) | `04_database_analysis.md:163-168`: cifras concretas (k=3, D=365, ~27,4 M/~274 M filas) etiquetadas como "ilustrativa (RC-03 — no es propuesta de retención)" | Riesgo de lectura errónea: magnitudes con apariencia de propuesta en un documento de resultados mientras P-02 sigue `null`; está mitigado por la etiqueta explícita | Bajo | database-specialist mantiene la etiqueta RC-03 y no las reutiliza como valor propuesto hasta que P-02/la norma se cierren | Cualquier reutilización de esas cifras queda supeditada a P-02 con dueño |
| **ARR-08** | Trazabilidad de la revisión (CA-01) | Existían `07_final_recommendation.md` y este `07_architecture_review.md` (ambos con prefijo 07); `09_validation_plan.md:13` citaba el primero como "07" en F0 | Ambigüedad de referencias: F0 ("07") podía leerse como cualquiera de los dos documentos | Bajo | solution-leader decide la numeración/títulos (H1 duplicado: "07 ·" en dos documentos) en la próxima fase de correcciones | Los nombres citados existen en disco (ver F0-01) y un único documento usa el prefijo 07 |

### Estado por hallazgo (tras la ronda de correcciones del solution-leader)

| Hallazgo | Estado | Dónde quedó el cierre |
|---|---|---|
| ARR-01 | ✅ **Cerrado** | `informe_arquitectura.md:8` matizado con su alcance (6 documentos) y CA-12 remitida a F0; veredicto APROBADO CON CONDICIONES intacto |
| ARR-02 | ✅ **Cerrado** (parcial ya, completo en esta ronda) | **P-18** registrada en `02 §5` tras P-17 (más nota de excepción y registro de correcciones); marcas de `04:240/280/285` actualizadas a "registrada en `02 §5`" |
| ARR-03 | ✅ **Cerrado** | `08_final_recommendation.md §6` condición 5 (P-03/P-15, dueño cliente, habilitantes de 7-C citando `03:314/321`) + refuerzo en `08 §1`; valores sin fijar (RC-03) |
| ARR-04 | ✅ **Cerrado** | **V-12** añadida en `09 §2` (exceso de saldo de receta en 7-C → incidente + ajuste autorizado, patrón C1–C4, H-04) y citada en `09 §4` CA-08 |
| ARR-05 | ✅ **Cerrado** (con reserva F0-01) | `ADR-001:20` remite ahora a la §3 de la recomendación (sin autoreferencia), pero aún con el nombre pre-renombre `07_final_recommendation.md`, que no existe en disco |
| ARR-06 | ✅ **Cerrado** | Tabla de trazado en `09 §4` con **RNF-004** (P-06, CA-06, V-05), **RNF-006** (P-05, CA-05, V-01/V-05) y **RNF-061** (`08 §3:58`, V-11/CA-09) |
| ARR-07 | ✅ **Cerrado** | Se mantiene la etiqueta RC-03 en `04 §5`; cualquier reutilización de las cifras queda supeditada a P-02 con dueño |
| ARR-08 | ✅ **Cerrado** | `09 §1` F0 nombra ahora `08_final_recommendation.md` + `07_architecture_review.md` (sin renombrar archivos) |

**Ningún hallazgo cierra un valor `null` (RC-03), RF/RNF/RN siguen intactos (RC-02) y no se nombró producto/stack (RC-01).**

## 3. Cobertura de RF

Verificación: grep de cada ID de `RF.md` con límites `\b` sobre `03–08+ADR`.

| Estado | RF | Dónde / nota |
|---|---|---|
| **Sin cobertura (13)** | RF-001, RF-002, RF-003, RF-010, RF-020, RF-030, RF-031, RF-032, RF-040, RF-044, RF-052, RF-060, RF-071 | Ninguna cita en 03–08/ADR. De ellos, críticos según `01:60,64,66,68`: **RF-031, RF-032, RF-052, RF-060, RF-071** (más RF-040/044 de inventario) → ARR-02 |
| **Parcial (1 documento)** | RF-050 (`03`), RF-051 (`03`), RF-054 (`05`), RF-061 (`03`) | Existe mención, sin trazado a decisión final ni ADR |
| **Cubierto** | RF-045 (`03,04,07,ADR`), RF-046 (`03,04,07,08,ADR`), RF-047 (`03,04,05,06,08,ADR`), RF-053 (`03,04,05,06,07,08,ADR`), RF-055 (`03,04`), RF-070 (`03,06`), RF-090 (`03,05`), RF-091 (`04,05,06`), RF-100 (`06,ADR`) | Cita en ≥2 documentos de decisión o en decisión+ADR |

## 4. Cobertura de RNF

| Estado | RNF | Dónde / nota |
|---|---|---|
| **Sin cobertura (3)** | RNF-004, RNF-006, RNF-061 | Ver ARR-06: contenido disuelto en P-05/P-06, CA-05/CA-06 y `08:58` sin cita del ID |
| **Citado con supuesto/pendiente explícito** | RNF-001 (`01:80` no validado; ADR S-1), RNF-041/044 (RPO/RTO `null`, `01:136`), RNF-043 (`01:138`, P-03), RNF-048 (latencia `null`), RNF-039 (P-02 `null`, `05:112`) | RC-03 respetado: citados sin fijar valores |
| **Cubierto** | Resto de los 41 RNF (RNF-002/003/005/010/011, RNF-020–025, RNF-030–037, RNF-038, RNF-040/042/045–047, RNF-050–053, RNF-060/062/063) | Cita verificada por grep en al menos un documento de decisión |

## 5. Matriz de criterios de aceptación (CA-01…CA-12)

| CA | Estado en esta revisión | Evidencia |
|---|---|---|
| CA-01 trazado decisión→RF/RNF/RN | ⚠️ **Parcial** | 12 RF + 3 RNF sin cita → ARR-02, ARR-06 |
| CA-02 ≥2 alternativas | ✅ | `03 §4` (DEC-02/03/04/05/07), `04 §2` |
| CA-03 concurrencia sin stock negativo/lotes no aptos | ✅ con umbral | C1–C4 = 0 en `04 §5`; V-01 en `09:23` |
| CA-04 sin sesgo de producto | ✅ | RC-01 verificado = 0 coincidencias (`04 §3` comparado solo por propiedades) |
| CA-05 25.000 txn/h | ⬜ Pendiente P-06 | `01:80` "no validado"; ADR S-1; no fijado (RC-03) |
| CA-06 escenario 10x | ⚠️ Condicionado | `03:322`, `08:70` (depende de P-06); V-05 en `09:27` sin fijar txn/h |
| CA-07 idempotencia de 4 operaciones | ⚠️ Parcial | 3-a en `04 §6`; 3-c bloqueada por P-16 (ADR S-2); V-02 en `09:24` |
| CA-08 seguridad/privacidad/recuperación | ⚠️ Parcial | H-01…H-12 en `05:120-131`; RPO/RTO `null` (P-01); V-07…V-10 en `09` |
| CA-09 sin K8s/microservicios sin justificación | ✅ | `03:313` (ALT-3 descartada por CA-09), `06:50`, ADR "Alternativas descartadas", V-11 en `09:33` |
| CA-10 trazabilidad lote↔dispensación | ✅ | `04 §4.1/§4.2`; V-06 en `09:28` |
| CA-11 datos sensibles y retención | ⬜ **No cerrable hoy** | H-02 "Bloqueante (parcial)" en `05:121` + P-02 `null`; V-09 condicionada a P-02 (`09:61`) |
| CA-12 sin hallazgos bloqueantes (ronda final) | ✅ **Cumplida en esta ronda** | 0 Bloqueantes; 4 Altos (ARR-01…04) como condición previa a ADR-002 (`09:17`) |

## 6. Destino de cada hallazgo

| Hallazgo | Destino (quién lo cierra) | Dónde se registra |
|---|---|---|
| ARR-01 | solution-leader | Corrección de `informe_arquitectura.md:8` + registro en `00_contexto/registro_cambios.md` |
| ARR-02 | database-specialist (RF de `04`) + solution-leader (ADR-001) | `04` o ADR-001 §Supuestos; filas de cobertura en `04 §10` |
| ARR-03 | solution-leader | `08 §6` (condiciones) con entrada en `registro_cambios.md`; valores siguen en manos del cliente (P-03/P-15) |
| ARR-04 | solution-leader (plan) + database-specialist/devops (ejecución en F2) | Ampliación de V-07 o V-12 en `09` |
| ARR-05 | Dueño del ADR (solution-leader) | Edición de `ADR-001:20` al pasar de Propuesto a Aceptado |
| ARR-06 | solution-leader | Matriz `09 §4` o ADR-001 |
| ARR-07 | database-specialist | Mantiene etiqueta RC-03; cualquier cierre pasa por P-02 (cliente) |
| ARR-08 | solution-leader | Decisión de numeración en la próxima fase de correcciones |

**Nota RC-03:** ningún hallazgo cierra por sí mismo un valor `null`. P-01, P-02, P-03, P-05, P-06, P-15, P-16, P-17 y `permitir_bajo_receta_sin_conexion` siguen siendo decisión del cliente.

---

## 7. F0 · Revisión adversarial final (architecture-reviewer)

**Alcance de esta ronda:** verificación en disco del cierre de ARR-01…ARR-08, re-verificación de restricciones y cobertura (RF/RNF/RN, CA-09/CA-02/CA-06), y emisión del veredicto CA-12. Presupuesto de edición: este documento, `08_final_recommendation.md` y `09_validation_plan.md`. Evidencia citada como `archivo:línea` respecto al estado actual tras las correcciones de F0.

### 7.1 Verificación de ARR-01…ARR-08

| ARR | Veredicto F0 | Evidencia |
|---|---|---|
| ARR-01 | ✅ Cerrado | `informe_arquitectura.md:8` matizado en `:10`: CA-12 "solo se da por cumplida en F0", con alcance de los 6 documentos de `:3` |
| ARR-02 | ✅ Cerrado | `04_database_analysis.md §10` (`:265-291`) lista los 13 RF; P-18 registrado en `02_decision_scope.md:151/177` |
| ARR-03 | ✅ Cerrado | `08_final_recommendation.md:101-105` (condición 5: P-03/P-15 habilitantes de 7-C, dueño cliente, citando `03:314/321`) |
| ARR-04 | ✅ Cerrado | V-12 en `09_validation_plan.md:34`, citada a CA-08 en `:58`, registro en `:88` |
| ARR-05 | ✅ Cerrado (reserva F0-01 cumplida) | `adr/ADR-001-arquitectura-inicial.md:20` ya no se auto-refiere; `:92-93` ahora cita `08_final_recommendation.md`/`09_validation_plan.md`, corregido al cerrar F0-01 |
| ARR-06 | ✅ Cerrado | Trazado RNF-004/006/061 en `09_validation_plan.md:68-70` (RNF-061 → `08 §3:58`) |
| ARR-07 | ✅ Cerrado | Etiqueta RC-03 mantenida en `04_database_analysis.md:163-168` (tabla `:163-166` + leyenda `:168`) |
 | ARR-08 | ✅ Cerrado | 18 de las 25 líneas con nombres pre-renombre corregidas en `07` (9), `09` (5) y `08` (4); las 7 restantes (ADR-001, informe, `04`) corregidas al cerrar F0-01; solo quedan menciones históricas en registros |

### 7.2 Hallazgos de esta ronda (F0-01…F0-04)

| ID | Requisito afectado | Evidencia (archivo:línea) | Impacto | Severidad | Cambio necesario | Criterio de cierre |
|---|---|---|---|---|---|---|
| **F0-01** | CA-01, CA-12 (trazabilidad y veredicto) | `adr/ADR-001:20,92,93,99`; `informe_arquitectura.md:10,79`; `04_database_analysis.md:190` | 7 sitios citan `07_final_recommendation.md`/`08_validation_plan.md`, renombrados a `08_final_recommendation.md`/`09_validation_plan.md`: ADR, informe y análisis de datos remiten a archivos inexistentes | **Alto** | solution-leader corrige esos 7 sitios y decide la numeración de títulos (H1 corregido en F0: `08` → "# 08 ·", `09` → "# 09 ·") — fuera del presupuesto de F0 | grep `07_final_recommendation\|08_validation_plan` = 0 en `03_resultados/` → ✅ **Cerrado**: 0 referencias vivas (las 7 corregidas); solo quedan 5 líneas históricas (actas, registros y la evidencia de este hallazgo; ver Registro 3) |
| **F0-02** | CA-12 (consistencia del veredicto) | `07 §3-§5` (`:66,70,72,76,77`) vs `08_final_recommendation.md:69,70,71,75,76` vs `09_validation_plan.md:51-62` | Tres matrices de estado CA divergentes (CA-05/06/07/11/12 con estados distintos): riesgo de leer el estado equivocado | **Medio** | consolidación en una sola matriz CA — **ejecutada en esta ronda**: la matriz válida es `§7.3` (CA-01…CA-12, con CA-12 remitiendo a `§7.4`); `08 §4` queda subordinado con marcadores y `09 §4` solo traza | Un único documento con el estado CA final y su evidencia → ✅ **Cerrado**: estado CA único en `§7.3`/`§7.4` |
| **F0-03** | CA-01 (trazabilidad RN) | `00_contexto/reglas_negocio.md:8` (RN-07) y `:9` (RN-08); grep = 0 coincidencias en `03`–`06`,`08`,`09`+ADR | 11/13 RN citados; RN-07 (recepción de compras: lote/vencimiento/cantidad) y RN-08 (transferencias con estados) no tienen ninguna cita en decisiones/resultados | **Medio** | architect/database-specialist enlazan RN-07 y RN-08 en sus decisiones o matriz de cobertura | grep `RN-07\|RN-08` ≥ 1 en `03_resultados/` → ✅ **Cerrado**: citas en `04_database_analysis.md` §4 (drivers, `:106`) y §4.1 (notas DEC-05 RN-07 y RN-08, `:126`/`:128`) + fila CA-01 de `04 §9`; cobertura RN 13/13 (§7.3) |
| **F0-04** | CA-01 (gate de preguntas) | `02_decision_scope.md:163` ("P-01…P-15"); `04_database_analysis.md:285` (afirma P-18 en el gate); `09_validation_plan.md:14` (corregido a P-01…P-18 en F0) | El gate de salida `02 §6` no cita P-16/P-17/P-18 pese a estar registrados en `02 §5` | **Bajo** | solution-leader actualiza `02 §6` a P-01…P-18 (fuera de presupuesto) | El paso 3 del gate cita P-01…P-18 → ✅ **Cerrado**: `02_decision_scope.md:163` ahora cita «P-01…P-18» |

Ningún hallazgo es **Bloqueante**. **F0-01, F0-02 y F0-04 → Cerrados** (ediciones fuera de los 3 archivos permitidos, ejecutadas por solution-leader en `ADR-001`, `informe_arquitectura.md`, `04`, `02`, `07`, `08` y `09`); **F0-03 → ✅ Cerrado** (dueño: architect/database-specialist — RN-07/RN-08 citados en `04_database_analysis.md` §4/§4.1 y §9, con cobertura RN 13/13 en §7.3). Estado de verificación de este informe: **`partially_verified`** (5 líneas históricas con nombres pre-renombre en actas/registros y en la evidencia de F0-01, que no son referencias vivas).

### 7.3 Verificación de restricciones, cobertura y matriz CA consolidada

| Verificación | Resultado | Evidencia |
|---|---|---|
| RC-01 (sin nombres de producto/stack) | ✅ | grep de nombres de producto sobre `07`/`08`/`09` = 0 coincidencias; definición en `02_decision_scope.md:111` |
| RC-02 (RF/RNF/RN intactos) | ✅ | Cambios de F0 solo en citas de resultados; ningún RF/RNF/RN alterado |
| RC-03 (ningún `null` fijado) | ✅ | 0 valores `null` fijados; P-01…P-18 siguen `a validar con el cliente` |
| RC-13 (alcance) | ✅ | Funciones fuera de alcance (web, domicilio, contabilidad…) no incorporadas |
| Cobertura RF | ✅ 29/29 citados | grep RF sobre `03`–`06`,`08`,`09`+ADR; tabla `04 §10` |
| Cobertura RNF | ✅ 41/41 citados | grep RNF (incluye el trazado F0 de `09 §4`) |
| Cobertura RN | ✅ 13/13 | RN-07/RN-08 citados en `04_database_analysis.md` §4 (`:106` drivers) y §4.1 (notas DEC-05), fila CA-01 de `04 §9`; los otros 11 RN ya citados → cierre F0-03 |
| CA-01 trazado decisión→RF/RNF/RN | ✅ 13/13 RN | RN-07/RN-08 trazados a decisión en `04_database_analysis.md` §4/§4.1 (cierre F0-03); AR-01/AR-06 cerrados (`08 §5`); trazado de CA-01 en `09:51` |
| CA-02 ≥2 alternativas | ✅ | DEC-02…DEC-07 en `03 §4`, `08:66` |
| CA-03 concurrencia sin stock negativo/lotes no aptos | ✅ con umbral | C1–C4 = 0 en `04 §5`; V-01 en `09:53` |
| CA-04 sin sesgo de producto | ✅ | DEC-04/DEC-05 comparados solo por propiedades (`04 §2/§3`); `09:54` |
| CA-05 25.000 txn/h | ⬜ Pendiente P-06 | `01:80` «no validado»; criterio C1–C4 en `04 §5` no fija volumen (RC-03); V-01/V-05 en `09:55` |
| CA-06 (escenario 10x) | ✅ analizado, condicionado a P-06 | `03 §5`, `04 §5`, `08:70`; V-05 en `09:27` sin fijar txn/h |
| CA-07 idempotencia de 4 operaciones | ⚠️ Parcial | 3-a validado en `04 §6`; 3-c bloqueada por P-16 (`null`); V-02 en `09:57` |
| CA-08 seguridad/privacidad/recuperación | ⚠️ Parcial | H-01…H-12 en `05:120-131`; RPO/RTO `null` (P-01); V-07…V-12 en `09:58` |
| CA-09 (sin K8s/microservicios injustificados) | ✅ | `08_final_recommendation.md:73`; `06:50`; ADR §5 |
| CA-10 trazabilidad lote↔dispensación | ✅ | `04 §4.1/§4.2`; V-06 en `09:60` |
| CA-11 datos sensibles y retención | ⬜ No cerrable hoy | P-02 `null` (norma/plazos) + H-02 (`05:121`); V-09 en `09:61` |
| CA-12 sin hallazgos bloqueantes | ✅ APTO con condiciones | Veredicto completo en `§7.4` de este documento |
| H-01/H-02 (`05:120-121`) | ✅ no bloquean CA-12 | Son supuestos con pregunta abierta y condición previa a producción (`05:170`, condición de `08 §6`); declarados aquí como verificación |
| Nota histórica | §3-§5 de este documento reflejan la ronda ARR previa | **§7.3-§7.4 es la matriz válida** (F0-02); matriz consolidada en `§7.3` (CA-01…CA-12) al cerrar F0-02 |

### 7.4 Veredicto CA-12

**CA-12 = APTO con condiciones** — 0 hallazgos Bloqueantes en F0 (F0-01 Alto · F0-02 Medio · F0-03 Medio · F0-04 Bajo).

Condiciones para que el APTO sea firme antes de abrir ADR-002 (regla de `09_validation_plan.md:81`):

1. **F0-01** — ✅ **Cerrado** (solution-leader): 7 referencias vivas corregidas en ADR-001, informe y `04`; numeración de títulos decidida.
2. **F0-02** — ✅ **Cerrado** (solution-leader): matriz CA consolidada en `§7.3` (CA-01…CA-12); `08 §4` y `09 §4` subordinados por precedencia.
3. **F0-03** — ✅ **Cerrado** (database-specialist): RN-07 y RN-08 citados en `04_database_analysis.md` §4 (drivers) y §4.1 (notas DEC-05), con fila CA-01 de §9 actualizada; cobertura RN 13/13 en §7.3.
4. **F0-04** — ✅ **Cerrado** (solution-leader): el paso 3 del gate `02 §6` cita P-01…P-18.
5. H-01/H-02 permanecen como supuestos con condición previa a producción (no bloquean F0; su cierre sigue en P-01/P-02 del cliente).

---

## Registro de correcciones

1. **Ronda de correcciones ARR-01…ARR-06/ARR-08 (solution-leader, paso 8):** tabla de estado por hallazgo añadida en §2 y correcciones aplicadas en `informe_arquitectura.md` (ARR-01), `02_decision_scope.md` (ARR-02: P-18), `04_database_analysis.md` (marcas de P-18), `08_final_recommendation.md` (ARR-03: condición 5 con P-03/P-15), `09_validation_plan.md` (ARR-04: V-12; ARR-06: RNF-004/006/061; ARR-08: F0 explícito) y `adr/ADR-001-arquitectura-inicial.md` (ARR-05: sin autoreferencia). **Qué:** cerrar los hallazgos sin alterar el alcance. **Por qué:** eran deudas de trazabilidad/validación abiertas en esta ronda. **Quién aprueba:** solution-leader; F0 (architecture-reviewer) como puerta de verificación final. **No se alteró RF/RNF/RN (RC-02), ningún valor `null` (RC-03) ni se nombró producto/stack (RC-01).**
2. **F0 · Revisión adversarial final (architecture-reviewer):** se añade **§7 completo** (§7.1 verificación ARR-01…ARR-08, §7.2 hallazgos F0-01…F0-04, §7.3 re-verificación de restricciones/cobertura, §7.4 veredicto CA-12 = APTO con condiciones) y se corrigen las 18 referencias a archivos pre-renombre dentro del presupuesto (9 en este documento, 4 en `08_final_recommendation.md` —incluida la fila CA-12 → ✅ y el H1 → "# 08 ·"— y 5 en `09_validation_plan.md` —incluido el H1 → "# 09 ·" y F1 → P-01…P-18—). **Qué:** ejecutar F0 y eliminar referencias rotas en los 3 archivos editables. **Por qué:** 25 líneas citaban `07_final_recommendation.md`/`08_validation_plan.md` inexistentes; las 7 restantes (ADR-001, informe, `04`) quedan como condición F0-01. **Quién aprueba:** solution-leader; ADR-002 no se abre hasta cerrar F0-01…F0-04 (regla `09:81`). **No se alteró RF/RNF/RN (RC-02), ningún valor `null` (RC-03) ni se nombró producto/stack (RC-01).**
3. **Cierre de F0-01, F0-02 y F0-04 (solution-leader):** F0-01 → corregidas las 7 referencias vivas a nombres pre-renombre en `adr/ADR-001-arquitectura-inicial.md` (`:20,92,93,99`), `informe_arquitectura.md` (`:10,79`) y `04_database_analysis.md` (`:190`), y §7.1 ARR-05/ARR-08 pasan a **Cerrado**. F0-02 → matriz CA consolidada en `§7.3` como **matriz única** (CA-01…CA-12): estados de CA-05/CA-11 ⬜ y de CA-01/CA-07/CA-08 ⚠️ tomados de la evidencia de `§5`, `04` y `05` sin fijar ningún valor `null`; CA-12 remite a `§7.4`; `08 §4` marcado con `→ 07 §7.3`/`§7.4` en las 6 filas divergentes y `09 §4` con nota de precedencia (solo trazado). F0-04 → `02_decision_scope.md:163` cita «P-01…P-18». **Qué:** cerrar los tres hallazgos y dejar una única fuente de verdad del estado CA. **Por qué:** referencias rotas, tres matrices CA divergentes y un gate de preguntas incompleto. **Quién aprueba:** solution-leader; F0-03 sigue **Abierto** (dueño: architect/database-specialist) y ADR-002 queda condicionado a él (`09:81`). **No se alteró RF/RNF/RN (RC-02), ningún valor `null` (RC-03) ni se nombró producto/stack (RC-01).**
4. **F0-03 (ronda F0, cierre):** **Qué:** fila **F0-03** de §7.2 pasa a **✅ Cerrado** con su evidencia (`04_database_analysis.md` §4 drivers `:106`, notas DEC-05 de RN-07/RN-08 en §4.1 `:126`/`:128` y fila CA-01 de `04 §9`); §7.3 filas «Cobertura RN» y «CA-01 trazado decisión→RF/RNF/RN» pasan de ⚠️ 11/13 a **✅ 13/13**; §7.4 condición 3 pasa a **✅ Cerrado** y el párrafo de estado de §7.2 deja de contar F0-03 como pendiente. **Por qué:** RN-07 (`reglas_negocio.md:8`) y RN-08 (`:9`) no tenían ninguna cita en los documentos de decisión/resultados (CA-01 incompleto) y ambas reglas ya condicionaban el modelo de recepción y de transferencias de `04`. **Quién aprueba:** database-specialist (trazado en `04`) + solution-leader (cierre del hallazgo); ADR-002 queda sin esa deuda pendiente. **No se alteró RF/RNF/RN (RC-02), ningún valor `null` (RC-03) ni se nombró producto/stack (RC-01).**
