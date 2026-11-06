<?php

// Carga las funciones comunes y comprueba el permiso.
require_once __DIR__ . '/../../includes/bootstrap.php';
requerirRol(['administrador']);

// Solo acepta formularios enviados mediante POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido.');
}

// Comprueba el token antes de procesar los campos.
validarTokenCsrf();

// Carga la funcion de auditoria.
require_once __DIR__ . '/../../includes/bitacora.php';

// Define los campos que deben recibirse como textos.
$campos = [
    'id', 'version', 'categoria_id', 'codigo', 'nombre',
    'marca', 'modelo', 'numero_serie', 'estado'
];

$datos = [];

// Rechaza campos ausentes o enviados como arreglos.
foreach ($campos as $campo) {
    $valor = $_POST[$campo] ?? null;

    if (!is_string($valor)) {
        http_response_code(400);
        exit('Datos no válidos.');
    }

    $datos[$campo] = trim($valor);
}

// Decide si debe crear o editar.
$editando = $datos['id'] !== '';
$id = null;
$volver = '/pages/equipo_form.php';

// Valida el identificador cuando se solicita editar.
if ($editando) {
    $id = filter_var(
        $datos['id'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id === false) {
        http_response_code(400);
        exit('ID no válido.');
    }

    // Normaliza el ID para recuperar los datos del formulario.
    $datos['id'] = (string) $id;
    $volver .= '?id=' . $id;
}

// Conserva los campos cuando una validacion falla.
$_SESSION['equipo_datos'] = $datos;

// Valida los campos de texto con sus limites de bytes.
$limites = [
    'codigo' => 50,
    'nombre' => 100,
    'marca' => 100,
    'modelo' => 100,
    'numero_serie' => 100,
];

foreach ($limites as $campo => $limite) {
    if (strlen($datos[$campo]) > $limite) {
        $_SESSION['equipo_error'] =
            'Uno de los campos supera la longitud permitida.';

        redirigir($volver);
    }
}

// Exige los campos principales.
if ($datos['codigo'] === '' || $datos['nombre'] === '') {
    $_SESSION['equipo_error'] =
        'El código y el nombre son obligatorios.';

    redirigir($volver);
}

// Valida el identificador de categoria.
$categoriaId = filter_var(
    $datos['categoria_id'],
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($categoriaId === false) {
    $_SESSION['equipo_error'] = 'Seleccione una categoría válida.';
    redirigir($volver);
}

// Valida la version enviada por el formulario.
$version = filter_var(
    $datos['version'],
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($version === false) {
    $_SESSION['equipo_error'] = 'La versión del equipo no es válida.';
    redirigir($volver);
}

// Valida el estado aunque se haya alterado el formulario.
if (
    !in_array(
        $datos['estado'],
        ['disponible', 'mantenimiento', 'baja'],
        true
    )
) {
    $_SESSION['equipo_error'] = 'Seleccione un estado válido.';
    redirigir($volver);
}

// Obtiene la conexion compartida por la escritura y la bitacora.
$pdo = obtenerConexion();

try {
    // Inicia la transaccion antes de comprobar los registros relacionados.
    $pdo->beginTransaction();

    // Bloquea la categoria para evitar que se elimine durante el guardado.
    $consulta = $pdo->prepare(
        'SELECT id
         FROM categorias
         WHERE id = :id
           AND eliminado_en IS NULL
         FOR UPDATE'
    );

    $consulta->execute(['id' => $categoriaId]);

    // Rechaza categorias inexistentes o eliminadas.
    if ($consulta->fetchColumn() === false) {
        $pdo->rollBack();

        $_SESSION['equipo_error'] =
            'La categoría no existe o fue eliminada.';

        redirigir($volver);
    }

    // Una creacion no tiene valores anteriores.
    $antes = null;

    if ($editando) {
        // Bloquea el equipo y recupera sus datos actuales.
        $consulta = $pdo->prepare(
            'SELECT
                id, categoria_id, codigo, nombre, marca, modelo,
                numero_serie, estado, version, eliminado_en
             FROM equipos
             WHERE id = :id
               AND eliminado_en IS NULL
             FOR UPDATE'
        );

        $consulta->execute(['id' => $id]);
        $antes = $consulta->fetch(PDO::FETCH_ASSOC);

        // Cancela si el equipo fue eliminado o no existe.
        if ($antes === false) {
            $pdo->rollBack();
            unset($_SESSION['equipo_datos']);

            $_SESSION['equipo_mensaje'] =
                'El equipo no existe o ya fue eliminado.';

            redirigir('/pages/equipos.php');
        }

        // Detecta si otra operacion cambio el equipo desde que se abrio.
        if ((int) $antes['version'] !== $version) {
            $pdo->rollBack();

            // Descarta el formulario viejo para cargar los valores actuales.
            unset($_SESSION['equipo_datos']);

            $_SESSION['equipo_error'] =
                'Otro proceso modificó el equipo. Revise los datos actualizados y vuelva a intentar.';

            redirigir($volver);
        }
    }

    // Prepara los valores compartidos por INSERT y UPDATE.
    $parametros = [
        'categoria_id' => $categoriaId,
        'codigo' => $datos['codigo'],
        'nombre' => $datos['nombre'],
        'marca' => $datos['marca'] === '' ? null : $datos['marca'],
        'modelo' => $datos['modelo'] === '' ? null : $datos['modelo'],
        'numero_serie' => $datos['numero_serie'] === ''
            ? null
            : $datos['numero_serie'],
        'estado' => $datos['estado'],
    ];

    if ($editando) {
        // Actualiza e incrementa la version del equipo.
        $consulta = $pdo->prepare(
            'UPDATE equipos
             SET categoria_id = :categoria_id,
                 codigo = :codigo,
                 nombre = :nombre,
                 marca = :marca,
                 modelo = :modelo,
                 numero_serie = :numero_serie,
                 estado = :estado,
                 version = version + 1
             WHERE id = :id
               AND version = :version
               AND eliminado_en IS NULL'
        );

        $parametros['id'] = $id;
        $parametros['version'] = $version;

        $consulta->execute($parametros);

        // Comprueba que se actualizo exactamente un equipo.
        if ($consulta->rowCount() !== 1) {
            throw new RuntimeException(
                'No se pudo actualizar la version del equipo.'
            );
        }

        $accion = 'EDITAR';
    } else {
        // Crea el equipo utilizando la version inicial del esquema.
        $consulta = $pdo->prepare(
            'INSERT INTO equipos (
                categoria_id, codigo, nombre, marca,
                modelo, numero_serie, estado
             ) VALUES (
                :categoria_id, :codigo, :nombre, :marca,
                :modelo, :numero_serie, :estado
             )'
        );

        $consulta->execute($parametros);

        // Recupera el ID antes de insertar la bitacora.
        $id = (int) $pdo->lastInsertId();
        $accion = 'CREAR';
    }

    // Recupera los valores que realmente quedaron guardados.
    $consulta = $pdo->prepare(
        'SELECT
            id, categoria_id, codigo, nombre, marca, modelo,
            numero_serie, estado, version, eliminado_en
         FROM equipos
         WHERE id = :id'
    );

    $consulta->execute(['id' => $id]);
    $despues = $consulta->fetch(PDO::FETCH_ASSOC);

    if ($despues === false) {
        throw new RuntimeException(
            'No se pudo recuperar el equipo guardado.'
        );
    }

    // Registra el evento dentro de la misma transaccion.
    registrarBitacora(
        $pdo,
        (int) $_SESSION['usuario_id'],
        $accion,
        'equipos',
        $id,
        $antes,
        $despues
    );

    // Confirma el equipo y su evento juntos.
    $pdo->commit();
} catch (Throwable $error) {
    // Revierte cualquier cambio pendiente.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Identifica la restriccion de codigo unico.
    $codigoDuplicado =
        $error instanceof PDOException
        && (int) ($error->errorInfo[1] ?? 0) === 1062
        && str_contains($error->getMessage(), 'uq_equipos_codigo');

    if ($codigoDuplicado) {
        $_SESSION['equipo_error'] =
            'Ya existe un equipo con ese código.';
    } else {
        // Guarda el detalle tecnico solamente en el archivo de errores.
        error_log((string) $error);

        $_SESSION['equipo_error'] =
            'No se pudo guardar el equipo.';
    }

    redirigir($volver);
}

// Limpia los campos temporales despues del guardado correcto.
unset($_SESSION['equipo_datos'], $_SESSION['equipo_error']);

// Regresa al listado mediante GET.
$_SESSION['equipo_mensaje'] = 'Equipo guardado correctamente.';
redirigir('/pages/equipos.php');