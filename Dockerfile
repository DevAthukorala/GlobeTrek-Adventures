FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && printf '<Directory /var/www/html/public>\nOptions -Indexes\nAllowOverride None\nRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/globetrek.conf \
    && a2enconf globetrek
COPY . /var/www/html
RUN printf 'display_errors=Off\nlog_errors=On\nexpose_php=Off\nsession.use_strict_mode=1\n' > /usr/local/etc/php/conf.d/globetrek.ini
