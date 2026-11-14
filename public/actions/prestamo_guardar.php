<?php

// Carga las funciones comunes y comprueba el permiso.
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/bitacora.php';
requerirRol(['operador']);

// Acepta solo solicitudes enviadas por POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido.');
}

validarTokenCsrf();

// Regresa al formulario con el resultado de la operacion.
function volverAlFormularioPrestamo(string $mensaje): void
{
    $_SESSION['prestamo_mensaje'] = $mensaje;
    redirigir('/pages/prestamo_form.php');
}

// Acepta solo fechas con el formato enviado por el formulario.
function leerFechaPrestamo(mixed $valor): ?DateTimeImmutable
{
    if (!is_string($valor) || strlen($valor) !== 16) {
        return null;
    }

    $fecha = DateTimeImmutable::createFromFormat(
        '!Y-m-d\TH:i',
        $valor,
        new DateTimeZone('America/Costa_Rica')
    );

    if ($fecha === false || $fecha->format('Y-m-d\TH:i') !== $valor) {
        return null;
    }

    return $fecha;
}

$usuarioId = filter_input(INPUT_POST, 'usuario_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

$inicio = leerFechaPrestamo($_POST['fecha_inicio'] ?? null);
$fin = leerFechaPrestamo($_POST['fecha_fin'] ?? null);
$seleccion = $_POST['equipos'] ?? [];

// Comprueba la persona, las fechas y la seleccion.
if (!$usuarioId) {
    volverAlFormularioPrestamo('Seleccione una persona válida.');
}

if ($inicio === null || $fin === null || $fin <= $inicio) {
    volverAlFormularioPrestamo('La fecha de fin debe ser posterior a la de inicio.');
}

if (!is_array($seleccion) || $seleccion === []) {
    volverAlFormularioPrestamo('Seleccione al menos un equipo.');
}

$ids = [];

foreach ($seleccion as $valor) {
    $id = is_string($valor)
        ? filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;

    if ($id === false) {
        volverAlFormularioPrestamo('La selección contiene un equipo no válido.');
    }

    $ids[$id] = $id;
}

// Bloquea siempre los equipos en el mismo orden.
sort($ids, SORT_NUMERIC);

$fechaInicio = $inicio->format('Y-m-d H:i:s');
$fechaFin = $fin->format('Y-m-d H:i:s');
$operadorId = (int) $_SESSION['usuario_id'];
$pdo = obtenerConexion();

try {
    // Guarda la solicitud, sus equipos y su bitacora juntos.
    $pdo->beginTransaction();

    $persona = $pdo->prepare(
        'SELECT id FROM usuarios
         WHERE id = :id AND activo = 1 AND eliminado_en IS NULL
         FOR UPDATE'
    );
    $persona->execute(['id' => $usuarioId]);

    if ($persona->fetchColumn() === false) {
        throw new DomainException('La persona seleccionada ya no está activa.');
    }

    // Bloquea el equipo y comprueba el limite de su categoria.
    $buscarEquipo = $pdo->prepare(
        "SELECT e.codigo, c.dias_max_prestamo
         FROM equipos e
         INNER JOIN categorias c ON c.id = e.categoria_id
         WHERE e.id = :id
           AND e.eliminado_en IS NULL
           AND c.eliminado_en IS NULL
           AND e.estado = 'disponible'
         FOR UPDATE"
    );

    $equipos = [];

    foreach ($ids as $id) {
        $buscarEquipo->execute(['id' => $id]);
        $equipo = $buscarEquipo->fetch(PDO::FETCH_ASSOC);
        $buscarEquipo->closeCursor();

        if ($equipo === false) {
            throw new DomainException('Uno de los equipos ya no está disponible.');
        }

        $dias = (int) $equipo['dias_max_prestamo'];

        if ($fin > $inicio->modify('+' . $dias . ' days')) {
            throw new DomainException(
                'El equipo ' . $equipo['codigo'] . ' permite un máximo de ' . $dias . ' días.'
            );
        }

        $equipos[$id] = $equipo['codigo'];
    }

    // Busca un prestamo activo que se cruce con el intervalo solicitado.
    $traslape = $pdo->prepare(
        "SELECT p.id
         FROM prestamo_detalle d
         INNER JOIN prestamos p ON p.id = d.prestamo_id
         WHERE d.equipo_id = :equipo_id
           AND p.estado IN ('solicitado', 'aprobado', 'entregado')
           AND p.fecha_inicio < :fecha_fin
           AND p.fecha_fin > :fecha_inicio
         LIMIT 1
         FOR UPDATE"
    );

    foreach ($equipos as $id => $codigo) {
        $traslape->execute([
            'equipo_id' => $id,
            'fecha_fin' => $fechaFin,
            'fecha_inicio' => $fechaInicio,
        ]);

        $ocupado = $traslape->fetchColumn() !== false;
        $traslape->closeCursor();

        if ($ocupado) {
            throw new DomainException(
                'El equipo ' . $codigo . ' ya tiene un préstamo que se cruza con esas fechas.'
            );
        }
    }

    // Guarda el encabezado con el estado inicial solicitado.
    $guardar = $pdo->prepare(
        'INSERT INTO prestamos (usuario_id, operador_id, fecha_inicio, fecha_fin)
         VALUES (:usuario_id, :operador_id, :fecha_inicio, :fecha_fin)'
    );

    $datos = [
        'usuario_id' => $usuarioId,
        'operador_id' => $operadorId,
        'fecha_inicio' => $fechaInicio,
        'fecha_fin' => $fechaFin,
    ];

    $guardar->execute($datos);
    $prestamoId = (int) $pdo->lastInsertId();

    // Guarda una fila de detalle por cada equipo.
    $guardarDetalle = $pdo->prepare(
        'INSERT INTO prestamo_detalle (prestamo_id, equipo_id)
         VALUES (:prestamo_id, :equipo_id)'
    );

    foreach ($ids as $id) {
        $guardarDetalle->execute([
            'prestamo_id' => $prestamoId,
            'equipo_id' => $id,
        ]);
    }

    // Agrega el estado y los equipos al evento de bitacora.
    $datos['estado'] = 'solicitado';
    $datos['equipos'] = $ids;
    registrarBitacora($pdo, $operadorId, 'CREAR', 'prestamos', $prestamoId, null, $datos);

    $pdo->commit();
} catch (Throwable $error) {
    // Revierte toda la solicitud si algun paso falla.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($error instanceof DomainException) {
        volverAlFormularioPrestamo($error->getMessage());
    }

    error_log((string) $error);
    volverAlFormularioPrestamo('No se pudo registrar el préstamo. Intente nuevamente.');
}

// Confirma el registro y vuelve por GET.
volverAlFormularioPrestamo('Solicitud de préstamo #' . $prestamoId . ' registrada.');