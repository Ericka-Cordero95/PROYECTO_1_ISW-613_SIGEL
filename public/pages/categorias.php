<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

//Solo el administrador gestiona categorias.
requerirRol(['administrador']);

require_once __DIR__ . '/../../includes/categorias.php';

//Lee y valida el texto de busqueda.
$busqueda = $_GET['buscar'] ?? '';

if (!is_string($busqueda) || strlen($busqueda) > 100) {
    http_response_code(400);
    exit('Búsqueda no válida.');
}

$busqueda = trim($busqueda);

//Lee la pagina solicitada.
$paginaRecibida = $_GET['pagina'] ?? '1';

if (!is_string($paginaRecibida)) {
    http_response_code(400);
    exit('Página no válida.');
}

$pagina = filter_var(
    $paginaRecibida,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($pagina === false) {
    http_response_code(400);
    exit('Página no válida.');
}

//Solo acepta estas cantidades de registros.
$opciones = ['5', '10', '20', '50'];
$cantidadRecibida = $_GET['cantidad']
    ?? $_COOKIE['sigel_categorias_cantidad']
    ?? '10';

if (
    !is_string($cantidadRecibida)
    || !in_array($cantidadRecibida, $opciones, true)
) {
    $cantidadRecibida = '10';
}

$cantidad = (int) $cantidadRecibida;

//Guarda la preferencia cuando se envia desde el formulario.
if (isset($_GET['cantidad'])) {
    $parametros = session_get_cookie_params();

    setcookie('sigel_categorias_cantidad', $cantidadRecibida, [
        'expires' => time() + 60 * 60 * 24 * 30,
        'path' => '/',
        'secure' => $parametros['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

$pdo = obtenerConexion();
$total = contarCategorias($pdo, $busqueda);

//Mantiene al menos una pagina aunque no haya resultados.
$totalPaginas = max(1, (int) ceil($total / $cantidad));
$pagina = min($pagina, $totalPaginas);
$inicio = ($pagina - 1) * $cantidad;

$categorias = listarCategorias($pdo, $busqueda, $cantidad, $inicio);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Categorías - SIGEL</title>
</head>
<body>
    <h1>Categorías</h1>

    <a href="/pages/inicio.php">Volver al inicio</a>

    <form action="/pages/categorias.php" method="get">
        <label for="buscar">Buscar por nombre</label>
        <input
            type="text"
            id="buscar"
            name="buscar"
            maxlength="100"
            value="<?= escapar($busqueda) ?>"
        >

        <label for="cantidad">Registros por página</label>
        <select id="cantidad" name="cantidad">
            <?php foreach ($opciones as $opcion): ?>
                <option
                    value="<?= escapar($opcion) ?>"
                    <?= $cantidadRecibida === $opcion ? 'selected' : '' ?>
                >
                    <?= escapar($opcion) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Buscar</button>
    </form>

    <p>Total: <?= $total ?></p>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Días máximos de préstamo</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categorias as $categoria): ?>
                <tr>
                    <td><?= (int) $categoria['id'] ?></td>
                    <td><?= escapar($categoria['nombre']) ?></td>
                    <td><?= (int) $categoria['dias_max_prestamo'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($categorias === []): ?>
        <p>No se encontraron categorías.</p>
    <?php endif; ?>

    <p>Página <?= $pagina ?> de <?= $totalPaginas ?></p>

    <?php if ($pagina > 1): ?>
        <?php
        //Conserva la busqueda y la cantidad al cambiar de página.
        $anterior = http_build_query([
            'buscar' => $busqueda,
            'cantidad' => $cantidad,
            'pagina' => $pagina - 1,
        ]);
        ?>
        <a href="?<?= escapar($anterior) ?>">Anterior</a>
    <?php endif; ?>

    <?php if ($pagina < $totalPaginas): ?>
        <?php
        $siguiente = http_build_query([
            'buscar' => $busqueda,
            'cantidad' => $cantidad,
            'pagina' => $pagina + 1,
        ]);
        ?>
        <a href="?<?= escapar($siguiente) ?>">Siguiente</a>
    <?php endif; ?>
</body>
</html>