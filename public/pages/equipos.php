<?php

// Carga las funciones comunes y comprueba los roles permitidos.
require_once __DIR__ . '/../../includes/bootstrap.php';
requerirRol(['administrador', 'operador']);

// Carga las consultas del modulo de equipos.
require_once __DIR__ . '/../../includes/equipos.php';

// Lee el texto enviado desde el buscador.
$busqueda = $_GET['buscar'] ?? '';

// Rechaza arreglos y busquedas demasiado largas.
if (!is_string($busqueda) || strlen($busqueda) > 100) {
    http_response_code(400);
    exit('Búsqueda no válida.');
}

// Elimina espacios del inicio y final.
$busqueda = trim($busqueda);

// Lee la pagina solicitada o utiliza la primera.
$paginaRecibida = $_GET['pagina'] ?? '1';

// Rechaza una pagina enviada como arreglo.
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

// Primero utiliza GET, despues la cookie y finalmente 10.
$cantidadRecibida = $_GET['cantidad']
    ?? $_COOKIE['sigel_equipos_cantidad']
    ?? '10';

// Comprueba los valores recibidos desde GET o la cookie.
if (
    !is_string($cantidadRecibida)
    || !in_array($cantidadRecibida, $opciones, true)
) {
    $cantidadRecibida = '10';
}

// Convierte la cantidad validada a numero entero.
$cantidad = (int) $cantidadRecibida;

// Guarda la preferencia antes de producir salida HTML.
if (isset($_GET['cantidad'])) {
    // Obtiene la configuracion de seguridad de la cookie de sesion.
    $parametros = session_get_cookie_params();

    // Conserva la cantidad elegida durante 30 dias.
    setcookie('sigel_equipos_cantidad', $cantidadRecibida, [
        'expires' => time() + 60 * 60 * 24 * 30,
        'path' => '/',
        'secure' => $parametros['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Obtiene la conexion y cuenta los resultados.
$pdo = obtenerConexion();
$total = contarEquipos($pdo, $busqueda);

// Calcula el total de paginas y mantiene un minimo de una.
$totalPaginas = max(1, (int) ceil($total / $cantidad));

// Ajusta la pagina cuando supera la ultima disponible.
$pagina = min($pagina, $totalPaginas);

// Calcula cuantos registros debe saltar MySQL.
$inicio = ($pagina - 1) * $cantidad;

// Obtiene solamente los equipos de la pagina actual.
$equipos = listarEquipos(
    $pdo,
    $busqueda,
    $cantidad,
    $inicio
);

// Prepara el enlace anterior conservando los filtros.
$anterior = http_build_query([
    'buscar' => $busqueda,
    'cantidad' => $cantidad,
    'pagina' => max(1, $pagina - 1),
]);

// Prepara el enlace siguiente conservando los filtros.
$siguiente = http_build_query([
    'buscar' => $busqueda,
    'cantidad' => $cantidad,
    'pagina' => min($totalPaginas, $pagina + 1),
]);

// Define los textos visibles de los estados almacenados.
$estados = [
    'disponible' => 'Disponible',
    'mantenimiento' => 'Mantenimiento',
    'baja' => 'Baja',
];

// Recupera el mensaje una sola vez y lo elimina de la sesion.
$mensaje = $_SESSION['equipo_mensaje'] ?? '';
unset($_SESSION['equipo_mensaje']);

// Determina si se muestran las opciones administrativas.
$esAdministrador = $_SESSION['usuario_rol'] === 'administrador';
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
    <title>Equipos - SIGEL</title>
</head>
<body>
    <!-- Muestra el titulo principal del modulo. -->
    <h1>Equipos</h1>

    <!-- Muestra el resultado de la ultima operacion. -->
    <?php if ($mensaje !== ''): ?>
        <p><?= escapar($mensaje) ?></p>
    <?php endif; ?>

    <!-- Permite abrir la creacion solamente al administrador. -->
    <?php if ($esAdministrador): ?>
        <p>
            <a href="/pages/equipo_form.php">Nuevo equipo</a>
        </p>
    <?php endif; ?>

    <!-- Regresa al menu principal. -->
    <p>
        <a href="/pages/inicio.php">Volver al inicio</a>
    </p>

    <!-- Usa GET porque solamente consulta datos. -->
    <form action="/pages/equipos.php" method="get">

        <!-- Captura el codigo o nombre buscado. -->
        <label for="buscar">Buscar por código o nombre</label>
        <input
            type="text"
            id="buscar"
            name="buscar"
            maxlength="100"
            value="<?= escapar($busqueda) ?>"
        >

        <!-- Permite elegir cuantos equipos mostrar por pagina. -->
        <label for="cantidad">Registros por página</label>
        <select id="cantidad" name="cantidad">

            <!-- Genera las opciones desde la lista permitida. -->
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
        <a href="/pages/equipos.php">Limpiar búsqueda</a>
    </form>

    <!-- Muestra el total de coincidencias. -->
    <p>Total: <?= $total ?></p>

    <!-- Organiza los equipos en filas y columnas. -->
    <table>
        <thead>
            <!-- Define los nombres de las columnas. -->
            <tr>
                <th scope="col">Código</th>
                <th scope="col">Nombre</th>
                <th scope="col">Categoría</th>
                <th scope="col">Marca</th>
                <th scope="col">Modelo</th>
                <th scope="col">Estado</th>

                <!-- Muestra la columna de acciones solo al administrador. -->
                <?php if ($esAdministrador): ?>
                    <th scope="col">Acciones</th>
                <?php endif; ?>
            </tr>
        </thead>

        <tbody>
            <!-- Genera una fila por cada equipo encontrado. -->
            <?php foreach ($equipos as $equipo): ?>
                <tr>
                    <!-- Escapa el codigo antes de mostrarlo en HTML. -->
                    <td><?= escapar($equipo['codigo']) ?></td>

                    <!-- Muestra el nombre como texto seguro. -->
                    <td><?= escapar($equipo['nombre']) ?></td>

                    <!-- Muestra el nombre de la categoria relacionada. -->
                    <td><?= escapar($equipo['categoria']) ?></td>

                    <!-- Utiliza un texto vacio cuando no existe marca. -->
                    <td><?= escapar($equipo['marca'] ?? '') ?></td>

                    <!-- Utiliza un texto vacio cuando no existe modelo. -->
                    <td><?= escapar($equipo['modelo'] ?? '') ?></td>

                    <!-- Convierte el estado almacenado a su etiqueta visible. -->
                    <td>
                        <?= escapar(
                            $estados[$equipo['estado']] ?? 'Desconocido'
                        ) ?>
                    </td>

                    <!-- Muestra las acciones solamente al administrador. -->
                    <?php if ($esAdministrador): ?>
                        <td>
                            <!-- Envia el ID de esta fila al formulario. -->
                            <a href="/pages/equipo_form.php?id=<?= (int) $equipo['id'] ?>">
                                Editar
                            </a>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Informa cuando la busqueda no tiene resultados. -->
    <?php if ($equipos === []): ?>
        <p>No se encontraron equipos.</p>
    <?php endif; ?>

    <!-- Muestra la pagina actual y el total de paginas. -->
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