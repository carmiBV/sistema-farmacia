# Workflow 05 — Frontend: Interfaz de Usuario y Experiencia POS

Precondición: Backend (`04_backend_workflow`) PASSED + APPROVED.

Alcance: Cobertura de la interfaz de usuario para todos los módulos del sistema (21 pantallas):
- Autenticación y Perfil (`/login`, `/dashboard`)
- Gestión de Usuarios y Permisos (`/autenticacion-usuarios`)
- Configuración y Sucursales (`/configuracion-sucursales`)
- Catálogo General (`/catalogo-productos`, `/catalogo-categorias`, `/catalogo-precios-promociones`)
- Proveedores, Pacientes y Prescriptores (`/gestion-proveedores`, `/gestion-pacientes-prescriptores`)
- Compras y Recepciones (`/compras-ordenes`, `/compras-recepciones`)
- Inventario, Lotes FEFO y Alertas (`/inventario-lotes-fefo`, `/inventario-stock-movimientos`, `/inventario-transferencias`, `/inventario-alertas-incidentes`)
- Recetas Médicas (`/recetas-medicas`)
- Punto de Venta POS, Pagos y Devoluciones (`/ventas-pos`, `/ventas-pagos-transacciones`, `/ventas-devoluciones`)
- Libro de Controlados y Auditoría (`/libro-controlados`, `/auditoria-operaciones-pii`)

Skills: `frontend-engineering`, `frontend-design`, `responsive-design`, `accessibility-compliance`.

## CP-FRONT-01
Módulo de Autenticación (`/login`): Formulario de inicio de sesión, manejo de tokens/sesión, control de errores de autenticación y redirección según rol de usuario. STOP.

## CP-FRONT-02
Layout Principal y Dashboard (`/dashboard`): Estructura base de navegación responsive (sidebar, header, usuario activo, selector de sucursal), widgets de resumen de ventas diarias, alertas de stock mínimo y vencimientos próximos. STOP.

## CP-FRONT-03
Módulo de Administración de Usuarios y Configuración (`/autenticacion-usuarios`, `/configuracion-sucursales`): Interfaces para gestión de usuarios, asignación de roles/permisos RBAC, configuración de sucursales, cajas registradoras y parámetros del sistema. STOP.

## CP-FRONT-04
Módulo de Catálogos y Precios (`/catalogo-categorias`, `/catalogo-productos`, `/catalogo-precios-promociones`): Pantallas de listado, búsqueda, creación y edición con borrado lógico (`estado='inactivo'`). Formularios con soporte para jerarquía de categorías, esquemas de precios y promociones. STOP.

## CP-FRONT-05
Módulo de Proveedores, Pacientes y Prescriptores (`/gestion-proveedores`, `/gestion-pacientes-prescriptores`): Pantallas de registro y consulta de proveedores de medicamentos, así como directorio de pacientes y médicos prescriptores autorizados. STOP.

## CP-FRONT-06
Módulo de Compras y Recepción de Mercancía (`/compras-ordenes`, `/compras-recepciones`): Interfaz para creación de órdenes de compra y pantalla de recepción con registro de lotes de fábrica, fecha de vencimiento y verificación contra orden. STOP.

## CP-FRONT-07
Módulo de Inventarios y Trazabilidad FEFO (`/inventario-lotes-fefo`, `/inventario-stock-movimientos`, `/inventario-transferencias`, `/inventario-alertas-incidentes`): Visualización de stock por lote con semaforización FEFO (vencimientos), kárdex de movimientos, solicitudes de transferencia entre sucursales y panel de incidentes. STOP.

## CP-FRONT-08
Módulo de Recetas Médicas y Medicamentos Controlados (`/recetas-medicas`, `/libro-controlados`): Interfaz para validación y registro de recetas médicas, dispensación vinculada a prescriptor/paciente y consulta del Libro Oficial de Controlados. STOP.

## CP-FRONT-09
Módulo Punto de Venta POS, Pagos y Devoluciones (`/ventas-pos`, `/ventas-pagos-transacciones`, `/ventas-devoluciones`): Interfaz optimizada para agilidad en caja (búsqueda rápida por código de barras, selección de lotes FEFO, cálculo de importes, múltiples medios de pago), emisión de comprobantes y procesamiento de devoluciones. STOP.

## CP-FRONT-10
Módulo de Auditoría y Seguridad PII (`/auditoria-operaciones-pii`): Pantalla de consulta de logs de operaciones sensibles y registro de acceso a datos personales de pacientes. STOP.

## CP-FRONT-11
Optimización Responsive, Accesibilidad y UI/UX: Pruebas de usabilidad en dispositivos táctiles/POS, cumplimiento de accesibilidad (WCAG) y suite de integración frontend. Terminar en `HUMAN_STATUS: PENDING`. No iniciar fase de integración final hasta aprobación explícita.