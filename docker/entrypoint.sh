#!/bin/sh
set -e

# Railway inyecta PORT; en local usamos 8080 por defecto.
export PORT="${PORT:-8080}"

php /var/www/html/docker/init-db.php || true

exec apache2-foreground
