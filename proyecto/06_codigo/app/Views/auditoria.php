<?php
declare(strict_types=1);
/** CP-FRONT-10: auditoria de operaciones, accesos PII y eventos (fragmento). */
?>
<section class="panel" aria-labelledby="sec-aud-op">
    <h2 id="sec-aud-op">Operaciones sensibles (RF-090)</h2>

    <form id="aud-buscar" class="form-grid">
        <div class="field">
            <label for="aud-accion">Acción</label>
            <select id="aud-accion">
                <option value="">Todas</option>
                <option value="creacion">creacion</option>
                <option value="modificacion">modificacion</option>
                <option value="eliminacion">eliminacion</option>
                <option value="solicitud">solicitud</option>
            </select>
        </div>
        <div class="field">
            <label for="aud-entidad">Entidad</label>
            <input type="text" id="aud-entidad" maxlength="100">
        </div>
        <div class="field">
            <label for="aud-desde">Desde</label>
            <input type="date" id="aud-desde">
        </div>
        <div class="field">
            <label for="aud-hasta">Hasta</label>
            <input type="date" id="aud-hasta">
        </div>
        <button type="submit" class="btn">Buscar</button>
    </form>

    <table class="tabla" id="aud-tabla">
        <thead>
            <tr><th scope="col">ID</th><th scope="col">Fecha</th><th scope="col">Usuario</th><th scope="col">Acción</th><th scope="col">Entidad</th><th scope="col">Entidad ID</th><th scope="col">Motivo</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody></tbody>
    </table>

    <div class="panel panel-sub" id="aud-detalle" hidden>
        <h3>Detalle de la operación</h3>
        <p class="detalle" id="aud-detalle-info"></p>
        <pre id="aud-detalle-json" class="json-view"></pre>
    </div>
</section>

<section class="panel" aria-labelledby="sec-aud-pii">
    <h2 id="sec-aud-pii">Accesos a datos personales (RF-091 / RN-13)</h2>

    <form id="pii-buscar" class="form-grid">
        <div class="field">
            <label for="pii-accion">Acción</label>
            <select id="pii-accion">
                <option value="">Todas</option>
                <option value="consulta">consulta</option>
                <option value="creacion">creacion</option>
                <option value="modificacion">modificacion</option>
                <option value="exportacion">exportacion</option>
            </select>
        </div>
        <button type="submit" class="btn">Buscar</button>
    </form>

    <table class="tabla" id="pii-tabla">
        <thead>
            <tr><th scope="col">ID</th><th scope="col">Fecha</th><th scope="col">Usuario</th><th scope="col">Acción</th><th scope="col">Paciente</th><th scope="col">Receta</th><th scope="col">Motivo</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>

<section class="panel" aria-labelledby="sec-aud-evt">
    <h2 id="sec-aud-evt">Eventos salientes (outbox, decisión 16)</h2>

    <form id="evt-buscar" class="form-grid">
        <div class="field">
            <label for="evt-estado">Estado</label>
            <select id="evt-estado">
                <option value="">Todos</option>
                <option value="pendiente">pendiente</option>
                <option value="procesado">procesado</option>
                <option value="fallido">fallido</option>
            </select>
        </div>
        <button type="submit" class="btn">Buscar</button>
    </form>

    <table class="tabla" id="evt-tabla">
        <thead>
            <tr><th scope="col">ID</th><th scope="col">Tipo de evento</th><th scope="col">Agregado</th><th scope="col">Estado</th><th scope="col">Intentos</th><th scope="col">Creado</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody></tbody>
    </table>

    <div class="panel panel-sub" id="evt-detalle" hidden>
        <h3>Payload del evento</h3>
        <pre id="evt-detalle-json" class="json-view"></pre>
    </div>
</section>
