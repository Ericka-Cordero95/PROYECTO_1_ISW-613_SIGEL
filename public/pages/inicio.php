<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

// Revisa la sesión antes de mostrar contenido.
requerirSesion();
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

<?php if ($_SESSION['usuario_rol'] === 'administrador'): ?>
    <p>
        <a href="/pages/usuarios.php">Administrar usuarios</a>
    </p>

    <p>
        <a href="/pages/categorias.php">Administrar categorías</a>
    </p>
<?php endif; ?>

<!-- Abre el inventario que pueden consultar ambos roles. -->
<p>
    <a href="/pages/equipos.php">Consultar equipos</a>
</p>

    <form action="/actions/logout.php" method="post">
        <!-- Protege el cierre de sesión. -->
        <input
            type="hidden"
            name="csrf_token"
            value="<?= escapar(obtenerTokenCsrf()) ?>"
        >

        <button type="submit">Cerrar sesión</button>
    </form>
</body>
</html>