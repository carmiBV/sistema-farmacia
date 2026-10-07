<?php
declare(strict_types=1);
/**
 * CP-FRONT-02: widgets del dashboard (fragmento del layout layouts/app.php).
 * Los datos los carga public/assets/js/dashboard.js via /api/v1/*.
 */
?>
<section class="widgets" aria-label="Resumen operativo">
    <article class="widget">
        <h2>Ventas diarias</h2>
        <p id="w-ventas-dato" class="dato">—</p>
        <p id="w-ventas-detalle" class="detalle">Cargando…</p>
    </article>
    <article class="widget">
        <h2>Alertas de stock mínimo</h2>
        <p id="w-stock-dato" class="dato">—</p>
        <p id="w-stock-detalle" class="detalle">Cargando…</p>
    </article>
    <article class="widget">
        <h2>Vencimientos próximos</h2>
        <p id="w-venc-dato" class="dato">—</p>
        <p id="w-venc-detalle" class="detalle">Cargando…</p>
    </article>
</section>
