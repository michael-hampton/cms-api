FROM php:8.2-apache

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Enable Apache modules
RUN a2enmod rewrite headers

# Allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Copy custom PHP config for large uploads
COPY ./uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Optional: copy source code if you don’t mount it as a volume
# COPY ./src /var/www/html/
