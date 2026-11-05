<?php

// Carga la configuracion, la sesion y las funciones comunes.
require_once __DIR__ . '/../../includes/bootstrap.php';

// Comprueba el permiso tambien en la accion.
requerirRol(['administrador']);

// Solo acepta formularios enviados mediante POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido.');
}

// Comprueba el token antes de procesar los datos.
validarTokenCsrf();

// Carga la funcion que registra los eventos.
require_once __DIR__ . '/../../includes/bitacora.php';

// Lee los campos enviados por el formulario.
$idRecibido = $_POST['id'] ?? null;
$nombre = $_POST['nombre'] ?? null;
$diasRecibidos = $_POST['dias'] ?? null;

// Rechaza campos ausentes o enviados como arreglos.
if (
    !is_string($idRecibido)
    || !is_string($nombre)
    || !is_string($diasRecibidos)
) {
    $_SESSION['categoria_error'] = 'Datos no válidos.';
    redirigir('/pages/categoria_form.php');
}

// Decide si la solicitud corresponde a crear o editar.
$editando = $idRecibido !== '';
$id = null;
$volver = '/pages/categoria_form.php';

// Valida el identificador cuando se solicita editar.
if ($editando) {
    $id = filter_var(
        $idRecibido,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    // Rechaza identificadores que no sean enteros positivos.
    if ($id === false) {
        http_response_code(400);
        exit('ID no válido.');
    }

    // Conserva el identificador al regresar al formulario.
    $volver .= '?id=' . $id;
}

// Quita espacios del inicio y final del nombre.
$nombre = trim($nombre);

// Conserva los datos para recuperarlos si ocurre un error.
$_SESSION['categoria_datos'] = [
    'id' => $editando ? (string) $id : '',
    'nombre' => $nombre,
    'dias' => $diasRecibidos,
];

// Valida el nombre en el servidor.
if ($nombre === '' || strlen($nombre) > 100) {
    $_SESSION['categoria_error'] =
        'Ingrese un nombre de hasta 100 bytes.';

    redirigir($volver);
}

// Acepta solamente dias enteros entre 1 y 365.
$dias = filter_var(
    $diasRecibidos,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 365]]
);

// Regresa al formulario cuando los dias no son validos.
if ($dias === false) {
    $_SESSION['categoria_error'] =
        'Los días deben estar entre 1 y 365.';

    redirigir($volver);
}

// Obtiene la conexion que usaran la categoria y la bitacora.
$pdo = obtenerConexion();

try {
    // Inicia una transaccion para guardar ambos registros juntos.
    $pdo->beginTransaction();

    // Una creacion no tiene valores anteriores.
    $antes = null;

    if ($editando) {
        // Lee y bloquea la fila hasta terminar la transaccion.
        $consulta = $pdo->prepare(
            'SELECT id, nombre, dias_max_prestamo, eliminado_en
             FROM categorias
             WHERE id = :id
               AND eliminado_en IS NULL
             FOR UPDATE'
        );

        $consulta->execute(['id' => $id]);
        $antes = $consulta->fetch(PDO::FETCH_ASSOC);

        // Libera la transaccion si la categoria no existe.
        if ($antes === false) {
            $pdo->rollBack();

            unset(
                $_SESSION['categoria_datos'],
                $_SESSION['categoria_error']
            );

            $_SESSION['categoria_mensaje'] =
                'La categoría no existe o ya fue eliminada.';

            redirigir('/pages/categorias.php');
        }

        // Actualiza los datos de la categoria bloqueada.
        $consulta = $pdo->prepare(
            'UPDATE categorias
             SET nombre = :nombre,
                 dias_max_prestamo = :dias
             WHERE id = :id
               AND eliminado_en IS NULL'
        );

        $consulta->execute([
            'nombre' => $nombre,
            'dias' => $dias,
            'id' => $id,
        ]);

        // Identifica el tipo de evento que se registrara.
        $accion = 'EDITAR';
    } else {
        // Inserta una categoria nueva.
        $consulta = $pdo->prepare(
            'INSERT INTO categorias (nombre, dias_max_prestamo)
             VALUES (:nombre, :dias)'
        );

        $consulta->execute([
            'nombre' => $nombre,
            'dias' => $dias,
        ]);

        // Recupera el ID antes de insertar el evento de bitacora.
        $id = (int) $pdo->lastInsertId();

        // Identifica el tipo de evento que se registrara.
        $accion = 'CREAR';
    }

    // Consulta los valores que realmente quedaron guardados.
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
            'No se pudo recuperar la categoria guardada.'
        );
    }

    // Registra el usuario, la operacion y los valores del cambio.
    registrarBitacora(
        $pdo,
        (int) $_SESSION['usuario_id'],
        $accion,
        'categorias',
        $id,
        $antes,
        $despues
    );

    // Confirma juntos el cambio de categoria y el evento.
    $pdo->commit();
} catch (Throwable $error) {
    // Revierte todos los cambios pendientes si algo fallo.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Identifica la restriccion de nombre duplicado de categorias.
    $nombreDuplicado =
        $error instanceof PDOException
        && (int) ($error->errorInfo[1] ?? 0) === 1062
        && str_contains($error->getMessage(), 'uq_categorias_nombre');

    if ($nombreDuplicado) {
        // Explica el duplicado sin mostrar detalles de MySQL.
        $_SESSION['categoria_error'] =
            'Ya existe una categoría con ese nombre.';
    } else {
        // Guarda el detalle tecnico solamente en el registro de errores.
        error_log((string) $error);

        $_SESSION['categoria_error'] =
            'No se pudo guardar la categoría.';
    }

    // Conserva los datos del formulario para intentar nuevamente.
    redirigir($volver);
}

// Limpia los datos temporales despues de confirmar la transaccion.
unset(
    $_SESSION['categoria_datos'],
    $_SESSION['categoria_error']
);

// Prepara el mensaje y regresa al listado mediante GET.
$_SESSION['categoria_mensaje'] =
    'Categoría guardada correctamente.';

redirigir('/pages/categorias.php');