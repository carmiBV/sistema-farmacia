/**
 * CP-FRONT-09: ventas, pagos y transacciones (Fetch API, vanilla JS).
 * Pagos acumulativos (pendiente -> pagada por suma) y anulacion con reposicion
 * de stock (RN-10: movimientos compensatorios).
 */
(function () {
    'use strict';

    var token = localStorage.getItem('sf_token');
    var products = [];
    var ordenSel = null;
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

    function boton(texto, alClic) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn btn-mini';
        b.textContent = texto;
        b.addEventListener('click', alClic);
        return b;
    }

    function fmtFecha(iso) {
        var d = new Date(iso);
        return isNaN(d) ? '' : d.toLocaleString('es', { dateStyle: 'short', timeStyle: 'short' });
    }

    function nombreProducto(id) {
        var p = products.filter(function (x) { return String(x.id) === String(id); })[0];
        return p ? p.nombre : ('producto ' + id);
    }

    function render(data) {
        var tbody = document.querySelector('#v-tabla tbody');
        tbody.textContent = '';
        data.forEach(function (o) {
            var tr = fila([String(o.id), fmtFecha(o.created_at), Number(o.total).toFixed(2), o.estado, '']);
            tr.lastChild.appendChild(boton('Ver', function () { verDetalle(o.id); }));
            tbody.appendChild(tr);
        });
    }

    async function verDetalle(id) {
        limpiarError();
        try {
            var body = await api('/api/v1/sales/orders/' + id);
            var d = body.data;
            ordenSel = d.orden.id;
            document.getElementById('v-detalle-info').textContent =
                'Venta ' + d.orden.id + ' · estado: ' + d.orden.estado + ' · total: ' +
                Number(d.orden.total).toFixed(2);
            var tItems = document.querySelector('#v-items-tabla tbody');
            tItems.textContent = '';
            (d.items || []).forEach(function (i) {
                tItems.appendChild(fila([nombreProducto(i.product_id), String(i.lot_id),
                    String(i.cantidad), Number(i.precio_unitario).toFixed(2), Number(i.subtotal).toFixed(2)]));
            });
            var tPagos = document.querySelector('#v-pagos-tabla tbody');
            tPagos.textContent = '';
            (d.pagos || []).forEach(function (p) {
                tPagos.appendChild(fila([String(p.id), p.medio, Number(p.amount).toFixed(2), p.status]));
            });
            document.getElementById('v-detalle-panel').hidden = false;
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    }

    async function cargar() {
        var rs = await Promise.all([
            api('/api/v1/catalog/products?limit=100'),
            api('/api/v1/ops/stores?limit=100'),
        ]);
        products = rs[0].data || [];
        var sel = document.getElementById('v-store');
        sel.textContent = '';
        var todas = document.createElement('option');
        todas.value = '';
        todas.textContent = 'Todas';
        sel.appendChild(todas);
        (rs[1].data || []).forEach(function (s) {
            var o = document.createElement('option');
            o.value = String(s.id);
            o.textContent = s.nombre;
            sel.appendChild(o);
        });
        await buscar();
    }

    async function buscar() {
        var ruta = '/api/v1/sales/orders?limit=100';
        var store = document.getElementById('v-store').value;
        var estado = document.getElementById('v-estado').value;
        if (store) { ruta += '&store_id=' + encodeURIComponent(store); }
        if (estado) { ruta += '&estado=' + encodeURIComponent(estado); }
        var body = await api(ruta);
        render(body.data || []);
    }

    document.getElementById('v-buscar').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        try {
            await buscar();
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    document.getElementById('v-pago-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        if (!ordenSel) { return; }
        var monto = parseFloat(document.getElementById('v-pago-monto').value);
        if (!monto || monto <= 0) {
            mostrarError('Ingrese un monto válido.');
            return;
        }
        try {
            await api('/api/v1/sales/orders/' + ordenSel + '/payments', 'POST', {
                medio: document.getElementById('v-pago-medio').value,
                monto: monto,
            });
            document.getElementById('v-pago-monto').value = '';
            await verDetalle(ordenSel);
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    document.getElementById('v-anular').addEventListener('click', async function () {
        limpiarError();
        if (!ordenSel) { return; }
        try {
            await api('/api/v1/sales/orders/' + ordenSel + '/anular', 'POST');
            await buscar();
            await verDetalle(ordenSel);
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    cargar().catch(function (err) {
        if (err.message !== 'sesion') { mostrarError(err.message); }
    });
})();
