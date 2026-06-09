<?php
/**
 * Ajuste la home et l'ordre du menu principal.
 *
 * Usage:
 *   wp eval-file setup/configure-home-refinements.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function galerie_home_log( string $message ): void {
    echo "  • {$message}\n";
}

function galerie_home_find_page( string $slug ): ?WP_Post {
    $page = get_page_by_path( $slug, OBJECT, 'page' );

    return $page instanceof WP_Post ? $page : null;
}

function galerie_home_url( string $path ): string {
    return esc_url( home_url( '/' . trim( $path, '/' ) . '/' ) );
}

function galerie_home_hero_url(): string {
    if ( function_exists( 'wc_get_products' ) ) {
        $products = wc_get_products( [
            'limit'    => 1,
            'status'   => 'publish',
            'orderby'  => 'date',
            'order'    => 'DESC',
            'category' => [ 'oeuvres-originales' ],
        ] );

        foreach ( $products as $product ) {
            if ( $product instanceof WC_Product && $product->get_image_id() ) {
                $url = wp_get_attachment_image_url( $product->get_image_id(), 'full' );
                if ( $url ) {
                    return esc_url( $url );
                }
            }
        }
    }

    $front_id = (int) get_option( 'page_on_front', 0 );
    if ( $front_id && has_post_thumbnail( $front_id ) ) {
        $url = get_the_post_thumbnail_url( $front_id, 'full' );
        if ( $url ) {
            return esc_url( $url );
        }
    }

    return '';
}

function galerie_home_primary_menu_id(): int {
    $locations = get_nav_menu_locations();
    foreach ( [ 'primary', 'mobile' ] as $location ) {
        if ( ! empty( $locations[ $location ] ) ) {
            return (int) $locations[ $location ];
        }
    }

    $menu = wp_get_nav_menu_object( 'Menu principal' );
    if ( $menu ) {
        return (int) $menu->term_id;
    }

    return (int) wp_create_nav_menu( 'Menu principal' );
}

function galerie_home_assign_menu_locations( int $menu_id ): void {
    $locations = get_theme_mod( 'nav_menu_locations', [] );

    foreach ( get_registered_nav_menus() as $location => $description ) {
        $haystack = strtolower( $location . ' ' . $description );
        if (
            str_contains( $haystack, 'primary' ) ||
            str_contains( $haystack, 'main' ) ||
            str_contains( $haystack, 'principal' )
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
}

function galerie_home_rebuild_menu(): int {
    $menu_id = galerie_home_primary_menu_id();

    $existing_items = wp_get_nav_menu_items( $menu_id );
    if ( $existing_items ) {
        foreach ( $existing_items as $item ) {
            wp_delete_post( (int) $item->ID, true );
        }
        galerie_home_log( 'Anciens items du menu principal retires.' );
    }

    $items = [
        'galerie'     => 'Galerie',
        'blog'        => 'Blog',
        'prestations' => 'Prestations',
        'a-propos'    => 'À propos',
        'contact'     => 'Contact',
    ];

    foreach ( $items as $slug => $label ) {
        $page = galerie_home_find_page( $slug );
        if ( ! $page ) {
            galerie_home_log( "Page {$slug} introuvable, item ignore." );
            continue;
        }

        wp_update_nav_menu_item( $menu_id, 0, [
            'menu-item-title'     => $label,
            'menu-item-object-id' => $page->ID,
            'menu-item-object'    => 'page',
            'menu-item-type'      => 'post_type',
            'menu-item-status'    => 'publish',
        ] );
        galerie_home_log( "Item de menu ajoute : {$label}." );
    }

    galerie_home_assign_menu_locations( $menu_id );

    return $menu_id;
}

$gallery_url  = galerie_home_url( 'galerie' );
$services_url = galerie_home_url( 'prestations' );
$blog_url     = galerie_home_url( 'blog' );
$hero_url     = galerie_home_hero_url();

$home_content = <<<HTML
<section class="mvp-hero" style="background-image:linear-gradient(90deg, rgba(26,24,20,.82), rgba(26,24,20,.22)), url('{$hero_url}');">
    <div class="mvp-hero__inner">
        <h1 class="mvp-hero-title--stacked"><span>Galerie</span><span>Djilali</span><span>Kadid</span></h1>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer laoreet, eros eu facilisis luctus, neque libero posuere arcu.</p>
        <div class="mvp-actions">
            <a class="button mvp-hero-button mvp-hero-button--primary" href="{$gallery_url}">Voir les oeuvres</a>
            <a class="button mvp-hero-button mvp-hero-button--secondary" href="{$services_url}">Découvrir les prestations</a>
        </div>
    </div>
</section>
<section class="mvp-section mvp-section--intro">
    <h2>Biographie courte</h2>
    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer laoreet, eros eu facilisis luctus, neque libero posuere arcu, vitae tempor mi sapien non nibh.</p>
</section>
<section class="mvp-section">
    <div class="mvp-section__head">
        <div>
            <h2>Galerie</h2>
        </div>
        <a href="{$gallery_url}">Toute la galerie</a>
    </div>
    [products category="oeuvres-originales" limit="6" columns="3" orderby="date" order="DESC"]
</section>
<section class="mvp-section mvp-home-services">
    <div class="mvp-section__head">
        <div>
            <h2>Prestations</h2>
        </div>
        <a href="{$services_url}">Voir les prestations</a>
    </div>
    [products category="prestations-culturelles" limit="3" columns="3" orderby="date" order="DESC"]
</section>
<section class="mvp-section">
    <div class="mvp-section__head">
        <div>
            <p class="mvp-kicker">Journal</p>
            <h2>Derniers articles</h2>
        </div>
        <a href="{$blog_url}">Lire le blog</a>
    </div>
    <!-- wp:latest-posts {"postsToShow":3,"displayPostContent":true,"excerptLength":26,"displayFeaturedImage":true,"featuredImageSizeSlug":"medium","addLinkToFeaturedImage":true,"className":"mvp-latest-posts"} /-->
</section>
HTML;

$home_page_id = (int) get_option( 'page_on_front', 0 );
$home_page    = $home_page_id ? get_post( $home_page_id ) : null;

if ( ! $home_page instanceof WP_Post ) {
    $home_page = galerie_home_find_page( 'accueil' );
}

if ( ! $home_page instanceof WP_Post ) {
    $home_page_id = wp_insert_post( [
        'post_title'   => 'Accueil',
        'post_name'    => 'accueil',
        'post_type'    => 'page',
        'post_status'  => 'publish',
        'post_content' => $home_content,
    ], true );

    if ( is_wp_error( $home_page_id ) ) {
        galerie_home_log( 'Erreur pendant la creation de la page Accueil : ' . $home_page_id->get_error_message() );
        $home_page_id = 0;
    } else {
        galerie_home_log( "Page Accueil creee (#{$home_page_id})." );
    }
} else {
    $home_page_id = (int) $home_page->ID;
    $updated      = wp_update_post( [
        'ID'           => $home_page_id,
        'post_content' => $home_content,
        'post_status'  => 'publish',
    ], true );

    if ( is_wp_error( $updated ) ) {
        galerie_home_log( 'Erreur pendant la mise a jour de la home : ' . $updated->get_error_message() );
    } else {
        galerie_home_log( "Page d'accueil mise a jour (#{$home_page_id})." );
    }
}

if ( $home_page_id ) {
    update_option( 'show_on_front', 'page' );
    update_option( 'page_on_front', $home_page_id );
    update_post_meta( $home_page_id, 'rank_math_title', 'Galerie Djilali Kadid | Peintures originales' );
    update_post_meta( $home_page_id, 'rank_math_description', 'Galerie d’artiste peintre : œuvres originales, boutique en ligne, cours et prestations culturelles.' );
    update_post_meta( $home_page_id, 'rank_math_focus_keyword', 'artiste peintre, galerie en ligne' );
    galerie_home_log( 'Accueil defini comme page d entree du site.' );
}

$menu_id = galerie_home_rebuild_menu();

flush_rewrite_rules();
galerie_home_log( 'Rewrite rules videes.' );

echo "\n> Menu final :\n";
$final_items = wp_get_nav_menu_items( $menu_id, [ 'orderby' => 'menu_order' ] );
if ( $final_items ) {
    foreach ( $final_items as $item ) {
        echo "  - {$item->title}\n";
    }
}

echo "\n> Home appliquee : " . home_url( '/' ) . "\n";
