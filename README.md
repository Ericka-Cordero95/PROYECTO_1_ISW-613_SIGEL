# SIGEL — Sistema de Gestión de Préstamo de Equipos de Laboratorio

Proyecto 1 de Programación en Ambiente Web I, ISW-613.

Estudiante: Ericka Cordero.

## Descripción

Aplicación web para gestionar equipos y préstamos de un laboratorio universitario.

El sistema tendrá dos roles: administrador y operador.
Se desarrolla con PHP y MySQL/MariaDB, sin frameworks ni librerías de terceros.

## Estado actual

- Virtual host local funcionando.
- Tablas de roles y usuarios creadas.
- Roles y usuarios de prueba agregados.
- Conexión PDO comprobada.
- Configuración privada excluida de Git.
- Registro de errores en un archivo privado.
- Inicio de sesiones configurado.
- Funciones de escape HTML y CSRF probadas.
- Login con correo y contraseña.
- Página de inicio que requiere sesión.
- Bloqueo de cinco minutos después de tres intentos incorrectos.
- Cierre de sesión por POST con token CSRF.
- Expiración después de 15 minutos sin actividad.
- Revisión de la cuenta en cada petición protegida.
- Control de acceso por rol en el servidor.
- Entrada al módulo de usuarios reservada al administrador.

## Requisitos

- XAMPP con Apache y PHP 8.x.
- MySQL o MariaDB con motor InnoDB.
- Extensión PDO para MySQL habilitada.
- Git.
- Navegador web.

## Instalación local

### 1. Clonar el repositorio

Ejecutar en la terminal:

```bash
git clone https://github.com/Ericka-Cordero95/PROYECTO_1_ISW-613_SIGEL.git
```

### 2. Configurar el nombre local

Abrir el Bloc de notas como administrador y editar:

```text
C:\Windows\System32\drivers\etc\hosts
```

Agregar esta línea y guardar el archivo sin extensión .txt:

```text
127.0.0.1 sigel.local
```

### 3. Configurar Apache

Editar:

```text
C:\xampp\apache\conf\extra\httpd-vhosts.conf
```

Agregar el siguiente bloque. Cambiar RUTA_DEL_PROYECTO por la ruta completa de la carpeta clonada, usando barras /.

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

En C:\xampp\apache\conf\httpd.conf, comprobar que esta línea no tenga # al inicio:

```apache
Include conf/extra/httpd-vhosts.conf
```

Reiniciar Apache desde XAMPP.

### 4. Crear la base de datos y cargar los datos

Iniciar MySQL desde XAMPP y abrir:

http://localhost/phpmyadmin

En una instalación nueva, importar los archivos en este orden:

1. sql/schema.sql: crea la base de datos y las tablas.
2. sql/seed.sql: agrega los roles y usuarios de prueba.

Los scripts actuales deben ejecutarse una vez en una instalación nueva.
Repetirlos sobre las mismas tablas y datos puede generar errores.

### 5. Configurar la conexión

Copiar config/config.example.php como config/config.local.php.

Completar la copia con los datos de MySQL del equipo:
servidor, puerto, base de datos, usuario y contraseña.

config.local.php no se sube a Git.

### 6. Comprobar el acceso

Con Apache y MySQL iniciados, abrir:

http://sigel.local

Se muestra el formulario de inicio de sesión.
Al entrar con una cuenta de prueba, se muestra su nombre y rol.

## Credenciales de prueba

Estas cuentas son solo para pruebas locales.

| Rol | Correo | Contraseña |
|---|---|---|
| Administrador | admin@sigel.local | AdminSigel2026! |
| Operador | operador@sigel.local | OperadorSigel2026! |

Las contraseñas se guardan como hashes en la base de datos.

## Estructura del proyecto

- config/: configuración y conexión PDO.
- includes/: funciones comunes e inicio de la aplicación.
- storage/: archivos privados.
- storage/logs/: registro de errores.
- sql/: estructura y datos iniciales.
- public/: único directorio publicado por Apache.
- public/pages/: páginas y formularios.
- public/actions/: archivos que procesan las operaciones.

Las carpetas para imágenes y recursos estáticos se completarán al desarrollar esos módulos.

## Sesiones

Las páginas y acciones cargan includes/bootstrap.php antes de mostrar contenido.

La cookie sigel_session guarda el identificador de sesión.
Los datos de la sesión se guardan en el servidor.

