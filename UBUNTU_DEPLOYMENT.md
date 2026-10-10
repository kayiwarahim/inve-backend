# Ubuntu 22.04 deployment guide

This guide deploys this repository to one Ubuntu 22.04 server/VM with Nginx, PHP 8.4-FPM, local MySQL 8, and two systemd queue workers. Commands run in **Bash on Ubuntu**, not in Windows PowerShell. The production environment below is based on this project's `.env.example` and its actual configuration files.

## 1. Choose your deployment values

| Example | Replace with |
| --- | --- |
| `deploy` | Your existing non-root Linux account with sudo access |
| `/var/www/inve-backend` | Application directory; use the same path throughout |
| `api.example.com` | Backend domain pointing to the VM |
| `app.example.com` | HTTPS frontend domain |
| `.example.com` | Shared parent domain for the frontend and backend |
| `REPOSITORY_URL` | Your Git repository URL |
| `RELEASE_TAG` | Tested release tag or commit |
| `inve_backend` / `inve_app` | Local database name / application database user |
| `REPLACE_*` | Real passwords and SMTP provider details |

This cookie configuration assumes both apps use trusted subdomains of the same parent domain. If your frontend uses an unrelated domain, do not copy these cookie settings unchanged: browser cross-site cookie restrictions require a different deployment/authentication design.

Point DNS to the server before requesting TLS. Allow inbound SSH, HTTP (80), and HTTPS (443) through the VM/provider firewall. Do not expose MySQL (3306), PHP-FPM, or a Vite development server.

## 2. Install system packages and PHP

