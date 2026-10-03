# Requisitos funcionales

## Administración
- RF-001. Administrar usuarios, roles y permisos (químico farmacéutico, auxiliar, cajero, administrador, auditor).
- RF-002. Administrar pacientes/clientes.
- RF-003. Administrar prescriptores (médicos) asociados a recetas.
- RF-010. Administrar sucursales y cajas/POS.
- RF-100. Administrar parámetros operativos sin modificar código: días de alerta de vencimiento, stock mínimo y política de devoluciones (estados de producto aceptados, plazo máximo y condiciones de reingreso a stock vendible; valores a definir por el cliente).

## Catálogo y precios
- RF-020. Administrar medicamentos y productos: principio activo, presentación, concentración, condición de venta (libre, bajo receta, controlado), categorías, precios y promociones.

## Compras y recepción
- RF-030. Administrar proveedores y órdenes de compra.
- RF-031. Registrar recepción parcial o total de mercadería, exigiendo lote, fecha de vencimiento y cantidad recibida.
- RF-032. Permitir dejar un lote en cuarentena al recibirlo hasta su liberación por el químico farmacéutico. La liberación se registra con usuario, fecha y motivo.

## Inventario
- RF-040. Mantener inventario por producto, lote y sucursal.
- RF-041. Registrar entradas, salidas, ajustes, bajas (vencimiento, deterioro, retiro) y transferencias.
- RF-042. Evitar doble descuento ante reintentos.
- RF-043. Aplicar FEFO en toda salida y bloquear lotes vencidos, en cuarentena o retirados.
- RF-044. Generar alertas de stock mínimo y de productos próximos a vencer.
- RF-045. Gestionar transferencias entre sucursales con estados controlados (solicitada, despachada, recibida, cerrada/rechazada) conservando el lote. Despachar y recibir un medicamento controlado genera el asiento correspondiente en el libro de controlados de la sucursal de origen y de la de destino.
- RF-046. Exigir doble autorización para ajustes de medicamentos controlados.
- RF-047. Al reconectar tras un corte de conexión, si el consumo acumulado de un lote supera su stock, el sistema registra la discrepancia como incidente y exige un ajuste autorizado. No se admite stock negativo silencioso.

## Venta y dispensación
- RF-050. Registrar ventas/dispensaciones presenciales y sus pagos. Medios de pago contemplados: efectivo, tarjeta y otros (a confirmar con el cliente). Los medios que dependan de un tercero (por ejemplo, la pasarela de tarjetas) aplican RNF-042 (timeout y reintentos controlados).
- RF-051. Descontar inventario del lote correspondiente al confirmar la dispensación.
- RF-052. Registrar la receta (prescriptor, paciente, fecha, medicamento, cantidad) cuando la condición de venta lo exija.
- RF-053. Controlar el saldo de la receta ante dispensaciones parciales. El saldo es único y global por receta, independiente de la sucursal donde se dispense.
- RF-054. Requerir verificación del químico farmacéutico para medicamentos bajo receta y controlados.
- RF-055. Mantener el libro de control de medicamentos controlados con saldo permanente por producto y sucursal.

## Devoluciones y retiro de lotes
- RF-060. Registrar devoluciones parciales o totales según la política vigente; el producto devuelto no vuelve al stock vendible sin evaluación.
- RF-061. Ejecutar un retiro de lote (recall): identificar sucursales, stock actual y dispensaciones asociadas, y bloquear el lote de inmediato.

## Reportes y auditoría
- RF-070. Generar reportes operativos de ventas, compras, inventario, vencimientos y rotación.
- RF-071. Generar reportes regulatorios de medicamentos controlados y dispensaciones por período.
- RF-090. Auditar operaciones críticas (usuario, fecha, motivo, valores antes y después).
- RF-091. Registrar cada consulta o modificación de datos de pacientes y recetas.
