 # Decisiones de Base de Datos

*Proyecto:* Sistema de Farmacia  
*Fecha base de análisis:* 2026-09-17  
*Estado del documento:* Consolidado para iniciar la fase de ingeniería de base de datos

 ## 0. Propósito

Este documento consolida exclusivamente las decisiones que condicionan el diseño, construcción, despliegue y validación de la base de datos.

Las decisiones se derivan de los siguientes artefactos existentes:

- 01_requirements_analysis.md
- 02_decision_scope.md
- 03_architecture_options.md
- 04_database_analysis.md
- 05_security_analysis.md
- 06_devops_analysis.md
- 07_architecture_review.md
- 08_recomendacion_final.md

Este archivo debe ser leído antes de generar los artefactos de proyecto/05_base_datos/.

### Estados utilizados

- *APROBADO:* decisión consolidada en la recomendación final.
- *APROBADO CON CONDICIÓN:* decisión adoptada, pero depende de una condición o validación posterior.
- *PENDIENTE:* no existe información suficiente para cerrar la decisión.
- *NO APLICA INICIALMENTE:* alternativa descartada para la primera versión, pero puede reevaluarse con evidencia.

# 1. Tipo de persistencia

## Estado

*APROBADO*

## Decisión

La persistencia principal será *relacional y transaccional*.

La base de datos deberá soportar:

- integridad referencial;
- transacciones ACID;
- control de concurrencia a nivel de fila;
- restricciones de unicidad;
- consultas operativas;
- auditoría;
- recuperación ante fallos;
- crecimiento por sucursales;
- integración entre inventario, ventas, pagos y devoluciones.

## Justificación

El sistema maneja inventario compartido entre POS y web, ventas, pagos, devoluciones, compras y transferencias. Estas operaciones requieren consistencia fuerte y relaciones entre entidades.

## Requisitos relacionados

RF-041
RF-043   
RF-042   
RF-051
RF-053   
RF-055
RF-045   
RF-047   
RNF-005
RNF-020
RNF-021

---

# 2. Motor de base de datos

## Estado

*APROBADO*

## Decisión

Utilizar *MySQL 8.x con InnoDB* como motor principal de base de datos.

## Alternativas analizadas

- PostgreSQL
- MySQL con InnoDB
- PostgreSQL + Redis

## Justificación

MySQL 8.x con InnoDB será el motor principal por:

- transacciones ACID;
- MVCC de InnoDB;
- bloqueo a nivel de fila;
- SELECT ... FOR UPDATE;
- SKIP LOCKED disponible en MySQL 8.x;
- SAVEPOINT;
- índices B-tree y FULLTEXT;
- tipo JSON;
- particionamiento;
- replicación;
- binary log (binlog);
- recuperación point-in-time basada en backups + binlog;
- amplio soporte operativo y despliegue en contenedores.

Esta decisión implica aceptar que algunas capacidades específicas de PostgreSQL
(JSONB avanzado, extensiones, LISTEN/NOTIFY, pgAudit) no estarán disponibles de la
misma forma y deberán resolverse mediante mecanismos equivalentes de MySQL o de la aplicación.

## Decisión sobre Redis

*NO APLICA INICIALMENTE.*

Redis no será parte de la persistencia inicial.

Solo deberá reconsiderarse si las métricas reales demuestran una necesidad que PostgreSQL no pueda resolver de forma adecuada, por ejemplo un hot-path de stock con latencia extremadamente baja o una carga de lectura muy superior a la prevista.

---

# 3. Organización de la base de datos por módulos

## Estado

*APROBADO*

## Decisión

Se utilizará *una instancia MySQL 8.x*. Dado que en MySQL schema y database son equivalentes, la separación modular no se implementará mediante schemas internos como en PostgreSQL.

La separación modular deberá mantenerse a nivel de diseño y nombres.

text
Opción recomendada inicial:
una sola base de datos + tablas organizadas por dominio

auth_users
auth_roles
inventory_stock
inventory_reservations
sales_orders
sales_order_items
payments_transactions

La segunda alternativa aumenta la complejidad transaccional entre módulos y deberá
justificarse antes de aplicarla.

## Reglas

- Preferir una única base de datos transaccional para mantener transacciones ACID simples.
- Conservar límites de dominio mediante convenciones de nombres y capas del backend.
- Evitar separar físicamente módulos si eso rompe transacciones críticas de inventario/venta.
- Los eventos entre módulos deberán seguir la estrategia Outbox cuando corresponda.

---
# 4. Estrategia de concurrencia de inventario

## Estado

*APROBADO CON CONDICIÓN*

## Decisión

Utilizar una estrategia híbrida:

text
Reserva temporal
+
available / reserved
+
lock breve durante confirmación


Modelo conceptual:

text
stock_available
stock_reserved
stock_sold


Las reservas tendrán:

text
status
expires_at
created_at


Estados mínimos previstos:

text
pending
confirmed
expired
cancelled


## Regla de confirmación

Durante la confirmación de una operación crítica se utilizará bloqueo de fila de InnoDB:

sql
SELECT ... FOR UPDATE;


y, cuando el diseño definitivo lo justifique en MySQL 8.x:

sql
SELECT ... FOR UPDATE SKIP LOCKED;


Se deberá revisar cuidadosamente el orden de acceso a filas y tablas para reducir
el riesgo de deadlocks en InnoDB.

## Objetivo

Evitar sobreventa entre:

- POS;
- canal web;
- operaciones simultáneas sobre el mismo producto y sucursal.

## Pendientes

Todavía debe confirmarse:

1. si una reserva web bloquea efectivamente el stock disponible para POS;
2. el comportamiento de negocio exacto cuando una reserva expira;
3. la cantidad máxima de productos involucrados simultáneamente en una transferencia.

Hasta que esas decisiones sean confirmadas, el modelo deberá mantener estas reglas como parámetros configurables.

