# 02 — Alcance de la decisión

- **Agente:** solution-leader (alcance de decisiones)
- **Entrada:** `03_resultados/01_requirements_analysis.md` (v2, requirements-analyst) y `00_contexto/registro_cambios.md` (CAM-001, CAM-002, CAM-003)
- **Reglas aplicadas:** AGENTS.md (orden obligatorio, sin stack hasta ADR, sin código, RF/RNF/RN intactos, valores `null` no inventados), `criterios_aceptacion.md` (CA-01…CA-12)
- **Tratamiento de CAM-001/CAM-002/CAM-003 (18 propuestas):** cambios ya aplicados pero **pendientes de aprobación del cliente** → aquí se usan como **supuestos explícitos**, nunca como hechos confirmados.
- **Alcance de este documento:** definir **qué** se decide, **quién** decide (un solo dueño), **qué alternativas** se comparan y **qué falta** para cerrar. **No se decide nada aquí y no se selecciona stack.**

---

## 1. Decisiones que deben tomarse

**Un solo dueño por decisión** (agente responsable de la recomendación). El validador no decide: revisa, veto o aprueba antes del cierre. Las validaciones humanas son **requisito de cierre, no de inicio**: mientras no haya respuesta, la decisión avanza con **supuesto explícito** y queda **abierta en el ADR** (para DEC-02, DEC-04, DEC-05 y DEC-07 rige además la excepción de §1, reglas de gobierno).

| ID | Decisión | Driver (RF/RNF/RN) | Dueño (único) | Validador | Alternativas mínimas (por propiedades) | Se cierra con |
|---|---|---|---|---|---|---|
| DEC-01 | **Patrón arquitectónico general**: una unidad de despliegue con módulos internos vs varias unidades de despliegue por frontera de negocio | D-08, D-10, RNF-010, CA-02 | architect | architecture-reviewer | ≥2, una debe ser la más simple viable | CA-02, CA-09 (sin servicios/K8s sin justificación medible) |
| DEC-02 | **Mecanismo de integridad de inventario bajo concurrencia** (FEFO sin stock negativo, sin doble descuento, 48 cajas) | D-01, RN-02, RN-03, RNF-005, RNF-020 | architect | químico farmacéutico | ≥2: serialización exclusiva por lote vs verificación optimista con versiones vs reserva anticipada de stock | CA-03: prueba de concurrencia descrita y verificable |
| DEC-03 | **Estrategia de idempotencia** para cobro, dispensación, recepción y transferencia | D-02, RN-09, RNF-022 | architect | database-specialist | ≥2: clave de idempotencia persistida vs restricción única a nivel de datos | CA-07: definición operativa por cada una de las 4 operaciones |
| DEC-04 | **Motor de persistencia** del registro transaccional (inventario por lote, libro de controlados, dinero, auditoría) | D-01, D-03, D-04, D-05, RNF-021, RNF-024 | database-specialist | architecture-reviewer | ≥2 por propiedades (integridad, concurrencia, reconstrucción de saldos, retención), **sin sesgo previo** | CA-04 justificada por requisitos; CA-10 evaluada |
| DEC-05 | **Modelo de datos de trazabilidad y consulta reversa** (lote → dispensaciones; dispensación → paciente/receta/lote/responsable) | D-03, D-04, RN-01, RN-12, RNF-045, RNF-048 | database-specialist | architect | ≥2: historial de movimientos como fuente de saldo con índices vs modelo de eventos con proyecciones dedicadas | CA-10; objetivo de latencia (RNF-048, **P-05**) |
| DEC-06 | **Estrategia de seguridad y privacidad**: autenticación/MFA, autorización, cifrado en tránsito y reposo, minimización y enmascaramiento | D-06, RNF-030–RNF-039, RN-13, CA-11 | security-reviewer | cliente + asesoría legal | ≥2 en ejes relevantes: identidad gestionada internamente vs delegada; cifrado a nivel de aplicación vs de plataforma | CA-08, CA-11; **P-02, P-07**; el método de verificación en dispensación (reautenticación o PIN) sigue **sin definir** (CAM-003-a/b) y se cierra como supuesto explícito hasta **P-14** |
| DEC-07 | **Modo degradado del mostrador y conciliación al reconectar** (ver §1.1) | D-07, restricción 9, RNF-043, RNF-047, **RF-047**, **RF-053**, RN-02, RN-04 | architect | cliente + químico farmacéutico | ≥2 por propiedades: estado local en el punto de venta con sincronización posterior vs modo central degradado con datos previamente cacheados vs solo lectura | Simulación de corte sin stock negativo ni doble dispensación de saldo de receta; **no se cierra sin respuesta de P-03 y P-15; avanza con supuesto explícito** |
| DEC-08 | **Estrategia de capacidad, escalado y separación de canales** (25.000 txn/h × pico 3,0; 10x; reportes fuera del canal transaccional) | D-08, D-09, D-10, RNF-001–RNF-011 | devops-architect | cliente | ≥2 formas de absorber carga (escalar escritura / cola de escritura / combinación) y de aislar reportes | CA-05 y CA-06 con perfil de carga; **P-06** |
| DEC-09 | **Estrategia de auditoría inalterable y retención/anonimización** (solo anexar vs derecho de archivo/eliminación) | D-05, RN-10, RNF-023, RNF-039, RNF-046 | security-reviewer | asesoría legal | ≥2: auditoría dentro del mismo almacén transaccional append-only vs almacén de auditoría independiente con protección adicional | CA-08, CA-11; tensión I-11 (supuesto CAM-002-g) + **P-02** |
| DEC-10 | **Estrategia de despliegue y operación**: contenerización, entornos, configuración externa, secretos, backup/RPO/RTO, observabilidad | D-11, D-12, D-13, **RNF-047**, RNF-040–RNF-044, RNF-050–RNF-063, RF-100 | devops-architect | cliente | ≥2 topologías de despliegue proporcionales (CA-09) | CA-08; restricciones 2–6; **P-01, P-04, P-11** |
| DEC-11 | **Frontera de confianza del backend** (validación crítica en servidor, límites de API) | D-14, RNF-025, RNF-035 | **architect (propone el corte de API)** | **security-reviewer (valida)** | ≥2 cortes de superficie API por propiedades (superficie mínima por rol con validación centralizada vs punto único de entrada con políticas por recurso) | CA-08, previa validación de security-reviewer |
| DEC-12 | **Integraciones externas**: medios de pago/dependencias con timeout y reintento | RNF-042, RF-050, I-09 | architect | cliente | ≥2 enfoques por propiedades: integración síncrona con timeout/reintento y modo degradación vs integración asíncrona con cola y conciliación posterior | RF-050 caracterizado; **no se cierra sin respuesta de P-09** (medios de pago; sin respuesta, se cierra como supuesto explícito con P-09 abierta en el ADR), solo aplica si el cliente confirma alcance |

