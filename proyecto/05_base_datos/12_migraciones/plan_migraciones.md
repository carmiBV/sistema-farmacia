# Paso 13 — Plan de migraciones

- **Workflow:** 02_database_workflow · **Paso:** 13
- **Skills utilizados:** `database-schema-designer` + `databases`
- **Base:** `09_versionamiento/versionamiento.md` (herramienta propuesta: Flyway), §11 de decisiones.
- **Regla:** preparar forward/rollback **antes** de ejecutar; sin ejecución real en este workflow (Paso 17 requiere `STATUS: APPROVED` + autorización).

## 1. Estrategia forward

| Fase | Contenido | Condición |
|---|---|---|
| F1 — Esquema base | `V1.0.0`: ~40 tablas por dominio (Paso 04), FKs, CHECKs, UNIQUE, ENUM, timestamps UTC | tras `STATUS: APPROVED` (Paso 14) |
| F2 — Índices | `V1.1.0`: I-01…I-23 del Paso 11 con `ALGORITHM=INPLACE, LOCK=NONE` | idem |
| F3 — Grants | `V1.2.0`: usuarios/permisos del Paso 09 (`migrator` ejecuta; usa credencial de entorno) | tras definir usuarios en `.env` |
| F4 — Datos iniciales | `V1.3.0`: filas de `system_config` **solo con valores aprobados** (los `null` NO se filan: RF-100 sin valores inventados), roles base RF-001 | requiere listado de roles/parámetros aprobados |
| F5 — Triggers auditoría | `V1.4.0`: triggers del Paso 10 | idem |
| F6 — Evoluciones | `V2+`: DB-P09 precios, DB-P12, tablas resumen §13, particionado §12 | cada una con decisión registrada (§26) |

Reglas por migración (skill + §11):
1. Compatibilidad hacia atrás (expand-contract): app vieja corre contra esquema nuevo.
2. `ALTER … ADD COLUMN` con DEFAULT o en dos pasos (nullable → backfill → NOT NULL).
3. Tablas grandes: cambios por lotes, ventana de bajo tráfico.
4. Nada destructivo: **sin** `DROP TABLE`/`TRUNCATE`/`DROP DATABASE` sin autorización explícita (workflow §17, AGENTS.md).
5. Backup/restaurable previo a cada F (skill: backups before schema changes).
6. Checksum Flyway: detecta drift de scripts ya aplicados.

## 2. Estrategia de rollback

| Tipo de migración | Rollback |
|---|---|
| Aditiva (CREATE TABLE/INDEX, ADD COLUMN) | **forward-fix**: no se revierte en prod; si falla, se deshabilita la feature. `down` opcional solo en dev |
| Backfill de datos | no reversible sin copia previa → migración de doble escritura (escribir viejo+nuevo durante ventana) |
| Cambio de tipo de columna | solo con tabla de transición + copia + swap en ventana (expand-contract) |
| V1.x inicial (esquema vacío) | destruir base de desarrollo y recrear; en prod: restaurar backup previo (no aplica: primera instalación) |
| Caso con pérdida de datos | **prohibido** sin procedimiento documentado de restauración (skill) |

Tabla-resumen de decisión (§11): *forward-fix como política*, rollback de datos solo vía PITR (binlog, §17).

## 3. Ventana y coordinación

- DDL con lock breve (INPLACE) → posible en horario normal; F1 completa solo en instalación nueva.
- Orden: aplicar migraciones → smoke de integridad (Paso 19) → desplegar app.
- Entornos (RNF-062): misma cadena en dev → pruebas → prod; nunca divergente.

## 4. Verificación post-migración (preparación Paso 18/19)

- Comparación esquema real vs modelo esperado (drift) con `database-documentation`.
- Conteos de constraint/índices esperados vs reales.
- `SHOW CREATE TABLE` de tablas críticas (`inventory_stock`, `rx_prescription_items`, `ctrl_balances`) guardados como golden.

## 5. Pendientes

- DB-P11: cierre formal de la herramienta (Flyway propuesto) — registrado al aprobar este documento.
- F4 depende de que RF-100 reciba valores aprobados (hoy `null`).

## 6. Salida

- → Paso 14: `15_reportes/revision_dba.md`.