---

# 5. Modelo transaccional

## Estado

*APROBADO*

## Decisión

Utilizar un modelo híbrido:

text
ACID para el dominio interno
+
Saga para integración con pagos externos


## ACID

Las operaciones internas críticas deberán agruparse en transacciones cortas.

Ejemplos:

- reservar stock;
- crear venta;
- confirmar inventario;
- registrar recepción;
- registrar devolución interna;
- actualizar estados relacionados.

## Saga

La interacción con gateways de pago externos no debe mantener abierta una transacción SQL mientras se espera la respuesta externa.

Flujo conceptual:

text
Reservar stock
    ↓
Crear orden
    ↓
Registrar evento Outbox
    ↓
Procesar pago externo
    ↓
Webhook / confirmación
    ↓
Confirmar venta

si falla:
    ↓
Compensar reserva


## Regla

Las operaciones externas deberán ser desacopladas de las transacciones de base de datos de larga duración.

---

# 6. Estrategia de idempotencia

## Estado

*APROBADO*

## Decisión

Combinar:

text
Idempotency Key
+
UNIQUE constraints


## Idempotency Key

Las operaciones críticas provenientes de API deberán poder asociarse a un UUID de idempotencia.

Se prevé una estructura dedicada como:

text
idempotency_keys


con retención temporal y limpieza periódica.

## Constraints

La base deberá proteger campos naturales únicos, por ejemplo:

text
order_number
payment_reference


mediante restricciones UNIQUE.

## Retención propuesta

El análisis previo propone inicialmente:

text
30 días


para registros de idempotencia.

Esta retención deberá validarse durante el diseño físico.

---

# 7. Integridad de datos

## Estado

*APROBADO COMO PRINCIPIO*

## Decisión

El modelo físico deberá utilizar restricciones de base de datos para garantizar la integridad.

Se deberán evaluar y justificar:

- PRIMARY KEY;
- FOREIGN KEY;
- UNIQUE;
- CHECK;
- NOT NULL;
- DEFAULT.

## Regla

Toda restricción deberá relacionarse con:

- un RF;
- un RNF;
- una regla de negocio;
- una decisión arquitectónica.

No se debe delegar toda la integridad únicamente a la aplicación.

---

# 8. Entidades críticas ya identificadas

## Estado

*REFERENCIA PARA MODELADO*

La recomendación final identifica, como mínimo, las siguientes estructuras conceptuales.

### Inventario

text
inventory


Atributos conceptuales:

text
id
product_id
store_id
stock_available
stock_reserved
stock_sold
version


### Reservas

text
reservations


Atributos conceptuales:

text
id
order_id
product_id
store_id
qty
status
expires_at
created_at


### Órdenes

text
orders


Atributos conceptuales:

text
id
order_number
channel
customer_id
store_id
status
total
idempotency_key
created_at


### Pagos

text
payments


Atributos conceptuales:

text
id
order_id
provider
reference
amount
status
idempotency_key
created_at


### Blacklist de tokens

text
token_blacklist


Conceptualmente:

text
token_hash
expires_at


### Configuración del sistema

text
system_config


Conceptualmente:

text
key
value
updated_at
updated_by


## Importante

Estas estructuras *no sustituyen* el proceso formal de:

- modelo conceptual;
- modelo lógico;
- normalización;
- diseño físico.

El workflow de base de datos deberá validar, completar o corregir estos candidatos contra todos los RF/RNF.

---

# 9. Auditoría

## Estado

*APROBADO COMO REQUISITO / DISEÑO DETALLADO PENDIENTE*

## Decisión

El sistema requiere auditoría de operaciones sensibles y trazabilidad de cambios.

La auditoría se implementará mediante una combinación de:

- tablas de auditoría;
- triggers cuando corresponda;
- logs de aplicación;
- binary log para recuperación y trazabilidad operativa.


## También deberá existir trazabilidad de negocio

En particular:

- movimientos de inventario;
- devoluciones;
- operaciones administrativas;
- cambios relevantes en configuración;
- acciones sensibles;
- usuario responsable;
- fecha/hora;
- motivo cuando corresponda.

## Inventario

Los movimientos de inventario deberán conservarse como registros históricos y no solamente como el valor actual del stock.

Se ha identificado conceptualmente:

text
inventory_movements


como log append-only.

## Devoluciones

Las devoluciones deberán registrar:

- orden original;
- usuario;
- fecha;
- motivo;
- productos;
- cantidades;
- monto;
- resultado del reembolso.

---

# 10. Eliminación de registros e histórico

## Estado

*APROBADO PARCIALMENTE*

## Decisión

Las transacciones confirmadas *no deben eliminarse físicamente*.

Especialmente:

- órdenes confirmadas;
- ventas;
- movimientos de inventario;
- devoluciones;
- pagos relevantes para trazabilidad.

Una devolución deberá crear un nuevo registro relacionado con la transacción original, en lugar de eliminar o reemplazar el registro original.

## Pendiente

La estrategia concreta para entidades maestras no transaccionales todavía debe definirse.

Durante el modelado se deberá decidir por entidad entre:

- eliminación física;
- soft delete;
- vigencia temporal;
- inactivación mediante estado.

No se aplicará soft delete automáticamente a todas las tablas.

---

# 11. Versionamiento del esquema y migraciones

## Estado

*PENDIENTE DE IMPLEMENTACIÓN*

## Decisión

Todos los cambios estructurales deberán estar versionados mediante migraciones.

El análisis reconoce compatibilidad con herramientas como:

- Flyway;
- Liquibase;

pero todavía no existe una herramienta final aprobada.

## Reglas

- No modificar manualmente producción sin migración.
- Cada cambio deberá tener versión.
- Las migraciones deberán poder auditarse.
- Se deberá definir estrategia de rollback o forward-fix.
- El esquema desplegado deberá poder compararse con el modelo esperado.

