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

// --- 1. secciones comerciales ---
check(/class="site-header"/.test(html) && /class="brand"/.test(html), 'header/navbar con logo');
check(/<nav[^>]*class="site-nav"/.test(html) && /href="#beneficios"/.test(html)
    && /href="#catalogo"/.test(html) && /href="#contacto"/.test(html),
    'navbar con navegación suave por anclas (#beneficios/#catalogo/#contacto)');
check(/scroll-behavior:\s*smooth/.test(css), 'navegación suave habilitada (scroll-behavior: smooth)');
check(/id="cta-header"/.test(html), 'botón CTA en el header');

check(/id="hero-title"/.test(html), 'hero section presente');
const hero = html.slice(html.indexOf('id="inicio"'), html.indexOf('id="beneficios"'));
check(/FEFO/i.test(hero) && /POS/i.test(hero) && /[Cc]ontrolados/.test(hero),
    'hero con propuesta de valor (FEFO, POS, controlados)');
check(/Bolivia/.test(hero), 'hero orientado a Bolivia');

check(/id="beneficios"/.test(html) && /benefit-card/.test(html), 'sección de beneficios');
const benef = html.slice(html.indexOf('id="beneficios"'), html.indexOf('id="catalogo"'));
check(/vencimiento/i.test(benef) && /(normativa|cumplimiento)/i.test(benef),
    'beneficios destacan valor técnico (pérdidas por vencimiento, normativa)');

check(/id="filter-bar"/.test(html) && /id="product-grid"/.test(html), 'showroom con filtro y grilla');
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

const waPlaceholder = api.enlaceWhatsApp
    ? api.enlaceWhatsApp(config)
    : null;
check(waPlaceholder === '#contacto',
    'CTA WhatsApp con placeholder -> sin enlace wa.me inventado (#contacto)');
const waReal = api.enlaceWhatsApp
    ? api.enlaceWhatsApp({ cta: { whatsapp: { is_placeholder: false, phone_placeholder: '+591 70000000' } } })
    : null;
check(waReal === 'https://wa.me/59170000000',
    'CTA WhatsApp configurable: con número autorizado genera wa.me');

check(/loading\s*=\s*['"]lazy['"]|\.loading\s*=\s*['"]lazy['"]/.test(js) || /loading="lazy"/.test(html),
    'imágenes con carga diferida (lazy)');

// --- evidencia ---
const fails = checks.filter((c) => c.status === 'FAIL').length;
const resultado = {
    checkpoint: 'CP-LANDING-03',
    date: new Date().toISOString().slice(0, 10),
    result: fails === 0 ? 'PASSED' : 'FAILED',
    summary: 'Secciones comerciales completas (header/nav, hero, beneficios, showroom con filtro por categoría sin recarga, CTA flotante WhatsApp placeholder, footer) 100% responsivas y sin alcance administrativo.',
    checks,
    total: checks.length,
    failed: fails,
};
mkdirSync(path.join(root, 'tests/results'), { recursive: true });
writeFileSync(path.join(root, 'tests/results/cp-landing-03.json'),
    JSON.stringify(resultado, null, 2) + '\n', 'utf8');
console.log(`RESULTADO: ${resultado.result} (${checks.length - fails}/${checks.length})`);
process.exit(fails === 0 ? 0 : 1);
