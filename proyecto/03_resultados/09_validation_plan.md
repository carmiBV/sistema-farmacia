# 09 · Plan de validación (paso 8 · solution-leader)

**Propósito:** evidenciar que la arquitectura recomendada (ADR-001, estado Propuesto) cumple los
criterios de aceptación **antes** de abrir ADR-002 (selección de stack) y de escribir código.
**Fecha:** 2026-09-28 · **Estado:** Propuesto.
**Reglas:** RC-01 (sin nombres de producto), RC-02 (RF/RNF/RN intactos), RC-03 (los `null` no se
fijan: RPO/RTO, plazos, p95, 25.000 txn/h siguen `a validar con el cliente`).

## 1. Fases

| Fase | Quién | Entrada | Salida |
|---|---|---|---|
| **F0 · Revisión adversarial final** | architecture-reviewer | `08_final_recommendation.md` + `07_architecture_review.md` (recomendación y revisión), ADR-001, este plan y `01…06` + `informe_arquitectura.md` | Veredicto CA-12: hallazgos bloqueantes (sí/no) con lista F0-n |
| **F1 · Validaciones humanas** | cliente, químico, database-specialist, requirements-analyst | P-01…P-18, Q-01…Q-08, H-*, CAM-004 | Respuestas firmadas o supuestos explícitos aceptados |
| **F2 · Pruebas de diseño (antes de stack)** | database-specialist + devops-architect | Criterio C1–C4 de `04 §5` | Evidencia de integridad (0 errores) en entorno de referencia |
| **F3 · Verificación por criterio** | solution-leader | Matriz §4 | CA-01…CA-12 con estado final y enlace a evidencia |
| **F4 · Puerta a ADR-002** | cliente + solution-leader | F0–F3 completas | ADR-001 Propuesto → Aceptado; abre ADR-002 |

## 2. Validaciones con criterio cuantitativo (V-01…V-12)

| ID | Qué se valida | Criterio / umbral | Método | Responsable | Fase |
|---|---|---|---|---|---|
| **V-01** | Integridad de inventario bajo concurrencia (CA-03, CA-05, RN-02) | C1–C4 = **0** (criterio `04 §5`) | 48 cajas concurrentes disputando 1 lote FEFO hasta agotarlo; variante con corte/reconexión (RF-047) | database-specialist + devops-architect | F2 |
| **V-02** | Idempotencia de las 4 operaciones (CA-07, RNF-022) | Reintentos con misma clave → **un solo efecto**; clave con distinto contenido → rechazo | Ensayo de reintento en cobro, dispensación, recepción, transferencia | architect | F2 |
| **V-03** | Doble autorización de controlados (RF-046, RN-06, RNF-031) | Ajuste sin 2 identidades distintas = **0**; doble efecto con doble autorización = **0** | Ensayo DEC-02x (opción A) | químico + architect | F2 |
| **V-04** | Saldo de receta nunca superado (D-15, RF-053) | C3 = **0** | Dispensaciones parciales concurrentes de una misma receta | database-specialist | F2 |
| **V-05** | Escenario 10x (CA-06) | Tasa pico ≈208 txn/s sin violar V-01/V-02 | Repetición de V-01 a ×10 sobre volumen; **sin fijar txn/h** hasta P-06 | devops-architect | F2 |
| **V-06** | Trazabilidad extremo a extremo (CA-10) | Lote → recepción, stock y dispensaciones **completos** por camino declarado | Consulta de recorrido completo (A y B) | database-specialist | F2 |
| **V-07** | Modo degradado (DEC-07 7-A/7-B/7-C, RF-047) | Tras corte: cola local; al reconectar: conciliación sin stock negativo y con incidente registrado si hay discrepancia | Ensayo corte de red y reconexión | devops-architect | F2 |
| **V-08** | Auditoría inalterable (I-11, RNF-039/046) | Registros confirmados: **0** eliminaciones; correcciones solo por movimiento compensatorio | Ensayo de intento de borrado y de corrección | security-reviewer | F3 |
| **V-09** | Minimización y cifrado de datos sensibles (CA-11, H-01/H-02) | PII solo en campos separados; acceso por rol; MFA donde CAM-003 lo exige | Revisión de acceso y de cifrado por rol | security-reviewer | F3 |
| **V-10** | Recuperación sin RPO/RTO fijados (RNF-040) | **Propiedad**: restauración que deje libro, inventario y auditoría coherentes entre sí | Prueba de restauración periódica (periodo = P-01) | devops-architect | F3 |
| **V-11** | Complejidad proporcional (CA-09) | Sin orquestación compleja ni microservicios sin justificación medible | Revisión de ADR-001 §5 y de ADR-002 al abrirse | solution-leader | F3 |
| **V-12** | Exceso de saldo de receta en 7-C sin conexión (ARR-04: H-04 de `05_security_review.md`, I-14, R-11, RF-053, RNF-043) | Al reconectar: detectar que una misma receta fue dispensada sin conexión por encima de su saldo → **incidente registrado + ajuste autorizado** (mismo patrón C1–C4 de `04 §5`, umbral 0) | Ensayo: dispensar la misma receta sin conexión en 2 puntos y reconectar para forzar la conciliación | devops-architect + database-specialist | F2 |

