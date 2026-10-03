# Registro de cambios

Todo cambio a requisitos (RF/RNF), reglas de negocio (RN) o parámetros de configuración se registra aquí: qué cambia, por qué y quién lo aprueba.

Estados: **Aplicado** (editado en los archivos), **Pendiente de aprobación** (aplicado como propuesta, falta validación del cliente).

---

## CAM-001 · 2026-09-27 · Correcciones tras el primer análisis de requisitos

**Origen:** `proyecto/03_resultados/01_requirements_analysis.md` (hallazgos I-01, I-02, I-04, I-05).
**Ejecutado por:** equipo de análisis.
**Aprobado por:** _(nombre y fecha)_

| ID | Archivo | Cambio | Motivo | Hallazgo | Estado |
|---|---|---|---|---|---|
| CAM-001-a | `02_configuracion/perfil_carga.yaml` | Verificar que exista una sola clave `rto_minutos`; el archivo entregado contiene una sola. | Clave duplicada reportada en el análisis (línea 66). | I-01 | Aplicado (verificar con `grep -n "rto_minutos"`) |
| CAM-001-b | `02_configuracion/perfil_carga.yaml` | Comentario `# propuesto, pendiente de aprobación` en latencias p95, disponibilidad 99,9 %, base de medición, días de alerta de vencimiento, vida útil mínima, expiración de sesiones, MFA por roles y bloqueo de controlados sin conexión. Se agregó una leyenda de estados al inicio. | Distinguir valores propuestos de valores aprobados. | I-02 | Aplicado |
| CAM-001-c | `01_requisitos/RNF.md` (RNF-006, RNF-044) | Se reemplazó "objetivo a definir" por referencia al objetivo propuesto en `perfil_carga.yaml`, pendiente de aprobación. RNF-044 pasa a definir la base de medición de la disponibilidad. | Sincronizar RNF con la configuración. | I-02 | Aplicado |
| CAM-001-d | `01_requisitos/RNF.md` (RNF-048) | Se indica que el parámetro existe en `perfil_carga.yaml` sin valor, pendiente de definir con el cliente. | Los valores de trazabilidad siguen en `null`; no hay propuesta. | I-02 | Aplicado |
| CAM-001-e | `02_configuracion/perfil_carga.yaml` | Se eliminó el comentario "(antes: 1200)" en `clientes_web_concurrentes_estimados`. Se mantiene en 0. | El canal web está fuera del alcance inicial; se elimina el rastro. | I-04 | Aplicado |
| CAM-001-f | `02_configuracion/perfil_carga.yaml` | Se quitó `consulta_de_stock_o_producto` de `tipos_transaccion_negocio` y se agregó `consultas_por_hora_estimadas: null`. | Definir el numerador de las 25.000 txn/h y hacer verificable CA-05. | I-05 | Aplicado (decisión de diseño, confirmar con el cliente) |
| CAM-001-g | `01_requisitos/RNF.md` (RNF-003) | Se agregó: las consultas de stock o producto no cuentan como transacción de negocio y se miden aparte. | Coherencia con CAM-001-f. | I-05 | Aplicado (decisión de diseño, confirmar con el cliente) |

### Valores que NO se modificaron
Permanecen en `null` o "a validar" y se tratan como decisiones abiertas: RPO, RTO, frecuencia de prueba de restauración, retención (dispensaciones, recetas, libro de controlados, auditoría), modo degradado, latencia de trazabilidad, `consultas_por_hora_estimadas`, presupuesto y normativa aplicable.

### Pendientes de aprobación del cliente
- Latencias p95 de consulta, venta y dispensación con receta.
- Disponibilidad 99,9 % y su base de medición (24x7 u horario de atención).
- Criterio de que las consultas no cuentan dentro de las 25.000 txn/h.
- Parámetros propuestos de inventario, sesiones y MFA.
- Bloqueo de controlados sin conexión.

---

## CAM-002 · 2026-09-27 · Omisiones detectadas en el análisis de requisitos

**Origen:** `proyecto/03_resultados/01_requirements_analysis.md` (I-06, I-07, I-08, I-09, I-10, I-11, R-02, R-08).
**Ejecutado por:** equipo de análisis.
**Aprobado por:** _(nombre y fecha)_

