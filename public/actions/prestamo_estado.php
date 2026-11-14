<?php

// Carga la aplicacion, la bitacora y el permiso del operador.
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/bitacora.php';
requerirRol(['operador']);

// Acepta solo solicitudes POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido.');
}

validarTokenCsrf();

// Lee el prestamo y el estado solicitado.
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$nuevoEstado = $_POST['estado'] ?? null;

if (!$id || !is_string($nuevoEstado)) {
    http_response_code(400);
    exit('Datos no válidos.');
}

// Define los cambios permitidos desde cada estado.
$transiciones = [
    'solicitado' => ['aprobado', 'cancelado'],
    'aprobado' => ['entregado', 'cancelado'],
    'entregado' => ['devuelto'],
];

$pdo = obtenerConexion();

try {
    // Bloquea el prestamo antes de revisar su estado actual.
    $pdo->beginTransaction();

    $consulta = $pdo->prepare(
        'SELECT id, estado, fecha_inicio, fecha_fin
         FROM prestamos
         WHERE id = :id
         FOR UPDATE'
    );
    $consulta->execute(['id' => $id]);
    $antes = $consulta->fetch(PDO::FETCH_ASSOC);
    $consulta->closeCursor();

    if ($antes === false) {
        throw new DomainException('El préstamo no existe.');
    }

    // Rechaza acciones que ya no corresponden al estado actual.
    $permitidos = $transiciones[$antes['estado']] ?? [];
    if (!in_array($nuevoEstado, $permitidos, true)) {
        throw new DomainException('Ese cambio no está permitido para el estado actual.');
    }

    // Guarda el nuevo estado.
    $actualizar = $pdo->prepare(
        'UPDATE prestamos SET estado = :estado WHERE id = :id'
    );
    $actualizar->execute(['estado' => $nuevoEstado, 'id' => $id]);

    $despues = $antes;
    $despues['estado'] = $nuevoEstado;

    // Marca la devolucion de todos los equipos del prestamo.
    if ($nuevoEstado === 'devuelto') {
        $fecha = new DateTimeImmutable('now', new DateTimeZone('America/Costa_Rica'));
        $fechaDevolucion = $fecha->format('Y-m-d H:i:s');

        $devolver = $pdo->prepare(
            'UPDATE prestamo_detalle
             SET fecha_devolucion = :fecha
             WHERE prestamo_id = :id'
        );
        $devolver->execute(['fecha' => $fechaDevolucion, 'id' => $id]);
        $despues['fecha_devolucion'] = $fechaDevolucion;
    }

    // Guarda el cambio y su evento en la misma transaccion.
    registrarBitacora(
        $pdo,
        (int) $_SESSION['usuario_id'],
        'EDITAR',
        'prestamos',
        $id,
        $antes,
        $despues
    );

    $pdo->commit();
    $_SESSION['prestamos_mensaje'] = 'Estado del préstamo actualizado.';
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($error instanceof DomainException) {
        $_SESSION['prestamos_mensaje'] = $error->getMessage();
    } else {
        error_log((string) $error);
        $_SESSION['prestamos_mensaje'] = 'No se pudo actualizar el préstamo.';
    }
}

// Vuelve al listado mediante GET.
redirigir('/pages/prestamos.php');