<?php
/**
 * update-artist-name.php
 * wp eval-file setup/update-artist-name.php
 *
 * Met à jour le nom de l'artiste dans les options structurées du site.
 */

if ( ! defined( 'ABSPATH' ) ) {
    echo "Lancez via WP-CLI : wp eval-file setup/update-artist-name.php\n";
    exit( 1 );
}

global $wpdb;

$artist_first = 'Djilali';
$artist_last  = 'Kadid';
$artist_full  = "$artist_first $artist_last";
$site_name    = "Galerie $artist_full";

update_option( 'blogname', $site_name );
update_option( 'blogdescription', 'Peintures originales, atelier et prestations culturelles' );
update_option( 'woocommerce_email_from_name', $site_name );

$admin = get_user_by( 'login', 'admin' );
if ( $admin ) {
    wp_update_user( [
        'ID'           => $admin->ID,
        'first_name'   => $artist_first,
        'last_name'    => $artist_last,
        'display_name' => $artist_full,
    ] );
    echo "Admin mis à jour : $artist_full\n";
}

$amelia_users = $wpdb->prefix . 'amelia_users';
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $amelia_users ) ) === $amelia_users ) {
    $wpdb->update(
        $amelia_users,
        [
            'firstName' => $artist_first,
            'lastName'  => $artist_last,
        ],
        [ 'type' => 'provider' ],
        [ '%s', '%s' ],
        [ '%s' ]
    );
    echo "Employé Amelia mis à jour : $artist_full\n";
}

echo "Nom du site : " . get_option( 'blogname' ) . "\n";
