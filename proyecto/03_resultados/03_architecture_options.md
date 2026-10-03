# 03 — Opciones arquitectónicas

- **Agente:** architect
- **Skill:** architecture-design (paso 3 del pipeline, AGENTS.md)
- **Entradas leídas:** `03_resultados/02_decision_scope.md` (corregido), `03_resultados/01_requirements_analysis.md` (v2), `00_contexto/registro_cambios.md`, `01_requisitos/criterios_aceptacion.md` (CA-01…CA-12)
- **Supuesto rector:** CAM-001, CAM-002 y CAM-003 (18 propuestas) son **supuestos explícitos pendientes de aprobación (P-14)**, no hechos confirmados. Sostienen esta propuesta mientras el cliente no apruebe.
- **Alcance:** comparación de alternativas por propiedades verificables. **Sin selección de stack, sin código, sin infraestructura real (RC-01)**; no se alteran RF/RNF/RN (RC-02); no se inventan valores pendientes (RC-03); no se amplía el alcance (RC-13). DEC-04 y DEC-05 **no se cierran aquí** (database-specialist).

---

## 1. Resumen ejecutivo y supuestos aplicados

Sistema multi-sucursal (8 sucursales, 48 cajas, ~180 usuarios internos concurrentes) con inventario por lote (FEFO, bloqueo de vencidos/cuarentena/retirados), dispensación trazable, libro de controlados, transferencias, modo degradado ante corte y auditoría inalterable.

Referencia de carga (CA-05/CA-06): **25.000 transacciones de negocio/hora** — numerador = los **5 tipos de negocio** de `01 §5` (no = escrituras; el factor de derivación a escrituras **no está definido**) —, **× pico 3,0**, escenario **10x**. Órdenes de magnitud aritméticos derivados de ese valor **a validar (P-06)**: base ≈ **7 txn/s**, pico ≈ **21 txn/s**, 10x sobre el pico ≈ **208 txn/s**. Las consultas se contabilizan aparte: `consultas_por_hora_estimadas: null` (CAM-001-f/g, P-06).

Se proponen **5 alternativas genuinamente distintas** (rango esperado 4–6, CA-02), diferentes en unidad de despliegue, ubicación del estado, modelo de fuente de verdad y dónde vive la tolerancia a cortes, de las cuales **ALT-1 es la más simple viable**: una unidad de despliegue con módulos internos. **ALT-2 y ALT-4 son extensiones ortogonales de ALT-1, no sustitutos**: ALT-2 (territorio de DEC-08) añade un canal de lectura sobre la misma unidad de escritura; ALT-4 añade estado local en el puesto sobre el mismo centro. Las distribuidas (ALT-3) solo entran con justificación medible (CA-09); hoy esa medición **no existe**.

Supuestos sensibles aplicados (todos abiertos): 25.000 txn/h y no-conteo de consultas (CAM-001-f/g, P-06); latencias p95 propuestas (D-09); disponibilidad propuesta (P-04); **RPO/RTO en `null` (P-01)**; **modo degradado y `permitir_bajo_receta_sin_conexion` en `null` (P-03, P-15)**; medios de pago "a confirmar" (P-09); plazos de retención `null` (P-02); ventana de retención de claves de idempotencia/identidad de negocio sin definir (DEC-03, nueva pendencia); método de verificación en dispensación sin definir (CAM-003-a/b). Donde un valor es `null`, este documento **no lo rellena**: señala qué decisión depende de él.

---

## 2. Alternativas

### ALT-1 — Una unidad de despliegue con módulos internos y registro transaccional con atomicidad multi-registro

**Descripción por propiedades.** Una sola unidad de despliegue; el dominio (inventario/lotes, venta/dispensación, libro de controlados, maestros, reportes) se organiza en módulos internos con contratos explícitos. Escritura y lectura operan sobre **un registro transaccional con atomicidad multi-registro** (consistencia fuerte en todas las operaciones de negocio). Escalado: vertical primero y, como propiedad exigida al registro, clon horizontal de la capa sin estado sobre el mismo origen. Auditoría e historial append-only en el mismo registro, con índices para consulta reversa.

**Comparación (13 dimensiones):**

1. *Complejidad:* **Baja** — una unidad que construir y operar; sin red inter-servicio ni sincronización de estado.
2. *Escalabilidad (≈7 txn/s base, ≈21/s pico — derivado de CA-05 a validar, P-06):* **Media en escritura, alta en lectura** — el orden de magnitud es modesto para un registro transaccional; el cuello de botella real es la **contención de escritura sobre los lotes más disputados** (48 cajas, R-01), no el volumen.
3. *10x (CA-06, ≈208 txn/s sobre el pico):* se rompe primero **la contención por lote** y después la capacidad de escritura del registro; remedio: escalar vertical, clonar la capa sin estado, y solo después escalar/particionar el registro (DEC-04/DEC-08) — sin tocar el modelo de dominio (RNF-010).
4. *Consistencia por operación:* **fuerte en cobro, dispensación, recepción y transferencia** (una transacción cubre movimiento + libro + auditoría). Eventual solo en lecturas de reportes (D-10), nunca en RN-02/RN-03.
5. *Costo operativo:* **Bajo** — un sistema que respaldar, observar y actualizar.
6. *Mantenibilidad:* **Media-alta** si los módulos mantienen contratos; riesgo de acoplamiento interno si no se impone frontera de módulo.
7. *Integridad bajo concurrencia (CA-03):* (a) sin stock negativo: toda salida de inventario ocurre en una transacción que **revalida cantidad y estado del lote en el momento de escritura** (bloqueo o control de versión por lote) → la validación nunca lee estado desactualizado; (b) lote no apto: la misma transacción **rechaza FEFO vencido, en cuarentena o retirado** (RF-041/RF-043) porque la verificación y el descuento son indivisibles.
8. *Idempotencia (CA-07) — definición operativa (compartida por las alternativas, mecanismo aquí):* **cobro** = la misma clave de operación de venta reenviada devuelve el resultado original y no genera un segundo cobro; **dispensación** = el descuento de lote y el asiento de libro ocurren una sola vez al confirmar (RF-051/RF-042, RF-055), el reintento jamás descuenta dos veces; **recepción** = la misma remisión reenviada produce un único ingreso y una única entrada en cuarentena; **transferencia** = el mismo par despacho/recepción produce un único movimiento y un único doble asiento (RF-045, RF-055). Mecanismo: **clave de idempotencia persistida con restricción única en la misma transacción** (DEC-03), de modo que corte o reintento no pueden duplicar el efecto.
9. *Modo degradado:* 7-A trivial (la unidad se detiene); 7-B factible con **componente mínimo** — caché de catálogos y cola de escritura en la sucursal/caja (Opción C de DEC-07); **no es "dentro de la misma unidad"**: es una pieza nueva pequeña, muy por debajo del estado completo de ALT-4; **7-C solo si P-15 lo autoriza**: sin conexión el saldo de receta global no es verificable (I-14) → requiere aceptar el riesgo R-11 o el componente completo de ALT-4.
10. *Trazabilidad (CA-10):* (a) lote → recepción, stock por sucursal y dispensaciones: consulta directa sobre el histórico transaccional; (b) dispensación → paciente, receta, lote, responsable: misma fuente. Auditoría append-only en el mismo registro (RC-08); correcciones por movimientos compensatorios.
11. *Seguridad/observabilidad/recuperación (CA-08):* superficie de red mínima; respaldo y restauración de un sistema (RPO/RTO **supuesto abierto, P-01**); logs/métricas de una unidad, correlación simple. Alertas exigidas: **fallo de dispensación, discrepancias de inventario y exceso de saldo de receta** (RNF-053, D-13).
12. *Datos sensibles (CA-11):* pacientes/recetas viven con el histórico transaccional; la anonimización por datos personales (CAM-002-g) debe poder aplicarse sin tocar movimientos/libro → **propiedad exigida al registro de datos** (señalada a database-specialist y security-reviewer; plazos `null`, P-02, DEC-06/DEC-09).
13. *Proporcionalidad Linux/contenedores (CA-09):* **óptima** — una unidad, proporcional; sin orquestación de clúster.