## 3. Validaciones humanas (F1) — ninguna se sustituye con prueba

- **Cliente:** P-01 (RPO/RTO), P-02 (retención), P-03/P-15 (modo degradado y `permitir_bajo_receta_sin_conexion`),
  P-05 (p95), P-06 (25.000 txn/h), P-09 (medios de pago), P-14 (CAM-001/002/003/004),
  P-16 (ventana de claves), P-17 (saldo en RNF-021), aprobación de ADR-001.
- **Químico farmacéutico:** DEC-02x (doble autorización), P-03/P-13, verificación en dispensación
  (CAM-003-a/b: método `null`).
- **Requirements-analyst:** P-17 — si exige modificar RNF, se reabre 01→02→03 (RC-02).
- **Database-specialist:** P-16 (cierre de 3-c) y diseño de V-01/V-04.
- **Architecture-reviewer:** F0 (CA-12) — única puerta a ADR-002.

## 4. Matriz CA → evidencia

**Nota de precedencia (cierre F0-02):** esta tabla **solo traza** CA → evidencia → fase; **no contiene estados CA**. El estado CA definitivo vive en `07_architecture_review.md §7.3` (CA-01…CA-12; veredicto de CA-12 en `§7.4`), que es la matriz válida; `08_final_recommendation.md §4` queda subordinado a esa matriz.

| CA | Evidencia esperada | Fase |
|---|---|---|
| CA-01 | DEC-02x + drivers de §4 (`03`), cobertura `04 §9` | F3 |
| CA-02 | ≥2 alternativas en DEC-02…DEC-07 (`03 §4`, `04 §2`) | F3 |
| CA-03 | V-01 con C1–C4 = 0 (`04 §5`) | F2 |
| CA-04 | DEC-04/DEC-05 sin nombres de producto (`04 §3`) | F3 |
| CA-05 | V-01 + V-05 (tasa p95 medida; umbral = P-05) | F2 |
| CA-06 | V-05 (×10) — condicionada a P-06 | F2 |
| CA-07 | V-02 + cierre de P-16 | F2/F1 |
| CA-08 | V-07, V-08, V-09, V-10, V-12 + `05_security_review.md` | F3 |
| CA-09 | V-11 (ADR-001 §5) | F3 |
| CA-10 | V-06 (`04 §4.1/§4.2`) | F2 |
| CA-11 | V-09 — condicionada a P-02 | F3/F1 |
| CA-12 | F0 (informe de architecture-reviewer) | F0 |

**Trazado de RNF sin cita (cierre ARR-06)** — dónde queda condicionada la exigencia de cada uno:

| RNF | Exigencia (`01_requisitos/RNF.md`) | Dónde se decide / condiciona | Fase |
|---|---|---|---|
| **RNF-004** | "Evaluar picos con el multiplicador configurado" | `02 §5` **P-06** (multiplicador/10x, valor `null`, no fijado) + `08_final_recommendation.md §4` **CA-06** (depende de P-06) y V-05 (×10) de §2 | F1/F2 |
| **RNF-006** | "La dispensación en mostrador debe responder en un tiempo acotado (objetivo propuesto, pendiente de aprobación)" | `02 §5` **P-05** (p95 `null`) + `08_final_recommendation.md §4` **CA-05** (umbral = P-05) y V-01/V-05 de §2 | F1/F2 |
| **RNF-061** | "Poder contenerizarse" | `08_final_recommendation.md §3` (línea 58: despliegue contenedorizado simple en Linux) + V-11 (CA-09) de §2 | F3 |

