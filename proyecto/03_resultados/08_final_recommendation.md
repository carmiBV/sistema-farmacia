# 08 · Recomendación arquitectónica final (paso 8 · solution-leader)

**Proyecto:** sistema de gestión de farmacia — control de inventario por lote y registro de dispensación.
**Estado:** **Aprobado** por el cliente — 2026-10-02 (CAM-006). Condiciones de §6 abiertas (ver `registro_cambios.md`).
**Fecha:** 2026-09-28.
**Insumos:** `01_requirements_analysis.md`, `02_decision_scope.md`, `03_architecture_options.md`,
`04_database_analysis.md`, `05_security_review.md`, `06_infrastructure.md`,
`informe_arquitectura.md` (revisión AR-01…AR-08), `00_contexto/registro_cambios.md` (CAM-001…004).
**Reglas aplicadas:** RC-01 (sin nombres de stack/producto), RC-02 (RF/RNF/RN intactos),
RC-03 (ningún `null` fijado), CA-09 (sin orquestador complejo ni microservicios sin justificación).

## 1. Veredicto

**Se recomienda aprobar la arquitectura ADR-001 en estado Propuesto** con las condiciones de §6.

La recomendación es **una sola base transaccional (registro transaccional con atomicidad
multi-registro) para inventario, libro de control y auditoría, más 8 sucursales, ~48 cajas, ~180
usuarios y modo degradado con cola de escritura local + conciliación al reconectar** (DEC-07 7-C,
mejorada por R-11). Es la única opción que cubre los 12 criterios de aceptación hoy con complejidad
mínima y que admite el escenario 10x sin reescribir la frontera transaccional (CA-06).

El escenario **7-C está condicionado** por P-03 y P-15 (cliente): `03_architecture_options.md:314`
lo declara «la condición de entrada» y `:321` que «P-03/P-15 habilitan 7-C». La condición de
aprobación correspondiente está en **§6, condición 5** — sin esas respuestas, 7-C avanza como deuda
de validación visible (no se fija ningún valor aquí, RC-03).

**No se ha programado, instalado ni seleccionado stack:** las decisiones de plataforma concreta
motor, framework, contenedor, nube y proveedor de identidad son **ADR-002**, y su puerta de entrada
es la aprobación de este documento.

## 2. Por qué esta y no otra

| Alternativa descartada | Motivo de descarte (trazable) |
|---|---|
| **Base con partición obligatoria desde el día 1 (DEC-04 B)** | Paga coordinación distribuida en RF-045 y RF-053 sin justificación medible; la opción recomendada la deja como crecimiento bajo condición (P-02/P-01) |
| **Proyecciones como fuente de lectura (DEC-05 B)** | Arriesga RN-02 si una proyección atrasada se lee en venta; se admite solo como optimización derivada y reconstruible (CA-10) |
| **Kubernetes / microservicios desde el inicio** | Prohibido sin justificación medible (CA-09, RC-12): el problema actual es de integridad transaccional, no de orquestación de contenedores ni de reparto de servicios |
| **Sistema dividido por módulos independientes con mensajes** | La invariante sin stock negativo, sin doble descuento y con FEFO (RN-02, RN-03) exige frontera transaccional única (§7.1); los mensajes la rompen en los bordes críticos |
| **Caché de stock en modo degradado sin componente mínimo** | El escenario 7-A/7-B exige componente mínimo (caché + cola en sucursal/caja) y 7-C exige conciliación al reconectar (DEC-07, R-11) |

## 3. Arquitectura recomendada (resumen ejecutivo, sin nombres de producto)

- **Una unidad de escritura con atomicidad multi-registro** para inventario por lote, libro de
  control, recetas y auditoría: todas las fronteras RF-045/RF-046/RF-053 viven en la misma unidad
  transaccional (DEC-04 A; opción C queda como camino de crecimiento ante plazos de retención
  largos).
- **Concurrencia de inventario:** verificación optimista con control de versión por lote (2-b) +
  serialización exclusiva por lote (2-a) como cierre para el lote más disputado; el **saldo global
  de receta** usa el mismo mecanismo con su propia prueba D-15 (DEC-02).
- **Idempotencia** de cobro, dispensación, recepción y transferencia con clave persistida (3-a) en
  la misma unidad del efecto; la ventana de retención de claves queda abierta (P-16).
- **Doble autorización de ajustes/bajas de controlados** (DEC-02x): `autorizante ≠ proponente`
  (RNF-031), un solo efecto de saldo, auditoría de ambos actores (RF-046, RN-05, RN-06, RC-06).
- **Trazabilidad de extremo a extremo** por lote y dispensación (CA-10) con historial con índices
  (DEC-05 A) y retención en el tiempo por partición/archivado que no rompa CA-10.
