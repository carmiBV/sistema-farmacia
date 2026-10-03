# 01 — Análisis de requisitos (v2)

- **Agente:** requirements-analyst
- **Skill:** requirements-analysis
- **Fuentes leídas:** `00_contexto/` (descripcion, alcance, reglas_negocio, restricciones, **registro_cambios**), `01_requisitos/` (RF, RNF, criterios_aceptacion), `02_configuracion/perfil_carga.yaml`
- **Tratamiento de CAM-001, CAM-002 y CAM-003 (18 propuestas):** cambios registrados en `00_contexto/registro_cambios.md`, **ya aplicados en los archivos pero pendientes de aprobación del cliente** (campo "Aprobado por" vacío). En este análisis se tratan como **supuestos**, no como hechos confirmados: sostienen el diseño mientras el cliente no apruebe, y cualquier agente posterior debe citarlos como pendientes.
- **Alcance de este documento:** análisis y drivers arquitectónicos. **No se selecciona tecnología** (pendiente para etapas posteriores: architect, database-specialist, security-reviewer, devops-architect).

---

## 1. Resumen

Sistema de gestión de farmacia multi-sucursal con inventario **por lote y ubicación**, dispensación en POS trazable (paciente, receta, lote, dispensador), régimen especial para **medicamentos bajo receta y controlados** (libro de control con saldo permanente, doble autorización), transferencias entre sucursales con estados, devoluciones, bajas y recall de lotes.

Dimensionamiento de referencia (a validar): **8 sucursales, 48 cajas, ~180 usuarios internos concurrentes, 25.000 transacciones de negocio/hora con multiplicador de pico 3,0 y escenario 10x a evaluar**.

Las tres tensiones que estructuran el análisis:

1. **Integridad del inventario bajo concurrencia** (48 cajas sobre el mismo stock): sin stock negativo, sin doble descuento, FEFO y bloqueo de lotes no aptos (RN-02, RN-03, RNF-020). CAM-002 agrega RF-047 (incidente de discrepancia tras corte, supuesto pendiente de aprobación).
2. **Continuidad del mostrador ante corte de conexión** (restricción 9, RNF-043) frente a reglas que exigen verificación en línea y libro de controlados (RN-05). El modo degradado sigue en `null`.
3. **Trazabilidad y auditoría inalterables con retención prolongada** (RN-10, RF-061, RNF-046) frente a privacidad y plazos de retención aún sin normativa definida (RNF-039). CAM-002-g acota la anonimización a datos personales (supuesto pendiente de aprobación).

---

## 2. Clasificación de RF y RNF

### 2.1 Requisitos funcionales

| Categoría | RF | Rol arquitectónico |
|---|---|---|
| Maestros y administración | RF-001, RF-002, RF-003, RF-010, RF-020, RF-030 | CRUD convencional; RF-001 es base de autorización (RNF-030/031) |
| Configuración sin despliegue | RF-100 | Parámetros operativos cambian sin código; exige configuración externa (RNF-002, RNF-062). CAM-002-e amplía RF-100 a política de devoluciones (supuesto) |
| Transaccionales de negocio | RF-031, RF-032, RF-040–RF-047, RF-050–RF-055, RF-060, RF-061 | **Núcleo crítico**: efecto sobre inventario por lote, dinero y libro de controlados. RF-047 es **nuevo** (CAM-002-f, pendiente de aprobación) |
| Reportes y auditoría | RF-070, RF-071, RF-090, RF-091 | Carga de lectura y registros append-only; no deben afectar el canal transaccional |

### 2.2 Requisitos no funcionales

| Categoría | RNF |
|---|---|
| Capacidad y rendimiento | RNF-001–RNF-006 |
| Escalabilidad | RNF-010, RNF-011 |
| Integridad y confiabilidad | RNF-020–RNF-025 |
| Seguridad | RNF-030–RNF-037 |
| Privacidad | RNF-038, RNF-039 |
| Trazabilidad y auditoría | RNF-045–RNF-048 |
| Disponibilidad y recuperación | RNF-040–RNF-044 |
| Observabilidad | RNF-050–RNF-053 |
| Portabilidad y mantenibilidad | RNF-060–RNF-063 |

---

## 3. RF críticos

