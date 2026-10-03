# Paso 09 — Seguridad de base de datos

- **Workflow:** 02_database_workflow · **Paso:** 09
- **Skill utilizado:** `databases` (MySQL). `sqlserver-security` no aplica (motor MySQL); `postgresql-table-design`/RLS no aplica (ver §6).
- **Fuentes:** decisiones §14, §15; `03_resultados/05_security_review.md`; RNF-030..039, RNF-034.

## 1. Usuarios y mínimo privilegio (§14 pendiente de diseño físico → resuelto aquí)

| Usuario de BD | Permisos | Uso |
|---|---|---|
| `app_rw` | SELECT/INSERT/UPDATE sobre tablas de negocio; **sin** DELETE en transaccionales/auditoría; sin DDL | backend |
| `app_ro` | SELECT sobre vistas de lectura (reportes) | reportes |
| `migrator` | DDL (CREATE/ALTER/INDEX) — solo en ventana de migración | Flyway/etapa CI |
| `dba_admin` | administración; credencial fuera de `.env` de app | operación |

- Principio: mínimo privilegio (RNF-031) y separación exigida por §14.
- Grants **no** publicados en este documento como credenciales: el SQL de grants va al Paso 15 (tras APPROVED).
- MFA: RNF-030 es de aplicación/identidad; para acceso DBA se recomienda salt o acceso restringido a red (pendiente DevOps, `06_infrastructure.md`).

## 2. Transporte y secretos

| Control | Detalle | Fuente |
|---|---|---|
| TLS obligatorio | `require_secure_transport = ON`; validar certificado del servidor en producción | §14, RNF-032 |
| Secretos fuera del repo | credenciales solo en `.env` (dev) / Docker Secrets (prod); jamés en artefactos, scripts ni reportes | RNF-034, §14, AGENTS.md |
| Authentication | `caching_sha2_password` (MySQL 8) | skill |
| Sin auth débil | prohibido `mysql_native_password` en prod si hay alternativa; sin usuarios anónimos; sin `root` de la app | skill |

## 3. Contraseñas, tokens, datos sensibles (§15)

- Contraseñas de usuario: **Argon2id** en `auth_users.password_hash` (aplicación; nunca texto plano, nunca hash reverso) — RNF-033.
- JWT revocados → `token_blacklist` con **hash** del token + `expires_at`; refresh tokens con TTL (§15). Nada de tokens en claro en columnas de negocio.
- `password_hash` solo visible para el rol de autenticación (no para `app_ro` de reportes): grants por columna no existen en MySQL → se exige que los reportes no consulten `auth_users` (control por rol de BD: `app_ro` sin SELECT a `auth_users`).

## 4. Privacidad (RNF-038, RNF-039, RN-13)

| Control | Implementación | Fuente |
|---|---|---|
| Acceso por rol | `app_rw` no lee PII: grant SELECT de `catalog_patients`, `catalog_prescribers`, `rx_prescriptions` solo a rol `svc_clinico`; rol de reportes (`app_ro`) **no** incluye esas tablas | RN-13, RNF-038 |
| Registro de cada acceso PII | `audit_pii_access` INSERT obligatorio por acceso (RF-091); trigger de BD como red de seguridad sobre SELECT es **inviable en MySQL** (no hay SELECT triggers) → control en capa de servicio + revisión de código + pruebas Paso 19 | RF-091 |
| Minimización | reportes con enmascaramiento (paciente → ID o iniciales) cuando no se necesita nombre | RNF-038 |
| Retención/anonimización | parámetros en `system_config` con valores `null` hasta definir (RNF-039); anonimización UPDATE sobre fila maestra permitida solo a `dba_admin`/servicio de retención; nunca afecta movimientos/lotes/libro (RN-10) | RNF-039, RN-10 |
| Logs sin PII | logs de aplicación no incluyen nombre/documento de paciente (RNF-050); `audit_operations.valores_*` no copia PII completa: guarda `paciente_id`, no el contenido (ya definido Paso 04 §9) | RNF-050 |

**Observación RLS:** en MySQL no existe RLS; la autorización fina vive en la aplicación (validado en `05_security_review`). Si la normativa por validar (restricciones §7) exige control en base, §26 de la decisión reabre la selección del motor (PostgreSQL — Paso 06 §3).

## 5. Separación de funciones (RNF-031)

- `app_rw` no tiene DDL ni GRANT.
- `migrator` no tiene SELECT a PII (solo DDL).
- Ajustes de controlados: proponente/autorizador son **usuarios de negocio** distintos (CHECK + regla de dominio, Paso 08) — quien dispensa no autoriza sus propios ajustes (RNF-031).

## 6. Auditoría de plataforma vs aplicación (§9)

- La auditoría de negocio es por aplicación/tablas (`audit_*`) — decisión §9.
- binlog ROW habilitado (§17) añade trazabilidad operativa de escritura.
- Plugin de auditoría comercial de MySQL: **no se adopta** (no es requisito; §20.1: no habilitar por disponibilidad). Revisar si `06_infrastructure.md` exige auditoría de plataforma — si la exige, plugin/enterprise o binlog + SIEM (pendiente DevOps).

## 7. cifrado en reposo (RNF-036)

- No es configurable desde el esquema: depende del volumen/plataforma (Docker/host o cifrado de disco). Documentado como **responsabilidad de infraestructura** en `06_infrastructure.md`; los artefactos de este workflow no afirman cumplimiento.
- Backups con la misma protección (RNF-036).

## 8. Conexiones y pooling (§19)

- Pool 50–100 por instancia de app; `max_connections` del servidor ≥ suma de pools + DBA/migrador, con margen.
- Timeouts de conexión y `wait_timeout` cortos para sesiones ociosas.
- Sin credenciales en cadena de conexión de reportes: usuario `app_ro` con contraseña rotable (proceso fuera de repo).

## 9. Hallazgos pendientes de otros artefactos (no duplicar)

- Rate limiting, validación de entradas, XSS/CSRF: son de API (`05_security_review.md`, RNF-035) — fuera del alcance de este documento.
- Verificación del químico (método): `null` (RNF-030) — el esquema solo prevé `quimico_verificador_id`.

## 10. Salida

- → Paso 10: `08_auditoria/auditoria.md` + `09_versionamiento/versionamiento.md`.