- **Seguridad y privacidad** según `05_security_review.md`: cifrado, minimización, acceso por rol,
  auditoría inalterable; plazos de retención y RPO/RTO siguen `null` (P-01, P-02).
- **Operación:** despliegue contenedorizado simple en Linux, respaldo con prueba de restauración
  (RNF-040), métricas de latencia y tasa de error; modos 7-A/7-B/7-C con cola local y conciliación.

## 4. Cobertura de criterios de aceptación (estado tras correcciones)

**Precedencia de estado (cierre F0-02):** la matriz de estado CA definitiva es `07_architecture_review.md §7.3` (CA-01…CA-12, con CA-12 remitiendo a `§7.4`). Las filas marcadas `→ 07 §7.3` **no fijan estado aquí**: su estado vive en esa matriz, única fuente de verdad (los 6 estados que en F0 divergían). Las filas sin marca (CA-02, CA-03, CA-04, CA-08, CA-09, CA-10) no divergen de `07` y se conservan con su evidencia.

| CA | Estado | Evidencia |
|---|---|---|
| CA-01 | → 07 §7.3 | AR-01 corregido (DEC-02x cita RF-046); AR-06 (ancla de D-15) queda como supuesto decidido en ADR-001 §3 |
| CA-02 | ✅ | ≥2 alternativas en DEC-02, DEC-03, DEC-04, DEC-05, DEC-07 (`03 §4`, `04 §2`) |
| CA-03 | ✅ con umbral | AR-03 corregido: criterio C1–C4 (umbral 0) en `04 §5` |
| CA-04 | ✅ | DEC-04/DEC-05 comparados solo por propiedades, sin nombrar productos (`04 §2/§3`) |
| CA-05 | → 07 §7.3 | Criterio en `04 §5`: volumen 25.000 txn/h `a validar` (P-06); p95 sin umbral (P-05) |
| CA-06 | → 07 §7.3 | Escenario 10x analizado (`03 §5`, `04 §5`: ≈69 txn/s, pico ≈208/s) — depende de que P-06 confirme 25.000 txn/h |
| CA-07 | → 07 §7.3 | 3-a validado en `04 §6`; 3-c bloqueada por P-16 (ventana de claves, `null`) |
| CA-08 | ⚠️ | Propiedades de seguridad/recuperación en `05` y `04 §5`; RPO/RTO y plazos `null` (P-01, P-02) |
| CA-09 | ✅ | ADR-001 §5: sin orquestación compleja ni microservicios; complejidad mínima justificada |
| CA-10 | ✅ | `04 §4.1/§4.2`: camino de acceso, índices y unicidad por separado para A y B |
| CA-11 | → 07 §7.3 | `05` exige cifrado, minimización y retención por norma; norma y plazos `null` (P-02) |
| CA-12 | → 07 §7.4 | F0 (ronda final de architecture-reviewer) ejecutada: **APTO, 0 hallazgos bloqueantes** (ver `07_architecture_review.md §7`; F0-01…F0-04 son deudas de trazabilidad, no bloqueantes) |

Leyenda: ✅ cubierto · ⚠️ cubierto con supuesto explícito · ⬜ pendiente de validación humana · `→ 07 §7.3` estado definido en la matriz válida (F0-02).

## 5. Tratamiento de los hallazgos de la revisión (AR-01…AR-08)

| Hallazgo | Tratamiento |
|---|---|
| **AR-01** | Cerrado: bloque DEC-02x en `03 §4` con mecanismo, alternativas y CA de RF-046; registro en `03` línea 21; CAM-004-a. Valida architect + químico |
| **AR-02** | Cerrado: P-16 y P-17 en `02 §5` con dueño; registro en `02` y CAM-004-b |
| **AR-03** | Cerrado: criterio C1–C4 en `04 §5`; registro en `04` y CAM-004-c |
| **AR-04** | Cerrado: riesgo R-01 con probabilidad Media en ADR-001 §6 (severidad ALTA mantiene alta) |
| **AR-05** | Cerrado: alcance P-03 en supuestos del ADR-001 §3 |
| **AR-06** | Cerrado: ancla de D-15 registrada como supuesto decidido (ADR-001 §3) |
| **AR-07** | Cerrado: extensión V-02 del perfil de carga como supuesto abierto (ADR-001 §3) |
| **AR-08** | Cerrado: excepción de devoluciones documentada en ADR-001 §3 |

## 6. Condiciones de aprobación (lo que falta antes de avanzar)

1. **Cliente aprueba este documento** y con él ADR-001 (estado Propuesto → Aceptado).
2. **Se responden P-01, P-02, P-05, P-06** (RPO/RTO, retención, p95, volumen) o se convierten en
   supuestos explícitos firmados — RC-03 impide fijarlos aquí.
