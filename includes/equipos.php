<?php

// Cuenta los equipos no eliminados que coinciden con la busqueda.
function contarEquipos(PDO $pdo, string $busqueda): int
{
    // Busca por codigo o nombre usando parametros diferentes.
    $consulta = $pdo->prepare(
        'SELECT COUNT(*)
         FROM equipos
         WHERE eliminado_en IS NULL
           AND (
               codigo LIKE :codigo
               OR nombre LIKE :nombre
           )'
    );

    // Envia los valores separados de la sentencia SQL.
    $consulta->execute([
        'codigo' => '%' . $busqueda . '%',
        'nombre' => '%' . $busqueda . '%',
    ]);

    return (int) $consulta->fetchColumn();
}

// Obtiene solamente los equipos de la pagina solicitada.
function listarEquipos(
    PDO $pdo,
    string $busqueda,
    int $cantidad,
    int $inicio
): array {
    // Relaciona cada equipo con el nombre de su categoria.
    $consulta = $pdo->prepare(
        'SELECT
            e.id,
            e.codigo,
            e.nombre,
            e.marca,
            e.modelo,
            e.estado,
            c.nombre AS categoria
         FROM equipos e
         INNER JOIN categorias c ON c.id = e.categoria_id
         WHERE e.eliminado_en IS NULL
           AND (
               e.codigo LIKE :codigo
               OR e.nombre LIKE :nombre
           )
         ORDER BY e.codigo, e.id
         LIMIT :cantidad OFFSET :inicio'
    );

    // Vincula los filtros como textos.
    $consulta->bindValue(':codigo', '%' . $busqueda . '%');
    $consulta->bindValue(':nombre', '%' . $busqueda . '%');

    // Vincula la paginacion como numeros enteros.
    $consulta->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
    $consulta->bindValue(':inicio', $inicio, PDO::PARAM_INT);

    $consulta->execute();

    return $consulta->fetchAll(PDO::FETCH_ASSOC);
}