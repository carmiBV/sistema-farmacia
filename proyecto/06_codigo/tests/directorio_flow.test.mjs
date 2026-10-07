// CP-FRONT-05: prueba de la logica de proveedores.js y pacientes.js sin navegador.
// Stub de document/localStorage/fetch/window.
// Uso: node tests/directorio_flow.test.mjs
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const srcProv = readFileSync(path.join(__dirname, '../public/assets/js/proveedores.js'), 'utf8');
const srcPac = readFileSync(path.join(__dirname, '../public/assets/js/pacientes.js'), 'utf8');

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

// ================= proveedores.js =================
{
    const dom = makeDom(
        ['app-error', 'prov-form', 'prov-identificacion', 'prov-nombre', 'prov-contacto',
            'prov-guardar', 'prov-cancelar', 'prov-buscar', 'prov-q'],
        ['#prov-tabla tbody'],
    );
    const prov = { id: 3, identificacion: 'NIT-1', nombre: 'Distribuidora X', contacto: 'x@local', estado: 'activo' };
    const fetchImpl = async (url, opts = {}) => {
        if (url.includes('/catalog/suppliers') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, { success: true, data: [prov] });
        }
        return jsonResp(201, { success: true, data: { id: 4 } });
    };
    const { calls } = run(srcProv, dom, fetchImpl, authSeed);
    await sleep(20);

    check(dom.bySel['#prov-tabla tbody'].children.length === 1, 'proveedores: tabla renderizada');

    dom.byId['prov-identificacion'].value = 'NIT-2';
    dom.byId['prov-nombre'].value = 'Farma Mayorista';
    await dom.byId['prov-form'].listeners.submit({ preventDefault() {}, target: dom.byId['prov-form'] });
    await sleep(10);
    const creado = calls.find((c) => c.url.endsWith('/catalog/suppliers') && c.opts.method === 'POST');
    const cuerpo = creado ? JSON.parse(creado.opts.body) : {};
    check(cuerpo.identificacion === 'NIT-2' && cuerpo.contacto === undefined,
        'proveedores: POST sin contacto cuando esta vacio');

    dom.bySel['#prov-tabla tbody'].children[0].lastChild.children[0].click(); // Editar
    check(dom.byId['prov-identificacion'].value === 'NIT-1', 'proveedores: Editar precarga');
    await dom.byId['prov-form'].listeners.submit({ preventDefault() {}, target: dom.byId['prov-form'] });
    await sleep(10);
    check(calls.some((c) => c.url.endsWith('/catalog/suppliers/3') && c.opts.method === 'PUT'),
        'proveedores: PUT del proveedor editado');

    dom.bySel['#prov-tabla tbody'].children[0].lastChild.children[1].click(); // Desactivar
    await sleep(10);
    check(calls.some((c) => c.url.endsWith('/catalog/suppliers/3') && c.opts.method === 'DELETE'),
        'proveedores: Desactivar -> DELETE (borrado logico)');

    dom.byId['prov-q'].value = 'farma';
    await dom.byId['prov-buscar'].listeners.submit({ preventDefault() {}, target: dom.byId['prov-buscar'] });
    await sleep(10);
    check(calls.some((c) => c.url.includes('q=farma')), 'proveedores: busqueda envia q');
}

// proveedores.js: 409 DUPLICATE_IDENTIFICATION
{
    const dom = makeDom(
        ['app-error', 'prov-form', 'prov-identificacion', 'prov-nombre', 'prov-contacto',
            'prov-guardar', 'prov-cancelar', 'prov-buscar', 'prov-q'],
        ['#prov-tabla tbody'],
    );
    const fetchImpl = async (url, opts = {}) => {
        if (opts.method === 'POST') {
            return jsonResp(409, { success: false, error: { code: 'DUPLICATE_IDENTIFICATION', message: 'La identificación ya existe.' } });
        }
        return jsonResp(200, { success: true, data: [] });
    };
    run(srcProv, dom, fetchImpl, authSeed);
    await sleep(20);
    await dom.byId['prov-form'].listeners.submit({ preventDefault() {}, target: dom.byId['prov-form'] });
    await sleep(10);
    check(dom.byId['app-error'].textContent === 'La identificación ya existe.', 'proveedores: 409 -> mensaje de la API');
}

