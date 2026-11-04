<?php

//Carga las funciones comunes.
require_once __DIR__ . '/../../includes/bootstrap.php';

//Revisa el permiso también en la accion.
requerirRol(['administrador']);

//Solo acepta formularios enviados por POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido.');
}

//Revisa el token antes de guardar.
validarTokenCsrf();

require_once __DIR__ . '/../../includes/categorias.php';

//Lee los datos enviados.
$idRecibido = $_POST['id'] ?? null;
$nombre = $_POST['nombre'] ?? null;
$diasRecibidos = $_POST['dias'] ?? null;

//comprueba que los campos sean textos.
if (
    !is_string($idRecibido)
    || !is_string($nombre)
    || !is_string($diasRecibidos)
) {
    $_SESSION['categoria_error'] = 'Datos no válidos.';
    redirigir('/pages/categoria_form.php');
}

//Decide si debe crear o editar.
$editando = $idRecibido !== '';
$id = null;
$volver = '/pages/categoria_form.php';

if ($editando) {
    //El ID debe ser un entero positivo.
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

$pdo = obtenerConexion();

//No permite editar categorias inexistentes o eliminadas.
if ($editando && buscarCategoria($pdo, $id) === false) {
    http_response_code(404);
    exit('La categoría no existe.');
}

//Quita espacios del inicio y final del nombre.
$nombre = trim($nombre);

//Guarda los datos para recuperarlos si ocurre un error.
$_SESSION['categoria_datos'] = [
    'id' => $editando ? (string) $id : '',
    'nombre' => $nombre,
    'dias' => $diasRecibidos,
];

//Valida el nombre en el servidor.
if ($nombre === '' || strlen($nombre) > 100) {
    $_SESSION['categoria_error'] = 'Ingrese un nombre de hasta 100 bytes.';
    redirigir($volver);
}

//Valida los dias en el servidor.
$dias = filter_var(
    $diasRecibidos,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 365]]
);

if ($dias === false) {
    $_SESSION['categoria_error'] = 'Los días deben estar entre 1 y 365.';
    redirigir($volver);
}

try {
    if ($editando) {
        //Cambia los datos de una categoria existente.
        $consulta = $pdo->prepare(
            'UPDATE categorias
             SET nombre = :nombre,
                 dias_max_prestamo = :dias
             WHERE id = :id
               AND eliminado_en IS NULL'
        );

        $consulta->execute([
            'nombre' => $nombre,
            'dias' => $dias,
            'id' => $id,
        ]);
    } else {
        //Crea una categoria nueva.
        $consulta = $pdo->prepare(
            'INSERT INTO categorias (nombre, dias_max_prestamo)
             VALUES (:nombre, :dias)'
        );

        $consulta->execute([
            'nombre' => $nombre,
            'dias' => $dias,
        ]);
    }
} catch (PDOException $error) {
    //El codigo 1062 indica un valor duplicado en MySQL.
    if ((int) ($error->errorInfo[1] ?? 0) === 1062) {
        $_SESSION['categoria_error'] = 'Ya existe una categoría con ese nombre.';
    } else {
        // Guarda el detalle sin mostrarlo al usuario.
        error_log((string) $error);
        $_SESSION['categoria_error'] = 'No se pudo guardar la categoría.';
    }

    redirigir($volver);
}

//Borra los datos temporales despues de guardar.
unset($_SESSION['categoria_datos'], $_SESSION['categoria_error']);

//Prepara el mensaje y vuelve al listado mediante GET.
$_SESSION['categoria_mensaje'] = 'Categoría guardada correctamente.';
redirigir('/pages/categorias.php');