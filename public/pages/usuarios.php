<?php

// Carga la configuracion, la sesion y las funciones comunes.
require_once __DIR__ . '/../../includes/bootstrap.php';

// Permite entrar solamente al administrador.
requerirRol(['administrador']);

// Carga las consultas del modulo de usuarios.
require_once __DIR__ . '/../../includes/usuarios.php';

// Lee la busqueda o utiliza un texto vacio.
$busqueda = $_GET['buscar'] ?? '';

// Rechaza arreglos y textos demasiado largos.
if (!is_string($busqueda) || strlen($busqueda) > 150) {
    http_response_code(400);
    exit('Búsqueda no válida.');
}

// Quita espacios del inicio y final.
$busqueda = trim($busqueda);

// Lee la pagina solicitada o utiliza la primera.
$paginaRecibida = $_GET['pagina'] ?? '1';

// Rechaza paginas enviadas como arreglos.
if (!is_string($paginaRecibida)) {
    http_response_code(400);
    exit('Página no válida.');
}

// Acepta solamente numeros enteros positivos.
$pagina = filter_var(
    $paginaRecibida,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

// Detiene la solicitud si la pagina no es valida.
if ($pagina === false) {
    http_response_code(400);
    exit('Página no válida.');
}

// Define las cantidades permitidas para el listado.
$opciones = ['5', '10', '20', '50'];

// Primero usa GET, despues la cookie y finalmente el valor por defecto.
$cantidadRecibida = $_GET['cantidad']
    ?? $_COOKIE['sigel_usuarios_cantidad']
    ?? '10';

// Trata el formulario y la cookie como entradas no confiables.
if (
    !is_string($cantidadRecibida)
    || !in_array($cantidadRecibida, $opciones, true)
) {
    $cantidadRecibida = '10';
}

// Convierte la cantidad validada a numero entero.
$cantidad = (int) $cantidadRecibida;

// Guarda la preferencia antes de producir cualquier salida HTML.
if (isset($_GET['cantidad'])) {
    // Reutiliza el criterio de HTTPS de la cookie de sesion.
    $parametros = session_get_cookie_params();

    // Conserva solamente la cantidad de registros durante 30 dias.
    setcookie('sigel_usuarios_cantidad', $cantidadRecibida, [
        'expires' => time() + 60 * 60 * 24 * 30,
        'path' => '/',
        'secure' => $parametros['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Obtiene la conexion y cuenta los resultados de la busqueda.
$pdo = obtenerConexion();
$total = contarUsuarios($pdo, $busqueda);

// Calcula el total de paginas con un minimo de una.
$totalPaginas = max(1, (int) ceil($total / $cantidad));

// Ajusta las solicitudes que superen la ultima pagina.
$pagina = min($pagina, $totalPaginas);

// Calcula cuantos registros debe saltar la consulta.
$inicio = ($pagina - 1) * $cantidad;

// Recupera solamente los usuarios de la pagina actual.
$usuarios = listarUsuarios(
    $pdo,
    $busqueda,
    $cantidad,
    $inicio
);

// Conserva los filtros al retroceder de pagina.
$anterior = http_build_query([
    'buscar' => $busqueda,
    'cantidad' => $cantidad,
    'pagina' => max(1, $pagina - 1),
]);

// Conserva los filtros al avanzar de pagina.
$siguiente = http_build_query([
    'buscar' => $busqueda,
    'cantidad' => $cantidad,
    'pagina' => min($totalPaginas, $pagina + 1),
]);

// Recupera el mensaje una sola vez y lo elimina de la sesion.
$mensaje = $_SESSION['usuario_mensaje'] ?? '';
unset($_SESSION['usuario_mensaje']);

// Identifica la cuenta actual para no mostrar su boton de eliminar.
$usuarioActual = (int) $_SESSION['usuario_id'];
?>
<!DOCTYPE html>
<!-- Define el idioma del documento. -->
<html lang="es">
<head>
    <!-- Permite mostrar correctamente los caracteres especiales. -->
    <meta charset="UTF-8">

    <!-- Adapta la pagina al ancho del dispositivo. -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Define el titulo de la pestana del navegador. -->
    <title>Usuarios - SIGEL</title>
</head>
<body>
    <!-- Muestra el titulo principal del modulo. -->
    <h1>Usuarios</h1>

    <!-- Muestra el resultado de la ultima operacion. -->
    <?php if ($mensaje !== ''): ?>
        <p><?= escapar($mensaje) ?></p>
    <?php endif; ?>

    <!-- Abre el formulario sin ID para crear una cuenta. -->
    <p>
        <a href="/pages/usuario_form.php">Nuevo usuario</a>
    </p>

    <!-- Regresa al menu principal. -->
    <p>
        <a href="/pages/inicio.php">Volver al inicio</a>
    </p>

    <!-- Usa GET porque la busqueda solamente consulta datos. -->
    <form action="/pages/usuarios.php" method="get">

        <!-- Captura el nombre o correo que se quiere buscar. -->
        <label for="buscar">Buscar por nombre o correo</label>
        <input
            type="text"
            id="buscar"
            name="buscar"
            maxlength="150"
            value="<?= escapar($busqueda) ?>"
        >

        <!-- Permite elegir la cantidad de registros por pagina. -->
        <label for="cantidad">Registros por página</label>
        <select id="cantidad" name="cantidad">

            <!-- Genera las opciones usando la misma lista validada en PHP. -->
            <?php foreach ($opciones as $opcion): ?>
                <option
                    value="<?= escapar($opcion) ?>"
                    <?= $cantidadRecibida === $opcion ? 'selected' : '' ?>
                >
                    <?= escapar($opcion) ?>
                </option>
            <?php endforeach; ?>

        </select>

        <!-- Envia los filtros y comienza desde la primera pagina. -->
        <button type="submit">Buscar</button>

        <!-- Quita la busqueda y conserva la preferencia mediante la cookie. -->
        <a href="/pages/usuarios.php">Limpiar búsqueda</a>
    </form>

    <!-- Muestra el total de coincidencias. -->
    <p>Total: <?= $total ?></p>

    <!-- Organiza los datos y acciones de cada usuario. -->
    <table>
        <thead>
            <!-- Define los encabezados de las columnas. -->
            <tr>
                <th scope="col">ID</th>
                <th scope="col">Nombre</th>
                <th scope="col">Correo</th>
                <th scope="col">Rol</th>
                <th scope="col">Estado</th>
                <th scope="col">Acciones</th>
            </tr>
        </thead>

        <tbody>
            <!-- Genera una fila por cada usuario encontrado. -->
            <?php foreach ($usuarios as $usuario): ?>
                <tr>
                    <!-- Muestra el identificador como numero entero. -->
                    <td><?= (int) $usuario['id'] ?></td>

                    <!-- Escapa el nombre antes de mostrarlo. -->
                    <td><?= escapar($usuario['nombre']) ?></td>

                    <!-- Escapa el correo antes de mostrarlo. -->
                    <td><?= escapar($usuario['correo']) ?></td>

                    <!-- Muestra el rol obtenido desde MySQL. -->
                    <td><?= escapar($usuario['rol']) ?></td>

                    <!-- Convierte el estado almacenado a un texto legible. -->
                    <td>
                        <?= (int) $usuario['activo'] === 1
                            ? 'Activo'
                            : 'Inactivo' ?>
                    </td>

                    <!-- Agrupa las operaciones del usuario de esta fila. -->
                    <td>
                        <!-- Envia el ID al formulario de edicion. -->
                        <a href="/pages/usuario_form.php?id=<?= (int) $usuario['id'] ?>">
                            Editar
                        </a>

                        <!-- Oculta la eliminacion de la cuenta actual. -->
                        <?php if ((int) $usuario['id'] !== $usuarioActual): ?>

                            <!-- Solicita confirmacion y envia la eliminacion por POST. -->
                            <form
                                action="/actions/usuario_eliminar.php"
                                method="post"
                                onsubmit="return confirm('¿Desea eliminar este usuario?');"
                            >
                                <!-- Envia el token para comprobarlo en el servidor. -->
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= escapar(obtenerTokenCsrf()) ?>"
                                >

                                <!-- Identifica el usuario que se quiere eliminar. -->
                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int) $usuario['id'] ?>"
                                >

                                <!-- Envia la solicitud de eliminacion logica. -->
                                <button type="submit">Eliminar</button>
                            </form>

                        <?php else: ?>

                            <!-- Explica por que esta fila no permite eliminar. -->
                            <span>Cuenta actual</span>

                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Informa cuando la busqueda no encuentra usuarios. -->
    <?php if ($usuarios === []): ?>
        <p>No se encontraron usuarios.</p>
    <?php endif; ?>

    <!-- Muestra la pagina actual y la cantidad total de paginas. -->
    <p>Página <?= $pagina ?> de <?= $totalPaginas ?></p>

    <!-- Permite retroceder si existe una pagina anterior. -->
    <?php if ($pagina > 1): ?>
        <a href="?<?= escapar($anterior) ?>">Anterior</a>
    <?php endif; ?>

    <!-- Permite avanzar si existe una pagina siguiente. -->
    <?php if ($pagina < $totalPaginas): ?>
        <a href="?<?= escapar($siguiente) ?>">Siguiente</a>
    <?php endif; ?>
</body>
</html>