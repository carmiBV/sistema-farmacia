# ADR de Cierre — Acta de Entrega (Workflow 06, CP-INT-08)

**Fecha:** 2026-10-07 · **Estado:** PENDIENTE DE APROBACIÓN FINAL DE ENTREGA
**Alcance certificado:** sistema de gestión de farmacia — PHP 8.x puro + PDO MySQL 8.x + frontend PHP/HTML5/CSS3/JS vanilla (stack oficial, sin frameworks SPA).

---

## 1. Resultado de la certificación E2E

| Área | Evidencia |
|---|---|
| Suite completa Grupo 1 (piloto + backend + integración) | **23/23 PASS** (`verify_cp02..08`, `verify_cp_back02..back10`, `verify_int01..07`) |
| Suite completa Grupo 2 (frontend + harnesses lógicos) | **22/22 PASS** (`verify_cp_front01..11` + 10 harnesses node) |
| Esquema de BD | **42/42 tablas oficiales** coincidentes con `V1.0.0__esquema_base.sql` |
| Contrato API | `docs/openapi.yaml` **81 rutas / 117 operaciones**, refs íntegras (`validate_openapi.py` PASS) |
| Accesibilidad estructural (WCAG 2.2 AA) | 21 pantallas PASS (`verify_cp_front11`) |
| Rendimiento (línea base local, 20 req/endpoint) | p95 31–53 ms, umbral 1000 ms (`verify_int08`) |
| Borrado lógico / append-only | Verificado (`verify_cp_back11`, checks de BD en los E2E) |

## 2. Cobertura funcional entregada

- **Autenticación/RBAC:** JWT HS256 Bearer, blacklist revocable verificada, throttle de login, permisos por endpoint (CP-INT-01).
- **Operaciones:** sucursales, cajas, turnos, parámetros del sistema (`system_config`).
- **Catálogo:** categorías jerárquicas (`parent_id_key`), productos, precios/promociones (append-only con cierre de vigencia), proveedores, pacientes (con anonimización), prescriptores.
- **Compras:** órdenes (borrador→emitida→recibida/cancelada), recepciones con lotes 1:1 (`reception_item_id NOT NULL`), idempotencia (RN-09).
- **Inventario FEFO:** stock en tiempo real, kárdex append-only, transferencias inter-sucursal (RN-02/08), alertas `stock_minimo`/`vencimiento` con umbrales y dedup, incidentes con doble autorización (RF-046).
- **Recetas y controlados:** dispensación con CAS de saldo, libro oficial con asiento automático por movimiento (RF-055) y conciliación (RNF-024).
- **POS:** venta con descuento FEFO, múltiples medios de pago, comprobante, devoluciones (`vendible`/`no_vendible`), anulación con movimientos compensatorios (RN-10).
- **Auditoría/PII:** `audit_operations` (RF-090), `audit_pii_access` (RF-091), redacción RNF-050, outbox (decisión 16), SQLi/XSS/CSRF verificados (CP-INT-07).
- **Frontend:** 21 pantallas sobre layout compartido (CP-FRONT-01..11).

## 3. Supuestos técnicos vigentes (a validar por el cliente)

1. **Sesión frontend:** token en `localStorage` (no cookie httpOnly). Riesgo XSS residual; migración a cookie httpOnly + token anti-CSRF pendiente de decisión.
2. **Dispensación de recetas sin descuento de stock** (Opción A, CP-BACK-07): el vínculo dispensación→kárdex (Opción B) no está implementado.
3. **RF-054 / RNF-030 — DECIDIDO (2026-10-07):** NO se exige verificación del químico ni MFA en esta fase (la doble autorización de ajustes y el libro oficial cubren el control; JWT + RBAC + throttle vigentes). MFA/reautenticación queda como mejora futura.
4. **RF-100 — DECIDIDO (2026-10-07):** las ventas NO transicionan a estado `devuelta`; las devoluciones se registran como movimientos y reingresos (comportamiento actual, se confirma).
5. **`EXCEEDS_PENDING` responde 400** (no 409): semántica HTTP de los errores de compras por unificar si el cliente lo exige.
6. **Política FEFO** aplicada por el POS (elección del lote); el backend valida stock/estado/vencimiento pero no fuerza el orden FEFO al recibir `lot_id`.
7. **Paginación:** listados con `limit=100` sin controles de página en UI; paginación explícita pendiente.
8. **Comprobante — DECIDIDO (2026-10-07):** ticket simple imprimible SIN valor fiscal (lo implementado); facturación electrónica queda fuera de alcance.
9. **SLA — DECIDIDO (2026-10-07):** p95 < 500 ms por petición con 50 usuarios concurrentes por sucursal. **VERIFICADO (2026-10-08)** en stack multi-worker (Apache 2.4.62 + mod_php 8.3, mpm_winnt): p95 135–157 ms con 50 concurrencias (`verify_int08` en modo duro, 10/10). Nota de despliegue: bajo Apache/FastCGI la cabecera `Authorization` debe re-inyectarse (`SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1` con FallbackResource), o los endpoints autenticados responden 401 con token válido.
10. **Claim de auditoría:** `entidad` guarda la ruta y `entidad_id` solo se llena en rutas con `{id}` (creaciones quedan con `entidad_id` NULL).
11. **Sesión de BD local** desarrollada como `root` sin contraseña (solo entorno dev).

