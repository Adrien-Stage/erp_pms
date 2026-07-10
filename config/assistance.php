<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Secret de signature des jetons d'assistance
    |--------------------------------------------------------------------------
    | Partagé entre pms (qui signe le jeton d'entrée) et chaque application
    | établissement (meka_template, qui le vérifie sur /assistance/enter).
    | Injecté dans les containers des établissements au provisioning via la
    | variable d'environnement ASSISTANCE_SECRET (voir
    | TenantProvisioningService). À définir dans le .env de pms.
    */
    'secret' => env('ASSISTANCE_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Durée de vie d'une session d'assistance (minutes)
    |--------------------------------------------------------------------------
    */
    'ttl_minutes' => (int) env('ASSISTANCE_TTL_MINUTES', 30),

];
