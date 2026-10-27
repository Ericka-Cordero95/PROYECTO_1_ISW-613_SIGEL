# SIGEL — Sistema de Gestión de Préstamo de Equipos de Laboratorio

Proyecto 1 Programación en Ambiente Web I, ISW-613.

Estudiante; Ericka Cordero.

## Descripción

Aplicación web para equipos y préstamos
de un laboratorio universitario.

El sistema tendrá dos roles: administrador y operador.
Se desarrolla con PHP y MySQL/MariaDB.

## Estado actual

- Conexión PDO comprobada con la base de datos.
- Configuración privada excluida de Git y plantilla de instalación.
- Registro privado de errores con mensajes genéricos al usuario.
- Inicio de sesiones con cookie HttpOnly y SameSite=Lax.

## Requisitos

- XAMPP con Apache y PHP.
- MySQL.
- Extensión PDO para MySQL habilitada.
- Git para el control de versiones.
- Navegador web.

## Instalación y despliegue local

### 1. Obtener el proyecto

Clonar el repositorio:

git clone https://github.com/Ericka-Cordero95/PROYECTO_1_ISW-613_SIGEL.git

### 2. Configurar el nombre local

Abrir el Bloc de notas como administrador y editar:

C:\Windows\System32\drivers\etc\hosts

Agregar y guardar, sin extensión .txt:

127.0.0.1 sigel.local

### 3. Configurar Apache

Editar el archivo:

C:\xampp\apache\conf\extra\httpd-vhosts.conf

Agregar este bloque y sustituir RUTA_DEL_PROYECTO por la ubicación
real de la carpeta clonada. Utilizar barras / en las rutas.

<VirtualHost *:80>
    ServerName sigel.local
    DocumentRoot "C:/Users/Ericka/OneDrive/Desktop/Progra_Ambiente_Web/PROYECTO_1_ISW-613_SIGEL/public""

    <Directory "C:/Users/Ericka/OneDrive/Desktop/Progra_Ambiente_Web/PROYECTO_1_ISW-613_SIGEL/public">
        Options -Indexes
        AllowOverride None
        Require all granted
        DirectoryIndex index.php
    </Directory>
</VirtualHost>

Comprobar que httpd.conf tenga habilitada esta instrucción:

Include conf/extra/httpd-vhosts.conf

Reiniciar Apache desde el panel de XAMPP.

### 4. Crear la base de datos

Iniciar MySQL desde XAMPP.

Abrir http://localhost/phpmyadmin e importar sql/schema.sql
en una instalación nueva.

El script crea la base de datos sigel y las tablas implementadas.

### Configurar la conexión a MySQL

Copiar config/config.example.php como config/config.local.php.

Editar la copia con el servidor, puerto, nombre de base de datos,
usuario y contraseña de MySQL correspondientes al entorno local.

config.local.php está excluido de Git y debe permanecer fuera de public/.

### 5. Comprobar el acceso

Abrir http://sigel.local.

Efectivamente se mostro el mensaje:

SIGEL: el virtual host funciona.


## Credenciales de prueba

Cuentas destinadas exclusivamente al entorno local de demostración.

| Rol | Correo | Contraseña |
|---|---|---|
| Administrador | admin@sigel.local | AdminSigel2026! |
| Operador | operador@sigel.local | OperadorSigel2026! |

Después de importar sql/schema.sql en una instalación nueva,
importar sql/seed.sql para crear los roles y usuarios de prueba.

## Sesiones y manejo de errores

Las páginas y acciones cargan includes/bootstrap.php antes de generar
contenido. Este archivo configura los errores y carga las funciones
de conexión y el inicio de sesión.

Los detalles de errores se guardan en storage/logs/php-error.log,
fuera del directorio público y excluidos de Git.

La cookie sigel_session contiene el identificador de sesión.
Los datos de la sesión se conservan en el servidor.

La cookie utiliza HttpOnly, SameSite=Lax y Secure cuando el sitio
se sirve mediante HTTPS. El entorno local actual utiliza HTTP.

La autenticación, la regeneración del identificador al autenticar,
la expiración por inactividad y el cierre de sesión están pendientes.

## Estructura del proyecto

- config/: configuración y conexión a la base de datos.
- includes/: funciones reutilizables.
- storage/equipos/: imágenes subidas, fuera del directorio público.
- storage/logs/: registros de errores internos.
- sql/: estructura y datos iniciales de la base de datos.
- public/: único directorio expuesto por el virtual host.
- public/pages/: páginas y formularios.
- public/actions/: procesamiento de operaciones.
- public/assets/: recursos estáticos.

## Configuración privada

Las credenciales locales se almacenarán en config/config.local.php.
Este archivo está excluido del control de versiones mediante .gitignore.

## Funcionalidades pendientes

Autenticación, autorización, CRUD, préstamos, imágenes, cookies,
reportes, bitácora y controles de seguridad asociados.