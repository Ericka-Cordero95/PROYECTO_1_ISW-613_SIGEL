<?php

// Carga las funciones comunes y comprueba el permiso.
require_once __DIR__ . '/../../includes/bootstrap.php';
requerirRol(['administrador']);

// Solo permite formularios enviados mediante POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido.');
}

// Comprueba el token antes de procesar los campos.
validarTokenCsrf();

// Carga la validacion de dominios y el registro de operaciones.
require_once __DIR__ . '/../../includes/configuracion.php';
require_once __DIR__ . '/../../includes/bitacora.php';

// Lee los campos enviados por el formulario.
$idRecibido = $_POST['id'] ?? null;
$nombre = $_POST['nombre'] ?? null;
$correo = $_POST['correo'] ?? null;
$rolRecibido = $_POST['rol_id'] ?? null;
$password = $_POST['password'] ?? null;

// Rechaza campos ausentes o enviados como arreglos.
if (
    !is_string($idRecibido)
    || !is_string($nombre)
    || !is_string($correo)
    || !is_string($rolRecibido)
    || !is_string($password)
) {
    http_response_code(400);
    exit('Datos no válidos.');
}

// Decide si la accion debe crear o editar.
$editando = $idRecibido !== '';
$id = null;
$volver = '/pages/usuario_form.php';

// Valida el identificador para las ediciones.
if ($editando) {
    $id = filter_var(
        $idRecibido,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id === false) {
        http_response_code(400);
        exit('ID no válido.');
    }

    $volver .= '?id=' . $id;
}

// Limpia los espacios externos sin modificar la contrasena.
$nombre = trim($nombre);
$correo = trim($correo);

// Conserva los campos recuperables sin guardar la contrasena.
$_SESSION['usuario_datos'] = [
    'id' => $editando ? (string) $id : '',
    'nombre' => $nombre,
    'correo' => $correo,
    'rol_id' => $rolRecibido,
];

// Valida el nombre usando el mismo limite de bytes de categorias.
if ($nombre === '' || strlen($nombre) > 100) {
    $_SESSION['usuario_error'] =
        'Ingrese un nombre de hasta 100 bytes.';

    redirigir($volver);
}

// Comprueba la longitud y el formato del correo.
if (
    strlen($correo) > 150
    || filter_var($correo, FILTER_VALIDATE_EMAIL) === false
) {
    $_SESSION['usuario_error'] =
        'Ingrese un correo válido de hasta 150 bytes.';

    redirigir($volver);
}

