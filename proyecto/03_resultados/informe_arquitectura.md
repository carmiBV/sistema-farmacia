# Informe de revisión adversarial de arquitectura

**Revisor:** architecture-reviewer (paso 7, AGENTS.md) · **Alcance:** los 6 documentos de `proyecto/03_resultados/` + `00_contexto/`, `01_requisitos/`, `02_configuracion/`
**Fecha:** 2026-09-28 · **Destino:** `proyecto/03_resultados/informe_arquitectura.md`

## 1. Veredicto global

**APROBADO CON CONDICIONES** — no se encontró ningún hallazgo **Bloqueante**; CA-12 ("sin hallazgos bloqueantes en revisión final") queda **cumplida por esta revisión**, con 3 hallazgos **Altos** que deben resolverse antes del ADR (o registrarse como supuesto explícito con pregunta abierta, conforme a la regla de cierre de `02 §5`).

**Matización de alcance de CA-12 (corrección ARR-01):** el alcance de esta revisión fueron los **6 documentos** de `03_resultados/` declarados en la línea de alcance — `08_final_recommendation.md`, `09_validation_plan.md` y `adr/ADR-001-arquitectura-inicial.md` **no existían** al emitirse este informe—. Por tanto CA-12 **solo se da por cumplida en F0**, con el veredicto de `07_architecture_review.md` (ronda que sí cubrió `08`, `09` y el ADR); el veredicto **APROBADO CON CONDICIONES** de este informe se mantiene intacto.

La arquitectura es coherente, trazable y proporcional: las 12 DEC tienen dueño único (`02 §1`), las alternativas se comparan por propiedades sin sesgo de producto (CA-04), no se fijó ningún valor `null` (verificado en `03`–`06`: RPO/RTO, retención, latencias y 25.000 txn/h aparecen solo como supuesto/pregunta abierta), y no hay Kubernetes ni microservicios sin justificación (CA-09, `06` T-A/T-B). Las prioridades farmacéuticas (lote, inventario, controlados, PII, degradado, auditoría) están presentes en todas las capas.

**Conteo por severidad:** Bloqueante: 0 · Alta: 3 · Media: 3 · Baja: 2 · **Total: 8**

## 2. Hallazgos

