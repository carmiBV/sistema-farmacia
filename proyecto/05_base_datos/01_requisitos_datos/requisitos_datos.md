# Paso 01 — Lectura y trazabilidad de requisitos de datos

- **Workflow:** 02_database_workflow · **Paso:** 01
- **Skill utilizado:** `database-schema-designer`
- **Fuentes leídas:** `00_contexto/` (alcance, descripcion, reglas_negocio, restricciones, registro_cambios), `01_requisitos/` (RF, RNF, criterios_aceptacion), `02_configuracion/perfil_carga.yaml`, `03_resultados/` (01_requirements_analysis, 04_database_analysis, 05_security_review, 06_infrastructure, 08_final_recommendation, adr/ADR-001), `04_decisiones/decisiones_dase_Datos.md`
- **SQL:** ninguno (prohibido hasta Paso 15 con `STATUS: APPROVED`).

## 1. Fuente de verdad y reglas aplicadas

- RF/RNF/RN y configuración son fuente de verdad; nada se altera en este paso.
- Decisiones `APROBADO` de `04_decisiones/decisiones_dase_Datos.md` se respetan (§25.6); las `PENDIENTE` se mantienen explícitas (§25.7); no se asumen respuestas inexistentes (§25.8).
- Regla de integridad transversal (§7): toda restricción futura debe trazarse a RF, RNF, RN o decisión.

## 2. Pendientes que NO deben inventarse

| Dato | Fuente | Valor | Efecto en diseño de datos |
|---|---|---|---|
| `rpo_minutos`, `rto_minutos` | RNF-041, perfil_carga | `null` (decisión §17 da RPO<1h/RTO<4h **provisionales**, no SLA) | No dimensionar réplicas/PITR con valor definitivo; registrar como supuesto en ADR |
| Retención: dispensaciones, recetas, libro, auditoría | RNF-039, perfil_carga | `null` | Parámetros en `system_config`; sin particionado por retención aún |
| `consultas_por_hora_estimadas` | RNF-003 | `null` | Índices de trazabilidad dimensionados por patrón, no por volumen exacto |
| Latencia trazabilidad (por lote/paciente) | RNF-048 | `null` | Requiere índices dedicados (CA-10); umbral pendiente |
| Latencias p95 objetivo | RNF-006 | `null` (P-05) | No fijar SLA de consultas |
| Modo degradado (`habilitado` y permitidos) | RNF-043 | `null`; controlados bloqueados propuestos | `DB-P06` (POS offline): **sin** tablas de cola offline hasta confirmar |
| Método de verificación del químico | RNF-030 | `null` | Columna `quimico_verificador_id` nullable; mecanismo sin definir |
| Política de devoluciones (valores) | RF-100 | `null` | Parámetros en `system_config`, no CHECKs con valores fijos |
| `DB-P01…DB-P12` | decisión §23 | abiertas | Reservas, precios, migraciones, eliminación por entidad, etc. |

## 3. Discrepancias detectadas (sin alterar RF/RNF — para registro del responsable)

| # | Hallazgo | Evidencia | Efecto | Acción recomendada |
|---|---|---|---|---|
| D-1 | `decisiones_dase_Datos.md` cita RF inexistentes: RF-062, RF-063, RF-064, RF-080 (§1, §24) | RF.md no los define (saltos: RF-061→RF-070→RF-090) | Trazabilidad rota en §24 | Registrar CAM corrigiendo citas o confirmar numeración antigua con el responsable |
| D-2 | §24 cita `RN-08` para "no eliminar confirmados"; RN-08 es transferencias, la regla es RN-10 | reglas_negocio.md | Cita errónea | Corrección de cita en decisiones (§26) |
| D-3 | §24 cita `RF-070` para auditoría y "Backup + WAL"; auditoría = RF-090/091, WAL es terminología PostgreSQL (MySQL: binlog, §17) | RF.md, decisión §17 | Terminología/cita | Corregir cita; usar binlog en diseño MySQL |
| D-4 | Decisiones mencionan canal web, `orders.channel`, reservas web, "POS y web"; alcance excluye ventas web (CAM-001-e dejó `clientes_web_concurrentes: 0`) | alcance.md, perfil_carga | Candidatos §8 ampliados | Modelar `channel` con default `pos` y reservas como capacidad; **no** modelar fulfillment web (DB-P10) |
| D-5 | §0 cita artefactos inexistentes: `05_security_analysis.md`, `06_devops_analysis.md`, `08_recomendacion_final.md` (los reales: `05_security_review.md`, `06_infrastructure.md`, `08_final_recommendation.md`) | `03_resultados/` | Referencias rotas | Corregir en decisiones (§26) |

