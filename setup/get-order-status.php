<?php
/**
 * Helper WP-CLI pour test-payment-flow.sh.
 * Usage: wp eval-file setup/get-order-status.php ORDER_ID
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit( 1 );
}

$order_id = isset( $args[0] ) ? (int) $args[0] : 0;
$order    = $order_id ? wc_get_order( $order_id ) : false;

if ( ! $order ) {
    exit( 1 );
}

echo $order->get_status();
