# Use the serversideup PHP image
FROM serversideup/php:8.4-fpm

EXPOSE 9000

# Create required directories
USER root
RUN mkdir -p /var/www/html/config /var/www/html/system/uploads

# Copy application files with correct ownership
COPY --chown=www-data:www-data . /var/www/html/

# Set directory permissions as root
RUN chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/config \
    && chmod -R 775 /var/www/html/system/uploads

# Switch back to non-root user
USER www-data

# Set working directory
WORKDIR /var/www/html
