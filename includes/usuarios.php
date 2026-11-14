<?php

// Cuenta las cuentas no eliminadas que coinciden con la busqueda.
function contarUsuarios(PDO $pdo, string $busqueda): int
{
    $consulta = $pdo->prepare(
        'SELECT COUNT(*)
         FROM usuarios
         WHERE eliminado_en IS NULL
           AND (nombre LIKE :nombre OR correo LIKE :correo)'
    );
    $consulta->execute([
        'nombre' => '%' . $busqueda . '%',
        'correo' => '%' . $busqueda . '%',
    ]);

    return (int) $consulta->fetchColumn();
}

// Consulta una pagina sin recuperar contrasenas ni hashes.
function listarUsuarios(PDO $pdo, string $busqueda, int $cantidad, int $inicio): array
{
    $consulta = $pdo->prepare(
        'SELECT u.id, u.nombre, u.correo, u.activo, r.nombre AS rol
         FROM usuarios u
         INNER JOIN roles r ON r.id = u.rol_id
         WHERE u.eliminado_en IS NULL
           AND (u.nombre LIKE :nombre OR u.correo LIKE :correo)
         ORDER BY u.nombre, u.id
         LIMIT :cantidad OFFSET :inicio'
    );
    $consulta->bindValue(':nombre', '%' . $busqueda . '%', PDO::PARAM_STR);
    $consulta->bindValue(':correo', '%' . $busqueda . '%', PDO::PARAM_STR);
    $consulta->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
    $consulta->bindValue(':inicio', $inicio, PDO::PARAM_INT);
    $consulta->execute();

    return $consulta->fetchAll(PDO::FETCH_ASSOC);
}

// Obtiene los campos que necesita el formulario de edicion.
function buscarUsuario(PDO $pdo, int $id): array|false
{
    $consulta = $pdo->prepare(
        'SELECT id, rol_id, nombre, correo, activo
         FROM usuarios
         WHERE id = :id AND eliminado_en IS NULL
         LIMIT 1'
    );
    $consulta->execute(['id' => $id]);

    return $consulta->fetch(PDO::FETCH_ASSOC);
}

// Muestra solamente los roles utilizados por la aplicacion.
function listarRoles(PDO $pdo): array
{
    $consulta = $pdo->query(
        "SELECT id, nombre FROM roles
         WHERE nombre IN ('administrador', 'operador')
         ORDER BY nombre, id"
    );

    return $consulta->fetchAll(PDO::FETCH_ASSOC);
}
