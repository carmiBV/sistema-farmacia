# Paso 05 — Informe de normalización

- **Workflow:** 02_database_workflow · **Paso:** 05
- **Skill utilizado:** `database-schema-designer`
- **Base:** `03_modelo_logico/modelo_logico.md`

## 1. 1FN — Ausencia de multivalorados y grupos

| Verificación | Resultado |
|---|---|
| Ningún atributo con listas separadas por coma | ✅ ítems en tablas propias (`sales_order_items`, `purchase_reception_items`, `inventory_transfer_items`, `rx_prescription_items`, `sales_return_items`, `catalog_product_categories`) |
| Claves compuestas con valores atómicos | ✅ `PK(store_id, lot_id)`, `PK(product_id, category_id)`, etc. |
| Categorías múltiples de producto | ✅ tabla asociativa, no columna multivalorada |
| Direcciones/contactos de pacientes en una columna | ✅ campo atómico `contacto` simplificado (modelo de contacto completo: DB-P08 pendiente) |

**Conclusión:** 1FN cumplida.

## 2. 2FN — Sin atributos dependientes parcialmente de la clave

| Tabla | Dependencia | Cumple |
|---|---|---|
| `inventory_stock` | cantidades dependen del par (store, lot) completo | ✅ |
| `catalog_product_categories` | sin atributos no clave | ✅ |
| `auth_user_roles` / `auth_role_permissions` | sin atributos no clave | ✅ |
| `sales_order_items` | precio/subtotal dependen de (order, lot) — la clave `id` es única, sin parcialidades | ✅ |
| `inventory_transfer_items` | cantidades dependen del par (transfer, lot) | ✅ |
| `ctrl_balances` | saldo depende del par (store, product) | ✅ |

**Conclusión:** 2FN cumplida (claves surrogatas + claves compuestas sin dependencias parciales).

## 3. 3FN — Sin dependencias transitivas

| Verificación | Resultado |
|---|---|
| `sales_orders` no repite datos de paciente (FK `paciente_id`, no nombre/documento) | ✅ |
| `sales_orders` no repite producto/lote (viven en `sales_order_items`) | ✅ |
| `inventory_movements` no calcula saldo guardado como atributo derivado obligatorio | ✅ (no almacena `saldo_resultante`; si se añadiera para reporte sería 3FN con justificación → ver §4) |
| `purchase_reception_items` referencia `lot_id` poblado al confirmar; no duplica estado del lote | ✅ |
| `system_config` no colapsa clave+valor en filas distintas por parámetro | ✅ (PK `config_key`) |
| Auditoría en JSON (`valores_antes/despues`) | ⚠️ ver §4.6 |

**Conclusión:** 3FN cumplida salvo las desnormalizaciones justificadas de §4.

## 4. BCNF y desnormalizaciones justificadas

Toda desnormalización se justifica con RF/RNF o patrón de acceso (regla del workflow).

### 4.1 `inventory_stock` (copia del saldo) — justificada
- **Qué desnormaliza:** el saldo por (sucursal, lote) es derivable de `SUM(movimientos)`.
- **Por qué:** RNF-020/RNF-005 exigen lectura de disponibilidad y escritura atómica baratas bajo concurrencia de 48 cajas; RNF-024 se satisface por conciliación, no por cálculo en cada consulta.
- **Control:** la fuente de verdad sigue siendo `inventory_movements`; discrepancias → `inventory_incidents` (RF-047).

### 4.2 `ctrl_balances` (saldo permanente del libro) — justificada
- **Por qué:** RF-055 pide saldo permanente por producto y sucursal; recomputar desde asientos en cada dispensación es inviable (RNF-006, RF-071).
- **Control:** conciliación `SUM(saldo_resultante)` vs `saldo` (RNF-024); asientos append-only.

### 4.3 `rx_prescription_items.cantidad_dispensada` — justificada
- **Por qué:** RF-053 exige saldo global ágil y atómico entre sucursales; derivar de ventas cruzadas por item sería lento y propenso a carreras.
- **Control:** CHECK `<= cantidad_prescrita`; actualización en la misma transacción de dispensación con `version` optimista.

### 4.4 `sales_orders.total` — justificada
- **Por qué:** patrón de acceso (listados, PDF, cierre de caja) no debe sumar ítems en cada lectura (RNF-006).
- **Control:** recálculo/verificación en pruebas (Paso 19); ítems fuente de verdad.

### 4.5 `purchase_order_items.cantidad_recibida` — justificada
- **Por qué:** RF-031 admite recepción parcial; el acumulado evita escanear todas las recepciones para saber el saldo de la orden.
- **Control:** CHECK `<= cantidad_pedida`; deriva de `purchase_reception_items` al confirmar.

### 4.6 Auditoría con JSON (`valores_antes/valores_despues`) — 3FN tolerada
- **Qué viola:** los campos dentro del JSON pueden tener dependencias transitivas.
- **Por qué se acepta:** RF-090 exige guardar valores antes/después de entidades heterogéneas en una sola tabla; normalizar un par de tablas por entidad multiplicaría el esquema sin beneficio de consulta (los accesos son por `entidad_id` + fecha).
- **Control:** índice `(entidad, entidad_id, created_at)`; el JSON es inmutable (solo INSERT, RNF-046).

### 4.7 `inventory_movements.ref_tipo + ref_id` (polimórfico) — denormalización de integridad
- **Qué sacrifica:** FK tipada hacia la tabla referenciada.
- **Por qué:** una tabla de movimientos heterogéneos (venta, recepción, transferencia, devolución, incidente) con 5 FKs nullables duplicaría reglas y haría imposible índices útiles; el workflow lo aprueba explícitamente en `diagrama_er.md` §3.
- **Control:** los tipos se validan en aplicación y por pruebas (Paso 19); alternativa FK dedicadas queda como opción abierta en Paso 08.

### 4.8 No desnormalizar
- No se materializa "stock por producto y sucursal" aparte del lote (RF-040 exige granularidad lote) — el agregado se consulta por `SUM` sobre `inventory_stock` con índice.
- No se copia `condicion_venta` del producto a `sales_order_items` (puede cambiar; el chequeo es a fecha de venta, decidido en Paso 12).

## 5. Conclusión

- 1FN, 2NF, 3NF cumplidas en el modelo lógico.
- BCNF: cumplida en todas las tablas cuyas determinantes son claves candidatas; las excepciones son las desnormalizaciones operacionales 4.1–4.7, cada una con RF/RNF/patrón de acceso y mecanismo de control.
- Pendiente DB-P08/DB-P09/DB-P12 pueden alterar tablas de catálogo; se re-evaluará normalización en Paso 13 si cambian.

## 6. Salida

- Este informe → Paso 06 (`05_modelo_fisico/seleccion_dbms.md`).
