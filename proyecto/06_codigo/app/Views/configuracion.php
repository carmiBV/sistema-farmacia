<?php
declare(strict_types=1);
/** CP-FRONT-03: sucursales, cajas y parámetros del sistema (fragmento del layout). */
?>
<section class="panel" aria-labelledby="sec-sucursales">
    <h2 id="sec-sucursales">Sucursales</h2>

    <form id="s-form" class="form-grid">
        <div class="field">
            <label for="s-codigo">Código</label>
            <input type="text" id="s-codigo" maxlength="20" required>
        </div>
        <div class="field">
            <label for="s-nombre">Nombre</label>
            <input type="text" id="s-nombre" maxlength="150" required>
        </div>
        <button type="submit" class="btn btn-primary" id="s-guardar">Crear sucursal</button>
        <button type="button" id="s-cancelar" class="btn" hidden>Cancelar edición</button>
    </form>

    <table class="tabla" id="s-tabla">
        <thead>
            <tr><th scope="col">ID</th><th scope="col">Código</th><th scope="col">Nombre</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>

<section class="panel" aria-labelledby="sec-cajas">
    <h2 id="sec-cajas">Cajas registradoras</h2>

    <form id="c-form" class="form-grid">
        <div class="field">
            <label for="c-store">Sucursal</label>
            <select id="c-store" required></select>
        </div>
        <div class="field">
            <label for="c-codigo">Código</label>
            <input type="text" id="c-codigo" maxlength="20" required>
        </div>
        <button type="submit" class="btn btn-primary" id="c-guardar">Crear caja</button>
        <button type="button" id="c-cancelar" class="btn" hidden>Cancelar edición</button>
    </form>

    <table class="tabla" id="c-tabla">
        <thead>
            <tr><th scope="col">ID</th><th scope="col">Sucursal</th><th scope="col">Código</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>

<section class="panel" aria-labelledby="sec-parametros">
    <h2 id="sec-parametros">Parámetros del sistema</h2>

    <form id="p-form" class="form-grid">
        <div class="field">
            <label for="p-key">Clave</label>
            <input type="text" id="p-key" maxlength="100" required>
        </div>
        <div class="field">
            <label for="p-value">Valor</label>
            <input type="text" id="p-value" maxlength="4000" required>
        </div>
        <div class="field">
            <label for="p-type">Tipo</label>
            <select id="p-type">
                <option value="texto">texto</option>
                <option value="numero">numero</option>
                <option value="booleano">booleano</option>
                <option value="json">json</option>
            </select>
        </div>
        <div class="field">
            <label for="p-store">Sucursal (solo claves store.*)</label>
            <select id="p-store"><option value="">—</option></select>
        </div>
        <button type="submit" class="btn btn-primary" id="p-guardar">Guardar parámetro</button>
        <button type="button" id="p-cancelar" class="btn" hidden>Cancelar edición</button>
    </form>

    <table class="tabla" id="p-tabla">
        <thead>
            <tr><th scope="col">Clave</th><th scope="col">Tipo</th><th scope="col">Valor</th><th scope="col">Sucursal</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>