| RF | Por qué es crítico | RN / CA |
|---|---|---|
| RF-043 + RF-041 | FEFO y bloqueo de lotes vencidos/cuarentena/retirados en **toda** salida de inventario | RN-03, CA-03 |
| RF-042 | Sin doble descuento ante reintentos (idempotencia del descuento) | RN-09, CA-07 |
| RF-047 **(nuevo, CAM-002-f, supuesto)** | Tras corte, si el consumo acumulado de un lote supera su stock: incidente + ajuste autorizado; prohíbe stock negativo silencioso en la conciliación | RN-02, RNF-020 |
| RF-051 | El descuento de lote ocurre **al confirmar** la dispensación: define la frontera transaccional | RN-01, RNF-021 |
| RF-052–RF-054 | Receta obligatoria, saldo de receta ante dispensaciones parciales, verificación del químico | RN-04, RN-05 |
| RF-053 **(CAM-002-a, supuesto)** | Saldo de receta **único y global, independiente de la sucursal**: exige verificación de saldo cruzando sucursales en la dispensación | RN-04, RNF-005, RNF-020 |
| RF-055 + RF-046 | Libro de controlados con saldo permanente por producto y sucursal; doble autorización en ajustes | RN-05, RN-06 |
| RF-045 **(CAM-002-c, supuesto)** | Despachar/recibir controlado genera asiento en el libro de **origen y destino**: dos escrituras de libro por transferencia | RN-05, RN-08 |
| RF-060 | Devoluciones restringidas por política; el devuelto no vuelve a stock vendible sin evaluación | RN-11 |
| RF-061 | Recall: identificar sucursales, stock actual y dispensaciones del lote y bloquearlo de inmediato | RN-12, CA-10 |
| RF-031 + RF-032 **(CAM-002-b, supuesto)** | Recepción exige lote/vencimiento/cantidad; cuarentena hasta liberación, **registrada con usuario, fecha y motivo** | RN-07, RN-06 |
| RF-090 + RF-091 | Auditoría de operaciones críticas con valores antes/después; registro de cada consulta a pacientes/recetas | RN-13, RNF-046 |
| RF-071 | Reportes regulatorios de controlados y dispensaciones por período | RN-05 |
| RF-100 **(CAM-002-e, supuesto)** | Parámetros operativos modificables sin código; ahora incluye **política de devoluciones parametrizada** (estados, plazo, condiciones de reingreso) | RNF-002 |
| RF-050 **(CAM-002-d, supuesto)** | Medios de pago contemplados (efectivo, tarjeta, otros) "a confirmar"; terceros → RNF-042 | RNF-042 |

**No críticos para la arquitectura** (pero requeridos): RF-002, RF-003, RF-010, RF-020, RF-030, RF-070.

---

## 4. RNF críticos

| RNF | Exigencia | Nota |
|---|---|---|
| RNF-001, RNF-004, RNF-011 | 25.000 txn/h × pico 3,0, evaluar 10x | Valor de referencia **no validado** (AGENTS.md) |
| RNF-002, RNF-010 | Capacidad configurable y crecimiento de sucursales/cajas sin rediseñar el dominio | Condiciona decisiones de escalado |
| RNF-003 **(CAM-001-g, supuesto)** | Una txn ≠ una sentencia SQL; **consultas de stock/producto NO cuentan** y se miden aparte (`consultas_por_hora_estimadas: null`) | Define el numerador de CA-05; requiere confirmación del cliente |
| RNF-005, RNF-020 | Integridad bajo concurrencia: sin stock negativo ni doble descuento por condiciones de carrera | El RNF más exigente técnicamente; RF-047 añade el caso de conciliación |
| RNF-021, RNF-022, RNF-024 | Fronteras transaccionales (dinero, inventario por lote, libro); idempotencia; saldo reconstruible desde el historial | Exige historial de movimientos como fuente de verdad del saldo |
| RNF-025 | Validación de lote, vencimiento, cantidades y receta **en el servidor** | Restringe la lógica confiable al backend |
| RNF-030 **(CAM-002-h + CAM-003-a, supuesto)**, RNF-031, RNF-037 | MFA al iniciar sesión para administrador, auditor y químico farmacéutico; verificación del químico al dispensar bajo receta y controlados; cajero y auxiliar sin MFA por acción; mínimo privilegio; sesiones con expiración | Método de verificación sin definir. Reduce la fricción del riesgo R-08; ver I-13 |
| RNF-032, RNF-033, RNF-036 | Cifrado en tránsito, hash adaptativo de contraseñas, cifrado en reposo y de respaldos | Mínimo no negociable |
| RNF-038, RNF-039 **(CAM-002-g, supuesto)** | Datos sensibles: acceso por rol, minimización, enmascaramiento; retención/anonimización que **solo afecta datos personales** (movimientos, lotes, cantidades y libro se conservan) | Resuelve la tensión I-11 como supuesto; plazos siguen `null` |
| RNF-045, RNF-046, RNF-047, RNF-048 | Trazabilidad extremo a extremo por lote; auditoría solo anexar; reloj sincronizado; trazabilidad con latencia acotada | RNF-048 ahora remite a parámetro **sin valor** en `perfil_carga.yaml` (CAM-001-d) |
| RNF-040, RNF-041 | Backup/restauración con pruebas; RPO/RTO antes de producción | `null` en configuración |
| RNF-043 | Modo degradado del mostrador: qué se permite/bloquea y cómo se concilia | `null` salvo controlados bloqueados (propuesto) |
| RNF-044 **(CAM-001-c, supuesto)** | Disponibilidad: objetivo **propuesto 99,9 %** y base de medición definida en `perfil_carga.yaml`, pendiente de aprobación | CA-08 incompleto hasta aprobación |
| RNF-006 **(CAM-001-c, supuesto)** | Dispensación con objetivo propuesto en `perfil_carga.yaml` (500/1200/1500 ms), pendiente de aprobación | D-09 |
| RNF-050–RNF-053 | Logs sin PII, métricas, correlación, alertas por fallo de dispensación y discrepancias | Ligado a auditoría y a RF-091 |
| RNF-060–RNF-063 | Linux, contenerización, separación dev/pruebas/prod, mantenimiento activo | Restricciones 2–6 |

