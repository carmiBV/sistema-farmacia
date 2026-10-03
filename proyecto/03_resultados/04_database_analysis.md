# 04 — Análisis de persistencia (DEC-04 y DEC-05)

- **Agente:** database-specialist (paso 4, AGENTS.md)
- **Entrada:** `03_architecture_options.md` (§4 DEC-02/DEC-03 y §7), `02_decision_scope.md` (§1 DEC-04/DEC-05 y §3), `01_requirements_analysis.md` (D-01…D-05, D-15, R-01, R-04, R-05), `01_requisitos/RNF.md`, `01_requisitos/criterios_aceptacion.md`
- **Reglas:** RC-01 (sin productos ni motores: todas las alternativas se nombran por propiedades), RC-02 (RF/RNF/RN intactos), RC-03 (valores `null` no se inventan), CA-04 (justificación sin sesgo previo), CA-10
- **Alcance:** DEC-04 (A/B/C) y DEC-05 (A/B) comparadas por propiedades; las 10 propiedades de `03 §7` son **exigidas a todas las alternativas, no una selección** — B y C deben demostrarlas **sobre su propia frontera** (§7 del 03)
- **Fuera de alcance:** DEC-08/DEC-09/DEC-10 (devops-architect / security-reviewer) y selección de stack (ADR de solution-leader)
- **Estado:** DEC-04 y DEC-05 son **bloqueantes** (§1 de `02`): propuesta técnica completa aquí; solo la validación humana puede quedar como supuesto explícito

---

## 1. Supuestos y pendencias explícitas

| ID | Supuesto / pendencia | Fuente | Tratamiento aquí |
|---|---|---|---|
| S-1 | CAM-001/002/003 (18 propuestas) sin aprobación: saldo global de receta, política degradada, método de verificación | `registro_cambios.md`, P-14 | Supuesto explícito; no se cierra nada que dependa de ellos |
| S-2 | 25.000 txn/h sin validar | RNF-001, CA-05 | §5 usa escenarios paramétricos (base y 10x; el pico ×3,0 solo para la **tasa**), no un valor fijo |
| S-3 | RPO/RTO y prueba de restauración sin valor | RNF-041, P-01 | Solo **propiedades** de recuperación, sin objetivos numéricos |
| S-4 | Plazos de retención `null` | RNF-039, P-02 | Crecimiento expresado **por día de retención**; no se inventa plazo |
| S-5 | Latencia de trazabilidad `null` | RNF-048, P-05 | CA-10 se demuestra por **camino de acceso y completitud**, no por milisegundos |
| S-6 | `consultas_por_hora_estimadas` `null` | RNF-003, P-06 | Consultas de stock **aparte** del volumen transaccional; consultas auditadas RF-091 aparte (§5) |
| S-7 | ¿Revalidación de vencimiento/cuarentena al recibir transferencia? | P-13 | DEC-05 registra paso condicional en recepción; no se asume |
| S-8 | Ventana de retención de claves de idempotencia/identidad de negocio sin plazo | `03` §6, DEC-03 3-c | Pendencia abierta; 3-c no puede cerrarse sin ella |
| S-9 | **Observación (RC-02):** RNF-021 enumera fronteras dinero/inventario/libro y **no menciona el saldo de receta** (RF-053, D-15) | `03` §4/§7 | RNF-021 **no se modifica**; el modelado sí cubre el saldo de receta; se eleva a architecture-reviewer |
| S-10 | Horas de operación `H` sin definir (¿24×7 u horario? ¿ventanas de mantenimiento?) | P-04 | §5 mantiene `H` simbólico; no se fija horario |
| S-11 | `k` = filas por txn (≈2–6) es **supuesto de estimación**, no medición | este documento | §5 lo marca como supuesto; sustituir por medición cuando CA-05/P-06 respondan |

---

## 2. DEC-04 — Alternativas de persistencia del registro transaccional

Drivers: D-01, D-03, D-04, D-05, RNF-021, RNF-024. | Validador: architecture-reviewer.

| Opción | Definición (sin productos) | Frontera transaccional |
|---|---|---|
| **A — Atomicidad multi-registro en un registro transaccional** | Una sola unidad transaccional local contiene inventario por lote, libro de controlados, dinero, saldo de receta y auditoría | Local e indivisible: **todas** las fronteras de RNF-021 + saldo de receta (S-9) en la misma unidad |
| **B — Distribuida con partición por sucursal** | Cada sucursal opera su propia partición; el dominio se divide por unidad de negocio | Por sucursal: indivisible **dentro** de la partición; transferencia (doble asiento RF-045) y receta global (D-15) **cruzan** particiones → requiere coordinación distribuida |
| **C — Híbrida (transaccional + almacén de historial de solo lectura)** | A conserva todas las fronteras de escritura; un segundo almacén de solo lectura recibe el historial para consulta reversa, retención y reportes | Igual que A en escritura; el historial derivado **nunca es fuente de decisión** |

### 2.1 Análisis por alternativa

