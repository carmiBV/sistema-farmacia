# FASE 1: ANÁLISIS INICIAL DE REQUISITOS DEL PROYECTO
@requirements-analyst
Analiza proyecto/ aplicando requirements-analysis.
No selecciones todavía una tecnología.
Devuelve el contenido para proyecto/03_resultados/01_requirements_analysis.md.

# FASE 2: DEFINICIÓN DEL ALCANCE DE DECISIONES Y RESTRICCIONES TÉCNICAS

Analiza el resultado de requisitos y define:
- decisiones que deben tomarse;
- especialistas necesarios;
- alternativas que deben compararse;
- restricciones críticas;
- preguntas pendientes.

No programes y no selecciones todavía un stack final.
Resultado:
proyecto/03_resultados/02_decision_scope.md

# FASE 3: REVISIÓN DE ANÁLISIS DE REQUISITOS (INCORPORACIÓN DE REGISTRO DE CAMBIOS)
@requirements-analyst
Analiza proyecto/ aplicando requirements-analysis.
Lee proyecto/00_contexto/registro_cambios.md (CAM-001 y CAM-002): los cambios ahí
registrados son propuestas ya aplicadas, pendientes de aprobación del cliente; trátalos
como supuestos, no como hechos confirmados.
No selecciones todavía una tecnología.
Devuelve el contenido para proyecto/03_resultados/01_requirements_analysis.md.
Al final agrega una sección "Cambios respecto al análisis anterior" que indique
qué hallazgos (I-xx, R-xx) quedaron resueltos y cuáles siguen abiertos.

# FASE 4: ACTUALIZACIÓN FINAL DEL ANÁLISIS DE REQUISITOS V2 (AJUSTES Y NORMAS)
@requirements-analyst
Actualiza proyecto/03_resultados/01_requirements_analysis.md (v2) sin cambiar requisitos.
Lee proyecto/00_contexto/registro_cambios.md (CAM-001, CAM-002 y CAM-003): los cambios ahí
registrados son propuestas ya aplicadas, pendientes de aprobación del cliente; trátalos
como supuestos, no como hechos confirmados.
No selecciones todavía una tecnología.

Aplica solo estos ajustes:
1. Cierra I-13 como supuesto según CAM-003-a/b: MFA al login para administrador, auditor y
   químico farmacéutico; verificación del químico al dispensar bajo receta y controlados;
   cajero y auxiliar sin MFA por acción. El método de verificación (reautenticación o PIN)
   sigue sin definir.
2. Agrega el hallazgo I-14: el saldo de receta global (RF-053, CAM-002-a) no puede verificarse
   sin conexión; RF-047 cubre solo el sobreconsumo de stock de lote, no el de saldo de receta.
   Vincúlalo a I-03 y a permitir_bajo_receta_sin_conexion (CAM-003-c). Estado: Abierto.
   Agrega el riesgo correspondiente (dispensación doble en modo degradado), la pregunta
   abierta para cliente y químico farmacéutico, y actualiza D-07 y D-15.
