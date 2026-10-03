---
name: database-evaluation
description: Compara estrategias de persistencia y motores de datos según consistencia, concurrencia, volumen, recuperación y operación.
compatibility: OpenCode y Claude
metadata:
  phase: data
  implementation: prohibited
---
# Procedimiento
1. Identificar patrón transaccional y analítico.
2. Evaluar consistencia, ACID, concurrencia, historial, auditoría y recuperación.
3. Comparar al menos 3 alternativas cuando sea razonable.
4. No asumir PostgreSQL/MySQL/MongoDB de antemano.
5. Justificar índices, caché, particionamiento y réplicas solo si la carga los exige.
6. Relacionar cada decisión con RF/RNF.
