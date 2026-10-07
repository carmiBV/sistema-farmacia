/**
 * CP-FRONT-03: sucursales, cajas y parámetros del sistema (Fetch API, vanilla JS).
 * Escrituras requieren permiso ops.config.manage (la API responde 403 si no).
 * DELETE = borrado logico (estado inactivo).
 */
(function () {
    'use strict';

    var token = localStorage.getItem('sf_token');
    var stores = [];
    var editStore = null;
    var editCaja = null;

    var cajaError = document.getElementById('app-error');

    function mostrarError(mensaje) {
        cajaError.textContent = mensaje;
        cajaError.hidden = false;
    }
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
        if (r.status === 401) {
            sesionCaducada();
            throw new Error('sesion');
        }
        var body = await r.json().catch(function () { return null; });
        if (r.status === 403) {
            throw new Error('No tiene permisos para gestionar configuración (ops.config.manage).');
        }
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

    function renderSucursales() {
        var tbody = document.querySelector('#s-tabla tbody');
        tbody.textContent = '';
        stores.forEach(function (s) {
            var tr = fila([String(s.id), s.codigo, s.nombre, s.estado, '']);
            var td = tr.lastChild;
            td.appendChild(boton('Editar', function () {
                editStore = s.id;
                document.getElementById('s-codigo').value = s.codigo;
                document.getElementById('s-nombre').value = s.nombre;
                document.getElementById('s-guardar').textContent = 'Guardar cambios';
                document.getElementById('s-cancelar').hidden = false;
            }));
            if (s.estado === 'activa') {
                td.appendChild(boton('Desactivar', async function () {
                    limpiarError();
                    try {
                        await api('/api/v1/ops/stores/' + s.id, 'DELETE');
                        await cargar();
                    } catch (err) {
                        if (err.message !== 'sesion') { mostrarError(err.message); }
                    }
                }));
            }
            tbody.appendChild(tr);
        });
    }

    function renderCajas(data) {
        var tbody = document.querySelector('#c-tabla tbody');
        tbody.textContent = '';
        data.forEach(function (c) {
            var tr = fila([String(c.id), nombreStore(c.store_id), c.codigo, c.estado, '']);
            var td = tr.lastChild;
            td.appendChild(boton('Editar', function () {
                editCaja = c.id;
                document.getElementById('c-store').value = String(c.store_id);
                document.getElementById('c-codigo').value = c.codigo;
                document.getElementById('c-guardar').textContent = 'Guardar cambios';
                document.getElementById('c-cancelar').hidden = false;
            }));
            if (c.estado === 'activa') {
                td.appendChild(boton('Desactivar', async function () {
                    limpiarError();
                    try {
                        await api('/api/v1/ops/registers/' + c.id, 'DELETE');
                        await cargar();
                    } catch (err) {
                        if (err.message !== 'sesion') { mostrarError(err.message); }
                    }
                }));
            }
            tbody.appendChild(tr);
        });
    }

    function renderConfig(data) {
        var tbody = document.querySelector('#p-tabla tbody');
        tbody.textContent = '';
        data.forEach(function (p) {
            var tr = fila([p.config_key, p.value_type, p.config_value,
                p.store_id ? nombreStore(p.store_id) : '—', '']);
            tr.lastChild.appendChild(boton('Editar', function () {
                document.getElementById('p-key').value = p.config_key;
                document.getElementById('p-value').value = p.config_value;
                document.getElementById('p-type').value = p.value_type;
                document.getElementById('p-store').value = p.store_id ? String(p.store_id) : '';
                document.getElementById('p-key').readOnly = true;
                document.getElementById('p-guardar').textContent = 'Guardar cambios';
                document.getElementById('p-cancelar').hidden = false;
            }));
            tbody.appendChild(tr);
        });
    }

    function llenarSelectSucursales() {
        ['c-store', 'p-store'].forEach(function (id) {
            var sel = document.getElementById(id);
            var previa = sel.value;
            sel.textContent = '';
            if (id === 'p-store') {
                var nada = document.createElement('option');
                nada.value = '';
                nada.textContent = '—';
                sel.appendChild(nada);
            }
            stores.forEach(function (s) {
                var o = document.createElement('option');
                o.value = String(s.id);
                o.textContent = s.nombre;
                sel.appendChild(o);
            });
            sel.value = previa;
        });
    }

    async function cargar() {
        var rs = await api('/api/v1/ops/stores?limit=100');
        stores = rs.data || [];
        llenarSelectSucursales();
        renderSucursales();
        var rc = await api('/api/v1/ops/registers?limit=100');
        renderCajas(rc.data || []);
        var rp = await api('/api/v1/ops/config?limit=100');
        renderConfig(rp.data || []);
    }

    document.getElementById('s-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        var cuerpo = {
            codigo: document.getElementById('s-codigo').value.trim(),
            nombre: document.getElementById('s-nombre').value.trim(),
        };
        try {
            if (editStore) {
                await api('/api/v1/ops/stores/' + editStore, 'PUT', cuerpo);
            } else {
                await api('/api/v1/ops/stores', 'POST', cuerpo);
            }
            cancelarEdicion();
            await cargar();
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    document.getElementById('c-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        var cuerpo = {
            store_id: parseInt(document.getElementById('c-store').value, 10),
            codigo: document.getElementById('c-codigo').value.trim(),
        };
        try {
            if (editCaja) {
                await api('/api/v1/ops/registers/' + editCaja, 'PUT', cuerpo);
            } else {
                await api('/api/v1/ops/registers', 'POST', cuerpo);
            }
            cancelarEdicion();
            await cargar();
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    document.getElementById('p-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        var cuerpo = {
            config_value: document.getElementById('p-value').value,
            value_type: document.getElementById('p-type').value,
        };
        var storeId = document.getElementById('p-store').value;
        if (storeId !== '') {
            cuerpo.store_id = parseInt(storeId, 10);
        }
        try {
            await api('/api/v1/ops/config/' + encodeURIComponent(document.getElementById('p-key').value.trim()),
                'PUT', cuerpo);
            cancelarEdicion();
            await cargar();
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    function cancelarEdicion() {
        editStore = null;
        editCaja = null;
        ['s-form', 'c-form', 'p-form'].forEach(function (id) {
            document.getElementById(id).reset();
        });
        document.getElementById('s-guardar').textContent = 'Crear sucursal';
        document.getElementById('c-guardar').textContent = 'Crear caja';
        document.getElementById('p-guardar').textContent = 'Guardar parámetro';
        document.getElementById('p-key').readOnly = false;
        ['s-cancelar', 'c-cancelar', 'p-cancelar'].forEach(function (id) {
            document.getElementById(id).hidden = true;
        });
    }

    ['s-cancelar', 'c-cancelar', 'p-cancelar'].forEach(function (id) {
        document.getElementById(id).addEventListener('click', function () {
            limpiarError();
            cancelarEdicion();
        });
    });

    cargar().catch(function (err) {
        if (err.message !== 'sesion') { mostrarError(err.message); }
    });
})();
