<?php
/**
 * functions.php — Thème enfant Kadence (Galerie d'art)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once get_stylesheet_directory() . '/admin-simplify.php';

add_filter( 'body_class', function ( array $classes ): array {
    if ( ! is_singular( 'page' ) ) {
        return $classes;
    }

    $page = get_queried_object();
    if ( $page instanceof WP_Post && $page->post_name ) {
        $classes[] = 'page-slug-' . sanitize_html_class( $page->post_name );
    }

    return $classes;
} );

// ── Enqueue styles ─────────────────────────────────────────
add_action( 'wp_enqueue_scripts', function (): void {
    wp_enqueue_style(
        'kadence-child-style',
        get_stylesheet_uri(),
        [ 'kadence-global' ],
        wp_get_theme()->get( 'Version' )
    );

    // Raleway (logo + titres, élégant et sobre)
    // DM Sans (corps de texte, navigation)
    wp_enqueue_style(
        'kadence-child-fonts',
        'https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&family=Raleway:wght@200;300;400;500&display=swap',
        [],
        null
    );

    wp_register_script(
        'kadence-child-reveal',
        false,
        [],
        wp_get_theme()->get( 'Version' ),
        true
    );
    wp_enqueue_script( 'kadence-child-reveal' );
    wp_add_inline_script(
        'kadence-child-reveal',
        <<<'JS'
(() => {
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const selector = [
    '.site-main > article',
    '.site-main > section',
    '.content-area > *',
    '.content-container > *',
    '.demo-home-hero__inner',
    '.demo-section > *',
    '.mvp-hero__inner',
    '.mvp-section',
    '.mvp-section__head',
    '.mvp-section > *',
    '.mvp-feature-grid > *',
    '.mvp-split > *',
    '.mvp-service-nav > *',
    '.mvp-timeline > *',
    '.mvp-latest-posts li',
    '.entry-header',
    '.entry-content > *',
    '.wp-block-group',
    '.wp-block-cover',
    '.wp-block-image',
    '.wp-block-gallery',
    '.wp-block-media-text',
    '.wp-block-columns',
    '.wp-block-columns > .wp-block-column',
    '.wp-block-buttons',
    '.wp-block-kadence-rowlayout',
    '.wp-block-kadence-column',
    '.kb-gallery-ul .kadence-blocks-gallery-item',
    '.woocommerce div.product > *',
    '.woocommerce ul.products li.product',
    '.woocommerce-cart-form',
    '.cart_totals',
    '.woocommerce-checkout-review-order',
    '.woocommerce-MyAccount-content > *',
    '.artwork-card',
    '.artwork-single__gallery',
    '.artwork-single__info',
    '.artwork-single__description',
    '.artwork-product-details',
    '.related.products',
    '.type-post .entry-content > *'
  ].join(',');

  let observer = null;
  let scheduled = false;

  const hasVisibleContent = (node) => (
    node.textContent.trim() ||
    node.querySelector('img, picture, video, iframe, canvas, svg')
  );

  const shouldSkip = (node) => (
    !(node instanceof HTMLElement) ||
    node.dataset.revealReady ||
    node.closest('.site-header, .site-footer, #wpadminbar, #cookie-notice, .cn-notice-container, .woocommerce-mini-cart, .modal, .mvp-hero') ||
    node.matches('script, style, noscript, input, textarea, select, button') ||
    !hasVisibleContent(node)
  );

  const revealImmediately = (nodes) => {
    nodes.forEach((node) => node.classList.add('is-revealed'));
  };

  const afterPaint = (callback) => {
    window.requestAnimationFrame(() => {
      window.requestAnimationFrame(callback);
    });
  };

  const prepareNode = (node) => {
    node.dataset.revealReady = '1';
    node.classList.add('reveal-on-scroll');
  };

  const prepareReveals = () => {
    scheduled = false;

    const nodes = Array.from(document.querySelectorAll(selector)).filter((node) => !shouldSkip(node));
    nodes.forEach(prepareNode);

    if (!nodes.length) {
      return;
    }

    if (reduceMotion) {
      revealImmediately(nodes);
      return;
    }

    if (!('IntersectionObserver' in window)) {
      afterPaint(() => revealImmediately(nodes));
      return;
    }

    if (!observer) {
      const STAGGER_STEP = 85;  // ms entre chaque élément d'une même vague
      const STAGGER_CAP = 6;    // au-delà, on ne rallonge plus le délai

      observer = new IntersectionObserver((entries, activeObserver) => {
        // On ne garde que ceux qui entrent réellement dans l'écran
        const shown = entries.filter((entry) => entry.isIntersecting);
        if (!shown.length) {
          return;
        }

        // Tri de haut en bas puis de gauche à droite → cascade naturelle
        shown.sort((a, b) => {
          const ra = a.target.getBoundingClientRect();
          const rb = b.target.getBoundingClientRect();
          return (ra.top - rb.top) || (ra.left - rb.left);
        });

        // Chaque élément de la vague se révèle un peu après le précédent
        shown.forEach((entry, index) => {
          const delay = Math.min(index, STAGGER_CAP) * STAGGER_STEP;
          entry.target.style.setProperty('--reveal-delay', delay + 'ms');
          entry.target.classList.add('is-revealed');
          activeObserver.unobserve(entry.target);
        });
      }, {
        rootMargin: '0px 0px -8% 0px',
        threshold: 0.12
      });
    }

    afterPaint(() => {
      nodes.forEach((node) => observer.observe(node));
    });
  };

  const scheduleReveals = () => {
    if (scheduled) {
      return;
    }

    scheduled = true;
    window.setTimeout(prepareReveals, 90);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', prepareReveals, { once: true });
  } else {
    prepareReveals();
  }

  window.addEventListener('load', prepareReveals, { once: true });

  if ('MutationObserver' in window) {
    new MutationObserver(scheduleReveals).observe(document.documentElement, {
      childList: true,
      subtree: true
    });
  }
})();
JS
    );
}, 99 );

// ── Support thème ──────────────────────────────────────────
add_action( 'after_setup_theme', function (): void {
    add_theme_support( 'post-thumbnails' );
    add_image_size( 'artwork-card', 720, 900, true );     // Grille galerie, net sur écrans Retina
    add_image_size( 'artwork-main', 1200, 1500, false );  // Fiche œuvre (ratio 4:5)
} );

// Images WooCommerce : éviter que la galerie étire les miniatures 300px par défaut.
add_filter( 'single_product_archive_thumbnail_size', function () {
    if ( function_exists( 'is_shop' ) && is_shop() ) {
        return 'artwork-main';
    }

    return 'artwork-card';
}, 20 );

add_filter( 'woocommerce_get_image_size_thumbnail', function ( array $size ): array {
    return [
        'width'  => 720,
        'height' => 900,
        'crop'   => 1,
    ];
}, 20 );

add_filter( 'woocommerce_get_image_size_single', function ( array $size ): array {
    return [
        'width'  => 1200,
        'height' => 1500,
        'crop'   => 0,
    ];
}, 20 );

// ── CPT : Œuvre ────────────────────────────────────────────
add_action( 'init', function (): void {
    register_post_type( 'artwork', [
        'labels' => [
            'name'               => 'Œuvres',
            'singular_name'      => 'Œuvre',
            'add_new'            => 'Ajouter une œuvre',
            'add_new_item'       => 'Ajouter une nouvelle œuvre',
            'edit_item'          => "Modifier l'œuvre",
            'new_item'           => 'Nouvelle œuvre',
            'view_item'          => "Voir l'œuvre",
            'search_items'       => 'Rechercher des œuvres',
            'not_found'          => 'Aucune œuvre trouvée',
            'not_found_in_trash' => 'Aucune œuvre dans la corbeille',
            'menu_name'          => 'Œuvres',
        ],
        'public'         => true,
        'has_archive'    => true,
        'rewrite'        => [ 'slug' => 'oeuvres', 'with_front' => false ],
        'supports'       => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
        'menu_icon'      => 'dashicons-art',
        'menu_position'  => 5,
        'show_in_rest'   => true,
        'taxonomies'     => [ 'technique', 'format_oeuvre', 'theme_oeuvre' ],
    ] );
} );

// ── Taxonomies ─────────────────────────────────────────────
add_action( 'init', function (): void {
    $artwork_object_types = [ 'artwork', 'product' ];

    register_taxonomy( 'technique', $artwork_object_types, [
        'labels'       => [
            'name'          => 'Techniques',
            'singular_name' => 'Technique',
            'all_items'     => 'Toutes les techniques',
            'add_new_item'  => 'Ajouter une technique',
        ],
        'hierarchical' => true,
        'public'       => true,
        'show_in_rest' => true,
        'rewrite'      => [ 'slug' => 'technique', 'with_front' => false ],
    ] );

    register_taxonomy( 'format_oeuvre', $artwork_object_types, [
        'labels'       => [
            'name'          => 'Formats',
            'singular_name' => 'Format',
            'all_items'     => 'Tous les formats',
            'add_new_item'  => 'Ajouter un format',
        ],
        'hierarchical' => true,
        'public'       => true,
        'show_in_rest' => true,
        'rewrite'      => [ 'slug' => 'format', 'with_front' => false ],
    ] );

    register_taxonomy( 'theme_oeuvre', $artwork_object_types, [
        'labels'       => [
            'name'          => 'Thèmes',
            'singular_name' => 'Thème',
            'all_items'     => 'Tous les thèmes',
            'add_new_item'  => 'Ajouter un thème',
        ],
        'hierarchical' => true,
        'public'       => true,
        'show_in_rest' => true,
        'rewrite'      => [ 'slug' => 'theme', 'with_front' => false ],
    ] );
} );

// ── Termes par défaut ──────────────────────────────────────
add_action( 'init', function (): void {
    foreach ( [ 'Gouache', 'Tempera', 'Huile', 'Acrylique', 'Aquarelle', 'Dessin', 'Technique mixte', 'Autre' ] as $t ) {
        if ( ! term_exists( $t, 'technique' ) ) {
            wp_insert_term( $t, 'technique' );
        }
    }
    $formats = [
        'Petit format (< 30 cm)'  => 'petit',
        'Moyen format (30-80 cm)' => 'moyen',
        'Grand format (> 80 cm)'  => 'grand',
    ];
    foreach ( $formats as $name => $slug ) {
        if ( ! term_exists( $name, 'format_oeuvre' ) ) {
            wp_insert_term( $name, 'format_oeuvre', [ 'slug' => $slug ] );
        }
    }
    foreach ( [ 'Paysage', 'Portrait', 'Abstrait', 'Nature morte', 'Marine', 'Floral', 'Autre' ] as $t ) {
        if ( ! term_exists( $t, 'theme_oeuvre' ) ) {
            wp_insert_term( $t, 'theme_oeuvre' );
        }
    }
}, 20 );

// ── Meta box : Détails de l'œuvre ──────────────────────────
add_action( 'add_meta_boxes', function (): void {
    foreach ( [ 'artwork', 'product' ] as $screen ) {
        add_meta_box(
            'artwork_details',
            "Détails de l'œuvre",
            'artwork_meta_box_html',
            $screen,
            'normal',
            'high'
        );
    }
} );

function artwork_meta_box_html( WP_Post $post ): void {
    wp_nonce_field( 'artwork_save_meta', 'artwork_nonce' );

    $height  = get_post_meta( $post->ID, '_artwork_height',      true );
    $width   = get_post_meta( $post->ID, '_artwork_width',       true );
    $depth   = get_post_meta( $post->ID, '_artwork_depth',       true );
    $year    = get_post_meta( $post->ID, '_artwork_year',        true );
    $cert    = get_post_meta( $post->ID, '_artwork_certificate', true );
    $gallery = get_post_meta( $post->ID, '_artwork_gallery',     true );
    $status  = get_post_meta( $post->ID, '_artwork_status',      true ) ?: 'available';
    ?>
    <style>
        .aw-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:16px; }
        .aw-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px; }
        .aw-field label { display:block; font-weight:600; margin-bottom:4px; font-size:13px; }
        .aw-field input, .aw-field select { width:100%; }
        .aw-sep { border:0; border-top:1px solid #ddd; margin:16px 0; }
    </style>
    <div class="aw-grid">
        <div class="aw-field">
            <label>Hauteur (cm)</label>
            <input type="number" name="artwork_height" value="<?php echo esc_attr( $height ); ?>" min="0" step="0.1">
        </div>
        <div class="aw-field">
            <label>Largeur (cm)</label>
            <input type="number" name="artwork_width" value="<?php echo esc_attr( $width ); ?>" min="0" step="0.1">
        </div>
        <div class="aw-field">
            <label>Profondeur (cm)</label>
            <input type="number" name="artwork_depth" value="<?php echo esc_attr( $depth ); ?>" min="0" step="0.1">
        </div>
    </div>
    <div class="aw-grid-2">
        <div class="aw-field">
            <label>Année de création</label>
            <input type="number" name="artwork_year" value="<?php echo esc_attr( $year ); ?>"
                   min="1900" max="<?php echo esc_attr( (string) date( 'Y' ) ); ?>">
        </div>
        <div class="aw-field">
            <label>Statut</label>
            <select name="artwork_status">
                <option value="available" <?php selected( $status, 'available' ); ?>>Disponible</option>
                <option value="sold"      <?php selected( $status, 'sold' ); ?>>Vendue</option>
                <option value="reserved"  <?php selected( $status, 'reserved' ); ?>>Réservée</option>
            </select>
        </div>
    </div>
    <hr class="aw-sep">
    <div class="aw-field" style="margin-bottom:16px;">
        <label>
            <input type="checkbox" name="artwork_certificate" value="1" <?php checked( $cert, '1' ); ?>>
            Livré avec certificat d'authenticité signé
        </label>
    </div>
    <hr class="aw-sep">
    <div class="aw-field">
        <label>Photos supplémentaires (IDs médiathèque, séparés par virgule — max 10)</label>
        <input type="text" name="artwork_gallery" value="<?php echo esc_attr( $gallery ); ?>" placeholder="12, 34, 56">
    </div>
    <?php
}

function artwork_save_meta_fields( int $post_id ): void {
    if (
        ! isset( $_POST['artwork_nonce'] ) ||
        ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['artwork_nonce'] ) ), 'artwork_save_meta' ) ||
        ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
        ! current_user_can( 'edit_post', $post_id )
    ) {
        return;
    }

    foreach ( [
        'artwork_height' => '_artwork_height',
        'artwork_width'  => '_artwork_width',
        'artwork_depth'  => '_artwork_depth',
        'artwork_year'   => '_artwork_year',
        'artwork_status' => '_artwork_status',
    ] as $post_key => $meta_key ) {
        if ( isset( $_POST[ $post_key ] ) ) {
            update_post_meta( $post_id, $meta_key, sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) ) );
        }
    }

    update_post_meta( $post_id, '_artwork_certificate', isset( $_POST['artwork_certificate'] ) ? '1' : '0' );

    if ( isset( $_POST['artwork_gallery'] ) ) {
        $ids = array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['artwork_gallery'] ) ) ) ) );
        update_post_meta( $post_id, '_artwork_gallery', implode( ',', array_slice( $ids, 0, 10 ) ) );
    }
}

add_action( 'save_post_artwork', 'artwork_save_meta_fields' );
add_action( 'save_post_product', 'artwork_save_meta_fields' );

// ── WooCommerce : fiche œuvre vendable ───────────────────────
function artwork_primary_term_name( int $post_id, string $taxonomy ): string {
    $terms = get_the_terms( $post_id, $taxonomy );
    if ( empty( $terms ) || is_wp_error( $terms ) ) {
        return '';
    }

    return (string) $terms[0]->name;
}

function artwork_dimensions_label( int $post_id ): string {
    $height = get_post_meta( $post_id, '_artwork_height', true );
    $width  = get_post_meta( $post_id, '_artwork_width', true );
    $depth  = get_post_meta( $post_id, '_artwork_depth', true );

    if ( ! $height || ! $width ) {
        return '';
    }

    $label = $height . ' x ' . $width . ' cm';
    if ( $depth ) {
        $label = $height . ' x ' . $width . ' x ' . $depth . ' cm';
    }

    return $label;
}

function artwork_is_sold( WC_Product $product ): bool {
    $status = get_post_meta( $product->get_id(), '_artwork_status', true );
    return 'sold' === $status || ! $product->is_in_stock();
}

add_filter( 'woocommerce_product_single_add_to_cart_text', function (): string {
    global $product;
    if ( $product instanceof WC_Product && 'cultural_service' === get_post_meta( $product->get_id(), '_service_type', true ) ) {
        return 'Réserver';
    }

    return "Acheter l'oeuvre";
} );

add_filter( 'woocommerce_product_add_to_cart_text', function ( string $text, WC_Product $product ): string {
    if ( 'cultural_service' === get_post_meta( $product->get_id(), '_service_type', true ) ) {
        return 'Réserver';
    }

    if ( artwork_is_sold( $product ) ) {
        return "Oeuvre vendue";
    }

    return "Acheter l'oeuvre";
}, 10, 2 );

add_action( 'woocommerce_single_product_summary', function (): void {
    if ( ! function_exists( 'wc_get_product' ) ) {
        return;
    }

    global $product;
    if ( ! $product instanceof WC_Product ) {
        return;
    }

    $post_id     = $product->get_id();
    $is_service  = 'cultural_service' === get_post_meta( $post_id, '_service_type', true );

    if ( $is_service ) {
        $duration = get_post_meta( $post_id, '_service_duration', true );
        $format   = get_post_meta( $post_id, '_service_format', true );
        $audience = get_post_meta( $post_id, '_service_audience', true );
        $booking  = get_post_meta( $post_id, '_service_booking', true );
        ?>
        <section class="artwork-product-details service-product-details" aria-label="Détails de la prestation">
            <div class="artwork-product-details__grid">
                <?php if ( $duration ) : ?><p><span>Durée</span><?php echo esc_html( $duration ); ?></p><?php endif; ?>
                <?php if ( $format ) : ?><p><span>Format</span><?php echo esc_html( $format ); ?></p><?php endif; ?>
                <?php if ( $audience ) : ?><p><span>Public</span><?php echo esc_html( $audience ); ?></p><?php endif; ?>
                <?php if ( $booking ) : ?><p><span>Réservation</span><?php echo esc_html( $booking ); ?></p><?php endif; ?>
            </div>
            <p class="artwork-shipping-info">Prestation de démonstration. Les créneaux définitifs seront connectés à Amelia.</p>
        </section>
        <?php
        return;
    }

    $technique   = artwork_primary_term_name( $post_id, 'technique' );
    $theme       = artwork_primary_term_name( $post_id, 'theme_oeuvre' );
    $format      = artwork_primary_term_name( $post_id, 'format_oeuvre' );
    $dimensions  = artwork_dimensions_label( $post_id );
    $year        = get_post_meta( $post_id, '_artwork_year', true );
    $certificate = '1' === get_post_meta( $post_id, '_artwork_certificate', true );

    if ( ! $technique && ! $theme && ! $format && ! $dimensions && ! $year && ! $certificate ) {
        return;
    }
    ?>
    <section class="artwork-product-details" aria-label="Détails de l'œuvre">
        <div class="artwork-product-details__grid">
            <?php if ( $technique ) : ?>
                <p><span>Technique</span><?php echo esc_html( $technique ); ?></p>
            <?php endif; ?>
            <?php if ( $dimensions ) : ?>
                <p><span>Dimensions</span><?php echo esc_html( $dimensions ); ?></p>
            <?php endif; ?>
            <?php if ( $year ) : ?>
                <p><span>Année</span><?php echo esc_html( $year ); ?></p>
            <?php endif; ?>
            <?php if ( $theme ) : ?>
                <p><span>Thème</span><?php echo esc_html( $theme ); ?></p>
            <?php endif; ?>
            <?php if ( $format ) : ?>
                <p><span>Format</span><?php echo esc_html( $format ); ?></p>
            <?php endif; ?>
        </div>
        <?php if ( $certificate ) : ?>
            <p class="artwork-certificate">Livrée avec certificat d'authenticité signé.</p>
        <?php endif; ?>
        <?php if ( artwork_is_sold( $product ) ) : ?>
            <p class="artwork-sold-message">Cette œuvre est vendue. Vous pouvez contacter l'atelier pour une commande similaire.</p>
        <?php else : ?>
            <p class="artwork-shipping-info">Œuvre unique. Emballage soigné, retrait à l'atelier ou expédition sur devis selon le format.</p>
        <?php endif; ?>
    </section>
    <?php
}, 25 );

add_action( 'woocommerce_after_shop_loop_item_title', function (): void {
    if ( ! function_exists( 'wc_get_product' ) ) {
        return;
    }

    global $product;
    if ( ! $product instanceof WC_Product ) {
        return;
    }

    $post_id    = $product->get_id();
    $technique  = artwork_primary_term_name( $post_id, 'technique' );
    $dimensions = artwork_dimensions_label( $post_id );
    $year       = get_post_meta( $post_id, '_artwork_year', true );
    $parts      = array_filter( [ $technique, $dimensions, $year ] );

    if ( 'cultural_service' === get_post_meta( $post_id, '_service_type', true ) ) {
        $excerpt = $product->get_short_description();
        if ( ! $excerpt ) {
            $excerpt = wp_trim_words( $product->get_description(), 18, '…' );
        }
        $parts = $excerpt ? [ wp_strip_all_tags( $excerpt ) ] : [];
    }

    if ( ! $parts ) {
        return;
    }

    echo '<p class="artwork-loop-meta">' . esc_html( implode( ' · ', $parts ) ) . '</p>';
}, 7 );

add_action( 'woocommerce_before_shop_loop_item_title', function (): void {
    if ( ! function_exists( 'wc_get_product' ) ) {
        return;
    }

    global $product;
    if ( $product instanceof WC_Product && artwork_is_sold( $product ) ) {
        echo '<span class="artwork-loop-sold">Vendue</span>';
    }
}, 9 );

add_action( 'woocommerce_product_query', function ( WP_Query $query ): void {
    if ( is_admin() || ! function_exists( 'is_shop' ) || ! is_shop() ) {
        return;
    }

    $artwork_category = get_term_by( 'slug', 'oeuvres-originales', 'product_cat' );
    if ( $artwork_category ) {
        $tax_query   = (array) $query->get( 'tax_query' );
        $tax_query[] = [
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => [ (int) $artwork_category->term_id ],
        ];
        $query->set( 'tax_query', $tax_query );
    }

    $meta_query   = (array) $query->get( 'meta_query' );
    $meta_query[] = [
        'relation' => 'OR',
        [
            'key'     => '_service_type',
            'compare' => 'NOT EXISTS',
        ],
        [
            'key'     => '_service_type',
            'value'   => 'cultural_service',
            'compare' => '!=',
        ],
    ];
    $query->set( 'meta_query', $meta_query );
}, 20 );

add_action( 'wp', function (): void {
    if ( function_exists( 'is_shop' ) && is_shop() ) {
        remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
        remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
    }
} );

// ── Polylang + WooCommerce : page shop par langue ──────────
// WooCommerce lit la page shop via get_option('woocommerce_shop_page_id').
// Ce filtre renvoie la traduction EN (Gallery, ID:24) quand la langue est EN,
// ce qui fait que is_shop() et le template WooCommerce s'appliquent correctement.
add_filter( 'option_woocommerce_shop_page_id', function ( $page_id ) {
    if (
        function_exists( 'pll_current_language' )
        && 'en' === pll_current_language()
        && function_exists( 'pll_get_post' )
    ) {
        $en_id = pll_get_post( (int) $page_id, 'en' );
        if ( $en_id ) {
            return (int) $en_id;
        }
    }
    return $page_id;
} );

// ── Polylang : enregistrer CPT et taxonomies ───────────────
add_filter( 'pll_get_post_types', function ( array $types ): array {
    $types['artwork'] = 'artwork';
    return $types;
} );

add_filter( 'pll_get_taxonomies', function ( array $taxonomies ): array {
    $taxonomies['technique']    = 'technique';
    $taxonomies['format_oeuvre'] = 'format_oeuvre';
    $taxonomies['theme_oeuvre'] = 'theme_oeuvre';
    return $taxonomies;
} );

add_filter( 'robots_txt', function ( string $output, bool $public ): string {
    $custom = get_option( 'rank_math_robots_txt' );
    return is_string( $custom ) && '' !== trim( $custom ) ? $custom : $output;
}, 999, 2 );

add_action( 'template_redirect', function (): void {
    if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return;
    }

    $redirects = get_option( 'galerie_legacy_redirects', [] );
    if ( ! is_array( $redirects ) || [] === $redirects ) {
        return;
    }

    $request_path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
    if ( ! is_string( $request_path ) ) {
        return;
    }

    $request_path = '/' . trim( $request_path, '/' ) . '/';
    $home_path    = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
    $home_path    = is_string( $home_path ) ? '/' . trim( $home_path, '/' ) . '/' : '/';

    if ( '//' === $home_path ) {
        $home_path = '/';
    }

    if ( '/' !== $home_path && str_starts_with( $request_path, $home_path ) ) {
        $request_path = '/' . trim( substr( $request_path, strlen( $home_path ) - 1 ), '/' ) . '/';
    }

    foreach ( $redirects as $source => $target ) {
        $source_path = '/' . trim( (string) $source, '/' ) . '/';

        if ( strtolower( $request_path ) !== strtolower( $source_path ) ) {
            continue;
        }

        $target_url = (string) $target;
        if ( ! preg_match( '#^https?://#i', $target_url ) ) {
            $target_url = home_url( '/' . trim( $target_url, '/' ) . '/' );
        }

        wp_safe_redirect( $target_url, 301 );
        exit;
    }
} );

add_action( 'wp_footer', function (): void {
    if ( class_exists( 'Cookie_Notice' ) || function_exists( 'cn_cookies_accepted' ) ) {
        return;
    }

    $fallback_js = get_option( 'kadence_child_cookie_fallback_js' );
    if ( is_string( $fallback_js ) && '' !== trim( $fallback_js ) ) {
        echo $fallback_js; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}, 100 );

add_filter( 'wp_nav_menu_items', function ( string $items, stdClass $args ): string {
    if ( is_admin() || ! function_exists( 'pll_the_languages' ) ) {
        return $items;
    }

    $theme_location = $args->theme_location ?? '';
    if ( ! in_array( $theme_location, [ 'primary', 'mobile', 'primary___en', 'mobile___en' ], true ) ) {
        return $items;
    }

    $languages = pll_the_languages( [
        'raw'           => 1,
        'hide_if_empty' => 0,
    ] );

    if ( ! is_array( $languages ) || count( $languages ) < 2 ) {
        return $items;
    }

    $switcher = '<li class="menu-item menu-item-language-switcher"><span class="site-language-switcher" aria-label="Language switcher">';
    foreach ( $languages as $language ) {
        $classes = [ 'site-language-switcher__link' ];
        if ( ! empty( $language['current_lang'] ) ) {
            $classes[] = 'is-current';
        }

        $switcher .= sprintf(
            '<a class="%1$s" href="%2$s" hreflang="%3$s">%4$s</a>',
            esc_attr( implode( ' ', $classes ) ),
            esc_url( $language['url'] ?? home_url( '/' ) ),
            esc_attr( $language['locale'] ?? $language['slug'] ?? '' ),
            esc_html( strtoupper( $language['slug'] ?? '' ) )
        );
    }
    $switcher .= '</span></li>';

    return $items . $switcher;
}, 20, 2 );
