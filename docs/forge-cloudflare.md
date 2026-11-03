# Publicación privada con Forge y Cloudflare Access

Estado: preparado en código; configuración externa pendiente. No se configuraron DNS, Forge ni Cloudflare desde este repositorio.

## 1. Primer despliegue en Forge

Crear el sitio Laravel con PHP 8.3 o superior y web root `/public`. Conservar el script generado por Forge y sus macros de creación/activación de releases cuando estén habilitadas. No reemplazarlo por un script para despliegues tradicionales.

Configurar el entorno antes de habilitar el acceso:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://SUBDOMINIO_DEFINITIVO
OWNER_EMAIL=TU_CORREO_EXACTO
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
SESSION_LIFETIME=30
SESSION_DOMAIN=null
```

Completar las variables de la base exclusiva de Mis cuentas. Crear una `APP_KEY` solo en la instalación inicial. Conservarla entre despliegues: también cifra las conexiones de los clientes. Respaldarla de forma privada junto con la base. No ejecutar `key:generate` en cada deploy.

Después de instalar las dependencias, ejecutar en el release correcto con el PHP seleccionado en Forge:

```bash
php artisan migrate --force
php artisan optimize
```

Crear tu cuenta por SSH con `php artisan app:create-owner` si aún no existe. El correo debe coincidir con `OWNER_EMAIL`. Si ya tenés cuenta, configurar su correo actual. El comando no modifica usuarios existentes.

En producción, `OWNER_EMAIL` vacío bloquea login y sesiones privadas. Cambiarlo revoca el acceso del correo anterior en su siguiente petición. Luego de editar variables, actualizar la caché con `php artisan config:cache`.

Mientras no esté Cloudflare Access, la página de login puede ser pública: la restricción al propietario protege el ingreso, pero todavía no existe la barrera de Access. Para mantener privado también el login durante la preparación, restringir el sitio temporalmente en Nginx o mantenerlo sin exposición pública. No cargar credenciales de clientes hasta terminar la protección.

## 2. DNS

Agregar el dominio a Cloudflare. Comparar los registros importados con el proveedor actual, incluidos MX, SPF, DKIM, DMARC y verificaciones. Cambiar los nameservers en el registrador después de esa revisión. Mantener los registros de correo sin proxy. No hace falta transferir el registro del dominio.

Usar un subdominio exclusivo para Mis cuentas. La política de Access cubrirá todo ese hostname, no solo `/login`.

## 3. Access y Tunnel

Crear una aplicación Access de tipo Self-hosted para el hostname completo. Permitir únicamente tu correo exacto, mediante un proveedor de identidad con MFA exigido. No usar políticas de Bypass ni permitir todo el dominio del correo. Configurar recuperación de la cuenta y guardar los códigos de recuperación fuera del servidor.

Instalar `cloudflared` como servicio en el servidor de Forge usando las instrucciones de Cloudflare para su sistema operativo. El token del túnel es un secreto: no guardarlo en git ni pegarlo en el chat.

Crear un upstream Nginx para este sitio escuchando únicamente en loopback, por ejemplo `127.0.0.1:8088`, con el root del release activo de Forge. Apuntar el hostname del túnel a ese servicio y configurar el Host HTTP correspondiente. Habilitar **Protect with Access** y el audience de esta aplicación. Configurar Laravel para reconocer HTTPS a través del proxy confiable; verificar URLs, cookies y redirecciones. No confiar indiscriminadamente en proxies públicos.

Deshabilitar todos los virtual hosts públicos que sirvan esta aplicación, incluidos el dominio temporal de Forge, aliases e IPv6. Un túnel por sí solo no elimina esas rutas alternativas. Si hay otros sitios en el servidor, conservar sus listeners y limitar el cambio a Mis cuentas. Validar `nginx -t` antes de recargar.

Si el health check de Forge encuentra Access y falla, ajustar el health check o comprobar salud por una vía privada; no abrir una excepción pública para toda la app. Configurar cualquier caché de Cloudflare para respetar los headers privados de Laravel y no cachear páginas autenticadas.

## 4. Verificación de publicación

- Tu identidad pasa Access y luego el login de Laravel.
- Otra identidad no pasa Access; otra cuenta de Laravel tampoco puede entrar.
- La IP pública con el hostname forzado, IPv6, aliases y dominio temporal no sirven la aplicación.
- El túnel sin token válido de Access no entrega la aplicación.
- Reiniciar el servidor recupera el túnel y mantiene el bloqueo de acceso directo.
- Cookies Secure/HttpOnly, redirecciones HTTPS y cierre de sesión funcionan.
- Tener una sesión Laravel cerrada no necesariamente cierra la sesión de Access: son sesiones distintas.

## 5. Instalaciones y siguientes integraciones

Al crear un cliente, la aplicación lleva a la carga de instalaciones. Permite varias bases MySQL/MariaDB. Cifra todos los datos de conexión con la `APP_KEY`, oculta esos datos en la serialización y no vuelve a mostrar las contraseñas. La cantidad de sistemas para facturación sigue siendo el dato manual del cliente; no se modifica al cargar una instalación.

Esta etapa **registra** las conexiones: no verifica conectividad, no consulta producción ni abre sesiones de administrador. El enlace “Abrir sistema” abre la URL habitual y no autentica al usuario.

Antes de consultar producción, crear usuarios realmente restringidos a SELECT y permitir conexión únicamente desde la red/IP del recolector. Definir TLS con verificación o red privada. Comparar los esquemas reales de cada cliente antes de implementar métricas; guardar agregados con fecha de medición y limitar tiempo/frecuencia de consultas. Nunca ofrecer SQL arbitrario desde el navegador.

El acceso como administrador necesita un endpoint en cada sistema cliente. Usar códigos opacos de un solo uso con vencimiento breve, consumo atómico y validación entre servidores, con credenciales independientes por instalación. Vincular el inicio al navegador que solicita soporte para evitar login CSRF; permitir solo destinos preconfigurados. No guardar tokens completos en logs ni dejarlos en el historial tras el canje. Exigir reautenticación reciente del propietario antes de emitirlos. Registrar operador, administrador emulado, inicio, cierre y acciones; limitar duración y permitir revocación. No modificar contraseñas ni escribir directamente sesiones en las bases de los clientes.

## Fuentes

- [Deployments de Laravel Forge](https://laravel.com/forge/docs/sites/deployments)
- [Agregar un dominio a Cloudflare](https://developers.cloudflare.com/fundamentals/manage-domains/add-site/)
- [Aplicación Self-hosted y protección del origen](https://developers.cloudflare.com/cloudflare-one/access-controls/applications/http-apps/self-hosted-public-app/)
