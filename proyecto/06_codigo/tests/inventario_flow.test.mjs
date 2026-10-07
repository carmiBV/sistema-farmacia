// CP-FRONT-07: prueba de la logica de lotes.js, stock.js, transferencias.js y
// alertas.js sin navegador. Stub de document/localStorage/fetch/window.
// Uso: node tests/inventario_flow.test.mjs
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const srcLotes = readFileSync(path.join(__dirname, '../public/assets/js/lotes.js'), 'utf8');
const srcStock = readFileSync(path.join(__dirname, '../public/assets/js/stock.js'), 'utf8');
const srcTransf = readFileSync(path.join(__dirname, '../public/assets/js/transferencias.js'), 'utf8');
const srcAlertas = readFileSync(path.join(__dirname, '../public/assets/js/alertas.js'), 'utf8');

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
const fecha = (dias) => {
    const d = new Date();
    d.setDate(d.getDate() + dias);
    return d.toISOString().slice(0, 10);
};

// ================= lotes.js (semaforo FEFO) =================
{
    const dom = makeDom(['app-error', 'lot-estado', 'lot-buscar'], ['#lot-tabla tbody']);
    const fetchImpl = async () => jsonResp(200, {
        success: true,
        data: [
            { id: 1, product_nombre: 'Ibuprofeno', numero_lote: 'L-1', fecha_vencimiento: fecha(-5), estado: 'cuarentena' },
            { id: 2, product_nombre: 'Paracetamol', numero_lote: 'L-2', fecha_vencimiento: fecha(30), estado: 'liberado' },
            { id: 3, product_nombre: 'Omeprazol', numero_lote: 'L-3', fecha_vencimiento: fecha(200), estado: 'liberado' },
        ],
    });
    const { calls } = run(srcLotes, dom, fetchImpl, authSeed);
    await sleep(20);

    const tbody = dom.bySel['#lot-tabla tbody'];
    check(tbody.children[0].children[4].className === 'sem-rojo', 'lotes: vencido -> sem-rojo');
    check(tbody.children[1].children[4].className === 'sem-amarillo', 'lotes: vence pronto -> sem-amarillo');
    check(tbody.children[2].children[4].className === 'sem-verde', 'lotes: vigente -> sem-verde');
    check(tbody.children[0].lastChild.children.length === 1 && tbody.children[1].lastChild.children.length === 0,
        'lotes: Liberar solo en cuarentena');
    tbody.children[0].lastChild.children[0].click();
    await sleep(10);
    const lib = calls.find((c) => c.url.endsWith('/batches/1/liberar') && c.opts.method === 'POST');
    check(lib && JSON.parse(lib.opts.body).motivo.length > 0, 'lotes: Liberar envia motivo');
}

// ================= stock.js =================
{
    const dom = makeDom(
        ['app-error', 'stock-store', 'stock-buscar', 'mov-store', 'mov-tipo', 'mov-buscar'],
        ['#stock-tabla tbody', '#mov-tabla tbody'],
    );
    const fetchImpl = async (url) => {
        if (url.includes('/ops/stores')) return jsonResp(200, { success: true, data: [{ id: 1, nombre: 'Central' }] });
        if (url.includes('/inventory/stocks')) return jsonResp(200, {
            success: true,
            data: [{ store_nombre: 'Central', product_nombre: 'Ibuprofeno', numero_lote: 'L-1', fecha_vencimiento: '2027-01-01', stock_available: 4, stock_reserved: 1, stock_sold: 2, lote_estado: 'liberado' }],
        });
        return jsonResp(200, {
            success: true,
            data: [{ id: 9, created_at: '2026-10-07T10:00:00', product_nombre: 'Ibuprofeno', lot_id: 1, tipo: 'entrada', cantidad: 10, signo: 1, usuario: 'admin', ref_tipo: 'recepcion', ref_id: 5 }],
        });
    };
    run(srcStock, dom, fetchImpl, authSeed);
    await sleep(20);
    check(dom.bySel['#stock-tabla tbody'].children.length === 1, 'stock: tabla de stock renderizada');
    check(dom.bySel['#mov-tabla tbody'].children[0].children[8].textContent === 'recepcion #5',
        'stock: kardex con referencia');
}

