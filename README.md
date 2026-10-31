# SIGEL — Sistema de Gestión de Préstamo de Equipos de Laboratorio

Proyecto 1 de Programación en Ambiente Web I, ISW-613.

Estudiante: Ericka Cordero.

## Descripción

Aplicación web para gestionar equipos y préstamos de un laboratorio
universitario.

El sistema tendrá dos roles: administrador y operador.
Se desarrolla con PHP y MySQL/MariaDB, sin frameworks ni librerías
de terceros.

## Estado actual

- Virtual host local funcionando.
- Tablas de roles y usuarios creadas.
- Roles y usuarios de prueba agregados.
- Conexión PDO comprobada.
- Configuración privada excluida de Git.
- Registro de errores en un archivo privado.
- Inicio de sesiones configurado.
- Funciones de escape HTML y CSRF probadas.

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

Agregar el siguiente bloque. Cambiar RUTA_DEL_PROYECTO por la ruta
completa de la carpeta clonada, usando barras /.

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

En C:\xampp\apache\conf\httpd.conf, comprobar que esta línea
no tenga # al inicio:

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

Abrir:

http://sigel.local

Por ahora se muestra:

SIGEL: el virtual host funciona.

## Credenciales de prueba

Estas cuentas son solo para pruebas locales.
El formulario de login todavía está pendiente.

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

Las carpetas para imágenes y recursos estáticos se completarán
al desarrollar esos módulos.

## Sesiones

Las páginas y acciones cargarán includes/bootstrap.php antes
de mostrar contenido.

La cookie sigel_session guarda el identificador de sesión.
Los datos de la sesión se guardan en el servidor.

La cookie usa HttpOnly y SameSite=Lax.
Secure se activa cuando el sitio utiliza HTTPS.
El entorno local actual utiliza HTTP.

Todavía faltan el login, la regeneración del identificador al entrar,
el tiempo límite por inactividad y el cierre de sesión.

## Manejo de errores

El usuario recibe un mensaje general cuando ocurre un error interno.

Los detalles se guardan en storage/logs/php-error.log.
Este archivo queda fuera de public/ y no se sube a Git.

PHP utiliza la zona horaria America/Costa_Rica.

## Escape HTML y CSRF

La función escapar() permite mostrar datos como texto en HTML.
Utiliza htmlspecialchars() con ENT_QUOTES, ENT_SUBSTITUTE y UTF-8.

El token CSRF se crea con random_bytes() y se guarda en la sesión.
Los formularios lo enviarán en un campo oculto.

El servidor lo revisará con hash_equals() antes de realizar cambios.

Se hicieron estas pruebas:

- Mostrar una etiqueta script como texto.
- Enviar el formulario con el token correcto.
- Cambiar el token y comprobar que el servidor rechaza el envío.

La página temporal se eliminó después de las pruebas.
Estas funciones se usarán en los formularios que se construyan.

## Pendientes

- Login y control de acceso por rol.
- Cierre de sesión y expiración por inactividad.
- CRUD de usuarios, categorías y equipos.
- Búsqueda, paginación y eliminación lógica.
- Préstamos y validación de fechas.
- Transacciones y control de concurrencia.
- Imágenes de equipos.
- Recordarme, preferencias y equipos recientes.
- Reporte y exportación CSV.
- Bitácora.
- Regla de negocio individual asignada por el profesor.