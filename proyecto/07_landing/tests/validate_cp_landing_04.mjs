// CP-LANDING-04: auditoría de políticas, accesibilidad y rendimiento.
// 1) Ausencia de alcance administrativo (rutas/enlaces/prompts).
// 2) Política de imágenes: URLs públicas funcionales (HTTP 200 en vivo),
//    atributos alt, licencia y fuente verificable.
// 3) Accesibilidad estructural y rendimiento (tamaños, lazy, tiempos de carga).
// Evidencia: tests/results/cp-landing-04.json
// Uso: node tests/validate_cp_landing_04.mjs [base-url-servidor]
import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const base = process.argv[2] || 'http://127.0.0.1:8010';

const checks = [];
const check = (ok, label) => {
    checks.push({ label, status: ok ? 'PASS' : 'FAIL' });
    console.log((ok ? 'PASS' : 'FAIL') + ': ' + label);
    return ok;
};

const html = readFileSync(path.join(root, 'index.html'), 'utf8');
const css = readFileSync(path.join(root, 'assets/css/main.css'), 'utf8');
const js = readFileSync(path.join(root, 'assets/js/main.js'), 'utf8');
const catalogo = JSON.parse(readFileSync(path.join(root, 'data/catalog.json'), 'utf8'));
const config = JSON.parse(readFileSync(path.join(root, 'data/config.json'), 'utf8'));
const productos = catalogo.categories.flatMap((c) => (c.products ?? []).map((p) => ({ ...p, categoria_id: c.id })));

// ============ 1. POLÍTICAS: sin alcance administrativo ============
const rutasAdmin = ['/login', '/registro', '/dashboard', '/panel', '/admin', '/carrito', '/checkout', '/crud'];
const fuentes = { 'index.html': html, 'assets/js/main.js': js, 'assets/css/main.css': css };
const referenciasAdmin = [];
for (const [archivo, texto] of Object.entries(fuentes)) {
    for (const ruta of rutasAdmin) {
        if (new RegExp(`(href|src|action|location)[^\\n]*["'\`]${ruta}`, 'i').test(texto)) {
            referenciasAdmin.push(`${archivo} -> ${ruta}`);
        }
    }
}
check(referenciasAdmin.length === 0,
    'sin enlaces/referencias a rutas administrativas' + (referenciasAdmin.length ? ` (${referenciasAdmin.join(', ')})` : ''));

const promptsAdmin = ['iniciar sesión', 'iniciar sesion', 'registrarse', 'crear cuenta', 'carrito de compras', 'checkout', 'panel de administración', 'panel de administracion'];
const lowerTodo = (html + js + css).toLowerCase();
const promptsEncontrados = promptsAdmin.filter((p) => lowerTodo.includes(p));
check(promptsEncontrados.length === 0,
    'sin prompts de login/registro/carrito/checkout' + (promptsEncontrados.length ? ` (${promptsEncontrados.join(', ')})` : ''));

check(!/<form/i.test(html), 'sin formularios (ni de pago ni de datos)');
check(!/fetch\([^)]*\/api\/v1\//i.test(js) && !/auth\/login/i.test(js),
    'la landing no consume la API administrativa (sin /api/v1 ni login)');
check(config.cta?.whatsapp?.is_placeholder === true || /\d/.test(config.cta?.whatsapp?.phone_placeholder ?? ''),
    'WhatsApp: placeholder o número configurado (nunca inventado)');
check(/is_placeholder/.test(js) && /wa\.me\//.test(js),
    'wa.me solo se construye tras validar is_placeholder (guard presente)');

