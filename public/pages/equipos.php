<?php

// Carga las funciones comunes y comprueba los roles permitidos.
require_once __DIR__ . '/../../includes/bootstrap.php';
requerirRol(['administrador', 'operador']);

// Carga las consultas del modulo de equipos.
require_once __DIR__ . '/../../includes/equipos.php';

// Lee y valida el texto de busqueda.
$busqueda = $_GET['buscar'] ?? '';

if (!is_string($busqueda) || strlen($busqueda) > 100) {
    http_response_code(400);
    exit('Búsqueda no válida.');
}

// Elimina espacios externos.
$busqueda = trim($busqueda);

// Lee la pagina solicitada.
$paginaRecibida = $_GET['pagina'] ?? '1';

// Rechaza una pagina enviada como arreglo.
if (!is_string($paginaRecibida)) {
    http_response_code(400);
    exit('Página no válida.');
}

// Acepta solamente paginas enteras positivas.
$pagina = filter_var(
    $paginaRecibida,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($pagina === false) {
    http_response_code(400);
    exit('Página no válida.');
}

// Define las cantidades permitidas.
$opciones = ['5', '10', '20', '50'];

// Da prioridad a GET, luego a la cookie y finalmente a 10.
$cantidadRecibida = $_GET['cantidad']
    ?? $_COOKIE['sigel_equipos_cantidad']
    ?? '10';

// Valida tanto los parametros como la cookie.
if (
    !is_string($cantidadRecibida)
    || !in_array($cantidadRecibida, $opciones, true)
) {
    $cantidadRecibida = '10';
}

// Convierte la cantidad validada a entero.
$cantidad = (int) $cantidadRecibida;

// Guarda la preferencia antes de mostrar HTML.
if (isset($_GET['cantidad'])) {
    $parametros = session_get_cookie_params();

    setcookie('sigel_equipos_cantidad', $cantidadRecibida, [
        'expires' => time() + 60 * 60 * 24 * 30,
        'path' => '/',
        'secure' => $parametros['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Cuenta los resultados y calcula las paginas.
$pdo = obtenerConexion();
$total = contarEquipos($pdo, $busqueda);
$totalPaginas = max(1, (int) ceil($total / $cantidad));

// Ajusta la pagina y calcula el desplazamiento.
$pagina = min($pagina, $totalPaginas);
$inicio = ($pagina - 1) * $cantidad;

// Obtiene solamente los registros necesarios.
$equipos = listarEquipos($pdo, $busqueda, $cantidad, $inicio);

// Prepara los enlaces conservando los filtros.
$anterior = http_build_query([
    'buscar' => $busqueda,
    'cantidad' => $cantidad,
    'pagina' => max(1, $pagina - 1),
]);

$siguiente = http_build_query([
    'buscar' => $busqueda,
    'cantidad' => $cantidad,
    'pagina' => min($totalPaginas, $pagina + 1),
]);

// Define las etiquetas visibles para los estados almacenados.
$estados = [
    'disponible' => 'Disponible',
    'mantenimiento' => 'Mantenimiento',
    'baja' => 'Baja',
];
?>
<!DOCTYPE html>
<!-- Define el idioma del documento. -->
<html lang="es">
<head>
    <!-- Permite mostrar correctamente los caracteres especiales. -->
    <meta charset="UTF-8">

    <!-- Adapta la pagina al dispositivo. -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Define el titulo de la pestana. -->
    <title>Equipos - SIGEL</title>
</head>
<body>
    <!-- Muestra el titulo principal. -->
    <h1>Equipos</h1>

    <!-- Regresa al menu inicial. -->
    <p>
        <a href="/pages/inicio.php">Volver al inicio</a>
    </p>

    <!-- Consulta datos mediante GET sin modificar el inventario. -->
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

        <!-- Permite elegir la cantidad de registros. -->
        <label for="cantidad">Registros por página</label>
        <select id="cantidad" name="cantidad">

            <!-- Genera solamente las cantidades permitidas. -->
            <?php foreach ($opciones as $opcion): ?>
                <option
                    value="<?= escapar($opcion) ?>"
                    <?= $cantidadRecibida === $opcion ? 'selected' : '' ?>
                >
                    <?= escapar($opcion) ?>
                </option>
            <?php endforeach; ?>

        </select>

        <!-- Envia los filtros desde la primera pagina. -->
        <button type="submit">Buscar</button>

        <!-- Quita la busqueda y utiliza la preferencia guardada. -->
        <a href="/pages/equipos.php">Limpiar búsqueda</a>
    </form>

    <!-- Muestra la cantidad total de coincidencias. -->
    <p>Total: <?= $total ?></p>

    <!-- Organiza los equipos en filas y columnas. -->
    <table>
        <thead>
            <!-- Define los encabezados. -->
            <tr>
                <th scope="col">Código</th>
                <th scope="col">Nombre</th>
                <th scope="col">Categoría</th>
                <th scope="col">Marca</th>
                <th scope="col">Modelo</th>
                <th scope="col">Estado</th>
            </tr>
        </thead>
        <tbody>
            <!-- Genera una fila por cada equipo encontrado. -->
            <?php foreach ($equipos as $equipo): ?>
                <tr>
                    <!-- Muestra los datos escapados para evitar HTML inyectado. -->
                    <td><?= escapar($equipo['codigo']) ?></td>
                    <td><?= escapar($equipo['nombre']) ?></td>
                    <td><?= escapar($equipo['categoria']) ?></td>
                    <td><?= escapar($equipo['marca'] ?? '') ?></td>
                    <td><?= escapar($equipo['modelo'] ?? '') ?></td>
                    <td>
                        <?= escapar(
                            $estados[$equipo['estado']] ?? 'Desconocido'
                        ) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Informa cuando no existen coincidencias. -->
    <?php if ($equipos === []): ?>
        <p>No se encontraron equipos.</p>
    <?php endif; ?>

    <!-- Muestra la pagina actual y el total. -->
    <p>Página <?= $pagina ?> de <?= $totalPaginas ?></p>

    <!-- Permite retroceder cuando corresponde. -->
    <?php if ($pagina > 1): ?>
        <a href="?<?= escapar($anterior) ?>">Anterior</a>
    <?php endif; ?>

    <!-- Permite avanzar cuando corresponde. -->
    <?php if ($pagina < $totalPaginas): ?>
        <a href="?<?= escapar($siguiente) ?>">Siguiente</a>
    <?php endif; ?>
</body>
</html>