| Propiedad | A — transaccional único | B — partición por sucursal | C — híbrida |
|---|---|---|---|
| **Frontera (RNF-021 + S-9)** | ✅ dinero+inventario+libro+saldo de receta+auditoría en una unidad | ⚠️ local ✅; **cross-sucursal exige coordinación** (dos fases/reintento) que debe demostrarse (§7.1) | ✅ misma frontera que A; el derivado no cuenta |
| **Concurrencia (48 cajas, R-01, RNF-005)** | ✅ control local a granularidad de lote (optimista o exclusivo, DEC-02) | ✅ contención repartida por sucursal, pero el lote disputado entre sucursales cae en coordinación | ✅ idéntico a A en escritura |
| **Saldo global de receta (RF-053, D-15)** | ✅ en la misma unidad | ⚠️ **siempre cruza** particiones: verificación global con coordinación en cada dispensación | ✅ idéntico a A |
| **Idempotencia (§7.3, CA-07, DEC-03)** | ✅ clave única en la misma unidad del efecto | ⚠️ clave única **por partición** no cubre claves de ámbito global (receta, transferencia) → exige ámbito global adicional | ✅ idéntico a A |
| **Reconstrucción de saldos (RNF-024)** | ✅ desde su propio historial | ⚠️ por partición + conciliación entre particiones para libro global (RF-055) | ✅ transaccional reconstruye; derivado se **regenera** desde la fuente |
| **Recuperación (RNF-040/041, S-3)** | ✅ punto de restauración único: libro+histórico coherentes a la vez | ⚠️ restauración por partición + reintentación coordinada; mayor superficie de fallo | ✅ restaurar A; si el derivado se pierde, **se reconstruye** (propiedad exigible: reconstrucción idempotente) |
| **Retención prolongada (RN-10, R-04, S-4)** | ⚠️ histórico crece junto al transaccional | ⚠️ ídem, repartido por partición | ✅ retención/archivado en el almacén de solo lectura fuera del canal transaccional |
| **Auditoría inalterable + anonimización (RNF-046/039, D-05)** | ✅ append-only en la misma unidad; anonimización toca **solo** datos personales (columnas separadas §7.7) | ⚠️ append-only por partición; auditoría global (accesos, exceso de receta) requiere agregación | ⚠️ doble superficie: el historial derivado **también contiene PII** → misma regla de anonimización en ambos, validada por security-reviewer |
| **Aislamiento de lectura (D-10, RNF-005)** | ⚠️ reportes compiten si no se aíslan | ⚠️ ídem dentro de cada partición | ✅ lectura/retención descargada del canal transaccional |
| **Volumen 10x (CA-06, RNF-011)** | ⚠️ escritura limitada a la unidad transaccional (particionable después, §7.9) | ✅ escalado de escritura natural por sucursal (RNF-010) | ⚠️ escritura = A; lectura ✅ |
| **Complejidad operativa (CA-09, RC-10)** | ✅ una unidad que operar | ❌ coordinación distribuida, conciliación y restauración multi-parte: exige justificación medible | ⚠️ dos almacenes, con una fuente única de verdad |

### 2.2 Cómo demuestra cada una las 10 propiedades de `03 §7`

| §7 | A | B | C |
|---|---|---|---|
| 1 Atomicidad multi-registro (incl. saldo de receta) | directa | **debe demostrarla** con coordinación cross-partición: la frontera crítica (RF-045, RF-053) queda fuera de su partición | directa (en A) |
| 2 Control de concurrencia por lote (CA-03/05) | directo | directo por sucursal; cruzado, coordinado | directo |
| 3 Restricciones únicas (CA-07) | directa, ámbito único | ámbito global **pendiente de demostrar** | directa |
| 4 Historial append-only con índices (CA-10) | directo | por partición + índice global de consulta | en el almacén de solo lectura (su fuerte) |
| 5 Retención con consulta por rango (R-04) | con partición/archivado temporal | por partición | ✅ natural |
| 6 Aislamiento de lectura (D-10) | con mecanismo externo | con mecanismo por partición | ✅ natural |
| 7 Cifrado/repo y separación de PII (§7.7) | columna/tabla separada de PII | ídem por partición | PII en **ambos** almacenes → regla doble |
| 8 Linux/contenedor, config por entorno, sin secretos | ✅ propiedades neutras | ✅ | ✅ |
| 9 Escalado 10x sin rediseñar (CA-06, RNF-010) | con partición posterior | ✅ natural | ✅ en lectura |
| 10 Backup/restauración probables (S-3) | ✅ punto único | multi-punto coordinado | A restaura; derivado se regenera |

### 2.3 Persistencia local para el modo degradado (DEC-07 — Opciones C/L de `03 §4`)

DEC-07 es del **architect** y es **bloqueante** (valida cliente + químico): **aquí no se cierra y no se elige mecanismo**. Solo se fijan las propiedades que debe demostrar la persistencia local del modo degradado — cola de escritura + caché de catálogos de la **Opción C** (componente mínimo) y estado local + cola de sincronización de la **Opción L** (= ALT-4) — respetando la frontera de DEC-04 (A: una unidad transaccional; C: componente mínimo sobre ella). Escenarios según matriz de `03 §4`: 7-A sin habilitar (detención controlada), 7-B venta libre sin conexión, 7-C bajo receta sin conexión (**bloqueado por P-15**: `permitir_bajo_receta_sin_conexion` = `null`).

| Propiedad exigida (sin imponer mecanismo) | Por qué / fuente | Opción C — componente mínimo | Opción L — estado local (= ALT-4) |
|---|---|---|---|
| **Durabilidad de lo encolado:** lo confirmado en el puesto no se pierde por corte de energía/reposo | continuidad del mostrador (restricción 9, RNF-043); **no se fija objetivo**: RPO/RTO `null` (P-01, S-3) | cola de escritura persistente en sucursal/caja | estado + cola persistentes en el puesto |
| **Idempotencia con doble identidad local/global:** clave local al confirmar en el puesto, clave global al sincronizar; el reintento no duplica efecto | CA-07 (las 4 operaciones) + DEC-03/ALT-4 (`03 §4`: 3-a aplicada dos veces) | misma regla en ámbito sucursal/caja | misma regla: ámbito local → ámbito global |
| **Cifrado en reposo del puesto (RNF-036):** cola, caché y estado local cifrados en reposo | RNF-036; el puesto residirá en él | cifrar cola + caché de catálogos | cifrar estado + cola |
| **PII temporal con borrado al sincronizar:** los datos personales solo se conservan mientras dure el corte y se eliminan al vaciar la cola | minimización y retención (RNF-039; CAM-002-g → P-14) | PII de venta libre en cola | PII de pacientes/recetas en estado local |
| **Registro del incidente (RF-047):** al reconectar, si el consumo acumulado de un lote supera su stock → incidente + ajuste autorizado; **nunca stock negativo silencioso** | RF-047, RN-02, D-07; RF-047 cubre stock **no** saldo de receta (I-14) → 7-C se prueba con criterio **D-15/RF-053** | incidentes en 7-B | incidentes en 7-B; detección de excesos en 7-C |
| **Conciliación al reconectar:** reintentos ordenados e idempotentes, aplicados dentro de la frontera de DEC-04, con detección de excesos en 7-C | RNF-045, D-15; la **política** de degradación la decide P-03/P-15, no este documento | cola vaciada contra la unidad transaccional | cola vaciada + reconciliación de estado local contra la central |

