// CP-LANDING-01: validador de configuración base y arquitectura.
// Verifica estructura, config.json (Bs./Bolivia/CTA/WhatsApp placeholder),
// shell HTML sin alcance administrativo y sintaxis básica.
// Evidencia: tests/results/cp-landing-01.json
// Uso: node tests/validate_cp_landing_01.mjs
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

// --- 1. estructura del proyecto ---
const estructura = [
    'index.html',
    'assets/css/main.css',
    'assets/js/main.js',
    'data/config.json',
    'components',
    'tests/results',
];
for (const rel of estructura) {
    check(existsSync(path.join(root, rel)), `estructura: ${rel}`);
}

// --- 2. configuración global ---
let config = null;
try {
    config = JSON.parse(readFileSync(path.join(root, 'data/config.json'), 'utf8'));
    check(true, 'config.json: JSON válido');
} catch {
    check(false, 'config.json: JSON válido');
}

if (config) {
    check(config.market?.country === 'Bolivia', 'mercado objetivo: Bolivia');
    check(config.market?.currency === 'Bs.', 'moneda visible: Bs.');
    check(typeof config.cta?.primary?.label === 'string' && config.cta.primary.label.length > 0,
        'CTA comercial configurable (label presente)');
    check(config.cta?.whatsapp?.is_placeholder === true,
        'WhatsApp: placeholder configurable (sin número real)');
    check(/^\D*\d[\d\s]*$/.test(config.cta?.whatsapp?.phone_placeholder || '') || /\d/.test(config.cta?.whatsapp?.phone_placeholder || ''),
        'WhatsApp: placeholder con formato de número');
    check(config.catalog?.categories_count === 7
        && config.catalog?.products_per_category === 10
        && config.catalog?.products_total === 70,
        'catálogo declarado: 7 categorías x 10 productos = 70');
    check(typeof config.contact?.email === 'string', 'contacto configurable presente');
}

// --- 3. shell HTML sin alcance administrativo ---
const html = readFileSync(path.join(root, 'index.html'), 'utf8');
check(/^<!DOCTYPE html>/i.test(html.trim()), 'index.html: DOCTYPE presente');
check(/<html[^>]*\blang="es"/i.test(html), 'index.html: lang="es"');
check(html.includes('assets/css/main.css') && html.includes('assets/js/main.js'),
    'index.html: referencia CSS y JS propios');
check(html.includes('data-') || html.includes('id="contenido"'), 'index.html: estructura semántica básica');

const prohibidos = ['login', 'registro', 'dashboard', 'panel', 'carrito', 'checkout', 'crud', 'roles', 'permisos'];
const lower = html.toLowerCase();
const encontrados = prohibidos.filter((p) => new RegExp(`href="[^"]*${p}|/${p}["/]`, 'i').test(html));
check(encontrados.length === 0, `sin rutas ni enlaces administrativos${encontrados.length ? ' (' + encontrados.join(', ') + ')' : ''}`);
check(!/<form[^>]*(pago|payment|checkout)/i.test(html), 'sin formularios de pago/checkout');
check(!lower.includes('iniciar sesión') && !lower.includes('iniciar sesion'), 'sin prompt de login');

// --- 4. sin dependencias externas en el shell ---
check(!/<script[^>]*src="https?:/i.test(html), 'sin scripts externos');
check(!/<link[^>]*href="https?:/i.test(html), 'sin CSS externo');

// --- evidencia ---
const fails = checks.filter((c) => c.status === 'FAIL').length;
const resultado = {
    checkpoint: 'CP-LANDING-01',
    date: new Date().toISOString().slice(0, 10),
    result: fails === 0 ? 'PASSED' : 'FAILED',
    summary: 'Configuración base y arquitectura de proyecto/07_landing (estructura, config.json con Bs./Bolivia/CTA/WhatsApp placeholder, shell HTML sin alcance administrativo, sin dependencias externas).',
    checks,
    total: checks.length,
    failed: fails,
};
mkdirSync(path.join(root, 'tests/results'), { recursive: true });
writeFileSync(path.join(root, 'tests/results/cp-landing-01.json'),
    JSON.stringify(resultado, null, 2) + '\n', 'utf8');
console.log(`RESULTADO: ${resultado.result} (${checks.length - fails}/${checks.length})`);
process.exit(fails === 0 ? 0 : 1);
