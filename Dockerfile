# =============================================================================
# BAUST Campus — single-stage build, mirrors the OBE-Nationwide (campus) image.
#
# The Vite build cannot run in a node-only stage: it imports `@/routes` and
# `@/actions`, which `php artisan wayfinder:generate` emits from the installed
# Laravel app (vendor + routes). Those dirs are git-ignored, so they are absent
# from a fresh checkout and must be generated here. We install PHP deps first,
# then build the frontend in the same image with a temporary Node toolchain,
# then strip Node.
# =============================================================================
FROM php:8.4-fpm-alpine AS production

LABEL maintainer="BAUST IT" \
      app.name="baust_campus" \
      app.version="1.0"

# Install system dependencies and PHP extensions in a single layer
RUN apk add --no-cache \
        nginx \
        supervisor \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
        zip \
        unzip \
        curl \
        oniguruma-dev \
        icu-dev \
        libxml2-dev \
        tzdata \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        xml \
        opcache \
    && rm -rf /var/cache/apk/* /tmp/* /var/tmp/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure PHP for production
RUN { \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=8'; \
        echo 'opcache.max_accelerated_files=4000'; \
        echo 'opcache.revalidate_freq=0'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.enable_cli=1'; \
        echo 'opcache.save_comments=1'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini \
    && { \
        echo 'upload_max_filesize=64M'; \
        echo 'post_max_size=64M'; \
        echo 'memory_limit=256M'; \
        echo 'max_execution_time=600'; \
        echo 'max_input_time=600'; \
        echo 'expose_php=Off'; \
    } > /usr/local/etc/php/conf.d/custom.ini

# Configure Nginx and Supervisor
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf

WORKDIR /var/www/html

# Install PHP dependencies first (cached layer keyed on composer files)
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --optimize-autoloader \
        --no-scripts

# Copy the rest of the application
COPY . .

# Re-run composer autoload (and package discovery) now that the full source is present
RUN composer dump-autoload --optimize

# -----------------------------------------------------------------------------
# Build frontend assets. Node is installed only for this step and removed after
# so it never ships in the runtime image. A throwaway APP_KEY lets artisan boot
# for `wayfinder:generate`; the real key is injected at runtime by compose.
# -----------------------------------------------------------------------------
RUN apk add --no-cache --virtual .build-node nodejs npm \
    && export APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    && export APP_ENV=production \
    && npm ci --ignore-scripts \
    && php artisan wayfinder:generate --with-form \
    && npm run build \
    && rm -rf node_modules \
    && apk del .build-node \
    && rm -rf /root/.npm /tmp/* /var/tmp/*

# Create required directories; only the writable dirs need www-data ownership.
RUN mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

# Health check for the container (Laravel /up endpoint)
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -f http://localhost/up || exit 1

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
