CREATE DATABASE IF NOT EXISTS sigel
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE sigel;

CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(30) NOT NULL,
    CONSTRAINT uq_roles_nombre UNIQUE (nombre)
) ENGINE=InnoDB;

CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rol_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    intentos_fallidos INT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_hasta DATETIME NULL,
    eliminado_en DATETIME NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_usuarios_correo UNIQUE (correo),

    CONSTRAINT fk_usuarios_rol
        FOREIGN KEY (rol_id)
        REFERENCES roles (id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE categorias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    dias_max_prestamo INT UNSIGNED NOT NULL,
    eliminado_en DATETIME NULL,

    CONSTRAINT uq_categorias_nombre UNIQUE (nombre)
) ENGINE=InnoDB;

-- Guarda configuraciones que pueden cambiar sin modificar PHP.
CREATE TABLE configuracion (
    clave VARCHAR(100) NOT NULL PRIMARY KEY,
    valor TEXT NOT NULL
) ENGINE=InnoDB;

-- Guarda el historial de operaciones realizadas en el sistema.
CREATE TABLE bitacora (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    accion VARCHAR(30) NOT NULL,
    entidad VARCHAR(50) NOT NULL,
    entidad_id INT UNSIGNED NOT NULL,
    valores_antes JSON NULL,
    valores_despues JSON NULL,
    ip VARCHAR(45) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_bitacora_entidad (entidad, entidad_id),
    INDEX idx_bitacora_usuario_fecha (usuario_id, creado_en),

    CONSTRAINT fk_bitacora_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT
) ENGINE=InnoDB;

-- Guarda el historial de operaciones realizadas en el sistema.
CREATE TABLE bitacora (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    accion VARCHAR(30) NOT NULL,
    entidad VARCHAR(50) NOT NULL,
    entidad_id INT UNSIGNED NOT NULL,
    valores_antes JSON NULL,
    valores_despues JSON NULL,
    ip VARCHAR(45) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_bitacora_entidad (entidad, entidad_id),
    INDEX idx_bitacora_usuario_fecha (usuario_id, creado_en),

    CONSTRAINT fk_bitacora_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT
) ENGINE=InnoDB;