> Ninguna discrepancia modifica RF/RNF/RN. No se corrigen archivos de requisitos en este paso.

## 4. RF → requisitos de datos

| RF | Datos requeridos | Entidades candidatas | Restricciones/estado derivados | RN/CA/RNF |
|---|---|---|---|---|
| RF-001 | usuarios, roles, permisos | auth_users, auth_roles, auth_permissions, auth_user_roles, auth_role_permissions | username UNIQUE; hash no texto plano (Argon2id, §15) | RNF-030..033 |
| RF-002 | pacientes/clientes (PII) | catalog_patients | acceso por rol; registro de consulta | RN-13, RNF-038, RF-091 |
| RF-003 | prescriptores (PII) | catalog_prescribers | registro de consulta; anonimizable sin borrar recetas | RN-13, RNF-039 |
| RF-010 | sucursales, cajas/POS | ops_stores, ops_registers | UNIQUE(store, código); FK desde ventas/movimientos | RNF-010 |
| RF-020 | medicamentos: principio activo, presentación, concentración, condición de venta, categorías, precios, promociones | catalog_products, catalog_categories, catalog_product_categories, catalog_prices, catalog_promotions | `condicion_venta IN ('libre','receta','controlado')`; precios: alcance global/por sucursal **pendiente DB-P09** | RN-04, RN-05 |
| RF-030 | proveedores, órdenes de compra | catalog_suppliers, purchase_orders, purchase_order_items | número de orden UNIQUE | §6 |
| RF-031 | recepción parcial/total exige lote, vencimiento, cantidad | purchase_receptions, purchase_reception_items | lote/vencimiento/cantidad NOT NULL | RN-07, RNF-025 |
| RF-032 | cuarentena del lote + liberación con usuario, fecha, motivo | inventory_lots (`estado`, `liberado_por`, `liberado_at`, `motivo_liberacion`) | liberación solo con los tres campos | RN-07, CAM-002-b |
| RF-040 | inventario por producto, lote y sucursal | inventory_stock (PK store+lot), inventory_lots | granularidad lote-sucursal; `stock_available >= 0` | RN-01, RN-02, RNF-020 |
| RF-041 | entradas, salidas, ajustes, bajas, transferencias | inventory_movements (append-only), inventory_transfers | tipos CHECK; sin DELETE | RN-06, RN-10, RNF-023 |
| RF-042 | no doble descuento ante reintentos | idempotency_keys + UNIQUE (order_number, payment_reference) | clave única por operación | RN-09, RNF-022, CA-07 |
| RF-043 | FEFO; bloqueo vencidos/cuarentena/retirados | inventory_lots (fecha_vencimiento, estado); índice FEFO por producto/sucursal | validación servidor; no ejecutable como CHECK puro (lógica temporal) | RN-03, CA-03 |
| RF-044 | alertas stock mínimo y vencimiento | inventory_alerts + umbrales en system_config | parámetros RF-100 | RNF-053 |
| RF-045 | transferencias con estados; libro origen y destino en controlados | inventory_transfers, inventory_transfer_items, ctrl_ledger_entries | estados CHECK: solicitada/despachada/recibida/cerrada/rechazada | RN-08, RF-055, CAM-002-c |
| RF-046 | doble autorización de ajustes de controlados | inventory_movements + ctrl_ledger_entries con `proponente_id`, `autorizador_id` | CHECK autorizador ≠ proponente; NOT NULL en ajuste de controlado | RN-06, RNF-031, CA-07 |
| RF-047 | incidente de discrepancia + ajuste autorizado; sin stock negativo silencioso | inventory_incidents + movimiento de ajuste vinculado | `stock_available >= 0` en BD | RN-02, RNF-020, CAM-002-f |
| RF-050 | ventas/dispensaciones y pagos | sales_orders, sales_order_items, payments_transactions | order_number UNIQUE; referencia de pago UNIQUE; medios de pago paramétricos (a confirmar) | RNF-042 (terceros), CAM-002-d |
| RF-051 | descuento del lote al confirmar | sales_order_items.lot_id + inventory_movements + inventory_stock en **una** transacción | frontera transaccional única | RN-01, RNF-021 |
| RF-052 | receta: prescriptor, paciente, fecha, medicamento, cantidad | rx_prescriptions, rx_prescription_items | FKs NOT NULL | RN-04 |
| RF-053 | saldo de receta único y global (no por sucursal) | rx_prescription_items (cantidad_dispensada/saldo) | `saldo >= 0` CHECK; una sola columna global, no agregada por store | RN-04, CAM-002-a, S-9/P-17 |
| RF-054 | verificación del químico al dispensar receta/controlado | sales_orders.quimico_verificador_id | NOT NULL cuando condición de venta ≠ libre (método de verificación pendiente) | RNF-030, CAM-003-a |
| RF-055 | libro de controlados con saldo permanente por producto y sucursal | ctrl_ledger_entries (asientos) + ctrl_balances (saldo) | saldo ≥ 0; asiento obligatorio en cada movimiento de controlado | RN-05, CA-10 |
| RF-060 | devoluciones según política; sin reingreso a vendible sin evaluación | sales_returns, sales_return_items + movimiento tipo `devolucion` (no acredita a vendible) | política en system_config (valores `null`) | RN-11, RF-100, §22 |
| RF-061 | recall: sucursales, stock y dispensaciones del lote | inventory_lots.estado='retirado' + consultas por `lot_id` | bloqueo inmediato | RN-12, CA-10 |
| RF-070 | reportes operativos | consultas controladas + tablas resumen (§13) | refresh propuesto 5 min (condición, DB-P07) | CA-10 |
| RF-071 | reportes regulatorios de controlados | ctrl_ledger_entries filtrados por período | índices por fecha | §13 |
| RF-090 | auditoría de operaciones críticas: usuario, fecha, motivo, antes/después | audit_operations | append-only, valores JSON | RNF-046, §9 |
| RF-091 | registro de cada consulta/modificación de pacientes y recetas | audit_pii_access | una fila por acceso PII | RN-13, RNF-038 |
| RF-100 | parámetros sin redeploy (vencimiento, stock mínimo, política de devoluciones) | system_config | clave UNIQUE, `updated_by`, `updated_at` | RNF-002, §21 |

