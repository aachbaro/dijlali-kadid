<?php
/**
 * configure-cookies.php
 * wp eval-file setup/configure-cookies.php
 *
 * Installe et configure le bandeau de consentement cookies (RGPD / CNIL).
 * Plugin : Cookie Notice & Compliance for GDPR / CCPA (dFactory)
 * — très léger, aucune configuration complexe, gratuit.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo "> Configuration du bandeau cookies RGPD...\n";

$privacy_page   = get_page_by_path( 'politique-cookies' );
$privacy_url    = $privacy_page ? get_permalink( $privacy_page->ID ) : home_url( '/politique-cookies/' );
$site_name      = get_bloginfo( 'name' );

// ── Installer le plugin si absent ─────────────────────────
$plugin_file = 'cookie-notice/cookie-notice.php';
if ( ! is_plugin_active( $plugin_file ) ) {
    echo "  Plugin 'cookie-notice' non actif, installation en cours...\n";
    echo "  (Lancez : wp plugin install cookie-notice --activate)\n";
    echo "  Puis relancez ce script.\n\n";
    // On continue pour configurer les options, le plugin lira ces valeurs à l'activation
}

// ── Options du plugin Cookie Notice ───────────────────────
$cookie_notice_options = [
    // Textes
    'message_text' => 'Ce site utilise des cookies strictement nécessaires à son fonctionnement (panier, commande, session) et des cookies tiers Stripe pour les paiements sécurisés. En continuant votre navigation, vous acceptez leur utilisation.',
    'accept_text'  => 'Accepter',
    'refuse_text'  => 'Refuser les optionnels',
    'read_more_text' => 'En savoir plus',

    // Lien vers la politique de cookies
    'read_more_link' => true,
    'read_more_link_text' => 'Politique de cookies',
    'privacy_redirect'    => true,
    'privacy_redirect_url' => $privacy_url,

    // Position et style
    'position'  => 'bottom',   // bottom ou top
    'css_style' => 'default',
    'hide_effect' => 'fade',

    // Durée de mémorisation du consentement
    'time'       => 'year',  // year, month, 3months, week, day, 12hours, hour, minute
    'time_rejected' => 'month',

    // Bouton refus
    'refuse_consent' => false, // Désactiver si CNIL niveau 1 suffit

    // Analytics (désactivé par défaut)
    'on_scroll'             => false,
    'on_scroll_offset'      => 100,
    'on_click'              => false,
    'deactivation_delete'   => false,

    // Accessibilité
    'see_more'         => true,
    'see_more_opt'     => [
        'text'         => 'En savoir plus',
        'link_type'    => 'page',
        'id'           => $privacy_page ? $privacy_page->ID : 0,
        'link'         => $privacy_url,
        'sync'         => false,
    ],

    // Couleurs adaptées à la charte graphique
    'colors' => [
        'bar'          => '#1A1814',    // Fond sombre (var --color-text)
        'bar_opacity'  => 100,
        'text'         => '#FAF8F5',    // Texte clair (var --color-bg)
        'accept_btn'   => '#8B7355',    // Accent beige doré
        'accept_btn_text' => '#FAF8F5',
        'refuse_btn'      => 'transparent',
        'refuse_btn_text' => '#D4C4A8',
        'read_more_btn'   => 'transparent',
        'read_more_btn_text' => '#D4C4A8',
    ],

    // Conformité CNIL niveau intermédiaire
    'global_cookie'    => false,
    'redirection'      => false,
    'reload_page'      => false,
    'script_placement' => 'header',
    'translate'        => true,
    'debug'            => false,
];

update_option( 'cookie_notice_options', $cookie_notice_options );

// Option de statut (indique si le plugin est configuré)
update_option( 'cookie_notice_status', 'active' );

echo "  • Options du bandeau cookies sauvegardées\n";
echo "  • Couleurs adaptées à la charte (fond sombre, accent beige)\n";
echo "  • Lien vers : $privacy_url\n";

// ── Alternative : bannière CSS native dans le thème ───────
// Si le plugin n'est pas disponible, on ajoute une bannière
// minimaliste via le child theme (dans functions.php)
$fallback_notice = <<<'JS'
(function() {
    if (localStorage.getItem('cookie_consent') === 'accepted') return;
    var bar = document.createElement('div');
    bar.id = 'native-cookie-bar';
    bar.style.cssText = 'position:fixed;bottom:0;left:0;right:0;z-index:9999;background:#1A1814;color:#FAF8F5;padding:1rem 2rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;font-family:sans-serif;font-size:14px;';
    bar.innerHTML = '<span>Ce site utilise des cookies nécessaires à son fonctionnement et au paiement sécurisé. <a href="/politique-cookies/" style="color:#D4C4A8;">En savoir plus</a></span>'
        + '<button onclick="localStorage.setItem(\'cookie_consent\',\'accepted\');document.getElementById(\'native-cookie-bar\').remove()" style="background:#8B7355;color:#fff;border:none;padding:.6em 1.4em;cursor:pointer;font-size:13px;letter-spacing:.05em;text-transform:uppercase;">OK</button>';
    document.body.appendChild(bar);
})();
JS;

// Sauvegarder le fallback JS pour usage possible dans functions.php
update_option( 'kadence_child_cookie_fallback_js', $fallback_notice );

echo "\nBandeau cookies configuré.\n";
echo "\n  Si le plugin 'cookie-notice' n'est pas installé :\n";
echo "  wp plugin install cookie-notice --activate\n";
echo "  wp eval-file setup/configure-cookies.php\n";
echo "\n  Vérifiez le bandeau sur : " . home_url() . "\n";
echo "  Lien politique cookies : $privacy_url\n";