Notas: la **Opción R** (solo lectura) no escribe → solo consulta, las propiedades 1 y 2 no aplican. **7-C no se cierra aquí** (P-15 abierta; `03 §4`): se documenta la vía técnica (Opción L), falta la decisión del cliente. Validan: **security-reviewer** (cifrado RNF-036, PII/borrado, superficie del puesto) y **devops-architect** (durabilidad, conciliación, incidentes RF-047) — fila en §7.

---

## 3. Matriz DEC-04 (criterios de `02 §3`, veredicto sintético)

| Criterio | A | B | C |
|---|---|---|---|
| Integridad transaccional (RNF-021, S-9) | ✅ | ⚠️ coordinación | ✅ |
| Concurrencia 48 cajas + receta global | ✅ | ⚠️ / ✅ local | ✅ |
| Reconstrucción de saldos (RNF-024) | ✅ | ⚠️ global | ✅ |
| Retención de años (R-04, P-02) | ⚠️ | ⚠️ | ✅ |
| Recuperación consistente (P-01) | ✅ | ⚠️ | ✅ + regeneración |
| Escalado escritura 10x (CA-06) | ⚠️ | ✅ | ⚠️ |
| Mantenimiento/despliegue Linux+contenedor (RNF-060…063) | ✅ | ✅ (más piezas) | ✅ |
| Complejidad proporcional (CA-09) | ✅ | ❌ salvo justificación medible | ⚠️ |
| **Veredicto** | **cumple las 10 propiedades con los mecanismos declarados en §2.2** | **cumple con mecanismos a demostrar** | **cumple y alivia retención/lectura** |

---

## 4. DEC-05 — Modelo de trazabilidad y demostración de CA-10

Drivers: D-03, D-04, RN-01, RN-07, RN-08, RN-12, RNF-045, RNF-048. | Validador: architect. | CA-10 exige **dos** consultas.

| Opción | Modelo | Fuente de saldo |
|---|---|---|
| **A — Historial de movimientos como fuente de saldo con índices** | Una fila por movimiento (recepción, transferencia, dispensación, devolución, baja, ajuste) con claves de lote, sucursal, receta, paciente, responsable; saldos como agregado o saldo materializado **reconstruible** (RNF-024) | El historial **es** la fuente: el saldo se deriva de él |
| **B — Eventos con proyecciones dedicadas** | Log append-only de eventos de dominio + proyecciones (stock por sucursal, dispensaciones por lote, detalle de dispensación) actualizadas en la misma unidad o de forma diferida | La proyección materializa el saldo; los eventos permiten **reconstruir** cualquier proyección |

> **Nota DEC-05 A — saldo materializado vs agregado de movimientos:** si A usa **saldo materializado por lote**, todas las escrituras del lote más disputado recaen en **la fila más contendida** (48 cajas, R-01; DEC-02 control de versión sobre esa fila). **Agregar desde el historial** reparte la contención en los índices del historial y conserva la reconstrucción (RNF-024). Ambas vías cumplen §7.4 del `03` (exige historial + índice, no mecanismo): elija el mecanismo quien cierre DEC-02/DEC-04 (architect + database-specialist). No cambia RF/RNF (RC-02).

> **Nota DEC-05 A — devoluciones (RF-060, RN-11):** la **devolución** (parcial o total) es una fila más del historial con las mismas claves (lote, sucursal, receta, paciente, responsable) y entra en el índice `(lote_id, tipo, fecha)` de §4.1; con esto la enumeración de tipos de movimiento queda coherente con **RF-041** (entradas, salidas, ajustes, bajas y transferencias) + **RF-060** (devoluciones). **RN-11:** el producto devuelto **no vuelve al stock vendible sin evaluación**, por lo que la devolución **no acredita stock por sí sola**: el acreditado, si procede, es un movimiento posterior tras la evaluación. La política vigente (estados, plazo, condiciones de reingreso) es el parámetro **RF-100** → **P-08** (cliente, `02 §5`; valores `null` → no se fijan aquí, RC-03) y el ciclo de vida detallado de la devolución se fija en fase de diseño (RN-11), no en este análisis. No cambia RF/RNF/RN (RC-02). Corrección ARR-02/AR-08.

### 4.1 CA-10, consulta 1: **lote → recepción, stock por sucursal y dispensaciones**

| Requerido por CA-10 | A (camino de acceso) | B (camino de acceso) |
|---|---|---|
| Recepción del lote | Movimiento de recepción con `lote_id` + `remision_id`; índice `(lote_id, tipo, fecha)` y **única** `(lote_id, remision_id)` | Evento `lote_recibido` con `lote_id`, `remision_id`; proyección `recepciones_por_lote`; unicidad por clave de idempotencia de la recepción (CA-07) |
| Stock por sucursal | Agregado de movimientos por `(lote_id, sucursal_id)` (o saldo materializado reconstruible, RNF-024) | Proyección `stock_por_sucursal(lote_id, sucursal_id, saldo)` derivada de los eventos |
| Dispensaciones del lote | Movimientos de dispensación con `lote_id`; índice `(lote_id, tipo, fecha)` → entrega dispensación, fecha, caja, sucursal | Proyección `dispensaciones_por_lote(lote_id, dispensacion_id, fecha)` enlazada al evento de dispensación |
| Completitud / reconstrucción | ✅ misma transacción que aplicó el movimiento (RNF-024 directo) | ⚠️ exige **garantía de no pérdida** (secuencia/offset verificable) y reconstrucción total desde eventos |

> **Nota DEC-05 — RN-07 (`00_contexto/reglas_negocio.md:8`, recepción de compras):** la regla exige que la recepción **actualice inventario solo al confirmarse** y que **exija lote, vencimiento y cantidad recibida**. En §4.1 eso ya condiciona las dos opciones: en **A**, el movimiento de recepción acredita stock en la misma transacción en que se confirma y la **única** `(lote_id, remision_id)` garantiza una sola confirmación por remisión —parcial o total— (CA-07; RF-031 trazado en §10); en **B**, la proyección `stock_por_sucursal` solo cambia cuando se acepta el evento `lote_recibido`, con la misma unicidad por clave de idempotencia. Lote, vencimiento y cantidad recibida son los datos que RF-031 exige registrar (§10) y que §5 C2 verifica al bloquear lote vencido/cuarentena. **RC-02:** se cita RN-07, no se modifica; ningún valor `null` se fija (RC-03).