## Decisión pendiente

Seleccionar durante la fase de ingeniería:

text
Flyway
o
Liquibase
o
migraciones nativas del stack finalmente elegido


---

# 12. Índices y rendimiento

## Estado

*APROBADO COMO ESTRATEGIA / DISEÑO DETALLADO PENDIENTE*

## Decisión

Los índices deberán derivarse de los patrones reales de acceso.

No se crearán índices indiscriminadamente.

## Criterios

Cada índice deberá asociarse a:

- consulta;
- RF/RNF;
- FK;
- constraint;
- ordenamiento;
- búsqueda;
- patrón de reporte.

## MySQL

Se podrán evaluar:

- B-tree;
- FULLTEXT;
- índices compuestos;
- índices sobre columnas generadas;
- índices funcionales cuando sean compatibles con la versión utilizada.

MySQL no ofrece equivalentes directos de GIN, GiST o BRIN; cualquier diseño que
dependa de esas capacidades deberá replantearse.

## Particionamiento

Se propone considerar particionamiento para tablas transaccionales cuando el volumen lo justifique.

La recomendación existente indica evaluar particionamiento de ventas/inventario por fecha cuando las tablas superen aproximadamente:

text
1.000.000 de filas


Esta cifra deberá validarse con mediciones antes de aplicar particionamiento.

---

# 13. Reportes operativos

## Estado

*APROBADO CON CONDICIÓN*

## Decisión

Estrategia prevista:

text
vistas materializadas
+
consultas directas controladas


Refresh inicial propuesto:

text
cada 5 minutos


## Escalamiento

Si la carga de reportes afecta al OLTP, evaluar:

text
read replica


antes de introducir una arquitectura analítica independiente.

## Pendiente

Confirmar si los reportes requieren:

- tiempo real;
- near-real-time;
- latencia de aproximadamente 5 minutos.

---

# 14. Seguridad de la conexión a base de datos

## Estado

*APROBADO*

## Decisiones

- No almacenar secretos en el repositorio.
- Utilizar variables de entorno durante desarrollo.
- Utilizar Docker Secrets en producción.
- Considerar un secret manager/vault si la infraestructura crece.
- Utilizar conexión cifrada.

MySQL deberá configurarse para conexiones seguras mediante TLS.

La configuración concreta dependerá del cliente utilizado, pero deberá requerirse
validación del certificado del servidor en producción siempre que sea viable.

## Credenciales

Las credenciales se leerán desde:

text
.env


con variables como:

env
DB_ENGINE=mysql
DB_HOST=
DB_PORT=3306
DB_NAME=
DB_USER=
DB_PASSWORD=


.env no debe ser versionado.

## Pendiente de diseño físico

Definir usuarios separados, como mínimo, para:

- aplicación;
- migraciones;
- reportes;
- administración/DBA.

Aplicar principio de mínimo privilegio.

---

# 15. Passwords, tokens y datos sensibles

## Estado

*APROBADO*

## Contraseñas

Las contraseñas de usuarios no se almacenarán en texto plano.

La recomendación consolidada especifica:

text
Argon2id


para hash de contraseñas.

## Tokens

La revocación de JWT utilizará una tabla como:

text
token_blacklist


almacenando hash del token y su expiración.

También se prevé almacenamiento de refresh tokens con TTL controlado.

## Regla

No almacenar tokens sensibles en texto plano cuando no sea necesario.

---

# 16. Outbox y eventos persistentes

## Estado

*APROBADO*

## Decisión

Utilizar patrón *Outbox* para desacoplar eventos importantes sin perder consistencia con la transacción de dominio.

Principal aplicación:

text
ventas / órdenes
        ↓
outbox
        ↓
worker
        ↓
gateway de pagos
        ↓
webhook


El registro Outbox deberá insertarse dentro de la misma transacción de negocio que genera el evento correspondiente.

---

# 17. Backup y recuperación

## Estado

*APROBADO CON VALORES PENDIENTES DE VALIDACIÓN*

## Decisión técnica base

Utilizar una estrategia basada en:

text
backup completo periódico
+
binary log (binlog) habilitado


para permitir recuperación point-in-time.

Herramientas posibles:
- mysqldump para entornos pequeños o copias lógicas;
- MySQL Shell Dump & Load;
- soluciones de backup físico compatibles con MySQL para producción.

La herramienta definitiva se seleccionará según volumen, RPO/RTO y entorno.

## Valores provisionales

text
RPO < 1 hora
RTO < 4 horas


## Importante

Los valores anteriores son *provisionales* y deben ser confirmados por el usuario/negocio.

No deben considerarse SLA definitivos hasta su validación.

## Prueba obligatoria

La estrategia de backup no se considerará válida hasta ejecutar una prueba de restauración.

---

# 18. Alta disponibilidad y escalabilidad

## Estado

*NO REQUERIDA INICIALMENTE*

## Decisión

No implementar inicialmente:

- sharding;
- cluster distribuido;
- múltiples nodos por defecto;
- Redis para caché de stock;
- infraestructura compleja de alta disponibilidad sin necesidad demostrada.

## Evolución

Si las métricas lo justifican, evaluar en este orden:

1. optimización de queries;
2. índices;
3. connection pooling;
4. read replica;
5. particionamiento;
6. escalamiento adicional.

---

# 19. Connection pooling

## Estado

*APROBADO COMO NECESIDAD / HERRAMIENTA PENDIENTE*

## Decisión

La aplicación deberá utilizar pooling de conexiones.

La implementación puede realizarse mediante:
- pool nativo del driver/framework;
- ProxySQL;
- MySQL Router, si la arquitectura lo requiere.

Rango inicial orientativo:

text
50–100 conexiones


El valor definitivo deberá obtenerse mediante pruebas de carga y considerando
max_connections, latencia y patrón de uso.

---

# 20. Capacidades específicas de MySQL