**DEC resumidas:** DEC-02 → optimista por lote con control de versión (o serialización exclusiva en FEFO, ver §4), aplicada también al saldo global de receta; DEC-03 → clave única persistida (3-a); DEC-07 → Opción C (componente mínimo) para 7-A/7-B, 7-C condicionada a P-15 con criterio D-15; DEC-11 → superficie única por rol con validación centralizada (módulos por dominio facilitan el corte por rol); DEC-12 → integración externa síncrona con timeout/reintento o cola, según P-09.

**Ventajas / desventajas / riesgos.** ✅ Mínima complejidad operativa; consistencia fuerte de origen; CA-09 cumplido por defecto; trazabilidad en una fuente. ❌ Escalabilidad de escritura limitada por un solo registro transaccional; reportes compiten con el canal transaccional si no se aíslan (D-10). ⚠️ Riesgos: R-01 (contención FEFO), R-03 (dimensionar sin 25.000 validados), R-04 (crecimiento del histórico).

**Trazabilidad (CA-01):** RN-01…RN-03, RN-05, RN-09…RN-13; RF-041…RF-047, RF-051, RF-055, RF-061, RF-090/091; RNF-005, 020…025, 045…048, 060…063; D-01…D-15; CA-01…CA-03, CA-05…CA-10.

---

### ALT-2 — Una unidad de despliegue de escritura + canal de lectura desacoplado

**Extensión ortogonal de ALT-1** (territorio de DEC-08): no sustituye a ALT-1, le añade un canal de lectura.

**Descripción por propiedades.** Igual que ALT-1 en escritura (unidad única, módulos internos, registro transaccional con consistencia fuerte), pero **reportes, analítica y consulta pesada de trazabilidad** se sirven desde un almacén de solo lectura alimentado por proyecciones (replicación o publicación de movimientos). La fuente transaccional sigue siendo la única verdad para RN-02/RN-03.

**Comparación:**

1. *Complejidad:* **Media** — una unidad a operar más un canal de lectura con su alimentación y su retardo.
2. *Escalabilidad:* **Alta en lectura, igual que ALT-1 en escritura (≈21 txn/s pico)** — la separación se justifica por **D-10** (los reportes RF-070/071 dejan de consumir capacidad del canal de venta), **no por volumen**; la escritura sigue siendo el techo.
3. *10x (≈208 txn/s):* se rompe primero **la escritura** (igual que ALT-1); la lectura ya no compite. El retardo de la proyección **no debe** usarse para decisiones de venta.
4. *Consistencia:* **fuerte en las 4 operaciones de negocio** (idéntico a ALT-1); eventual **solo** en lecturas de reportes y tableros — aceptable (D-10); **inaceptable** para decidir venta, saldo de receta o libro (RN-02, RN-03, RN-05): esas consultas siempre van a la fuente transaccional.
5. *Costo operativo:* **Medio** — dos almacenes que respaldar/observar; el de solo lectura es descartable y reconstruible.
6. *Mantenibilidad:* **Media** — más piezas, pero la frontera lectura/escritura es explícita y protegida.
7. *Integridad (CA-03):* idéntica a ALT-1 (la escritura no cambia); precaución: **nunca validar stock ni lote desde la proyección**.
8. *Idempotencia (CA-07):* misma definición operativa que ALT-1; la proyección debe ser **reprocesable sin duplicar efecto** (el reintento entra una sola vez en la fuente).
9. *Modo degradado:* igual que ALT-1 — 7-A detención; 7-B con **componente mínimo (Opción C)** más la proyección local, útil para consulta sin conexión parcial (catálogos, últimas dispensaciones); **7-C condicionada a P-15** (mismo impedimento I-14).
10. *Trazabilidad (CA-10):* (a) y (b) se responden **en la fuente** (índices transaccionales) o en la proyección si su retardo es aceptable (**P-05, `null`**); auditoría append-only en la fuente (RC-08).
11. *CA-08:* superficie de red algo mayor (un canal de lectura); recuperación: restaurar fuente y reconstruir la proyección (RPO/RTO supuesto, P-01). Alertas exigidas: **fallo de dispensación, discrepancias de inventario y exceso de saldo de receta** (RNF-053, D-13).
12. *CA-11:* los datos de pacientes/recetas pueden minimizarse en la proyección (reportes sin PII, RNF-038/050); la fuente conserva el histórico; plazos `null` (P-02), DEC-06/DEC-09.
13. *CA-09:* **buena** — dos piezas proporcionales, sin clúster.

**DEC resumidas:** DEC-02/03 iguales a ALT-1; DEC-07 igual (Opción C para 7-A/7-B, 7-C condicionada a P-15); DEC-11 — el canal de lectura expone otra superficie que el corte por rol debe cubrir; DEC-12 según P-09, natural en la unidad de escritura. DEC-08 (capacidad/escalado) es el territorio propio de esta alternativa.

**Ventajas / desventajas / riesgos.** ✅ Resuelve D-10 de raíz; mantiene consistencia fuerte donde importa; buen camino de crecimiento sin rediseñar dominio (RNF-010). ❌ Costo y operación de dos almacenes; tentación de leer la proyección para decisiones. ⚠️ Riesgos: retardo de proyección usado por error (mitigación: frontera explícita de lecturas permitidas), R-04, R-05.

**Trazabilidad (CA-01):** lo mismo que ALT-1 más RNF-051 (métricas de latencia), RNF-050…053; D-08, D-10, D-13.

---

### ALT-3 — Varias unidades de despliegue separadas por frontera de negocio

**Descripción por propiedades.** Mínimo dos unidades: **canal de mostrador** (venta, dispensación, inventario, libro) y **back-office/analítica** (maestros, reportes, administración), comunicadas por contrato de API interna y, para lecturas, por publicación de movimientos. Escalado independiente del canal de venta. **Solo entra si la medición lo justifica (CA-09/RC-12):** la justificación medible sería que el 10x o la p95 de dispensación (D-09) no se alcancen en ALT-1/ALT-2 — **hoy no hay medición que lo sustente; el volumen derivado (≈21 txn/s pico) tampoco lo justifica**.

**Comparación:**

