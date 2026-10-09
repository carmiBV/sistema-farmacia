// CP-LANDING-02: validador del catálogo demostrativo (7 x 10 = 70).
// Verifica estructura, precios en Bs., imágenes con licencia y fuente
// verificable (Wikimedia Commons) y que cada URL responde HTTP 200.
// Evidencia: tests/results/cp-landing-02.json
// Uso: node tests/validate_cp_landing_02.mjs
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

let catalogo = null;
try {
    catalogo = JSON.parse(readFileSync(path.join(root, 'data/catalog.json'), 'utf8'));
    check(true, 'catalog.json: JSON válido');
} catch {
    check(false, 'catalog.json: JSON válido');
}

if (catalogo) {
    const cats = catalogo.categories ?? [];
    check(cats.length === 7, `exactamente 7 categorías (hay ${cats.length})`);
    check(catalogo.currency === 'Bs.', 'moneda del catálogo: Bs.');

    const productos = cats.flatMap((c) => c.products ?? []);
    check(productos.length === 70, `exactamente 70 productos (hay ${productos.length})`);
    check(cats.every((c) => (c.products ?? []).length === 10), 'cada categoría tiene 10 productos');

    check(productos.every((p) => typeof p.price === 'number' && p.price > 0),
        'todos los precios son numéricos > 0');
    check(productos.every((p) => /^Bs\.\s\d/.test(p.price_display ?? '')),
        'todos los precios se muestran en Bs.');

    // CP-LANDING-06: promos demostrativas con precio_anterior tachado (DESIGN.md)
    check(productos.every((p) => p.precio_anterior === undefined
        || (typeof p.precio_anterior === 'number' && p.precio_anterior > p.price)),
        'precio_anterior (promos): numérico y mayor que el precio actual');
    check(productos.some((p) => p.precio_anterior !== undefined),
        'el catálogo incluye promos demostrativas con precio_anterior');

    const ids = new Set(productos.map((p) => p.id));
    check(ids.size === 70, 'identificadores de producto únicos');

    check(productos.every((p) => p.image_url && p.image_url.startsWith('https://')),
        'todas las imágenes son URLs externas https');
    check(productos.every((p) => p.image_provider === 'Wikimedia Commons'),
        'proveedor de imágenes declarado (Wikimedia Commons)');
    check(productos.every((p) => (p.image_license ?? '') !== '' && p.image_license !== 'SIN IMAGEN'),
        'cada imagen tiene licencia declarada');
    check(productos.every((p) => (p.image_source ?? '').startsWith('https://commons.wikimedia.org/wiki/')),
        'cada imagen tiene fuente verificable (página del archivo en Commons)');
    check(productos.every((p) => (p.image_alt ?? '').length > 0), 'cada imagen tiene texto alternativo');

    // URLs funcionales en vivo (la política exige imágenes públicas operativas).
    // upload.wikimedia.org limita por IP con ventana de decaimiento: varios
    // barridos con pausa entre ellos; cada URL debe responder 200 en al menos
    // un barrido (404/0 = fallo real; 429 = se reintenta en el siguiente).
    const resultados = new Map();
    const probar = async (p) => {
        try {
            const r = await fetch(p.image_url, {
                method: 'HEAD',
                headers: { 'User-Agent': 'SistemaFarmaciaLanding/1.0 (image check)' },
            });
            return r.status;
        } catch {
            return 0;
        }
    };

    let pendientes = [...productos];
    for (let barrido = 0; barrido < 3 && pendientes.length > 0; barrido++) {
        if (barrido > 0) {
            console.log(`INFO: enfriamiento 60s antes del barrido ${barrido + 1} (${pendientes.length} URLs pendientes)`);
            await new Promise((res) => setTimeout(res, 60000));
        }
        const siguen = [];
        for (const p of pendientes) {
            const status = await probar(p);
            resultados.set(p.id, status);
            if (status !== 200) siguen.push(p);
            await new Promise((res) => setTimeout(res, 800));
        }
        pendientes = siguen;
    }
    const fallidas = [...resultados.entries()].filter(([, s]) => s !== 200);
    check(fallidas.length === 0,
        `las 70 URLs de imagen responden HTTP 200 (${70 - fallidas.length}/70)` +
        (fallidas.length ? ` — sin 200: ${fallidas.map(([id, s]) => `${id}=>${s}`).join(', ')}` : ''));
    var detalleUrls = [...resultados.entries()].map(([id, status]) => ({ id, status }));
}

const fails = checks.filter((c) => c.status === 'FAIL').length;
const resultado = {
    checkpoint: 'CP-LANDING-02',
    date: new Date().toISOString().slice(0, 10),
    result: fails === 0 ? 'PASSED' : 'FAILED',
    summary: 'Catálogo demostrativo data/catalog.json: 7 categorías x 10 productos = 70, precios en Bs., imágenes de Wikimedia Commons con licencia y fuente verificable por archivo, URLs operativas.',
    checks,
    total: checks.length,
    failed: fails,
    image_urls: detalleUrls ?? [],
    sources_file: 'tests/results/cp-landing-02-sources.json',
};
mkdirSync(path.join(root, 'tests/results'), { recursive: true });
writeFileSync(path.join(root, 'tests/results/cp-landing-02.json'),
    JSON.stringify(resultado, null, 2) + '\n', 'utf8');
console.log(`RESULTADO: ${resultado.result} (${checks.length - fails}/${checks.length})`);
process.exit(fails === 0 ? 0 : 1);
