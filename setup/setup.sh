#!/usr/bin/env bash
# ============================================================
# setup.sh — Installation WordPress complète pour galerie d'art
# Compatible o2switch (mutualisé, PHP 8.1+, WP-CLI via SSH)
# ============================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# ── Chargement du .env ─────────────────────────────────────
if [[ ! -f "$SCRIPT_DIR/.env" ]]; then
    echo "Erreur : fichier .env introuvable."
    echo "  Copiez .env.example en .env et remplissez les variables."
    exit 1
fi

set -a
# shellcheck disable=SC1091
source "$SCRIPT_DIR/.env"
set +a

: "${DB_NAME:?DB_NAME est requis}"
: "${DB_USER:?DB_USER est requis}"
: "${DB_PASSWORD:?DB_PASSWORD est requis}"
: "${DB_HOST:=localhost}"
: "${WP_HOME:?WP_HOME est requis (ex: https://monsite.fr)}"
: "${ADMIN_USER:?ADMIN_USER est requis}"
: "${ADMIN_PASSWORD:?ADMIN_PASSWORD est requis}"
: "${ADMIN_EMAIL:?ADMIN_EMAIL est requis}"

SITE_TITLE="${SITE_TITLE:-Galerie d art}"
TABLE_PREFIX="${TABLE_PREFIX:-art_}"
WP_LOCALE="fr_FR"

echo "========================================================"
echo "  Installation WordPress — Galerie d'art"
echo "  Site : $WP_HOME"
echo "========================================================"

# ── 1. Téléchargement WordPress ────────────────────────────
echo ""
echo "> Téléchargement de WordPress ($WP_LOCALE)..."
wp core download --locale="$WP_LOCALE" --version=latest --skip-content

# ── 2. wp-config.php ───────────────────────────────────────
echo "> Création du wp-config.php..."
wp config create \
    --dbname="$DB_NAME" \
    --dbuser="$DB_USER" \
    --dbpass="$DB_PASSWORD" \
    --dbhost="$DB_HOST" \
    --dbprefix="$TABLE_PREFIX" \
    --locale="$WP_LOCALE" \
    --extra-php <<'PHP'
define( 'DISALLOW_FILE_EDIT', true );
define( 'FORCE_SSL_ADMIN',    true );
define( 'WP_POST_REVISIONS',  5 );
define( 'EMPTY_TRASH_DAYS',   30 );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
define( 'WP_MEMORY_LIMIT',    '256M' );
define( 'WP_MAX_MEMORY_LIMIT','512M' );
$_wp_debug = filter_var( getenv( 'WP_DEBUG' ) ?: 'false', FILTER_VALIDATE_BOOLEAN );
define( 'WP_DEBUG',         $_wp_debug );
define( 'WP_DEBUG_LOG',     $_wp_debug );
define( 'WP_DEBUG_DISPLAY', false );
@ini_set( 'display_errors', 0 );
PHP

# ── 3. Installation WordPress ──────────────────────────────
echo "> Installation de WordPress..."
wp core install \
    --url="$WP_HOME" \
    --title="$SITE_TITLE" \
    --admin_user="$ADMIN_USER" \
    --admin_password="$ADMIN_PASSWORD" \
    --admin_email="$ADMIN_EMAIL" \
    --skip-email

# ── 4. Suppression des plugins par défaut ──────────────────
echo "> Suppression des plugins par défaut..."
wp plugin delete hello akismet 2>/dev/null || true

# ── 5. Installation des plugins ────────────────────────────
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
)

for plugin in "${PLUGINS[@]}"; do
    echo "  • $plugin"
    wp plugin install "$plugin" --activate
done

# ── 6. Thème Kadence ───────────────────────────────────────
echo "> Installation du thème Kadence..."
wp theme install kadence --activate

# ── 7. Thème enfant ────────────────────────────────────────
echo "> Installation du thème enfant..."
THEMES_DIR="$(wp eval 'echo get_theme_root();')"
cp -r "$SCRIPT_DIR/kadence-child" "$THEMES_DIR/"
wp theme activate kadence-child

# ── 8. Pages de base ───────────────────────────────────────
echo "> Création des pages..."

create_page() {
    local title="$1"
    local slug="$2"
    wp post create \
        --post_type=page \
        --post_title="$title" \
        --post_name="$slug" \
        --post_status=publish \
        --porcelain
}

PAGE_ACCUEIL=$(create_page "Accueil"         "accueil")
PAGE_GALERIE=$(create_page "Galerie"          "galerie")
PAGE_BOUTIQUE=$(create_page "Boutique"        "boutique")
PAGE_RESA=$(create_page "Réservations"        "reservations")
PAGE_BLOG=$(create_page "Blog"               "blog")
PAGE_APROPOS=$(create_page "À propos"         "a-propos")
PAGE_CONTACT=$(create_page "Contact"          "contact")
PAGE_LEGAL=$(create_page "Mentions légales"   "mentions-legales")

echo "  Pages créées (IDs: $PAGE_ACCUEIL $PAGE_GALERIE $PAGE_BOUTIQUE $PAGE_RESA $PAGE_BLOG $PAGE_APROPOS $PAGE_CONTACT $PAGE_LEGAL)"

wp option update show_on_front  page
wp option update page_on_front  "$PAGE_ACCUEIL"
wp option update page_for_posts "$PAGE_BLOG"

# ── 9. Permaliens ──────────────────────────────────────────
echo "> Configuration des permaliens..."
wp rewrite structure '/%postname%/' --hard
wp rewrite flush --hard

# ── 10. Réglages WooCommerce ───────────────────────────────
echo "> Configuration WooCommerce..."
wp eval-file "$SCRIPT_DIR/configure-woocommerce.php"

# ── 11. Polylang ───────────────────────────────────────────
echo "> Configuration Polylang..."
wp eval-file "$SCRIPT_DIR/configure-polylang.php"

# ── 12. Rank Math ──────────────────────────────────────────
echo "> Configuration Rank Math..."
wp eval-file "$SCRIPT_DIR/configure-rankmath.php"

# ── 13. .htaccess ──────────────────────────────────────────
echo "> Copie du .htaccess optimisé..."
WP_ROOT="$(wp eval 'echo ABSPATH;')"
cp "$SCRIPT_DIR/.htaccess" "$WP_ROOT/.htaccess"
wp rewrite flush --hard

# ── 14. Réglages finaux ────────────────────────────────────
echo "> Réglages finaux..."
wp option update timezone_string      "Europe/Paris"
wp option update date_format          "d/m/Y"
wp option update time_format          "H:i"
wp option update WPLANG               "fr_FR"
wp option update default_comment_status  closed
wp option update default_ping_status    closed

# Mises à jour traductions
wp language core update 2>/dev/null || true
wp language plugin update --all 2>/dev/null || true

echo ""
echo "========================================================"
echo "  Installation terminée !"
echo "========================================================"
echo ""
echo "  Admin : $WP_HOME/wp-admin"
echo "  Login : $ADMIN_USER"
echo ""
echo "  Etapes suivantes :"
echo "  1. WooCommerce > Reglages > Paiements : configurer Stripe"
echo "  2. Amelia : configurer services et disponibilites"
echo "  3. Rank Math : connecter Google Search Console"
echo ""
