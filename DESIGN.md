---
name: Farmacia San Francisco — Landing & Sistema de Diseño
description: Identidad sobria y limpia para la Farmacia San Francisco en Bolivia, basada en un único verde farmacéutico/salud y fotografía real de productos de salud, cuidado personal y bienestar.
colors:
  bg: "#f8fafc"
  surface: "#ffffff"
  ink: "#0f172a"
  ink-muted: "#475569"
  line: "#e2e8f0"
  accent: "#0d9488"
  accent-hover: "#0f766e"
  on-accent: "#ffffff"
  warning: "#b45309"
typography:
  display:
    fontFamily: "Outfit, system-ui, -apple-system, Segoe UI, sans-serif"
    fontSize: "clamp(2rem, 4.5vw, 3.25rem)"
    fontWeight: 800
    lineHeight: "1.05"
    letterSpacing: "-0.02em"
  headline:
    fontFamily: "Outfit, system-ui, -apple-system, Segoe UI, sans-serif"
    fontSize: "clamp(1.5rem, 3vw, 2.25rem)"
    fontWeight: 800
    lineHeight: "1.15"
    letterSpacing: "-0.015em"
  title:
    fontFamily: "Outfit, system-ui, -apple-system, Segoe UI, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 600
    lineHeight: "1.3"
  body:
    fontFamily: "Outfit, system-ui, -apple-system, Segoe UI, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: "1.65"
  label:
    fontFamily: "Outfit, system-ui, -apple-system, Segoe UI, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: "1.5"
  price:
    fontFamily: "Outfit, system-ui, -apple-system, Segoe UI, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 800
    lineHeight: "1.2"
rounded:
  sm: "6px"
  md: "10px"
  lg: "16px"
spacing:
  1: "4px"
  2: "8px"
  3: "12px"
  4: "16px"
  6: "24px"
  8: "32px"
  12: "48px"
  16: "64px"
  24: "96px"
components:
  button-primary:
    backgroundColor: "{colors.accent}"
    textColor: "{colors.on-accent}"
    rounded: "{rounded.md}"
    padding: "14px 28px"
    height: "48px"
  button-primary-hover:
    backgroundColor: "{colors.accent-hover}"
    textColor: "{colors.on-accent}"
    rounded: "{rounded.md}"
    padding: "14px 28px"
    height: "48px"
  button-secondary:
    backgroundColor: "transparent"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    padding: "14px 28px"
    height: "48px"
  badge:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink-muted}"
    rounded: "{rounded.sm}"
    padding: "4px 12px"
---

# Design System: Farmacia San Francisco — Landing

## 1. Overview

**Creative North Star: "Salud y confianza sin artificios"**

Este sistema transmite tranquilidad, limpieza e integridad profesional. Un fondo fresco y sobrio (`#f8fafc`), tipografía Outfit con peso 800 en los títulos, un único verde azulado médico/farmacéutico (`#0d9488`) y la fotografía real de medicamentos, dermocosmética y artículos de cuidado personal como protagonistas de cada sección. El layout es asimétrico por decisión: hero 55/45, categorías de salud en zig-zag de 2 columnas, y grid de productos destacados; sin saturación visual ni elementos innecesarios.

Rechaza explícitamente el slop de landing AI (gradientes morado/azul, Inter, emojis, eyebrows en mayúsculas repetidos), el look invasivo de e-commerce promocional o el estilo frío de dashboard administrativo. Confianza y claridad médica por peso y espacio, no por exceso de color.

**Key Characteristics:**
- Un acento verde teal (`#0d9488`); saturación < 80%; más de un acento cromático está prohibido.
- Outfit única (400/600/800); sin serif en UI; Inter vetado.
- Layout asimétrico con fallback estricto a 1 columna en `< 768px`.
- Superficies planas por defecto; sombra tintada difusa solo en elevación real.
- Movimiento CSS nativo (`transform`/`opacity`), `prefers-reduced-motion` obligatorio.
- Voz clara y cercana en español de Bolivia; datos marcados como informativos/demostrativos.

## 2. Colors

Paleta de un solo acento clínico-cálido sobre neutros limpios: el acento solo aparece en acciones primarias, disponibilidad o énfasis.

### Primary
- **Verde farmacéutico / Teal** (`#0d9488`): CTA primario, links, `:focus-visible`, precio o disponibilidad destacada. Es el 100% del acento cromático del sistema.
- **Verde profundo hover** (`#0f766e`): hover/active del CTA primario y del bloque CTA final.

### Neutral
- **Blanco clinico** (`#f8fafc`): fondo de página y de secciones alternas (`.catálogo`, `.servicios-turnos`).
- **Blanco puro** (`#ffffff`): superficie de tarjetas, header, secciones elevadas (`.categorías`, `.atención`).
- **Tinta carbón** (`#0f172a`): texto principal y CTA secundario (borde/fondo en hover). `#000000` prohibido.
- **Gris pizarra** (`#475569`): cuerpo secundario, composición, dosis/presentación, metadata (contraste AA ≥ 4.5:1 sobre `#f8fafc`).
- **Línea tenue** (`#e2e8f0`): bordes de 1px, divisores, chips.

