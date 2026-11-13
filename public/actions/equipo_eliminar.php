<?php

// Carga las funciones comunes y comprueba el permiso.
require_once __DIR__ . '/../../includes/bootstrap.php';
requerirRol(['administrador']);

// Acepta solamente solicitudes POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido.');
}

// Comprueba el token antes de modificar el equipo.
validarTokenCsrf();

// Carga la funcion de bitacora.
require_once __DIR__ . '/../../includes/bitacora.php';

// Valida el identificador recibido.
$idRecibido = $_POST['id'] ?? null;
$id = is_string($idRecibido)
    ? filter_var($idRecibido, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ])
    : false;

if ($id === false) {
    http_response_code(400);
    exit('ID no válido.');
}

$pdo = obtenerConexion();

try {
    // Inicia la transaccion y bloquea el equipo.
    $pdo->beginTransaction();

    $consulta = $pdo->prepare(
        'SELECT id, categoria_id, codigo, nombre, marca, modelo,
                numero_serie, estado, version, eliminado_en
         FROM equipos
         WHERE id = :id
         FOR UPDATE'
    );

    $consulta->execute(['id' => $id]);
    $antes = $consulta->fetch(PDO::FETCH_ASSOC);

    if ($antes === false || $antes['eliminado_en'] !== null) {
        $pdo->rollBack();
        $_SESSION['equipo_mensaje'] = 'El equipo no existe o ya fue eliminado.';
        redirigir('/pages/equipos.php');
    }

    // Marca la eliminacion e incrementa la version.
    $actualizar = $pdo->prepare(
        'UPDATE equipos
         SET eliminado_en = CURRENT_TIMESTAMP,
             version = version + 1
         WHERE id = :id'
    );

    $actualizar->execute(['id' => $id]);

    // Lee los valores guardados para registrarlos en la bitacora.
    $consulta->execute(['id' => $id]);
    $despues = $consulta->fetch(PDO::FETCH_ASSOC);

    if ($despues === false) {
        throw new RuntimeException('No se pudo recuperar el equipo eliminado.');
    }

    registrarBitacora(
        $pdo,
        (int) $_SESSION['usuario_id'],
        'ELIMINAR',
        'equipos',
        $id,
        $antes,
        $despues
    );

    // Confirma la eliminacion y su evento juntos.
    $pdo->commit();
    $_SESSION['equipo_mensaje'] = 'Equipo eliminado correctamente.';
} catch (Throwable $error) {
    // Revierte los cambios y registra el error tecnico.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log((string) $error);
    $_SESSION['equipo_mensaje'] = 'No se pudo eliminar el equipo.';
}

// Regresa al listado.
redirigir('/pages/equipos.php');