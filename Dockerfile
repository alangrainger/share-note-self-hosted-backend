FROM php:8.3-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    gettext-base \
    && docker-php-ext-install zip pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy application files
COPY ./api /var/www/html/api
COPY ./public /var/www/html/public

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Create a startup script
WORKDIR /var/www/html
COPY ./docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Set the entrypoint to our custom script
ENTRYPOINT ["docker-entrypoint.sh"]

# Set the command to start Apache
CMD ["apache2-foreground"]