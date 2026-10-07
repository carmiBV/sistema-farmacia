<?php
declare(strict_types=1);
/** CP-FRONT-09: punto de venta tactil (fragmento del layout). */
?>
<section class="panel" aria-labelledby="sec-pos">
    <h2 id="sec-pos">Punto de Venta</h2>

    <form id="pos-contexto" class="form-grid">
        <div class="field">
            <label for="pos-store">Sucursal</label>
            <select id="pos-store" required></select>
        </div>
        <div class="field">
            <label for="pos-register">Caja</label>
            <select id="pos-register" required></select>
        </div>
        <div class="field">
            <label for="pos-paciente">Paciente (opcional)</label>
            <select id="pos-paciente"><option value="">—</option></select>
        </div>
    </form>

    <form id="pos-buscar" class="form-grid">
        <div class="field">
            <label for="pos-codigo">Código de barras o SKU</label>
            <input type="text" id="pos-codigo" autocomplete="off" autofocus>
        </div>
        <button type="submit" class="btn btn-primary">Agregar</button>
    </form>

    <table class="tabla" id="pos-tabla">
        <thead>
            <tr><th scope="col">Producto</th><th scope="col">Lote (FEFO)</th><th scope="col">Vence</th><th scope="col">P. unitario</th><th scope="col">Cantidad</th><th scope="col">Subtotal</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody></tbody>
    </table>
    <p class="pos-total">Total: <span id="pos-total">0.00</span></p>
</section>

<section class="panel" aria-labelledby="sec-pago">
    <h2 id="sec-pago">Cobro</h2>

    <form id="pago-form" class="form-grid">
        <div class="field">
            <label for="pago-medio">Medio de pago</label>
            <select id="pago-medio">
                <option value="efectivo">efectivo</option>
                <option value="tarjeta">tarjeta</option>
                <option value="otros">otros</option>
            </select>
        </div>
        <div class="field">
            <label for="pago-monto">Monto</label>
            <input type="number" id="pago-monto" step="0.01" min="0.01">
        </div>
        <button type="button" class="btn" id="pago-agregar">Agregar pago</button>
        <button type="button" class="btn btn-primary" id="pos-cobrar">Cobrar y emitir comprobante</button>
    </form>

    <ul id="pago-lista" class="items-lista"></ul>
</section>

<section class="panel" id="comprobante" aria-labelledby="sec-comp" hidden>
    <h2 id="sec-comp">Comprobante</h2>
    <div id="comprobante-detalle"></div>
    <button type="button" class="btn" id="comprobante-imprimir">Imprimir</button>
</section>
