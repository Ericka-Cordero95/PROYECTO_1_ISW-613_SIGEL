<?php

//Borra los datos, vacia los datos de la sesion actual.
function cerrarSesion(): void
{
    $_SESSION = [];

    $parametros = session_get_cookie_params();

    //Una fecha pasada hace que el navegador elimine la cookie.
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $parametros['path'],
        'domain' => $parametros['domain'],
        'secure' => $parametros['secure'],
        'httponly' => $parametros['httponly'],
        'samesite' => $parametros['samesite'],
    ]);

    session_destroy();
}

//Revisa la sesion antes de mostrar una pagina protegida.
function requerirSesion(): void
{
    if (!isset($_SESSION['usuario_id'])) {
        redirigir('/index.php');
    }

    $ahora = time();
    $ultimaActividad = $_SESSION['ultima_actividad'] ?? 0;

    //Cierra la sesion despues de 15 minutos sin peticiones.
    if (
        !is_int($ultimaActividad)
        || $ahora - $ultimaActividad >= 900
    ) {
        cerrarSesion();
        redirigir('/index.php?estado=expirada');
    }

    //Comprueba que la cuenta siga existiendo y este activa.
    $pdo = obtenerConexion();

    $consulta = $pdo->prepare(
        'SELECT u.nombre, r.nombre AS rol
         FROM usuarios u
         INNER JOIN roles r ON r.id = u.rol_id
         WHERE u.id = :id
           AND u.activo = 1
           AND u.eliminado_en IS NULL
         LIMIT 1'
    );

    $consulta->execute(['id' => $_SESSION['usuario_id']]);
    $usuario = $consulta->fetch();

    if ($usuario === false) {
        cerrarSesion();
        redirigir('/index.php');
    }

    // Actualiza los datos desde la base de datos.
    $_SESSION['usuario_nombre'] = $usuario['nombre'];
    $_SESSION['usuario_rol'] = $usuario['rol'];
    $_SESSION['ultima_actividad'] = $ahora;

    // Evita guardar la página en caché.
    header('Cache-Control: no-store');
}

// Revisa la sesión y los roles permitidos.
function requerirRol(array $rolesPermitidos): void
{
    requerirSesion();

    if (!in_array($_SESSION['usuario_rol'], $rolesPermitidos, true)) {
        http_response_code(403);
        exit('No tiene permiso para ingresar.');
    }
}