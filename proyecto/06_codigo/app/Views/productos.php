<?php
declare(strict_types=1);
/** CP-FRONT-04: catalogo de productos (fragmento del layout). */
?>
<section class="panel" aria-labelledby="sec-prod">
    <h2 id="sec-prod">Productos</h2>

    <form id="prod-form" class="form-grid">
        <div class="field">
            <label for="prod-sku">SKU</label>
            <input type="text" id="prod-sku" maxlength="50" required>
        </div>
        <div class="field">
            <label for="prod-nombre">Nombre</label>
            <input type="text" id="prod-nombre" maxlength="255" required>
        </div>
        <div class="field">
            <label for="prod-principio">Principio activo</label>
            <input type="text" id="prod-principio" maxlength="255">
        </div>
        <div class="field">
            <label for="prod-presentacion">Presentación</label>
            <input type="text" id="prod-presentacion" maxlength="100">
        </div>
        <div class="field">
            <label for="prod-concentracion">Concentración</label>
            <input type="text" id="prod-concentracion" maxlength="100">
        </div>
        <div class="field">
            <label for="prod-condicion">Condición de venta</label>
            <select id="prod-condicion">
                <option value="libre">libre</option>
                <option value="receta">receta</option>
                <option value="controlado">controlado</option>
            </select>
        </div>
        <div class="field">
            <label for="prod-categorias">Categorías</label>
            <select id="prod-categorias" multiple size="4"></select>
        </div>
        <button type="submit" class="btn btn-primary" id="prod-guardar">Crear producto</button>
        <button type="button" class="btn" id="prod-cancelar" hidden>Cancelar edición</button>
    </form>

    <form id="prod-buscar" class="form-grid">
        <div class="field">
            <label for="prod-q">Buscar por nombre o SKU</label>
            <input type="search" id="prod-q">
        </div>
        <button type="submit" class="btn">Buscar</button>
    </form>

    <table class="tabla" id="prod-tabla">
        <thead>
            <tr><th scope="col">ID</th><th scope="col">SKU</th><th scope="col">Nombre</th><th scope="col">Principio activo</th><th scope="col">Presentación</th><th scope="col">Condición</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>