**Reglas de gobierno de estas decisiones**

- **CA-01:** cada decisión se traza explícitamente a RF, RNF y, cuando aplique, RN (columna Driver + tabla de trazabilidad final en la consolidación).
- **Un dueño, un validador:** ninguna decisión se recomienda por mayoría ni por consenso de agentes; el dueño emite, el validador veto/aprueba.
- **DEC-02 y DEC-07 (architect) y DEC-04 y DEC-05 (database-specialist) son bloqueantes de la consolidación en el ADR.** Cada agente entrega su propuesta en su turno; ninguna de estas cuatro puede llegar a solution-leader sin **propuesta técnica completa** (comparación de ≥2 alternativas por propiedades y su CA de verificación, puntos (a) y (b) de la regla de cierre). **Excepción a la regla de cierre general:** en estas cuatro solo la validación humana (punto (c)) puede quedar pendiente, y únicamente como supuesto explícito con su pregunta abierta en el ADR; la falta de propuesta técnica no puede cubrirse con un supuesto.
- Ninguna decisión adopta producto, lenguaje o nube concretos; eso ocurre únicamente en el ADR de `solution-leader` (consolidación).

### 1.1 DEC-07: escenarios a evaluar

El modo degradado debe analizarse en **tres escenarios** (no como un único sí/no), porque cada uno exige reglas distintas y toca RF-047, RF-053 y RN-05:

