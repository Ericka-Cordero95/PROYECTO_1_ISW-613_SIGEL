<?php

//Carga la configuracion, la sesion y las funciones comunes.
require_once __DIR__ . '/../../includes/bootstrap.php';

//Comprueba la sesion y permite entrar solo al administrador.
requerirRol(['administrador']);

//Carga las consultas del modulo de usuarios.
require_once __DIR__ . '/../../includes/usuarios.php';

//Lee el texto de busqueda o utiliza un texto vacio.
$busqueda = $_GET['buscar'] ?? '';

//Rechaza arreglos y textos que superen el limite permitido.
if (!is_string($busqueda) || strlen($busqueda) > 150) {
    http_response_code(400);
    exit('Búsqueda no válida.');
}

//Quita los espacios del inicio y final.
$busqueda = trim($busqueda);

//Lee la pagina solicitada o utiliza la primera.
$paginaRecibida = $_GET['pagina'] ?? '1';

// Rechaza una pagina enviada como arreglo.
if (!is_string($paginaRecibida)) {
    http_response_code(400);
    exit('Página no válida.');
}

//Acepta solamente numeros enteros positivos.
$pagina = filter_var(
    $paginaRecibida,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

//Detiene la solicitud cuando la pagina no es valida.
if ($pagina === false) {
    http_response_code(400);
    exit('Página no válida.');
}

//Define cuantos usuarios se muestran por pagina.
$cantidad = 10;

//Obtiene la conexion y cuenta los resultados.
$pdo = obtenerConexion();
$total = contarUsuarios($pdo, $busqueda);

//Calcula el total de paginas y mantiene un minimo de una.
$totalPaginas = max(1, (int) ceil($total / $cantidad));

//Ajusta la pagina cuando supera la ultima disponible.
$pagina = min($pagina, $totalPaginas);

//Calcula cuantos registros debe saltar MySQL.
$inicio = ($pagina - 1) * $cantidad;

//Consulta solamente los usuarios de la pagina actual.
$usuarios = listarUsuarios(
    $pdo,
    $busqueda,
    $cantidad,
    $inicio
);

//Prepara el enlace anterior conservando la busqueda.
$anterior = http_build_query([
    'buscar' => $busqueda,
    'pagina' => max(1, $pagina - 1),
]);

//Prepara el enlace siguiente conservando la busqueda.
$siguiente = http_build_query([
    'buscar' => $busqueda,
    'pagina' => min($totalPaginas, $pagina + 1),
]);
?>
<!DOCTYPE html>
<!-- Define el idioma del documento. -->
<html lang="es">
<head>
    <!-- Permite mostrar correctamente los caracteres especiales. -->
    <meta charset="UTF-8">

    <!-- Adapta el ancho de la pagina al dispositivo. -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Define el titulo de la pestana del navegador. -->
    <title>Usuarios - SIGEL</title>
</head>
<body>
    <!-- Muestra el titulo principal del modulo. -->
    <h1>Usuarios</h1>

    <!-- Regresa al menu de la aplicacion. -->
    <p>
        <a href="/pages/inicio.php">Volver al inicio</a>
    </p>

    <!-- Envia la busqueda por GET porque solo consulta datos. -->
    <form action="/pages/usuarios.php" method="get">

        <!-- Relaciona la etiqueta con el campo de busqueda. -->
        <label for="buscar">Buscar por nombre o correo</label>

        <!-- Conserva el texto buscado y lo escapa antes de mostrarlo. -->
        <input
            type="text"
            id="buscar"
            name="buscar"
            maxlength="150"
            value="<?= escapar($busqueda) ?>"
        >

        <!-- Ejecuta la busqueda desde la primera pagina. -->
        <button type="submit">Buscar</button>

        <!-- Abre el listado sin filtros. -->
        <a href="/pages/usuarios.php">Limpiar búsqueda</a>
    </form>

    <!-- Muestra cuantos usuarios coinciden con la busqueda. -->
    <p>Total: <?= $total ?></p>

    <!-- Organiza los usuarios en filas y columnas. -->
    <table>
        <thead>
            <!-- Define los encabezados de las columnas. -->
            <tr>
                <th scope="col">ID</th>
                <th scope="col">Nombre</th>
                <th scope="col">Correo</th>
                <th scope="col">Rol</th>
                <th scope="col">Estado</th>
            </tr>
        </thead>

        <tbody>
            <!-- Genera una fila por cada usuario encontrado. -->
            <?php foreach ($usuarios as $usuario): ?>
                <tr>
                    <!-- Muestra el identificador como numero entero. -->
                    <td><?= (int) $usuario['id'] ?></td>

                    <!-- Escapa el nombre antes de mostrarlo en HTML. -->
                    <td><?= escapar($usuario['nombre']) ?></td>

                    <!-- Muestra el correo sin interpretarlo como HTML. -->
                    <td><?= escapar($usuario['correo']) ?></td>

                    <!-- Muestra el nombre obtenido desde la tabla roles. -->
                    <td><?= escapar($usuario['rol']) ?></td>

                    <!-- Convierte el valor de activo en un texto legible. -->
                    <td>
                        <?= (int) $usuario['activo'] === 1
                            ? 'Activo'
                            : 'Inactivo' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Informa cuando no existen resultados. -->
    <?php if ($usuarios === []): ?>
        <p>No se encontraron usuarios.</p>
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