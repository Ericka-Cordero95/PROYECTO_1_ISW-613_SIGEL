<?php

// Carga la configuracion, la sesion y las funciones comunes.
require_once __DIR__ . '/../../includes/bootstrap.php';

// Permite esta operacion solamente al administrador.
requerirRol(['administrador']);

// Rechaza solicitudes que no utilicen POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido.');
}

// Comprueba el token antes de procesar la eliminacion.
validarTokenCsrf();

// Carga la funcion que registra las operaciones.
require_once __DIR__ . '/../../includes/bitacora.php';

// Lee el identificador enviado por el formulario.
$idRecibido = $_POST['id'] ?? null;

// Rechaza campos ausentes o enviados como arreglos.
if (!is_string($idRecibido)) {
    http_response_code(400);
    exit('ID no válido.');
}

// Acepta solamente un identificador entero positivo.
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

// Obtiene el usuario responsable desde la sesion.
$usuarioResponsable = (int) $_SESSION['usuario_id'];

// Impide que el administrador elimine su propia cuenta.
if ($id === $usuarioResponsable) {
    $_SESSION['usuario_mensaje'] =
        'No puede eliminar su propia cuenta.';

    redirigir('/pages/usuarios.php');
}

// Obtiene la conexion compartida por la operacion y la bitacora.
$pdo = obtenerConexion();

try {
    // Inicia la transaccion antes de leer y modificar el usuario.
    $pdo->beginTransaction();

    // Bloquea el registro y recupera datos sin incluir su contrasena.
    $consulta = $pdo->prepare(
        'SELECT
            id,
            rol_id,
            nombre,
            correo,
            activo,
            eliminado_en
         FROM usuarios
         WHERE id = :id
           AND eliminado_en IS NULL
         FOR UPDATE'
    );

    $consulta->execute(['id' => $id]);
    $antes = $consulta->fetch(PDO::FETCH_ASSOC);

    // Cancela la operacion si el usuario no esta disponible.
    if ($antes === false) {
        $pdo->rollBack();

        $_SESSION['usuario_mensaje'] =
            'El usuario no existe o ya fue eliminado.';

        redirigir('/pages/usuarios.php');
    }

    // Marca el usuario como eliminado e inactivo sin borrar su fila.
    $consulta = $pdo->prepare(
        'UPDATE usuarios
         SET eliminado_en = CURRENT_TIMESTAMP,
             activo = 0
         WHERE id = :id
           AND eliminado_en IS NULL'
    );

    $consulta->execute(['id' => $id]);

    // Recupera los valores que realmente quedaron guardados.
    $consulta = $pdo->prepare(
        'SELECT
            id,
            rol_id,
            nombre,
            correo,
            activo,
            eliminado_en
         FROM usuarios
         WHERE id = :id'
    );

    $consulta->execute(['id' => $id]);
    $despues = $consulta->fetch(PDO::FETCH_ASSOC);

    // Impide confirmar si no se pudo recuperar el usuario.
    if ($despues === false) {
        throw new RuntimeException(
            'No se pudo recuperar el usuario eliminado.'
        );
    }

    // Registra la operacion sin incluir contrasenas ni hashes.
    registrarBitacora(
        $pdo,
        $usuarioResponsable,
        'ELIMINAR',
        'usuarios',
        $id,
        $antes,
        $despues
    );

    // Confirma juntos la eliminacion logica y su evento.
    $pdo->commit();

    // Prepara el mensaje despues de confirmar la operacion.
    $_SESSION['usuario_mensaje'] =
        'Usuario eliminado correctamente.';
} catch (Throwable $error) {
    // Deshace todos los cambios pendientes si ocurre un fallo.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Registra el detalle tecnico sin mostrarlo al usuario.
    error_log((string) $error);

    $_SESSION['usuario_mensaje'] =
        'No se pudo eliminar el usuario.';
}

// Regresa al listado mediante una nueva solicitud GET.
redirigir('/pages/usuarios.php');