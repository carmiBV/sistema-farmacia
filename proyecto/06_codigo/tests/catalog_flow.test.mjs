// CP-FRONT-04: prueba de la logica de categorias.js, productos.js y precios.js
// sin navegador. Stub de document/localStorage/fetch/window.
// Uso: node tests/catalog_flow.test.mjs
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const srcCat = readFileSync(path.join(__dirname, '../public/assets/js/categorias.js'), 'utf8');
const srcProd = readFileSync(path.join(__dirname, '../public/assets/js/productos.js'), 'utf8');
const srcPrec = readFileSync(path.join(__dirname, '../public/assets/js/precios.js'), 'utf8');

let fail = 0;
const check = (ok, label) => {
    console.log((ok ? 'PASS' : 'FAIL') + ': ' + label);
    if (!ok) fail++;
};
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function el() {
    let tc = '';
    const e = {
        children: [], listeners: {}, value: '', hidden: false,
        className: '', type: '', readOnly: false, selected: false,
        appendChild(c) { this.children.push(c); return c; },
        addEventListener(ev, fn) { this.listeners[ev] = fn; },
        reset() {},
        click() { if (this.listeners.click) this.listeners.click({}); },
    };
    Object.defineProperty(e, 'textContent', {
        get() { return tc; },
        set(v) { tc = String(v); if (tc === '') e.children.length = 0; },
    });
    Object.defineProperty(e, 'lastChild', {
        get() { return e.children[e.children.length - 1] || null; },
    });
    Object.defineProperty(e, 'options', {
        get() { return e.children; },
    });
    return e;
}

function makeDom(ids, sels) {
    const byId = {};
    ids.forEach((id) => { byId[id] = el(); });
    const bySel = {};
    sels.forEach((s) => { bySel[s] = el(); });
    return {
        byId, bySel,
        document: {
            getElementById: (id) => byId[id] ?? null,
            querySelector: (s) => bySel[s] ?? null,
            createElement: () => el(),
        },
    };
}

function run(src, dom, fetchImpl, seed = {}) {
    const store = new Map(Object.entries(seed));
    const calls = [];
    const win = { location: { replaced: null, replace(u) { this.replaced = u; } } };
    const wrappedFetch = (url, opts) => { calls.push({ url: String(url), opts }); return fetchImpl(String(url), opts); };
    new Function('document', 'localStorage', 'fetch', 'window', src)(
        dom.document,
        {
            getItem: (k) => (store.has(k) ? store.get(k) : null),
            setItem: (k, v) => store.set(k, String(v)),
            removeItem: (k) => store.delete(k),
        },
        wrappedFetch,
        win,
    );
    return { store, calls, win };
}

const jsonResp = (status, body) => ({ ok: status >= 200 && status < 300, status, json: async () => body });
const authSeed = { sf_token: 'tok', sf_user: '{}' };
const catData = () => ([
    { id: 1, nombre: 'Analgésicos', parent_id: null },
    { id: 2, nombre: 'Ibuprofeno', parent_id: 1 },
]);

// ================= categorias.js =================
{
    const dom = makeDom(
        ['app-error', 'cat-form', 'cat-nombre', 'cat-parent', 'cat-guardar', 'cat-cancelar', 'cat-buscar', 'cat-q'],
        ['#cat-tabla tbody'],
    );
    const fetchImpl = async (url, opts = {}) => {
        if (url.includes('/catalog/categories') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, { success: true, data: catData(), meta: { total: 2 } });
        }
        return jsonResp(201, { success: true, data: { id: 3 } });
    };
    const { calls } = run(srcCat, dom, fetchImpl, authSeed);
    await sleep(20);

    const tbody = dom.bySel['#cat-tabla tbody'];
    check(tbody.children.length === 2, 'categorias: tabla renderizada');
    check(tbody.children[1].children[2].textContent === 'Analgésicos', 'categorias: columna padre resuelve el nombre');

    dom.byId['cat-nombre'].value = 'Jarabes';
    dom.byId['cat-parent'].value = '1';
    await dom.byId['cat-form'].listeners.submit({ preventDefault() {}, target: dom.byId['cat-form'] });
    await sleep(10);
    const creado = calls.find((c) => c.url.endsWith('/catalog/categories') && c.opts.method === 'POST');
    check(creado && JSON.parse(creado.opts.body).parent_id === 1, 'categorias: POST con parent_id');

    tbody.children[0].lastChild.children[0].click(); // Editar (raiz)
    check(dom.byId['cat-nombre'].value === 'Analgésicos', 'categorias: Editar precarga');
    await dom.byId['cat-form'].listeners.submit({ preventDefault() {}, target: dom.byId['cat-form'] });
    await sleep(10);
    const upd = calls.find((c) => c.url.endsWith('/catalog/categories/1') && c.opts.method === 'PUT');
    check(upd && JSON.parse(upd.opts.body).parent_id === null, 'categorias: PUT envia parent_id null para raiz');

    tbody.children[1].lastChild.children[1].click(); // Desactivar
    await sleep(10);
    check(calls.some((c) => c.url.endsWith('/catalog/categories/2') && c.opts.method === 'DELETE'),
        'categorias: Desactivar -> DELETE (borrado logico)');

    dom.byId['cat-q'].value = 'ibu';
    await dom.byId['cat-buscar'].listeners.submit({ preventDefault() {}, target: dom.byId['cat-buscar'] });
    await sleep(10);
    check(calls.some((c) => c.url.includes('q=ibu')), 'categorias: busqueda envia q');
}

