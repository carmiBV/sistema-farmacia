// CP-LANDING-06: sourcing de imagenes de seccion (hero + 7 categorias)
// desde Wikimedia Commons (API abierta). Sin IA, sin inventar URLs.
// Escribe data/imagenes.json y tests/results/cp-landing-06-sources.json.
// Uso: node tests/source_landing_images.mjs
import { writeFileSync, mkdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');

const SECCIONES = [
    { id: 'hero', uso: 'hero', categoria: null, termino: 'pharmacy counter interior', fallback: 'pharmacy' },
    { id: 'cat-medicamentos-receta', uso: 'categoria', categoria: 'medicamentos-receta', termino: 'prescription medicine blister pack', fallback: 'medicine pills' },
    { id: 'cat-medicamentos-venta-libre', uso: 'categoria', categoria: 'medicamentos-venta-libre', termino: 'over the counter medicine shelf pharmacy', fallback: 'medicine box' },
    { id: 'cat-dermocosmetica-cuidado-personal', uso: 'categoria', categoria: 'dermocosmetica-cuidado-personal', termino: 'skin care cosmetics bottles', fallback: 'cosmetics bottles' },
    { id: 'cat-bebe-maternidad', uso: 'categoria', categoria: 'bebe-maternidad', termino: 'baby care products', fallback: 'baby products' },
    { id: 'cat-nutricion-suplementos', uso: 'categoria', categoria: 'nutricion-suplementos', termino: 'dietary supplement bottles vitamins', fallback: 'dietary supplements' },
    { id: 'cat-primeros-auxilios', uso: 'categoria', categoria: 'primeros-auxilios-cuidado-heridas', termino: 'first aid kit supplies', fallback: 'first aid kit' },
    { id: 'cat-equipos-medicion', uso: 'categoria', categoria: 'equipos-medicion-cuidado-hogar', termino: 'digital blood pressure monitor', fallback: 'medical device home' },
];

const API = 'https://commons.wikimedia.org/w/api.php';

async function buscarImagen(termino) {
    const params = new URLSearchParams({
        action: 'query',
        generator: 'search',
        gsrsearch: `filetype:bitmap ${termino}`,
        gsrnamespace: '6',
        gsrlimit: '6',
        prop: 'imageinfo',
        iiprop: 'url|extmetadata|mime',
        iiurlwidth: '800',
        format: 'json',
        origin: '*',
    });
    for (let intento = 0; intento < 3; intento++) {
        try {
            const r = await fetch(`${API}?${params}`, {
                headers: { 'User-Agent': 'SistemaFarmaciaLanding/1.0 (landing sections sourcing)' },
            });
            if (!r.ok) {
                await new Promise((res) => setTimeout(res, 1500 * (intento + 1)));
                continue;
            }
            const data = await r.json();
            for (const p of Object.values(data?.query?.pages ?? {})) {
                const info = p.imageinfo?.[0];
                if (!info) continue;
                if (!['image/jpeg', 'image/png', 'image/webp'].includes(info.mime ?? '')) continue;
                const meta = info.extmetadata ?? {};
                return {
                    image_url: (info.thumburl || info.url).split('?')[0],
                    image_alt: (meta.ImageDescription?.value ?? p.title ?? termino)
                        .replace(/<[^>]+>/g, '').slice(0, 160),
                    image_license: meta.LicenseShortName?.value ?? 'ver licencia en la fuente',
                    image_source: info.descriptionurl ?? `https://commons.wikimedia.org/wiki/${encodeURIComponent(p.title)}`,
                    image_provider: 'Wikimedia Commons',
                };
            }
            return null;
        } catch {
            await new Promise((res) => setTimeout(res, 1500 * (intento + 1)));
        }
    }
    return null;
}

const salida = [];
const faltas = [];
for (const s of SECCIONES) {
    let img = await buscarImagen(s.termino);
    if (!img) img = await buscarImagen(s.fallback);
    if (!img) {
        faltas.push(s.id);
        img = { image_url: '', image_alt: s.id, image_license: 'SIN IMAGEN', image_source: '', image_provider: '' };
    }
    salida.push({ id: s.id, uso: s.uso, categoria: s.categoria, ...img });
    console.log(`${s.id}: ${img.image_license} — ${img.image_source}`);
    await new Promise((r) => setTimeout(r, 700));
}

mkdirSync(path.join(root, 'data'), { recursive: true });
writeFileSync(path.join(root, 'data/imagenes.json'), JSON.stringify({
    generated_at: new Date().toISOString(),
    provider: 'Wikimedia Commons API',
    license_note: 'Licencias verificables por archivo en image_source (pagina del archivo en Commons). Sin generacion por IA.',
    images: salida,
}, null, 2) + '\n', 'utf8');

mkdirSync(path.join(__dirname, 'results'), { recursive: true });
writeFileSync(path.join(__dirname, 'results/cp-landing-06-sources.json'), JSON.stringify({
    generated_at: new Date().toISOString(),
    provider: 'Wikimedia Commons API',
    license_note: 'Licencias verificables por archivo en image_source. Sin generacion por IA.',
    missing_images: faltas,
    sections: salida,
}, null, 2) + '\n', 'utf8');

console.log(`imagenes: ${salida.length} secciones, faltas: ${faltas.length}`);
