/**
 * CP-FRONT-08: recetas medicas y dispensacion (Fetch API, vanilla JS).
 * POST /rx/prescriptions {prescriber_id, patient_id, fecha, numero_referencia?,
 * items:[{product_id, cantidad_prescrita}]}; dispensar {items:[{rx_item_id,
 * cantidad}]} con CAS por saldo (RX_SALDO_INSUFICIENTE).
 */
(function () {
    'use strict';

    var token = localStorage.getItem('sf_token');
    var products = [];
    var items = [];
    var inputsDisp = [];
    var recetaSel = null;
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

    function opciones(sel, listado) {
        sel.textContent = '';
        listado.forEach(function (it) {
            var o = document.createElement('option');
            o.value = String(it.id);
            o.textContent = it.nombre;
            sel.appendChild(o);
        });
    }

    function renderItems() {
        var ul = document.getElementById('rx-items-lista');
        ul.textContent = '';
        items.forEach(function (it, idx) {
            var li = document.createElement('li');
            li.textContent = nombreProducto(it.product_id) + ' — prescrita: ' + it.cantidad_prescrita + ' ';
            li.appendChild(boton('Quitar', function () {
                items.splice(idx, 1);
                renderItems();
            }));
            ul.appendChild(li);
        });
    }

    function render(data) {
        var tbody = document.querySelector('#rx-tabla tbody');
        tbody.textContent = '';
        data.forEach(function (r) {
            var tr = fila([String(r.id), r.fecha, r.prescriber_nombre, r.patient_nombre,
                r.numero_referencia || '—', '']);
            tr.lastChild.appendChild(boton('Ver', function () { verDetalle(r.id); }));
            tbody.appendChild(tr);
        });
    }

    async function verDetalle(id) {
        limpiarError();
        try {
            var body = await api('/api/v1/rx/prescriptions/' + id);
            var d = body.data;
            recetaSel = d.prescripcion.id;
            document.getElementById('rx-detalle-info').textContent =
                'Receta ' + d.prescripcion.id + ' · ' + d.prescripcion.prescriber_nombre +
                ' → ' + d.prescripcion.patient_nombre + ' · fecha: ' + d.prescripcion.fecha;
            var tbody = document.querySelector('#rx-detalle-tabla tbody');
            tbody.textContent = '';
            inputsDisp = [];
            (d.items || []).forEach(function (i) {
                var tr = fila([i.product_nombre + ' (' + i.sku + ')', String(i.cantidad_prescrita),
                    String(i.cantidad_dispensada), String(i.saldo), '']);
                var input = document.createElement('input');
                input.type = 'number';
                input.min = '0';
                input.step = '1';
                input.value = '0';
                input.className = 'input-mini';
                input.dataset.itemId = String(i.id);
                input.dataset.saldo = String(i.saldo);
                tr.lastChild.appendChild(input);
                inputsDisp.push(input);
                tbody.appendChild(tr);
            });
            document.getElementById('rx-detalle-panel').hidden = false;
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    }

    async function cargar() {
        var rs = await Promise.all([
            api('/api/v1/catalog/prescribers?limit=100'),
            api('/api/v1/catalog/patients?limit=100'),
            api('/api/v1/catalog/products?limit=100'),
            api('/api/v1/rx/prescriptions?limit=100'),
        ]);
        var presc = (rs[0].data || []).filter(function (p) { return p.estado === 'activo'; });
        var pac = (rs[1].data || []).filter(function (p) { return p.estado === 'activo'; });
        products = (rs[2].data || []).filter(function (p) {
            return p.estado === 'activo' && (p.condicion_venta === 'receta' || p.condicion_venta === 'controlado');
        });
        opciones(document.getElementById('rx-prescriptor'), presc);
        opciones(document.getElementById('rx-paciente'), pac);
        opciones(document.getElementById('rx-filtro-paciente'), pac);
        opciones(document.getElementById('rx-item-producto'), products);
        render(rs[3].data || []);
    }

    document.getElementById('rx-item-agregar').addEventListener('click', function () {
        limpiarError();
        var producto = parseInt(document.getElementById('rx-item-producto').value, 10);
        var cantidad = parseInt(document.getElementById('rx-item-cantidad').value, 10);
        if (!producto || !cantidad || cantidad < 1) {
            mostrarError('Seleccione un producto y una cantidad prescrita válida (>= 1).');
            return;
        }
        items.push({ product_id: producto, cantidad_prescrita: cantidad });
        document.getElementById('rx-item-cantidad').value = '';
        renderItems();
    });

    document.getElementById('rx-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        if (items.length === 0) {
            mostrarError('La receta necesita al menos un ítem.');
            return;
        }
        var cuerpo = {
            prescriber_id: parseInt(document.getElementById('rx-prescriptor').value, 10),
            patient_id: parseInt(document.getElementById('rx-paciente').value, 10),
            fecha: document.getElementById('rx-fecha').value,
            items: items,
        };
        var ref = document.getElementById('rx-referencia').value.trim();
        if (ref !== '') { cuerpo.numero_referencia = ref; }
        try {
            await api('/api/v1/rx/prescriptions', 'POST', cuerpo);
            items = [];
            renderItems();
            document.getElementById('rx-form').reset();
            await cargar();
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    document.getElementById('rx-buscar').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        var ruta = '/api/v1/rx/prescriptions?limit=100';
        var pac = document.getElementById('rx-filtro-paciente').value;
        if (pac) { ruta += '&patient_id=' + encodeURIComponent(pac); }
        try {
            var body = await api(ruta);
            render(body.data || []);
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    document.getElementById('rx-dispensar').addEventListener('click', async function () {
        limpiarError();
        if (!recetaSel) { return; }
        var itemsDisp = [];
        var invalido = false;
        inputsDisp.forEach(function (input) {
            var cantidad = parseInt(input.value, 10) || 0;
            var saldo = parseInt(input.dataset.saldo, 10) || 0;
            if (cantidad > saldo) { invalido = true; }
            if (cantidad > 0) {
                itemsDisp.push({ rx_item_id: parseInt(input.dataset.itemId, 10), cantidad: cantidad });
            }
        });
        if (invalido) {
            mostrarError('La cantidad a dispensar no puede superar el saldo del ítem.');
            return;
        }
        if (itemsDisp.length === 0) {
            mostrarError('Indique al menos una cantidad a dispensar.');
            return;
        }
        try {
            await api('/api/v1/rx/prescriptions/' + recetaSel + '/dispensar', 'POST', { items: itemsDisp });
            await verDetalle(recetaSel);
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    cargar().catch(function (err) {
        if (err.message !== 'sesion') { mostrarError(err.message); }
    });
})();
