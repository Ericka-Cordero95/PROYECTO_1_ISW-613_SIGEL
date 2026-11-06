<?php

// Carga las funciones comunes y comprueba el permiso.
require_once __DIR__ . '/../../includes/bootstrap.php';
requerirRol(['administrador']);

// Carga las consultas de equipos.
require_once __DIR__ . '/../../includes/equipos.php';

// Obtiene la conexion y las categorias activas.
$pdo = obtenerConexion();
$categorias = listarCategoriasParaEquipos($pdo);

// Prepara los valores de un equipo nuevo.
$equipo = [
    'id' => '',
    'categoria_id' => '',
    'codigo' => '',
    'nombre' => '',
    'marca' => '',
    'modelo' => '',
    'numero_serie' => '',
    'estado' => 'disponible',
    'version' => 1,
];

// Lee el ID para decidir si se debe editar.
$idRecibido = $_GET['id'] ?? '';
$editando = false;

// Rechaza identificadores enviados como arreglos.
if (!is_string($idRecibido)) {
    http_response_code(400);
    exit('ID no válido.');
}

// Carga los datos cuando se recibe un identificador.
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

    $equipo = buscarEquipo($pdo, $id);

    if ($equipo === false) {
        http_response_code(404);
        exit('El equipo no existe.');
    }

    $editando = true;
}

// Recupera los datos del ultimo envio sin guardarlos permanentemente.
$error = $_SESSION['equipo_error'] ?? '';
$datosAnteriores = $_SESSION['equipo_datos'] ?? null;

unset($_SESSION['equipo_error'], $_SESSION['equipo_datos']);

// Recupera los campos solo si pertenecen a este equipo.
if (
    is_array($datosAnteriores)
    && ($datosAnteriores['id'] ?? null) === (string) $equipo['id']
) {
    foreach (
        [
            'categoria_id', 'codigo', 'nombre', 'marca', 'modelo',
            'numero_serie', 'estado', 'version'
        ] as $campo
    ) {
        $equipo[$campo] = $datosAnteriores[$campo];
    }
}

// Define los estados que muestra el formulario.
$estados = [
    'disponible' => 'Disponible',
    'mantenimiento' => 'Mantenimiento',
    'baja' => 'Baja',
];

// Define los campos de texto y sus longitudes maximas.
$camposTexto = [
    'codigo' => ['Código de inventario', 50],
    'nombre' => ['Nombre', 100],
    'marca' => ['Marca', 100],
    'modelo' => ['Modelo', 100],
    'numero_serie' => ['Número de serie', 100],
];

// Define el titulo segun la operacion.
$titulo = $editando ? 'Editar equipo' : 'Nuevo equipo';
?>
<!DOCTYPE html>
<!-- Define el idioma del documento. -->
<html lang="es">
<head>
    <!-- Permite mostrar correctamente los caracteres especiales. -->
    <meta charset="UTF-8">

    <!-- Adapta la pagina al ancho del dispositivo. -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Muestra la operacion en la pestana del navegador. -->
    <title><?= escapar($titulo) ?> - SIGEL</title>
</head>
<body>
    <!-- Muestra el titulo principal. -->
    <h1><?= escapar($titulo) ?></h1>

    <!-- Muestra los errores del ultimo envio. -->
    <?php if ($error !== ''): ?>
        <p role="alert"><?= escapar($error) ?></p>
    <?php endif; ?>

    <!-- Explica por que no puede crearse un equipo sin categoria activa. -->
    <?php if ($categorias === []): ?>
        <p>Debe crear una categoría activa antes de guardar equipos.</p>
    <?php endif; ?>

    <!-- Envia los datos a la accion de guardado. -->
    <form action="/actions/equipo_guardar.php" method="post">

        <!-- Envia el token que se comprobara en el servidor. -->
        <input
            type="hidden"
            name="csrf_token"
            value="<?= escapar(obtenerTokenCsrf()) ?>"
        >

        <!-- Identifica el equipo o indica una creacion con valor vacio. -->
        <input
            type="hidden"
            name="id"
            value="<?= escapar((string) $equipo['id']) ?>"
        >

        <!-- Conserva la version que tenia el equipo al abrir el formulario. -->
        <input
            type="hidden"
            name="version"
            value="<?= escapar((string) $equipo['version']) ?>"
        >

        <!-- Permite asignar solamente categorias activas. -->
        <div>
            <label for="categoria_id">Categoría</label>
            <select id="categoria_id" name="categoria_id" required>
                <option value="">Seleccione una categoría activa</option>

                <!-- Genera las opciones desde MySQL. -->
                <?php foreach ($categorias as $categoria): ?>
                    <option
                        value="<?= (int) $categoria['id'] ?>"
                        <?= (string) $equipo['categoria_id'] === (string) $categoria['id']
                            ? 'selected'
                            : '' ?>
                    >
                        <?= escapar($categoria['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Genera los campos de texto usando la configuracion anterior. -->
        <?php foreach ($camposTexto as $campo => $configuracion): ?>
            <div>
                <label for="<?= escapar($campo) ?>">
                    <?= escapar($configuracion[0]) ?>
                </label>

                <!-- Codigo y nombre son obligatorios; los demas son opcionales. -->
                <input
                    type="text"
                    id="<?= escapar($campo) ?>"
                    name="<?= escapar($campo) ?>"
                    maxlength="<?= (int) $configuracion[1] ?>"
                    value="<?= escapar((string) ($equipo[$campo] ?? '')) ?>"
                    <?= in_array($campo, ['codigo', 'nombre'], true)
                        ? 'required'
                        : '' ?>
                >
            </div>
        <?php endforeach; ?>

        <!-- Captura el estado administrativo del equipo. -->
        <div>
            <label for="estado">Estado</label>
            <select id="estado" name="estado" required>
                <?php foreach ($estados as $valor => $etiqueta): ?>
                    <option
                        value="<?= escapar($valor) ?>"
                        <?= $equipo['estado'] === $valor ? 'selected' : '' ?>
                    >
                        <?= escapar($etiqueta) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Envia el formulario cuando existen categorias activas. -->
        <button
            type="submit"
            <?= $categorias === [] ? 'disabled' : '' ?>
        >
            Guardar
        </button>

        <!-- Regresa sin guardar cambios. -->
        <a href="/pages/equipos.php">Cancelar</a>
    </form>
</body>
</html>