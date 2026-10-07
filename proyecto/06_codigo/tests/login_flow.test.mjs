// CP-FRONT-01: prueba de la logica de public/assets/js/login.js sin navegador.
// Stub de document/localStorage/fetch/window; ejecuta el IIFE y simula el submit.
// Uso: node tests/login_flow.test.mjs
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const src = readFileSync(path.join(__dirname, '../public/assets/js/login.js'), 'utf8');

let fail = 0;
const check = (ok, label) => {
    console.log((ok ? 'PASS' : 'FAIL') + ': ' + label);
    if (!ok) fail++;
};

function makeEnv(fetchImpl, seed = {}) {
    const store = new Map(Object.entries(seed));
    const calls = [];
    const form = {
        usuario: { value: '' },
        password: { value: '' },
        addEventListener: (ev, fn) => { form._submit = fn; },
    };
    const els = {
        'login-form': form,
        'login-error': { hidden: true, textContent: '' },
        'login-submit': { disabled: false },
    };
    const win = { location: { replaced: null, replace(u) { this.replaced = u; } } };
    const wrappedFetch = (url, opts) => { calls.push({ url, opts }); return fetchImpl(url, opts); };
    new Function('document', 'localStorage', 'fetch', 'window', src)(
        { getElementById: (id) => els[id] ?? null },
        {
            getItem: (k) => (store.has(k) ? store.get(k) : null),
            setItem: (k, v) => store.set(k, String(v)),
            removeItem: (k) => store.delete(k),
        },
        wrappedFetch,
        win,
    );
    return { els, store, calls, win, form };
}

const submit = async (env, usuario, password) => {
    env.form.usuario.value = usuario;
    env.form.password.value = password;
    await env.form._submit({ preventDefault() {} });
};
const tick = () => new Promise((r) => setImmediate(r));

const jsonResp = (status, body) => ({ ok: status >= 200 && status < 300, status, json: async () => body });

// 1. login exitoso -> token guardado y redireccion por rol admin
{
    const env = makeEnv(async () => jsonResp(200, { success: true, data: { token: 'tok-1', token_type: 'Bearer', expires_in: 3600, user: { id: 1, usuario: 'admin', roles: ['admin'], permisos: [] } } }));
    await submit(env, 'admin', 'secreta');
    const llamada = env.calls.find((c) => c.url.includes('/auth/login'));
    check(!!llamada && llamada.opts.method === 'POST' && JSON.parse(llamada.opts.body).usuario === 'admin', 'envia POST /auth/login con credenciales');
    check(env.store.get('sf_token') === 'tok-1', 'guarda el token en localStorage (sf_token)');
    check(env.win.location.replaced === '/dashboard', 'redirige segun rol admin -> /dashboard');
    check(JSON.parse(env.store.get('sf_user')).usuario === 'admin', 'guarda el usuario en sf_user');
}

// 2. rol sin mapeo -> destino default
{
    const env = makeEnv(async () => jsonResp(200, { success: true, data: { token: 'tok-2', user: { id: 2, usuario: 'caj', roles: ['cajero'] } } }));
    await submit(env, 'caj', 'secreta');
    check(env.win.location.replaced === '/dashboard', 'rol sin mapeo -> /dashboard (default)');
}

// 3. credenciales malas -> mensaje de error, sin redireccion
{
    const env = makeEnv(async () => jsonResp(401, { success: false, error: { code: 'INVALID_CREDENTIALS', message: 'x' } }));
    await submit(env, 'admin', 'mala');
    check(env.els['login-error'].hidden === false && env.els['login-error'].textContent === 'Usuario o contraseña incorrectos.', '401 -> "Usuario o contraseña incorrectos."');
    check(env.win.location.replaced === null, '401 -> sin redireccion');
    check(env.els['login-submit'].disabled === false, 'boton re-habilitado tras el error');
}

// 4. cuenta deshabilitada y rate limit
{
    const env = makeEnv(async () => jsonResp(403, { success: false, error: { code: 'ACCOUNT_DISABLED', message: 'x' } }));
    await submit(env, 'a', 'b');
    check(env.els['login-error'].textContent === 'La cuenta está deshabilitada. Contacte al administrador.', '403 ACCOUNT_DISABLED -> mensaje propio');
}
{
    const env = makeEnv(async () => jsonResp(429, { success: false, error: { code: 'RATE_LIMITED', message: 'x' } }));
    await submit(env, 'a', 'b');
    check(env.els['login-error'].textContent === 'Demasiados intentos fallidos. Intente más tarde.', '429 RATE_LIMITED -> mensaje propio');
}

// 5. campos vacios -> validacion local sin llamada a la API
{
    const env = makeEnv(async () => jsonResp(200, { success: true, data: {} }));
    await submit(env, '  ', '');
    check(env.calls.length === 0, 'campos vacios -> no llama a la API');
    check(env.els['login-error'].textContent === 'Complete usuario y contraseña.', 'campos vacios -> mensaje de validacion');
}

// 6. error de red
{
    const env = makeEnv(async () => { throw new Error('net'); });
    await submit(env, 'a', 'b');
    check(env.els['login-error'].textContent === 'Error de conexión con el servidor.', 'fallo de red -> mensaje de conexion');
}

// 7. sesion previa valida -> redirige al cargar sin tocar el formulario
{
    const env = makeEnv(async (url) => (String(url).includes('/auth/me')
        ? jsonResp(200, { success: true, data: { id: 1, usuario: 'admin', roles: ['admin'] } })
        : jsonResp(500, {})), { sf_token: 'tok-viejo' });
    await tick(); await tick();
    check(env.win.location.replaced === '/dashboard', 'token previo valido -> redirige al cargar');
}
// 8. sesion previa invalida -> se limpia
{
    const env = makeEnv(async () => jsonResp(401, { success: false, error: { code: 'UNAUTHENTICATED', message: 'x' } }), { sf_token: 'tok-vencido' });
    await tick(); await tick();
    check(!env.store.has('sf_token') && !env.store.has('sf_user'), 'token previo invalido -> sesion limpiada');
    check(env.win.location.replaced === null, 'token invalido -> se queda en /login');
}

console.log('RESULTADO: ' + (fail === 0 ? 'PASS' : `FAIL (${fail})`));
process.exit(fail === 0 ? 0 : 1);
