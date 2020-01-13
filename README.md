# System-monitor

## Install
### As a standalone tool
1. Clone the repository
2. Run `composer install --no-dev --classmap-authoritative`
3. Copy `.env` as `.env.local` and fill the parameters
4. Run `composer dump-env prod`
5. Run `php bin/console cache:warmup`
6. Create a `build` directory at the root of your cloned repository (at the same level as `public` and `src`)
7. Run `php create-phar.php`

Enjoy your `.phar`.  You can just run it with `php system-monitor.php services:check`

## Usage

### Monitor services
Just call `php bin/console services:check`.

Two parameters are available:
* `--no-notification` : does not send any email nor SMS
* `--no-restart` : does not try to restart services

### Backup database
Call `php bin/console database:backup` (or `./system-monitor.phar database:backup` if you
are using the PHAR).
