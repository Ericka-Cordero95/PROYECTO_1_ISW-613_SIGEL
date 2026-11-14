<?php

// Carga la aplicacion y exige el rol operador.
require_once __DIR__ . '/../../includes/bootstrap.php';
requerirRol(['operador']);

$pdo = obtenerConexion();

// Trae una fila por equipo para mostrar el detalle completo.
$consulta = $pdo->query(
    'SELECT p.id, p.estado, p.fecha_inicio, p.fecha_fin,
            u.nombre AS persona, e.codigo, e.nombre AS equipo,
            d.fecha_devolucion
     FROM prestamos p
     INNER JOIN usuarios u ON u.id = p.usuario_id
     INNER JOIN prestamo_detalle d ON d.prestamo_id = p.id
     INNER JOIN equipos e ON e.id = d.equipo_id
     ORDER BY p.id DESC, e.codigo'
);

// Agrupa los equipos por prestamo.
$prestamos = [];
while ($fila = $consulta->fetch(PDO::FETCH_ASSOC)) {
    $id = (int) $fila['id'];
    if (!isset($prestamos[$id])) {
        $prestamos[$id] = $fila;
        $prestamos[$id]['equipos'] = [];
    }

    $prestamos[$id]['equipos'][] = $fila;
}

// Define los botones visibles para cada estado.
$acciones = [
    'solicitado' => ['aprobado' => 'Aprobar', 'cancelado' => 'Cancelar'],
    'aprobado' => ['entregado' => 'Entregar', 'cancelado' => 'Cancelar'],
    'entregado' => ['devuelto' => 'Devolver'],
];

// Recupera el resultado de la ultima operacion.
$mensaje = $_SESSION['prestamos_mensaje'] ?? '';
unset($_SESSION['prestamos_mensaje']);
$token = obtenerTokenCsrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Préstamos - SIGEL</title>
</head>
<body>
    <h1>Préstamos</h1>

    <!-- Enlaces del modulo. -->
    <p><a href="/pages/prestamo_form.php">Nuevo préstamo</a></p>
    <p><a href="/pages/inicio.php">Volver al inicio</a></p>

    <!-- Muestra el resultado de la ultima operacion. -->
    <?php if ($mensaje !== ''): ?>
        <p role="status"><?= escapar($mensaje) ?></p>
    <?php endif; ?>

    <?php if ($prestamos === []): ?>
        <p>Todavía no hay préstamos registrados.</p>
    <?php endif; ?>

    <!-- Muestra una seccion por prestamo con todos sus equipos. -->
    <?php foreach ($prestamos as $id => $prestamo): ?>
        <section>
            <h2>Préstamo #<?= $id ?></h2>
            <p>Persona: <?= escapar($prestamo['persona']) ?></p>
            <p>Estado: <?= escapar($prestamo['estado']) ?></p>
            <p>
                Inicio: <?= escapar($prestamo['fecha_inicio']) ?> |
                Fin previsto: <?= escapar($prestamo['fecha_fin']) ?>
            </p>

            <!-- Muestra el codigo y la devolucion de cada equipo. -->
            <ul>
                <?php foreach ($prestamo['equipos'] as $equipo): ?>
                    <li>
                        <?= escapar($equipo['codigo']) ?> —
                        <?= escapar($equipo['equipo']) ?>
                        <?php if ($equipo['fecha_devolucion'] !== null): ?>
                            (devuelto el <?= escapar($equipo['fecha_devolucion']) ?>)
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <!-- Envia la accion elegida por POST. -->
            <?php if (isset($acciones[$prestamo['estado']])): ?>
                <form action="/actions/prestamo_estado.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= escapar($token) ?>">
                    <input type="hidden" name="id" value="<?= $id ?>">

                    <?php foreach ($acciones[$prestamo['estado']] as $estado => $etiqueta): ?>
                        <button type="submit" name="estado" value="<?= escapar($estado) ?>">
                            <?= escapar($etiqueta) ?>
                        </button>
                    <?php endforeach; ?>
                </form>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</body>
</html>