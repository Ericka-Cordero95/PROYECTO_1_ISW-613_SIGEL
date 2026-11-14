<?php

// Carga las funciones comunes y comprueba los roles permitidos.
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/equipos.php';
requerirRol(['administrador', 'operador']);

// Valida el identificador del equipo.
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if (!$id) {
    http_response_code(400);
    exit('ID no válido.');
}

// Busca un equipo que siga activo en el listado.
$pdo = obtenerConexion();
$equipo = buscarEquipo($pdo, $id);

if ($equipo === false) {
    http_response_code(404);
    exit('El equipo no existe.');
}

// Guarda la visita solo cuando el operador abre un equipo existente.
require_once __DIR__ . '/../../includes/equipos_recientes.php';

if ($_SESSION['usuario_rol'] === 'operador') {
    registrarEquipoReciente((int) $_SESSION['usuario_id'], (int) $equipo['id']);
}

// Busca las imagenes asociadas al equipo.
$consulta = $pdo->prepare(
    'SELECT id
     FROM equipo_imagenes
     WHERE equipo_id = :id
     ORDER BY id'
);
$consulta->execute(['id' => $id]);
$imagenes = $consulta->fetchAll(PDO::FETCH_ASSOC);

// Recupera el mensaje del ultimo intento de carga.
$mensaje = $_SESSION['equipo_imagen_mensaje'][$id] ?? '';
unset($_SESSION['equipo_imagen_mensaje'][$id]);

$esAdministrador = $_SESSION['usuario_rol'] === 'administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de equipo - SIGEL</title>
</head>
<body>
    <!-- Muestra el equipo seleccionado. -->
    <h1><?= escapar($equipo['nombre']) ?></h1>
    <p>Código: <?= escapar($equipo['codigo']) ?></p>
    <p><a href="/pages/equipos.php">Volver a equipos</a></p>

    <!-- Muestra el resultado de la carga. -->
    <?php if ($mensaje !== ''): ?>
        <p role="status"><?= escapar($mensaje) ?></p>
    <?php endif; ?>

    <!-- Carga las imagenes mediante una ruta protegida. -->
    <h2>Imágenes</h2>
    <?php if ($imagenes === []): ?>
        <p>Este equipo todavía no tiene imágenes.</p>
    <?php endif; ?>

    <?php foreach ($imagenes as $imagen): ?>
        <img
            src="/actions/equipo_imagen_ver.php?id=<?= (int) $imagen['id'] ?>"
            alt="Imagen de <?= escapar($equipo['nombre']) ?>"
            width="240"
            loading="lazy"
        >
    <?php endforeach; ?>

    <!-- Muestra la carga solo al administrador. -->
    <?php if ($esAdministrador): ?>
        <h2>Agregar imagen</h2>
        <form
            action="/actions/equipo_imagen_subir.php"
            method="post"
            enctype="multipart/form-data"
        >
            <!-- Envia el token y el ID del equipo. -->
            <input
                type="hidden"
                name="csrf_token"
                value="<?= escapar(obtenerTokenCsrf()) ?>"
            >
            <input type="hidden" name="equipo_id" value="<?= (int) $id ?>">

            <label for="imagen">Imagen JPG, PNG o WebP, hasta 2 MB</label>
            <input
                type="file"
                id="imagen"
                name="imagen"
                accept="image/jpeg,image/png,image/webp"
                required
            >
            <button type="submit">Subir imagen</button>
        </form>
    <?php endif; ?>
</body>
</html>