## 5. RNF → requisitos de datos

| RNF | Requisito sobre datos | Evidencia en diseño |
|---|---|---|
| RNF-001..004 | capacidad 25.000 txn/h (referencia), configurable, picos | dimensionamiento en Paso 11; no altera esquema; objetivo en system_config |
| RNF-005, RNF-020 | integridad bajo concurrencia, sin stock negativo/doble descuento | CHECK `stock >= 0`, `version` optimista, FOR UPDATE (Paso 12) |
| RNF-021 | fronteras transaccionales: dinero, inventario por lote, libro | agrupación venta+stock+libro en una transacción (Paso 12) |
| RNF-022 | idempotencia | idempotency_keys + UNIQUE (§6) |
| RNF-023 | sin borrado físico; compensatorios | sin DELETE en tablas transaccionales; movimientos compensatorios |
| RNF-024 | saldo reconstruible desde movimientos | inventory_movements completo + conciliación (RF-047) |
| RNF-025 | validación en servidor | CHECK/NOT NULL en BD + validación app |
| RNF-030..033 | MFA, mínimo privilegio, TLS, hash | auth_users (hash), tablas en §9 seguridad; MFA es de aplicación |
| RNF-034 | secretos fuera del repo | `.env`/Docker Secrets (§14); nada en artefactos |
| RNF-037 | sesiones expirables | token_blacklist (hash + expires_at), §15 |
| RNF-038, RNF-039 | PII protegido, retención/anonimización | catálogo separado, audit_pii_access, retención como parámetros `null`; anonimizar solo personales (RN-10) |
| RNF-045 | trazabilidad extremo a extremo por lote | `lot_id` presente en movimientos, ventas, transferencias, devoluciones |
| RNF-046 | auditoría solo anexar | audit_operations sin UPDATE/DELETE |
| RNF-047 | reloj consistente | timestamps UTC en todas las tablas (Paso 07) |
| RNF-048 | trazabilidad con latencia acotada (`null`) | índices FEFO/trazabilidad (Paso 11) |
| RNF-040..044 | backup/PITR, modo degradado, disponibilidad | §17 backup+binlog; modo degradado → **sin** esquema offline (DB-P06) |
| RNF-050..053 | logs sin PII, métricas, alertas | audit_operations no duplica PII de paciente en logs de app |
| RNF-060..063 | Linux, contenerizable, mantenimiento activo | criterio del Paso 06 (selección DBMS) |

## 6. Reglas de negocio → reglas de integridad verificables

