# Paso 10b — Versionamiento del esquema

- **Workflow:** 02_database_workflow · **Paso:** 10 (versionamiento)
- **Skills utilizados:** `database-schema-designer` + `databases`
- **Decisión §11:** todos los cambios estructurales versionados con migraciones; herramienta pendiente (DB-P11) a seleccionar en esta fase.

## 1. Selección de herramienta (propuesta para cerrar DB-P11)

| Alternativa | A favor | En contra |
|---|---|---|
| **Flyway** (elegida) | SQL-first simple, corre como contenedor/CLI sin stack de aplicación, versionado por nombre `V1__…`, checksum de integridad, amplio uso con MySQL | rollback manual (solo forward-fix) |
| Liquibase | changelog XML/YAML, rollback nativo declarativo | más complejidad, formato propio |
| Migraciones nativas del stack | sin dependencia nueva | depende del stack de app (RF: aún no elegido); no ejecutable desde BD/CI aislada |

**Decisión propuesta:** **Flyway** — coherente con que el lenguaje/framework aún no está elegido (restricciones §1) y con contenerización (RNF-061): la imagen de Flyway corre en CI/CD.
**Estado:** propuesta; cierre formal de DB-P11 al aprobar este documento (§26 requiere registro: decisión anterior `PENDIENTE` → nueva decisión con fecha/fuente).

## 2. Reglas de migración (§11 + skill `databases`)

1. Nombres: `V{major}.{minor}__descripcion.sql` (Flyway); secuencia estricta.
2. **Nunca** DDL manual en producción sin migración (§11).
3. Compatibilidad hacia atrás (expand-contract): columna nueva → backfill → NOT NULL → contracción en versión posterior (skill: `NOT NULL` con DEFAULT o en dos pasos).
4. Cambios de gran volumen: por lotes, fuera de ventana de tráfico; índice nuevo en MySQL con `ALGORITHM=INPLACE, LOCK=NONE` cuando aplique.
5. Rollback: preferir *forward-fix* (§11); `down` solo cuando no pierda datos; si la reversión pierde datos → procedimiento documentado de restauración (backup previo obligatorio: skill "take restorable backups before schema changes").
6. Toda migración debe pasar primero por pruebas de integridad (Paso 19).
7. El runner registra versiones aplicadas; detección de drift: comparar esquema real vs modelo esperado (Paso 18, `database-documentation`).
8. Usuarios: ejecuta `migrator` (Paso 09), no `app_rw`.

## 3. Plan de versiones iniciales (referencia; SQL en Paso 15)

| Versión | Contenido |
|---|---|
| V1.0.0 | esquema base: dominios auth/ops/catalog/purchase/inventory/sales/rx/ctrl/audit/sys (Paso 04) + índices del Paso 11 + grants (Paso 09) |
| V1.x | parámetros iniciales de `system_config` **solo con valores aprobados** (los `null` no se filan) |
| V2+ | DB-P09 (precios), DB-P12 (estrategia por entidad), particionado (§12), tablas resumen (§13), cambios por nuevos RF |

## 4. Entornos (RNF-062)

- Mismos scripts para dev/CI/prod; variación solo en credenciales/`DB_NAME` desde entorno (§14).
- `dev`, `pruebas`, `producción` separados por instancia/base; el `migrador` aplica la misma cadena.

## 5. Pendientes explícitos

- DB-P11 queda **propuesta de cierre** con este documento (Flyway). Si el responsable prefiere Liquibase/stack nativo, registrar con §26.
- Rollback automatizado de datos: fuera de alcance inicial (§11 admite forward-fix).

## 6. Salida

- → Paso 11: `10_indices_rendimiento/indices_rendimiento.md`.
