SIGEL — Sistema de Gestión de Préstamo de Equipos de Laboratorio
Proyecto 1 de Programación en Ambiente Web I, ISW-613.
Universidad Técnica Nacional — Ingeniería del Software.
Estudiante: Ericka Cordero.
Descripción
Aplicación web para gestionar equipos y préstamos de un laboratorio universitario. Se desarrolla con PHP 8.x y MySQL/MariaDB, sin frameworks ni librerías de terceros en el código de la aplicación.

El sistema utiliza los roles administrador y operador. Actualmente están implementadas la autenticación, las sesiones, los permisos administrativos, el CRUD de categorías y la creación, consulta y edición de usuarios. Equipos cuenta con inventario, imágenes y consultas recientes. Préstamos permite registrar solicitudes y gestionar su estado. El registro inicial ya se probó en XAMPP.
Estado actual
Área	Implementación actual
Entorno	Virtual host con public/ como único directorio publicado y conexión PDO
Configuración	Credenciales privadas fuera de public/ y excluidas de Git
Autenticación	Login con correo y contraseña; bloqueo de cinco minutos después de tres fallos
Sesiones	Regeneración del identificador al autenticar, logout por POST y expiración por 15 minutos de inactividad
Autorización	Revisión de sesión y rol en páginas y acciones protegidas
Seguridad común	Escape HTML, tokens CSRF y consultas preparadas
Categorías	Crear, buscar, listar, editar y eliminar lógicamente
Preferencias de categorías	Cantidades de 5, 10, 20 y 50 con cookie validada
Usuarios	Crear, buscar, listar y editar; listado de 10 registros por página
Regla 25	Validación del dominio al crear y editar usuarios; correo único
Bitácora	Creación, edición y eliminación de categorías; creación y edición de usuarios
Equipos	Crear, buscar, listar, editar y eliminar lógicamente. Imágenes privadas y últimos cinco equipos consultados por el operador
Préstamos	Registro de uno o varios equipos, validación de duración y traslapes, listado y cambios de estado
Errores	Registro técnico en un archivo privado


La eliminación lógica de usuarios y su selector de cantidad con cookie todavía no están implementados. Las funcionalidades pendientes se detallan al final.
Requisitos
- XAMPP con Apache y PHP 8.x.
- MySQL o MariaDB con motor InnoDB y soporte de columnas JSON.
- Extensión PDO para MySQL habilitada.
- Git y navegador web.
- Permiso de escritura de PHP en storage/logs/.
Instalación y despliegue local
1. Clonar el repositorio
git clone https://github.com/Ericka-Cordero95/PROYECTO_1_ISW-613_SIGEL.git
cd PROYECTO_1_ISW-613_SIGEL
2. Configurar el nombre local
Abrir el Bloc de notas como administrador y editar:
C:\Windows\System32\drivers\etc\hosts
Agregar esta línea y guardar el archivo sin extensión .txt:
127.0.0.1 sigel.local
3. Configurar Apache
Editar:
C:\xampp\apache\conf\extra\httpd-vhosts.conf
Agregar el siguiente bloque. Sustituir RUTA_DEL_PROYECTO por la ruta absoluta de la carpeta clonada, usando barras /:
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
En C:\xampp\apache\conf\httpd.conf, comprobar que esta línea no tenga # al inicio:
Include conf/extra/httpd-vhosts.conf
Reiniciar Apache desde XAMPP. El DocumentRoot debe apuntar únicamente a public/, para que configuración, scripts SQL y archivos privados no queden publicados.
4. Crear la base de datos y cargar datos iniciales
Iniciar MySQL desde XAMPP y abrir phpMyAdmin.

En una instalación nueva, importar en este orden:
1. sql/schema.sql: crea la base sigel y sus tablas.
2. sql/seed.sql: agrega roles, cuentas de prueba, categorías y dominios permitidos iniciales.
El esquema actualizado debe incluir roles, usuarios, categorias, configuracion, bitacora, equipos, equipo_imagenes, prestamos y prestamo_detalle. La bitácora se llena con las operaciones realizadas en la aplicación.

