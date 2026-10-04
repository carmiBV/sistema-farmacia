# Fase de Desarrollo del Sistema Farmacéutico

Alcance General:
- Base de Datos: Esquema oficial de 42 tablas (`proyecto/05_base_datos/13_sql/`).
- API Piloto: Recurso `catalog_categories` (`/api/v1/catalog/categories`).
- Backend: Arquitectura MVC en PHP 8.x puro (25 módulos de servicio).
- Frontend: 21 pantallas operativas con interfaz POS táctil y renderizado PHP + JS Vanilla.

Flujo de Inicio: `CONTINUAR_API_PILOTO.md`

Regla Obligatoria: UN checkpoint por interacción → probar en `proyecto/06_codigo/` → actualizar estado en `.agents/state/` → `HUMAN_STATUS: PENDING` → STOP.