1. *Complejidad:* **Alta** — dos unidades, contratos versionados, despliegues coordinados, diagnóstico distribuido.
2. *Escalabilidad:* **Alta en el canal de venta** — escala el mostrador sin tocar maestros/reportes; pero la escritura de inventario sigue concentrada en la unidad transaccional: el techo real apenas se mueve salvo que esa unidad se trocee (y trocear inventario/libro por sucursal rompe la consulta reversa global).
3. *10x (≈208 txn/s):* se rompe primero **la frontera entre unidades (red, contratos, errores en cascada)** y después la capacidad de la unidad transaccional; además exige rehacer la consulta reversa de CA-10 agregando fuentes.
4. *Consistencia:* fuerte **dentro** de la unidad transaccional; **entre** unidades es eventual o coordinada (dos fases entre servicios = costo y fallo parcial) → la transferencia (RF-045) y el libro (RF-055) quedan en la misma unidad obligatoriamente; separarlos introduce puntos donde la consistencia es eventual **inaceptable** para RN-05/RN-02 si se elige mal la frontera.
5. *Costo operativo:* **Alto** — N despliegues, N pipelines, N conjuntos de secretos y observabilidad.
6. *Mantenibilidad:* **Baja (para este tamaño)** — desacopla equipos, pero añade contract testing y versionado de API; para 8 sucursales y ~180 usuarios es complejidad **desproporcionada al tamaño** (justificación medible ausente).
7. *Integridad (CA-03):* resuelta **dentro** de la unidad de inventario (mecanismos de ALT-1); el riesgo es la frontera: una lectura de stock servida por otra unidad con retardo podría aprobar una venta sobre estado viejo → **prohibido por diseño** (RNF-025: validación en servidor, en la unidad dueña del lote).
8. *Idempotencia (CA-07):* misma definición operativa; el mecanismo se vuelve **clave única por unidad + compensación** en las interacciones entre unidades (más fallos parciales que ALT-1).
9. *Modo degradado:* 7-A/7-B dependen de qué unidad esté caída (la de mostrador puede seguir con el **componente mínimo, Opción C**); **7-C complica**: el saldo de receta global vive en la unidad transaccional y sin conexión no es verificable (I-14) — mismo impedimento que ALT-1, ahora con más red en el camino.
10. *Trazabilidad (CA-10):* (a) y (b) exigen **agregar** historiales de unidades → riesgo directo con RNF-048 (latencia `null`) y con la auditoría única (RC-08): la auditoría debe quedar en una sola fuente o en una fuente inmutable compartida.
11. *CA-08:* más superficie atacable, más secretos, más correlación entre unidades; recuperación por pieza (RPO/RTO supuesto, P-01, ahora múltiple). Alertas exigidas: **fallo de dispensación, discrepancias de inventario y exceso de saldo de receta** (RNF-053, D-13).
12. *CA-11:* PII repartida entre unidades aumenta el esfuerzo de minimización y anonimización (CAM-002-g) — más puntos a proteger (DEC-06/DEC-09).
13. *CA-09:* **requiere justificación medible que hoy no existe** → mientras no exista, esta alternativa queda **fuera por regla** (CA-09/RC-12).

**DEC resumidas:** DEC-02/03 se aplican dentro de la unidad dueña del lote; DEC-07 se complica (más nodos que poner en modo degradado); DEC-11 — la superficie pública más la interna; DEC-12 — cola entre unidades si P-09 confirma integraciones.

**Ventajas / desventajas / riesgos.** ✅ Escalado independiente del canal de venta; fallo aislado por unidad. ❌ Complejidad y costo sin medida que los justifique; consulta reversa más cara. ⚠️ Riesgos: fronteras mal puestas rompen RN-02/RN-05; RC-12 si se adopta sin medición.

**Trazabilidad (CA-01):** mismos RF/RNF/RN de ALT-1 más los de integración (RNF-042, RNF-035); drivers D-08, D-10, D-11.

---

### ALT-4 — Unidad central + estado de mostrador en el puesto de venta (sincronización posterior)

**Extensión ortogonal de ALT-1** (no la sustituye): el centro es el de ALT-1; la novedad es el **estado en el puesto**.

**Descripción por propiedades.** Como ALT-1 en el centro, pero el **punto de venta mantiene estado local** (catálogos, stock proyectado, cola de operaciones) y sincroniza con el centro cuando hay conexión: la tolerancia a cortes es una **propiedad estructural**, no un modo encendido. Requiere conciliación al reconectar con detección de conflictos (RF-047) y resolución de duplicados (idempotencia con doble identidad: local y global).

**Comparación:**

1. *Complejidad:* **Alta** — 48 estados locales que sincronizar, conflictos por resolver, versiones de esquema locales.
2. *Escalabilidad:* **Buena en el centro** (igual que ALT-1) y el centro no absorbe el tráfico de consulta local; el costo se paga en operación del puesto.
3. *10x (≈208 txn/s):* se rompe primero **la cola de sincronización y la resolución de conflictos** (dos cajas modificando el mismo lote sin verse); el centro escala igual que ALT-1.
4. *Consistencia:* **fuerte en el centro**; en el puesto es **optimista tolerante a desconexión** — para cobro/venta libre es aceptable con reconciliación; para **dispensación con receta y libro de controlados es inaceptable sin política explícita**: RN-04/RN-05 exigen verificación y saldo en línea, y el saldo de receta global no existe localmente (I-14) → **7-C no se sostiene con solo sincronización posterior** (R-11).
5. *Costo operativo:* **Medio-alto** — 48 agentes locales a actualizar, monitorizar y blindar.
6. *Mantenibilidad:* **Baja** — la lógica de negocio vive en dos lugares (centro y puesto) con la tentación de divergir; exige contratos de sincronización versionados.
7. *Integridad (CA-03):* (a) sin stock negativo: la reserva local evita vender de más **dentro** de un puesto, pero **entre sucursales** el stock proyectado local es una estimación → el control definitivo está en el centro al sincronizar: toda operación revalida contra el estado del centro y, si no hay stock, se convierte en **incidente con ajuste autorizado (RF-047)**, nunca en stock negativo silencioso; (b) lote no apto: el puesto bloquea FEFO/cuarentena con su copia y **el centro revalida al confirmar** — doble red.
8. *Idempotencia (CA-07):* definición operativa idéntica, pero el mecanismo es **doble identidad** (clave local al confirmar + clave global al sincronizar) con deduplicación en el centro; el reintento en los dos lados es el caso a probar (CA-07).
9. *Modo degradado:* **implementa la Opción L de DEC-07**: 7-A detención del centro con el puesto en C; 7-B es natural; 7-C es técnicamente abordable con la cola local, pero **sigue bloqueada por P-15** y exige el criterio D-15: la cola permite dispensar sin conexión, pero no verifica el saldo global hasta reconectar → aceptar R-11 o prohibir la operación (P-03/P-15 deciden).
10. *Trazabilidad (CA-10):* (a) y (b) completas **solo cuando la cola está sincronizada**; en pleno corte, la consulta reversa del centro no ve las dispensaciones locales (retardo = duración del corte) → impacto directo en RNF-048 y en recall durante un corte (RN-12). La auditoría debe ser inmutable también en el puesto (RC-08).
11. *CA-08:* PII y operaciones pendientes en 48 equipos (cifrado en reposo del puesto, RNF-036); recuperación con dos frentes; RPO/RTO **supuesto (P-01)** ahora incluye la cola local no sincronizada. Alertas exigidas: **fallo de dispensación, discrepancias de inventario y exceso de saldo de receta** (RNF-053, D-13).
12. *CA-11:* datos de pacientes/recetas **residen temporalmente en el puesto** — minimización obligatoria y borrado al sincronizar; plazos `null` (P-02); DEC-06/DEC-09.
13. *CA-09:* proporcional **solo si P-03 lo exige** — el componente local es la justificación medible del estado distribuido; sin requerimiento de corte aceptado, es complejidad injustificada.

**DEC resumidas:** DEC-07 → esta alternativa implementa la **Opción L** (estado local en el punto de venta); DEC-02 — concurrencia local optimista + revalidación central; DEC-03 — doble clave de idempotencia; DEC-11 — el puesto no debe exponer API propia al exterior (superficie mínima por rol); DEC-12 — la cola de sincronización puede llevar integraciones externas (P-09).

**Ventajas / desventajas / riesgos.** ✅ Única vía completa para 7-B/7-C (Opción L); continuidad de mostrador sin depender del centro (restricción 9, RC-09). ❌ Complejidad y costo de operar 48 estados; trazabilidad con retardo durante cortes. ⚠️ Riesgos: **R-11 (doble dispensación de receta sin conexión)**, divergencia de lógica, PII en el puesto.