// ================= productos.js =================
{
    const dom = makeDom(
        ['app-error', 'prod-form', 'prod-sku', 'prod-nombre', 'prod-principio', 'prod-presentacion',
            'prod-concentracion', 'prod-condicion', 'prod-categorias', 'prod-guardar', 'prod-cancelar',
            'prod-buscar', 'prod-q'],
        ['#prod-tabla tbody'],
    );
    const prod = {
        id: 7, sku: 'IBU400', nombre: 'Ibuprofeno 400', principio_activo: 'Ibuprofeno',
        presentacion: 'Caja', concentracion: '400mg', condicion_venta: 'libre', estado: 'activo',
    };
    const fetchImpl = async (url, opts = {}) => {
        if (url.includes('/catalog/categories') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, { success: true, data: catData() });
        }
        if (url.includes('/products/7/categories') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, { success: true, data: { product_id: 7, categories: [{ id: 2, nombre: 'Ibuprofeno' }] } });
        }
        if (url.includes('/products/') && url.includes('/categories')) {
            return jsonResp(200, { success: true, data: {} });
        }
        if (url.includes('/catalog/products') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, { success: true, data: [prod] });
        }
        return jsonResp(201, { success: true, data: { id: 8 } });
    };
    const { calls } = run(srcProd, dom, fetchImpl, authSeed);
    await sleep(20);

    check(dom.bySel['#prod-tabla tbody'].children.length === 1, 'productos: tabla renderizada');
    check(dom.byId['prod-categorias'].options.length === 2, 'productos: select de categorias poblado');

    dom.byId['prod-sku'].value = 'PAR500';
    dom.byId['prod-nombre'].value = 'Paracetamol 500';
    dom.byId['prod-principio'].value = 'Paracetamol';
    dom.byId['prod-presentacion'].value = 'Caja';
    dom.byId['prod-concentracion'].value = '500mg';
    dom.byId['prod-condicion'].value = 'libre';
    dom.byId['prod-categorias'].options[0].selected = true;
    await dom.byId['prod-form'].listeners.submit({ preventDefault() {}, target: dom.byId['prod-form'] });
    await sleep(10);
    const creado = calls.find((c) => c.url.endsWith('/catalog/products') && c.opts.method === 'POST');
    const sync = calls.find((c) => c.url.endsWith('/products/8/categories') && c.opts.method === 'PUT');
    check(!!creado && JSON.parse(creado.opts.body).sku === 'PAR500', 'productos: POST del producto');
    check(sync && JSON.parse(sync.opts.body).category_ids.length === 1, 'productos: sincroniza categorias por subrecurso');

    dom.bySel['#prod-tabla tbody'].children[0].lastChild.children[0].click(); // Editar
    await sleep(10);
    check(dom.byId['prod-sku'].value === 'IBU400', 'productos: Editar precarga el formulario');
    await dom.byId['prod-form'].listeners.submit({ preventDefault() {}, target: dom.byId['prod-form'] });
    await sleep(10);
    check(calls.some((c) => c.url.endsWith('/catalog/products/7') && c.opts.method === 'PUT'),
        'productos: PUT del producto editado');
}

