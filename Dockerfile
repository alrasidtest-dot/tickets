# IT Helpdesk Ticketing System — Railway deployment image.
# PHP 8.2 with the built-in web server; document root is public/ only.
FROM php:8.2-cli

# PDO MySQL driver required by core/Database.php.
RUN docker-php-ext-install pdo_mysql

WORKDIR /var/www/html
COPY . /var/www/html/

# Serve public/ on the port Railway injects via $PORT (8080 locally).
# The built-in server uses public/index.php as the directory index, so the
# query-string routing (index.php?page=...) works without extra rewrite rules.
EXPOSE 8080
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t public"]
