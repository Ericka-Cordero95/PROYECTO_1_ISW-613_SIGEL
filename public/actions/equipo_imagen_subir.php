<?php

// Carga las funciones comunes y exige el rol administrador.
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/bitacora.php';
requerirRol(['administrador']);

// Acepta solo solicitudes POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido.');
}

validarTokenCsrf();

// Valida el equipo enviado por el formulario.
$id = filter_input(INPUT_POST, 'equipo_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if (!$id) {
    http_response_code(400);
    exit('ID de equipo no válido.');
}

$volver = '/pages/equipo_detalle.php?id=' . $id;
$archivo = $_FILES['imagen'] ?? [];

// Comprueba que PHP recibio un archivo valido.
if (
    ($archivo['error'] ?? null) !== UPLOAD_ERR_OK
    || !is_string($archivo['tmp_name'] ?? null)
    || !is_string($archivo['name'] ?? null)
    || !is_uploaded_file($archivo['tmp_name'])
) {
    $_SESSION['equipo_imagen_mensaje'][$id] =
        'No se recibió una imagen válida. Revise el archivo y su tamaño.';
    redirigir($volver);
}

// Comprueba el tamano real del archivo temporal.
$tamano = filesize($archivo['tmp_name']);

if ($tamano === false || $tamano < 1 || $tamano > 2 * 1024 * 1024) {
    $_SESSION['equipo_imagen_mensaje'][$id] =
        'La imagen debe pesar como máximo 2 MB.';
    redirigir($volver);
}

// Define los tipos permitidos y sus extensiones.
$tiposPermitidos = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
];

$extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);

// Comprueba que la extension coincida con el tipo real.
if (!isset($tiposPermitidos[$extension]) || $tiposPermitidos[$extension] !== $mime) {
    $_SESSION['equipo_imagen_mensaje'][$id] =
        'Seleccione una imagen JPG, PNG o WebP válida.';
    redirigir($volver);
}

$pdo = obtenerConexion();
$rutaGuardada = null;

try {
    $pdo->beginTransaction();

    // Bloquea el equipo mientras se agrega la imagen.
    $consulta = $pdo->prepare(
        'SELECT id
         FROM equipos
         WHERE id = :id AND eliminado_en IS NULL
         FOR UPDATE'
    );
    $consulta->execute(['id' => $id]);

    if ($consulta->fetchColumn() === false) {
        $pdo->rollBack();
        http_response_code(404);
        exit('El equipo no existe.');
    }

    // Crea la carpeta privada cuando se necesita.
    $carpeta = __DIR__ . '/../../storage/equipos';

    if (!is_dir($carpeta) && !mkdir($carpeta, 0750, true) && !is_dir($carpeta)) {
        throw new RuntimeException('No se pudo crear la carpeta de imagenes.');
    }

    // Genera el nombre y guarda el archivo fuera de public.
    $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $extension;
    $ruta = $carpeta . '/' . $nombreArchivo;

    if (!move_uploaded_file($archivo['tmp_name'], $ruta)) {
        throw new RuntimeException('No se pudo guardar la imagen.');
    }

    $rutaGuardada = $ruta;

    // Guarda los datos de la imagen en MySQL.
    $datos = [
        'equipo_id' => $id,
        'nombre_archivo' => $nombreArchivo,
        'mime' => $mime,
        'tamano_bytes' => $tamano,
    ];

    $consulta = $pdo->prepare(
        'INSERT INTO equipo_imagenes (
            equipo_id, nombre_archivo, mime, tamano_bytes
         ) VALUES (
            :equipo_id, :nombre_archivo, :mime, :tamano_bytes
         )'
    );
    $consulta->execute($datos);

    // Registra la carga en la misma transaccion.
    registrarBitacora(
        $pdo,
        (int) $_SESSION['usuario_id'],
        'CREAR',
        'equipo_imagenes',
        (int) $pdo->lastInsertId(),
        null,
        $datos
    );

    $pdo->commit();
    $_SESSION['equipo_imagen_mensaje'][$id] = 'Imagen agregada correctamente.';
} catch (Throwable $error) {
    // Revierte los datos y retira el archivo si ya se guardo.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($rutaGuardada !== null && is_file($rutaGuardada)) {
        unlink($rutaGuardada);
    }

    error_log((string) $error);
    $_SESSION['equipo_imagen_mensaje'][$id] = 'No se pudo guardar la imagen.';
}

redirigir($volver);