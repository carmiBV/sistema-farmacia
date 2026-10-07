<?php
declare(strict_types=1);
/** CP-FRONT-04: categorias con jerarquia (fragmento del layout). */
?>
<section class="panel" aria-labelledby="sec-cat">
    <h2 id="sec-cat">Categorías</h2>

    <form id="cat-form" class="form-grid">
        <div class="field">
            <label for="cat-nombre">Nombre</label>
            <input type="text" id="cat-nombre" maxlength="150" required>
        </div>
        <div class="field">
            <label for="cat-parent">Categoría padre</label>
            <select id="cat-parent">
                <option value="">— Raíz —</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" id="cat-guardar">Crear categoría</button>
        <button type="button" class="btn" id="cat-cancelar" hidden>Cancelar edición</button>
    </form>

    <form id="cat-buscar" class="form-grid">
        <div class="field">
            <label for="cat-q">Buscar por nombre</label>
            <input type="search" id="cat-q">
        </div>
        <button type="submit" class="btn">Buscar</button>
    </form>

    <table class="tabla" id="cat-tabla">
        <thead>
            <tr><th scope="col">ID</th><th scope="col">Nombre</th><th scope="col">Categoría padre</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>
