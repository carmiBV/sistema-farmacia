# .database-documentation - reporte de paridad

**Estado: VERIFIED**

## Metodo y tiering

| Artefacto | Tier | Fuente |
|---|---|---|
| `introspection.*`, `live_*`, `fk_table_rows.txt`, `uq_table_rows.txt`, `dictionary_tables.md`, `indexes_by_table.md` | T2 (verificado) | `information_schema` + `SHOW CREATE TABLE` leidos de servidor MySQL vivo |
| `model_*`, `er_edges.txt`, `mermaid_rel.txt`, `modelo_fisico.sql` | T3 (derivado) | `13_sql/V1.0.0`, `V1.1.0`, `V1.4.0` parseados |

Instancia live: MySQL 8.4.3 efimera en `127.0.0.1:33061`, BD `farmacia_doc`, creada aplicando V1.0.0 -> V1.1.0 -> V1.2.0 (placeholders sustituidos por valores de prueba efimeros) -> V1.3.0 -> V1.4.0. Es la misma tecnologia y mismos scripts que el registro historico de pruebas del Paso 19; no es una base de produccion ni un despliegue (sigue pendiente el Paso 16).

## Gate de conteos (42/84/37/27)

| Objeto | Live (T2) | Modelo (T3) | Match |
|---|---:|---:|---|
| Tablas | 42 | 42 | OK |
| FK | 84 | 84 | OK |
| CHECK | 37 | 37 | OK |
| UNIQUE | 27 | 27 | OK |
| PK | 42 | 42 | OK |
| Triggers | 2 | 2 (V1.4.0) | OK |
| Grupos de indice | 149 | *(V1.1.0 + auto)* | informativo |

## Diferencias de identidad (live vs modelo)

| Conjunto | Diferencias | Detalle |
|---|---:|---|
| Tablas | 0 |  |
| Nombres FK | 0 |  |
| Aristas columna->columna | 0 |  |
| Nombres CHECK | 0 |  |
| Nombres UNIQUE | 0 |  |

## Inventario de archivos (21)

| # | Archivo | Bytes |
|---:|---|---:|
| 1 | `introspection.txt` | 54502 |
| 2 | `introspection.tsv` | 17124 |
| 3 | `live_columns_full.tsv` | 24168 |
| 4 | `live_creates.tsv` | 39354 |
| 5 | `live_chk_names.txt` | 791 |
| 6 | `live_fk_edges.txt` | 4318 |
| 7 | `live_trig_names.txt` | 41 |
| 8 | `live_triggers.tsv` | 1001 |
| 9 | `model_chk.txt` | 3088 |
| 10 | `model_chk_names.txt` | 791 |
| 11 | `model_fk.txt` | 8302 |
| 12 | `model_fk_edges.txt` | 4318 |
| 13 | `model_tables.txt` | 956 |
| 14 | `model_uniq.txt` | 1845 |
| 15 | `dictionary_tables.md` | 77575 |
| 16 | `indexes_by_table.md` | 13026 |
| 17 | `er_edges.txt` | 3095 |
| 18 | `fk_table_rows.txt` | 7353 |
| 19 | `mermaid_rel.txt` | 5577 |
| 20 | `uq_table_rows.txt` | 1618 |
| 21 | `modelo_fisico.sql` | 49282 |

El generador `gen_doc.ps1` vive en esta carpeta y NO cuenta como artefacto.

## Reproduccion

1. Instancia efimera: `mysqld --initialize-insecure --datadir=<tmp>`; `mysqld --port=33061 --bind-address=127.0.0.1`.
2. `CREATE DATABASE farmacia_doc ...` y aplicar `13_sql/V1.0.0..V1.4.0` (V1.2.0 con placeholders sustituidos).
3. Correr `gen_doc.ps1` (incluido en esta misma carpeta): reintrospeccion + parseo de `13_sql` + paridad.
4. Apagar `mysqladmin shutdown` y borrar el datadir efimero.
