FROM php:8.3-fpm

# Laravel 13 için PHP eklentileri. pcntl: queue:work --timeout sinyal
# işleme için gerekli.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev libzip-dev libonig-dev \
    && docker-php-ext-install -j"$(nproc)" intl zip bcmath pdo_mysql mbstring opcache pcntl \
    && rm -rf /var/lib/apt/lists/*

# Aynı droplet'te 9000 (blt-php83) ve 9001 (proposial-php) dolu; 9002.
RUN printf '[www]\nlisten = 127.0.0.1:9002\n' > /usr/local/etc/php-fpm.d/zzz-listen.conf

RUN { \
        echo 'upload_max_filesize=8M'; \
        echo 'post_max_size=8M'; \
        echo 'memory_limit=256M'; \
        echo 'max_execution_time=60'; \
        echo 'expose_php=Off'; \
    } > /usr/local/etc/php/conf.d/zz-app.ini
