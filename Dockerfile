# Use the serversideup PHP image
FROM serversideup/php:8.4-unit AS base

#Development environment

EXPOSE 8080

FROM base AS development

# Create required directories
USER root

# Save the build arguments as a variable
ARG USER_ID
ARG GROUP_ID

# Use the build arguments to change the UID 
# and GID of www-data while also changing 
# the file permissions for NGINX
RUN docker-php-serversideup-set-id www-data $USER_ID:$GROUP_ID && \
    \
    # Update the file permissions for our NGINX service to match the new UID/GID
    docker-php-serversideup-set-file-permissions --owner $USER_ID:$GROUP_ID --service nginx

RUN mkdir -p /var/www/html/config /var/www/html/system/uploads

    # Copy application files
COPY . /var/www/html/

# Set directory permissions as root
RUN chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/config \
    && chmod -R 775 /var/www/html/system/uploads

# Switch back to non-root user
USER www-data

# Set working directory
WORKDIR /var/www/html

# Production environment

FROM base AS production

# Copy application files with correct ownership

RUN mkdir -p /var/www/html/config /var/www/html/system/uploads

COPY --chown=www-data:www-data . /var/www/html/
