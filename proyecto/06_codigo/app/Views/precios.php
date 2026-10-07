<?php
declare(strict_types=1);
/** CP-FRONT-04: precios y promociones (fragmento del layout). */
?>
<section class="panel" aria-labelledby="sec-precios">
    <h2 id="sec-precios">Precios</h2>

    <form id="pr-form" class="form-grid">
        <div class="field">
            <label for="pr-producto">Producto</label>
            <select id="pr-producto" required></select>
        </div>
        <div class="field">
            <label for="pr-precio">Precio</label>
            <input type="number" id="pr-precio" step="0.01" min="0.01" required>
        </div>
        <div class="field">
            <label for="pr-desde">Vigente desde</label>
            <input type="date" id="pr-desde" required>
        </div>
        <div class="field">
            <label for="pr-hasta">Vigente hasta (opcional)</label>
            <input type="date" id="pr-hasta">
        </div>
        <div class="field">
            <label for="pr-store">Sucursal (opcional)</label>
            <select id="pr-store"><option value="">Global</option></select>
        </div>
        <button type="submit" class="btn btn-primary" id="pr-guardar">Crear precio</button>
        <button type="button" class="btn" id="pr-cancelar" hidden>Cancelar edición</button>
    </form>

    <table class="tabla" id="pr-tabla">
        <thead>
            <tr><th scope="col">ID</th><th scope="col">Producto</th><th scope="col">Precio</th><th scope="col">Desde</th><th scope="col">Hasta</th><th scope="col">Sucursal</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>

<section class="panel" aria-labelledby="sec-promos">
    <h2 id="sec-promos">Promociones</h2>

    <form id="pm-form" class="form-grid">
        <div class="field">
            <label for="pm-tipo">Alcance</label>
            <select id="pm-tipo">
                <option value="product_id">Producto</option>
                <option value="category_id">Categoría</option>
            </select>
        </div>
        <div class="field">
            <label for="pm-alcance">Elemento</label>
            <select id="pm-alcance" required></select>
        </div>
        <div class="field">
            <label for="pm-descuento">Descuento %</label>
            <input type="number" id="pm-descuento" step="0.01" min="0.01" max="100" required>
        </div>
        <div class="field">
            <label for="pm-desde">Desde</label>
            <input type="date" id="pm-desde" required>
        </div>
        <div class="field">
            <label for="pm-hasta">Hasta</label>
            <input type="date" id="pm-hasta" required>
        </div>
        <button type="submit" class="btn btn-primary" id="pm-guardar">Crear promoción</button>
        <button type="button" class="btn" id="pm-cancelar" hidden>Cancelar edición</button>
    </form>

    <table class="tabla" id="pm-tabla">
        <thead>
            <tr><th scope="col">ID</th><th scope="col">Alcance</th><th scope="col">Descuento %</th><th scope="col">Desde</th><th scope="col">Hasta</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>
