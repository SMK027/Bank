#!/bin/bash
set -e

# ── Variables d'environnement pour cron ───────────────────────────────────
# Le daemon cron ne hérite pas de l'environnement du container Docker.
# PAM (/etc/environment) est absent ou non configuré dans les images Docker
# minimales — on génère un fichier source explicite pour chaque job cron.
#
# /etc/environment : conservé pour compatibilité (remplacé à chaque démarrage)
printenv | grep -E '^(APP_|DB_|MAIL_)' > /etc/environment

# /etc/cron.env : sourceé explicitement par chaque job cron (format bash export)
{
    while IFS= read -r var; do
        printf 'export %s=%q\n' "${var%%=*}" "${var#*=}"
    done < <(printenv | grep -E '^(APP_|DB_|MAIL_)')
} > /etc/cron.env
chmod 644 /etc/cron.env

# ── Attendre que MariaDB soit disponible ──────────────────────────────────
echo "Attente de MariaDB (${DB_HOST:-db}:${DB_PORT:-3306})…"
MAX_TRIES=60
i=0
until mysql -h"${DB_HOST:-db}" -P"${DB_PORT:-3306}" \
            --ssl=FALSE \
            -u"${DB_USERNAME:-bankapp}" -p"${DB_PASSWORD:-bankapp_secret}" \
            "${DB_DATABASE:-bankapp}" -e "SELECT 1" >/dev/null 2>&1; do
    i=$((i+1))
    if [ $i -ge $MAX_TRIES ]; then
        echo "ERREUR : MariaDB inaccessible après ${MAX_TRIES} tentatives." >&2
        exit 1
    fi
    sleep 2
done
echo "MariaDB prêt."

# ── Appliquer les migrations ───────────────────────────────────────────────
php /var/www/html/database/migrate.php

# ── Démarrer le daemon cron (transactions programmées) ────────────────────
cron

exec apache2-foreground
