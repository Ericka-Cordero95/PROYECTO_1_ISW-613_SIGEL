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