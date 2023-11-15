# Use the official PHP image with a specified version
ARG PHP_VERSION
FROM php:${PHP_VERSION}-cli

# Set the working directory inside the container
WORKDIR /app

# Copy the composer.json and composer.lock files to the container
COPY composer.json composer.lock ./

# Install project dependencies
RUN apt-get update -yqq
RUN apt-get install git unzip -yqq

# Install Composer globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer > /dev/null 2>&1

# Install project dependencies
RUN composer install > /dev/null 2>&1

# Copy the rest of the application code to the container
COPY . .