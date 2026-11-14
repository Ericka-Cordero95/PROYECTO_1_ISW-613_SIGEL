<?php

// Carga las funciones comunes y comprueba el rol.
require_once __DIR__ . '/../../includes/bootstrap.php';
requerirRol(['operador']);

$pdo = obtenerConexion();

// Busca las cuentas que pueden recibir un prestamo.
$usuarios = $pdo->query(
    'SELECT id, nombre, correo
     FROM usuarios
     WHERE activo = 1 AND eliminado_en IS NULL
     ORDER BY nombre, id'
)->fetchAll(PDO::FETCH_ASSOC);

// Muestra los equipos de categorias activas.
$equipos = $pdo->query(
    "SELECT e.id, e.codigo, e.nombre, c.dias_max_prestamo
     FROM equipos e
     INNER JOIN categorias c ON c.id = e.categoria_id
     WHERE e.eliminado_en IS NULL
       AND c.eliminado_en IS NULL
       AND e.estado = 'disponible'
     ORDER BY e.codigo, e.id"
)->fetchAll(PDO::FETCH_ASSOC);

// Muestra el resultado del ultimo envio una sola vez.
$mensaje = $_SESSION['prestamo_mensaje'] ?? '';
unset($_SESSION['prestamo_mensaje']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo préstamo - SIGEL</title>
</head>
<body>
    <h1>Nuevo préstamo</h1>
    <p><a href="/pages/inicio.php">Volver al inicio</a></p>

    <!-- Muestra si la solicitud se guardo o fue rechazada. -->
    <?php if ($mensaje !== ''): ?>
        <p role="status"><?= escapar($mensaje) ?></p>
    <?php endif; ?>

    <?php if ($usuarios === [] || $equipos === []): ?>
        <p>Se necesita al menos una cuenta activa y un equipo disponible.</p>
    <?php else: ?>
        <!-- Envia la solicitud y el token a la accion de guardado. -->
        <form action="/actions/prestamo_guardar.php" method="post">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= escapar(obtenerTokenCsrf()) ?>"
            >

            <p>
                <label for="usuario_id">Persona que recibe el equipo</label>
                <select id="usuario_id" name="usuario_id" required>
                    <option value="">Seleccione una persona</option>
                    <?php foreach ($usuarios as $usuario): ?>
                        <option value="<?= (int) $usuario['id'] ?>">
                            <?= escapar($usuario['nombre']) ?>
                            (<?= escapar($usuario['correo']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <!-- Permite seleccionar varios equipos para la misma solicitud. -->
            <fieldset>
                <legend>Equipos</legend>
                <?php foreach ($equipos as $equipo): ?>
                    <label>
                        <input
                            type="checkbox"
                            name="equipos[]"
                            value="<?= (int) $equipo['id'] ?>"
                        >
                        <?= escapar($equipo['codigo']) ?> —
                        <?= escapar($equipo['nombre']) ?>
                        (máximo <?= (int) $equipo['dias_max_prestamo'] ?> días)
                    </label>
                    <br>
                <?php endforeach; ?>
            </fieldset>

            <!-- Captura el inicio y el fin previsto. -->
            <p>
                <label for="fecha_inicio">Inicio</label>
                <input type="datetime-local" id="fecha_inicio" name="fecha_inicio" required>
            </p>
            <p>
                <label for="fecha_fin">Fin previsto</label>
                <input type="datetime-local" id="fecha_fin" name="fecha_fin" required>
            </p>

            <button type="submit">Registrar solicitud</button>
        </form>
    <?php endif; ?>
</body>
</html>