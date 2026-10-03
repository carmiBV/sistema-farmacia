# 06 - Infraestructura, despliegue y operación

> **Agente:** devops-architect (paso 6 del pipeline de `AGENTS.md`).
> **Dueño de:** DEC-08 (capacidad/escalado) y DEC-10 (despliegue/operación); **validador de ambas: cliente**.
> **Estado:** recomendación preliminar, sujeta a architecture-reviewer, solution-leader (ADR) y aprobación del cliente. **No se cierra ninguna decisión aquí.**
> **RC-01:** sin stack, sin producto, sin nube, sin infraestructura real. Todo lo que sigue son **propiedades verificables y comparaciones por propiedades**; la elección concreta es del ADR.
> **RC-03:** ningún valor `null` (RPO/RTO, frecuencia de pruebas, presupuesto, latencias de trazabilidad) se inventa; se declara como supuesto con su pregunta abierta.

---

## 1. Fuentes, alcance y reglas aplicadas

| Fuente | Qué se toma |
|---|---|
| `01_requirements_analysis.md` | Drivers **D-08** (capacidad configurable 25.000 txn/h, pico 3,0, 10x, crecimiento sin rediseño), **D-09** (latencia p95 propuesta en mostrador), **D-10** (reportes fuera del canal transaccional), **D-11** (Linux, contenerización, config por entorno, sin secretos), **D-12** (config externa, RF-100), **D-13** (backup probado, RPO/RTO, logs/métricas/alertas sin PII) |
| `02_configuracion/perfil_carga.yaml` | 25.000 txn/h (a validar), pico ×3,0, 10x, 8 sucursales, 6 cajas/sucursal (48 cajas), ~180 usuarios internos concurrentes, latencias p95 propuestas, RPO/RTO `null`, disponibilidad 99,9 % propuesta |
| `01_requisitos/RNF.md` | RNF-001…011 (capacidad), RNF-040 (backup con pruebas), RNF-041 (RPO/RTO antes de producción), RNF-042 (timeout/reintentos), RNF-043 (modo degradado), RNF-044 (ventanas de mantenimiento), RNF-047 (reloj), RNF-050…053 (logs/métricas/correlación/alertas), RNF-060…063 (Linux, contenerización, config por entorno, mantenimiento activo) |
| `01_requisitos/criterios_aceptacion.md` | **CA-05** (25.000), **CA-06** (10x), **CA-08** (observabilidad y recuperación), **CA-09** (sin K8s/microservicios sin justificación medible), **CA-12** (sin hallazgos bloqueantes) |
| `02_decision_scope.md` | DEC-08 y DEC-10 con su =2 exigido; RC-01, RC-03, RC-10, RC-11, **RC-12**; pendientes P-01, P-04, P-05, P-06, P-10, P-11, P-15 |
| `03_architecture_options.md` | Recomendación preliminar **ALT-1** (una unidad de despliegue con módulos internos) con escalón ALT-2; ALT-3 descartada por CA-09; camino de crecimiento documentado (§5) |
| `04_database_analysis.md` §7 | Insumos directos: **punto de restauración único (A) vs multi-partición (B) vs regeneración de derivado (C)**; **escalado de escritura 10x**; latencias de consulta reversa sin objetivo (P-05); durabilidad de la cola del puesto e idempotencia de conciliación **sin fijar RPO/RTO** |
| `05_security_review.md` | H-07 (cola/caché cifradas en reposo), **H-08** (degradación de la identidad externa + RNF-042), **H-10** (sincronización de reloj → devops-architect), **H-11** (misma política de anonimización en respaldos), **H-12** (alerta de accesos anómalos a PII, definir con devops-architect), CA-08 Observabilidad parcial |

**No se decide aquí:** DEC-04/DEC-05 (database-specialist), DEC-06/DEC-09/DEC-11 (security-reviewer), DEC-07/DEC-12 (architect). Este documento **entra al ADR como insumo de DEC-08 y DEC-10**.

---

## 2. DEC-10 - Despliegue y operación (=2 topologías proporcionales, CA-09)