3. Corrige la referencia "P-12" del arqueo de caja por "pregunta 12 de la sección 8".
4. Agrega RNF-047 como fuente en D-07 y D-13.
5. Actualiza los conteos de la sección 10 y la sección 12 ("Cambios respecto al análisis
   anterior") para incluir CAM-003 (18 propuestas en total).

No modifiques RF, RNF, RN ni parámetros. No rellenes valores en null.


# FASE 5: REGENERACIÓN DEL MATRIZ DE DECISIONES CON BASE EN REQUISITOS V2
@solution-leader
Regenera proyecto/03_resultados/02_decision_scope.md completo (alcance de decisiones).
Lee proyecto/03_resultados/01_requirements_analysis.md (v2) y proyecto/00_contexto/registro_cambios.md
(CAM-001, CAM-002 y CAM-003): trata sus cambios como supuestos pendientes de aprobación del cliente.
No decidas nada ni selecciones stack, código, frameworks ni infraestructura.

Requisitos del documento:
1. Un solo dueño por decisión. DEC-11: architect propone el corte de API y security-reviewer lo valida.
2. La puerta de salida asigna al architect DEC-01, 02, 03, 07, 11 y 12, y al database-specialist DEC-04 y 05.
3. DEC-07 se analiza en escenarios: modo degradado no habilitado, habilitado solo con venta libre,
   y habilitado con bajo receta. Incluye I-14 (saldo de receta global no verificable sin conexión)
   como pregunta bloqueante.
4. DEC-12 depende de la pregunta sobre medios de pago; usa el identificador de pregunta que
   corresponda en este documento, sin referencias inexistentes.
5. Agrega RNF-047 como driver de DEC-10.
6. Las validaciones humanas son requisito para cerrar cada decisión, no para iniciarla; las respuestas
   pendientes se tratan como supuestos explícitos y quedan abiertas en el ADR.
7. Las alternativas se formulan por propiedades, sin sesgo tecnológico (CA-04).
8. Numera las preguntas pendientes de forma coherente con el 01 v2 (incluida la pregunta 15 de I-14).
9. No incluyas agentes que no existan en el framework.


# FASE 6: PROPUESA Y COMPARACIÓN DE ALTERNATIVAS ARQUITECTÓNICAS

Actúa como architect (AGENTS.md, paso 3 del pipeline).

ENTRADAS OBLIGATORIAS (léelas antes de escribir):
- proyecto/03_resultados/02_decision_scope.md (versión corregida)
- proyecto/03_resultados/01_requirements_analysis.md (v2)
- proyecto/00_contexto/registro_cambios.md
- criterios_aceptacion.md (CA-01…CA-12)
CAM-001/002/003 son supuestos explícitos pendientes de aprobación (P-14),
no hechos confirmados.

TAREA
Proponer de 4 a 6 alternativas arquitectónicas, formuladas por propiedades
verificables (no por producto, lenguaje, framework, nube ni base de datos).
- Debe incluir la más simple viable (una unidad de despliegue con módulos
  internos).
- Las alternativas distribuidas o con microservicios solo entran con
  justificación medible (CA-09); nunca por defecto.
- Deben ser realmente distintas entre sí, no variantes cosméticas.

COMPARACIÓN (cada alternativa, escala cualitativa con justificación breve;
sin inventar cifras ni valores null)
1. Complejidad (construcción y operación)
2. Escalabilidad: cómo soporta 25.000 transacciones de negocio/hora
   (valor de referencia a validar con el cliente, CA-05; no tratarlo como
   confirmado), pico 3,0, 8 sucursales, 48 cajas
3. Escenario 10x (CA-06): qué se rompe primero y qué habría que cambiar
4. Consistencia por operación (cobro, dispensación, recepción, transferencia):
   fuerte vs eventual, y dónde la eventual es inaceptable (RN-02, RN-03)
5. Costo operativo
6. Mantenibilidad
7. Integridad de inventario bajo concurrencia (CA-03): explicar cómo evita
   (a) stock negativo y (b) la dispensación de lotes no aptos (vencidos, en
   cuarentena o retirados)
8. Idempotencia (CA-07): definir de forma operativa para cobro, dispensación,
   recepción y transferencia (las cuatro, no una genérica)
9. Comportamiento en modo degradado (escenarios 7-A, 7-B y 7-C)
10. Trazabilidad (CA-10): cómo permite (a) dado un lote: recepción, stock por
    sucursal y dispensaciones; (b) dada una dispensación: paciente, receta,
    lote y responsable. Auditoría inalterable (RC-08)
11. Seguridad, privacidad, observabilidad y recuperación (CA-08); incluir
    RPO/RTO como supuesto abierto (P-01), sin inventar valores
12. Datos sensibles y retención (CA-11): cómo acomoda la alternativa el
    tratamiento de datos de pacientes y recetas y los plazos de retención,
    sin fijar plazos (P-02 abierta). Decisión final: security-reviewer
    (DEC-06, DEC-09)
13. Proporcionalidad de despliegue en Linux/contenedores (CA-09)

POR CADA ALTERNATIVA INCLUIR
- Descripción por propiedades
- Cómo abordaría DEC-02, DEC-03, DEC-07, DEC-11 y DEC-12 (resumido)
- Ventajas, desventajas y riesgos que introduce
- Trazabilidad a RF/RNF/RN (CA-01)

RECOMENDACIÓN PRELIMINAR
- Indicar qué alternativa recomiendas y por qué, con criterios explícitos.
- Explicar por qué se descartan las demás.
- Indicar bajo qué condiciones cambiaría la recomendación (p. ej. según la
  respuesta a P-03, P-06 o P-15).
- Marcarla como "recomendación preliminar sujeta a database-specialist,
  security-reviewer, devops-architect, architecture-reviewer y ADR final";
  no es una decisión cerrada.
- La preferencia debe salir de la matriz, no de una tecnología de moda.

TRATAMIENTO DE DECISIONES
- DEC-02 y DEC-07 son bloqueantes: su propuesta técnica (≥2 alternativas
  comparadas por propiedades y su CA de verificación) debe estar completa;
  para DEC-07 cubrir los tres escenarios 7-A, 7-B y 7-C. Solo la validación
  humana puede quedar como supuesto explícito.
- DEC-01, DEC-03, DEC-11 y DEC-12: proponer y comparar; DEC-11 la valida
  security-reviewer; DEC-12 solo aplica si P-09 confirma alcance.
- NO cerrar ni condicionar DEC-04 (persistencia) ni DEC-05 (trazabilidad de
  datos): son del database-specialist. CA-04 (justificar la base de datos sin
  sesgo) se cumple en su fase; aquí solo señalar qué propiedades exige cada
  alternativa al motor de datos, sin nombrar productos.

RESTRICCIONES
- RC-01: sin selección de stack, sin código, sin infraestructura real.
- RC-02: no alterar RF/RNF/RN.
- RC-03: no inventar valores pendientes; tratarlos como supuestos explícitos.
- RC-13: no ampliar el alcance.
- Listar como abiertas las preguntas que afecten cada alternativa
  (P-01, P-02, P-03, P-05, P-06, P-09, P-14, P-15…).

FORMATO DE SALIDA
Archivo: proyecto/03_resultados/03_architecture_options.md
Estructura:
1. Resumen ejecutivo y supuestos aplicados
2. Alternativas (4–6)
3. Matriz comparativa
4. Tratamiento de DEC-01, 02, 03, 07, 11, 12 por alternativa
5. Recomendación preliminar y condiciones de cambio
6. Riesgos y preguntas abiertas
7. Insumos para database-specialist (propiedades requeridas al motor de datos)
8. Cobertura de criterios de aceptación: tabla CA-01…CA-12 indicando dónde
   se cubre en este documento, o qué agente/fase lo completa (CA-04 →
   database-specialist; CA-11 → security-reviewer; CA-12 → architecture-reviewer)

# FASE 7: SUBSANACIÓN Y CORRECCIÓN BLOQUEANTE EN OPCIONES DE ARQUITECTURA
   Actúa como architect (AGENTS.md, paso 3 del pipeline).

TAREA
Corrige proyecto/03_resultados/03_architecture_options.md (versión actual)
sin cambiar su estructura de 8 secciones. No lo reescribas desde cero: aplica
solo los cambios listados y conserva el resto.

ENTRADAS OBLIGATORIAS (léelas antes de editar)
- proyecto/03_resultados/03_architecture_options.md
- proyecto/03_resultados/02_decision_scope.md (versión corregida)
- proyecto/03_resultados/01_requirements_analysis.md (v2): drivers D-01…D-15,
  riesgos R-nn, hallazgos I-nn y §5 (definición de transacción de negocio)
- proyecto/01_requisitos/RF.md, RNF.md y criterios_aceptacion.md
- proyecto/00_contexto/registro_cambios.md
CAM-001/002/003 son supuestos explícitos pendientes de aprobación (P-14).

════════════════════════════════════════
A. CORRECCIONES BLOQUEANTES
════════════════════════════════════════

1. DEC-07 (coherencia). Corrige en §2 (ALT-1 punto 9, ALT-3 punto 9, ALT-4
   puntos 9 y "Ventajas"), §3 (fila 9), §4 (DEC-07) y §5 (recomendación,
   descartes y tabla de condiciones):
   a) El modo 7-B no puede resolverse con "caché y cola dentro de la misma
      unidad": ante un corte de conexión la unidad central no es accesible.
      La caché y la cola de escritura diferida deben vivir en la sucursal o
      en la caja. Reconoce que 7-B exige un componente local mínimo de
      contingencia.
   b) Elimina la contradicción entre ALT-1 (7-B "sin nuevo componente"),
      ALT-4 ("única vía real para 7-B/7-C") y §5 (P-03 elige 7-B → ALT-4).
      Distingue claramente: componente local mínimo de contingencia (caché +
      cola en sucursal o caja) frente a ALT-4 completa (estado local en 48
      cajas con conciliación por caja). Indica cuál necesita cada escenario.
   c) Renombra las opciones de DEC-07 (hoy "7-op-A/B/C") para que no choquen
      con los escenarios 7-A/7-B/7-C de 02 §1.1. Usa: Opción L (estado local
      en el punto de venta), Opción C (modo central degradado con caché),
      Opción R (solo lectura). Presenta la matriz opción × escenario (3×3).
   d) Corrige el CA de verificación de 7-C: hoy dice "solo consulta"; el
      escenario 7-C de 02 es dispensación bajo receta sin conexión.
   e) Explica cómo la recomendación cumple RC-09 (continuidad del mostrador ante
      corte con conciliación controlada). No descartes ALT-4 solo "por P-03 =
      null" sin explicar cómo se cumple RC-09 con la alternativa recomendada.
   f) Mantén que ningún escenario rellena permitir_bajo_receta_sin_conexion
      (null). La propuesta técnica de DEC-07 debe quedar completa para los tres
      escenarios (bloqueante); solo la validación humana puede quedar como
      supuesto explícito.

