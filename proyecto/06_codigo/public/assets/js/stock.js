/**
 * CP-FRONT-07: stock por lote y kardex de movimientos (Fetch API, vanilla JS).
 */
(function () {
    'use strict';

    var token = localStorage.getItem('sf_token');
    var cajaError = document.getElementById('app-error');

    function mostrarError(m) { cajaError.textContent = m; cajaError.hidden = false; }
    function limpiarError() { cajaError.hidden = true; }
    function sesionCaducada() {
        localStorage.removeItem('sf_token');
        localStorage.removeItem('sf_user');
        window.location.replace('/login');
    }

    async function api(ruta) {
        var r = await fetch(ruta, { headers: { Authorization: 'Bearer ' + token } });
        if (r.status === 401) { sesionCaducada(); throw new Error('sesion'); }
        var body = await r.json().catch(function () { return null; });
        if (!r.ok || !body || body.success !== true) {
            throw new Error((body && body.error && body.error.message) || 'Error en la operación.');
        }
        return body;
    }

    function fila(celdas) {
        var tr = document.createElement('tr');
        celdas.forEach(function (c) {
            var td = document.createElement('td');
            td.textContent = c;
            tr.appendChild(td);
        });
        return tr;
    }

    function fmtFecha(iso) {
        var d = new Date(iso);
        return isNaN(d) ? '' : d.toLocaleString('es', { dateStyle: 'short', timeStyle: 'short' });
    }

    function renderStock(data) {
        var tbody = document.querySelector('#stock-tabla tbody');
        tbody.textContent = '';
        data.forEach(function (s) {
            tbody.appendChild(fila([s.store_nombre, s.product_nombre, s.numero_lote,
                s.fecha_vencimiento, String(s.stock_available), String(s.stock_reserved),
                String(s.stock_sold), s.lote_estado]));
        });
    }

    function renderMov(data) {
        var tbody = document.querySelector('#mov-tabla tbody');
        tbody.textContent = '';
        data.forEach(function (m) {
            tbody.appendChild(fila([String(m.id), fmtFecha(m.created_at), m.product_nombre,
                String(m.lot_id), m.tipo, String(m.cantidad), String(m.signo),
                m.usuario, (m.ref_tipo || '—') + (m.ref_id ? ' #' + m.ref_id : '')]));
        });
    }

    function opcionesStores() {
        return api('/api/v1/ops/stores?limit=100').then(function (body) {
            ['stock-store', 'mov-store'].forEach(function (id) {
                var sel = document.getElementById(id);
                (body.data || []).forEach(function (s) {
                    var o = document.createElement('option');
                    o.value = String(s.id);
                    o.textContent = s.nombre;
                    sel.appendChild(o);
                });
            });
        });
    }

    async function cargarStock() {
        var ruta = '/api/v1/inventory/stocks?limit=100';
        var store = document.getElementById('stock-store').value;
        if (store) { ruta += '&store_id=' + encodeURIComponent(store); }
        var body = await api(ruta);
        renderStock(body.data || []);
    }

    async function cargarMov() {
        var ruta = '/api/v1/inventory/movements?limit=100';
        var store = document.getElementById('mov-store').value;
        var tipo = document.getElementById('mov-tipo').value;
        if (store) { ruta += '&store_id=' + encodeURIComponent(store); }
        if (tipo) { ruta += '&tipo=' + encodeURIComponent(tipo); }
        var body = await api(ruta);
        renderMov(body.data || []);
    }

    document.getElementById('stock-buscar').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        try {
            await cargarStock();
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    document.getElementById('mov-buscar').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        try {
            await cargarMov();
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    (async function init() {
        try {
            await opcionesStores();
            await Promise.all([cargarStock(), cargarMov()]);
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    })();
})();
