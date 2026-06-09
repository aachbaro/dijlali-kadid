#!/bin/bash
# deploy.sh — À lancer sur le home server après migrate-from-local.ps1
# Usage : bash docker/deploy.sh

set -e
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

ENV_FILE=".env.prod"
EXPORT_DIR="./export"

# ── Vérifications ──────────────────────────────────────────
if [ ! -f "$ENV_FILE" ]; then
  echo "ERREUR : $ENV_FILE introuvable."
  echo "Copie .env.example → .env.prod et remplis les valeurs."
  exit 1
fi

echo ""
echo "[1/5] Démarrage MySQL..."
docker compose -f docker-compose.prod.yml --env-file $ENV_FILE up -d db
echo "  Attente que MySQL soit prêt..."
sleep 15

# ── Import base de données ─────────────────────────────────
if [ -f "$EXPORT_DIR/dump.sql" ]; then
  echo ""
  echo "[2/5] Import de la base de données..."
  source $ENV_FILE
  docker compose -f docker-compose.prod.yml --env-file $ENV_FILE exec -T db \
    mysql -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < "$EXPORT_DIR/dump.sql"
  echo "  OK : base importée"
else
  echo "[2/5] Pas de dump.sql trouvé, skip import DB"
fi

# ── Démarrage WordPress ────────────────────────────────────
echo ""
echo "[3/5] Démarrage WordPress..."
docker compose -f docker-compose.prod.yml --env-file $ENV_FILE up -d wordpress
sleep 5

# ── Copie wp-content ───────────────────────────────────────
if [ -f "$EXPORT_DIR/wp-content.tar.gz" ]; then
  echo ""
  echo "[4/5] Restauration wp-content (thèmes, plugins, uploads)..."
  WP_CONTAINER=$(docker compose -f docker-compose.prod.yml --env-file $ENV_FILE ps -q wordpress)
  tar -xzf "$EXPORT_DIR/wp-content.tar.gz" -C /tmp/
  docker cp /tmp/wp-content/. "$WP_CONTAINER:/var/www/html/wp-content/"
  docker compose -f docker-compose.prod.yml --env-file $ENV_FILE exec wordpress \
    chown -R www-data:www-data /var/www/html/wp-content
  echo "  OK : wp-content restauré"
fi

# ── Démarrage complet ──────────────────────────────────────
echo ""
echo "[5/5] Démarrage nginx + cloudflared..."
docker compose -f docker-compose.prod.yml --env-file $ENV_FILE up -d
echo ""
echo "=== Déployé ! ==="
echo "Vérifie : docker compose -f docker-compose.prod.yml --env-file $ENV_FILE ps"
echo "Logs    : docker compose -f docker-compose.prod.yml --env-file $ENV_FILE logs -f"
