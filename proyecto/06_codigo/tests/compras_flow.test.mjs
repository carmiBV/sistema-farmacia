// CP-FRONT-06: prueba de la logica de ordenes.js y recepciones.js sin navegador.
// Stub de document/localStorage/fetch/window.
// Uso: node tests/compras_flow.test.mjs
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const srcOrd = readFileSync(path.join(__dirname, '../public/assets/js/ordenes.js'), 'utf8');
const srcRec = readFileSync(path.join(__dirname, '../public/assets/js/recepciones.js'), 'utf8');

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
        className: '', type: '', readOnly: false, selected: false, disabled: false,
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
const prodList = () => ([
    { id: 7, nombre: 'Ibuprofeno 400' },
    { id: 8, nombre: 'Paracetamol 500' },
]);
const orden = () => ({
    id: 11, numero: 'OC-1', supplier_id: 3, estado: 'emitida', creado_por: 1,
    items: [
        { product_id: 7, cantidad_pedida: 10, cantidad_recibida: 4 },
        { product_id: 8, cantidad_pedida: 5, cantidad_recibida: 5 },
    ],
});

// ================= ordenes.js =================
{
    const dom = makeDom(
        ['app-error', 'ord-form', 'ord-numero', 'ord-supplier', 'ord-guardar', 'ord-cancelar',
            'ord-item-producto', 'ord-item-cantidad', 'ord-item-agregar', 'ord-items-lista',
            'ord-q', 'ord-estado', 'ord-buscar', 'ord-detalle-panel', 'ord-detalle-info'],
        ['#ord-tabla tbody', '#ord-detalle-tabla tbody'],
    );
    const fetchImpl = async (url, opts = {}) => {
        if (url.includes('/catalog/suppliers') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, { success: true, data: [{ id: 3, nombre: 'Distribuidora X' }] });
        }
        if (url.includes('/catalog/products') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, { success: true, data: prodList() });
        }
        if (url.endsWith('/purchases/orders/11') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, { success: true, data: { ...orden(), estado: 'borrador' } });
        }
        if (url.includes('/purchases/orders') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, {
                success: true,
                data: [
                    { id: 11, numero: 'OC-1', supplier_id: 3, estado: 'borrador' },
                    { id: 12, numero: 'OC-2', supplier_id: 3, estado: 'emitida' },
                ],
            });
        }
        return jsonResp(201, { success: true, data: { id: 13 } });
    };
    const { calls } = run(srcOrd, dom, fetchImpl, authSeed);
    await sleep(20);

    const tbody = dom.bySel['#ord-tabla tbody'];
    check(tbody.children.length === 2, 'ordenes: tabla renderizada');
    check(tbody.children[0].children[2].textContent === 'Distribuidora X', 'ordenes: proveedor resuelto');
    check(tbody.children[0].lastChild.children.length === 4 && tbody.children[1].lastChild.children.length === 1,
        'ordenes: acciones segun estado (borrador: Ver/Editar/Emitir/Cancelar; emitida: solo Ver)');

    dom.byId['ord-item-agregar'].click();
    check(dom.byId['app-error'].hidden === false, 'ordenes: item sin cantidad -> error');
    dom.byId['ord-item-producto'].value = '7';
    dom.byId['ord-item-cantidad'].value = '10';
    dom.byId['ord-item-agregar'].click();
    check(dom.byId['ord-items-lista'].children.length === 1, 'ordenes: item agregado al editor');

    dom.byId['ord-supplier'].value = '3';
    await dom.byId['ord-form'].listeners.submit({ preventDefault() {}, target: dom.byId['ord-form'] });
    await sleep(10);
    const creado = calls.find((c) => c.url.endsWith('/purchases/orders') && c.opts.method === 'POST');
    const cuerpo = creado ? JSON.parse(creado.opts.body) : {};
    check(cuerpo.supplier_id === 3 && cuerpo.numero === undefined && cuerpo.items[0].cantidad_pedida === 10,
        'ordenes: POST sin numero autogenerado, con items');

    tbody.children[0].lastChild.children[2].click(); // Emitir
    await sleep(10);
    check(calls.some((c) => c.url.endsWith('/purchases/orders/11/emitir') && c.opts.method === 'POST'),
        'ordenes: Emitir -> POST /emitir');

    tbody.children[0].lastChild.children[0].click(); // Ver
    await sleep(10);
    check(dom.byId['ord-detalle-panel'].hidden === false
        && dom.bySel['#ord-detalle-tabla tbody'].children.length === 2, 'ordenes: Ver -> detalle con items');
}