**Trazabilidad (CA-01):** restricción 9, RNF-043, RNF-047 (reloj), RF-047, RF-053, RN-02, RN-04, RN-12; D-07, D-15; RC-09.

---

### ALT-5 — Historial de sucesos como fuente de verdad (saldos reconstruibles)

**Descripción por propiedades.** Todo efecto de negocio se registra como **suceso inmutable** (recepción, transferencia, dispensación, ajuste, asiento de libro); los saldos de stock, de libro y de receta son **proyecciones reconstruibles** desde el historial. Una unidad de despliegue (o varias) sobre un almacén de sucesos con proyecciones.

**Comparación:**

1. *Complejidad:* **Alta** — nuevo paradigma: eventos versionados, proyecciones, reordenación, reproceso.
2. *Escalabilidad:* **Buena en escritura apéndice** (escribir sucesos es secuencial simple) y **la reconstrucción/proyección es el cuello de botella** bajo pico 3,0 (≈21 txn/s); lecturas de saldo dependen de la proyección (debe mantenerse al día o leerse combinando sucesos).
3. *10x (≈208 txn/s):* se rompe primero **la proyección de stock/libro** (retardo de saldo) y el volumen/retención del historial; remedio: partición de sucesos y proyecciones escaladas — más piezas, mismo dominio.
4. *Consistencia:* **fuerte para quien lee la proyección actualizada en la misma transacción de suceso**; **eventual para cualquier lector de proyección** → inaceptable si una caja lee un saldo proyectado atrasado (RN-02: podría dispensar de más). Obliga a que la decisión de venta lea el saldo con garantía de frescura.
5. *Costo operativo:* **Medio-alto** — proyecciones que monitorear y reprocesar; almacenamiento del log con retención de años (R-04).
6. *Mantenibilidad:* **Baja en equipo sin experiencia** — el modelo es elegante para trazabilidad, pero cualquier cambio de regla exige reproceso de proyecciones (RN-10 ayuda: nada se borra; RNF-021/024 encajan).
7. *Integridad (CA-03):* (a) sin stock negativo: la proyección de stock se actualiza **en la misma confirmación** del suceso con control de versión/serialización por lote; (b) lote no apto: la validación ocurre en el servidor contra la proyección fresca en el momento de emitir el suceso; FEFO y bloqueos son predicados del servidor.
8. *Idempotencia (CA-07):* definición operativa idéntica; mecanismo natural: **el suceso lleva identidad única y la proyección lo aplica una sola vez** (deduplicación) — la más elegante de las cinco, pero el cobro/transferencia aún necesitan clave única en el borde.
9. *Modo degradado:* 7-A detención; 7-B con el **componente mínimo (Opción C)**; 7-C: el suceso local se emite sin conexión y **la proyección de saldo de receta no puede validar** (I-14) → mismo impedimento que todas (criterio D-15); además, durante el corte no hay historial central.
10. *Trazabilidad (CA-10):* **la más fuerte de las cinco**: (a) lote y (b) dispensación responden por sucesos indexados con una consulta; la auditoría inalterable es el propio modelo (RC-08).
11. *CA-08:* una fuente inmutable simplifica auditoría de accesos; recuperación = reconstruir proyecciones (RPO/RTO supuesto, P-01); observabilidad: métricas de lag de proyección. Alertas exigidas: **fallo de dispensación, discrepancias de inventario y exceso de saldo de receta** (RNF-053, D-13).
12. *CA-11:* PII en el log de sucesos — minimización por diseño (sucesos de negocio sin PII + referencia a la ficha del paciente, CAM-002-g); plazos `null` (P-02).
13. *CA-09:* proporcional si es una unidad; el riesgo CA-09 es adoptar **arquitectura de eventos distribuida** sin medición — fuera de alcance.

**DEC resumidas:** DEC-02 — control de versión por lote en la proyección; DEC-03 — identidad de suceso + clave de borde (y opción 3-c con su restricción); DEC-07 — Opción C para 7-A/7-B, 7-C condicionada a P-15; DEC-11 — superficie por rol sobre el mismo API; DEC-12 — integración externa emite/recibe sucesos (P-09).

**Ventajas / desventajas / riesgos. ✅** Trazabilidad y auditoría nativas (CA-10/RC-08); saldos reconstruibles (RNF-021/024). ❌ Complejidad alta; frescura de proyección como riesgo crítico; equipo debe conocer el paradigma. ⚠️ Riesgos: proyección atrasada leída en venta (RN-02), reproceso costoso, R-04.

**Trazabilidad (CA-01):** RN-01, RN-09…RN-13; RF-047, RF-053, RF-061, RF-090/091; RNF-021, RNF-022, RNF-024, RNF-045, RNF-046; D-02, D-03, D-05.

---

## 3. Matriz comparativa

Escala cualitativa normalizada a **Baja / Media / Alta** para las filas 1, 2, 5, 6 y 13, con justificación breve; el resto de filas usa estados descriptivos o ✅/⚠️/❌. Sin cifras inventadas; 25.000 txn/h es referencia **a validar (P-06)** (≈7 txn/s base, ≈21/s pico, ≈208/s en 10x).

| # | Dimensión | ALT-1 | ALT-2 | ALT-3 | ALT-4 | ALT-5 |
|---|---|---|---|---|---|---|
| 1 | Complejidad constr./operación | **Baja** — 1 unidad, 1 registro transaccional | **Media** — + canal de lectura | **Alta** — N unidades, contratos | **Alta** — 48 estados locales | **Alta** — paradigma de sucesos |
| 2 | Escalabilidad (≈21 txn/s pico, 8 suc., 48 cajas) | **Media** — techo en contención por lote, no en volumen | **Media** — techo igual en escritura; lectura desacoplada (D-10) | **Alta** — del mostrador; techo real igual | **Media** — buena en centro; no carga el centro en consulta | **Media** — buena en apéndice; techo en proyecciones |
| 3 | 10x (CA-06, ≈208 txn/s): qué rompe primero | Contención por lote y después escritura del registro | Escritura (ya separó lectura) | Frontera/red entre unidades | Cola de sincronización y conflictos | Proyección de saldo y volumen del log |
| 4 | Consistencia de cobro/dispensación/recepción/transferencia | Fuerte en las 4 | Fuerte en las 4 (eventual solo en lecturas de reporte) | Fuerte dentro de la unidad dueña; eventual entre unidades (riesgo RN-02/RN-05) | Fuerte en centro; optimista en el puesto | Fuerte si la lectura garantiza frescura de proyección |
| 5 | Costo operativo | **Baja** | Media | **Alta** | Media | Media |
| 6 | Mantenibilidad | **Media** — según contratos de módulo | Media | **Baja (para este tamaño)** | **Baja** (lógica en 2 lugares) | **Baja** (sin experiencia en el paradigma) |
| 7 | Integridad concurrencia (CA-03) | Revalidación indivisible de cantidad+lote en escritura | Ídem ALT-1 (nunca leer la proyección para decidir) | Ídem dentro de la unidad dueña; frontera no decide stock | Reserva local + revalidación central + RF-047 | Proyección con control de versión en la misma confirmación |
| 8 | Idempotencia (CA-07), 4 operaciones | Clave única en la misma transacción | Ídem + proyección reprocesable sin duplicar | Clave por unidad + compensación entre unidades | Doble identidad (local/global) + deduplicación al sincronizar | Identidad de suceso + deduplicación de proyección + clave de borde |
| 9 | Modo degradado 7-A / 7-B / 7-C | ✅ / ✅ con componente mínimo / ❌ sujeta a P-15 + D-15 | ✅ / ✅ con componente mínimo (+ proyección local) / ❌ ídem | ✅ / ⚠️ depende de qué unidad cae / ❌ ídem | ✅ / ✅ natural (Opción L) / ⚠️ vía técnica, sujeta a P-15 + D-15 | ✅ / ✅ con componente mínimo / ❌ sujeta a P-15 + D-15 |
| 10 | Trazabilidad (CA-10) + RC-08 | Fuente transaccional única con auditoría append-only | Ídem fuente; proyección si P-05 lo permite | Agrega fuentes → riesgo RNF-048 | Completa al sincronizar; **retardo durante corte** | **La más fuerte** — sucesos inmutables |
| 11 | Seguridad/observabilidad/recuperación (CA-08) | Superficie mínima; 1 respaldo (P-01); alertas RNF-053 | 2 superficies; fuente + reconstrucción; alertas RNF-053 | Más superficie/secretos/correlación | PII y cola en 48 equipos | Auditoría nativa; reconstruir proyecciones (P-01) |
| 12 | Datos sensibles/retención (CA-11, P-02) | Histórico con anonimización diferenciada | Minimización natural en proyecciones | PII repartida → más esfuerzo | PII temporal en el puesto | PII por diseño en sucesos |
| 13 | Proporcionalidad Linux/contenedor (CA-09) | **Alta** — una unidad, óptima | **Media** — dos piezas proporcionales | **Baja** — requiere medición que no existe | **Baja hoy** — solo si P-03 lo exige | **Media** — si es 1 unidad |