---

## 5. Transacciones de negocio vs operaciones técnicas internas

Fuente: `perfil_carga.yaml` (RNF-003, CAM-001-f/g — **supuesto pendiente de confirmación**).

**Transacciones de negocio (numerador de las 25.000/h):**
1. dispensación o venta confirmada
2. recepción de compra
3. ajuste o baja de inventario
4. transferencia entre sucursales
5. devolución

**NO cuentan como transacción de negocio (se miden aparte):**
- consultas de stock/producto → `consultas_por_hora_estimadas: null` (**por estimar con el cliente**)
- escrituras derivadas de una misma operación (movimiento de inventario + asiento en libro de controlados + auditoría + alerta)
- logs, métricas, sincronización, tareas de alertas de vencimiento, generación de reportes (RF-070/071) y consultas administrativas de catálogo

Criterio: **una transacción de negocio = una operación completa con efecto de negocio confirmado**, no una sentencia SQL ni sus efectos colaterales.

---

## 6. Inconsistencias, ambigüedades y requisitos no verificables

Estado: **Cerrado** (resuelto por CAM) · **Cerrado como supuesto** (resuelto en archivo, falta aprobación del cliente) · **Parcial** · **Abierto**.

| ID | Hallazgo | Tipo | Fuente | Estado |
|---|---|---|---|---|
| I-01 | Clave `rto_minutos` duplicada en `perfil_carga.yaml` (líneas 38 y 66) | Defecto de documento | perfil_carga.yaml | **Cerrado** — CAM-001-a; verificado por grep: una sola ocurrencia (línea 45) |
| I-02 | RNF-006/048/041/044 desincronizados de `perfil_carga.yaml` (valores propuestos vs "a definir") | Documentos desincronizados | RNF vs perfil_carga | **Cerrado como supuesto** — CAM-001-b/c/d: RNF remiten a `perfil_carga.yaml` con leyenda de estados; aprobaciones pendientes (§10) |
| I-03 | Restricción 9 exige operar en mostrador ante corte; RNF-043 lo deja "a definir" y `modo_degradado.habilitado: null`; RN-05 exige verificación y libro en línea | Tensión requisito vs regla de negocio | restricciones 9, RNF-043, RN-05 | **Abierto** (el nuevo RF-047 cubre solo la conciliación, no el modo) |
| I-04 | Alcance pone ventas web "a validar"; perfil dejaba rastro `antes: 1200` | Alcance inestable | alcance.md, perfil_carga | **Cerrado como supuesto** — CAM-001-e elimina el rastro; alcance web sigue "a validar" (R-07) |
| I-05 | Numerador de 25.000 txn/h sin definir (¿cuentan las consultas?) | Ambigüedad no verificable (CA-05) | RNF-003, perfil_carga | **Cerrado como supuesto** — CAM-001-f/g: consultas no cuentan y se miden aparte; confirmar con cliente. Nueva métrica `consultas_por_hora_estimadas: null` |
| I-06 | RF-053: saldo de receta global o por sucursal | Ambigüedad funcional | RF-053 | **Cerrado como supuesto** — CAM-002-a: global e independiente de sucursal |
| I-07 | Transferencias de controlados sin asiento en libro de origen/destino | Omisión de regla | RF-055, RN-08 | **Cerrado como supuesto** — CAM-002-c: doble asiento (RF-045) |
| I-08 | Política de devoluciones no definida (RN-11/RF-060 no ejecutables) | Regla no ejecutable | RN-11, RF-060, RF-100 | **Cerrado como supuesto** — CAM-002-e: política parametrizada en RF-100; **valores concretos siguen pendientes** |
| I-09 | RF-050 sin caracterizar medios de pago ni dependencias externas | Requisito incompleto | RF-050, RNF-042 | **Cerrado como supuesto** — CAM-002-d: efectivo/tarjeta/otros "a confirmar"; terceros → RNF-042 |
| I-10 | Sin RF de cierre/arqueo de caja; liberación de cuarentena sin registro explícito | Posible omisión funcional | RF-050, RF-032 | **Parcial** — CAM-002-b cierra la liberación de cuarentena (registrada con usuario/fecha/motivo); **arqueo/cierre de caja sigue abierto** (pregunta 12 de la sección 8, CAM-002 lo excluye explícitamente) |
| I-11 | Inalterabilidad (RN-10, RNF-046) vs anonimización por vencimiento (RNF-039) | Tensión legal | RN-10 vs RNF-039 | **Cerrado como supuesto** — CAM-002-g: anonimiza solo datos personales; movimientos/lotes/libro se conservan. Plazos y normativa siguen `null` |
| I-12 | 99,9 % sin base de medición; RPO/RTO y prueba de restauración en `null` | No verificable (CA-08 incompleto) | RNF-041, RNF-044, perfil_carga | **Abierto** — CAM-001-b solo etiqueta los valores como propuestos; RPO/RTO siguen `null` |
| I-13 | RNF-030 (CAM-002-h) hablaba de "roles administrativos" sin definirlos; solapamiento MFA-en-login vs verificación-en-dispensación del químico | Ambigüedad nueva tras CAM | RNF-030, perfil_carga sesiones, RF-001 | **Cerrado como supuesto** — CAM-003-a/b: MFA al login para administrador, auditor y químico farmacéutico; verificación del químico al dispensar bajo receta y controlados; cajero y auxiliar sin MFA por acción. **Método de verificación (reautenticación o PIN) sigue sin definir** |
| I-14 | **Nuevo:** el saldo de receta global (RF-053, CAM-002-a) **no puede verificarse sin conexión**; RF-047 cubre solo el sobreconsumo de stock de lote, no el de saldo de receta | Tensión requisito vs modo degradado | RF-053, RF-047, RNF-043, **I-03**, `permitir_bajo_receta_sin_conexion` (CAM-003-c) | **Abierto** |

