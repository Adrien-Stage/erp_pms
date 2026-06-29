<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chemin de base des tenants sur l'hôte
    |--------------------------------------------------------------------------
    | Chemin ABSOLU sur la machine hôte (pas dans le container) où HotelixOS
    | stocke le code source cloné de chaque établissement.
    |
    | Windows/WSL2 exemple : /c/Users/user/Herd/tenants
    | Linux exemple         : /home/ubuntu/hotelixos/tenants
    |
    | Ce chemin est aussi utilisé pour monter les volumes Docker.
    */
    'tenants_base_path' => env('TENANTS_BASE_PATH', '/var/hotelixos/tenants'),

    /*
    |--------------------------------------------------------------------------
    | URL du dépôt GitHub template
    |--------------------------------------------------------------------------
    | Dépôt cloné lorsque source_type = 'github'.
    | Peut être un repo privé si les credentials Git sont configurés sur l'hôte.
    */
    'template_github_url' => env('TEMPLATE_GITHUB_URL', 'https://github.com/Adrien-Stage/villa_b.git'),

    /*
    |--------------------------------------------------------------------------
    | Chemin de repli local (fallback)
    |--------------------------------------------------------------------------
    | Si le clone GitHub échoue (dépôt privé sans credentials, hors-ligne...),
    | le provisioning copie le template depuis ce dossier hôte local.
    | Chemin ABSOLU sur la machine hôte (pas dans le container).
    | Windows/WSL2 ex : /c/Users/user/Herd/villab
    */
    'local_fallback_path' => env('LOCAL_FALLBACK_PATH', '/c/Users/user/Herd/villab'),

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

];