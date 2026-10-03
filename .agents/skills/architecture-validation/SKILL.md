---
name: architecture-validation
description: Revisión adversarial de la arquitectura contra RF/RNF, detectando contradicciones, cobertura faltante y complejidad innecesaria.
compatibility: OpenCode y Claude
metadata:
  phase: validation
  implementation: prohibited
---
# Procedimiento
Para cada hallazgo indicar:
- ID;
- requisito afectado;
- evidencia;
- impacto;
- severidad;
- cambio necesario;
- criterio de cierre.

Buscar especialmente:
- requisitos sin cobertura;
- supuestos ocultos;
- sobrearquitectura;
- condiciones de carrera;
- duplicación por reintentos;
- recuperación incompleta;
- decisiones sin evidencia.