| Escenario | Descripción | Qué hay que decidir | Pregunta bloqueante |
|---|---|---|---|
| **7-A. No habilitado** | Ante corte, el mostrador se detiene | Tiempo máximo de detención aceptable, comunicación al cliente, qué hace el sistema de respaldo | **P-03** |
| **7-B. Habilitado solo con venta libre** | Dispensación sin receta en línea; bajo receta y controlados bloqueados | Operaciones permitidas, límite por caja, conciliación de inventario al reconectar (RF-047) | **P-03** |
| **7-C. Habilitado también con bajo receta** | Incluye dispensación con receta sin conexión | El **saldo de receta global (RF-053, supuesto CAM-002-a) no es verificable sin conexión** (I-14): RF-047 solo cubre el sobreconsumo de stock de lote, no el de saldo de receta → riesgo de **doble dispensación (R-11)** | **P-15 (I-14)** además de **P-03** |

Ningún escenario puede inventar el valor de `permitir_bajo_receta_sin_conexion` (sigue en `null`, CAM-003-c). Si el cliente no responde, la validación humana de DEC-07 queda como **supuesto explícito** y la pregunta abierta en el ADR. Como DEC-07 es bloqueante (§1), su propuesta técnica debe estar completa para los tres escenarios 7-A, 7-B y 7-C antes de llegar a solution-leader.

---

## 2. Especialistas necesarios

### 2.1 Pipeline obligatorio (AGENTS.md, en orden)

| # | Agente | Rol en este alcance | Entrada | Salida esperada | Estado |
|---|---|---|---|---|---|
| 1 | requirements-analyst | Análisis de requisitos y drivers | `proyecto/` | `01_requirements_analysis.md` (v2) | ✅ Hecho |
| 2 | **solution-leader** | Alcance de decisiones (este documento) | `01_requirements_analysis.md` | `02_decision_scope.md` | ✅ Este |
| 3 | **architect** | Comparar ≥2 alternativas; dueño de **DEC-01, DEC-02, DEC-03, DEC-07, DEC-11, DEC-12** | RF/RNF/RN, este alcance | Propuesta de arquitectura con matriz de decisión | ⬜ Siguiente |
| 4 | **database-specialist** | Persistencia sin sesgo; modelo de trazabilidad; fronteras transaccionales; dueño de **DEC-04 y DEC-05** | Propuesta de architect | Comparación de motores y modelo de datos | ⬜ |
| 5 | **security-reviewer** | Seguridad, privacidad, auditoría y retención; dueño de **DEC-06 y DEC-09**; **valida DEC-11** | RF/RNF de seguridad y este alcance | Hallazgos y requisitos de control | ⬜ |
| 6 | **devops-architect** | Despliegue, escalado, capacidad, DR, observabilidad; dueño de **DEC-08 y DEC-10** | Propuestas previas | Estrategia de despliegue y operación | ⬜ |
| 7 | **architecture-reviewer** | Revisión adversarial: cobertura RF/RNF/RN, contradicciones, complejidad innecesaria | Todo lo anterior | Hallazgos con severidad; sin hallazgos bloqueantes (CA-12) | ⬜ |
| 8 | **solution-leader** | Consolidación final: recomendación, matriz de decisión, ADR, plan de validación | Todo lo anterior | `ADR` y recomendación aprobable | ⬜ |

### 2.2 Validadores humanos (no agentes)

| Quién | Qué valida | Cuándo |
|---|---|---|
| Cliente | Capacidad (25.000 txn/h), disponibilidad, RPO/RTO, modo degradado (P-03, P-15), alcance web, presupuesto, política de devoluciones, medios de pago (P-09), aprobación de CAM (P-14) | **Para cerrar** DEC-07, DEC-08, DEC-10, DEC-12 y el ADR final |
| Químico farmacéutico | Flujo de controlados, cuarentena/liberación, doble autorización, escenarios de modo degradado (P-03, P-15) | **Para cerrar** DEC-02 y DEC-07 |
| Asesoría legal / cliente | Normativa sanitaria y de datos, plazos de retención, tensión inalterabilidad vs anonimización (I-11) | **Para cerrar** DEC-06, DEC-09 y el ADR final |

**Regla de validación:** ninguna respuesta humana es requisito para **iniciar** una decisión; todas son requisito para **cerrarla**. Sin respuesta → supuesto explícito en la matriz de decisión → pregunta **abierta en el ADR** (en DEC-02, DEC-04, DEC-05 y DEC-07 el supuesto cubre solo la validación humana, nunca la propuesta técnica).

