# Use the official PHP image with Apache
FROM serversideup/php:8.4-unit
EXPOSE 80
# Create and configure config directory
RUN mkdir -p /var/www/html/config \
    && sudo chown www-data:www-data /var/www/html/config \
    && sudo chmod 755 /var/www/html/config

# copy contents into directory
COPY . /var/www/html

# Set appropriate permissions
RUN sudo chown -R www-data:www-data /var/www/html
RUN sudo chmod -R 755 /var/www/html

# Set working directory
WORKDIR /var/www/html