La cookie usa HttpOnly y SameSite=Lax.
Secure se activa cuando el sitio utiliza HTTPS.
El entorno local actual utiliza HTTP.

Al entrar correctamente, se cambia el identificador de sesión.
Se guardan el ID, el nombre, el rol y la última actividad.

La función requerirSesion() revisa que exista una sesión y que la cuenta
siga activa y no esté eliminada. También actualiza el nombre y el rol
desde la base de datos.

Después de 15 minutos sin peticiones a páginas protegidas, la siguiente
petición cierra la sesión y vuelve al login.

El botón Cerrar sesión envía un formulario POST con token CSRF.
Se borran los datos de la sesión, la cookie y la sesión del servidor.

Las páginas protegidas envían Cache-Control: no-store para indicar
al navegador que no debe guardarlas en caché.

## Manejo de errores

El usuario recibe un mensaje general cuando ocurre un error interno.

Los detalles se guardan en storage/logs/php-error.log.
Este archivo queda fuera de public/ y no se sube a Git.

PHP utiliza la zona horaria America/Costa_Rica.

## Escape HTML y CSRF

La función escapar() permite mostrar datos como texto en HTML.
Utiliza htmlspecialchars() con ENT_QUOTES, ENT_SUBSTITUTE y UTF-8.

El token CSRF se crea con random_bytes() y se guarda en la sesión.
El formulario de login lo envía en un campo oculto.
La acción lo revisa con hash_equals() antes de procesar los datos.

Después de entrar correctamente, se elimina el token anterior.
El siguiente formulario generará uno nuevo.

Se hicieron estas pruebas:

- Mostrar una etiqueta script como texto.
- Enviar el formulario con el token correcto.
- Cambiar el token y comprobar que el servidor rechaza el envío.

La página temporal se eliminó después de las pruebas.
Los próximos formularios también deberán usar estas funciones.

## Inicio de sesión

El formulario envía el correo, la contraseña y el token CSRF.

El servidor valida los datos y busca al usuario con una consulta preparada.
La contraseña se revisa con password_verify().

Las cuentas desactivadas o eliminadas no pueden entrar.

Los primeros dos fallos muestran un mensaje para revisar los datos.
El tercer fallo bloquea la cuenta durante cinco minutos y muestra el aviso de bloqueo.

Intentar entrar durante el bloqueo no reinicia el tiempo.
Cuando termina, se puede intentar nuevamente.

Un acceso correcto limpia los fallos y permite entrar a la página de inicio.

La página de inicio comprueba que exista un usuario en la sesión.
El control de permisos por rol todavía está pendiente.

## Pruebas del login

Se comprobó lo siguiente:

- Una cuenta sin bloqueo puede entrar con la contraseña correcta.
- El primer y segundo fallo muestran el mensaje de datos incorrectos.
- El tercer fallo muestra el mensaje de bloqueo.
- Durante el bloqueo no se permite entrar, aunque la contraseña sea correcta.

## Pruebas de sesión

- Cerrar sesión vuelve al login y muestra un mensaje.
- Abrir la página de inicio después de salir vuelve al login.
- Abrir logout.php directamente por URL rechaza la petición.
- La sesión termina por inactividad.
- Desactivar una cuenta impide que siga usando una página protegida.

Para probar la inactividad se usaron temporalmente 30 segundos.
Después de la prueba, el límite se dejó en 900 segundos, equivalentes
a 15 minutos.

## Permisos por rol

La función requerirRol() está en includes/auth.php.
Primero revisa la sesión y después comprueba el rol del usuario.

La página de usuarios permite entrar solo al administrador.
El operador no puede acceder aunque escriba la URL directamente.

El enlace de usuarios se muestra solo al administrador.
Ocultar el enlace no reemplaza la revisión del servidor.

Se probó el acceso como administrador, como operador y sin sesión.
El CRUD de usuarios todavía está pendiente.

## Pendientes

- Aplicar los permisos en cada página y acción de los próximos módulos.
- CRUD de usuarios, categorías y equipos.
- Búsqueda, paginación y eliminación lógica.
- Préstamos y validación de fechas.
- Transacciones y control de concurrencia.
- Imágenes de equipos.
- Recordarme, preferencias y equipos recientes.
- Reporte y exportación CSV.
- Bitácora.
- Regla de negocio individual asignada por el profesor.