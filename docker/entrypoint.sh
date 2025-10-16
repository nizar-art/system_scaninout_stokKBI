#!/bin/bash

cd /var/www/html

# Install dependencies if not already installed
if [ ! -d "vendor" ]; then
    composer install --no-interaction
fi

# Generate application key if not already set
if ! grep -q "APP_KEY=" .env || grep -q "APP_KEY=base64:$" .env; then
    php artisan key:generate
fi

# Run migrations
php artisan migrate --force

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set proper permissions
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html/storage

# Execute passed command
exec "$@"
