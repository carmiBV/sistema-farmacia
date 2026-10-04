# Desarrollo Incremental y Validación Humana

**Estado:** APROBADO

## Regla Universal de Desarrollo
Cada iteración o mensaje de confirmación del usuario permite:
1. Validar y aprobar el checkpoint anterior si cumple con las pruebas.
2. Ejecutar **UN** solo checkpoint nuevo (`CP`).
3. Realizar la prueba técnica correspondiente.
4. Actualizar el archivo de estado `.json` correspondiente en `.agents/state/`.
5. Marcar `HUMAN_STATUS: PENDING` y detenerse inmediatamente a esperar la confirmación del usuario.

## Matriz de Estados de Control
- **TECHNICAL_STATUS:** `PENDING` | `PASSED` | `FAILED` | `BLOCKED`
- **HUMAN_STATUS:** `PENDING` | `APPROVED` | `CHANGES_REQUIRED`

## Flujo Progresivo de Workflows
1. `03_api_pilot_workflow.md` (`api-pilot-workflow.json`)
2. `04_backend_workflow.md` (`backend-workflow.json`)
3. `05_frontend_workflow.md` (`frontend-workflow.json`)
4. `06_integration_workflow.md` (`integration-workflow.json`)

## Ubicación del Código Fuente
Todo el código producido se integrará exclusivamente dentro del directorio:
`proyecto/06_codigo/`