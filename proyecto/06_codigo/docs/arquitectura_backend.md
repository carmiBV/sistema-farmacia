# Arquitectura Backend — plantilla multicapa

Patrón consolidado en CP-BACK-01 sobre el piloto `catalog_categories`
(`03_api_pilot_workflow`). Cada módulo de los CP-BACK-02..10 se construye
replicando esta plantilla.

## Capas (por módulo)

| Capa | Archivo | Responsabilidad |
|---|---|---|
| Controller | `src/Controllers/<Modulo>Controller.php` | Ruta HTTP → Service → envelope `{success, data, meta\|error}`. No contiene lógica de negocio ni SQL. |
| Service | `src/Services/<Modulo>Service.php` | Casos de uso, validación de flujo (404/409), traducción de `PDOException` → `AppException` sin exponer el driver. |
| Repository | `src/Repositories/<Modulo>Repository.php` | SQL preparado (PDO, sin emulación). Whitelist de columnas en SELECT. Filtros de borrado lógico (`estado='activo'`). |
| Model | `src/Models/<Modulo>.php` | Value object inmutable: `fromRow()` (hidratación) y `toArray()` (whitelist de campos de API; nunca columnas internas). |
| Validator | `src/Validators/<Modulo>Validator.php` | Validación de entrada (400 `VALIDATION_ERROR`). |
| Ruta | `public/index.php` | `$router->add('METHOD', '/api/v1/...', fn($p) => ...)` |
| Spec | `docs/openapi.yaml` | Contrato del endpoint. |
| Verificación | `tools/verify_cp<NN>.php` | Suite del CP con fixtures de nombre único (sin DELETE de BD). |

## Reglas transversales (obligatorias en todos los módulos)

1. Envelope de respuesta: `{success, data}` · `{success, data, meta}` · `{success, error:{code, message}}`.
2. Códigos: 200/201/204 · 400 validación · 401 sin auth · 403 RBAC · 404 no existe · 409 duplicado/jerarquía · 500 genérico (`INTERNAL_ERROR`, sin detalle de driver).
3. Borrado lógico: `estado='inactivo'` (`UPDATE`), **prohibido `DELETE FROM`**; registro inactivo fuera de la API (GET/PUT/DELETE → 404, listado lo excluye); checks de unicidad sin filtro de estado si el índice único incluye inactivos (F14).
4. Tablas append-only (`ctrl_*`, `audit_*`, `inventory_movements`, kárdex): INSERT/SELECT únicamente.
5. Consultas preparadas siempre; sin `ATTR_EMULATE_PREPARES`.
6. Nunca exponer columnas generadas/llaves internas (`*_key`) ni credenciales.
7. Directorio de trabajo para tests: `proyecto/06_codigo`; servidor de prueba `php -S 127.0.0.1:8000 -t public public/index.php`.

## Orden de un checkpoint backend

1. Model → Repository → Validator → Service → Controller → ruta (`index.php`).
2. `docs/openapi.yaml` + `tools/validate_openapi.py`.
3. `php -l` en los archivos tocados.
4. Suite del CP (`tools/verify_cp<NN>.php`) + regresión de suites previas.
5. Actualizar `.agents/state/backend-workflow.json` → `HUMAN_STATUS: PENDING` y parar.

Referencia de implementación: `src/Models/Categoria.php`,
`src/Repositories/CategoriaRepository.php`, `src/Services/CategoriaService.php`,
`src/Controllers/CategoriaController.php`, `src/Validators/CategoriaValidator.php`.
