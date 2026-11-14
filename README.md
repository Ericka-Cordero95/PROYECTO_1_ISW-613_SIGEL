# SIGEL — Sistema de Gestión de Préstamo de Equipos de Laboratorio

**Universidad Técnica Nacional**  
**Carrera:** Ingeniería del Software  
**Curso:** Programación en Ambiente Web I, ISW-613  
**Proyecto:** Proyecto 1  
**Estudiante:** Ericka Cordero

## Descripción

SIGEL es una aplicación web para administrar equipos y préstamos de un laboratorio universitario. Utiliza PHP 8.x y MySQL/MariaDB mediante PDO, sin frameworks ni librerías de terceros en el código de la aplicación.

El administrador gestiona usuarios, categorías y equipos, y consulta reportes. El operador consulta equipos y registra y gestiona préstamos. Los permisos se comprueban en el servidor.

## Requisitos

- Apache y PHP 8.x, disponibles en XAMPP.
- MySQL o MariaDB con motor InnoDB y soporte de columnas JSON.
- Extensiones PDO MySQL y Fileinfo habilitadas en PHP.
- Git y un navegador web.
- Permisos de escritura para PHP en `storage/logs/` y `storage/equipos/`.

## Instalación y despliegue local

### 1. Clonar el repositorio

Ejecutar en Git Bash:

```bash
git clone https://github.com/Ericka-Cordero95/PROYECTO_1_ISW-613_SIGEL.git
cd PROYECTO_1_ISW-613_SIGEL
```

### 2. Configurar el dominio local

Abrir el Bloc de notas como administrador y editar:

```text
C:\Windows\System32\drivers\etc\hosts
```

Agregar esta línea y guardar sin añadir la extensión `.txt`:

```text
127.0.0.1 sigel.local
```

### 3. Configurar Apache

Editar el archivo:

```text
C:\xampp\apache\conf\extra\httpd-vhosts.conf
```

Agregar el siguiente bloque. Sustituir `RUTA_DEL_PROYECTO` por la ruta absoluta de la carpeta clonada, utilizando barras `/`:

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

Reiniciar Apache desde XAMPP. El `DocumentRoot` debe apuntar únicamente a `public/`.

### 4. Crear la base de datos

