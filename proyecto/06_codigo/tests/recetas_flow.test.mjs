// CP-FRONT-08: prueba de la logica de recetas.js y controlados.js sin navegador.
// Stub de document/localStorage/fetch/window.
// Uso: node tests/recetas_flow.test.mjs
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const srcRx = readFileSync(path.join(__dirname, '../public/assets/js/recetas.js'), 'utf8');
const srcCtrl = readFileSync(path.join(__dirname, '../public/assets/js/controlados.js'), 'utf8');

let fail = 0;
const check = (ok, label) => {
    console.log((ok ? 'PASS' : 'FAIL') + ': ' + label);
    if (!ok) fail++;
};
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function el() {
    let tc = '';
    const e = {
        children: [], listeners: {}, value: '', hidden: false, dataset: {},
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

// ================= recetas.js =================
{
    const dom = makeDom(
        ['app-error', 'rx-form', 'rx-prescriptor', 'rx-paciente', 'rx-fecha', 'rx-referencia', 'rx-guardar',
            'rx-item-producto', 'rx-item-cantidad', 'rx-item-agregar', 'rx-items-lista',
            'rx-buscar', 'rx-filtro-paciente', 'rx-detalle-panel', 'rx-detalle-info', 'rx-dispensar'],
        ['#rx-tabla tbody', '#rx-detalle-tabla tbody'],
    );
    const fetchImpl = async (url, opts = {}) => {
        if (url.includes('/catalog/prescribers')) return jsonResp(200, {
            success: true,
            data: [{ id: 2, nombre: 'Dr. Solís', estado: 'activo' }, { id: 9, nombre: 'Baja', estado: 'inactivo' }],
        });
        if (url.includes('/catalog/patients')) return jsonResp(200, {
            success: true,
            data: [{ id: 1, nombre: 'Ana Pérez', estado: 'activo' }],
        });
        if (url.includes('/catalog/products')) return jsonResp(200, {
            success: true,
            data: [
                { id: 7, nombre: 'Clonazepam', estado: 'activo', condicion_venta: 'controlado' },
                { id: 8, nombre: 'Paracetamol', estado: 'activo', condicion_venta: 'libre' },
            ],
        });
        if (url.endsWith('/rx/prescriptions/5') && (!opts.method || opts.method === 'GET')) return jsonResp(200, {
            success: true,
            data: {
                prescripcion: { id: 5, prescriber_nombre: 'Dr. Solís', patient_nombre: 'Ana Pérez', fecha: '2026-10-07' },
                items: [{ id: 21, product_nombre: 'Clonazepam', sku: 'CLON', cantidad_prescrita: 10, cantidad_dispensada: 4, saldo: 6 }],
            },
        });
        if (url.includes('/rx/prescriptions') && (!opts.method || opts.method === 'GET')) return jsonResp(200, {
            success: true,
            data: [{ id: 5, fecha: '2026-10-07', prescriber_nombre: 'Dr. Solís', patient_nombre: 'Ana Pérez', numero_referencia: 'RX-1' }],
        });
        return jsonResp(200, { success: true, data: {} });
    };
    const { calls } = run(srcRx, dom, fetchImpl, authSeed);
    await sleep(20);

    check(dom.byId['rx-prescriptor'].options.length === 1 && dom.byId['rx-paciente'].options.length === 1,
        'recetas: prescriptor y paciente solo activos');
    check(dom.byId['rx-item-producto'].options.length === 1 && dom.byId['rx-item-producto'].options[0].textContent === 'Clonazepam',
        'recetas: items solo productos receta/controlado');
    check(dom.bySel['#rx-tabla tbody'].children.length === 1, 'recetas: tabla renderizada');

    dom.byId['rx-item-agregar'].click();
    check(dom.byId['app-error'].hidden === false, 'recetas: item sin cantidad -> error');
    dom.byId['rx-item-producto'].value = '7';
    dom.byId['rx-item-cantidad'].value = '10';
    dom.byId['rx-item-agregar'].click();
    check(dom.byId['rx-items-lista'].children.length === 1, 'recetas: item agregado');

    dom.byId['rx-prescriptor'].value = '2';
    dom.byId['rx-paciente'].value = '1';
    dom.byId['rx-fecha'].value = '2026-10-07';
    dom.byId['rx-referencia'].value = 'RX-1';
    await dom.byId['rx-form'].listeners.submit({ preventDefault() {}, target: dom.byId['rx-form'] });
    await sleep(10);
    const creada = calls.find((c) => c.url.endsWith('/rx/prescriptions') && c.opts.method === 'POST');
    const cuerpo = creada ? JSON.parse(creada.opts.body) : {};
    check(cuerpo.prescriber_id === 2 && cuerpo.patient_id === 1 && cuerpo.fecha === '2026-10-07'
        && cuerpo.numero_referencia === 'RX-1' && cuerpo.items[0].cantidad_prescrita === 10,
        'recetas: POST con prescriptor/paciente/fecha/items');

    dom.bySel['#rx-tabla tbody'].children[0].lastChild.children[0].click(); // Ver
    await sleep(10);
    const filas = dom.bySel['#rx-detalle-tabla tbody'].children;
    check(dom.byId['rx-detalle-panel'].hidden === false && filas[0].lastChild.children[0].dataset.itemId === '21',
        'recetas: detalle con input por item (rx_item_id)');

    filas[0].lastChild.children[0].value = '7'; // > saldo 6
    await dom.byId['rx-dispensar'].click();
    check(dom.byId['app-error'].textContent === 'La cantidad a dispensar no puede superar el saldo del ítem.',
        'recetas: dispensar mas del saldo -> error local');

    filas[0].lastChild.children[0].value = '4';
    await dom.byId['rx-dispensar'].click();
    await sleep(10);
    const disp = calls.find((c) => c.url.endsWith('/rx/prescriptions/5/dispensar') && c.opts.method === 'POST');
    const cuerpoD = disp ? JSON.parse(disp.opts.body) : {};
    check(cuerpoD.items[0].rx_item_id === 21 && cuerpoD.items[0].cantidad === 4,
        'recetas: dispensar envia rx_item_id y cantidad');
}

// ================= controlados.js =================
{
    const dom = makeDom(
        ['app-error', 'led-buscar', 'led-tipo', 'led-ref', 'led-desde', 'led-hasta',
            'sal-conciliar', 'sal-conciliacion', 'aj-form', 'aj-store', 'aj-producto', 'aj-lote',
            'aj-cantidad', 'aj-direccion', 'aj-motivo', 'aj-autorizador', 'aj-guardar'],
        ['#led-tabla tbody', '#sal-tabla tbody'],
    );
    const fetchImpl = async (url, opts = {}) => {
        if (url.includes('/catalog/products')) return jsonResp(200, { success: true, data: [{ id: 7, nombre: 'Clonazepam' }] });
        if (url.includes('/ops/stores')) return jsonResp(200, { success: true, data: [{ id: 1, nombre: 'Central' }] });
        if (url.includes('/inventory/batches')) return jsonResp(200, { success: true, data: [{ id: 5, numero_lote: 'L-5', product_nombre: 'Clonazepam' }] });
        if (url.includes('/auth/users')) return jsonResp(200, {
            success: true,
            data: [{ id: 1, usuario: 'admin' }, { id: 13, usuario: 'ctl_x' }],
        });
        if (url.includes('/control/ledger') && (!opts.method || opts.method === 'GET')) return jsonResp(200, {
            success: true,
            data: [{ id: 3, created_at: '2026-10-07T09:00:00', tipo: 'entrada', product_id: 7, cantidad: 10, saldo_resultante: 10, ref_tipo: 'recepcion', ref_id: 5, motivo: null }],
        });
        if (url.includes('/control/balances')) return jsonResp(200, {
            success: true,
            data: [{ store_id: 1, product_id: 7, saldo: 10, saldo_calculado: 10, conciliado: true }],
        });
        if (url.includes('/control/reconciliation')) return jsonResp(200, {
            success: true, data: { total: 1, conciliados: 1, desbalanceados: 0, diferencias: [] },
        });
        return jsonResp(201, { success: true, data: { movimiento_id: 99, saldo: 7 } });
    };
    const { calls } = run(srcCtrl, dom, fetchImpl, authSeed);
    await sleep(20);

    check(dom.bySel['#led-tabla tbody'].children.length === 1 && dom.bySel['#sal-tabla tbody'].children.length === 1,
        'controlados: asientos y saldos renderizados');
    check(dom.bySel['#led-tabla tbody'].children[0].children[3].textContent === 'Clonazepam'
        && dom.bySel['#led-tabla tbody'].children[0].children[6].textContent === 'recepcion #5',
        'controlados: asiento con producto y referencia');

    dom.byId['led-tipo'].value = 'entrada';
    dom.byId['led-desde'].value = '2026-10-01';
    await dom.byId['led-buscar'].listeners.submit({ preventDefault() {}, target: dom.byId['led-buscar'] });
    await sleep(10);
    check(calls.some((c) => c.url.includes('tipo=entrada') && c.url.includes('desde=2026-10-01')),
        'controlados: filtros por periodo (RF-071)');

    await dom.byId['sal-conciliar'].click();
    await sleep(10);
    check(dom.byId['sal-conciliacion'].textContent.includes('desbalanceados: 0'),
        'controlados: conciliacion muestra resumen (RNF-024)');

    dom.byId['aj-store'].value = '1';
    dom.byId['aj-producto'].value = '7';
    dom.byId['aj-lote'].value = '5';
    dom.byId['aj-direccion'].value = 'baja';
    dom.byId['aj-autorizador'].value = '13';
    dom.byId['aj-motivo'].value = 'Merma';
    await dom.byId['aj-form'].listeners.submit({ preventDefault() {}, target: dom.byId['aj-form'] });
    check(dom.byId['app-error'].hidden === false, 'controlados: ajuste sin cantidad -> error');
    dom.byId['aj-cantidad'].value = '3';
    await dom.byId['aj-form'].listeners.submit({ preventDefault() {}, target: dom.byId['aj-form'] });
    await sleep(10);
    const aj = calls.find((c) => c.url.endsWith('/control/adjustments') && c.opts.method === 'POST');
    const cuerpoA = aj ? JSON.parse(aj.opts.body) : {};
    check(cuerpoA.store_id === 1 && cuerpoA.product_id === 7 && cuerpoA.lot_id === 5
        && cuerpoA.cantidad === 3 && cuerpoA.direccion === 'baja' && cuerpoA.motivo === 'Merma'
        && cuerpoA.autorizador_id === 13, 'controlados: POST de ajuste con doble autorizacion (RF-046)');
}

console.log('RESULTADO: ' + (fail === 0 ? 'PASS' : `FAIL (${fail})`));
process.exit(fail === 0 ? 0 : 1);
