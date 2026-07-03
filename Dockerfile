FROM php:8.5-apache

# Install system dependencies first
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    ffmpeg \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mysqli \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        xml

# Enable Apache modules
RUN a2enmod rewrite headers

# Allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Create export directories and assign ownership to www-data
RUN mkdir -p /var/www/html/storage/exports/labels \
    && mkdir -p /var/www/html/storage/exports/print \
    && chown -R www-data:www-data /var/www/html/storage \
    && chmod -R 775 /var/www/html/storage

# Copy custom PHP config for large uploads
COPY ./uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Optional: copy source code if you don’t mount it as a volume
# COPY ./src /var/www/html/
