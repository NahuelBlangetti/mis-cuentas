# Mis cuentas

Aplicación privada para administrar clientes, abonos mensuales, cobros, movimientos del negocio y finanzas personales. Está hecha con Laravel 12 y Blade, usa MySQL/MariaDB y tiene diseño adaptable para celular. Incluye manifest y service worker para agregarla a la pantalla de inicio como PWA. Los datos de sesión y las páginas privadas no se guardan en caché.

## Incluye

- Inicio de sesión privado, sin registro público.
- Alta, edición y pausa/reactivación de clientes.
- Sistemas por cliente, abono mensual, vencimiento y notas.
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
