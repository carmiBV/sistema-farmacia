<<<<<<< HEAD
# Validación de Arquitectura — Cliente y Químico Farmacéutico (Fase F1)

**Proyecto:** Sistema de Gestión de Farmacia (Control de Inventario y Dispensación)[cite: 11]  
**Estado:** COMPLETADO — Respuestas Firmadas[cite: 12]  
**Fecha:** 2026-10-02  
**Origen:** `09_validation_plan.md` (Fase F1)[cite: 12]  
**Documentos impactados:** `02_configuracion/perfil_carga.yaml`, `00_contexto/registro_cambios.md` (CAM-005)[cite: 7, 10]

---

## PARTE 1: Respuestas del Cliente / Negocio

### 1.1 Continuidad del Negocio y Recuperación (RPO / RTO)
* **P-01 | Pérdida máxima de datos tolerable (RPO):**
  * [X] Máximo 5 minutos. *(Decisión: Se acepta una ventana máxima de 5 minutos para evitar descuadres mayores en libros sanitarios e inventario)*.
* **P-01 | Tiempo máximo de recuperación de servicio (RTO):**
  * [X] Máximo 60 minutos (1 hora). *(Decisión: En caso de contingencia mayor en el nodo central, las sucursales pueden operar de forma temporal en degradado mientras se restaura el servicio)*.
* **Frecuencia de prueba de restauración (RNF-040):**[cite: 9, 10]
  * [X] Cada 90 días.

### 1.2 Capacidad y Perfil de Carga[cite: 10]
* **P-06 | Volumen transaccional:**
  * [X] Aprobado: **25.000 transacciones de negocio por hora** como referencia inicial[cite: 10].
* **Consultas de stock/producto:**
  * Estimación acordada: **50.000 consultas por hora** (picos de 150.000/h con multiplicador 3.0)[cite: 10].

### 1.3 Disponibilidad y Ventana Operativa[cite: 10]
* **Disponibilidad objetivo (RNF-044):**[cite: 9, 10]
  * [X] Aprobado: **99,9 %**[cite: 10].
* **Base de medición:**
  * [X] **Horario de atención comercial** (definido sobre el horario operativo de las 8 sucursales)[cite: 10].

### 1.4 Retención Legal de Datos y Normativa (RNF-038, RNF-039)[cite: 9, 10]
* **P-02 | Dispensaciones y Ventas:** **5 años**.
* **P-02 | Recetas Médicas:** **5 años**.
* **P-02 | Libro de Medicamentos Controlados:** **10 años** (conforme a exigencia de la autoridad sanitaria).
* **P-02 | Registros de Auditoría:** **5 años**.

### 1.5 Modo Degradado y Trazabilidad[cite: 10]
* **Venta libre sin conexión:**
  * [X] **Sí**, se permite continuar vendiendo productos de venta libre sin conexión, registrando la cola de transacciones localmente y conciliando al reconectar[cite: 11, 12].
* **P-16 | Ventana de Idempotencia:**
  * [X] **7 días** (las claves de idempotencia para cobro, recepción, dispensación y transferencia se retendrán durante 7 días para evitar duplicados ante reintentos tardíos)[cite: 6, 11].

---

## PARTE 2: Respuestas Técnico-Sanitarias del Químico Farmacéutico

### 2.1 Control de Medicamentos Bajo Receta y Controlados[cite: 6, 8]
* **P-15 / I-14 | Dispensación de recetas bajo receta sin conexión:**[cite: 7, 10]
  Dado que el saldo de la receta es único y global (RF-053)[cite: 7, 8], la evaluación técnica determinó el siguiente criterio sanitario:
  * [X] **Opción A (Estricta - APROBADA):** **Bloquear totalmente la dispensación de recetas sin conexión a la red central**[cite: 10].
    * *Justificación Sanitaria:* "No se puede arriesgar la duplicación de dispensación de una misma receta en distintas sucursales. Si no hay conexión con el nodo central para verificar el saldo global, el sistema debe solicitar al paciente acudir a una sucursal en línea o esperar el restablecimiento del enlace".

