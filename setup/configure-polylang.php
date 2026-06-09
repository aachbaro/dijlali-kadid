<?php
/**
 * configure-polylang.php
 * Lancez avec : wp eval-file setup/configure-polylang.php
 *
 * Necessite que Polylang soit actif et que les pages aient ete creees.
 */

if ( ! defined( 'ABSPATH' ) ) {
    echo "Ce fichier doit etre lance via WP-CLI.\n";
    exit( 1 );
}

if ( ! function_exists( 'pll_languages_list' ) ) {
    echo "Polylang n'est pas actif. Activez-le d'abord.\n";
    exit( 1 );
}

echo "> Configuration Polylang...\n";

// ── Ajouter les langues ────────────────────────────────────
// Polylang stocke les langues comme des termes dans la taxonomie 'language'

function _pll_add_language( string $name, string $slug, string $locale, string $flag ): void {
    // Verifier si la langue existe deja
    $existing = get_terms( [
        'taxonomy'   => 'language',
        'slug'       => $slug,
        'hide_empty' => false,
    ] );

    if ( ! empty( $existing ) && ! is_wp_error( $existing ) ) {
        echo "  • Langue '$slug' deja presente\n";
        return;
    }

    // PLL_Language::create() est disponible dans le contexte admin/CLI
    if ( class_exists( 'PLL_Language' ) && method_exists( 'PLL_Language', 'create' ) ) {
        PLL_Language::create( [
            'name'       => $name,
            'slug'       => $slug,
            'locale'     => $locale,
            'rtl'        => 0,
            'flag'       => $flag,
            'term_group' => 0,
        ] );
        echo "  • Langue ajoutee : $name ($slug)\n";
    } else {
        // Fallback : insertion directe via la taxonomie
        wp_insert_term( $name, 'language', [ 'slug' => $slug ] );
        echo "  • Langue ajoutee via fallback : $name ($slug)\n";
    }
}

function _pll_repair_language( string $name, string $slug, string $locale, string $flag ): void {
    $description = maybe_serialize( [
        'locale'    => $locale,
        'rtl'       => false,
        'flag_code' => $flag,
    ] );

    $terms = get_terms( [
        'taxonomy'   => 'language',
        'slug'       => $slug,
        'hide_empty' => false,
    ] );

    if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
        wp_update_term( (int) $terms[0]->term_id, 'language', [
            'name'        => $name,
            'description' => $description,
        ] );
    }

    if ( ! term_exists( $slug, 'term_language' ) ) {
        wp_insert_term( $name, 'term_language', [ 'slug' => $slug ] );
    }

    echo "  â€¢ Metadonnees langue '$slug' verifiees\n";
}

_pll_add_language( 'Francais', 'fr', 'fr_FR', 'fr' );
_pll_add_language( 'English',  'en', 'en_US', 'gb' );
_pll_repair_language( 'Francais', 'fr', 'fr_FR', 'fr' );
_pll_repair_language( 'English',  'en', 'en_US', 'gb' );

// ── Options Polylang ───────────────────────────────────────
$opts = get_option( 'polylang', [] );

$opts['default_lang']  = 'fr';
$opts['hide_default']  = 1;   // URLs propres : monsite.fr (pas /fr/) pour le francais
$opts['force_lang']    = 1;   // Prefixe dans les URLs
$opts['browser']       = 1;   // Detection langue navigateur
$opts['redirect_lang'] = 1;
$opts['rewrite']       = 1;
$opts['media_support'] = 1;
$opts['sync']          = [
    'post_meta',
    'comment_status',
    'ping_status',
    'post_thumbnail',
    'menu_order',
];

update_option( 'polylang', $opts );
echo "  • Options Polylang sauvegardees\n";

// ── Assigner la langue FR aux pages existantes ────────────
$pages_fr = get_posts( [
    'post_type'      => 'page',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
] );

// Correspondance slug FR => titre EN
$translations_map = [
    'accueil'          => 'Home',
    'galerie'          => 'Gallery',
    'boutique'         => 'Shop',
    'reservations'     => 'Bookings',
    'blog'             => 'Blog',
    'a-propos'         => 'About',
    'contact'          => 'Contact',
    'mentions-legales' => 'Legal Notice',
    // Pages WooCommerce
    'panier'           => 'Cart',
    'commander'        => 'Checkout',
    'mon-compte'       => 'My Account',
];

foreach ( $pages_fr as $page ) {
    // Assigner FR
    if ( function_exists( 'pll_set_post_language' ) ) {
        pll_set_post_language( $page->ID, 'fr' );
    }

    $en_title = $translations_map[ $page->post_name ] ?? null;
    if ( ! $en_title ) {
        continue;
    }

    // Verifier si traduction EN existe
    $tr_id = function_exists( 'pll_get_post' ) ? pll_get_post( $page->ID, 'en' ) : 0;
    if ( $tr_id ) {
        echo "  • Traduction EN de '{$page->post_title}' existe (ID: $tr_id)\n";
        continue;
    }

    // Creer la version EN
    $en_id = wp_insert_post( [
        'post_title'   => $en_title,
        'post_name'    => sanitize_title( strtolower( $en_title ) ),
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_content' => $page->post_content,
    ] );

    if ( is_wp_error( $en_id ) ) {
        echo "  Erreur creation page EN '$en_title' : " . $en_id->get_error_message() . "\n";
        continue;
    }

    if ( function_exists( 'pll_set_post_language' ) ) {
        pll_set_post_language( $en_id, 'en' );
    }

    if ( function_exists( 'pll_save_post_translations' ) ) {
        pll_save_post_translations( [
            'fr' => $page->ID,
            'en' => $en_id,
        ] );
    }

    echo "  • '{$page->post_title}' (FR) => '$en_title' (EN, ID: $en_id)\n";
}

// ── Widget switcher de langue ──────────────────────────────
update_option( 'widget_polylang', [
    2 => [
        'title'                  => '',
        'dropdown'               => 0,
        'show_names'             => 1,
        'show_flags'             => 1,
        'hide_if_empty'          => 0,
        'force_home'             => 0,
        'echo'                   => 1,
        'hide_if_no_translation' => 0,
    ],
    '_multiwidget' => 1,
] );

echo "\nPolylang configure.\n";
echo "  FR : monsite.fr/  |  EN : monsite.fr/en/\n";
echo "  Ajoutez le widget 'Selecteur de langue' dans Apparence > Widgets\n";
