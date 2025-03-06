# Use the official PHP image with Apache
FROM serversideup/php:8.4-unit
EXPOSE 8080
# Create and configure config directory
RUN mkdir -p /var/www/html/config

# copy contents into directory
COPY . /var/www/html

# Set appropriate permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html
