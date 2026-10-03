# Reglas universales para agentes

Este repositorio es un framework de análisis arquitectónico reutilizable. En esta instancia se aplica al **sistema de gestión de farmacia** (control de inventario de medicamentos por lote y registro de dispensación).

## Orden obligatorio
1. requirements-analyst
2. solution-leader (alcance de decisiones)
3. architect
4. database-specialist
5. security-reviewer
6. devops-architect
7. architecture-reviewer
8. solution-leader (consolidación final)

## Regla principal
Hasta aprobar la recomendación final y el ADR:
- NO generar código de la aplicación.
- NO instalar frameworks.
- NO crear migraciones.
- NO crear infraestructura real.
- NO alterar RF/RNF/RN silenciosamente. Todo cambio a requisitos, reglas de negocio o parámetros debe registrarse de forma explícita, indicando qué cambia, por qué y quién lo aprueba.

## Fuente de verdad
- `proyecto/00_contexto/` (alcance, reglas de negocio RN, supuestos y restricciones)
- `proyecto/01_requisitos/` (criterios de aceptación, RF y RNF)
- `proyecto/02_configuracion/` (parámetros de capacidad, latencia, disponibilidad y reglas operativas)

Los valores en `null` o marcados "a validar" (RPO/RTO, plazos de retención, modo degradado, objetivos de trazabilidad, volumen de 25.000 transacciones/hora) son **pendientes de validación con el cliente**. Los agentes no deben inventarlos: deben señalarlos como supuesto, analizar su sensibilidad y registrar la decisión como abierta en el ADR.

## Consideraciones específicas del dominio farmacéutico
Los agentes deben tratar como prioritarios, en todas sus decisiones:
- **Trazabilidad por lote:** de la recepción a la dispensación, incluidos transferencias, bajas y retiro de lotes (recall).
- **Integridad del inventario:** sin stock negativo, sin doble descuento, con FEFO y bloqueo de lotes vencidos, en cuarentena o retirados.
- **Medicamentos controlados:** libro de control con saldo permanente, doble autorización de ajustes y segregación de funciones.
- **Datos sensibles de pacientes y recetas:** acceso por rol, registro de consultas, cifrado, minimización y retención según normativa.
- **Continuidad del mostrador:** modo degradado ante cortes de conexión y conciliación al reconectar.
- **Auditoría inalterable:** los registros confirmados no se eliminan; las correcciones se hacen por movimientos compensatorios.

## Alcance
Las funciones fuera del alcance inicial (ventas web, entrega a domicilio, contabilidad, nómina, preparados magistrales, historia clínica, integración con seguros, app móvil nativa) no deben incorporarse a las decisiones salvo que el cliente las agregue formalmente al contexto. Si se agregan, se actualizan primero contexto, requisitos y configuración, y luego se repite el análisis afectado.

## Reutilización
Para usar este framework con otro sistema:
1. copiar `plantillas/` a `proyecto/`;
2. reemplazar el ejemplo de farmacia por el del nuevo sistema;
3. conservar `.agents/skills/`, `.opencode/agents/` y `.claude/agents/`.

## Trazabilidad
Toda decisión debe relacionarse con un RF, RNF, regla de negocio (RN), restricción o medición. Las decisiones de seguridad, privacidad y trazabilidad deben citar además el requisito o la norma sanitaria o de protección de datos que las motiva, o marcarlos como pendientes de validación.

## Criterios de tecnología
- No se asume lenguaje, framework, base de datos ni nube; toda elección debe justificarse por requisitos, no por preferencias.
- No introducir Kubernetes ni microservicios sin justificación medible (CA-09).
- Se comparan al menos dos alternativas arquitectónicas (CA-02) y se evalúa el escenario 10x (CA-06).
- Las tecnologías deben ser desplegables en Linux, contenerizables y con mantenimiento activo.
- Ningún secreto se almacena en código o repositorio.
  
## Política de skills
No crear skills de base de datos si ya existe uno instalado que cubra la tarea.
Los skills de BD deben provenir de repositorios registrados en
SKILLS_SOURCES.md.

Los skills instalados se encuentran en:
.agents/skills/
  
## Flujo de BD
Cuando se solicite diseño/implementación de base de datos:
1. leer SKILLS_SOURCES.md;
2. leer .agents/workflows/02_database_workflow.md;
3. leer .agents/state/database-workflow.json;
4. usar los skills externos indicados para la etapa;
5. trabajar en orden;
6. actualizar el checkpoint.
   
   ## Reglas
- No saltar al SQL antes de completar el diseño.
- No elegir DBMS por preferencia.
- Mantener trazabilidad RF/RNF → modelo → constraint/índice/decisión.
- No guardar credenciales en archivos versionados.
- No desplegar antes de STATUS: APPROVED.
- No ejecutar operaciones destructivas sin autorización explícita.
  