---

## 4. Tratamiento de DEC-01, DEC-02, DEC-03, DEC-07, DEC-11 y DEC-12 por alternativa

### DEC-01 — Arquitectura general (dueño: architect)
**Drivers:** D-08, D-10, RNF-010, CA-02.
Cada alternativa **es** una candidata; se contrasta con la matriz de §3 (criterio: adecuación a 8 sucursales/48 cajas con menor complejidad justificada — CA-02, CA-09). Ver §5.

### DEC-02 — Concurrencia de inventario (dueño: architect; **bloqueante**)
**Drivers:** D-01, D-15, RN-02, RN-03, RNF-005, RNF-020.
Comparación de ≥2 alternativas por propiedades con su CA de verificación (CA-03/CA-05):

| Opción | Propiedad | Verificación (CA) | Dónde encaja |
|---|---|---|---|
| **2-a Serialización exclusiva por lote** | Solo una escritura activa por lote; FEFO y bloqueo revalidados bajo el candado | Prueba de 48 cajas agotando un lote: nunca stock negativo ni salida de lote bloqueado (CA-03); p95 medida (CA-05) | ALT-1/ALT-2/ALT-3 (centro); ALT-5 (bloqueo en proyección) |
| **2-b Verificación optimista con control de versión** | Rechazo y reintento si el lote cambió; sin candado largo | Igual CA-03; menos contención, más reintentos bajo pico 3,0 | ALT-1/ALT-2/ALT-3; ALT-4 (local) + revalidación central |
| **2-c Reserva anticipada de stock** | Descuento al iniciar y liberación si no se confirma | CA-03 + CA-07 (liberación idempotente); arriesga stock bloqueado si no se libera | Complemento de 2-a/2-b en el puesto de ALT-4 |

**Segundo recurso disputado (añadido por esta fase):** el **saldo global de receta (RF-053, D-15)** también es concurrente — varias cajas/sucursales dispensando contra la misma receta — y exige su **propia prueba**: dispensaciones parciales concurrentes de una misma receta **nunca superan lo prescrito** (criterio D-15). El mismo mecanismo (2-b con control de versión, o 2-a) se aplica a ese saldo, y la frontera transaccional debe incluirlo (§7.1).

**Observación sobre un requisito (sin modificarlo, RC-02):** **RNF-021** enumera las fronteras de la transacción de negocio (dinero, inventario, libro) y **no menciona el saldo de receta** pese a RF-053/D-15. Se registra como observación para **database-specialist** (modelado) y **architecture-reviewer** (cobertura); el texto de RNF-021 queda intacto.

**Recomendación de DEC-02 (preliminar):** 2-b con control de versión por lote y rechazo explícito, **más** 2-a como alternativa de cierre para el lote más disputado (R-01), y 2-b sobre el saldo de receta. **CA-03 es la prueba obligatoria en cualquiera de las tres**; P-13 (revalidación al recibir transferencia) puede añadir un paso de 2-b en recepción — pregunta abierta.

### DEC-02x — Doble autorización de ajustes y bajas de controlados (RF-046, RN-05, RN-06, RC-06, RNF-031)

**Mecanismo declarado (propiedad verificable, sin producto).** Todo ajuste o baja de un medicamento
controlado se ejecuta como una **única transacción con dos actores distintos**:

1. **Proponente** — usuario que registra el movimiento, la cantidad y el motivo.
2. **Autorizante** — identidad con rol habilitado (químico farmacéutico), **distinta del proponente**.

El saldo del libro de control y del inventario solo cambia cuando ambas identidades quedan confirmadas
en la misma unidad atómica. Sin segundo actor, la propuesta permanece en estado "pendiente" **sin
efecto de saldo**. Condición de commit: `autorizante_id ≠ proponente_id` (RNF-031: quien dispensa no
autoriza sus propios ajustes); el intento inválido queda registrado en la auditoría sin efecto.

**Alternativas (regla de cierre de `02 §6`):**

| | A — autorización en la misma transacción | B — propuesta y autorización como eventos idempotentes separados |
|---|---|---|
| Efecto de saldo | Un solo efecto al confirmar ambas identidades | Único efecto al confirmar la autorización (la propuesta no muta saldo) |
| Corte entre pasos | Exige sesión abierta del autorizante | Tolerado: la propuesta persiste pendiente |
| Concurrencia | Dos autorizantes simultáneos → un solo efecto | Idem, por unicidad de la autorización efectiva |
| Costo | Menor complejidad | Requiere ciclo de vida de estado "pendiente" y retiro de propuestas caducadas (plazo = P-14/policy, `null`) |

**Recomendación del bloque: A**, con B como camino si la operación exige proponer y autorizar en
sesiones distintas. **Pendiente de validación del dueño (architect) y del químico** antes del ADR.

**CA de verificación:** ajuste efectivo exige dos identidades distintas en la auditoría (RF-046, RN-06,
RC-06); prueba de concurrencia incluida en el criterio de CA-03 de `04 §5`: **cero** ajustes efectivos
sin segundo actor y un solo efecto con autorización doble simultánea.

**Trazabilidad:** RF-046, RN-05, RN-06, RC-06, RNF-021 (frontera transaccional de inventario), RNF-031.

### DEC-03 — Idempotencia (dueño: architect; CA-07)
**Drivers:** D-02, RN-09, RNF-022.
Definición operativa de las cuatro operaciones: ver §2, punto 8 de cada alternativa (cobro, dispensación, recepción, transferencia — las cuatro, no una genérica). Comparación de mecanismos sobre esas cuatro operaciones:

| Operación | Quién genera la clave | Alcance | Efecto único | Respuesta ante reintento | Misma clave con distinto contenido / primera aún en curso |
|---|---|---|---|---|---|
| **Cobro** | la caja al confirmar el cobro | por venta | un solo cobro | devuelve el resultado original; nunca un segundo cobro | rechazo del contenido distinto (nunca sobrescribe) y espera/reintento sin segundo efecto si la primera sigue en curso |
| **Dispensación** | el mostrador al confirmar | por confirmación | descuento de lote + asiento de libro una sola vez (RF-051/RF-042, RF-055) | devuelve el resultado original | ídem |
| **Recepción** | la sucursal al confirmar la remisión | por remisión | un único ingreso y una única entrada en cuarentena | devuelve el resultado original | ídem |
| **Transferencia** | el sistema al confirmar el par | por par despacho/recepción | un único movimiento y un único doble asiento (RF-045) | devuelve el resultado original | ídem |

