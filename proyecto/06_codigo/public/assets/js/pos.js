/**
 * CP-FRONT-09: punto de venta (Fetch API, vanilla JS).
 * Busqueda por SKU/codigo de barras, lote FEFO automatico (mas proximo a
 * vencer, liberado, con stock), calculo de importes y cobro con multiples
 * medios de pago. Comprobante listo para impresion.
 */
(function () {
    'use strict';

    var token = localStorage.getItem('sf_token');
    var stores = [];
    var registers = [];
    var products = [];
    var stocks = [];
    var prices = [];
    var carrito = [];
    var pagos = [];
    var cajaError = document.getElementById('app-error');

    function mostrarError(m) { cajaError.textContent = m; cajaError.hidden = false; }
    function limpiarError() { cajaError.hidden = true; }
    function sesionCaducada() {
        localStorage.removeItem('sf_token');
        localStorage.removeItem('sf_user');
        window.location.replace('/login');
    }

    async function api(ruta, metodo, cuerpo) {
        var r = await fetch(ruta, {
            method: metodo || 'GET',
            headers: cuerpo ? { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' }
                : { Authorization: 'Bearer ' + token },
            body: cuerpo ? JSON.stringify(cuerpo) : undefined,
        });
        if (r.status === 401) { sesionCaducada(); throw new Error('sesion'); }
        var body = await r.json().catch(function () { return null; });
        if (!r.ok && r.status !== 204) {
            throw new Error((body && body.error && body.error.message) || 'Error en la operación.');
        }
        return body;
    }

    function fila(celdas) {
        var tr = document.createElement('tr');
        celdas.forEach(function (c) {
            var td = document.createElement('td');
            td.textContent = c;
            tr.appendChild(td);
        });
        return tr;
    }

    function boton(texto, alClic) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn btn-mini';
        b.textContent = texto;
        b.addEventListener('click', alClic);
        return b;
    }

    function fmt(n) { return Number(n || 0).toFixed(2); }

    /** Lote FEFO: liberado, no vencido y con stock; el de vencimiento mas proximo. */
    function loteFefo(productId) {
        var hoy = new Date().toISOString().slice(0, 10);
        return stocks
            .filter(function (s) {
                return String(s.product_id) === String(productId)
                    && String(s.store_id) === document.getElementById('pos-store').value
                    && s.lote_estado === 'liberado'
                    && s.stock_available > 0
                    && s.fecha_vencimiento >= hoy;
            })
            .sort(function (a, b) { return a.fecha_vencimiento < b.fecha_vencimiento ? -1 : 1; })[0] || null;
    }

    function precioUnitario(productId) {
        var hoy = new Date().toISOString().slice(0, 10);
        var store = document.getElementById('pos-store').value;
        var candidatos = prices.filter(function (p) {
            return String(p.product_id) === String(productId)
                && (!p.store_id || String(p.store_id) === store)
                && p.vigente_desde <= hoy
                && (!p.vigente_hasta || p.vigente_hasta >= hoy);
        });
        // prefiere precio de sucursal sobre global
        candidatos.sort(function (a, b) { return (b.store_id ? 1 : 0) - (a.store_id ? 1 : 0); });
        return candidatos.length ? Number(candidatos[0].precio) : null;
    }

    function renderCarrito() {
        var tbody = document.querySelector('#pos-tabla tbody');
        tbody.textContent = '';
        var total = 0;
        carrito.forEach(function (it, idx) {
            var subtotal = it.precio_unitario * it.cantidad;
            total += subtotal;
            var tr = fila([it.product_nombre, it.numero_lote, it.fecha_vencimiento,
                fmt(it.precio_unitario), String(it.cantidad), fmt(subtotal), '']);
            var input = document.createElement('input');
            input.type = 'number';
            input.min = '1';
            input.step = '1';
            input.value = String(it.cantidad);
            input.className = 'input-mini';
            input.addEventListener('change', function () {
                var c = parseInt(input.value, 10) || 1;
                carrito[idx].cantidad = Math.max(1, Math.min(c, it.stock_available));
                renderCarrito();
            });
            tr.children[4].textContent = '';
            tr.children[4].appendChild(input);
            tr.lastChild.appendChild(boton('Quitar', function () {
                carrito.splice(idx, 1);
                renderCarrito();
            }));
            tbody.appendChild(tr);
        });
        document.getElementById('pos-total').textContent = fmt(total);
    }

    function renderPagos() {
        var ul = document.getElementById('pago-lista');
        ul.textContent = '';
        pagos.forEach(function (p, idx) {
            var li = document.createElement('li');
            li.textContent = p.medio + ' — ' + fmt(p.monto) + ' ';
            li.appendChild(boton('Quitar', function () {
                pagos.splice(idx, 1);
                renderPagos();
            }));
            ul.appendChild(li);
        });
    }

    function agregarAlCarrito() {
        limpiarError();
        var codigo = document.getElementById('pos-codigo').value.trim().toLowerCase();
        if (codigo === '') { return; }
        var producto = products.filter(function (p) { return p.sku.toLowerCase() === codigo; })[0];
        if (!producto) {
            mostrarError('Producto no encontrado para el código "' + codigo + '".');
            return;
        }
        var lote = loteFefo(producto.id);
        if (!lote) {
            mostrarError('Sin lote liberado con stock para "' + producto.nombre + '" en esta sucursal.');
            return;
        }
        var precio = precioUnitario(producto.id);
        if (precio === null) {
            mostrarError('El producto "' + producto.nombre + '" no tiene precio vigente.');
            return;
        }
        var existente = carrito.filter(function (c) { return String(c.lot_id) === String(lote.lot_id); })[0];
        if (existente) {
            if (existente.cantidad < existente.stock_available) {
                existente.cantidad += 1;
            }
        } else {
            carrito.push({
                lot_id: lote.lot_id,
                numero_lote: lote.numero_lote,
                fecha_vencimiento: lote.fecha_vencimiento,
                product_id: producto.id,
                product_nombre: producto.nombre,
                precio_unitario: precio,
                stock_available: lote.stock_available,
                cantidad: 1,
            });
        }
        document.getElementById('pos-codigo').value = '';
        renderCarrito();
    }

    async function cargarContexto() {
        var rs = await Promise.all([
            api('/api/v1/ops/stores?limit=100'),
            api('/api/v1/ops/registers?limit=100'),
            api('/api/v1/catalog/patients?limit=100'),
            api('/api/v1/catalog/products?limit=100'),
            api('/api/v1/inventory/stocks?limit=100'),
            api('/api/v1/catalog/prices?limit=100'),
        ]);
        stores = (rs[0].data || []).filter(function (s) { return s.estado === 'activa'; });
        registers = (rs[1].data || []).filter(function (c) { return c.estado === 'activa'; });
        products = rs[3].data || [];
        stocks = rs[4].data || [];
        prices = rs[5].data || [];

        var selStore = document.getElementById('pos-store');
        selStore.textContent = '';
        stores.forEach(function (s) {
            var o = document.createElement('option');
            o.value = String(s.id);
            o.textContent = s.nombre;
            selStore.appendChild(o);
        });
        var previa = localStorage.getItem('sf_store') || '';
        if (previa && stores.some(function (s) { return String(s.id) === previa; })) {
            selStore.value = previa;
        }
        if (!selStore.value && selStore.options.length > 0) {
            selStore.value = selStore.options[0].value;
        }
        llenarCajas();

        var selPac = document.getElementById('pos-paciente');
        (rs[2].data || []).filter(function (p) { return p.estado === 'activo'; }).forEach(function (p) {
            var o = document.createElement('option');
            o.value = String(p.id);
            o.textContent = p.nombre;
            selPac.appendChild(o);
        });
    }

    function llenarCajas() {
        var sel = document.getElementById('pos-register');
        sel.textContent = '';
        registers
            .filter(function (c) { return String(c.store_id) === document.getElementById('pos-store').value; })
            .forEach(function (c) {
                var o = document.createElement('option');
                o.value = String(c.id);
                o.textContent = c.codigo;
                sel.appendChild(o);
            });
        if (!sel.value && sel.options.length > 0) {
            sel.value = sel.options[0].value;
        }
    }

    document.getElementById('pos-store').addEventListener('change', function () {
        localStorage.setItem('sf_store', document.getElementById('pos-store').value);
        carrito = [];
        renderCarrito();
        llenarCajas();
    });

    document.getElementById('pos-buscar').addEventListener('submit', function (e) {
        e.preventDefault();
        agregarAlCarrito();
    });

    document.getElementById('pago-agregar').addEventListener('click', function () {
        limpiarError();
        var monto = parseFloat(document.getElementById('pago-monto').value);
        if (!monto || monto <= 0) {
            mostrarError('Ingrese un monto de pago válido.');
            return;
        }
        pagos.push({ medio: document.getElementById('pago-medio').value, monto: monto });
        document.getElementById('pago-monto').value = '';
        renderPagos();
    });

    document.getElementById('pos-cobrar').addEventListener('click', async function () {
        limpiarError();
        if (carrito.length === 0) {
            mostrarError('El carrito está vacío.');
            return;
        }
        if (pagos.length === 0) {
            mostrarError('Agregue al menos un pago.');
            return;
        }
        var total = carrito.reduce(function (a, it) { return a + it.precio_unitario * it.cantidad; }, 0);
        var pagado = pagos.reduce(function (a, p) { return a + p.monto; }, 0);
        if (pagado + 0.001 < total) {
            mostrarError('Los pagos (' + fmt(pagado) + ') no cubren el total (' + fmt(total) + ').');
            return;
        }
        var cuerpo = {
            store_id: parseInt(document.getElementById('pos-store').value, 10),
            register_id: parseInt(document.getElementById('pos-register').value, 10),
            items: carrito.map(function (it) {
                return { lot_id: it.lot_id, cantidad: it.cantidad, precio_unitario: it.precio_unitario };
            }),
            idempotency_key: 'pos-' + Date.now() + '-' + Math.floor(Math.random() * 1000),
        };
        var pac = document.getElementById('pos-paciente').value;
        if (pac !== '') { cuerpo.paciente_id = parseInt(pac, 10); }
        try {
            var r = await api('/api/v1/sales/orders', 'POST', cuerpo);
            var orden = r.data.orden || r.data;
            for (var i = 0; i < pagos.length; i++) {
                await api('/api/v1/sales/orders/' + orden.id + '/payments', 'POST', {
                    medio: pagos[i].medio,
                    monto: pagos[i].monto,
                });
            }
            var lineas = carrito.map(function (it) {
                return '<p>' + it.product_nombre + ' · ' + it.numero_lote + ' × ' + it.cantidad +
                    ' — ' + fmt(it.precio_unitario * it.cantidad) + '</p>';
            }).join('');
            var lineasPagos = pagos.map(function (p) {
                return '<p>Pago ' + p.medio + ': ' + fmt(p.monto) + '</p>';
            }).join('');
            document.getElementById('comprobante-detalle').innerHTML =
                '<p><strong>Venta #' + orden.id + '</strong></p>' + lineas +
                '<p><strong>Total: ' + fmt(total) + '</strong></p>' + lineasPagos +
                '<p>Vuelto: ' + fmt(pagado - total) + '</p>';
            document.getElementById('comprobante').hidden = false;
            carrito = [];
            pagos = [];
            renderCarrito();
            renderPagos();
        } catch (err) {
            if (err.message !== 'sesion') { mostrarError(err.message); }
        }
    });

    document.getElementById('comprobante-imprimir').addEventListener('click', function () {
        window.print();
    });

    cargarContexto().catch(function (err) {
        if (err.message !== 'sesion') { mostrarError(err.message); }
    });
})();
