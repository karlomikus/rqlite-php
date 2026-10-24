FROM php:8.2-cli-alpine

# Copy Composer from the official image to keep this image minimal
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Composer needs unzip (or ext-zip) to extract package archives
RUN apk add --no-cache unzip

WORKDIR /app
