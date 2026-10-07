#!/bin/sh
set -e

# Railway inyecta PORT; en local usamos 8080 por defecto.
export PORT="${PORT:-8080}"

php /var/www/html/docker/init-db.php || true

# PHP+Apache requiere prefork: nos aseguramos de que sea el único MPM habilitado.
rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf
ln -s ../mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load
ln -s ../mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf

exec apache2-foreground
