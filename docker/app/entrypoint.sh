#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
# WeTchah ERP — Entrypoint du conteneur admin
# Ce script fixe les permissions du socket Docker avant de lancer supervisord.
# ─────────────────────────────────────────────────────────────────────────────
set -e

DOCKER_SOCK="/var/run/docker.sock"

if [ -S "$DOCKER_SOCK" ]; then
    echo "🔧 Ajustement des permissions du socket Docker..."

    # Récupérer le GID du socket monté depuis l'hôte
    SOCK_GID=$(stat -c '%g' "$DOCKER_SOCK")

    # Si le GID du socket ne correspond pas au groupe docker interne,
    # on ajuste le groupe docker pour correspondre au GID de l'hôte.
    DOCKER_GID=$(getent group docker | cut -d: -f3)

    if [ "$SOCK_GID" != "$DOCKER_GID" ]; then
        # Vérifier si un groupe avec ce GID existe déjà
        EXISTING_GROUP=$(getent group "$SOCK_GID" | cut -d: -f1 || true)
        if [ -n "$EXISTING_GROUP" ] && [ "$EXISTING_GROUP" != "docker" ]; then
            usermod -aG "$EXISTING_GROUP" www-data
        else
            groupmod -g "$SOCK_GID" docker 2>/dev/null || true
        fi
    fi

    # Garantir que www-data peut accéder au socket
    chmod 666 "$DOCKER_SOCK" 2>/dev/null || true

    echo "✅ Socket Docker accessible par www-data (GID: $SOCK_GID)"
else
    echo "⚠️  Socket Docker non trouvé — les commandes Docker ne fonctionneront pas."
fi

# ── Permissions storage / cache ───────────────────────────────────────────────
# php-fpm et le scheduler tournent en www-data : garantir qu'ils peuvent
# écrire logs, cache et sauvegardes (storage/app/private/backups), même après
# un build où les fichiers sont copiés en root.
echo "🔧 Ajustement des permissions storage/ et bootstrap/cache/..."
mkdir -p /var/www/html/storage/app/private/backups
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# ── Fraîcheur des volumes de dépendances ─────────────────────────────────────
# vendor/ et node_modules/ viennent de l'image via un volume nommé, et non du
# bind mount : c'est ce qui évite de lire 10 000 fichiers à ~34 ms pièce au
# démarrage. Revers de la médaille, un `composer require` lancé sur l'hôte ne
# les met pas à jour. On compare donc l'empreinte figée au build aux verrous
# réellement montés, et on le dit franchement plutôt que de laisser tourner
# l'application sur des dépendances qui ne sont plus les bonnes.
verifier_deps() {
    nom="$1"; verrou="$2"; repertoire="$3"

    [ -f "$verrou" ] || return 0

    if [ ! -f "$repertoire/.lock-stamp" ]; then
        echo "⚠️  $nom : volume sans empreinte — reconstruire l'image."
        return 0
    fi

    if [ "$(cat "$repertoire/.lock-stamp")" != "$(md5sum "$verrou" | cut -d' ' -f1)" ]; then
        echo "⚠️  $nom : le volume ne correspond plus à $(basename "$verrou")."
        echo "    Les dépendances servies datent d'un build précédent."
        echo "    Corriger avec :  .\\dev-refresh.ps1 -Deps"
    fi
}

verifier_deps "vendor"       /var/www/html/composer.lock     /var/www/html/vendor
verifier_deps "node_modules" /var/www/html/package-lock.json /var/www/html/node_modules

# Exécuter la commande passée (CMD du Dockerfile = supervisord)
exec "$@"