// ================= transferencias.js =================
{
    const dom = makeDom(
        ['app-error', 'tr-origen', 'tr-destino', 'tr-idempotency', 'tr-item-lote', 'tr-item-cantidad',
            'tr-item-agregar', 'tr-items-lista', 'tr-guardar', 'tr-detalle-panel', 'tr-detalle-info',
            'tr-despachar', 'tr-recibir', 'tr-cerrar', 'tr-rechazar'],
        ['#tr-tabla tbody', '#tr-detalle-tabla tbody'],
    );
    const transferencia = () => ({
        data: {
            transferencia: { id: 31, store_origen_id: 1, store_destino_id: 2, estado: 'despachada', idempotency_key: 'k2' },
            items: [{ lot_id: 5, cantidad_despachada: 6, cantidad_recibida: null }],
        },
    });
    const fetchImpl = async (url, opts = {}) => {
        if (url.includes('/ops/stores')) return jsonResp(200, { success: true, data: [{ id: 1, nombre: 'Central' }, { id: 2, nombre: 'Norte' }] });
        if (url.includes('/inventory/batches')) return jsonResp(200, { success: true, data: [{ id: 5, numero_lote: 'L-5', product_nombre: 'Ibuprofeno' }] });
        if (url.endsWith('/inventory/transfers/31') && (!opts.method || opts.method === 'GET')) return jsonResp(200, transferencia());
        if (url.includes('/inventory/transfers') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, { success: true, data: [{ id: 31, store_origen_id: 1, store_destino_id: 2, estado: 'despachada', idempotency_key: 'k2' }] });
        }
        return jsonResp(201, { success: true, data: { id: 32 } });
    };
    const { calls } = run(srcTransf, dom, fetchImpl, authSeed);
    await sleep(20);

    check(dom.bySel['#tr-tabla tbody'].children.length === 1, 'transferencias: tabla renderizada');

    dom.byId['tr-item-agregar'].click();
    check(dom.byId['app-error'].hidden === false, 'transferencias: item sin cantidad -> error');
    dom.byId['tr-item-lote'].value = '5';
    dom.byId['tr-item-cantidad'].value = '6';
    dom.byId['tr-item-agregar'].click();
    check(dom.byId['tr-items-lista'].children.length === 1, 'transferencias: item agregado');

    dom.byId['tr-origen'].value = '1';
    dom.byId['tr-destino'].value = '1';
    await dom.byId['tr-guardar'].click();
    check(dom.byId['app-error'].textContent === 'Origen y destino deben ser sucursales distintas.',
        'transferencias: origen == destino -> error');

    dom.byId['tr-destino'].value = '2';
    dom.byId['tr-idempotency'].value = 'k2';
    await dom.byId['tr-guardar'].click();
    await sleep(10);
    const creada = calls.find((c) => c.url.endsWith('/inventory/transfers') && c.opts.method === 'POST');
    const cuerpo = creada ? JSON.parse(creada.opts.body) : {};
    check(cuerpo.store_origen_id === 1 && cuerpo.store_destino_id === 2
        && cuerpo.items[0].lot_id === 5 && cuerpo.idempotency_key === 'k2',
        'transferencias: POST con items e idempotency_key');

    dom.bySel['#tr-tabla tbody'].children[0].lastChild.children[0].click(); // Ver
    await sleep(10);
    const det = dom.bySel['#tr-detalle-tabla tbody'];
    check(dom.byId['tr-detalle-panel'].hidden === false && det.children[0].children[1].textContent === '6',
        'transferencias: detalle usa cantidad_despachada');
    check(dom.byId['tr-recibir'].disabled === false && dom.byId['tr-despachar'].disabled === true,
        'transferencias: acciones segun estado (despachada: recibir habilitado)');

    await dom.byId['tr-recibir'].click();
    await sleep(10);
    const rec = calls.find((c) => c.url.endsWith('/inventory/transfers/31/recibir') && c.opts.method === 'POST');
    const cuerpoR = rec ? JSON.parse(rec.opts.body) : {};
    check(cuerpoR.items[0].cantidad_recibida === 6,
        'transferencias: recibir completo (recibida = despachada)');
}

