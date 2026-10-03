# Paso 02 — Modelo conceptual

- **Workflow:** 02_database_workflow · **Paso:** 02
- **Skill utilizado:** `database-schema-designer`
- **Entrada:** `01_requisitos_datos/requisitos_datos.md` (trazabilidad RF/RNF/RN/decisiones)
- **Motor:** sin asignar todavía (el modelo es lógico-independiente).

Convención de dominios (decisión §3): prefijo por módulo en una única base.

| Prefijo | Dominio | RF |
|---|---|---|
| `auth_` | identidad, roles, sesiones | RF-001 |
| `ops_` | sucursales, cajas, configuración | RF-010, RF-100 |
| `catalog_` | productos, precios, terceros (pacientes, prescriptores, proveedores) | RF-002, RF-003, RF-020, RF-030 |
| `purchase_` | órdenes y recepciones | RF-030..032 |
| `inventory_` | lotes, stock, movimientos, transferencias, reservas, alertas, incidentes | RF-040..047 |
| `sales_` | ventas, pagos, devoluciones | RF-050, RF-060 |
| `rx_` | recetas y saldo | RF-052, RF-053 |
| `ctrl_` | libro de controlados | RF-055, RF-071 |
| `audit_` | auditoría de operaciones y accesos PII | RF-090, RF-091 |
| `sys_` | idempotencia, outbox, blacklist de tokens | RN-09, §6, §15, §16 |

## 1. Entidades

### 1.1 Identidad (auth_)

| Entidad | Atributos conceptuales | Reglas |
|---|---|---|
| Rol | id, nombre, descripción | nombre único |
| Permiso | id, clave, descripción | clave única; asignado a roles |
| Usuario | id, usuario, hash contraseña, estado, MFA, fecha creación | hash Argon2id (§15), nunca texto plano (RNF-033) |
| Rol-Permiso / Usuario-Rol | asociaciones | N:M |
| Blacklist de token | hash del token, expiración | hash único; expiración para limpieza (§15) |

### 1.2 Operación y configuración (ops_)

| Entidad | Atributos | Reglas |
|---|---|---|
| Sucursal | id, código, nombre, estado | código único; crecimiento sin rediseño (RNF-010) |
| Caja (POS) | id, sucursal, código, estado | código único por sucursal |
| Configuración del sistema | clave, valor, alcance (global/sucursal), fecha, usuario | clave única; cambio auditado (RF-100, §21) |

### 1.3 Catálogo y terceros (catalog_)

| Entidad | Atributos | Reglas |
|---|---|---|
| Categoría | id, nombre, padre (opcional) | jerarquía simple, autoreferencia opcional |
| Medicamento/Producto | id, sku, nombre, principio activo, presentación, concentración, condición de venta (libre/receta/controlado), estado | sku único; condición de venta controlada por dominio (RF-020) |
| Precio | producto, alcance (global/sucursal), importe, vigencia | alcance **pendiente DB-P09** |
| Promoción | id, producto/categoría, descuento, vigencia | fechas coherentes |
| Proveedor | id, identificación, nombre, contacto | identificación única |
| Paciente (PII) | id, identificación, nombre, nacimiento, contacto | PII: acceso por rol + registro de acceso (RN-13); anonimizable sin borrar recetas/movimientos (RN-10, RNF-039) |
| Prescriptor (PII) | id, identificación, nombre, especialidad | idem PII |

### 1.4 Compras y recepción (purchase_)

| Entidad | Atributos | Reglas |
|---|---|---|
| Orden de compra | id, número, proveedor, estado, fechas | número único (§6) |
| Ítem de orden | orden, producto, cantidad pedida/recibida | cantidad ≥ 0 |
| Recepción | id, orden, estado (recibida/confirmada), receptor, fecha | inventario **solo al confirmar** (RN-07) |
| Ítem de recepción | recepción, número de lote, vencimiento, cantidad | lote, vencimiento y cantidad obligatorios (RF-031, RNF-025) |

