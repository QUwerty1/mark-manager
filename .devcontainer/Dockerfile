FROM php:7.4.33-apache-bullseye

# Установка переменных окружения
ENV DEBIAN_FRONTEND=noninteractive

# Установка системных зависимостей
RUN apt-get update && apt-get install -y \
    git \
    nano \
    cron \
    curl \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    libpq-dev \
    libldap2-dev \
    libxslt1-dev \
    postgresql-client \
    zsh \
    sudo \
    && rm -rf /var/lib/apt/lists/*

# Настройка расширений PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    gd \
    intl \
    soap \
    zip \
    xmlrpc \
    pgsql \
    pdo_pgsql \
    ldap \
    xsl \
    opcache

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Настройка Apache
RUN a2enmod rewrite headers \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Создание пользователя vscode
ARG USERNAME=vscode
ARG USER_UID=1000
ARG USER_GID=$USER_UID

RUN groupadd --gid $USER_GID $USERNAME \
    && useradd --uid $USER_UID --gid $USER_GID -m $USERNAME -s /bin/zsh \
    && echo "$USERNAME ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/$USERNAME \
    && chmod 0440 /etc/sudoers.d/$USERNAME

# Установка Oh My Zsh для vscode
USER $USERNAME
RUN sh -c "$(curl -fsSL https://raw.github.com/ohmyzsh/ohmyzsh/master/tools/install.sh)" "" --unattended
USER root

# Создание директорий
RUN mkdir -p /var/www/moodledata \
    && chown -R $USERNAME:$USERNAME /var/www/moodledata \
    && mkdir -p /workspace \
    && chown -R $USERNAME:$USERNAME /workspace

WORKDIR /workspace

EXPOSE 80

CMD ["apache2-foreground"]