| ID | Archivo | Cambio | Motivo | Hallazgo | Estado |
|---|---|---|---|---|---|
| CAM-002-a | `01_requisitos/RF.md` (RF-053) | El saldo de la receta es único y global, independiente de la sucursal. | Evitar dispensar la misma receta completa en dos sucursales. | I-06 | Pendiente de aprobación |
| CAM-002-b | `01_requisitos/RF.md` (RF-032) | La liberación de cuarentena se registra con usuario, fecha y motivo. | Acción crítica sin registro explícito. | I-10 | Pendiente de aprobación |
| CAM-002-c | `01_requisitos/RF.md` (RF-045) | Despachar y recibir un controlado genera asiento en el libro de origen y de destino. | Mantener el saldo permanente por sucursal (RF-055). | I-07 | Pendiente de aprobación |
| CAM-002-d | `01_requisitos/RF.md` (RF-050) | Medios de pago contemplados (efectivo, tarjeta, otros), a confirmar; los de terceros aplican RNF-042. | Caracterizar dependencias externas (DEC-12). | I-09 | Pendiente de aprobación |
| CAM-002-e | `01_requisitos/RF.md` (RF-100) | La política de devoluciones pasa a ser parámetro (estados aceptados, plazo máximo, condiciones de reingreso). | Hacer ejecutable RN-11/RF-060 sin inventar valores. | I-08 | Pendiente de aprobación |
| CAM-002-f | `01_requisitos/RF.md` (RF-047, nuevo) | Discrepancia de stock tras corte se registra como incidente con ajuste autorizado. | Evitar stock negativo silencioso al conciliar (RN-02). | R-02 | Pendiente de aprobación |
| CAM-002-g | `01_requisitos/RNF.md` (RNF-039) y `00_contexto/reglas_negocio.md` (RN-10) | La anonimización afecta solo datos personales; movimientos, lotes, cantidades y libro se conservan. | Resolver tensión inalterabilidad vs. anonimización. | I-11 | Pendiente de aprobación |
| CAM-002-h | `01_requisitos/RNF.md` (RNF-030) | MFA al iniciar sesión para roles administrativos; verificación del químico al dispensar controlados. | Evitar fricción que lleve a compartir sesiones. | R-08 | Pendiente de aprobación |

No se modificó: arqueo o cierre de caja (I-10). Se mantiene como pregunta abierta P-14 hasta que el cliente confirme si está en alcance.

---

## CAM-003 · 2026-09-27 · Roles con MFA y nota sobre saldo de receta sin conexión

**Origen:** `proyecto/03_resultados/01_requirements_analysis.md` v2 (I-13; nuevo hallazgo I-14).
**Ejecutado por:** equipo de análisis.
**Aprobado por:** _(nombre y fecha)_

| ID | Archivo | Cambio | Motivo | Hallazgo | Estado |
|---|---|---|---|---|---|
| CAM-003-a | `01_requisitos/RNF.md` (RNF-030) | Se definen los roles con MFA al iniciar sesión (administrador, auditor, químico farmacéutico), la verificación del químico al dispensar bajo receta y controlados, y que cajero y auxiliar no requieren MFA por acción. | "Roles administrativos" estaba sin definir y se solapaba con la verificación al dispensar. | I-13 | Pendiente de aprobación |
| CAM-003-b | `02_configuracion/perfil_carga.yaml` (sesiones) | `mfa_requerido_para_roles` pasa a [administrador, auditor, quimico_farmaceutico]; se agregan `roles_sin_mfa_por_accion` y `verificacion_en_dispensacion` (método sin definir, `null`). | Reflejar RNF-030 en la configuración. | I-13 | Pendiente de aprobación |
| CAM-003-c | `02_configuracion/perfil_carga.yaml` (modo_degradado) | Solo comentario en `permitir_bajo_receta_sin_conexion`: el saldo de receta global no puede verificarse sin conexión. El valor sigue en `null`. | Dejar visible la tensión entre RF-053 y el modo degradado para la decisión del cliente y del químico. | I-14 | Abierto (sin cambio de valor) |

No se modificó: el método de verificación en dispensación (reautenticación o PIN) ni el valor de `permitir_bajo_receta_sin_conexion`; ambos dependen del cliente.

---

## CAM-004 · 2026-09-28 · Ronda de correcciones AR-01/AR-02/AR-03

**Origen:** `proyecto/03_resultados/informe_arquitectura.md` (revisión final, paso 7 · hallazgos de architecture-reviewer).
**Ejecutado por:** solution-leader (paso 8).
**Aprobado por:** _(nombre y fecha)_

