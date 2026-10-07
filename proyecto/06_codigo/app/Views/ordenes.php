<?php
declare(strict_types=1);
/** CP-FRONT-06: ordenes de compra (fragmento del layout). */
?>
<section class="panel" aria-labelledby="sec-ord">
    <h2 id="sec-ord">Órdenes de compra</h2>

    <form id="ord-form" class="form-grid">
        <div class="field">
            <label for="ord-numero">Número (opcional, autogenerado si se deja vacío)</label>
            <input type="text" id="ord-numero" maxlength="50">
        </div>
        <div class="field">
            <label for="ord-supplier">Proveedor</label>
            <select id="ord-supplier" required></select>
        </div>
        <button type="submit" class="btn btn-primary" id="ord-guardar">Crear orden</button>
        <button type="button" class="btn" id="ord-cancelar" hidden>Cancelar edición</button>
    </form>

    <fieldset class="items-editor">
        <legend>Ítems de la orden</legend>
        <div class="form-grid">
            <div class="field">
                <label for="ord-item-producto">Producto</label>
                <select id="ord-item-producto"></select>
            </div>
            <div class="field">
                <label for="ord-item-cantidad">Cantidad pedida</label>
                <input type="number" id="ord-item-cantidad" min="1" step="1">
            </div>
            <button type="button" class="btn" id="ord-item-agregar">Agregar ítem</button>
        </div>
        <ul id="ord-items-lista" class="items-lista"></ul>
    </fieldset>

    <form id="ord-buscar" class="form-grid">
        <div class="field">
            <label for="ord-q">Buscar por número</label>
            <input type="search" id="ord-q">
        </div>
        <div class="field">
            <label for="ord-estado">Estado</label>
            <select id="ord-estado">
                <option value="">Todos</option>
                <option value="borrador">borrador</option>
                <option value="emitida">emitida</option>
                <option value="recibida">recibida</option>
                <option value="cancelada">cancelada</option>
            </select>
        </div>
        <button type="submit" class="btn">Buscar</button>
    </form>

    <table class="tabla" id="ord-tabla">
        <thead>
            <tr><th scope="col">ID</th><th scope="col">Número</th><th scope="col">Proveedor</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>

<section class="panel" id="ord-detalle-panel" aria-labelledby="sec-ord-det" hidden>
    <h2 id="sec-ord-det">Detalle de la orden</h2>
    <p class="detalle" id="ord-detalle-info"></p>
    <table class="tabla" id="ord-detalle-tabla">
        <thead>
            <tr><th scope="col">Producto</th><th scope="col">Cantidad pedida</th><th scope="col">Cantidad recibida</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>
