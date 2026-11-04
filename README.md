# Mis cuentas

Aplicación privada para administrar clientes, abonos mensuales, cobros, movimientos del negocio y finanzas personales. Está hecha con Laravel 12 y Blade, usa MySQL/MariaDB y tiene diseño adaptable para celular. Incluye manifest y service worker para agregarla a la pantalla de inicio como PWA. Los datos de sesión y las páginas privadas no se guardan en caché.

## Incluye

- Inicio de sesión privado, sin registro público.
- Alta, edición y pausa/reactivación de clientes.
- Sistemas por cliente, abono mensual, vencimiento y notas.
- Instalaciones y bases de cada cliente, vinculadas a servidores y clústeres compartidos, con tu participación en cada recurso.
- Gastos fijos en pesos o dólares, con importe por vigencia y cotización mensual de USDC en Arq.
- Registro de cobros totales o parciales, asociados al mes de abono.
- Registro de ingresos y gastos del negocio y personales.
- Resumen mensual, saldo pendiente, finanzas personales y exportación de movimientos a CSV.
- Cinco clientes de ejemplo cargados al crear la cuenta: cuatro por $27.500 y uno por $42.500 con dos sistemas. Cambiá sus nombres y vencimientos al ingresar.

## Cómo se calcula lo pendiente

- Cada cliente activo genera un abono por mes con el monto vigente en ese momento. Si cambiás el abono, el nuevo monto rige desde el mes actual y los meses anteriores conservan el suyo.
- Cada cobro se imputa al mes que elegís en "Mes que paga", aunque la fecha de cobro sea otra. La fecha define en qué mes aparece el ingreso en los movimientos.
- Lo pendiente es la suma, cliente por cliente y mes por mes, de lo que falta cobrar. Incluye la deuda de meses anteriores. Si un cliente paga de más, el excedente no reduce la deuda de otro cliente ni la de otro mes.
- Al pausar un cliente, el mes actual deja de cobrarse si todavía no tenía pagos; la deuda anterior se mantiene. Al reactivarlo, vuelve a cobrarse desde ese mes y los meses en pausa no generan deuda.

## Tests

Los tests usan SQLite en memoria, así que necesitan la extensión `pdo_sqlite`:

```bash
php artisan test
```

Sin `pdo_sqlite`, usá una base MySQL local cuyo nombre termine en `_test` y pasá sus datos por variables de entorno:

```bash
DB_CONNECTION=mysql DB_DATABASE=mis_cuentas_test DB_USERNAME=usuario_local DB_PASSWORD=... php artisan test
```

`phpunit.xml` fija `DB_HOST=127.0.0.1` y deja `OWNER_EMAIL` vacío, y `tests/TestCase.php` se niega a correr contra cualquier base que no sea SQLite en memoria o una `*_test` local. Así, aunque el `.env` apunte a producción, `RefreshDatabase` no puede borrar tablas reales.

## Requisitos

- PHP 8.2 o superior con extensiones habituales de Laravel, incluida `pdo_mysql`.
- Composer 2.
- MySQL 8 o MariaDB compatible.
- HTTPS en producción para instalar la PWA desde el navegador del celular.

No requiere Node.js ni compilar assets: el CSS y JavaScript se sirven desde `public/`.

## Desarrollo local

```bash
cp .env.example .env
composer install
php artisan key:generate
```

Creá una base MySQL y ajustá `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD` en `.env`. Para usarla localmente por HTTP, poné `SESSION_SECURE_COOKIE=false`. Después ejecutá:

```bash
php artisan migrate
php artisan app:create-owner
php artisan serve
```

Abrí la URL local que informa Artisan. El comando `app:create-owner` pide nombre, correo y una contraseña de al menos 12 caracteres; crea la cuenta y los cinco clientes iniciales.

## Puesta en un hosting

Para desplegar en Forge y restringir el sitio a tu identidad, seguir [la guía de Forge y Cloudflare Access](docs/forge-cloudflare.md). Configurar `OWNER_EMAIL` antes del primer acceso: fuera de local/testing, si está vacío el login queda bloqueado. Si existe una cuenta, debe coincidir con su correo.

Las instalaciones de cada cliente se cargan desde “Instalaciones y bases”. Cada instalación (sistema y URL) puede tener varias bases, y sus credenciales se guardan cifradas con `APP_KEY`; conservar esa clave entre despliegues y respaldarla de forma privada. Por ahora se registran conexiones: no se consultan métricas ni se inicia sesión como administrador en los sistemas externos.