2. No condicionar DEC-04 ni DEC-05 (son del database-specialist):
   a) Título y descripción de ALT-1: quitar "motor transaccional único".
      Formular como propiedad: "registro transaccional con atomicidad
      multi-registro". Aplicar lo mismo en la recomendación (§5).
   b) Descarte de ALT-5 (§5): descartarla solo como estilo arquitectónico
      (complejidad, frescura de proyección). Eliminar el argumento "ALT-1 ya
      entrega la trazabilidad con historial indexado", que es la opción A de
      DEC-05. Indicar explícitamente que el modelo de datos de trazabilidad
      queda para database-specialist.
   c) §7 ítem 2: sustituir "concurrencia a nivel de fila" por "control de
      concurrencia a granularidad de lote (optimista o exclusivo), sin imponer
      mecanismo".
   d) §7 ítem 1: expresar la atomicidad como propiedad exigida derivada de
      RN/RNF, y añadir que cualquier candidato (incluidos motores
      particionados o híbridos, ver 02 §3 DEC-04) debe demostrar cómo la
      cumple. Corregir "particionarse/particionarse" (ítem 9) → "particionarse
      después".

3. CA-01 (trazabilidad de decisiones). En §4 añade una línea "Drivers" a cada
   decisión, tomada de la tabla de 02 §1:
   - DEC-01: D-08, D-10, RNF-010, CA-02
   - DEC-02: D-01, RN-02, RN-03, RNF-005, RNF-020 (+ D-15 según punto 9)
   - DEC-03: D-02, RN-09, RNF-022
   - DEC-07: D-07, D-15, restricción 9, RNF-043, RNF-047, RF-047, RF-053,
     RN-02, RN-04
   - DEC-11: D-14, RNF-025, RNF-035
   - DEC-12: RNF-042, RF-050, I-09
   Corrige la fila CA-01 de §8 para que su afirmación sea cierta.