The repository's `composer.json` requires **PHP ^8.4**, even though Laravel itself supports earlier versions. Ubuntu 22.04's default PHP is insufficient. This example uses the third-party [Ondrej PHP PPA](https://launchpad.net/~ondrej/+archive/ubuntu/php); adding it makes that maintainer a trusted package source.

```bash
sudo apt update
sudo apt upgrade -y
sudo apt install -y software-properties-common ca-certificates curl git unzip nginx mysql-server mysql-client ufw snapd
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.4-cli php8.4-fpm php8.4-common php8.4-mysql php8.4-sqlite3 php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-gd php8.4-bcmath php8.4-intl php8.4-opcache
sudo update-alternatives --set php /usr/bin/php8.4
sudo systemctl enable --now mysql php8.4-fpm nginx
sudo timedatectl set-ntp true
php8.4 -v
php8.4 -m
mysql --version
mysqldump --version
```

Both `pdo_mysql` and `pdo_sqlite` are required. MySQL stores application data; SQLite stores the independent backup queue. ZIP is required for backup archives; GD supports image/barcode work. Keep system time synchronized for MFA codes.

Configure the host firewall after confirming your SSH port. If SSH uses a custom port, allow it instead of relying on the `OpenSSH` profile. Keep your current SSH session open while verifying another connection.

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
sudo ufw status
```

## 3. Install Composer

Verify the installer against its current checksum, following [Composer's installation guidance](https://getcomposer.org/doc/faqs/how-to-install-composer-programmatically.md).

```bash
cd /tmp
curl -fsS https://getcomposer.org/installer -o composer-setup.php
expected_checksum="$(curl -fsS https://composer.github.io/installer.sig)"
actual_checksum="$(php8.4 -r "echo hash_file('sha384', 'composer-setup.php');")"
if [ "$expected_checksum" != "$actual_checksum" ]; then
    echo 'Composer installer checksum failed' >&2
    exit 1
fi
sudo php8.4 composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
composer --version
```

Run application Composer commands as `deploy`, not root.

## 4. Configure the local database

Inspect `/etc/mysql/mysql.conf.d/mysqld.cnf` and ensure the `[mysqld]` section binds MySQL to `127.0.0.1`. Preserve the other existing settings. Then restart MySQL:

```bash
sudo systemctl restart mysql
sudo mysql
```

In the MySQL prompt, replace the password before executing:

```sql
CREATE DATABASE inve_backend CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'inve_app'@'127.0.0.1' IDENTIFIED BY 'REPLACE_STRONG_DB_PASSWORD';
GRANT ALL PRIVILEGES ON inve_backend.* TO 'inve_app'@'127.0.0.1';
GRANT PROCESS, SELECT ON *.* TO 'inve_app'@'127.0.0.1';
EXIT;
```

The current backup service uses the same database credentials as the application. Its `mysqldump` flags include routines and tablespaces: MySQL documents global `SELECT` for routines and `PROCESS` for tablespaces. Consequently, the second grant gives this account read access beyond the application database. This guide assumes a dedicated single-application VM; on a shared database server, adapt the backup implementation/credentials before granting that access. See [MySQL mysqldump privileges](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html).

Do not use the MySQL root account in `.env`. This setup assumes default GTID settings; enabling GTIDs or introducing stored routines/definers may require additional backup/restore privilege planning. Do not grant `SUPER` just to bypass an error.

Verify TCP login using the exact host configured in `.env`:

```bash
mysql -h 127.0.0.1 -u inve_app -p inve_backend -e 'SELECT DATABASE(), VERSION();'
```

## 5. Check out the application

Run these as the `deploy` account. If that account has another name, replace it in ownership commands.

```bash
sudo usermod -aG www-data deploy
sudo mkdir -p /var/www/inve-backend
sudo chown deploy:www-data /var/www/inve-backend
sudo chmod 2750 /var/www/inve-backend
git clone REPOSITORY_URL /var/www/inve-backend
cd /var/www/inve-backend
git checkout RELEASE_TAG
sudo chgrp -R www-data /var/www/inve-backend
sudo chmod -R g+rX,o-rwx /var/www/inve-backend
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 2770 {} +
sudo find storage bootstrap/cache -type f -exec chmod 0660 {} +
```

Reconnect your SSH session after the group change so `deploy` receives its new `www-data` membership. Then:

```bash
cd /var/www/inve-backend
cp .env.example .env
chmod 0640 .env
nano .env
```

Application code belongs to the deployment account. Only `storage` and `bootstrap/cache` need runtime writes. Do not use `chmod 777` or make the entire repository owned by the web server.

## 6. Suggested production `.env`

Replace every example domain and `REPLACE_*` value. Quote values containing spaces or special characters and follow Laravel dotenv syntax; ensure the database password matches the MySQL user password.

For a **new empty installation**, leave `APP_KEY` empty until step 7 generates it. For an existing installation/database, put the **original APP_KEY** here instead. Keep that key securely backed up: encrypted MFA secrets and backup signatures depend on it.

```dotenv
APP_NAME="Inve Backend"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://api.example.com

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
APP_TIMEZONE=Africa/Kampala
APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=inve_backend
DB_USERNAME=inve_app
DB_PASSWORD="REPLACE_STRONG_DB_PASSWORD"

DATABASE_BACKUP_CONNECTION=mysql
DATABASE_BACKUP_QUEUE_CONNECTION=database-backups
DATABASE_BACKUP_CONTROL_DATABASE=/var/www/inve-backend/storage/app/database-backups/queue.sqlite
DATABASE_BACKUP_DUMP_BINARY=/usr/bin/mysqldump
DATABASE_BACKUP_RESTORE_BINARY=/usr/bin/mysql
DATABASE_BACKUP_MAX_UPLOAD_KB=262144
DATABASE_BACKUP_MAX_SQL_BYTES=1073741824

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=.example.com
SESSION_SECURE_COOKIE=true
SESSION_EXPIRE_ON_CLOSE=true
SESSION_SAME_SITE=lax
SANCTUM_TOKEN_EXPIRATION=120
SANCTUM_STATEFUL_DOMAINS=app.example.com,api.example.com
CORS_ALLOWED_ORIGINS=https://app.example.com

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database
CACHE_PREFIX=inve_backend_production_

MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=REPLACE_SMTP_HOST
MAIL_PORT=587
MAIL_USERNAME="REPLACE_SMTP_USERNAME"
MAIL_PASSWORD="REPLACE_SMTP_PASSWORD"
MAIL_FROM_ADDRESS="no-reply@example.com"
MAIL_FROM_NAME="${APP_NAME}"
MAIL_EHLO_DOMAIN=api.example.com

VITE_APP_NAME="${APP_NAME}"
MFA_ISSUER="${APP_NAME}"
MFA_REQUIRED_ROLES=admin,manager
```

### Differences from `.env.example`

| Setting | Deployment reason |
| --- | --- |
| `APP_ENV=production`, `APP_DEBUG=false` | Production behavior without exposing debug details |
| HTTPS URLs, production CORS/stateful domains | Replace all localhost development origins |
| Local DB with a named account | Avoid application access as root |
| Linux backup binary paths | Replace XAMPP assumptions |
| Absolute backup-control SQLite path | All PHP processes use the same queue database |
| `QUEUE_CONNECTION=database` | Regular jobs run through the main queue worker |
| Encrypted sessions and secure cookies | Protect session data and require HTTPS cookies |
| Real SMTP | `MAIL_MAILER=log` logs messages instead of delivering authentication emails |
| Daily logs at `info` | Reduce debug logging and rotate Laravel log files |

`SANCTUM_STATEFUL_DOMAINS` contains hostnames, without schemes; `CORS_ALLOWED_ORIGINS` contains full origins, including `https://`. Add comma-separated origins only for trusted frontends. A cookie domain of `.example.com` shares cookies with subdomains, so all those subdomains must be trusted.

In the current code, `SESSION_HTTP_ONLY` and `CORS_SUPPORTS_CREDENTIALS` from `.env.example` are not read: both behaviors are enabled directly in configuration. `MAIL_ENCRYPTION` is also not read; SMTP uses `MAIL_SCHEME`. For a provider requiring implicit TLS on port 465, use `MAIL_SCHEME=smtps` and `MAIL_PORT=465`. For port 587, use your provider's STARTTLS configuration and verify delivery.

Redis, Memcached, and AWS values are omitted because this deployment uses database queues/cache and local file storage. Do not put secrets in `VITE_*` variables; frontend builds expose them. Direct Nginx TLS termination is assumed. If TLS later terminates at a proxy/load balancer, configure Laravel trusted proxies as well.

## 7. Install dependencies and initialize Laravel

From the application directory:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
```

**Only for a fresh installation with an empty database and no existing key:**

```bash
php8.4 artisan key:generate --force --no-interaction
```

Do not regenerate the key when migrating an existing installation or restoring its database.

Run migrations as the same user that runs PHP-FPM and both workers. The backup migration creates a SQLite file with mode `0600`, so running it as root or `deploy` can prevent backups from working.

```bash
sudo -u www-data php8.4 artisan config:clear --no-interaction
sudo -u www-data php8.4 artisan migrate --force --no-interaction
sudo -u www-data php8.4 artisan db:seed --class=RolePermissionSeeder --force --no-interaction
php8.4 artisan storage:link --no-interaction
sudo -u www-data php8.4 artisan optimize --no-interaction
sudo chmod 0700 storage/app/database-backups
sudo chmod 0600 storage/app/database-backups/queue.sqlite
```

Do not run the unrestricted `db:seed` command on production. The default seeder includes sample business records and a demo admin with a fixed password. Review individual business seeders before using any of them.

If importing an existing production database, treat that as a migration project: preserve its key, take a snapshot, import using a compatible database engine, then apply pending migrations. Do not run `migrate:fresh` on a database containing real data.

### Create the first administrator on a fresh database

Use interactive Tinker so the password is entered through a hidden prompt rather than stored as a literal shell command:

```bash
sudo -u www-data php8.4 artisan tinker
```

Enter one line at a time:

```php
$name = \Laravel\Prompts\text('Admin name');
$email = \Laravel\Prompts\text('Admin email');
$password = \Laravel\Prompts\password('Admin password');
$admin = \App\Models\User::query()->create(['name' => $name, 'email' => $email, 'password' => \Illuminate\Support\Facades\Hash::make($password)]);
$admin->assignRole('admin');
unset($password);
exit
```

Use a unique email and a strong password. Existing installations should use their existing administrator accounts. Admin and manager roles require MFA enrollment; complete enrollment through the frontend before using restore endpoints.

### Build backend assets

If deploying Blade pages that load Vite assets, build this repository's assets from the same release on your workstation/CI and upload the complete `public/build/` directory to the server. It is normally ignored by Git. A separate frontend build does not replace the backend's asset build.

Use a supported Node version, such as Node 22.12 or later in the 22.x line; see [Vite requirements](https://vite.dev/guide/).

```bash
npm ci
npm run build
```

After transferring assets, ensure they are readable by `www-data`. On the server remove a stale development-server marker if present:

```bash
cd /var/www/inve-backend
rm -f public/hot
```

Node is unnecessary on the production VM when build artifacts are supplied by CI. Do not run `npm run dev` or `php artisan serve` as production services.

## 8. Configure PHP upload limits

Create `/etc/php/8.4/fpm/conf.d/99-inve.ini`:

```ini
upload_max_filesize = 256M
post_max_size = 270M
memory_limit = 512M
max_execution_time = 120
expose_php = Off
opcache.enable = 1
opcache.validate_timestamps = 1
```

The application's ZIP upload limit is 256 MiB. The POST and Nginx limits need room for multipart overhead. Backup/restore processing happens in the queue, so HTTP request execution time does not need to cover a complete restore. The SQL extraction limit is independently 1 GiB in the proposed `.env`.

Ensure PHP's native process execution is available: the backup service needs `proc_open` to run MySQL tools. Reload FPM after editing its configuration:

```bash
sudo systemctl reload php8.4-fpm
```

## 9. Configure Nginx

Create `/etc/nginx/sites-available/inve-backend`, replacing the domain:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name api.example.com;
    root /var/www/inve-backend/public;
    index index.php;
    charset utf-8;
    client_max_body_size 270m;

    add_header X-Content-Type-Options nosniff;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_param DOCUMENT_ROOT $document_root;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ \.php$ {
        return 404;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Only `public/` is exposed, and only the front controller is executed as PHP. Never point the document root at the repository root, which contains `.env` and private backups. See [Laravel deployment guidance](https://laravel.com/docs/13.x/deployment).

```bash
sudo ln -s /etc/nginx/sites-available/inve-backend /etc/nginx/sites-enabled/inve-backend
sudo nginx -t
sudo systemctl reload nginx
```

Disable conflicting sites if an existing server block uses the same domain. The domain must resolve to this VM for the next step. Test configuration before each Nginx reload.

## 10. Enable HTTPS

Use [Certbot's Nginx instructions](https://certbot.eff.org/instructions?ws=nginx&os=snap). With a fresh snap installation:

```bash
sudo snap install --classic certbot
sudo /snap/bin/certbot --nginx -d api.example.com
sudo /snap/bin/certbot renew --dry-run
sudo nginx -t
```

Choose the HTTP-to-HTTPS redirect when prompted. Verify that automatic renewal is installed and that ports 80/443 remain reachable. Do not expose the production login before HTTPS is working.

## 11. Run both queue workers with systemd

Two separate workers are required. Regular jobs use MySQL; backup/restore jobs use the SQLite control database so their queue survives replacing MySQL.

Create `/etc/systemd/system/inve-queue.service`:

```ini
[Unit]
Description=Inve application queue
After=network.target mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/inve-backend
ExecStart=/usr/bin/php8.4 /var/www/inve-backend/artisan queue:work database --queue=default --sleep=3 --tries=3 --timeout=60
Restart=always
RestartSec=5
KillSignal=SIGTERM
TimeoutStopSec=120
UMask=0007

[Install]
WantedBy=multi-user.target
```

Create `/etc/systemd/system/inve-backups.service`:

```ini
[Unit]
Description=Inve database backup and restore queue
After=network.target mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/inve-backend
ExecStart=/usr/bin/php8.4 /var/www/inve-backend/artisan queue:work database-backups --queue=database-backups --sleep=3 --tries=1 --timeout=3600
Restart=always
RestartSec=5
KillSignal=SIGTERM
TimeoutStopSec=3700
UMask=0007

[Install]
WantedBy=multi-user.target
```

The worker timeouts remain below their configured retry intervals: 60 < 90 seconds for normal jobs and 3600 < 3660 seconds for backup jobs. The longer stop timeout lets an active backup/restore finish during a planned service stop. Do not interrupt an active restore to deploy a release.

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now inve-queue inve-backups
sudo systemctl status inve-queue inve-backups --no-pager
sudo journalctl -u inve-queue -u inve-backups -n 100 --no-pager
```

Do not configure systemd `EnvironmentFile=.env`: Laravel reads its dotenv file itself, and dotenv interpolation is not systemd environment-file syntax. After changing `.env`, rebuild config and restart both workers:

```bash
cd /var/www/inve-backend
sudo -u www-data php8.4 artisan config:cache --no-interaction
sudo systemctl restart inve-queue inve-backups
sudo systemctl reload php8.4-fpm
```

Schedule these restarts when no restore is active. There are no recurring application tasks requiring a scheduler in the current console routes. If you add scheduled jobs later, configure `schedule:run` and include those writers in the restore pause procedure.

## 12. Verify the deployment

```bash
curl -I https://api.example.com/up
sudo -u www-data test -r /var/www/inve-backend/.env
sudo -u www-data test -w /var/www/inve-backend/storage
sudo -u www-data test -w /var/www/inve-backend/storage/app/database-backups/queue.sqlite
sudo -u www-data /usr/bin/mysqldump --version
sudo -u www-data /usr/bin/mysql --version
df -h /var/www/inve-backend
```

`/up` verifies Laravel boots; it does not prove database queries, email, or queued backups work. Perform these functional checks through the frontend:

1. Fetch `/sanctum/csrf-cookie`, then log in using credentialed requests and the XSRF token. The normal login uses session cookies; it is not a bearer-token-only flow.
2. Complete required MFA enrollment/challenges and verify real email delivery.
3. Verify an authenticated database-backed screen and a representative report/barcode operation.
4. Request a backup and poll its operation until it completes. Verify both download and upload of that archive using [the backup API contract](DATABASE_BACKUP_API.md).
5. Test restore on a disposable staging deployment with the same release and original signing key before relying on production recovery.

Allow disk capacity for the live database, uploaded/downloaded ZIPs, extracted SQL, and a safety backup during restore. A 256 MiB ZIP can expand considerably. Monitor available disk space and both worker services.

## 13. Backup and restore operations

Backups, metadata, queue control data, and the restore marker live in `storage/app/database-backups/`. Nginx must never expose that directory. Archives are signed, **not encrypted**: protect downloads and off-server storage.

The database and backups being on the same VM does not protect against VM/disk loss. Keep off-VM copies of completed archives and metadata, the original `APP_KEY` in a secret store, and uploaded files from `storage/app/public`. Database backups do not include uploaded files, configuration, or source code. Retain the matching application release and migration files with each recovery point.

For a consistent filesystem copy of the SQLite control database, pause incoming work and stop the backup worker first; do not blindly copy a live SQLite database while it is being written. Keep backup directory contents private and restore ownership to `www-data` when moving them between servers.

### Planned API restore

1. Confirm you have a current off-VM backup and a valid archive for the current database driver and migration fingerprint.
2. Stop normal writers: `sudo systemctl stop inve-queue`. Also pause any scheduler, import processes, integrations, and external database writers. **Leave `inve-backups` running.** Do not run `artisan down` before submitting the authenticated restore request.
3. Submit the restore endpoint with `password`, `confirm=true`, and `writers_paused=true`, as documented in [DATABASE_BACKUP_API.md](DATABASE_BACKUP_API.md). The administrator must have MFA enabled.
4. The restore process blocks HTTP requests while replacing the database, including status polling and `/up`. Use server logs and operation metadata during this interval.
5. Once restore succeeds, restart normal writers: `sudo systemctl start inve-queue`. All users must log in again.

### Recovery after a failed restore

A failure after destructive work leaves the filesystem restore marker in place to keep the app blocked. Do not delete that marker just to reopen traffic. Use the CLI recovery path after investigating the underlying error and identifying a valid backup UUID (for example, the safety backup):

```bash
cd /var/www/inve-backend
sudo systemctl stop inve-queue inve-backups
sudo -u www-data php8.4 artisan database-backups:restore BACKUP_UUID --force --no-interaction
```

Stop all other writers too. The command replaces the database and validates the backup; `--force` confirms you have paused those writers. Restart services only after the command succeeds:

```bash
sudo systemctl start inve-queue inve-backups
```

For complete VM loss, first rebuild the environment, restore the original key and private backup directory/control database from a consistent copy, and use the matching application release. A copied ZIP by itself is not automatically a registered server backup. Use the documented upload flow when the application is healthy; CLI recovery identifies registered archives by UUID. Existing migration history will not automatically rerun the backup-control migration if its SQLite file was lost, so restore that control file or explicitly reconstruct it using the migration's schema before starting the backup worker.

## 14. Deploy subsequent releases

Before an update, finish pending restores, take and download an application backup, and save the current release identifier. Test the target release first. An old backup may require the old release because restores validate the migration fingerprint.

For a planned maintenance deployment:

```bash
cd /var/www/inve-backend
sudo -u www-data php8.4 artisan down --no-interaction
sudo systemctl stop inve-queue inve-backups
git fetch --tags
git checkout RELEASE_TAG
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
sudo -u www-data php8.4 artisan optimize:clear --no-interaction
sudo -u www-data php8.4 artisan migrate --force --no-interaction
sudo -u www-data php8.4 artisan optimize --no-interaction
sudo systemctl reload php8.4-fpm
sudo -u www-data php8.4 artisan up --no-interaction
sudo systemctl start inve-queue inve-backups
```

Deploy the target release's `public/build` assets before bringing the app up, and keep new code/assets group-readable by `www-data`. Preserve `.env`, `storage`, and the SQLite queue file. Do not run `composer update`, regenerate the application key, or reset the database as part of a deployment. Do not reseed permissions routinely without reviewing role changes: the role seeder synchronizes permissions.

If deployment fails, keep maintenance enabled while investigating. Checking out old code alone may not reverse schema changes; use the release's tested rollback/recovery plan.

## 15. Troubleshooting

| Symptom | Check |
| --- | --- |
| Nginx 502 | `systemctl status php8.4-fpm`; socket path; `/var/log/nginx/error.log` |
| Laravel 500 | `storage/logs/laravel-*.log`; dependency extensions; `.env`; storage/cache permissions |
| Login 419 or cookies missing | HTTPS on both apps, shared cookie domain, frontend credentials/XSRF handling, CORS/stateful domains |
| MFA/email never arrives | Real SMTP credentials/provider access, sender verification, mail logs |
| Backup operation stays queued | `inve-backups` service, `pdo_sqlite`, SQLite file ownership |
| Backup fails with access denied | MySQL grants required by current dump flags, matching client/server versions |
| Upload fails with 413 | Nginx `client_max_body_size` |
| Upload is missing or too large | FPM `upload_max_filesize`/`post_max_size`, application ZIP limit, FPM reload |
| Backup signature mismatch | Original `APP_KEY`, unmodified archive |
| Restore rejects migration fingerprint | Deploy matching release/migrations; do not modify the archive to bypass validation |
| App remains blocked after restore error | Restore marker, operation metadata and logs; follow CLI recovery |
| Vite manifest missing | Backend `public/build/manifest.json` from the matching release, no stale `public/hot` |
| `.env` changes have no effect | Rebuild config cache and restart workers/FPM |

Useful logs:

```bash
sudo journalctl -u inve-queue -u inve-backups -n 100 --no-pager
sudo tail -n 100 /var/log/nginx/error.log
sudo -u www-data ls -lt /var/www/inve-backend/storage/logs
```

Do not enable production debug output to diagnose an issue. Inspect server logs privately and redact secrets before sharing them.