| ID | Archivo | Cambio | Motivo | Hallazgo | Estado |
|---|---|---|---|---|---|
| CAM-004-a | `03_resultados/03_architecture_options.md` (§4) y `registro` interno | Bloque DEC-02x: mecanismo de doble autorización de ajustes/bajas de controlados (dos actores, una transacción, CA verificable), alternativas A/B, sin editar RF-046. | RF-046 no tenía decisión dueña ni mecanismo. | AR-01 | Aplicado (validan architect + químico) |
| CAM-004-b | `03_resultados/02_decision_scope.md` (§5) | Filas P-16 (S-8) y P-17 (S-9) con dueño y nota de excepción de numeración. | S-8/S-9 vivían fuera de la fuente de verdad de pendientes. | AR-02 | Aplicado (P-16/P-17 siguen abiertas con cliente) |
| CAM-004-c | `03_resultados/04_database_analysis.md` (§5) y `registro` interno | Criterio cuantitativo de verificación CA-03/CA-05 (métricas con umbral 0 + método con 48 cajas); p95 con umbral = P-05 (`null`, RC-03). | Recomendación optimista sin umbral verificable. | AR-03 | Aplicado |

No se modificó: RF/RNF/RN, `02_configuracion` ni valores `null` (RC-02, RC-03).

---

## CAM-005 · 2026-10-02 · Cierre de validaciones de Fase F1: Parámetros del Cliente y Químico Farmacéutico

| Campo | Valor |
|---|---|
| **ID** | CAM-005 |
| **Fecha** | 2026-10-02 |
| **Título** | Cierre de validaciones de Fase F1: Parámetros del Cliente y Químico Farmacéutico |
| **Origen** | `proyecto/03_resultados/10_cliente_quimico_validation.md` (Fase F1 de `09_validation_plan.md`); documentos impactados: `02_configuracion/perfil_carga.yaml` |
| **Ejecutor** | solution-leader |
| **Aprobadores** | **Roberto Morales V.** — Director de Operaciones y Tecnología (2026-10-02) · **Dra. Elena Santelices R.** — Químico Farmacéutico Director Técnico (2026-10-02) |
| **Estado de la minuta** | COMPLETADO — Respuestas firmadas |

### Tabla de cambios mapeados

