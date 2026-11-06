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

-- Agrega equipos de prueba relacionados con categorias existentes.
INSERT INTO equipos (
    categoria_id,
    codigo,
    nombre,
    marca,
    modelo,
    numero_serie,
    estado
)
SELECT
    id,
    'EQ-001',
    'Computadora portátil 1',
    'Dell',
    'Latitude',
    'SERIE-001',
    'disponible'
FROM categorias
WHERE nombre = 'Computadoras'
  AND eliminado_en IS NULL;

INSERT INTO equipos (
    categoria_id,
    codigo,
    nombre,
    marca,
    modelo,
    numero_serie,
    estado
)
SELECT
    id,
    'EQ-002',
    'Computadora portátil 2',
    'HP',
    'ProBook',
    'SERIE-002',
    'mantenimiento'
FROM categorias
WHERE nombre = 'Computadoras'
  AND eliminado_en IS NULL;

INSERT INTO equipos (
    categoria_id,
    codigo,
    nombre,
    marca,
    modelo,
    numero_serie,
    estado
)
SELECT
    id,
    'EQ-003',
    'Proyector de laboratorio',
    'Epson',
    'PowerLite',
    'SERIE-003',
    'disponible'
FROM categorias
WHERE nombre = 'Proyectores'
  AND eliminado_en IS NULL;