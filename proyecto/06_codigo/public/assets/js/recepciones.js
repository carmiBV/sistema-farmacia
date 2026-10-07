/**
 * CP-FRONT-06: recepcion de mercancia (Fetch API, vanilla JS).
 * POST /purchases/orders/{id}/receptions con items {product_id, numero_lote,
 * fecha_vencimiento, cantidad}; confirmar (store_id) crea lotes en cuarentena;
 * rechazar cierra la recepcion. Verificacion contra la orden: solo se aceptan
 * los productos de la orden y se muestra lo pendiente.
 */
(function () {
    'use strict';

    var token = localStorage.getItem('sf_token');
    var products = [];
    var stores = [];
    var items = [];
    var ordenSel = null;
    var recepcionSel = null;
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

    function nombreProducto(id) {
        var p = products.filter(function (x) { return String(x.id) === String(id); })[0];
        return p ? p.nombre : ('producto ' + id);
    }

    function renderItems() {
        var ul = document.getElementById('rec-items-lista');
        ul.textContent = '';
        items.forEach(function (it, idx) {
            var li = document.createElement('li');
            li.textContent = nombreProducto(it.product_id) + ' · lote ' + it.numero_lote +
                ' · vence ' + it.fecha_vencimiento + ' · cant. ' + it.cantidad + ' ';
            li.appendChild(boton('Quitar', function () {
                items.splice(idx, 1);
                renderItems();
            }));
            ul.appendChild(li);
        });
    }

    /** Verificacion contra orden: productos, pedidas vs recibidas vs pendientes. */
    function renderVerificacion(d) {
        var tbody = document.querySelector('#rec-orden-tabla tbody');
        tbody.textContent = '';
        var sel = document.getElementById('rec-item-producto');
        sel.textContent = '';
        (d.items || []).forEach(function (i) {
            var pendiente = Number(i.cantidad_pedida) - Number(i.cantidad_recibida || 0);
            tbody.appendChild(fila([nombreProducto(i.product_id), String(i.cantidad_pedida),
                String(i.cantidad_recibida || 0), String(pendiente)]));
            if (pendiente > 0) {
                var o = document.createElement('option');
                o.value = String(i.product_id);
                o.textContent = nombreProducto(i.product_id);
                sel.appendChild(o);
            }
        });
        document.getElementById('rec-verificacion').hidden = false;
    }

    function render(data) {
        var tbody = document.querySelector('#rec-tabla tbody');
        tbody.textContent = '';
        data.forEach(function (r) {
            var tr = fila([String(r.id), String(r.order_id), r.estado, r.idempotency_key || '—', '']);
            tr.lastChild.appendChild(boton('Ver', function () { verDetalle(r.id); }));
            tbody.appendChild(tr);
        });
    }

    async function verDetalle(id) {
        limpiarError();
        try {
            var body = await api('/api/v1/purchases/receptions/' + id);
            var d = body.data;
            recepcionSel = d.id;
            document.getElementById('rec-detalle-info').textContent =
                'Recepción ' + d.id + ' · orden ' + d.order_id + ' · estado: ' + d.estado;
            var tbody = document.querySelector('#rec-detalle-tabla tbody');
            tbody.textContent = '';
            (d.items || []).forEach(function (i) {
                tbody.appendChild(fila([nombreProducto(i.product_id), i.numero_lote || '—',
                    i.fecha_vencimiento || '—', String(i.cantidad), i.lot_id ? String(i.lot_id) : '—']));
            });
            var activa = d.estado === 'recibida';
            document.getElementById('rec-confirmar').disabled = !activa;
            document.getElementById('rec-rechazar').disabled = !activa;
            document.getElementById('rec-detalle-panel').hidden = false;
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    }

    async function cargar() {
        var rs = await Promise.all([
            api('/api/v1/catalog/products?limit=100'),
            api('/api/v1/ops/stores?limit=100'),
            api('/api/v1/purchases/orders?estado=emitida&limit=100'),
            api('/api/v1/purchases/receptions?limit=100'),
        ]);
        products = rs[0].data || [];
        stores = rs[1].data || [];
        var selOrd = document.getElementById('rec-orden');
        selOrd.textContent = '';
        (rs[2].data || []).forEach(function (o) {
            var opt = document.createElement('option');
            opt.value = String(o.id);
            opt.textContent = o.numero + ' (id ' + o.id + ')';
            selOrd.appendChild(opt);
        });
        var selStore = document.getElementById('rec-confirmar-store');
        selStore.textContent = '';
        stores.forEach(function (s) {
            var opt = document.createElement('option');
            opt.value = String(s.id);
            opt.textContent = s.nombre;
            selStore.appendChild(opt);
        });
        render(rs[3].data || []);
    }

    async function alElegirOrden() {
        limpiarError();
        items = [];
        renderItems();
        var id = parseInt(document.getElementById('rec-orden').value, 10);
        if (!id) {
            document.getElementById('rec-verificacion').hidden = true;
            return;
        }
        try {
            var body = await api('/api/v1/purchases/orders/' + id);
            ordenSel = body.data;
            renderVerificacion(ordenSel);
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    }

    document.getElementById('rec-orden').addEventListener('change', alElegirOrden);

    document.getElementById('rec-item-agregar').addEventListener('click', function () {
        limpiarError();
        var producto = parseInt(document.getElementById('rec-item-producto').value, 10);
        var lote = document.getElementById('rec-item-lote').value.trim();
        var venc = document.getElementById('rec-item-vencimiento').value;
        var cantidad = parseInt(document.getElementById('rec-item-cantidad').value, 10);
        if (!producto || lote === '' || venc === '' || !cantidad || cantidad < 1) {
            mostrarError('Complete producto, lote, vencimiento y cantidad (>= 1) del ítem.');
            return;
        }
        items.push({ product_id: producto, numero_lote: lote, fecha_vencimiento: venc, cantidad: cantidad });
        document.getElementById('rec-item-lote').value = '';
        document.getElementById('rec-item-vencimiento').value = '';
        document.getElementById('rec-item-cantidad').value = '';
        renderItems();
    });

    document.getElementById('rec-guardar').addEventListener('click', async function () {
        limpiarError();
        var ordenId = parseInt(document.getElementById('rec-orden').value, 10);
        if (!ordenId) {
            mostrarError('Seleccione una orden emitida.');
            return;
        }
        if (items.length === 0) {
            mostrarError('La recepción necesita al menos un ítem.');
            return;
        }
        var cuerpo = { items: items };
        var idem = document.getElementById('rec-idempotency').value.trim();
        if (idem !== '') { cuerpo.idempotency_key = idem; }
        try {
            await api('/api/v1/purchases/orders/' + ordenId + '/receptions', 'POST', cuerpo);
            items = [];
            renderItems();
            await cargar();
            await alElegirOrden();
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    document.getElementById('rec-confirmar').addEventListener('click', async function () {
        limpiarError();
        if (!recepcionSel) { return; }
        try {
            await api('/api/v1/purchases/receptions/' + recepcionSel + '/confirmar', 'POST', {
                store_id: parseInt(document.getElementById('rec-confirmar-store').value, 10),
            });
            await cargar();
            await verDetalle(recepcionSel);
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    document.getElementById('rec-rechazar').addEventListener('click', async function () {
        limpiarError();
        if (!recepcionSel) { return; }
        try {
            await api('/api/v1/purchases/receptions/' + recepcionSel + '/rechazar', 'POST');
            await cargar();
            await verDetalle(recepcionSel);
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    cargar().catch(function (err) {
        if (err.message !== 'sesion') { mostrarError(err.message); }
    });
})();
