<#
.SYNOPSIS
    Recharge wetchah_erp apres une modification de code.

.DESCRIPTION
    Deux reglages conditionnent la vitesse de cette application, tous deux lies
    au fait que le code est monte depuis Windows (bind mount), ou un simple
    stat() coute ~4,3 ms contre ~0,005 ms sur le disque du conteneur.

    1. OPcache tourne avec validate_timestamps=0 (docker/app/php-opcache.ini).
       Sans ce reglage, PHP revalidait ~950 fichiers par requete, soit ~4 s.
       En contrepartie, aucune modification n'est visible tant que PHP-FPM n'a
       pas redemarre : ni le code PHP, ni les vues Blade (recompilees au meme
       chemin, donc OPcache sert l'ancien opcode).

    2. Les assets sont servis compiles par nginx depuis public/build/, et non
       par le dev server Vite. En mode dev, Tailwind rescanne les sources a
       travers le mount a chaque invalidation : le CSS mettait jusqu'a 95 s a
       etre servi, ce qui bloquait l'affichage de la page.

.PARAMETER Assets
    Recompile aussi les assets (npm run build, ~2 min). Necessaire uniquement
    apres avoir ajoute des classes Tailwind ou modifie du CSS/JS.

.EXAMPLE
    .\dev-refresh.ps1
    Recharge le code PHP et les vues.

.EXAMPLE
    .\dev-refresh.ps1 -Assets
    Recharge le code et recompile les assets.
#>

param([switch]$Assets)

$container = 'wetchah_erp-app'
$root      = $PSScriptRoot

$running = docker ps --filter "name=$container" --format '{{.Names}}'
if ($running -ne $container) {
    Write-Host "Le conteneur $container ne tourne pas." -ForegroundColor Red
    exit 1
}

# Le dev server Vite reecrit public/hot a son demarrage, ce qui rebascule
# l'application en mode dev (et donc en lenteur). On le signale.
if (Test-Path (Join-Path $root 'public\hot')) {
    Write-Host 'public/hot est present : les assets repassent par le dev server Vite (lent).' -ForegroundColor Yellow
    Write-Host 'Pour revenir aux assets compiles : arreter pms-vite puis supprimer public/hot.' -ForegroundColor Yellow
}

if ($Assets) {
    Write-Host 'Compilation des assets (environ 2 minutes)...' -ForegroundColor Cyan
    docker exec pms-vite sh -lc 'cd /var/www/html && npm run build'
    if (-not $?) { Write-Host 'Echec de la compilation des assets.' -ForegroundColor Red; exit 1 }
}

Write-Host 'Vidage des caches Laravel...' -ForegroundColor Cyan
docker exec $container php artisan view:clear   | Out-Null
docker exec $container php artisan route:clear  | Out-Null
docker exec $container php artisan config:clear | Out-Null

Write-Host 'Rechargement de PHP-FPM (reset OPcache)...' -ForegroundColor Cyan
docker exec $container supervisorctl restart php-fpm | Out-Null

# La premiere requete apres un redemarrage recompile ~950 fichiers depuis le
# mount Windows. On absorbe ce cout ici plutot que de le faire subir a la
# premiere navigation.
Write-Host 'Prechauffage du cache...' -ForegroundColor Cyan
$sw = [Diagnostics.Stopwatch]::StartNew()
docker exec $container curl -s -o /dev/null http://localhost/login | Out-Null
$sw.Stop()

Write-Host ("Termine. Prechauffage en {0:N1} s ; les pages repondent en ~0,2 s." -f $sw.Elapsed.TotalSeconds) -ForegroundColor Green