### 2.1 Topologías comparadas por propiedades

| Propiedad | **T-A: despliegue simple por contenedor** (una unidad de aplicación + almacén de datos, contenedores gestionados de forma directa, sin orquestador) | **T-B: orquestación mayor** (clúster de contenedores con plano de control, balanceo, autoescalado y despliegues graduales gestionados) |
|---|---|---|
| Superficie operativa | 1 unidad de despliegue + 1 almacén; procesos supervisados con health-check | Plano de control + nodos + redes + almacenamiento + políticas + RBAC + control de versiones del propio clúster |
| Escalado | Réplicas idénticas de la unidad de aplicación tras un balanceador; horizontal por medición | Autoescalado por métricas integrado; innecesario por debajo del umbral de nodos que sostiene la carga |
| Alta disponibilidad y ventanas | Reinicio por proceso supervisor + réplicas; ventanas definidas por RNF-044/P-04 | Recolección automática y despliegues sin caída; ventanas del clúster también a gestionar |
| Complejidad de red y seguridad | Frontera única (coherente con DEC-11 = 11-a) | Superficie interna añadida (servicios, políticas, identidad del clúster) que exige operación especializada |
| Coste operativo (P-11 `null`) | Bajo: sin equipo ni plataforma dedicados | Alto sostenido: personal con competencias de clúster, coste de plataforma 24×7 |
| Adecuación a escala real | 8 sucursales, 48 cajas, ~180 usuarios internos concurrentes, 1 unidad de despliegue (ALT-1) | Sobredimensionada para una unidad de despliegue y una tasa derivada de ≈7 txn/s (§3.1) |
| Requisitos duros (RC-10) | Cumple: Linux, contenerizable, config por entorno (RNF-060…062) | También cumple, a mayor coste |

**Recomendación preliminar DEC-10 = T-A**, por propiedades: es la más simple viable para el alcance actual (CA-09, criterio de `03 §5`), mantiene RC-10/RC-11, y deja T-B documentada como escalón, no descartada.

### 2.2 Kubernetes: justificación explícita de NO usarlo hoy (CA-09 / RC-12)

**Decisión: no se usa Kubernetes.** Justificación medible, no por preferencia:

1. **Tasa derivada del perfil supuesto (P-06):** 25.000 txn/h ÷ 3.600 ≈ **7 txn/s sostenidos**; con pico ×3,0 ≈ **21 txn/s**; escenario 10x ≈ **69 txn/s**; 10x con pico ≈ **208 txn/s**. Ninguna de esas magnitudes justifica un plano de control de clúster para **una sola unidad de despliegue** (ALT-1): el escalado necesario (réplicas de la unidad de aplicación) se obtiene sin orquestador.
2. **Una unidad de despliegue, no fronteras múltiples:** ALT-3 (microservicios por frontera) ya está **descartada por CA-09 en `03 §5`** porque el volumen derivado no la justifica; K8s sobre una unidad única introduce la mayor parte de su coste operativo sin obtener sus beneficios (descubrimiento entre servicios, partición de despliegue).
3. **Coste operativo sin contrapartida:** K8s exige operación especializada continua (actualización del clúster, políticas de red, almacenamiento, seguridad del plano de control) mientras el presupuesto está sin definir (**P-11 abierta**) y el objetivo de disponibilidad (99,9 %, **propuesto y no aprobado**, P-04) se cubre con una unidad de despliegue + réplicas + respaldos probados.
4. **CA-09/RC-12 prohíben invertir la carga de la prueba:** quien proponga K8s o microservicios debe aportar **medición** (techo real de escritura, necesidad de múltiples unidades de despliegue, requisito multi-zona). Hoy esa medición no existe (CA-06 aún no ejecutada).

**Condiciones que reabrirían esta evaluación** (se registran como tales; ninguna es una decisión hoy):

