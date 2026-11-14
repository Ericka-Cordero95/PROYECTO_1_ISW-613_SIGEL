<?php

// Usa una cookie distinta para cada operador del navegador.
function nombreCookieEquiposRecientes(int $usuarioId): string
{
    return 'sigel_equipos_recientes_' . $usuarioId;
}

// Lee hasta cinco identificadores validos de la cookie.
function leerIdsEquiposRecientes(int $usuarioId): array
{
    $valor = $_COOKIE[nombreCookieEquiposRecientes($usuarioId)] ?? '';

    // Rechaza valores inesperados antes de separar los IDs.
    if (!is_string($valor) || strlen($valor) > 100) {
        return [];
    }

    $ids = [];

    foreach (explode(',', $valor, 5) as $parte) {
        $id = filter_var($parte, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 1,
                'max_range' => 4294967295,
            ],
        ]);

        if ($id !== false && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }

    return $ids;
}

// Coloca el equipo consultado primero y conserva hasta cinco IDs.
function registrarEquipoReciente(int $usuarioId, int $equipoId): void
{
    $ids = [$equipoId];

    foreach (leerIdsEquiposRecientes($usuarioId) as $id) {
        if ($id !== $equipoId && count($ids) < 5) {
            $ids[] = $id;
        }
    }

    $parametros = session_get_cookie_params();

    setcookie(nombreCookieEquiposRecientes($usuarioId), implode(',', $ids), [
        'expires' => time() + 60 * 60 * 24 * 30,
        'path' => '/',
        'secure' => $parametros['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Consulta los equipos vigentes en el orden de la cookie.
function listarEquiposRecientes(PDO $pdo, int $usuarioId): array
{
    $ids = leerIdsEquiposRecientes($usuarioId);

    if ($ids === []) {
        return [];
    }

    // Genera un marcador por ID y envia los valores por separado.
    $marcadores = implode(', ', array_fill(0, count($ids), '?'));

    $consulta = $pdo->prepare(
        'SELECT id, codigo, nombre
         FROM equipos
         WHERE eliminado_en IS NULL
           AND id IN (' . $marcadores . ')'
    );

    $consulta->execute($ids);

    // Indexa los resultados por ID para recuperar el orden de visita.
    $porId = [];

    foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $equipo) {
        $porId[(int) $equipo['id']] = $equipo;
    }

    $recientes = [];

    foreach ($ids as $id) {
        if (isset($porId[$id])) {
            $recientes[] = $porId[$id];
        }
    }

    return $recientes;
}