> **Nota DEC-05 — RN-08 (`00_contexto/reglas_negocio.md:9`, transferencias entre sucursales):** la regla exige **estados controlados** (solicitada → despachada → recibida → cerrada/rechazada) y que la transferencia **conserva el lote**. Condiciona §4 en las dos opciones: la transferencia es un tipo de movimiento del historial con `lote_id` y sucursal origen/destino (mismo grano que RF-040 y el doble asiento RF-045 de §5 `k`), y **cada transición de estado queda registrada en orden y con la misma clave de lote** —en **A** como filas del historial, en **B** como eventos de dominio con `lote_id`—, de modo que el estado consultable se deriva de lo registrado y el lote conserva su identidad de origen a destino. Aquí **no** se fija qué hace el sistema en cada transición (detalle de fase de diseño) ni se cierra **P-13** (¿revalidación al recibir?), que sigue abierta (§8, §6 condición 6). **RC-02:** se cita RN-08, no se modifica.

### 4.2 CA-10, consulta 2: **dispensación → paciente, receta, lote y responsable**

| Requerido por CA-10 | A | B |
|---|---|---|
| Registro único de dispensación | Fila de movimiento con `dispensacion_id`, `paciente_id`, `receta_id` (nullable en venta libre), `lote_id`, `responsable_id`, `fecha`, `importe`; índice único `(dispensacion_id)` + `(paciente_id, fecha)`, `(receta_id, fecha)` | Evento `dispensacion_confirmado` con clave única de confirmación (CA-07) y referencias a paciente, receta, lote y responsable |
| Responsable y auditoría | Misma unidad que auditoría append-only (RNF-046): usuario→acción sin hueco | Proyección `dispensacion_detallada` + evento de auditoría asociado (misma clave de correlación, RNF-052) |
| Conciliación del libro (D-04) | Saldo del libro concuerda con el historial por sucursal (mismo origen) | Saldo del libro = proyección reconstruible; conciliación por reproceso de eventos |
| PII y anonimización (RNF-039) | Identificadores personales en campos separados de montos/lotes/libro → anonimizar solo PII preserva saldos e historial | PII **dentro del payload del evento**: exige separar PII del cuerpo del evento (referencia a ficha anonimizable) o entra en tensión con RNF-023/046 (I-11, DEC-09 de security-reviewer) |

### 4.3 Comparación de propiedades DEC-05

| Propiedad | A — historial con índices | B — eventos con proyecciones |
|---|---|---|
| Exactitud histórica (CA-10) | ✅ consulta directa sobre lo ocurrido | ✅ eventos inmutables + proyección reconstruible |
| Frescura | ✅ inmediata (misma escritura) | ⚠️ diferida: **nunca decidir con proyección atrasada** (RN-02; frontera explícita de lecturas permitidas) |
| Idempotencia (CA-07, DEC-03) | clave única por efecto | clave única por evento: evita **doble proyección** |
| Concurrencia de escritura (48 cajas) | sin coste añadido (ya está en la frontera) | proyección en la misma unidad = más escritura; diferida = más lógica |
| Retención (R-04, S-4) | ⚠️ crece con el transaccional (o en el derivado de DEC-04 C) | ✅ eventos fríos y proyecciones calientes separables |
| Volumen 10x de lectura (CA-06) | ✅ con índices hasta el punto que fije P-05 | ✅ proyecciones dedicadas por consulta |
| Recuperación (P-01) | restauración del historial | restauración + **reproceso** de proyecciones (debe ser idempotente) |
| Coste de operación | ✅ un modelo | ⚠️ dos modelos y verificación de frescura |
| **Pareo natural con DEC-04** | A o C | C (proyecciones en el almacén de solo lectura) o A con proyección interna |

---

## 5. Volumen, crecimiento y recuperación (estimación paramétrica; sin inventar valores)

**Variables (S-2, S-4, S-6, S-10, S-11):**

- `T` = txn/h (base 25.000 y 10x ×10 — a validar, S-2). El **pico ×3,0 se aplica solo a la tasa (txn/s), nunca al volumen diario**.
- `H` = horas de operación por día — **P-04 abierta** (¿24×7 u horario?, S-10): no se fija; `H` queda simbólico.
- `k` = filas por txn (dispensación ≈ 2–6: movimiento + asiento de libro si controlado + auditoría + descuento de receta + clave de idempotencia; transferencia: doble asiento RF-045) — **supuesto de estimación, no medición** (S-11).
- `D` = días de retención (**`null` → no se fija**, S-4).
- `Q` = consultas/modificaciones de pacientes y recetas auditadas por hora (**RF-091**) — **fuera de `T`**; volumen `null` (P-06) y su retención `null` (P-02) → pendiente de ambas.

**Fórmulas:** `filas/día = T × H × k` · `filas_totales = T × H × k × D` · `tasa txn/s = T / 3600` (aquí aplica el pico ×3,0).

| Escenario | txn/h (T) | tasa txn/s (= T/3600; pico ×3,0 solo aquí) | Filas/día = T×H×k | Filas totales = T×H×k×D |
|---|---|---|---|---|
| Base (a validar, S-2) | 25.000 | ≈6,9 (con pico ≈20,8) | 25.000·H·k | 25.000·H·k·D |
| 10x (CA-06) | 250.000 | ≈69,4 (con pico ≈208) | 250.000·H·k | 250.000·H·k·D |

Magnitud **ilustrativa** (RC-03 — **no es propuesta de retención ni de horario**): con `k=3` (supuesto) y `D=365` (ilustrativo, no retención propuesta): base → `75.000·H` filas/día y ≈`27,4 M·H` filas; 10x → `750.000·H` filas/día y ≈`274 M·H`.

