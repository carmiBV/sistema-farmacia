---
name: requirements-analysis
description: Analiza requisitos funcionales y no funcionales, reglas, restricciones, capacidad y ambigüedades antes de seleccionar tecnologías.
compatibility: OpenCode y agentes compatibles con Agent Skills; reutilizable por Claude mediante instrucciones del proyecto.
metadata:
  phase: analysis
  implementation: prohibited
---
# Objetivo
Convertir requisitos y contexto en drivers arquitectónicos verificables sin elegir todavía un stack definitivo.

# Procedimiento
1. Leer contexto, alcance, reglas de negocio, RF, RNF y perfil de carga.
2. Clasificar RF y RNF.
3. Detectar contradicciones, ambigüedades y requisitos no verificables.
4. Identificar restricciones y dependencias.
5. Distinguir transacciones de negocio de operaciones técnicas internas.
6. Identificar drivers arquitectónicos.
7. Formular preguntas abiertas.
8. No programar.
9. No elegir una tecnología definitiva.

# Salida
- resumen;
- RF críticos;
- RNF críticos;
- inconsistencias;
- riesgos;
- preguntas abiertas;
- drivers arquitectónicos;
- requisitos que requieren validación humana.
