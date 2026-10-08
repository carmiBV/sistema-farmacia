Corrección arquitectónica de la API

La implementación actual está concentrando la lógica de la API en:
proyecto/06_codigo/public/index.php
Esto incumple la arquitectura MVC y la separación de responsabilidades definida para el proyecto.

Objetivo:
Refactorizar el código existente para separar las capas sin alterar la funcionalidad, los endpoints actuales, ni las estructuras de respuesta JSON existentes. No avances al siguiente checkpoint.

Regla Estricta para public/index.php:
`public/index.php` debe ser ÚNICAMENTE el Front Controller.
No debe contener:
- Sentencias SQL ni PDO.
- Operaciones CRUD ni lógica de negocio/categorías.
- Validaciones o autenticación.

Estructura de Directorios Obligatoria:
proyecto/06_codigo/
├── public/
│   └── index.php
├── app/
│   ├── Core/
│   │   ├── Router.php
│   │   ├── Request.php
│   │   ├── Response.php
│   │   └── Database.php
│   ├── Controllers/
│   │   └── Api/
│   │       └── CategoriaController.php
│   ├── Models/
│   │   └── Categoria.php
│   ├── Repositories/
│   │   └── CategoriaRepository.php
│   ├── Services/
│   │   └── CategoriaService.php
│   └── Validators/
│       └── CategoriaValidator.php
├── routes/
│   └── api.php
├── config/
│   ├── app.php
│   └── database.php
└── tests/

Matriz de Responsabilidades:
- public/index.php: Bootstraping, autoloader (PSR-4 o spl_autoload_register), cargar configuraciones/rutas y ejecutar el Router.
- Router.php: Despachar URI y método HTTP al controlador/método correspondiente.
- Database.php: Configuración y retorno del objeto de conexión PDO (Singleton o Factory).
- CategoriaRepository.php: Única capa con consultas SQL PDO a la tabla `cat_categoria`.
- CategoriaService.php: Lógica de negocio y llamado al repositorio.
- CategoriaValidator.php: Validación de inputs y estructura de payloads.
- CategoriaController.php: Recibir Request, invocar Validator/Service y retornar la Response en JSON.
- routes/api.php: Declaración y registro de endpoints de categorías.

Acción Requerida:
1. Refactoriza únicamente el código ya implementado (manten los mismos formatos JSON y códigos HTTP).
2. Asegura el correcto funcionamiento del autoloading de clases en `app/`.
3. No agregues nuevos endpoints ni modifiques la estructura de la base de datos.
4. Realiza validación de sintaxis PHP (`php -l`) en todos los archivos modificados/creados.
5. Verifica manualmente o mediante pruebas que los endpoints existentes sigan respondiendo de forma idéntica.
6. Confirma que `public/index.php` haya quedado completamente limpio de lógica SQL y CRUD.
7. Actualiza `.agents/state/api-pilot-workflow.json`:
   - En caso de éxito: Establece `HUMAN_STATUS: PENDING`.
   - En caso de fallo: Establece `TECHNICAL_STATUS: FAILED` y `CAN_CONTINUE: false`.
8. Detente tras completar y verificar los pasos.