| Si... | Entonces... |
|---|---|
| La medición 10x (CA-06) muestra **techo de escritura** que exige varias unidades de despliegue independientes y sostenibles | Se reevalúa orquestación; la medición adjunta justifica o descarta por CA-09 |
| **P-10** reactiva canal web/domicilio con crecimiento de concurrencia ajeno al mostrador | Se reevalúa escalado y topología con nuevo perfil de carga |
| Se exige **multi-zona/multi-sucursal con conmutación automática** y P-11 aprueba presupuesto y operación especializada | T-B (con o sin K8s) vuelve a comparación con números de disponibilidad medidos |
| Ventanas de mantenimiento (P-04) resultan incompatibles con despliegues simples y se aprueba operación continua | Se compara T-B por la propiedad "despliegue sin ventana" |

**Microservicios:** mismo veredicto — fuera hoy; condición de entrada idéntica (medición de frontera, CA-09, `03 §5` ALT-3 descartada).

### 2.3 Contenedores, configuración y secretos (RC-10, RC-11, RNF-060…063)

- **Imagen única por release**, reproducible en Linux (RNF-060/061), construida una vez y promovida entre entornos sin reconstrucción.
- **Configuración externa por entorno** (RNF-062): desarrollo, pruebas y producción separados; parámetros operativos (RF-100: días de alerta de vencimiento, stock mínimo, política de devoluciones) y **capacidad objetivo** (RNF-002) modificables **sin desplegar código**, con cambio auditado (D-12).
- **Secretos fuera de repositorio y fuera de la imagen** (RNF-034, RC-11): inyectados en tiempo de despliegue desde gestión de secretos de la plataforma; rotación definida (control H-08 de `05`).
- **Tecnologías con mantenimiento activo y documentación** (RNF-063): criterio de selección del ADR, no preferencia.

### 2.4 Entornos, mantenimiento y continuidad

- **Ventanas de mantenimiento y base de medición de disponibilidad:** RNF-044; 99,9 % y "horario de atención vs 24×7" están **propuestos y sin aprobar (P-04)**; no se fija aquí.
- **Modo degradado (RNF-043, RC-09):** la operación de contingencia es del puesto de venta; a esta capa le corresponde garantizar: durabilidad de la cola local (cuando P-03 habilite 7-B), idempotencia de la conciliación al reconectar (doble clave local/global), registro de incidentes (RF-047) y alerta de excesos por receta (D-15) — **sin fijar RPO/RTO** (`04 §7` y S-3/P-01).
- **Dependencias externas (RNF-042, control H-08):** timeout y reintentos controlados; degradación explícita de identidad externa para que el mostrador no quede con sesión indefinida.

---

## 3. DEC-08 - Capacidad, escalado y separación de canales (=2, CA-05/CA-06)

### 3.1 Punto de partida (supuesto declarado, no aprobado)

Los valores salen de `perfil_carga.yaml`; **P-06 sigue abierta** (¿las consultas de stock cuentan en las 25.000? `consultas_por_hora_estimadas: null`):

| Magnitud | Valor derivado (supuesto P-06) |
|---|---|
| Sostenido objetivo | 25.000 txn/h ≈ **7 txn/s** |
| Pico (×3,0) | ≈ **21 txn/s** |
| 10x (CA-06) | ≈ **69 txn/s** |
| 10x con pico | ≈ **208 txn/s** |
| Consultas de stock/producto (solo lectura) | `null` — no cuentan como transacción de negocio; se dimensionan **aparte** (RNF-003) |
| Escrituras de auditoría por acceso a PII (variable `Q`) | `null` — cada consulta o modificación de datos de pacientes/recetas genera un registro de auditoría (RF-091, RN-13); es **carga de escritura**, no una consulta exenta; pendiente de dimensionar (P-06) |

### 3.2 Formas de absorber la carga (2 exigidas por `02`)

