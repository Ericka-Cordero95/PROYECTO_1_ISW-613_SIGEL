<?php

// Registra una operacion usando la misma conexion de la escritura.
function registrarBitacora(
    PDO $pdo,
    int $usuarioId,
    string $accion,
    string $entidad,
    int $entidadId,
    ?array $antes,
    ?array $despues
): void {
    // Obtiene la direccion reportada directamente por el servidor.
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    // Guarda null cuando la direccion no es valida o no esta disponible.
    if (
        !is_string($ip)
        || filter_var($ip, FILTER_VALIDATE_IP) === false
    ) {
        $ip = null;
    }

    // Convierte los datos anteriores a JSON cuando existen.
    $antesJson = $antes === null
        ? null
        : json_encode(
            $antes,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

    // Convierte los datos posteriores a JSON cuando existen.
    $despuesJson = $despues === null
        ? null
        : json_encode(
            $despues,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

    // Prepara la insercion del evento sin modificar eventos anteriores.
    $consulta = $pdo->prepare(
        'INSERT INTO bitacora (
            usuario_id,
            accion,
            entidad,
            entidad_id,
            valores_antes,
            valores_despues,
            ip
         ) VALUES (
            :usuario_id,
            :accion,
            :entidad,
            :entidad_id,
            :valores_antes,
            :valores_despues,
            :ip
         )'
    );

    // Envia los valores separados de la sentencia SQL.
    $consulta->execute([
        'usuario_id' => $usuarioId,
        'accion' => $accion,
        'entidad' => $entidad,
        'entidad_id' => $entidadId,
        'valores_antes' => $antesJson,
        'valores_despues' => $despuesJson,
        'ip' => $ip,
    ]);
}