# SIGEL — Sistema de Gestión de Préstamo de Equipos de Laboratorio

Proyecto 1 de Programación en Ambiente Web I, ISW-613.  
Universidad Técnica Nacional — Ingeniería del Software.  
Estudiante: Ericka Cordero.

## Descripción

Aplicación web para gestionar equipos y préstamos de un laboratorio universitario. Se desarrolla con PHP 8.x y MySQL/MariaDB, sin frameworks ni librerías de terceros en el código de la aplicación.

El sistema utiliza los roles `administrador` y `operador`.

Actualmente están implementadas la autenticación, las sesiones, los permisos administrativos, el CRUD de categorías y la creación, consulta y edición de usuarios.

Los módulos de equipos y préstamos siguen pendientes.

## Estado actual

| Área | Implementación actual |
|---|---|
| Entorno | Virtual host con `public/` como único directorio publicado y conexión PDO |
| Configuración | Credenciales privadas fuera de `public/` y excluidas de Git |
| Autenticación | Login con correo y contraseña; bloqueo de cinco minutos después de tres fallos |
| Sesiones | Regeneración del identificador al autenticar, logout por POST y expiración por 15 minutos de inactividad |
| Autorización | Revisión de sesión y rol en páginas y acciones protegidas |
| Seguridad común | Escape HTML, tokens CSRF y consultas preparadas |
| Categorías | Crear, buscar, listar, editar y eliminar lógicamente |
| Preferencias de categorías | Cantidades de 5, 10, 20 y 50 con cookie validada |
| Usuarios | Crear, buscar, listar y editar; listado de 10 registros por página |
| Regla 25 | Validación del dominio al crear y editar usuarios; correo único |
| Bitácora | Creación, edición y eliminación de categorías; creación y edición de usuarios |
| Errores | Registro técnico en un archivo privado |
| Equipos | Tablas, datos iniciales, listado y búsqueda por código o nombre |
| Preferencias de equipos | Cantidades de 5, 10, 20 y 50 con cookie validada |

La eliminación lógica de usuarios y su selector de cantidad con cookie todavía no están implementados.

## Requisitos

- XAMPP con Apache y PHP 8.x.
- MySQL o MariaDB con motor InnoDB y soporte de columnas JSON.
- Extensión PDO para MySQL habilitada.
- Git.
- Navegador web.
- Permiso de escritura de PHP en `storage/logs/`.

## Instalación y despliegue local

### 1. Clonar el repositorio

Ejecutar en la terminal:

```bash
git clone https://github.com/Ericka-Cordero95/PROYECTO_1_ISW-613_SIGEL.git
cd PROYECTO_1_ISW-613_SIGEL
```

### 2. Configurar el nombre local

Abrir el Bloc de notas como administrador y editar:

```text
C:\Windows\System32\drivers\etc\hosts
```

Agregar esta línea y guardar el archivo sin extensión `.txt`:

```text
127.0.0.1 sigel.local
```

### 3. Configurar Apache

Editar:

```text
C:\xampp\apache\conf\extra\httpd-vhosts.conf
```

Agregar el siguiente bloque.

Sustituir `RUTA_DEL_PROYECTO` por la ruta absoluta de la carpeta clonada, usando barras `/`:

```apache
<VirtualHost *:80>
    ServerName sigel.local
    DocumentRoot "RUTA_DEL_PROYECTO/public"

    <Directory "RUTA_DEL_PROYECTO/public">
        Options -Indexes
        AllowOverride None
        Require all granted
        DirectoryIndex index.php
    </Directory>
</VirtualHost>
```

En `C:\xampp\apache\conf\httpd.conf`, comprobar que esta línea no tenga `#` al inicio:

```apache
Include conf/extra/httpd-vhosts.conf
```

Reiniciar Apache desde XAMPP.

El `DocumentRoot` debe apuntar únicamente a `public/`, para que la configuración, los scripts SQL y los archivos privados no queden publicados.

### 4. Crear la base de datos y cargar los datos iniciales

Iniciar MySQL desde XAMPP y abrir:

```text
http://localhost/phpmyadmin
```

En una instalación nueva, importar en este orden:

1. `sql/schema.sql`: crea la base `sigel` y sus tablas.
2. `sql/seed.sql`: agrega roles, cuentas de prueba, categorías y dominios permitidos iniciales.

El esquema actualizado debe incluir estas tablas:

- `roles`
- `usuarios`
- `categorias`
- `configuracion`
- `bitacora`

