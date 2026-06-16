# IT Helpdesk Ticketing System — Railway deployment image.
# Apache + PHP 8.2, document root pointed at public/ only.
FROM php:8.2-apache

# PDO MySQL driver required by core/Database.php.
RUN docker-php-ext-install pdo_mysql

# Force a single MPM. The base image can end up with more than one MPM loaded
# (Apache error AH00534); PHP needs the prefork MPM, so disable the others.
RUN a2dismod mpm_event mpm_worker 2>/dev/null; a2enmod mpm_prefork

# Enable rewrite (harmless; routing is query-string based).
RUN a2enmod rewrite

# Point Apache at public/ and allow .htaccess overrides there.
COPY docker/vhost.conf /etc/apache2/sites-available/000-default.conf

# Copy the application. Only public/ is web-served; everything else lives
# outside the document root and is reached via includes from public/index.php.
COPY . /var/www/html/

# Let Apache write to uploads/ and logs/ at runtime.
RUN chown -R www-data:www-data /var/www/html/uploads /var/www/html/logs

# Railway provides the listening port via $PORT; bind Apache to it at start.
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
