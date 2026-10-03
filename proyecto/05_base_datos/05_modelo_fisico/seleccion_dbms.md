# Paso 06 — Selección del DBMS

- **Workflow:** 02_database_workflow · **Paso:** 06
- **Skill utilizado:** `databases`
- **Criterio:** CA-04 (justificar sin sesgo), CA-02 (≥2 alternativas), restricciones (`00_contexto/restricciones.md`), RNF-060..063, decisiones §2 (APROBADO) y §26 (criterio de modificación).

## 1. Restricciones de entrada

1. Linux (RNF-060), contenerizable (RNF-061), mantenimiento activo y documentación (RNF-063).
2. Sin presupuesto definido (restricciones; `perfil_carga` no lo fija) → el riesgo de licenciamiento queda **sin cuantificar** (supuesto explícito §5).
3. Decisiones previas `APROBADO`: persistencia relacional (§1), **MySQL 8.x/InnoDB** (§2), una sola base (§3). Modificación solo por §26 (nuevo RF/RNF, evidencia, medición…).
4. Criterios funcionales de la base: ACID (§1), FK, bloqueo por fila, restricciones únicas y CHECK (§7, RNF-025), idempotencia con UNIQUE (§6), auditoría con triggers + log (§9), particionado por volumen (§12), reportes con "vistas materializadas" (§13), PITR con binlog (§17), sin HA inicial (§18), TLS y usuarios separados (§14).

## 2. Alternativas comparadas (PostgreSQL · MySQL/MariaDB · SQL Server)

| # | Criterio (fuente) | PostgreSQL 16+ | MySQL 8.x/InnoDB | SQL Server (Linux) |
|---|---|---|---|---|
| C1 | ACID, FK, unicidad (§1) | ✅ | ✅ | ✅ |
| C2 | Bloqueo por fila + `SELECT FOR UPDATE` (§4, RNF-020) | ✅ | ✅ | ✅ (hint `UPDLOCK`) |
| C3 | `SKIP LOCKED` (§4) | ✅ | ✅ 8.0+ | ✅ (`READPAST`) |
| C4 | CHECK realmente enforced (§7, RNF-025) | ✅ | ✅ desde 8.0.16 (exigir ≥8.0.16) | ✅ |
| C5 | MVCC + transacciones cortas (§5) | ✅ | ✅ InnoDB | ✅ (READ COMMITTED default) |
| C6 | Particionado por rango de fecha (§12) | ✅ declarativo | ✅ (PK/UK debe incluir columna de partición → condiciona claves únicas de tablas grandes; ver Paso 07) | ✅ (partitioning) |
| C7 | PITR backup (§17) | ✅ WAL + base | ✅ binlog + base (decisión §17) | ✅ t-log + full |
| C8 | Triggers para auditoría (§9) | ✅ | ✅ (限制: triggers BEFORE solo en MySQL para UPDATE/INSERT → usar AFTER o lógica en app; documentado) | ✅ |
| C9 | Vistas materializadas (§13) | ✅ nativas | ⚠️ tablas resumen + refresh programado (equivalente aceptado en §13) | ⚠️ indexed views (limitadas a lectura) |
| C10 | Auditoría de plataforma | ✅ pgAudit (open source) | ⚠️ plugin comercial; compensada por app-level + binlog (§9) | ⚠️ SQL Server Audit |
| C11 | RLS para PII (RNF-038, defensa en profundidad) | ✅ nativa | ❌ (autorización en app — validada en `05_security_review`) | ✅ nativa |
| C12 | JSON (§20) | ✅ JSONB | ✅ JSON | ✅ JSON |
| C13 | Linux + contenedores (RNF-060/061) | ✅ | ✅ | ✅ (2017+) |
| C14 | Mantenimiento activo (RNF-063) | ✅ | ✅ | ✅ |
| C15 | Licenciamiento con presupuesto sin definir (restricciones) | ✅ libre | ✅ libre (Oracle MySQL Open Source) | ⚠️ comercial por core; coste no cuantificable hoy |
| C16 | Equipo/experiencia | no definido | no definido | no definido |
| C17 | Coherencia con decisión §2 y §25.6 | — | ✅ APROBADO | contradice sin §26 |

**Ranking por requisitos (no por preferencia):**

1. **MySQL 8.x/InnoDB** — cumple C1–C8, C12–C14, C17; carencias C9/C10/C11 tienen mecanismo compensatorio ya aprobado en las decisiones (§13 tablas resumen, §9 auditoría en app, `05_security_review` autoriza autorización en aplicación).
2. **PostgreSQL** — empata en todo y supera en C9/C10/C11; es la alternativa de referencia si alguna de esas capacidades pasa a requisito.
3. **SQL Server** — equivalente en C1–C8, C12–C14; desventaja en C15 y sin ventaja funcional sobre las otras dos para este conjunto de RF/RNF.

## 3. Decisión

**Se confirma MySQL 8.x + InnoDB como motor** (decisión §2, APROBADO), re-justificada por requisitos:

- Cobertura de C1–C8 que es exactamente lo que exigen RF-040..RF-061, RNF-020..RNF-025 y las decisiones §4–§7.
- Linux/contenedor/mantenimiento (RNF-060/061/063) cumplidos.
- PITR por binlog ya comprometido en §17.
- Carencias conocidas (vistas materializadas, RLS, pgAudit) con compensación aprobada; no son requisitos hoy.

**Condiciones obligatorias del motor:**

1. Versión mínima **MySQL 8.0.16** (CHECK enforced) — mejor: 8.0/8.4 LTS; MariaDB no se adopta (§2 dice MySQL; equivalencia de drop-in requiere re-aprobación §26).
2. `SQL_MODE` estricto (`STRICT_ALL_TABLES`, `NO_ENGINE_SUBSTITUTION`), InnoDB, `innodb_print_all_deadlocks`, binlog en modo `ROW`, tz UTC.
3. Todo lo que dependa de C9/C10/C11 se diseña con el mecanismo compensatorio, no con supuestos del motor.

**Re-evaluación obligatoria (§26) si:**
- surge un RF/RNF de RLS/privacidad en base (C11);
- los reportes exigen vistas materializadas nativas y las tablas resumen no alcanzan la latencia (DB-P07);
- medición de rendimiento (CA-05/CA-06) muestra que InnoDB no sostiene 25.000 txn/h con los índices del Paso 11;
- se define presupuesto que penalice licenciamiento o que permita SQL Server.

## 4. Supuestos explícitos

| # | Supuesto | Validar con |
|---|---|---|
| S-1 | Sin presupuesto definido → motores de licencia libre primero | responsable del proyecto |
| S-2 | Tablas resumen sustituyen a vistas materializadas sin efecto en RF-070/071 | DB-P07 |
| S-3 | Autorización PII en aplicación es suficiente (RLS no exigida) | `05_security_review` ya validó; revalidar si cambia normativa (restricciones §7) |
| S-4 | MySQL 8.x en contenedor es aceptable operativamente (§17/§18) | fase DevOps |

## 5. Impacto en el workflow

- Motor: `mysql`.
- Skills de los Pasos 07–14: `databases` (workflow §MySQL) + `database-schema-designer`.
- Pendiente DB-P11 (herramienta de migraciones) se propone en Paso 09/13, sin cerrar definitivamente aquí.

## 6. Salida

- → Paso 07: `05_modelo_fisico/modelo_fisico.md`.
