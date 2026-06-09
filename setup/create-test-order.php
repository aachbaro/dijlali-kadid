<?php
/**
 * Helper WP-CLI pour test-payment-flow.sh.
 * Usage: wp eval-file setup/create-test-order.php PRODUCT_ID
 */

if ( ! defined( 'ABSPATH' ) ) {
    fwrite( STDERR, "Ce fichier doit etre lance via WP-CLI.\n" );
    exit( 1 );
}

$product_id = isset( $args[0] ) ? (int) $args[0] : 0;
$product    = $product_id ? wc_get_product( $product_id ) : false;

if ( ! $product ) {
    fwrite( STDERR, "Produit $product_id introuvable ou invalide.\n" );
    exit( 1 );
}

$order = wc_create_order( [ 'status' => 'pending' ] );
if ( is_wp_error( $order ) ) {
    fwrite( STDERR, 'Erreur creation commande : ' . $order->get_error_message() . "\n" );
    exit( 1 );
}

$added = $order->add_product( $product, 1 );
if ( is_wp_error( $added ) ) {
    fwrite( STDERR, 'Erreur ajout produit : ' . $added->get_error_message() . "\n" );
    exit( 1 );
}

$order->set_billing_first_name( 'Jean' );
$order->set_billing_last_name( 'Test' );
$order->set_billing_email( 'jean.test@example.com' );
$order->set_payment_method( 'stripe_cc' );
$order->set_payment_method_title( 'Carte bancaire (Test)' );
$order->calculate_totals();
$order->save();

echo $order->get_id();