### 2.3 No se requieren todavía

Agentes de implementación de código (frontend/backend) y de revisión de código: **fuera de fase** hasta aprobar el ADR (AGENTS.md prohíbe generar código de la aplicación).

---

## 3. Alternativas que deben compararse

CA-02 exige comparar **al menos dos alternativas arquitectónicas**; CA-04 exige justificar sin sesgo. Las alternativas se formulan **por propiedades verificables** (comportamiento, integridad, costo operativo), **nunca por producto o tecnología de moda**; siempre evaluando el escenario 10x (CA-06) y la regla CA-09.

| Eje (decisión) | Alternativas a comparar (por propiedades, mínimo) | Criterios de comparación obligatorios | CA |
|---|---|---|---|
| Arquitectura general (DEC-01) | A) una unidad de despliegue con módulos internos acoplados por contratos / B) varias unidades de despliegue separadas por frontera de negocio | Complejidad operativa, escalado del canal de venta, coste de mantenimiento, adecuación a 8 sucursales y 48 cajas | CA-02, CA-09 |
| Concurrencia de inventario (DEC-02) | A) serialización exclusiva por lote / B) verificación optimista con control de versiones / C) reserva anticipada de stock | Corrección FEFO, imposibilidad de stock negativo, p95 de dispensación, comportamiento en 10x | CA-03, CA-05, CA-06 |
| Idempotencia (DEC-03) | A) clave de idempotencia persistida con registro único / B) restricción única a nivel de datos | Cobertura de las 4 operaciones, costo en latencia, semántica ante reintentos con corte | CA-07 |
| Persistencia (DEC-04) | A) motor con transacciones ACID multi-registro / B) motor distribuido con partición por sucursal / C) híbrido: motor transaccional + almacén de historial de solo lectura | Integridad transaccional, concurrencia, reconstrucción de saldos, retención de años, mantenimiento activo, despliegue en Linux/contenedor | CA-04, CA-10 |
| Trazabilidad (DEC-05) | A) historial de movimientos como fuente de verdad con índices de consulta / B) modelo de eventos con proyecciones dedicadas | Consulta reversa completa, latencia (RNF-048), coste de retención | CA-10 |
| Seguridad/privacidad (DEC-06) | A) identidad gestionada internamente vs B) delegada; cifrado A) a nivel de aplicación vs B) a nivel de plataforma/almacenamiento | MFA en mostrador sin fricción insalvable, método de verificación en dispensación (reautenticación o PIN; sin definir, CAM-003-a/b), minimización, enmascaramiento en reportes | CA-08, CA-11 |
| Modo degradado (DEC-07) | A) estado local en el punto de venta con sincronización posterior / B) modo central degradado con datos previamente cacheados / C) solo lectura — evaluados en los escenarios 7-A, 7-B y 7-C (§1.1) | Qué opera sin conexión, riesgo de conflicto al conciliar, cumplimiento de RN-05/RN-02/RF-053 | CA-03 |
| Capacidad/escalado (DEC-08) | A) escalar el canal de escritura horizontalmente / B) absorber picos con cola de escritura / C) combinación; y para reportes: A) mismo almacén con aislamiento de lectura vs B) almacén de análisis separado | 25.000 txn/h ×3 pico, 10x, reportes sin degradar dispensación | CA-05, CA-06 |
| Auditoría y retención (DEC-09) | A) registro dentro del mismo almacén transaccional append-only / B) almacén de auditoría independiente con protección adicional | Inalterabilidad, coste de retención, coherencia con anonimización (supuesto CAM-002-g) | CA-08, CA-11 |
| Despliegue (DEC-10) | A) despliegue simple por contenedor vs B) orquestación mayor; observabilidad y respaldo como propiedades transversales | Proporcionalidad (CA-09), ventanas de mantenimiento, multi-entorno, RPO/RTO (P-01), RNF-047 | CA-08, CA-09 |
| Frontera de API (DEC-11) | A) superficie mínima por rol con validación centralizada / B) punto único de entrada con políticas por recurso | Cubre RNF-025 y RNF-035; validado por security-reviewer antes de cerrar | CA-08 |
| Integraciones externas (DEC-12) | A) integración síncrona con timeout/reintento y modo degradación / B) integración asíncrona con cola y conciliación posterior — **solo si P-09 confirma alcance** | Comportamiento ante fallo del tercero, degradación del mostrador, sin dependencia bloqueante | CA-09 |

