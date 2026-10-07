/**
 * CP-FRONT-07: lotes con semaforizacion FEFO (Fetch API, vanilla JS).
 * Semafono: rojo = vencido, amarillo = vence en <= 90 dias, verde = vigente.
 * Liberar lote (cuarentena -> liberado) con motivo.
 */
(function () {
    'use strict';

    var DIAS_ALERTA = 90;
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

    /** @returns 'rojo'|'amarillo'|'verde' */
    function semaforo(fecha) {
        var venc = new Date(fecha + 'T00:00:00');
        var hoy = new Date();
        var dias = Math.floor((venc - hoy) / 86400000);
        if (dias < 0) { return 'rojo'; }
        if (dias <= DIAS_ALERTA) { return 'amarillo'; }
        return 'verde';
    }

    function render(data) {
        var tbody = document.querySelector('#lot-tabla tbody');
        tbody.textContent = '';
        data.forEach(function (l) {
            var sem = semaforo(l.fecha_vencimiento);
            var tr = fila([String(l.id), l.product_nombre, l.numero_lote,
                l.fecha_vencimiento, sem, l.estado, '']);
            tr.children[4].className = 'sem-' + sem;
            if (l.estado === 'cuarentena') {
                tr.lastChild.appendChild(boton('Liberar', async function () {
                    limpiarError();
                    try {
                        await api('/api/v1/inventory/batches/' + l.id + '/liberar', 'POST',
                            { motivo: 'Liberación desde interfaz web' });
                        await cargar();
                    } catch (err) {
                        if (err.message !== 'sesion') { mostrarError(err.message); }
                    }
                }));
            }
            tbody.appendChild(tr);
        });
    }

    async function cargar() {
        var ruta = '/api/v1/inventory/batches?limit=100';
        var estado = document.getElementById('lot-estado').value;
        if (estado) { ruta += '&estado=' + encodeURIComponent(estado); }
        var body = await api(ruta);
        render(body.data || []);
    }

    document.getElementById('lot-buscar').addEventListener('submit', async function (e) {
        e.preventDefault();
        limpiarError();
        try {
            await cargar();
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    cargar().catch(function (err) {
        if (err.message !== 'sesion') { mostrarError(err.message); }
    });
})();