### Named Rules
**La Regla del Acento Único.** Verde Teal `#0d9488` es el único acento cromático permitido.
Prohibidos: gradiente morado/azul «AI», neón, glow exterior, degradados de texto, puro negro o combinaciones tipo "rojo cruz roja".
**La Regla del Precio / Alerta.** `#b45309` (ámbar) existe exclusivamente para
`precio_anterior` tachado o notas de receta/disponibilidad; nunca se usa como acento de marca.

## 3. Typography

**Display Font:** Outfit (fallback `system-ui, -apple-system, "Segoe UI", sans-serif`)
**Body Font:** Outfit (misma familia)

**Character:** Una sola familia geométrica pulcra y accesible; la personalidad proviene de la legibilidad, el contraste de peso (400 vs 800) y el tracking en títulos, no de una segunda tipografía.

### Hierarchy
- **Display/H1** (800, `clamp(2rem, 4.5vw, 3.25rem)`, 1.05, `-0.02em`): título del hero; transmite solidez y presencia sin estridentismo.
- **Headline/H2** (800, `clamp(1.5rem, 3vw, 2.25rem)`, 1.15, `-0.015em`): títulos de sección (p. ej., "Farmacia de Turno", "Cuidado y Salud").
- **Title/H3** (600, `1.25rem`, normal): nombres de medicamentos/productos y servicios.
- **Body** (400, `1rem`, 1.65, `max-width: 65ch`): información de uso, ubicación y párrafos descriptivos.
- **Label** (400, `0.875rem`, muted): presentación, laboratorio, horario de turno, chips.
- **Price** (800, `1.125rem`, `tabular-nums`): precio actual en Bolivianos (`Bs`); `precio_anterior` tachado en muted.

### Named Rules
**La Regla del Peso sobre el Tamaño.** La jerarquía se construye con peso (400/600/800) y
color (ink vs muted); ningún título necesita superar `3.25rem`.

## 4. Elevation

Sistema plano y pulcro por defecto, con sombra tintada difusa como única herramienta de profundidad.
Sin sombras duras ni efectos recargados: donde no hay elevación real, se usa borde de 1px `--color-line`.

### Shadow Vocabulary
- **Elevación difusa** (`box-shadow: 0 20px 40px -15px rgba(15,23,42,.08)`): header al hacer scroll (`.is-scrolled`) y tarjetas en interacción hover.

### Named Rules
**La Regla del Plano por Defecto.** Las superficies se mantienen pulcras y planas en reposo. La sombra
aparece solo como respuesta a un estado (scroll, hover, focus).

## 5. Components

### Buttons
- **Shape:** radio `10px` (`--radius-md`), `min-height: 48px` (target táctil ≥ 44px).
- **Primary:** fondo `#0d9488`, texto `#ffffff`, `padding: 14px 28px`, peso 600. Hover → `#0f766e`. `:active { transform: scale(.98) }`.
- **Secondary:** transparente, texto y borde 1px `#0f172a`. Hover → fondo `#0f172a`, texto blanco.
- **On-accent** (bloque CTA final / Envio WhatsApp): texto/borde `#ffffff` sobre fondo verde teal; hover invierte a blanco sólido con texto verde.
- **Focus:** `2px solid #0d9488` con `outline-offset: 2px` (`:focus-visible` global).

### Chips / Badges
- **Style:** fondo `#ffffff`, borde 1px `#e2e8f0`, radio `6px`, texto `#475569`, `font-size: 0.875rem`, `padding: 4px 12px`.
- **State:** estáticos (etiquetas de categoría: "Venta libre", "Cuidado personal", "Turno 24h").

### Cards / Containers
- **Corner Style:** radio `16px` (`--radius-lg`) en productos, servicios de salud y secciones destacadas.
- **Background:** `#ffffff` sobre fondo `#f8fafc`.
- **Shadow Strategy:** borde 1px `#e2e8f0` en reposo; elevación difusa solo en hover (media query `hover: hover`).
- **Internal Padding:** `24px` (`--spacing-6`) en tarjetas de producto y servicio; `32px` en contenedores destacados.

## 6. Layout y Movimiento (valores operativos)

*(Sección reconstruida 2026-10-08 al completar el documento truncado; no introduce reglas nuevas: consolida las ya declaradas en Overview y Key Characteristics.)*

- **Hero:** 55/45 (texto / fotografía) desde `768px`; una columna por debajo.
- **Categorías:** zig-zag de 2 columnas (imagen/texto alternados) desde `768px`; una columna por debajo.
- **Catálogo / grid:** `repeat(auto-fill, …)`; una columna `< 768px`.
- **Breakpoint único:** `768px`.
- **Movimiento:** `transform` / `opacity` (transiciones 150–200 ms); `prefers-reduced-motion: reduce` desactiva transiciones y `scroll-behavior: smooth`.
- **Tipografía Outfit:** auto-hospedada (`assets/fonts/`, SIL OFL 1.1), pesos 400/600/800 desde fuente variable.