**Regla anti-sesgo (CA-04):** toda comparación se formula a partir de requisitos medibles (integridad, concurrencia, trazabilidad, retención, coste operativo); ninguna tecnología aparece como "por defecto" ni por preferencia.

---

## 4. Restricciones críticas

Bloqueantes que ninguna alternativa puede violar:

| ID | Restricción | Fuente |
|---|---|---|
| RC-01 | **Sin stack definitivo, sin código, sin frameworks instalados, sin migraciones ni infraestructura real** hasta aprobación del ADR | AGENTS.md |
| RC-02 | **No alterar RF/RNF/RN** silenciosamente; todo cambio se registra con qué, por qué y quién aprueba | AGENTS.md |
| RC-03 | **No inventar valores pendientes** (RPO/RTO, retención, modo degradado, latencias nulas, 25.000 txn/h): tratarlos como supuesto y registrar la decisión como abierta | AGENTS.md, §10 de `01_requirements_analysis.md` |
| RC-04 | **Integridad del inventario**: sin stock negativo, sin doble descuento, FEFO, bloqueo de lotes vencidos/en cuarentena/retirados | RN-02, RN-03, RF-043, RNF-020 |
| RC-05 | **Trazabilidad por lote** de recepción a dispensación (transferencias, bajas, recall) con consulta reversa | RN-12, RF-061, RNF-045, CA-10 |
| RC-06 | **Controlados**: libro con saldo permanente, doble autorización, segregación de funciones | RN-05, RN-06, RF-046, RF-055 |
| RC-07 | **Datos sensibles** de pacientes y recetas: acceso por rol, registro de consultas, cifrado, minimización, retención según normativa | RN-13, RNF-036, RNF-038, RNF-039, CA-11 |
| RC-08 | **Auditoría inalterable**: registros confirmados no se eliminan; correcciones por movimientos compensatorios | RN-10, RNF-023, RNF-046 |
| RC-09 | **Continuidad del mostrador** ante corte de conexión con conciliación controlada | restricción 9, RNF-043, RNF-047, RF-047 |
| RC-10 | **Linux + contenerización**; tecnología con mantenimiento activo y documentación; configuración separada por entorno | restricciones 2–6, RNF-060–RNF-063 |
| RC-11 | **Sin secretos en código o repositorio** | restricción 5, RNF-034 |
| RC-12 | **Sin Kubernetes ni microservicios sin justificación medible** | CA-09 |
| RC-13 | **Alcance cerrado**: no incorporar ventas web, domicilio, contabilidad, nómina, magistrales, historia clínica, seguros ni app móvil salvo alta formal del cliente (que obliga a repetir contexto → requisitos → configuración → análisis afectado) | `alcance.md`, AGENTS.md |
| RC-14 | **Sin hallazgos bloqueantes** en la revisión final de arquitectura | CA-12 |

---

## 5. Preguntas pendientes

Numeración **idéntica a §8 de `01_requirements_analysis.md` (v2)**: P-NN ≡ pregunta NN del 01. Requieren humano; no se resuelven dentro de los agentes. **Excepción (rondas AR-02 y ARR-02):** P-16 y P-17 numeran S-8/S-9 de `04 §1/§8` y P-18 numeran la pendiente RF-044 de `04 §8/§10`, que no existen en `01 §8` v2.

