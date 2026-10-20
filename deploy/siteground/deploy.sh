#!/usr/bin/env bash
# Installs or updates the club on SiteGround shared hosting, over SSH, as the site user.
#
#   ~/www/<site>/app          this repository (code, .env, storage), not reachable from the web
#   ~/www/<site>/public_html  web root: a copy of public/ whose index.php points at ../app
#
# Update:  cd ~/www/<site>/app && git pull && bash deploy/siteground/deploy.sh
# First import from the old site:  IMPORT=1 bash deploy/siteground/deploy.sh
set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
SITE_DIR="$(dirname "$APP_DIR")"
WEB_DIR="${WEB_DIR:-$SITE_DIR/public_html}"
PHP="${PHP:-php}"
JOOMLA_FILES="${JOOMLA_FILES:-$HOME/www/anahnu.club/public_html}"
cd "$APP_DIR"

if [ -f "$WEB_DIR/configuration.php" ]; then
    echo "$WEB_DIR holds a Joomla site; refusing to touch it. Set WEB_DIR to the new site's public_html." >&2
    exit 1
fi

echo "==> PHP"
"$PHP" -r 'exit(version_compare(PHP_VERSION, "8.2", "<") ? 1 : 0);' || {
    echo "PHP $("$PHP" -r 'echo PHP_VERSION;') is too old: pick 8.3 in Site Tools > Devs > PHP Manager, or run with PHP=php83." >&2
    exit 1
}
missing=""
for ext in pdo_pgsql mbstring gd intl fileinfo curl; do
    "$PHP" -m | grep -qix "$ext" || missing="$missing $ext"
done
[ -z "$missing" ] || { echo "PHP is missing:$missing" >&2; exit 1; }

if [ ! -f .env ]; then
    cp .env.example .env
    host="$(basename "$SITE_DIR")"
    sed -i "s#^APP_ENV=.*#APP_ENV=production#; s#^APP_DEBUG=.*#APP_DEBUG=false#; s#^APP_URL=.*#APP_URL=https://$host#" .env
    echo
    echo "Created .env. Fill in DB_*, MAIL_PASSWORD and JOOMLA_DB_* (nano .env), then run this script again."
    exit 0
fi
grep -q '^DB_PASSWORD=.\+' .env || { echo "DB_PASSWORD in .env is empty: fill in the database settings first." >&2; exit 1; }

echo "==> Composer"
if command -v composer >/dev/null; then
    COMPOSER=(composer)
else
    [ -f composer.phar ] || curl -fsSL https://getcomposer.org/installer | "$PHP" -- --quiet
    COMPOSER=("$PHP" composer.phar)
fi
"${COMPOSER[@]}" install --no-interaction --prefer-dist --optimize-autoloader --no-dev
grep -q '^APP_KEY=base64' .env || "$PHP" artisan key:generate --force

echo "==> Frontend"
if command -v npm >/dev/null; then
    npm ci --no-audit --no-fund && npm run build
else
    # Built on GitHub (.github/workflows/build-assets.yml); wait for the build of this commit.
    want="$(git rev-parse HEAD)" got=""
    for _ in $(seq 1 30); do
        git fetch -q origin build && got="$(git show FETCH_HEAD:build/.commit 2>/dev/null || true)"
        [ "$got" = "$want" ] && break
        echo "    waiting for GitHub to build $want ..."
        sleep 20
    done
    [ "$got" = "$want" ] || echo "    (no build of this commit yet, using the build of ${got:-nothing})"
    rm -rf public/build
    git archive FETCH_HEAD build | tar -x -C public
fi

echo "==> Database"
"$PHP" artisan migrate --force --seed
"$PHP" artisan storage:link 2>/dev/null || true

if [ "${IMPORT:-}" = 1 ]; then
    echo "==> Import from the old site"
    "$PHP" artisan import:joomla ${JOOMLA_FILES:+--files="$JOOMLA_FILES"}
    "$PHP" artisan posts:link-previews
fi

"$PHP" artisan optimize

echo "==> Web root $WEB_DIR"
mkdir -p "$WEB_DIR"
rm -rf "$WEB_DIR/build" "$WEB_DIR/index.html" "$WEB_DIR/default.html"
(cd public && find . -mindepth 1 -maxdepth 1 ! -name storage ! -name index.php ! -name hot -exec cp -a {} "$WEB_DIR/" \;)
sed "s#__DIR__.'/\.\./#'$APP_DIR/#g" public/index.php > "$WEB_DIR/index.php"
ln -sfn "$APP_DIR/storage/app/public" "$WEB_DIR/storage"

echo
echo "Done: $(grep '^APP_URL=' .env | cut -d= -f2)"
echo "Cron (Site Tools > Devs > Cron Jobs, every minute):"
echo "    cd $APP_DIR && $PHP artisan schedule:run >> /dev/null 2>&1"