| ID | Archivo modificado | Cambio exacto realizado | Motivo / Justificación sanitaria o técnica | Pregunta o hallazgo resuelto | Estado |
|---|---|---|---|---|---|
| CAM-005-a | `02_configuracion/perfil_carga.yaml` → `perfil_carga` | Nuevo bloque: `transacciones_negocio_por_hora: 25000`, `factor_pico: 3.0`, `transacciones_pico_por_hora: 75000`, `consultas_por_hora_base: 50000`, `consultas_por_hora_pico: 150000`, `distribucion_operaciones_pct`, `proyeccion_escalabilidad_10x.transacciones_por_hora: 250000` | Volumen aprobado como referencia inicial; las consultas de lectura se miden aparte de las transacciones de mutación (coherencia con CAM-001-f/g) y sostienen el escenario 10x sin rediseño | **P-06** · CA-05 · CA-06 · RNF-001 · RNF-003 · RNF-011 | Aplicado |
| CAM-005-b | `02_configuracion/perfil_carga.yaml` → `continuidad_y_sla` | Nuevo bloque: `disponibilidad_objetivo_pct: 99.9`, `ventana_medicion: horario_atencion_comercial`, `recuperacion.rpo_minutos: 5`, `recuperacion.rto_minutos: 60`, `recuperacion.frecuencia_prueba_restauracion_dias: 90` | RPO 5 min y RTO 60 min cierran RNF-041 (antes `null`); ante contingencia las sucursales operan en degradado mientras se restaura. Disponibilidad medida sobre el horario comercial de las 8 sucursales. Simulacro trimestral exigido por RNF-040 | **P-01** · RNF-040 · RNF-041 · RNF-044 | Aplicado |
| CAM-005-c | `02_configuracion/perfil_carga.yaml` → `retencion_datos_anios` | Nuevo bloque: `dispensaciones_y_ventas: 5`, `recetas_medicas: 5`, `libro_controlados: 10`, `registros_auditoria: 5`, `politica_anonimizacion_pii { metodo: anonimizacion_irreversible, alcance: datos_paciente_y_medico, preserva_movimientos_inventario: true }` | El libro de controlados se retiene 10 años por exigencia de la autoridad sanitaria; el resto, 5 años. La anonimización es irreversible sobre datos de paciente y médico pero **conserva** movimientos, lotes y libro, resolviendo la tensión inalterabilidad vs. minimización | **P-02** · RNF-038 · RNF-039 · RN-10 · CAM-002-g | Aplicado |
| CAM-005-d | `02_configuracion/perfil_carga.yaml` → `modo_degradado` | `permitir_venta_libre_sin_conexion: true`, `permitir_bajo_receta_sin_conexion: false` (Opción A estricta), `permitir_controlados_sin_conexion: false` | *Sanitaria:* el saldo de la receta es único y global (RF-053) y no puede verificarse sin el nodo central; sin enlace existe riesgo de dispensar la misma receta en dos sucursales, por lo que se bloquea y se pide al paciente acudir a una sucursal en línea. Controlados prohibidos sin red por RN-05 | **P-03** · **P-15** · I-14 · RN-05 · RN-09 · RF-053 | Aplicado |
| CAM-005-e | `02_configuracion/perfil_carga.yaml` → `modo_degradado.idempotencia` | `ventana_retencion_claves_dias: 7` | Retención de claves de idempotencia de cobro, recepción, dispensación y transferencia para absorber reintentos tardíos sin duplicar efectos | **P-16** · **S-8** · RN-09 · CA-07 · DEC-03 (3-c) | Aplicado |
| CAM-005-f | `02_configuracion/perfil_carga.yaml` → `seguridad_operativa.dispensacion_mostrador` | `mecanismo_autenticacion: pin_4_digitos` | *Sanitaria:* el químico revisa la receta física y valida con su PIN de 4 dígitos; operación de alta frecuencia en mostrador, el PIN evita cuellos de botella. Cierra el método que estaba en `null` | **RF-054** · RNF-030 · CAM-003-a (método `null` → resuelto) | Aplicado |
| CAM-005-g | `02_configuracion/perfil_carga.yaml` → `seguridad_operativa.ajustes_y_bajas_controlados` | `requiere_doble_autorizacion: true`, `mecanismo_autorizante: usuario_y_clave_completa` | *Sanitaria:* el PIN es susceptible de compartirse oralmente en el mostrador; para ajustes y bajas del libro de controlados el autorizante debe reautenticarse con credenciales completas. Mecanismo que DEC-02x dejó pendiente de validación por architect y químico | **RF-046** · RN-06 · RNF-031 · DEC-02x · CAM-004-a | Aplicado |
| CAM-005-h | `02_configuracion/perfil_carga.yaml` (cabezal) | Encabezado «PERFIL DE CARGA, DISPONIBILIDAD Y PARÁMETROS OPERATIVOS (Filtro F1)», `version: "1.0"`, `estado: "APROBADO_F1"` y puntero a `10_cliente_quimico_validation.md` como fuente de verdad | Trazabilidad: el bloque queda identificable, fechado y vinculado a la minuta firmada | — | Aplicado |

### Observaciones de consistencia (abiertas, no bloquean F1)

Registradas para no ocultarlas; ninguna altera RF/RNF/RN.

1. **Clave duplicada `modo_degradado`.** El bloque original (líneas 65–71) y el bloque F1 (líneas 139–145) comparten la misma clave de nivel superior. En YAML la última definición gana, por lo que `habilitado` y `tiempo_maximo_operacion_sin_conexion_minutos` quedarían descartados: el bloque F1 no los incluye. Requiere reconciliación en un **CAM-007**.
2. **Legado de `null` sin migrar.** Permanecen con valor `null` las claves de nivel superior `rpo_minutos` (L44), `rto_minutos` (L45), `frecuencia_prueba_restauracion_dias` (L46), `consultas_por_hora_estimadas` (L24) y `retencion_anios.*` (L73–77), mientras que el bloque F1 fija los valores equivalentes en `continuidad_y_sla`, `perfil_carga` y `retencion_datos_anios`. **El archivo queda con dos fuentes de verdad aparentes**: los `null` legados y los valores aprobados. Mismo destino para `disponibilidad_objetivo_porcentaje`/`disponibilidad_medida_en` (L42–43), hoy marcados todavía como «propuesto, pendiente de aprobación».
3. **`distribucion_operaciones_pct`** y **`proyeccion_escalabilidad_10x`** no figuran textualmente en la minuta: son derivados técnicos del equipo a partir de P-06 y CA-06. Se marcan como derivados, no como respuestas firmadas.

