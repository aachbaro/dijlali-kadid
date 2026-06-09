<?php
/**
 * configure-fix-woo-slugs.php
 * Lance avec : wp eval-file setup/configure-fix-woo-slugs.php
 *
 * Corrige les slugs des pages WooCommerce en français.
 * Par défaut WooCommerce crée cart/ checkout/ my-account/
 * Ce script les renomme en panier/ commander/ mon-compte/
 * et met à jour les options WooCommerce correspondantes.
 */

if ( ! defined( 'ABSPATH' ) ) {
    echo "Ce fichier doit etre lance via WP-CLI.\n";
    exit( 1 );
}

echo "> Correction des slugs WooCommerce en français...\n";

$pages = [
    'cart'     => [ 'slug' => 'panier',     'title' => 'Panier',     'option' => 'woocommerce_cart_page_id' ],
    'checkout' => [ 'slug' => 'commander',  'title' => 'Commander',  'option' => 'woocommerce_checkout_page_id' ],
    'my-account' => [ 'slug' => 'mon-compte', 'title' => 'Mon compte', 'option' => 'woocommerce_myaccount_page_id' ],
];

foreach ( $pages as $english_slug => $config ) {
    $french_slug = $config['slug'];
    $option_key  = $config['option'];

    // 1. Chercher par option WooCommerce (priorité)
    $page_id = (int) get_option( $option_key, 0 );

    // 2. Fallback : chercher par slug anglais
    if ( ! $page_id ) {
        $page = get_page_by_path( $english_slug );
        $page_id = $page ? $page->ID : 0;
    }

    // 3. Fallback : chercher par slug français (déjà corrigé)
    if ( ! $page_id ) {
        $page = get_page_by_path( $french_slug );
        $page_id = $page ? $page->ID : 0;
    }

    if ( ! $page_id ) {
        // Créer la page si elle n'existe pas
        $page_id = wp_insert_post( [
            'post_title'   => $config['title'],
            'post_name'    => $french_slug,
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
        ] );
        echo "  • Page '{$french_slug}' créée (ID {$page_id})\n";
    } else {
        $current_slug = get_post_field( 'post_name', $page_id );
        if ( $current_slug !== $french_slug ) {
            wp_update_post( [
                'ID'        => $page_id,
                'post_name' => $french_slug,
                'post_title' => $config['title'],
            ] );
            echo "  • Slug de la page ID {$page_id} : '{$current_slug}' → '{$french_slug}'\n";
        } else {
            echo "  • Page '{$french_slug}' (ID {$page_id}) : slug déjà correct\n";
        }
    }

    // Mettre à jour l'option WooCommerce si pas encore fait
    $current_option = (int) get_option( $option_key, 0 );
    if ( $current_option !== $page_id ) {
        update_option( $option_key, $page_id );
        echo "    Option '{$option_key}' mise à jour → ID {$page_id}\n";
    }
}

// Flush des rewrite rules pour les nouveaux slugs
flush_rewrite_rules();
echo "\n> Rewrite rules vidées.\n";
echo "> Tunnel de commande :\n";
echo "  Panier    : " . home_url( '/panier/' ) . "\n";
echo "  Commander : " . home_url( '/commander/' ) . "\n";
echo "  Mon compte: " . home_url( '/mon-compte/' ) . "\n";
