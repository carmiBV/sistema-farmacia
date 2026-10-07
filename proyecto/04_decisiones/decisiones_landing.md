# Decisiones de la Landing Comercial

**Estado:** PROPUESTO — pendiente de aprobación del cliente (los checkpoints se validan en `landing-workflow.json`).

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