## Estado

*APROBADAS PARA EVALUACIÓN DURANTE EL DISEÑO FÍSICO*

MySQL no utiliza un modelo de extensiones equivalente al de PostgreSQL.

Capacidades que deberán evaluarse según necesidad:

text
JSON
FULLTEXT
generated columns
functional indexes
Event Scheduler
Performance Schema
binary log


## Regla

No habilitar funcionalidades únicamente porque estén disponibles.

Cada capacidad deberá justificarse por un requisito concreto.

---

# 21. Configuración operativa en base de datos

## Estado

*APROBADO*

## Decisión

RF-100 requiere modificar parámetros sin redeploy.

Se utilizará conceptualmente:

text
system_config


La tabla deberá permitir:

- claves únicas;
- valores configurables;
- fecha de actualización;
- usuario que realizó la modificación.

## Pendiente

Durante el modelado físico se deberá definir:

- tipos de valores;
- validación;
- categorías;
- alcance global o por sucursal;
- auditoría;
- cache e invalidación.

---

# 22. Estrategia de devoluciones

## Estado

*APROBADO A NIVEL ARQUITECTÓNICO*

## Decisión

Las devoluciones se modelarán como nuevas transacciones relacionadas con la orden original.

Flujo:

text
orden original
    ↓
return_order
    ↓
actualización de inventario
    ↓
reembolso externo
    ↓
confirmación / compensación


## Principio

No modificar destructivamente el historial original.

La devolución deberá conservar:

- vínculo con la orden original;
- productos;
- cantidades;
- monto;
- estado;
- motivo;
- usuario;
- timestamps.

---

# 23. Decisiones todavía pendientes

Las siguientes decisiones no deben inventarse durante el diseño.

| ID | Decisión pendiente | Impacto |
|---|---|---|
| DB-P01 | Confirmar si la reserva web bloquea stock para POS | concurrencia, reservas, locks |
| DB-P02 | Confirmar comportamiento exacto al expirar una reserva | diseño de reservas y job |
| DB-P03 | Confirmar máximo de productos por transferencia | granularidad y orden de locks |
| DB-P04 | Confirmar proveedor(es) de pago iniciales | Saga, Outbox, estados |
| DB-P05 | Validar RPO y RTO | backup, PITR, replicación |
| DB-P06 | Confirmar si POS debe operar offline | sincronización y conflictos |
| DB-P07 | Confirmar latencia aceptable de reportes | vistas materializadas / réplica |
| DB-P08 | Definir modelo definitivo de clientes | claves, alcance, historial |
| DB-P09 | Definir precios globales o por sucursal | modelo de productos/precios |
| DB-P10 | Detallar fulfillment: entrega vs retiro | reservas, sucursales, estados |
| DB-P11 | Seleccionar herramienta final de migraciones | versionamiento del esquema |
| DB-P12 | Definir estrategia de eliminación por cada entidad maestra | histórico y soft delete |

---

# 24. Trazabilidad de decisiones de base de datos

| Decisión | Fuente principal | RF/RNF relacionados | Estado |
|---|---|---|---|
| MySQL 8.x / InnoDB | D-09 | RF-064, RNF-020, RNF-021, RNF-001, RNF-011 | APROBADO |
| Reserva temporal + lock | D-01 | RF-062, RF-064, RNF-005, RNF-020 | APROBADO CON CONDICIÓN |
| ACID + Saga pagos | D-02 | RF-050, RF-063, RNF-021 | APROBADO |
| Idempotency key + UNIQUE | D-03 | RF-042, RNF-022 | APROBADO |
| Schemas por módulo | arquitectura final | Modular Monolith | APROBADO |
| Outbox | arquitectura final | RF-063, RNF-021, RNF-042 | APROBADO |
| Auditoría | persistencia/seguridad | RF-090, RF-070 | APROBADO COMO REQUISITO |
| No eliminar transacciones confirmadas | RN-08 | integridad/auditoría | APROBADO |
| Backup + WAL | persistencia | RNF-040, RNF-041 | APROBADO CON VALIDACIÓN |
| Vistas materializadas | reportes | RF-080 | APROBADO CON CONDICIÓN |
| Configuración en BD | configuración | RF-100, RNF-002 | APROBADO |
| Redis fuera de fase inicial | D-09 | simplicidad operativa | NO APLICA INICIALMENTE |

---

# 25. Reglas para la fase proyecto/05_base_datos/

Antes de crear tablas o scripts SQL, el agente deberá:

1. leer proyecto/00_contexto/;
2. leer proyecto/01_requisitos/;
3. leer proyecto/02_configuracion/;
4. revisar proyecto/03_resultados/;
5. leer este archivo;
6. respetar todas las decisiones con estado *APROBADO*;
7. mantener explícitas las decisiones *PENDIENTES*;
8. no asumir respuestas inexistentes;
9. generar trazabilidad entre requisitos y modelo de datos.

## Orden esperado

text
requisitos de datos
    ↓
modelo conceptual
    ↓
diagrama E-R
    ↓
modelo lógico
    ↓
normalización
    ↓
modelo físico MySQL
    ↓
integridad
    ↓
seguridad
    ↓
auditoría
    ↓
versionamiento
    ↓
índices y rendimiento
    ↓
transacciones y concurrencia
    ↓
migraciones
    ↓
revisión DBA
    ↓
SQL
    ↓
despliegue
    ↓
pruebas


---

# 26. Criterio para modificar este documento

Una decisión aprobada solamente podrá modificarse cuando exista:

- un nuevo RF/RNF;
- una regla de negocio nueva;
- evidencia de pruebas;
- una restricción tecnológica;
- una medición de rendimiento;
- una decisión explícita del responsable del proyecto.

Cualquier modificación deberá registrar:

text
Decisión anterior
Motivo del cambio
Nueva decisión
Impacto
Fecha
Fuente
 # Decisiones de Base de Datos

