/**
 * CP-FRONT-04: catalogo de productos (Fetch API, vanilla JS).
 * PUT = reemplazo completo; categorias se sincronizan por el subrecurso
 * PUT /products/{id}/categories {category_ids}.
 */
(function () {
    'use strict';

    var token = localStorage.getItem('sf_token');
    var categorias = [];
    var productos = [];
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

    function render() {
        var tbody = document.querySelector('#prod-tabla tbody');
        tbody.textContent = '';
        productos.forEach(function (p) {
            var tr = fila([String(p.id), p.sku, p.nombre, p.principio_activo || '', p.presentacion || '',
                p.condicion_venta, p.estado, '']);
            var td = tr.lastChild;
            td.appendChild(boton('Editar', function () { editar(p); }));
            if (p.estado === 'activo') {
                td.appendChild(boton('Desactivar', async function () {
                    limpiarError();
                    try {
                        await api('/api/v1/catalog/products/' + p.id, 'DELETE');
                        await cargar();
                    } catch (err) {
                        if (err.message !== 'sesion') { mostrarError(err.message); }
                    }
                }));
            }
            tbody.appendChild(tr);
        });
    }

    function editar(p) {
        editando = p.id;
        document.getElementById('prod-sku').value = p.sku;
        document.getElementById('prod-nombre').value = p.nombre;
        document.getElementById('prod-principio').value = p.principio_activo || '';
        document.getElementById('prod-presentacion').value = p.presentacion || '';
        document.getElementById('prod-concentracion').value = p.concentracion || '';
        document.getElementById('prod-condicion').value = p.condicion_venta;
        document.getElementById('prod-guardar').textContent = 'Guardar cambios';
        document.getElementById('prod-cancelar').hidden = false;
        api('/api/v1/catalog/products/' + p.id + '/categories').then(function (body) {
            var ids = (body.data.categories || []).map(function (c) { return String(c.id); });
            Array.prototype.forEach.call(document.getElementById('prod-categorias').options, function (o) {
                o.selected = ids.indexOf(o.value) !== -1;
            });
        }).catch(function () { /* sin categorias precargadas */ });
    }

    function renderCategorias() {
        var sel = document.getElementById('prod-categorias');
        sel.textContent = '';
        categorias.forEach(function (c) {
            var o = document.createElement('option');
            o.value = String(c.id);
            o.textContent = c.nombre;
            sel.appendChild(o);
        });
    }

    async function cargar(q) {
        var rutaCat = '/api/v1/catalog/categories?limit=100';
        var rutaProd = '/api/v1/catalog/products?limit=100';
        if (q) { rutaProd += '&q=' + encodeURIComponent(q); }
        var rs = await Promise.all([api(rutaCat), api(rutaProd)]);
        categorias = rs[0].data || [];
        renderCategorias();
        productos = rs[1].data || [];
        render();
    }

    function categoryIds() {
        return Array.prototype.filter
            .call(document.getElementById('prod-categorias').options, function (o) { return o.selected; })
            .map(function (o) { return parseInt(o.value, 10); });
    }

    document.getElementById('prod-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        var cuerpo = {
            sku: document.getElementById('prod-sku').value.trim(),
            nombre: document.getElementById('prod-nombre').value.trim(),
            principio_activo: document.getElementById('prod-principio').value.trim(),
            presentacion: document.getElementById('prod-presentacion').value.trim(),
            concentracion: document.getElementById('prod-concentracion').value.trim(),
            condicion_venta: document.getElementById('prod-condicion').value,
        };
        try {
            var id = editando;
            if (id) {
                await api('/api/v1/catalog/products/' + id, 'PUT', cuerpo);
            } else {
                var r = await api('/api/v1/catalog/products', 'POST', cuerpo);
                id = r.data.id;
            }
            await api('/api/v1/catalog/products/' + id + '/categories', 'PUT', { category_ids: categoryIds() });
            cancelar();
            await cargar(document.getElementById('prod-q').value.trim());
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    document.getElementById('prod-buscar').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        try {
            await cargar(document.getElementById('prod-q').value.trim());
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    function cancelar() {
        editando = null;
        document.getElementById('prod-form').reset();
        document.getElementById('prod-guardar').textContent = 'Crear producto';
        document.getElementById('prod-cancelar').hidden = true;
    }

    document.getElementById('prod-cancelar').addEventListener('click', function () {
        limpiarError();
        cancelar();
    });

    cargar().catch(function (err) {
        if (err.message !== 'sesion') { mostrarError(err.message); }
    });
})();
