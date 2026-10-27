<?php

//Reduce el riesgo de aceptar un identificador de sesion echo por un atacante.
ini_set('session.use_strict_mode', '1');

//El identificador de sesion se transporta mediante cookie, no por la URL.
ini_set('session.use_only_cookies', '1');

//En el entorno local usamos http, con https la cookie debe ser Secure.
$esHttps = isset($_SERVER['HTTPS'])
    && $_SERVER['HTTPS'] !== 'off';

//Nombre propio para distinguir la cookie de sesion de SIGEL.
session_name('sigel_session');

//Estos parametros deben configurarse antes de iniciar la sesion.
session_set_cookie_params([
    'lifetime' => 0, //Crea una cookie de sesion, sin fecha de expiracion persistente
    'path' => '/',//Permite utilizar la cookie en todas las rutas del sitio
    'secure' => $esHttps,//Restringe el envio de la cookie a HTTPS cuando corresponde
    'httponly' => true,//Impide que JavaScript lea la cookie mediante document.cookie
    'samesite' => 'Lax',//Restringe el envio de la cookie en ciertas peticiones desde otros sitios
]);

//Inicia una sesion nueva o recupera la asociada con la cookie recibida.Permite utilizar $_SESSION
session_start();