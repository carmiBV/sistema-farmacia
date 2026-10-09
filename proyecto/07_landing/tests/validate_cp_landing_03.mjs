// CP-LANDING-03: validador de componentes UI y secciones comerciales.
// Verifica estructura (header/nav/hero/beneficios/showroom/CTA/footer),
// responsive, filtrado por categoría sin recarga y lógica de CTA WhatsApp
// placeholder. Evidencia: tests/results/cp-landing-03.json
// Uso: node tests/validate_cp_landing_03.mjs
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');

const checks = [];
const check = (ok, label) => {
    checks.push({ label, status: ok ? 'PASS' : 'FAIL' });
    console.log((ok ? 'PASS' : 'FAIL') + ': ' + label);
    return ok;
};

const html = readFileSync(path.join(root, 'index.html'), 'utf8');
const css = readFileSync(path.join(root, 'assets/css/main.css'), 'utf8');
const js = readFileSync(path.join(root, 'assets/js/main.js'), 'utf8');
const config = JSON.parse(readFileSync(path.join(root, 'data/config.json'), 'utf8'));

// --- 1. secciones comerciales (reestructuradas en CP-LANDING-06) ---
check(/class="site-header"/.test(html) && /class="brand"/.test(html)
    && /Farmacia San Francisco/.test(html), 'header/navbar con marca Farmacia San Francisco');
check(/<nav[^>]*class="site-nav"/.test(html) && /href="#categorias"/.test(html)
    && /href="#catalogo"/.test(html) && /href="#turno"/.test(html) && /href="#contacto"/.test(html),
    'navbar con navegación suave por anclas (#categorias/#catalogo/#turno/#contacto)');
check(/scroll-behavior:\s*smooth/.test(css), 'navegación suave habilitada (scroll-behavior: smooth)');
check(/id="cta-header"/.test(html), 'botón CTA en el header');

check(/id="hero-title"/.test(html), 'hero section presente');
const hero = html.slice(html.indexOf('id="inicio"'), html.indexOf('id="categorias"'));
check(/grid-template-columns:\s*55% 45%/.test(css), 'hero asimétrico 55/45 (DESIGN.md)');
check(/Venta libre/.test(hero) && /Cuidado personal/.test(hero) && /Turno 24h/.test(hero),
    'hero con chips de servicio (Venta libre / Cuidado personal / Turno 24h)');
check(/Bolivia/.test(hero), 'hero orientado a Bolivia');

check(/id="categorias"/.test(html) && /cat-zigzag/.test(html), 'sección de categorías en zig-zag');
const cats = html.slice(html.indexOf('id="categorias"'), html.indexOf('id="catalogo"'));
check((cats.match(/class="cat-row"/g) || []).length === 7, '7 categorías de salud en zig-zag');
check(/Cuidado y Salud/.test(cats), 'sección "Cuidado y Salud" (título requisito del DESIGN.md)');

check(/id="catalogo"/.test(html) && /id="filter-bar"/.test(html) && /id="product-grid"/.test(html),
    'catálogo con filtro y grilla de productos destacados');
check(/price-anterior/.test(js), 'precio_anterior tachado en el render (promos)');

check(/id="turno"/.test(html) && /Farmacia de Turno/.test(html) && /benefit-card/.test(html),
    'sección Farmacia de Turno (título requisito)');
check(/id="contacto"/.test(html) && /class="atencion"/.test(html) && /id="contact-list"/.test(html),
    'sección Atención con datos de contacto');
check(/id="cta-whatsapp-float"/.test(html), 'CTA flotante presente');
check(/id="contacto"/.test(html) && /cta-whatsapp/.test(html), 'sección CTA con WhatsApp');
check(/id="footer-legal"/.test(html) && /id="footer-links"/.test(html) && /id="contact-email"/.test(html),
    'footer con propiedad intelectual, enlaces legales y contacto');