// ================= recepciones.js =================
{
    const dom = makeDom(
        ['app-error', 'rec-orden', 'rec-idempotency', 'rec-verificacion',
            'rec-item-producto', 'rec-item-lote', 'rec-item-vencimiento', 'rec-item-cantidad',
            'rec-item-agregar', 'rec-items-lista', 'rec-guardar',
            'rec-confirmar-store', 'rec-confirmar', 'rec-rechazar',
            'rec-detalle-panel', 'rec-detalle-info'],
        ['#rec-orden-tabla tbody', '#rec-tabla tbody', '#rec-detalle-tabla tbody'],
    );
    const fetchImpl = async (url, opts = {}) => {
        if (url.includes('/catalog/products') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, { success: true, data: prodList() });
        }
        if (url.includes('/ops/stores') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, { success: true, data: [{ id: 1, nombre: 'Central' }] });
        }
        if (url.includes('estado=emitida') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, { success: true, data: [{ id: 11, numero: 'OC-1', estado: 'emitida' }] });
        }
        if (url.endsWith('/purchases/orders/11') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, { success: true, data: orden() });
        }
        if (url.endsWith('/purchases/receptions/21') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, {
                success: true,
                data: {
                    id: 21, order_id: 11, estado: 'recibida', idempotency_key: 'k1',
                    items: [{ product_id: 7, numero_lote: 'L-1', fecha_vencimiento: '2027-01-01', cantidad: 4, lot_id: null }],
                },
            });
        }
        if (url.includes('/purchases/receptions') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, {
                success: true,
                data: [{ id: 21, order_id: 11, estado: 'recibida', idempotency_key: 'k1' }],
            });
        }
        return jsonResp(201, { success: true, data: { id: 22 }, meta: { idempotent_reused: false } });
    };
    const { calls } = run(srcRec, dom, fetchImpl, authSeed);
    await sleep(20);

    check(dom.byId['rec-orden'].options.length === 1 && dom.byId['rec-confirmar-store'].options.length === 1,
        'recepciones: ordenes emitidas y sucursales pobladas');
    check(dom.bySel['#rec-tabla tbody'].children.length === 1, 'recepciones: tabla renderizada');

    dom.byId['rec-orden'].value = '11';
    await dom.byId['rec-orden'].listeners.change();
    await sleep(10);
    const verif = dom.bySel['#rec-orden-tabla tbody'];
    check(verif.children.length === 2 && verif.children[0].children[3].textContent === '6'
        && verif.children[1].children[3].textContent === '0',
        'recepciones: verificacion contra orden (pendientes = pedida - recibida)');
    check(dom.byId['rec-item-producto'].options.length === 1,
        'recepciones: solo se ofrecen productos con cantidad pendiente');

    dom.byId['rec-item-agregar'].click();
    check(dom.byId['app-error'].hidden === false, 'recepciones: item sin lote/vencimiento -> error');
    dom.byId['rec-item-producto'].value = '7';
    dom.byId['rec-item-lote'].value = 'L-1';
    dom.byId['rec-item-vencimiento'].value = '2027-01-01';
    dom.byId['rec-item-cantidad'].value = '4';
    dom.byId['rec-item-agregar'].click();
    check(dom.byId['rec-items-lista'].children.length === 1, 'recepciones: item con lote agregado');

    dom.byId['rec-idempotency'].value = 'k1';
    await dom.byId['rec-guardar'].click();
    await sleep(10);
    const creada = calls.find((c) => c.url.endsWith('/purchases/orders/11/receptions') && c.opts.method === 'POST');
    const cuerpoR = creada ? JSON.parse(creada.opts.body) : {};
    check(cuerpoR.idempotency_key === 'k1' && cuerpoR.items[0].numero_lote === 'L-1'
        && cuerpoR.items[0].fecha_vencimiento === '2027-01-01', 'recepciones: POST con lote, vencimiento e idempotency_key');

    dom.bySel['#rec-tabla tbody'].children[0].lastChild.children[0].click(); // Ver
    await sleep(10);
    check(dom.byId['rec-detalle-panel'].hidden === false && dom.byId['rec-confirmar'].disabled === false,
        'recepciones: Ver -> detalle con confirmar habilitado');
    dom.byId['rec-confirmar-store'].value = '1'; // el DOM real auto-selecciona la primera opcion
    await dom.byId['rec-confirmar'].click();
    await sleep(10);
    const conf = calls.find((c) => c.url.endsWith('/purchases/receptions/21/confirmar') && c.opts.method === 'POST');
    check(conf && JSON.parse(conf.opts.body).store_id === 1, 'recepciones: Confirmar envia store_id');
}

console.log('RESULTADO: ' + (fail === 0 ? 'PASS' : `FAIL (${fail})`));
process.exit(fail === 0 ? 0 : 1);
