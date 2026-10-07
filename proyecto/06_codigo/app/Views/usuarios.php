<?php
declare(strict_types=1);
/** CP-FRONT-03: gestión de usuarios y roles RBAC (fragmento del layout). */
?>
<section class="panel" aria-labelledby="sec-usuarios">
    <h2 id="sec-usuarios">Usuarios</h2>

    <form id="u-form" class="form-grid">
        <div class="field">
            <label for="u-usuario">Usuario</label>
            <input type="text" id="u-usuario" autocomplete="off" required>
        </div>
        <div class="field">
            <label for="u-password">Contraseña (mín. 8)</label>
            <input type="password" id="u-password" autocomplete="new-password" required>
        </div>
        <div class="field">
            <label for="u-roles">Roles</label>
            <select id="u-roles" multiple size="3"></select>
        </div>
        <button type="submit" class="btn btn-primary">Crear usuario</button>
    </form>

    <form id="u-asignar" class="form-grid" hidden>
        <p class="detalle" id="u-asignar-titulo">Asignar roles</p>
        <div class="field">
            <label for="u-asignar-roles">Roles del usuario</label>
            <select id="u-asignar-roles" multiple size="3"></select>
        </div>
        <button type="submit" class="btn btn-primary">Guardar roles</button>
        <button type="button" id="u-asignar-cancelar" class="btn">Cancelar</button>
    </form>

    <table class="tabla" id="u-tabla">
        <thead>
            <tr><th scope="col">ID</th><th scope="col">Usuario</th><th scope="col">Estado</th><th scope="col">Roles</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>

<section class="panel" aria-labelledby="sec-roles">
    <h2 id="sec-roles">Roles y permisos</h2>

    <form id="r-form" class="form-grid">
        <div class="field">
            <label for="r-nombre">Nombre del rol</label>
            <input type="text" id="r-nombre" required>
        </div>
        <div class="field">
            <label for="r-descripcion">Descripción</label>
            <input type="text" id="r-descripcion">
        </div>
        <div class="field">
            <label for="r-permisos">Permisos (separados por coma)</label>
            <input type="text" id="r-permisos" placeholder="auth.users.manage, catalog.manage">
        </div>
        <button type="submit" class="btn btn-primary">Crear rol</button>
    </form>

    <table class="tabla" id="r-tabla">
        <thead>
            <tr><th scope="col">ID</th><th scope="col">Rol</th><th scope="col">Descripción</th><th scope="col">Permisos</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>
