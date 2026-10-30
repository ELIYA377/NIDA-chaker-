FROM php:8.1-apache
RUN docker-php-ext-install pdo pdo_sqlite
RUN echo "DirectoryIndex index.php index.html" > /etc/apache2/conf-enabled/directory-index.conf
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html
EXPOSE 80