Los scripts se ejecutan una vez en una instalación nueva. No reimportarlos sobre una base existente: las actualizaciones de desarrollo se aplican ejecutando únicamente los bloques nuevos necesarios.
5. Configurar la conexión
Copiar config/config.example.php como config/config.local.php y completar los campos existentes para servidor, puerto, base de datos, usuario y contraseña de MySQL.

La base de datos debe ser sigel. No subir config.local.php a Git.
6. Comprobar el acceso
Con Apache y MySQL iniciados, abrir SIGEL local.

Se muestra el formulario de inicio de sesión. Al entrar con una cuenta de prueba, se muestra su nombre y rol.
Credenciales de prueba por rol
Estas cuentas son exclusivamente para pruebas locales. Las contraseñas indicadas deben corresponder a los hashes de sql/seed.sql.
Rol	Correo	Contraseña
Administrador	admin@sigel.local	AdminSigel2026!
Operador	operador@sigel.local	OperadorSigel2026!


Las contraseñas se almacenan como hashes, nunca en texto plano.
Estructura y conexión entre archivos
Ruta	Responsabilidad
config/config.example.php	Plantilla de configuración local
config/config.local.php	Configuración privada de cada instalación; no versionada
config/database.php	Define obtenerConexion() para acceder a MySQL mediante PDO
includes/bootstrap.php	Carga configuración, sesión y funciones comunes antes de producir salida
includes/session.php	Configura e inicia la sesión
includes/auth.php	Define el cierre de sesión y las comprobaciones de sesión y rol
includes/helpers.php	Funciones comunes como escapar() y redirigir()
includes/csrf.php	Generación y validación de tokens CSRF
includes/categorias.php	Consultas de categorías
includes/usuarios.php	Consultas de usuarios y roles
includes/configuracion.php	Lectura de configuración y validación de dominios
includes/bitacora.php	Inserción de eventos de auditoría
public/index.php	Formulario de login
public/pages/	Páginas protegidas, listados y formularios
public/actions/	Procesamiento de formularios y operaciones de escritura
storage/logs/php-error.log	Errores técnicos; privado y excluido de Git
sql/schema.sql	Estructura de la base de datos
sql/seed.sql	Datos iniciales de prueba


Las imágenes subidas se guardan en storage/equipos/, fuera de public/ y excluidas de Git.

El flujo habitual es: la página carga bootstrap.php, comprueba el permiso y consulta sus datos. El formulario envía POST a una acción, que valida la entrada, realiza la operación y redirige mediante GET al listado. Cargar un archivo de funciones no ejecuta automáticamente todas sus funciones.
Sesiones y autenticación
- El login valida correo, contraseña y token CSRF en el servidor.
- La cuenta y su rol se consultan mediante una sentencia preparada.
- La contraseña se verifica con password_verify().
- Las cuentas inactivas o eliminadas no pueden entrar.
- Los primeros dos fallos muestran el mensaje de credenciales incorrectas. El tercero bloquea la cuenta durante cinco minutos.
- Intentar entrar durante el bloqueo no reinicia su duración. Un acceso correcto limpia los fallos.
- Al autenticar se ejecuta session_regenerate_id(true) y se renueva el token CSRF.
La cookie sigel_session contiene el identificador de sesión; los datos se guardan en el servidor. Utiliza HttpOnly y SameSite=Lax; Secure se activa cuando corresponde a HTTPS. El entorno local actual utiliza HTTP.

requerirSesion() comprueba la sesión, la inactividad y que la cuenta siga activa y no eliminada. También actualiza el nombre y el rol desde MySQL.

Después de 900 segundos sin peticiones protegidas, la siguiente petición cierra la sesión y vuelve al login. Para probarlo se utilizaron temporalmente 30 segundos y después se restauraron los 900 segundos.

El cierre de sesión utiliza POST y CSRF, destruye la sesión y elimina su cookie. Las páginas protegidas envían Cache-Control: no-store.

La sesión persistente «Recordarme» todavía está pendiente.
Permisos por rol
requerirRol() está en includes/auth.php. Primero comprueba la sesión y después el rol permitido.

Actualmente los módulos de categorías y usuarios están reservados al administrador, tanto en sus páginas como en sus acciones. Ocultar un enlace no sustituye la comprobación del servidor.

