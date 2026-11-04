<?php

//Carga la sesion y las funciones comunes.
require_once __DIR__ . '/../../includes/bootstrap.php';

//Solo permite entrar al administrador.
requerirRol(['administrador']);

//Carga las consultas de categorias.
require_once __DIR__ . '/../../includes/categorias.php';

//Lee el texto enviado desde el buscador.
$busqueda = $_GET['buscar'] ?? '';

//Rechaza arreglos o busquedas demasiado largas.
if (!is_string($busqueda) || strlen($busqueda) > 100) {
    http_response_code(400);
    exit('Búsqueda no válida.');
}

//Quita espacios del inicio y final.
$busqueda = trim($busqueda);

//Si no se indica una pagina, utiliza la primera.
$paginaRecibida = $_GET['pagina'] ?? '1';

//Comprueba que la pagina no sea un arreglo.
if (!is_string($paginaRecibida)) {
    http_response_code(400);
    exit('Página no válida.');
}

//Acepta solamente numeros enteros desde 1.
$pagina = filter_var(
    $paginaRecibida,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

//Detiene la solicitud si la pagina no es valida.
if ($pagina === false) {
    http_response_code(400);
    exit('Página no válida.');
}

//Define las cantidades permitidas para la paginacion.
$opciones = ['5', '10', '20', '50'];

//Primero usa el formulario, despues la cookie y finalmente 10.
$cantidadRecibida = $_GET['cantidad']
    ?? $_COOKIE['sigel_categorias_cantidad']
    ?? '10';

//Si el valor no esta permitido, utiliza 10.
if (
    !is_string($cantidadRecibida)
    || !in_array($cantidadRecibida, $opciones, true)
) {
    $cantidadRecibida = '10';
}

//Convierte la cantidad a entero para calcular las paginas.
$cantidad = (int) $cantidadRecibida;

//Guarda la preferencia cuando llega desde el formulario o enlace.
if (isset($_GET['cantidad'])) {
    //obtiene la configuracion de seguridad de la cookie de sesion.
    $parametros = session_get_cookie_params();

    //Guarda la cantidad elegida durante 30 dias.
    setcookie('sigel_categorias_cantidad', $cantidadRecibida, [
        'expires' => time() + 60 * 60 * 24 * 30,
        'path' => '/',
        'secure' => $parametros['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

//Abre la conexion y cuenta los resultados de la busqueda.
$pdo = obtenerConexion();
$total = contarCategorias($pdo, $busqueda);

//Calcula las paginas y mantiene al menos una aunque no haya resultados.
$totalPaginas = max(1, (int) ceil($total / $cantidad));

//Evita pedir una pagina mayor que la ultima.
$pagina = min($pagina, $totalPaginas);

//Calcula cuantos registros debe saltar la consulta.
$inicio = ($pagina - 1) * $cantidad;

//Obtiene solamente las categorias de esta pagina.
$categorias = listarCategorias(
    $pdo,
    $busqueda,
    $cantidad,
    $inicio
);

//Lee el mensaje del ultimo guardado o eliminacion y despues lo borra.
$mensaje = $_SESSION['categoria_mensaje'] ?? '';
unset($_SESSION['categoria_mensaje']);

//Prepara el enlace anterior conservando los filtros.
$anterior = http_build_query([
    'buscar' => $busqueda,
    'cantidad' => $cantidad,
    'pagina' => max(1, $pagina - 1),
]);

//Prepara el enlace siguiente conservando los filtros.
$siguiente = http_build_query([
    'buscar' => $busqueda,
    'cantidad' => $cantidad,
    'pagina' => min($totalPaginas, $pagina + 1),
]);
?>
<!DOCTYPE html>
<!-- Define el idioma del documento. -->
<html lang="es">
<head>
    <!-- Permite mostrar correctamente los caracteres especiales. -->
    <meta charset="UTF-8">

    <!-- Adapta el ancho de la pagina a la pantalla del dispositivo. -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Define el nombre que aparece en la pestana del navegador. -->
    <title>Categorías - SIGEL</title>
</head>
<body>
    <!-- Muestra el titulo principal de la pagina. -->
    <h1>Categorías</h1>

    <!-- Muestra el resultado del ultimo guardado o eliminacion. -->
    <?php if ($mensaje !== ''): ?>
        <p><?= escapar($mensaje) ?></p>
    <?php endif; ?>

    <!-- Abre el formulario sin ID para crear una categoria. -->
    <p>
        <a href="/pages/categoria_form.php">Nueva categoría</a>
    </p>

    <!-- Regresa al menu inicial. -->
    <p>
        <a href="/pages/inicio.php">Volver al inicio</a>
    </p>

    <!-- Usa GET porque el formulario solamente consulta datos. -->
    <form action="/pages/categorias.php" method="get">

        <!-- Captura el nombre que se quiere buscar. -->
        <label for="buscar">Buscar por nombre</label>
        <input
            type="text"
            id="buscar"
            name="buscar"
            maxlength="100"
            value="<?= escapar($busqueda) ?>"
        >

        <!-- Permite elegir cuantos registros mostrar por pagina. -->
        <label for="cantidad">Registros por página</label>
        <select id="cantidad" name="cantidad">

            <!-- Genera una opcion por cada cantidad permitida. -->
            <?php foreach ($opciones as $opcion): ?>
                <option
                    value="<?= escapar($opcion) ?>"
                    <?= $cantidadRecibida === $opcion ? 'selected' : '' ?>
                >
                    <?= escapar($opcion) ?>
                </option>
            <?php endforeach; ?>

        </select>

        <!-- Envia la busqueda y la cantidad elegida. -->
        <button type="submit">Buscar</button>
    </form>

    <!-- Muestra el total de categorias que coinciden con la busqueda. -->
    <p>Total: <?= $total ?></p>

    <!-- Organiza las categorias en filas y columnas. -->
    <table>
        <thead>
            <!-- Define los nombres de las columnas. -->
            <tr>
                <th scope="col">ID</th>
                <th scope="col">Nombre</th>
                <th scope="col">Días máximos de préstamo</th>
                <th scope="col">Acciones</th>
            </tr>
        </thead>

        <tbody>
            <!-- Genera una fila por cada categoria encontrada. -->
            <?php foreach ($categorias as $categoria): ?>
                <tr>
                    <!-- Muestra el identificador como numero entero. -->
                    <td><?= (int) $categoria['id'] ?></td>

                    <!-- Escapa el nombre antes de mostrarlo en HTML. -->
                    <td><?= escapar($categoria['nombre']) ?></td>

                    <!-- Muestra el limite de dias como numero entero. -->
                    <td><?= (int) $categoria['dias_max_prestamo'] ?></td>

                    <!-- Agrupa las acciones de la categoria de esta fila. -->
                    <td>
                        <!-- Envia el ID al formulario para cargar sus datos. -->
                        <a href="/pages/categoria_form.php?id=<?= (int) $categoria['id'] ?>">
                            Editar
                        </a>

                        <!-- Solicita confirmacion y envia la eliminacion por POST. -->
                        <form
                            action="/actions/categoria_eliminar.php"
                            method="post"
                            onsubmit="return confirm('¿Desea eliminar esta categoría?');"
                        >
                            <!-- Envia el token que se comprobara en el servidor. -->
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= escapar(obtenerTokenCsrf()) ?>"
                            >

                            <!-- Envia el ID de la categoria de esta fila. -->
                            <input
                                type="hidden"
                                name="id"
                                value="<?= (int) $categoria['id'] ?>"
                            >

                            <!-- Envia la solicitud para eliminar la categoria. -->
                            <button type="submit">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Avisa cuando no existen resultados para la busqueda. -->
    <?php if ($categorias === []): ?>
        <p>No se encontraron categorías.</p>
    <?php endif; ?>

    <!-- Muestra la pagina actual y el total de paginas. -->
    <p>Página <?= $pagina ?> de <?= $totalPaginas ?></p>

    <!-- Muestra Anterior solamente si existe una pagina previa. -->
    <?php if ($pagina > 1): ?>
        <a href="?<?= escapar($anterior) ?>">Anterior</a>
    <?php endif; ?>

    <!-- Muestra Siguiente solamente si quedan mas paginas. -->
    <?php if ($pagina < $totalPaginas): ?>
        <a href="?<?= escapar($siguiente) ?>">Siguiente</a>
    <?php endif; ?>
</body>
</html>