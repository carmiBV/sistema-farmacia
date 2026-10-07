// CP-LANDING-02: sourcing de imágenes desde Wikimedia Commons (API abierta).
// Cada producto obtiene: thumb URL (upload.wikimedia.org), licencia y página
// de origen verificable (commons.wikimedia.org). Sin IA, sin inventar URLs.
// Escribe data/catalog.json (7 categorias x 10 productos, precios Bs.).
// Uso: node tests/source_catalog_images.mjs
import { writeFileSync, mkdirSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');

const CATEGORIAS = [
    {
        id: 'medicamentos-receta',
        nombre: 'Medicamentos con Receta',
        fallback: 'medicine pills',
        productos: [
            ['Amoxicilina 500 mg — caja x 21 cápsulas', 45.0, 'amoxicillin capsules'],
            ['Losartán 50 mg — caja x 30 comprimidos', 38.5, 'losartan tablets'],
            ['Metformina 850 mg — caja x 30 comprimidos', 32.0, 'metformin tablets'],
            ['Omeprazol 20 mg — caja x 14 cápsulas', 28.0, 'omeprazole capsules'],
            ['Cefalexina 500 mg — caja x 21 cápsulas', 62.0, 'cephalexin capsules'],
            ['Azitromicina 500 mg — caja x 3 comprimidos', 55.0, 'azithromycin tablets'],
            ['Insulina NPH 100 UI/mL — vial 10 mL', 120.0, 'insulin vial'],
            ['Salbutamol inhalador 100 mcg — 200 dosis', 85.0, 'asthma inhaler'],
            ['Warfarina 5 mg — caja x 30 comprimidos', 42.0, 'warfarin tablets'],
            ['Prednisolona 5 mg — caja x 20 comprimidos', 26.5, 'prednisolone tablets'],
        ],
    },
    {
        id: 'medicamentos-venta-libre',
        nombre: 'Medicamentos de Venta Libre (OTC)',
        fallback: 'medicine box',
        productos: [
            ['Paracetamol 500 mg — caja x 20 comprimidos', 12.0, 'paracetamol tablets'],
            ['Ibuprofeno 400 mg — caja x 20 comprimidos', 15.5, 'ibuprofen tablets'],
            ['Loratadina 10 mg — caja x 10 comprimidos', 18.0, 'loratadine tablets'],
            ['Antigripal compuesto — caja x 10 sobres', 21.0, 'cold medicine'],
            ['Ácido acetilsalicílico 100 mg — caja x 30', 14.0, 'aspirin tablets'],
            ['Sales de rehidratación oral — x 10 sobres', 16.5, 'oral rehydration salts'],
            ['Pastillas para la garganta — caja x 24', 12.5, 'throat lozenges'],
            ['Clotrimazol crema 1% — tubo 20 g', 24.0, 'clotrimazole cream'],
            ['Solución salina nasal spray — 50 mL', 19.0, 'nasal spray'],
            ['Glicerina boricada gotas óticas — 15 mL', 22.0, 'ear drops bottle'],
        ],
    },
    {
        id: 'dermocosmetica-cuidado-personal',
        nombre: 'Dermocosmética y Cuidado Personal',
        fallback: 'cosmetics bottles',
        productos: [
            ['Protector solar FPS 50 — 120 mL', 89.0, 'sunscreen'],
            ['Crema hidratante facial — 50 mL', 75.0, 'face cream jar'],
            ['Limpiador facial suave — 200 mL', 68.0, 'facial cleanser'],
            ['Sérum de vitamina C — 30 mL', 120.0, 'serum dropper bottle'],
            ['Champú anticaspa — 250 mL', 55.0, 'shampoo bottle'],
            ['Jabón dermatológico — barra 100 g', 22.0, 'soap bar'],
            ['Bálsamo labial con FPS 30 — 4.8 g', 25.0, 'lip balm'],
            ['Crema de manos reparadora — 75 mL', 32.0, 'hand cream tube'],
            ['Tónico facial calmante — 150 mL', 62.0, 'toner bottle'],
            ['Gel de aloe vera — 200 mL', 38.0, 'aloe vera gel'],
        ],
    },
    {
        id: 'bebe-maternidad',
        nombre: 'Bebé y Maternidad',
        fallback: 'baby products',
        productos: [
            ['Fórmula infantil etapa 1 — lata 400 g', 145.0, 'baby formula tin'],
            ['Pañales desechables talla M — x 40', 95.0, 'diapers pack'],
            ['Toallitas húmedas para bebé — x 80', 28.0, 'baby wipes'],
            ['Shampoo para bebé — 200 mL', 32.0, 'baby shampoo'],
            ['Crema para dermatitis de pañal — 100 g', 36.0, 'diaper rash cream'],
            ['Biberón anticólico — 250 mL', 45.0, 'baby bottle'],
            ['Termómetro digital para bebé', 55.0, 'baby thermometer'],
            ['Extractor de leche manual', 110.0, 'breast pump'],
            ['Suero fisiológico monodosis — x 30', 24.0, 'saline ampoules'],
            ['Protector solar infantil FPS 50 — 90 mL', 78.0, 'baby sunscreen'],
        ],
    },
    {
        id: 'nutricion-suplementos',
        nombre: 'Nutrición y Suplementos',
        fallback: 'dietary supplements',
        productos: [
            ['Multivitamínico adulto — x 30 comprimidos', 65.0, 'multivitamin bottle'],
            ['Vitamina C 1000 mg efervescente — x 20', 42.0, 'vitamin c tablets'],
            ['Complejo vitamínico B — x 30 cápsulas', 38.0, 'vitamin b capsules'],
            ['Calcio + Vitamina D3 — x 60 comprimidos', 72.0, 'calcium tablets'],
            ['Omega 3 1000 mg — x 60 cápsulas', 98.0, 'fish oil capsules'],
            ['Hierro + ácido fólico — x 30 comprimidos', 45.0, 'iron supplement tablets'],
            ['Colágeno hidrolizado — envase 300 g', 135.0, 'collagen powder'],
            ['Proteína whey — envase 500 g', 165.0, 'whey protein powder'],
            ['Probiótico 30 cápsulas', 88.0, 'probiotic capsules'],
            ['Magnesio 400 mg — x 30 comprimidos', 52.0, 'magnesium supplement'],
        ],
    },
    {
        id: 'primeros-auxilios',
        nombre: 'Primeros Auxilios y Cuidado de Heridas',
        fallback: 'first aid kit',
        productos: [
            ['Venda elástica 10 cm x 4 m', 12.0, 'elastic bandage'],
            ['Gasas estériles 10x10 cm — x 10', 15.0, 'sterile gauze'],
            ['Tela adhesiva hipoalergénica — 10 m', 18.5, 'medical tape'],
            ['Apósitos transparentes — x 20', 28.0, 'adhesive bandages'],
            ['Antiséptico de povidona — 250 mL', 32.0, 'povidone iodine bottle'],
            ['Alcohol antiséptico 70% — 250 mL', 16.0, 'alcohol bottle medical'],
            ['Tijeras quirúrgicas puntas romas', 35.0, 'surgical scissors'],
            ['Termómetro clínico digital', 48.0, 'digital thermometer medical'],
            ['Botiquín portátil — 40 piezas', 120.0, 'first aid kit'],
            ['Guantes de nitrilo — caja x 100', 65.0, 'nitrile gloves'],
        ],
    },
    {
        id: 'equipos-medicion-hogar',
        nombre: 'Equipos de Medición y Cuidado del Hogar',
        fallback: 'medical device',
        productos: [
            ['Oxímetro de pulso digital', 185.0, 'pulse oximeter'],
            ['Tensiómetro digital de brazo', 295.0, 'blood pressure monitor'],
            ['Glucómetro con tiras reactivas x 50', 210.0, 'glucometer'],
            ['Nebulizador de compresor', 320.0, 'nebulizer machine'],
            ['Báscula de cocina digital', 95.0, 'digital kitchen scale'],
            ['Termómetro infrarrojo sin contacto', 165.0, 'infrared thermometer'],
            ['Humidificador ultrasónico 2 L', 175.0, 'humidifier'],
            ['Silla de ruedas plegable', 890.0, 'wheelchair'],
            ['Muletas de aluminio — par', 145.0, 'crutches'],
            ['Colchón antiescaras', 450.0, 'air mattress hospital'],
        ],
    },
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
        iiurlwidth: '320',
        format: 'json',
        origin: '*',
    });
    for (let intento = 0; intento < 3; intento++) {
        try {
            const r = await fetch(`${API}?${params}`, {
                headers: { 'User-Agent': 'SistemaFarmaciaLanding/1.0 (catalog sourcing; contact placeholder)' },
            });
            if (!r.ok) {
                await new Promise((res) => setTimeout(res, 1500 * (intento + 1)));
                continue;
            }
            const data = await r.json();
            const paginas = Object.values(data?.query?.pages ?? {});
            for (const p of paginas) {
                const info = p.imageinfo?.[0];
                if (!info) continue;
                const mime = info.mime ?? '';
                if (!['image/jpeg', 'image/png', 'image/webp'].includes(mime)) continue;
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

const catalogo = { generated_at: new Date().toISOString(), currency: 'Bs.', categories: [] };
const faltas = [];

// reanudable: conserva las imagenes ya obtenidas en corridas previas
// (solo si ya apuntan a thumbs escalados de thumb.wikimedia.org)
let previas = {};
try {
    const previo = JSON.parse(readFileSync(path.join(root, 'data/catalog.json'), 'utf8'));
    for (const c of previo.categories ?? []) {
        for (const p of c.products ?? []) {
            if (p.image_url && p.image_url.includes('thumb.wikimedia.org')) previas[p.name] = p;
        }
    }
} catch { /* primera corrida */ }

for (const cat of CATEGORIAS) {
    const categoria = { id: cat.id, name: cat.nombre, products: [] };
    for (const [nombre, precio, termino] of cat.productos) {
        let img = previas[nombre] ? {
            image_url: previas[nombre].image_url,
            image_alt: previas[nombre].image_alt,
            image_license: previas[nombre].image_license,
            image_source: previas[nombre].image_source,
            image_provider: previas[nombre].image_provider,
        } : await buscarImagen(termino);
        if (!img) img = await buscarImagen(cat.fallback);
        if (!img) {
            faltas.push(`${cat.id}/${nombre}`);
            img = {
                image_url: '', image_alt: nombre, image_license: 'SIN IMAGEN',
                image_source: '', image_provider: '',
            };
        }
        categoria.products.push({
            id: `${cat.id}-${categoria.products.length + 1}`,
            name: nombre,
            price: precio,
            price_display: `Bs. ${precio.toFixed(2)}`,
            ...img,
        });
        if (!previas[nombre]) await new Promise((r) => setTimeout(r, 700)); // cortesía con la API
    }
    catalogo.categories.push(categoria);
}

mkdirSync(path.join(root, 'data'), { recursive: true });
writeFileSync(path.join(root, 'data/catalog.json'), JSON.stringify(catalogo, null, 2) + '\n', 'utf8');
mkdirSync(path.join(__dirname, 'results'), { recursive: true });
writeFileSync(path.join(__dirname, 'results/cp-landing-02-sources.json'), JSON.stringify({
    generated_at: catalogo.generated_at,
    provider: 'Wikimedia Commons API',
    license_note: 'Licencias verificables por archivo en image_source (página del archivo en Commons).',
    missing_images: faltas,
    products: catalogo.categories.flatMap((c) => c.products.map((p) => ({
        category: c.id, name: p.name, image_url: p.image_url,
        image_license: p.image_license, image_source: p.image_source,
    }))),
}, null, 2) + '\n', 'utf8');

console.log(`catalog.json: ${catalogo.categories.length} categorias, ` +
    `${catalogo.categories.reduce((n, c) => n + c.products.length, 0)} productos`);
if (faltas.length) console.log('sin imagen: ' + faltas.join(' | '));
