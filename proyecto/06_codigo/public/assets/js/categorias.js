/**
 * CP-FRONT-04: categorias con jerarquia (Fetch API, vanilla JS).
 * DELETE = borrado logico; PUT envia parent_id (null = raiz).
 */
(function () {
    'use strict';

    var token = localStorage.getItem('sf_token');
    var categorias = [];
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

    function nombrePadre(id) {
        if (!id) { return '—'; }
        var c = categorias.filter(function (x) { return String(x.id) === String(id); })[0];
        return c ? c.nombre : ('id ' + id);
    }

    function render() {
        var tbody = document.querySelector('#cat-tabla tbody');
        tbody.textContent = '';
        categorias.forEach(function (c) {
            var tr = fila([String(c.id), c.nombre, nombrePadre(c.parent_id), '']);
            var td = tr.lastChild;
            td.appendChild(boton('Editar', function () {
                editando = c.id;
                document.getElementById('cat-nombre').value = c.nombre;
                document.getElementById('cat-parent').value = c.parent_id ? String(c.parent_id) : '';
                document.getElementById('cat-guardar').textContent = 'Guardar cambios';
                document.getElementById('cat-cancelar').hidden = false;
            }));
            td.appendChild(boton('Desactivar', async function () {
                limpiarError();
                try {
                    await api('/api/v1/catalog/categories/' + c.id, 'DELETE');
                    await cargar();
                } catch (err) {
                    if (err.message !== 'sesion') { mostrarError(err.message); }
                }
            }));
            tbody.appendChild(tr);
        });
        var sel = document.getElementById('cat-parent');
        var previa = sel.value;
        sel.textContent = '';
        var raiz = document.createElement('option');
        raiz.value = '';
        raiz.textContent = '— Raíz —';
        sel.appendChild(raiz);
        categorias.forEach(function (c) {
            if (editando === c.id) { return; } // no puede ser su propio padre
            var o = document.createElement('option');
            o.value = String(c.id);
            o.textContent = c.nombre;
            sel.appendChild(o);
        });
        sel.value = previa;
    }

    async function cargar(q) {
        var ruta = '/api/v1/catalog/categories?limit=100';
        if (q) { ruta += '&q=' + encodeURIComponent(q); }
        var body = await api(ruta);
        categorias = body.data || [];
        render();
    }

    document.getElementById('cat-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        var parentId = document.getElementById('cat-parent').value;
        var cuerpo = {
            nombre: document.getElementById('cat-nombre').value.trim(),
            parent_id: parentId === '' ? null : parseInt(parentId, 10),
        };
        try {
            if (editando) {
                await api('/api/v1/catalog/categories/' + editando, 'PUT', cuerpo);
            } else {
                await api('/api/v1/catalog/categories', 'POST', cuerpo);
            }
            cancelar();
            await cargar(document.getElementById('cat-q').value.trim());
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    document.getElementById('cat-buscar').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        try {
            await cargar(document.getElementById('cat-q').value.trim());
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    function cancelar() {
        editando = null;
        document.getElementById('cat-form').reset();
        document.getElementById('cat-guardar').textContent = 'Crear categoría';
        document.getElementById('cat-cancelar').hidden = true;
    }

    document.getElementById('cat-cancelar').addEventListener('click', function () {
        limpiarError();
        cancelar();
    });

    cargar().catch(function (err) {
        if (err.message !== 'sesion') { mostrarError(err.message); }
    });
})();
