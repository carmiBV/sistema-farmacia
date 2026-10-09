// CP-LANDING-05: validador de tokens y componentes del DESIGN.md.
// Verifica paleta, tipografía Outfit auto-hospedada, jerarquías, radios,
// espacios, elevación, botones/chips/cards, focus-visible y reduced-motion.
// Evidencia: tests/results/cp-landing-05.json
// Uso: node tests/validate_cp_landing_05.mjs
import { readFileSync, writeFileSync, existsSync, mkdirSync } from 'node:fs';
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

const css = readFileSync(path.join(root, 'assets/css/main.css'), 'utf8');

// --- 1. paleta (DESIGN.md §2) ---
const colores = {
    '--color-bg': '#f8fafc',
    '--color-surface': '#ffffff',
    '--color-ink': '#0f172a',
    '--color-ink-muted': '#475569',
    '--color-line': '#e2e8f0',
    '--color-accent': '#0d9488',
    '--color-accent-hover': '#0f766e',
    '--color-on-accent': '#ffffff',
    '--color-warning': '#b45309',
};
for (const [token, hex] of Object.entries(colores)) {
    check(new RegExp(`${token}:\\s*${hex}`, 'i').test(css), `token de color ${token} = ${hex}`);
}
check(!/#000000\b|:\s*#000\b/i.test(css), 'sin puro negro (#000 prohibido por DESIGN.md)');

// --- 2. tipografía (DESIGN.md §3) ---
check(/@font-face/.test(css) && /'Outfit'/.test(css), 'Outfit declarada (@font-face)');
check(/url\('\.\.\/fonts\/outfit-latin\.woff2'\)/.test(css)
    && existsSync(path.join(root, 'assets/fonts/outfit-latin.woff2')),
    'Outfit latin auto-hospedada (archivo presente, sin CDN)');
check(/url\('\.\.\/fonts\/outfit-latin-ext\.woff2'\)/.test(css)
    && existsSync(path.join(root, 'assets/fonts/outfit-latin-ext.woff2')),
    'Outfit latin-ext auto-hospedada (archivo presente)');
check(!/<link[^>]*href="https?:/i.test(readFileSync(path.join(root, 'index.html'), 'utf8'))
    && !/<script[^>]*src="https?:/i.test(readFileSync(path.join(root, 'index.html'), 'utf8')),
    'sin CSS/scripts externos (validador CP-01 conservado)');
check(/font-weight:\s*800/.test(css) && /font-weight:\s*600/.test(css), 'escala de peso 800/600 presente');
check(/clamp\(2rem,\s*4\.5vw,\s*3\.25rem\)/.test(css), 'display/H1: clamp(2rem, 4.5vw, 3.25rem)');
check(/clamp\(1\.5rem,\s*3vw,\s*2\.25rem\)/.test(css), 'headline/H2: clamp(1.5rem, 3vw, 2.25rem)');
check(/tabular-nums/.test(css), 'price: tabular-nums');
check(/max-width:\s*65ch/.test(css), 'body: max-width 65ch en párrafos');
check(!/\bInter\b/.test(css), 'Inter vetado (DESIGN.md)');

// --- 3. radios y espaciado (frontmatter DESIGN.md) ---
check(/--radius-sm:\s*6px/.test(css) && /--radius-md:\s*10px/.test(css) && /--radius-lg:\s*16px/.test(css),
    'radios 6/10/16px');
check(/--space-6:\s*24px/.test(css) && /--space-24:\s*96px/.test(css), 'escala de espaciado (4→96px)');

// --- 4. elevación y estados (DESIGN.md §4) ---
check(/rgba\(15,\s*23,\s*42,\s*\.08\)/.test(css), 'sombra difusa tintada (0 20px 40px -15px)');
check(/\.site-header\.is-scrolled/.test(css), 'elevación en header al hacer scroll (.is-scrolled)');
check(/@media \(hover: hover\)/.test(css), 'sombra de tarjeta solo en hover (hover: hover)');

// --- 5. componentes (DESIGN.md §5) ---
check(/\.btn-primary/.test(css) && /--color-accent-hover/.test(css), 'botón primario (accent → accent-hover)');
check(/\.btn-secondary/.test(css), 'botón secundario (borde ink, hover invertido)');
check(/\.btn-on-accent|\.btn-whatsapp/.test(css), 'botón on-accent (CTA final / WhatsApp)');
check(/transform:\s*scale\(\.98\)/.test(css), 'botón :active scale(.98)');
check(/min-height:\s*48px/.test(css), 'target táctil min-height 48px');
check(/\.filter-chip/.test(css) && /\.market-badge/.test(css) && /\.product-category/.test(css),
    'chips/badges (radio 6px, borde line, texto muted)');
check(/\.benefit-card/.test(css) && /\.product-card/.test(css) && /--radius-lg/.test(css),
    'cards (radio 16px, borde 1px en reposo)');
check(/\.price-anterior/.test(css) && /line-through/.test(css),
    'precio_anterior tachado (ámbar solo para precio/alerta)');

// --- 6. accesibilidad y movimiento ---
check(/:focus-visible/.test(css) && /outline:\s*2px solid var\(--color-accent\)/.test(css),
    'focus-visible 2px teal con outline-offset');
check(/@media \(prefers-reduced-motion: reduce\)/.test(css), 'prefers-reduced-motion obligatorio');
check(/transform|opacity/.test(css) && /transition:/.test(css), 'movimiento CSS nativo (transform/opacity)');

// --- 7. asserts conservados de CP-03/CP-04 ---
check(/scroll-behavior:\s*smooth/.test(css), 'scroll-behavior: smooth (CP-03 conservado)');
check(/repeat\(auto-(fit|fill)/.test(css), 'grillas auto-fit/auto-fill (CP-03 conservado)');
check(/@media \(max-width: 768px\)/.test(css), 'fallback 1 columna < 768px (regla de layout)');

// --- evidencia ---
const fails = checks.filter((c) => c.status === 'FAIL').length;
const resultado = {
    checkpoint: 'CP-LANDING-05',
    date: new Date().toISOString().slice(0, 10),
    result: fails === 0 ? 'PASSED' : 'FAILED',
    summary: 'Tokens y componentes del DESIGN.md aplicados: paleta de un acento, Outfit auto-hospedada 400/600/800, jerarquías, radios/espaciado, plano por defecto con sombra en scroll/hover, botones/chips/cards, focus-visible y reduced-motion.',
    checks,
    total: checks.length,
    failed: fails,
};
mkdirSync(path.join(root, 'tests/results'), { recursive: true });
writeFileSync(path.join(root, 'tests/results/cp-landing-05.json'),
    JSON.stringify(resultado, null, 2) + '\n', 'utf8');
console.log(`RESULTADO: ${resultado.result} (${checks.length - fails}/${checks.length})`);
process.exit(fails === 0 ? 0 : 1);