La bitácora se llena con las operaciones realizadas en la aplicación.

Los scripts se ejecutan una vez en una instalación nueva. No se deben reimportar sobre una base existente.

Durante el desarrollo, las actualizaciones se aplican ejecutando únicamente los bloques nuevos necesarios.

### 5. Configurar la conexión

Copiar:

```text
config/config.example.php
```

como:

```text
config/config.local.php
```

Completar los campos existentes para:

- Servidor.
- Puerto.
- Base de datos.
- Usuario de MySQL.
- Contraseña de MySQL.

La base de datos debe ser `sigel`.

El archivo `config.local.php` no se sube a Git.

### 6. Comprobar el acceso

Con Apache y MySQL iniciados, abrir:

```text
http://sigel.local
```

Se muestra el formulario de inicio de sesión.

Al entrar con una cuenta de prueba, se muestra su nombre y rol.

## Credenciales de prueba por rol

Estas cuentas son exclusivamente para pruebas locales.

Las contraseñas indicadas deben corresponder a los hashes de `sql/seed.sql`.

| Rol | Correo | Contraseña |
|---|---|---|
| Administrador | admin@sigel.local | AdminSigel2026! |
| Operador | operador@sigel.local | OperadorSigel2026! |

Las contraseñas se almacenan como hashes, nunca en texto plano.

## Estructura y conexión entre archivos

| Ruta | Responsabilidad |
|---|---|
| `config/config.example.php` | Plantilla de configuración local |
| `config/config.local.php` | Configuración privada de cada instalación; no versionada |
| `config/database.php` | Define `obtenerConexion()` para acceder a MySQL mediante PDO |
| `includes/bootstrap.php` | Carga configuración, sesión y funciones comunes antes de producir salida |
| `includes/session.php` | Configura e inicia la sesión |
| `includes/auth.php` | Define el cierre de sesión y las comprobaciones de sesión y rol |
| `includes/helpers.php` | Funciones comunes como `escapar()` y `redirigir()` |
| `includes/csrf.php` | Generación y validación de tokens CSRF |
| `includes/categorias.php` | Consultas de categorías |
| `includes/usuarios.php` | Consultas de usuarios y roles |
| `includes/configuracion.php` | Lectura de configuración y validación de dominios |
| `includes/bitacora.php` | Inserción de eventos de auditoría |
| `public/index.php` | Formulario de login |
| `public/pages/` | Páginas protegidas, listados y formularios |
| `public/actions/` | Procesamiento de formularios y operaciones de escritura |
| `storage/logs/php-error.log` | Errores técnicos; privado y excluido de Git |
| `sql/schema.sql` | Estructura de la base de datos |
| `sql/seed.sql` | Datos iniciales de prueba |

Las carpetas de imágenes y recursos se completarán al desarrollar equipos.

### Flujo de una petición

1. La página carga `includes/bootstrap.php`.
2. Comprueba la sesión y el permiso.
3. Consulta los datos necesarios mediante PDO.
4. Muestra el HTML escapando los datos.
5. El formulario envía POST a una acción.
6. La acción comprueba permiso, método, token CSRF y datos.
7. Ejecuta la operación y registra la bitácora cuando corresponde.
8. Redirige mediante GET al listado.

Cargar un archivo de funciones no ejecuta automáticamente todas sus funciones.

## Sesiones y autenticación

El login valida correo, contraseña y token CSRF en el servidor.

La cuenta y su rol se consultan mediante una sentencia preparada. La contraseña se verifica con `password_verify()`.

Las cuentas inactivas o eliminadas no pueden entrar.

### Bloqueo por intentos incorrectos

- Los primeros dos fallos muestran el mensaje de credenciales incorrectas.
- El tercero bloquea la cuenta durante cinco minutos.
- Intentar entrar durante el bloqueo no reinicia su duración.
- Un acceso correcto limpia los fallos.

### Sesión autenticada

Al autenticar se ejecuta `session_regenerate_id(true)` y se renueva el token CSRF.

En la sesión se guardan el identificador, el nombre, el rol y la última actividad.

La cookie `sigel_session` contiene el identificador de sesión. Los datos de la sesión se guardan en el servidor.

La cookie utiliza:

- `HttpOnly`.
- `SameSite=Lax`.
- `Secure` cuando corresponde a HTTPS.

El entorno local actual utiliza HTTP.

### Revisión de sesión

`requerirSesion()` comprueba:

- Que exista una sesión autenticada.
- Que no haya vencido por inactividad.
- Que la cuenta siga activa.
- Que la cuenta no esté eliminada.