// ================= precios.js =================
{
    const dom = makeDom(
        ['app-error', 'pr-form', 'pr-producto', 'pr-precio', 'pr-desde', 'pr-hasta', 'pr-store',
            'pr-guardar', 'pr-cancelar', 'pm-form', 'pm-tipo', 'pm-alcance', 'pm-descuento',
            'pm-desde', 'pm-hasta', 'pm-guardar', 'pm-cancelar'],
        ['#pr-tabla tbody', '#pm-tabla tbody'],
    );
    const fetchImpl = async (url, opts = {}) => {
        if (url.includes('/catalog/products') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, { success: true, data: [{ id: 7, nombre: 'Ibuprofeno 400' }] });
        }
        if (url.includes('/catalog/categories') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, { success: true, data: catData() });
        }
        if (url.includes('/ops/stores') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, { success: true, data: [{ id: 1, nombre: 'Central' }] });
        }
        if (url.includes('/catalog/prices') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, { success: true, data: [{ id: 4, product_id: 7, store_id: null, precio: '12.50', vigente_desde: '2026-10-01', vigente_hasta: null }] });
        }
        if (url.includes('/catalog/promotions') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, { success: true, data: [{ id: 9, product_id: null, category_id: 1, descuento_pct: '10.00', desde: '2026-10-01', hasta: '2026-10-31' }] });
        }
        return jsonResp(201, { success: true, data: { id: 10 } });
    };
    const { calls } = run(srcPrec, dom, fetchImpl, authSeed);
    await sleep(20);

    check(dom.bySel['#pr-tabla tbody'].children.length === 1 && dom.bySel['#pm-tabla tbody'].children.length === 1,
        'precios: tablas de precios y promociones renderizadas');
    check(dom.bySel['#pm-tabla tbody'].children[0].children[1].textContent === 'Categoría: Analgésicos',
        'precios: alcance de promo resuelve nombre de categoria');

    dom.byId['pr-producto'].value = '7';
    dom.byId['pr-precio'].value = '15.75';
    dom.byId['pr-desde'].value = '2026-10-07';
    await dom.byId['pr-form'].listeners.submit({ preventDefault() {}, target: dom.byId['pr-form'] });
    await sleep(10);
    const precioCreado = calls.find((c) => c.url.endsWith('/catalog/prices') && c.opts.method === 'POST');
    const cuerpoP = precioCreado ? JSON.parse(precioCreado.opts.body) : {};
    check(cuerpoP.product_id === 7 && cuerpoP.precio === 15.75 && cuerpoP.vigente_hasta === undefined && cuerpoP.store_id === undefined,
        'precios: POST sin vigente_hasta ni store_id cuando estan vacios');

    dom.bySel['#pr-tabla tbody'].children[0].lastChild.children[0].click(); // Editar precio
    check(dom.byId['pr-precio'].value === '12.50', 'precios: Editar precarga el precio');
    await dom.byId['pr-form'].listeners.submit({ preventDefault() {}, target: dom.byId['pr-form'] });
    await sleep(10);
    check(calls.some((c) => c.url.endsWith('/catalog/prices/4') && c.opts.method === 'PUT'),
        'precios: PUT del precio editado');

    dom.byId['pm-tipo'].value = 'product_id';
    await dom.byId['pm-tipo'].listeners.change();
    dom.byId['pm-alcance'].value = '7';
    dom.byId['pm-descuento'].value = '25';
    dom.byId['pm-desde'].value = '2026-10-07';
    dom.byId['pm-hasta'].value = '2026-10-20';
    await dom.byId['pm-form'].listeners.submit({ preventDefault() {}, target: dom.byId['pm-form'] });
    await sleep(10);
    const promoCreada = calls.find((c) => c.url.endsWith('/catalog/promotions') && c.opts.method === 'POST');
    const cuerpoM = promoCreada ? JSON.parse(promoCreada.opts.body) : {};
    check(cuerpoM.product_id === 7 && cuerpoM.category_id === undefined && cuerpoM.descuento_pct === 25,
        'precios: POST de promo con alcance por producto');
}

console.log('RESULTADO: ' + (fail === 0 ? 'PASS' : `FAIL (${fail})`));
process.exit(fail === 0 ? 0 : 1);
