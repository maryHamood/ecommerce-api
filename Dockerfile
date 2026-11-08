FROM php:8.3-apache


RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libc-dev \
    pkg-config \
    build-essential \
    && rm -rf /var/lib/apt/lists/*


RUN docker-php-ext-install pdo pdo_mysql zip bcmath


RUN pecl install redis && docker-php-ext-enable redis


RUN pecl install grpc && docker-php-ext-enable grpc


RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer


RUN a2enmod rewrite


RUN echo 'PassEnv DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD' >> /etc/apache2/conf-available/passenv.conf && a2enconf passenv


COPY --exclude=public/storage . /var/www/html


RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache


COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 80