| Tema | Propuesta sin valores inventados |
|---|---|
| Crecimiento (R-04) | Filas ≈ `T × H × k × D`; al responder P-02 y P-04 el número sale de la fórmula. Mecanismo exigible a **toda** alternativa: partición/archivado **por rango de tiempo** que no rompa CA-10 (§7.5) |
| Consultas de stock (S-6) | Se miden aparte (RNF-003) y no entran en `T` hasta que P-06 confirme |
| Consultas auditadas RF-091 (`Q`) | RF-091 registra cada consulta o modificación de pacientes y recetas: **no entra en `T`**; volumen `null` (P-06 `consultas_por_hora_estimadas`) y retención de esa auditoría `null` (P-02) → se modela como serie aparte `Q × H` cuando ambas respondan |
| Recuperación (S-3) | Sin RPO/RTO: se exige **propiedad**, no objetivo: (i) punto de restauración que deje libro, inventario y auditoría **coherentes entre sí**; (ii) prueba periódica de restauración (RNF-040); (iii) en DEC-04 C, el historial derivado debe **regenerarse** desde la fuente; (iv) en DEC-04 B, la restauración multi-partición debe garantizar conciliación del libro global |
| Auditoría vs anonimización (RNF-046/039) | Ambas opciones de DEC-05 conservan montos, lotes, cantidades y libro; la anonimización toca **solo** PII de pacientes/prescriptores, en campos separados; la tensión con la inalterabilidad (I-11) es de DEC-09 → security-reviewer |

### Criterio de verificación de CA-03 y CA-05 (cuantitativo — cierre de AR-03)

| Elemento | Especificación |
|---|---|
| **Métrica C1** (CA-03) | Ocurrencias de **stock negativo** bajo concurrencia: **cero** en todas las corridas. |
| **Métrica C2** (CA-03) | Ocurrencias de **salida de lote vencido, en cuarentena o retirado**: **cero**. |
| **Métrica C3** (D-15, saldo de receta) | Dispensaciones concurrentes que superan lo prescrito: **cero**. |
| **Métrica C4** (RF-046, DEC-02x) | Ajustes/bajas de controlados efectivos sin dos identidades distintas: **cero**; autorización doble simultánea con doble efecto: **cero**. |
| **Método** | Prueba automatizada: **48 sesiones de caja simultáneas** (8 sucursales × 6 cajas) disputando un único lote FEFO de menor vencimiento, hasta agotarlo en mitad de la corrida. Dos variantes: (i) red estable; (ii) corte y reconexión simulados (RF-047) — tras reconectar, saldo nunca negativo. Nº de ciclos: parámetro de prueba que fija database-specialist (no es valor de negocio). |
| **Registro por corrida** | id de corrida, dispensaciones, conflictos de versión, reintentos, resultados C1–C4, latencia de dispensación. |
| **Umbral C1–C4** | **0** (invariante, no es parámetro). |
| **Umbral de tasa de conflicto y p95** | Se **reporta**; umbral = objetivo de P-05 (`null` → no se fija, RC-03) y validación de CA-05 según P-06 (25.000 txn/h `a validar`). |
| **Responsables** | database-specialist (diseño) + devops-architect (entorno); evidencia → ADR-001 y `09_validation_plan.md` (V-01). |

---

## 6. Recomendación preliminar (sujeta a security-reviewer, devops-architect, architecture-reviewer y ADR)

| Decisión | Recomendación | Por qué (propiedades) | CA de verificación |
|---|---|---|---|
| **DEC-04** | **A como base**, con **C como camino de crecimiento** (escalonar historial/retención/lectura a un almacén de solo lectura cuando P-02/R-04 lo justifiquen); **B solo si** una prueba de carga demuestra contención insostenible a 10x | A cumple las 10 propiedades de §7 con los mecanismos declarados en §2.2 y con la complejidad mínima (CA-09); C añade lo que A tiene débil (retención, lectura) **sin mover la frontera transaccional**; B paga coordinación distribuida en las fronteras críticas (RF-045, RF-053) que solo se acepta con justificación medible | CA-04 (matriz §3 por requisitos, sin nombrar productos), CA-06/CA-05 (§5), CA-09 |
| **DEC-05** | **A (historial con índices) como modelo base**, con proyecciones **derivadas** permitidas como optimización de lectura; B solo si P-05 fija latencias que A no alcance y demostrando reconstrucción total desde la fuente | CA-10 se demuestra en A con camino de acceso directo y sin problema de frescura; el riesgo de B (proyección atrasada leída en venta) toca RN-02 | CA-10 (§4.1/§4.2), RNF-024, RNF-048 (P-05) |
| **DEC-03 (validación como validador de `02`)** | **3-a (clave de idempotencia persistida) validada**: la clave vive en la misma unidad del efecto — en DEC-04 A y C directo; en B exige ámbito global para receta/transferencia | Cobertura de las 4 operaciones de la tabla de `03 §4`; 3-c queda **sin cerrar** por S-8 (ventana de claves sin plazo) | CA-07 |

**Condiciones de cambio de esta recomendación (reabrir si ocurren):**

1. **P-02** fija plazos de retención largos que hagan insostenible el histórico junto al transaccional → escalar a DEC-04 **C** (o evaluar B).
2. **P-01** fija RPO/RTO exigibles que ninguna opción cumpla con sus mecanismos actuales → reevaluar §5/§6.
3. **CA-05/CA-06** (P-06) muestran contención insostenible sobre lotes disputados a 10x → evaluar DEC-04 **B** con su justificación medible.
4. **security-reviewer** no acepte PII en el historial derivado o exija cifrado/anonimización distintos por opción → ajustar §4/§5.
5. **architecture-reviewer** (CA-12) hallazgo bloqueante, o resolución de S-9 (saldo de receta en RNF-021) → rehacer frontera §2.1.
6. **P-13** exige revalidación en recepción → añadir paso de unicidad/revalidación en el flujo de recepción (DEC-05).
7. Ampliación de alcance (RC-13, P-10) → repetir contexto → requisitos → este análisis.

---

## 7. Insumos para security-reviewer y devops-architect

