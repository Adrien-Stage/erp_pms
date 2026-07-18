<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chemin de base des tenants sur l'hôte
    |--------------------------------------------------------------------------
    | Chemin ABSOLU sur la machine hôte (pas dans le container). Utilisé pour
    | stocker les docker-compose générés par établissement (dossier .compose).
    |
    | Windows/WSL2 exemple : /c/Users/user/Herd/tenants
    | Linux exemple         : /home/ubuntu/meka-erp/tenants
    */
    'tenants_base_path' => env('TENANTS_BASE_PATH', '/var/meka-erp/tenants'),

    /*
    |--------------------------------------------------------------------------
    | Image Docker du template applicatif (registre)
    |--------------------------------------------------------------------------
    | Image publiée automatiquement par la CI du repo villa_b (GitHub Actions
    | -> GHCR). Package public : aucune authentification requise pour le pull.
    | Le provisioning épingle chaque établissement sur un digest précis
    | (voir Tenant::docker_image_tag) plutôt que de suivre "latest" en continu.
    */
    'registry_image' => env('REGISTRY_IMAGE', 'ghcr.io/adrien-stage/villa_b'),

    /*
    |--------------------------------------------------------------------------
    | Image Docker du site vitrine (registre)
    |--------------------------------------------------------------------------
    | Image publiée par la CI du repo site_villab (template SvelteKit) -> GHCR.
    | Provisionnée en 3e container ("web") uniquement pour les établissements
    | avec le module "website" actif — même principe de pin par digest que
    | registry_image.
    */
    'registry_image_web' => env('REGISTRY_IMAGE_WEB', 'ghcr.io/clyde237/site_villab'),

    /*
    |--------------------------------------------------------------------------
    | Container admin (pms) sur le réseau Docker partagé
    |--------------------------------------------------------------------------
    | Nom du container applicatif de l'admin lui-même (voir docker-compose.yml
    | à la racine, service "app") — utilisé pour injecter CMS_API_URL dans le
    | container "web" de chaque établissement, qui doit consommer l'API de
    | contenu marketing depuis le réseau Docker interne.
    */
    'cms_container' => env('CMS_CONTAINER_NAME', 'MEKA_ERP-app'),

    /*
    |--------------------------------------------------------------------------
    | Secret de service de l'API de reporting business
    |--------------------------------------------------------------------------
    | Jeton bearer partagé avec chaque établissement (injecté dans les
    | containers au provisioning). La console business de pms l'utilise pour
    | consommer l'API financière (/api/reporting/*) de chaque tenant.
    */
    'reporting_secret' => env('REPORTING_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Réseau Docker partagé
    |--------------------------------------------------------------------------
    | Tous les containers (admin + établissements) sont sur ce réseau.
    | Le réseau est créé automatiquement s'il n'existe pas.
    */
    'docker_network' => env('DOCKER_NETWORK', 'pms'),

    /*
    |--------------------------------------------------------------------------
    | PostgreSQL Admin (pour créer les bases de données)
    |--------------------------------------------------------------------------
    | Connexion au PostgreSQL vu depuis le container admin.
    | En production, c'est le container "db" du projet admin.
    | En développement local, peut être 127.0.0.1:5433.
    */
    'postgres' => [
        'host' => env('POSTGRES_ADMIN_HOST', 'db'),
        'port' => env('POSTGRES_ADMIN_PORT', '5432'),
        'user' => env('POSTGRES_ADMIN_USER', 'pms'),
        'pass' => env('POSTGRES_ADMIN_PASS', 'secret'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Plage de ports automatiques
    |--------------------------------------------------------------------------
    | Utilisée pour suggérer le prochain port disponible dans le formulaire.
    | Le service de provisioning n'utilise pas cette config — le port est
    | choisi par l'admin TECH dans le formulaire.
    */
    'port_range' => [
        'app_start' => (int) env('PORT_RANGE_APP_START', 8081),
        'db_start'  => (int) env('PORT_RANGE_DB_START',  5434),
    ],

    /*
    |--------------------------------------------------------------------------
    | Téléchargement des images Docker (résilience réseau)
    |--------------------------------------------------------------------------
    | pull_stall_timeout : nombre de secondes sans la moindre progression au-delà
    |   duquel un `docker pull` est considéré bloqué (connexion instable), tué,
    |   puis relancé — Docker reprend les couches déjà téléchargées.
    | pull_max_seconds : durée maximale d'une seule tentative de pull, garde-fou
    |   au cas où le transfert « avancerait » sans jamais aboutir.
    */
    'pull_stall_timeout' => (int) env('PULL_STALL_TIMEOUT', 120),
    'pull_max_seconds'   => (int) env('PULL_MAX_SECONDS', 900),

];