3. **Químico farmacéutico valida DEC-02x** (doble autorización) y **P-17** (saldo de receta en
   RNF-021) con requirements-analyst.
4. **database-specialist cierra P-16** (ventana de claves) antes de dar por bueno 3-c.
5. **Cliente responde P-03 y P-15** (modo degradado y `permitir_bajo_receta_sin_conexion`, hoy
   `null`) — son los **habilitantes del escenario 7-C** que §1 incluye en la arquitectura
   recomendada: `03_architecture_options.md:314` lo condiciona ("la condición de entrada") y
   `:321` declara que "P-03/P-15 habilitan 7-C". Dueño: **cliente** (+ químico en P-03). **Aquí no
   se fija ningún valor** (RC-03); sin respuesta, 7-C queda como deuda de validación visible.
6. **architecture-reviewer ejecuta la ronda final (CA-12)** sobre este documento + ADR-001 +
   09_validation_plan.md.
7. Solo entonces se abre **ADR-002** (selección de stack) — nunca antes.

**Si el cliente no responde:** se avanza con supuestos explícitos (regla de cierre de `02 §6`), pero
las condiciones 1–5 (incluida la **condición 5: P-03/P-15, habilitantes de 7-C**) quedan como
**deuda de validación** visible en ADR-001 §4 y en 09_validation_plan.md.

## 7. Artefactos producidos en esta fase

| Artefacto | Rol |
|---|---|
| `03_resultados/08_final_recommendation.md` | Este documento: recomendación final y condiciones |
| `03_resultados/adr/ADR-001-arquitectura-inicial.md` | ADR de arquitectura inicial (estado Propuesto) |
| `03_resultados/09_validation_plan.md` | Plan de validación F0–F4 con evidencia por CA |
| Correcciones en `02_decision_scope.md`, `03_architecture_options.md`, `04_database_analysis.md` | Cierre de AR-01/AR-02/AR-03 + entrada `registro_cambios.md` (CAM-004) |

## Registro de correcciones

1. **ARR-03 (ronda de architecture-reviewer, `07_architecture_review.md`):** nueva **condición 5** en §6 (P-03 y P-15, dueño cliente, como habilitantes del escenario 7-C, citando `03_architecture_options.md:314/321`), renumeración de las condiciones anteriores 5–6 → 6–7, refuerzo en §1 con la misma referencia y mención de la condición 5 en el párrafo de "si el cliente no responde". **Qué:** las condiciones de §6 no incluían los habilitantes de 7-C, que §1 presenta en la arquitectura recomendada. **Por qué:** si el cliente no responde, 7-C avanzaba sin su condición registrada como deuda (CA-01, ARR-03). **Quién aprueba:** solution-leader (dueño del documento); valores de P-03/P-15 siguen en manos del cliente. **No se alteró RF/RNF/RN (RC-02), ningún valor `null` (RC-03) ni se nombró producto/stack (RC-01).**
2. **F0 (ronda de architecture-reviewer):** H1 renumerado a "# 08 ·" (coincidía el "# 07 ·" con `07_architecture_review.md`), fila **CA-12** de §4 actualizada de ⬜ a ✅ con el veredicto F0 (APTO, 0 bloqueantes, ver `07_architecture_review.md §7`) y referencias a los archivos pre-renombre (`07_final_recommendation.md`/`08_validation_plan.md`) sustituidas por `08_final_recommendation.md`/`09_validation_plan.md`. **Qué:** alinear el documento con el cierre de F0. **Por qué:** CA-12 dependía de esta ronda y los nombres citados no existían en disco. **Quién aprueba:** solution-leader; ADR-002 sigue bloqueado hasta cerrar F0-01…F0-04. **No se alteró RF/RNF/RN (RC-02), ningún valor `null` (RC-03) ni se nombró producto/stack (RC-01).**
3. **F0-02 (cierre, solution-leader):** §4 queda subordinado a la matriz válida `07_architecture_review.md §7.3`: nueva nota de precedencia bajo el título, marcador `→ 07 §7.3` (o `§7.4` para CA-12) en las 6 filas que divergían (CA-01, CA-05, CA-06, CA-07, CA-11, CA-12) y ampliación de la leyenda. Las 6 filas sin marca no divergen de `07` y conservan su estado y evidencia. **Qué:** tabla §4 de este documento. **Por qué:** tres matrices CA divergentes con riesgo de leer el estado equivocado; el estado definitivo es ahora único (`07 §7.3`/`§7.4`). **Quién aprueba:** solution-leader; F0-03 sigue Abierto. **No se alteró RF/RNF/RN (RC-02), ningún valor `null` (RC-03) ni se nombró producto/stack (RC-01).**
