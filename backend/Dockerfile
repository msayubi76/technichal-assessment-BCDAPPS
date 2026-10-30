FROM php:8.4-cli

# System packages Composer needs, plus the PHP extensions this app uses.
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev default-mysql-client \
    && docker-php-ext-install pdo_mysql bcmath pcntl zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# The built-in server runs as CLI, where OPcache is off by default, so every
# request would compile the whole framework again. Timestamps are still
# checked, so code changes show up straight away.
RUN printf "opcache.enable_cli=1\nopcache.validate_timestamps=1\nopcache.revalidate_freq=0\n" \
    > /usr/local/etc/php/conf.d/opcache-cli.ini

WORKDIR /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 8000

ENTRYPOINT ["entrypoint"]

# PHP's built-in server with several workers (PHP_CLI_SERVER_WORKERS) is fine
# for local use. We call it directly instead of `artisan serve`, because that
# command drops the container's environment variables. Production would run
# php-fpm behind nginx, or Laravel Octane.
CMD ["sh", "-c", "cd public && exec php -S 0.0.0.0:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"]
