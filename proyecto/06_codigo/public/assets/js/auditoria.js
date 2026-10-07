/**
 * CP-FRONT-10: auditoria de operaciones, accesos PII y eventos outbox
 * (Fetch API, vanilla JS). Lecturas requieren audit.read; el ciclo del outbox
 * (procesar/fallar/reintentar) exige audit.manage.
 */
(function () {
    'use strict';

    var token = localStorage.getItem('sf_token');
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
        if (r.status === 403) {
            throw new Error('No tiene permisos para consultar la auditoría (audit.read / audit.manage).');
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

    function fmtFecha(iso) {
        var d = new Date(iso);
        return isNaN(d) ? '' : d.toLocaleString('es', { dateStyle: 'short', timeStyle: 'short' });
    }

    function renderOperaciones(data) {
        var tbody = document.querySelector('#aud-tabla tbody');
        tbody.textContent = '';
        data.forEach(function (o) {
            var tr = fila([String(o.id), fmtFecha(o.created_at), String(o.usuario_id), o.accion,
                o.entidad, String(o.entidad_id), o.motivo || '—', '']);
            tr.lastChild.appendChild(boton('Ver', function () { verOperacion(o.id); }));
            tbody.appendChild(tr);
        });
    }

    async function verOperacion(id) {
        limpiarError();
        try {
            var body = await api('/api/v1/audit/operations/' + id);
            var d = body.data;
            document.getElementById('aud-detalle-info').textContent =
                'Operación ' + d.id + ' · ' + d.accion + ' sobre ' + d.entidad + ' #' + d.entidad_id;
            document.getElementById('aud-detalle-json').textContent = JSON.stringify({
                valores_antes: d.valores_antes,
                valores_despues: d.valores_despues,
            }, null, 2);
            document.getElementById('aud-detalle').hidden = false;
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    }

    function renderPii(data) {
        var tbody = document.querySelector('#pii-tabla tbody');
        tbody.textContent = '';
        data.forEach(function (p) {
            tbody.appendChild(fila([String(p.id), fmtFecha(p.created_at), String(p.usuario_id),
                p.accion, String(p.paciente_id), String(p.prescription_id), p.motivo || '—']));
        });
    }

    function renderEventos(data) {
        var tbody = document.querySelector('#evt-tabla tbody');
        tbody.textContent = '';
        data.forEach(function (e) {
            var tr = fila([String(e.id), e.tipo_evento,
                e.agregado_tipo + ' #' + e.agregado_id, e.estado, String(e.intentos),
                fmtFecha(e.created_at), '']);
            var td = tr.lastChild;
            td.appendChild(boton('Ver', function () { verEvento(e.id); }));
            if (e.estado === 'pendiente') {
                td.appendChild(boton('Procesar', function () { transicionar(e.id, 'procesar'); }));
                td.appendChild(boton('Fallar', function () { transicionar(e.id, 'fallar'); }));
            } else if (e.estado === 'fallido') {
                td.appendChild(boton('Reintentar', function () { transicionar(e.id, 'reintentar'); }));
            }
            tbody.appendChild(tr);
        });
    }

    async function verEvento(id) {
        limpiarError();
        try {
            var body = await api('/api/v1/audit/events/' + id);
            document.getElementById('evt-detalle-json').textContent = JSON.stringify(body.data, null, 2);
            document.getElementById('evt-detalle').hidden = false;
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    }

    async function transicionar(id, accion) {
        limpiarError();
        try {
            await api('/api/v1/audit/events/' + id + '/' + accion, 'POST');
            await cargarEventos();
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    }

    function query(base, campos) {
        var ruta = base + '?limit=100';
        campos.forEach(function (c) {
            var v = document.getElementById(c.id).value;
            if (v !== '') { ruta += '&' + c.param + '=' + encodeURIComponent(v); }
        });
        return ruta;
    }

    async function cargarOperaciones() {
        var body = await api(query('/api/v1/audit/operations', [
            { id: 'aud-accion', param: 'accion' },
            { id: 'aud-entidad', param: 'entidad' },
            { id: 'aud-desde', param: 'desde' },
            { id: 'aud-hasta', param: 'hasta' },
        ]));
        renderOperaciones(body.data || []);
    }

    async function cargarPii() {
        var body = await api(query('/api/v1/audit/pii', [
            { id: 'pii-accion', param: 'accion' },
        ]));
        renderPii(body.data || []);
    }

    async function cargarEventos() {
        var body = await api(query('/api/v1/audit/events', [
            { id: 'evt-estado', param: 'estado' },
        ]));
        renderEventos(body.data || []);
    }

    document.getElementById('aud-buscar').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        try {
            await cargarOperaciones();
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    document.getElementById('pii-buscar').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        try {
            await cargarPii();
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    document.getElementById('evt-buscar').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        try {
            await cargarEventos();
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    (async function init() {
        try {
            await Promise.all([cargarOperaciones(), cargarPii(), cargarEventos()]);
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    })();
})();
