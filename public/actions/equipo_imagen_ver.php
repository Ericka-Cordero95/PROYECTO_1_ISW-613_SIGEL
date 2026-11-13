<?php

// Permite consultar imagenes a los dos roles autenticados.
require_once __DIR__ . '/../../includes/bootstrap.php';
requerirRol(['administrador', 'operador']);

// Valida el identificador de la imagen.
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if (!$id) {
    http_response_code(400);
    exit('ID de imagen no válido.');
}

// Busca la imagen de un equipo que no este eliminado.
$pdo = obtenerConexion();
$consulta = $pdo->prepare(
    'SELECT i.nombre_archivo, i.mime
     FROM equipo_imagenes i
     INNER JOIN equipos e ON e.id = i.equipo_id
     WHERE i.id = :id AND e.eliminado_en IS NULL'
);
$consulta->execute(['id' => $id]);
$imagen = $consulta->fetch(PDO::FETCH_ASSOC);

// Acepta solo nombres generados por la accion de carga.
if (
    $imagen === false
    || !preg_match('/\A[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)\z/', $imagen['nombre_archivo'])
    || !in_array($imagen['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)
) {
    http_response_code(404);
    exit('Imagen no encontrada.');
}

$ruta = __DIR__ . '/../../storage/equipos/' . $imagen['nombre_archivo'];
$archivo = is_file($ruta) ? fopen($ruta, 'rb') : false;

if ($archivo === false) {
    http_response_code(404);
    exit('Imagen no encontrada.');
}

// Envia la imagen sin exponer su ruta en el servidor.
header('Content-Type: ' . $imagen['mime']);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
fpassthru($archivo);
fclose($archivo);