| Insumo | Para | Qué debe evaluar | Fuente |
|---|---|---|---|
| PII en historial derivado (DEC-04 C) y en payload de eventos (DEC-05 B) | security-reviewer | Cifrado en reposo, minimización, anonimización sin tocar montos/lotes/libro; tensión I-11 con inalterabilidad | RNF-036/038/039, CAM-002-g, P-02, P-14 |
| Retención y archivado por rango de tiempo | security-reviewer | DEC-09: auditoría en la misma unidad vs almacén independiente; plazos (S-4) | RNF-039/046, DEC-09 |
| Separación de identificadores personales de montos/libro (§7.7 del 03) | security-reviewer | Que la anonimización preserva saldos e historial en **ambas** opciones de DEC-05 | D-05, RNF-039 |
| Persistencia local del modo degradado (§2.3, Opciones C/L de DEC-07) | security-reviewer **y** devops-architect | security: cifrado en reposo del puesto (RNF-036), PII temporal con borrado al sincronizar. devops: durabilidad de la cola, idempotencia de la conciliación (doble clave local/global), registro de incidentes RF-047 y detección de excesos D-15; **sin fijar RPO/RTO** (S-3/P-01); 7-C **no se cierra** (P-15) | DEC-07 (Opciones C/L), RF-047, RNF-036, P-15 |
| Punto de restauración único (A) vs multi-partición (B) vs regeneración de derivado (C) | devops-architect | Estrategia de backup/restauración y pruebas; sin fijar RPO/RTO (S-3) | RNF-040/041, P-01, DEC-10 |
| Escalado de escritura 10x (A/C limitados, B natural) | devops-architect | DEC-08: cómo absorber carga y **aislar reportes** del canal transaccional | CA-05/CA-06, D-10, DEC-08 |
| Latencias de consulta reversa y métricas (P-05 `null`) | devops-architect | Observabilidad de consultas CA-10 sin inventar objetivo | RNF-048/051, P-05 |
| Linux, contenerización, config por entorno, sin secretos | devops-architect | Verificación de despliegue de cualquiera de las tres opciones | RC-10, RC-11, RNF-060…063 |

---

## 8. Preguntas abiertas (afectan el cierre de DEC-04/DEC-05)

| ID | Pregunta | Afecta |
|---|---|---|
| **P-01** | RPO/RTO y prueba de restauración | §5 recuperación; condición de cambio 2 |
| **P-02** | Plazos de retención y normativa | §5 crecimiento; condición de cambio 1; pareo A vs C |
| **P-04** | Horario de operación 24×7 u horario (¿ventanas de mantenimiento?) | §5 variable `H` (S-10) |
| **P-05** | Latencia p95 de trazabilidad | DEC-05 A vs proyecciones (B) |
| **P-06** | ¿Consultas de stock en las 25.000 txn/h?, `consultas_por_hora_estimadas` y consultas auditadas **RF-091** (`Q`) | §5 volumen (S-6) |
| **P-13** | ¿Revalidación al recibir transferencia? | DEC-05 flujo de recepción |
| **P-14** | Aprobación de CAM-001/002/003 | S-1: saldo de receta, política degradada, verificación en dispensación |
| **P-18** | RF-044: umbrales de alerta (stock mínimo, días de vencimiento — RF-100, "valores a definir por el cliente") y ventana de evaluación que no compita con el canal transaccional | §4.1/§5 (dato ya modelado); ventana → DEC-08 con devops-architect. **Registrada en `02 §5` (solution-leader)**, patrón S-8/S-9 → P-16/P-17 (AR-02) |
| **S-8** | Ventana de retención de claves de idempotencia (DEC-03 3-c) | Cierre de 3-c; numeración formal pendiente en `02 §5` |
| **S-9** | RNF-021 no menciona el saldo de receta | Frontera §2.1 y §7.1 del 03 → architecture-reviewer |

---

## 9. Cobertura de criterios de aceptación

| CA | Texto (resumen) | Cobertura en este documento |
|---|---|---|
| **CA-04** | Justificar la base de datos sin sesgo previo | ✅ DEC-04 compara **A/B/C** y DEC-05 compara **A/B** únicamente por propiedades derivadas de RF/RNF/RN/D/R (§2, §4); **ningún producto o motor se nombra**; el veredicto §3 sale de criterios de `02 §3`, no de preferencia; ninguna opción es "por defecto" |
| **CA-10** | Lote → recepción + stock por sucursal + dispensaciones; dispensación → paciente, receta, lote, responsable | ✅ demostrada **por separado para A y B** con camino de acceso, índices/unicidad y garantía de completitud (§4.1, §4.2) |
| CA-01 | Decisiones mapeadas a RF/RNF/RN | ✅ cabeceras de §2 (D-01…D-05, RNF-021/024) y §4 (D-03, D-04, RN-01, RN-07, RN-08, RN-12, RNF-045/048; RN-07 y RN-08 con nota propia en **§4.1**); **§10** traza los 13 RF que ARR-02 halló sin cita (cobertura de RF completa) |
| CA-02 | ≥2 alternativas | ✅ 3 en DEC-04, 2 en DEC-05, más validación de DEC-03 |
| CA-05 / CA-06 | 25.000 txn/h y 10x | ✅ §5 paramétrico (T, H, k, D) sin fijar valor (S-2); pico ×3,0 solo para la tasa (txn/s) |
| CA-07 | Idempotencia de las 4 operaciones | ✅ §6 (validación de 3-a) y unicidad en §4.1/§4.2 |
| CA-08 | Seguridad, privacidad, observabilidad, recuperación | ⚠️ aquí solo **propiedades** de recuperación y anonimización (§5); completo con security-reviewer y devops-architect (§7) |
| CA-09 | Sin K8s/microservicios sin justificación | ✅ ninguna opción introduce orquestación/microservicios; B exige justificación medible si se evalúa (§6) |
| CA-11 | Datos sensibles y retención | ✅ §4.2/§5 (PII separado, retención `null` sin inventar) → cierre con security-reviewer |
| CA-12 | Sin hallazgos bloqueantes | ⬜ pendiente de architecture-reviewer |

**RC-01/RC-02/RC-03 cumplidos:** sin stack ni código; RF/RNF/RN intactos (S-9 solo se observa, no se edita); todos los valores `null` permanecen `null` y figuran como supuesto o pregunta abierta.

---

## 10. Cobertura de RF (cierre de ARR-02)

Los 13 RF que `07_architecture_review.md` (ARR-02) detectó **sin ninguna cita** en `03`–`08` y los ADR quedan trazados aquí, **uno por fila y con un único estado**. Criterio: **Cubierto** = la exigencia del RF (su efecto de integridad/concurrencia/retención/auditoría, no el CRUD de alta) ya condiciona una sección de este documento o de `03` aunque se citara bajo otro ID; **Pregunta abierta con dueño** = su cierre depende de un valor `null`/a definir por un humano, con dueño y regla de cierre; **Sin impacto arquitectónico** = CRUD funcional sin reglas nuevas de integridad, concurrencia, retención o auditoría —coincide con `01:72`, que los declara "no críticos para la arquitectura", y con `01:31`, "CRUD convencional"—. **RC-02:** no se editó ningún RF/RNF/RN. **RC-03:** ningún valor `null` se fija. **RC-01:** sin productos ni stack.

