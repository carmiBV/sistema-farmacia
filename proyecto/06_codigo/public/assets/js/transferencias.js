/**
 * CP-FRONT-07: transferencias entre sucursales (Fetch API, vanilla JS).
 * Ciclo: solicitada -> despachada -> recibida -> cerrada (o rechazada).
 * Despachar es completo (sin body); Recibir envia items con cantidad_recibida
 * igual a la solicitada (recepcion parcial solo via API).
 */
(function () {
    'use strict';

    var token = localStorage.getItem('sf_token');
    var stores = [];
    var items = [];
    var transferSel = null;
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

    function nombreStore(id) {
        var s = stores.filter(function (x) { return String(x.id) === String(id); })[0];
        return s ? s.nombre : ('sucursal ' + id);
    }

    function renderItems() {
        var ul = document.getElementById('tr-items-lista');
        ul.textContent = '';
        items.forEach(function (it, idx) {
            var li = document.createElement('li');
            li.textContent = 'lote ' + it.lot_id + ' · cantidad ' + it.cantidad + ' ';
            li.appendChild(boton('Quitar', function () {
                items.splice(idx, 1);
                renderItems();
            }));
            ul.appendChild(li);
        });
    }

    function render(data) {
        var tbody = document.querySelector('#tr-tabla tbody');
        tbody.textContent = '';
        data.forEach(function (t) {
            var tr = fila([String(t.id), nombreStore(t.store_origen_id), nombreStore(t.store_destino_id),
                t.estado, t.idempotency_key || '—', '']);
            tr.lastChild.appendChild(boton('Ver', function () { verDetalle(t.id); }));
            tbody.appendChild(tr);
        });
    }

    async function verDetalle(id) {
        limpiarError();
        try {
            var body = await api('/api/v1/inventory/transfers/' + id);
            // el servicio responde {transferencia: {...}, items: [...]}
            var t = body.data.transferencia || body.data;
            var itemsDet = body.data.items || [];
            transferSel = { id: t.id, estado: t.estado, items: itemsDet };
            document.getElementById('tr-detalle-info').textContent =
                'Transferencia ' + t.id + ' · ' + nombreStore(t.store_origen_id) + ' → ' +
                nombreStore(t.store_destino_id) + ' · estado: ' + t.estado;
            var tbody = document.querySelector('#tr-detalle-tabla tbody');
            tbody.textContent = '';
            itemsDet.forEach(function (i) {
                tbody.appendChild(fila([String(i.lot_id), String(i.cantidad_despachada),
                    String(i.cantidad_recibida != null ? i.cantidad_recibida : '—')]));
            });
            document.getElementById('tr-despachar').disabled = t.estado !== 'solicitada';
            document.getElementById('tr-recibir').disabled = t.estado !== 'despachada';
            document.getElementById('tr-cerrar').disabled = t.estado !== 'recibida';
            document.getElementById('tr-rechazar').disabled =
                t.estado !== 'solicitada' && t.estado !== 'despachada';
            document.getElementById('tr-detalle-panel').hidden = false;
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    }

    async function accion(accion) {
        limpiarError();
        if (!transferSel) { return; }
        var cuerpo;
        if (accion === 'recibir') {
            // recepcion completa: cantidad_recibida = cantidad_despachada (parcial solo via API)
            cuerpo = {
                items: transferSel.items.map(function (i) {
                    return { lot_id: i.lot_id, cantidad_recibida: Number(i.cantidad_despachada) };
                }),
            };
        }
        try {
            await api('/api/v1/inventory/transfers/' + transferSel.id + '/' + accion,
                'POST', cuerpo);
            await cargar();
            await verDetalle(transferSel.id);
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    }

    async function cargar() {
        var rs = await Promise.all([
            api('/api/v1/ops/stores?limit=100'),
            api('/api/v1/inventory/batches?estado=liberado&limit=100'),
            api('/api/v1/inventory/transfers?limit=100'),
        ]);
        stores = rs[0].data || [];
        ['tr-origen', 'tr-destino'].forEach(function (id) {
            var sel = document.getElementById(id);
            sel.textContent = '';
            stores.forEach(function (s) {
                var o = document.createElement('option');
                o.value = String(s.id);
                o.textContent = s.nombre;
                sel.appendChild(o);
            });
        });
        var selLote = document.getElementById('tr-item-lote');
        selLote.textContent = '';
        (rs[1].data || []).forEach(function (l) {
            var o = document.createElement('option');
            o.value = String(l.id);
            o.textContent = l.numero_lote + ' — ' + l.product_nombre;
            selLote.appendChild(o);
        });
        render(rs[2].data || []);
    }

    document.getElementById('tr-item-agregar').addEventListener('click', function () {
        limpiarError();
        var lot = parseInt(document.getElementById('tr-item-lote').value, 10);
        var cantidad = parseInt(document.getElementById('tr-item-cantidad').value, 10);
        if (!lot || !cantidad || cantidad < 1) {
            mostrarError('Seleccione un lote y una cantidad válida (>= 1).');
            return;
        }
        items.push({ lot_id: lot, cantidad: cantidad });
        document.getElementById('tr-item-cantidad').value = '';
        renderItems();
    });

    document.getElementById('tr-guardar').addEventListener('click', async function () {
        limpiarError();
        var origen = parseInt(document.getElementById('tr-origen').value, 10);
        var destino = parseInt(document.getElementById('tr-destino').value, 10);
        if (!origen || !destino || origen === destino) {
            mostrarError('Origen y destino deben ser sucursales distintas.');
            return;
        }
        if (items.length === 0) {
            mostrarError('La transferencia necesita al menos un ítem.');
            return;
        }
        var cuerpo = {
            store_origen_id: origen,
            store_destino_id: destino,
            items: items,
        };
        var idem = document.getElementById('tr-idempotency').value.trim();
        if (idem !== '') { cuerpo.idempotency_key = idem; }
        try {
            await api('/api/v1/inventory/transfers', 'POST', cuerpo);
            items = [];
            renderItems();
            await cargar();
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    document.getElementById('tr-despachar').addEventListener('click', function () { accion('despachar'); });
    document.getElementById('tr-recibir').addEventListener('click', function () { accion('recibir'); });
    document.getElementById('tr-cerrar').addEventListener('click', function () { accion('cerrar'); });
    document.getElementById('tr-rechazar').addEventListener('click', function () { accion('rechazar'); });

    cargar().catch(function (err) {
        if (err.message !== 'sesion') { mostrarError(err.message); }
    });
})();
