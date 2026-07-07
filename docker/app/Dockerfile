FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    nginx \
    supervisor \
    git \
    unzip \
    curl \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    && docker-php-ext-install pdo_pgsql zip gd mbstring \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ── Docker CLI + Compose plugin ───────────────────────────────────────────────
# Binaires pré-téléchargés sur l'hôte (dans docker/app/bin/) pour éviter
# les problèmes réseau pendant le build Docker.
COPY docker/app/bin/docker.tgz /tmp/docker.tgz
RUN tar -xzf /tmp/docker.tgz -C /tmp && \
    mv /tmp/docker/docker /usr/local/bin/docker && \
    rm -rf /tmp/docker /tmp/docker.tgz

# Docker Compose plugin (v2) — requis pour `docker compose up`
RUN mkdir -p /usr/local/lib/docker/cli-plugins
COPY docker/app/bin/docker-compose /usr/local/lib/docker/cli-plugins/docker-compose
RUN chmod +x /usr/local/lib/docker/cli-plugins/docker-compose

# ── Groupe docker pour www-data ───────────────────────────────────────────────
# Crée un groupe 'docker' et y ajoute www-data pour l'accès au socket.
RUN groupadd -f docker && usermod -aG docker www-data

RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY docker/app/nginx.conf /etc/nginx/sites-available/default
COPY docker/app/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/app/php-fpm-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf
COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n"]
