<?php
/**
 * configure-simplify-nav.php
 * Lance avec : wp eval-file setup/configure-simplify-nav.php
 *
 * Simplifie la navigation publique :
 * - Galerie devient l'unique page catalogue WooCommerce.
 * - Boutique et Reservations sont conservees en brouillon.
 * - Le shortcode Amelia est ajoute a Prestations.
 * - Le menu principal devient : Galerie | Blog | Prestations | A propos | Contact.
 * - Les anciennes URLs /boutique/ et /reservations/ redirigent en 301.
 */

if ( ! defined( 'ABSPATH' ) ) {
    echo "Ce fichier doit etre lance via WP-CLI.\n";
    exit( 1 );
}

echo "> Simplification de la navigation publique...\n";

function galerie_simplify_log( string $message ): void {
    echo "  • {$message}\n";
}

function galerie_simplify_find_page( string $slug ): ?WP_Post {
    $pages = get_posts( [
        'name'           => $slug,
        'post_type'      => 'page',
        'post_status'    => [ 'publish', 'draft', 'pending', 'private', 'future' ],
        'posts_per_page' => 1,
    ] );

    if ( ! empty( $pages ) && $pages[0] instanceof WP_Post ) {
        return $pages[0];
    }

    $page = get_page_by_path( $slug, OBJECT, 'page' );
    return $page instanceof WP_Post ? $page : null;
}

function galerie_simplify_draft_page( ?WP_Post $page, string $label ): void {
    if ( ! $page ) {
        galerie_simplify_log( "Page {$label} introuvable, aucune mise en brouillon." );
        return;
    }

    if ( 'draft' === $page->post_status ) {
        galerie_simplify_log( "Page {$label} deja en brouillon." );
        return;
    }

    $result = wp_update_post( [
        'ID'          => $page->ID,
        'post_status' => 'draft',
    ], true );

    if ( is_wp_error( $result ) ) {
        galerie_simplify_log( "Erreur pendant la mise en brouillon de {$label} : " . $result->get_error_message() );
        return;
    }

    galerie_simplify_log( "Page {$label} mise en brouillon, sans suppression." );
}

function galerie_simplify_add_booking_to_services( ?WP_Post $prestations, ?WP_Post $reservations ): void {
    if ( ! $prestations ) {
        galerie_simplify_log( "Page Prestations introuvable, shortcode Amelia non ajoute." );
        return;
    }

    $content = (string) $prestations->post_content;
    if ( false !== stripos( $content, '[ameliabooking' ) ) {
        galerie_simplify_log( "Page Prestations contient deja le shortcode Amelia." );
        return;
    }

    $shortcode = '[ameliabooking]';
    if ( $reservations && preg_match( '/\[ameliabooking[^\]]*\]/i', (string) $reservations->post_content, $matches ) ) {
        $shortcode = $matches[0];
        galerie_simplify_log( "Shortcode Amelia recupere depuis la page Reservations." );
    } else {
        galerie_simplify_log( "Shortcode Amelia standard utilise : [ameliabooking]." );
    }

    $booking_block = "\n\n<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Réserver une prestation</h2>\n<!-- /wp:heading -->\n\n<!-- wp:shortcode -->\n{$shortcode}\n<!-- /wp:shortcode -->\n";

    $result = wp_update_post( [
        'ID'           => $prestations->ID,
        'post_content' => rtrim( $content ) . $booking_block,
    ], true );

    if ( is_wp_error( $result ) ) {
        galerie_simplify_log( "Erreur pendant la mise a jour de Prestations : " . $result->get_error_message() );
        return;
    }

    galerie_simplify_log( "Bloc de reservation Amelia ajoute a la fin de Prestations." );
}

