# Use the serversideup PHP image
FROM serversideup/php:8.4-unit AS base

FROM base AS development

# Create required directories
USER root

# Save the build arguments as variables
ARG USER_ID="1000"
ARG GROUP_ID="1000"

# Use the build arguments to change the UID and GID
RUN docker-php-serversideup-set-id www-data "${USER_ID}:${GROUP_ID}" && \
    docker-php-serversideup-set-file-permissions --owner "${USER_ID}:${GROUP_ID}" --service nginx

RUN mkdir -p /var/www/html/config /var/www/html/system/uploads

# Copy application files
COPY --chown=www-data:www-data . /var/www/html/

# Set directory permissions
RUN chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/config \
    && chmod -R 775 /var/www/html/system/uploads \
    && chown -R www-data:www-data /var/www/html

# Switch back to non-root user
USER www-data

# Set working directory
WORKDIR /var/www/html

# Expose port for development
EXPOSE 8080

FROM base AS production

RUN mkdir -p /var/www/html/config /var/www/html/system/uploads

COPY --chown=www-data:www-data . /var/www/html/

USER www-data

WORKDIR /var/www/html

# Expose port for production
EXPOSE 8080
