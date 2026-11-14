<?php

// Carga las funciones comunes.
require_once __DIR__ . '/../../includes/bootstrap.php';

// Permite consultar y descargar solamente al administrador.
requerirRol(['administrador']);

// Esta pagina solamente acepta consultas por GET.
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit('Método no permitido.');
}

// Utiliza el mes actual como rango inicial.
$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$descargar = $_GET['descargar'] ?? '';

// Rechaza parametros enviados como arreglos.
if (
    !is_string($desde)
    || !is_string($hasta)
    || !is_string($descargar)
) {
    http_response_code(400);
    exit('Parámetros no válidos.');
}

// Comprueba el formato y que la fecha exista.
$fechaValida = static function (string $valor): bool {
    if (!preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $valor)) {
        return false;
    }

    $anio = (int) substr($valor, 0, 4);
    $mes = (int) substr($valor, 5, 2);
    $dia = (int) substr($valor, 8, 2);

    // Deja espacio para calcular el dia siguiente en MySQL.
    return $anio >= 1000
        && $anio <= 9998
        && checkdate($mes, $dia, $anio);
};

// Prepara los resultados y el mensaje de validacion.
$error = '';
$resultados = [];

if (!$fechaValida($desde) || !$fechaValida($hasta)) {
    $error = 'Ingrese fechas válidas.';
} elseif ($desde > $hasta) {
    $error = 'La fecha final debe ser igual o posterior a la inicial.';
}

// Consulta solamente cuando las fechas son validas.
if ($error === '') {
    try {
        $pdo = obtenerConexion();

        // Cuenta prestamos entregados o devueltos por equipo.
        // El rango se aplica a la fecha de inicio del prestamo.
        // Incluye todo el ultimo dia seleccionado.
        // Conserva el historial aunque el equipo este eliminado.
        $consulta = $pdo->prepare(
            "SELECT
                e.id,
                e.codigo,
                e.nombre,
                COUNT(DISTINCT p.id) AS total_prestamos
             FROM prestamos p
             INNER JOIN prestamo_detalle pd
                ON pd.prestamo_id = p.id
             INNER JOIN equipos e
                ON e.id = pd.equipo_id
             WHERE p.estado IN ('entregado', 'devuelto')
               AND p.fecha_inicio >= :desde
               AND p.fecha_inicio < DATE_ADD(:hasta, INTERVAL 1 DAY)
             GROUP BY e.id, e.codigo, e.nombre
             ORDER BY total_prestamos DESC, e.codigo ASC"
        );

        $consulta->execute([
            'desde' => $desde . ' 00:00:00',
            'hasta' => $hasta . ' 00:00:00',
        ]);

        $resultados = $consulta->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $excepcion) {
        // Guarda el detalle tecnico en el registro de errores.
        error_log((string) $excepcion);

        // Muestra un mensaje sin detalles internos.
        http_response_code(500);
        $error = 'No se pudo generar el reporte. Intente nuevamente.';
    }
}

// Descarga los mismos resultados que muestra la tabla.
if ($descargar === 'csv' && $error === '') {
    // Protege textos que una hoja de calculo podria interpretar.
    $textoCsv = static function (string $valor): string {
        if (
            preg_match('/^[\s\p{Z}\x{FEFF}]*[=+\-@＝＋－＠]/u', $valor)
            || preg_match('/^[\t\r\n]/', $valor)
        ) {
            return "'" . $valor;
        }

        return $valor;
    };

    // Abre la salida antes de enviar las cabeceras.
    $salida = fopen('php://output', 'w');

    if ($salida === false) {
        http_response_code(500);
        exit('No se pudo generar el archivo CSV.');
    }

    // Construye el nombre con las fechas ya validadas.
    $nombreArchivo =
        'equipos-prestados-' . $desde . '-a-' . $hasta . '.csv';

    // Indica al navegador que debe descargar el archivo.
    header('Content-Type: text/csv; charset=UTF-8');
    header(
        'Content-Disposition: attachment; filename="'
        . $nombreArchivo
        . '"'
    );
    header('Cache-Control: no-store');

    // Facilita que Excel reconozca las tildes en UTF-8.
    fwrite($salida, "\xEF\xBB\xBF");

    // Escribe los nombres de las columnas.
    fputcsv(
        $salida,
        ['Código', 'Equipo', 'Total de préstamos'],
        ',',
        '"',
        ''
    );

    // Escribe una fila por equipo.
    foreach ($resultados as $equipo) {
        fputcsv(
            $salida,
            [
                $textoCsv((string) $equipo['codigo']),
                $textoCsv((string) $equipo['nombre']),
                (int) $equipo['total_prestamos'],
            ],
            ',',
            '"',
            ''
        );
    }

    fclose($salida);
    exit;
}

// Prepara la descarga conservando las fechas consultadas.
$parametrosCsv = http_build_query([
    'desde' => $desde,
    'hasta' => $hasta,
    'descargar' => 'csv',
]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <!-- Permite mostrar tildes y caracteres especiales. -->
    <meta charset="UTF-8">

    <!-- Adapta la pagina al dispositivo. -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Equipos más prestados - SIGEL</title>
</head>
<body>
    <h1>Equipos más prestados</h1>

    <!-- Regresa al menu principal. -->
    <p>
        <a href="/pages/inicio.php">Volver al inicio</a>
    </p>

    <!-- Explica cuales prestamos se cuentan. -->
    <p>
        Cuenta préstamos en estado entregado o devuelto,
        según su fecha de inicio. Incluye ambos días del rango.
    </p>

    <!-- Consulta el reporte sin modificar datos. -->
    <form action="/pages/reporte_equipos.php" method="get">
        <label for="desde">Desde</label>
        <input
            type="date"
            id="desde"
            name="desde"
            min="1000-01-01"
            max="9998-12-31"
            value="<?= escapar($desde) ?>"
            required
        >

        <label for="hasta">Hasta</label>
        <input
            type="date"
            id="hasta"
            name="hasta"
            min="1000-01-01"
            max="9998-12-31"
            value="<?= escapar($hasta) ?>"
            required
        >

        <button type="submit">Consultar</button>
    </form>

    <!-- Muestra errores de validacion o consulta. -->
    <?php if ($error !== ''): ?>
        <p role="alert"><?= escapar($error) ?></p>
    <?php else: ?>
        <h2>Resultados</h2>

        <p>
            Desde <?= escapar($desde) ?>
            hasta <?= escapar($hasta) ?>.
        </p>

        <!-- Descarga exactamente el rango consultado. -->
        <p>
            <a href="/pages/reporte_equipos.php?<?= escapar($parametrosCsv) ?>">
                Descargar CSV
            </a>
        </p>

        <?php if ($resultados === []): ?>
            <p>
                No hay préstamos entregados o devueltos
                para el rango seleccionado.
            </p>
        <?php else: ?>
            <!-- Ordena los equipos desde el mas prestado. -->
            <table>
                <thead>
                    <tr>
                        <th scope="col">Código</th>
                        <th scope="col">Equipo</th>
                        <th scope="col">Total de préstamos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultados as $equipo): ?>
                        <tr>
                            <td><?= escapar($equipo['codigo']) ?></td>
                            <td><?= escapar($equipo['nombre']) ?></td>
                            <td>
                                <?= (int) $equipo['total_prestamos'] ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>
