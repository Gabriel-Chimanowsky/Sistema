FROM php:8.2-apache

# Instalar extensões necessárias
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libc-client-dev \
    libkrb5-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Configurar IMAP
RUN docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install imap

# Instalar extensões PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql zip

# Habilitar mod_rewrite do Apache
RUN a2enmod rewrite

# Copiar arquivos do projeto
COPY . /var/www/html/

# Ajustar permissões
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
