// CP-FRONT-09: prueba de la logica de pos.js, pagos.js y devoluciones.js sin navegador.
// Stub de document/localStorage/fetch/window.
// Uso: node tests/ventas_flow.test.mjs
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const srcPos = readFileSync(path.join(__dirname, '../public/assets/js/pos.js'), 'utf8');
const srcPagos = readFileSync(path.join(__dirname, '../public/assets/js/pagos.js'), 'utf8');
const srcDevol = readFileSync(path.join(__dirname, '../public/assets/js/devoluciones.js'), 'utf8');

let fail = 0;
const check = (ok, label) => {
    console.log((ok ? 'PASS' : 'FAIL') + ': ' + label);
    if (!ok) fail++;
};
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function el() {
    let tc = '';
    const e = {
        children: [], listeners: {}, value: '', hidden: false, dataset: {}, innerHTML: '',
        className: '', type: '', readOnly: false, selected: false, disabled: false, min: '', step: '',
        appendChild(c) { this.children.push(c); return c; },
        addEventListener(ev, fn) { this.listeners[ev] = fn; },
        reset() {},
        click() { if (this.listeners.click) return this.listeners.click({}); },
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
    const win = { location: { replaced: null, replace(u) { this.replaced = u; } }, print() {} };
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

// ================= pos.js =================
{
    const dom = makeDom(
        ['app-error', 'pos-store', 'pos-register', 'pos-paciente', 'pos-codigo', 'pos-buscar',
            'pos-total', 'pago-medio', 'pago-monto', 'pago-agregar', 'pago-lista', 'pos-cobrar',
            'comprobante', 'comprobante-detalle', 'comprobante-imprimir'],
        ['#pos-tabla tbody'],
    );
    const fetchImpl = async (url, opts = {}) => {
        if (url.includes('/ops/stores')) return jsonResp(200, {
            success: true,
            data: [{ id: 1, nombre: 'Central', estado: 'activa' }, { id: 2, nombre: 'Cerrada', estado: 'inactiva' }],
        });
        if (url.includes('/ops/registers')) return jsonResp(200, {
            success: true,
            data: [{ id: 10, store_id: 1, codigo: 'C1', estado: 'activa' }, { id: 11, store_id: 2, codigo: 'C2', estado: 'activa' }],
        });
        if (url.includes('/catalog/patients')) return jsonResp(200, {
            success: true,
            data: [{ id: 1, nombre: 'Ana', estado: 'activo' }],
        });
        if (url.includes('/catalog/products')) return jsonResp(200, {
            success: true,
            data: [{ id: 7, sku: 'IBU400', nombre: 'Ibuprofeno 400', estado: 'activo', condicion_venta: 'libre' }],
        });
        if (url.includes('/inventory/stocks')) return jsonResp(200, {
            success: true,
            data: [
                { store_id: 1, product_id: 7, lot_id: 5, numero_lote: 'L-5', fecha_vencimiento: '2027-06-01', lote_estado: 'liberado', stock_available: 4 },
                { store_id: 1, product_id: 7, lot_id: 6, numero_lote: 'L-6', fecha_vencimiento: '2026-11-01', lote_estado: 'liberado', stock_available: 9 },
                { store_id: 1, product_id: 7, lot_id: 7, numero_lote: 'L-7', fecha_vencimiento: '2027-01-01', lote_estado: 'cuarentena', stock_available: 9 },
            ],
        });
        if (url.includes('/catalog/prices')) return jsonResp(200, {
            success: true,
            data: [
                { product_id: 7, store_id: 1, precio: '15.00', vigente_desde: '2026-01-01', vigente_hasta: null },
                { product_id: 7, store_id: null, precio: '12.00', vigente_desde: '2026-01-01', vigente_hasta: null },
            ],
        });
        if (url.endsWith('/sales/orders') && opts.method === 'POST') return jsonResp(201, {
            success: true,
            data: { orden: { id: 55, estado: 'pendiente', total: '30.00' } },
        });
        if (url.includes('/payments') && opts.method === 'POST') return jsonResp(201, { success: true, data: { id: 1 } });
        return jsonResp(200, { success: true, data: {} });
    };
    const { calls } = run(srcPos, dom, fetchImpl, authSeed);
    await sleep(20);

    check(dom.byId['pos-store'].options.length === 1 && dom.byId['pos-register'].options.length === 1,
        'pos: solo sucursales activas y cajas de la sucursal elegida');
    check(dom.byId['pos-paciente'].options.length === 1, 'pos: pacientes activos');

    dom.byId['pos-codigo'].value = 'NOEXISTE';
    await dom.byId['pos-buscar'].listeners.submit({ preventDefault() {}, target: dom.byId['pos-buscar'] });
    check(dom.byId['app-error'].hidden === false, 'pos: codigo desconocido -> error');

    dom.byId['pos-codigo'].value = 'IBU400';
    await dom.byId['pos-buscar'].listeners.submit({ preventDefault() {}, target: dom.byId['pos-buscar'] });
    let filas = dom.bySel['#pos-tabla tbody'].children;
    check(filas.length === 1 && filas[0].children[1].textContent === 'L-6',
        'pos: lote FEFO automatico (vencimiento mas proximo, liberado)');
    check(filas[0].children[3].textContent === '15.00', 'pos: precio de sucursal preferido sobre global');
    check(dom.byId['pos-total'].textContent === '15.00', 'pos: total calculado');

    dom.byId['pago-agregar'].click();
    check(dom.byId['app-error'].hidden === false, 'pos: pago sin monto -> error');
    dom.byId['pago-medio'].value = 'efectivo';
    dom.byId['pago-monto'].value = '10';
    dom.byId['pago-agregar'].click();
    await dom.byId['pos-cobrar'].click();
    check(dom.byId['app-error'].textContent.startsWith('Los pagos'), 'pos: pagos insuficientes -> error');

    dom.byId['pago-monto'].value = '20';
    dom.byId['pago-agregar'].click();
    await dom.byId['pos-cobrar'].click();
    await sleep(20);
    const orden = calls.find((c) => c.url.endsWith('/sales/orders') && c.opts.method === 'POST');
    const cuerpoO = orden ? JSON.parse(orden.opts.body) : {};
    check(cuerpoO.store_id === 1 && cuerpoO.register_id === 10 && cuerpoO.items[0].lot_id === 6
        && cuerpoO.items[0].precio_unitario === 15 && typeof cuerpoO.idempotency_key === 'string',
        'pos: POST de venta con items FEFO, precio e idempotency_key');
    check(calls.filter((c) => c.url.includes('/sales/orders/55/payments') && c.opts.method === 'POST').length === 2,
        'pos: multiples pagos registrados');
    check(dom.byId['comprobante'].hidden === false && dom.byId['comprobante-detalle'].innerHTML.includes('Venta #55')
        && dom.byId['comprobante-detalle'].innerHTML.includes('Vuelto: 15.00'), 'pos: comprobante emitido con vuelto');
    check(dom.bySel['#pos-tabla tbody'].children.length === 0, 'pos: carrito vaciado tras cobrar');
}

// ================= pagos.js =================
{
    const dom = makeDom(
        ['app-error', 'v-buscar', 'v-store', 'v-estado', 'v-detalle-panel', 'v-detalle-info',
            'v-pago-form', 'v-pago-medio', 'v-pago-monto', 'v-anular'],
        ['#v-tabla tbody', '#v-items-tabla tbody', '#v-pagos-tabla tbody'],
    );
    const fetchImpl = async (url, opts = {}) => {
        if (url.includes('/catalog/products')) return jsonResp(200, { success: true, data: [{ id: 7, nombre: 'Ibuprofeno' }] });
        if (url.includes('/ops/stores')) return jsonResp(200, { success: true, data: [{ id: 1, nombre: 'Central' }] });
        if (url.endsWith('/sales/orders/55') && (!opts.method || opts.method === 'GET')) return jsonResp(200, {
            success: true,
            data: {
                orden: { id: 55, estado: 'pendiente', total: '30.00' },
                items: [{ id: 21, product_id: 7, lot_id: 6, cantidad: 2, precio_unitario: '15.00', subtotal: '30.00' }],
                pagos: [{ id: 1, medio: 'efectivo', amount: '10.00', status: 'aprobado' }],
            },
        });
        if (url.includes('/sales/orders') && (!opts.method || opts.method === 'GET')) return jsonResp(200, {
            success: true,
            data: [{ id: 55, created_at: '2026-10-07T12:00:00', total: '30.00', estado: 'pendiente' }],
        });
        return jsonResp(201, { success: true, data: { id: 2 } });
    };
    const { calls } = run(srcPagos, dom, fetchImpl, authSeed);
    await sleep(20);

    check(dom.bySel['#v-tabla tbody'].children.length === 1, 'pagos: tabla de ventas renderizada');
    dom.bySel['#v-tabla tbody'].children[0].lastChild.children[0].click(); // Ver
    await sleep(10);
    check(dom.bySel['#v-items-tabla tbody'].children.length === 1 && dom.bySel['#v-pagos-tabla tbody'].children.length === 1,
        'pagos: detalle con items y pagos registrados');

    dom.byId['v-pago-monto'].value = '20';
    dom.byId['v-pago-medio'].value = 'tarjeta';
    await dom.byId['v-pago-form'].listeners.submit({ preventDefault() {}, target: dom.byId['v-pago-form'] });
    await sleep(10);
    const pago = calls.find((c) => c.url.endsWith('/sales/orders/55/payments') && c.opts.method === 'POST');
    check(pago && JSON.parse(pago.opts.body).medio === 'tarjeta' && JSON.parse(pago.opts.body).monto === 20,
        'pagos: registro de pago acumulativo');

    await dom.byId['v-anular'].click();
    await sleep(10);
    check(calls.some((c) => c.url.endsWith('/sales/orders/55/anular') && c.opts.method === 'POST'),
        'pagos: anular venta -> POST /anular');
}

// ================= devoluciones.js =================
{
    const dom = makeDom(
        ['app-error', 'dv-cargar', 'dv-order-id', 'dv-info', 'dv-form', 'dv-motivo', 'dv-detalle-panel'],
        ['#dv-items-tabla tbody', '#dv-tabla tbody', '#dv-detalle-tabla tbody'],
    );
    const fetchImpl = async (url, opts = {}) => {
        if (url.includes('/catalog/products')) return jsonResp(200, { success: true, data: [{ id: 7, nombre: 'Ibuprofeno' }] });
        if (url.includes('/sales/returns/9')) return jsonResp(200, {
            success: true,
            data: { id: 9, order_id: 55, items: [{ order_item_id: 21, product_id: 7, lot_id: 6, cantidad: 1, condicion: 'vendible' }] },
        });
        if (url.includes('/sales/returns') && (!opts.method || opts.method === 'GET')) return jsonResp(200, {
            success: true,
            data: [{ id: 9, order_id: 55, motivo: 'mal empaquetado', created_at: '2026-10-07T13:00:00' }],
        });
        if (url.endsWith('/sales/orders/55') && (!opts.method || opts.method === 'GET')) return jsonResp(200, {
            success: true,
            data: {
                orden: { id: 55, estado: 'pagada' },
                items: [{ id: 21, product_id: 7, lot_id: 6, cantidad: 2, precio_unitario: '15.00' }],
            },
        });
        return jsonResp(201, { success: true, data: { id: 10 } });
    };
    const { calls } = run(srcDevol, dom, fetchImpl, authSeed);
    await sleep(20);

    dom.byId['dv-order-id'].value = '55';
    await dom.byId['dv-cargar'].listeners.submit({ preventDefault() {}, target: dom.byId['dv-cargar'] });
    await sleep(20);
    const filaDet = dom.bySel['#dv-items-tabla tbody'].children[0];
    check(filaDet.children[4].textContent === '1', 'devoluciones: cantidad devuelta acumulada (N+1)');
    check(filaDet.children[5].children[0].dataset.max === '1', 'devoluciones: maximo por item = vendida - devuelta');

    filaDet.children[5].children[0].value = '2';
    dom.byId['dv-motivo'].value = 'Dañado';
    await dom.byId['dv-form'].listeners.submit({ preventDefault() {}, target: dom.byId['dv-form'] });
    check(dom.byId['app-error'].textContent === 'La cantidad a devolver no puede superar lo pendiente del ítem.',
        'devoluciones: excede el pendiente -> error local');

    filaDet.children[5].children[0].value = '1';
    dom.byId['dv-motivo'].value = 'Dañado';
    await dom.byId['dv-form'].listeners.submit({ preventDefault() {}, target: dom.byId['dv-form'] });
    await sleep(20);
    const devuelta = calls.find((c) => c.url.endsWith('/sales/returns') && c.opts.method === 'POST');
    const cuerpoD = devuelta ? JSON.parse(devuelta.opts.body) : {};
    check(cuerpoD.order_id === 55 && cuerpoD.motivo === 'Dañado'
        && cuerpoD.items[0].order_item_id === 21 && cuerpoD.items[0].cantidad === 1
        && cuerpoD.items[0].condicion === 'vendible',
        'devoluciones: POST con condicion vendible/no_vendible');
}

console.log('RESULTADO: ' + (fail === 0 ? 'PASS' : `FAIL (${fail})`));
process.exit(fail === 0 ? 0 : 1);
