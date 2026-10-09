# Decisiones de la Landing Comercial

**Estado:** APROBADO — stack (HTML/CSS/JS vanilla) y rediseño según `DESIGN.md` (marca "Farmacia San Francisco") aprobados por el cliente; workflow 07 cerrado el 2026-10-09 (`landing-workflow.json`).

## 1. Alcance (REQUISITOS, precedencia máxima)

Fuente: instrucción del cliente para `proyecto/07_landing/` (2026-10-07).

- Landing pública comercial / demostrativa para tráfico de anuncios, dirigida a **propietarios y administradores de farmacias en Bolivia**.
- **NO es el sistema administrativo.** Prohibido: login, registro, dashboard, panel, CRUD administrativo, roles, permisos, carrito, checkout y pagos reales.
- Mercado objetivo: **Bolivia**. Moneda visible: **Bs.**
- Catálogo demostrativo: **exactamente 7 categorías × 10 productos = 70 productos**.
- Imágenes: externas, gratuitas, con fuente/licencia verificable (Unsplash, Pexels, etc.). **Sin generación por IA.**
- CTA comercial configurable ("Solicitar Demo" / "Contactar por WhatsApp").
- **Prohibido inventar números reales de WhatsApp**: usar placeholder configurable hasta que el cliente entregue el número autorizado.

## 2. Categorías oficiales del showroom (WORKFLOW 07, CP-LANDING-02)

1. Medicamentos con Receta
2. Medicamentos de Venta Libre (OTC)
3. Dermocosmética y Cuidado Personal
4. Bebé y Maternidad
5. Nutrición y Suplementos
6. Primeros Auxilios y Cuidado de Heridas
7. Equipos de Medición y Cuidado del Hogar

(La lista del cliente era ilustrativa —"ej."—; se usa esta enumeración del workflow que cumple "exactamente 7".)

## 3. Stack técnico

**Decisión:** HTML estático + CSS puro + JavaScript vanilla. Cero dependencias externas, sin build.

- Por qué: el workflow permite "HTML/Tailwind/JS Vanilla o Astro/React **según decisiones acordadas**" y no existía `decisiones_landing.md`; esta es la opción sin frameworks ni paso de compilación, consistente con el stack aprobado del sistema (PHP/HTML5/CSS3/JS vanilla, "sin frameworks SPA pesados") y con la regla de que ningún skill puede introducir frameworks no autorizados.
- Alcance: CSS propio en `assets/css/main.css`; JS propio en `assets/js/main.js`; datos en `data/*.json`.
- **Aprueba:** cliente (pendiente — este checkpoint se cierra en `HUMAN_STATUS: PENDING`).

## 4. Estructura del proyecto

```
proyecto/07_landing/
  index.html            página pública única (secciones en CP-LANDING-03)
  assets/css/main.css   estilos
  assets/js/main.js     JS vanilla (config, filtros en CP-LANDING-03)
  components/           secciones/piezas UI (CP-LANDING-03)
  data/config.json      configuración global (moneda, CTA, WhatsApp placeholder)
  data/catalog.json     catálogo demostrativo (CP-LANDING-02)
  tests/                validadores por checkpoint
  tests/results/        evidencia JSON por checkpoint
```

## 5. Notas de contexto

- No existen `.agents/guides/*landing*.md` ni `.agents/skills-landing-manifest.json`: se procede con REQUISITOS + WORKFLOW 07 como fuentes de verdad. Si estos documentos aparecen, su contenido se reconcilia en el checkpoint siguiente (precedencia: GUIDES/SKILLS nunca cambian stack ni alcance).
- Esta landing no forma parte del alcance operativo de `decisiones_alcance.md` (sistema administrativo); es un entregable comercial aparte bajo el workflow 07.

## 6. Rediseño visual (2026-10-08) — sistema de diseño DESIGN.md

**Origen:** instrucción del cliente 2026-10-08 (aplicar `DESIGN.md` y reestructurar contenido). Registro completo: `00_contexto/registro_cambios.md` (CAM-007).

- **Fuente de verdad del diseño:** `DESIGN.md` (Farmacia San Francisco — Landing & Sistema de Diseño). Reglas vinculantes: Acento Único (`#0d9488`), Precio/Alerta (ámbar `#b45309` solo para `precio_anterior` o avisos de receta/disponibilidad), Peso sobre el Tamaño (400/600/800), Plano por Defecto (sombra solo en scroll/hover/focus).
- **Contenido requisito (no ilustrativo):** hero 55/45, categorías de salud en zig-zag de 2 columnas, grid de productos con precio y `precio_anterior`, sección **Farmacia de Turno**, sección **Atención**, bloque CTA final on-accent. Se aplican en CP-LANDING-06.
- **Tipografía Outfit:** auto-hospedada en `assets/fonts/` (SIL OFL 1.1, variable 400–800) para conservar "sin CSS/scripts externos" (validador CP-01). Sin CDN, sin `<link>` externo.
- **Stack:** sin cambios (HTML + CSS + JS vanilla, sin build).
- **Alcance:** sin cambios (prohibido login/registro/dashboard/carrito/checkout/pagos; 7×10=70 productos demostrativos en Bs.; imágenes Commons con licencia; WhatsApp `+591 72248223`).
- **Checkpoints reabiertos:** CP-LANDING-05 (tokens y componentes), CP-LANDING-06 (reestructura de contenido + datos + imágenes), CP-LANDING-07 (auditoría y cierre). Un checkpoint por interacción.
- **Puntos resueltos (2026-10-08, cliente):** marca = **"Farmacia San Francisco"**; el contenido SaaS (beneficios FEFO/POS) se sustituye por las secciones requisito (CAM-008). Pendientes: datos demostrativos (textos, precios, email de contacto) a sustituir por los reales del cliente.