### Impacto en arquitectura

- **Ningún valor fue fijado de manera arbitraria** (RC-03): todos los valores de este CAM provienen de respuestas firmadas en `10_cliente_quimico_validation.md`. El equipo no propuso ni ajustó cifras.
- **Los campos `null` de RPO/RTO y de retenciones quedan cerrados formalmente**: RNF-041 (RPO 5 min / RTO 60 min) y RNF-039/RNF-038 (5 años dispensaciones, recetas y auditoría; 10 años libro de controlados) pasan de `null` a valor aprobado por cliente y Dirección Farmacéutica.
- **Se mantiene RC-01:** este CAM no nombra ningún producto, motor, framework, contenedor ni proveedor; la selección concreta de stack es ADR-002.
- **Se mantiene RC-02:** no se modificó ningún RF, RNF ni RN. RF-046, RF-053, RF-054 y RNF-030 conservan su texto; lo que se cierra es el **mecanismo** y el **parámetro** que tenían pendientes.
- **La Fase F1 se declara COMPLETADA**, con las preguntas P-01 a P-18 dadas por cerradas en la minuta firmada. Esto **habilita el paso al ADR-002 (Selección de Stack Tecnológico y Plataforma)**.
- **Condición de vigencia:** las tres observaciones de consistencia de §«Observaciones» deben reconciliarse antes de que `perfil_carga.yaml` se consuma como fuente única por la aplicación.

---

## CAM-006 · 2026-10-02 · Aprobación de la recomendación final y de ADR-001

> **Nota de renumeración:** esta entrada se registró originalmente como **CAM-005**. Se renumera a **CAM-006** porque la minuta firmada `10_cliente_quimico_validation.md` (§Documentos impactados) y `perfil_carga.yaml` («Fuente de verdad») designan ya **CAM-005** al cierre de la Fase F1. Prevalece el documento firmado; el reordenamiento queda registrado aquí para no alterar RF/RNF/RN ni perder la trazabilidad de la aprobación. Referencias actualizadas en `ADR-001-arquitectura-inicial.md` y `08_final_recommendation.md`.

**Origen:** `proyecto/03_resultados/08_final_recommendation.md` (§1 Veredicto) y `proyecto/03_resultados/adr/ADR-001-arquitectura-inicial.md`.
**Ejecutado por:** solution-leader (paso 8).
**Aprobador:** cliente / responsable del proyecto — aprobación explícita registrada en sesión el 2026-10-02 _(firma/nombre pendiente de consignar por el responsable)_.

| ID | Archivo | Cambio | Motivo | Hallazgo | Estado |
|---|---|---|---|---|---|
| CAM-006-a | `03_resultados/08_final_recommendation.md` (§cabezal) | Estado: `Propuesto — pendiente de aprobación del cliente` → `Aprobado`. | El cliente revisó y aprobó la recomendación arquitectónica final. | — | Aplicado (cliente) |
| CAM-006-b | `03_resultados/adr/ADR-001-arquitectura-inicial.md` (§cabezal) | Estado: `Propuesto` → `Aceptado`. | Puerta de entrada de ADR-002 (motor, framework, contenedor, nube) según `08 §2`. | — | Aplicado (cliente) |
| CAM-006-c | `.agents/state/database-workflow.json` | Levantamiento del bloqueo del Paso 15. | `revision_dba.md §5.1` exigía aprobación explícita + ADR-001 aprobado. | Paso 14 | Aplicado (solution-leader) |

No se modificó: RF/RNF/RN, `00_contexto` (alcance/RN/restricciones), `01_requisitos`, `02_configuracion`, ni ningún valor `null` (RC-02, RC-03). Ningún producto, motor ni proveedor es nombrado por esta aprobación (RC-01): la selección concreta queda en ADR-002.

**Condiciones heredadas de `08 §6`:** P-05 (latencias p95) y P-08 siguen abiertos; P-03/P-15/P-16/P-06 quedan cerrados por CAM-005. F6–F9 de `revision_dba.md` permanecen fuera de este workflow.

---

## Plantilla para próximos cambios