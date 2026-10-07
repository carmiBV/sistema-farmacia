<?php
declare(strict_types=1);
/** CP-FRONT-09: devoluciones (fragmento del layout). */
?>
<section class="panel" aria-labelledby="sec-devol">
    <h2 id="sec-devol">Procesamiento de devoluciones</h2>

    <form id="dv-cargar" class="form-grid">
        <div class="field">
            <label for="dv-order-id">ID de la venta</label>
            <input type="number" id="dv-order-id" min="1" step="1">
        </div>
        <button type="submit" class="btn">Cargar venta</button>
    </form>

    <p class="detalle" id="dv-info"></p>

    <table class="tabla" id="dv-items-tabla">
        <thead>
            <tr><th scope="col">Item</th><th scope="col">Producto</th><th scope="col">Lote</th><th scope="col">Vendida</th><th scope="col">Devuelta</th><th scope="col">Cantidad a devolver</th><th scope="col">Condición</th></tr>
        </thead>
        <tbody></tbody>
    </table>

    <form id="dv-form" class="form-grid">
        <div class="field">
            <label for="dv-motivo">Motivo</label>
            <input type="text" id="dv-motivo" maxlength="500">
        </div>
        <button type="submit" class="btn btn-primary">Registrar devolución</button>
    </form>

    <h3>Devoluciones registradas</h3>
    <table class="tabla" id="dv-tabla">
        <thead>
            <tr><th scope="col">ID</th><th scope="col">Venta</th><th scope="col">Motivo</th><th scope="col">Fecha</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>

<section class="panel" id="dv-detalle-panel" aria-labelledby="sec-dv-det" hidden>
    <h2 id="sec-dv-det">Detalle de la devolución</h2>
    <table class="tabla" id="dv-detalle-tabla">
        <thead>
            <tr><th scope="col">Producto</th><th scope="col">Lote</th><th scope="col">Cantidad</th><th scope="col">Condición</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>