| ID | Pregunta | Bloquea / afecta | Para quién | Estado |
|---|---|---|---|---|
| P-01 | RPO, RTO y frecuencia de prueba de restauración | DEC-10, ADR | Cliente | Abierta |
| P-02 | Normativa sanitaria y de protección de datos; plazos de retención | DEC-06, DEC-09 | Cliente + asesoría legal | Abierta |
| P-03 | Modo degradado: ¿habilitado? ¿venta libre / bajo receta? ¿tiempo máximo? ¿conciliación exacta? | **DEC-07 (escenarios 7-A/7-B/7-C)** | Cliente + químico | Abierta |
| P-04 | Disponibilidad 99,9 %: ¿24×7 u horario? ¿ventanas de mantenimiento? | DEC-10 | Cliente | Abierta (valor propuesto, no aprobado) |
| P-05 | Latencia p95 de trazabilidad por lote, paciente y receta (`null`) | DEC-05 | Cliente | Abierta |
| P-06 | ¿Consultas de stock cuentan en las 25.000 txn/h? (supuesto CAM-001-f/g: no) + estimar `consultas_por_hora_estimadas` (`null`) | DEC-08, CA-05 | Cliente | Supuesto pendiente de confirmación |
| P-07 | Receta: ¿solo datos o imagen digital? (saldo global es supuesto CAM-002-a) | DEC-06, DEC-11 | Cliente | Abierta + supuesto |
| P-08 | Política de devoluciones concreta (estados, plazo, condiciones de reingreso) | DEC-10 (parámetros RF-100) y detalle de RF-060 | Cliente | Supuesto (CAM-002-e), valores pendientes |
| P-09 | **Medios de pago** (efectivo/tarjeta/otros "a confirmar", CAM-002-d) y dependencias externas | **DEC-12** | Cliente | Supuesto pendiente de confirmación |
| P-10 | ¿Se reactivan ventas web/entrega a domicilio? | RC-13, DEC-08 | Cliente | Abierta |
| P-11 | Presupuesto y operación (monitoreo, respaldos, actualizaciones) | DEC-10 | Cliente | Abierta |
| P-12 | Cierre/arqueo de caja: ¿en alcance? (I-10; CAM-002 lo excluye) | ¿reapertura de RF? → requirements-analyst | Cliente | Abierta |
| P-13 | Recepción de transferencias: ¿se revalida vencimiento/cuarentena al recibir? | DEC-02, DEC-05 | Químico → architect | Abierta |
| P-14 | Aprobación de **CAM-001, CAM-002 y CAM-003 (18 propuestas)**, incl. definición del método de verificación en dispensación (CAM-003-a/b) | Todas las DEC (condiciona sus supuestos); DEC-06 en particular | Cliente | Abierta, bloqueante de confianza |
| P-15 | **Saldo de receta sin conexión (I-14):** ¿se permite `permitir_bajo_receta_sin_conexion` (hoy `null`)? Si sí, ¿cómo se evita la doble dispensación de la misma receta en sucursales distintas hasta reconectar? RF-047 solo cubre stock de lote | **DEC-07 (escenario 7-C)**, R-11 | Cliente + químico | Abierta |
| P-16 | **Ventana de retención de claves de idempotencia/identidad de negocio** (S-8): ¿cuánto tiempo se conservan las claves para detectar reintentos? Plazo `null` → no se fija (RC-03). | **DEC-03** — sin plazo no se puede cerrar 3-c; el alcance de 3-a también retiene claves | Cliente + database-specialist | Abierta (AR-02) |
| P-17 | **Saldo de receta en la frontera de RNF-021** (S-9): RNF-021 no menciona el saldo global de receta (RF-053, D-15). ¿Se cubre por modelado sin editar RNF (RC-02) o se propone vía requirements-analyst por `registro_cambios`? | **DEC-02, DEC-05**; si se habilita una modificación → reapertura 01→02→03 | Cliente + requirements-analyst → architect | Abierta (AR-02) |
| P-18 | **RF-044 — umbrales de alerta de stock mínimo y días de vencimiento de RF-100** ("valores a definir por el cliente") **+ ventana de evaluación** de esas alertas que no compita con el canal transaccional. Ningún valor se fija aquí (RC-03) | **DEC-08** (ventana), RF-100 (parámetros de alerta), trazado RF-044 en `04 §8/§10` | Cliente (valores) + devops-architect (ventana → DEC-08) | Abierta (ARR-02) — cierre: valores aceptados por cliente y ventana fijada en DEC-08 |

**Resueltas (no bloquean):** corrección de `rto_minutos` duplicado (CAM-001-a, verificado).

---

## 6. Puerta de salida (gate) hacia la siguiente fase

`architect` inicia cuando:

