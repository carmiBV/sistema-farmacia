# Paso 10a — Auditoría e histórico

- **Workflow:** 02_database_workflow · **Paso:** 10 (auditoría)
- **Skills utilizados:** `database-schema-designer` + `databases`
- **Regla de distinción (workflow):** auditoría de cambios ≠ histórico temporal ≠ versionamiento de esquema (éste: `09_versionamiento/versionamiento.md`).

## 1. Auditoría de cambios (¿quién cambió qué y cuándo?)

| Capa | Mecanismo | Fuente |
|---|---|---|
| Operaciones críticas | `audit_operations`: usuario, acción, entidad, `valores_antes/valores_despues` (JSON), motivo, fecha — **solo INSERT** | RF-090, §9, RNF-046 |
| Accesos a PII | `audit_pii_access`: una fila por consulta/modificación de pacientes/recetas | RF-091, RN-13 |
| Dispositivo de red de seguridad | triggers `AFTER INSERT` en `inventory_movements` y `ctrl_ledger_entries` que verifican coherencia (ej.: cantidad con signo, asiento presente para controlados) y **fallan la transacción** si la app lo omitió | §9 (triggers "cuando corresponda"), RF-045, RF-055 |
| Registro de configuración | `system_config.updated_at/updated_by` + fila en `audit_operations` al modificar claves RF-100 | RF-100 |
| Binlog ROW | trazabilidad operativa de escritura; habilita PITR | §9, §17 |
| Grants | sin UPDATE/DELETE en `audit_*`, `inventory_movements`, `ctrl_ledger_entries` (roles §09-seguridad) | RNF-046 |
| Correcciones | nunca UPDATE sobre lo auditado: movimientos compensatorios referenciados (`movimiento_ref_id`) | RN-10, RNF-023 |

**No usar:** triggers `BEFORE` en MySQL para lógica de auditoría compleja (solo disponibilidad LIMITADA); se usan `AFTER INSERT` donde aplica. El registro "quién cambió X" de tablas maestras se hace desde la aplicación en la misma transacción (RF-090).

## 2. Histórico temporal (¿cómo evolucionó el estado?)

| Dato | Dónde vive | Retención |
|---|---|---|
| Stock por lote y sucursal | `inventory_movements` (log) + `inventory_stock` (saldo) | sin purga (RNF-023/RNF-045); RNF-039 `null` → parámetro futuro |
| Libro de controlados | `ctrl_ledger_entries` + `ctrl_balances` | plazo legal **por validar** (perfil_carga `libro_controlados: null`) |
| Ventas, devoluciones, pagos | tablas transaccionales | idem (`dispensaciones`, `recetas`: `null`) |
| Discrepancias | `inventory_incidents` | sin purga |
| Entidades maestras | estado/`anonimizado` (no histórico de versiones) | DB-P12 pendiente: **no** se crea tabla de historial genérico (YAGNI hasta decisión) |
| Auditoría PII | `audit_pii_access` | RNF-039 (`auditoria: null`) |

**Nota de tensión (CAM-002-g):** al anonimizar, se conservan movimientos/lotes/cantidades/libro (RN-10, RNF-039 modificado) — el modelo lo soporta porque la anonimización solo UPDATE `catalog_patients/prescribers`.

## 3. Triggers propuestos (diseño; se crean en Paso 15)

| Trigger | Momento | Objetivo |
|---|---|---|
| `trg_movements_sign` | AFTER INSERT `inventory_movements` | rechazar cantidad 0 / signo incoherente (defensa RN-02/CA-03) |
| `trg_ledger_vs_stock` | AFTER INSERT `ctrl_ledger_entries` con `tipo='salida'` | verificar que existe movimiento de inventario correspondiente en la misma transacción (RF-055) |
| `trg_no_update_delete_audit` | no existe en MySQL (no hay triggers de UPDATE/DELETE que blockeen por rol) | sustituido por grants |

Si en Paso 14 un trigger se juzga innecesario por duplicar CHECK, se elimina de este documento (degradación de diseño, no de requisito).

## 4. Correlación con observabilidad

- `audit_operations` y outbox comparten `created_at` + IDs para correlación (RNF-052).
- Alertas de acceso anómalo (RNF-053): consumo de `audit_pii_access` por parte de aplicación/monitor, fuera del alcance de BD.

## 5. Salida

- → `09_versionamiento/versionamiento.md` (misma etapa Paso 10).
