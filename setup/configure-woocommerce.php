<?php
/**
 * configure-woocommerce.php
 * Lancez avec : wp eval-file setup/configure-woocommerce.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    echo "Ce fichier doit etre lance via WP-CLI : wp eval-file configure-woocommerce.php\n";
    exit( 1 );
}

if ( ! class_exists( 'WooCommerce' ) ) {
    echo "WooCommerce n'est pas actif.\n";
    exit( 1 );
}

echo "> Configuration WooCommerce...\n";

// ── Reglages generaux ──────────────────────────────────────
$general = [
    'woocommerce_default_country'    => 'FR',
    'woocommerce_currency'           => 'EUR',
    'woocommerce_currency_pos'       => 'right_space',  // 100,00 €
    'woocommerce_price_thousand_sep' => ' ',
    'woocommerce_price_decimal_sep'  => ',',
    'woocommerce_price_num_decimals' => '2',
    'woocommerce_weight_unit'        => 'kg',
    'woocommerce_dimension_unit'     => 'cm',
];

foreach ( $general as $key => $value ) {
    update_option( $key, $value );
}

// ── Taxes (desactivees — artiste non assujetti TVA) ────────
update_option( 'woocommerce_calc_taxes',          'no' );
update_option( 'woocommerce_prices_include_tax',  'yes' );

// ── Pages WooCommerce ──────────────────────────────────────
$woo_pages = [
    'shop'      => [ 'title' => 'Boutique',   'slug' => 'boutique',   'content' => '' ],
    'cart'      => [ 'title' => 'Panier',     'slug' => 'panier',     'content' => '<!-- wp:shortcode -->[woocommerce_cart]<!-- /wp:shortcode -->' ],
    'checkout'  => [ 'title' => 'Commander',  'slug' => 'commander',  'content' => '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->' ],
    'myaccount' => [ 'title' => 'Mon compte', 'slug' => 'mon-compte', 'content' => '<!-- wp:shortcode -->[woocommerce_my_account]<!-- /wp:shortcode -->' ],
];

foreach ( $woo_pages as $key => $data ) {
    $existing = wc_get_page_id( $key );
    if ( $existing > 0 && get_post( $existing ) ) {
        echo "  • Page '$key' existe deja (ID: $existing)\n";
        continue;
    }
    $id = wp_insert_post( [
        'post_title'   => $data['title'],
        'post_name'    => $data['slug'],
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_content' => $data['content'],
    ] );
    update_option( 'woocommerce_' . $key . '_page_id', $id );
    echo "  • Page '$key' creee (ID: $id)\n";
}

// ── Gestion des stocks ─────────────────────────────────────
update_option( 'woocommerce_manage_stock',            'yes' );
update_option( 'woocommerce_notify_low_stock',        'yes' );
update_option( 'woocommerce_notify_low_stock_amount', 1 );
update_option( 'woocommerce_notify_no_stock',         'yes' );
update_option( 'woocommerce_notify_no_stock_amount',  0 );
update_option( 'woocommerce_hide_out_of_stock_items', 'no' ); // Afficher "vendu" plutot que cacher

// ── Zones d'expedition ─────────────────────────────────────
// Supprimer les zones existantes
$existing_zones = WC_Shipping_Zones::get_zones();
foreach ( $existing_zones as $zone_data ) {
    $z = new WC_Shipping_Zone( $zone_data['id'] );
    $z->delete();
}

// Zone France
$zone_fr = new WC_Shipping_Zone();
$zone_fr->set_zone_name( 'France' );
$zone_fr->set_zone_order( 1 );
$zone_fr->save();
$zone_fr->add_location( 'FR', 'country' );

$colissimo_id     = $zone_fr->add_shipping_method( 'flat_rate' );
$colissimo_method = WC_Shipping_Zones::get_shipping_method( $colissimo_id );
$colissimo_method->instance_settings['title'] = 'Colissimo';
$colissimo_method->instance_settings['cost']  = '8.50';
update_option( $colissimo_method->get_instance_option_key(), $colissimo_method->instance_settings );

$pickup_id     = $zone_fr->add_shipping_method( 'local_pickup' );
$pickup_method = WC_Shipping_Zones::get_shipping_method( $pickup_id );
$pickup_method->instance_settings['title'] = 'Retrait sur place';
$pickup_method->instance_settings['cost']  = '0';
update_option( $pickup_method->get_instance_option_key(), $pickup_method->instance_settings );

echo "  • Zone France : Colissimo (8,50 EUR) + Retrait sur place\n";

// Zone International (reste du monde)
$zone_intl = new WC_Shipping_Zone();
$zone_intl->set_zone_name( 'International' );
$zone_intl->set_zone_order( 2 );
$zone_intl->save();

$intl_id     = $zone_intl->add_shipping_method( 'flat_rate' );
$intl_method = WC_Shipping_Zones::get_shipping_method( $intl_id );
$intl_method->instance_settings['title'] = 'Livraison internationale';
$intl_method->instance_settings['cost']  = '25.00';
update_option( $intl_method->get_instance_option_key(), $intl_method->instance_settings );

echo "  • Zone International : tarif fixe 25,00 EUR (a ajuster)\n";

// ── Emails ─────────────────────────────────────────────────
$site_name = get_bloginfo( 'name' );
$admin_email = get_option( 'admin_email' );
update_option( 'woocommerce_email_from_name',    $site_name );
update_option( 'woocommerce_email_from_address', $admin_email );

foreach ( [
    'woocommerce_new_order_settings',
    'woocommerce_cancelled_order_settings',
    'woocommerce_customer_on_hold_order_settings',
    'woocommerce_customer_completed_order_settings',
    'woocommerce_customer_invoice_settings',
] as $email_option ) {
    $settings = get_option( $email_option, [] );
    $settings['enabled'] = 'yes';
    update_option( $email_option, $settings );
}

// ── Boutique ───────────────────────────────────────────────
update_option( 'woocommerce_catalog_columns',        3 );
update_option( 'woocommerce_catalog_rows',           4 );
update_option( 'woocommerce_default_catalog_orderby','date' );
update_option( 'woocommerce_enable_reviews',         'no' );  // Pas de notes/avis
update_option( 'woocommerce_enable_lightbox',        'yes' );

echo "\nWooCommerce configure.\n";
echo "  Pensez a configurer Stripe dans WooCommerce > Reglages > Paiements\n";