---

## 7. Riesgos

| ID | Riesgo | Impacto | Fuente | Estado |
|---|---|---|---|---|
| R-01 | Contención en FEFO: 48 cajas disputando el mismo lote próximo a vencer con stock mínimo | Latencia de dispensación fuera de p95 | RN-03, RNF-006 | Abierto |
| R-02 | Conciliación posterior a modo degradado: consumos concurrentes en dos sucursales del mismo lote pueden revertirse en stock negativo | Violación RN-02/RNF-020 | RNF-043, RN-02 | **Parcialmente mitigado (supuesto):** RF-047 (CAM-002-f) registra el incidente y exige ajuste autorizado; depende de aprobación y del modo degradado aún `null` |
| R-03 | Dimensionar con 25.000 txn/h no validado; volumen de consultas desconocido | Sobre/subdimensionamiento | RNF-001, I-05 | Abierto (numerador definido como supuesto; `consultas_por_hora_estimadas: null`) |
| R-04 | Auditoría y libro append-only con retención de años: crecimiento de almacenamiento, costo de respaldo y de consultas | Costo y mantenibilidad | RN-10, RNF-046, RNF-039 | Abierto |
| R-05 | Consulta reversa de recall y trazabilidad sobre histórico completo sin objetivo de latencia definido | Incumplimiento RN-12/RNF-048 | RF-061, I-02 | Abierto |
| R-06 | Fuga de PII de pacientes/recetas en logs, reportes o errores | Incumplimiento RN-13/RNF-038/050 | RNF-038, RNF-050 | Abierto |
| R-07 | Reactivación de ventas web/domicilio a mitad de proyecto | Rediseño de escalabilidad y alcance | alcance.md, I-04 | Abierto (rastro eliminado, alcance sigue "a validar") |
| R-08 | Fricción de MFA y sesiones de 5 min en mostrador → compartir sesiones | Debilita RNF-030/031 | RNF-030, RNF-037 | **Mitigado como supuesto (CAM-002-h, refinado por CAM-003-a):** MFA al login de administrador/auditor/químico, verificación del químico al dispensar bajo receta y controlados, cajero y auxiliar sin MFA por acción; ver I-13 |
| R-09 | RPO/RTO sin definir: no hay criterio de aceptación para recuperación | CA-08 no cerrable | RNF-041, I-12 | Abierto |
| R-10 | Normativa sanitaria y de datos "por identificar": puede imponer retención, consentimiento o residencia de datos no previstos | Restricciones nuevas tardías | restricciones 7–8 | Abierto |
| R-11 | **Nuevo:** dispensación doble en modo degradado: dos dispensaciones con la misma receta en sucursales distintas sin conexión agotan el saldo global sin detectarse hasta reconectar | Violación RN-04/RF-053; posible exceso de cantidad prescrita | RF-053, RNF-043, **I-14**, I-03 | Abierto (depende de `permitir_bajo_receta_sin_conexion`, en `null`) |