| Opción | Descripción por propiedades | Límite conocido |
|---|---|---|
| **A) Escalar escritura horizontalmente** | Más réplicas de la unidad de aplicación; el cuello de botella migra al almacén de datos | En ALT-1 la escritura es transaccional única: el techo lo pone el almacén, no la réplica; requiere medición previa |
| **B) Cola de escritura** | Absorbe picos encolando trabajos; el mostrador confirma rápido y la escritura se drena | Encaja con confirmación **síncrona** del mostrador solo para trabajos no interactivos; no aplica a la confirmación de venta/dispensación |
| **C) Combinación (recomendada)** | Réplicas para el canal interactivo + cola para picos de trabajos no interactivos (reportes, alertas, conciliación) | Cada pieza debe medirse por separado |

**Recomendación preliminar DEC-08 = C (combinación paramétrica):**
1. **Canal transaccional interactivo:** escalar la unidad de aplicación horizontalmente (A) hasta el techo medido del almacén; es el escalón natural de ALT-1 sin rediseñar el dominio (RNF-010).
2. **Picos y trabajos diferidos:** cola (B) **solo** para lo que no exige confirmación inmediata — reportes (RF-070/071), alertas (RNF-053), trabajos de conciliación. La **venta y la dispensación se confirman de forma síncrona** dentro de la p95 propuesta (D-09); encolarlas cambiaría el contrato de negocio y no está autorizado por RF.
3. **Condición de cambio:** si la medición 10x muestra que **A no llega** y **B no aplica** al canal interactivo, se vuelve a `02` con el dato medido (caja de `03 §5`: techo de escritura → database-specialist evalúa partición).

### 3.3 Aislamiento de reportes (2 exigidas por `02`; driver D-10)

| Opción | Propiedades |
|---|---|
| **A) Mismo almacén con aislamiento de lectura** (recomendada hoy) | Consultas de reporte sobre lectura dedicada/aislada; sin duplicar datos ni proceso de sincronización; coherencia suficiente para reportes; complejidad baja |
| **B) Almacén de análisis separado** | Aísla al extremo (los reportes no tocan el almacén transaccional) a cambio de proceso de extracción, latencia de frescura y segundo sistema que operar |

**Recomendación preliminar: A hoy**, con **B como escalón documentado** si la medición muestra que A degrada la p95 de dispensación (coincide con el escalón ALT-2 de `03 §5`). Elegir B sin medición violaría CA-09 (segundo almacén sin justificación medible).

---

## 4. Observabilidad (CA-08; RNF-050…053; insumo directo de `05`)

Propiedades exigibles a cualquier topología; **umbrales como parámetro, no fijados aquí**:

| Señal | Qué exige | Fuente / hallazgo | Estado |
|---|---|---|---|
| **Logs estructurados sin PII** | Sin datos sensibles de pacientes/recetas en logs, errores ni reportes; sanitización y enmascaramiento por defecto | RNF-050, R-06, **H-03/H-11 de `05`** | Propiedad definida; verificación en revisión de seguridad |
| **Métricas** | Latencia (p95 propuestas 500/1200/1500 ms, **por aprobar**), errores, disponibilidad, volumen | RNF-051, D-09 | Dimensionables; valores sin aprobar (P-04/P-05) |
| **Correlación** | Correlación de operaciones distribuidas cuando corresponda (colas, conciliación) | RNF-052 | Aplica a cola y conciliación post-corte |
| **Alertas: fallo de dispensación** | Fallo en dispensación/venta → alerta operativa | RNF-053, D-13 | Regla a definir con umbrales paramétricos |
| **Alertas: discrepancias de inventario** | Diferencia entre conciliación e historial → alerta | RNF-053, D-13, RNF-024 | Regla a definir |
| **Alertas: exceso de saldo de receta** | Excesos detectados al reconectar (7-C, D-15) | RF-053, `04 §7` | Solo si P-15 habilita 7-C |
| **Alertas: accesos anómalos a PII** | Frecuencia por usuario/rol, fuera de horario, consultas masivas sobre `Q` | RNF-053, RF-091, **H-12 de `05`** | **Pendiente de definir con devops-architect + security-reviewer**; `Q` sin volumen (P-06) |
| **Alertas: dependencia externa** | Timeout/reintentos agotados y modo degradado de identidad | RNF-042, **H-08 de `05`** | Propiedad definida |
| **Reloj** | Sincronización confiable entre sucursales (integridad temporal de auditoría) | RNF-047, **H-10 de `05`** | **Asignado a esta capa**: requisito de despliegue |
| **Continuidad del mostrador** | Corte y reconexión visibles (cola pendiente, conciliación en curso) | RC-09, RNF-043 | Condicionado a P-03/P-15 |
| **Consulta reversa (CA-10)** | Observabilidad de latencia de trazabilidad **sin inventar objetivo** | RNF-048, **P-05 `null`** | Métrica expuesta; objetivo pendiente |

