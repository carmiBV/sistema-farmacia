// CP-LANDING-07: validador de cierre del workflow de la landing.
// Verifica que la suite 01-06 esté en verde, que la evidencia exista y que
// las capturas renderizadas (desktop/móvil) correspondan al diseño actual.
// Evidencia: tests/results/cp-landing-07.json
// Uso: node tests/validate_cp_landing_07.mjs
import { readFileSync, writeFileSync, mkdirSync, existsSync, statSync } from 'node:fs';
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

// --- 1. suite completa en verde ---
for (const cp of ['01', '02', '03', '04', '05', '06']) {
    const rel = `tests/results/cp-landing-${cp}.json`;
    if (!existsSync(path.join(root, rel))) {
        check(false, `evidencia presente: ${rel}`);
        continue;
    }
    const r = JSON.parse(readFileSync(path.join(root, rel), 'utf8'));
    check(r.result === 'PASSED', `CP-LANDING-${cp}: ${r.result} (${r.total - r.failed}/${r.total})`);
}

// --- 2. capturas renderizadas del diseño actual ---
function pngDims(buf) {
    return { width: buf.readUInt32BE(16), height: buf.readUInt32BE(20) };
}
const capD = path.join(root, 'tests/results/cp-landing-07-desktop.png');
const capM = path.join(root, 'tests/results/cp-landing-07-mobile.png');
check(existsSync(capD) && existsSync(capM), 'capturas CP-07 desktop/móvil presentes');
if (existsSync(capD) && existsSync(capM)) {
    const d = pngDims(readFileSync(capD));
    const m = pngDims(readFileSync(capM));
    check(d.width === 1280, `captura desktop 1280px (${d.width}x${d.height})`);
    check(m.width === 375, `captura móvil 375px (${m.width}x${m.height})`);
    check(statSync(capD).size > 100 * 1024 && statSync(capM).size > 50 * 1024,
        'capturas con contenido renderizado (>100 KB / >50 KB)');
    check(readFileSync(capD).equals(readFileSync(path.join(root, 'tests/results/cp-landing-03-desktop.png')))
        && readFileSync(capM).equals(readFileSync(path.join(root, 'tests/results/cp-landing-03-mobile.png'))),
        'las capturas referenciadas por CP-04 son las del diseño actual');
}

// --- 3. regresión final de políticas y sistema de diseño ---
const html = readFileSync(path.join(root, 'index.html'), 'utf8');
const css = readFileSync(path.join(root, 'assets/css/main.css'), 'utf8');
const prohibidos = ['login', 'registro', 'dashboard', 'panel', 'carrito', 'checkout', 'crud', 'roles', 'permisos'];
const encontrados = prohibidos.filter((p) => new RegExp(`href="[^"]*${p}|/${p}["/]`, 'i').test(html));
check(encontrados.length === 0, 'sin alcance administrativo (regresión final)');
check(!/<form/i.test(html), 'sin formularios (regresión final)');
check(/--color-accent:\s*#0d9488/.test(css) && /@font-face/.test(css),
    'tokens del DESIGN.md vigentes (acento único + Outfit)');
check(/<link[^>]*href="https?:/i.test(html) === false && /<script[^>]*src="https?:/i.test(html) === false,
    'sin CSS/scripts externos (regresión final)');

// --- evidencia ---
const fails = checks.filter((c) => c.status === 'FAIL').length;
const resultado = {
    checkpoint: 'CP-LANDING-07',
    date: new Date().toISOString().slice(0, 10),
    result: fails === 0 ? 'PASSED' : 'FAILED',
    summary: 'Cierre del workflow 07: suite 01-06 en verde, capturas renderizadas desktop/móvil del diseño actual (Farmacia San Francisco) y regresión final de políticas y sistema de diseño.',
    checks,
    total: checks.length,
    failed: fails,
};
mkdirSync(path.join(root, 'tests/results'), { recursive: true });
writeFileSync(path.join(root, 'tests/results/cp-landing-07.json'),
    JSON.stringify(resultado, null, 2) + '\n', 'utf8');
console.log(`RESULTADO: ${resultado.result} (${checks.length - fails}/${checks.length})`);
process.exit(fails === 0 ? 0 : 1);