---

## 8. Preguntas abiertas

Con estado tras CAM-001, CAM-002 y CAM-003.

1. **RPO, RTO y frecuencia de prueba de restauración** (RNF-040/041): ¿valores aceptados por el cliente? — **abierta**.
2. **Normativa aplicable**: país y regulación sanitaria y de protección de datos (restricciones 7–8, RNF-039). — **abierta**.
3. **Modo degradado**: ¿habilitado? ¿Venta libre/bajo receta sin conexión? ¿Tiempo máximo? ¿Conciliación exacta? (RNF-043; RF-047 cubre solo discrepancias). — **abierta**.
4. **Disponibilidad**: 99,9 % ¿24×7 u horario de atención? ¿Ventanas de mantenimiento? — **abierta** (valores propuestos, no aprobados).
5. **Latencia de trazabilidad** (RNF-048): p95 por lote, paciente y receta (`null`). — **abierta**.
6. **Conteo de consultas en las 25.000 txn/h**: supuesto CAM-001-f/g dice que **no cuentan** → confirmar con el cliente. Además, **estimar `consultas_por_hora_estimadas`**. — **supuesto pendiente de confirmación + nueva estimación**.
7. **Receta**: ¿solo datos o también imagen digital? — **abierta**. Saldo global → supuesto CAM-002-a, **pendiente de aprobación**.
8. **Política de devoluciones** concreta (estados, plazo, condiciones de reingreso): estructura parametrizada (supuesto CAM-002-e), **valores pendientes**.
9. **Medios de pago**: efectivo/tarjeta/otros "a confirmar" (supuesto CAM-002-d) e integraciones con terceros. — **pendiente de confirmación**.
10. **Alcance web**: ¿se reactivan ventas web/entrega a domicilio? — **abierta**.
11. **Presupuesto y operación**: equipo de monitoreo, respaldos y actualizaciones (restricción 10). — **abierta**.
12. **Cierre/arqueo de caja**: ¿en alcance? — **abierta** (CAM-002 lo excluye; era parte de I-10).
13. **Recepción de transferencias**: ¿se revalida vencimiento/cuarentena al recibir? (RN-08, RF-045). — **abierta**.
14. **Aprobación de CAM-001, CAM-002 y CAM-003** en su totalidad (18 propuestas, ver `registro_cambios.md`). — **abierta, bloqueante de confianza en todo lo marcado como supuesto**.
15. **Saldo de receta sin conexión (I-14)**: ¿se permite dispensar bajo receta con `permitir_bajo_receta_sin_conexion` en `null`? Si se permite, ¿cómo se evita la doble dispensación de la misma receta en sucursales distintas hasta reconectar? — **abierta para el cliente y el químico farmacéutico**.

---

## 9. Drivers arquitectónicos

