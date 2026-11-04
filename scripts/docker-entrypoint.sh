#!/bin/sh
# Container start-up: TLS material, Render's port, and the ownership a mounted
# disk brings with it. Runs as root, then hands off to Apache (workers = www-data).
set -eu

# The Aiven/Cloud provider CA is git-ignored, so it arrives as a secret env var
# instead of a file in the image. Absolute path is honoured by db_ssl_options().
if [ -n "${DB_SSL_CA_PEM:-}" ]; then
    umask 027
    printf '%s\n' "$DB_SSL_CA_PEM" > /etc/dhaka/ca.pem
    chown root:www-data /etc/dhaka/ca.pem
    chmod 640 /etc/dhaka/ca.pem
    export DB_SSL_CA=/etc/dhaka/ca.pem
fi

# Render injects PORT and routes there; the stock image still listens on 80.
PORT="${PORT:-80}"
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# A Render Disk lands mounted as root:root, which silently breaks every upload
# and every storage_log() call. Best effort: a read-only mount is not fatal.
for dir in /var/www/html/storage /var/www/html/uploads; do
    mkdir -p "$dir" 2>/dev/null || true
    chown -R www-data:www-data "$dir" 2>/dev/null || true
done

# Composer autoload is baked into the image, so opcache.validate_timestamps=0 is
# safe; a deploy is always a fresh container.
exec docker-php-entrypoint "$@"
