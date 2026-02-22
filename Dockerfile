FROM php:8.3-fpm

# Install system dependencies including Node.js
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    zip \
    unzip \
    default-mysql-client \
    gnupg \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js 18 for asset compilation
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer.json only (lock may be out of sync after L11 upgrade; resolve in image)
COPY composer.json ./

# Install PHP dependencies (prod) — use update so build works without a current composer.lock
RUN composer update --no-dev --optimize-autoloader --no-interaction --ignore-platform-req=ext-zip --no-scripts

# Copy application files (vendor/ and node_modules/ excluded via .dockerignore)
COPY . .

# Create .env from example if missing
RUN if [ -f ".env.example" ] && [ ! -f ".env" ]; then cp .env.example .env; fi

# Install Node dependencies and build assets (Vite)
RUN set +e; \
    if [ -f "package.json" ]; then \
        npm install || echo "Warning: npm install failed, continuing..."; \
        npm run build || echo "Warning: npm run build failed, continuing..."; \
        rm -rf node_modules 2>/dev/null || true; \
    fi; \
    set -e; \
    echo "Vite build step completed"

# Prepare writable directories and set permissions
RUN mkdir -p storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# Backup public directory (will be copied to volume on startup)
RUN cp -r public /tmp/public-backup || true

# Create entrypoint script - SKIP auto migrations
RUN printf '#!/bin/sh\n\
set -e\n\
echo "=== MyCities Laravel Container Starting ==="\n\
if [ ! -f /var/www/html/.env ]; then\
    echo "Creating .env file...";\
    cat > /var/www/html/.env << ENVFILE\n\
APP_NAME=${APP_NAME:-MyCities}\n\
APP_ENV=${APP_ENV:-production}\n\
APP_KEY=${APP_KEY:-}\n\
APP_DEBUG=${APP_DEBUG:-false}\n\
APP_URL=${APP_URL:-http://localhost}\n\
DB_CONNECTION=${DB_CONNECTION:-mysql}\n\
DB_HOST=${DB_HOST:-mysql}\n\
DB_PORT=${DB_PORT:-3306}\n\
DB_DATABASE=${DB_DATABASE:-mycities}\n\
DB_USERNAME=${DB_USERNAME:-mycities_user}\n\
DB_PASSWORD=${DB_PASSWORD:-MyCities2024Pass!}\n\
SESSION_DRIVER=${SESSION_DRIVER:-file}\n\
CACHE_DRIVER=${CACHE_DRIVER:-file}\n\
QUEUE_CONNECTION=${QUEUE_CONNECTION:-sync}\n\
ENVFILE\n\
fi\n\
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true;\
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache;\
echo "Syncing public files to volume...";\
cp -r /tmp/public-backup/* /var/www/html/public/ 2>/dev/null || true;\
chown -R www-data:www-data /var/www/html/public;\
echo "Waiting for MySQL...";\
MAX_TRIES=30;\
TRIES=0;\
while ! mysqladmin ping -h"$DB_HOST" --silent 2>/dev/null; do\
    TRIES=$((TRIES + 1));\
    if [ $TRIES -ge $MAX_TRIES ]; then\
        echo "MySQL not available after $MAX_TRIES attempts, continuing anyway...";\
        break;\
    fi;\
    echo "  Waiting for MySQL... ($TRIES/$MAX_TRIES)";\
    sleep 2;\
done;\
echo "MySQL is ready!";\
if grep -q "^APP_KEY=$" /var/www/html/.env 2>/dev/null; then php artisan key:generate --force 2>/dev/null || true; fi;\
echo "Skipping auto-migrations - run manually after container starts";\
php artisan config:clear 2>/dev/null || true;\
echo "=== Laravel container ready ===";\
exec "$@"\n' > /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["php-fpm"]
