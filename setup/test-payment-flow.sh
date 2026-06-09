#!/usr/bin/env bash
# ============================================================
# test-payment-flow.sh — Test bout en bout du tunnel de paiement
#
# Ce script :
#   1. Crée une œuvre de test (produit WooCommerce)
#   2. Crée une commande en attente
#   3. Déclenche le fake webhook Stripe
#   4. Vérifie que la commande est payée
#   5. Vérifie que le stock est à 0
#   6. Affiche un résumé ✅ / ❌
#
# Usage (depuis la racine WordPress dans le shell Local) :
#   bash setup/test-payment-flow.sh
# ============================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/.env.local"

# ── Chargement .env.local ──────────────────────────────────
if [[ -f "$ENV_FILE" ]]; then
    set -a
    # shellcheck disable=SC1091
    source "$ENV_FILE"
    set +a
fi

WP_HOME="${WP_HOME:-http://art-sell.local}"
FAKE_WEBHOOK_TOKEN="${FAKE_WEBHOOK_TOKEN:-test123}"
WEBHOOK_URL="$WP_HOME/fake-webhook?token=$FAKE_WEBHOOK_TOKEN"

# ── Couleurs pour le résumé ────────────────────────────────
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

ok()   { echo -e "  ${GREEN}✅ $1${NC}"; }
fail() { echo -e "  ${RED}❌ $1${NC}"; FAILED=$((FAILED + 1)); }
info() { echo -e "  ${YELLOW}→  $1${NC}"; }

FAILED=0

echo "========================================================"
echo "  Test tunnel de paiement — $(date '+%H:%M:%S')"
echo "  Site : $WP_HOME"
echo "========================================================"
echo ""

# ── Vérification préalable : WordPress accessible ─────────
if [[ ! -f "wp-load.php" ]]; then
    echo "Erreur : lancez ce script depuis la racine WordPress."
    echo "  cd ~/Local Sites/art-sell/app/public"
    exit 1
fi

if ! wp core is-installed 2>/dev/null; then
    echo "Erreur : WordPress non installé. Lancez setup-local.sh d'abord."
    exit 1
fi

# ── Vérification : fake webhook accessible ────────────────
echo "[ 0/5 ] Vérification du fake webhook..."
WEBHOOK_CHECK=$(curl -s -o /dev/null -w "%{http_code}" -X POST \
    -H "Content-Type: application/json" \
    -d '{"type":"ping"}' \
    "$WEBHOOK_URL" 2>/dev/null || echo "000")

if [[ "$WEBHOOK_CHECK" == "200" ]]; then
    ok "Fake webhook accessible ($WEBHOOK_URL)"
elif [[ "$WEBHOOK_CHECK" == "000" ]]; then
    fail "Impossible de joindre $WP_HOME — le site Local est-il démarré ?"
    echo ""
    echo "  Démarrez le site dans Local by Flywheel puis relancez ce script."
    exit 1
else
    fail "Fake webhook répond $WEBHOOK_CHECK (attendu 200)"
    info "Vérifiez que fake-webhook.php est dans wp-content/mu-plugins/"
    info "Puis : wp rewrite flush --hard"
fi

echo ""

# ── Étape 1 : Créer une œuvre de test ─────────────────────
echo "[ 1/5 ] Création de l'œuvre de test..."

# Nettoyer les éventuelles œuvres de test précédentes
OLD_IDS=$(wp post list \
    --post_type=product \
    --post_status=any \
    --title="Tableau Test Automatique" \
    --format=ids 2>/dev/null || echo "")

if [[ -n "$OLD_IDS" ]]; then
    info "Suppression des œuvres de test précédentes (IDs: $OLD_IDS)..."
    for old_id in $OLD_IDS; do
        wp post delete "$old_id" --force 2>/dev/null || true
    done
fi

PRODUCT_ID=$(wp post create \
    --post_type=product \
    --post_title="Tableau Test Automatique" \
    --post_status=publish \
    --porcelain 2>/dev/null)

if [[ -z "$PRODUCT_ID" ]] || ! [[ "$PRODUCT_ID" =~ ^[0-9]+$ ]]; then
    fail "Impossible de créer le produit WooCommerce"
    exit 1
fi

# Métadonnées produit WooCommerce
wp post meta update "$PRODUCT_ID" _price           250  2>/dev/null
wp post meta update "$PRODUCT_ID" _regular_price   250  2>/dev/null
wp post meta update "$PRODUCT_ID" _manage_stock    yes  2>/dev/null
wp post meta update "$PRODUCT_ID" _stock           1    2>/dev/null
wp post meta update "$PRODUCT_ID" _stock_status    instock 2>/dev/null
wp post meta update "$PRODUCT_ID" _virtual         no   2>/dev/null
wp post meta update "$PRODUCT_ID" _downloadable    no   2>/dev/null
wp post meta update "$PRODUCT_ID" _visibility      visible 2>/dev/null

# Terme requis par WooCommerce : declarer le produit comme "simple"
wp post term set "$PRODUCT_ID" product_type simple 2>/dev/null || true

ok "Œuvre créée (ID: $PRODUCT_ID, titre: «Tableau Test Automatique», prix: 250 EUR, stock: 1)"

echo ""

# ── Étape 2 : Créer une commande en attente ───────────────
echo "[ 2/5 ] Création d'une commande en attente..."

ORDER_ID=$(wp eval-file "$SCRIPT_DIR/create-test-order.php" "$PRODUCT_ID" 2>/dev/null)

