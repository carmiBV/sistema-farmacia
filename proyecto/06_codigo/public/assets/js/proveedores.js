/**
 * CP-FRONT-05: directorio de proveedores (Fetch API, vanilla JS).
 * DELETE = borrado logico; identificacion unica -> 409 DUPLICATE_IDENTIFICATION.
 */
(function () {
    'use strict';

    var token = localStorage.getItem('sf_token');
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

    function render(data) {
        var tbody = document.querySelector('#prov-tabla tbody');
        tbody.textContent = '';
        data.forEach(function (p) {
            var tr = fila([String(p.id), p.identificacion, p.nombre, p.contacto || '', p.estado, '']);
            var td = tr.lastChild;
            td.appendChild(boton('Editar', function () {
                editando = p.id;
                document.getElementById('prov-identificacion').value = p.identificacion;
                document.getElementById('prov-nombre').value = p.nombre;
                document.getElementById('prov-contacto').value = p.contacto || '';
                document.getElementById('prov-guardar').textContent = 'Guardar cambios';
                document.getElementById('prov-cancelar').hidden = false;
            }));
            if (p.estado === 'activo') {
                td.appendChild(boton('Desactivar', async function () {
                    limpiarError();
                    try {
                        await api('/api/v1/catalog/suppliers/' + p.id, 'DELETE');
                        await cargar(document.getElementById('prov-q').value.trim());
                    } catch (err) {
                        if (err.message !== 'sesion') { mostrarError(err.message); }
                    }
                }));
            }
            tbody.appendChild(tr);
        });
    }

    async function cargar(q) {
        var ruta = '/api/v1/catalog/suppliers?limit=100';
        if (q) { ruta += '&q=' + encodeURIComponent(q); }
        var body = await api(ruta);
        render(body.data || []);
    }

    document.getElementById('prov-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        var cuerpo = {
            identificacion: document.getElementById('prov-identificacion').value.trim(),
            nombre: document.getElementById('prov-nombre').value.trim(),
        };
        var contacto = document.getElementById('prov-contacto').value.trim();
        if (contacto !== '') { cuerpo.contacto = contacto; }
        try {
            if (editando) {
                await api('/api/v1/catalog/suppliers/' + editando, 'PUT', cuerpo);
            } else {
                await api('/api/v1/catalog/suppliers', 'POST', cuerpo);
            }
            cancelar();
            await cargar(document.getElementById('prov-q').value.trim());
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    document.getElementById('prov-buscar').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        try {
            await cargar(document.getElementById('prov-q').value.trim());
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    function cancelar() {
        editando = null;
        document.getElementById('prov-form').reset();
        document.getElementById('prov-guardar').textContent = 'Crear proveedor';
        document.getElementById('prov-cancelar').hidden = true;
    }

    document.getElementById('prov-cancelar').addEventListener('click', function () {
        limpiarError();
        cancelar();
    });

    cargar().catch(function (err) {
        if (err.message !== 'sesion') { mostrarError(err.message); }
    });
})();
