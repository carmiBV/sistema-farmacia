# Decisiones de Frontend

**Estado:** APROBADO

## Stack Tecnológico
- PHP Views (renderizado modular) + HTML5 + CSS3 + JavaScript ES6+ (Vanilla JS / Fetch API).
- Sin frameworks SPA pesados (React, Vue, Angular) ni procesadores como Webpack/Vite.
- Estilos CSS puros o utilitarios ligeros sin dependencias complejas.

## Arquitectura de Interfaz (21 Pantallas Operativas)
El frontend implementará la suite completa de interfaces para el personal operativo, administradores y cajeros:
1. `/login` - Autenticación y selección de sucursal/caja.
2. `/dashboard` - Indicadores principales, alertas de inventario y accesos directos.
3. `/pos` - Punto de venta táctil, búsqueda rápida, escáner de barras y cobro.
4. `/categorias` - Gestión de categorías de productos.
5. `/productos` - Catálogo general de medicamentos y productos.
6. `/lotes` - Control de lotes, fechas de vencimiento y estados de cuarentena.
7. `/inventario` - Stock por sucursal, ajustes y kárdex.
8. `/compras` - Órdenes de compra y recepción con registro de lotes.
9. `/recetas` - Registro y validación de prescripciones médicas.
10. `/controlados` - Libro Oficial de Medicamentos Controlados y saldos.
11. `/reportes` - Consultas de ventas, inventario y movimientos.
12. *(Pantallas complementarias de clientes, proveedores, prescriptores, usuarios, roles, sucursales, cajas, promociones, auditoría y configuración).*

## Orientación Operativa
- Interfaz adaptada a pantallas táctiles y atajos de teclado para operaciones ágiles de caja.
- Consumo asíncrono de la API REST (`/api/v1/...`) mediante Fetch API.

## Regla de Desarrollo
Una pantalla o componente funcional significativo por checkpoint, garantizando su prueba antes de continuar.