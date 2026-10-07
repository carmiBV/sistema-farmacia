/**
 * CP-FRONT-08: libro oficial de controlados (Fetch API, vanilla JS).
 * Asientos (append-only) con filtros por periodo (RF-071), saldos con
 * conciliacion (RNF-024) y ajustes con doble autorizacion (RF-046: el
 * autorizador debe ser un usuario distinto al propuesto por sesion).
 */
(function () {
    'use strict';

    var token = localStorage.getItem('sf_token');
    var products = [];
    var cajaError = document.getElementById('app-error');

    function mostrarError(m) { cajaError.textContent = m; cajaError.hidden = false; }
    function limpiarError() { cajaError.hidden = true; }
    function sesionCaducada() {
        localStorage.removeItem('sf_token');
        localStorage.removeItem('sf_user');
        window.location.replace('/login');
    }

    async function api(ruta, metodo, cuerpo) {
        var r = await fetch(ruta, {
            method: metodo || 'GET',
            headers: cuerpo ? { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' }
                : { Authorization: 'Bearer ' + token },
            body: cuerpo ? JSON.stringify(cuerpo) : undefined,
        });
        if (r.status === 401) { sesionCaducada(); throw new Error('sesion'); }
        var body = await r.json().catch(function () { return null; });
        if (!r.ok && r.status !== 204) {
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

    function nombreProducto(id) {
        var p = products.filter(function (x) { return String(x.id) === String(id); })[0];
        return p ? p.nombre : ('producto ' + id);
    }

    function opciones(sel, listado) {
        sel.textContent = '';
        listado.forEach(function (it) {
            var o = document.createElement('option');
            o.value = String(it.value !== undefined ? it.value : it.id);
            o.textContent = it.label !== undefined ? it.label : it.nombre;
            sel.appendChild(o);
        });
    }

    function renderLedger(data) {
        var tbody = document.querySelector('#led-tabla tbody');
        tbody.textContent = '';
        data.forEach(function (l) {
            tbody.appendChild(fila([String(l.id), fmtFecha(l.created_at), l.tipo,
                nombreProducto(l.product_id), String(l.cantidad), String(l.saldo_resultante),
                (l.ref_tipo || '—') + (l.ref_id ? ' #' + l.ref_id : ''), l.motivo || '—']));
        });
    }

    function renderSaldos(data) {
        var tbody = document.querySelector('#sal-tabla tbody');
        tbody.textContent = '';
        data.forEach(function (s) {
            tbody.appendChild(fila([String(s.store_id), nombreProducto(s.product_id),
                String(s.saldo), String(s.saldo_calculado), s.conciliado ? 'sí' : 'NO']));
        });
    }

    async function cargarLedger() {
        var ruta = '/api/v1/control/ledger?limit=100';
        var tipo = document.getElementById('led-tipo').value;
        var ref = document.getElementById('led-ref').value;
        var desde = document.getElementById('led-desde').value;
        var hasta = document.getElementById('led-hasta').value;
        if (tipo) { ruta += '&tipo=' + encodeURIComponent(tipo); }
        if (ref) { ruta += '&ref_tipo=' + encodeURIComponent(ref); }
        if (desde) { ruta += '&desde=' + encodeURIComponent(desde); }
        if (hasta) { ruta += '&hasta=' + encodeURIComponent(hasta); }
        var body = await api(ruta);
        renderLedger(body.data || []);
    }

    async function cargarSaldos() {
        var body = await api('/api/v1/control/balances?limit=100');
        renderSaldos(body.data || []);
    }

    async function cargarSelects() {
        var rs = await Promise.all([
            api('/api/v1/catalog/products?limit=100'),
            api('/api/v1/ops/stores?limit=100'),
            api('/api/v1/inventory/batches?limit=100'),
            api('/api/v1/auth/users'),
        ]);
        products = rs[0].data || [];
        opciones(document.getElementById('aj-store'), rs[1].data || []);
        opciones(document.getElementById('aj-producto'), products);
        opciones(document.getElementById('aj-lote'), (rs[2].data || []).map(function (l) {
            return { value: l.id, label: l.numero_lote + ' — ' + l.product_nombre };
        }));
        opciones(document.getElementById('aj-autorizador'), (rs[3].data || []).map(function (u) {
            return { value: u.id, label: u.usuario };
        }));
    }

    document.getElementById('led-buscar').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        try {
            await cargarLedger();
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    document.getElementById('sal-conciliar').addEventListener('click', async function () {
        limpiarError();
        try {
            var body = await api('/api/v1/control/reconciliation');
            var d = body.data;
            document.getElementById('sal-conciliacion').textContent =
                'Total: ' + d.total + ' · conciliados: ' + d.conciliados + ' · desbalanceados: ' + d.desbalanceados;
            await cargarSaldos();
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    document.getElementById('aj-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        var cantidad = parseInt(document.getElementById('aj-cantidad').value, 10);
        if (!cantidad || cantidad < 1) {
            mostrarError('La cantidad debe ser >= 1.');
            return;
        }
        try {
            await api('/api/v1/control/adjustments', 'POST', {
                store_id: parseInt(document.getElementById('aj-store').value, 10),
                product_id: parseInt(document.getElementById('aj-producto').value, 10),
                lot_id: parseInt(document.getElementById('aj-lote').value, 10),
                cantidad: cantidad,
                direccion: document.getElementById('aj-direccion').value,
                motivo: document.getElementById('aj-motivo').value.trim(),
                autorizador_id: parseInt(document.getElementById('aj-autorizador').value, 10),
            });
            document.getElementById('aj-motivo').value = '';
            document.getElementById('aj-cantidad').value = '';
            await Promise.all([cargarLedger(), cargarSaldos()]);
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    (async function init() {
        try {
            await cargarSelects();
            await Promise.all([cargarLedger(), cargarSaldos()]);
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    })();
})();
