#!/usr/bin/env bash
# ============================================================
# setup-local.sh — Installation pour Local by Flywheel
#
# Différences avec setup.sh :
#   - WordPress déjà installé par Local → on saute download/install
#   - Base de données créée par Local (local/root/root)
#   - Valeurs depuis .env.local (pas .env)
#   - URLs en http:// (pas de HTTPS en local)
#
# Usage :
#   cd ~/Local\ Sites/art-sell/app/public
#   bash setup/setup-local.sh
# ============================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/.env.local"

# ── Chargement du .env.local ───────────────────────────────
if [[ ! -f "$ENV_FILE" ]]; then
    echo "Erreur : $ENV_FILE introuvable."
    echo "  Copiez setup/.env.local et remplissez vos clés Stripe test."
    exit 1
fi

set -a
# shellcheck disable=SC1091
source "$ENV_FILE"
set +a

# Valeurs par défaut pour Local by Flywheel
WP_HOME="${WP_HOME:-http://art-sell.local}"
DB_NAME="${DB_NAME:-local}"
DB_USER="${DB_USER:-root}"
DB_PASSWORD="${DB_PASSWORD:-root}"
DB_HOST="${DB_HOST:-localhost}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-admin123}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@test.local}"
SITE_TITLE="${SITE_TITLE:-Galerie Djilali Kadid (DEV)}"
TABLE_PREFIX="${TABLE_PREFIX:-art_}"

echo "========================================================"
echo "  Setup LOCAL — Galerie d'art"
echo "  URL : $WP_HOME"
echo "  (WordPress deja installe par Local by Flywheel)"
echo "========================================================"

# ── Verification : sommes-nous dans le bon dossier ? ──────
if [[ ! -f "wp-load.php" ]]; then
    echo "Erreur : ce script doit etre lance depuis la racine WordPress."
    echo "  cd ~/Local Sites/art-sell/app/public"
    echo "  bash setup/setup-local.sh"
    exit 1
fi

# ── WordPress deja installe ? ──────────────────────────────
if wp core is-installed 2>/dev/null; then
    echo "> WordPress deja installe — mise a jour du titre et des reglages..."
    wp option update blogname "$SITE_TITLE"
    wp option update siteurl "$WP_HOME"
    wp option update home    "$WP_HOME"
else
    echo "> Installation de WordPress (Local a cree la DB, on configure WP)..."
    wp config set DB_NAME     "$DB_NAME"
    wp config set DB_USER     "$DB_USER"
    wp config set DB_PASSWORD "$DB_PASSWORD"
    wp config set DB_HOST     "$DB_HOST"
    wp config set table_prefix "$TABLE_PREFIX" --type=variable
    wp core install \
        --url="$WP_HOME" \
        --title="$SITE_TITLE" \
        --admin_user="$ADMIN_USER" \
        --admin_password="$ADMIN_PASSWORD" \
        --admin_email="$ADMIN_EMAIL" \
        --skip-email
fi

# ── Constantes wp-config (debug local ON) ─────────────────
wp config set WP_DEBUG         true  --type=constant --raw
wp config set WP_DEBUG_LOG     true  --type=constant --raw
wp config set WP_DEBUG_DISPLAY false --type=constant --raw
wp config set DISALLOW_FILE_EDIT true --type=constant --raw
wp config set WP_POST_REVISIONS 5    --type=constant --raw
wp config set WP_MEMORY_LIMIT '256M' --type=constant

echo "> wp-config configure (debug ON pour le dev)"

# ── Suppression plugins par defaut ────────────────────────
echo "> Suppression des plugins par defaut..."
wp plugin delete hello akismet 2>/dev/null || true

# ── Installation des plugins ──────────────────────────────
echo "> Installation des plugins..."
PLUGINS=(
    "kadence-blocks"
    "woocommerce"
    "woo-stripe-payment"
    "polylang"
    "ameliabooking"
    "seo-by-rank-math"
    "woocommerce-pdf-invoices-packing-slips"
    "wordfence"
    "wp-super-cache"
    "wp-mail-smtp"
)

for plugin in "${PLUGINS[@]}"; do
    if wp plugin is-installed "$plugin" 2>/dev/null; then
        echo "  • $plugin (deja installe, activation...)"
        wp plugin activate "$plugin"
    else
        echo "  • $plugin"
        wp plugin install "$plugin" --activate
    fi
done

# ── Theme Kadence ──────────────────────────────────────────
echo "> Theme Kadence..."
if ! wp theme is-installed kadence 2>/dev/null; then
    wp theme install kadence --activate
else
    wp theme activate kadence
fi

