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
    && rm -rf /var/lib/apt/lists/*


RUN docker-php-ext-install pdo pdo_mysql zip bcmath


RUN a2enmod rewrite


RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer


RUN pecl install grpc && docker-php-ext-enable grpc


RUN echo 'PassEnv DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD' >> /etc/apache2/conf-available/passenv.conf && a2enconf passenv


WORKDIR /var/www/html

COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 80