| ID | Driver | Fuente (RF/RNF/RN/CA) | Criterio de verificación |
|---|---|---|---|
| D-01 | Integridad del inventario bajo concurrencia: sin stock negativo, sin doble descuento, FEFO, bloqueo de lotes no aptos; incidente + ajuste autorizado en conciliación post-corte | RN-02, RN-03, RF-041, RF-042, RF-043, **RF-047**, RNF-005, RNF-020, CA-03 | Prueba de concurrencia con 48 cajas que agota un lote: nunca stock negativo ni salida de lote bloqueado; simulación de corte genera incidente, no stock negativo |
| D-02 | Idempotencia de cobro, dispensación, recepción y transferencia | RN-09, RF-042, RNF-022, CA-07 | Reenvío de la misma operación produce un único efecto de negocio |
| D-03 | Trazabilidad extremo a extremo y consulta reversa (lote → stock por sucursal + dispensaciones; dispensación → paciente, receta, lote, responsable) | RN-01, RN-12, RF-045, RF-061, RNF-045, CA-10 | Ambas consultas reversas completas en tiempo acotado (objetivo pendiente, RNF-048) |
| D-04 | Libro de controlados con saldo permanente, doble autorización, segregación de funciones y **asiento doble en transferencias** (origen y destino) | RN-05, RN-06, **RF-045**, RF-046, RF-054, RF-055, RF-071, RNF-031 | Saldo del libro concuerda con el historial por sucursal; un dispensador no autoriza su propio ajuste |
| D-05 | Auditoría inalterable y correcciones por movimientos compensatorios; anonimización que no toca movimientos/lotes/libro | RN-10, RF-090, RF-091, RNF-023, RNF-046, RNF-039 | Ningún usuario operativo modifica registros confirmados; la anonimización preserva saldos e historial |
| D-06 | Privacidad de pacientes y recetas: acceso por rol, minimización, enmascaramiento, registro de cada consulta, retención definida | RN-13, RF-091, RNF-030, RNF-031, RNF-036, RNF-038, RNF-039, CA-11 | Auditoría de accesos completa; logs y reportes sin PII innecesaria; plazos aprobados por el cliente |
| D-07 | Continuidad del mostrador ante corte y conciliación controlada (incidente de discrepancia, ajuste autorizado); la política de dispensación sin conexión debe resolver que **el saldo de receta global no es verificable offline** (I-14) | restricción 9, RNF-043, RNF-047, **RF-047**, **RF-053**, RN-02, RN-04 | Simulación de corte: operaciones permitidas según política (aún `null`), sin stock negativo tras conciliar y sin doble dispensación de saldo de receta |
| D-08 | Capacidad configurable: 25.000 txn/h con pico 3,0, escenario 10x, crecimiento de sucursales sin rediseño | RNF-001, RNF-002, RNF-004, RNF-010, RNF-011, CA-05, CA-06 | Carga sostenida al objetivo y al 10x con métricas; agregar sucursal no rediseña el dominio |
| D-09 | Latencia acotada en el mostrador (p95 propuestos: consulta 500 ms, venta 1200 ms, dispensación con receta 1500 ms) | RNF-006, perfil_carga | Mediciones p95 dentro del objetivo (**valores propuestos, por aprobar**) |
| D-10 | Separación del canal transaccional del canal de reportes/analítica | RF-070, RF-071, RNF-005 | Los reportes no degradan la latencia de dispensación |
| D-11 | Portabilidad: Linux, contenerización, config separada por entorno, tecnologías con mantenimiento activo | restricciones 2–6, RNF-060–RNF-063 | Despliegue reproducible en Linux por contenedor; sin secretos en repositorio |
| D-12 | Configuración externa: parámetros operativos (incluida política de devoluciones) y capacidad modificables sin desplegar código | **RF-100**, RNF-002, RNF-062 | Cambio de umbral/parámetro sin rebuild; cambios auditados |
| D-13 | Disponibilidad, recuperación y observabilidad: backup probado, RPO/RTO definidos, logs/métricas/alertas sin PII; alerta de discrepancias de inventario y de receta en la reconciliación post-corte | RNF-040–RNF-044, RNF-047, RNF-050–RNF-053, CA-08 | Prueba de restauración periódica; alertas por fallo de dispensación, discrepancias de inventario y exceso de saldo de receta |
| D-14 | Validación de datos críticos en el servidor | RNF-025 | Lote, vencimiento, cantidades y receta se revalidan siempre en backend |
| D-15 | **Nuevo:** saldo de receta **global e independiente de sucursal**: toda dispensación con receta debe verificar el saldo considerando todas las sucursales; **sin conexión esa verificación no es posible** (I-14, CAM-003-c) → la política `permitir_bajo_receta_sin_conexion` decide si se dispensa bajo riesgo o se bloquea | **RF-053**, RN-04, RNF-005, RNF-043, **I-14** | Dispensaciones parciales de la misma receta en sucursales distintas nunca superan la cantidad total prescrita, incluso bajo concurrencia; en modo degradado, reconectar detecta y alerta cualquier exceso |

---

