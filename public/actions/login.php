<?php

// Carga las funciones comunes antes de procesar el formulario.
require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido.');
}

validarTokenCsrf();

$correo = $_POST['correo'] ?? null;
$password = $_POST['password'] ?? null;
$recordar = $_POST['recordar'] ?? '0';
$mensajeError = 'No se pudo iniciar sesión. Revise el correo y la contraseña.';
$mensajeBloqueo = 'Alcanzó 3 intentos incorrectos. Espere a que termine el bloqueo de 5 minutos.';

// Comprueba los tipos antes de validar o utilizar los campos.
if (
    !is_string($correo)
    || !is_string($password)
    || !is_string($recordar)
    || !in_array($recordar, ['0', '1'], true)
) {
    $_SESSION['error_login'] = $mensajeError;
    redirigir('/index.php');
}

$correo = trim($correo);

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

try {
    // Serializa los intentos aunque lleguen desde navegadores distintos.
    $pdo->beginTransaction();
    $consulta = $pdo->prepare(
        'SELECT u.id, u.nombre, u.password_hash, u.activo,
                u.intentos_fallidos, r.nombre AS rol,
                (u.bloqueado_hasta IS NOT NULL
                    AND u.bloqueado_hasta > CURRENT_TIMESTAMP) AS bloqueado,
                (u.bloqueado_hasta IS NOT NULL
                    AND u.bloqueado_hasta <= CURRENT_TIMESTAMP) AS bloqueo_vencido
         FROM usuarios u
         INNER JOIN roles r ON r.id = u.rol_id
         WHERE u.correo = :correo AND u.eliminado_en IS NULL
         LIMIT 1
         FOR UPDATE'
    );
    $consulta->execute(['correo' => $correo]);
    $usuario = $consulta->fetch(PDO::FETCH_ASSOC);
    $consulta->closeCursor();

    if ($usuario === false || (int) $usuario['activo'] !== 1) {
        $pdo->commit();
        $_SESSION['error_login'] = $mensajeError;
        redirigir('/index.php');
    }

    // Los intentos durante un bloqueo no cambian su vencimiento.
    if ((int) $usuario['bloqueado'] === 1) {
        $pdo->commit();
        $_SESSION['error_login'] = $mensajeBloqueo;
        redirigir('/index.php');
    }

    if (!password_verify($password, $usuario['password_hash'])) {
        $fallos = (int) $usuario['bloqueo_vencido'] === 1
            ? 1
            : min(3, (int) $usuario['intentos_fallidos'] + 1);

        $consulta = $pdo->prepare(
            'UPDATE usuarios
             SET intentos_fallidos = :fallos,
                 bloqueado_hasta = CASE WHEN :bloquear = 1
                     THEN DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 5 MINUTE)
                     ELSE NULL END
             WHERE id = :id'
        );
        $consulta->execute([
            'fallos' => $fallos,
            'bloquear' => $fallos >= 3 ? 1 : 0,
            'id' => $usuario['id'],
        ]);
        $pdo->commit();

        $_SESSION['error_login'] = $fallos >= 3 ? $mensajeBloqueo : $mensajeError;
        redirigir('/index.php');
    }

    // Limpia los intentos despues de comprobar la clave.
    $consulta = $pdo->prepare(
        'UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL
         WHERE id = :id'
    );
    $consulta->execute(['id' => $usuario['id']]);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log((string) $error);
    $_SESSION['error_login'] = 'No se pudo iniciar sesión. Intente nuevamente.';
    redirigir('/index.php');
}

// Sustituye el recordatorio anterior de este navegador.
revocarRecordatorio($pdo);
$selector = null;

if ($recordar === '1') {
    $selector = crearRecordatorio($pdo, (int) $usuario['id']);
}

// Crea la sesion autenticada con un nuevo identificador y token CSRF.
iniciarSesionUsuario($usuario, $selector);
redirigir('/pages/inicio.php');
