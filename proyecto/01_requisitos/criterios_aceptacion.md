# Criterios de aceptación
- CA-01. Cada decisión principal debe mapearse a RF/RNF y, cuando aplique, a una regla de negocio (RN).
- CA-02. Comparar al menos 2 alternativas arquitectónicas.
- CA-03. Explicar cómo se evita el stock negativo y la dispensación de lotes no aptos (vencidos, en cuarentena o retirados) bajo concurrencia.
- CA-04. Justificar la base de datos sin sesgo previo.
- CA-05. Explicar soporte para 25.000 transacciones de negocio/hora (valor de referencia a validar con el cliente).
- CA-06. Evaluar escenario 10x.
- CA-07. Definir idempotencia para cobro, dispensación, recepción y transferencia.
- CA-08. Cubrir seguridad, privacidad, observabilidad y recuperación.
- CA-09. No introducir Kubernetes/microservicios sin justificación.
- CA-10. Demostrar trazabilidad completa: dado un lote, identificar recepción, stock por sucursal y dispensaciones; dada una dispensación, identificar paciente, receta, lote y responsable.
- CA-11. Justificar el tratamiento de datos sensibles (pacientes y recetas) y los plazos de retención.
- CA-12. No deben quedar hallazgos bloqueantes en revisión final.

