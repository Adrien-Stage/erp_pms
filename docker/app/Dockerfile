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

# ── Dépendances PHP, installées DANS l'image ──────────────────────────────────
# C'est le cœur du correctif de démarrage à froid. Le code du projet est monté
# depuis Windows ; sur ce pont, lire un fichier coûte ~34 ms contre ~0,04 ms sur
# le disque du conteneur (mesuré sur 800 fichiers : 27,13 s contre 0,035 s, soit
# ×775). Avec 10 262 fichiers PHP dans vendor/, la première requête passait
# plusieurs minutes à lire le framework à travers le mount.
#
# En construisant vendor/ dans l'image, ces fichiers vivent sur le disque du
# conteneur. Le docker-compose les y maintient via un volume nommé qui recouvre
# le bind mount (voir docker-compose.yml).
#
# Les dépendances de développement sont conservées : la suite Pest tourne dans
# ce conteneur. --no-scripts évite d'exécuter artisan avant que le code ne soit
# copié ; l'autoloader est régénéré plus bas.
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-interaction

# node_modules/ suit la même logique. Il ne sert pas à php-fpm, donc n'entre pas
# dans le chargement de la page de login : ce qu'il accélère, c'est `npm run
# build`, dont le rescan Tailwind à travers le mount était le poste le plus lent.
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY docker/app/nginx.conf /etc/nginx/sites-available/default
# Chargé en dernier (préfixe zz-) pour surcharger docker-php-ext-opcache.ini.
COPY docker/app/php-opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/app/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/app/php-fpm-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf
COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/app/warmup.sh /usr/local/bin/warmup.sh
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/warmup.sh

# ── Code applicatif ───────────────────────────────────────────────────────────
# Copié en dernier : c'est ce qui change le plus souvent, les couches de
# dépendances ci-dessus restent ainsi en cache d'un build à l'autre.
#
# En développement, le bind mount du docker-compose recouvre ce code — les
# éditions restent donc immédiates. Seuls vendor/ et node_modules/ continuent
# d'être servis par l'image. L'image reste néanmoins autonome, ce qui permet de
# la lancer sans le dépôt.
COPY . .

# Arborescence d'exécution : .dockerignore exclut le contenu de storage/, ces
# répertoires doivent donc être recréés pour que l'image tienne debout seule.
RUN mkdir -p storage/framework/views storage/framework/cache/data \
             storage/framework/sessions storage/logs \
             storage/app/private/backups bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

# Autoloader optimisé (classmap). Pas de --classmap-authoritative : en
# développement, une classe nouvellement créée doit être trouvée sans avoir à
# relancer composer.
RUN composer dump-autoload --optimize --no-scripts --no-interaction

# Empreinte des verrous au moment du build. Les volumes de dépendances en
# héritent ; l'entrypoint la compare aux verrous montés depuis l'hôte et
# signale un volume devenu obsolète après un composer/npm install.
RUN md5sum composer.lock     | cut -d' ' -f1 > vendor/.lock-stamp \
 && md5sum package-lock.json | cut -d' ' -f1 > node_modules/.lock-stamp

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n"]