También actualiza el nombre y el rol desde MySQL.

Después de 900 segundos sin peticiones protegidas, la siguiente petición cierra la sesión y vuelve al login.

Para probar la inactividad se utilizaron temporalmente 30 segundos. Después se restauraron los 900 segundos, equivalentes a 15 minutos.

### Cierre de sesión

El cierre de sesión utiliza POST y token CSRF.

Destruye la sesión y elimina su cookie.

Las páginas protegidas envían `Cache-Control: no-store`.

La sesión persistente «Recordarme» todavía está pendiente.

## Permisos por rol

La función `requerirRol()` está en `includes/auth.php`.

Primero comprueba la sesión y después el rol permitido.

Actualmente los módulos de categorías y usuarios están reservados al administrador, tanto en sus páginas como en sus acciones.

Ocultar un enlace no sustituye la comprobación del servidor.

Según el alcance del proyecto:

- El administrador gestionará usuarios, categorías, equipos y consultará reportes.
- El operador gestionará préstamos y consultará equipos.

Las funciones de equipos, préstamos y reportes aún están pendientes.

## Escape HTML, CSRF y errores

### Escape HTML

`escapar()` utiliza `htmlspecialchars()` con:

- `ENT_QUOTES`.
- `ENT_SUBSTITUTE`.
- Codificación UTF-8.

Permite mostrar los datos como texto en HTML.

### Protección CSRF

El token CSRF:

- Se genera con `random_bytes()`.
- Se conserva en la sesión.
- Se envía en campos ocultos.
- Se comprueba con `hash_equals()` antes de modificar datos.

Los formularios de escritura utilizan POST y redirección posterior mediante GET.

La validación del navegador complementa la validación definitiva del servidor.

### Manejo de errores

Los errores internos se presentan con mensajes generales.

El detalle técnico se registra en:

```text
storage/logs/php-error.log
```

Este archivo está fuera de `public/` y excluido de Git.

PHP utiliza la zona horaria `America/Costa_Rica`. Las fechas generadas por MySQL dependen de la configuración de su sesión.

El registro de errores técnicos es distinto de la bitácora de operaciones.

## Categorías

El administrador puede crear, consultar, editar y eliminar lógicamente categorías.

| Archivo | Función |
|---|---|
| `includes/categorias.php` | Consultas del módulo |
| `public/pages/categorias.php` | Listado, búsqueda y paginación |
| `public/pages/categoria_form.php` | Formulario compartido de creación y edición |
| `public/actions/categoria_guardar.php` | Validación y guardado |
| `public/actions/categoria_eliminar.php` | Eliminación lógica |

### Listado y preferencias

La búsqueda por nombre y la paginación utilizan GET.

Se permiten 5, 10, 20 y 50 registros por página.

La cookie `sigel_categorias_cantidad` recuerda la preferencia durante 30 días.

Su valor se valida contra la lista permitida. Un valor inesperado utiliza 10.

### Creación y edición

El formulario sin ID crea una categoría.

El formulario con ID carga una categoría existente para editarla.

El nombre es obligatorio y admite hasta 100 bytes con la validación actual de `strlen()`.

Los días máximos de préstamo deben estar entre 1 y 365. Este rango es una decisión inicial del proyecto.

Todavía falta utilizar ese límite en préstamos.

La restricción `uq_categorias_nombre` impide nombres duplicados y también reserva los nombres de categorías eliminadas.

### Eliminación lógica

La eliminación establece una fecha en `eliminado_en`; no borra la fila.

Las consultas del listado excluyen las categorías eliminadas.

### Seguridad y bitácora

Guardar y eliminar requieren:

- Rol administrador.
- Método POST.
- Token CSRF válido.
- Validación de datos en el servidor.

La operación y su evento de bitácora se confirman dentro de una misma transacción.

Las ediciones y eliminaciones leen la fila con `FOR UPDATE`.

## Usuarios

El módulo está reservado al administrador.

El listado relaciona `usuarios.rol_id` con `roles.id` y muestra:

- ID.
- Nombre.
- Correo.
- Rol.
- Estado.
- Enlace de edición.

Incluye cuentas activas e inactivas, pero excluye las eliminadas.

Permite buscar por nombre o correo y actualmente muestra 10 registros por página.

El selector de cantidad y su cookie aún están pendientes.

