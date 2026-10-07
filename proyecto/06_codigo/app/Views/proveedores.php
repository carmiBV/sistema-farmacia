<?php
declare(strict_types=1);
/** CP-FRONT-05: directorio de proveedores (fragmento del layout). */
?>
<section class="panel" aria-labelledby="sec-prov">
    <h2 id="sec-prov">Proveedores de medicamentos</h2>

    <form id="prov-form" class="form-grid">
        <div class="field">
            <label for="prov-identificacion">Identificación</label>
            <input type="text" id="prov-identificacion" maxlength="30" required>
        </div>
        <div class="field">
            <label for="prov-nombre">Nombre</label>
            <input type="text" id="prov-nombre" maxlength="200" required>
        </div>
        <div class="field">
            <label for="prov-contacto">Contacto</label>
            <input type="text" id="prov-contacto" maxlength="255">
        </div>
        <button type="submit" class="btn btn-primary" id="prov-guardar">Crear proveedor</button>
        <button type="button" class="btn" id="prov-cancelar" hidden>Cancelar edición</button>
    </form>

    <form id="prov-buscar" class="form-grid">
        <div class="field">
            <label for="prov-q">Buscar por nombre o identificación</label>
            <input type="search" id="prov-q">
        </div>
        <button type="submit" class="btn">Buscar</button>
    </form>

    <table class="tabla" id="prov-tabla">
        <thead>
            <tr><th scope="col">ID</th><th scope="col">Identificación</th><th scope="col">Nombre</th><th scope="col">Contacto</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>