// ================= pacientes.js (ambos paneles) =================
{
    const dom = makeDom(
        ['app-error',
            'pac-form', 'pac-identificacion', 'pac-nombre', 'pac-fecha', 'pac-contacto',
            'pac-guardar', 'pac-cancelar', 'pac-buscar', 'pac-q',
            'presc-form', 'presc-identificacion', 'presc-nombre', 'presc-especialidad',
            'presc-guardar', 'presc-cancelar', 'presc-buscar', 'presc-q'],
        ['#pac-tabla tbody', '#presc-tabla tbody'],
    );
    const fetchImpl = async (url, opts = {}) => {
        if (url.includes('/catalog/patients') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, {
                success: true,
                data: [{ id: 1, identificacion: 'P-1', nombre: 'Ana Pérez', fecha_nacimiento: '1990-05-01', contacto: null, estado: 'activo', anonimizado_at: null }],
            });
        }
        if (url.includes('/catalog/prescribers') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, {
                success: true,
                data: [{ id: 2, identificacion: 'M-1', nombre: 'Dr. Solís', especialidad: 'Cardiología', estado: 'activo' }],
            });
        }
        return jsonResp(201, { success: true, data: { id: 9 } });
    };
    const { calls } = run(srcPac, dom, fetchImpl, authSeed);
    await sleep(20);

    check(dom.bySel['#pac-tabla tbody'].children.length === 1 && dom.bySel['#presc-tabla tbody'].children.length === 1,
        'directorio: tablas de pacientes y prescriptores renderizadas');
    check(dom.bySel['#pac-tabla tbody'].children[0].children[5].textContent === 'activo',
        'directorio: columna de estado de paciente');

    dom.byId['pac-identificacion'].value = 'P-2';
    dom.byId['pac-nombre'].value = 'Luis Gómez';
    dom.byId['pac-fecha'].value = '1985-02-10';
    await dom.byId['pac-form'].listeners.submit({ preventDefault() {}, target: dom.byId['pac-form'] });
    await sleep(10);
    const pacCreado = calls.find((c) => c.url.endsWith('/catalog/patients') && c.opts.method === 'POST');
    const cuerpoP = pacCreado ? JSON.parse(pacCreado.opts.body) : {};
    check(cuerpoP.fecha_nacimiento === '1985-02-10' && cuerpoP.contacto === undefined,
        'directorio: POST de paciente con fecha_nacimiento');

    dom.byId['presc-identificacion'].value = 'M-2';
    dom.byId['presc-nombre'].value = 'Dra. Ríos';
    dom.byId['presc-especialidad'].value = 'Pediatría';
    await dom.byId['presc-form'].listeners.submit({ preventDefault() {}, target: dom.byId['presc-form'] });
    await sleep(10);
    const prescCreado = calls.find((c) => c.url.endsWith('/catalog/prescribers') && c.opts.method === 'POST');
    const cuerpoM = prescCreado ? JSON.parse(prescCreado.opts.body) : {};
    check(cuerpoM.especialidad === 'Pediatría', 'directorio: POST de prescriptor con especialidad');

    dom.bySel['#pac-tabla tbody'].children[0].lastChild.children[0].click(); // Editar paciente
    check(dom.byId['pac-nombre'].value === 'Ana Pérez', 'directorio: Editar precarga el paciente');
    await dom.byId['pac-form'].listeners.submit({ preventDefault() {}, target: dom.byId['pac-form'] });
    await sleep(10);
    check(calls.some((c) => c.url.endsWith('/catalog/patients/1') && c.opts.method === 'PUT'),
        'directorio: PUT del paciente editado');

    dom.bySel['#presc-tabla tbody'].children[0].lastChild.children[1].click(); // Desactivar prescriptor
    await sleep(10);
    check(calls.some((c) => c.url.endsWith('/catalog/prescribers/2') && c.opts.method === 'DELETE'),
        'directorio: Desactivar -> DELETE en prescriptores');
}

console.log('RESULTADO: ' + (fail === 0 ? 'PASS' : `FAIL (${fail})`));
process.exit(fail === 0 ? 0 : 1);