| Archivo | Función |
|---|---|
| `includes/usuarios.php` | Consultas de usuarios y roles |
| `public/pages/usuarios.php` | Listado y enlaces Nuevo usuario y Editar |
| `public/pages/usuario_form.php` | Formulario compartido de creación y edición |
| `public/actions/usuario_guardar.php` | Validación, guardado y registro de bitácora |

### Validaciones

El nombre admite hasta 100 bytes y el correo hasta 150 bytes.

El rol se comprueba en MySQL. Solo se permiten `administrador` y `operador`.

El administrador no puede quitarse su propio rol administrativo.

La edición actual conserva el estado activo de la cuenta.

### Contraseñas

La contraseña es obligatoria al crear.

Al editar, dejarla vacía conserva la contraseña actual.

Las contraseñas nuevas admiten entre 8 y 72 bytes, sin caracteres nulos. Este rango es una decisión inicial de validación.

Se almacenan mediante `password_hash()`.

### Regla individual y bitácora

La creación y edición aplican la regla 25 y guardan su evento de bitácora en la misma transacción.

No se recuperan hashes para el formulario ni se registran contraseñas o hashes en la bitácora.

El evento indica si se estableció una contraseña nueva mediante `contrasena_actualizada`.

La eliminación lógica de usuarios y una interfaz para cambiar su estado activo siguen pendientes.

Desactivar una cuenta no sustituye la eliminación lógica exigida.

## Regla de negocio individual 25: dominio de correo permitido

La validación está integrada en la creación y edición de usuarios.

La tabla `configuracion` tiene las columnas `clave` y `valor`.

La lista está en el valor de la fila cuya clave es `dominios_permitidos`. No es una columna llamada `dominios_permitidos`.

Valor inicial de prueba:

```text
sigel.local,utn.ac.cr
```

### Funcionamiento

`includes/configuracion.php`:

1. Consulta la lista en MySQL.
2. Separa los dominios por comas.
3. Elimina espacios externos.
4. Convierte los dominios a minúsculas.
5. Extrae el dominio del correo.
6. Comprueba una coincidencia exacta.

La comparación no distingue mayúsculas de minúsculas.

Una lista vacía o ausente rechaza todos los dominios.

Permitir `utn.ac.cr` no permite automáticamente sus subdominios ni dominios como `utn.ac.cr.otro.com`.

### Correo único

La restricción `uq_usuarios_correo` impide correos duplicados, incluso los de usuarios eliminados.

La acción muestra un mensaje claro cuando el dominio no está permitido o el correo ya existe.

Editar una cuenta sin cambiar su propio correo no constituye un duplicado.

### Cambios de configuración

Cambiar la lista desde MySQL afecta al siguiente envío sin modificar PHP.

La regla se aplica al guardar usuarios, no al login.

Retirar un dominio no elimina ni desactiva automáticamente las cuentas existentes.

### Prueba de cambio de configuración

1. Agregar temporalmente `ejemplo.com` al valor de `dominios_permitidos`, conservando los dominios anteriores.
2. Crear una cuenta con un correo de ese dominio: debe aceptarse.
3. Restaurar la lista original.
4. Intentar editar esa cuenta conservando su correo: debe rechazarse sin guardar cambios.
5. Intentar crear otra cuenta con ese dominio: debe rechazarse.

Esta prueba forma parte de la validación final de la regla y debe completarse antes de darla por verificada en el formulario.

## Bitácora de operaciones

`includes/bitacora.php` define `registrarBitacora()`.

La tabla `bitacora` contiene:

| Campo | Información |
|---|---|
| `usuario_id` | Usuario responsable |
| `accion` | Operación realizada |
| `entidad` | Tabla o módulo afectado |
| `entidad_id` | Identificador del registro |
| `valores_antes` | Datos anteriores en JSON |
| `valores_despues` | Datos posteriores en JSON |
| `ip` | Dirección de la solicitud |
| `creado_en` | Fecha y hora |

La aplicación solo inserta eventos. No ofrece acciones para modificarlos ni eliminarlos.

En una creación, los valores anteriores son `NULL`.

La eliminación lógica conserva en los valores posteriores la fecha de eliminación.

### Operaciones integradas

- Crear categorías.
- Editar categorías.
- Eliminar lógicamente categorías.
- Crear usuarios.
- Editar usuarios.

### Transacciones

La operación y su evento usan la misma conexión PDO y transacción.

Si falla alguna parte, se ejecuta `rollBack()`.

Solo se confirma con `commit()` cuando ambas partes terminan correctamente.

Las ediciones y eliminaciones bloquean la fila mediante `FOR UPDATE` durante la transacción.