En “Infraestructura” se registran una sola vez los servidores y clústeres, aunque los compartan varios clientes. Las instalaciones se vinculan al servidor donde corren y las bases, al clúster que las aloja. Tu participación en cada recurso, es decir, el porcentaje del costo que pagás vos, se carga con un mes de vigencia: un cambio crea un registro nuevo y los meses anteriores conservan el porcentaje que tenían. Sin porcentaje cargado, el recurso figura como **pendiente** y no se asume ningún reparto.

En “Gastos fijos” se cargan las suscripciones y servicios mensuales, en pesos o en dólares. La moneda queda fija desde el alta. Cada importe tiene un mes de vigencia: un cambio de plan se carga como un importe nuevo y los meses anteriores conservan el que tenían. Un importe 0 indica que el gasto se dejó de pagar desde ese mes. Si el gasto es la factura de un servidor o clúster compartido, se carga el total, se vincula al recurso y tu parte sale de tu participación en él. Mientras esa participación no esté cargada, el gasto queda pendiente y no suma.

Los dólares se pasan a pesos con una cotización por mes: el precio de compra de USDC en Arq, tomado de la API pública de comparadolar.ar (`EXCHANGE_RATE_URL` y `EXCHANGE_RATE_PROVIDER` permiten cambiar la fuente). Se guarda la primera vez que abrís el mes en curso y no cambia hasta que la actualizás. Los meses cerrados conservan la suya y solo se corrigen a mano. Si la API no responde o informa un precio desactualizado, el mes queda **sin cotización**: no se usa ningún cero ni precio viejo.

1. Creá una base MySQL/MariaDB y un usuario exclusivo para esta app.
2. Subí el proyecto a una carpeta privada de la cuenta del hosting. Configurá el document root del dominio o subdominio para que apunte a la carpeta `public/` de este proyecto. No expongas `.env`, `storage/` ni el resto del código al público.
3. Creá `.env` a partir de `.env.example`. Configurá `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://tu-dominio`, `SESSION_SECURE_COOKIE=true`, las credenciales de la base y una `APP_KEY` nueva (`php artisan key:generate`). Nunca subas el `.env` de desarrollo.
4. Instalá dependencias de producción desde la carpeta del proyecto:

   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan migrate --force
   php artisan app:create-owner
   php artisan optimize
   ```

5. Permití que PHP escriba en `storage/` y `bootstrap/cache/`. Activá HTTPS antes de iniciar sesión o instalar la app en el teléfono.
6. En Android, abrí el sitio en Chrome y elegí **Instalar app** o **Agregar a pantalla de inicio**. En iPhone, abrilo en Safari, tocá **Compartir** y luego **Agregar a pantalla de inicio**.

Si el hosting no permite SSH o Composer, instalá las dependencias en una máquina con Composer y subí también `vendor/`. Conservá `composer.lock` para repetir las mismas versiones. Antes de usarla con datos reales, verificá que el proveedor permita configurar el document root hacia `public/` y hacer copias de seguridad de MySQL.

## Copias de seguridad

La base MySQL es el único registro de tus cobros. El CSV de movimientos sirve para revisar números, pero no es un backup: no incluye clientes ni abonos.

1. Guardá las credenciales de la base en `~/.my.cnf` (fuera de `public/`) con permisos `600`, para no escribir la contraseña en el cron:

   ```ini
   [client]
   user=USUARIO_DE_LA_BASE
   password=CONTRASEÑA
   ```

2. Creá el script `~/bin/backup-mis-cuentas.sh` (fuera de `public/`) y dale permisos con `chmod 700`. Guarda un archivo comprimido por día y borra los de más de 30 días. Si `mysqldump` falla, `pipefail` corta el script y el archivo a medias no reemplaza a ningún backup:

   ```bash
   #!/usr/bin/env bash
   set -euo pipefail
   mkdir -p ~/backups
   destino=~/backups/mis-cuentas-$(date +%F).sql.gz
   mysqldump --single-transaction --no-tablespaces NOMBRE_DE_LA_BASE | gzip > "$destino.tmp"
   mv "$destino.tmp" "$destino"
   find ~/backups -name 'mis-cuentas-*.sql.gz' -mtime +30 -delete
   ```

   Programá un cron diario desde el panel del hosting que ejecute `~/bin/backup-mis-cuentas.sh` y te avise por correo si sale con error.

3. Una vez por semana, descargá el último archivo a tu computadora o a un almacenamiento externo. Una copia que vive solo en el mismo hosting se pierde junto con él.
4. Antes de cargar datos reales, y después cada tanto, probá restaurar una copia en una base vacía y abrí la app contra ella:

   ```bash
   gunzip < ~/backups/mis-cuentas-AAAA-MM-DD.sql.gz | mysql NOMBRE_DE_UNA_BASE_DE_PRUEBA
   ```

Si el hosting no ofrece cron ni `mysqldump`, usá las copias automáticas del panel y descargá una exportación de phpMyAdmin cada semana.