Ningún valor se fija con este trazado (RC-03); los tres RNF siguen condicionados a sus preguntas/CA.

## 5. Reglas

1. **Ningún `null` se cierra con prueba técnica:** RPO/RTO, plazos, p95 y 25.000 txn/h solo con
   respuesta del cliente (RC-03). El plan mide y **reporta**; no fija umbrales de negocio.
2. **F2 no requiere stack:** las pruebas se diseñan contra propiedades y criterios; la ejecución
   definitiva ocurre sobre ADR-002, pero V-01…V-07 deben poder repetirse sin cambiar el criterio.
3. **Un fallo en V-01, V-03 o V-04 reabre ADR-001** (condición de revisión 3 del ADR).
4. **F4 exige F0 sin hallazgos bloqueantes** y F1 con todas las preguntas respondidas o aceptadas
   como supuesto explícito con dueño y fecha.

---

## Registro de correcciones

1. **ARR-04:** nueva **V-12** en §2 (exceso de saldo de receta en 7-C sin conexión → incidente + ajuste autorizado al reconectar, mismo patrón C1–C4, severidad H-04 de `05_security_review.md`), con V-12 añadido a la evidencia de CA-08 en §4 y título de §2 ampliado a V-01…V-12. **Por qué:** el plan no ensayaba la variante de mayor riesgo del modo degradado y V-07 no habría fallado si se producía ese exceso. **Quién aprueba:** solution-leader (plan); ejecución en F2 con devops-architect + database-specialist. **No se alteró RF/RNF/RN (RC-02), ningún valor `null` (RC-03) ni se nombró producto/stack (RC-01).**
2. **ARR-06:** tabla "Trazado de RNF sin cita" en §4 con **RNF-004** (→ P-06 en `02 §5`, CA-06 en `08 §4`, V-05), **RNF-006** (→ P-05 en `02 §5`, CA-05 en `08 §4`, V-01/V-05) y **RNF-061** (→ `08 §3` línea 58, V-11/CA-09). **Por qué:** los tres RNF no tenían ninguna cita en `03`–`06`,`08`,`09`/ADR (CA-01 incompleto). **Quién aprueba:** solution-leader. **No se alteró RF/RNF/RN (RC-02), ningún valor `null` (RC-03) ni se nombró producto/stack (RC-01).**
3. **ARR-08:** en F0 (§1) la referencia genérica "07" se sustituye por `08_final_recommendation.md` + `07_architecture_review.md`, sin renombrar archivos. **Por qué:** ambigüedad de cuál de los dos documentos entra en F0. **Quién aprueba:** solution-leader. **No se alteró RF/RNF/RN (RC-02), ningún valor `null` (RC-03) ni se nombró producto/stack (RC-01).**
4. **F0-04 (F0 · architecture-reviewer):** entrada F1 de §1 ampliada a **P-01…P-18** (antes P-01…P-17; P-18 se registró en `02 §5` en la ronda ARR-02 y la puerta `02 §6` aún cita "P-01…P-15"). **Qué:** §1 (tabla de fases) de este plan. **Por qué:** F1 debe cubrir todas las preguntas abiertas del gate. **Quién aprueba:** solution-leader (registro de cambio; `02 §6` queda como condición F0-04 fuera de este documento). **No se alteró RF/RNF/RN (RC-02), ningún valor `null` (RC-03) ni se nombró producto/stack (RC-01).**
5. **F0-02 (cierre, solution-leader):** nueva **nota de precedencia** en §4 declarando que esta tabla solo traza CA → evidencia → fase y que el estado CA definitivo vive en `07_architecture_review.md §7.3`/`§7.4` (matriz consolidada en esta ronda); corrección además de la referencia de la entrada 4 (`02 §6` es condición **F0-04**, no F0-01). **Qué:** tabla §4 de este documento. **Por qué:** §4 no contiene estados y no debe leerse como fuente de estado CA. **Quién aprueba:** solution-leader; F0-03 sigue Abierto. **No se alteró RF/RNF/RN (RC-02), ningún valor `null` (RC-03) ni se nombró producto/stack (RC-01).**