### 1.5 Inventario (inventory_)

| Entidad | Atributos | Reglas |
|---|---|---|
| Lote | id, producto, número de lote, vencimiento, estado (cuarentena/liberado/retirado), origen recepción, liberación (usuario/fecha/motivo) | liberación exige los 3 datos (RF-032); estado controla dispensación (RN-03); recall = retirado inmediato (RN-12) |
| Stock por lote y sucursal | sucursal, lote, disponible, reservado, vendido, versión | PK (sucursal, lote); disponible ≥ 0 (RN-02); modelo available/reserved/sold (§4) |
| Movimiento de inventario | id, sucursal, lote, producto, tipo (entrada/salida/ajuste/baja/devolución/transferencia), cantidad, usuario, motivo, referencia, proponente, autorizador | append-only (RNF-023, §9); doble autorización en controlados (RN-06); compensatorios referencian al movimiento original |
| Transferencia | id, sucursal origen, destino, estado (solicitada/despachada/recibida/cerrada/rechazada), usuarios y fechas por transición | estados cerrados (RN-08); conserva lote (RF-045) |
| Ítem de transferencia | transferencia, lote, cantidad | cantidad > 0 |
| Reserva | id, orden, producto, sucursal, cantidad, estado (pending/confirmed/expired/cancelled), expiración | capacidad congelada por §4; DB-P01/DB-P02 abiertas; **sin canal web activo** (alcance) |
| Alerta | tipo (stock mínimo/vencimiento), producto, sucursal, estado, fecha | umbrales desde configuración (RF-044, RF-100) |
| Incidente de conciliación | sucursal, lote, stock registrado, consumo acumulado, estado, movimiento de ajuste vinculado | RF-047; exige ajuste autorizado, nunca stock negativo silencioso |

### 1.6 Venta, pago y devolución (sales_)

| Entidad | Atributos | Reglas |
|---|---|---|
| Venta/Dispensación | id, número de venta, canal (default `pos`), sucursal, caja, paciente (opcional), estado, total, usuario, químico verificador, fecha | número único (§6); verificación químico si condición ≠ libre (RF-054); canal anticipa evolución sin activar web (D-4) |
| Ítem de venta | venta, lote, producto, cantidad, precio, subtotal | lote obligatorio (RN-01); descuento al confirmar (RF-051) |
| Pago | id, venta, proveedor/medio, referencia, importe, estado, clave de idempotencia | referencia única; terceros → timeout/reintento (RNF-042); saga con outbox (§5, §16) |
| Devolución | id, venta original, sucursal, usuario, fecha, motivo, monto, resultado de reembolso, estado de evaluación | transacción **nueva** vinculada (§22); sin borrado del original (RN-10) |
| Ítem de devolución | devolución, lote, producto, cantidad, condición | no reingresa a vendible sin evaluación (RN-11) |

### 1.7 Recetas (rx_)

| Entidad | Atributos | Reglas |
|---|---|---|
| Receta | id, prescriptor, paciente, fecha | PII (RN-13) |
| Ítem de receta | receta, producto, cantidad prescrita, cantidad dispensada, versión | **saldo global único por receta**, no por sucursal (RF-053); saldo ≥ 0; concurrencia optimista por versión (DEC-02/§5) |

### 1.8 Libro de controlados (ctrl_)

| Entidad | Atributos | Reglas |
|---|---|---|
| Asiento del libro | id, sucursal, producto, tipo, cantidad, saldo resultante, usuario, autorizador, motivo, referencia | append-only (RN-05, RNF-046); asiento obligatorio en cada movimiento de controlado, incl. transferencias origen y destino (RF-045) |
| Saldo permanente | sucursal, producto, saldo, versión | PK (sucursal, producto); saldo ≥ 0; conciliable con asientos (RNF-024) |

### 1.9 Auditoría y soporte técnico (audit_, sys_)

