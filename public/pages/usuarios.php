<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

// Solo el administrador puede entrar.
requerirRol(['administrador']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Usuarios - SIGEL</title>
</head>
<body>
    <h1>Usuarios</h1>

    <p>Aquí se administrarán los usuarios.</p>

    <a href="/pages/inicio.php">Volver al inicio</a>
</body>
</html>