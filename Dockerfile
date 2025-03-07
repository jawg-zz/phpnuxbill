# Use the serversideup PHP image
FROM serversideup/php:8.4-unit AS base

FROM base AS development

# Create required directories
USER root

# Save the build arguments as variables
ARG USER_ID
ARG GROUP_ID

# Update www-data user/group IDs
RUN if [ ! -z "$USER_ID" ]; then \
        usermod -u $USER_ID www-data; \
    fi && \
    if [ ! -z "$GROUP_ID" ]; then \
        groupmod -g $GROUP_ID www-data; \
    fi

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
