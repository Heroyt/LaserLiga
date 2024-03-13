FROM node:19-slim AS node_base
FROM php:8.2-fpm-bullseye as setup
LABEL authors="heroyt"

#COPY --from=node_base / /
COPY --from=node_base /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=node_base /usr/local/bin/node /usr/local/bin/node
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

# Setup
RUN apt-get update
RUN apt-get -y install apt-utils wget git build-essential gettext cron zlib1g-dev

#RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY docker/core/php.ini "$PHP_INI_DIR/php.ini"

# SPX
WORKDIR /home
RUN git clone https://github.com/NoiseByNorthwest/php-spx.git
WORKDIR /home/php-spx
RUN git checkout release/latest
RUN phpize
RUN ./configure
RUN make
RUN make install
RUN docker-php-ext-enable spx

COPY docker/core/spx.ini "$PHP_INI_DIR/conf.d/docker-php-ext-spx.ini"

FROM setup as extensions

# PHP extensions
RUN apt-get install -y libzip-dev unzip libicu-dev cifs-utils ffmpeg libpng-dev libwebp-dev
RUN apt install -y curl libcurl4-openssl-dev
RUN docker-php-ext-install mysqli curl gettext sockets pdo_mysql zip pcntl intl opcache gd
# Imagick
RUN apt-get update && apt-get install -y libmagickwand-dev --no-install-recommends && rm -rf /var/lib/apt/lists/*
RUN pecl install imagick
# Enable all extensions
RUN docker-php-ext-enable mysqli curl gettext sockets pdo_mysql zip pcntl intl opcache imagick
RUN docker-php-ext-configure gd --with-jpeg --with-freetype --with-webp && docker-php-ext-install gd

RUN apt-get update  \
    && apt-get -y install sudo

# APCu cache
RUN pecl install apcu
RUN docker-php-ext-enable apcu

# Redis
RUN pecl install redis
RUN docker-php-ext-enable redis

FROM extensions as langs

# Setup gettext languages
RUN apt-get update  \
    && apt-get install -y locales \
	&& sed -i -e 's/# cs_CZ.UTF-8 UTF-8/cs_CZ.UTF-8 UTF-8/' /etc/locale.gen \
	&& sed -i -e 's/# en_US.UTF-8 UTF-8/en_US.UTF-8 UTF-8/' /etc/locale.gen \
	&& sed -i -e 's/# de_DE.UTF-8 UTF-8/de_DE.UTF-8 UTF-8/' /etc/locale.gen \
	&& sed -i -e 's/# fr_FR.UTF-8 UTF-8/fr_FR.UTF-8 UTF-8/' /etc/locale.gen \
	&& sed -i -e 's/# sk_SK.UTF-8 UTF-8/sk_SK.UTF-8 UTF-8/' /etc/locale.gen \
	&& sed -i -e 's/# ru_RU.UTF-8 UTF-8/ru_RU.UTF-8 UTF-8/' /etc/locale.gen \
    && dpkg-reconfigure --frontend=noninteractive locales
RUN locale-gen cs_CZ.UTF-8
RUN update-locale -y

FROM langs as composer

# Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php -r "if (hash_file('sha384', 'composer-setup.php') === 'edb40769019ccf227279e3bdd1f5b2e9950eb000c3233ee85148944e555d97be3ea4f40c3c2fe73b22f875385f6a5155') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
RUN php composer-setup.php
RUN rm composer-setup.php
RUN mv composer.phar /usr/local/bin/composer

FROM composer as base

# Project files
RUN mkdir -p "/var/www/.npm" && chown -R 33:33 "/var/www/.npm"
RUN apt-get -y install sudo
RUN echo "www-data:pass" | chpasswd && adduser www-data sudo
RUN cron
#USER www-data

# Inkscape
RUN apt-get update
RUN apt-get install -y ca-certificates apt-transport-https software-properties-common lsb-release
RUN apt-get install -y snap
RUN snap install -y inkscape

# Move to project directory
WORKDIR /var/www/

# Initialize git and download project
RUN git init
RUN mkdir -p modules
RUN git remote add origin https://github.com/Heroyt/LaserLiga.git
RUN git fetch --all --tags
RUN git checkout -t origin/master
RUN git config pull.ff only --autostash
RUN git submodule init

# Initialize all configs and create necessary directories
RUN git pull --recurse-submodules=yes && \
    git submodule update --init --recursive --remote && \
    mv private/docker-config.ini private/config.ini && \
    mkdir -p logs && \
    mkdir -p temp && \
    mkdir -p lmx && \
    mkdir -p lmx/results && \
    mkdir -p lmx/games

# Copy shell scripts
COPY start.sh start.sh

# Initialize crontab
RUN crontab -l | { cat; echo "* * * * * /usr/local/bin/php /var/www/bin/scheduler.php >> /var/www/logs/cron.log 2>&1"; } | crontab -

# Install
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN chmod 0777 -R logs
RUN chmod 0777 -R temp

# Start command
# Updates project, builds it and runs a start script which starts WS event server and Apache
CMD sh ./start.sh