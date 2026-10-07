<?php
declare(strict_types=1);
/** CP-FRONT-08: libro oficial de controlados y saldos (fragmento del layout). */
?>
<section class="panel" aria-labelledby="sec-ledger">
    <h2 id="sec-ledger">Libro Oficial de Controlados</h2>

    <form id="led-buscar" class="form-grid">
        <div class="field">
            <label for="led-tipo">Tipo de asiento</label>
            <select id="led-tipo">
                <option value="">Todos</option>
                <option value="entrada">entrada</option>
                <option value="salida">salida</option>
                <option value="devolucion">devolucion</option>
                <option value="transferencia_out">transferencia_out</option>
                <option value="transferencia_in">transferencia_in</option>
                <option value="ajuste">ajuste</option>
            </select>
        </div>
        <div class="field">
            <label for="led-ref">Referencia</label>
            <select id="led-ref">
                <option value="">Todas</option>
                <option value="venta">venta</option>
                <option value="recepcion">recepcion</option>
                <option value="transferencia">transferencia</option>
                <option value="devolucion">devolucion</option>
                <option value="ajuste">ajuste</option>
            </select>
        </div>
        <div class="field">
            <label for="led-desde">Desde</label>
            <input type="date" id="led-desde">
        </div>
        <div class="field">
            <label for="led-hasta">Hasta</label>
            <input type="date" id="led-hasta">
        </div>
        <button type="submit" class="btn">Buscar</button>
    </form>

    <table class="tabla" id="led-tabla">
        <thead>
            <tr><th scope="col">ID</th><th scope="col">Fecha</th><th scope="col">Tipo</th><th scope="col">Producto</th><th scope="col">Cantidad</th><th scope="col">Saldo resultante</th><th scope="col">Referencia</th><th scope="col">Motivo</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>

<section class="panel" aria-labelledby="sec-saldos">
    <h2 id="sec-saldos">Saldos y conciliación (RNF-024)</h2>

    <button type="button" class="btn" id="sal-conciliar">Ejecutar conciliación</button>
    <p class="detalle" id="sal-conciliacion"></p>

    <table class="tabla" id="sal-tabla">
        <thead>
            <tr><th scope="col">Sucursal</th><th scope="col">Producto</th><th scope="col">Saldo</th><th scope="col">Saldo calculado</th><th scope="col">Conciliado</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>

<section class="panel" aria-labelledby="sec-ajuste">
    <h2 id="sec-ajuste">Ajuste con doble autorización (RF-046)</h2>

    <form id="aj-form" class="form-grid">
        <div class="field">
            <label for="aj-store">Sucursal</label>
            <select id="aj-store" required></select>
        </div>
        <div class="field">
            <label for="aj-producto">Producto</label>
            <select id="aj-producto" required></select>
        </div>
        <div class="field">
            <label for="aj-lote">Lote</label>
            <select id="aj-lote" required></select>
        </div>
        <div class="field">
            <label for="aj-cantidad">Cantidad</label>
            <input type="number" id="aj-cantidad" min="1" step="1" required>
        </div>
        <div class="field">
            <label for="aj-direccion">Dirección</label>
            <select id="aj-direccion">
                <option value="baja">baja</option>
                <option value="incremento">incremento</option>
            </select>
        </div>
        <div class="field">
            <label for="aj-motivo">Motivo</label>
            <input type="text" id="aj-motivo" maxlength="500" required>
        </div>
        <div class="field">
            <label for="aj-autorizador">Autorizador (debe ser otro usuario)</label>
            <select id="aj-autorizador" required></select>
        </div>
        <button type="submit" class="btn btn-primary" id="aj-guardar">Registrar ajuste</button>
    </form>
</section>