════════════════════════════════════════
B. CORRECCIONES IMPORTANTES
════════════════════════════════════════

4. Alternativas genuinamente distintas (§1 y §2): declara que ALT-2 (canal de
   lectura, territorio de DEC-08) y ALT-4 (estado local) son extensiones
   ortogonales de ALT-1 en dimensiones distintas (ubicación de la lectura,
   ubicación del estado), o fusiónalas. Mantén entre 4 y 6 alternativas.

5. DEC-03 / CA-07. Añade en §4 una tabla con las 4 operaciones (cobro,
   dispensación, recepción, transferencia) y estas columnas: quién genera la
   clave, alcance, efecto único garantizado, respuesta ante reintento, y
   comportamiento con misma clave y contenido distinto o con la primera
   operación aún en curso. No inventes la ventana de retención de claves
   (déjala como pendiente). Reconoce que 3-b (restricción única a nivel de
   datos) es una implementación posible de 3-a y no una alternativa
   independiente; presenta al menos una diferencia real de propiedades.

6. CA-05 y CA-06 (§2 puntos 2–3 y §8): añade el orden de magnitud derivado de
   la referencia NO validada (P-06): 25.000/h ≈ 7 transacciones de negocio/s;
   pico ×3 ≈ 21/s; 10x en pico ≈ 208/s. Aclara, según 01 §5, que se cuentan
   transacciones de negocio (5 tipos) y no escrituras: cada una genera varias
   escrituras derivadas (movimiento, libro, auditoría, alerta) con factor no
   definido, y las consultas de stock se miden aparte
   (consultas_por_hora_estimadas: null). Recalibra el calificativo "limitada en
   escritura" de ALT-1 y la justificación de ALT-2/ALT-3 según esto, sin
   inventar cifras adicionales.

