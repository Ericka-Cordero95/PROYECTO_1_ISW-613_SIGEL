<?php

// Carga las funciones comunes y comprueba el permiso.
require_once __DIR__ . '/../../includes/bootstrap.php';
requerirRol(['administrador']);

// Carga las consultas de usuarios.
require_once __DIR__ . '/../../includes/usuarios.php';

// Obtiene la conexion y los roles disponibles.
$pdo = obtenerConexion();
$roles = listarRoles($pdo);

// Prepara los valores para una cuenta nueva.
$usuario = [
    'id' => '',
    'rol_id' => '',
    'nombre' => '',
    'correo' => '',
];

// Lee el identificador que determina si se debe editar.
$idRecibido = $_GET['id'] ?? '';
$editando = false;

// Rechaza identificadores enviados como arreglos.
if (!is_string($idRecibido)) {
    http_response_code(400);
    exit('ID no válido.');
}

// Cuando existe un identificador, carga el usuario.
if ($idRecibido !== '') {
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

    $usuario = buscarUsuario($pdo, $id);

    // Impide editar usuarios inexistentes o eliminados.
    if ($usuario === false) {
        http_response_code(404);
        exit('El usuario no existe.');
    }

    $editando = true;
}

// Recupera los mensajes y datos del ultimo envio.
$error = $_SESSION['usuario_error'] ?? '';
$datosAnteriores = $_SESSION['usuario_datos'] ?? null;

// Consume los datos temporales para mostrarlos una sola vez.
unset($_SESSION['usuario_error'], $_SESSION['usuario_datos']);

// Recupera los campos solamente si pertenecen a este formulario.
if (
    is_array($datosAnteriores)
    && ($datosAnteriores['id'] ?? null) === (string) $usuario['id']
) {
    $usuario['nombre'] = $datosAnteriores['nombre'];
    $usuario['correo'] = $datosAnteriores['correo'];
    $usuario['rol_id'] = $datosAnteriores['rol_id'];
}

// Define el titulo segun la operacion.
$titulo = $editando ? 'Editar usuario' : 'Nuevo usuario';
?>
<!DOCTYPE html>
<!-- Define el idioma del documento. -->
<html lang="es">
<head>
    <!-- Permite mostrar correctamente los caracteres especiales. -->
    <meta charset="UTF-8">

    <!-- Adapta la pagina al ancho del dispositivo. -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Muestra el nombre de la operacion en la pestana. -->
    <title><?= escapar($titulo) ?> - SIGEL</title>
</head>
<body>
    <!-- Muestra el titulo de la operacion. -->
    <h1><?= escapar($titulo) ?></h1>

    <!-- Presenta los errores sin interpretarlos como HTML. -->
    <?php if ($error !== ''): ?>
        <p role="alert"><?= escapar($error) ?></p>
    <?php endif; ?>

    <!-- Envia los datos a la accion que crea o actualiza usuarios. -->
    <form action="/actions/usuario_guardar.php" method="post">

        <!-- Envia el token que se comprobara en el servidor. -->
        <input
            type="hidden"
            name="csrf_token"
            value="<?= escapar(obtenerTokenCsrf()) ?>"
        >

        <!-- Un identificador vacio indica que se debe crear. -->
        <input
            type="hidden"
            name="id"
            value="<?= escapar((string) $usuario['id']) ?>"
        >

        <!-- Captura el nombre del usuario. -->
        <div>
            <label for="nombre">Nombre</label>
            <input
                type="text"
                id="nombre"
                name="nombre"
                maxlength="100"
                value="<?= escapar($usuario['nombre']) ?>"
                required
            >
        </div>

        <!-- Captura el correo que se validara con la regla 25. -->
        <div>
            <label for="correo">Correo electrónico</label>
            <input
                type="email"
                id="correo"
                name="correo"
                maxlength="150"
                value="<?= escapar($usuario['correo']) ?>"
                required
            >
        </div>

        <!-- Permite elegir uno de los roles obtenidos desde MySQL. -->
        <div>
            <label for="rol_id">Rol</label>
            <select id="rol_id" name="rol_id" required>
                <option value="">Seleccione un rol</option>

                <!-- Genera una opcion por cada rol disponible. -->
                <?php foreach ($roles as $rol): ?>
                    <option
                        value="<?= (int) $rol['id'] ?>"
                        <?= (string) $usuario['rol_id'] === (string) $rol['id']
                            ? 'selected'
                            : '' ?>
                    >
                        <?= escapar($rol['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Nunca recupera ni muestra la contrasena almacenada. -->
        <div>
            <label for="password">Contraseña</label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="new-password"
                <?= $editando ? '' : 'required' ?>
                aria-describedby="ayuda-password"
            >

            <!-- Explica cuando el campo es obligatorio. -->
            <p id="ayuda-password">
                Entre 8 y 72 bytes.
                <?php if ($editando): ?>
                    Déjela vacía para conservar la contraseña actual.
                <?php endif; ?>
            </p>
        </div>

        <!-- Envia los datos para su validacion y guardado. -->
        <button type="submit">Guardar</button>

        <!-- Regresa al listado sin enviar el formulario. -->
        <a href="/pages/usuarios.php">Cancelar</a>
    </form>
</body>
</html>