| ID | Requisito | Evidencia (archivo:línea) | Impacto | Sev. | Cambio necesario | Criterio de cierre |
|---|---|---|---|---|---|---|
| **AR-01** | **RF-046** — doble autorización para ajustes de controlados; RC-06; CA-01/CA-10 | `RF.md:25`; `02:71` (químico valida "doble autorización" para cerrar DEC-02/07); `02:116` (RC-06); **cero menciones en `03`, `04`, `06`**; único roce `05:59` = RNF-031 (segregación de 1 actor, no doble firma) | El control de controlados prioritario no tiene decisión dueña ni mecanismo (p. ej. dos actores en la misma transacción de ajuste): CA-01 incompleto y riesgo de que el ADR cierre DEC-02 sin cubrir RF-046 | Alta | Que `architect` (dueño de DEC-02) o `database-specialist` (DEC-05) declare el mecanismo de doble autorización en su recomendación, o crear DEC explícita; **no alterar RF-046** | RF-046 citado en una DEC con mecanismo verificable (grep RF-046 en `03`–`06` ≥ 1) |
| **AR-02** | Trazabilidad de pendientes (CA-01, regla de cierre de `02 §5`) | `04:23-24` (S-8 ventana de claves sin plazo; S-9 RNF-021 sin saldo de receta); `04:184` (3-c sin cerrar por S-8); `04:224-225` ("numeración formal pendiente en `02 §5`"); `04:192` (S-9 puede rehacer frontera §2.1); **`02 §5` solo lista P-01…P-15 (`02:132-148`)** | Dos pendientes con impacto arquitectónico viven fuera de la fuente de verdad; se perderán en la consolidación del paso 8 | Alta | Numerar S-8 y S-9 en `02 §5` con dueño y regla de cierre (o registrar en `00_contexto/registro_cambios.md` como P-16/P-17) | `grep "S-8\|S-9" 02_decision_scope.md` con dueño asignado |
| **AR-03** | **CA-03** (prueba de concurrencia "descrita y verificable", `02:18`), **CA-05/CA-06** | `03:45` y `03:204` (DEC-02 → optimista 2-b, "más reintentos bajo pico 3,0"); `03:328` (propiedades, "sin imponer mecanismo"); `04:45/04:190` (contención por lote disputado, condición 3); `02:89` (comparar p95) | La recomendación optimista depende de una prueba sin **umbral cuantitativo** (tasa de conflicto/reintentos o p95 aceptable): CA-03/CA-05 no son verificables como exige `02:18` | Alta | Definir criterio de verificación (métrica + método de prueba de concurrencia con 48 cajas) en `04 §5` o `02`, **sin fijar valores de negocio** (RC-03: p95 objetivo = P-05) | Criterio cuantitativo escrito y referido a CA-03/CA-05 |
| **AR-04** | **CA-12** — "sin hallazgos bloqueantes en revisión final" | `05:120-121` (H-01/H-02 severidad **Bloqueante**); `05:170` (se cierran en ADR como supuesto y se declara "compatible con CA-12"); `04:242` (CA-12 pendiente); `06:195` (CA-12 con pendientes heredados) | Redefinir el sentido de un CA dentro del informe que lo evalúa es riesgo de RC-02; el cierre por supuesto es legítimo (`02 §5`), pero la **decisión de CA-12 corresponde al ADR**, no a `05` | Media | Dejar CA-12 como pendiente en `05` y `06`, para que solution-leader la resuelva en el ADR constando H-01/H-02 como supuestos con condición previa a producción | Fila CA-12 decidida en el ADR con H-01/H-02 visibles |
| **AR-05** | **P-03** (modo degradado, pendiente de validar) vs **RF-047** (ajuste autorizado al reconectar) y RF-060/RNF-045 | `02:136` (P-03 cubre "qué se permite/bloquea" de venta libre, bajo receta y controlados); `02:43-45` (7-A/7-B/7-C); `04:82` (la política de degradación la decide P-03/P-15) | Ajustes, devoluciones, recepción y cierre de caja durante corte quedan **sin política**; RF-047 exige un ajuste autorizado justo en ese escenario | Media | Ampliar el alcance de P-03 o registrar pregunta abierta en ADR; **no inventar la política** (RC-03) | P-03 o pregunta abierta cubre ajuste/recepción/cierre en corte |
| **AR-06** | **D-15/I-14** (detección de excesos de saldo de receta) vs **RF-053/RF-047** | `01:200` (D-15 ancla a RF-053, RN-04, I-14, CAM-003-c); `RF.md:32` (RF-053 no menciona reconexión ni alerta); `RF.md:26` (RF-047 cubre **stock**, no saldo de receta); `04:81`; `05:142` (H-04) | Una obligación funcional nueva (alertar excesos de receta al reconectar) descansa sobre RFs que no la contienen: riesgo de cambio de alcance encubierto (RC-02) | Media | Registrar D-15 como **supuesto/decisión abierta** en el ADR, o proponer RF nuevo por el canal formal de `registro_cambios` (qué, por qué, quién aprueba) | Decisión explícita sobre el ancla de D-15 en el ADR o CAM registrado |
| **AR-07** | **CA-07** — idempotencia de cobro, dispensación, recepción y transferencia; **RF-041/RF-047** (ajustes, bajas) | `03:38` (definición operativa de las 4 operaciones); `02:90`; `RF.md:20`, `RF.md:26` | Ajustes y bajas también mutan inventario y libro: una doble aplicación no está cubierta por la definición (menor que las 4 operaciones, pero real) | Baja | Extender la definición de CA-07 a ajuste/baja **o** declarar por qué queda fuera (la exigencia CA-07 nace de requisito, no expandirla en silencio) | Mención explícita de ajuste en la tabla CA-07 de `03 §4` o nota de exclusión |
| **AR-08** | **RF-060** (devoluciones) y RF-100 (política de devoluciones) | `RF.md:37`, `RF.md:8`; `04:110` (movimientos: "recepción, transferencia, dispensación, baja, ajuste" — **sin devolución**); `03:143` (ídem); `06:67` (solo como parámetro de config) | La devolución es un movimiento por lote con estados; si entra en DEC-04/DEC-05 no está en la enumeración de tipos de movimiento | Baja | Añadir devolución a la enumeración de movimientos de `04 §2` o confirmar que se modela en fase posterior al ADR | Enumeración de movimientos de `04` consistente con RF-041/RF-060 |

