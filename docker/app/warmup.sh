#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
# WeTchah ERP — Préchauffage du cache d'opcode
#
# OPcache tourne avec validate_timestamps=0 : une fois un fichier compilé, PHP
# ne retouche plus au disque. La contrepartie est que la TOUTE PREMIÈRE requête
# après un démarrage doit lire et compiler l'ensemble du code — et c'est elle
# qui payait les 4 à 5 minutes d'attente sur la page de login.
#
# Ce script déclenche cette requête depuis le conteneur, dès que nginx écoute.
# Le coût est donc absorbé pendant le `docker compose up`, et non par la
# première navigation de l'utilisateur.
#
# Lancé par supervisord en autorestart=false : il s'exécute une fois, puis rend
# la main.
# ─────────────────────────────────────────────────────────────────────────────
set -u

# ── Attendre que nginx accepte les connexions ────────────────────────────────
# On teste le port sans requêter PHP : une requête ici déclencherait la
# compilation avant même que php-fpm ne soit prêt à la servir.
for _ in $(seq 1 60); do
    if (echo > /dev/tcp/127.0.0.1/80) 2>/dev/null; then
        break
    fi
    sleep 1
done

if ! (echo > /dev/tcp/127.0.0.1/80) 2>/dev/null; then
    echo "⚠️  Préchauffage abandonné : nginx n'écoute pas après 60 s."
    exit 0
fi

# ── Requête de préchauffage ───────────────────────────────────────────────────
# --max-time large : sur un tout premier démarrage (volumes de dépendances à
# peine créés), la compilation peut légitimement durer.
echo "🔥 Préchauffage du cache d'opcode..."
debut=$(date +%s)

if curl -fsS -o /dev/null --max-time 600 http://localhost/login 2>/dev/null; then
    echo "✅ Application prête en $(( $(date +%s) - debut )) s — /login répond."
else
    echo "⚠️  Préchauffage : /login n'a pas répondu (voir storage/logs/laravel.log)."
fi

exit 0
