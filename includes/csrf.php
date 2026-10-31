<?php

// Genera un token impredecible y lo conserva durante la sesión.
function obtenerTokenCsrf(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));//32 bytes aleatorios, 64 caracteres.
    }

    return $_SESSION['csrf_token'];//guardamos token
}

// Rechaza solicitudes que no incluyan el token correcto de la sesión.
function validarTokenCsrf(): void
{
    $tokenSesion = $_SESSION['csrf_token'] ?? null;
    $tokenRecibido = $_POST['csrf_token'] ?? null;//se proporciona un valor alternativo si el dato mno existe.

    // Verifica los tipos antes de comparar: una entrada puede ser un arreglo.
    if (
        !is_string($tokenSesion)
        || !is_string($tokenRecibido)
        || !hash_equals($tokenSesion, $tokenRecibido)//se comparan copias
    ) {
        http_response_code(403);

        exit('Solicitud no válida. Recargue el formulario e intente nuevamente.');
    }
}