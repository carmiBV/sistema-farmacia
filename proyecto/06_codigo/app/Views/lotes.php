<?php
declare(strict_types=1);
/** CP-FRONT-07: lotes con semaforizacion FEFO (fragmento del layout). */
?>
<section class="panel" aria-labelledby="sec-lotes">
    <h2 id="sec-lotes">Lotes y vencimientos (FEFO)</h2>

    <form id="lot-buscar" class="form-grid">
        <div class="field">
            <label for="lot-estado">Estado del lote</label>
            <select id="lot-estado">
                <option value="">Todos</option>
                <option value="cuarentena">cuarentena</option>
                <option value="liberado">liberado</option>
                <option value="retirado">retirado</option>
            </select>
        </div>
        <button type="submit" class="btn">Buscar</button>
    </form>

    <table class="tabla" id="lot-tabla">
        <thead>
            <tr><th scope="col">ID</th><th scope="col">Producto</th><th scope="col">Número de lote</th><th scope="col">Vencimiento</th><th scope="col">FEFO</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>