| Entidad | Atributos | Reglas |
|---|---|---|
| Auditoría de operaciones | usuario, acción, entidad, valores antes/después, motivo, fecha | solo anexar (RF-090, RNF-046) |
| Auditoría de accesos PII | usuario, entidad/persona afectada, acción, fecha | una fila por acceso a paciente/receta (RF-091) |
| Idempotencia | clave única, operación, hash de solicitud, creación, expiración | RN-09; retención propuesta 30 d (§6, validar) |
| Evento outbox | tipo de evento, agregado, payload, estado, creación, procesamiento | insertado en la misma transacción de negocio (§16) |

## 2. Relaciones y cardinalidades

| Relación | Cardinalidad | Notas |
|---|---|---|
| Sucursal ← Caja | 1:N | RNF-010 |
| Sucursal ← Movimiento / Stock / Asiento / Incidente / Alerta | 1:N | trazabilidad por sucursal |
| Producto ← Categoría | N:M (producto-categoría) | RF-020 |
| Producto ← Lote | 1:N | RN-12 |
| Lote ← Stock | 1:N (por sucursal) | RF-040 |
| Recepción → Lote | 1:N (al confirmar) | RN-07 |
| Orden de compra ← Recepción | 1:N | recepción parcial (RF-031) |
| Transferencia → Sucursal (origen/destino) | N:1 ×2 | RN-08 |
| Transferencia ← Ítem → Lote | 1:N | conserva lote |
| Venta → Sucursal, Caja, Usuario | N:1 | RF-010 |
| Venta ← Ítem → Lote, Producto | 1:N | RN-01, CA-10 |
| Venta ← Pago | 1:N | RF-050 |
| Venta → Paciente (opcional) | N:1 | PII |
| Devolución → Venta original | N:1 | §22 |
| Devolución ← Ítem → Lote | 1:N | RN-11 |
| Receta → Prescriptor, Paciente | N:1 ×2 | RF-052 |
| Receta ← Ítem → Producto | 1:N | RF-053 |
| Asiento/Saldo → Producto, Sucursal | N:1 ×2 | RF-055 |
| Movimiento → Referencia (venta/recepción/transferencia/devolución/incidente) | N:1 polimórfica | trazabilidad CA-10 |
| Reserva → Orden, Producto, Sucursal | N:1 ×3 | §4 |
| Outbox ← Eventos de Venta/Pago | 1:N | §16 |
| Usuario → Auditoría, Asiento (proponente/autorizador) | 1:N | RN-06, RNF-031 |

## 3. Reglas de negocio globales sobre el modelo

1. **RN-01/RN-03:** toda salida identifica sucursal+lote; el lote en estado ≠ `liberado` o vencido no puede ser origen de salida.
2. **RN-02:** el stock disponible no puede ser negativo (restricción en el almacén, no solo en la app).
3. **RN-05/RF-055:** un movimiento de medicamento controlado genera movimiento de inventario **y** asiento de libro en la(s) sucursal(es) afectada(s) — una sola transacción (RNF-021).
4. **RN-06/RF-046:** ajuste de controlado requiere dos actores distintos registrados.
5. **RN-09:** cobro, dispensación, recepción y transferencia admiten reintentos sin duplicar efectos.
6. **RN-10:** ninguna entidad transaccional se borra; correcciones por movimiento compensatorio referenciado.
7. **RN-13:** cada acceso a paciente/receta deja fila en auditoría PII.

## 4. Supuestos y pendientes abiertos que el modelo NO resuelve

- DB-P01 (¿la reserva bloquea stock para POS?), DB-P02 (expiración de reserva), DB-P03 (máximo ítems por transferencia).
- DB-P08 (modelo definitivo de clientes), DB-P09 (precios global/por sucursal), DB-P12 (eliminación por entidad maestra).
- Sin entidades de fulfillment/pedido web, pagos de terceros con detalle de pasarela más allá de `payments_transactions` (DB-P04), ni tablas de modo degradado offline (DB-P06).

## 5. Salida

- Este documento + `diagrama_er.md` (Paso 03).
