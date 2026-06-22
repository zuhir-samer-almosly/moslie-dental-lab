FROM php:8.3-fpm-alpine

# Install system dependencies including Node.js
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    nginx \
    supervisor \
    mysql-client \
    nodejs \
    npm \
    autoconf \
    gcc \
    g++ \
    make

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql zip gd

# Install PHP Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /opt/dental-lab/moslie-dental-lab

# Copy composer files and install PHP dependencies
COPY composer*.json ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy package files and install Node dependencies
COPY package*.json ./
RUN npm ci

# Copy application files
COPY . .

# Generate autoloader (needed for Wayfinder)
RUN composer dump-autoload --optimize

# Build frontend assets (Wayfinder will generate routes here)
RUN npm run build

# Set permissions
RUN chown -R www-data:www-data /opt/dental-lab/moslie-dental-lab \
    && chmod -R 755 /opt/dental-lab/moslie-dental-lab/storage \
    && chmod -R 755 /opt/dental-lab/moslie-dental-lab/bootstrap/cache

# Copy nginx configuration
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Make sure directories exist
RUN mkdir -p /etc/supervisor/conf.d /var/log/supervisor

# Copy supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy entrypoint script
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Expose port
EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
