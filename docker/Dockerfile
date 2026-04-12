FROM php:8.2-apache-bookworm

RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite

WORKDIR /var/www/html

COPY . .

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

RUN printf '%s\n' \
 '<VirtualHost *:80>' \
 '  DocumentRoot /var/www/html/public' \
 '  <Directory /var/www/html/public>' \
 '    AllowOverride All' \
 '    Require all granted' \
 '  </Directory>' \
 '</VirtualHost>' \
 > /etc/apache2/sites-available/000-default.conf

ENTRYPOINT ["/entrypoint.sh"]