*Proyecto:* Sistema de Farmacia  
*Fecha base de análisis:* 2026-09-17  
*Estado del documento:* Consolidado para iniciar la fase de ingeniería de base de datos

 ## 0. Propósito

Este documento consolida exclusivamente las decisiones que condicionan el diseño, construcción, despliegue y validación de la base de datos.

Las decisiones se derivan de los siguientes artefactos existentes:

- 01_requirements_analysis.md
- 02_decision_scope.md
- 03_architecture_options.md
- 04_database_analysis.md
- 05_security_analysis.md
- 06_devops_analysis.md
- 07_architecture_review.md
- 08_recomendacion_final.md

Este archivo debe ser leído antes de generar los artefactos de proyecto/05_base_datos/.

### Estados utilizados

- *APROBADO:* decisión consolidada en la recomendación final.
- *APROBADO CON CONDICIÓN:* decisión adoptada, pero depende de una condición o validación posterior.
- *PENDIENTE:* no existe información suficiente para cerrar la decisión.
- *NO APLICA INICIALMENTE:* alternativa descartada para la primera versión, pero puede reevaluarse con evidencia.

# 1. Tipo de persistencia

## Estado

*APROBADO*

## Decisión

La persistencia principal será *relacional y transaccional*.

La base de datos deberá soportar:

- integridad referencial;
- transacciones ACID;
- control de concurrencia a nivel de fila;
- restricciones de unicidad;
- consultas operativas;
- auditoría;
- recuperación ante fallos;
- crecimiento por sucursales;
- integración entre inventario, ventas, pagos y devoluciones.

## Justificación

El sistema maneja inventario compartido entre POS y web, ventas, pagos, devoluciones, compras y transferencias. Estas operaciones requieren consistencia fuerte y relaciones entre entidades.

## Requisitos relacionados

RF-041
RF-043   
RF-042   
RF-051
RF-053   
RF-055
RF-045   
RF-047   
RNF-005
RNF-020
RNF-021

---

# 2. Motor de base de datos

## Estado

*APROBADO*

## Decisión

Utilizar *MySQL 8.x con InnoDB* como motor principal de base de datos.

## Alternativas analizadas

- PostgreSQL
- MySQL con InnoDB
- PostgreSQL + Redis

## Justificación

MySQL 8.x con InnoDB será el motor principal por:

- transacciones ACID;
- MVCC de InnoDB;
- bloqueo a nivel de fila;
- SELECT ... FOR UPDATE;
- SKIP LOCKED disponible en MySQL 8.x;
- SAVEPOINT;
- índices B-tree y FULLTEXT;
- tipo JSON;
- particionamiento;
- replicación;
- binary log (binlog);
- recuperación point-in-time basada en backups + binlog;
- amplio soporte operativo y despliegue en contenedores.

Esta decisión implica aceptar que algunas capacidades específicas de PostgreSQL
(JSONB avanzado, extensiones, LISTEN/NOTIFY, pgAudit) no estarán disponibles de la
misma forma y deberán resolverse mediante mecanismos equivalentes de MySQL o de la aplicación.

## Decisión sobre Redis

*NO APLICA INICIALMENTE.*

Redis no será parte de la persistencia inicial.

Solo deberá reconsiderarse si las métricas reales demuestran una necesidad que PostgreSQL no pueda resolver de forma adecuada, por ejemplo un hot-path de stock con latencia extremadamente baja o una carga de lectura muy superior a la prevista.

---

# 3. Organización de la base de datos por módulos

## Estado

*APROBADO*

## Decisión

Se utilizará *una instancia MySQL 8.x*. Dado que en MySQL schema y database son equivalentes, la separación modular no se implementará mediante schemas internos como en PostgreSQL.

La separación modular deberá mantenerse a nivel de diseño y nombres.

text
Opción recomendada inicial:
una sola base de datos + tablas organizadas por dominio

auth_users
auth_roles
inventory_stock
inventory_reservations
sales_orders
sales_order_items
payments_transactions

La segunda alternativa aumenta la complejidad transaccional entre módulos y deberá
justificarse antes de aplicarla.

## Reglas

- Preferir una única base de datos transaccional para mantener transacciones ACID simples.
- Conservar límites de dominio mediante convenciones de nombres y capas del backend.
- Evitar separar físicamente módulos si eso rompe transacciones críticas de inventario/venta.
- Los eventos entre módulos deberán seguir la estrategia Outbox cuando corresponda.

---
# 4. Estrategia de concurrencia de inventario

## Estado

*APROBADO CON CONDICIÓN*

## Decisión

Utilizar una estrategia híbrida:

text
Reserva temporal
+
available / reserved
+
lock breve durante confirmación


Modelo conceptual:

text
stock_available
stock_reserved
stock_sold


Las reservas tendrán:

text
status
expires_at
created_at


Estados mínimos previstos:

text
pending
confirmed
expired
cancelled


## Regla de confirmación

Durante la confirmación de una operación crítica se utilizará bloqueo de fila de InnoDB:

sql
SELECT ... FOR UPDATE;


y, cuando el diseño definitivo lo justifique en MySQL 8.x:

sql
SELECT ... FOR UPDATE SKIP LOCKED;


Se deberá revisar cuidadosamente el orden de acceso a filas y tablas para reducir
el riesgo de deadlocks en InnoDB.

## Objetivo

Evitar sobreventa entre:

- POS;
- canal web;
- operaciones simultáneas sobre el mismo producto y sucursal.

## Pendientes

Todavía debe confirmarse:

1. si una reserva web bloquea efectivamente el stock disponible para POS;
2. el comportamiento de negocio exacto cuando una reserva expira;
3. la cantidad máxima de productos involucrados simultáneamente en una transferencia.

Hasta que esas decisiones sean confirmadas, el modelo deberá mantener estas reglas como parámetros configurables.

