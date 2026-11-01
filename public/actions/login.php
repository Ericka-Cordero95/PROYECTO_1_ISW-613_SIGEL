<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

//Esta accion solo acepta formularios enviados por POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido.');
}

//Revisa el token antes de procesar el formulario.
validarTokenCsrf();

$correo = $_POST['correo'] ?? null;
$password = $_POST['password'] ?? null;

// Mensaje para datos incorrectos.
$mensajeError = 'No se pudo iniciar sesión. Revise el correo y la contraseña.';

// Mensaje cuando la cuenta está bloqueada.
$mensajeBloqueo = 'Alcanzó 3 intentos incorrectos. Espere a que termine el bloqueo de 5 minutos.';

//Comprueba que se recibieron textos.
if (!is_string($correo) || !is_string($password)) {
    $_SESSION['error_login'] = $mensajeError;
    redirigir('/index.php');
}

//Quita espacios del correo, pero no de la contraseña.
$correo = trim($correo);

// Valida los datos tambien en el servidor.
if (
    strlen($correo) > 150
    || filter_var($correo, FILTER_VALIDATE_EMAIL) === false
    || $password === ''
    || strlen($password) > 4096
) {
    $_SESSION['error_login'] = $mensajeError;
    redirigir('/index.php');
}

$pdo = obtenerConexion();

//Busca la cuenta y su rol sin incluir usuarios eliminados.
$consulta = $pdo->prepare(
    'SELECT
        u.id,
        u.nombre,
        u.password_hash,
        u.activo,
        r.nombre AS rol,
        (
            u.bloqueado_hasta IS NOT NULL
            AND u.bloqueado_hasta > CURRENT_TIMESTAMP
        ) AS bloqueado
     FROM usuarios u
     INNER JOIN roles r ON r.id = u.rol_id
     WHERE u.correo = :correo
       AND u.eliminado_en IS NULL
     LIMIT 1'
);

$consulta->execute(['correo' => $correo]);
$usuario = $consulta->fetch();

//Rechaza cuentas inexistentes o desactivadas.
if (
    $usuario === false
    || (int) $usuario['activo'] !== 1
) {
    $_SESSION['error_login'] = $mensajeError;
    redirigir('/index.php');
}

// Si el bloqueo sigue activo no permite intentar entrar.
if ((int) $usuario['bloqueado'] === 1) {
    $_SESSION['error_login'] = $mensajeBloqueo;
    redirigir('/index.php');
}


//Comprueba si la contraseña es incorrecta.
if (!password_verify($password, $usuario['password_hash'])) {

    //Limpia los fallos si ya termino un bloqueo anterior.
    $consulta = $pdo->prepare(
        'UPDATE usuarios
         SET intentos_fallidos = 0,
             bloqueado_hasta = NULL
         WHERE id = :id
           AND bloqueado_hasta <= CURRENT_TIMESTAMP'
    );

    $consulta->execute(['id' => $usuario['id']]);

    //Suma un fallo y bloquea al tercer intento incorrecto.
    $consulta = $pdo->prepare(
        'UPDATE usuarios
         SET bloqueado_hasta = CASE
                 WHEN intentos_fallidos >= 2
                 THEN DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 5 MINUTE)
                 ELSE bloqueado_hasta
             END,
             intentos_fallidos = intentos_fallidos + 1
         WHERE id = :id'
    );

    $consulta->execute(['id' => $usuario['id']]);

    //Revisa si este intento dejo la cuenta bloqueada.
    $consulta = $pdo->prepare(
        'SELECT
            (
                bloqueado_hasta IS NOT NULL
                AND bloqueado_hasta > CURRENT_TIMESTAMP
            ) AS bloqueado
         FROM usuarios
         WHERE id = :id'
    );

    $consulta->execute(['id' => $usuario['id']]);
    $estado = $consulta->fetch();

    if ($estado !== false && (int) $estado['bloqueado'] === 1) {
        $_SESSION['error_login'] = $mensajeBloqueo;
    } else {
        $_SESSION['error_login'] = $mensajeError;
    }

    redirigir('/index.php');
}
//Un acceso correcto limpia los fallos anteriores.
$consulta = $pdo->prepare(
    'UPDATE usuarios
     SET intentos_fallidos = 0,
         bloqueado_hasta = NULL
     WHERE id = :id'
);

$consulta->execute(['id' => $usuario['id']]);

//Cambia el identificador despues de comprobar las credenciales.
session_regenerate_id(true);

//Guarda solo los datos necesarios del usuario.
$_SESSION['usuario_id'] = (int) $usuario['id'];
$_SESSION['usuario_nombre'] = $usuario['nombre'];
$_SESSION['usuario_rol'] = $usuario['rol'];
$_SESSION['ultima_actividad'] = time();

//Crea otro token para la sesion autenticada.
unset($_SESSION['csrf_token']);

redirigir('/pages/inicio.php');