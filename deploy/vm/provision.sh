#!/usr/bin/env bash
# Sets up a test server for AnahnuClub on a fresh Ubuntu 24.04 or 26.04 machine:
# nginx + PHP (the release's default, 8.2+) + PostgreSQL (the app) + MariaDB (only to read the old Joomla dump) + Node 22.
#
#   sudo bash deploy/vm/provision.sh                       # app from this checkout
#   sudo JOOMLA_SQL=/home/ubuntu/old.sql JOOMLA_FILES=/home/ubuntu/public_html bash deploy/vm/provision.sh
#
# Safe to run again after pulling new code.
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
DB_PASSWORD="${DB_PASSWORD:-anahnu}"
JOOMLA_SQL="${JOOMLA_SQL:-}"
JOOMLA_FILES="${JOOMLA_FILES:-}"
export DEBIAN_FRONTEND=noninteractive

start() { if command -v systemctl >/dev/null && [ -d /run/systemd/system ]; then systemctl enable --now "$1"; else service "$1" start; fi; }
reload() { if command -v systemctl >/dev/null && [ -d /run/systemd/system ]; then systemctl reload "$1"; else service "$1" reload; fi; }

echo "==> Packages"
apt-get update -qq
apt-get install -y -qq ca-certificates curl git unzip nginx postgresql mariadb-server cron \
    php-fpm php-cli php-pgsql php-mysql php-mbstring php-xml php-curl \
    php-zip php-intl php-gd php-bcmath >/dev/null
PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"

if ! command -v node >/dev/null || [ "$(node -v | cut -c2-3)" -lt 20 ]; then
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash - >/dev/null
    apt-get install -y -qq nodejs >/dev/null
fi
if ! command -v composer >/dev/null; then
    curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer >/dev/null
fi

# Members upload photos and videos (up to 200 MB) straight from their phones.
printf 'upload_max_filesize = 210M\npost_max_size = 220M\nmemory_limit = 256M\nmax_file_uploads = 25\n' > "/etc/php/$PHP_VERSION/fpm/conf.d/99-anahnuclub.ini"

start postgresql
start mariadb
start "php$PHP_VERSION-fpm"
reload "php$PHP_VERSION-fpm"
start cron

echo "==> PostgreSQL database"
sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='anahnu'" | grep -q 1 \
    || sudo -u postgres psql -c "CREATE USER anahnu WITH PASSWORD '$DB_PASSWORD'"
sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='anahnu'" | grep -q 1 \
    || sudo -u postgres psql -c "CREATE DATABASE anahnu OWNER anahnu"

if [ -n "$JOOMLA_SQL" ]; then
    echo "==> Loading old Joomla dump into MariaDB"
    mysql -e "CREATE DATABASE IF NOT EXISTS joomla CHARACTER SET utf8mb4;
              CREATE USER IF NOT EXISTS 'joomla'@'localhost' IDENTIFIED BY 'joomla';
              GRANT ALL ON joomla.* TO 'joomla'@'localhost';"
    if [ "$(mysql -N joomla -e "SHOW TABLES LIKE '%users'" | wc -l)" -eq 0 ]; then
        sed 's/utf8mb4_0900_ai_ci/utf8mb4_unicode_ci/g' "$JOOMLA_SQL" | mysql joomla
    fi
fi

echo "==> Application"
cd "$APP_DIR"
if [ ! -f .env ]; then
    cp .env.example .env
    sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=$DB_PASSWORD/; s/^JOOMLA_DB_PASSWORD=.*/JOOMLA_DB_PASSWORD=joomla/; s#^APP_URL=.*#APP_URL=http://$(hostname -I | awk '{print $1}')#" .env
fi
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-interaction --prefer-dist --optimize-autoloader
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force
npm ci --no-audit --no-fund
npm run build
php artisan migrate --force --seed
php artisan storage:link 2>/dev/null || true
php artisan posts:link-previews

if [ -n "$JOOMLA_SQL" ]; then
    php artisan import:joomla ${JOOMLA_FILES:+--files="$JOOMLA_FILES"}
fi

php artisan optimize
chown -R www-data:www-data storage bootstrap/cache

echo "==> nginx"
cat > /etc/nginx/sites-available/anahnuclub <<NGINX
server {
    listen 80 default_server;
    server_name _;
    root $APP_DIR/public;
    index index.php;
    client_max_body_size 220m;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php$PHP_VERSION-fpm.sock;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
NGINX
ln -sf /etc/nginx/sites-available/anahnuclub /etc/nginx/sites-enabled/anahnuclub
rm -f /etc/nginx/sites-enabled/default
nginx -t
if pgrep -x nginx >/dev/null; then reload nginx; else start nginx; fi

echo "==> Scheduler (queue for translations)"
echo "* * * * * www-data cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/anahnuclub

# nginx runs as www-data and has to reach the app folder.
dir="$APP_DIR"; while [ "$dir" != "/" ]; do chmod o+x "$dir"; dir="$(dirname "$dir")"; done

echo
echo "Ready: http://$(hostname -I | awk '{print $1}')/"
