<?php
declare(strict_types=1);
/** CP-FRONT-05: directorio de pacientes y prescriptores (fragmento del layout). */
?>
<section class="panel" aria-labelledby="sec-pac">
    <h2 id="sec-pac">Pacientes</h2>

    <form id="pac-form" class="form-grid">
        <div class="field">
            <label for="pac-identificacion">Identificación</label>
            <input type="text" id="pac-identificacion" maxlength="30" required>
        </div>
        <div class="field">
            <label for="pac-nombre">Nombre</label>
            <input type="text" id="pac-nombre" maxlength="200" required>
        </div>
        <div class="field">
            <label for="pac-fecha">Fecha de nacimiento</label>
            <input type="date" id="pac-fecha" required>
        </div>
        <div class="field">
            <label for="pac-contacto">Contacto</label>
            <input type="text" id="pac-contacto" maxlength="255">
        </div>
        <button type="submit" class="btn btn-primary" id="pac-guardar">Crear paciente</button>
        <button type="button" class="btn" id="pac-cancelar" hidden>Cancelar edición</button>
    </form>

    <form id="pac-buscar" class="form-grid">
        <div class="field">
            <label for="pac-q">Buscar por nombre o identificación</label>
            <input type="search" id="pac-q">
        </div>
        <button type="submit" class="btn">Buscar</button>
    </form>

    <table class="tabla" id="pac-tabla">
        <thead>
            <tr><th scope="col">ID</th><th scope="col">Identificación</th><th scope="col">Nombre</th><th scope="col">Nacimiento</th><th scope="col">Contacto</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>

<section class="panel" aria-labelledby="sec-presc">
    <h2 id="sec-presc">Médicos prescriptores</h2>

    <form id="presc-form" class="form-grid">
        <div class="field">
            <label for="presc-identificacion">Identificación</label>
            <input type="text" id="presc-identificacion" maxlength="30" required>
        </div>
        <div class="field">
            <label for="presc-nombre">Nombre</label>
            <input type="text" id="presc-nombre" maxlength="200" required>
        </div>
        <div class="field">
            <label for="presc-especialidad">Especialidad</label>
            <input type="text" id="presc-especialidad" maxlength="150">
        </div>
        <button type="submit" class="btn btn-primary" id="presc-guardar">Crear prescriptor</button>
        <button type="button" class="btn" id="presc-cancelar" hidden>Cancelar edición</button>
    </form>

    <form id="presc-buscar" class="form-grid">
        <div class="field">
            <label for="presc-q">Buscar por nombre o identificación</label>
            <input type="search" id="presc-q">
        </div>
        <button type="submit" class="btn">Buscar</button>
    </form>

    <table class="tabla" id="presc-tabla">
        <thead>
            <tr><th scope="col">ID</th><th scope="col">Identificación</th><th scope="col">Nombre</th><th scope="col">Especialidad</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>