## 10. Requisitos que requieren validación humana

Ninguno de los valores siguientes puede darse por definitivo; los agentes posteriores deben tratarlos como **supuestos sensibles**, no inventarlos.

| Item | Valor actual | Fuente | Quién valida |
|---|---|---|---|
| **Aprobación de CAM-001, CAM-002 y CAM-003** (18 propuestas aplicadas: 7 + 8 + 3) | "Aprobado por: _(vacío)_" | registro_cambios.md | Cliente (revalida todo lo marcado como supuesto en este documento) |
| 25.000 transacciones de negocio/hora | Referencia | RNF-001, CA-05 | Cliente |
| ¿Consultas NO cuentan en el volumen? + volumen de consultas/hora | Supuesto CAM-001-f/g / `null` | RNF-003, perfil_carga | Cliente |
| Latencia p95 (500/1200/1500 ms) y de trazabilidad (`null`) | Propuesto / `null` | RNF-006, RNF-048, perfil_carga | Cliente |
| Disponibilidad 99,9 % y ventana (24×7 vs horario) | Propuesto / "a validar" | RNF-044, perfil_carga | Cliente |
| RPO, RTO, frecuencia de prueba de restauración | `null` | RNF-040, RNF-041 | Cliente |
| Modo degradado (habilitado, operaciones permitidas, tiempo máximo, conciliación) y `permitir_bajo_receta_sin_conexion` (I-14) | `null` (solo controlados bloqueados propuesto; saldo de receta sin conexión no verificable) | RNF-043, restricción 9, RF-053, CAM-003-c | Cliente + químico farmacéutico |
| Normativa sanitaria y de protección de datos | "por identificar" | restricción 7 | Cliente + asesoría legal |
| Plazos de retención (dispensaciones, recetas, libro, auditoría) | `null` | RNF-039, perfil_carga | Cliente + asesoría legal |
| Política de devoluciones (estados, plazo, reingreso) | Estructura supuesta; valores `null`/a definir | RN-11, RF-060, RF-100 | Cliente |
| Saldo de receta global (CAM-002-a) | Supuesto | RF-053 | Cliente |
| Medios de pago (CAM-002-d) | "a confirmar" | RF-050 | Cliente |
| Roles con MFA y verificación al dispensar (I-13) | Cerrado como supuesto (CAM-003-a/b): administrador, auditor, químico con MFA en login; cajero y auxiliar sin MFA por acción. **Método de verificación: sin definir (`null`)** | RNF-030, perfil_carga | Cliente + seguridad |
| Alta de controlados sin conexión | Propuesto: bloqueado | RN-05, perfil_carga | Cliente + químico farmacéutico |
| Inclusión de ventas web/entrega a domicilio | "a validar" (fuera de alcance inicial) | alcance.md | Cliente |
| Presupuesto | Sin definir | restricción 10 | Cliente |
| ~~Corrección de clave duplicada `rto_minutos`~~ | Verificado (una sola ocurrencia) | perfil_carga.yaml:45 | ~~Pendiente~~ **Cerrado (CAM-001-a)** |

---

## 11. Salida de este análisis

- **Insumo para:** `architect` (comparación de ≥2 alternativas, CA-02), `database-specialist` (justificación de persistencia, CA-04), `security-reviewer` (D-06), `devops-architect` (D-11, D-13), `architecture-reviewer` (contrarrevisión) y `solution-leader` (consolidación y ADR).
- **No se ha:** elegido tecnología, generado código, instalado frameworks ni alterado RF/RNF/RN (los cambios CAM-001/CAM-002/CAM-003 están registrados en `registro_cambios.md` y pendientes de aprobación).
- **Restricción vigente:** hasta aprobación del ADR final, ninguna decisión de stack (AGENTS.md).
- **Cualquier cambio posterior a RF/RNF/RN/parámetros** debe registrarse en `00_contexto/registro_cambios.md` (qué, por qué, quién aprueba).

---

## 12. Cambios respecto al análisis anterior

Primera edición → esta edición (v2), tras CAM-001, CAM-002 y CAM-003 — **18 propuestas** (7 + 8 + 3) en `registro_cambios.md`, todas pendientes de aprobación.

### 12.1 Hallazgos resueltos

