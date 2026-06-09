<?php
/**
 * configure-rankmath.php
 * Lancez avec : wp eval-file setup/configure-rankmath.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    echo "Ce fichier doit etre lance via WP-CLI.\n";
    exit( 1 );
}

if ( ! class_exists( 'RankMath' ) ) {
    echo "Rank Math n'est pas actif.\n";
    exit( 1 );
}

echo "> Configuration Rank Math SEO...\n";

$site_name = get_bloginfo( 'name' );

update_option( 'rank_math_is_configured', true, false );
update_option( 'rank_math_wizard_completed', true, false );
update_option( 'rank_math_registration_skip', true, false );
if ( class_exists( '\RankMath\Helper' ) && method_exists( '\RankMath\Helper', 'is_configured' ) ) {
    \RankMath\Helper::is_configured( true );
}

// ── Reglages generaux ──────────────────────────────────────
$general = get_option( 'rank_math_general_settings', [] );
$general = array_merge( $general, [
    'breadcrumbs'              => 'on',
    'breadcrumbs_separator'    => '>',
    'breadcrumbs_home_label'   => 'Accueil',
    'noindex_empty_taxonomies' => 'on',
    'attachment_redirect_urls' => 'on',
] );
update_option( 'rank_math_general_settings', $general );
echo "  • Reglages generaux\n";

// ── Titres et metas ────────────────────────────────────────
$titles = get_option( 'rank_math_titles', [] );
$titles = array_merge( $titles, [
    // Separateur global
    'sep'                  => '|',

    // Page d'accueil
    'homepage_title'       => "%sitename% | Galerie d'art originale",
    'homepage_description' => "Peintures originales de $site_name — achat en ligne, expositions, ateliers.",

    // Pages statiques
    'page_title'           => '%title% | %sitename%',
    'page_robots'          => [ 'index', 'follow' ],

    // Articles de blog
    'post_title'           => '%title% | %sitename%',
    'post_robots'          => [ 'index', 'follow' ],
    'post_description'     => '%excerpt%',
    'post_article_type'    => 'BlogPosting',

    // CPT Oeuvres
    'artwork_title'        => '%title% — %term% | %sitename%',
    'artwork_description'  => '%excerpt%',
    'artwork_robots'       => [ 'index', 'follow' ],

    // Produits WooCommerce
    'product_title'        => '%title% | %sitename%',
    'product_description'  => '%excerpt%',
    'product_robots'       => [ 'index', 'follow' ],

    // Taxonomies
    'tax_technique_title'     => 'Peintures %term% | %sitename%',
    'tax_theme_oeuvre_title'  => 'Oeuvres %term% | %sitename%',
    'tax_format_oeuvre_title' => 'Format %term% | %sitename%',
    'category_title'          => '%term% | %sitename%',
    'category_description'    => '%term_description%',
    'category_robots'         => [ 'index', 'follow' ],
    'product_cat_title'       => '%term% | %sitename%',
    'product_cat_description' => '%term_description%',
    'product_cat_robots'      => [ 'index', 'follow' ],
    'tag_title'               => 'Tag : %term% | %sitename%',
    'tag_robots'              => [ 'noindex', 'follow' ],

    // Noindex
    'author_archive_robots' => [ 'noindex' ],
    'date_archive_robots'   => [ 'noindex' ],
] );
update_option( 'rank_math_titles', $titles );
echo "  • Titres et metas\n";

// ── Sitemap ────────────────────────────────────────────────
$sitemap = get_option( 'rank_math_sitemap', [] );
$sitemap = array_merge( $sitemap, [
    'items_per_page'   => 200,
    'include_images'   => 'on',
    'ping_search_engines' => 'on',

    // Inclure
    'post_type_post_sitemap'    => 'on',
    'post_type_page_sitemap'    => 'on',
    'post_type_artwork_sitemap' => 'on',
    'post_type_product_sitemap' => 'on',

    // Exclure (commandes, comptes clients)
    'post_type_shop_order_sitemap'          => 'off',
    'post_type_shop_order_refund_sitemap'   => 'off',

    // Taxonomies
    'tax_technique_sitemap'     => 'on',
    'tax_theme_oeuvre_sitemap'  => 'on',
    'tax_format_oeuvre_sitemap' => 'on',
    'tax_category_sitemap'      => 'on',
    'tax_product_cat_sitemap'   => 'on',
    'tax_post_tag_sitemap'      => 'off',
    'tax_product_tag_sitemap'   => 'off',
] );
update_option( 'rank_math_sitemap', $sitemap );
echo "  • Sitemap\n";

// ── Schema global (ArtGallery) ─────────────────────────────
$schema = get_option( 'rank_math_schema_global', [] );
$schema = array_merge( $schema, [
    'type'        => 'ArtGallery',
    'name'        => $site_name,
    'description' => "Galerie d'art en ligne",
] );
update_option( 'rank_math_schema_global', $schema );
echo "  • Schema ArtGallery\n";

// ── Robots.txt personnalise ────────────────────────────────
$robots_txt  = "User-agent: *\n";
$robots_txt .= "Disallow: /wp-admin/\n";
$robots_txt .= "Disallow: /wp-login.php\n";
$robots_txt .= "Disallow: /panier/\n";
$robots_txt .= "Disallow: /commander/\n";
$robots_txt .= "Disallow: /mon-compte/\n";
$robots_txt .= "Disallow: /cart/\n";
$robots_txt .= "Disallow: /checkout/\n";
$robots_txt .= "Disallow: /my-account/\n";
$robots_txt .= "Disallow: /fake-webhook\n";
$robots_txt .= "Disallow: /*?add-to-cart=*\n";
$robots_txt .= "Disallow: /*?orderby=*\n";
$robots_txt .= "Disallow: /?*\n";
$robots_txt .= "Allow: /wp-admin/admin-ajax.php\n";
$robots_txt .= "\n";
$robots_txt .= "Sitemap: " . home_url( '/sitemap_index.xml' ) . "\n";
update_option( 'rank_math_robots_txt', $robots_txt );
echo "  • Robots.txt\n";

// ── Modules actifs ─────────────────────────────────────────
$modules = get_option( 'rank_math_modules', [] );
foreach ( [ 'rich-snippet', 'sitemap', 'redirections', '404-monitor', 'breadcrumbs', 'seo-analysis' ] as $m ) {
    if ( ! in_array( $m, $modules, true ) ) {
        $modules[] = $m;
    }
}
update_option( 'rank_math_modules', $modules );
echo "  • Modules actives : rich-snippet, sitemap, redirections, 404-monitor, breadcrumbs\n";

flush_rewrite_rules();
echo "  • Règles de réécriture rafraîchies\n";

echo "\nRank Math configure.\n";
echo "  Etapes manuelles :\n";
echo "  1. Rank Math > Tableau de bord > Google Search Console > Connecter\n";
echo "  2. Verifier le sitemap : " . home_url( '/sitemap_index.xml' ) . "\n";
echo "  3. Soumettre le sitemap dans Google Search Console\n";
