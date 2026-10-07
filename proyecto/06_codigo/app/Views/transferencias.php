<?php
declare(strict_types=1);
/** CP-FRONT-07: transferencias entre sucursales (fragmento del layout). */
?>
<section class="panel" aria-labelledby="sec-transf">
    <h2 id="sec-transf">Transferencias entre sucursales</h2>

    <form id="tr-form" class="form-grid">
        <div class="field">
            <label for="tr-origen">Sucursal origen</label>
            <select id="tr-origen" required></select>
        </div>
        <div class="field">
            <label for="tr-destino">Sucursal destino</label>
            <select id="tr-destino" required></select>
        </div>
        <div class="field">
            <label for="tr-idempotency">Clave de idempotencia (opcional)</label>
            <input type="text" id="tr-idempotency" maxlength="255">
        </div>
    </form>

    <fieldset class="items-editor">
        <legend>Ítems (lote y cantidad)</legend>
        <div class="form-grid">
            <div class="field">
                <label for="tr-item-lote">Lote</label>
                <select id="tr-item-lote"></select>
            </div>
            <div class="field">
                <label for="tr-item-cantidad">Cantidad</label>
                <input type="number" id="tr-item-cantidad" min="1" step="1">
            </div>
            <button type="button" class="btn" id="tr-item-agregar">Agregar ítem</button>
        </div>
        <ul id="tr-items-lista" class="items-lista"></ul>
    </fieldset>

    <button type="submit" class="btn btn-primary" id="tr-guardar">Crear transferencia</button>

    <table class="tabla" id="tr-tabla">
        <thead>
            <tr><th scope="col">ID</th><th scope="col">Origen</th><th scope="col">Destino</th><th scope="col">Estado</th><th scope="col">Idempotencia</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>

<section class="panel" id="tr-detalle-panel" aria-labelledby="sec-tr-det" hidden>
    <h2 id="sec-tr-det">Detalle de la transferencia</h2>
    <p class="detalle" id="tr-detalle-info"></p>
    <table class="tabla" id="tr-detalle-tabla">
        <thead>
            <tr><th scope="col">Lote</th><th scope="col">Cantidad solicitada</th><th scope="col">Cantidad recibida</th></tr>
        </thead>
        <tbody></tbody>
    </table>
    <div class="form-grid">
        <button type="button" class="btn btn-primary" id="tr-despachar">Despachar (completo)</button>
        <button type="button" class="btn btn-primary" id="tr-recibir">Recibir (completo)</button>
        <button type="button" class="btn" id="tr-cerrar">Cerrar</button>
        <button type="button" class="btn" id="tr-rechazar">Rechazar</button>
    </div>
</section>
