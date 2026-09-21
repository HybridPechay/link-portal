FROM php:8.2-apache

# pdo_mysql for the database, plus opcache for performance.
RUN docker-php-ext-install pdo pdo_mysql opcache

# Harden PHP defaults a bit for production.
RUN { \
        echo 'expose_php = Off'; \
        echo 'display_errors = Off'; \
        echo 'log_errors = On'; \
        echo 'session.cookie_httponly = 1'; \
        echo 'session.use_strict_mode = 1'; \
        echo 'upload_max_filesize = 2M'; \
        echo 'post_max_size = 2M'; \
    } > /usr/local/etc/php/conf.d/hardening.ini

# Private, persistent session folder (mounted as a volume in docker-compose.yml)
# so rebuilding/restarting the container doesn't log everyone out.
RUN mkdir -p /var/lib/lp_sessions && chown www-data:www-data /var/lib/lp_sessions && chmod 700 /var/lib/lp_sessions

RUN a2enmod headers rewrite

# The php:apache image ignores .htaccess by default (AllowOverride None).
# Turn it on so the project's .htaccess rules actually apply.
RUN { \
        echo '<Directory /var/www/html>'; \
        echo '    AllowOverride All'; \
        echo '</Directory>'; \
    } > /etc/apache2/conf-available/allow-htaccess.conf \
    && a2enconf allow-htaccess

COPY . /var/www/html/

# Apache should serve the app directly; config.php etc. are PHP so they
# execute rather than get served as text, but we still lock them down.
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
