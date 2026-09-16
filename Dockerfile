# Shared base: PHP 8.5 CLI + Composer. Used directly by docker-compose, which
# bind-mounts the working tree and the host-installed vendor/ into it.
FROM php:8.5-cli AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Self-contained image for reviewers without PHP 8.5:
#   docker build -t alcor . && docker run --rm alcor composer check
FROM base AS app

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --no-scripts

COPY . .
RUN composer dump-autoload --optimize

CMD ["composer", "check"]
