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

-- Guarda los datos del inventario de equipos.
CREATE TABLE equipos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT UNSIGNED NOT NULL,
    codigo VARCHAR(50) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    marca VARCHAR(100) NULL,
    modelo VARCHAR(100) NULL,
    numero_serie VARCHAR(100) NULL,
    estado ENUM('disponible', 'mantenimiento', 'baja')
        NOT NULL DEFAULT 'disponible',
    version INT UNSIGNED NOT NULL DEFAULT 1,
    eliminado_en DATETIME NULL,

    CONSTRAINT uq_equipos_codigo UNIQUE (codigo),

    CONSTRAINT fk_equipos_categoria
        FOREIGN KEY (categoria_id)
        REFERENCES categorias (id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT
) ENGINE=InnoDB;

-- Guarda los metadatos de las imagenes almacenadas fuera de public.
CREATE TABLE equipo_imagenes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipo_id INT UNSIGNED NOT NULL,
    nombre_archivo VARCHAR(100) NOT NULL,
    mime VARCHAR(50) NOT NULL,
    tamano_bytes INT UNSIGNED NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_equipo_imagenes_archivo UNIQUE (nombre_archivo),

    CONSTRAINT fk_equipo_imagenes_equipo
        FOREIGN KEY (equipo_id)
        REFERENCES equipos (id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT
) ENGINE=InnoDB;

-- Guarda a quien se presta, quien registra y las fechas del prestamo.
CREATE TABLE prestamos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    operador_id INT UNSIGNED NOT NULL,
    estado ENUM('solicitado', 'aprobado', 'entregado', 'devuelto', 'cancelado')
        NOT NULL DEFAULT 'solicitado',
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NOT NULL,

    CONSTRAINT chk_prestamos_fechas CHECK (fecha_fin > fecha_inicio),
    CONSTRAINT fk_prestamos_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id),
    CONSTRAINT fk_prestamos_operador
        FOREIGN KEY (operador_id) REFERENCES usuarios (id)
) ENGINE=InnoDB;

-- Relaciona varios equipos con un mismo prestamo.
CREATE TABLE prestamo_detalle (
    prestamo_id INT UNSIGNED NOT NULL,
    equipo_id INT UNSIGNED NOT NULL,
    fecha_devolucion DATETIME NULL,

    PRIMARY KEY (prestamo_id, equipo_id),
    INDEX idx_prestamo_detalle_equipo (equipo_id, prestamo_id),

    CONSTRAINT fk_prestamo_detalle_prestamo
        FOREIGN KEY (prestamo_id) REFERENCES prestamos (id),
    CONSTRAINT fk_prestamo_detalle_equipo
        FOREIGN KEY (equipo_id) REFERENCES equipos (id)
) ENGINE=InnoDB;

-- Guarda los recordatorios. La fecha de vencimiento se almacena en UTC.
CREATE TABLE auth_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    selector CHAR(18) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    validador_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    expira_en DATETIME NOT NULL,

    CONSTRAINT uq_auth_tokens_selector UNIQUE (selector),
    INDEX idx_auth_tokens_usuario (usuario_id),
    INDEX idx_auth_tokens_expiracion (expira_en),

    CONSTRAINT fk_auth_tokens_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE CASCADE
        ON UPDATE RESTRICT
) ENGINE=InnoDB;
