<?php
declare(strict_types=1);
/** CP-FRONT-09: ventas, pagos y transacciones (fragmento del layout). */
?>
<section class="panel" aria-labelledby="sec-ventas">
    <h2 id="sec-ventas">Ventas y transacciones</h2>

    <form id="v-buscar" class="form-grid">
        <div class="field">
            <label for="v-store">Sucursal</label>
            <select id="v-store"><option value="">Todas</option></select>
        </div>
        <div class="field">
            <label for="v-estado">Estado</label>
            <select id="v-estado">
                <option value="">Todos</option>
                <option value="pendiente">pendiente</option>
                <option value="pagada">pagada</option>
                <option value="anulada">anulada</option>
                <option value="devuelta">devuelta</option>
            </select>
        </div>
        <button type="submit" class="btn">Buscar</button>
    </form>

    <table class="tabla" id="v-tabla">
        <thead>
            <tr><th scope="col">ID</th><th scope="col">Fecha</th><th scope="col">Total</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>

<section class="panel" id="v-detalle-panel" aria-labelledby="sec-v-det" hidden>
    <h2 id="sec-v-det">Detalle de la venta</h2>
    <p class="detalle" id="v-detalle-info"></p>

    <table class="tabla" id="v-items-tabla">
        <thead>
            <tr><th scope="col">Producto</th><th scope="col">Lote</th><th scope="col">Cantidad</th><th scope="col">P. unitario</th><th scope="col">Subtotal</th></tr>
        </thead>
        <tbody></tbody>
    </table>

    <h3>Pagos registrados</h3>
    <table class="tabla" id="v-pagos-tabla">
        <thead>
            <tr><th scope="col">ID</th><th scope="col">Medio</th><th scope="col">Monto</th><th scope="col">Estado</th></tr>
        </thead>
        <tbody></tbody>
    </table>

    <form id="v-pago-form" class="form-grid">
        <div class="field">
            <label for="v-pago-medio">Medio</label>
            <select id="v-pago-medio">
                <option value="efectivo">efectivo</option>
                <option value="tarjeta">tarjeta</option>
                <option value="otros">otros</option>
            </select>
        </div>
        <div class="field">
            <label for="v-pago-monto">Monto</label>
            <input type="number" id="v-pago-monto" step="0.01" min="0.01">
        </div>
        <button type="submit" class="btn btn-primary">Registrar pago</button>
        <button type="button" class="btn" id="v-anular">Anular venta (repone stock)</button>
    </form>
</section>
