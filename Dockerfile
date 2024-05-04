FROM php:8.2

SHELL ["/bin/bash", "-c"]

# Basic dependencies
RUN apt-get update && apt-get install -y --no-install-recommends coreutils git wget curl gnupg libcurl4-openssl-dev libxml2-dev libzip-dev procps build-essential cmake docker.io libssl-dev pkg-config libpng-dev libwebp-dev libmagickwand-dev &&\
    apt-get clean &&\
    rm -rf /var/lib/apt/lists/*

# PHP extensions: Imagick
RUN pecl install -f --onlyreqdeps --nobuild imagick      &&\
    cd "$(pecl config-get temp_dir)/imagick"             &&\
    phpize                                               &&\
    ./configure                                          &&\
    make -j$(nproc --all) && make install

# PHP extensions: Swoole
RUN pecl install -f --onlyreqdeps --nobuild swoole &&\
    cd "$(pecl config-get temp_dir)/swoole"        &&\
    phpize                                         &&\
    ./configure                                    &&\
    make -j$(nproc --all) && make install

# Enable PECL-based extension: Imagick + Swoole
RUN docker-php-ext-enable imagick swoole

# Install several officially supported PHP extensions: cURL, XML, ZIP, DOM, MySQLi, PDO MySQL, Sockets and PCNTL.
RUN docker-php-ext-install -j$(( $(nproc --all) * 2 )) curl xml zip dom mysqli pdo_mysql sockets pcntl

# Install the ping tool
RUN apt-get update && apt-get install -y --no-install-recommends inetutils-ping &&\
    apt-get clean &&\
    rm -rf /var/lib/apt/lists/*

# Install the Composer PHP package manager
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# PHP extension: intl
RUN docker-php-ext-install -j$(( $(nproc --all) * 2 )) intl

# Install unzip
RUN apt-get update && apt-get install -y --no-install-recommends unzip &&\
    apt-get clean &&\
    rm -rf /var/lib/apt/lists/*

# Install pstree (psmisc)
RUN apt-get update && apt-get install -y --no-install-recommends psmisc &&\
    apt-get clean &&\
    rm -rf /var/lib/apt/lists/*

WORKDIR /var/www

ENTRYPOINT [ "./run.sh" ]