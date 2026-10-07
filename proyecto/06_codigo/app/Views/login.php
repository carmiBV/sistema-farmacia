<?php
declare(strict_types=1);
/** CP-FRONT-01: pantalla de login. */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión — Sistema de Farmacia</title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="/assets/js/login.js" defer></script>
</head>
<body class="login-body">
<main class="login-card">
    <h1>Sistema de Farmacia</h1>
    <p class="login-sub">Inicie sesión para continuar</p>

    <div id="login-error" class="alert alert-error" role="alert" hidden></div>

    <form id="login-form" method="post" action="/api/v1/auth/login" novalidate>
        <div class="field">
            <label for="usuario">Usuario</label>
            <input type="text" id="usuario" name="usuario" autocomplete="username" required autofocus>
        </div>
        <div class="field">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" id="login-submit" class="btn btn-primary">Ingresar</button>
    </form>
</main>
</body>
</html>