| RF | Exigencia (texto de `RF.md`) | Estado | Trazado / justificación |
|---|---|---|---|
| RF-001 | Administrar usuarios, roles y permisos (químico, auxiliar, cajero, administrador, auditor) | **Cubierto en `04`** | A diferencia de los CRUD siguientes **no** está en `01:72`: `01:31` lo declara "base de autorización (RNF-030/031)" y su efecto de modelo ya condiciona §5 C4 ("dos identidades distintas" para ajustes/bajas de controlados) y §4.2 (`responsable_id` → auditoría usuario→acción sin hueco, RNF-046). El CRUD de alta/edición es funcional; lo trazado aquí es el modelo de roles que hace evaluables esas secciones |
| RF-002 | Administrar pacientes/clientes | **Sin impacto arquitectónico** | CRUD funcional (`01:72`); el paciente entra en el modelo por CA-10 (§4.2 `paciente_id`, índice `(paciente_id, fecha)`) y su PII por RNF-039/RF-091 (§4.2, §5), ya citados |
| RF-003 | Administrar prescriptores | **Sin impacto arquitectónico** | CRUD funcional (`01:72`); el prescriptor es un dato del registro de receta de RF-052 (trazado abajo, §4.2) |
| RF-010 | Administrar sucursales y cajas/POS | **Sin impacto arquitectónico** | CRUD funcional (`01:72`); la dimensión sucursal/caja ya condiciona §2.1 (partición por sucursal de DEC-04 B), §4.1 (`(lote_id, sucursal_id)`) y el escalado sin rediseño por RNF-010 (§2.2, fila 9) |
| RF-020 | Administrar catálogo de medicamentos y productos (principio activo, presentación, concentración, condición de venta, categorías, precios, promociones) | **Sin impacto arquitectónico** | CRUD funcional (`01:72`); su atributo determinante —la **condición de venta**— es la puerta de RF-052/RF-053, trazados abajo (§4.2: `receta_id` **nullable en venta libre**); precios/promociones no condicionan DEC-04/DEC-05 |
| RF-030 | Administrar proveedores y órdenes de compra | **Sin impacto arquitectónico** | CRUD funcional (`01:72`); el efecto arquitectónico de la compra es la recepción con `remision_id`, trazada bajo RF-031 (§4.1) |
| RF-031 | Registrar recepción parcial o total exigiendo lote, fecha de vencimiento y cantidad recibida | **Cubierto en `04`** | §4.1: movimiento de recepción con `lote_id` + `remision_id`, índice `(lote_id, tipo, fecha)` y **única** `(lote_id, remision_id)` → parcial/total **idempotente** (CA-07); lote, vencimiento y cantidad son claves del movimiento/lote (§4.1, §5 C2). Relacionada: **P-13** (¿revalidación al recibir transferencia?) → condición de cambio 6 (§6) |
| RF-032 | Dejar un lote en cuarentena al recibirlo hasta su liberación por el químico; la liberación se registra con usuario, fecha y motivo | **Cubierto en `04`** | §5 C2: métrica "salida de lote vencido, en cuarentena o retirado: **0**" (bloqueo exigido); la liberación registrada (usuario, fecha, motivo) cae en §4.2 (auditoría append-only, usuario→acción sin hueco, RNF-046) con §5 C4 (doble identidad). Relacionada: **P-13** |
| RF-040 | Mantener inventario por producto, lote y sucursal | **Cubierto en `04`** | El grano (producto, lote, sucursal) **es** el eje de DEC-04/DEC-05: §2.1 (concurrencia a granularidad de lote, 48 cajas), §4.1 (agregado por `(lote_id, sucursal_id)`), §5 C1 (stock negativo = 0) |
| RF-044 | Generar alertas de stock mínimo y de productos próximos a vencer | **Pregunta abierta con dueño → P-18** (nueva) | El dato sobre el que alerta ya está modelado (§4.1 stock por lote/sucursal; vencimiento como clave del lote en §4.1/§5 C2), pero los **umbrales** (stock mínimo, días de vencimiento) son parámetros de **RF-100** ("valores a definir por el cliente") y la **ventana de evaluación** no debe competir con el canal transaccional (§2.1 aislamiento de lectura D-10; §7 fila "escalado 10x" → DEC-08 con devops-architect). Dueños: **cliente** (valores) + **devops-architect** (ventana → DEC-08). Regla de cierre: aceptados los parámetros, la ventana se fija en DEC-08; aquí **no** se fija valor (RC-03) ni se edita RF (RC-02). Registro de P-18 en `02 §5` (solution-leader) |
| RF-052 | Registrar la receta (prescriptor, paciente, fecha, medicamento, cantidad) cuando la condición de venta lo exija | **Cubierto en `04`** | §4.2: `receta_id` **nullable en venta libre** —exactamente la exigencia— con índice `(receta_id, fecha)`; §2.1/§2.2 (frontera y atomicidad incl. saldo de receta, S-9); §6 condición de cambio 5. Forma del registro (¿solo datos o imagen?) → **P-07** (cliente, `02:140`) |
| RF-060 | Registrar devoluciones parciales o totales según la política vigente; el producto devuelto no vuelve al stock vendible sin evaluación | **Cubierto en `04`** | Corrección ARR-02/AR-08: **devolución** añadida a la enumeración de tipos de movimiento de §4 + **Nota DEC-05 A — devoluciones** (mismas claves e índice de §4.1; no acredita stock vendible sin evaluación, RN-11). La política vigente (estados, plazo, reingreso) es **RF-100** → **P-08** (cliente, `02:141`, valores pendientes); el ciclo de vida detallado se fija en fase de diseño (RN-11) |
| RF-071 | Generar reportes regulatorios de medicamentos controlados y dispensaciones por período | **Pregunta abierta con dueño → P-02** (ya registrada en `02 §5`) | Exige conservar el historial ≥ horizonte regulatorio: §5 (`D` = `null` → **no se fija**) y partición/archivado **por rango de tiempo** que no rompa CA-10 (§5 tabla "Crecimiento (R-04)"; §2.2 fila 5); conciliación del libro por período en §4.2 (D-04); cierre condicionado en §6 condición de cambio 1. Dueño: **cliente + asesoría legal** (`02:135`). Regla de cierre: respondida P-02 se fija `D` y se valida que la partición cubra el período regulatorio; mientras, supuesto explícito S-4 y en el ADR |