---

# 5. Modelo transaccional

## Estado

*APROBADO*

## Decisión

Utilizar un modelo híbrido:

text
ACID para el dominio interno
+
Saga para integración con pagos externos


## ACID

Las operaciones internas críticas deberán agruparse en transacciones cortas.

Ejemplos:

- reservar stock;
- crear venta;
- confirmar inventario;
- registrar recepción;
- registrar devolución interna;
- actualizar estados relacionados.

## Saga

La interacción con gateways de pago externos no debe mantener abierta una transacción SQL mientras se espera la respuesta externa.

Flujo conceptual:

text
Reservar stock
    ↓
Crear orden
    ↓
Registrar evento Outbox
    ↓
Procesar pago externo
    ↓
Webhook / confirmación
    ↓
Confirmar venta

si falla:
    ↓
Compensar reserva


## Regla

Las operaciones externas deberán ser desacopladas de las transacciones de base de datos de larga duración.

---

# 6. Estrategia de idempotencia

## Estado

*APROBADO*

## Decisión

Combinar:

text
Idempotency Key
+
UNIQUE constraints


## Idempotency Key

Las operaciones críticas provenientes de API deberán poder asociarse a un UUID de idempotencia.

Se prevé una estructura dedicada como:

text
idempotency_keys


con retención temporal y limpieza periódica.

## Constraints

La base deberá proteger campos naturales únicos, por ejemplo:

text
order_number
payment_reference


mediante restricciones UNIQUE.

## Retención propuesta

El análisis previo propone inicialmente:

text
30 días


para registros de idempotencia.

Esta retención deberá validarse durante el diseño físico.

---

# 7. Integridad de datos

## Estado

*APROBADO COMO PRINCIPIO*

## Decisión

El modelo físico deberá utilizar restricciones de base de datos para garantizar la integridad.

Se deberán evaluar y justificar:

- PRIMARY KEY;
- FOREIGN KEY;
- UNIQUE;
- CHECK;
- NOT NULL;
- DEFAULT.

## Regla

Toda restricción deberá relacionarse con:

- un RF;
- un RNF;
- una regla de negocio;
- una decisión arquitectónica.

No se debe delegar toda la integridad únicamente a la aplicación.

---

# 8. Entidades críticas ya identificadas

## Estado

*REFERENCIA PARA MODELADO*

La recomendación final identifica, como mínimo, las siguientes estructuras conceptuales.

### Inventario

text
inventory


Atributos conceptuales:

text
id
product_id
store_id
stock_available
stock_reserved
stock_sold
version


### Reservas

text
reservations


Atributos conceptuales:

text
id
order_id
product_id
store_id
qty
status
expires_at
created_at


### Órdenes

text
orders


Atributos conceptuales:

text
id
order_number
channel
customer_id
store_id
status
total
idempotency_key
created_at


### Pagos

text
payments


Atributos conceptuales:

text
id
order_id
provider
reference
amount
status
idempotency_key
created_at


### Blacklist de tokens

text
token_blacklist


Conceptualmente:

text
token_hash
expires_at


### Configuración del sistema

text
system_config


Conceptualmente:

text
key
value
updated_at
updated_by


## Importante

Estas estructuras *no sustituyen* el proceso formal de:

- modelo conceptual;
- modelo lógico;
- normalización;
- diseño físico.

El workflow de base de datos deberá validar, completar o corregir estos candidatos contra todos los RF/RNF.

---

# 9. Auditoría

## Estado

*APROBADO COMO REQUISITO / DISEÑO DETALLADO PENDIENTE*

## Decisión

El sistema requiere auditoría de operaciones sensibles y trazabilidad de cambios.

La auditoría se implementará mediante una combinación de:

- tablas de auditoría;
- triggers cuando corresponda;
- logs de aplicación;
- binary log para recuperación y trazabilidad operativa.


## También deberá existir trazabilidad de negocio

En particular:

- movimientos de inventario;
- devoluciones;
- operaciones administrativas;
- cambios relevantes en configuración;
- acciones sensibles;
- usuario responsable;
- fecha/hora;
- motivo cuando corresponda.

## Inventario

Los movimientos de inventario deberán conservarse como registros históricos y no solamente como el valor actual del stock.

Se ha identificado conceptualmente:

text
inventory_movements


como log append-only.

## Devoluciones

Las devoluciones deberán registrar:

- orden original;
- usuario;
- fecha;
- motivo;
- productos;
- cantidades;
- monto;
- resultado del reembolso.

---

# 10. Eliminación de registros e histórico

## Estado

*APROBADO PARCIALMENTE*

## Decisión

Las transacciones confirmadas *no deben eliminarse físicamente*.

Especialmente:

- órdenes confirmadas;
- ventas;
- movimientos de inventario;
- devoluciones;
- pagos relevantes para trazabilidad.

Una devolución deberá crear un nuevo registro relacionado con la transacción original, en lugar de eliminar o reemplazar el registro original.

## Pendiente

La estrategia concreta para entidades maestras no transaccionales todavía debe definirse.

Durante el modelado se deberá decidir por entidad entre:

- eliminación física;
- soft delete;
- vigencia temporal;
- inactivación mediante estado.

No se aplicará soft delete automáticamente a todas las tablas.

---

# 11. Versionamiento del esquema y migraciones

## Estado

*PENDIENTE DE IMPLEMENTACIÓN*

## Decisión

Todos los cambios estructurales deberán estar versionados mediante migraciones.

El análisis reconoce compatibilidad con herramientas como:

- Flyway;
- Liquibase;

pero todavía no existe una herramienta final aprobada.

## Reglas

- No modificar manualmente producción sin migración.
- Cada cambio deberá tener versión.
- Las migraciones deberán poder auditarse.
- Se deberá definir estrategia de rollback o forward-fix.
- El esquema desplegado deberá poder compararse con el modelo esperado.