---

## 5. Backup y recuperación (CA-08; RNF-040/041)

**Ningún RPO/RTO se fija aquí (RNF-041, P-01, RC-03).** Se definen propiedades:

1. **Estrategia de respaldo paramétrica** (RNF-040): respaldos de datos vivos **cifrados en reposo** (RNF-036, H-07/H-11 de `05`), con frecuencia y retención derivadas del RPO cuando P-01 lo fije.
2. **Pruebas de restauración periódicas:** obligatorias; frecuencia en `frecuencia_prueba_restauracion_dias: null` → **pendiente de P-01** (RNF-040). Sin fecha inventada.
3. **Tres estrategias de restauración comparadas** (insumo `04 §7`):
   - **A) Punto de restauración único:** simple, coherente en conjunto; el tiempo de restauración crece con el volumen.
   - **B) Multi-partición:** restaura por partes; paraleliza, pero exige consistencia entre particiones.
   - **C) Regeneración de derivados:** restaura la fuente y regenera derivados; el más rápido para reportes, incompleto para datos vivos sin su fuente.
   - **Recomendación preliminar:** **A como base + C como complemento** para derivados reportables (coherente con DEC-08 §3.3-A: sin almacén de análisis separado hoy no hay segundo respaldo que sincronizar). B solo si el volumen medido lo exige.
4. **Respaldos y anonimización (H-11 de `05`):** al restaurar se aplica la **misma política de anonimización** sobre PII; los movimientos, lotes, montos y el libro de controlados **no** se anonimizan (RNF-039).
5. **Recuperación del mostrador (RC-09):** el respaldo central **no** cubre la cola local del puesto en vuelo; su durabilidad es local y su recuperación es la conciliación al reconectar (RNF-043) — depende de P-03/P-15.
6. **Antes de producción:** RPO/RTO definidos (RNF-041) y prueba de restauración documentada; es **condición de puesta en marcha**, igual que el tratamiento de H-01/H-02 en `05 §8`.

---

## 6. CI/CD (propiedades; RC-01: hoy solo se definen, no se implementa)

| Etapa | Propiedad exigible | Fuente |
|---|---|---|
| Construcción | Artefacto (imagen) **inmutable y reproducible** a partir del repositorio; sin secretos en código ni en la imagen | RC-11, RNF-034, D-11 |
| Calidad | Gates automáticos antes de promover: análisis estático, pruebas, escaneo de dependencias y de secretos | D-11, seguridad transversal (`05`) |
| Entornos | Separación estricta dev/test/prod con configuración por entorno; el mismo artefacto se promueve, no se reconstruye | RNF-062, D-12 |
| Despliegue | Reproducible en Linux por contenedor; con rollback a la versión anterior | RNF-060/061, RC-10 |
| Post-despliegue | Verificación de salud + smoke test de las cuatro operaciones de negocio; alerta si el despliegue degrada métricas | RNF-051/053 |
| Datos | Migraciones versionadas y ejecutadas como paso del despliegue (el detalle del motor es de database-specialist) | D-11 |
| Cadencia y ventanas | Alineada con ventanas de mantenimiento (P-04); despliegues fuera de ventana cuando la disponibilidad lo exija | RNF-044 |

---

## 7. Pendientes que afectan DEC-08 y DEC-10

