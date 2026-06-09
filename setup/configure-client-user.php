<?php
/**
 * configure-client-user.php
 * Lance avec : wp eval-file setup/configure-client-user.php
 *
 * Cree/met a jour un role client dedie a l'interface simplifiee.
 * Variables optionnelles :
 * CLIENT_USER, CLIENT_EMAIL, CLIENT_PASSWORD, CLIENT_DISPLAY_NAME.
 */

if ( ! defined( 'ABSPATH' ) ) {
    echo "Ce fichier doit etre lance via WP-CLI.\n";
    exit( 1 );
}

echo "> Configuration du compte client Galerie...\n";

function galerie_client_setup_log( string $message ): void {
    echo "  • {$message}\n";
}

if ( function_exists( 'galerie_client_ensure_role' ) ) {
    galerie_client_ensure_role();
    galerie_client_setup_log( "Role 'Client Galerie' synchronise depuis le theme." );
} else {
    add_role( 'galerie_client', 'Client Galerie', [
        'read'                  => true,
        'upload_files'          => true,
        'edit_posts'            => true,
        'edit_others_posts'     => true,
        'edit_published_posts'  => true,
        'publish_posts'         => true,
        'delete_posts'          => true,
        'delete_others_posts'   => true,
        'delete_published_posts' => true,
        'edit_products'         => true,
        'edit_others_products'  => true,
        'edit_published_products' => true,
        'publish_products'      => true,
        'delete_products'       => true,
        'delete_others_products' => true,
        'delete_published_products' => true,
        'manage_woocommerce'    => true,
        'galerie_client_access' => true,
    ] );
    galerie_client_setup_log( "Role 'Client Galerie' cree avec les capacites de base." );
}

$login        = getenv( 'CLIENT_USER' ) ?: 'client';
$email        = getenv( 'CLIENT_EMAIL' ) ?: 'client@test.local';
$password     = getenv( 'CLIENT_PASSWORD' ) ?: 'client123';
$display_name = getenv( 'CLIENT_DISPLAY_NAME' ) ?: 'Compte client galerie';

$user = get_user_by( 'login', $login );
if ( ! $user ) {
    $user_id = wp_create_user( $login, $password, $email );
    if ( is_wp_error( $user_id ) ) {
        echo "Erreur : " . $user_id->get_error_message() . "\n";
        exit( 1 );
    }

    $user = get_user_by( 'id', $user_id );
    galerie_client_setup_log( "Utilisateur '{$login}' cree." );
} else {
    $user_id = (int) $user->ID;
    wp_update_user( [
        'ID'         => $user_id,
        'user_email' => $email,
    ] );
    if ( getenv( 'CLIENT_PASSWORD' ) ) {
        wp_set_password( $password, $user_id );
        galerie_client_setup_log( "Mot de passe de '{$login}' mis a jour depuis CLIENT_PASSWORD." );
    }
    galerie_client_setup_log( "Utilisateur '{$login}' deja existant, email/role mis a jour." );
}

wp_update_user( [
    'ID'           => (int) $user->ID,
    'display_name' => $display_name,
] );

$wp_user = new WP_User( (int) $user->ID );
$wp_user->set_role( 'galerie_client' );
galerie_client_setup_log( "Role 'Client Galerie' assigne a '{$login}'." );

echo "\n> Compte client pret :\n";
echo "  Login : {$login}\n";
echo "  Email : {$email}\n";
if ( ! getenv( 'CLIENT_PASSWORD' ) ) {
    echo "  Mot de passe local par defaut : client123\n";
    echo "  Important : changer ce mot de passe avant production.\n";
}
echo "  Connexion : " . admin_url() . "\n";
