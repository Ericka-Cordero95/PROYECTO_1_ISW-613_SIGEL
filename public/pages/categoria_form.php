<?php

//Carga la configuracion y las funciones comunes.
require_once __DIR__ . '/../../includes/bootstrap.php';

//Solo el administrador puede usar el formulario.
requerirRol(['administrador']);

//Carga las consultas de categorias.
require_once __DIR__ . '/../../includes/categorias.php';

//Sin ID se crea; con ID se edita.
$idRecibido = $_GET['id'] ?? '';
$editando = false;

//Valores iniciales para una categoria nueva.
$categoria = [
    'id' => '',
    'nombre' => '',
    'dias_max_prestamo' => 1,
];

//Rechaza un ID enviado como arreglo.
if (!is_string($idRecibido)) {
    http_response_code(400);
    exit('ID no válido.');
}

//Si llego un ID, busca los datos de la categoria.
if ($idRecibido !== '') {
    $id = filter_var(
        $idRecibido,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id === false) {
        http_response_code(400);
        exit('ID no válido.');
    }

    $categoria = buscarCategoria(obtenerConexion(), $id);

    if ($categoria === false) {
        http_response_code(404);
        exit('La categoría no existe.');
    }

    $editando = true;
}

//Recupera los errores y datos del ultimo envio.
$error = $_SESSION['categoria_error'] ?? '';
$datosAnteriores = $_SESSION['categoria_datos'] ?? null;

unset(
    $_SESSION['categoria_error'],
    $_SESSION['categoria_datos']
);

//Recupera los datos solo si pertenecen a este formulario.
if (
    is_array($datosAnteriores)
    && $datosAnteriores['id'] === (string) $categoria['id']
) {
    $categoria['nombre'] = $datosAnteriores['nombre'];
    $categoria['dias_max_prestamo'] = $datosAnteriores['dias'];
}

//El título depende de la operacion.
$titulo = $editando ? 'Editar categoría' : 'Nueva categoría';
?>
<!DOCTYPE html>
<!-- Indica que el documento utiliza HTML5 -->
<html lang="es">
<head>
    <!-- Permite mostrar tildes y ñ -->
    <meta charset="UTF-8">

    <!-- Muestra el titulo en la pestaña del navegador -->
    <title><?= escapar($titulo) ?> - SIGEL</title>
</head>
<body>
    <!-- Muestra si estamos creando o editando -->
    <h1><?= escapar($titulo) ?></h1>

    <!-- Muestra un error del envio anterior -->
    <?php if ($error !== ''): ?>
        <p><?= escapar($error) ?></p>
    <?php endif; ?>

    <!-- Envia los datos a la accion que guarda la categoria -->
    <form action="/actions/categoria_guardar.php" method="post">

        <!-- Permite comprobar que el envio pertenece a la sesion -->
        <input
            type="hidden"
            name="csrf_token"
            value="<?= escapar(obtenerTokenCsrf()) ?>"
        >

        <!-- Vacio significa crear; un numero significa editar -->
        <input
            type="hidden"
            name="id"
            value="<?= escapar((string) $categoria['id']) ?>"
        >

        <!-- Captura el nombre de la categoria -->
        <div>
            <label for="nombre">Nombre</label>
            <input
                type="text"
                id="nombre"
                name="nombre"
                maxlength="100"
                value="<?= escapar($categoria['nombre']) ?>"
                required
            >
        </div>

        <!-- Captura el limite de días para los prestamos -->
        <div>
            <label for="dias">Días máximos de préstamo</label>
            <input
                type="number"
                id="dias"
                name="dias"
                min="1"
                max="365"
                value="<?= escapar((string) $categoria['dias_max_prestamo']) ?>"
                required
            >
        </div>

        <!-- Envia el formulario -->
        <button type="submit">Guardar</button>

        <!-- Regresa al listado sin guardar cambios -->
        <a href="/pages/categorias.php">Cancelar</a>
    </form>
</body>
</html>