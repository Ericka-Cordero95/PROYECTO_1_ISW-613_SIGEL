<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

//Solo permite cerrar sesion mediante POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido.');
}

//Revisa que el envio tenga el token correcto.
validarTokenCsrf();

//Elimina la sesion y vuelve al login.
cerrarSesion();
redirigir('/index.php?estado=cerrada');