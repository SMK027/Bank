#!/bin/bash
set -e

# ── Exporter les variables d'environnement pour cron ──────────────────────
printenv | grep -E '^(APP_|DB_)' >> /etc/environment

# ── Attendre que MariaDB soit disponible ──────────────────────────────────
echo "Attente de MariaDB (${DB_HOST:-db}:${DB_PORT:-3306})…"
MAX_TRIES=30
i=0
until mysql -h"${DB_HOST:-db}" -P"${DB_PORT:-3306}" \
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
