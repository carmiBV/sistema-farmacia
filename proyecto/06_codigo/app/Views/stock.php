<?php
declare(strict_types=1);
/** CP-FRONT-07: stock por lote y kardex de movimientos (fragmento del layout). */
?>
<section class="panel" aria-labelledby="sec-stock">
    <h2 id="sec-stock">Stock por sucursal y lote</h2>

    <form id="stock-buscar" class="form-grid">
        <div class="field">
            <label for="stock-store">Sucursal</label>
            <select id="stock-store"><option value="">Todas</option></select>
        </div>
        <button type="submit" class="btn">Buscar</button>
    </form>

    <table class="tabla" id="stock-tabla">
        <thead>
            <tr><th scope="col">Sucursal</th><th scope="col">Producto</th><th scope="col">Lote</th><th scope="col">Vencimiento</th><th scope="col">Disponible</th><th scope="col">Reservado</th><th scope="col">Vendido</th><th scope="col">Estado lote</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>

<section class="panel" aria-labelledby="sec-kardex">
    <h2 id="sec-kardex">Kárdex de movimientos</h2>

    <form id="mov-buscar" class="form-grid">
        <div class="field">
            <label for="mov-store">Sucursal</label>
            <select id="mov-store"><option value="">Todas</option></select>
        </div>
        <div class="field">
            <label for="mov-tipo">Tipo de movimiento</label>
            <select id="mov-tipo">
                <option value="">Todos</option>
                <option value="entrada">entrada</option>
                <option value="salida">salida</option>
                <option value="devolucion">devolucion</option>
                <option value="transferencia_salida">transferencia_salida</option>
                <option value="transferencia_entrada">transferencia_entrada</option>
                <option value="ajuste_baja">ajuste_baja</option>
                <option value="ajuste_incremento">ajuste_incremento</option>
                <option value="compensatorio">compensatorio</option>
            </select>
        </div>
        <button type="submit" class="btn">Buscar</button>
    </form>

    <table class="tabla" id="mov-tabla">
        <thead>
            <tr><th scope="col">ID</th><th scope="col">Fecha</th><th scope="col">Producto</th><th scope="col">Lote</th><th scope="col">Tipo</th><th scope="col">Cantidad</th><th scope="col">Signo</th><th scope="col">Usuario</th><th scope="col">Referencia</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>