════════════════════════════════════════
C. HALLAZGOS DERIVADOS DE 01
════════════════════════════════════════

7. DEC-07 / D-15: usa como CA de 7-C el criterio de D-15 (las dispensaciones
   parciales de la misma receta en sucursales distintas nunca superan la
   cantidad total prescrita, incluso bajo concurrencia; al reconectar, se
   detecta y alerta cualquier exceso). Recuerda que RF-047 solo cubre stock de
   lote, no saldo de receta (I-14).

8. DEC-02: añade el saldo de receta global (RF-053, D-15) como segundo recurso
   disputado entre sucursales, incluso con conexión, con su propia prueba de
   concurrencia. En §7 exige al motor de datos que ese saldo entre en una
   frontera transaccional. RNF-021 no lo lista: NO modifiques RNF-021 (RC-02);
   regístralo como observación para database-specialist y architecture-reviewer.

9. CA-08 (§2 punto 11 de cada alternativa): incluir alertas operativas por
   fallo de dispensación, discrepancias de inventario y exceso de saldo de
   receta (RNF-053, D-13). RPO/RTO siguen como supuesto abierto (P-01).

════════════════════════════════════════
D. CORRECCIONES MENORES
════════════════════════════════════════

10. "fuerza fuerte" → "consistencia fuerte" (ALT-1 descripción, ALT-2
    ventajas, §5 descarte de ALT-5).
11. ALT-1 punto 13: "desproporcionadamente proporcional" → "proporcional".
12. ALT-1 punto 6: eliminar "RC-13 no aplica aquí; es disciplina interna".
13. Unificar nomenclatura: "A-1/A-2/A-3/A-5" → "ALT-1/ALT-2/ALT-3/ALT-5" en §4.
14. Escala: sustituir "Baja-alta" (mantenibilidad de ALT-3, texto y matriz) por
    "Baja (para este tamaño)"; usar solo Baja/Media/Alta.
15. ALT-5 punto 9 y matriz fila 9: alinear (el texto habla de suceso local sin
    conexión; la matriz dice "ídem ALT-1").
16. DEC-11: suavizar el argumento contra 11-b (sin evidencia, sesga; CA-04):
    describir su propiedad sin presuponer que "se excede en permisos".
17. ALT-2 trazabilidad: "RNF-047 (observabilidad de latencia)" → RNF-051
    (RNF-047 es sincronización de reloj).
18. ALT-1 punto 8 (dispensación): citar también RF-055 (asiento de libro).
19. ALT-1 bloque de trazabilidad: añadir D-06, D-07, D-14 y D-15.
20. §1: "~180 usuarios" → "~180 usuarios internos concurrentes".

════════════════════════════════════════
RESTRICCIONES (vigentes)
════════════════════════════════════════
- RC-01: sin selección de stack, lenguaje, framework, nube ni base de datos;
  sin código, sin infraestructura real.
- RC-02: no alterar RF/RNF/RN; las observaciones sobre requisitos se registran
  como observaciones, no como cambios.
- RC-03: no inventar valores null (RPO/RTO, retención, latencia de
  trazabilidad, modo degradado, permitir_bajo_receta_sin_conexion, ventana de
  retención de claves, etc.).
- RC-13: no ampliar el alcance.
- DEC-04 y DEC-05 no se cierran ni se condicionan aquí (database-specialist).
- DEC-02 y DEC-07 son bloqueantes: propuesta técnica completa; solo la
  validación humana puede quedar como supuesto explícito.
- La recomendación preliminar debe seguir marcada como "sujeta a
  database-specialist, security-reviewer, devops-architect,
  architecture-reviewer y ADR final".