| RN | Regla de integridad para el modelo | Tipo de mecanismo |
|---|---|---|
| RN-01 | toda salida referencia `store_id` + `lot_id` | FK NOT NULL |
| RN-02 | `stock_available >= 0` | CHECK (BD) |
| RN-03 | no dispensar vencido/cuarentena/retirado; FEFO | estado del lote + orden FEFO en app (lógica temporal, Paso 12) |
| RN-04/RN-05 | receta y libro obligatorios según condición de venta | FKs + transacción (Paso 12) |
| RN-06 | auditoría de ajustes; doble autorización en controlados | campos proponente/autorizador + CHECK |
| RN-07 | inventario solo al confirmar recepción | máquina de estados de recepción |
| RN-08 | estados de transferencia cerrados | CHECK de estados |
| RN-09 | idempotencia de cobro/dispensación/recepción/transferencia | idempotency_keys + UNIQUE |
| RN-10 | sin DELETE físico; correcciones compensatorias | grants sin DELETE + movimientos compensatorios |
| RN-11 | devolución no reingresa a vendible sin evaluación | tipo de movimiento + evaluación posterior |
| RN-12 | recall identifica sucursales/stock/dispensaciones | `lot_id` indexado en todas las tablas transaccionales |
| RN-13 | cada acceso a PII se registra | audit_pii_access |

## 7. Decisiones de `04_decisiones` condicionantes del modelo

| § | Decisión | Estado | Efecto en 05_base_datos |
|---|---|---|---|
| 1 | Persistencia relacional/transaccional | APROBADO | modelo relacional, FK/ACID |
| 2 | MySQL 8.x / InnoDB | APROBADO | motor en Paso 06-07 (re-justificado por RF/RNF, CA-04) |
| 3 | Una base + prefijos de dominio | APROBADO | convención de nombres por módulo |
| 4 | Reserva + available/reserved + lock breve | APROBADO CON CONDICIÓN | columnas en inventory_stock + inventory_reservations; DB-P01/02/03 abiertas |
| 5 | ACID interno + Saga pagos | APROBADO | outbox; sin transacción abierta contra gateway |
| 6 | Idempotency key + UNIQUE | APROBADO | idempotency_keys (retención 30 d propuesta, validar) |
| 7 | Integridad en BD, trazable | APROBADO (principio) | Paso 08 |
| 8 | Candidatos de entidades | REFERENCIA | workflow valida/completa/corrige contra RF/RNF |
| 9 | Auditoría (tablas+triggers+logs+binlog) | APROBADO req. | Paso 08-10 |
| 10 | Sin borrado físico; estrategia por entidad maestra pendiente (DB-P12) | APROBADO PARCIAL | soft delete opcional, decidido por entidad |
| 11 | Migraciones versionadas; herramienta pendiente (DB-P11) | PENDIENTE impl. | Paso 09/13: se propone, no se cierra sin registro |
| 12 | Índices por patrón de acceso; partición >1M filas a validar | APROBADO estrategia | Paso 11 |
| 13 | Vistas materializadas + refresh 5 min (MySQL: tablas resumen) | APROBADO CON CONDICIÓN | Paso 11; DB-P07 abierta |
| 14 | Sin secretos en repo; TLS; usuarios BD separados | APROBADO | Paso 07-08 |
| 15 | Argon2id; token_blacklist | APROBADO | auth_users/token_blacklist |
| 16 | Outbox en la misma transacción | APROBADO | outbox_events |
| 17 | Backup completo + binlog; RPO/RTO provisionales; prueba de restauración | APROBADO CON VALORES PENDIENTES | no fijar SLA |
| 18 | Sin HA/sharding inicial; evolución ordenada | NO REQUERIDA INICIALMENTE | un nodo |
| 19 | Pooling 50–100 (orientativo) | APROBADO necesidad | no impacta esquema |
| 20 | Capacidades MySQL por requisito | evaluación | Paso 07 |
| 21 | system_config para RF-100 | APROBADO | tabla system_config |
| 22 | Devoluciones como transacción nueva vinculada | APROBADO arquitectónico | sales_returns |
| 24 | Tabla de trazabilidad con citas rotas (D-1..D-3) | ver §3 | corregir cita, no requisito |

## 8. Supuestos explícitos (a validar, no incorporados como requisito)

1. Canal web fuera de alcance inicial: `channel` y `inventory_reservations` se modelan como **capacidad**, sin tablas de fulfillment/pedidos web (D-4, DB-P10).
2. RPO/RTO provisionales de §17 no se usan como objetivo de diseño hasta confirmación (RNF-041).
3. Herramienta de migraciones: se recomienda en Paso 09/13, cierre formal queda registrado como decisión pendiente DB-P11.
4. `inventory_reservations` granularidad producto+sucursal (reserva), con asignación de lote FEFO al confirmar — coherente con §8 y RN-03; pendiente DB-P01/DB-P02.

## 9. Salida de este paso

- Este documento: `proyecto/05_base_datos/01_requisitos_datos/requisitos_datos.md`.
- Pendiente: Paso 02 — `02_modelo_conceptual/modelo_conceptual.md`.