El administrador gestiona usuarios, categorías y equipos. Ambos roles pueden consultar equipos e imágenes. El operador registra y gestiona préstamos. Los reportes siguen pendientes.
Escape HTML, CSRF y errores
escapar() utiliza htmlspecialchars() con ENT_QUOTES, ENT_SUBSTITUTE y UTF-8 para mostrar los datos como texto en HTML.

El token CSRF se genera con random_bytes(), se conserva en la sesión y se envía en campos ocultos. Las acciones lo comprueban con hash_equals() antes de modificar datos.

Los formularios de escritura utilizan POST y redirección posterior mediante GET. La validación del navegador complementa la validación definitiva del servidor.

Los errores internos se presentan con mensajes generales. El detalle técnico se registra en storage/logs/php-error.log, fuera de public/ y excluido de Git. PHP utiliza la zona horaria America/Costa_Rica; las fechas generadas por MySQL dependen de la configuración de su sesión.

El registro de errores técnicos es distinto de la bitácora de operaciones.
Categorías
El administrador puede crear, consultar, editar y eliminar lógicamente categorías.
- includes/categorias.php contiene las consultas del módulo.
- public/pages/categorias.php muestra el listado, la búsqueda y la paginación.
- public/pages/categoria_form.php sirve para crear sin ID y editar con ID.
- public/actions/categoria_guardar.php valida y ejecuta la creación o edición.
- public/actions/categoria_eliminar.php guarda la fecha de eliminación.
La búsqueda por nombre y la paginación utilizan GET. Se permiten 5, 10, 20 y 50 registros por página. La cookie sigel_categorias_cantidad recuerda la preferencia durante 30 días. Su valor se valida contra la lista permitida; un valor inesperado utiliza 10.

El nombre es obligatorio y admite hasta 100 bytes con la validación actual de strlen(). Los días máximos de préstamo deben estar entre 1 y 365; este rango es una decisión inicial del proyecto. El registro de préstamos valida la duración contra el límite de cada categoría seleccionada.

La restricción uq_categorias_nombre impide nombres duplicados y también reserva los nombres de categorías eliminadas.

La eliminación establece eliminado_en; no borra la fila. Las consultas del listado excluyen las categorías eliminadas.

Guardar y eliminar requieren administrador, POST y CSRF. La operación y su evento de bitácora se confirman dentro de una misma transacción. Las ediciones y eliminaciones leen la fila con FOR UPDATE.
Usuarios
El módulo está reservado al administrador. El listado relaciona usuarios.rol_id con roles.id y muestra ID, nombre, correo, rol y estado. Incluye cuentas activas e inactivas, pero excluye las eliminadas.

Permite buscar por nombre o correo y actualmente muestra 10 registros por página. El selector de cantidad y su cookie aún están pendientes.
- includes/usuarios.php contiene las consultas y obtiene los roles disponibles.
- public/pages/usuarios.php muestra el listado y los enlaces Nuevo usuario y Editar.
- public/pages/usuario_form.php comparte el formulario de creación y edición.
- public/actions/usuario_guardar.php valida, guarda y registra el evento.
El nombre admite hasta 100 bytes y el correo hasta 150 bytes. El rol se comprueba en MySQL y solo se permiten administrador y operador.

La contraseña es obligatoria al crear. Al editar, dejarla vacía conserva la contraseña actual. Las contraseñas nuevas admiten entre 8 y 72 bytes, sin caracteres nulos; es una decisión inicial de validación. Se almacenan mediante password_hash().

El administrador no puede quitarse su propio rol administrativo. La edición actual conserva el estado activo de la cuenta.

La creación y edición aplican la regla 25 y guardan su evento de bitácora en la misma transacción. No se recuperan hashes para el formulario ni se registran contraseñas o hashes en la bitácora. El evento indica si se estableció una contraseña nueva mediante contrasena_actualizada.

La eliminación lógica de usuarios y una interfaz para cambiar su estado activo siguen pendientes. Desactivar una cuenta no sustituye la eliminación lógica exigida.
Regla de negocio individual 25: dominio de correo permitido
La validación está integrada en la creación y edición de usuarios.

