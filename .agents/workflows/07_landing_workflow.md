# Workflow 07: Landing Comercial Pública — Sistema Farmacia

## 1. Contexto y Objetivos del Workflow
* **Objetivo:** Construir una landing page comercial pública (`proyecto/07_landing/`) destinada al tráfico de anuncios para captar propietarios de farmacias en Bolivia.
* **Límites estrictos (Anti-Scope Creep):** 
  * NO es el sistema administrativo.
  * NO incluye login, registro, dashboard, panel de administración, CRUD, roles, permisos, carrito de compras, checkout ni procesamiento de pagos reales.
* **Mercado Objetivo:** Bolivia.
* **Moneda Visible:** Bs.
* **Catálogo Demostrativo:** Exactamente 7 categorías y 10 productos por categoría (70 productos en total).
* **Imágenes:** Fuentes externas gratuitas con licencias verificables (Unsplash/Pexels). Sin generación por IA.

---

## 2. Precedencia de Reglas y Documentos
1. REQUISITOS GENERALES
2. `proyecto/04_decisiones/decisiones_landing.md`
3. `07_landing_workflow.md` (Este documento)
4. `.agents/state/landing-workflow.json`
5. Guías y Skills

---

## 3. Checkpoints de Desarrollo (CP-LANDING)

### CP-LANDING-01 | Configuración Base y Arquitectura del Proyecto
* **Alcance:**
  * Inicializar el proyecto dentro de `proyecto/07_landing/` (HTML/Tailwind CSS/JS Vanilla o Astro/React según decisiones acordadas).
  * Configurar estructura de carpetas (`assets/`, `components/`, `data/`, `tests/`).
  * Definir variables de configuración global (moneda `Bs.`, datos de contacto/placeholder de WhatsApp, enlaces de CTA).
* **Criterios de Aceptación (TECHNICAL_STATUS):**
  * `proyecto/07_landing/` estructurado sin errores de sintaxis o empaquetado.
  * Archivo de datos de configuración presente.
* **Evidencia:** `proyecto/07_landing/tests/results/cp-landing-01.json`.

---

### CP-LANDING-02 | Datos del Catálogo (7 Categorías x 10 Productos)
* **Alcance:**
  * Crear el archivo de datos JSON con las 7 categorías farmacéuticas:
    1. Medicamentos con Receta
    2. Medicamentos de Venta Libre (OTC)
    3. Dermocosmética y Cuidado Personal
    4. Bebé y Maternidad
    5. Nutrición y Suplementos
    6. Primeros Auxilios e Cuidado de Heridas
    7. Equipos de Medición y Cuidado del Hogar
  * Definir 10 productos coherentes por categoría con precios expresados en Bs.
  * Mapear URLs de imágenes externas con licencias verificables.
* **Criterios de Aceptación (TECHNICAL_STATUS):**
  * Exactamente 70 productos mapeados.
  * Todos los precios en moneda Bs.
* **Evidencia:** `proyecto/07_landing/tests/results/cp-landing-02.json`.

---

### CP-LANDING-03 | Componentes UI y Secciones Comerciales
* **Alcance:**
  * **Header/Navbar:** Logo de `Sistema Farmacia`, navegación suave (scroll) y botón CTA.
  * **Hero Section:** Propuesta de valor clara para farmacias en Bolivia (Gestión de stock FEFO, ventas rápidas POS, controlados).
  * **Sección de Beneficios:** Destacar valor técnico (evitar pérdidas por vencimiento, cumplimiento de normativas).
  * **Showroom/Catálogo Demostrativo:** Filtro interactivo por las 7 categorías con la grilla de productos demostrativos.
  * **Call to Action (CTA):** Botón o formulario flotante hacia WhatsApp configurable (sin usar número real no autorizado).
  * **Footer:** Propiedad intelectual, enlaces legales placeholder y datos de contacto.
* **Criterios de Aceptación (TECHNICAL_STATUS):**
  * Diseño 100% responsivo (Móvil, Tablet, Desktop).
  * Búsqueda o filtrado fluido por categorías sin recarga de página.
* **Evidencia:** `proyecto/07_landing/tests/results/cp-landing-03.json`.

---

### CP-LANDING-04 | Auditoría de Políticas, Accesibilidad y Rendimiento
* **Alcance:**
  * Validar ausencia de código/rutas administrativas (`/login`, `/dashboard`, etc.).
  * Verificar cumplimiento de políticas de imágenes (URLs públicas funcionales, atributos `alt` presentes).
  * Verificar velocidad de carga y rendimiento de la landing page.
* **Criterios de Aceptación (TECHNICAL_STATUS):**
  * Pruebas de integración visual y estructura aprobadas.
  * Sin enlaces ni referencias a paneles de administración o carritos.
* **Evidencia:** `proyecto/07_landing/tests/results/cp-landing-04.json`.

---

## 4. Reglas de Ejecución del Agente
1. El agente debe ejecutar **EXACTAMENTE UN CHECKPOINT** por interacción.
2. Al finalizar un checkpoint, debe actualizar `.agents/state/landing-workflow.json`.
3. Establecer `HUMAN_STATUS: PENDING` y solicitar la revisión del usuario.
4. Queda prohibido avanzar al siguiente checkpoint si el estado actual no es `TECHNICAL_STATUS: PASSED` y `HUMAN_STATUS: APPROVED`.