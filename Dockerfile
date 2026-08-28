FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY . /var/www/html
COPY php.ini-production /usr/local/etc/php/conf.d/zz-house-production.ini
RUN mkdir -p storage/logs storage/uploads \
    && chown -R www-data:www-data storage \
    && chmod -R 750 storage

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD php -r "exit(@file_get_contents('http://127.0.0.1/')===false?1:0);"
CMD ["sh", "-c", "chown -R www-data:www-data /var/www/html/storage && apache2-foreground"]

