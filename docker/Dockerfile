# Use PHP version from build args
ARG PHP_VERSION
FROM php:${PHP_VERSION}-cli

# Set working directory
WORKDIR /app

# Non-interactive apt
ENV DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER=1

# Update apt for old PHP versions (buster/older), install required tools
RUN set -eux; \
    # Use archive.debian.org for old releases (Buster/EOL)
    if [ -f /etc/apt/sources.list ]; then \
      sed -i 's@http://deb.debian.org/debian@http://archive.debian.org/debian@g' /etc/apt/sources.list || true; \
      sed -i 's@http://security.debian.org/debian-security@http://archive.debian.org/debian@g' /etc/apt/sources.list || true; \
    fi; \
    printf '%s\n' 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99no-check-valid-until; \
    apt-get update -yqq || true; \
    apt-get install -y --no-install-recommends git unzip curl wget zip ca-certificates gnupg2 apt-transport-https; \
    rm -rf /var/lib/apt/lists/*

# Install Composer globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy only composer files first for caching
COPY composer.json composer.lock* ./

# Install/update PHP dependencies per PHP version with BuildKit cache
# Uses --mount to cache downloads across builds (speeds up repeated builds)
RUN --mount=type=cache,target=/root/.cache/composer \
    composer update --no-interaction --with-all-dependencies --prefer-dist --no-progress

# Copy the rest of the application
COPY . .

# Optimize autoload (optional)
RUN composer dump-autoload --optimize

# Default command (can be overridden by docker-compose)
CMD ["php", "-v"]
