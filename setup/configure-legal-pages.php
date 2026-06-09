<?php
/**
 * configure-legal-pages.php
 * wp eval-file setup/configure-legal-pages.php
 *
 * Crée ou met à jour les 4 pages légales obligatoires pour un e-commerce français :
 *   - Mentions légales (LCEN art. 6)
 *   - CGV (Code de la consommation)
 *   - Politique de confidentialité (RGPD)
 *   - Politique de cookies
 *
 * ⚠️  Ces textes sont des MODÈLES. Faites-les relire par un professionnel
 *     et adaptez toutes les variables entre [crochets] avant la mise en ligne.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo "> Création des pages légales...\n";

$site_name   = get_bloginfo( 'name' );
$site_url    = home_url();
$admin_email = get_option( 'admin_email' );
$year        = date( 'Y' );

// ── Helper : créer ou mettre à jour une page ──────────────
function _legal_upsert_page( string $title, string $slug, string $content ): int {
    $existing = get_page_by_path( $slug );
    if ( $existing ) {
        wp_update_post( [ 'ID' => $existing->ID, 'post_content' => $content, 'post_title' => $title ] );
        echo "  • '$title' mise à jour (ID: {$existing->ID})\n";
        return $existing->ID;
    }
    $id = wp_insert_post( [
        'post_title'   => $title,
        'post_name'    => $slug,
        'post_type'    => 'page',
        'post_status'  => 'publish',
        'post_content' => $content,
    ] );
    echo "  • '$title' créée (ID: $id)\n";
    return $id;
}

// ────────────────────────────────────────────────────────────
// 1. MENTIONS LÉGALES
// ────────────────────────────────────────────────────────────
$mentions_content = <<<EOT
<!-- wp:group {"layout":{"type":"constrained","contentSize":"800px"}} -->
<div class="wp-block-group">

<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Mentions légales</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"color":{"text":"var(--color-text-muted)"}}} -->
<p style="color:var(--color-text-muted)">En vigueur au $year — Conformément à la loi n° 2004-575 du 21 juin 2004</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Éditeur du site</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><strong>$site_name</strong><br>
[Prénom NOM de l'artiste]<br>
Statut : Auto-entrepreneur / Artiste-auteur (⚠️ à préciser)<br>
Numéro SIRET : [NUMÉRO SIRET ou "en cours d'immatriculation"]<br>
Adresse : [Adresse complète]<br>
Email : <a href="mailto:$admin_email">$admin_email</a><br>
Téléphone : [Numéro de téléphone]</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Directeur de la publication :</strong> [Prénom NOM de l'artiste]</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Hébergeur</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><strong>o2switch</strong><br>
Forme juridique : SARL<br>
Capital social : 100 000 €<br>
Siège social : Chemin des Pardiaux, 63000 Clermont-Ferrand<br>
RCS Clermont-Ferrand B 510 909 807<br>
Téléphone : 04 44 44 60 40<br>
Site : <a href="https://www.o2switch.fr" target="_blank" rel="noreferrer noopener">www.o2switch.fr</a></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Propriété intellectuelle</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>L'ensemble du contenu de ce site (textes, photographies d'œuvres, illustrations, vidéos, logotypes) est la propriété exclusive de $site_name ou de leurs auteurs respectifs, et est protégé par le droit d'auteur (Code de la propriété intellectuelle).</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Toute reproduction, représentation, modification, publication ou adaptation de tout ou partie des éléments du site, quel que soit le moyen ou le procédé utilisé, est interdite sans autorisation écrite préalable.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Limitation de responsabilité</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>$site_name s'efforce d'assurer l'exactitude des informations diffusées sur ce site. Toutefois, elle ne peut garantir l'exhaustivité ou l'absence d'erreur des informations présentées. Les prix des œuvres sont exprimés en euros toutes taxes comprises.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Médiation des litiges</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>En cas de litige, vous pouvez recourir gratuitement à la médiation de la consommation. Plateforme européenne de règlement en ligne des litiges : <a href="https://ec.europa.eu/consumers/odr" target="_blank" rel="noreferrer noopener">ec.europa.eu/consumers/odr</a>.</p>
<!-- /wp:paragraph -->

</div>
<!-- /wp:group -->
EOT;

_legal_upsert_page( 'Mentions légales', 'mentions-legales', $mentions_content );

// ────────────────────────────────────────────────────────────
// 2. CONDITIONS GÉNÉRALES DE VENTE
// ────────────────────────────────────────────────────────────
$cgv_content = <<<EOT
<!-- wp:group {"layout":{"type":"constrained","contentSize":"800px"}} -->
<div class="wp-block-group">

<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Conditions Générales de Vente</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"color":{"text":"var(--color-text-muted)"}}} -->
<p style="color:var(--color-text-muted)">Version en vigueur au $year</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Article 1 — Vendeur</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Les présentes CGV régissent les ventes effectuées sur <strong>$site_url</strong> par :<br>
[Prénom NOM], artiste peintre<br>
[Adresse]<br>
Email : <a href="mailto:$admin_email">$admin_email</a><br>
SIRET : [NUMÉRO]<br>
[TVA non applicable, art. 293 B du CGI] ou [TVA intracommunautaire : FR...] (⚠️ à préciser selon situation)</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Article 2 — Produits</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Le site propose à la vente :</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul class="wp-block-list">
<li>Des <strong>œuvres originales</strong> (peintures, dessins, aquarelles) — pièces uniques</li>
<li>Des <strong>reproductions</strong> (prints numérotés)</li>
<li>Des <strong>livres et produits numériques</strong> (le cas échéant)</li>
<li>Des <strong>prestations culturelles</strong> (cours, ateliers, conférences)</li>
</ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>Les photographies des œuvres sont aussi fidèles que possible, mais des variations de teintes peuvent exister entre l'écran et l'original. Les dimensions indiquées sur les fiches sont données à titre indicatif (±2 cm).</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Article 3 — Prix</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Les prix sont exprimés en euros. [TVA non applicable, art. 293 B du CGI — l'artiste bénéficie de la franchise en base de TVA.] ou [Les prix sont indiqués TTC.]</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Les frais de livraison sont indiqués lors du processus de commande, avant validation.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Article 4 — Commande</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>La commande est considérée comme définitive après :</p>
<!-- /wp:paragraph -->

<!-- wp:list {"ordered":true} -->
<ol class="wp-block-list">
<li>Ajout du produit au panier</li>
<li>Saisie des informations de livraison</li>
<li>Acceptation des présentes CGV</li>
<li>Validation du paiement</li>
</ol>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>Un email de confirmation est adressé à l'acheteur dès réception du paiement. $site_name se réserve le droit d'annuler une commande en cas d'indisponibilité de l'œuvre, avec remboursement intégral.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Article 5 — Paiement</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Le paiement s'effectue en ligne par carte bancaire via <strong>Stripe</strong>, prestataire sécurisé (protocole SSL/TLS). Les informations bancaires ne transitent pas sur le site et ne sont pas conservées.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Article 6 — Livraison</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Les œuvres sont expédiées soigneusement emballées (tube ou carton renforcé selon le format) dans un délai de <strong>[3 à 10 jours ouvrés]</strong> après confirmation du paiement.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Modes de livraison :</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul class="wp-block-list">
<li><strong>France métropolitaine :</strong> Colissimo avec suivi</li>
<li><strong>Europe et international :</strong> Tarif sur devis selon le format et la destination</li>
<li><strong>Retrait sur place :</strong> possible sur rendez-vous à l'atelier (gratuit)</li>
</ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>En cas de dommage pendant le transport, contacter l'artiste dans les <strong>48h</strong> suivant la réception avec photos à l'appui. Une solution de remplacement ou de remboursement sera proposée.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Article 7 — Droit de rétractation</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Conformément à l'article L. 221-18 du Code de la consommation, vous disposez d'un délai de <strong>14 jours</strong> à compter de la réception de votre commande pour exercer votre droit de rétractation, sans avoir à justifier de motif.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Pour exercer ce droit : envoyez un email à <a href="mailto:$admin_email">$admin_email</a> en précisant votre numéro de commande. L'œuvre doit être retournée dans son emballage d'origine, en parfait état, à vos frais.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Exception :</strong> le droit de rétractation ne s'applique pas aux œuvres réalisées sur commande personnalisée (article L. 221-28 du Code de la consommation).</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Le remboursement interviendra dans les <strong>14 jours</strong> suivant réception de l'œuvre retournée, par le même moyen de paiement.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Article 8 — Garanties légales</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>L'acheteur bénéficie des garanties légales suivantes :</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul class="wp-block-list">
<li><strong>Garantie de conformité</strong> (art. L. 224-25-1 et s. du Code de la consommation) : 2 ans</li>
<li><strong>Garantie des vices cachés</strong> (art. 1641 et s. du Code civil) : 2 ans</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Article 9 — Propriété intellectuelle</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>L'achat d'une œuvre originale transfère la <strong>propriété matérielle</strong> de l'œuvre, non les droits d'auteur. Toute reproduction, diffusion ou exploitation commerciale de l'œuvre est soumise à l'autorisation préalable de l'artiste (Code de la propriété intellectuelle, art. L. 122-1).</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Un <strong>certificat d'authenticité</strong> signé par l'artiste est joint à chaque œuvre originale vendue.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Article 10 — Litiges</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>En cas de litige, une solution amiable sera recherchée en priorité. En l'absence d'accord, vous pouvez saisir un médiateur de la consommation agréé ou la plateforme européenne : <a href="https://ec.europa.eu/consumers/odr" target="_blank" rel="noreferrer noopener">ec.europa.eu/consumers/odr</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Les présentes CGV sont soumises au droit français. En cas de litige non résolu, les tribunaux français seront seuls compétents.</p>
<!-- /wp:paragraph -->

</div>
<!-- /wp:group -->
EOT;

_legal_upsert_page( 'Conditions générales de vente', 'cgv', $cgv_content );

// ────────────────────────────────────────────────────────────
// 3. POLITIQUE DE CONFIDENTIALITÉ
// ────────────────────────────────────────────────────────────
$privacy_content = <<<EOT
<!-- wp:group {"layout":{"type":"constrained","contentSize":"800px"}} -->
<div class="wp-block-group">

<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Politique de confidentialité</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"color":{"text":"var(--color-text-muted)"}}} -->
<p style="color:var(--color-text-muted)">Conformément au RGPD (Règlement UE 2016/679) — Version $year</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Responsable du traitement</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>[Prénom NOM], artiste peintre — [Adresse] — <a href="mailto:$admin_email">$admin_email</a></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Données collectées et finalités</h2>
<!-- /wp:heading -->

<!-- wp:table {"className":"is-style-stripes"} -->
<figure class="wp-block-table is-style-stripes"><table><thead><tr><th>Source</th><th>Données</th><th>Finalité</th><th>Base légale</th><th>Durée</th></tr></thead><tbody><tr><td>Commande WooCommerce</td><td>Nom, email, adresse, téléphone, historique</td><td>Exécution de la commande, facturation</td><td>Contrat</td><td>10 ans (obligation comptable)</td></tr><tr><td>Formulaire contact</td><td>Nom, email, message</td><td>Traitement de la demande</td><td>Intérêt légitime</td><td>3 ans</td></tr><tr><td>Réservation Amelia</td><td>Nom, email, téléphone, créneau</td><td>Gestion des rendez-vous</td><td>Contrat</td><td>3 ans</td></tr><tr><td>Compte client</td><td>Identifiants, adresses</td><td>Espace personnel</td><td>Contrat</td><td>Durée du compte + 3 ans</td></tr></tbody></table></figure>
<!-- /wp:table -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Partage des données</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Vos données peuvent être transmises à :</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul class="wp-block-list">
<li><strong>Stripe</strong> (paiement sécurisé) — <a href="https://stripe.com/fr/privacy" target="_blank" rel="noreferrer noopener">Politique Stripe</a></li>
<li><strong>o2switch</strong> (hébergement) — serveurs en France</li>
<li><strong>Transporteurs</strong> (livraison) — uniquement adresse de livraison</li>
</ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>Aucune donnée n'est vendue ou cédée à des tiers à des fins commerciales.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Vos droits</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Conformément au RGPD, vous disposez des droits suivants :</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul class="wp-block-list">
<li><strong>Accès</strong> — obtenir une copie de vos données</li>
<li><strong>Rectification</strong> — corriger des données inexactes</li>
<li><strong>Effacement</strong> — supprimer vos données (sous réserve des obligations légales)</li>
<li><strong>Portabilité</strong> — recevoir vos données dans un format structuré</li>
<li><strong>Opposition</strong> — vous opposer à un traitement</li>
<li><strong>Limitation</strong> — limiter un traitement</li>
</ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>Pour exercer ces droits : <a href="mailto:$admin_email">$admin_email</a><br>
Réponse sous 1 mois. En cas de réclamation non résolue : <a href="https://www.cnil.fr/fr/plaintes" target="_blank" rel="noreferrer noopener">CNIL.fr</a></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Sécurité</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Le site utilise le protocole HTTPS. Les paiements sont chiffrés par Stripe (PCI-DSS). Les mots de passe sont stockés sous forme hachée. L'hébergement est assuré en France (o2switch).</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Cookies</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Voir notre <a href="/politique-cookies/">Politique de cookies</a>.</p>
<!-- /wp:paragraph -->

</div>
<!-- /wp:group -->
EOT;

_legal_upsert_page( 'Politique de confidentialité', 'politique-confidentialite', $privacy_content );

// ────────────────────────────────────────────────────────────
// 4. POLITIQUE DE COOKIES
// ────────────────────────────────────────────────────────────
$cookies_content = <<<EOT
<!-- wp:group {"layout":{"type":"constrained","contentSize":"800px"}} -->
<div class="wp-block-group">

<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Politique de cookies</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"color":{"text":"var(--color-text-muted)"}}} -->
<p style="color:var(--color-text-muted)">Version $year</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Qu'est-ce qu'un cookie ?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Un cookie est un petit fichier texte déposé sur votre navigateur lors de votre visite. Il permet de mémoriser vos préférences et d'améliorer votre expérience.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Cookies utilisés sur ce site</h2>
<!-- /wp:heading -->

<!-- wp:table {"className":"is-style-stripes"} -->
<figure class="wp-block-table is-style-stripes"><table><thead><tr><th>Nom</th><th>Type</th><th>Finalité</th><th>Durée</th></tr></thead><tbody><tr><td>wordpress_logged_in_*</td><td>Strictement nécessaire</td><td>Authentification WordPress</td><td>Session</td></tr><tr><td>woocommerce_cart_hash</td><td>Strictement nécessaire</td><td>Panier d'achat</td><td>Session</td></tr><tr><td>woocommerce_items_in_cart</td><td>Strictement nécessaire</td><td>Panier d'achat</td><td>Session</td></tr><tr><td>wp_woocommerce_session_*</td><td>Strictement nécessaire</td><td>Session commande</td><td>2 jours</td></tr><tr><td>cookie_notice_accepted</td><td>Fonctionnel</td><td>Mémorisation du consentement cookies</td><td>1 an</td></tr><tr><td>__stripe_mid</td><td>Paiement (tiers)</td><td>Sécurité paiement Stripe</td><td>1 an</td></tr><tr><td>__stripe_sid</td><td>Paiement (tiers)</td><td>Session paiement Stripe</td><td>30 min</td></tr></tbody></table></figure>
<!-- /wp:table -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Cookies analytiques</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Ce site n'utilise pas de cookies de tracking ou de publicité (Google Analytics, Facebook Pixel, etc.) sans votre consentement explicite.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Gérer vos préférences</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Vous pouvez modifier vos préférences à tout moment via le bandeau de consentement affiché lors de votre première visite, ou en modifiant les paramètres de votre navigateur.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>La suppression des cookies strictement nécessaires peut perturber le fonctionnement du panier et de l'espace client.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Pour en savoir plus sur les cookies : <a href="https://www.cnil.fr/fr/cookies-et-autres-traceurs" target="_blank" rel="noreferrer noopener">CNIL — Cookies et traceurs</a></p>
<!-- /wp:paragraph -->

</div>
<!-- /wp:group -->
EOT;

_legal_upsert_page( 'Politique de cookies', 'politique-cookies', $cookies_content );

// ── Ajouter les pages aux réglages WooCommerce ─────────────
$terms_page = get_page_by_path( 'cgv' );
if ( $terms_page ) {
    update_option( 'woocommerce_terms_page_id', $terms_page->ID );
    echo "  • CGV liées à WooCommerce (case à cocher en checkout)\n";
}

$privacy_page = get_page_by_path( 'politique-confidentialite' );
if ( $privacy_page ) {
    update_option( 'wp_page_for_privacy_policy', $privacy_page->ID );
    echo "  • Politique confidentialité liée à WordPress\n";
}

echo "\nPages légales créées.\n";
echo "  ⚠️  Ces pages sont des MODÈLES.\n";
echo "      Adaptez toutes les mentions [entre crochets] avant la mise en ligne.\n";
echo "      Faites-les valider par un professionnel juridique.\n";
echo "\n  Pages créées :\n";
echo "  • " . home_url( '/mentions-legales/' ) . "\n";
echo "  • " . home_url( '/cgv/' ) . "\n";
echo "  • " . home_url( '/politique-confidentialite/' ) . "\n";
echo "  • " . home_url( '/politique-cookies/' ) . "\n";
