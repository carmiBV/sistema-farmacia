/**
 * CP-FRONT-09: devoluciones (Fetch API, vanilla JS).
 * POST /sales/returns {order_id, motivo, items:[{order_item_id, cantidad,
 * condicion}]}; condicion vendible -> reingresa stock, no_vendible -> baja.
 */
(function () {
    'use strict';

    var token = localStorage.getItem('sf_token');
    var products = [];
    var itemsVenta = [];
    var devueltoPorItem = {};
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

    async function cargarVenta(id) {
        limpiarError();
        var body = await api('/api/v1/sales/orders/' + id);
        var d = body.data;
        ordenSel = d.orden.id;
        itemsVenta = d.items || [];
        devueltoPorItem = {};
        // ponytail: N+1 sobre las devoluciones de la orden (pocas en la practica)
        var devoluciones = await api('/api/v1/sales/returns?order_id=' + id + '&limit=100');
        for (var i = 0; i < (devoluciones.data || []).length; i++) {
            var det = await api('/api/v1/sales/returns/' + devoluciones.data[i].id);
            (det.data.items || []).forEach(function (it) {
                devueltoPorItem[it.order_item_id] = (devueltoPorItem[it.order_item_id] || 0) + Number(it.cantidad);
            });
        }
        document.getElementById('dv-info').textContent =
            'Venta ' + d.orden.id + ' · estado: ' + d.orden.estado;
        var tbody = document.querySelector('#dv-items-tabla tbody');
        tbody.textContent = '';
        itemsVenta.forEach(function (i) {
            var devuelto = devueltoPorItem[i.id] || 0;
            var tr = fila([String(i.id), nombreProducto(i.product_id), String(i.lot_id),
                String(i.cantidad), String(devuelto), '', '']);
            var input = document.createElement('input');
            input.type = 'number';
            input.min = '0';
            input.step = '1';
            input.value = '0';
            input.className = 'input-mini';
            input.dataset.itemId = String(i.id);
            input.dataset.max = String(i.cantidad - devuelto);
            tr.children[5].appendChild(input);
            var sel = document.createElement('select');
            ['vendible', 'no_vendible'].forEach(function (c) {
                var o = document.createElement('option');
                o.value = c;
                o.textContent = c;
                sel.appendChild(o);
            });
            sel.value = 'vendible';
            sel.dataset.itemId = String(i.id);
            tr.children[6].appendChild(sel);
            tbody.appendChild(tr);
        });
    }

    function renderDevoluciones(data) {
        var tbody = document.querySelector('#dv-tabla tbody');
        tbody.textContent = '';
        data.forEach(function (d) {
            var tr = fila([String(d.id), String(d.order_id), d.motivo || '—',
                fmtFecha(d.created_at), '']);
            tr.lastChild.appendChild(boton('Ver', function () { verDetalle(d.id); }));
            tbody.appendChild(tr);
        });
    }

    async function verDetalle(id) {
        limpiarError();
        try {
            var body = await api('/api/v1/sales/returns/' + id);
            var d = body.data;
            var tbody = document.querySelector('#dv-detalle-tabla tbody');
            tbody.textContent = '';
            (d.items || []).forEach(function (i) {
                tbody.appendChild(fila([nombreProducto(i.product_id), String(i.lot_id),
                    String(i.cantidad), i.condicion]));
            });
            document.getElementById('dv-detalle-panel').hidden = false;
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    }

    async function cargarListado() {
        var rs = await Promise.all([
            api('/api/v1/catalog/products?limit=100'),
            api('/api/v1/sales/returns?limit=100'),
        ]);
        products = rs[0].data || [];
        renderDevoluciones(rs[1].data || []);
    }

    document.getElementById('dv-cargar').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        var id = parseInt(document.getElementById('dv-order-id').value, 10);
        if (!id) {
            mostrarError('Ingrese el ID de la venta.');
            return;
        }
        try {
            await cargarVenta(id);
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    document.getElementById('dv-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        if (!ordenSel) {
            mostrarError('Cargue primero la venta.');
            return;
        }
        var motivo = document.getElementById('dv-motivo').value.trim();
        if (motivo === '') {
            mostrarError('Indique el motivo de la devolución.');
            return;
        }
        var items = [];
        var excedido = false;
        var tbody = document.querySelector('#dv-items-tabla tbody');
        Array.prototype.forEach.call(tbody.children, function (tr) {
            var input = tr.children[5].children[0];
            var sel = tr.children[6].children[0];
            var cantidad = parseInt(input.value, 10) || 0;
            if (cantidad > Number(input.dataset.max)) { excedido = true; }
            if (cantidad > 0) {
                items.push({
                    order_item_id: parseInt(input.dataset.itemId, 10),
                    cantidad: cantidad,
                    condicion: sel.value,
                });
            }
        });
        if (excedido) {
            mostrarError('La cantidad a devolver no puede superar lo pendiente del ítem.');
            return;
        }
        if (items.length === 0) {
            mostrarError('Indique al menos un ítem a devolver.');
            return;
        }
        try {
            await api('/api/v1/sales/returns', 'POST', {
                order_id: ordenSel,
                motivo: motivo,
                items: items,
            });
            document.getElementById('dv-motivo').value = '';
            await cargarListado();
            await cargarVenta(ordenSel);
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    cargarListado().catch(function (err) {
        if (err.message !== 'sesion') { mostrarError(err.message); }
    });
})();