Iniciar MySQL desde XAMPP y abrir [phpMyAdmin](http://localhost/phpmyadmin/).

En una instalación nueva, importar una sola vez y en este orden:

1. `sql/schema.sql`: crea la base `sigel` y sus tablas.
2. `sql/seed.sql`: carga los datos iniciales de prueba.

El esquema incluye las siguientes tablas:

| Tabla | Uso |
|---|---|
| `roles` | Roles de acceso. |
| `usuarios` | Cuentas, credenciales y control de intentos. |
| `categorias` | Categorías y días máximos de préstamo. |
| `configuracion` | Parámetros modificables, como los dominios permitidos. |
| `bitacora` | Registro de operaciones de la aplicación. |
| `equipos` | Inventario de equipos. |
| `equipo_imagenes` | Metadatos de las imágenes de equipos. |
| `prestamos` | Destinatario, operador, estado y fechas del préstamo. |
| `prestamo_detalle` | Equipos asociados y fecha de devolución. |
| `auth_tokens` | Tokens de la opción «Recordarme». |

`schema.sql` contiene una sola declaración de `bitacora` e incluye `auth_tokens`. No se debe reimportar el esquema completo sobre una base que ya contiene las tablas.

### 5. Configurar la conexión y las carpetas privadas

Copiar `config/config.example.php` como `config/config.local.php` y completar sus campos de servidor, puerto, base de datos, usuario y contraseña de MySQL. La base utilizada es `sigel`.

`config.local.php` permanece fuera de `public/` y excluido de Git. La conexión se obtiene mediante `obtenerConexion()`, definida en `config/database.php`. Utiliza excepciones, consultas preparadas nativas y la zona horaria `-06:00` para las fechas locales. Los vencimientos de «Recordarme» se calculan explícitamente en UTC.

Comprobar que existan las carpetas privadas y que PHP pueda escribir en ellas. Desde la raíz del proyecto se pueden crear con:

```bash
mkdir -p storage/logs storage/equipos
```

Los archivos de imágenes subidos y el registro de errores no se incluyen en Git.

### 6. Abrir la aplicación

Con Apache y MySQL iniciados, abrir [SIGEL](http://sigel.local/).

## Credenciales de prueba

| Rol | Correo | Contraseña |
|---|---|---|
| Administrador | `admin@sigel.local` | `AdminSigel2026!` |
| Operador | `operador@sigel.local` | `OperadorSigel2026!` |

Estas credenciales corresponden a las cuentas de prueba documentadas para `sql/seed.sql`. Las contraseñas se almacenan como hashes. Si una contraseña se cambia desde la aplicación, la cuenta de esa instalación utiliza la nueva contraseña.

## Estructura del proyecto

| Ruta | Responsabilidad |
|---|---|
| `config/` | Configuración privada y conexión PDO. |
| `includes/bootstrap.php` | Carga la sesión, las funciones comunes y la recuperación mediante «Recordarme». |
| `includes/session.php` | Configura e inicia la sesión. |
| `includes/auth.php` | Inicio y cierre de sesión, vigencia y permisos por rol. |
| `includes/recordarme.php` | Creación, validación, rotación y revocación de tokens persistentes. |
| `includes/helpers.php` | Funciones comunes para escapar texto y redirigir. |
| `includes/csrf.php` | Generación y validación de tokens CSRF. |
| `includes/categorias.php` | Consultas de categorías. |
| `includes/usuarios.php` | Consultas de usuarios y roles. |
| `includes/equipos.php` | Consultas del inventario. |
| `includes/configuracion.php` | Lectura de configuración y validación de dominios. |
| `includes/bitacora.php` | Inserción de eventos de auditoría. |
| `public/index.php` | Formulario de inicio de sesión. |
| `public/pages/` | Listados, formularios, detalles y reportes. |
| `public/actions/` | Procesamiento de formularios y operaciones de escritura. |
| `storage/equipos/` | Imágenes privadas de los equipos. |
| `storage/logs/php-error.log` | Registro privado de errores técnicos. |
| `sql/schema.sql` | Estructura de la base de datos. |
| `sql/seed.sql` | Datos iniciales de prueba. |

Las páginas cargan `bootstrap.php`, comprueban los permisos y consultan sus datos. Los formularios de escritura envían POST a una acción que valida la entrada, realiza la operación y redirige mediante GET.

## Autenticación, sesiones y permisos

- El login valida correo, contraseña y CSRF en el servidor.
- La contraseña se comprueba con `password_verify()`; las nuevas contraseñas se almacenan con `password_hash()`.
- Las cuentas inactivas o eliminadas no pueden iniciar sesión.
- Tres intentos incorrectos bloquean la cuenta durante cinco minutos. Los intentos se procesan con bloqueo de la fila y transacción, incluso cuando llegan desde distintos navegadores. Un acceso correcto limpia los fallos.
- Al autenticar se renueva el identificador mediante `session_regenerate_id(true)` y se descarta el token CSRF anterior.
- La cookie `sigel_session` utiliza HttpOnly y SameSite=Lax. Secure se activa cuando la conexión es HTTPS; el despliegue local utiliza HTTP.
- La sesión vence después de 15 minutos de inactividad. La siguiente petición protegida detecta el vencimiento y solicita ingresar nuevamente.
- El cierre de sesión requiere POST y CSRF, destruye la sesión y elimina su cookie.
- Las páginas protegidas comprueban la cuenta y su rol en el servidor y envían `Cache-Control: no-store`.

| Funcionalidad | Administrador | Operador |
|---|---|---|
| Administrar usuarios y categorías | Permitido | Denegado |
| Administrar equipos | Permitido | Denegado |
| Consultar equipos e imágenes | Permitido | Permitido |
| Registrar y gestionar préstamos | Denegado | Permitido |
| Consultar el reporte y descargar CSV | Permitido | Denegado |

Ocultar botones o enlaces no sustituye la validación de permisos en páginas y acciones.

## Sesión persistente: Recordarme

El formulario de ingreso permite marcar **Recordarme durante 14 días**.

Al autenticar con la opción marcada, el servidor genera un selector y un validador mediante `random_bytes()`. La cookie `sigel_remember` contiene `selector:validador`. La tabla `auth_tokens` conserva el usuario, el selector, el hash SHA-256 del validador y su vencimiento en UTC.

Cuando llega una petición GET o HEAD sin sesión autenticada, el servidor busca el selector y comprueba el validador mediante `hash_equals()`, el vencimiento y el estado de la cuenta. Si es válido, cambia el selector y el validador dentro de una transacción y crea una sesión con un nuevo identificador. La rotación conserva la fecha original de vencimiento.

Los tokens inválidos o vencidos no autentican. Las cuentas inactivas, eliminadas o con bloqueo vigente tampoco pueden recuperar sesión. Los POST no recuperan automáticamente la sesión mediante esta cookie.

La cookie utiliza HttpOnly, SameSite=Lax y Secure cuando corresponde a HTTPS. Al cerrar sesión se revoca el token del navegador y se elimina la cookie persistente. Cuando se detecta que una sesión autenticada venció por inactividad, también se revoca su recordatorio.

## Categorías

El administrador puede crear, buscar, listar, editar y eliminar lógicamente categorías.

- El nombre es obligatorio y admite hasta 100 bytes con la validación actual de `strlen()`.
- Los días máximos de préstamo deben estar entre 1 y 365.
- La búsqueda por nombre y la paginación utilizan GET.
- Se permiten 5, 10, 20 y 50 registros por página. La cookie `sigel_categorias_cantidad` conserva la preferencia durante 30 días; un valor no permitido utiliza 10.
- La restricción de unicidad impide repetir nombres, incluidos los de categorías eliminadas.
- La eliminación establece `eliminado_en`, conserva la fila y la excluye del listado.

Guardar y eliminar requieren administrador, POST y CSRF. La operación y su evento de bitácora se confirman en una misma transacción. Las ediciones y eliminaciones bloquean la fila durante la operación.

## Usuarios y regla individual 25

El módulo de usuarios está reservado al administrador. Permite crear, buscar, listar, editar y eliminar lógicamente cuentas. El listado muestra nombre, correo, rol y estado, y excluye usuarios eliminados.

Se permiten 5, 10, 20 y 50 registros por página. La cookie `sigel_usuarios_cantidad` conserva la cantidad durante 30 días y utiliza 10 si recibe un valor no permitido. `includes/usuarios.php` contiene las consultas y no genera HTML.

El nombre admite hasta 100 bytes y el correo hasta 150 bytes. Los roles se validan en la base de datos. La contraseña es obligatoria al crear; al editar, dejarla vacía conserva la actual. Las contraseñas nuevas admiten entre 8 y 72 bytes, sin caracteres nulos.

El administrador no puede quitarse su propio rol administrativo ni eliminar su propia cuenta. La eliminación establece `eliminado_en` y `activo = 0` sin borrar la fila. La creación, edición y eliminación registran su evento de bitácora sin guardar contraseñas ni hashes en los JSON de auditoría.

Al cambiar la contraseña o eliminar la cuenta se revocan sus tokens de «Recordarme». Las sesiones existentes de una cuenta eliminada pierden acceso en la siguiente petición protegida.

### Dominio de correo permitido

La regla 25 exige que, al crear o editar una cuenta, su correo pertenezca a un dominio permitido y no esté repetido.

La lista se almacena en `configuracion`, en la fila cuya `clave` es `dominios_permitidos`. El campo `valor` contiene los dominios separados por comas. El valor inicial documentado es:

```text
sigel.local,utn.ac.cr
```

La aplicación consulta la configuración, separa los dominios, elimina espacios externos y compara el dominio exacto sin distinguir mayúsculas y minúsculas. Permitir `utn.ac.cr` no permite automáticamente subdominios ni `utn.ac.cr.otro.com`. Una lista ausente o vacía rechaza todos los dominios.

La restricción `uq_usuarios_correo` impide repetir correos, incluidos los de usuarios eliminados. Editar una cuenta manteniendo su propio correo no constituye un duplicado.

Cambiar los dominios en MySQL afecta al siguiente guardado sin modificar PHP. La regla se aplica al guardar usuarios; retirar un dominio no elimina ni desactiva automáticamente las cuentas existentes ni cambia el login.

## Equipos e imágenes

El inventario permite buscar por código o nombre y guardar la cantidad por página en la cookie validada `sigel_equipos_cantidad`. Se permiten 5, 10, 20 y 50 registros; un valor no permitido utiliza 10. El administrador crea, edita y elimina lógicamente equipos.

El código es único y queda reservado después de la eliminación. La edición comprueba la versión del registro para evitar sobrescribir cambios realizados desde otro formulario. Los equipos con préstamos activos no se pueden eliminar.

Ambos roles pueden consultar el detalle y las imágenes mediante una ruta autenticada. El administrador puede cargar imágenes JPG, PNG o WebP de hasta 2 MB. Se valida la extensión y el tipo MIME, se genera el nombre en el servidor y se guardan los metadatos en `equipo_imagenes`.

Los archivos se almacenan en `storage/equipos/`, fuera del directorio público. El operador puede volver a los últimos cinco equipos consultados mediante una cookie por cuenta; el listado excluye equipos eliminados.

## Préstamos

El operador puede registrar solicitudes para una cuenta activa, seleccionando uno o varios equipos disponibles de categorías activas. El listado muestra el destinatario, las fechas, el estado y los equipos asociados.

| Archivo | Responsabilidad |
|---|---|
| `public/pages/prestamo_form.php` | Formulario de registro. |
| `public/actions/prestamo_guardar.php` | Validación y registro de la solicitud. |
| `public/pages/prestamos.php` | Listado y acciones disponibles. |
| `public/actions/prestamo_estado.php` | Cambios de estado y devolución. |

El servidor comprueba que las fechas sean válidas para MySQL, que el fin sea posterior al inicio y que la duración respete el máximo de cada categoría. Las fechas del formulario corresponden a la hora de Costa Rica. Si una validación rechaza el envío, se conservan la persona, los equipos y las fechas válidas para corregirlos.

Un préstamo solicitado, aprobado o entregado ocupa su intervalo. El traslape se comprueba con la condición:

```sql
existente.fecha_inicio < nuevo.fecha_fin
AND existente.fecha_fin > nuevo.fecha_inicio
```

Si alguno de los equipos tiene un préstamo activo que se traslapa, se revierte toda la solicitud. Los intervalos consecutivos se permiten cuando uno comienza exactamente al finalizar el anterior.

| Estado actual | Acciones permitidas |
|---|---|
| Solicitado | Aprobar o cancelar. |
| Aprobado | Entregar o cancelar. |
| Entregado | Devolver. |
| Devuelto o cancelado | Préstamo cerrado. |

Al devolver, se registra la fecha de devolución de los equipos asociados. Cada escritura utiliza autorización, POST, CSRF y una transacción que incluye la bitácora.

## Reporte de equipos más prestados

El administrador consulta el reporte en `public/pages/reporte_equipos.php` mediante un rango inclusivo de fechas.

El reporte cuenta préstamos entregados o devueltos según la fecha de inicio programada y ordena los equipos por total de préstamos. Conserva el historial de equipos eliminados.

La descarga CSV utiliza los mismos filtros y resultados. Se genera con `fputcsv()` en UTF-8 y antepone un apóstrofo a los textos detectados como posibles fórmulas de una hoja de cálculo. El servidor rechaza rangos de fechas inválidos y el acceso del operador.

## Bitácora, validación y errores

La tabla `bitacora` registra el usuario responsable, la acción, la entidad, el identificador, los valores anteriores y posteriores en JSON, la IP y la fecha de la operación.

La aplicación inserta eventos y no ofrece acciones para modificarlos ni eliminarlos. En una creación, los valores anteriores son `NULL`. En una eliminación lógica, los valores posteriores conservan la fecha de eliminación. La auditoría comienza desde su integración; no se reconstruyen eventos anteriores sin información verificable.

Se registran operaciones de categorías, creación, edición y eliminación de usuarios, cambios de equipos, cargas de imágenes y operaciones de préstamos. Las escrituras y sus eventos se confirman juntos; un fallo revierte la transacción.

Las consultas con entradas externas utilizan parámetros preparados. Los datos se validan en el servidor y se escapan al mostrarlos en HTML. Los formularios que modifican datos requieren un token CSRF de sesión.

Los errores internos se muestran con mensajes generales y se registran en `storage/logs/php-error.log`. Este registro técnico es distinto de la bitácora de operaciones. PHP utiliza `America/Costa_Rica`; los tokens de «Recordarme» almacenan su vencimiento en UTC.

## Pruebas realizadas

Durante el desarrollo se registraron las siguientes verificaciones:

- Conexión PDO y funcionamiento del virtual host.
- Login, rechazo de credenciales incorrectas y bloqueo temporal.
- Logout, acceso sin sesión y expiración por inactividad.
- Restricción de acceso administrativo para el operador.
- Escape de texto HTML y rechazo de un token CSRF alterado.
- CRUD de categorías, búsqueda, paginación y preferencia en cookie.
- Validación desde PHP de dominios permitidos, rechazados y en mayúsculas.
- Eventos de creación, edición y eliminación de una categoría visibles en phpMyAdmin.
- Eliminación lógica de equipos y registro de su evento en bitácora.
- Registro inicial de un préstamo con varios equipos.

### Recordarme: pruebas confirmadas en XAMPP

| Prueba | Resultado |
|---|---|
| Revisar sintaxis de los archivos de autenticación | Sin errores de sintaxis. |
| Ingresar como operador con «Recordarme» marcado | Acceso correcto. |
| Revisar `auth_tokens` en phpMyAdmin | Se crea el token de la cuenta. |
| Consultar las cookies del navegador | Aparecen `sigel_session` y `sigel_remember`. |
| Eliminar solo `sigel_session` y abrir el inicio | Se recupera el acceso sin escribir la contraseña. |
| Consultar el selector después de recuperar la sesión | El selector cambia. |
| Cerrar sesión y volver a abrir el inicio | Se elimina el token de ese navegador y se solicita ingresar. |

## Pruebas de aceptación para la revisión final

Realizar estas pruebas sobre una instalación local. Los resultados esperados permiten comprobar el comportamiento antes de entregar; no constituyen una afirmación de que se hayan ejecutado en todas las instalaciones.

### Usuarios, categorías y equipos

Como administrador, crear estos registros de prueba:

| Registro | Datos |
|---|---|
| Usuario | Nombre: `Usuario de prueba final`; correo: `revision.final@utn.ac.cr`; rol: operador; contraseña: `PruebaFinal2026!`. |
| Categoría | Nombre: `Revision final`; días máximos: `2`. |
| Equipo 1 | Código: `PRUEBA-FINAL-01`; nombre: `Equipo final 1`; categoría: `Revision final`; estado: disponible. Marca, modelo y serie pueden quedar vacíos. |
| Equipo 2 | Código: `PRUEBA-FINAL-02`; nombre: `Equipo final 2`; categoría: `Revision final`; estado: disponible. Marca, modelo y serie pueden quedar vacíos. |

Comprobar lo siguiente:

- Los registros aparecen en sus listados y se encuentran mediante el buscador.
- Editar el usuario como `Usuario final editado` y dejar la contraseña vacía conserva el acceso con `PruebaFinal2026!`.
- Crear otra cuenta con `revision.final@utn.ac.cr` rechaza el duplicado y conserva los campos recuperables.
- Los correos `rechazo@gmail.com` y `rechazo@utn.ac.cr.otro.com` se rechazan. El dominio `UTN.AC.CR` en mayúsculas se acepta.
- Seleccionar 5 registros por página conserva la preferencia; enviar `cantidad=999` o manipular la cookie hace que se utilice 10.
- Abrir la edición de un equipo en dos pestañas, guardar en una y después en la otra rechaza el formulario que conserva la versión anterior.
- Cargar una imagen PNG real menor de 2 MB permite verla con ambos roles. Un archivo de texto renombrado como PNG, una extensión PHP o un archivo mayor de 2 MB se rechazan.
- Como operador, abrir seis equipos diferentes conserva solamente los últimos cinco en equipos recientes. Repetir una visita mueve ese equipo al inicio.

### Préstamos y reporte

Ingresar como `operador@sigel.local`, seleccionar como destinatario la cuenta `revision.final@utn.ac.cr` y utilizar los dos equipos anteriores.

| Prueba | Datos | Resultado esperado |
|---|---|---|
| Registrar | 18/11/2026, de 09:00 a 11:00; ambos equipos. | Una solicitud con dos filas de detalle. |
| Traslape | 18/11/2026, de 10:00 a 12:00; ambos equipos. | Rechazo completo y conservación del formulario. |
| Horario consecutivo | 18/11/2026, de 11:00 a 12:00; ambos equipos. | Registro permitido. Cancelar esta segunda solicitud después de comprobarla. |
| Duración excesiva | Del 20/11/2026 a las 09:00 al 23/11/2026 a las 09:00. | Rechazo por superar los dos días de la categoría. |
| Eliminación con préstamo activo | Intentar eliminar `PRUEBA-FINAL-01` como administrador. | Rechazo sin eliminar el equipo. |
| Estados | En la primera solicitud, aprobar, entregar y devolver. | Cambios válidos y fecha de devolución en ambos detalles. |
| Reporte | Como administrador, desde 18/11/2026 hasta 18/11/2026. | Cada equipo de prueba tiene total 1, si solo la primera solicitud fue entregada o devuelta. |
| CSV | Descargar con las mismas fechas. | Mismos equipos y totales que el reporte. |
| Rango invertido | Desde 19/11/2026 hasta 18/11/2026. | Mensaje de fecha final inválida. |
| Permiso del reporte | Abrir su URL como operador. | Acceso denegado, también para CSV. |

Después de cerrar o cancelar los préstamos de prueba, comprobar Eliminar → Cancelar y Eliminar → Aceptar en los CRUD. Cancelar conserva el registro; aceptar lo oculta y establece `eliminado_en`. En usuarios también se establece `activo = 0`. Las filas y sus eventos deben permanecer en la base de datos.

### Seguridad, auditoría e instalación

- Enviar un formulario de escritura sin CSRF válido debe rechazarse sin registrar una operación exitosa.
- Abrir una acción de escritura desde la barra del navegador con el rol autorizado debe responder `405 Método no permitido`.
- Acceder sin sesión a una página protegida debe dirigir al formulario de ingreso; un rol no autorizado debe recibir acceso denegado.
- Comprobar que los JSON de usuarios en `bitacora` no contienen contraseñas ni hashes.
- Para la regla 25, agregar temporalmente `ejemplo.com` a `dominios_permitidos`, crear `dominio.final@ejemplo.com`, restaurar la lista y comprobar que se rechazan tanto la edición conservando ese correo como una nueva cuenta con el dominio retirado.
- Probar «Recordarme» sin marcar la casilla y con una cookie vencida o alterada. Comprobar también cuentas inactivas, eliminadas o bloqueadas y el cierre por inactividad.
- Repetir una instalación desde cero con `schema.sql`, `seed.sql` y las credenciales documentadas, usando una base de pruebas separada de los datos que se desean conservar.
- Revisar que `config.local.php`, los errores y las imágenes subidas estén excluidos de Git. No versionar los ZIP utilizados para transferir el proyecto.
- Verificar el historial incremental de Git en al menos 12 días distintos, según el enunciado. El historial debe conservar las fechas reales de los avances.
- Preparar la defensa sobre sesiones, cookies, CSRF, sentencias preparadas, traslapes, bloqueos y transacciones. Comprobar que el repositorio se puede clonar e instalar en otro equipo.

### Resultado de las pruebas finales en XAMPP

Se completaron correctamente las pruebas de creación y edición de usuarios,
rechazo de correo duplicado, creación de categoría y equipos, registro de
préstamos con varios equipos, rechazo de traslapes, aceptación de horarios
consecutivos, cancelación, límite de duración y recorrido de estados hasta
la devolución.

También se comprobaron el reporte por rango de fechas, la descarga CSV,
el rechazo de un rango invertido y la restricción del reporte para el operador.
