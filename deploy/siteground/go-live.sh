#!/usr/bin/env bash
# Moves the club from test.anahnu.club to anahnu.club, in place of the old Joomla site.
# Members and content are imported anew from Joomla; only the administrator settings of the test
# site stay (languages, translators and their keys, site pages, interface texts).
#
#   bash deploy/siteground/go-live.sh          from ~/www/test.anahnu.club/app
#
# The Joomla files are kept as ~/www/anahnu.club/public_html_joomla and its database is untouched,
# so going back is: bash deploy/siteground/go-live.sh --back
set -euo pipefail

PHP="${PHP:-php}"
DOMAIN=anahnu.club
TEST_DIR="$HOME/www/test.$DOMAIN"
LIVE_DIR="$HOME/www/$DOMAIN"
JOOMLA="$LIVE_DIR/public_html_joomla"

if [ "${1:-}" = --back ]; then
    [ -d "$JOOMLA" ] || { echo "No $JOOMLA: nothing to go back to." >&2; exit 1; }
    mv "$LIVE_DIR/public_html" "$LIVE_DIR/public_html_new"
    mv "$JOOMLA" "$LIVE_DIR/public_html"
    echo "The old Joomla site is back at https://$DOMAIN. The new site's files are in $LIVE_DIR/public_html_new,"
    echo "its code in $LIVE_DIR/app. Turn the cron job off in Site Tools until the next try."
    exit 0
fi

[ -d "$LIVE_DIR" ] || { echo "No $LIVE_DIR folder." >&2; exit 1; }
[ ! -e "$LIVE_DIR/app" ] || { echo "$LIVE_DIR/app already exists: the switch was done before." >&2; exit 1; }
[ -f "$TEST_DIR/app/.env" ] || { echo "No installed site in $TEST_DIR/app." >&2; exit 1; }
"$PHP" -r 'exit(version_compare(PHP_VERSION, "8.2", "<") ? 1 : 0);' || { echo "PHP is too old: run with PHP=php83." >&2; exit 1; }

echo "==> Turning the old site off (kept as $JOOMLA)"
if [ -f "$LIVE_DIR/public_html/configuration.php" ]; then
    mv "$LIVE_DIR/public_html" "$JOOMLA"
fi
[ -d "$JOOMLA" ] || { echo "No Joomla site found in $LIVE_DIR/public_html." >&2; exit 1; }
mkdir -p "$LIVE_DIR/public_html"
echo '<!doctype html><meta charset="utf-8"><title>Anahnu</title><p style="font:18px sans-serif;text-align:center;margin-top:20vh">Сайт обновляется, через несколько минут всё заработает.</p>' > "$LIVE_DIR/public_html/index.html"

echo "==> Moving the code to $LIVE_DIR/app"
mv "$TEST_DIR/app" "$LIVE_DIR/app"
cd "$LIVE_DIR/app"
sed -i "s#^APP_URL=.*#APP_URL=https://$DOMAIN#" .env

trap 'echo; echo "Stopped. Fix the error above and go on with: cd $LIVE_DIR/app && php artisan site:reset --force && JOOMLA_FILES=$JOOMLA IMPORT=1 bash deploy/siteground/deploy.sh"; echo "or bring the old site back: bash $LIVE_DIR/app/deploy/siteground/go-live.sh --back"' ERR
echo "==> Emptying the database: what was tried on the test site does not go live"
"$PHP" artisan config:clear >/dev/null
"$PHP" artisan site:reset --force

echo "==> Importing everything from the old site anew"
JOOMLA_FILES="$JOOMLA" IMPORT=1 PHP="$PHP" bash deploy/siteground/deploy.sh

trap - ERR
echo "==> test.$DOMAIN now sends visitors to $DOMAIN"
find "$TEST_DIR/public_html" -mindepth 1 -maxdepth 1 ! -name .well-known -exec rm -rf {} +
cat > "$TEST_DIR/public_html/.htaccess" <<HT
RewriteEngine On
RewriteRule ^(.*)$ https://$DOMAIN/\$1 [R=301,L]
HT

echo
echo "Done: https://$DOMAIN"
echo "Still to do in Site Tools and elsewhere:"
echo "  1. Devs > Cron Jobs: replace the old job with"
echo "       cd $LIVE_DIR/app && $PHP artisan schedule:run >> /dev/null 2>&1"
echo "  2. Google and Facebook developer consoles: add https://$DOMAIN/auth/google/callback"
echo "     and https://$DOMAIN/auth/facebook/callback"
echo "Going back to the old site: bash $LIVE_DIR/app/deploy/siteground/go-live.sh --back"
