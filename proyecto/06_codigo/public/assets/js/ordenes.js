/**
 * CP-FRONT-06: ordenes de compra (Fetch API, vanilla JS).
 * Flujo: borrador -> emitir -> recibida/cancelada. PUT solo en borrador
 * (reemplazo completo de items); DELETE = cancelacion logica del borrador.
 */
(function () {
    'use strict';

    var token = localStorage.getItem('sf_token');
    var suppliers = [];
    var products = [];
    var items = [];
    var editando = null;
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
    function nombreProveedor(id) {
        var s = suppliers.filter(function (x) { return String(x.id) === String(id); })[0];
        return s ? s.nombre : ('proveedor ' + id);
    }

    function renderItems() {
        var ul = document.getElementById('ord-items-lista');
        ul.textContent = '';
        items.forEach(function (it, idx) {
            var li = document.createElement('li');
            li.textContent = nombreProducto(it.product_id) + ' — cantidad pedida: ' + it.cantidad_pedida + ' ';
            li.appendChild(boton('Quitar', function () {
                items.splice(idx, 1);
                renderItems();
            }));
            ul.appendChild(li);
        });
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

    function render(data) {
        var tbody = document.querySelector('#ord-tabla tbody');
        tbody.textContent = '';
        data.forEach(function (o) {
            var tr = fila([String(o.id), o.numero, nombreProveedor(o.supplier_id), o.estado, '']);
            var td = tr.lastChild;
            td.appendChild(boton('Ver', function () { verDetalle(o.id); }));
            if (o.estado === 'borrador') {
                td.appendChild(boton('Editar', function () { editar(o); }));
                td.appendChild(boton('Emitir', async function () {
                    limpiarError();
                    try {
                        await api('/api/v1/purchases/orders/' + o.id + '/emitir', 'POST');
                        await cargar();
                    } catch (err) {
                        if (err.message !== 'sesion') { mostrarError(err.message); }
                    }
                }));
                td.appendChild(boton('Cancelar', async function () {
                    limpiarError();
                    try {
                        await api('/api/v1/purchases/orders/' + o.id, 'DELETE');
                        await cargar();
                    } catch (err) {
                        if (err.message !== 'sesion') { mostrarError(err.message); }
                    }
                }));
            }
            tbody.appendChild(tr);
        });
    }

    function editar(o) {
        editando = o.id;
        document.getElementById('ord-numero').value = o.numero || '';
        document.getElementById('ord-supplier').value = String(o.supplier_id);
        api('/api/v1/purchases/orders/' + o.id).then(function (body) {
            items = (body.data.items || []).map(function (i) {
                return { product_id: i.product_id, cantidad_pedida: i.cantidad_pedida };
            });
            renderItems();
        }).catch(function () { items = []; renderItems(); });
        document.getElementById('ord-guardar').textContent = 'Guardar cambios';
        document.getElementById('ord-cancelar').hidden = false;
    }

    async function verDetalle(id) {
        limpiarError();
        try {
            var body = await api('/api/v1/purchases/orders/' + id);
            var d = body.data;
            document.getElementById('ord-detalle-info').textContent =
                'Orden ' + d.numero + ' · proveedor: ' + nombreProveedor(d.supplier_id) + ' · estado: ' + d.estado;
            var tbody = document.querySelector('#ord-detalle-tabla tbody');
            tbody.textContent = '';
            (d.items || []).forEach(function (i) {
                tbody.appendChild(fila([nombreProducto(i.product_id),
                    String(i.cantidad_pedida), String(i.cantidad_recibida || 0)]));
            });
            document.getElementById('ord-detalle-panel').hidden = false;
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    }

    async function cargar() {
        var rs = await Promise.all([
            api('/api/v1/catalog/suppliers?limit=100'),
            api('/api/v1/catalog/products?limit=100'),
            api('/api/v1/purchases/orders?limit=100'),
        ]);
        suppliers = rs[0].data || [];
        products = rs[1].data || [];
        opciones(document.getElementById('ord-supplier'), suppliers);
        opciones(document.getElementById('ord-item-producto'), products);
        render(rs[2].data || []);
    }

    document.getElementById('ord-item-agregar').addEventListener('click', function () {
        limpiarError();
        var cantidad = parseInt(document.getElementById('ord-item-cantidad').value, 10);
        var producto = parseInt(document.getElementById('ord-item-producto').value, 10);
        if (!producto || !cantidad || cantidad < 1) {
            mostrarError('Seleccione un producto y una cantidad válida (>= 1).');
            return;
        }
        items.push({ product_id: producto, cantidad_pedida: cantidad });
        document.getElementById('ord-item-cantidad').value = '';
        renderItems();
    });

    document.getElementById('ord-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        if (items.length === 0) {
            mostrarError('La orden necesita al menos un ítem.');
            return;
        }
        var cuerpo = {
            supplier_id: parseInt(document.getElementById('ord-supplier').value, 10),
            items: items,
        };
        var numero = document.getElementById('ord-numero').value.trim();
        if (numero !== '') { cuerpo.numero = numero; }
        try {
            if (editando) {
                await api('/api/v1/purchases/orders/' + editando, 'PUT', cuerpo);
            } else {
                await api('/api/v1/purchases/orders', 'POST', cuerpo);
            }
            cancelar();
            await cargar();
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    document.getElementById('ord-buscar').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        var ruta = '/api/v1/purchases/orders?limit=100';
        var q = document.getElementById('ord-q').value.trim();
        var estado = document.getElementById('ord-estado').value;
        if (q) { ruta += '&q=' + encodeURIComponent(q); }
        if (estado) { ruta += '&estado=' + encodeURIComponent(estado); }
        try {
            var body = await api(ruta);
            render(body.data || []);
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    function cancelar() {
        editando = null;
        items = [];
        renderItems();
        document.getElementById('ord-form').reset();
        document.getElementById('ord-guardar').textContent = 'Crear orden';
        document.getElementById('ord-cancelar').hidden = true;
    }

    document.getElementById('ord-cancelar').addEventListener('click', function () {
        limpiarError();
        cancelar();
    });

    cargar().catch(function (err) {
        if (err.message !== 'sesion') { mostrarError(err.message); }
    });
})();
