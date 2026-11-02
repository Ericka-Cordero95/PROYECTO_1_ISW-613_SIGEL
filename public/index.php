<?php

//Carga las funciones comunes y la sesion.
require_once __DIR__ . '/../includes/bootstrap.php';

// Muestra el mensaje una vez.
$mensaje = $_SESSION['error_login'] ?? '';
unset($_SESSION['error_login']);

//Solo muestra mensajes definidos por nosotros.
$estado = $_GET['estado'] ?? '';

if ($mensaje === '' && is_string($estado)) {
    if ($estado === 'cerrada') {
        $mensaje = 'Sesión cerrada correctamente.';
    } elseif ($estado === 'expirada') {
        $mensaje = 'La sesión terminó por inactividad. Ingrese nuevamente.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar sesión - SIGEL</title>
</head>
<body>
    <h1>SIGEL</h1>
    <h2>Iniciar sesión</h2>

    <?php if ($mensaje !== ''): ?>
        <p><?= escapar($mensaje) ?></p>
    <?php endif; ?>

    <form action="/actions/login.php" method="post">
        <!-- Envia el token de esta sesion. -->
        <input
            type="hidden"
            name="csrf_token"
            value="<?= escapar(obtenerTokenCsrf()) ?>"
        >

        <div>
            <label for="correo">Correo</label>
            <input
                type="email"
                id="correo"
                name="correo"
                maxlength="150"
                autocomplete="username"
                required
            >
        </div>

        <div>
            <label for="password">Contraseña</label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >
        </div>

        <button type="submit">Entrar</button>
    </form>
</body>
</html>