function galerie_simplify_remove_gallery_shortcode( ?WP_Post $gallery ): void {
    if ( ! $gallery ) {
        galerie_simplify_log( "Page Galerie introuvable, shortcode catalogue non modifie." );
        return;
    }

    $content = (string) $gallery->post_content;
    $updated = preg_replace(
        '#\s*<section class="mvp-section">\s*\[products[^\]]*category=(["\'])oeuvres-originales\1[^\]]*\]\s*</section>#i',
        '',
        $content
    );

    if ( null === $updated ) {
        $updated = $content;
    }

    $updated = preg_replace(
        '#\s*<!-- wp:shortcode -->\s*\[products[^\]]*category=(["\'])oeuvres-originales\1[^\]]*\]\s*<!-- /wp:shortcode -->#i',
        '',
        $updated
    );

    if ( null === $updated ) {
        $updated = $content;
    }

    $updated = preg_replace(
        '#\[products[^\]]*category=(["\'])oeuvres-originales\1[^\]]*\]#i',
        '',
        $updated
    );

    if ( null === $updated || $updated === $content ) {
        galerie_simplify_log( "Page Galerie deja nettoyee : aucun shortcode catalogue en doublon." );
        return;
    }

    $result = wp_update_post( [
        'ID'           => $gallery->ID,
        'post_content' => trim( $updated ),
    ], true );

    if ( is_wp_error( $result ) ) {
        galerie_simplify_log( "Erreur pendant le nettoyage de Galerie : " . $result->get_error_message() );
        return;
    }

    galerie_simplify_log( "Shortcode catalogue retire de Galerie pour eviter le double affichage WooCommerce." );
}

function galerie_simplify_update_redirects( array $pairs ): array {
    $redirects = get_option( 'galerie_legacy_redirects', [] );
    if ( ! is_array( $redirects ) ) {
        $redirects = [];
    }

    foreach ( $pairs as $source => $target ) {
        $source_path = '/' . trim( (string) $source, '/' ) . '/';
        $target_path = '/' . trim( (string) $target, '/' ) . '/';

        if ( isset( $redirects[ $source_path ] ) && $redirects[ $source_path ] === $target_path ) {
            galerie_simplify_log( "Redirection 301 deja active : {$source_path} -> {$target_path}" );
            continue;
        }

        $redirects[ $source_path ] = $target_path;
        galerie_simplify_log( "Redirection 301 ajoutee : {$source_path} -> {$target_path}" );
    }

    update_option( 'galerie_legacy_redirects', $redirects, false );

    return $redirects;
}

function galerie_simplify_primary_menu_id(): int {
    $menu = wp_get_nav_menu_object( 'Menu principal' );
    if ( $menu && ! is_wp_error( $menu ) ) {
        return (int) $menu->term_id;
    }

    $locations = get_nav_menu_locations();
    foreach ( [ 'primary', 'mobile' ] as $location ) {
        if ( ! empty( $locations[ $location ] ) ) {
            return (int) $locations[ $location ];
        }
    }

    $menu_id = wp_create_nav_menu( 'Menu principal' );
    galerie_simplify_log( "Menu principal cree." );

    return (int) $menu_id;
}

function galerie_simplify_assign_menu_locations( int $menu_id ): void {
    $locations = get_theme_mod( 'nav_menu_locations', [] );
    if ( ! is_array( $locations ) ) {
        $locations = [];
    }

    foreach ( get_registered_nav_menus() as $location => $description ) {
        $haystack = strtolower( $location . ' ' . $description );
        if (
            str_contains( $haystack, 'primary' )
            || str_contains( $haystack, 'main' )
            || str_contains( $haystack, 'principal' )
            || str_contains( $haystack, 'mobile' )
        ) {
            $locations[ $location ] = $menu_id;
        }
    }

    if ( empty( $locations['primary'] ) ) {
        $locations['primary'] = $menu_id;
    }

    set_theme_mod( 'nav_menu_locations', $locations );

    if ( function_exists( 'pll_languages_list' ) ) {
        $language_slugs = pll_languages_list( [ 'fields' => 'slug' ] );
        $pll_options    = get_option( 'polylang', [] );
        $theme_slug     = get_option( 'stylesheet' );

        if ( is_array( $pll_options ) && $theme_slug ) {
            foreach ( [ 'primary', 'mobile' ] as $location ) {
                foreach ( $language_slugs ?: [ 'fr' ] as $language_slug ) {
                    $pll_options['nav_menus'][ $theme_slug ][ $location ][ $language_slug ] = $menu_id;
                }
            }
            update_option( 'polylang', $pll_options );
        }
    }

    galerie_simplify_log( "Menu principal assigne aux emplacements du theme." );
}