Los envíos rechazados por validación o duplicados no generan eventos de operación exitosa.

### Alcance del historial

La auditoría comienza desde su integración.

No se reconstruyen eventos anteriores sin información fiable.

Git y el README documentan los avances de programación. La bitácora documenta operaciones dentro de SIGEL.

La integración en eliminación de usuarios, equipos y préstamos sigue pendiente.

## Pruebas y validación

### Evidencias registradas durante el desarrollo

- Conexión PDO y funcionamiento del virtual host.
- Login correcto, fallos de credenciales y bloqueo temporal.
- Logout, acceso sin sesión y expiración por inactividad.
- Restricción de acceso administrativo para el operador.
- Escape de una etiqueta script como texto.
- Rechazo de un token CSRF alterado.
- CRUD de categorías, búsqueda, paginación y preferencia en cookie.
- Validación de dominios permitidos, rechazados y en mayúsculas desde PHP.
- Eventos CREAR, EDITAR y ELIMINAR de una misma categoría visibles en phpMyAdmin.

## Equipos

El administrador y el operador pueden consultar el inventario.
El permiso se comprueba en el servidor.

La tabla equipos se relaciona con categorias mediante categoria_id.
El código de inventario tiene una restricción UNIQUE.

Los estados definidos son disponible, mantenimiento y baja.
La disponibilidad para una ventana de préstamo se calculará
con las fechas y estados de los préstamos.

El listado permite buscar por código o nombre.
Utiliza consultas preparadas, LIMIT y OFFSET.

La cookie sigel_equipos_cantidad conserva durante 30 días
la cantidad elegida: 5, 10, 20 o 50.
Un valor inesperado utiliza 10.

La tabla equipo_imagenes permite relacionar varias imágenes
con un equipo. La carga y visualización siguen pendientes.

La creación, edición, eliminación lógica, control de versión,
imágenes y bitácora de equipos siguen pendientes.

Se comprobó el listado, la búsqueda, la preferencia y el acceso
con ambos roles. La navegación entre varias páginas se comprobará
cuando existan suficientes equipos.

### Comprobaciones que deben cerrarse en el avance actual

- Crear y editar usuarios con dominio permitido y comprobar sus eventos.
- Rechazar dominio no permitido tanto al crear como al editar.
- Rechazar correo duplicado y conservar los datos recuperables del formulario.
- Editar sin cambiar la contraseña y comprobar el acceso con la contraseña anterior.
- Cambiar la contraseña y comprobar el acceso con la nueva.
- Completar la prueba de cambio de dominios desde MySQL.
- Confirmar que los JSON de usuarios no contienen contraseñas ni hashes.
- Comprobar que operaciones rechazadas o canceladas no generan eventos exitosos.

Esta lista distingue lo documentado previamente de las pruebas del último avance.

No sustituye una revisión del código y ejecución completa antes de entregar.

## Pendientes

- Completar las pruebas de creación y edición de usuarios y de la regla 25.
- Implementar eliminación lógica de usuarios con permiso, CSRF, transacción y bitácora.
- Agregar selector de cantidad y cookie validada al listado de usuarios.
- Implementar el CRUD de equipos con búsqueda, paginación, preferencia y eliminación lógica.
- Implementar imágenes de equipos: tamaño máximo, extensiones permitidas, MIME con `finfo`, nombres generados por el servidor y almacenamiento privado.
- Implementar préstamos con uno o varios equipos, fechas y estados: solicitado, aprobado, entregado, devuelto y cancelado.
- Rechazar traslapes de préstamos activos para el mismo equipo y aplicar transacciones y control de concurrencia.
- Integrar el límite de días de categorías en los préstamos según el diseño final.
- Integrar bitácora en las operaciones pendientes de usuarios, equipos y préstamos.
- Implementar «Recordarme» con selector/validador, hash del validador, expiración, rotación y revocación al cerrar sesión.
- Implementar la cookie de hasta cinco equipos consultados recientemente.
- Implementar reporte de equipos más prestados por rango de fechas y exportación CSV con `fputcsv()` y neutralización de fórmulas.
- Aplicar y comprobar permisos en cada nueva página y acción.
- Verificar la configuración PDO: excepciones y preparaciones emuladas desactivadas.
- Probar una instalación desde cero con los scripts y credenciales documentados.
- Revisar el historial incremental de Git y el requisito de commits en al menos 12 días distintos.
- Preparar la defensa: flujo con ambos roles, recorrido de código, manipulación de cookies, CSRF y URL, y modificación en vivo.