- **3-a Clave de idempotencia persistida con registro único (recomendada):** clave generada en el borde y persistida **en la misma transacción del efecto** → corte o reintento devuelven el resultado original (ALT-1, ALT-2, ALT-3, ALT-5 en el borde).
- **3-b Restricción única a nivel de datos:** **no es una alternativa, es la implementación de 3-a** (el índice único es el guardián); misma garantía, peor diagnóstico.
- **3-c Identidad de negocio (comparada, no recomendada):** deduplicación por identidad natural (número de remisión, par de transferencia, referencia de venta) sin clave separada. **Diferencias reales de propiedades frente a 3-a:** (i) para **cobro y dispensación** la identidad de negocio puede no existir hasta confirmar la venta → la unicidad no se puede imponer antes del efecto; (ii) reintentar con **distinto contenido bajo la misma identidad** es indistinguible de una corrección legítima → peor comportamiento en la prueba CA-07 de cobro; (iii) los identificadores de negocio pueden reutilizarse legítimamente en el tiempo → exige **ventana de retención de claves** y restricción temporal, cuyo plazo **no está definido** (pendiente nueva; RC-03 — no se inventa).
- En **ALT-4**, 3-a/3-b se aplican **dos veces** (clave local al confirmar, clave global al sincronizar).

### DEC-07 — Modo degradado (dueño: architect; **bloqueante**; valida cliente + químico)
**Drivers:** D-07, D-15, restricción 9, RNF-043, RNF-047 (reloj), RF-047, RF-053, RN-02, RN-04.
Las tres opciones de `02 §3` comparadas por propiedades, con nombres normalizados:

- **Opción C — componente mínimo:** caché de catálogos + cola de escritura en la sucursal/caja (base de ALT-1/ALT-2/ALT-3/ALT-5).
- **Opción R — solo lectura:** sin escritura en corte; el mostrador no cobra ni dispensa (fallback si no se despliega componente alguno).
- **Opción L — estado local en el puesto (= ALT-4):** estado completo y cola de sincronización; la vía natural a 7-C.

Matriz **opción × escenario (3×3):**

| Opción | 7-A no habilitado | 7-B venta libre sin conexión | 7-C bajo receta sin conexión |
|---|---|---|---|
| **C — componente mínimo** | ✅ detención controlada | ✅ venta libre con caché + cola; **RF-047 genera incidente, no stock negativo** | ❌ no verifica saldo global (I-14) → sujeta a **P-15** con criterio **D-15** |
| **R — solo lectura** | ✅ detención | ⚠️ solo consulta; el mostrador no cobra | ❌ sin escritura no hay dispensación |
| **L — estado local (= ALT-4)** | ✅ detención del centro; el puesto puede seguir en C | ✅ natural | ⚠️ vía técnica (cola local) pero **no verifica saldo global hasta reconectar** → **P-15** + criterio **D-15** |

Cobertura por alternativa: **ALT-1/ALT-2/ALT-5 → Opción C** (y R como mínimo); **ALT-3 → Opción C** sobre la unidad de mostrador; **ALT-4 → Opción L** (incluye C).

**CA de verificación por escenario:** 7-A: detención controlada; 7-B: la venta libre opera con la opción elegida y **RF-047 genera incidente, nunca stock negativo**; **7-C: criterio D-15 — las dispensaciones parciales de una misma receta en sucursales distintas nunca superan lo prescrito, incluso concurrente; al reconectar, el sistema detecta y alerta cualquier exceso.** Nota: RF-047 cubre stock, **no** el saldo de receta (I-14); por eso 7-C no se prueba con RF-047 sino con D-15/RF-053.

**Cumplimiento de RC-09 (restricción 9) con la recomendada (ALT-1 + Opción C):** la continuidad del mostrador queda cubierta para **7-A** (detención controlada) y **7-B** (venta libre con caché + cola, incidentes RF-047 en lugar de stock negativo); **7-C** no se compromete ni se descarta: `permitir_bajo_receta_sin_conexion` sigue en `null` (P-03/P-15) y la **propuesta técnica está completa para los tres escenarios** — lo que falta es la decisión del cliente, no la opción. Opción L es la condición de entrada a 7-C si P-15 la autoriza.

Ningún escenario rellena `permitir_bajo_receta_sin_conexion` (`null`). Si P-03/P-15 no responden: la decisión queda como **supuesto explícito** (§2.2 de `02`) con la pregunta abierta en el ADR (condición de entrada a solution-leader).

### DEC-11 — Frontera de API (dueño: architect; **valida security-reviewer**)
**Drivers:** D-14, RNF-025, RNF-035.
- **11-a Superficie mínima por rol con validación centralizada (recomendada):** cada operación expone solo lo que el rol necesita (RNF-025, RNF-035); el corte por rol es una propiedad de la capa única de entrada → **ALT-1/ALT-2/ALT-5** la cumplen naturalmente.
- **11-b Punto único de entrada con políticas por recurso:** más simple de publicar; exige que la política por recurso **recupere el contexto del rol**, que no ve de forma natural → riesgo de cobertura incompleta de permisos, a evaluar sin presuponer error (CA-04, security-reviewer).
- En **ALT-3** hay que cortar la superficie **pública y la interna** (más trabajo, más riesgo); en **ALT-4** el puesto **no** puede exponer API propia (solo cliente de la central). Validación: security-reviewer antes de cerrar; si P-07 (imagen de receta) cambia, la superficie de adjuntos también.

### DEC-12 — Integraciones externas (dueño: architect; **solo aplica si P-09 confirma alcance**)
**Drivers:** RNF-042, RF-050, I-09.
- **12-a Síncrona con timeout/reintento y modo de degradación:** el mostrador nunca espera indefinidamente; si el tercero no responde, se degrada la operación (efectivo sigue; tarjeta en espera) — evita dependencia bloqueante (CA-09).
- **12-b Asíncrona con cola y conciliación posterior:** mejor para terceros lentos, añade cola y conciliación (costo operativo y caso de reconciliación similar a RF-047).
- En ALT-1/ALT-2 la integración vive en la unidad de escritura; en ALT-3 podría vivir en su propia unidad (**solo con medición**); en ALT-4 la cola local puede encolar el intento. **Sin P-09, DEC-12 no se cierra** (supuesto explícito).

---

## 5. Recomendación preliminar y condiciones de cambio

> **Recomendación preliminar sujeta a database-specialist, security-reviewer, devops-architect, architecture-reviewer y ADR final. No es una decisión cerrada.**

**Recomendada: ALT-1 (unidad con módulos internos y registro transaccional con atomicidad multi-registro), con ALT-2 como escalón de crecimiento previsto y DEC-02 = 2-b (+2-a para el lote más disputado, aplicado también al saldo de receta), DEC-03 = 3-a (3-b como implementación), DEC-11 = 11-a, DEC-07 = Opción C para 7-A/7-B, con Opción L como única vía a 7-C si P-15 la autoriza.**

Criterios explícitos que la sostienen (salen de la matriz, no de moda tecnológica):
1. **Cumple la restricción de integridad más exigente** (CA-03/RN-02/RN-03) con la menor superficie donde fallar: una transacción cubre movimiento + libro + auditoría (fuerte en las 4 operaciones).
2. **CA-09 por defecto:** la más simple viable; nada distribuido sin medición.
3. **Costo y operación bajos** coherentes con un sistema de 8 sucursales/48 cajas y presupuesto sin definir (P-11).
4. **Trazabilidad en una fuente** (CA-10, RC-08) sin agregación de historiales.
5. **Camino de crecimiento documentado:** si la lectura satura (D-10, P-05), se activa ALT-2 sin rediseñar el dominio (RNF-010); la frontera lectura/escritura ya está prevista.

