# Apache + PHP 8.5, deliberately the same shape as the XAMPP stack this app was
# built on: mod_php, not PHP-FPM. That is what makes the audited .htaccess rules
# (folder denies, dotfile deny, the uploads/ engine guard) keep working here;
# Render's Nginx runtime would ignore every one of them.

FROM php:8.5-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev libonig-dev unzip ca-certificates \
    && docker-php-ext-install pdo_mysql mbstring zip \
    && a2enmod rewrite headers mime remoteip \
    && rm -rf /var/lib/apt/lists/*

# Built into php.ini rather than a runtime ini file so the CLI (cron, migrations)
# gets the same limits as the web.
RUN { \
      echo 'expose_php=Off'; \
      echo 'display_errors=Off'; \
      echo 'log_errors=On'; \
      echo 'error_log=/var/www/html/storage/logs/php-error.log'; \
      echo 'memory_limit=192M'; \
      echo 'upload_max_filesize=8M'; \
      echo 'post_max_size=10M'; \
      echo 'max_execution_time=30'; \
      echo 'session.use_strict_mode=1'; \
      echo 'opcache.enable=1'; \
      echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/99-app.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY composer.json ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader

COPY . .

# AllowOverride All keeps .htaccess authoritative; without it every deny rule in
# that file is silently ignored and storage/logs/app.log becomes downloadable.
RUN { \
      echo '<Directory /var/www/html>'; \
      echo '  AllowOverride All'; \
      echo '  Require all granted'; \
      echo '</Directory>'; \
      echo 'TraceEnable Off'; \
      echo 'RemoteIPHeader X-Forwarded-For'; \
    } > /etc/apache2/conf-available/dhaka.conf \
    && a2enconf dhaka \
    && sed -i -e 's/^ServerTokens.*/ServerTokens Prod/' -e 's/^ServerSignature.*/ServerSignature Off/' /etc/apache2/conf-available/security.conf \
    && rm -rf /var/www/html/.git /var/www/html/tests \
    && mkdir -p /var/www/html/storage/logs /var/www/html/uploads/menu /etc/dhaka \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/uploads \
    && chmod +x /var/www/html/scripts/docker-entrypoint.sh

# Render terminates TLS and injects PORT; the entrypoint repoints Apache at it.
ENV PORT=80 \
    APACHE_BASE_PATH=

ENTRYPOINT ["/var/www/html/scripts/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
