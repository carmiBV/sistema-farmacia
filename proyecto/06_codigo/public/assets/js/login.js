/**
 * CP-FRONT-01: login contra POST /api/v1/auth/login (Fetch API, vanilla JS).
 * - Sesión: token JWT + usuario en localStorage (sf_token / sf_user).
 * - Errores de autenticación mapeados desde error.code de la API.
 * - Redirección según rol (redireccionesPorRol; default /dashboard).
 */
(function () {
    'use strict';

    var API_LOGIN = '/api/v1/auth/login';
    var API_ME = '/api/v1/auth/me';
    var DESTINO_DEFAULT = '/dashboard';

    // ponytail: hoy todos los roles van al dashboard; ampliar por pantalla (p. ej. cajero -> /pos)
    var redireccionesPorRol = {
        admin: '/dashboard'
    };

    var form = document.getElementById('login-form');
    var cajaError = document.getElementById('login-error');
    var boton = document.getElementById('login-submit');

    var mensajesDeError = {
        INVALID_CREDENTIALS: 'Usuario o contraseña incorrectos.',
        ACCOUNT_DISABLED: 'La cuenta está deshabilitada. Contacte al administrador.',
        RATE_LIMITED: 'Demasiados intentos fallidos. Intente más tarde.',
        VALIDATION_ERROR: 'Complete usuario y contraseña.',
        UNAUTHENTICATED: 'Sesión inválida. Inicie sesión nuevamente.',
        INTERNAL_ERROR: 'Error interno del servidor. Intente más tarde.'
    };

    function destinoPorRol(roles) {
        for (var i = 0; i < (roles || []).length; i++) {
            if (redireccionesPorRol[roles[i]]) {
                return redireccionesPorRol[roles[i]];
            }
        }
        return DESTINO_DEFAULT;
    }

    function guardarSesion(data) {
        localStorage.setItem('sf_token', data.token);
        localStorage.setItem('sf_user', JSON.stringify(data.user || {}));
    }

    function mostrarError(mensaje) {
        cajaError.textContent = mensaje;
        cajaError.hidden = false;
    }

    function limpiarError() {
        cajaError.hidden = true;
        cajaError.textContent = '';
    }

    async function yaHaySesion() {
        var token = localStorage.getItem('sf_token');
        if (!token) {
            return;
        }
        try {
            var r = await fetch(API_ME, { headers: { Authorization: 'Bearer ' + token } });
            if (r.ok) {
                var body = await r.json();
                window.location.replace(destinoPorRol((body.data || {}).roles));
            } else {
                localStorage.removeItem('sf_token');
                localStorage.removeItem('sf_user');
            }
        } catch (e) {
            // sin conexión: no intercambiar el formulario
        }
    }

    async function enviar(evento) {
        evento.preventDefault();
        limpiarError();

        var usuario = form.usuario.value.trim();
        var password = form.password.value;
        if (!usuario || !password) {
            mostrarError(mensajesDeError.VALIDATION_ERROR);
            return;
        }

        boton.disabled = true;
        try {
            var r = await fetch(API_LOGIN, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ usuario: usuario, password: password })
            });
            var body = await r.json().catch(function () { return null; });

            if (r.ok && body && body.success) {
                guardarSesion(body.data);
                window.location.replace(destinoPorRol((body.data.user || {}).roles));
                return;
            }

            var codigo = (body && body.error && body.error.code) || 'INTERNAL_ERROR';
            mostrarError(mensajesDeError[codigo] || 'No se pudo iniciar sesión.');
        } catch (e) {
            mostrarError('Error de conexión con el servidor.');
        } finally {
            boton.disabled = false;
        }
    }

    form.addEventListener('submit', enviar);
    yaHaySesion();
})();
