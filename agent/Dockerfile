ARG PHP_VERSION=8.5
ARG DEBIAN_VERSION=trixie

FROM php:${PHP_VERSION}-${DEBIAN_VERSION} AS app

LABEL org.opencontainers.image.source=https://github.com/yohang/unlockey

ARG EXTERNAL_USER_ID

# persistent / runtime deps
# hadolint ignore=DL3008
RUN apt-get update && apt-get install -y --no-install-recommends \
	file \
	gettext \
	git \
	libnss3-tools \
	sqlite3 \
	&& rm -rf /var/lib/apt/lists/*

RUN set -eux; \
    echo "Setting User id (external): ${EXTERNAL_USER_ID}"; \
    sed -i -r s/"(www-data:x:)([[:digit:]]+):([[:digit:]]+):"/\\1${EXTERNAL_USER_ID}:${EXTERNAL_USER_ID}:/g /etc/passwd; \
    sed -i -r s/"(www-data:x:)([[:digit:]]+):"/\\1${EXTERNAL_USER_ID}:/g /etc/group; \
    mkdir -p /var/run/php /app/var /var/www /data /config; \
    chown -R www-data:www-data /usr/local/etc/php /var/run/php /var/www /app /app/var /data /config

RUN addgroup --gid 986 gpio; \
    usermod -aG gpio www-data

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions @composer pdo_sqlite pcntl

USER www-data

ENV APP_ENV=prod

WORKDIR /app

COPY --chown=www-data:www-data composer.json composer.lock symfony.lock ./

RUN composer install --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

COPY --chown=www-data:www-data .env .env
COPY --chown=www-data:www-data bin bin/
COPY --chown=www-data:www-data config config/
COPY --chown=www-data:www-data migrations migrations/
COPY --chown=www-data:www-data src src/

RUN set -eux; \
    mkdir -p var/cache var/log; \
    composer install --prefer-dist --no-dev --no-progress; \
    composer dump-autoload --optimize --no-dev --classmap-authoritative; \
    php bin/console cache:clear; \
    php bin/console cache:warmup -eprod; \
    chmod +x bin/console; \
    sync


EXPOSE 80
