#!/bin/bash
set -e

# Corriger les permissions du dossier data (nécessaire avec les volumes montés)
chown -R www-data:www-data /var/www/html/data 2>/dev/null || true
chmod -R 777 /var/www/html/data 2>/dev/null || true

exec apache2-foreground
