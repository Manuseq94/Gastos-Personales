FROM php:8.3-apache

# pdo_mysql para la base; curl y mbstring ya vienen en la imagen oficial.
RUN docker-php-ext-install pdo_mysql \
    && rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf \
    && ln -s ../mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load \
    && ln -s ../mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf \
    && a2enmod rewrite headers setenvif

# Apache escucha en el puerto que define Railway (variable PORT).
RUN rm -f /etc/apache2/sites-enabled/000-default.conf \
    && sed -i 's/^Listen 80$//' /etc/apache2/ports.conf \
    && echo 'ServerName localhost' > /etc/apache2/conf-enabled/servername.conf
COPY docker/apache.conf /etc/apache2/sites-enabled/app.conf

# Si la configuración de Apache es inválida, el build falla acá (y no en runtime).
RUN grep -rn "LoadModule mpm_" /etc/apache2 --include=*.load --include=*.conf || true \
    && PORT=8080 apache2ctl -t

# Configuración de PHP para producción.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo 'date.timezone=${TZ}'; \
        echo 'expose_php=Off'; \
    } > "$PHP_INI_DIR/conf.d/app.ini"

ENV PORT=8080 \
    TZ=America/Argentina/Buenos_Aires

WORKDIR /var/www/html
COPY backend/ ./backend/
COPY frontend/ ./frontend/
COPY docker/ ./docker/

RUN echo '<!doctype html><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=frontend/"><a href="frontend/">Ir a la aplicación</a>' > index.html \
    && sed -i 's/\r$//' docker/entrypoint.sh \
    && chmod +x docker/entrypoint.sh \
    && chown -R www-data:www-data /var/www/html

EXPOSE 8080

CMD ["/var/www/html/docker/entrypoint.sh"]
