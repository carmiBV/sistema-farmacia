/**
 * CP-FRONT-04: precios y promociones (Fetch API, vanilla JS).
 * Precios: {product_id, store_id?, precio, vigente_desde, vigente_hasta?}.
 * Promos: alcance obligatorio product_id O category_id + descuento_pct.
 * Editar = PUT (cierre de vigencia / ajuste); no hay DELETE en el contrato.
 */
(function () {
    'use strict';

    var token = localStorage.getItem('sf_token');
    var productos = [];
    var categorias = [];
    var stores = [];
    var editPrecio = null;
    var editPromo = null;
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
        var p = productos.filter(function (x) { return String(x.id) === String(id); })[0];
        return p ? p.nombre : ('producto ' + id);
    }
    function nombreCategoria(id) {
        var c = categorias.filter(function (x) { return String(x.id) === String(id); })[0];
        return c ? c.nombre : ('categoría ' + id);
    }
    function nombreStore(id) {
        if (!id) { return 'Global'; }
        var s = stores.filter(function (x) { return String(x.id) === String(id); })[0];
        return s ? s.nombre : ('sucursal ' + id);
    }

    function renderPrecios(data) {
        var tbody = document.querySelector('#pr-tabla tbody');
        tbody.textContent = '';
        data.forEach(function (p) {
            var tr = fila([String(p.id), nombreProducto(p.product_id), String(p.precio),
                p.vigente_desde, p.vigente_hasta || '—', nombreStore(p.store_id), '']);
            tr.lastChild.appendChild(boton('Editar', function () {
                editPrecio = p.id;
                document.getElementById('pr-producto').value = String(p.product_id);
                document.getElementById('pr-precio').value = String(p.precio);
                document.getElementById('pr-desde').value = p.vigente_desde;
                document.getElementById('pr-hasta').value = p.vigente_hasta || '';
                document.getElementById('pr-store').value = p.store_id ? String(p.store_id) : '';
                document.getElementById('pr-guardar').textContent = 'Guardar cambios';
                document.getElementById('pr-cancelar').hidden = false;
            }));
            tbody.appendChild(tr);
        });
    }

    function renderPromos(data) {
        var tbody = document.querySelector('#pm-tabla tbody');
        tbody.textContent = '';
        data.forEach(function (p) {
            var alcance = p.product_id
                ? 'Producto: ' + nombreProducto(p.product_id)
                : 'Categoría: ' + nombreCategoria(p.category_id);
            var tr = fila([String(p.id), alcance, String(p.descuento_pct), p.desde, p.hasta, '']);
            tr.lastChild.appendChild(boton('Editar', function () {
                editPromo = p.id;
                document.getElementById('pm-tipo').value = p.product_id ? 'product_id' : 'category_id';
                llenarAlcance();
                document.getElementById('pm-alcance').value = String(p.product_id || p.category_id);
                document.getElementById('pm-descuento').value = String(p.descuento_pct);
                document.getElementById('pm-desde').value = p.desde;
                document.getElementById('pm-hasta').value = p.hasta;
                document.getElementById('pm-guardar').textContent = 'Guardar cambios';
                document.getElementById('pm-cancelar').hidden = false;
            }));
            tbody.appendChild(tr);
        });
    }

    function opciones(sel, items, textoVacio) {
        sel.textContent = '';
        if (textoVacio) {
            var nada = document.createElement('option');
            nada.value = '';
            nada.textContent = textoVacio;
            sel.appendChild(nada);
        }
        items.forEach(function (it) {
            var o = document.createElement('option');
            o.value = String(it.id);
            o.textContent = it.nombre;
            sel.appendChild(o);
        });
    }

    function llenarAlcance() {
        var tipo = document.getElementById('pm-tipo').value;
        opciones(document.getElementById('pm-alcance'),
            tipo === 'product_id' ? productos : categorias);
    }

    async function cargar() {
        var rs = await Promise.all([
            api('/api/v1/catalog/products?limit=100'),
            api('/api/v1/catalog/categories?limit=100'),
            api('/api/v1/ops/stores?limit=100'),
            api('/api/v1/catalog/prices?limit=100'),
            api('/api/v1/catalog/promotions?limit=100'),
        ]);
        productos = rs[0].data || [];
        categorias = rs[1].data || [];
        stores = rs[2].data || [];
        opciones(document.getElementById('pr-producto'), productos);
        opciones(document.getElementById('pr-store'), stores, 'Global');
        llenarAlcance();
        renderPrecios(rs[3].data || []);
        renderPromos(rs[4].data || []);
    }

    document.getElementById('pm-tipo').addEventListener('change', llenarAlcance);

    document.getElementById('pr-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        var cuerpo = {
            product_id: parseInt(document.getElementById('pr-producto').value, 10),
            precio: parseFloat(document.getElementById('pr-precio').value),
            vigente_desde: document.getElementById('pr-desde').value,
        };
        var hasta = document.getElementById('pr-hasta').value;
        if (hasta !== '') { cuerpo.vigente_hasta = hasta; }
        var storeId = document.getElementById('pr-store').value;
        if (storeId !== '') { cuerpo.store_id = parseInt(storeId, 10); }
        try {
            if (editPrecio) {
                await api('/api/v1/catalog/prices/' + editPrecio, 'PUT', cuerpo);
            } else {
                await api('/api/v1/catalog/prices', 'POST', cuerpo);
            }
            cancelar();
            await cargar();
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    document.getElementById('pm-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        var tipo = document.getElementById('pm-tipo').value;
        var cuerpo = {
            descuento_pct: parseFloat(document.getElementById('pm-descuento').value),
            desde: document.getElementById('pm-desde').value,
            hasta: document.getElementById('pm-hasta').value,
        };
        cuerpo[tipo] = parseInt(document.getElementById('pm-alcance').value, 10);
        try {
            if (editPromo) {
                await api('/api/v1/catalog/promotions/' + editPromo, 'PUT', cuerpo);
            } else {
                await api('/api/v1/catalog/promotions', 'POST', cuerpo);
            }
            cancelar();
            await cargar();
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    function cancelar() {
        editPrecio = null;
        editPromo = null;
        document.getElementById('pr-form').reset();
        document.getElementById('pm-form').reset();
        document.getElementById('pr-guardar').textContent = 'Crear precio';
        document.getElementById('pm-guardar').textContent = 'Crear promoción';
        ['pr-cancelar', 'pm-cancelar'].forEach(function (id) {
            document.getElementById(id).hidden = true;
        });
    }

    ['pr-cancelar', 'pm-cancelar'].forEach(function (id) {
        document.getElementById(id).addEventListener('click', function () {
            limpiarError();
            cancelar();
        });
    });

    cargar().catch(function (err) {
        if (err.message !== 'sesion') { mostrarError(err.message); }
    });
})();