* **Controlados sin conexión:**
  * [X] **Aprobado (Bloqueado sin red):** Queda estrictamente prohibida la dispensación de medicamentos controlados si la caja o sucursal no tiene conexión con la base central (cumplimiento RN-05)[cite: 6, 10].

### 2.2 Mecanismo de Doble Autorización (DEC-02x / RF-046)[cite: 7, 8]
Para realizar ajustes de inventario o bajas de medicamentos controlados (doble autorización obligatoria)[cite: 6, 7, 8]:
* [X] **Reautenticación por Usuario y Contraseña del Químico Farmacéutico Autorizante.**
  * *Justificación Sanitaria:* "El PIN es susceptible de ser compartido oralmente en el mostrador. Para ajustes y bajas de productos controlados en el libro, el profesional autorizante debe autenticarse activamente en la terminal con sus credenciales completas".

### 2.3 Verificación de Identidad al Dispensar (RNF-030)[cite: 7, 9]
Para autorizar la dispensación individual de un medicamento bajo receta o controlado en el punto de venta[cite: 7, 8, 10]:
* [X] **PIN de seguridad de 4 dígitos (de rápida digitación).**
  * *Justificación Sanitaria:* "Al ser una operación de alta frecuencia en mostrador, el Químico Farmacéutico validará la venta ingresando su PIN personal tras la revisión de la receta física, evitando cuellos de botella en la atención al paciente".

---

## PARTE 3: Conformidad y Firmas de Aprobación

Con la firma de este documento se dan por cerradas las preguntas **P-01 a P-18** de la Fase F1[cite: 12], autorizando la actualización de `perfil_carga.yaml` y la apertura inmediata del **ADR-002 (Selección de Stack Tecnológico y Plataforma)**[cite: 10, 11, 12].

* **Por la Dirección del Cliente / Operaciones:**  
  **Nombre:** Roberto Morales V.  
  **Cargo:** Director de Operaciones y Tecnología  
  **Estado:** Aprobado — 2026-10-02  

* **Por la Dirección Farmacéutica / Sanitaria:**  
  **Nombre:** Dra. Elena Santelices R.  
  **Cargo:** Químico Farmacéutico Director Técnico  
=======
# Validación de Arquitectura — Cliente y Químico Farmacéutico (Fase F1)

**Proyecto:** Sistema de Gestión de Farmacia (Control de Inventario y Dispensación)[cite: 11]  
**Estado:** COMPLETADO — Respuestas Firmadas[cite: 12]  
**Fecha:** 2026-10-02  
**Origen:** `09_validation_plan.md` (Fase F1)[cite: 12]  
**Documentos impactados:** `02_configuracion/perfil_carga.yaml`, `00_contexto/registro_cambios.md` (CAM-005)[cite: 7, 10]

---

## PARTE 1: Respuestas del Cliente / Negocio

### 1.1 Continuidad del Negocio y Recuperación (RPO / RTO)
* **P-01 | Pérdida máxima de datos tolerable (RPO):**
  * [X] Máximo 5 minutos. *(Decisión: Se acepta una ventana máxima de 5 minutos para evitar descuadres mayores en libros sanitarios e inventario)*.
* **P-01 | Tiempo máximo de recuperación de servicio (RTO):**
  * [X] Máximo 60 minutos (1 hora). *(Decisión: En caso de contingencia mayor en el nodo central, las sucursales pueden operar de forma temporal en degradado mientras se restaura el servicio)*.
* **Frecuencia de prueba de restauración (RNF-040):**[cite: 9, 10]
  * [X] Cada 90 días.

### 1.2 Capacidad y Perfil de Carga[cite: 10]
* **P-06 | Volumen transaccional:**
  * [X] Aprobado: **25.000 transacciones de negocio por hora** como referencia inicial[cite: 10].
* **Consultas de stock/producto:**
  * Estimación acordada: **50.000 consultas por hora** (picos de 150.000/h con multiplicador 3.0)[cite: 10].

### 1.3 Disponibilidad y Ventana Operativa[cite: 10]
* **Disponibilidad objetivo (RNF-044):**[cite: 9, 10]
  * [X] Aprobado: **99,9 %**[cite: 10].
* **Base de medición:**
  * [X] **Horario de atención comercial** (definido sobre el horario operativo de las 8 sucursales)[cite: 10].

