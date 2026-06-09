#!/usr/bin/env bash
# ============================================================
# setup-missing-features.sh
# Active les 5 features manquantes identifiées avant livraison :
#   1. Amelia (réservations)
#   2. Formulaire de contact
#   3. Pages légales (CGV, mentions légales, RGPD, cookies)
#   4. Bandeau cookies RGPD
#   5. SMTP (local = Mailtrap | prod = o2switch)
#
# Usage (shell Local depuis la racine WordPress) :
#   bash setup/setup-missing-features.sh
#   bash setup/setup-missing-features.sh --production
# ============================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MODE="${1:-local}"  # local ou --production

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

ok()   { echo -e "  ${GREEN}✅ $*${NC}"; }
fail() { echo -e "  ${RED}❌ $*${NC}"; }
step() { echo -e "\n${YELLOW}[ $* ]${NC}"; }

ERRORS=0

echo "========================================================"
echo "  Features manquantes — installation"
echo "  Mode : $MODE"
echo "========================================================"

# ── Vérification de base ───────────────────────────────────
if [[ ! -f "wp-load.php" ]]; then
    fail "Lancez depuis la racine WordPress (wp-load.php introuvable)"
    exit 1
fi

if ! wp core is-installed 2>/dev/null; then
    fail "WordPress non installé — lancez setup-local.sh d'abord"
    exit 1
fi

# ── 1. Amelia ─────────────────────────────────────────────
step "1/5 — Amelia (réservations)"

if ! wp plugin is-active ameliabooking 2>/dev/null; then
    echo "  Activation d'Amelia..."
    wp plugin activate ameliabooking 2>/dev/null || true
fi

if wp plugin is-active ameliabooking 2>/dev/null; then
    wp eval-file "$SCRIPT_DIR/configure-amelia.php" && ok "Amelia configuré (5 services + planning Mar–Sam)" || { fail "Erreur configure-amelia.php"; ERRORS=$((ERRORS+1)); }
else
    fail "Amelia non disponible — installez-le d'abord"
    ERRORS=$((ERRORS+1))
fi

# ── 2. Formulaire de contact ──────────────────────────────
step "2/5 — Formulaire de contact (Kadence Form)"

if ! wp plugin is-active kadence-blocks 2>/dev/null; then
    echo "  Activation de Kadence Blocks..."
    wp plugin activate kadence-blocks 2>/dev/null || true
fi

wp eval-file "$SCRIPT_DIR/configure-contact-form.php" && ok "Formulaire de contact créé" || { fail "Erreur configure-contact-form.php"; ERRORS=$((ERRORS+1)); }

# ── 3. Pages légales ──────────────────────────────────────
step "3/5 — Pages légales (CGV, mentions légales, RGPD, cookies)"

wp eval-file "$SCRIPT_DIR/configure-legal-pages.php" && ok "4 pages légales créées" || { fail "Erreur configure-legal-pages.php"; ERRORS=$((ERRORS+1)); }

# ── 4. Bandeau cookies ────────────────────────────────────
step "4/5 — Bandeau cookies RGPD"

# Installer cookie-notice si absent
if ! wp plugin is-installed cookie-notice 2>/dev/null; then
    echo "  Installation du plugin cookie-notice..."
    wp plugin install cookie-notice --activate 2>/dev/null || true
elif ! wp plugin is-active cookie-notice 2>/dev/null; then
    wp plugin activate cookie-notice 2>/dev/null || true
fi

wp eval-file "$SCRIPT_DIR/configure-cookies.php" && ok "Bandeau cookies configuré" || { fail "Erreur configure-cookies.php"; ERRORS=$((ERRORS+1)); }

# ── 5. SMTP / Emails ──────────────────────────────────────
step "5/5 — Configuration emails"

if ! wp plugin is-installed wp-mail-smtp 2>/dev/null; then
    echo "  Installation de WP Mail SMTP..."
    wp plugin install wp-mail-smtp --activate 2>/dev/null || true
elif ! wp plugin is-active wp-mail-smtp 2>/dev/null; then
    wp plugin activate wp-mail-smtp 2>/dev/null || true
fi

if [[ "$MODE" == "--production" ]]; then
    echo "  Mode PRODUCTION — configuration SMTP o2switch"
    wp eval-file "$SCRIPT_DIR/configure-smtp-production.php" && ok "SMTP production configuré" || { fail "Erreur configure-smtp-production.php"; ERRORS=$((ERRORS+1)); }
else
    echo "  Mode LOCAL — configuration Mailtrap"
    wp eval-file "$SCRIPT_DIR/configure-mailtrap.php" && ok "Mailtrap configuré" || { fail "Erreur configure-mailtrap.php"; ERRORS=$((ERRORS+1)); }
fi

# ── Ajouter les pages légales dans le footer ──────────────
step "Bonus — Liens footer"

echo "  Création/mise à jour du menu Footer..."
wp eval '
$pages = [
    ["cgv",                       "CGV"],
    ["mentions-legales",          "Mentions légales"],
    ["politique-confidentialite", "Confidentialité"],
    ["politique-cookies",         "Cookies"],
];

// Créer le menu footer si inexistant
$menu_name = "Footer";
$menu_exists = wp_get_nav_menu_object($menu_name);
$menu_id = $menu_exists ? $menu_exists->term_id : wp_create_nav_menu($menu_name);

foreach ($pages as [$slug, $label]) {
    $page = get_page_by_path($slug);
    if (!$page) continue;

    // Vérifier si déjà dans le menu
    $items = wp_get_nav_menu_items($menu_id);
    $already = false;
    if ($items) {
        foreach ($items as $item) {
            if ((int)$item->object_id === $page->ID) { $already = true; break; }
        }
    }

    if (!$already) {
        wp_update_nav_menu_item($menu_id, 0, [
            "menu-item-title"     => $label,
            "menu-item-object"    => "page",
            "menu-item-object-id" => $page->ID,
            "menu-item-type"      => "post_type",
            "menu-item-status"    => "publish",
        ]);
    }
}
echo "Menu Footer mis à jour\n";
' 2>/dev/null && ok "Menu Footer avec liens légaux" || echo "  (Menu footer à configurer manuellement dans Apparence → Menus)"

# ── Flush ──────────────────────────────────────────────────
echo ""
echo "  Flush des permaliens..."
wp rewrite flush --hard 2>/dev/null

# ── Résumé ────────────────────────────────────────────────
echo ""
echo "========================================================"
if [[ $ERRORS -eq 0 ]]; then
    echo -e "  ${GREEN}Toutes les features manquantes sont installées !${NC}"
else
    echo -e "  ${RED}$ERRORS erreur(s) — vérifiez les messages ci-dessus.${NC}"
fi
echo "========================================================"
echo ""
echo "  Vérifications à faire :"
echo "  → Contact    : $(wp option get home)/contact/"
echo "  → CGV        : $(wp option get home)/cgv/"
echo "  → Réservations: $(wp option get home)/reservations/"
echo ""
echo "  Prochaine étape :"
echo "  bash setup/test-payment-flow.sh"
echo ""

if [[ "$MODE" != "--production" ]]; then
    echo "  Checklist Stripe navigateur :"
    echo "  cat setup/STRIPE-TEST-CHECKLIST.md"
    echo ""
fi

exit $ERRORS
