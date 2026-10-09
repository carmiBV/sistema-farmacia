/**
 * CP-LANDING-03: secciones comerciales de la landing (vanilla JS).
 * - Carga config (Bs./Bolivia/CTA/WhatsApp placeholder) y catálogo (7 x 10).
 * - Showroom con filtro por categoría SIN recarga de página.
 * - CTA de WhatsApp configurable: mientras is_placeholder=true el enlace
 *   apunta a #contacto (prohibido inventar números reales).
 */
(function () {
    'use strict';

    var config = null;
    var catalogo = null;
    var categoriaActiva = 'todas';

    function fmtPrecio(p) {
        return (p.price_display || ('Bs. ' + Number(p.price || 0).toFixed(2)));
    }

    /** Filtra productos por categoría ('todas' o id de categoría). Función pura y testeable. */
    function filtrarPorCategoria(productos, categoria) {
        if (!categoria || categoria === 'todas') { return productos.slice(); }
        return productos.filter(function (p) { return p.categoria_id === categoria; });
    }

    function todosLosProductos() {
        if (!catalogo) { return []; }
        var salida = [];
        catalogo.categories.forEach(function (c) {
            (c.products || []).forEach(function (p) {
                salida.push(Object.assign({}, p, { categoria_id: c.id, categoria_nombre: c.name }));
            });
        });
        return salida;
    }

    function tarjetaProducto(p) {
        var art = document.createElement('article');
        art.className = 'product-card';

        var img = document.createElement('img');
        img.src = p.image_url;
        img.alt = p.image_alt || p.name;
        img.loading = 'lazy';
        art.appendChild(img);

        var cuerpo = document.createElement('div');
        cuerpo.className = 'product-body';

        var cat = document.createElement('span');
        cat.className = 'product-category';
        cat.textContent = p.categoria_nombre;
        cuerpo.appendChild(cat);

        var nombre = document.createElement('h3');
        nombre.textContent = p.name;
        cuerpo.appendChild(nombre);

        var precio = document.createElement('p');
        precio.className = 'product-price';
        precio.textContent = fmtPrecio(p);
        cuerpo.appendChild(precio);

        if (p.precio_anterior) {
            var anterior = document.createElement('span');
            anterior.className = 'price-anterior';
            anterior.textContent = 'Bs. ' + Number(p.precio_anterior).toFixed(2);
            cuerpo.appendChild(anterior);
        }

        var lic = document.createElement('a');
        lic.className = 'product-license';
        lic.href = p.image_source || '#';
        lic.target = '_blank';
        lic.rel = 'noopener';
        lic.textContent = 'Imagen: ' + (p.image_license || 'ver fuente');
        cuerpo.appendChild(lic);

        art.appendChild(cuerpo);
        return art;
    }

    function renderCatalogo() {
        var grid = document.getElementById('product-grid');
        var vacio = document.getElementById('catalogo-vacio');
        if (!grid) { return; }
        var productos = filtrarPorCategoria(todosLosProductos(), categoriaActiva);
        grid.textContent = '';
        productos.forEach(function (p) {
            grid.appendChild(tarjetaProducto(p));
        });
        if (vacio) { vacio.hidden = productos.length > 0; }
    }

    function renderFiltros() {
        var barra = document.getElementById('filter-bar');
        if (!barra || !catalogo) { return; }
        barra.textContent = '';

        var opciones = [{ id: 'todas', name: 'Todas' }].concat(catalogo.categories.map(function (c) {
            return { id: c.id, name: c.name };
        }));

        opciones.forEach(function (op) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'filter-chip' + (op.id === categoriaActiva ? ' filter-chip-active' : '');
            b.textContent = op.name;
            b.setAttribute('aria-pressed', op.id === categoriaActiva ? 'true' : 'false');
            b.addEventListener('click', function () {
                categoriaActiva = op.id;
                renderFiltros();
                renderCatalogo();
            });
            barra.appendChild(b);
        });
    }

    /** Enlace de WhatsApp: con placeholder NO se inventa ningún número real. */
    function enlaceWhatsApp(cfg) {
        if (!cfg || !cfg.cta || !cfg.cta.whatsapp) { return '#contacto'; }
        var wa = cfg.cta.whatsapp;
        if (wa.is_placeholder || !wa.phone_placeholder) {
            return '#contacto'; // sin número autorizado: no se inventan enlaces wa.me
        }
        var digitos = String(wa.phone_placeholder).replace(/\D/g, '');
        return digitos ? 'https://wa.me/' + digitos : '#contacto';
    }

    function aplicarConfig(cfg) {
        config = cfg;
        window.LANDING_CONFIG = cfg;

        var badge = document.getElementById('market-badge');
        if (badge && cfg.market) {
            badge.textContent = cfg.market.country + ' · ' + cfg.market.currency;
        }

        ['cta-header', 'cta-hero', 'cta-contacto-demo'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el && cfg.cta && cfg.cta.primary) {
                el.textContent = cfg.cta.primary.label;
                el.href = cfg.cta.primary.href || '#contacto';
            }
        });

        var hrefWa = enlaceWhatsApp(cfg);
        ['cta-whatsapp', 'cta-whatsapp-float'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el && cfg.cta && cfg.cta.whatsapp) {
                if (id === 'cta-whatsapp') { el.textContent = cfg.cta.whatsapp.label; }
                el.href = hrefWa;
                if (cfg.cta.whatsapp.is_placeholder) {
                    el.title = 'Número de WhatsApp pendiente de configuración';
                }
            }
        });

        if (cfg.contact) {
            var email = document.getElementById('contact-email');
            var addr = document.getElementById('contact-address');
            var hours = document.getElementById('contact-hours');
            if (email) { email.textContent = cfg.contact.email; }
            if (addr) { addr.textContent = cfg.contact.address; }
            if (hours) { hours.textContent = cfg.contact.hours; }
        }

        if (cfg.legal) {
            var legal = document.getElementById('footer-legal');
            if (legal) {
                legal.textContent = '© ' + cfg.legal.year + ' ' + cfg.legal.owner + ' · Todos los derechos reservados';
            }
            var links = document.getElementById('footer-links');
            if (links) {
                links.textContent = '';
                (cfg.legal.links || []).forEach(function (l) {
                    var a = document.createElement('a');
                    a.href = l.href || '#';
                    a.textContent = l.label;
                    links.appendChild(a);
                });
            }
        }

        var total = document.getElementById('catalogo-total');
        if (total && cfg.catalog) { total.textContent = String(cfg.catalog.products_total); }
    }

    async function cargarJson(ruta) {
        var respuesta = await fetch(ruta, { cache: 'no-cache' });
        if (!respuesta.ok) { throw new Error('No se pudo cargar ' + ruta); }
        return respuesta.json();
    }

    (async function init() {
        try {
            aplicarConfig(await cargarJson('data/config.json'));
        } catch (error) {
            console.error('[landing] configuración no disponible:', error.message);
        }
        try {
            catalogo = await cargarJson('data/catalog.json');
            renderFiltros();
            renderCatalogo();
        } catch (error) {
            console.error('[landing] catálogo no disponible:', error.message);
        }
    })();

    // elevación del header al hacer scroll (DESIGN.md: .is-scrolled)
    var header = document.getElementById('site-header');
    if (header && typeof window.addEventListener === 'function') {
        window.addEventListener('scroll', function () {
            var y = window.pageYOffset
                || (document.documentElement && document.documentElement.scrollTop)
                || 0;
            header.className = y > 8 ? 'site-header is-scrolled' : 'site-header';
        }, { passive: true });
    }

    // expuesto para pruebas (tests/validate_cp_landing_03.mjs)
    window.LANDING_TEST = { filtrarPorCategoria: filtrarPorCategoria, enlaceWhatsApp: enlaceWhatsApp };
})();
