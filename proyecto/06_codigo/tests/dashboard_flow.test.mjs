// CP-FRONT-02: prueba de la logica de public/assets/js/dashboard.js sin navegador.
// Stub de document/localStorage/fetch/window; ejecuta el IIFE y verifica sesion,
// selector de sucursal y widgets (datos, vacio, error, 401).
// Uso: node tests/dashboard_flow.test.mjs
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const src = readFileSync(path.join(__dirname, '../public/assets/js/dashboard.js'), 'utf8');

let fail = 0;
const check = (ok, label) => {
    console.log((ok ? 'PASS' : 'FAIL') + ': ' + label);
    if (!ok) fail++;
};
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function makeEnv(fetchImpl, seed = {}) {
    const store = new Map(Object.entries(seed));
    const calls = [];
    const el = () => ({ textContent: '', hidden: true });
    const sel = {
        value: '',
        options: [],
        appendChild(o) { this.options.push(o); },
        listeners: {},
        addEventListener(ev, fn) { this.listeners[ev] = fn; },
    };
    const els = {
        'store-select': sel,
        'header-user': el(),
        'app-error': el(),
        'w-ventas-dato': el(), 'w-ventas-detalle': el(),
        'w-stock-dato': el(), 'w-stock-detalle': el(),
        'w-venc-dato': el(), 'w-venc-detalle': el(),
    };
    const win = { location: { replaced: null, replace(u) { this.replaced = u; } } };
    const wrappedFetch = (url, opts) => { calls.push({ url: String(url), opts }); return fetchImpl(String(url), opts); };
    new Function('document', 'localStorage', 'fetch', 'window', src)(
        {
            getElementById: (id) => els[id] ?? null,
            createElement: () => ({ value: '', textContent: '' }),
        },
        {
            getItem: (k) => (store.has(k) ? store.get(k) : null),
            setItem: (k, v) => store.set(k, String(v)),
            removeItem: (k) => store.delete(k),
        },
        wrappedFetch,
        win,
    );
    return { els, sel, store, calls, win };
}

const jsonResp = (status, body) => ({ ok: status >= 200 && status < 300, status, json: async () => body });

const rutas = (hoy, overrides = {}) => async (url) => {
    if (url.includes('/ops/stores')) return overrides.stores ?? jsonResp(200, {
        success: true,
        data: [
            { id: 1, nombre: 'Central', estado: 'activa' },
            { id: 2, nombre: 'Norte', estado: 'inactiva' },
        ],
    });
    if (url.includes('/sales/orders')) return overrides.orders ?? jsonResp(200, {
        success: true,
        data: hoy,
        meta: { total: hoy.length, page: 1, limit: 100 },
    });
    if (url.includes('tipo=stock_minimo')) return overrides.stock ?? jsonResp(200, {
        success: true,
        data: [
            { id: 11, tipo: 'stock_minimo', product_id: 7, product_nombre: 'Ibuprofeno 400mg', fecha_generada: '2026-10-07T08:00:00' },
            { id: 12, tipo: 'stock_minimo', product_id: 8, product_nombre: 'Paracetamol 500mg', fecha_generada: '2026-10-07T09:00:00' },
        ],
        meta: { total: 3, page: 1, limit: 5 },
    });
    if (url.includes('tipo=vencimiento')) return overrides.venc ?? jsonResp(200, { success: true, data: [], meta: { total: 0 } });
    return jsonResp(404, { success: false, error: { code: 'NOT_FOUND' } });
};

const ahora = new Date();
const iso = (d) => d.toISOString();
const pedidosBase = () => ([
    { id: 1, total: '10.00', estado: 'pagada', created_at: iso(ahora) },
    { id: 2, total: '5.50', estado: 'pendiente', created_at: iso(ahora) },
    { id: 3, total: '99.00', estado: 'anulada', created_at: iso(ahora) },
    { id: 4, total: '50.00', estado: 'pagada', created_at: '2020-01-01T10:00:00.000Z' },
]);

// 1. sin token -> redirige a /login y no toca nada mas
{
    const env = makeEnv(async () => jsonResp(200, {}));
    await sleep(10);
    check(env.win.location.replaced === '/login', 'sin sf_token -> /login');
    check(env.calls.length === 0, 'sin sesion -> sin llamadas a la API');
}

// 2. con sesion: usuario, sucursales y widgets
{
    const env = makeEnv(rutas(pedidosBase()), { sf_token: 'tok', sf_user: JSON.stringify({ id: 1, usuario: 'admin', roles: ['admin'] }) });
    await sleep(20);
    check(env.els['header-user'].textContent === 'admin · admin', 'header muestra usuario activo y roles');
    check(env.sel.options.length === 1 && env.sel.options[0].value === '1', 'selector solo con sucursales activas');
    check(env.els['w-ventas-dato'].textContent === '15.50', 'ventas de hoy suman no anuladas (10.00+5.50)');
    check(env.els['w-ventas-detalle'].textContent === '2 órdenes hoy', 'detalle de ventas excluye anuladas y dias previos');
    check(env.els['w-stock-dato'].textContent === '3', 'widget stock_minimo usa meta.total');
    check(env.els['w-stock-detalle'].textContent.includes('Ibuprofeno 400mg'), 'detalle de stock muestra productos');
    check(env.els['w-venc-dato'].textContent === '0' && env.els['w-venc-detalle'].textContent === 'Sin vencimientos próximos',
        'vencimientos sin alertas -> estado vacio');
}

// 3. cambio de sucursal -> persiste y reconsulta con store_id
{
    const env = makeEnv(rutas(pedidosBase()), { sf_token: 'tok', sf_user: '{}' });
    await sleep(20);
    env.sel.value = '1';
    await env.sel.listeners.change();
    await sleep(10);
    check(env.store.get('sf_store') === '1', 'cambio de sucursal persiste en sf_store');
    check(env.calls.some((c) => c.url.includes('/sales/orders') && c.url.includes('store_id=1')), 'widgets reconsultan con store_id=1');
}

// 4. 401 -> limpia sesion y redirige
{
    const env = makeEnv(async () => jsonResp(401, { success: false, error: { code: 'UNAUTHENTICATED' } }),
        { sf_token: 'tok-vencido', sf_user: '{}' });
    await sleep(20);
    check(env.win.location.replaced === '/login', '401 -> redirige a /login');
    check(!env.store.has('sf_token') && !env.store.has('sf_user'), '401 -> sesion limpiada');
}

// 5. error de API en un widget -> estado de error sin tumbar el resto
{
    const hoy = pedidosBase();
    const env = makeEnv(rutas(hoy, { orders: jsonResp(500, { success: false, error: { code: 'DATABASE_ERROR' } }) }),
        { sf_token: 'tok', sf_user: '{}' });
    await sleep(20);
    check(env.els['w-ventas-dato'].textContent === '—' && env.els['w-ventas-detalle'].textContent === 'No se pudo cargar el resumen de ventas',
        'error en ventas -> estado de error del widget');
    check(env.els['w-stock-dato'].textContent === '3', 'los demas widgets siguen operativos');
    check(env.els['app-error'].hidden === true, 'sin error global');
}

console.log('RESULTADO: ' + (fail === 0 ? 'PASS' : `FAIL (${fail})`));
process.exit(fail === 0 ? 0 : 1);
