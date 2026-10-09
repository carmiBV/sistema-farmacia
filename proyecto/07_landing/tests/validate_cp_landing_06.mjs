// CP-LANDING-06: validador de la reestructura de contenido (DESIGN.md).
// Verifica marca, hero 55/45, zig-zag de 7 categorías, catálogo con
// precio_anterior, Farmacia de Turno, atención, CTA final on-accent y la
// política de imágenes de sección (Commons con licencia/fuente, sin IA).
// Evidencia: tests/results/cp-landing-06.json
// Uso: node tests/validate_cp_landing_06.mjs
import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
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
const catalogo = JSON.parse(readFileSync(path.join(root, 'data/catalog.json'), 'utf8'));
const imagenes = JSON.parse(readFileSync(path.join(root, 'data/imagenes.json'), 'utf8'));

// --- 1. marca y estructura requisito ---
check(config.brand?.name === 'Farmacia San Francisco', 'config: marca Farmacia San Francisco');
check(/<title>[^<]*Farmacia San Francisco/.test(html) && /class="brand"[^>]*>Farmacia San Francisco</.test(html),
    'HTML: marca visible en title y logo');
check(/grid-template-columns:\s*55% 45%/.test(css) && /class="hero-media"/.test(html),
    'hero 55/45 con fotografía (DESIGN.md §6)');
check(/class="cat-zigzag"/.test(html) && /\.cat-row:nth-child\(even\)/.test(css),
    'categorías en zig-zag de 2 columnas (orden alternado)');
const cats = html.slice(html.indexOf('id="categorias"'), html.indexOf('id="catalogo"'));
check((cats.match(/class="cat-row"/g) || []).length === 7, '7 categorías en zig-zag');
for (const c of catalogo.categories ?? []) {
    check(cats.includes(`<h3>${c.name}</h3>`), `categoría visible: ${c.name}`);
}
check(/id="turno"/.test(html) && /<h2 id="turno-title">Farmacia de Turno<\/h2>/.test(html),
    'sección "Farmacia de Turno" (H2 requisito)');
check(/id="contacto"/.test(html) && /class="atencion"/.test(html) && /id="contact-list"/.test(html),
    'sección "Atención" con contacto');
check(/class="cta-section"/.test(html) && /id="cta-whatsapp"/.test(html),
    'bloque CTA final con Envío WhatsApp');
check(config.cta?.whatsapp?.label === 'Envío WhatsApp', 'CTA WhatsApp etiquetado "Envío WhatsApp" (DESIGN.md)');
check(/price-anterior/.test(js) && /precio_anterior/.test(js),
    'precio_anterior renderizado tachado (promos demostrativas)');
check(/demostrativ/i.test(html), 'datos marcados como demostrativos (voz del sistema de diseño)');
check(/\.site-header\.is-scrolled/.test(css) && /is-scrolled/.test(js),
    'elevación del header en scroll (.is-scrolled)');

// --- 2. política de imágenes de sección ---
check(existsSync(path.join(root, 'data/imagenes.json')), 'data/imagenes.json presente (fuentes de imágenes)');
const imgs = imagenes.images ?? [];
check(imgs.length === 8, `8 imágenes de sección (hero + 7 categorías): hay ${imgs.length}`);
check(imgs.every((i) => i.image_url && /^https:\/\//.test(i.image_url)), 'imágenes de sección: URLs https externas');
check(imgs.every((i) => (i.image_alt ?? '').trim().length > 0), 'imágenes de sección: alt declarado');
check(imgs.every((i) => (i.image_license ?? '') !== '' && i.image_license !== 'SIN IMAGEN'),
    'imágenes de sección: licencia declarada');
check(imgs.every((i) => (i.image_source ?? '').startsWith('https://commons.wikimedia.org/wiki/')),
    'imágenes de sección: fuente verificable (página del archivo en Commons)');
check(!/openai|dall-?e|midjourney|stable\s*diffusion|ia\s+generativa/i.test(html + js + JSON.stringify(imagenes)),
    'sin imágenes generadas por IA');

const imgsHtml = [...html.matchAll(/<img\s[^>]*src="([^"]+)"[^>]*alt="([^"]*)"/g)];
check(imgsHtml.length === 8, `HTML embebe 8 <img> de sección (hay ${imgsHtml.length})`);
const urlsHtml = new Set(imgsHtml.map((m) => m[1]));
const urlsData = new Set(imgs.map((i) => i.image_url));
check([...urlsHtml].every((u) => urlsData.has(u)) && [...urlsData].every((u) => urlsHtml.has(u)),
    'cada <img> del HTML corresponde 1:1 con data/imagenes.json');
check(imgsHtml.every((m) => m[2].trim().length > 0), 'cada <img> del HTML tiene alt no vacío');
check(imgs.every((i) => html.includes(`href="${i.image_source}"`)),
    'cada imagen lleva crédito con enlace a su fuente (licencia verificable)');

// URLs públicas funcionales (HTTP 200 en vivo)
let fallidas = [];
for (const i of imgs) {
    let status = 0;
    try {
        const r = await fetch(i.image_url, {
            method: 'HEAD',
            headers: { 'User-Agent': 'SistemaFarmaciaLanding/1.0 (cp06 audit)' },
        });
        status = r.status;
    } catch { status = 0; }
    if (status !== 200) { fallidas.push(`${i.id}=>${status}`); }
    await new Promise((r) => setTimeout(r, 700));
}
check(fallidas.length === 0, `las 8 URLs de sección responden HTTP 200${fallidas.length ? ' — sin 200: ' + fallidas.join(', ') : ''}`);

// --- 3. alcance y regresión ---
const prohibidos = ['login', 'registro', 'dashboard', 'panel', 'carrito', 'checkout', 'crud', 'roles', 'permisos'];
const lower = html.toLowerCase();
const encontrados = prohibidos.filter((p) => new RegExp(`href="[^"]*${p}|/${p}["/]`, 'i').test(html));
check(encontrados.length === 0, `sin rutas ni enlaces administrativos${encontrados.length ? ' (' + encontrados.join(', ') + ')' : ''}`);
check(!/<form/i.test(html), 'sin formularios');
check(/is_placeholder/.test(js) && /wa\.me\//.test(js), 'guard de placeholder de WhatsApp conservado');
const digitosWa = String(config.cta?.whatsapp?.phone_placeholder ?? '').replace(/\D/g, '');
check(config.cta?.whatsapp?.is_placeholder === false && digitosWa === '59172248223',
    'número autorizado por el cliente conservado (+591 72248223)');

// --- evidencia ---
const fails = checks.filter((c) => c.status === 'FAIL').length;
const resultado = {
    checkpoint: 'CP-LANDING-06',
    date: new Date().toISOString().slice(0, 10),
    result: fails === 0 ? 'PASSED' : 'FAILED',
    summary: 'Reestructura de contenido según DESIGN.md: marca Farmacia San Francisco, hero 55/45, categorías zig-zag, catálogo con precio_anterior, Farmacia de Turno, atención y CTA final; imágenes de sección en Commons con licencia/fuente y sin IA.',
    checks,
    total: checks.length,
    failed: fails,
};
mkdirSync(path.join(root, 'tests/results'), { recursive: true });
writeFileSync(path.join(root, 'tests/results/cp-landing-06.json'),
    JSON.stringify(resultado, null, 2) + '\n', 'utf8');
console.log(`RESULTADO: ${resultado.result} (${checks.length - fails}/${checks.length})`);
process.exit(fails === 0 ? 0 : 1);
