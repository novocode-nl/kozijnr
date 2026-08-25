# Kozijnr backend — local development image.
# Pinned explicitly to PHP 8.5 so the toolchain doesn't silently drift.
FROM php:8.5-cli-alpine

RUN apk add --no-cache \
        git \
        unzip \
        icu-dev \
        libzip-dev \
        postgresql-dev \
    && docker-php-ext-install \
        intl \
        pdo \
        pdo_pgsql \
        zip \
    && rm -rf /var/cache/apk/*
# opcache ships built into the php:8.5 image already, no install needed

# KOZ-32: without this, PHP falls back to its built-in defaults
# (upload_max_filesize=2M, post_max_size=8M), which silently reject any
# profile-photo upload between 2MB and the application's own 5MB limit
# before UploadProfilePhoto::MAX_SIZE_IN_BYTES is ever reached.
COPY uploads.ini "$PHP_INI_DIR/conf.d/uploads.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
