# Workflow 03 — API piloto: Catálogo de Categorías

Tabla principal: `catalog_categories` (asociada al modelo `CategoriaModel`).

Skills: `php-development`, `api-design-principles`, `openapi-spec-generation`.

## CP-API-01
Diseñar el contrato OpenAPI/Swagger para el recurso de categorías (`/api/v1/catalog/categories`). No programar código de aplicación. STOP.

## CP-API-02
Bootstrap de arquitectura PHP + `.env` + PDO MySQL + Front Controller (`public/index.php`) + Endpoint de salud (`/api/v1/health`). Verificar estructura sin código de negocio en el Front Controller. STOP.

## CP-API-03
GET `/api/v1/catalog/categories`. Implementar controlador (`CategoriaController`), servicio (`CategoriaService`) y repositorio (`CategoriaRepository`). Probar respuesta con datos de `catalog_categories`, colección vacía y manejo de errores de base de datos. STOP.

## CP-API-04
GET `/api/v1/catalog/categories/{id}`. Probar búsqueda por ID existente, ID inexistente (404) e ID con formato inválido (400). STOP.

## CP-API-05
POST `/api/v1/catalog/categories`. Validación mediante `CategoriaValidator` (requiere `nombre`, valida unicidad con `parent_id_key`). Probar fixtures válidos e inválidos. STOP.

## CP-API-06
PUT `/api/v1/catalog/categories/{id}`. Actualización de categorías con validación de datos e integridad jerárquica. STOP.

## CP-API-07
DELETE `/api/v1/catalog/categories/{id}`. Borrado lógico actualizando el estado de la categoría (`estado='inactivo'`). Prohibido usar `DELETE FROM`. STOP.

## CP-API-08
Generación final de especificación OpenAPI + ejecución de la suite CRUD completa de categorías. Probar respuesta de integración y terminar en `HUMAN_STATUS: PENDING`. No iniciar backend hasta aprobación explícita.