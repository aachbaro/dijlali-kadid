<?php
/**
 * configure-stripe-test.php
 * Lancez avec : wp eval-file setup/configure-stripe-test.php
 *
 * Configure le plugin woo-stripe-payment en mode TEST.
 * Les clés test ne débitent jamais de vrais comptes.
 */

if ( ! defined( 'ABSPATH' ) ) {
    echo "Ce fichier doit etre lance via WP-CLI.\n";
    exit( 1 );
}

// ── Lecture des clés depuis .env.local ────────────────────
$env_file = dirname( ABSPATH ) . '/setup/.env.local';
if ( ! file_exists( $env_file ) ) {
    // Chercher dans ABSPATH aussi (si on est dans public/)
    $env_file = ABSPATH . '../setup/.env.local';
}
if ( ! file_exists( $env_file ) ) {
    $env_file = ABSPATH . 'setup/.env.local';
}

$test_pk = '';
$test_sk = '';

if ( file_exists( $env_file ) ) {
    foreach ( file( $env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
        if ( str_starts_with( trim( $line ), '#' ) || ! str_contains( $line, '=' ) ) {
            continue;
        }
        [ $k, $v ] = explode( '=', $line, 2 );
        $k = trim( $k );
        $v = trim( $v );
        if ( $k === 'STRIPE_TEST_PUBLIC_KEY' ) { $test_pk = $v; }
        if ( $k === 'STRIPE_TEST_SECRET_KEY' ) { $test_sk = $v; }
    }
}

// Fallback : variables d'environnement shell
if ( ! $test_pk ) { $test_pk = getenv( 'STRIPE_TEST_PUBLIC_KEY' ) ?: ''; }
if ( ! $test_sk ) { $test_sk = getenv( 'STRIPE_TEST_SECRET_KEY' ) ?: ''; }

if ( ! $test_pk || str_contains( $test_pk, 'REMPLACER' ) ) {
    echo "\nATTENTION : STRIPE_TEST_PUBLIC_KEY non configuree dans .env.local\n";
    echo "  Ouvrez setup/.env.local et remplissez STRIPE_TEST_PUBLIC_KEY et STRIPE_TEST_SECRET_KEY\n";
    echo "  Obtenir les cles sur : stripe.com → Developpeurs → Cles API → Mode test\n\n";
    // On continue quand meme pour configurer le mode test et activer le gateway
}

echo "> Configuration Stripe mode TEST...\n";

// ── Detecter le gateway Stripe installe ───────────────────
// Le plugin woo-stripe-payment (Payment Plugins) utilise plusieurs gateway IDs
$stripe_gateway_ids = [
    'stripe',           // ID générique possible
    'stripe_cc',        // Carte de crédit (Payment Plugins)
    'stripe_sepa',      // SEPA
    'stripe_ideal',     // iDEAL
];

$configured = false;

// Approche 1 : via les instances de gateways WooCommerce
if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
    $gateways = WC()->payment_gateways()->payment_gateways();
    foreach ( $gateways as $gw_id => $gateway ) {
        if ( ! str_contains( strtolower( $gw_id ), 'stripe' ) ) {
            continue;
        }

        $settings = get_option( 'woocommerce_' . $gw_id . '_settings', [] );

        // Proprietes communes aux deux plugins Stripe majeurs
        $settings['enabled']             = 'yes';
        $settings['testmode']            = 'yes';

        // Format pour le plugin officiel WooCommerce Stripe
        $settings['test_publishable_key'] = $test_pk;
        $settings['test_secret_key']      = $test_sk;
        $settings['publishable_key']      = '';
        $settings['secret_key']           = '';

        // Format pour woo-stripe-payment (Payment Plugins)
        $settings['test_pub_key']         = $test_pk;
        $settings['test_secret_key']      = $test_sk;
        $settings['live_pub_key']         = '';
        $settings['live_secret_key']      = '';
        $settings['mode']                 = 'test';

        update_option( 'woocommerce_' . $gw_id . '_settings', $settings );
        echo "  • Gateway '$gw_id' configure en mode test\n";
        $configured = true;
    }
}

// Approche 2 : options specifiques au plugin woo-stripe-payment (Payment Plugins)
// Ce plugin stocke les clés API dans 'woocommerce_stripe_api_settings' (onglet API Settings)
// et les settings globaux dans 'wc_stripe_settings'.
$api_settings = get_option( 'woocommerce_stripe_api_settings', [] );
$api_settings = array_merge( $api_settings, [
    'mode'            => 'test',
    'test_pub_key'    => $test_pk,
    'test_secret_key' => $test_sk,
    'live_pub_key'    => '',
    'live_secret_key' => '',
] );
update_option( 'woocommerce_stripe_api_settings', $api_settings );
echo "  • Option 'woocommerce_stripe_api_settings' mise a jour (onglet API Settings)\n";

$stripe_plugin_option = get_option( 'wc_stripe_settings', [] );
$stripe_plugin_option = array_merge( $stripe_plugin_option, [
    'mode'            => 'test',
    'test_pub_key'    => $test_pk,
    'test_secret_key' => $test_sk,
    'live_pub_key'    => '',
    'live_secret_key' => '',
] );
update_option( 'wc_stripe_settings', $stripe_plugin_option );
echo "  • Option 'wc_stripe_settings' mise a jour\n";

// Approche 3 : option du plugin officiel woocommerce-gateway-stripe
$official_settings = get_option( 'woocommerce_stripe_settings', [] );
if ( ! empty( $official_settings ) ) {
    $official_settings['testmode']           = 'yes';
    $official_settings['test_publishable_key'] = $test_pk;
    $official_settings['test_secret_key']    = $test_sk;
    $official_settings['publishable_key']    = '';
    $official_settings['secret_key']         = '';
    $official_settings['enabled']            = 'yes';
    update_option( 'woocommerce_stripe_settings', $official_settings );
    echo "  • Option 'woocommerce_stripe_settings' mise a jour\n";
    $configured = true;
}

// S'assurer que WooCommerce affiche Stripe dans les moyens de paiement actifs
$active_gateways = get_option( 'woocommerce_gateway_order', [] );
if ( ! in_array( 'stripe_cc', $active_gateways, true ) && ! in_array( 'stripe', $active_gateways, true ) ) {
    $active_gateways[] = 'stripe_cc';
    $active_gateways[] = 'stripe';
    update_option( 'woocommerce_gateway_order', $active_gateways );
}

echo "\nStripe mode TEST configure.\n";

if ( $test_pk && ! str_contains( $test_pk, 'REMPLACER' ) ) {
    echo "  Cle publique test : " . substr( $test_pk, 0, 12 ) . "...\n";
} else {
    echo "\n  IMPORTANT : configurez les cles dans .env.local puis relancez.\n";
    echo "  Configuration manuelle : WooCommerce > Reglages > Paiements > Stripe\n";
}

echo "\n  Cartes de test Stripe :\n";
echo "  Paiement reussi : 4242 4242 4242 4242  exp: 12/34  CVV: 123\n";
echo "  Paiement refuse : 4000 0000 0000 0002  exp: 12/34  CVV: 123\n";
echo "  Authentification 3DS : 4000 0025 0000 3155\n";