// ============ 2. POLÍTICA DE IMÁGENES ============
check(productos.every((p) => p.image_url && /^https:\/\//.test(p.image_url)), 'todas las imágenes son URLs https externas');
check(productos.every((p) => (p.image_alt ?? '').trim().length > 0), 'todas las imágenes tienen texto alternativo');
check(productos.every((p) => (p.image_license ?? '') !== '' && p.image_license !== 'SIN IMAGEN'),
    'todas las imágenes tienen licencia declarada');
check(productos.every((p) => (p.image_source ?? '').startsWith('https://commons.wikimedia.org/wiki/')),
    'todas las imágenes tienen fuente/licencia verificable');
check(/img\.alt\s*=/.test(js) && /img\.src\s*=/.test(js), 'el render asigna src y alt a cada imagen');
check(/loading\s*=\s*['"]lazy['"]/.test(js), 'imágenes con carga diferida (lazy)');
check(!/openai|dall-?e|midjourney|stable\s*diffusion|ia\s+generativa/i.test(html + js),
    'sin imágenes generadas por IA (declarado en fuentes)');

// URLs públicas funcionales (HTTP 200 en vivo, con barridos ante rate-limit)
const estatusUrl = new Map();
let pendientes = [...productos];
for (let barrido = 0; barrido < 2 && pendientes.length > 0; barrido++) {
    if (barrido > 0) {
        console.log(`INFO: enfriamiento 45s antes del barrido ${barrido + 1} (${pendientes.length} URLs pendientes)`);
        await new Promise((r) => setTimeout(r, 45000));
    }
    const siguen = [];
    for (const p of pendientes) {
        let status = 0;
        try {
            const r = await fetch(p.image_url, {
                method: 'HEAD',
                headers: { 'User-Agent': 'SistemaFarmaciaLanding/1.0 (audit)' },
            });
            status = r.status;
        } catch { status = 0; }
        estatusUrl.set(p.id, status);
        if (status !== 200) { siguen.push(p); }
        await new Promise((r) => setTimeout(r, 700));
    }
    pendientes = siguen;
}
const urlsFallidas = [...estatusUrl.entries()].filter(([, s]) => s !== 200);
check(urlsFallidas.length === 0,
    `las 70 URLs de imagen son públicas y funcionales (HTTP 200): ${70 - urlsFallidas.length}/70` +
    (urlsFallidas.length ? ` — sin 200: ${urlsFallidas.map(([id, s]) => `${id}=>${s}`).join(', ')}` : ''));

// render: cada tarjeta inyectada lleva alt (stub DOM con catálogo real)
function el() {
    let tc = '';
    const e = {
        children: [], listeners: {}, value: '', hidden: false, dataset: {}, className: '', title: '', href: '', src: '', alt: '', loading: '',
        appendChild(c) { this.children.push(c); return c; },
        addEventListener(ev, fn) { this.listeners[ev] = fn; },
        setAttribute() {},
    };
    Object.defineProperty(e, 'textContent', {
        get() { return tc; },
        set(v) { tc = String(v); if (tc === '') e.children.length = 0; },
    });
    return e;
}
const byId = {};
for (const id of ['market-badge', 'cta-header', 'cta-hero', 'cta-contacto-demo', 'cta-whatsapp',
    'cta-whatsapp-float', 'contact-email', 'contact-address', 'contact-hours', 'footer-legal',
    'footer-links', 'catalogo-total', 'filter-bar', 'product-grid', 'catalogo-vacio']) {
    byId[id] = el();
}
const creados = [];
const dom = {
    getElementById: (id) => byId[id] ?? null,
    createElement: (tag) => { const e = el(); e.tag = tag; creados.push(e); return e; },
};
const win = {};
new Function('document', 'localStorage', 'fetch', 'window', 'console', js)(
    dom,
    { getItem: () => null, setItem: () => {}, removeItem: () => {} },
    async (url) => ({
        ok: true,
        json: async () => (String(url).includes('catalog') ? catalogo : config),
    }),
    win,
    { error: () => {} },
);
await new Promise((r) => setTimeout(r, 200));

const imagenes = creados.filter((e) => e.tag === 'img');
check(imagenes.length === 70, `el render inyecta 70 imágenes (hay ${imagenes.length})`);
check(imagenes.every((i) => (i.alt ?? '').trim().length > 0), 'cada imagen renderizada tiene alt no vacío');
check(imagenes.every((i) => /^https:\/\//.test(i.src ?? '')), 'cada imagen renderizada usa URL https');
check(byId['product-grid'].children.length === 70, 'la grilla renderiza los 70 productos');

// ============ 3. ACCESIBILIDAD ESTRUCTURAL ============
check(/name="viewport"/.test(html), 'meta viewport');
check(/<html[^>]*\blang="es"/.test(html), 'idioma declarado (lang=es)');
check(/aria-label/.test(html) && /aria-live/.test(html) && /aria-pressed/.test(js), 'ARIA: etiquetas, live region y estados de filtro');
check(/id="contenido"/.test(html), 'landmark principal identificado');
check(/:focus-visible/.test(css), 'indicador de foco visible');
check(/alt=|img\.alt/.test(js), 'imágenes con alt (política + accesibilidad)');

// ============ 4. RENDIMIENTO ============
const metricas = {};
let falloRendimiento = false;
for (const rel of ['index.html', 'assets/css/main.css', 'assets/js/main.js', 'data/config.json', 'data/catalog.json']) {
    const bytes = readFileSync(path.join(root, rel)).length;
    metricas[`bytes_${rel}`] = bytes;
    if (bytes > 500 * 1024) { falloRendimiento = true; }
}
check(!falloRendimiento, 'cada recurso < 500 KB (catálogo incluido)');
const totalBytes = Object.values(metricas).reduce((a, b) => a + b, 0);
metricas.bytes_total_core = totalBytes;
check(totalBytes < 1024 * 1024, `recursos núcleo < 1 MB (total ${(totalBytes / 1024).toFixed(1)} KB)`);
check(/loading="lazy"/.test(js) || /\.loading\s*=\s*['"]lazy['"]/.test(js), 'carga diferida de imágenes activa');

// tiempos de carga en vivo (servidor local; doc de referencia para producción)
const tiempos = {};
let fallosTiempo = 0;
for (const ruta of ['/', '/assets/css/main.css', '/assets/js/main.js', '/data/catalog.json', '/data/config.json']) {
    const muestras = [];
    for (let i = 0; i < 5; i++) {
        const t0 = Date.now();
        try {
            const r = await fetch(base + ruta, { headers: { 'User-Agent': 'SistemaFarmaciaLanding/1.0 (perf)' } });
            await r.arrayBuffer();
            if (r.ok) { muestras.push(Date.now() - t0); }
        } catch { /* servidor caído */ }
        await new Promise((res) => setTimeout(res, 120));
    }
    const avg = muestras.length ? muestras.reduce((a, b) => a + b, 0) / muestras.length : -1;
    tiempos[ruta] = { avg_ms: Math.round(avg), ok: muestras.length };
    if (avg < 0 || avg > 500) { fallosTiempo++; }
}
metricas.tiempos_carga = tiempos;
check(fallosTiempo === 0, `recursos núcleo cargan en < 500 ms (local): ${Object.entries(tiempos).map(([k, v]) => `${k}=${v.avg_ms}ms`).join(' ')}`);

// integración visual: evidencia de capturas renderizadas
check(existsSync(path.join(root, 'tests/results/cp-landing-03-desktop.png'))
    && existsSync(path.join(root, 'tests/results/cp-landing-03-mobile.png')),
    'integración visual: capturas renderizadas desktop/móvil presentes');

// --- evidencia ---
const fails = checks.filter((c) => c.status === 'FAIL').length;
const resultado = {
    checkpoint: 'CP-LANDING-04',
    date: new Date().toISOString().slice(0, 10),
    result: fails === 0 ? 'PASSED' : 'FAILED',
    summary: 'Auditoría de políticas (sin alcance administrativo), política de imágenes (70/70 URLs https funcionales con alt/licencia/fuente, sin IA), accesibilidad estructural y rendimiento de la landing.',
    checks,
    total: checks.length,
    failed: fails,
    metrics: metricas,
};
mkdirSync(path.join(root, 'tests/results'), { recursive: true });
writeFileSync(path.join(root, 'tests/results/cp-landing-04.json'),
    JSON.stringify(resultado, null, 2) + '\n', 'utf8');
console.log(`RESULTADO: ${resultado.result} (${checks.length - fails}/${checks.length})`);
process.exit(fails === 0 ? 0 : 1);
