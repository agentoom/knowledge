FROM php:8.4-cli-alpine

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    postgresql-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    libxml2-dev \
    libzip-dev \
    unzip \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        gd \
        mbstring \
        xml \
        zip \
        bcmath \
        pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies first (better layer caching)
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader

# Copy application files
COPY . .

# Run post-install scripts now that all files are present
# Skip artisan commands at build time — they need env vars only available at runtime
RUN composer run-script post-autoload-dump --no-interaction

# Ensure writable directories
RUN mkdir -p storage/framework/{cache,views,sessions} storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Create and set up the entrypoint
COPY docker/glama/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Create non-root user
RUN addgroup -S app && adduser -S -G app app \
    && chown -R app:app /var/www/html
USER app

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php", "artisan", "mcp:start", "knowledge"]
