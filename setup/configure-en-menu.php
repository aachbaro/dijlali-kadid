<?php
/**
 * configure-en-menu.php
 * Lance avec : wp eval-file setup/configure-en-menu.php
 *
 * Cree le menu de navigation anglais et l'assigne a Polylang.
 * Prerequis : configure-polylang.php et configure-simplify-nav.php deja joues.
 *
 * Ce que fait ce script :
 *   1. Verifie/cree les pages EN manquantes (Services, Legal Notice).
 *   2. Cree le menu "Menu principal (EN)".
 *   3. Ajoute les items : Gallery | Blog | Services | About | Contact.
 *   4. Assigne ce menu a Polylang pour primary + mobile EN.
 *   5. Ajoute les redirections EN : /en/shop/ → /en/gallery/ et /en/bookings/ → /en/services/.
 */

if ( ! defined( 'ABSPATH' ) ) {
    echo "Ce fichier doit etre lance via WP-CLI.\n";
    exit( 1 );
}

if ( ! function_exists( 'pll_languages_list' ) ) {
    echo "Polylang n'est pas actif. Activez-le d'abord.\n";
    exit( 1 );
}

echo "> Configuration menu anglais...\n\n";

// ── Helpers ────────────────────────────────────────────────

function en_menu_log( string $message ): void {
    echo "  • {$message}\n";
}

/**
 * Cherche une page par slug (toutes langues).
 */
function en_menu_find_page( string $slug ): ?WP_Post {
    $page = get_page_by_path( $slug, OBJECT, 'page' );
    if ( $page instanceof WP_Post ) {
        return $page;
    }

    $results = get_posts( [
        'name'           => $slug,
        'post_type'      => 'page',
        'post_status'    => [ 'publish', 'draft', 'private' ],
        'posts_per_page' => 1,
        'suppress_filters' => true,
    ] );

    return ( ! empty( $results ) && $results[0] instanceof WP_Post ) ? $results[0] : null;
}

/**
 * Retourne la page EN d'une page FR (via pll_get_post).
 * Si elle n'existe pas, la cree avec le titre et le slug fournis.
 *
 * @param string $fr_slug   Slug de la page FR parente.
 * @param string $en_title  Titre EN a creer si absent.
 * @param string $en_slug   Slug EN a utiliser.
 * @param string $en_content Contenu EN (optionnel).
 */
function en_menu_ensure_en_page(
    string $fr_slug,
    string $en_title,
    string $en_slug,
    string $en_content = ''
): ?WP_Post {
    // Chercher la page FR source
    $fr_page = en_menu_find_page( $fr_slug );

    // Si la page FR a une traduction EN, on la retourne
    if ( $fr_page && function_exists( 'pll_get_post' ) ) {
        $en_id = pll_get_post( $fr_page->ID, 'en' );
        if ( $en_id ) {
            $en_page = get_post( $en_id );
            if ( $en_page instanceof WP_Post ) {
                en_menu_log( "Page EN '{$en_title}' existe deja (ID: {$en_id})." );
                return $en_page;
            }
        }
    }

    // Sinon, chercher directement par slug EN
    $existing = en_menu_find_page( $en_slug );
    if ( $existing instanceof WP_Post ) {
        // Langue non encore assignee — on la lie
        if ( function_exists( 'pll_set_post_language' ) ) {
            pll_set_post_language( $existing->ID, 'en' );
        }
        if ( $fr_page && function_exists( 'pll_save_post_translations' ) ) {
            pll_save_post_translations( [
                'fr' => $fr_page->ID,
                'en' => $existing->ID,
            ] );
        }
        en_menu_log( "Page EN '{$en_title}' trouvee via slug, langue assignee (ID: {$existing->ID})." );
        return $existing;
    }

    // Creer la page EN
    $content = $en_content ?: ( $fr_page ? $fr_page->post_content : '' );
    $new_id  = wp_insert_post( [
        'post_title'   => $en_title,
        'post_name'    => $en_slug,
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_content' => $content,
    ], true );

    if ( is_wp_error( $new_id ) ) {
        en_menu_log( "Erreur creation '{$en_title}' : " . $new_id->get_error_message() );
        return null;
    }

    if ( function_exists( 'pll_set_post_language' ) ) {
        pll_set_post_language( $new_id, 'en' );
    }
    if ( $fr_page && function_exists( 'pll_save_post_translations' ) ) {
        pll_save_post_translations( [
            'fr' => $fr_page->ID,
            'en' => $new_id,
        ] );
    }

    en_menu_log( "Page EN '{$en_title}' creee (ID: {$new_id})." );
    return get_post( $new_id );
}

