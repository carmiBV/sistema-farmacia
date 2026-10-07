/**
 * CP-FRONT-07: alertas de inventario e incidentes de consumo (vanilla JS).
 * Alertas: evaluar (genera candidatas) y resolver. Incidentes: reportar
 * (store/lote/consumo declarado) y ajustar (doble autorizacion: el backend
 * exige un usuario distinto al que abrio el incidente) o descartar.
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

    function renderAlertas(data) {
        var tbody = document.querySelector('#al-tabla tbody');
        tbody.textContent = '';
        data.forEach(function (a) {
            var tr = fila([String(a.id), a.tipo, a.product_nombre, String(a.store_id),
                fmtFecha(a.fecha_generada), a.estado, '']);
            if (a.estado === 'abierta') {
                tr.lastChild.appendChild(boton('Resolver', async function () {
                    limpiarError();
                    try {
                        await api('/api/v1/inventory/alerts/' + a.id + '/resolver', 'POST');
                        await cargarAlertas();
                    } catch (err) {
                        if (err.message !== 'sesion') { mostrarError(err.message); }
                    }
                }));
            }
            tbody.appendChild(tr);
        });
    }

    function renderIncidentes(data) {
        var tbody = document.querySelector('#inc-tabla tbody');
        tbody.textContent = '';
        data.forEach(function (i) {
            var tr = fila([String(i.id), i.store_nombre, i.numero_lote, String(i.stock_registrado),
                String(i.consumo_declarado), i.estado, i.usuario, '']);
            var td = tr.lastChild;
            if (i.estado === 'abierto') {
                td.appendChild(boton('Ajustar (2ª autorización)', async function () {
                    limpiarError();
                    try {
                        await api('/api/v1/inventory/incidents/' + i.id + '/ajustar', 'POST');
                        await cargarIncidentes();
                    } catch (err) {
                        if (err.message !== 'sesion') { mostrarError(err.message); }
                    }
                }));
                td.appendChild(boton('Descartar', async function () {
                    limpiarError();
                    try {
                        await api('/api/v1/inventory/incidents/' + i.id + '/descartar', 'POST');
                        await cargarIncidentes();
                    } catch (err) {
                        if (err.message !== 'sesion') { mostrarError(err.message); }
                    }
                }));
            }
            tbody.appendChild(tr);
        });
    }

    async function cargarAlertas() {
        var ruta = '/api/v1/inventory/alerts?limit=100';
        var tipo = document.getElementById('al-tipo').value;
        var estado = document.getElementById('al-estado').value;
        if (tipo) { ruta += '&tipo=' + encodeURIComponent(tipo); }
        if (estado) { ruta += '&estado=' + encodeURIComponent(estado); }
        var body = await api(ruta);
        renderAlertas(body.data || []);
    }

    async function cargarIncidentes() {
        var body = await api('/api/v1/inventory/incidents?limit=100');
        renderIncidentes(body.data || []);
    }

    async function cargarSelects() {
        var rs = await Promise.all([
            api('/api/v1/ops/stores?limit=100'),
            api('/api/v1/inventory/batches?limit=100'),
        ]);
        var selStore = document.getElementById('inc-store');
        selStore.textContent = '';
        (rs[0].data || []).forEach(function (s) {
            var o = document.createElement('option');
            o.value = String(s.id);
            o.textContent = s.nombre;
            selStore.appendChild(o);
        });
        var selLote = document.getElementById('inc-lote');
        selLote.textContent = '';
        (rs[1].data || []).forEach(function (l) {
            var o = document.createElement('option');
            o.value = String(l.id);
            o.textContent = l.numero_lote + ' — ' + l.product_nombre;
            selLote.appendChild(o);
        });
    }

    document.getElementById('al-buscar').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        try {
            await cargarAlertas();
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    document.getElementById('al-evaluar').addEventListener('click', async function () {
        limpiarError();
        try {
            await api('/api/v1/inventory/alerts/evaluar', 'POST');
            await cargarAlertas();
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    document.getElementById('inc-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        var consumo = parseInt(document.getElementById('inc-consumo').value, 10);
        if (!consumo || consumo < 1) {
            mostrarError('El consumo declarado debe ser >= 1.');
            return;
        }
        try {
            await api('/api/v1/inventory/incidents', 'POST', {
                store_id: parseInt(document.getElementById('inc-store').value, 10),
                lot_id: parseInt(document.getElementById('inc-lote').value, 10),
                consumo_declarado: consumo,
            });
            document.getElementById('inc-consumo').value = '';
            await cargarIncidentes();
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    (async function init() {
        try {
            await cargarSelects();
            await Promise.all([cargarAlertas(), cargarIncidentes()]);
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    })();
})();
