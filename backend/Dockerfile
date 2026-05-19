ARG PHP_VERSION=8.5
ARG FRANKENPHP_VERSION=1.12
ARG DEBIAN_VERSION=trixie

FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION}-${DEBIAN_VERSION} AS app

LABEL org.opencontainers.image.source=https://github.com/yohang/unlockey

ARG EXTERNAL_USER_ID

# persistent / runtime deps
# hadolint ignore=DL3008
RUN apt-get update && apt-get install -y --no-install-recommends \
	file \
	gettext \
	git \
	libnss3-tools \
	&& rm -rf /var/lib/apt/lists/*

RUN set -eux; \
	install-php-extensions \
		@composer \
		apcu \
		intl \
		opcache \
		zip \
		pdo_pgsql \
	;


COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY --chown=www-data:www-data infra/docker/php/Caddyfile /etc/caddy/Caddyfile

RUN set -eux; \
    echo "Setting User id (external): ${EXTERNAL_USER_ID}"; \
    sed -i -r s/"(www-data:x:)([[:digit:]]+):([[:digit:]]+):"/\\1${EXTERNAL_USER_ID}:${EXTERNAL_USER_ID}:/g /etc/passwd; \
    sed -i -r s/"(www-data:x:)([[:digit:]]+):"/\\1${EXTERNAL_USER_ID}:/g /etc/group; \
    mkdir -p /var/run/php /app/var /var/www /data /config; \
    chown -R www-data:www-data /usr/local/etc/php /var/run/php /var/www /app /app/var /data /config


USER www-data

ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"

###> recipes ###
###< recipes ###

COPY --chown=www-data infra/docker/php/conf.d/10-app.ini $PHP_INI_DIR/app.conf.d/
COPY --chown=www-data --chmod=755 infra/docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --chown=www-data infra/docker/php/Caddyfile /etc/caddy/Caddyfile

COPY --chown=www-data infra/docker/php/conf.d/20-app.ini-prod $PHP_INI_DIR/app.conf.d/20-app.ini


ENV APP_ENV=prod

WORKDIR /app

COPY --chown=www-data:www-data composer.json composer.lock symfony.lock ./
RUN composer install --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

COPY --chown=www-data:www-data .env .env
COPY --chown=www-data:www-data importmap.php importmap.php
COPY --chown=www-data:www-data assets assets/
COPY --chown=www-data:www-data bin bin/
COPY --chown=www-data:www-data config config/
COPY --chown=www-data:www-data migrations migrations/
COPY --chown=www-data:www-data public public/
COPY --chown=www-data:www-data src src/
COPY --chown=www-data:www-data templates templates/
COPY --chown=www-data:www-data translations translations/

RUN set -eux; \
    mkdir -p var/cache var/log; \
    composer install --prefer-dist --no-dev --no-progress; \
    composer dump-autoload --optimize --no-dev --classmap-authoritative; \
    php bin/console asset-map:compile; \
    php bin/console cache:clear; \
    php bin/console cache:warmup -eprod; \
    chmod +x bin/console; \
    sync


EXPOSE 80
EXPOSE 443
EXPOSE 443/udp

ENTRYPOINT ["/usr/local/bin/docker-entrypoint"]

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
