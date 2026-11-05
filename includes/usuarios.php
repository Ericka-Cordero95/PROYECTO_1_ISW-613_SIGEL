<?php
//Este archivo contrendra las consultas, no genera HTML

//Cuenta los usuarios no eliminados que coinciden con la busqueda.
function contarUsuarios(PDO $pdo, string $busqueda): int
{
    //Usa parametros diferentes para buscar por nombre y correo.
    $consulta = $pdo->prepare(
        'SELECT COUNT(*)
         FROM usuarios
         WHERE eliminado_en IS NULL
           AND (
               nombre LIKE :nombre
               OR correo LIKE :correo
           )'
    );

    //Agrega comodines para buscar el texto en cualquier posicion.
    $consulta->execute([
        'nombre' => '%' . $busqueda . '%',
        'correo' => '%' . $busqueda . '%',
    ]);

    //Convierte el resultado del conteo a un numero entero.
    return (int) $consulta->fetchColumn();
}

//Obtiene los usuarios correspondientes a una pagina del listado.
function listarUsuarios(
    PDO $pdo,
    string $busqueda,
    int $cantidad,
    int $inicio
): array {
    //Relaciona cada usuario con su rol y excluye los eliminados.
    $consulta = $pdo->prepare(
        'SELECT
            u.id,
            u.nombre,
            u.correo,
            u.activo,
            r.nombre AS rol
         FROM usuarios u
         INNER JOIN roles r ON r.id = u.rol_id
         WHERE u.eliminado_en IS NULL
           AND (
               u.nombre LIKE :nombre
               OR u.correo LIKE :correo
           )
         ORDER BY u.nombre, u.id
         LIMIT :cantidad OFFSET :inicio'
    );

    //Envia la busqueda separada del texto SQL.
    $consulta->bindValue(':nombre', '%' . $busqueda . '%');
    $consulta->bindValue(':correo', '%' . $busqueda . '%');

    //Envia el limite y el desplazamiento como numeros enteros.
    $consulta->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
    $consulta->bindValue(':inicio', $inicio, PDO::PARAM_INT);

    //Ejecuta la consulta preparada.
    $consulta->execute();

    //Devuelve las filas encontradas sin incluir contrasenas.
    return $consulta->fetchAll();
}
