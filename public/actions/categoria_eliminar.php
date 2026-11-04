<?php

//Carga la configuracion, la sesion y las funciones comunes.
require_once __DIR__ . '/../../includes/bootstrap.php';

//Comprueba que el usuario sea administrador.
requerirRol(['administrador']);

//Solo permite solicitudes enviadas mediante POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido.');
}

//Comprueba el token antes de modificar datos.
validarTokenCsrf();

//Carga la funcion que busca una categoria activa.
require_once __DIR__ . '/../../includes/categorias.php';

//Lee el identificador enviado por el formulario.
$idRecibido = $_POST['id'] ?? null;

//Rechaza campos ausentes o enviados como arreglos.
if (!is_string($idRecibido)) {
    http_response_code(400);
    exit('ID no válido.');
}

//Comprueba que el identificador sea un entero positivo.
$id = filter_var(
    $idRecibido,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

//Detiene la solicitud si el identificador no es valido.
if ($id === false) {
    http_response_code(400);
    exit('ID no válido.');
}

//Obtiene la conexion con MySQL.
$pdo = obtenerConexion();

//Comprueba que la categoria exista y no este eliminada.
if (buscarCategoria($pdo, $id) === false) {
    http_response_code(404);
    exit('La categoría no existe o ya fue eliminada.');
}

try {
    //Guarda la fecha actual sin borrar el registro.
    $consulta = $pdo->prepare(
        'UPDATE categorias
         SET eliminado_en = NOW()
         WHERE id = :id
           AND eliminado_en IS NULL'
    );

    //Envia el identificador separado del texto SQL.
    $consulta->execute(['id' => $id]);

    //Prepara el mensaje que mostrara el listado.
    $_SESSION['categoria_mensaje'] =
        'Categoría eliminada correctamente.';
} catch (PDOException $error) {
    //Registra el detalle tecnico en el archivo de errores.
    error_log((string) $error);

    //Prepara un mensaje sin mostrar informacion de la base de datos.
    $_SESSION['categoria_mensaje'] =
        'No se pudo eliminar la categoría.';
}

//Regresa al listado mediante una nueva solicitud GET.
redirigir('/pages/categorias.php');