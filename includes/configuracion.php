<?php
//consulta la configuración desde MySQL y convierte la lista separada por comas en un arreglo.

//Obtiene un valor de configuracion a partir de su clave.
function obtenerConfiguracion(PDO $pdo, string $clave): ?string
{
    //Busca una sola configuracion mediante un parametro preparado.
    $consulta = $pdo->prepare(
        'SELECT valor
         FROM configuracion
         WHERE clave = :clave
         LIMIT 1'
    );

    //Envia la clave separada del texto SQL.
    $consulta->execute(['clave' => $clave]);

    //Obtiene el valor o false cuando la clave no existe.
    $valor = $consulta->fetchColumn();

    //Devuelve null cuando no se encontro la configuracion.
    return $valor === false ? null : (string) $valor;
}

//Obtiene los dominios permitidos desde la base de datos.
function obtenerDominiosPermitidos(PDO $pdo): array
{
    //Consulta el valor actual en cada llamada.
    $valor = obtenerConfiguracion($pdo, 'dominios_permitidos');

    //Una configuracion ausente o vacia no permite ningun dominio.
    if ($valor === null || trim($valor) === '') {
        return [];
    }

    //Separa la lista utilizando las comas.
    $partes = explode(',', $valor);

    //Prepara el arreglo que contendra los dominios.
    $dominios = [];

    //Limpia y normaliza cada dominio de la lista.
    foreach ($partes as $parte) {
        //Quita espacios y convierte las letras a minusculas.
        $dominio = strtolower(trim($parte));

        //Omite entradas vacias y evita dominios repetidos.
        if (
            $dominio !== ''
            && !in_array($dominio, $dominios, true)
        ) {
            $dominios[] = $dominio;
        }
    }

    //Devuelve la lista lista para comparar.
    return $dominios;
}

//Comprueba el formato del correo y su dominio exacto.
function correoTieneDominioPermitido(
    PDO $pdo,
    string $correo
): bool {
    //Quita espacios del inicio y final.
    $correo = trim($correo);

    //Rechaza correos con un formato no valido.
    if (filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
        return false;
    }

    //Localiza el ultimo separador entre el usuario y el dominio.
    $posicion = strrpos($correo, '@');

    //Detiene la validacion si no existe el separador.
    if ($posicion === false) {
        return false;
    }

    //Extrae el dominio y lo convierte a minusculas.
    $dominio = strtolower(substr($correo, $posicion + 1));

    // Consulta la configuracion actual desde MySQL.
    $permitidos = obtenerDominiosPermitidos($pdo);

    //Exige una coincidencia exacta con alguno de los dominios.
    return in_array($dominio, $permitidos, true);
}