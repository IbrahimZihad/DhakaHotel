#!/bin/sh
# Container start-up: TLS material, Render's port, and the ownership a mounted
# disk brings with it. Runs as root, then hands off to Apache (workers = www-data).
set -eu

# The Aiven/Cloud provider CA is git-ignored, so it arrives as a secret env var
# instead of a file in the image. Absolute path is honoured by db_ssl_options().
#
# DB_SSL_CA_PEM_B64 exists because a multi-line value does not survive every
# dashboard, copy-paste or CI layer: once the newlines are flattened mysqlnd
# rejects the file and the only symptom is "Cannot connect to MySQL using SSL".
# Base64 is one unbroken line, so it cannot be mangled that way.
CA_TEXT=''
CA_SOURCE=''
if [ -n "${DB_SSL_CA_PEM_B64:-}" ]; then
    CA_TEXT=$(printf '%s' "$DB_SSL_CA_PEM_B64" | base64 -d 2>/dev/null) || CA_TEXT=''
    CA_SOURCE=DB_SSL_CA_PEM_B64
elif [ -n "${DB_SSL_CA_PEM:-}" ]; then
    CA_TEXT="$DB_SSL_CA_PEM"
    CA_SOURCE=DB_SSL_CA_PEM
fi

if [ -n "$CA_TEXT" ]; then
    umask 027
    printf '%s\n' "$CA_TEXT" > /etc/dhaka/ca.pem
    if openssl x509 -in /etc/dhaka/ca.pem -noout >/dev/null 2>&1; then
        chown root:www-data /etc/dhaka/ca.pem
        chmod 640 /etc/dhaka/ca.pem
        export DB_SSL_CA=/etc/dhaka/ca.pem
    else
        # Deleting it matters: leaving a bad file here would override a working
        # DB_SSL_CA, such as a Render Secret File at /etc/secrets/ca.pem.
        rm -f /etc/dhaka/ca.pem
        echo "dhaka: ${CA_SOURCE} is not a parseable PEM certificate, so it was" \
            "not used and DB_SSL_CA stays as '${DB_SSL_CA:-unset}'. A multi-line" \
            "value loses its newlines somewhere between the dashboard and the" \
            "container - use DB_SSL_CA_PEM_B64, or a Render Secret File plus" \
            "DB_SSL_CA=/etc/secrets/ca.pem." >&2
    fi
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
