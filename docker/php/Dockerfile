FROM php:8.4-fpm

# Set working directory
WORKDIR /var/www

# Install system dependencies
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    libxpm-dev \
    locales \
    zip \
    jpegoptim optipng pngquant gifsicle \
    vim \
    unzip \
    git \
    curl \
    wget \
    libonig-dev \
    libzip-dev \
    libgd-dev \
    libicu-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    # Redis dependencies
    autoconf \
    gcc \
    g++ \
    make \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configure and install GD extension with full support
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp \
    --with-xpm

# Install PHP extensions required by NukeViet 5.x
RUN docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    mysqli \
    mbstring \
    zip \
    exif \
    pcntl \
    gd \
    intl \
    soap \
    bcmath \
    opcache \
    calendar

# Install Redis extension via PECL
RUN pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Create user for NukeViet application
RUN groupadd -g 1000 www \
    && useradd -u 1000 -ms /bin/bash -g www www

# Set proper permissions
RUN chown -R www:www /var/www

# Change current user to www
USER www

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]