## Decisión pendiente

Seleccionar durante la fase de ingeniería:

text
Flyway
o
Liquibase
o
migraciones nativas del stack finalmente elegido


---

# 12. Índices y rendimiento

## Estado

*APROBADO COMO ESTRATEGIA / DISEÑO DETALLADO PENDIENTE*

## Decisión

Los índices deberán derivarse de los patrones reales de acceso.

No se crearán índices indiscriminadamente.

## Criterios

Cada índice deberá asociarse a:

- consulta;
- RF/RNF;
- FK;
- constraint;
- ordenamiento;
- búsqueda;
- patrón de reporte.

## MySQL

Se podrán evaluar:

- B-tree;
- FULLTEXT;
- índices compuestos;
- índices sobre columnas generadas;
- índices funcionales cuando sean compatibles con la versión utilizada.

MySQL no ofrece equivalentes directos de GIN, GiST o BRIN; cualquier diseño que
dependa de esas capacidades deberá replantearse.

## Particionamiento

Se propone considerar particionamiento para tablas transaccionales cuando el volumen lo justifique.

La recomendación existente indica evaluar particionamiento de ventas/inventario por fecha cuando las tablas superen aproximadamente:

text
1.000.000 de filas


Esta cifra deberá validarse con mediciones antes de aplicar particionamiento.

---

# 13. Reportes operativos

## Estado

*APROBADO CON CONDICIÓN*

## Decisión

Estrategia prevista:

text
vistas materializadas
+
consultas directas controladas


Refresh inicial propuesto:

text
cada 5 minutos


## Escalamiento

Si la carga de reportes afecta al OLTP, evaluar:

text
read replica


antes de introducir una arquitectura analítica independiente.

## Pendiente

Confirmar si los reportes requieren:

- tiempo real;
- near-real-time;
- latencia de aproximadamente 5 minutos.

---

# 14. Seguridad de la conexión a base de datos

## Estado

*APROBADO*

## Decisiones

- No almacenar secretos en el repositorio.
- Utilizar variables de entorno durante desarrollo.
- Utilizar Docker Secrets en producción.
- Considerar un secret manager/vault si la infraestructura crece.
- Utilizar conexión cifrada.

MySQL deberá configurarse para conexiones seguras mediante TLS.

La configuración concreta dependerá del cliente utilizado, pero deberá requerirse
validación del certificado del servidor en producción siempre que sea viable.

## Credenciales

Las credenciales se leerán desde:

text
.env


con variables como:

env
DB_ENGINE=mysql
DB_HOST=
DB_PORT=3306
DB_NAME=
DB_USER=
DB_PASSWORD=


.env no debe ser versionado.

## Pendiente de diseño físico

Definir usuarios separados, como mínimo, para:

- aplicación;
- migraciones;
- reportes;
- administración/DBA.

Aplicar principio de mínimo privilegio.

---

# 15. Passwords, tokens y datos sensibles

## Estado

*APROBADO*

## Contraseñas

Las contraseñas de usuarios no se almacenarán en texto plano.

La recomendación consolidada especifica:

text
Argon2id


para hash de contraseñas.

## Tokens

La revocación de JWT utilizará una tabla como:

text
token_blacklist


almacenando hash del token y su expiración.

También se prevé almacenamiento de refresh tokens con TTL controlado.

## Regla

No almacenar tokens sensibles en texto plano cuando no sea necesario.

---

# 16. Outbox y eventos persistentes

## Estado

*APROBADO*

## Decisión

Utilizar patrón *Outbox* para desacoplar eventos importantes sin perder consistencia con la transacción de dominio.

Principal aplicación:

text
ventas / órdenes
        ↓
outbox
        ↓
worker
        ↓
gateway de pagos
        ↓
webhook


El registro Outbox deberá insertarse dentro de la misma transacción de negocio que genera el evento correspondiente.

---

# 17. Backup y recuperación

## Estado

*APROBADO CON VALORES PENDIENTES DE VALIDACIÓN*

## Decisión técnica base

Utilizar una estrategia basada en:

text
backup completo periódico
+
binary log (binlog) habilitado


para permitir recuperación point-in-time.

Herramientas posibles:
- mysqldump para entornos pequeños o copias lógicas;
- MySQL Shell Dump & Load;
- soluciones de backup físico compatibles con MySQL para producción.

La herramienta definitiva se seleccionará según volumen, RPO/RTO y entorno.

## Valores provisionales

text
RPO < 1 hora
RTO < 4 horas


## Importante

Los valores anteriores son *provisionales* y deben ser confirmados por el usuario/negocio.

No deben considerarse SLA definitivos hasta su validación.

## Prueba obligatoria

La estrategia de backup no se considerará válida hasta ejecutar una prueba de restauración.

---

# 18. Alta disponibilidad y escalabilidad

## Estado

*NO REQUERIDA INICIALMENTE*

## Decisión

No implementar inicialmente:

- sharding;
- cluster distribuido;
- múltiples nodos por defecto;
- Redis para caché de stock;
- infraestructura compleja de alta disponibilidad sin necesidad demostrada.

## Evolución

Si las métricas lo justifican, evaluar en este orden:

1. optimización de queries;
2. índices;
3. connection pooling;
4. read replica;
5. particionamiento;
6. escalamiento adicional.

---

# 19. Connection pooling

## Estado

*APROBADO COMO NECESIDAD / HERRAMIENTA PENDIENTE*

## Decisión

La aplicación deberá utilizar pooling de conexiones.

La implementación puede realizarse mediante:
- pool nativo del driver/framework;
- ProxySQL;
- MySQL Router, si la arquitectura lo requiere.

Rango inicial orientativo:

text
50–100 conexiones


El valor definitivo deberá obtenerse mediante pruebas de carga y considerando
max_connections, latencia y patrón de uso.

---

# 20. Capacidades específicas de MySQL

