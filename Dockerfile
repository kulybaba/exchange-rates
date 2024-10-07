FROM php:8.3-fpm

RUN apt update && apt install -y vim git curl wget zip unzip libzip-dev

RUN apt clean && rm -rf /var/lib/apt/lists/*

RUN pecl install redis && docker-php-ext-enable redis

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

CMD ["php-fpm"]