| Pendiente | Qué altera | Dueño |
|---|---|---|
| **P-01** RPO/RTO y frecuencia de prueba de restauración | §5 completo; condición antes de producción | Cliente |
| **P-04** 99,9 %: 24×7 u horario; ventanas de mantenimiento | §2.4, §6, medición de disponibilidad | Cliente |
| **P-05** latencia p95 de trazabilidad | §4 (objetivo de la métrica CA-10), escalón ALT-2 | Cliente |
| **P-06** numerador de 25.000 y consultas (`Q`) | §3.1 y todo dimensionamiento | Cliente |
| **P-11** presupuesto y operación (monitoreo, respaldos, actualizaciones) | T-A vs T-B (§2.1), profundidad de CI/CD | Cliente |
| **P-15 / P-03** modo degradado y 7-C | §2.4 cola local, alerta de excesos, respaldo del puesto | Cliente + químico |
| **P-10** canal web reactivado | §2.2 condición de reevaluación de topología | Cliente |
| **Q-07 de `05`** identidad externa sin conexión | §2.4 dependencias externas | Cliente + devops |
| **H-12 de `05`** alerta de accesos anómalos a PII | §4 — regla a codiseñar con security-reviewer | devops + security |
| **H-10 de `05`** sincronización de reloj | §4 — requisito de despliegue por sucursal | devops |

---

## 8. Cobertura de criterios de aceptación

| CA | Texto (resumen) | Cobertura aquí |
|---|---|---|
| **CA-09** | Sin Kubernetes/microservicios sin justificación medible | ✓ **§2.2 con justificación explícita de NO usar K8s** (tasa derivada, una unidad de despliegue, coste vs P-11, condiciones de reevaluación); microservicios descartados con la misma regla; §3.3 evita segundo almacén sin medición |
| **CA-02** | ≥2 alternativas comparadas | ✓ §2.1 (T-A vs T-B), §3.2 (A/B/C), §3.3 (A/B), §5 (A/B/C de restauración) — todas por propiedades, sin producto |
| **CA-05** | 25.000 txn/h configurable | ✓ §3.1 + capacidad como parámetro externo (RNF-002, §2.3) |
| **CA-06** | Escenario 10x | ✓ §3.1 con tasas derivadas y §3.2 condición de cambio con medición |
| **CA-08** | Seguridad, privacidad, observabilidad y recuperación | **Parcial aquí:** §4 (observabilidad) y §5 (recuperación, sin RPO/RTO por P-01); seguridad/privacidad completadas en `05`; cierre conjunto en el ADR |
| **CA-01** | Trazabilidad a RF/RNF/RN | ✓ cabeceras y tablas de cada sección (drivers D-08…D-13) |
| **CA-12** | Sin hallazgos bloqueantes | Sin hallazgos nuevos propios de esta capa; **pendientes heredados** P-01/P-04/P-11 y H-10/H-12 se declaran abiertos, no se ocultan |

**RC-01/RC-03 cumplidos:** sin stack, producto ni nube; sin infraestructura creada; todos los `null` permanecen `null` con su pregunta abierta; los valores derivados de §3.1 se marcan como supuesto (P-06).

---

## 9. Condiciones de esta revisión

1. **Ninguna decisión cerrada:** DEC-08 y DEC-10 son **recomendación preliminar** (T-A + combinación C + reportes A + restauración A/C) sujeta a cliente (validador), architecture-reviewer y ADR final.
2. **Condiciones de cambio (reabrir si ocurren):** (i) medición 10x con techo de escritura → §3.2; (ii) P-10 reactiva canal web → §2.2/§3.3; (iii) P-11 + requisito multi-zona/sin ventana → reevaluar T-B/K8s con números; (iv) P-15 habilita 7-C → §2.4/§5.5; (v) P-01 fija RPO/RTO → §5 deja de ser paramétrico.
3. **Condición de producción:** RPO/RTO definidos y prueba de restauración documentada (RNF-040/041); sincronización de reloj (H-10) y alerta de accesos anómalos (H-12) operativas antes de declarar disponibilidad.
4. **Entrada siguiente:** architecture-reviewer (contrarrevisión) y solution-leader (consolidación + ADR).