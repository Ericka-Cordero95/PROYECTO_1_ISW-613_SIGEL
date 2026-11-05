<?php

// Carga la configuracion, la sesion y las funciones comunes.
require_once __DIR__ . '/../../includes/bootstrap.php';

// Permite eliminar solamente al administrador.
requerirRol(['administrador']);

// Solo acepta solicitudes enviadas mediante POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido.');
}

// Comprueba el token antes de modificar datos.
validarTokenCsrf();

// Carga la funcion que registra los eventos.
require_once __DIR__ . '/../../includes/bitacora.php';

// Lee el identificador enviado desde la fila del listado.
$idRecibido = $_POST['id'] ?? null;

// Rechaza campos ausentes o enviados como arreglos.
if (!is_string($idRecibido)) {
    http_response_code(400);
    exit('ID no válido.');
}

// Comprueba que el identificador sea un entero positivo.
$id = filter_var(
    $idRecibido,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

// Detiene la solicitud cuando el identificador no es valido.
if ($id === false) {
    http_response_code(400);
    exit('ID no válido.');
}

// Obtiene la conexion compartida por la operacion y la bitacora.
$pdo = obtenerConexion();

try {
    // Inicia la transaccion antes de consultar y modificar la fila.
    $pdo->beginTransaction();

    // Recupera los datos anteriores y bloquea la categoria.
    $consulta = $pdo->prepare(
        'SELECT id, nombre, dias_max_prestamo, eliminado_en
         FROM categorias
         WHERE id = :id
           AND eliminado_en IS NULL
         FOR UPDATE'
    );

    $consulta->execute(['id' => $id]);
    $antes = $consulta->fetch(PDO::FETCH_ASSOC);

    // Libera la transaccion si el registro no esta disponible.
    if ($antes === false) {
        $pdo->rollBack();

        $_SESSION['categoria_mensaje'] =
            'La categoría no existe o ya fue eliminada.';

        redirigir('/pages/categorias.php');
    }

    // Realiza la eliminacion logica sin borrar el registro.
    $consulta = $pdo->prepare(
        'UPDATE categorias
         SET eliminado_en = NOW()
         WHERE id = :id
           AND eliminado_en IS NULL'
    );

    $consulta->execute(['id' => $id]);

    // Recupera los datos posteriores con la fecha exacta guardada.
    $consulta = $pdo->prepare(
        'SELECT id, nombre, dias_max_prestamo, eliminado_en
         FROM categorias
         WHERE id = :id'
    );

    $consulta->execute(['id' => $id]);
    $despues = $consulta->fetch(PDO::FETCH_ASSOC);

    // Impide confirmar si no se pudo recuperar el registro.
    if ($despues === false) {
        throw new RuntimeException(
            'No se pudo recuperar la categoria eliminada.'
        );
    }

    // Registra la eliminacion con los valores anteriores y posteriores.
    registrarBitacora(
        $pdo,
        (int) $_SESSION['usuario_id'],
        'ELIMINAR',
        'categorias',
        $id,
        $antes,
        $despues
    );

    // Confirma juntos la eliminacion logica y su evento.
    $pdo->commit();

    // Prepara el mensaje solamente despues de confirmar.
    $_SESSION['categoria_mensaje'] =
        'Categoría eliminada correctamente.';
} catch (Throwable $error) {
    // Revierte la eliminacion si falla la operacion o la bitacora.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Guarda el detalle tecnico sin mostrarlo al usuario.
    error_log((string) $error);

    $_SESSION['categoria_mensaje'] =
        'No se pudo eliminar la categoría.';
}

// Regresa al listado mediante una nueva solicitud GET.
redirigir('/pages/categorias.php');