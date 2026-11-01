<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

// Sin usuario autenticado, vuelve al login.
if (!isset($_SESSION['usuario_id'])) {
    redirigir('/index.php');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inicio - SIGEL</title>
</head>
<body>
    <h1>Inicio de SIGEL</h1>

    <p>
        Usuario:
        <?= escapar($_SESSION['usuario_nombre']) ?>
    </p>

    <p>
        Rol:
        <?= escapar($_SESSION['usuario_rol']) ?>
    </p>
</body>
</html>