## 4. Checklist de pendientes para la entrega

| # | Pendiente | Responsable | Estado |
|---|---|---|---|
| 1 | ~~Commit del `git rm --cached` + rotación de credenciales + purga del historial~~ **HECHO (2026-10-08):** commit `e67b542`; `JWT_SECRET` y `DEMO_USER_PASSWORD` regenerados; `.env` y `tools/.env` eliminados de TODO el historial (filter-branch) y **force-push autorizado por el cliente** al remoto `carmiBV/sistema-farmacia` (main en `397ef44`); solo quedan `.env.example` | Cliente / security-reviewer | **Completado** |
| 2 | ~~Aplicar `V1.2.0__grants.sql`~~ **HECHO (2026-10-08):** usuarios `app_rw`/`app_ro`/`migrator`/`svc_clinico` creados y la app corre con `app_rw` (mínimo privilegio, flujos verificados). **Reconciliación F12 (pendiente de aprobación):** DELETE sobre `catalog_product_categories`, `token_blacklist`, `auth_user_roles`, `purchase_order_items` (patrones ya aprobados en implementación) | Cliente (F12) / devops | Aplicado con F12 pendiente |
| 3 | ~~DROP de la tabla legacy `usuarios`~~ **HECHO de hecho (2026-10-08):** la tabla ya no existe; esquema en **42/42 exactas** | — | Completado |
| 4 | ~~Decisiones RF-054 / RNF-030 / RF-100~~ **RESUELTO (2026-10-07):** sin verificación de químico ni MFA en esta fase; ventas sin estado `devuelta` | Cliente | Completado |
| 5 | **SLA DECIDIDO (2026-10-07):** p95 < 500 ms / 50 usuarios concurrentes · **Comprobante DECIDIDO:** ticket simple sin valor fiscal. **VERIFICADO (2026-10-08)** en stack multi-worker (Apache 2.4.62 + mod_php 8.3, mpm_winnt): p95 135-157 ms con 50 concurrencias (verify_int08 modo duro 10/10). Nota de despliegue: con FallbackResource/rewrite re-inyectar Authorization (SetEnvIf) o los endpoints autenticados responden 401 | Cliente / devops | **Completado** |
| 6 | Auditoría a11y final (axe / lector de pantalla, `aria-label` en controles dinámicos) y prueba táctil en POS físico | Cliente / QA | Abierto |
| 7 | Unificación HTTP de errores del módulo de compras (400 vs 409) | Cliente | Abierto |
| 8 | Endpoints fuera del contrato piloto: **RESUELTO** — `docs/openapi.yaml` ahora cubre las 117 operaciones | — | Completado |

## 5. Riesgos conocidos

- **Credenciales históricas en git** (hasta que se ejecute el commit del punto 1 y la rotación): riesgo de fuga; mitigación ya iniciada (archivos fuera del índice).
- **Permisos de BD sin aplicar** (V1.2.0): usuarios operativos de la app podrían mutar tablas append-only a nivel SQL (RNF-046 depende de grants).
- **Mocks de harness** aceptaban métodos HTTP arbitrarios (detectado en CP-INT-02): lección incorporada — los contratos se verifican siempre contra el backend real en los E2E.

## 6. Trazabilidad

Requisitos cubiertos: RF-020/031/032/045/046/050/055/060/071/090/091/100 (parcial), RN-01/02/04/05/06/07/08/09/10/13, RNF-021/023/024/034/046/050, CA-02/03/06/09/10. Estados de control:
`.agents/state/{database,api-pilot,backend,frontend,integration}-workflow.json` (evidencia por checkpoint).
