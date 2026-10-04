# Decisiones de Backend

**Estado:** APROBADO

## Stack Tecnológico
- PHP 8.x puro.
- Arquitectura MVC multicapa: `Front Controller` (`public/index.php`) → `Router` → `Controllers` → `Validators` → `Services` → `Repositories` → `Models/PDO`.
- Manejo de entorno mediante `.env`.
- Base de datos MySQL 8.x / InnoDB.

## Cobertura y Módulos Backend
El backend implementará los 25 módulos de servicio necesarios para dar soporte a las 42 tablas del sistema:
- Gestión de sesiones y seguridad RBAC con tokens y directivas de acceso.
- Algoritmo de despacho de inventario FEFO (First Expired, First Out) con bloqueo de lotes vencidos o en cuarentena.
- Módulo de recepciones de compra vinculando obligatoriamente los lotes recibidos (`reception_item_id NOT NULL`).
- Registro inmutable append-only del Libro Oficial de Controlados (`ctrl_ledger_entries`).
- Motor de ventas POS con soporte de transacciones atómicas y registro de auditoría PII sobre datos de pacientes.

## Autenticación
- Manejo de sesiones y tokens con almacenamiento seguro de contraseñas mediante `PASSWORD_ARGON2ID` o `PASSWORD_BCRYPT`.
- Sembrado inicial mediante script `proyecto/06_codigo/tools/create_demo_user.php` o seeds SQL.

## Restricciones Arquitectónicas
- Queda totalmente prohibida la introducción de Laravel, Symfony, Slim u otros frameworks externos.
- Sin ORMs externos (se utiliza PDO directo encapsulation en capas Repository).
- Sin operaciones destructivas de datos (`DELETE`).

## Regla de Desarrollo
Ejecución de un solo punto de control (`CP`) por interacción con validación humana obligatoria.