SALIDA
Sobrescribe proyecto/03_resultados/03_architecture_options.md.
Al final añade una sección breve "Registro de correcciones" con una línea por
punto (1–20), indicando dónde se applied y si queda algo pendiente de decisión
humana. Antes de terminar, verifica que §8 sea coherente con los cambios
(CA-01, CA-05, CA-07, CA-08, CA-10) y que ninguna sección siga afirmando lo
que se corrigió.

# FASE 8: EVALUACIÓN DE PERSISTENCIA Y MODELADO DE DATOS (DEC-04 Y DEC-05)
Actúa como database-specialist (AGENTS.md, paso 4 del pipeline).

Lee: 03_architecture_options.md (§4 DEC-02/DEC-03 y §7), 02_decision_scope.md
(§1 DEC-04/DEC-05 y §3), 01_requirements_analysis.md (D-01…D-05, D-15, R-01,
R-04, R-05), RNF.md y criterios_aceptacion.md.

Tarea: evaluar la estrategia de persistencia y comparar alternativas por
propiedades, sin nombrar productos ni motores (RC-01; CA-04 sin sesgo previo).
- DEC-04: compara al menos A) atomicidad multi-registro en un registro
  transaccional, B) distribuida con partición por sucursal, C) híbrida
  (transaccional + almacén de historial de solo lectura).
- DEC-05: A) historial de movimientos como fuente de saldo con índices,
  B) eventos con proyecciones. Demuestra las dos consultas de CA-10 (lote →
  recepción, stock por sucursal y dispensaciones; dispensación → paciente,
  receta, lote y responsable).

Analiza por alternativa: concurrencia (48 cajas sobre un lote y saldo global de
receta), consistencia y fronteras transaccionales (RNF-021), idempotencia y
unicidad (DEC-03), recuperación (RPO/RTO null: solo propiedades), volumen y
crecimiento (retención null: estimación paramétrica, sin inventar valores),
auditoría inalterable y anonimización solo de datos personales (RNF-046/039) y
escenario 10x.

Restricciones: RC-01, RC-02, RC-03. No decidir DEC-08/09/10. El §7 de 03 son
propiedades exigidas, no una selección: B y C deben poder demostrarlas.
DEC-04 y DEC-05 son bloqueantes: propuesta técnica completa; solo la validación
humana puede quedar como supuesto explícito.

Formato: máximo ~250 líneas, tablas en vez de prosa. Secciones: 1 supuestos,
2 alternativas DEC-04, 3 matriz, 4 DEC-05 y consulta reversa, 5 volumen,
crecimiento y recuperación, 6 recomendación preliminar (sujeta a
security-reviewer, devops-architect, architecture-reviewer y ADR) y condiciones
de cambio, 7 insumos para security-reviewer y devops-architect, 8 preguntas
abiertas, 9 cobertura de CA (sobre todo CA-04 y CA-10).
Salida: proyecto/03_resultados/04_database_analysis.md

# FASE 9: AJUSTES EN EL ANÁLISIS DE PERSISTENCIA Y MODO DEGRADADO*

Corrige proyecto/03_resultados/04_database_analysis.md editando SOLO lo
indicado (no reescribas el archivo). Lee 03 §4 DEC-07 y §7, y 01 (RF-047,
RF-091).

1. Añade §2.3 "Persistencia local para el modo degradado" (DEC-07, Opciones
   C/L de 03): propiedades exigidas a la cola/caché local (durabilidad,
   idempotencia con doble identidad local/global, cifrado en reposo del puesto
   RNF-036, PII temporal con borrado al sincronizar, registro del incidente de
   RF-047, conciliación). No cierres DEC-07 ni elijas mecanismo. Añade la fila
   correspondiente en §7 para security-reviewer y devops-architect.
2. §3 y §6: sustituye "cumple todo" y "sin mecanismos adicionales" por
   "cumple las 10 propiedades con los mecanismos declarados en §2.2".
3. §5: corrige la fórmula (filas/día = T × horas_operación × k; filas totales
   = eso × D). Usa el pico ×3 solo para tasa (txn/s), no para volumen diario.
   Añade horas de operación como variable (P-04 abierta). Marca D=365 como
   ilustrativo (no es retención propuesta, RC-03) y k como supuesto.
4. §5: añade la variable de consultas auditadas por hora (RF-091), fuera de T,
   pendiente de P-06 y P-02.
5. Renumera los supuestos S-1…S-n en orden. En DEC-05 A añade una nota sobre
   saldo materializado por lote vs agregación de movimientos (contención en
   la fila más disputada).
