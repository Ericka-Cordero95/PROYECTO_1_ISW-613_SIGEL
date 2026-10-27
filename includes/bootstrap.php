
<?php
date_default_timezone_set('America/Costa_Rica');
error_reporting(E_ALL); //Activa el reporte de todas las categorías de errores

ini_set('display_errors', '0');//Evita mostrar detalles tecnicos en la respuesta
ini_set('log_errors', '1');//Activa su registro en archivo
ini_set(
    'error_log', //	Define dónde se guardan los errores
    __DIR__ . '/../storage/logs/php-error.log'//desde la carpeta includes, subir un nivel y entrar en storage.
);
//Atiende las excepciones que el codigo no haya capturado
set_exception_handler(function (Throwable $error): void {//Throwable:Permite recibir tanto excepciones como errores de PHP
    error_log((string) $error);

    http_response_code(500);//Comunica que ocurrio un error interno del servido

    echo 'Ocurrió un error interno. Intente nuevamente más tarde.';
});
//Carga la conexion una sola vez por peticion
require_once __DIR__ . '/../config/database.php';