// ── 1. S'assurer que les pages EN existent ────────────────────

echo "> Verification des pages EN...\n";

$en_pages = [
    [
        'fr_slug'    => 'galerie',
        'en_title'   => 'Gallery',
        'en_slug'    => 'gallery',
    ],
    [
        'fr_slug'    => 'blog',
        'en_title'   => 'Blog',
        'en_slug'    => 'blog-en',
    ],
    [
        'fr_slug'    => 'prestations',
        'en_title'   => 'Services',
        'en_slug'    => 'services',
        'en_content' => "<!-- wp:paragraph -->\n<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Book a session</h2>\n<!-- /wp:heading -->\n\n<!-- wp:shortcode -->\n[ameliabooking]\n<!-- /wp:shortcode -->",
    ],
    [
        'fr_slug'    => 'a-propos',
        'en_title'   => 'About',
        'en_slug'    => 'about',
    ],
    [
        'fr_slug'    => 'contact',
        'en_title'   => 'Contact',
        'en_slug'    => 'contact-en',
    ],
    [
        'fr_slug'    => 'mentions-legales',
        'en_title'   => 'Legal Notice',
        'en_slug'    => 'legal-notice',
    ],
];

$resolved_pages = [];
foreach ( $en_pages as $def ) {
    $page = en_menu_ensure_en_page(
        $def['fr_slug'],
        $def['en_title'],
        $def['en_slug'],
        $def['en_content'] ?? ''
    );
    if ( $page ) {
        $resolved_pages[ $def['en_title'] ] = $page;
    }
}

echo "\n";

// ── 2. Mettre "Shop" et "Bookings" en brouillon ───────────────

echo "> Mise en brouillon des pages Shop et Bookings...\n";

foreach ( [ 'shop' => 'Shop', 'bookings' => 'Bookings' ] as $slug => $label ) {
    $page = en_menu_find_page( $slug );
    if ( ! $page ) {
        en_menu_log( "Page '{$label}' ({$slug}) introuvable, ignoree." );
        continue;
    }
    if ( 'draft' === $page->post_status ) {
        en_menu_log( "Page '{$label}' deja en brouillon." );
        continue;
    }
    wp_update_post( [
        'ID'          => $page->ID,
        'post_status' => 'draft',
    ] );
    en_menu_log( "Page '{$label}' mise en brouillon." );
}

echo "\n";

// ── 3. Creer le menu principal EN ────────────────────────────

echo "> Creation du menu EN...\n";

$menu_name = 'Menu principal (EN)';
$menu_obj  = wp_get_nav_menu_object( $menu_name );

if ( $menu_obj && ! is_wp_error( $menu_obj ) ) {
    $en_menu_id = (int) $menu_obj->term_id;

    // Vider les anciens items
    $existing_items = wp_get_nav_menu_items( $en_menu_id );
    if ( $existing_items ) {
        foreach ( $existing_items as $item ) {
            wp_delete_post( (int) $item->ID, true );
        }
        en_menu_log( "Anciens items du menu '{$menu_name}' supprimes." );
    } else {
        en_menu_log( "Menu '{$menu_name}' existant (ID: {$en_menu_id}), items vides." );
    }
} else {
    $en_menu_id = (int) wp_create_nav_menu( $menu_name );
    en_menu_log( "Menu '{$menu_name}' cree (ID: {$en_menu_id})." );
}

