# 使用在用户其他项目中验证成功的 php:8.1-fpm 镜像
FROM php:8.1-fpm-bullseye

# 安装系统依赖和项目所需的 PHP 扩展
RUN set -eux; \
    apt-get -o Acquire::Retries=5 update; \
    apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        libzip-dev \
        libonig-dev \
        libxml2-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        pdo_mysql \
        zip \
        exif \
        pcntl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 安装 Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 设置工作目录
WORKDIR /var/www

# 创建必要的目录并设置权限
RUN mkdir -p runtime public/uploads && \
    chown -R www-data:www-data /var/www

# 暴露端口
EXPOSE 9000
