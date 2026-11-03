<?php

//Cuenta las categorias que coinciden con la busqueda.
function contarCategorias(PDO $pdo, string $busqueda): int
{
    $consulta = $pdo->prepare(
            'SELECT COUNT(*)	
         FROM categorias
         WHERE eliminado_en IS NULL
           AND nombre LIKE :busqueda'
    );

    $consulta->execute(['busqueda' => '%' . $busqueda . '%']);

    return (int) $consulta->fetchColumn();
}

//Obtiene solamente los registros de la pagina solicitada.
function listarCategorias(
    PDO $pdo,
    string $busqueda,
    int $cantidad,
    int $inicio
): array {
    $consulta = $pdo->prepare(
        'SELECT id, nombre, dias_max_prestamo
         FROM categorias
         WHERE eliminado_en IS NULL
           AND nombre LIKE :busqueda
         ORDER BY nombre, id
         LIMIT :cantidad OFFSET :inicio'
    );

    $consulta->bindValue(':busqueda', '%' . $busqueda . '%');
    $consulta->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
    $consulta->bindValue(':inicio', $inicio, PDO::PARAM_INT);
    $consulta->execute();

    return $consulta->fetchAll();
}