> **P-18 (nueva por ARR-02):** RF-044 — ¿qué stock mínimo y cuántos días de alerta de vencimiento aplica RF-100 (hoy "valores a definir por el cliente"), y con qué ventana se evalúan sin competir con el canal transaccional? Dueño: **cliente** (valores) + **devops-architect** (ventana → DEC-08). Regla de cierre: valores aceptados por el cliente y ventana fijada en DEC-08; **aquí no se fija ningún valor** (RC-03). **Registrada en `02 §5` por solution-leader** (mismo patrón que S-8/S-9 → P-16/P-17, AR-02); con ello queda en la puerta de salida de `02 §6`.

## Registro de correcciones

1. **AR-03 (CAM-004-c, hallazgo de architecture-reviewer):** criterio de verificación de CA-03/CA-05 añadido en §5 (métricas C1–C4 con umbral 0, método con 48 cajas, variantes con corte simulado); p95 y volumen no fijados (RC-03: P-05/P-06). **Por qué:** la recomendación de §6 declaraba CA-03/CA-05 cumplidos sin umbral cuantitativo verificable. **Quién aprueba:** database-specialist (diseño) + devops-architect (entorno); firma final con el ADR-001. **No se alteró ningún RF/RNF/RN (RC-02) ni se fijó ningún valor `null` (RC-03).**
2. **ARR-02 + ARR-07 (ronda de architecture-reviewer, `07_architecture_review.md`):** **ARR-02** — nueva **§10 (Cobertura de RF)** con una fila por cada uno de los 13 RF sin cita: RF-001, RF-031, RF-032, RF-040, RF-052 y RF-060 → *Cubierto en `04`* con sección citada (RF-001 justificado aparte de `01:72`: es base de autorización, `01:31`, y condiciona §5 C4/§4.2); RF-044 → *pregunta abierta* **P-18** (cliente + devops-architect; registro en `02 §5` pendiente de solution-leader, patrón S-8/S-9 → P-16/P-17) y RF-071 → *pregunta abierta* **P-02** (cliente + asesoría legal, ya en `02 §5`); RF-002/003/010/020/030 → *sin impacto arquitectónico* (CRUD funcional; `01:31`/`01:72`). Además **devolución** añadida a la enumeración de tipos de movimiento de §4 (AR-08: `04:110`) con la **Nota DEC-05 A — devoluciones**, y fila **P-18** en §8. **ARR-07** — verificada presente en §5 la etiqueta «Magnitud **ilustrativa** (RC-03 — **no es propuesta de retención ni de horario**)»: se **mantiene** y esas cifras (k=3, D=365) no se reutilizan como propuesta mientras P-02 siga `null`. **Por qué:** 7 RF críticos (recepción, cuarentena, inventario, stock mínimo, receta, devoluciones, reportes regulatorios) no tenían trazado a decisión ni a pregunta con dueño → CA-01 incompleto. **Quién aprueba:** database-specialist (trazado en este documento) + solution-leader (registro de P-18 en `02 §5`); firma final con el ADR-001. **No se alteró ningún RF/RNF/RN (RC-02), ningún valor `null` (RC-03) ni se nombró producto/stack (RC-01).**
3. **ARR-02 — cierre de la marca de pendencia de P-18:** las marcas **"Registro formal pendiente en `02 §5`"** de §8 (fila P-18), §10 (nota RF-044) y §10 (párrafo de P-18) pasan a **"registrada en `02 §5`"**, una vez solution-leader añadió la fila P-18 tras P-17. **Qué:** actualización de estado, no de contenido. **Por qué:** el registro ya existe en `02 §5`; dejar "pendiente" contradecía la fuente de verdad. **Quién aprueba:** solution-leader (dueño de `02 §5`), con database-specialist. **No se alteró RF/RNF/RN (RC-02), ningún valor `null` (RC-03) ni se nombró producto/stack (RC-01).**
4. **F0-01 (ronda F0):** la única referencia de este documento a un archivo pre-renombre (`§5`, fila *Responsables*, `:190`) pasa a citar `09_validation_plan.md`, que es el nombre que existe en disco. **Qué:** solo el nombre del archivo citado; la fila sigue remitiendo a ADR-001 y a V-01 del plan de validación, sin cambiar método, umbrales ni responsables. **Por qué:** el nombre antiguo remitía a un archivo inexistente y rompía la trazabilidad criterio→ensayo (CA-01, CA-03). **Quién aprueba:** solution-leader, con database-specialist (dueño del criterio C1–C4); criterio de cierre = grep de nombres pre-renombre sin resultados sobre este archivo. **No se alteró RF/RNF/RN (RC-02), ningún valor `null` (RC-03) ni se nombró producto/stack (RC-01).**
5. **F0-03 (ronda F0):** trazabilidad de **RN-07** y **RN-08** en las secciones que ya modelan esos flujos — **Qué:** drivers de §4 ampliados con RN-07 y RN-08; dos notas nuevas al final de **§4.1** («Nota DEC-05 — RN-07»: recepción de compras que actualiza inventario solo al confirmarse exigiendo lote, vencimiento y cantidad; «Nota DEC-05 — RN-08»: estados controlados de la transferencia con el lote conservado en el historial de ambas opciones) y fila **CA-01** de §9 actualizada con esas citas. **Por qué:** RN-07 (`reglas_negocio.md:8`) y RN-08 (`:9`) no tenían ninguna cita en `03`–`06`, `08`, `09` ni ADR (11/13 RN → CA-01 incompleto); ambas reglas ya condicionaban §4.1, §5 `k`/C2 y §10 (RF-031/RF-040) sin enlazarse. **Quién aprueba:** database-specialist (trazado en este documento) + solution-leader (cierre de F0-03 en `07_architecture_review.md §7.2/§7.4`); P-13 sigue abierta. **No se alteró RF/RNF/RN (RC-02), ningún valor `null` (RC-03) ni se nombró producto/stack (RC-01).**
