FROM php:8.2-apache

# Copy app
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Install PHP extensions (core PHP + mysqli only)
RUN docker-php-ext-install mysqli

# Fix Apache warnings
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf
RUN echo "DirectoryIndex index.php index.html" >> /etc/apache2/mods-enabled/dir.conf

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