| Hallazgo | Resolución |
|---|---|
| **I-01** (clave `rto_minutos` duplicada) | CAM-001-a. Verificado por grep: una sola ocurrencia (`perfil_carga.yaml:45`). **Cerrado** |
| **I-02** (RNF vs perfil desincronizados) | CAM-001-b/c/d: leyenda de estados en el perfil; RNF-006/044 remiten al valor propuesto; RNF-048 remite al parámetro sin valor. **Cerrado como supuesto** (aprobaciones pendientes) |
| **I-04** (rastro `antes: 1200` de clientes web) | CAM-001-e. **Cerrado como supuesto** (el alcance web sigue "a validar" → R-07 abierto) |
| **I-05** (numerador de 25.000 txn/h) | CAM-001-f/g: consultas de stock/producto no cuentan y se miden aparte (`consultas_por_hora_estimadas: null`). **Cerrado como supuesto**; nueva pregunta de estimación de consultas |
| **I-06** (saldo de receta global/por sucursal) | CAM-002-a: global. **Cerrado como supuesto** |
| **I-07** (transferencia de controlados sin asiento doble) | CAM-002-c: RF-045 exige asiento en libro de origen y destino. **Cerrado como supuesto** |
| **I-08** (política de devoluciones no ejecutable) | CAM-002-e: RF-100 la parametriza. **Cerrado como supuesto**; valores concretos pendientes |
| **I-09** (medios de pago sin caracterizar) | CAM-002-d: efectivo/tarjeta/otros "a confirmar"; terceros → RNF-042. **Cerrado como supuesto** |
| **I-11** (inalterabilidad vs anonimización) | CAM-002-g: la anonimización afecta solo datos personales; movimientos/lotes/libro se conservan (RNF-039, RN-10). **Cerrado como supuesto**; plazos/normativa siguen `null` |
| **R-08** (fricción de MFA en mostrador) | CAM-002-h (RNF-030) + **CAM-003-a/b**: MFA al login de administrador/auditor/químico, verificación del químico al dispensar bajo receta y controlados, cajero y auxiliar sin MFA por acción. **Mitigado como supuesto** |
| **I-13** (roles "administrativos" sin definir) | CAM-003-a/b (RNF-030, perfil_carga). **Cerrado como supuesto**; el método de verificación (reautenticación o PIN) sigue sin definir |
| **Fila "clave duplicada"** de validación humana (`rto_minutos`, CAM-001-a) | Cerrada al verificarse I-01 |

### 12.2 Hallazgos parcialmente resueltos

| Hallazgo | Qué queda abierto |
|---|---|
| **I-10** | CAM-002-b cierra la **liberación de cuarentena** (usuario, fecha, motivo). El **cierre/arqueo de caja** sigue fuera (CAM-002 lo excluye; pregunta 12 de la sección 8 abierta) |
| **R-02** (stock negativo tras conciliación) | CAM-002-f añade **RF-047** (incidente + ajuste autorizado): mecanismo definido pero **pendiente de aprobación** y sin modo degradado asociado (I-03 abierto) |
| **R-03** (dimensionamiento) | Numerador definido como supuesto; siguen **`null`** el volumen de consultas/hora y la validación de las 25.000 txn/h |

### 12.3 Hallazgos que siguen abiertos

| Hallazgo | Motivo |
|---|---|
| **I-03** | Modo degradado `null` (RNF-043 / restricción 9 vs RN-05). RF-047 no define qué opera sin conexión |
| **I-14** (**nuevo, CAM-003**) | Saldo de receta global (RF-053) no verificable sin conexión; RF-047 cubre solo stock de lote. Vinculado a I-03 y a `permitir_bajo_receta_sin_conexion` (`null`); riesgo R-11 (doble dispensación) |
| **I-12** | RPO/RTO/prueba de restauración en `null`; 99,9 % y su base de medición solo propuestos |
| **R-01** | Contención FEFO sin evaluar |
| **R-04** | Crecimiento append-only por retención larga |
| **R-05** | Latencia de trazabilidad sin objetivo |
| **R-06** | PII en logs/reportes |
| **R-07** | Alcance web "a validar" |
| **R-09** | RPO/RTO sin definir |
| **R-10** | Normativa por identificar |
| **R-11** | Dispensación doble de saldo de receta en modo degradado (nuevo con CAM-003-c) |

### 12.4 Nota para los agentes posteriores

Todos los elementos marcados **"supuesto"** derivados de CAM-001, CAM-002 y CAM-003 (18 propuestas) carecen de aprobación del cliente (`registro_cambios.md`, campo "Aprobado por" vacío). Las fases siguientes (architect → … → solution-leader) deben:

1. citarlos como supuestos en sus documentos;
2. **no** tratarlos como hechos confirmados en el ADR;
3. listarlos entre las decisiones abiertas si el cliente aún no los aprueba.