La tabla configuracion tiene las columnas clave y valor. La lista está en el valor de la fila cuya clave es dominios_permitidos; no es una columna llamada dominios_permitidos.

Valor inicial de prueba:
sigel.local,utn.ac.cr
includes/configuracion.php consulta la lista en MySQL, separa los dominios por comas, elimina espacios externos y los convierte a minúsculas. Después compara el dominio exacto del correo sin distinguir mayúsculas. Una lista vacía o ausente rechaza todos los dominios.

Permitir utn.ac.cr no permite automáticamente sus subdominios ni dominios como utn.ac.cr.otro.com.

La restricción uq_usuarios_correo impide correos duplicados, incluso los de usuarios eliminados. La acción muestra un mensaje claro cuando el dominio no está permitido o el correo ya existe. Editar una cuenta sin cambiar su propio correo no constituye un duplicado.

Cambiar la lista desde MySQL afecta al siguiente envío sin modificar PHP. La regla se aplica al guardar usuarios, no al login: retirar un dominio no elimina ni desactiva automáticamente las cuentas existentes.
Prueba de cambio de configuración
1. Agregar temporalmente ejemplo.com al valor de dominios_permitidos, conservando los dominios anteriores.
2. Crear una cuenta con un correo de ese dominio: debe aceptarse.
3. Restaurar la lista original.
4. Intentar editar esa cuenta conservando su correo: debe rechazarse sin guardar cambios.
5. Intentar crear otra cuenta con ese dominio: debe rechazarse.
Esta prueba forma parte de la validación final de la regla y debe completarse antes de darla por verificada en el formulario.
Equipos
El inventario permite buscar por código o nombre y guardar la cantidad por página en una cookie. El administrador crea, edita y elimina lógicamente equipos. El código es único y queda reservado después de la eliminación. La edición comprueba la versión guardada para evitar sobrescribir cambios de otro formulario.

Ambos roles pueden abrir el detalle y consultar imágenes mediante una ruta autenticada. El administrador puede cargar imágenes JPG, PNG o WebP de hasta 2 MB. La aplicación valida la extensión y el tipo MIME, genera el nombre del archivo y guarda sus datos en equipo_imagenes.

El operador puede volver a los últimos cinco equipos consultados. Una cookie por cuenta guarda los identificadores en orden de visita y el listado excluye equipos eliminados.
Préstamos
El operador accede a Préstamos desde el inicio. El listado muestra el destinatario, las fechas, el estado y todos los equipos asociados. Desde allí puede abrir Nuevo préstamo y seleccionar una cuenta activa y uno o varios equipos disponibles de categorías activas.
Archivo	Responsabilidad
public/pages/prestamo_form.php	Formulario de registro
public/actions/prestamo_guardar.php	Validación y registro de la solicitud
public/pages/prestamos.php	Listado y acciones disponibles
public/actions/prestamo_estado.php	Cambios de estado y registro de la devolución
public/actions/equipo_eliminar.php	Bloqueo de la eliminación cuando existe un préstamo activo


prestamos guarda el destinatario, el operador, el estado y las fechas. prestamo_detalle relaciona cada equipo con su préstamo y guarda la fecha de devolución. Ambas tablas deben crearse en la base sigel, en ese orden.

El servidor valida que el fin sea posterior al inicio y que la duración respete el máximo de cada categoría. Las fechas corresponden a la hora de Costa Rica. Un préstamo solicitado, aprobado o entregado ocupa su intervalo. Si un equipo tiene un préstamo activo que se traslapa, se revierte toda la solicitud. Los intervalos consecutivos se permiten cuando uno empieza exactamente al terminar el anterior.
Estado actual	Acciones permitidas
Solicitado	Aprobar o cancelar
Aprobado	Entregar o cancelar
Entregado	Devolver
Devuelto o cancelado	Préstamo cerrado


