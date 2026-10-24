# GIMT Enterprise ERP - Production Dockerfile
# Base Image: PHP 8.2 with Apache Web Server
FROM php:8.2-apache

# Set Maintainer Label
LABEL maintainer="GIMT Technical Directorate <admin@gimt.ac.in>"
LABEL version="3.0-MAKAUT"

# Install System Dependencies & Build Tools
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    mariadb-client \
    && rm -rf /var/lib/apt/lists/*

# Configure & Install PHP Extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mysqli \
        gd \
        zip \
        opcache

# Enable Essential Apache Modules
RUN a2enmod rewrite headers expires

# Configure Production PHP Environment
RUN { \
        echo 'upload_max_filesize = 32M'; \
        echo 'post_max_size = 40M'; \
        echo 'memory_limit = 256M'; \
        echo 'max_execution_time = 180'; \
        echo 'date.timezone = Asia/Kolkata'; \
        echo 'session.cookie_httponly = 1'; \
        echo 'session.use_only_cookies = 1'; \
        echo 'session.cookie_samesite = Strict'; \
        echo 'opcache.enable = 1'; \
        echo 'opcache.memory_consumption = 128'; \
        echo 'opcache.interned_strings_buffer = 8'; \
        echo 'opcache.max_accelerated_files = 10000'; \
        echo 'opcache.revalidate_freq = 2'; \
    } > /usr/local/etc/php/conf.d/gimt-production.ini

# Set Apache DocumentRoot to application directory
ENV APACHE_DOCUMENT_ROOT=/var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Set Working Directory
WORKDIR /var/www/html

# Copy Project Files
COPY . /var/www/html/

# Create Upload Subdirectories & Ensure Correct File Ownership
RUN mkdir -p /var/www/html/uploads/admissions \
             /var/www/html/uploads/assignments \
             /var/www/html/uploads/mar \
             /var/www/html/uploads/moocs \
             /var/www/html/uploads/profile \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/uploads

# Expose HTTP Port
EXPOSE 80

# Launch Apache Foreground Process
CMD ["apache2-foreground"]