function galerie_simplify_rebuild_menu( array $final_items ): int {
    $menu_id = galerie_simplify_primary_menu_id();

    $existing_items = wp_get_nav_menu_items( $menu_id );
    if ( $existing_items ) {
        foreach ( $existing_items as $item ) {
            wp_delete_post( (int) $item->ID, true );
        }
        galerie_simplify_log( "Anciens items du menu principal retires." );
    }

    foreach ( $final_items as $slug => $label ) {
        if ( is_int( $slug ) ) {
            $slug = (string) $label;
            $label = '';
        }

        $page = galerie_simplify_find_page( $slug );
        if ( ! $page ) {
            galerie_simplify_log( "Page {$slug} introuvable, item de menu ignore." );
            continue;
        }

        $title = $label ?: $page->post_title;

        wp_update_nav_menu_item( $menu_id, 0, [
            'menu-item-title'     => $title,
            'menu-item-object-id' => $page->ID,
            'menu-item-object'    => 'page',
            'menu-item-type'      => 'post_type',
            'menu-item-status'    => 'publish',
        ] );
        galerie_simplify_log( "Item de menu ajoute : {$title}." );
    }

    galerie_simplify_assign_menu_locations( $menu_id );

    return $menu_id;
}

$gallery      = galerie_simplify_find_page( 'galerie' );
$shop         = galerie_simplify_find_page( 'boutique' );
$prestations  = galerie_simplify_find_page( 'prestations' );
$reservations = galerie_simplify_find_page( 'reservations' );

if ( $gallery ) {
    $current_shop_page_id = (int) get_option( 'woocommerce_shop_page_id', 0 );

    if ( (int) $gallery->ID === $current_shop_page_id ) {
        galerie_simplify_log( "WooCommerce utilise deja Galerie comme page boutique." );
    } else {
        update_option( 'woocommerce_shop_page_id', (int) $gallery->ID );
        $from = $shop && (int) $shop->ID === $current_shop_page_id ? 'Boutique' : 'ancienne page boutique';
        galerie_simplify_log( "Page boutique WooCommerce basculee de {$from} vers Galerie." );
    }
} else {
    galerie_simplify_log( "Page Galerie introuvable, option WooCommerce non modifiee." );
}

galerie_simplify_remove_gallery_shortcode( $gallery );
galerie_simplify_add_booking_to_services( $prestations, $reservations );

galerie_simplify_draft_page( $shop, 'Boutique' );
galerie_simplify_draft_page( $reservations, 'Reservations' );

$redirects = galerie_simplify_update_redirects( [
    '/boutique/'     => '/galerie/',
    '/reservations/' => '/prestations/',
] );

$menu_id = galerie_simplify_rebuild_menu( [
    'galerie'     => 'Galerie',
    'blog'        => 'Blog',
    'prestations' => 'Prestations',
    'a-propos'    => 'À propos',
    'contact'     => 'Contact',
] );

flush_rewrite_rules();
galerie_simplify_log( "Rewrite rules videes." );

echo "\n> Menu final :\n";
$final_items = wp_get_nav_menu_items( $menu_id, [ 'orderby' => 'menu_order' ] );
if ( $final_items ) {
    foreach ( $final_items as $item ) {
        echo "  - {$item->title}\n";
    }
}

echo "\n> Redirections actives :\n";
foreach ( [ '/boutique/' => '/galerie/', '/reservations/' => '/prestations/' ] as $source => $target ) {
    $source_path = '/' . trim( $source, '/' ) . '/';
    $target_path = $redirects[ $source_path ] ?? '';
    if ( $target_path ) {
        echo '  - ' . home_url( $source_path ) . ' -> ' . home_url( $target_path ) . "\n";
    }
}

echo "\n> Navigation simplifiee terminee.\n";