## 3. Cobertura faltante (resumen)

- **RF-046** (doble autorización): sin decisión dueña → AR-01.
- **RF-060** (devoluciones): ausente de enumeraciones de movimientos → AR-08.
- **S-8/S-9**: pendientes de `04` sin numerar en `02 §5` → AR-02.
- **D-15**: obligación sin RF ancla formal → AR-06.
- **Recall / retiro de lotes** (RC-05): presente en `02:115` y `03:128` y trazado por RNF-045/CA-10; **sin hallazgo** — la consulta reversa lo soporta, pero conviene que el ADR confirme el flujo de recall como prueba de CA-10.
- Todo lo demás verificado: RF-041…RF-055, RF-090/091, RF-100 y **RNF-001…063 con cobertura completa** en `01`–`06`; RN-01…RN-13 trazadas.

## 4. Comprobación CA-01…CA-12

| CA | Estado | Evidencia / motivo |
|---|---|---|
| CA-01 mapeo decisión→RF/RNF/RN | ⚠️ | `01:62-64` y tablas de `03`–`06`; excepciones AR-01/AR-06 |
| CA-02 ≥2 alternativas | ✅ | `03 §3-§4` (5 alternativas), `04 §3` (A/B/C), `05 §2`, `06 §2` |
| CA-03 concurrencia sin stock negativo/lotes no aptos | ⚠️ | Mecanismo descrito (`03 §2`, `04 §2.2`); sin umbral verificable → AR-03 |
| CA-04 DB sin sesgo | ✅ | `04 §3` matriz por propiedades, sin nombrar productos |
| CA-05 25.000 txn/h | ⚠️ | Cálculo ≈7 txn/s base, ≈21/s pico (×3), num. = 5 tipos (`04 §5`); factor k supuesto, **valor a validar con cliente** |
| CA-06 10x (≈208/s) | ✅ | `03 §6`, `04 §5`, `06 §3` con estrés y condiciones de cambio |
| CA-07 idempotencia | ⚠️ | 4 operaciones definidas (`03:38`); ajuste/baja fuera → AR-07 |
| CA-08 seguridad/privacidad/observabilidad/recuperación | ⚠️ | `05` completo + `06` §observabilidad/backup; H-08/H-10/H-12 abiertos declarados (`06:195`) |
| CA-09 sin K8s/microservicios injustificados | ✅ | `06` T-A (contenedor simple) y T-B solo con justificación; `01`/`02`/`03` sin microservicios; RC-01 respetado |
| CA-10 trazabilidad lote↔dispensación | ✅ | `04 §4.1/§4.2` camino de acceso directo, fuente única; recall bajo RNF-045 |
| CA-11 datos sensibles/retención | ⬜ | `05:112`: no cerrable hasta P-02/P-14 y I-11 (esperado, no es hallazgo) |
| CA-12 sin hallazgos bloqueantes | ⚠️ | Esta revisión: **0 bloqueantes**; H-01/H-02 de `05` abiertos → decisión final en ADR (AR-04) |

## 5. Verificación solicitada en `06_infrastructure.md`

