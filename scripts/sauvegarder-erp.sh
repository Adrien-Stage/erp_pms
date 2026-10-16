#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
# WeTchah ERP — sauvegarde de la console sur un serveur.
#
# Écrit dans $DESTINATION (défaut /srv/sauvegardes), horodaté :
#   console-AAAA-MM-JJ_HHMM.sqlite  copie cohérente de la base de la console
#   console-storage-…tgz            journaux + sauvegardes des établissements
#                                   (dumps faits par le menu « Sauvegardes »)
#   tenants-…tgz                    les fichiers Compose des établissements
#                                   (clés et mots de passe de chaque hôtel)
# Garde les $CONSERVER dernières copies de chaque sorte (défaut 14).
#
# Ce script protège d'une fausse manipulation. Il n'est PAS une sauvegarde tant
# que $DESTINATION reste sur le serveur : copier ensuite ce dossier ailleurs.
#
# Usage :  sudo ./scripts/sauvegarder-erp.sh
# Planification (sudo crontab -e) :
#   30 2 * * *  /opt/wetchah/wetchah_erp/scripts/sauvegarder-erp.sh >> /var/log/sauvegarde-erp.log 2>&1
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

CONTENEUR="${CONTENEUR:-wetchah_erp-app}"
VOLUME="${VOLUME:-wetchah-erp_erp_storage}"
TENANTS_BASE_PATH="${TENANTS_BASE_PATH:-/var/wetchah/tenants}"
DESTINATION="${DESTINATION:-/srv/sauvegardes}"
CONSERVER="${CONSERVER:-14}"

horodatage="$(date +%F_%H%M)"
mkdir -p "$DESTINATION"

echo "[$(date '+%F %T')] Sauvegarde de $CONTENEUR vers $DESTINATION"

# 1. Base de la console : VACUUM INTO donne une copie cohérente sans arrêter la console.
interne="/var/www/html/storage/app/private/sauvegarde-$horodatage.sqlite"
docker exec -u www-data "$CONTENEUR" php -r '
    $p = new PDO("sqlite:/var/www/html/storage/database/database.sqlite");
    $p->exec("VACUUM INTO \"" . $argv[1] . "\"");
' "$interne"
docker cp "$CONTENEUR:$interne" "$DESTINATION/console-$horodatage.sqlite"
docker exec "$CONTENEUR" rm -f "$interne"
echo "  base de la console : console-$horodatage.sqlite"

# 2. Volume storage (journaux, dumps des établissements).
docker run --rm -v "$VOLUME":/donnees:ro -v "$DESTINATION":/sortie alpine sh -c \
    "tar czf /sortie/console-storage-$horodatage.tgz -C /donnees . && chown $(id -u):$(id -g) /sortie/console-storage-$horodatage.tgz"
echo "  volume storage     : console-storage-$horodatage.tgz"

# 3. Fichiers Compose des établissements (contiennent leurs clés et secrets).
tar czf "$DESTINATION/tenants-$horodatage.tgz" -C "$(dirname "$TENANTS_BASE_PATH")" "$(basename "$TENANTS_BASE_PATH")"
echo "  dossier des hôtels : tenants-$horodatage.tgz"

chmod 600 "$DESTINATION"/*-"$horodatage".*

# 4. Rotation : ne garder que les $CONSERVER plus récentes de chaque sorte.
for motif in 'console-2*.sqlite' 'console-storage-*.tgz' 'tenants-*.tgz'; do
    ls -1t "$DESTINATION"/$motif 2>/dev/null | tail -n +"$((CONSERVER + 1))" | xargs -r rm -f --
done

echo "[$(date '+%F %T')] Terminé."