### 1.4 Retención Legal de Datos y Normativa (RNF-038, RNF-039)[cite: 9, 10]
* **P-02 | Dispensaciones y Ventas:** **5 años**.
* **P-02 | Recetas Médicas:** **5 años**.
* **P-02 | Libro de Medicamentos Controlados:** **10 años** (conforme a exigencia de la autoridad sanitaria).
* **P-02 | Registros de Auditoría:** **5 años**.

### 1.5 Modo Degradado y Trazabilidad[cite: 10]
* **Venta libre sin conexión:**
  * [X] **Sí**, se permite continuar vendiendo productos de venta libre sin conexión, registrando la cola de transacciones localmente y conciliando al reconectar[cite: 11, 12].
* **P-16 | Ventana de Idempotencia:**
  * [X] **7 días** (las claves de idempotencia para cobro, recepción, dispensación y transferencia se retendrán durante 7 días para evitar duplicados ante reintentos tardíos)[cite: 6, 11].

---

## PARTE 2: Respuestas Técnico-Sanitarias del Químico Farmacéutico

### 2.1 Control de Medicamentos Bajo Receta y Controlados[cite: 6, 8]
* **P-15 / I-14 | Dispensación de recetas bajo receta sin conexión:**[cite: 7, 10]
  Dado que el saldo de la receta es único y global (RF-053)[cite: 7, 8], la evaluación técnica determinó el siguiente criterio sanitario:
  * [X] **Opción A (Estricta - APROBADA):** **Bloquear totalmente la dispensación de recetas sin conexión a la red central**[cite: 10].
    * *Justificación Sanitaria:* "No se puede arriesgar la duplicación de dispensación de una misma receta en distintas sucursales. Si no hay conexión con el nodo central para verificar el saldo global, el sistema debe solicitar al paciente acudir a una sucursal en línea o esperar el restablecimiento del enlace".

* **Controlados sin conexión:**
  * [X] **Aprobado (Bloqueado sin red):** Queda estrictamente prohibida la dispensación de medicamentos controlados si la caja o sucursal no tiene conexión con la base central (cumplimiento RN-05)[cite: 6, 10].

### 2.2 Mecanismo de Doble Autorización (DEC-02x / RF-046)[cite: 7, 8]
Para realizar ajustes de inventario o bajas de medicamentos controlados (doble autorización obligatoria)[cite: 6, 7, 8]:
* [X] **Reautenticación por Usuario y Contraseña del Químico Farmacéutico Autorizante.**
  * *Justificación Sanitaria:* "El PIN es susceptible de ser compartido oralmente en el mostrador. Para ajustes y bajas de productos controlados en el libro, el profesional autorizante debe autenticarse activamente en la terminal con sus credenciales completas".

### 2.3 Verificación de Identidad al Dispensar (RNF-030)[cite: 7, 9]
Para autorizar la dispensación individual de un medicamento bajo receta o controlado en el punto de venta[cite: 7, 8, 10]:
* [X] **PIN de seguridad de 4 dígitos (de rápida digitación).**
  * *Justificación Sanitaria:* "Al ser una operación de alta frecuencia en mostrador, el Químico Farmacéutico validará la venta ingresando su PIN personal tras la revisión de la receta física, evitando cuellos de botella en la atención al paciente".

---

## PARTE 3: Conformidad y Firmas de Aprobación

Con la firma de este documento se dan por cerradas las preguntas **P-01 a P-18** de la Fase F1[cite: 12], autorizando la actualización de `perfil_carga.yaml` y la apertura inmediata del **ADR-002 (Selección de Stack Tecnológico y Plataforma)**[cite: 10, 11, 12].

* **Por la Dirección del Cliente / Operaciones:**  
  **Nombre:** Roberto Morales V.  
  **Cargo:** Director de Operaciones y Tecnología  
  **Estado:** Aprobado — 2026-10-02  

* **Por la Dirección Farmacéutica / Sanitaria:**  
  **Nombre:** Dra. Elena Santelices R.  
  **Cargo:** Químico Farmacéutico Director Técnico  
>>>>>>> 5e30ee8d479d3bb84f19c322844832eef42dc31d
  **Estado:** Aprobado — 2026-10-02