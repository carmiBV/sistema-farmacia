<?php
declare(strict_types=1);
/** CP-FRONT-08: recetas medicas y dispensacion (fragmento del layout). */
?>
<section class="panel" aria-labelledby="sec-rx">
    <h2 id="sec-rx">Recetas médicas</h2>

    <form id="rx-form" class="form-grid">
        <div class="field">
            <label for="rx-prescriptor">Prescriptor</label>
            <select id="rx-prescriptor" required></select>
        </div>
        <div class="field">
            <label for="rx-paciente">Paciente</label>
            <select id="rx-paciente" required></select>
        </div>
        <div class="field">
            <label for="rx-fecha">Fecha de la receta</label>
            <input type="date" id="rx-fecha" required>
        </div>
        <div class="field">
            <label for="rx-referencia">Nº de referencia (opcional)</label>
            <input type="text" id="rx-referencia" maxlength="100">
        </div>
        <button type="submit" class="btn btn-primary" id="rx-guardar">Registrar receta</button>
    </form>

    <fieldset class="items-editor">
        <legend>Ítems recetados</legend>
        <div class="form-grid">
            <div class="field">
                <label for="rx-item-producto">Producto (receta o controlado)</label>
                <select id="rx-item-producto"></select>
            </div>
            <div class="field">
                <label for="rx-item-cantidad">Cantidad prescrita</label>
                <input type="number" id="rx-item-cantidad" min="1" step="1">
            </div>
            <button type="button" class="btn" id="rx-item-agregar">Agregar ítem</button>
        </div>
        <ul id="rx-items-lista" class="items-lista"></ul>
    </fieldset>

    <form id="rx-buscar" class="form-grid">
        <div class="field">
            <label for="rx-filtro-paciente">Filtrar por paciente</label>
            <select id="rx-filtro-paciente"><option value="">Todos</option></select>
        </div>
        <button type="submit" class="btn">Buscar</button>
    </form>

    <table class="tabla" id="rx-tabla">
        <thead>
            <tr><th scope="col">ID</th><th scope="col">Fecha</th><th scope="col">Prescriptor</th><th scope="col">Paciente</th><th scope="col">Referencia</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>

<section class="panel" id="rx-detalle-panel" aria-labelledby="sec-rx-det" hidden>
    <h2 id="sec-rx-det">Detalle y dispensación</h2>
    <p class="detalle" id="rx-detalle-info"></p>
    <table class="tabla" id="rx-detalle-tabla">
        <thead>
            <tr><th scope="col">Producto</th><th scope="col">Prescrita</th><th scope="col">Dispensada</th><th scope="col">Saldo</th><th scope="col">A dispensar</th></tr>
        </thead>
        <tbody></tbody>
    </table>
    <button type="button" class="btn btn-primary" id="rx-dispensar">Dispensar cantidades indicadas</button>
</section>