**Por qué se descartan las demás (hoy):**
- **ALT-2 completa:** no se descarta — es el escalón 2; se mantiene en espera de medición de lectura (P-05/latencias de reporte).
- **ALT-3:** descartada **por CA-09**: no existe justificación medible (el volumen derivado, ≈21 txn/s pico, tampoco la da); duplica costo y complica CA-10 con 8 sucursales.
- **ALT-4:** descartada **hoy** por P-03 = `null` (modo degradado sin decidir); sin ese requisito aprobado, 48 estados locales son complejidad injustificada. Es la condición de entrada a Opción L/7-C.
- **ALT-5:** descartada por complejidad frente a un dominio pequeño: obliga a un paradigma (sucesos + proyecciones + reproceso) y a frescura garantizada de proyección para venta, cuando la consistencia fuerte ya se obtiene en ALT-1 con el mismo dominio. (Su descarte no presupone ninguna decisión de DEC-05: esa sigue abierta en database-specialist.)

**Condiciones que cambiarían la recomendación:**
| Si... | Entonces... |
|---|---|
| **P-03** habilita **7-B** | Se añade el **componente mínimo (Opción C: caché + cola en sucursal/caja)** a ALT-1 — pieza pequeña, condición documentada (RC-09) |
| **P-03/P-15** habilitan **7-C** | Se adopta la **Opción L** (estado local en el puesto = ALT-4), con RF-047 + criterio **D-15** + lo que defina **P-15** |
| **P-06/P-05 y medición 10x (CA-06)** muestran lectura degradando la p95 | Se activa **ALT-2** |
| La medición 10x muestra la **escritura** del registro como techo | database-specialist evalúa partición (DEC-04/DEC-08); **ALT-3 solo si además hay medición de frontera** (CA-09) |
| **P-09** confirma integraciones de pago complejas | DEC-12 cierra con 12-a o 12-b según latencia del tercero |
| **P-14** rechaza supuestos CAM (p. ej. RF-053 saldo global) | Se reabre 01 → 02 → este documento (I-14 y 7-C cambian de peso) |
| architecture-reviewer levante hallazgo bloqueante (CA-12) | Se revisa antes del ADR |

---

## 6. Riesgos y preguntas abiertas

**Riesgos de la recomendación:** R-01 contención FEFO (mitigación: DEC-02 2-b/2-a); R-03 dimensionar sobre 25.000 no validado; R-04 crecimiento del histórico; R-05 consulta reversa sin objetivo de latencia; R-06 PII en logs; R-09 RPO/RTO sin definir; R-11 (solo si 7-C). R-07 (ventas web) fuera de alcance mientras P-10 no diga lo contrario (RC-13).

**Preguntas abiertas que afectan estas alternativas** (numeración de `01 §8` / `02 §5`):

| Pregunta | Qué altera |
|---|---|
| **P-01** RPO/RTO | Recuperación de ALT-1/ALT-2; CA-08 no cerrable |
| **P-02** Normativa y plazos de retención | CA-11, DEC-06/DEC-09, crecimiento R-04 |
| **P-03** Modo degradado | **Componente mínimo (7-B) y Opción L/ALT-4 (7-C)** — condición de la recomendación |
| **P-04** Disponibilidad/ventanas | devops-architect (DEC-10) |
| **P-05** Latencia de trazabilidad | ALT-2 (proyección) y CA-10 |
| **P-06** Numerador de 25.000 y consultas | CA-05/CA-06, techo de escritura (órdenes de magnitud de §1) |
| **P-07** Receta con imagen | DEC-11 (superficie), almacenamiento adjuntos |
| **P-09** Medios de pago | **DEC-12** (no se cierra sin ella) |
| **P-13** Revalidación al recibir transferencia | DEC-02 (paso en recepción), DEC-05 |
| **P-14** Aprobación de CAM (18 propuestas) | Todos los supuestos de este documento |
| **P-15** Saldo de receta sin conexión | **Escenario 7-C, R-11, Opción L/ALT-4** |

**Pendencia nueva (sin número P):** ventana de retención de claves de idempotencia/identidad de negocio — afecta DEC-03 (opción 3-c); plazo no definido, no se inventa (RC-03). Debe numerarse en `02 §5` si solution-leader la eleva a pendiente formal.

---

## 7. Insumos para database-specialist (propiedades exigidas al motor de datos, sin productos)

DEC-04 y DEC-05 **no se cierran aquí** (CA-04 en su fase): no se descarta ningún candidato de `02 §3` por estilo ni por presuponer su modelo. Propiedades derivadas de RN/RF/RNF que esta propuesta — **y también los candidatos particionados/híbridos** — deben poder demostrar, evaluables sin sesgo:

1. **Atomicidad multi-registro** (propiedad derivada de RN-01, RF-051, D-01) que cubra movimiento de inventario + asiento de libro + auditoría **y, en dispensación, el saldo global de receta (RF-053, D-15)** en una sola unidad atómica: los candidatos particionados/híbridos de `02 §3` deberán demostrarla **sobre su propia frontera**.
2. **Control de concurrencia a granularidad de lote** (optimista con control de versión o exclusivo), **sin imponer mecanismo**; rendimiento con 48 escritores disputando el mismo lote y con dispensaciones concurrentes contra la misma receta (CA-03, CA-05, D-15).
3. **Restricciones únicas** para clave de idempotencia por operación (CA-07) y para claves de negocio (lote, remisión, par de transferencia).
4. **Historial append-only con índices** para consulta reversa por lote y por dispensación (CA-10, RNF-045) y reconstrucción de saldos desde el historial (RNF-024).
5. **Retención prolongada** de histórico y auditoría con consultas por rango eficientes (RN-10, R-04); partición o archivado sin romper la consulta reversa (P-02 plazos `null`).
6. **Aislamiento de lectura de reportes** (para ALT-2: réplica o proyección con retardo medible) sin degradar el canal transaccional (D-10, RNF-005).
7. **Cifrado en reposo y de respaldos** (RNF-036) y **separación de datos personales** de movimientos/lotes/libro para anonimización sin tocar saldos (CAM-002-g, RNF-039 — supuesto P-14).
8. **Despliegue en Linux/contenedor**, configuración por entorno, sin secretos en repositorio (RC-10, RC-11, RNF-060…063).
9. **Escalabilidad** de escritura al escenario 10x y a 8 sucursales sin rediseñar el dominio (CA-06, RNF-010) — el candidato debe poder **particionarse después** sin perder CA-10.
10. **Backup/restauración probables** con RPO/RTO aún `null` (P-01): el motor debe permitir restauración consistente del histórico y del libro (RNF-040/041).

**Observación para revisión (sin modificar requisitos, RC-02):** RNF-021 no menciona el saldo de receta como frontera de transacción (ver DEC-02, §4) — evaluar si el modelado lo cubre pese a eso.

---

## 8. Cobertura de criterios de aceptación