Al devolver, se registra la fecha de devolución de todos los equipos. Cada escritura usa POST, token CSRF y autorización en el servidor. Los cambios y su bitácora se confirman juntos en una transacción. Los equipos con préstamos activos no se pueden eliminar.
Prueba de registro
Como operador, selecciona admin@sigel.local como destinatario y marca EQ-001 y EQ-003. Usa el 16/11/2026 de 09:00 a 11:00 en una base de prueba con ambos equipos libres. Deben quedar dos filas en prestamo_detalle con el mismo identificador de préstamo y el encabezado en estado solicitado.

El registro inicial funciona después de crear prestamo_detalle en MySQL. Queda por verificar el rechazo de traslapes, los horarios consecutivos, el límite por categoría, el bloqueo de eliminación y el recorrido completo de estados y bitácora.
Bitácora de operaciones
includes/bitacora.php define registrarBitacora(). La tabla bitacora contiene:
- Usuario responsable: usuario_id.
- Operación: accion.
- Entidad e identificador: entidad, entidad_id.
- Datos anteriores y posteriores en JSON: valores_antes, valores_despues.
- Dirección de la solicitud: ip.
- Fecha y hora: creado_en.
La aplicación solo inserta eventos; no ofrece acciones para modificarlos ni eliminarlos. En una creación, los valores anteriores son NULL. La eliminación lógica conserva en los valores posteriores la fecha de eliminación.

Está integrada en crear, editar y eliminar categorías, y en crear y editar usuarios. La operación y su evento usan la misma conexión PDO y transacción. Si falla alguna parte, se ejecuta rollBack(); solo se confirma con commit() cuando ambas partes terminan correctamente.

Las ediciones y eliminaciones bloquean la fila mediante FOR UPDATE durante la transacción. Los envíos rechazados por validación o duplicados no generan eventos de operación exitosa.

La auditoría comienza desde su integración. No se reconstruyen eventos anteriores sin información fiable. Git y el README documentan los avances de programación; la bitácora documenta operaciones dentro de SIGEL.

La bitácora también registra cambios de equipos, cargas de imágenes y operaciones de préstamos. La eliminación lógica de equipos quedó verificada en phpMyAdmin.
Pruebas y validación
Evidencias registradas durante el desarrollo
- Conexión PDO y funcionamiento del virtual host.
- Login correcto, fallos de credenciales y bloqueo temporal.
- Logout, acceso sin sesión y expiración por inactividad.
- Restricción de acceso administrativo para el operador.
- Escape de una etiqueta script como texto y rechazo de un token CSRF alterado.
- CRUD de categorías, búsqueda, paginación y preferencia en cookie.
- Validación de dominios permitidos, rechazados y en mayúsculas desde PHP.
- Eventos CREAR, EDITAR y ELIMINAR de una misma categoría visibles en phpMyAdmin.
Comprobaciones que deben cerrarse en el avance actual
- Crear y editar usuarios con dominio permitido y comprobar sus eventos.
- Rechazar dominio no permitido tanto al crear como al editar.
- Rechazar correo duplicado y conservar los datos recuperables del formulario.
- Editar sin cambiar la contraseña y comprobar el acceso con la contraseña anterior.
- Cambiar la contraseña y comprobar el acceso con la nueva.
- Completar la prueba de cambio de dominios desde MySQL descrita arriba.
- Confirmar que los JSON de usuarios no contienen contraseñas ni hashes.
- Comprobar que operaciones rechazadas o canceladas no generan eventos exitosos.
Esta lista distingue lo documentado previamente de las pruebas del último avance; no sustituye una revisión del código y ejecución completa antes de entregar.
Pendientes
- Verificar en XAMPP el recorrido completo de préstamos con los dos roles.
- Completar las pruebas de creación y edición de usuarios y de la regla 25.
- Implementar eliminación lógica de usuarios con permiso, CSRF, transacción y bitácora.
- Agregar selector de cantidad y cookie validada al listado de usuarios.
- Implementar «Recordarme» con selector/validador, hash del validador, expiración, rotación y revocación al cerrar sesión.
- Implementar reporte de equipos más prestados por rango de fechas y exportación CSV con fputcsv() y neutralización de fórmulas.
- Aplicar y comprobar permisos en cada nueva página y acción.
- Verificar la configuración PDO: excepciones y preparaciones emuladas desactivadas.
- Probar una instalación desde cero con los scripts y credenciales documentados.
