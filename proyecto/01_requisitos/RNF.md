# Requisitos no funcionales

## Capacidad y rendimiento
- RNF-001. Soportar inicialmente 25.000 transacciones de negocio por hora (referencia a validar con el cliente).
- RNF-002. La capacidad objetivo debe ser configurable externamente.
- RNF-003. Una transacción de negocio no equivale a una sentencia SQL. Las consultas de stock o producto no cuentan como transacción de negocio y se miden aparte.
- RNF-004. Evaluar picos con el multiplicador configurado.
- RNF-005. Mantener integridad bajo concurrencia entre cajas y sucursales que operan sobre el mismo stock.
- RNF-006. La dispensación en mostrador debe responder en un tiempo acotado (objetivo propuesto en `perfil_carga.yaml`, pendiente de aprobación).

## Escalabilidad
- RNF-010. Permitir crecimiento de sucursales/cajas sin rediseñar todo el dominio.
- RNF-011. Evaluar un escenario de 10x la carga inicial.

## Integridad y confiabilidad de la información
- RNF-020. Evitar stock negativo y doble descuento por condiciones de carrera.
- RNF-021. Definir fronteras transaccionales para dinero, inventario por lote y libro de controlados.
- RNF-022. Evitar duplicados ante reintentos (idempotencia).
- RNF-023. Los registros confirmados no se eliminan físicamente; las correcciones se hacen por movimientos compensatorios.
- RNF-024. El saldo de inventario debe poder reconstruirse y conciliarse a partir del historial de movimientos.
- RNF-025. Validar datos críticos en el servidor (lote, vencimiento, cantidades, receta).

## Seguridad
- RNF-030. Autenticación para todos los usuarios. MFA al iniciar sesión para los roles administrador, auditor y químico farmacéutico; para la dispensación de medicamentos bajo receta y controlados, verificación del químico farmacéutico en el momento de la dispensación (reautenticación o PIN). Los roles cajero y auxiliar no requieren MFA por acción.
- RNF-031. Autorización por mínimo privilegio y segregación de funciones (quien dispensa no autoriza sus propios ajustes).
- RNF-032. Cifrado en tránsito.
- RNF-033. Contraseñas nunca en texto plano (hash con algoritmo adaptativo).
- RNF-034. Secretos fuera del repositorio.
- RNF-035. Controles de seguridad para APIs (autenticación, límites de uso, validación de entradas).
- RNF-036. Cifrado en reposo para datos sensibles y respaldos.
- RNF-037. Gestión de sesiones con expiración y cierre por inactividad en POS.

## Privacidad
- RNF-038. Proteger los datos de pacientes y recetas como datos sensibles: acceso por rol, minimización de datos y enmascaramiento en reportes cuando no sean necesarios.
- RNF-039. Definir y aplicar plazos de retención y procedimientos de anonimización o archivo al vencer (según normativa por validar). La anonimización afecta solo los datos personales de pacientes y prescriptores; los movimientos, lotes, cantidades y el libro de controlados se conservan.

## Trazabilidad y auditoría
- RNF-045. Trazabilidad extremo a extremo por lote: recepción, transferencias, dispensaciones y bajas.
- RNF-046. Registros de auditoría inalterables (solo anexar), con protección contra modificación por usuarios operativos.
- RNF-047. Sincronización de reloj confiable para que las marcas de tiempo sean consistentes entre sucursales.
- RNF-048. Consultas de trazabilidad (por lote, paciente o receta) con respuesta en un tiempo acotado (parámetro en `perfil_carga.yaml`, sin valor, pendiente de definir con el cliente).

## Disponibilidad y recuperación
- RNF-040. Definir estrategia de backup/restauración, con pruebas periódicas de restauración.
- RNF-041. RPO/RTO deben definirse antes de producción.
- RNF-042. Dependencias externas deben manejar timeout y reintentos controlados.
- RNF-043. Definir el modo degradado del mostrador ante corte de conexión: qué se permite, qué se bloquea (p. ej., controlados) y cómo se concilia al reconectar.
- RNF-044. Definir ventanas de mantenimiento y la base de medición de la disponibilidad (objetivo propuesto de 99,9 % en `perfil_carga.yaml`, pendiente de aprobación).

## Observabilidad
- RNF-050. Logs estructurados sin datos sensibles de pacientes.
- RNF-051. Métricas de latencia, errores, disponibilidad y volumen.
- RNF-052. Correlación de operaciones distribuidas cuando corresponda.
- RNF-053. Alertas operativas ante fallos de dispensación, discrepancias de inventario y accesos anómalos.

## Portabilidad y mantenibilidad
- RNF-060. Ejecutarse en Linux.
- RNF-061. Poder contenerizarse.
- RNF-062. Separar configuraciones de desarrollo, pruebas y producción.
- RNF-063. Usar tecnologías con mantenimiento activo y documentación suficiente.