Sin stack (RC-01), sin inventar valores null (RC-03).


# FASE 10: EVALUACIÓN DE SEGURIDAD, AUTENTICACIÓN Y AUDITORÍA (DEC-06, DEC-09, DEC-11)
Actúa como security-reviewer (AGENTS.md, paso 5 del pipeline).

Lee: 04_database_analysis.md, 03_architecture_options.md (§4 DEC-11, §7),
02_decision_scope.md (DEC-06, DEC-09, DEC-11), 01_requirements_analysis.md
(D-05, D-06, D-14, R-06, R-08, R-10, I-11, I-13), RNF.md (RNF-030…039,
046, 050) y criterios_aceptacion.md.

Tarea:
- DEC-06 (dueño): compara ≥2 alternativas por propiedades para identidad
  (gestionada internamente vs delegada) y cifrado (de aplicación vs de
  plataforma). Cubre MFA, autorización, minimización y enmascaramiento. El
  método de verificación en dispensación (reautenticación o PIN) sigue sin
  definir (CAM-003-a/b): trátalo como supuesto abierto.
- DEC-09 (dueño): compara auditoría dentro del mismo almacén append-only vs
  almacén independiente con protección adicional. Resuelve la tensión I-11
  (inalterabilidad vs anonimización, supuesto CAM-002-g) sin fijar plazos.
- DEC-11 (validador): valida o veta el corte de API propuesto en 03 §4.
- Cubre CA-08 (seguridad, privacidad, observabilidad, recuperación) y CA-11
  (datos sensibles y retención).

Restricciones: RC-01 (sin productos ni stack), RC-02, RC-03 (no inventar
plazos de retención, normativa ni RPO/RTO: P-01, P-02 abiertas). No decidir
DEC-04/05/08/10. Sin decisiones cerradas: recomendación preliminar sujeta a
asesoría legal y ADR final.

Formato: máximo ~250 líneas, con tablas. Incluye hallazgos con severidad
(bloqueante/alto/medio/bajo), requisitos de control por hallazgo y preguntas
abiertas.
Salida: proyecto/03_resultados/05_security_review.md

# FASE 11: REVISIÓN Y CONDICIONAMIENTO DE SEGURIDAD PARA MODO OFFLINE Y PII

Corrige proyecto/03_resultados/05_security_review.md editando SOLO lo indicado
(no reescribas el archivo). Lee 01 (I-13, R-08, RNF-030), 03 §4 DEC-07 y 04 §2.3 y §7.

1. §2.1, H-08 y Q-07: analiza la autenticación y la verificación del químico
   sin conexión (7-B/7-C, RC-09). Condiciona la recomendación de identidad
   delegada a que ambas funcionen sin conexión; sube H-08 a Alto. Sustituye
   "RFC-13" por RNF-042 (dependencia externa).
2. §6.1 y §8: para H-01 y H-02 aclara el tratamiento: se cierran en el ADR
   como supuesto explícito con la pregunta abierta (regla de cierre de 02) y
   son requisito antes de producción; explica cómo esto es compatible con
   CA-12.
3. Añade un hallazgo y su requisito de control sobre: PII en el historial
   derivado (04 DEC-04 C) y en el payload de eventos (DEC-05 B), y sobre
   respaldos frente a anonimización (RNF-036/039); sin fijar plazos.
4. Añade un hallazgo y su control para RNF-053 (alertas por accesos anómalos a
   PII), coordinado con devops-architect; actualiza CA-08 Observabilidad en §5.
5. §2.4: separa MFA al login (administrador, auditor, químico) de la
   verificación en dispensación (químico farmacéutico, RF-054).
6. Menores: "TLS" → "cifrado en tránsito (RNF-032)"; "UPDATE/DELETE" →
   "sin modificación ni borrado"; reformula la frase de H-05 sobre 11-b como
   condición a verificar; separa P-01 de P-02 en Q-02; marca Q-03 y Q-07 como
   preguntas nuevas.
Sin stack (RC-01), sin inventar plazos ni normativa (RC-03).

# FASE 12: ANÁLISIS DE INFRAESTRUCTURA, DESPLIEGUE Y ESTRATEGIA CI/CD
Evalúa despliegue, contenedores, escalado, observabilidad,
backup, recuperación y CI/CD.
Justifica explícitamente usar o NO usar Kubernetes.

