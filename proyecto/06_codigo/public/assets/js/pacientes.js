/**
 * CP-FRONT-05: directorio de pacientes y prescriptores (Fetch API, vanilla JS).
 * Un motor local (montarDirectorio) cubre ambos paneles; DELETE = borrado
 * logico (pacientes quedan anonimizados por la API).
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

    /** CRUD generico por prefijo de ids de DOM (pac / presc). */
    function montarDirectorio(cfg) {
        var editando = null;
        var $ = function (suf) { return document.getElementById(cfg.prefijo + '-' + suf); };

        function render(data) {
            var tbody = document.querySelector('#' + cfg.prefijo + '-tabla tbody');
            tbody.textContent = '';
            data.forEach(function (d) {
                var tr = fila(cfg.columnas(d).concat(['']));
                var td = tr.lastChild;
                td.appendChild(boton('Editar', function () {
                    editando = d.id;
                    cfg.campos.forEach(function (c) {
                        $(c.id).value = d[c.clave] || '';
                    });
                    $('guardar').textContent = 'Guardar cambios';
                    $('cancelar').hidden = false;
                }));
                if (d.estado === 'activo') {
                    td.appendChild(boton('Desactivar', async function () {
                        limpiarError();
                        try {
                            await api(cfg.endpoint + '/' + d.id, 'DELETE');
                            await cargar($('q').value.trim());
                        } catch (err) {
                            if (err.message !== 'sesion') { mostrarError(err.message); }
                        }
                    }));
                }
                tbody.appendChild(tr);
            });
        }

        async function cargar(q) {
            var ruta = cfg.endpoint + '?limit=100';
            if (q) { ruta += '&q=' + encodeURIComponent(q); }
            var body = await api(ruta);
            render(body.data || []);
        }

        $('form').addEventListener('submit', async function (e) {
            e.preventDefault();
            limpiarError();
            var cuerpo = {};
            cfg.campos.forEach(function (c) {
                var v = $(c.id).value.trim();
                if (v !== '') { cuerpo[c.clave] = v; }
            });
            try {
                if (editando) {
                    await api(cfg.endpoint + '/' + editando, 'PUT', cuerpo);
                } else {
                    await api(cfg.endpoint, 'POST', cuerpo);
                }
                cancelar();
                await cargar($('q').value.trim());
            } catch (err) {
                if (err.message !== 'sesion') { mostrarError(err.message); }
            }
        });

        $('buscar').addEventListener('submit', async function (e) {
            e.preventDefault();
            limpiarError();
            try {
                await cargar($('q').value.trim());
            } catch (err) {
                if (err.message !== 'sesion') { mostrarError(err.message); }
            }
        });

        function cancelar() {
            editando = null;
            document.getElementById(cfg.prefijo + '-form').reset();
            $('guardar').textContent = cfg.textoCrear;
            $('cancelar').hidden = true;
        }

        $('cancelar').addEventListener('click', function () {
            limpiarError();
            cancelar();
        });

        cargar().catch(function (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        });
    }

    montarDirectorio({
        prefijo: 'pac',
        endpoint: '/api/v1/catalog/patients',
        textoCrear: 'Crear paciente',
        campos: [
            { id: 'identificacion', clave: 'identificacion' },
            { id: 'nombre', clave: 'nombre' },
            { id: 'fecha', clave: 'fecha_nacimiento' },
            { id: 'contacto', clave: 'contacto' },
        ],
        columnas: function (d) {
            return [String(d.id), d.identificacion, d.nombre, d.fecha_nacimiento || '',
                d.contacto || '', d.anonimizado_at ? 'anonimizado' : d.estado];
        },
    });

    montarDirectorio({
        prefijo: 'presc',
        endpoint: '/api/v1/catalog/prescribers',
        textoCrear: 'Crear prescriptor',
        campos: [
            { id: 'identificacion', clave: 'identificacion' },
            { id: 'nombre', clave: 'nombre' },
            { id: 'especialidad', clave: 'especialidad' },
        ],
        columnas: function (d) {
            return [String(d.id), d.identificacion, d.nombre, d.especialidad || '', d.estado];
        },
    });
})();