# ── Theme enfant ───────────────────────────────────────────
echo "> Theme enfant kadence-child..."
THEMES_DIR="$(wp eval 'echo get_theme_root();')"
if [[ -d "$THEMES_DIR/kadence-child" ]]; then
    echo "  Theme enfant deja present, mise a jour des fichiers..."
    cp -rf "$SCRIPT_DIR/kadence-child/." "$THEMES_DIR/kadence-child/"
else
    cp -r "$SCRIPT_DIR/kadence-child" "$THEMES_DIR/"
fi
wp theme activate kadence-child

# ── Pages de base ──────────────────────────────────────────
echo "> Creation des pages..."

create_page_if_missing() {
    local title="$1"
    local slug="$2"
    if wp post list --post_type=page --post_status=publish --name="$slug" --format=ids | grep -q '[0-9]'; then
        existing_id=$(wp post list --post_type=page --post_status=publish --name="$slug" --format=ids)
        echo "  • Page '$title' existe (ID: $existing_id)"
        echo "$existing_id"
    else
        wp post create \
            --post_type=page \
            --post_title="$title" \
            --post_name="$slug" \
            --post_status=publish \
            --porcelain
        echo "  (creee)" >&2
    fi
}

PAGE_ACCUEIL=$(create_page_if_missing "Accueil"         "accueil")
PAGE_GALERIE=$(create_page_if_missing "Galerie"          "galerie")
create_page_if_missing "Boutique"        "boutique"       > /dev/null
create_page_if_missing "Reservations"    "reservations"   > /dev/null
PAGE_BLOG=$(create_page_if_missing "Blog"               "blog")
create_page_if_missing "A propos"        "a-propos"       > /dev/null
create_page_if_missing "Contact"         "contact"        > /dev/null
create_page_if_missing "Mentions legales" "mentions-legales" > /dev/null

wp option update show_on_front  page
wp option update page_on_front  "$PAGE_ACCUEIL"
wp option update page_for_posts "$PAGE_BLOG"

# ── Permaliens ─────────────────────────────────────────────
echo "> Permaliens..."
wp rewrite structure '/%postname%/' --hard
wp rewrite flush --hard

# ── WooCommerce ────────────────────────────────────────────
echo "> Configuration WooCommerce..."
wp eval-file "$SCRIPT_DIR/configure-woocommerce.php"

# ── Polylang ───────────────────────────────────────────────
echo "> Configuration Polylang..."
wp eval-file "$SCRIPT_DIR/configure-polylang.php"

# ── Rank Math ──────────────────────────────────────────────
echo "> Configuration Rank Math..."
wp eval-file "$SCRIPT_DIR/configure-rankmath.php"

# ── Stripe mode test ───────────────────────────────────────
echo "> Configuration Stripe (mode TEST)..."
wp eval-file "$SCRIPT_DIR/configure-stripe-test.php"

# ── Mailtrap ───────────────────────────────────────────────
echo "> Configuration Mailtrap (emails de test)..."
wp eval-file "$SCRIPT_DIR/configure-mailtrap.php"

# ── Fake webhook (mu-plugin) ───────────────────────────────
echo "> Installation du fake webhook Stripe..."
MU_DIR="$(wp eval 'echo WPMU_PLUGIN_DIR;')"
mkdir -p "$MU_DIR"
cp "$SCRIPT_DIR/fake-webhook.php" "$MU_DIR/fake-webhook.php"
echo "  Endpoint : $WP_HOME/fake-webhook?token=${FAKE_WEBHOOK_TOKEN:-test123}"

# ── Reglages locaux ────────────────────────────────────────
echo "> Reglages locaux..."
wp option update timezone_string      "Europe/Paris"
wp option update date_format          "d/m/Y"
wp option update time_format          "H:i"
wp option update WPLANG               "fr_FR"
wp option update default_comment_status  closed
wp option update default_ping_status    closed

# Desactiver WP Cron natif (Local le gere)
wp config set DISABLE_WP_CRON true --type=constant --raw

wp rewrite flush --hard

echo ""
echo "========================================================"
echo "  Setup local termine !"
echo "========================================================"
echo ""
echo "  Admin  : $WP_HOME/wp-admin"
echo "  Login  : $ADMIN_USER / $ADMIN_PASSWORD"
echo ""
echo "  Etapes suivantes :"
echo "  1. bash setup/test-payment-flow.sh"
echo "  2. Verifier les emails dans Mailtrap"
echo "  3. Ouvrir $WP_HOME/wp-admin"
echo ""
echo "  RAPPEL : fake-webhook.php est dans mu-plugins."
echo "           Retirez-le AVANT la mise en production."
echo ""
