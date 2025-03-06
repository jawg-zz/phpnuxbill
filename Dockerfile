# Use the official PHP image with Apache
FROM serversideup/php:8.4-unit
EXPOSE 8080
# Create and configure config directory
RUN mkdir -p /var/www/html/config

# copy contents into directory
COPY . /var/www/html

# Set appropriate permissions
RUN sudo chown -R www-data:www-data /var/www/html
RUN sudo chmod -R 755 /var/www/html
