/**
 * CP-FRONT-03: gestión de usuarios y roles (Fetch API, vanilla JS).
 * Requiere permiso auth.users.manage (la API responde 403 si no se tiene).
 * POST /auth/users recibe roles por NOMBRE; PUT /auth/users/{id}/roles por ID.
 */
(function () {
    'use strict';

    var token = localStorage.getItem('sf_token');
    var roles = [];
    var editandoUserId = null;

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
            throw new Error('No tiene permisos para gestionar usuarios (auth.users.manage).');
        }
        if (!r.ok || !body || body.success !== true) {
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

    function renderRoles() {
        var tbody = document.querySelector('#r-tabla tbody');
        tbody.textContent = '';
        roles.forEach(function (r) {
            tbody.appendChild(fila([String(r.id), r.nombre, r.descripcion || '', (r.permisos || []).join(', ')]));
        });
        ['u-roles', 'u-asignar-roles'].forEach(function (id) {
            var sel = document.getElementById(id);
            sel.textContent = '';
            roles.forEach(function (r) {
                var o = document.createElement('option');
                o.value = String(r.id);
                o.textContent = r.nombre;
                sel.appendChild(o);
            });
        });
    }

    function renderUsuarios(data) {
        var tbody = document.querySelector('#u-tabla tbody');
        tbody.textContent = '';
        data.forEach(function (u) {
            var tr = fila([String(u.id), u.usuario, u.estado, (u.roles || []).join(', '), '']);
            var td = tr.lastChild;
            td.appendChild(boton('Asignar roles', function () { abrirAsignar(u); }));
            tbody.appendChild(tr);
        });
    }

    function abrirAsignar(u) {
        editandoUserId = u.id;
        document.getElementById('u-asignar-titulo').textContent =
            'Asignar roles a ' + u.usuario + ' (id ' + u.id + ')';
        var sel = document.getElementById('u-asignar-roles');
        Array.prototype.forEach.call(sel.options, function (o) {
            o.selected = (u.roles || []).indexOf(o.textContent) !== -1;
        });
        document.getElementById('u-asignar').hidden = false;
    }

    async function cargar() {
        var r = await api('/api/v1/auth/roles');
        roles = r.data || [];
        renderRoles();
        var u = await api('/api/v1/auth/users');
        renderUsuarios(u.data || []);
    }

    document.getElementById('u-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        var sel = document.getElementById('u-roles');
        var nombres = Array.prototype.filter.call(sel.options, function (o) { return o.selected; })
            .map(function (o) { return o.textContent; });
        try {
            await api('/api/v1/auth/users', 'POST', {
                usuario: document.getElementById('u-usuario').value.trim(),
                password: document.getElementById('u-password').value,
                roles: nombres,
            });
            e.target.reset();
            await cargar();
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    document.getElementById('u-asignar').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        var sel = document.getElementById('u-asignar-roles');
        var ids = Array.prototype.filter.call(sel.options, function (o) { return o.selected; })
            .map(function (o) { return parseInt(o.value, 10); });
        try {
            await api('/api/v1/auth/users/' + editandoUserId + '/roles', 'PUT', { roles: ids });
            document.getElementById('u-asignar').hidden = true;
            editandoUserId = null;
            await cargar();
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    document.getElementById('u-asignar-cancelar').addEventListener('click', function () {
        document.getElementById('u-asignar').hidden = true;
        editandoUserId = null;
    });

    document.getElementById('r-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        var permisos = document.getElementById('r-permisos').value.split(',')
            .map(function (s) { return s.trim(); })
            .filter(function (s) { return s !== ''; });
        var desc = document.getElementById('r-descripcion').value.trim();
        var cuerpo = {
            nombre: document.getElementById('r-nombre').value.trim(),
            permisos: permisos,
        };
        if (desc !== '') { cuerpo.descripcion = desc; }
        try {
            await api('/api/v1/auth/roles', 'POST', cuerpo);
            e.target.reset();
            await cargar();
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    cargar().catch(function (err) {
        if (err.message !== 'sesion') { mostrarError(err.message); }
    });
})();
