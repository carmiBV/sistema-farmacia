/**
 * CP-FRONT-02: layout + widgets del dashboard (Fetch API, vanilla JS).
 * - Guard de sesion: sin sf_token -> /login.
 * - Header: usuario activo (sf_user) y selector de sucursal (GET /ops/stores).
 * - Widgets: ventas de hoy (sales/orders), alertas stock_minimo y vencimiento
 *   (inventory/alerts?estado=abierta), con estados cargando/vacio/error.
 */
(function () {
    'use strict';

    var token = localStorage.getItem('sf_token');
    if (!token) {
        window.location.replace('/login');
        return;
    }

    var user = {};
    try { user = JSON.parse(localStorage.getItem('sf_user') || '{}'); } catch (e) { user = {}; }

    var sel = document.getElementById('store-select');
    var chip = document.getElementById('header-user');
    var cajaError = document.getElementById('app-error');

    chip.textContent = user.usuario
        ? user.usuario + (user.roles && user.roles.length ? ' · ' + user.roles.join(', ') : '')
        : 'Sesión activa';

    function sesionCaducada() {
        localStorage.removeItem('sf_token');
        localStorage.removeItem('sf_user');
        window.location.replace('/login');
    }

    function mostrarError(mensaje) {
        cajaError.textContent = mensaje;
        cajaError.hidden = false;
    }

    /** @returns Promise<{data:?array,meta:?object}> */
    async function api(ruta) {
        var r = await fetch(ruta, { headers: { Authorization: 'Bearer ' + token } });
        if (r.status === 401) {
            sesionCaducada();
            throw new Error('sesion');
        }
        var body = await r.json().catch(function () { return null; });
        if (!r.ok || !body || body.success !== true) {
            throw new Error((body && body.error && body.error.code) || 'ERROR');
        }
        return body;
    }

    function fmtMonto(n) {
        return Number(n || 0).toFixed(2);
    }

    function fmtFechaHora(iso) {
        var d = new Date(iso);
        return isNaN(d) ? '' : d.toLocaleDateString('es', { day: '2-digit', month: '2-digit' }) + ' ' +
            d.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' });
    }

    function esHoy(iso) {
        var d = new Date(iso);
        var h = new Date();
        return d.getFullYear() === h.getFullYear() && d.getMonth() === h.getMonth() && d.getDate() === h.getDate();
    }

    function widget(idDato, idDetalle, dato, detalle) {
        document.getElementById(idDato).textContent = dato;
        document.getElementById(idDetalle).textContent = detalle;
    }

    function storeQuery() {
        return sel.value ? 'store_id=' + encodeURIComponent(sel.value) + '&' : '';
    }

    async function cargarSucursales() {
        var body = await api('/api/v1/ops/stores?limit=100');
        var activas = (body.data || []).filter(function (s) { return s.estado === 'activa'; });
        var previa = localStorage.getItem('sf_store') || '';
        activas.forEach(function (s) {
            var o = document.createElement('option');
            o.value = String(s.id);
            o.textContent = s.nombre;
            sel.appendChild(o);
        });
        if (previa && activas.some(function (s) { return String(s.id) === previa; })) {
            sel.value = previa;
        }
    }

    async function cargarVentas() {
        try {
            var body = await api('/api/v1/sales/orders?' + storeQuery() + 'limit=100');
            var hoy = (body.data || []).filter(function (o) { return esHoy(o.created_at) && o.estado !== 'anulada'; });
            var total = hoy.reduce(function (a, o) { return a + Number(o.total || 0); }, 0);
            if (hoy.length === 0) {
                widget('w-ventas-dato', 'w-ventas-detalle', '0.00', 'Sin ventas registradas hoy');
                return;
            }
            widget('w-ventas-dato', 'w-ventas-detalle', fmtMonto(total),
                hoy.length + (hoy.length === 1 ? ' orden hoy' : ' órdenes hoy'));
        } catch (e) {
            if (e.message !== 'sesion') {
                widget('w-ventas-dato', 'w-ventas-detalle', '—', 'No se pudo cargar el resumen de ventas');
            }
        }
    }

    async function cargarAlertas(tipo, idDato, idDetalle, vacio) {
        try {
            var body = await api('/api/v1/inventory/alerts?' + storeQuery() +
                'tipo=' + tipo + '&estado=abierta&limit=5');
            var total = (body.meta && body.meta.total) || 0;
            var filas = body.data || [];
            if (total === 0) {
                widget(idDato, idDetalle, '0', vacio);
                return;
            }
            widget(idDato, idDetalle, String(total),
                filas.map(function (a) { return (a.product_nombre || ('producto ' + a.product_id)) + ' (' + fmtFechaHora(a.fecha_generada) + ')'; }).join(' · '));
        } catch (e) {
            if (e.message !== 'sesion') {
                widget(idDato, idDetalle, '—', 'No se pudieron cargar las alertas');
            }
        }
    }

    async function cargarTodo() {
        await Promise.all([
            cargarVentas(),
            cargarAlertas('stock_minimo', 'w-stock-dato', 'w-stock-detalle', 'Sin alertas de stock mínimo'),
            cargarAlertas('vencimiento', 'w-venc-dato', 'w-venc-detalle', 'Sin vencimientos próximos'),
        ]);
    }

    sel.addEventListener('change', function () {
        localStorage.setItem('sf_store', sel.value);
        cargarTodo();
    });

    (async function init() {
        try {
            await cargarSucursales();
        } catch (e) {
            if (e.message !== 'sesion') {
                mostrarError('No se pudo cargar el listado de sucursales.');
            }
        }
        await cargarTodo();
    })();
})();