// ================= alertas.js =================
{
    const dom = makeDom(
        ['app-error', 'al-tipo', 'al-estado', 'al-buscar', 'al-evaluar', 'inc-form', 'inc-store',
            'inc-lote', 'inc-consumo', 'inc-guardar'],
        ['#al-tabla tbody', '#inc-tabla tbody'],
    );
    const fetchImpl = async (url, opts = {}) => {
        if (url.includes('/ops/stores')) return jsonResp(200, { success: true, data: [{ id: 1, nombre: 'Central' }] });
        if (url.includes('/inventory/batches')) return jsonResp(200, { success: true, data: [{ id: 5, numero_lote: 'L-5', product_nombre: 'Ibuprofeno' }] });
        if (url.includes('/inventory/alerts') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, {
                success: true,
                data: [{ id: 4, tipo: 'vencimiento', product_nombre: 'Ibuprofeno', store_id: 1, fecha_generada: '2026-10-07T08:00:00', estado: 'abierta' }],
            });
        }
        if (url.includes('/inventory/incidents') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, {
                success: true,
                data: [{ id: 7, store_nombre: 'Central', numero_lote: 'L-5', stock_registrado: 10, consumo_declarado: 3, estado: 'abierto', usuario: 'admin' }],
            });
        }
        return jsonResp(200, { success: true, data: {} });
    };
    const { calls } = run(srcAlertas, dom, fetchImpl, authSeed);
    await sleep(20);

    check(dom.bySel['#al-tabla tbody'].children.length === 1 && dom.bySel['#inc-tabla tbody'].children.length === 1,
        'alertas: tablas de alertas e incidentes renderizadas');

    dom.bySel['#al-tabla tbody'].children[0].lastChild.children[0].click(); // Resolver
    await sleep(10);
    check(calls.some((c) => c.url.endsWith('/alerts/4/resolver') && c.opts.method === 'POST'),
        'alertas: Resolver -> POST /resolver');

    await dom.byId['al-evaluar'].click();
    await sleep(10);
    check(calls.some((c) => c.url.endsWith('/alerts/evaluar') && c.opts.method === 'POST'),
        'alertas: Evaluar -> POST /evaluar');

    dom.byId['inc-consumo'].value = '0';
    await dom.byId['inc-form'].listeners.submit({ preventDefault() {}, target: dom.byId['inc-form'] });
    check(dom.byId['app-error'].hidden === false, 'alertas: consumo < 1 -> error');

    dom.byId['inc-store'].value = '1';
    dom.byId['inc-lote'].value = '5';
    dom.byId['inc-consumo'].value = '3';
    await dom.byId['inc-form'].listeners.submit({ preventDefault() {}, target: dom.byId['inc-form'] });
    await sleep(10);
    const inc = calls.find((c) => c.url.endsWith('/inventory/incidents') && c.opts.method === 'POST');
    const cuerpoI = inc ? JSON.parse(inc.opts.body) : {};
    check(cuerpoI.store_id === 1 && cuerpoI.lot_id === 5 && cuerpoI.consumo_declarado === 3,
        'alertas: POST de incidente con consumo declarado');

    dom.bySel['#inc-tabla tbody'].children[0].lastChild.children[0].click(); // Ajustar
    await sleep(10);
    check(calls.some((c) => c.url.endsWith('/incidents/7/ajustar') && c.opts.method === 'POST'),
        'alertas: Ajustar -> POST /ajustar (doble autorizacion)');
}

console.log('RESULTADO: ' + (fail === 0 ? 'PASS' : `FAIL (${fail})`));
process.exit(fail === 0 ? 0 : 1);
