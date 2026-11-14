<?php

// Lee la cookie y comprueba su formato antes de consultar MySQL.
function leerRecordatorio(): ?array
{
    $valor = $_COOKIE['sigel_remember'] ?? null;

    if (
        !is_string($valor)
        || preg_match('/\A([a-f0-9]{18}):([a-f0-9]{64})\z/', $valor, $partes) !== 1
    ) {
        return null;
    }

    return ['selector' => $partes[1], 'validador' => $partes[2]];
}

// Usa los mismos atributos que la cookie de sesion.
function escribirCookieRecordatorio(string $valor, int $expira): void
{
    $parametros = session_get_cookie_params();

    setcookie('sigel_remember', $valor, [
        'expires' => $expira,
        'path' => $parametros['path'],
        'domain' => $parametros['domain'],
        'secure' => $parametros['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Borra la cookie del navegador y de esta peticion.
function borrarCookieRecordatorio(): void
{
    escribirCookieRecordatorio('', time() - 3600);
    unset($_COOKIE['sigel_remember']);
}

// Revoca el recordatorio de este navegador.
function revocarRecordatorio(?PDO $pdo = null): void
{
    $token = leerRecordatorio();
    $selectorSesion = $_SESSION['recordar_selector'] ?? null;
    $usuarioId = $_SESSION['usuario_id'] ?? null;

    try {
        if ($token !== null || (is_string($selectorSesion) && isset($usuarioId))) {
            $pdo = $pdo ?? obtenerConexion();

            // Permite revocar aunque hayan borrado la cookie manualmente.
            if (is_string($selectorSesion) && isset($usuarioId)) {
                $consulta = $pdo->prepare(
                    'DELETE FROM auth_tokens
                     WHERE selector = :selector AND usuario_id = :usuario_id'
                );
                $consulta->execute([
                    'selector' => $selectorSesion,
                    'usuario_id' => $usuarioId,
                ]);
            }

            // Una cookie ajena no puede borrar un token solo por su selector.
            if ($token !== null) {
                $consulta = $pdo->prepare(
                    'DELETE FROM auth_tokens
                     WHERE selector = :selector AND validador_hash = :hash'
                );
                $consulta->execute([
                    'selector' => $token['selector'],
                    'hash' => hash('sha256', $token['validador']),
                ]);
            }
        }
    } finally {
        // Borra la cookie incluso si falla la conexion.
        borrarCookieRecordatorio();
        unset($_SESSION['recordar_selector']);
    }
}

// Genera un recordatorio nuevo que dura 14 dias.
function crearRecordatorio(PDO $pdo, int $usuarioId): string
{
    $selector = bin2hex(random_bytes(9));
    $validador = bin2hex(random_bytes(32));
    $expira = time() + 60 * 60 * 24 * 14;

    // Limpia los tokens vencidos de esta cuenta.
    $consulta = $pdo->prepare(
        'DELETE FROM auth_tokens
         WHERE usuario_id = :usuario_id AND expira_en <= UTC_TIMESTAMP()'
    );
    $consulta->execute(['usuario_id' => $usuarioId]);

    // Guarda solo el hash del validador y la fecha en UTC.
    $consulta = $pdo->prepare(
        'INSERT INTO auth_tokens
            (usuario_id, selector, validador_hash, expira_en)
         VALUES (:usuario_id, :selector, :hash, :expira_en)'
    );
    $consulta->execute([
        'usuario_id' => $usuarioId,
        'selector' => $selector,
        'hash' => hash('sha256', $validador),
        'expira_en' => gmdate('Y-m-d H:i:s', $expira),
    ]);

    escribirCookieRecordatorio($selector . ':' . $validador, $expira);
    return $selector;
}

// Recupera una sesion usando el recordatorio del navegador.
function recuperarSesionRecordada(): bool
{
    if (isset($_SESSION['usuario_id'])) {
        return false;
    }

    // No autentica automaticamente un formulario enviado sin sesion.
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
        return false;
    }

    if (!isset($_COOKIE['sigel_remember'])) {
        return false;
    }

    $token = leerRecordatorio();

    if ($token === null) {
        borrarCookieRecordatorio();
        return false;
    }

    $pdo = null;

    try {
        $pdo = obtenerConexion();
        $pdo->beginTransaction();

        // Bloquea el token mientras se comprueba y se rota.
        $consulta = $pdo->prepare(
            'SELECT
                t.id AS token_id,
                t.validador_hash,
                TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), t.expira_en) AS segundos,
                u.id,
                u.nombre,
                u.activo,
                u.eliminado_en,
                r.nombre AS rol,
                (u.bloqueado_hasta IS NOT NULL
                    AND u.bloqueado_hasta > CURRENT_TIMESTAMP) AS bloqueado
             FROM auth_tokens t
             INNER JOIN usuarios u ON u.id = t.usuario_id
             INNER JOIN roles r ON r.id = u.rol_id
             WHERE t.selector = :selector
             LIMIT 1
             FOR UPDATE'
        );
        $consulta->execute(['selector' => $token['selector']]);
        $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

        if ($usuario === false) {
            $pdo->commit();
            borrarCookieRecordatorio();
            return false;
        }

        // Compara el hash sin usar una comparacion normal de textos.
        $validadorCorrecto = hash_equals(
            $usuario['validador_hash'],
            hash('sha256', $token['validador'])
        );

        // El recordatorio no evita bloqueos ni reactiva cuentas.
        if (
            !$validadorCorrecto
            || (int) $usuario['segundos'] <= 0
            || (int) $usuario['activo'] !== 1
            || $usuario['eliminado_en'] !== null
            || (int) $usuario['bloqueado'] === 1
        ) {
            $consulta = $pdo->prepare('DELETE FROM auth_tokens WHERE id = :id');
            $consulta->execute(['id' => $usuario['token_id']]);
            $pdo->commit();
            borrarCookieRecordatorio();
            return false;
        }

        // Reemplaza ambas partes para que la cookie anterior no sirva otra vez.
        $nuevoSelector = bin2hex(random_bytes(9));
        $nuevoValidador = bin2hex(random_bytes(32));

        $consulta = $pdo->prepare(
            'UPDATE auth_tokens
             SET selector = :selector, validador_hash = :hash
             WHERE id = :id'
        );
        $consulta->execute([
            'selector' => $nuevoSelector,
            'hash' => hash('sha256', $nuevoValidador),
            'id' => $usuario['token_id'],
        ]);

        $pdo->commit();

        // Mantiene el vencimiento original, sin sumar otros 14 dias.
        escribirCookieRecordatorio(
            $nuevoSelector . ':' . $nuevoValidador,
            time() + (int) $usuario['segundos']
        );

        iniciarSesionUsuario($usuario, $nuevoSelector);
        return true;
    } catch (Throwable $error) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log((string) $error);
        borrarCookieRecordatorio();
        return false;
    }
}
