FROM php:8.2-apache

# Install MySQL/MariaDB extension required by mysqli
RUN docker-php-ext-install mysqli

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy project files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html/

# Make sure Apache can read the application
RUN chown -R www-data:www-data /var/www/html/

# Render provides the PORT environment variable.
# Apache listens on port 80 by default, so configure it
# to use Render's PORT when supplied.

RUN sed -i 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf && \
    sed -i 's/<VirtualHost \*:80>/<VirtualHost *:10000>/' \
    /etc/apache2/sites-available/000-default.conf

EXPOSE 10000

CMD ["apache2-foreground"]