- **DEC-10** → recomendación **T-A** (`06:42`): consistente con CA-09 y con la proporcionalidad de `03 §7`; observabilidad/respaldo de `06` refuerzan (no contradicen) los controles de `05` (H-08 identidad degradada, alertas de PII H-12, correlación RNF-053).
- **DEC-08** → **C** (combinación paramétrica, escalado + aislamiento de reportes): coherente con el insumo de `04:207` y con CA-05/CA-06; dueño devops, `03` no la decide → **sin contradicción**.
- **No fijó `null`**: verificado — `06` menciona RPO/RTO/retención/25.000 txn/h solo como "sin fijar" o pendiente (p. ej. `06:17-18`, `06:195`). Igual en `03`–`05`.
- **CA-09 en T-A/T-B**: T-A cumple directamente; T-B exige justificación medible explícita antes de adoptarse.

## 6. Pendientes que permanecen como supuestos (no inventados)

RPO/RTO (P-01) · plazos de retención y ventana de claves de idempotencia (P-02/P-14/S-8) · política de modo degradado (P-03/P-15) · latencias p95 (P-05) · 25.000 txn/h · normativa sanitaria y de datos · medios de pago (P-09) · saldo de receta en RNF-021 (S-9) · ventana de rotación de claves (DEC-03 3-c) · aprobación de CAM-001/002/003 (P-14). Ninguno fue fijado por los documentos revisados.

---

## Resumen final (paso 8 · solution-leader)

- **Veredicto:** APROBADO CON CONDICIONES — **0 hallazgos bloqueantes** (CA-12 de esta revisión cumplida); decisión final de CA-12 y de H-01/H-02 en el ADR.
- **Conteo:** 0 bloqueantes · 3 altos · 3 medios · 2 bajos (total 8: AR-01…AR-08).
- **Hallazgos graves:** **AR-01** RF-046 (doble autorización de controlados) sin decisión dueña; **AR-02** S-8/S-9 sin numerar en `02 §5`; **AR-03** recomendación optimista sin umbral cuantitativo para CA-03/CA-05; más AR-04 (reinterpretación de CA-12) y AR-05 (alcance de P-03 sin ajustes/devoluciones/cierre de caja).
- **Siguiente paso:** paso 8 — solution-leader (consolidación final: recomendación, matriz de decisión, ADR, plan de validación), resolviendo AR-01…AR-03 antes del ADR.

---

## Registro de correcciones

1. **ARR-01 (ronda de architecture-reviewer, `07_architecture_review.md`):** matización del párrafo de veredicto (§1) con el alcance real de esta revisión (los 6 documentos, sin `07_final`, `08` ni `ADR-001`, creados después) y referencia de CA-12 a F0. **Qué:** la frase "cumplida por esta revisión" se leía como CA-12 cerrada sin cubrir los documentos posteriores. **Por qué:** contradicción con `08_final_recommendation.md:71` y con `09_validation_plan.md:13`, que reservan CA-12 a la ronda final. **Quién aprueba:** solution-leader (dueño del informe) con architecture-reviewer en F0. **No se alteró RF/RNF/RN (RC-02), ningún valor `null` (RC-03) ni se nombró producto/stack (RC-01);** el veredicto APROBADO CON CONDICIONES no se borró.
2. **F0-01 (ronda F0):** las 2 referencias de este informe a archivos pre-renombre (`:10`, en el matiz de alcance de CA-12, y `:79`, en el registro ARR-01) pasan a citar `08_final_recommendation.md` y `09_validation_plan.md`, que son los nombres que existen en disco (el shorthand `07`/`08` de la misma frase de `:10` queda `08`/`09` por el mismo renombre). **Qué:** solo el nombre del archivo citado; se **mantiene el sentido** —esos documentos **no existían** al emitirse este informe— y el veredicto APROBADO CON CONDICIONES intacto. **Por qué:** los nombres antiguos remitían a archivos inexistentes y rompían la trazabilidad informe→evidencia (CA-01, CA-12). **Quién aprueba:** solution-leader (dueño del informe); criterio de cierre = grep de nombres pre-renombre sin resultados sobre este archivo. **No se alteró RF/RNF/RN (RC-02), ningún valor `null` (RC-03) ni se nombró producto/stack (RC-01).**
