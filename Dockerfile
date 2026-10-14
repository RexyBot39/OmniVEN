# OmniVEN production image (Railway). PHP 8.4 + nginx + php-fpm.
FROM serversideup/php:8.4-fpm-nginx

ENV PHP_OPCACHE_ENABLE=1 \
    AUTORUN_ENABLED=false \
    NGINX_WEBROOT=/var/www/html/public

WORKDIR /var/www/html

COPY --chown=www-data:www-data composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY --chown=www-data:www-data . .
RUN composer dump-autoload --no-dev --optimize \
 && php artisan package:discover --ansi \
 && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

# Railway routes traffic to $PORT; this image's nginx listens on 8080.
EXPOSE 8080
