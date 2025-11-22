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

# Composer but without lock file
COPY composer.json ./
RUN composer update --no-interaction --with-all-dependencies --prefer-dist --no-progress

# Copy only what is needed
COPY src/ src/
COPY tests/ tests/
COPY phpunit.xml.dist phpunit.xml.dist

# Default command (can be overridden by docker-compose)
CMD ["php", "-v"]
