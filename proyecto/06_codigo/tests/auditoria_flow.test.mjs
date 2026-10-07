// CP-FRONT-10: prueba de la logica de auditoria.js sin navegador.
// Stub de document/localStorage/fetch/window.
// Uso: node tests/auditoria_flow.test.mjs
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const src = readFileSync(path.join(__dirname, '../public/assets/js/auditoria.js'), 'utf8');

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

function run(fetchImpl, seed = {}) {
    const dom = makeDom(
        ['app-error', 'aud-buscar', 'aud-accion', 'aud-entidad', 'aud-desde', 'aud-hasta',
            'aud-detalle', 'aud-detalle-info', 'aud-detalle-json',
            'pii-buscar', 'pii-accion', 'evt-buscar', 'evt-estado', 'evt-detalle', 'evt-detalle-json'],
        ['#aud-tabla tbody', '#pii-tabla tbody', '#evt-tabla tbody'],
    );
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
    return { dom, store, calls, win };
}

const jsonResp = (status, body) => ({ ok: status >= 200 && status < 300, status, json: async () => body });
const authSeed = { sf_token: 'tok', sf_user: '{}' };

// 1. render + acciones segun estado + transiciones + filtros
{
    const fetchImpl = async (url, opts = {}) => {
        if (url.includes('/audit/operations/3')) return jsonResp(200, {
            success: true,
            data: { id: 3, accion: 'modificacion', entidad: 'catalog_products', entidad_id: 7, valores_antes: null, valores_despues: { nombre: '[REDACTADO]' } },
        });
        if (url.includes('/audit/operations')) return jsonResp(200, {
            success: true,
            data: [{ id: 3, created_at: '2026-10-07T10:00:00', usuario_id: 1, accion: 'modificacion', entidad: 'catalog_products', entidad_id: 7, motivo: null }],
        });
        if (url.includes('/audit/pii')) return jsonResp(200, {
            success: true,
            data: [{ id: 4, created_at: '2026-10-07T10:05:00', usuario_id: 1, accion: 'consulta', paciente_id: 1, prescription_id: 5, motivo: null }],
        });
        if (url.includes('/audit/events/8')) return jsonResp(200, {
            success: true,
            data: { id: 8, tipo_evento: 'venta.registrada', payload: { order_id: 55 }, estado: 'pendiente' },
        });
        if (url.includes('/audit/events')) return jsonResp(200, {
            success: true,
            data: [
                { id: 8, tipo_evento: 'venta.registrada', agregado_tipo: 'venta', agregado_id: 55, estado: 'pendiente', intentos: 0, created_at: '2026-10-07T10:10:00' },
                { id: 9, tipo_evento: 'recepcion.confirmada', agregado_tipo: 'recepcion', agregado_id: 5, estado: 'fallido', intentos: 1, created_at: '2026-10-07T10:11:00' },
                { id: 10, tipo_evento: 'venta.registrada', agregado_tipo: 'venta', agregado_id: 56, estado: 'procesado', intentos: 1, created_at: '2026-10-07T10:12:00' },
            ],
        });
        return jsonResp(200, { success: true, data: {} });
    };
    const { dom, calls } = run(fetchImpl, authSeed);
    await sleep(20);

    check(dom.bySel['#aud-tabla tbody'].children.length === 1
        && dom.bySel['#pii-tabla tbody'].children.length === 1
        && dom.bySel['#evt-tabla tbody'].children.length === 3, 'auditoria: 3 tablas renderizadas');

    dom.byId['aud-accion'].value = 'modificacion';
    dom.byId['aud-entidad'].value = 'catalog_products';
    dom.byId['aud-desde'].value = '2026-10-01';
    await dom.byId['aud-buscar'].listeners.submit({ preventDefault() {}, target: dom.byId['aud-buscar'] });
    await sleep(10);
    check(calls.some((c) => c.url.includes('accion=modificacion') && c.url.includes('entidad=catalog_products')
        && c.url.includes('desde=2026-10-01')), 'auditoria: filtros de operaciones enviados (RF-090)');

    dom.bySel['#aud-tabla tbody'].children[0].lastChild.children[0].click(); // Ver
    await sleep(10);
    check(dom.byId['aud-detalle'].hidden === false
        && dom.byId['aud-detalle-json'].textContent.includes('[REDACTADO]'),
        'auditoria: detalle con valores redactados (RNF-050)');

    dom.byId['pii-accion'].value = 'consulta';
    await dom.byId['pii-buscar'].listeners.submit({ preventDefault() {}, target: dom.byId['pii-buscar'] });
    await sleep(10);
    check(calls.some((c) => c.url.includes('/audit/pii') && c.url.includes('accion=consulta')),
        'auditoria: filtro de accesos PII enviado (RF-091)');

    const evt = dom.bySel['#evt-tabla tbody'];
    check(evt.children[0].lastChild.children.length === 3 && evt.children[1].lastChild.children.length === 2
        && evt.children[2].lastChild.children.length === 1,
        'auditoria: acciones segun estado (pendiente: Ver/Procesar/Fallar; fallido: Ver/Reintentar; procesado: Ver)');

    evt.children[0].lastChild.children[1].click(); // Procesar
    await sleep(10);
    check(calls.some((c) => c.url.endsWith('/audit/events/8/procesar') && c.opts.method === 'POST'),
        'auditoria: Procesar -> POST /procesar');

    evt.children[1].lastChild.children[1].click(); // Reintentar
    await sleep(10);
    check(calls.some((c) => c.url.endsWith('/audit/events/9/reintentar') && c.opts.method === 'POST'),
        'auditoria: Reintentar -> POST /reintentar');

    evt.children[0].lastChild.children[0].click(); // Ver evento
    await sleep(10);
    check(dom.byId['evt-detalle'].hidden === false && dom.byId['evt-detalle-json'].textContent.includes('venta.registrada'),
        'auditoria: payload del evento visible');
}

// 2. 403 -> mensaje de permisos (RN-13)
{
    const { dom } = run(async () => jsonResp(403, { success: false, error: { code: 'FORBIDDEN' } }), authSeed);
    await sleep(20);
    check(dom.byId['app-error'].hidden === false && dom.byId['app-error'].textContent.includes('audit.read'),
        'auditoria: 403 -> mensaje de permisos');
}

console.log('RESULTADO: ' + (fail === 0 ? 'PASS' : `FAIL (${fail})`));
process.exit(fail === 0 ? 0 : 1);
