// CP-FRONT-03: prueba de la logica de usuarios.js y configuracion.js sin navegador.
// Stub de document/localStorage/fetch/window; verifica listados, creacion,
// edicion, borrado logico y mapeo de errores (403/409/400).
// Uso: node tests/admin_flow.test.mjs
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const srcUsuarios = readFileSync(path.join(__dirname, '../public/assets/js/usuarios.js'), 'utf8');
const srcConfig = readFileSync(path.join(__dirname, '../public/assets/js/configuracion.js'), 'utf8');

let fail = 0;
const check = (ok, label) => {
    console.log((ok ? 'PASS' : 'FAIL') + ': ' + label);
    if (!ok) fail++;
};
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function el(tag) {
    let tc = '';
    const e = {
        tag, children: [], listeners: {}, value: '', hidden: false,
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

function makeDom(ids, querySelectors) {
    const byId = {};
    ids.forEach((id) => { byId[id] = el('div'); });
    const bySel = {};
    querySelectors.forEach((s) => { bySel[s] = el('tbody'); });
    return {
        byId, bySel,
        document: {
            getElementById: (id) => byId[id] ?? null,
            querySelector: (s) => bySel[s] ?? null,
            createElement: (tag) => el(tag),
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
const authSeed = { sf_token: 'tok', sf_user: JSON.stringify({ id: 1, usuario: 'admin', roles: ['admin'] }) };

// ================= usuarios.js =================
{
    const dom = makeDom(
        ['app-error', 'u-usuario', 'u-password', 'u-roles', 'u-asignar', 'u-asignar-roles', 'u-asignar-titulo',
            'u-asignar-cancelar', 'r-nombre', 'r-descripcion', 'r-permisos', 'u-form', 'r-form'],
        ['#u-tabla tbody', '#r-tabla tbody'],
    );
    const fetchImpl = async (url, opts = {}) => {
        if (url.includes('/auth/roles') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, { success: true, data: [{ id: 6, nombre: 'admin', descripcion: 'Full', permisos: ['auth.users.manage'] }] });
        }
        if (url.includes('/auth/users') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, { success: true, data: [{ id: 1, usuario: 'admin', estado: 'activo', roles: ['admin'] }] });
        }
        return jsonResp(201, { success: true, data: { id: 9 } });
    };
    const { calls } = run(srcUsuarios, dom, fetchImpl, authSeed);
    await sleep(20);

    check(dom.bySel['#u-tabla tbody'].children.length === 1, 'usuarios: tabla renderizada');
    check(dom.byId['u-roles'].options.length === 1 && dom.byId['u-roles'].options[0].textContent === 'admin',
        'usuarios: select de roles poblado');

    dom.byId['u-usuario'].value = 'nuevo.user';
    dom.byId['u-password'].value = 'secreta123';
    dom.byId['u-roles'].options[0].selected = true;
    await dom.byId['u-form'].listeners.submit({ preventDefault() {}, target: dom.byId['u-form'] });
    await sleep(10);
    const creado = calls.find((c) => c.url.endsWith('/auth/users') && c.opts.method === 'POST');
    const cuerpo = creado ? JSON.parse(creado.opts.body) : {};
    check(cuerpo.usuario === 'nuevo.user' && cuerpo.password === 'secreta123' && cuerpo.roles[0] === 'admin',
        'usuarios: POST /auth/users con roles por NOMBRE');

    const fila0 = dom.bySel['#u-tabla tbody'].children[0];
    fila0.lastChild.children[0].click(); // boton 'Asignar roles'
    check(dom.byId['u-asignar'].hidden === false, 'usuarios: boton Asignar roles abre el formulario');
    dom.byId['u-asignar-roles'].options[0].selected = true;
    await dom.byId['u-asignar'].listeners.submit({ preventDefault() {}, target: dom.byId['u-asignar'] });
    await sleep(10);
    const asignado = calls.find((c) => c.url.includes('/roles') && c.opts.method === 'PUT');
    check(asignado && JSON.parse(asignado.opts.body).roles[0] === 6,
        'usuarios: PUT /auth/users/{id}/roles con IDs');

    dom.byId['r-nombre'].value = 'cajero';
    dom.byId['r-permisos'].value = 'sales.manage, rx.manage';
    await dom.byId['r-form'].listeners.submit({ preventDefault() {}, target: dom.byId['r-form'] });
    await sleep(10);
    const rolCreado = calls.find((c) => c.url.endsWith('/auth/roles') && c.opts.method === 'POST');
    const cuerpoRol = rolCreado ? JSON.parse(rolCreado.opts.body) : {};
    check(cuerpoRol.nombre === 'cajero' && cuerpoRol.permisos.join(',') === 'sales.manage,rx.manage',
        'usuarios: POST /auth/roles con permisos separados por coma');
}

// usuarios.js: 403 y 409
{
    const dom = makeDom(
        ['app-error', 'u-usuario', 'u-password', 'u-roles', 'u-asignar', 'u-asignar-roles', 'u-asignar-titulo',
            'u-asignar-cancelar', 'r-nombre', 'r-descripcion', 'r-permisos', 'u-form', 'r-form'],
        ['#u-tabla tbody', '#r-tabla tbody'],
    );
    const { } = run(srcUsuarios, dom, async () => jsonResp(403, { success: false, error: { code: 'FORBIDDEN' } }), authSeed);
    await sleep(20);
    check(dom.byId['app-error'].hidden === false
        && dom.byId['app-error'].textContent.includes('auth.users.manage'), 'usuarios: 403 -> mensaje de permisos');
}
{
    const dom = makeDom(
        ['app-error', 'u-usuario', 'u-password', 'u-roles', 'u-asignar', 'u-asignar-roles', 'u-asignar-titulo',
            'u-asignar-cancelar', 'r-nombre', 'r-descripcion', 'r-permisos', 'u-form', 'r-form'],
        ['#u-tabla tbody', '#r-tabla tbody'],
    );
    const fetchImpl = async (url, opts = {}) => {
        if (opts.method === 'POST') return jsonResp(409, { success: false, error: { code: 'DUPLICATE_NAME', message: 'Ya existe el usuario.' } });
        return jsonResp(200, { success: true, data: [] });
    };
    const { } = run(srcUsuarios, dom, fetchImpl, authSeed);
    await sleep(20);
    await dom.byId['u-form'].listeners.submit({ preventDefault() {}, target: dom.byId['u-form'] });
    await sleep(10);
    check(dom.byId['app-error'].textContent === 'Ya existe el usuario.', 'usuarios: 409 -> mensaje de la API');
}

// ================= configuracion.js =================
{
    const dom = makeDom(
        ['app-error', 's-form', 's-codigo', 's-nombre', 's-guardar', 's-cancelar',
            'c-form', 'c-store', 'c-codigo', 'c-guardar', 'c-cancelar',
            'p-form', 'p-key', 'p-value', 'p-type', 'p-store', 'p-guardar', 'p-cancelar'],
        ['#s-tabla tbody', '#c-tabla tbody', '#p-tabla tbody'],
    );
    const fetchImpl = async (url, opts = {}) => {
        if (url.includes('/ops/stores') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, { success: true, data: [{ id: 1, codigo: 'CEN', nombre: 'Central', estado: 'activa' }] });
        }
        if (url.includes('/ops/registers') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, { success: true, data: [{ id: 5, store_id: 1, codigo: 'C1', estado: 'activa' }] });
        }
        if (url.includes('/ops/config') && (!opts.method || opts.method === 'GET')) {
            return jsonResp(200, { success: true, data: [{ config_key: 'alerta.dias_vencimiento', config_value: '30', value_type: 'numero', store_id: null }] });
        }
        return jsonResp(200, { success: true, data: {} });
    };
    const { calls } = run(srcConfig, dom, fetchImpl, authSeed);
    await sleep(20);

    check(dom.bySel['#s-tabla tbody'].children.length === 1 && dom.bySel['#c-tabla tbody'].children.length === 1
        && dom.bySel['#p-tabla tbody'].children.length === 1, 'config: las 3 tablas renderizadas');
    check(dom.byId['c-store'].options.length === 1 && dom.byId['p-store'].options.length === 2,
        'config: selects de sucursal poblados (p-store incluye opcion vacia)');

    dom.byId['s-codigo'].value = 'SUR';
    dom.byId['s-nombre'].value = 'Sucursal Sur';
    await dom.byId['s-form'].listeners.submit({ preventDefault() {}, target: dom.byId['s-form'] });
    await sleep(10);
    const sCreada = calls.find((c) => c.url.endsWith('/ops/stores') && c.opts.method === 'POST');
    check(sCreada && JSON.parse(sCreada.opts.body).codigo === 'SUR', 'config: POST /ops/stores');

    dom.bySel['#s-tabla tbody'].children[0].lastChild.children[0].click(); // Editar
    check(dom.byId['s-codigo'].value === 'CEN' && dom.byId['s-guardar'].textContent === 'Guardar cambios',
        'config: Editar precarga el formulario');
    await dom.byId['s-form'].listeners.submit({ preventDefault() {}, target: dom.byId['s-form'] });
    await sleep(10);
    check(calls.some((c) => c.url.endsWith('/ops/stores/1') && c.opts.method === 'PUT'), 'config: PUT /ops/stores/{id}');

    dom.bySel['#s-tabla tbody'].children[0].lastChild.children[1].click(); // Desactivar
    await sleep(10);
    check(calls.some((c) => c.url.endsWith('/ops/stores/1') && c.opts.method === 'DELETE'), 'config: DELETE (borrado logico)');

    dom.byId['p-key'].value = 'alerta.dias_vencimiento';
    dom.byId['p-value'].value = '45';
    dom.byId['p-type'].value = 'numero';
    await dom.byId['p-form'].listeners.submit({ preventDefault() {}, target: dom.byId['p-form'] });
    await sleep(10);
    const pGuardado = calls.find((c) => c.url.includes('/ops/config/alerta.dias_vencimiento') && c.opts.method === 'PUT');
    const cuerpoP = pGuardado ? JSON.parse(pGuardado.opts.body) : {};
    check(cuerpoP.config_value === '45' && cuerpoP.value_type === 'numero' && cuerpoP.store_id === undefined,
        'config: PUT /ops/config/{key} con config_value/value_type');
}

// configuracion.js: 403
{
    const dom = makeDom(
        ['app-error', 's-form', 's-codigo', 's-nombre', 's-guardar', 's-cancelar',
            'c-form', 'c-store', 'c-codigo', 'c-guardar', 'c-cancelar',
            'p-form', 'p-key', 'p-value', 'p-type', 'p-store', 'p-guardar', 'p-cancelar'],
        ['#s-tabla tbody', '#c-tabla tbody', '#p-tabla tbody'],
    );
    run(srcConfig, dom, async () => jsonResp(403, { success: false, error: { code: 'FORBIDDEN' } }), authSeed);
    await sleep(20);
    check(dom.byId['app-error'].hidden === false
        && dom.byId['app-error'].textContent.includes('ops.config.manage'), 'config: 403 -> mensaje de permisos');
}

console.log('RESULTADO: ' + (fail === 0 ? 'PASS' : `FAIL (${fail})`));
process.exit(fail === 0 ? 0 : 1);
