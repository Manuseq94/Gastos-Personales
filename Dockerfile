FROM php:8.3-apache

# pdo_mysql para la base; curl y mbstring ya vienen en la imagen oficial.
RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers setenvif

# Apache escucha en el puerto que define Railway (variable PORT).
RUN rm -f /etc/apache2/sites-enabled/000-default.conf \
    && sed -i 's/^Listen 80$//' /etc/apache2/ports.conf \
    && echo 'ServerName localhost' > /etc/apache2/conf-enabled/servername.conf
COPY docker/apache.conf /etc/apache2/sites-enabled/app.conf

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

RUN sed -i 's/\r$//' docker/entrypoint.sh \
    && chmod +x docker/entrypoint.sh \
    && chown -R www-data:www-data /var/www/html

EXPOSE 8080

CMD ["/var/www/html/docker/entrypoint.sh"]
