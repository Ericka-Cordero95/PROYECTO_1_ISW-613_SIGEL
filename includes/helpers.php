<?php

// Escapa texto para mostrarlo en HTML y en atributos entre comillas.
// No debe utilizarse como escape para JavaScript, CSS o consultas SQL.
function escapar(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}
// Envía al navegador a otra página y termina el código.
function redirigir(string $ruta): void
{
    header('Location: ' . $ruta, true, 303);
    exit;
}