// Comprueba que el identificador del rol sea un entero positivo.
$rolId = filter_var(
    $rolRecibido,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($rolId === false) {
    $_SESSION['usuario_error'] = 'Seleccione un rol válido.';
    redirigir($volver);
}

// Exige una contrasena al crear y permite conservarla al editar.
if (
    (!$editando && $password === '')
    || (
        $password !== ''
        && (
            strlen($password) < 8
            || strlen($password) > 72
            || str_contains($password, "\0")
        )
    )
) {
    $_SESSION['usuario_error'] =
        'La contraseña nueva debe tener entre 8 y 72 bytes y no contener caracteres nulos.';

    redirigir($volver);
}

// Obtiene la conexion compartida por la escritura y la bitacora.
$pdo = obtenerConexion();

try {
    // Aplica la regla 25 tanto al crear como al editar.
    if (!correoTieneDominioPermitido($pdo, $correo)) {
        $_SESSION['usuario_error'] =
            'El dominio del correo no está permitido.';

        redirigir($volver);
    }

    // Comprueba el rol en MySQL aunque el formulario haya sido alterado.
    $consulta = $pdo->prepare(
        'SELECT nombre
         FROM roles
         WHERE id = :id
           AND nombre IN (:administrador, :operador)'
    );

    $consulta->execute([
        'id' => $rolId,
        'administrador' => 'administrador',
        'operador' => 'operador',
    ]);

    $rolNombre = $consulta->fetchColumn();

    if ($rolNombre === false) {
        $_SESSION['usuario_error'] = 'Seleccione un rol válido.';
        redirigir($volver);
    }

    // Evita que el administrador pierda su propio acceso por accidente.
    if (
        $editando
        && $id === (int) $_SESSION['usuario_id']
        && $rolNombre !== 'administrador'
    ) {
        $_SESSION['usuario_error'] =
            'No puede quitarse su propio rol de administrador.';

        redirigir($volver);
    }

    // Genera el hash solamente cuando se envio una contrasena nueva.
    $hash = $password === ''
        ? null
        : password_hash($password, PASSWORD_DEFAULT);

    // Inicia la transaccion para guardar el usuario y su evento.
    $pdo->beginTransaction();

    // Las creaciones no tienen datos anteriores.
    $antes = null;

    if ($editando) {
        // Bloquea la fila y obtiene datos seguros para la bitacora.
        $consulta = $pdo->prepare(
            'SELECT id, rol_id, nombre, correo, activo
             FROM usuarios
             WHERE id = :id
               AND eliminado_en IS NULL
             FOR UPDATE'
        );

        $consulta->execute(['id' => $id]);
        $antes = $consulta->fetch(PDO::FETCH_ASSOC);

        // Cancela la operacion si el usuario ya no esta disponible.
        if ($antes === false) {
            $pdo->rollBack();

            unset(
                $_SESSION['usuario_datos'],
                $_SESSION['usuario_error']
            );

            $_SESSION['usuario_mensaje'] =
                'El usuario no existe o ya fue eliminado.';

            redirigir('/pages/usuarios.php');
        }

        // Actualiza los campos generales conservando el estado activo.
        $consulta = $pdo->prepare(
            'UPDATE usuarios
             SET nombre = :nombre,
                 correo = :correo,
                 rol_id = :rol_id
             WHERE id = :id
               AND eliminado_en IS NULL'
        );

        $consulta->execute([
            'nombre' => $nombre,
            'correo' => $correo,
            'rol_id' => $rolId,
            'id' => $id,
        ]);

        // Cambia la contrasena solamente cuando se recibio una nueva.
        if ($hash !== null) {
            $consulta = $pdo->prepare(
                'UPDATE usuarios
                 SET password_hash = :hash
                 WHERE id = :id'
            );

            $consulta->execute([
                'hash' => $hash,
                'id' => $id,
            ]);

            // Revoca los recordatorios anteriores al cambio de clave.
            $consulta = $pdo->prepare(
                'DELETE FROM auth_tokens WHERE usuario_id = :id'
            );
            $consulta->execute(['id' => $id]);
        }

        $accion = 'EDITAR';
    } else {
        // Crea una cuenta activa usando los valores predeterminados.
        $consulta = $pdo->prepare(
            'INSERT INTO usuarios (
                rol_id, nombre, correo, password_hash
             ) VALUES (
                :rol_id, :nombre, :correo, :hash
             )'
        );

        $consulta->execute([
            'rol_id' => $rolId,
            'nombre' => $nombre,
            'correo' => $correo,
            'hash' => $hash,
        ]);

        // Obtiene el ID antes de insertar el evento de bitacora.
        $id = (int) $pdo->lastInsertId();
        $accion = 'CREAR';
    }

    // Recupera los datos guardados sin incluir el hash.
    $consulta = $pdo->prepare(
        'SELECT id, rol_id, nombre, correo, activo
         FROM usuarios
         WHERE id = :id'
    );

    $consulta->execute(['id' => $id]);
    $despues = $consulta->fetch(PDO::FETCH_ASSOC);

    if ($despues === false) {
        throw new RuntimeException(
            'No se pudo recuperar el usuario guardado.'
        );
    }

    // Indica si hubo cambio de contrasena sin registrar su contenido.
    $despues['contrasena_actualizada'] = $hash !== null;

    // Inserta el evento usando la misma transaccion.
    registrarBitacora(
        $pdo,
        (int) $_SESSION['usuario_id'],
        $accion,
        'usuarios',
        $id,
        $antes,
        $despues
    );

    // Confirma juntos el usuario y su evento.
    $pdo->commit();
} catch (Throwable $error) {
    // Revierte la escritura cuando falla alguna parte.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Identifica especificamente la restriccion de correo unico.
    $correoDuplicado =
        $error instanceof PDOException
        && (int) ($error->errorInfo[1] ?? 0) === 1062
        && str_contains($error->getMessage(), 'uq_usuarios_correo');

    if ($correoDuplicado) {
        $_SESSION['usuario_error'] =
            'Ya existe un usuario con ese correo.';
    } else {
        // Registra el detalle tecnico sin mostrarlo en pantalla.
        error_log((string) $error);

        $_SESSION['usuario_error'] =
            'No se pudo guardar el usuario.';
    }

    redirigir($volver);
}

// Limpia los campos temporales despues del guardado correcto.
unset($_SESSION['usuario_datos'], $_SESSION['usuario_error']);

// Regresa al listado mediante GET para evitar repetir el envio.
$_SESSION['usuario_mensaje'] = 'Usuario guardado correctamente.';
redirigir('/pages/usuarios.php');