Resultado:
proyecto/03_resultados/06_infrastructure.md

## FASE 13: INGENIERÍA Y DISEÑO DE BASE DE DATOS

Continúa este proyecto con la fase de ingeniería de base de datos.

Mantén exactamente la estructura existente.

Lee:
- AGENTS.md
- SKILLS_SOURCES.md
- proyecto/00_contexto/
- proyecto/01_requisitos/
- proyecto/02_configuracion/
- proyecto/03_resultados/
- proyecto/04_decisiones/

Luego sigue:
.agents/workflows/02_database_workflow.md

Usa únicamente los skills existentes instalados en:
.agents/skills/

Continúa desde:
.agents/state/database-workflow.json

Trabaja paso por paso.
No saltes etapas.
No empieces directamente creando tablas o SQL.
No elijas PostgreSQL, MySQL o SQL Server por preferencia:
justifica la selección mediante RF, RNF, restricciones y arquitectura.

Después de cada etapa:
1. indica el skill utilizado;
2. genera el artefacto correspondiente en proyecto/05_base_datos/;
3. resume las decisiones;
4. actualiza el checkpoint;
5. continúa con el siguiente paso si no existe bloqueo.

No conectes ni despliegues hasta que la revisión DBA genere:
STATUS: APPROVED

Nunca escribas credenciales en archivos versionados.
No ejecutes operaciones destructivas sin autorización explícita.


### FASE 14: RESOLUCIÓN DE HALLAZGOS Y APROBACIÓN DE REVISIÓN DBA

Para resolver el hallazgo F15 del Paso 14 (revisión DBA), adopta la Opción 1 de 06_integridad §7 para restringir los lotes con reception_item_id. Cierra el hallazgo F15 como RESUELTO/CERRADO, actualiza el reporte de revisión del DBA en proyecto/05_base_datos/ y marca el Paso 14 como totalmente completado en .agents/state/database-workflow.json con dba_status: APPROVED y 0 hallazgos bloqueantes abiertos.


### FASE 15: EJECUCIÓN Y DOCUMENTACIÓN DE PRUEBAS DE BASE DE DATOS

Con la Revisión DBA del paso 15 aprobada (dba_status: APPROVED), autorizo la fase de pruebas (deploy_authorization = GRANTED). Genera la carpeta proyecto/05_base_datos/14_pruebas/ y redacta el archivo resultados_pruebas.md documentando la validación del esquema SQL (V1.0.0 a V1.4.0), las restricciones de integridad y los triggers de auditoría, finalizando la actualización del checkpoint.


### FASE 16: EJECUCIÓN Y DOCUMENTACIÓN DOCUMENTACIÓN DB


Basándote en el contenido y la estructura de las carpetas 01 a 15 de proyecto/05_base_datos, crea la carpeta 05_base_datos/.database-documentation/ en la raíz del proyecto. Genera dentro de ella toda la documentación detallada del esquema de base de datos, incluyendo la estructura de tablas, diccionario de datos, claves primarias/foráneas, índices y scripts SQL/TSV de respaldo, replicando la misma estructura y archivos que genera el proyecto de ejemplo del docente.

Continúa con el proyecto desde la fase final del flujo de base de datos (.agents/state/database-workflow.json).

La estructura hasta la carpeta 15 ya está completa. Ahora ejecuta la etapa de documentación del esquema y revisión DBA final siguiendo .agents/workflows/02_database_workflow.md y utilizando las skills de .agents/skills/.

Genera la carpeta .database-documentation/ en la raíz del proyecto con la totalidad de sus artefactos de introspección, diagramas y diccionarios de datos:

dictionary_tables.md

er_edges.txt

fk_table_rows.txt

indexes_by_table.md

introspection.tsv

introspection.txt

live_chk_names.txt

live_columns_full.tsv

live_creates.tsv

live_fk_edges.txt

live_trig_names.txt

live_triggers.tsv

mermaid_rel.txt

model_chk.txt

model_chk_names.txt

model_fk.txt

model_fk_edges.txt

model_tables.txt

model_uniq.txt

modelo_fisico.sql

uq_table_rows.txt

Actualiza el checkpoint en .agents/state/database-workflow.json indicando que la revisión DBA ha finalizado con: STATUS: APPROVED.