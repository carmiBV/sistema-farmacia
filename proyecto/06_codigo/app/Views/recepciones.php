<?php
declare(strict_types=1);
/** CP-FRONT-06: recepcion de mercancia con registro de lotes (fragmento del layout). */
?>
<section class="panel" aria-labelledby="sec-rec">
    <h2 id="sec-rec">Recepción de mercancía</h2>

    <form id="rec-form" class="form-grid">
        <div class="field">
            <label for="rec-orden">Orden emitida</label>
            <select id="rec-orden" required></select>
        </div>
        <div class="field">
            <label for="rec-idempotency">Clave de idempotencia (opcional)</label>
            <input type="text" id="rec-idempotency" maxlength="255">
        </div>
    </form>

    <div class="panel panel-sub" id="rec-verificacion" hidden>
        <h3>Verificación contra la orden</h3>
        <table class="tabla" id="rec-orden-tabla">
            <thead>
                <tr><th scope="col">Producto</th><th scope="col">Cantidad pedida</th><th scope="col">Recibida</th><th scope="col">Pendiente</th></tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <fieldset class="items-editor">
        <legend>Ítems de la recepción (lote de fábrica y vencimiento)</legend>
        <div class="form-grid">
            <div class="field">
                <label for="rec-item-producto">Producto</label>
                <select id="rec-item-producto"></select>
            </div>
            <div class="field">
                <label for="rec-item-lote">Número de lote</label>
                <input type="text" id="rec-item-lote" maxlength="50">
            </div>
            <div class="field">
                <label for="rec-item-vencimiento">Fecha de vencimiento</label>
                <input type="date" id="rec-item-vencimiento">
            </div>
            <div class="field">
                <label for="rec-item-cantidad">Cantidad</label>
                <input type="number" id="rec-item-cantidad" min="1" step="1">
            </div>
            <button type="button" class="btn" id="rec-item-agregar">Agregar ítem</button>
        </div>
        <ul id="rec-items-lista" class="items-lista"></ul>
    </fieldset>

    <button type="button" class="btn btn-primary" id="rec-guardar">Registrar recepción</button>

    <table class="tabla" id="rec-tabla">
        <thead>
            <tr><th scope="col">ID</th><th scope="col">Orden</th><th scope="col">Estado</th><th scope="col">Idempotencia</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>

<section class="panel" id="rec-detalle-panel" aria-labelledby="sec-rec-det" hidden>
    <h2 id="sec-rec-det">Detalle de la recepción</h2>
    <p class="detalle" id="rec-detalle-info"></p>
    <table class="tabla" id="rec-detalle-tabla">
        <thead>
            <tr><th scope="col">Producto</th><th scope="col">Lote</th><th scope="col">Vencimiento</th><th scope="col">Cantidad</th><th scope="col">Lote asignado</th></tr>
        </thead>
        <tbody></tbody>
    </table>
    <div class="form-grid">
        <div class="field">
            <label for="rec-confirmar-store">Sucursal para confirmar</label>
            <select id="rec-confirmar-store"></select>
        </div>
        <button type="button" class="btn btn-primary" id="rec-confirmar">Confirmar recepción</button>
        <button type="button" class="btn" id="rec-rechazar">Rechazar</button>
    </div>
</section>
