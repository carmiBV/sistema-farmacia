<?php
declare(strict_types=1);
/** CP-FRONT-07: alertas de vencimiento/stock e incidentes (fragmento del layout). */
?>
<section class="panel" aria-labelledby="sec-alertas">
    <h2 id="sec-alertas">Alertas de inventario</h2>

    <form id="al-buscar" class="form-grid">
        <div class="field">
            <label for="al-tipo">Tipo</label>
            <select id="al-tipo">
                <option value="">Todas</option>
                <option value="stock_minimo">stock_minimo</option>
                <option value="vencimiento">vencimiento</option>
            </select>
        </div>
        <div class="field">
            <label for="al-estado">Estado</label>
            <select id="al-estado">
                <option value="abierta">abierta</option>
                <option value="resuelta">resuelta</option>
                <option value="">Todos</option>
            </select>
        </div>
        <button type="submit" class="btn">Buscar</button>
        <button type="button" class="btn btn-primary" id="al-evaluar">Evaluar alertas ahora</button>
    </form>

    <table class="tabla" id="al-tabla">
        <thead>
            <tr><th scope="col">ID</th><th scope="col">Tipo</th><th scope="col">Producto</th><th scope="col">Sucursal</th><th scope="col">Generada</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>

<section class="panel" aria-labelledby="sec-incidentes">
    <h2 id="sec-incidentes">Incidentes de consumo</h2>

    <form id="inc-form" class="form-grid">
        <div class="field">
            <label for="inc-store">Sucursal</label>
            <select id="inc-store" required></select>
        </div>
        <div class="field">
            <label for="inc-lote">Lote</label>
            <select id="inc-lote" required></select>
        </div>
        <div class="field">
            <label for="inc-consumo">Consumo declarado</label>
            <input type="number" id="inc-consumo" min="1" step="1" required>
        </div>
        <button type="submit" class="btn btn-primary" id="inc-guardar">Reportar incidente</button>
    </form>

    <table class="tabla" id="inc-tabla">
        <thead>
            <tr><th scope="col">ID</th><th scope="col">Sucursal</th><th scope="col">Lote</th><th scope="col">Stock registrado</th><th scope="col">Consumo declarado</th><th scope="col">Estado</th><th scope="col">Abierto por</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>
