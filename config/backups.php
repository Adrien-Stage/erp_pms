<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chemin hôte du dossier des sauvegardes
    |--------------------------------------------------------------------------
    | Le storage du container pms est monté depuis la machine hôte : ce chemin
    | est l'emplacement du dossier backups tel que visible depuis le PC
    | (ex. C:\Users\user\Herd\pms\storage\app\private\backups). Utilisé
    | uniquement pour l'affichage dans les messages de confirmation — laisser
    | vide pour afficher le chemin interne au container.
    */
    'host_path' => env('BACKUPS_HOST_PATH', ''),

];
