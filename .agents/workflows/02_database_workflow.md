# Workflow 02 — Ingeniería de base de datos

## Objetivo

Entradas:
- `proyecto/00_contexto/`
- `proyecto/01_requisitos/`
- `proyecto/02_configuracion/`
- `proyecto/03_resultados/`
- `proyecto/04_decisiones/`

Salida:
- `proyecto/05_base_datos/`

## Skills base

### Comunes
- `database-schema-designer`
- `databases`
- `database-documentation`

### Si se selecciona PostgreSQL
- `postgresql-table-design`

### Si se selecciona SQL Server
- `sql-server`
- `sqlserver-engineering`
- `sqlserver-security`
- `mssql` para verificaciones de solo lectura

### Si se selecciona MySQL/MariaDB
- `databases`

## Secuencia obligatoria

### Paso 01 — Lectura y trazabilidad
Usar `database-schema-designer`.

Leer RF, RNF, reglas de negocio, restricciones, resultados de arquitectura y ADR.
Generar:
`proyecto/05_base_datos/01_requisitos_datos/requisitos_datos.md`

No crear SQL todavía.

### Paso 02 — Modelo conceptual
Usar `database-schema-designer`.

Generar entidades, relaciones, cardinalidades y reglas.
Salida:
`proyecto/05_base_datos/02_modelo_conceptual/modelo_conceptual.md`

### Paso 03 — Diagrama E-R
Usar `database-schema-designer`.

Generar:
`proyecto/05_base_datos/02_modelo_conceptual/diagrama_er.md`

Preferir Mermaid para mantener el diagrama versionable.

### Paso 04 — Modelo lógico
Usar `database-schema-designer`.

Definir relaciones, PK, FK, UNIQUE, NULL/NOT NULL y constraints lógicos.
Salida:
`proyecto/05_base_datos/03_modelo_logico/modelo_logico.md`

### Paso 05 — Normalización
Usar `database-schema-designer`.

Revisar 1FN, 2FN y 3FN; aplicar BCNF cuando sea pertinente.
Toda desnormalización debe justificarse con un RF/RNF o patrón de acceso.
Salida:
`proyecto/05_base_datos/04_normalizacion/informe_normalizacion.md`

### Paso 06 — Selección del DBMS
Usar `databases`.

Comparar al menos PostgreSQL, MySQL/MariaDB y SQL Server cuando no exista una
restricción previa. No elegir por preferencia.
Salida:
`proyecto/05_base_datos/05_modelo_fisico/seleccion_dbms.md`

### Paso 07 — Diseño físico
Usar:
- PostgreSQL: `postgresql-table-design` + `databases`
- MySQL/MariaDB: `databases`
- SQL Server: `sql-server` + `sqlserver-engineering`

Generar:
`proyecto/05_base_datos/05_modelo_fisico/modelo_fisico.md`

### Paso 08 — Integridad
Usar `database-schema-designer` y el skill específico del motor.

Documentar PK, FK, UNIQUE, CHECK, DEFAULT, NOT NULL y reglas de negocio.
Salida:
`proyecto/05_base_datos/06_integridad/integridad.md`

### Paso 09 — Seguridad
Usar `databases`.

Para SQL Server añadir `sqlserver-security`.
Para PostgreSQL, usar también `postgresql-table-design` si se considera RLS.

Salida:
`proyecto/05_base_datos/07_seguridad/seguridad.md`

### Paso 10 — Auditoría, histórico y versionamiento
Usar `database-schema-designer` y `databases`.

Distinguir:
- auditoría de cambios;
- historial temporal;
- versionamiento de esquema/migraciones.

Salida:
- `proyecto/05_base_datos/08_auditoria/auditoria.md`
- `proyecto/05_base_datos/09_versionamiento/versionamiento.md`

### Paso 11 — Índices y rendimiento
Usar `database-schema-designer`, `databases` y, para PostgreSQL,
`postgresql-table-design`.

Todo índice debe vincularse a una consulta o patrón de acceso.
Salida:
`proyecto/05_base_datos/10_indices_rendimiento/indices_rendimiento.md`

### Paso 12 — Transacciones y concurrencia
Usar `databases` y el skill específico del motor.

Salida:
`proyecto/05_base_datos/11_transacciones_concurrencia/transacciones_concurrencia.md`

### Paso 13 — Migraciones
Usar `database-schema-designer` + `databases`.

Preparar estrategia forward/rollback antes de ejecutar.
Salida:
`proyecto/05_base_datos/12_migraciones/plan_migraciones.md`

### Paso 14 — Revisión DBA
No desplegar todavía.

Revisar todos los artefactos anteriores usando:
- `database-schema-designer`
- `databases`
- skill específico del motor

Generar:
`proyecto/05_base_datos/15_reportes/revision_dba.md`

Debe terminar con uno de:
- `STATUS: APPROVED`
- `STATUS: CHANGES_REQUIRED`
- `STATUS: REJECTED`

### Paso 15 — Generación SQL
Solo si `STATUS: APPROVED`.

Generar scripts en:
`proyecto/05_base_datos/13_sql/`

### Paso 16 — Conexión
Leer credenciales solo desde `.env`.

No escribir contraseñas en reportes ni commits.

### Paso 17 — Despliegue
Aplicar scripts aprobados.

Nunca ejecutar `DROP DATABASE`, `DROP TABLE`, `TRUNCATE` o equivalentes sin
autorización explícita.

### Paso 18 — Verificación de base real
Usar `database-documentation`.

Si SQL Server, `mssql` puede utilizarse para validaciones de solo lectura.

Generar:
- ERD obtenido de la base real;
- diccionario de datos;
- comparación diseño vs esquema real;
- detección de drift.

Salida:
`proyecto/05_base_datos/15_reportes/documentacion_real.md`

### Paso 19 — Pruebas
Validar PK/FK/UNIQUE/CHECK, auditoría, permisos, transacciones y migraciones.
Salida:
`proyecto/05_base_datos/14_pruebas/resultados_pruebas.md`

### Paso 20 — Informe final
Salida:
`proyecto/05_base_datos/15_reportes/validacion_final.md`

## Checkpoint
Después de cada paso actualizar:
`.agents/state/database-workflow.json`