| CA | Texto (resumen) | Dónde se cubre / quién lo completa |
|---|---|---|
| CA-01 | Cada decisión mapeada a RF/RNF/RN | **Cubierto aquí:** cada alternativa lleva su bloque de trazabilidad (§2) y **cada DEC de §4 cita sus drivers** (D-01…D-15, RF, RNF, RN, I, restricciones) |
| CA-02 | ≥2 alternativas comparadas | **Cubierto:** 5 alternativas (§2, rango 4–6) + matriz (§3) |
| CA-03 | Sin stock negativo ni lote no apto bajo concurrencia | **Cubierto:** punto 7 de cada alternativa + DEC-02 (§4) con CA de verificación y segundo recurso (saldo de receta) |
| CA-04 | Base de datos sin sesgo | **→ database-specialist** (§7 solo fija propiedades, sin productos ni mecanismos impuestos) |
| CA-05 | 25.000 txn/h (a validar) | **Cubierto cualitativamente** con órdenes de magnitud (≈7 txn/s base, ≈21/s pico; §1, puntos 1–2 de §2, matriz); numerador = 5 tipos de negocio; consultas aparte (`null`); cuantificación → **devops-architect** con cliente (P-06) |
| CA-06 | Escenario 10x | **Cubierto:** punto 3 de cada alternativa + matriz (≈208 txn/s sobre el pico, derivado de CA-05) |
| CA-07 | Idempotencia de cobro, dispensación, recepción, transferencia | **Cubierto:** punto 8 de cada alternativa (las 4 operaciones) + tabla de 4 operaciones y opciones 3-a/3-b/3-c en DEC-03 (§4) |
| CA-08 | Seguridad, privacidad, observabilidad, recuperación | **Parcial aquí** (punto 11 de §2, con alertas RNF-053/D-13); **→ security-reviewer** (DEC-06, DEC-09) y **devops-architect** (DEC-08, DEC-10); RPO/RTO abiertos (P-01) |
| CA-09 | Sin K8s/microservicios sin justificación | **Cubierto:** ALT-3/ALT-4 condicionadas a medición; matriz fila 13; §5 descarta ALT-3 por esta regla |
| CA-10 | Trazabilidad completa (lote y dispensación) | **Cubierto:** punto 10 de cada alternativa; modelo de datos → **database-specialist** (DEC-05) |
| CA-11 | Datos sensibles y plazos de retención | **→ security-reviewer** (DEC-06/DEC-09); aquí solo el punto 12 por alternativa; plazos `null` (P-02) |
| CA-12 | Sin hallazgos bloqueantes en revisión final | **→ architecture-reviewer** |

**No se ha seleccionado stack, escrito código, instalado frameworks ni alterado RF/RNF/RN (RC-01, RC-02).** Pendiente de validación humana: P-01…P-15 según corresponda (§6), ventana de retención de claves (DEC-03) y aprobación CAM (P-14).

---

## Registro de correcciones

Una línea por punto de la ronda de correcciones (1–21), aplicado en esta versión:

1. DEC-07 reescrita: Opción **L/C/R** (antes 7-op-A/B/C), matriz **opción × escenario 3×3**, 7-B exige **componente mínimo** (caché + cola en sucursal/caja, no "dentro de la misma unidad"), CA de 7-C = criterio **D-15**, cumplimiento de RC-09 con la recomendada explícito, `permitir_bajo_receta_sin_conexion` sigue `null` con propuesta técnica para los 3 escenarios; eliminadas las contradicciones ALT-1/ALT-4/§5 (§2 p.9, matriz fila 9, §4 DEC-07, §5).
2. DEC-04/DEC-05 no condicionan: "motor transaccional único" → **"registro transaccional con atomicidad multi-registro"** (título y descripción ALT-1, §2, matriz, §5); descarte de ALT-5 ya no presupone "historial indexado" (DEC-05-a); §7.1 atomicidad como propiedad derivada de RN/RNF exigible también a los candidatos particionados/híbridos; §7.2 control de concurrencia por lote **sin imponer mecanismo**; §7.9 "particionarse/particionarse después" → "particionarse después".
3. **Línea Drivers** añadida en cada DEC de §4 (DEC-01, DEC-02, DEC-03, DEC-07, DEC-11, DEC-12) y fila CA-01 de §8 corregida en consecuencia.
4. ALT-2 (territorio DEC-08) y ALT-4 declaradas **extensiones ortogonales de ALT-1** en §1 y §2; 5 alternativas con rango 4–6 explícito.
5. DEC-03 con **tabla de las 4 operaciones** (quién genera clave, alcance, efecto único, reintento, misma clave con distinto contenido/primera en curso); **3-b = implementación de 3-a, no alternativa**; añadida **3-c (identidad de negocio)** con diferencias reales de propiedades; **ventana de retención de claves** registrada como pendiente nueva sin inventar plazo.
6. CA-05/CA-06 recalibrados: **≈7 txn/s base, ≈21/s pico, ≈208/s en 10x**; numerador = 5 tipos de negocio (no escrituras, factor no definido); consultas aparte (`null`); puntos 2–3 de ALT-1 recalibrados; ALT-2 justificada por **D-10**, no por volumen; ALT-3 desjustificada por volumen.
7. CA de 7-C = **criterio D-15** (nunca superar lo prescrito incluso concurrente + alerta al reconectar); nota explícita de que **RF-047 cubre stock, no saldo de receta (I-14)**.
8. DEC-02 con **segundo recurso disputado: saldo global de receta (RF-053, D-15)** y su propia prueba; §7.1 lo incluye en la frontera transaccional; **observación sobre RNF-021 registrada sin modificarlo (RC-02)** para database-specialist y architecture-reviewer.
9. Punto 11 de las 5 alternativas con **alertas: fallo de dispensación, discrepancias de inventario, exceso de saldo de receta (RNF-053, D-13)**; RPO/RTO abiertos (P-01).
10. "fuerza fuerte" → **"consistencia fuerte"** (§2 ALT-1/ALT-2, §5).
11. "desproporcionadamente proporcional" → **"proporcional"** (§2 ALT-1 p.13).
12. Eliminado "RC-13 no aplica aquí; es disciplina interna" (§2 ALT-1 p.6).
13. "A-1/A-2/A-3/A-5" → **"ALT-n"** en §4 (DEC-02, DEC-03, DEC-11).
14. "Baja-alta" → **"Baja (para este tamaño)"** (§2 ALT-3 p.6 y matriz fila 6); **matriz normalizada a Baja/Media/Alta** (filas 1, 2, 5, 6, 13).
15. ALT-5 p.9 y matriz fila 9 **alineadas** con la DEC-07 corregida (componente mínimo en 7-B, P-15+D-15 en 7-C).
16. Argumento de 11-b **suavizado** (no presupone exceso de permisos; lo plantea como riesgo a evaluar, CA-04).
17. ALT-2 trazabilidad: RNF-047 (reloj) → **RNF-051 (métricas de latencia)**.
18. ALT-1 p.8 cita también **RF-055** (libro) en dispensación y transferencia.
19. Trazabilidad ALT-1: "D-01…D-05, D-08…D-13" → **"D-01…D-15"**.
20. §1: **"~180 usuarios internos concurrentes"**.
21. **AR-02 / CAM-004-b:** P-16 (S-8) y P-17 (S-9) numeradas en `02 §5` y enlazadas aquí (§6, punto 3): la ventana de retención de claves (DEC-03 3-c) y la observación RNF-021 (DEC-02) quedan con dueño explícito. **AR-01 / CAM-004-a:** bloque **DEC-02x** añadido (§4) con el mecanismo de doble autorización de ajustes/bajas de controlados y su CA, **sin modificar RF-046, RN-05, RN-06, RC-06 ni RNF-031 (RC-02)**; pendiente de validación de architect y químico.

**Pendientes humanos tras esta ronda:** P-01…P-17 (según §6), ventana de retención de claves (DEC-03, P-16), frontera de receta en RNF-021 (P-17), mecanismo de RF-046 (DEC-02x), aprobación CAM-001/002/003/004 (P-14).
