USE sigel;

INSERT INTO roles (nombre)
VALUES
    ('administrador'),
    ('operador');

INSERT INTO usuarios (rol_id, nombre, correo, password_hash)
VALUES (
    (SELECT id FROM roles WHERE nombre = 'administrador'),
    'Administrador de prueba',
    'admin@sigel.local',
    '$2y$10$Epig7bg76S8MmcoERQO3T.9xwHb/vJU6f5R02I13r8r.RkGhEX.9S'
);

INSERT INTO usuarios (rol_id, nombre, correo, password_hash)
VALUES (
    (SELECT id FROM roles WHERE nombre = 'operador'),
    'Operador de prueba',
    'operador@sigel.local',
    '$2y$10$u7.RONHjW4GOaHmNg5ontOawVlIE0Bg4ltTz2TrDav/ieQDfyYDWi'
);

INSERT INTO categorias (nombre, dias_max_prestamo)
VALUES
    ('Computadoras', 3),
    ('Proyectores', 2),
    ('Microscopios', 1),
    ('Instrumentos de medicion', 2),
    ('Audio', 3),
    ('Camaras', 2);

-- Define los dominios iniciales permitidos para los correos.
INSERT INTO configuracion (clave, valor)
VALUES ('dominios_permitidos', 'sigel.local,utn.ac.cr');