1. Este documento (`02_decision_scope.md`) existe y fue aceptado.
2. Su lista de entrada contiene, con dueño único y CA asociados: **DEC-01, DEC-02, DEC-03, DEC-07, DEC-11 y DEC-12** (DEC-11: architect propone el corte de API y security-reviewer lo valida; DEC-12 no se cierra sin respuesta de P-09; avanza con supuesto explícito).
3. Las preguntas pendientes **P-01…P-18** se manejan como **supuestos explícitos** si el cliente aún no responde: pueden iniciarse y desarrollarse las decisiones, pero solo se cierran como supuesto explícito, con la pregunta abierta en el ADR.
4. Se reconfirma RC-01 y RC-02: sin selección de stack definitivo y sin tocar RF/RNF/RN.

`database-specialist` inicia cuando la propuesta de architect esté disponible y reciba con ella: **DEC-04 y DEC-05** con sus drivers y CA (CA-04 sin sesgo, CA-10).

**Regla de cierre:** toda decisión se cierra solo con (a) comparación de ≥2 alternativas por propiedades, (b) su CA de verificación y (c) la validación humana que le corresponda (§2.2). Si (c) falta, la decisión se cierra como **supuesto explícito** y su pregunta queda **abierta en el ADR**. Faltar (a) o (b) nunca se cubre con un supuesto; en DEC-02, DEC-04, DEC-05 y DEC-07 esto es condición para entrar a solution-leader (§1, reglas de gobierno).

**No se ha programado, instalado ni seleccionado stack en este documento.**

---

## Registro de correcciones

1. **AR-02 (CAM-004-b, hallazgo de architecture-reviewer):** filas **P-16 (S-8)** y **P-17 (S-9)** añadidas a §5 tras P-15, con nota de excepción de numeración; refuerzo en `03_architecture_options.md` §6. **Qué:** las pendencias S-8/S-9 de `04_database_analysis.md` (§1 y §8) no tenían fila en la fuente de verdad de preguntas. **Por qué:** sin fila en §5 no tienen dueño ni entraban en la puerta de salida (§6), por lo que DEC-03 (3-c) y la observación sobre RNF-021 podían cerrarse sin dueño visible. **Quién aprueba:** cliente (P-16: database-specialist; P-17: requirements-analyst → architect; si se decide modificar un RNF, se reabre 01→02→03). **No se alteró RF/RNF/RN (RC-02) ni ningún valor `null` (RC-03).**
2. **ARR-02 — cierre parcial (ronda de architecture-reviewer, `07_architecture_review.md`):** fila **P-18** añadida a §5 tras P-17 (RF-044: umbrales de alerta de stock mínimo y días de vencimiento de RF-100 + ventana de evaluación sin competir con el canal transaccional) y nota de excepción de numeración ampliada; marcas "registro formal pendiente" de `04_database_analysis.md:240,280,285` actualizadas a "registrada en `02 §5`". **Qué:** la pendencia RF-044 detectada en `04 §8/§10` no tenía fila en la fuente de verdad de preguntas. **Por qué:** sin fila en §5 no tenía dueño visible ni entraba en la puerta de salida (§6), y CA-01 exigía el trazado RF-044→decisión. **Quién aprueba:** cliente (valores de umbrales) + devops-architect (ventana → DEC-08). **Regla de cierre:** valores aceptados por cliente y ventana fijada en DEC-08. **No se alteró RF/RNF/RN (RC-02), ningún valor `null` (RC-03) ni se nombró producto/stack (RC-01).**
3. **F0-04 (ronda F0):** el rango de la puerta de salida (§6, punto 3, `:163`) pasa de **P-01…P-15** a **P-01…P-18**. **Qué:** una sola cifra en la condición de entrada de `architect`; el listado completo de preguntas (§5) ya incluía P-16 (S-8), P-17 (S-9) y P-18 (RF-044), añadidas en ARR-02. **Por qué:** con el rango viejo, P-16/P-17/P-18 quedaban fuera de la puerta y podían cerrarse como supuesto sin figurar en la condición de entrada — contradicción entre §5 y §6 (CA-01, regla de cierre de `02 §5`). **Quién aprueba:** solution-leader (dueño de `02`). **No se alteró RF/RNF/RN (RC-02), ningún valor `null` (RC-03) ni se nombró producto/stack (RC-01);** no se añadieron ni eliminaron preguntas de §5, solo se corrigió el rango de la puerta.