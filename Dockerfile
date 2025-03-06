# Use the official PHP image with Apache
FROM serversideup/php:8.4-unit
EXPOSE 9000
# Create and configure config directory
RUN mkdir -p /var/www/html/config

# copy contents into directory
COPY . /var/www/html