if [[ -z "$ORDER_ID" ]] || ! [[ "$ORDER_ID" =~ ^[0-9]+$ ]]; then
    fail "Impossible de créer la commande WooCommerce (résultat: '$ORDER_ID')"
    info "Vérifiez que WooCommerce est actif et configuré"
    exit 1
fi

ORDER_STATUS_BEFORE=$(wp eval-file "$SCRIPT_DIR/get-order-status.php" "$ORDER_ID" 2>/dev/null || echo "inconnu")
ok "Commande créée (ID: $ORDER_ID, statut: $ORDER_STATUS_BEFORE)"

echo ""

# ── Étape 3 : Déclencher le fake webhook ──────────────────
echo "[ 3/5 ] Déclenchement du fake webhook..."

PAYLOAD=$(printf '{"type":"checkout.session.completed","data":{"object":{"payment_status":"paid","metadata":{"order_id":"%s"}}}}' "$ORDER_ID")

WEBHOOK_RESPONSE=$(curl -s -w "\n%{http_code}" \
    -X POST \
    -H "Content-Type: application/json" \
    -d "$PAYLOAD" \
    "$WEBHOOK_URL" 2>/dev/null)

HTTP_BODY=$(echo "$WEBHOOK_RESPONSE" | head -n -1)
HTTP_CODE=$(echo "$WEBHOOK_RESPONSE" | tail -n 1)

if [[ "$HTTP_CODE" == "200" ]]; then
    ok "Fake webhook déclenché (HTTP $HTTP_CODE)"
    info "Réponse : $HTTP_BODY"
else
    fail "Fake webhook a retourné HTTP $HTTP_CODE"
    info "Réponse : $HTTP_BODY"
    info "Log : wp-content/fake-webhook.log"
fi

echo ""

# ── Petite attente pour que WooCommerce traite ─────────────
sleep 1

# ── Étape 4 : Vérifier le statut de la commande ───────────
echo "[ 4/5 ] Vérification du statut de la commande..."

ORDER_STATUS_AFTER=$(wp eval-file "$SCRIPT_DIR/get-order-status.php" "$ORDER_ID" 2>/dev/null || echo "inconnu")
ORDER_TOTAL=$(wp post meta get "$ORDER_ID" _order_total 2>/dev/null || echo "?")

# WooCommerce utilise processing ou completed apres payment_complete()
if [[ "$ORDER_STATUS_AFTER" == "processing" ]] || [[ "$ORDER_STATUS_AFTER" == "completed" ]] || [[ "$ORDER_STATUS_AFTER" == "wc-processing" ]] || [[ "$ORDER_STATUS_AFTER" == "wc-completed" ]]; then
    ok "Commande #$ORDER_ID → statut : $ORDER_STATUS_AFTER (payée) | Total : ${ORDER_TOTAL} EUR"
else
    fail "Commande #$ORDER_ID → statut : $ORDER_STATUS_AFTER (attendu: processing)"
    info "Vérifiez wp-content/fake-webhook.log pour les détails"
fi

echo ""

# ── Étape 5 : Vérifier le stock ───────────────────────────
echo "[ 5/5 ] Vérification du stock de l'œuvre..."

STOCK_AFTER=$(wp post meta get "$PRODUCT_ID" _stock 2>/dev/null || echo "?")
STOCK_STATUS=$(wp post meta get "$PRODUCT_ID" _stock_status 2>/dev/null || echo "?")

if [[ "$STOCK_AFTER" == "0" ]]; then
    ok "Stock de l'œuvre (ID: $PRODUCT_ID) → $STOCK_AFTER (statut: $STOCK_STATUS)"
else
    fail "Stock de l'œuvre (ID: $PRODUCT_ID) → $STOCK_AFTER (attendu: 0)"
    info "Le stock n'a pas été décrémenté — vérifiez la gestion de stock WooCommerce"
    info "Admin : $WP_HOME/wp-admin/post.php?post=$PRODUCT_ID&action=edit"
fi

echo ""

# ── Résumé ─────────────────────────────────────────────────
echo "========================================================"
if [[ $FAILED -eq 0 ]]; then
    echo -e "  ${GREEN}Tous les tests sont passés !${NC}"
else
    echo -e "  ${RED}$FAILED test(s) ont échoué.${NC}"
fi
echo "========================================================"
echo ""
echo "  Liens utiles :"
echo "  Commande #$ORDER_ID : $WP_HOME/wp-admin/post.php?post=$ORDER_ID&action=edit"
echo "  Œuvre #$PRODUCT_ID  : $WP_HOME/wp-admin/post.php?post=$PRODUCT_ID&action=edit"
echo "  Log webhook         : wp-content/fake-webhook.log"
echo "  Emails Mailtrap     : mailtrap.io → Email Testing → Inboxes"
echo ""
echo "  Test manuel :"
echo "  1. Allez sur $WP_HOME/?p=$PRODUCT_ID"
echo "  2. Ajoutez au panier et passez commande (carte test: 4242 4242 4242 4242)"
echo "  3. Récupérez l'ID de commande et appelez le webhook manuellement :"
echo "     curl -X POST '$WEBHOOK_URL' \\"
echo "       -H 'Content-Type: application/json' \\"
echo "       -d '{\"type\":\"checkout.session.completed\",\"data\":{\"object\":{\"payment_status\":\"paid\",\"metadata\":{\"order_id\":\"ID_COMMANDE\"}}}}'"
echo ""

# Nettoyer les données de test (optionnel, commenté par défaut)
# info "Nettoyage des données de test..."
# wp post delete "$PRODUCT_ID" --force 2>/dev/null || true
# wp post delete "$ORDER_ID"   --force 2>/dev/null || true

exit $FAILED