## Estado

*APROBADAS PARA EVALUACIÓN DURANTE EL DISEÑO FÍSICO*

MySQL no utiliza un modelo de extensiones equivalente al de PostgreSQL.

Capacidades que deberán evaluarse según necesidad:

text
JSON
FULLTEXT
generated columns
functional indexes
Event Scheduler
Performance Schema
binary log


## Regla

No habilitar funcionalidades únicamente porque estén disponibles.

Cada capacidad deberá justificarse por un requisito concreto.

---

# 21. Configuración operativa en base de datos

## Estado

*APROBADO*

## Decisión

RF-100 requiere modificar parámetros sin redeploy.

Se utilizará conceptualmente:

text
system_config


La tabla deberá permitir:

- claves únicas;
- valores configurables;
- fecha de actualización;
- usuario que realizó la modificación.

## Pendiente

Durante el modelado físico se deberá definir:

- tipos de valores;
- validación;
- categorías;
- alcance global o por sucursal;
- auditoría;
- cache e invalidación.

---

# 22. Estrategia de devoluciones

## Estado

*APROBADO A NIVEL ARQUITECTÓNICO*

## Decisión

Las devoluciones se modelarán como nuevas transacciones relacionadas con la orden original.

Flujo:

text
orden original
    ↓
return_order
    ↓
actualización de inventario
    ↓
reembolso externo
    ↓
confirmación / compensación


## Principio

No modificar destructivamente el historial original.

La devolución deberá conservar:

- vínculo con la orden original;
- productos;
- cantidades;
- monto;
- estado;
- motivo;
- usuario;
- timestamps.

---

# 23. Decisiones todavía pendientes

Las siguientes decisiones no deben inventarse durante el diseño.

| ID | Decisión pendiente | Impacto |
|---|---|---|
| DB-P01 | Confirmar si la reserva web bloquea stock para POS | concurrencia, reservas, locks |
| DB-P02 | Confirmar comportamiento exacto al expirar una reserva | diseño de reservas y job |
| DB-P03 | Confirmar máximo de productos por transferencia | granularidad y orden de locks |
| DB-P04 | Confirmar proveedor(es) de pago iniciales | Saga, Outbox, estados |
| DB-P05 | Validar RPO y RTO | backup, PITR, replicación |
| DB-P06 | Confirmar si POS debe operar offline | sincronización y conflictos |
| DB-P07 | Confirmar latencia aceptable de reportes | vistas materializadas / réplica |
| DB-P08 | Definir modelo definitivo de clientes | claves, alcance, historial |
| DB-P09 | Definir precios globales o por sucursal | modelo de productos/precios |
| DB-P10 | Detallar fulfillment: entrega vs retiro | reservas, sucursales, estados |
| DB-P11 | Seleccionar herramienta final de migraciones | versionamiento del esquema |
| DB-P12 | Definir estrategia de eliminación por cada entidad maestra | histórico y soft delete |

---

# 24. Trazabilidad de decisiones de base de datos

| Decisión | Fuente principal | RF/RNF relacionados | Estado |
|---|---|---|---|
| MySQL 8.x / InnoDB | D-09 | RF-064, RNF-020, RNF-021, RNF-001, RNF-011 | APROBADO |
| Reserva temporal + lock | D-01 | RF-062, RF-064, RNF-005, RNF-020 | APROBADO CON CONDICIÓN |
| ACID + Saga pagos | D-02 | RF-050, RF-063, RNF-021 | APROBADO |
| Idempotency key + UNIQUE | D-03 | RF-042, RNF-022 | APROBADO |
| Schemas por módulo | arquitectura final | Modular Monolith | APROBADO |
| Outbox | arquitectura final | RF-063, RNF-021, RNF-042 | APROBADO |
| Auditoría | persistencia/seguridad | RF-090, RF-070 | APROBADO COMO REQUISITO |
| No eliminar transacciones confirmadas | RN-08 | integridad/auditoría | APROBADO |
| Backup + WAL | persistencia | RNF-040, RNF-041 | APROBADO CON VALIDACIÓN |
| Vistas materializadas | reportes | RF-080 | APROBADO CON CONDICIÓN |
| Configuración en BD | configuración | RF-100, RNF-002 | APROBADO |
| Redis fuera de fase inicial | D-09 | simplicidad operativa | NO APLICA INICIALMENTE |

---

# 25. Reglas para la fase proyecto/05_base_datos/

Antes de crear tablas o scripts SQL, el agente deberá:

1. leer proyecto/00_contexto/;
2. leer proyecto/01_requisitos/;
3. leer proyecto/02_configuracion/;
4. revisar proyecto/03_resultados/;
5. leer este archivo;
6. respetar todas las decisiones con estado *APROBADO*;
7. mantener explícitas las decisiones *PENDIENTES*;
8. no asumir respuestas inexistentes;
9. generar trazabilidad entre requisitos y modelo de datos.

## Orden esperado

text
requisitos de datos
    ↓
modelo conceptual
    ↓
diagrama E-R
    ↓
modelo lógico
    ↓
normalización
    ↓
modelo físico MySQL
    ↓
integridad
    ↓
seguridad
    ↓
auditoría
    ↓
versionamiento
    ↓
índices y rendimiento
    ↓
transacciones y concurrencia
    ↓
migraciones
    ↓
revisión DBA
    ↓
SQL
    ↓
despliegue
    ↓
pruebas


---

# 26. Criterio para modificar este documento

Una decisión aprobada solamente podrá modificarse cuando exista:

- un nuevo RF/RNF;
- una regla de negocio nueva;
- evidencia de pruebas;
- una restricción tecnológica;
- una medición de rendimiento;
- una decisión explícita del responsable del proyecto.

Cualquier modificación deberá registrar:

text
Decisión anterior
Motivo del cambio
Nueva decisión
Impacto
Fecha
Fuente
---