// Ordre souhaite du menu EN
$menu_items_order = [
    'Gallery',
    'Blog',
    'Services',
    'About',
    'Contact',
];

$menu_order = 1;
foreach ( $menu_items_order as $title ) {
    if ( ! isset( $resolved_pages[ $title ] ) ) {
        en_menu_log( "Page '{$title}' absente, item de menu ignore." );
        continue;
    }

    $page = $resolved_pages[ $title ];
    wp_update_nav_menu_item( $en_menu_id, 0, [
        'menu-item-title'     => $title,
        'menu-item-object-id' => $page->ID,
        'menu-item-object'    => 'page',
        'menu-item-type'      => 'post_type',
        'menu-item-status'    => 'publish',
        'menu-item-position'  => $menu_order++,
    ] );
    en_menu_log( "Item '{$title}' ajoute (page ID: {$page->ID})." );
}

echo "\n";

// ── 4. Assigner le menu EN a Polylang ────────────────────────

echo "> Assignation du menu EN a Polylang...\n";

$theme_slug  = get_option( 'stylesheet', '' );
$pll_options = get_option( 'polylang', [] );
if ( ! is_array( $pll_options ) ) {
    $pll_options = [];
}

$updated = false;
foreach ( [ 'primary', 'mobile' ] as $location ) {
    $current = $pll_options['nav_menus'][ $theme_slug ][ $location ]['en'] ?? null;
    if ( (int) $current === $en_menu_id ) {
        en_menu_log( "Location '{$location}' EN deja assignee au menu." );
        continue;
    }
    $pll_options['nav_menus'][ $theme_slug ][ $location ]['en'] = $en_menu_id;
    en_menu_log( "Location '{$location}' EN assignee au menu '{$menu_name}'." );
    $updated = true;
}

if ( $updated ) {
    update_option( 'polylang', $pll_options );
    en_menu_log( "Options Polylang sauvegardees." );
}

echo "\n";

// ── 5. Redirections EN (shop → gallery, bookings → services) ─

echo "> Ajout des redirections EN...\n";

$redirects = get_option( 'galerie_legacy_redirects', [] );
if ( ! is_array( $redirects ) ) {
    $redirects = [];
}

$en_redirects = [
    '/en/shop/'     => '/en/gallery/',
    '/en/bookings/' => '/en/services/',
];

foreach ( $en_redirects as $source => $target ) {
    if ( isset( $redirects[ $source ] ) && $redirects[ $source ] === $target ) {
        en_menu_log( "Redirection deja active : {$source} -> {$target}" );
        continue;
    }
    $redirects[ $source ] = $target;
    en_menu_log( "Redirection ajoutee : {$source} -> {$target}" );
}

update_option( 'galerie_legacy_redirects', $redirects, false );

flush_rewrite_rules();
en_menu_log( "Rewrite rules rafraichies." );

// ── Resume final ──────────────────────────────────────────────

echo "\n> Menu EN final :\n";
$final_items = wp_get_nav_menu_items( $en_menu_id, [ 'orderby' => 'menu_order' ] );
if ( $final_items ) {
    foreach ( $final_items as $item ) {
        $page    = get_post( (int) $item->object_id );
        $en_url  = $page ? get_permalink( $page->ID ) : '—';
        echo "  - {$item->title}  →  {$en_url}\n";
    }
}

echo "\n> Redirections EN actives :\n";
foreach ( $en_redirects as $source => $target ) {
    echo "  - " . home_url( $source ) . "  →  " . home_url( $target ) . "\n";
}

echo "\nMenu de navigation anglais configure.\n";
echo "  EN : " . home_url( '/en/' ) . "\n";
echo "  Switcher FR/EN deja integre dans le menu via functions.php.\n";
