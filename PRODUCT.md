# Product: Sistema Farmacia

> **Reconciliación (2026-10-09, CAM-009).** Este documento se subordina a `DESIGN.md` y a `proyecto/04_decisiones/decisiones_landing.md`:
> - **Marca:** "Farmacia San Francisco" (instrucción explícita del cliente, 2026-10-08) reemplaza "Sistema Farmacia".
> - **Acento cromático:** `#0d9488` (Regla del Acento Único de `DESIGN.md`, aprobado en CP-LANDING-05/06/07) reemplaza `#047857`. Cambiarlo implica actualizar tokens, validadores y capturas.
> - El resto del documento (usuarios, propósito, anti-referencias, principios y accesibilidad) se conserva como requisito de producto; su implementación está verificada en `proyecto/07_landing/tests/`.

## Register

brand / public-catalog

## Users

Pacientes, clientes y usuarios en Bolivia que buscan consultar la disponibilidad, precios e información técnica de medicamentos y productos farmacéuticos en una plataforma pública, accesible sin necesidad de crear cuenta ni iniciar sesión. 

* **Contexto de uso**: Búsqueda rápida y ágil desde dispositivos móviles (móvil primero) o escritorio, priorizando la consulta sobre disponibilidad de stock y la acción inmediata de consulta o reserva vía WhatsApp/CTA directo, sin pasar por una compra en línea compleja.

## Product Purpose

Portal público, confiable e intuitivo para el **Sistema Farmacia**: presentar el catálogo actualizado de medicamentos y productos de salud, generar tranquilidad respecto a la autenticidad e información de los productos, y canalizar al usuario hacia la atención o consulta directa (WhatsApp o canal de contacto).

* **Criterio de éxito**: El cliente encuentra o consulta la disponibilidad de su medicamento de forma clara desde la primera interacción y ejecuta la acción de contacto/consulta sin fricciones.
* **Fase demostrativa/piloto**: El catálogo refleja datos representativos para consulta pública sin requerir flujo de pasarela de pago (checkout) ni registro obligatorio en el portal público.

## Brand Personality

* **Atributos**: Limpio, confiable, asistencial, claro y profesional.
* **Estilo visual**: Identidad clínico-comercial sobria. Imágenes claras de productos/empaques como protagonistas, acento cromático institucional verde/médico (`#047857`), tipografía moderna con excelente legibilidad (`Outfit`), composición asimétrica organizada y estructurada. Transmisión de higiene, seguridad y confianza médica sin caer en saturación.

## Anti-references

- **Diseño genérico tipo IA**: Gradientes chillones morado/azul, héroe centrado sobre imágenes oscuras descontextualizadas, tarjetas de producto repetitivas sin jerarquía, uso excesivo de emojis en el marcado o textos alt.
- **E-commerce agresivo tipo Marketplace masivo**: Evitar patrones que sugieran venta directa impulsiva o flujos complejos de carrito/checkout sin asistencia farmacéutica.
- **Aspecto de panel administrativo/Dashboard**: La interfaz pública debe verse como un catálogo asistencial de cara al cliente, no como un sistema de gestión interna de inventario.
- **Saturación tipográfica**: Evitar el exceso de estilos, fuentes serif innecesarias o etiquetas monocromáticas que dificulten la lectura de nombres de medicamentos y dosis.

## Design Principles

1. **Claridad sobre la información médica y el producto**: La imagen real y clara del medicamento/producto junto a su presentación (dosis, formato) son los elementos centrales.
2. **Consistencia de acento y voz**: Uso del tono verde sanitario (`#047857`) reservado exclusivamente para acciones clave (CTA), llamados a la consulta y estados de disponibilidad.
3. **Consulta inmediata y sin barreras**: Cero barreras de entrada; acceso inmediato a la información sin requerir login o captura de datos previa.
4. **Interacciones funcionales y accesibles**: Animaciones y transiciones sutiles con soporte estricto de reducción de movimiento (`reduced-motion`).
5. **Transparencia e información clara**: Presentación explícita sobre condiciones de disponibilidad, aclaraciones sobre recetas o indicaciones de consulta profesional.

## Accessibility & Inclusion

* **Normativa WCAG 2.2 AA**:
  * Relación de contraste adecuada ($\ge 4.5:1$, ej. texto tenue `#52525b` sobre fondo claro `#f9fafb`).
  * Áreas táctiles cómodas en móviles ($\ge 44\text{px}$).
  * Estructura semántica HTML5, enlace de salto rápido (*skip-link*) e indicadores de foco visibles (`:focus-visible`).
  * Adaptación para preferencia de movimiento reducido (`prefers-reduced-motion`).
  * Carga diferida (*lazy-loading*) de imágenes con texto alternativo (`alt`) descriptivo (nombre del producto y presentación).
  * Escalabilidad visual y diseño responsivo adaptado hasta un 200% de zoom sin desbordamiento de pantalla (verificado en viewports de 360px, 768px y 1280px).