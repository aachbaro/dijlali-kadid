<?php
/**
 * Rend la page Galerie plus immersive :
 * - supprime le texte d'introduction de la page shop ;
 * - force WooCommerce a utiliser Galerie comme page boutique.
 *
 * Usage:
 *   wp eval-file setup/configure-gallery-immersive.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function galerie_immersive_log( string $message ): void {
    echo "  • {$message}\n";
}

$gallery = get_page_by_path( 'galerie', OBJECT, 'page' );

if ( ! $gallery instanceof WP_Post ) {
    galerie_immersive_log( 'Page Galerie introuvable.' );
    return;
}

if ( '' === trim( (string) $gallery->post_content ) ) {
    galerie_immersive_log( 'Le contenu de Galerie est deja vide.' );
} else {
    $updated = wp_update_post( [
        'ID'           => $gallery->ID,
        'post_content' => '',
    ], true );

    if ( is_wp_error( $updated ) ) {
        galerie_immersive_log( 'Erreur pendant la mise a jour de Galerie : ' . $updated->get_error_message() );
    } else {
        galerie_immersive_log( 'Texte introductif de Galerie retire.' );
    }
}

$current_shop_page_id = (int) get_option( 'woocommerce_shop_page_id', 0 );
if ( (int) $gallery->ID === $current_shop_page_id ) {
    galerie_immersive_log( 'WooCommerce utilise deja Galerie comme page catalogue.' );
} else {
    update_option( 'woocommerce_shop_page_id', (int) $gallery->ID );
    galerie_immersive_log( 'Galerie definie comme page catalogue WooCommerce.' );
}

flush_rewrite_rules();
galerie_immersive_log( 'Rewrite rules videes.' );

echo "\n> Galerie immersive active : " . home_url( '/galerie/' ) . "\n";
