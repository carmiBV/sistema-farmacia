# Decisiones de API

**Estado:** APROBADO

- **Lenguaje/Entorno:** PHP 8.x puro (sin frameworks externos).
- **Base de Datos:** MySQL 8.x / MariaDB (Motor InnoDB).
- **Driver de Conexión:** PDO con consultas preparadas obligatorias.
- **Formato de Intercambio:** RESTful API con respuestas JSON estandarizadas.
- **Prefijo Global:** `/api/v1/`.

## Recurso Piloto Oficial
Tabla: `catalog_categories`

Endpoints Piloto:
- `GET /api/v1/catalog/categories`
- `GET /api/v1/catalog/categories/{id}`
- `POST /api/v1/catalog/categories`
- `PUT /api/v1/catalog/categories/{id}`
- `DELETE /api/v1/catalog/categories/{id}` *(Acción: inactivación lógica `estado='inactivo'`)*.

## Cobertura General de Endpoints API
La API se desplegará de forma modular cubriendo los recursos de la arquitectura de 42 tablas:
- `/api/v1/auth/*` (login, logout, me, refresh).
- `/api/v1/catalog/*` (categories, products, suppliers, customers, prescribers).
- `/api/v1/inventory/*` (batches, stocks, movements, transfers, reorder-alerts).
- `/api/v1/purchases/*` (orders, receptions).
- `/api/v1/sales/*` (pos, transactions, idempotency, receipts).
- `/api/v1/rx/*` e `/api/v1/controlled/*` (prescriptions, ledger-entries, balances).
- `/api/v1/audit/*` (operations, pii-access).

## Seguridad en la API
- Consultas preparadas PDO en el 100% de las consultas.
- Manejo estricto de claves de idempotencia en transacciones POS (`idempotency_keys`).
- Sanitización y validación estricta server-side.
- Respuestas de error limpias sin exposición de *stack traces* o datos sensibles de BD.

## Validación Humana
La instrucción del usuario aprueba únicamente el checkpoint anterior y habilita un único checkpoint nuevo.