// --- 2. responsive ---
check(/name="viewport"/.test(html), 'meta viewport presente');
check(/@media/.test(css), 'consultas @media (responsive)');
check(/repeat\(auto-(fit|fill)/.test(css), 'grillas fluidas (auto-fit/auto-fill)');

// --- 3. alcance: sin código/rutas administrativas ---
const prohibidos = ['login', 'registro', 'dashboard', 'carrito', 'checkout', 'crud', 'roles', 'permisos', 'panel'];
const lower = html.toLowerCase();
const encontrados = prohibidos.filter((p) =>
    new RegExp(`href="[^"]*${p}|/${p}["/]`, 'i').test(html) || lower.includes('iniciar sesión'));
check(encontrados.length === 0,
    `sin rutas ni prompts administrativos${encontrados.length ? ' (' + encontrados.join(', ') + ')' : ''}`);
check(!/<form[^>]*(pago|payment|checkout)/i.test(html), 'sin formularios de pago/checkout');

// --- 4. filtrado sin recarga + lógica CTA (funcional, con stub) ---
function el() {
    let tc = '';
    const e = {
        children: [], listeners: {}, value: '', hidden: false, dataset: {},
        className: '', title: '', href: '', textContent: '',
        appendChild(c) { this.children.push(c); return c; },
        addEventListener(ev, fn) { this.listeners[ev] = fn; },
        setAttribute() {},
        click() { if (this.listeners.click) return this.listeners.click({}); },
    };
    Object.defineProperty(e, 'textContent', {
        get() { return tc; },
        set(v) { tc = String(v); if (tc === '') e.children.length = 0; },
    });
    return e;
}

const byId = {};
for (const id of ['market-badge', 'cta-header', 'cta-hero', 'cta-contacto-demo',
    'cta-whatsapp', 'cta-whatsapp-float', 'contact-email', 'contact-address', 'contact-hours',
    'footer-legal', 'footer-links', 'catalogo-total', 'filter-bar', 'product-grid', 'catalogo-vacio']) {
    byId[id] = el();
}
const dom = {
    getElementById: (id) => byId[id] ?? null,
    createElement: () => el(),
};
const win = {};
new Function('document', 'localStorage', 'fetch', 'window', 'console', js)(
    dom,
    { getItem: () => null, setItem: () => {}, removeItem: () => {} },
    async () => { throw new Error('sin red en pruebas'); },
    win,
    { error: () => {} },
);

const api = win.LANDING_TEST ?? {};
check(typeof api.filtrarPorCategoria === 'function', 'filtrarPorCategoria expuesta y testeable');

const productos = [
    { id: 'a-1', categoria_id: 'cat-a' },
    { id: 'b-1', categoria_id: 'cat-b' },
    { id: 'b-2', categoria_id: 'cat-b' },
];
const filtroA = api.filtrarPorCategoria ? api.filtrarPorCategoria(productos, 'cat-b') : [];
const filtroTodas = api.filtrarPorCategoria ? api.filtrarPorCategoria(productos, 'todas') : [];
check(filtroA.length === 2 && filtroA.every((p) => p.categoria_id === 'cat-b'),
    'filtro por categoría selecciona solo la categoría activa');
check(filtroTodas.length === 3, 'filtro "Todas" muestra el catálogo completo');

check(!/location\.reload|location\.href\s*=|window\.location\.assign/.test(js),
    'filtrado sin recarga de página (sin navegación en el JS)');
check(/addEventListener\('click'/.test(js) && /renderCatalogo/.test(js),
    'el filtro re-renderiza la grilla en cliente');

const waReal = api.enlaceWhatsApp ? api.enlaceWhatsApp(config) : null;
const configPlaceholder = { cta: { whatsapp: { is_placeholder: true, phone_placeholder: '+591 000 000 000' } } };
const waPlaceholder = api.enlaceWhatsApp ? api.enlaceWhatsApp(configPlaceholder) : null;
check(waPlaceholder === '#contacto',
    'CTA WhatsApp con placeholder -> sin enlace wa.me inventado (#contacto)');
check(waReal === 'https://wa.me/59172248223',
    `CTA WhatsApp configurado por el cliente -> ${waReal}`);
check(/is_placeholder/.test(js) && /wa\.me\//.test(js),
    'guard de placeholder presente en el código (números nunca inventados)');

check(/loading\s*=\s*['"]lazy['"]|\.loading\s*=\s*['"]lazy['"]/.test(js) || /loading="lazy"/.test(html),
    'imágenes con carga diferida (lazy)');

// --- evidencia ---
const fails = checks.filter((c) => c.status === 'FAIL').length;
const resultado = {
    checkpoint: 'CP-LANDING-03',
    date: new Date().toISOString().slice(0, 10),
    result: fails === 0 ? 'PASSED' : 'FAILED',
    summary: 'Secciones comerciales de Farmacia San Francisco (header/navbar, hero 55/45, categorías zig-zag, catálogo con filtro sin recarga, Farmacia de Turno, atención, CTA final y flotante WhatsApp) 100% responsivas y sin alcance administrativo.',
    checks,
    total: checks.length,
    failed: fails,
};
mkdirSync(path.join(root, 'tests/results'), { recursive: true });
writeFileSync(path.join(root, 'tests/results/cp-landing-03.json'),
    JSON.stringify(resultado, null, 2) + '\n', 'utf8');
console.log(`RESULTADO: ${resultado.result} (${checks.length - fails}/${checks.length})`);
process.exit(fails === 0 ? 0 : 1);
