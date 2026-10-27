<?php

//Utiliza la zona horaria local para las operaciones de fecha de PHP.
date_default_timezone_set('America/Costa_Rica');

//Reporta todos los errores, pero evita mostrarlos al usuario.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

//Guarda los detalles tecnicos fuera del directorio publico.
ini_set(
    'error_log',
    __DIR__ . '/../storage/logs/php-error.log'
);

//Atiende errores y excepciones que no se hayan capturado previamente.
set_exception_handler(function (Throwable $error): void {
    //Conserva los detalles para investigar el problema.
    error_log((string) $error);

    //Devuelve una respuesta generica sin revelar informacion interna.
    http_response_code(500);

    echo 'Ocurrió un error interno. Intente nuevamente más tarde.';
});

//Carga la funcion de conexion, sin abrir todavia una conexion.
require_once __DIR__ . '/../config/database.php';

//Configura e inicia la sesion antes de generar contenido.
require_once __DIR__ . '/session.php';