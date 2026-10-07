Continuar — Landing para Sistema Farmacia

Contexto del Negocio
El sistema es una plataforma de gestión para farmacias en Bolivia (control de medicamentos, lotes, vence FEFO, catálogo y punto de venta). La landing page es una vitrina comercial/demostrativa orientada a propietarios y administradores de farmacias.

Objetivo
Construir proyecto/07_landing/ como landing pública para tráfico proveniente de anuncios.
NO es el sistema administrativo. NO puede incorporar login, registro, dashboard, panel, CRUD administrativo, roles, permisos, carrito, checkout ni pagos reales.

Alcance obligatorio
- Mercado objetivo: Bolivia.
- Moneda visible: Bs.
- Exactamente 7 categorías del rubro farmacéutico (ej. Medicamentos con receta, De venta libre, Cuidado personal, Dermocosmética, Bebé y maternidad, Nutrición y suplementos, Primeros auxilios).
- Exactamente 10 productos demostrativos por categoría (Total: 70 productos).
- Imágenes: gratuitas, externas y con fuente/licencia verificable (Unsplash, Pexels, etc.).
- CTA comercial configurable (ej. "Solicitar Demo" / "Contactar por WhatsApp").
- No inventar un número real de WhatsApp (usar placeholder configurable).
- No generar imágenes mediante IA.

Lee primero, en este orden
1. AGENTS.md si existe.
2. proyecto/04_decisiones/decisiones_alcance.md si existe.
3. proyecto/04_decisiones/decisiones_landing.md.
4. .agents/guides/estructura_landing.md.
5. .agents/guides/reglas_landing.md.
6. .agents/guides/politica_imagenes_landing.md.
7. .agents/skills-landing-manifest.json.
8. .agents/state/landing-workflow.json.
9. .agents/workflows/07_landing_workflow.md.

Regla de ejecución
Ejecuta EXACTAMENTE UN checkpoint por interacción.
Al finalizar:
1. verifica físicamente cada archivo requerido;
2. ejecuta validadores y pruebas del checkpoint;
3. guarda evidencia en proyecto/07_landing/tests/results/;
4. actualiza .agents/state/landing-workflow.json;
5. establece HUMAN_STATUS: PENDING;
6. detente.

Regla de aprobación
Solo se avanza con:
- TECHNICAL_STATUS: PASSED
- HUMAN_STATUS: APPROVED
Si falla una regla obligatoria:
- TECHNICAL_STATUS: FAILED
- CAN_CONTINUE: false

Precedencia
REQUISITOS > DECISIONES > WORKFLOW > STATE > GUIDES > SKILLS > IMPLEMENTACION
Un skill jamás puede cambiar el stack aprobado ni introducir frameworks/librerías no autorizadas. La creación visual y las auditorías POST-CREATE son fases diferentes.