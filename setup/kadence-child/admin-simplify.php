<?php
/**
 * admin-simplify.php — Interface admin simplifiée pour artiste non-technicien
 * Inclus depuis functions.php du thème enfant.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Capacites du role "Client Galerie".
 *
 * Reduit apres l'incident de securite du 08/07/2026 : le compte portant ce
 * role avait ete compromis par force brute et disposait alors d'un acces aux
 * reglages WooCommerce, donc aux passerelles de paiement Stripe.
 *
 * Retire volontairement :
 *   manage_woocommerce        -> reglages WooCommerce et Stripe
 *   edit_others_posts         -> modification des articles d'autrui
 *   delete_others_posts       -> suppression des articles d'autrui
 *   delete_published_posts    -> suppression d'articles publies
 *   delete_others_products    -> suppression des oeuvres d'autrui
 *   delete_published_products -> suppression d'oeuvres publiees
 *
 * Conserve sciemment :
 *   edit_others_products      -> les oeuvres sont souvent creees par l'admin
 *   edit_others_shop_orders   -> les commandes appartiennent aux clients,
 *                                sans cette capacite aucune commande n'est
 *                                traitable
 */
function galerie_client_caps(): array {
    return [
        'read'                     => true,
        'upload_files'             => true,
        'edit_posts'               => true,
        'edit_published_posts'     => true,
        'publish_posts'            => true,
        'delete_posts'             => true,
        'edit_product'             => true,
        'read_product'             => true,
        'delete_product'           => true,
        'edit_products'            => true,
        'edit_others_products'     => true,
        'edit_published_products'  => true,
        'publish_products'         => true,
        'delete_products'          => true,
        'read_shop_order'          => true,
        'read_shop_orders'         => true,
        'edit_shop_order'          => true,
        'edit_shop_orders'         => true,
        'edit_others_shop_orders'  => true,
        'view_woocommerce_reports' => true,
        'galerie_client_access'    => true,
    ];
}

/**
 * Capacites a retirer explicitement.
 *
 * add_cap() n'enleve rien : sans ce nettoyage, les capacites accordees par
 * une version anterieure du theme resteraient en base indefiniment.
 */
function galerie_client_caps_revoquees(): array {
    return [
        'manage_woocommerce',
        'edit_others_posts',
        'delete_others_posts',
        'delete_published_posts',
        'delete_others_products',
        'delete_published_products',
    ];
}

function galerie_client_ensure_role(): void {
    $role = get_role( 'galerie_client' );
    if ( ! $role ) {
        add_role( 'galerie_client', 'Client Galerie', galerie_client_caps() );
        $role = get_role( 'galerie_client' );
    }

    if ( $role ) {
        foreach ( galerie_client_caps() as $cap => $grant ) {
            $role->add_cap( $cap, $grant );
        }
        foreach ( galerie_client_caps_revoquees() as $cap ) {
            if ( isset( $role->capabilities[ $cap ] ) ) {
                $role->remove_cap( $cap );
            }
        }
    }
}
add_action( 'init', 'galerie_client_ensure_role' );

function galerie_client_is_restricted_user(): bool {
    $user = wp_get_current_user();
    return $user instanceof WP_User
        && in_array( 'galerie_client', (array) $user->roles, true )
        && ! in_array( 'administrator', (array) $user->roles, true );
}

function galerie_client_orders_url(): string {
    return class_exists( 'WooCommerce' )
        ? admin_url( 'admin.php?page=wc-orders' )
        : admin_url( 'edit.php?post_type=shop_order' );
}

function galerie_client_page_edit_url( string $slug ): string {
    $page = get_page_by_path( $slug );
    $link = $page ? get_edit_post_link( $page->ID, '' ) : '';
    return $link ?: admin_url( 'edit.php?post_type=page' );
}

function galerie_client_action_cards(): void {
    $cards = [
        [
            'title' => 'Ajouter une oeuvre',
            'text'  => 'Créer une fiche tableau vendable avec photo, prix et stock.',
            'url'   => admin_url( 'admin.php?page=galerie-artwork-form' ),
        ],
        [
            'title' => 'Voir les oeuvres',
            'text'  => 'Modifier un prix, marquer une oeuvre vendue ou changer une photo.',
            'url'   => admin_url( 'admin.php?page=galerie-artworks' ),
        ],
        [
            'title' => 'Ajouter un article',
            'text'  => 'Publier une actualité, un texte d’atelier ou un article artistique.',
            'url'   => admin_url( 'admin.php?page=galerie-blog-form' ),
        ],
        [
            'title' => 'Voir les articles',
            'text'  => 'Modifier, relire, publier ou supprimer les articles du blog.',
            'url'   => admin_url( 'admin.php?page=galerie-blog-posts' ),
        ],
        [
            'title' => 'Ajouter une prestation',
            'text'  => 'Creer un cours, une visite, une conference ou un service culturel.',
            'url'   => admin_url( 'admin.php?page=galerie-service-form' ),
        ],
        [
            'title' => 'Voir les prestations',
            'text'  => 'Modifier les tarifs, durees, formats et descriptions des services.',
            'url'   => admin_url( 'admin.php?page=galerie-services' ),
        ],
        [
            'title' => 'Commandes',
            'text'  => 'Suivre les ventes, adresses, paiements et factures.',
            'url'   => galerie_client_orders_url(),
        ],
        [
            'title' => 'Réservations Amelia',
            'text'  => 'Gérer les rendez-vous et les créneaux dans le calendrier Amelia.',
            'url'   => admin_url( 'admin.php?page=wpamelia-dashboard' ),
        ],
        [
            'title' => 'Contact',
            'text'  => 'Modifier le texte de la page Contact. Les messages arrivent par email.',
            'url'   => galerie_client_page_edit_url( 'contact' ),
        ],
        [
            'title' => 'Voir le site',
            'text'  => 'Ouvrir le site public dans un nouvel onglet.',
            'url'   => home_url( '/' ),
            'blank' => true,
        ],
    ];
    ?>
    <div class="galerie-client-cards">
        <?php foreach ( $cards as $card ) : ?>
            <a class="galerie-client-card" href="<?php echo esc_url( $card['url'] ); ?>" <?php echo ! empty( $card['blank'] ) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                <strong><?php echo esc_html( $card['title'] ); ?></strong>
                <span><?php echo esc_html( $card['text'] ); ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="galerie-client-rules">
        <strong>Règles simples</strong>
        <ul>
            <li>Pour vendre une oeuvre, utiliser toujours <strong>Oeuvres &gt; Ajouter une oeuvre</strong>.</li>
            <li>Mettre le stock à <strong>1</strong> pour une oeuvre unique.</li>
            <li>Ne pas toucher aux menus Extensions, Réglages techniques, Thème ou Stripe sans accompagnement.</li>
            <li>Après une vente, vérifier la commande et préparer l’emballage avant de marquer la commande terminée.</li>
        </ul>
    </div>
    <?php
}

add_action( 'admin_menu', function (): void {
    add_menu_page(
        'Mode Client',
        'Mode Client',
        'edit_posts',
        'galerie-client',
        function (): void {
            ?>
            <div class="wrap galerie-client-page">
                <h1>Mode Client</h1>
                <p class="galerie-client-intro">Les actions essentielles du site sont regroupées ici. C’est la page à utiliser au quotidien.</p>
                <?php galerie_client_action_cards(); ?>
            </div>
            <?php
        },
        'dashicons-art',
        2
    );
}, 5 );

// ── Suppression des menus inutiles ─────────────────────────
add_action( 'admin_menu', function (): void {
    remove_menu_page( 'edit-comments.php' );           // Commentaires
    remove_menu_page( 'tools.php' );                   // Outils
    remove_menu_page( 'edit.php?post_type=artwork' );  // CPT technique, les œuvres vendables sont des produits
    remove_submenu_page( 'themes.php',   'theme-editor.php' );   // Éditeur de thème
    remove_submenu_page( 'plugins.php',  'plugin-editor.php' );  // Éditeur d'extension
}, 999 );

add_action( 'admin_menu', function (): void {
    global $menu, $submenu;

    foreach ( $menu as &$item ) {
        if ( isset( $item[2] ) && 'edit.php?post_type=product' === $item[2] ) {
            $item[0] = 'Œuvres';
        }
    }
    unset( $item );

    if ( isset( $submenu['edit.php?post_type=product'] ) ) {
        foreach ( $submenu['edit.php?post_type=product'] as &$item ) {
            if ( isset( $item[2] ) && 'edit.php?post_type=product' === $item[2] ) {
                $item[0] = 'Toutes les œuvres';
            }
            if ( isset( $item[2] ) && 'post-new.php?post_type=product' === $item[2] ) {
                $item[0] = 'Ajouter une œuvre';
            }
        }
        unset( $item );
    }
}, 1000 );

// ── Réorganisation du menu admin ──────────────────────────
add_filter( 'custom_menu_order', '__return_true' );
add_filter( 'menu_order', function ( array $menu_order ): array {
    return [
        'galerie-client',                   // Mode Client
        'index.php',                        // Tableau de bord
        'edit.php?post_type=product',       // Œuvres vendables
        'edit.php?post_type=shop_order',    // Commandes
        'amelia',                           // Réservations
        'edit.php',                         // Blog
        'upload.php',                       // Médias
        'options-general.php',              // Réglages
    ];
} );

add_action( 'admin_menu', function (): void {
    if ( ! galerie_client_is_restricted_user() ) {
        return;
    }

    global $menu;
    $allowed = [ 'galerie-client', 'index.php', 'upload.php', 'profile.php' ];

    foreach ( $menu as $index => $item ) {
        $slug = $item[2] ?? '';
        if ( ! in_array( $slug, $allowed, true ) ) {
            unset( $menu[ $index ] );
        }
    }
}, 2000 );

add_action( 'admin_init', function (): void {
    if ( ! galerie_client_is_restricted_user() ) {
        return;
    }

    $pagenow       = $GLOBALS['pagenow'] ?? '';
    $page          = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    $allowed_pages = [
        'galerie-client',
        'galerie-artworks',
        'galerie-artwork-form',
        'galerie-blog-posts',
        'galerie-blog-form',
        'galerie-services',
        'galerie-service-form',
        'wc-orders',
        'wpamelia-dashboard',
        'amelia',
    ];
    $allowed_files = [
        'index.php',
        'profile.php',
        'upload.php',
        'media-upload.php',
        'async-upload.php',
        'admin-ajax.php',
        'admin-post.php',
    ];

    if ( 'admin.php' === $pagenow && in_array( $page, $allowed_pages, true ) ) {
        return;
    }

    if ( in_array( $pagenow, $allowed_files, true ) ) {
        return;
    }

    wp_safe_redirect( admin_url( 'admin.php?page=galerie-client' ) );
    exit;
}, 1 );

add_filter( 'login_redirect', function ( string $redirect_to, string $requested_redirect_to, WP_User|WP_Error $user ) {
    if ( $user instanceof WP_User && in_array( 'galerie_client', (array) $user->roles, true ) ) {
        return admin_url( 'admin.php?page=galerie-client' );
    }

    return $redirect_to;
}, 10, 3 );

// ── Widget de bienvenue ────────────────────────────────────
add_action( 'wp_dashboard_setup', function (): void {
    wp_add_dashboard_widget(
        'artwork_welcome',
        __( 'Bienvenue !', 'kadence-child' ),
        function (): void {
            $name = esc_html( get_bloginfo( 'name' ) );
            ?>
            <div style="line-height:1.8;padding:4px 0;">
                <p style="font-size:15px;margin-bottom:10px;">
                    Bienvenue sur <strong><?php echo $name; ?></strong> !
                </p>
                <p>Pour ajouter un tableau vendable, cliquez sur
                    <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>">
                        <strong>Produits → Ajouter</strong></a>.
                </p>
                <ul style="list-style:disc;margin-left:1.4em;margin-top:8px;">
                    <li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>">Voir toutes les œuvres vendables</a></li>
                    <li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order' ) ); ?>">Voir les commandes</a></li>
                    <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=amelia' ) ); ?>">Gérer les réservations</a></li>
                </ul>
                <p style="margin-top:12px;color:#888;font-size:13px;">
                    En cas de problème, contactez votre développeur.
                </p>
            </div>
            <?php
        },
        null,
        null,
        'normal',
        'high'
    );
} );

// ── Suppression des widgets inutiles du tableau de bord ───
add_action( 'wp_dashboard_setup', function (): void {
    remove_meta_box( 'dashboard_primary',      'dashboard', 'side' );   // Actu WordPress
    remove_meta_box( 'dashboard_site_health',  'dashboard', 'normal' ); // État du site
    remove_meta_box( 'dashboard_quick_press',  'dashboard', 'side' );   // Publication rapide
    remove_action( 'welcome_panel', 'wp_welcome_panel' );
}, 20 );

// ── Masquer les notices admin pour les non-admins ─────────
add_action( 'admin_head', function (): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        remove_all_actions( 'admin_notices' );
        remove_all_actions( 'all_admin_notices' );
    }
}, 1 );

// ── Barre d'outils : retirer les éléments superflus ──────
add_action( 'admin_bar_menu', function ( WP_Admin_Bar $bar ): void {
    $bar->remove_node( 'comments' );
    $bar->remove_node( 'new-user' );
    $bar->remove_node( 'new-link' );
}, 999 );

// ── Pied de page admin personnalisé ───────────────────────
add_filter( 'admin_footer_text', function (): string {
    return 'Site de <strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong>';
} );

// ── Logo de connexion : utiliser le logo du site ──────────
add_action( 'login_enqueue_scripts', function (): void {
    $logo_id = get_theme_mod( 'custom_logo' );
    if ( ! $logo_id ) {
        return;
    }
    $logo_url = wp_get_attachment_image_url( $logo_id, 'medium' );
    if ( ! $logo_url ) {
        return;
    }
    ?>
    <style>
        #login h1 a {
            background-image: url('<?php echo esc_url( $logo_url ); ?>');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            width: 200px;
            height: 80px;
        }
    </style>
    <?php
} );

add_filter( 'login_headerurl',  fn() => home_url() );
add_filter( 'login_headertext', fn() => esc_html( get_bloginfo( 'name' ) ) );

add_action( 'wp_dashboard_setup', function (): void {
    wp_add_dashboard_widget(
        'galerie_client_actions',
        'Mode Client - actions essentielles',
        function (): void {
            echo '<p>Les raccourcis ci-dessous regroupent les actions utiles au quotidien.</p>';
            galerie_client_action_cards();
        },
        null,
        null,
        'normal',
        'high'
    );
}, 5 );

add_action( 'add_meta_boxes_product', function (): void {
    add_meta_box(
        'galerie_product_help',
        'Aide - oeuvre vendable',
        function (): void {
            ?>
            <ol class="galerie-product-help">
                <li>Titre : nom de l’oeuvre.</li>
                <li>Image produit : photo principale.</li>
                <li>Prix normal : prix de vente.</li>
                <li>Inventaire : cocher gestion du stock et mettre quantité 1.</li>
                <li>Description : histoire, technique, dimensions, année.</li>
                <li>Publier, puis vérifier la fiche sur le site.</li>
            </ol>
            <p><strong>Si l’oeuvre est vendue :</strong> mettre le stock à 0.</p>
            <?php
        },
        'product',
        'side',
        'high'
    );
} );

add_action( 'admin_enqueue_scripts', function (): void {
    wp_add_inline_style(
        'common',
        '
        .galerie-client-page .galerie-client-intro { font-size: 15px; max-width: 760px; }
        .galerie-client-cards { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin: 16px 0; }
        .galerie-client-card { background: #fff; border: 1px solid #dcdcde; border-left: 4px solid #8B7355; box-shadow: 0 1px 2px rgba(0,0,0,.04); color: #1d2327; display: block; min-height: 92px; padding: 16px; text-decoration: none; }
        .galerie-client-card:hover { border-left-color: #1d2327; box-shadow: 0 2px 8px rgba(0,0,0,.08); color: #1d2327; }
        .galerie-client-card strong { display: block; font-size: 16px; margin-bottom: 8px; }
        .galerie-client-card span { color: #646970; display: block; line-height: 1.45; }
        .galerie-client-rules { background: #f6f3ee; border: 1px solid #d8cdbd; margin-top: 18px; max-width: 860px; padding: 16px 18px; }
        .galerie-client-rules ul { list-style: disc; margin-left: 20px; }
        .galerie-product-help { margin-left: 18px; }
        .galerie-product-help li { margin-bottom: 7px; }
        '
    );
} );

add_action( 'admin_menu', function (): void {
    add_submenu_page(
        'galerie-client',
        'Mes oeuvres',
        'Mes oeuvres',
        'edit_posts',
        'galerie-artworks',
        'galerie_client_artworks_page'
    );

    add_submenu_page(
        'galerie-client',
        'Ajouter une oeuvre',
        'Ajouter une oeuvre',
        'edit_posts',
        'galerie-artwork-form',
        'galerie_client_artwork_form_page'
    );

    add_submenu_page(
        'galerie-client',
        'Mes articles',
        'Mes articles',
        'edit_posts',
        'galerie-blog-posts',
        'galerie_client_blog_posts_page'
    );

    add_submenu_page(
        'galerie-client',
        'Ajouter un article',
        'Ajouter un article',
        'edit_posts',
        'galerie-blog-form',
        'galerie_client_blog_form_page'
    );

    add_submenu_page(
        'galerie-client',
        'Mes prestations',
        'Mes prestations',
        'edit_posts',
        'galerie-services',
        'galerie_client_services_page'
    );

    add_submenu_page(
        'galerie-client',
        'Ajouter une prestation',
        'Ajouter une prestation',
        'edit_posts',
        'galerie-service-form',
        'galerie_client_service_form_page'
    );
}, 6 );

function galerie_client_artwork_category_id(): int {
    $term = term_exists( 'Oeuvres originales', 'product_cat' );
    if ( ! $term ) {
        $term = wp_insert_term( 'Oeuvres originales', 'product_cat', [ 'slug' => 'oeuvres-originales' ] );
    }

    return is_array( $term ) ? (int) $term['term_id'] : (int) $term;
}

function galerie_client_first_term_id( int $post_id, string $taxonomy ): int {
    $terms = wp_get_post_terms( $post_id, $taxonomy, [ 'fields' => 'ids' ] );
    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        return 0;
    }

    return (int) $terms[0];
}

function galerie_client_taxonomy_select( string $name, string $taxonomy, int $selected = 0 ): void {
    $terms = get_terms( [
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
        'orderby'    => 'name',
    ] );

    echo '<select name="' . esc_attr( $name ) . '">';
    echo '<option value="">Choisir</option>';
    if ( ! is_wp_error( $terms ) ) {
        foreach ( $terms as $term ) {
            echo '<option value="' . esc_attr( (string) $term->term_id ) . '" ' . selected( $selected, $term->term_id, false ) . '>' . esc_html( $term->name ) . '</option>';
        }
    }
    echo '</select>';
}

function galerie_client_post_value( string $key, string $fallback = '' ): string {
    if ( isset( $_POST[ $key ] ) ) {
        return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
    }

    return $fallback;
}

function galerie_client_textarea_value( string $key, string $fallback = '' ): string {
    if ( isset( $_POST[ $key ] ) ) {
        return wp_kses_post( wp_unslash( $_POST[ $key ] ) );
    }

    return $fallback;
}

function galerie_client_product_is_artwork( int $product_id ): bool {
    return 'product' === get_post_type( $product_id ) && 'cultural_service' !== get_post_meta( $product_id, '_service_type', true );
}

add_action( 'admin_init', function (): void {
    if ( ! isset( $_POST['galerie_artwork_submit'] ) ) {
        return;
    }

    if (
        ! current_user_can( 'edit_posts' ) ||
        ! isset( $_POST['galerie_artwork_nonce'] ) ||
        ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['galerie_artwork_nonce'] ) ), 'galerie_artwork_save' )
    ) {
        wp_die( esc_html__( 'Action non autorisée.', 'kadence-child' ) );
    }

    $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
    if ( $product_id && ! galerie_client_product_is_artwork( $product_id ) ) {
        wp_die( esc_html__( 'Cette fiche ne peut pas être modifiée ici.', 'kadence-child' ) );
    }

    $title       = galerie_client_post_value( 'artwork_title' );
    $price_raw   = str_replace( ',', '.', galerie_client_post_value( 'artwork_price' ) );
    $image_id    = isset( $_POST['artwork_image_id'] ) ? absint( $_POST['artwork_image_id'] ) : 0;
    $submit_mode = galerie_client_post_value( 'galerie_artwork_submit', 'draft' );
    $post_status = 'publish' === $submit_mode ? 'publish' : 'draft';
    $errors      = [];

    if ( '' === $title ) {
        $errors[] = 'Ajoutez un titre.';
    }

    if ( '' === $price_raw || ! is_numeric( $price_raw ) ) {
        $errors[] = 'Ajoutez un prix valide.';
    }

    if ( 'publish' === $post_status && ! $image_id ) {
        $errors[] = 'Ajoutez une photo principale avant de publier.';
    }

    if ( $errors ) {
        $GLOBALS['galerie_client_artwork_errors'] = $errors;
        return;
    }

    $price             = wc_format_decimal( $price_raw );
    $short_description = galerie_client_textarea_value( 'artwork_short_description' );
    $description       = galerie_client_textarea_value( 'artwork_description' );
    $artwork_status    = galerie_client_post_value( 'artwork_status', 'available' );

    $post_data = [
        'post_type'    => 'product',
        'post_title'   => $title,
        'post_excerpt' => $short_description,
        'post_content' => $description,
        'post_status'  => $post_status,
    ];

    if ( $product_id ) {
        $post_data['ID'] = $product_id;
        $product_id = wp_update_post( $post_data, true );
    } else {
        $product_id = wp_insert_post( $post_data, true );
    }

    if ( is_wp_error( $product_id ) ) {
        $GLOBALS['galerie_client_artwork_errors'] = [ $product_id->get_error_message() ];
        return;
    }

    wp_set_object_terms( $product_id, 'simple', 'product_type' );
    wp_set_object_terms( $product_id, [ galerie_client_artwork_category_id() ], 'product_cat' );

    foreach ( [
        'artwork_technique' => 'technique',
        'artwork_format'    => 'format_oeuvre',
        'artwork_theme'     => 'theme_oeuvre',
    ] as $field => $taxonomy ) {
        $term_id = isset( $_POST[ $field ] ) ? absint( $_POST[ $field ] ) : 0;
        if ( $term_id ) {
            wp_set_object_terms( $product_id, [ $term_id ], $taxonomy );
        }
    }

    if ( $image_id ) {
        set_post_thumbnail( $product_id, $image_id );
    }

    update_post_meta( $product_id, '_regular_price', $price );
    update_post_meta( $product_id, '_price', $price );
    update_post_meta( $product_id, '_manage_stock', 'yes' );
    update_post_meta( $product_id, '_virtual', 'no' );
    update_post_meta( $product_id, '_downloadable', 'no' );
    update_post_meta( $product_id, '_visibility', 'visible' );
    update_post_meta( $product_id, '_artwork_status', in_array( $artwork_status, [ 'available', 'reserved', 'sold' ], true ) ? $artwork_status : 'available' );

    $is_unavailable = in_array( $artwork_status, [ 'reserved', 'sold' ], true );
    update_post_meta( $product_id, '_stock', $is_unavailable ? '0' : '1' );
    update_post_meta( $product_id, '_stock_status', $is_unavailable ? 'outofstock' : 'instock' );

    foreach ( [
        'artwork_height' => '_artwork_height',
        'artwork_width'  => '_artwork_width',
        'artwork_depth'  => '_artwork_depth',
        'artwork_year'   => '_artwork_year',
    ] as $field => $meta_key ) {
        update_post_meta( $product_id, $meta_key, galerie_client_post_value( $field ) );
    }

    update_post_meta( $product_id, '_artwork_certificate', isset( $_POST['artwork_certificate'] ) ? '1' : '0' );

    if ( function_exists( 'wc_delete_product_transients' ) ) {
        wc_delete_product_transients( $product_id );
    }

    wp_safe_redirect( admin_url( 'admin.php?page=galerie-artwork-form&product_id=' . (int) $product_id . '&saved=1' ) );
    exit;
} );

add_action( 'admin_post_galerie_mark_sold', function (): void {
    $product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
    if (
        ! current_user_can( 'edit_post', $product_id ) ||
        ! $product_id ||
        ! galerie_client_product_is_artwork( $product_id ) ||
        ! isset( $_GET['_wpnonce'] ) ||
        ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'galerie_mark_sold_' . $product_id )
    ) {
        wp_die( esc_html__( 'Action non autorisée.', 'kadence-child' ) );
    }

    update_post_meta( $product_id, '_artwork_status', 'sold' );
    update_post_meta( $product_id, '_stock', '0' );
    update_post_meta( $product_id, '_stock_status', 'outofstock' );

    if ( function_exists( 'wc_delete_product_transients' ) ) {
        wc_delete_product_transients( $product_id );
    }

    wp_safe_redirect( admin_url( 'admin.php?page=galerie-artworks&updated=sold' ) );
    exit;
} );

add_action( 'admin_post_galerie_delete_artwork', function (): void {
    $product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
    if (
        ! current_user_can( 'delete_post', $product_id ) ||
        ! $product_id ||
        ! galerie_client_product_is_artwork( $product_id ) ||
        ! isset( $_GET['_wpnonce'] ) ||
        ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'galerie_delete_artwork_' . $product_id )
    ) {
        wp_die( esc_html__( 'Action non autorisée.', 'kadence-child' ) );
    }

    $trashed = wp_trash_post( $product_id );
    if ( $trashed && function_exists( 'wc_delete_product_transients' ) ) {
        wc_delete_product_transients( $product_id );
    }

    wp_safe_redirect( admin_url( 'admin.php?page=galerie-artworks&updated=' . ( $trashed ? 'deleted' : 'delete-error' ) ) );
    exit;
} );

function galerie_client_artworks_page(): void {
    $query = new WP_Query( [
        'post_type'      => 'product',
        'post_status'    => [ 'publish', 'draft' ],
        'posts_per_page' => 50,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => [
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
        ],
    ] );
    ?>
    <div class="wrap galerie-client-page">
        <h1>Mes oeuvres</h1>
        <p class="galerie-client-intro">Liste simplifiée des oeuvres vendables. Utilisez cette page au lieu de la liste WooCommerce complète.</p>
        <p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=galerie-artwork-form' ) ); ?>">Ajouter une oeuvre</a></p>

        <?php if ( isset( $_GET['updated'] ) ) : ?>
            <?php $updated = sanitize_key( wp_unslash( $_GET['updated'] ) ); ?>
            <?php if ( 'deleted' === $updated ) : ?>
                <div class="notice notice-success is-dismissible"><p>Oeuvre déplacée dans la corbeille.</p></div>
            <?php elseif ( 'delete-error' === $updated ) : ?>
                <div class="notice notice-error is-dismissible"><p>Impossible de supprimer l'oeuvre.</p></div>
            <?php else : ?>
            <div class="notice notice-success is-dismissible"><p>Oeuvre mise à jour.</p></div>
            <?php endif; ?>
        <?php endif; ?>

        <table class="widefat fixed striped galerie-artworks-table">
            <thead>
                <tr>
                    <th>Photo</th>
                    <th>Oeuvre</th>
                    <th>Prix</th>
                    <th>Statut</th>
                    <th>Stock</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $query->have_posts() ) : ?>
                    <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                        <?php
                        $product_id = get_the_ID();
                        $product    = wc_get_product( $product_id );
                        $status     = get_post_meta( $product_id, '_artwork_status', true ) ?: 'available';
                        ?>
                        <tr>
                            <td><?php echo get_the_post_thumbnail( $product_id, [ 70, 70 ] ) ?: '<span class="galerie-no-image">Aucune photo</span>'; ?></td>
                            <td>
                                <strong><?php echo esc_html( get_the_title() ?: '(sans titre)' ); ?></strong><br>
                                <span><?php echo esc_html( 'publish' === get_post_status() ? 'Publié' : 'Brouillon' ); ?></span>
                            </td>
                            <td><?php echo $product ? wp_kses_post( $product->get_price_html() ) : '-'; ?></td>
                            <td><?php echo esc_html( [ 'available' => 'Disponible', 'reserved' => 'Réservée', 'sold' => 'Vendue' ][ $status ] ?? 'Disponible' ); ?></td>
                            <td><?php echo $product ? esc_html( (string) $product->get_stock_quantity() ) : '-'; ?></td>
                            <td>
                                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=galerie-artwork-form&product_id=' . $product_id ) ); ?>">Modifier</a>
                                <a class="button" href="<?php echo esc_url( get_permalink( $product_id ) ); ?>" target="_blank" rel="noopener noreferrer">Voir</a>
                                <?php if ( 'sold' !== $status ) : ?>
                                    <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=galerie_mark_sold&product_id=' . $product_id ), 'galerie_mark_sold_' . $product_id ) ); ?>">Marquer vendue</a>
                                <?php endif; ?>
                                <a class="button galerie-delete-artwork"
                                   href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=galerie_delete_artwork&product_id=' . $product_id ), 'galerie_delete_artwork_' . $product_id ) ); ?>"
                                   onclick="return confirm('Mettre cette oeuvre dans la corbeille ?');">Supprimer</a>
                            </td>
                        </tr>
                    <?php endwhile; wp_reset_postdata(); ?>
                <?php else : ?>
                    <tr><td colspan="6">Aucune oeuvre pour le moment.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function galerie_client_artwork_form_page(): void {
    $product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
    $product    = $product_id && galerie_client_product_is_artwork( $product_id ) ? wc_get_product( $product_id ) : null;
    $post       = $product ? get_post( $product_id ) : null;

    $title       = galerie_client_post_value( 'artwork_title', $post ? $post->post_title : '' );
    $price       = galerie_client_post_value( 'artwork_price', $product ? (string) $product->get_regular_price() : '' );
    $image_id    = isset( $_POST['artwork_image_id'] ) ? absint( $_POST['artwork_image_id'] ) : ( $product_id ? get_post_thumbnail_id( $product_id ) : 0 );
    $short_desc  = galerie_client_textarea_value( 'artwork_short_description', $post ? $post->post_excerpt : '' );
    $description = galerie_client_textarea_value( 'artwork_description', $post ? $post->post_content : '' );
    $status      = galerie_client_post_value( 'artwork_status', $product_id ? get_post_meta( $product_id, '_artwork_status', true ) ?: 'available' : 'available' );
    ?>
    <div class="wrap galerie-client-page">
        <h1><?php echo $product_id ? 'Modifier une oeuvre' : 'Ajouter une oeuvre'; ?></h1>
        <p class="galerie-client-intro">Remplissez les étapes dans l'ordre. WooCommerce est configuré automatiquement en arrière-plan.</p>

        <?php if ( isset( $_GET['saved'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p>Oeuvre enregistrée.</p></div>
        <?php endif; ?>

        <?php if ( ! empty( $GLOBALS['galerie_client_artwork_errors'] ) ) : ?>
            <div class="notice notice-error">
                <p><strong>Impossible de publier pour le moment :</strong></p>
                <ul>
                    <?php foreach ( $GLOBALS['galerie_client_artwork_errors'] as $error ) : ?>
                        <li><?php echo esc_html( $error ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="galerie-artwork-form">
            <?php wp_nonce_field( 'galerie_artwork_save', 'galerie_artwork_nonce' ); ?>
            <input type="hidden" name="product_id" value="<?php echo esc_attr( (string) $product_id ); ?>">

            <section class="galerie-form-card">
                <h2>1. Photo principale</h2>
                <input type="hidden" id="artwork_image_id" name="artwork_image_id" value="<?php echo esc_attr( (string) $image_id ); ?>">
                <div id="artwork_image_preview" class="galerie-image-preview">
                    <?php echo $image_id ? wp_get_attachment_image( $image_id, 'medium' ) : '<span>Aucune photo sélectionnée</span>'; ?>
                </div>
                <p>
                    <button type="button" class="button" id="galerie_select_image">Choisir une photo</button>
                    <button type="button" class="button" id="galerie_remove_image">Retirer</button>
                </p>
            </section>

            <section class="galerie-form-card">
                <h2>2. Informations de base</h2>
                <div class="galerie-form-grid">
                    <label>Titre de l'oeuvre <input type="text" name="artwork_title" value="<?php echo esc_attr( $title ); ?>" required></label>
                    <label>Année <input type="number" name="artwork_year" value="<?php echo esc_attr( galerie_client_post_value( 'artwork_year', $product_id ? get_post_meta( $product_id, '_artwork_year', true ) : '' ) ); ?>" min="1900" max="<?php echo esc_attr( (string) date( 'Y' ) ); ?>"></label>
                    <label>Technique <?php galerie_client_taxonomy_select( 'artwork_technique', 'technique', galerie_client_first_term_id( $product_id, 'technique' ) ); ?></label>
                    <label>Format <?php galerie_client_taxonomy_select( 'artwork_format', 'format_oeuvre', galerie_client_first_term_id( $product_id, 'format_oeuvre' ) ); ?></label>
                    <label>Thème <?php galerie_client_taxonomy_select( 'artwork_theme', 'theme_oeuvre', galerie_client_first_term_id( $product_id, 'theme_oeuvre' ) ); ?></label>
                </div>
            </section>

            <section class="galerie-form-card">
                <h2>3. Dimensions et vente</h2>
                <div class="galerie-form-grid">
                    <label>Hauteur (cm) <input type="number" step="0.1" name="artwork_height" value="<?php echo esc_attr( galerie_client_post_value( 'artwork_height', $product_id ? get_post_meta( $product_id, '_artwork_height', true ) : '' ) ); ?>"></label>
                    <label>Largeur (cm) <input type="number" step="0.1" name="artwork_width" value="<?php echo esc_attr( galerie_client_post_value( 'artwork_width', $product_id ? get_post_meta( $product_id, '_artwork_width', true ) : '' ) ); ?>"></label>
                    <label>Profondeur (cm) <input type="number" step="0.1" name="artwork_depth" value="<?php echo esc_attr( galerie_client_post_value( 'artwork_depth', $product_id ? get_post_meta( $product_id, '_artwork_depth', true ) : '' ) ); ?>"></label>
                    <label>Prix (€) <input type="number" step="0.01" min="0" name="artwork_price" value="<?php echo esc_attr( $price ); ?>" required></label>
                    <label>Statut
                        <select name="artwork_status">
                            <option value="available" <?php selected( $status, 'available' ); ?>>Disponible</option>
                            <option value="reserved" <?php selected( $status, 'reserved' ); ?>>Réservée</option>
                            <option value="sold" <?php selected( $status, 'sold' ); ?>>Vendue</option>
                        </select>
                    </label>
                    <label class="galerie-checkbox"><input type="checkbox" name="artwork_certificate" value="1" <?php checked( '1', $product_id ? get_post_meta( $product_id, '_artwork_certificate', true ) : '1' ); ?>> Certificat d'authenticité fourni</label>
                </div>
            </section>

            <section class="galerie-form-card">
                <h2>4. Description</h2>
                <label>Description courte <textarea name="artwork_short_description" rows="3"><?php echo esc_textarea( $short_desc ); ?></textarea></label>
                <label>Description complète <textarea name="artwork_description" rows="8"><?php echo esc_textarea( $description ); ?></textarea></label>
            </section>

            <div class="galerie-form-actions">
                <button type="submit" name="galerie_artwork_submit" value="draft" class="button">Enregistrer en brouillon</button>
                <button type="submit" name="galerie_artwork_submit" value="publish" class="button button-primary">Publier l'oeuvre</button>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=galerie-artworks' ) ); ?>">Retour à mes oeuvres</a>
            </div>
        </form>
    </div>
    <?php
}

function galerie_client_service_category_id(): int {
    $term = term_exists( 'Prestations culturelles', 'product_cat' );
    if ( ! $term ) {
        $term = wp_insert_term( 'Prestations culturelles', 'product_cat', [ 'slug' => 'prestations-culturelles' ] );
    }

    return is_array( $term ) ? (int) $term['term_id'] : (int) $term;
}

function galerie_client_product_is_service( int $product_id ): bool {
    return 'product' === get_post_type( $product_id ) && 'cultural_service' === get_post_meta( $product_id, '_service_type', true );
}

add_action( 'admin_init', function (): void {
    if ( ! isset( $_POST['galerie_service_submit'] ) ) {
        return;
    }

    if (
        ! current_user_can( 'edit_posts' ) ||
        ! isset( $_POST['galerie_service_nonce'] ) ||
        ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['galerie_service_nonce'] ) ), 'galerie_service_save' )
    ) {
        wp_die( esc_html__( 'Action non autorisee.', 'kadence-child' ) );
    }

    $product_id = isset( $_POST['service_product_id'] ) ? absint( $_POST['service_product_id'] ) : 0;
    if ( $product_id && ! galerie_client_product_is_service( $product_id ) ) {
        wp_die( esc_html__( 'Cette prestation ne peut pas etre modifiee ici.', 'kadence-child' ) );
    }

    $title       = galerie_client_post_value( 'service_title' );
    $price_raw   = str_replace( ',', '.', galerie_client_post_value( 'service_price' ) );
    $image_id    = isset( $_POST['service_image_id'] ) ? absint( $_POST['service_image_id'] ) : 0;
    $submit_mode = galerie_client_post_value( 'galerie_service_submit', 'draft' );
    $post_status = 'publish' === $submit_mode ? 'publish' : 'draft';
    $errors      = [];

    if ( '' === $title ) {
        $errors[] = 'Ajoutez un titre.';
    }

    if ( '' === $price_raw || ! is_numeric( $price_raw ) ) {
        $errors[] = 'Ajoutez un prix valide.';
    }

    if ( $errors ) {
        $GLOBALS['galerie_client_service_errors'] = $errors;
        return;
    }

    $price       = wc_format_decimal( $price_raw );
    $duration    = galerie_client_post_value( 'service_duration' );
    $format      = galerie_client_post_value( 'service_format' );
    $audience    = galerie_client_post_value( 'service_audience' );
    $booking     = galerie_client_post_value( 'service_booking' );
    $excerpt     = galerie_client_textarea_value( 'service_excerpt' );
    $description = galerie_client_textarea_value( 'service_description' );

    if ( '' === $excerpt ) {
        $excerpt = trim( implode( ' - ', array_filter( [ $duration, $format ] ) ) );
    }

    $post_data = [
        'post_type'    => 'product',
        'post_title'   => $title,
        'post_excerpt' => $excerpt,
        'post_content' => $description,
        'post_status'  => $post_status,
    ];

    if ( $product_id ) {
        $post_data['ID'] = $product_id;
        $product_id = wp_update_post( $post_data, true );
    } else {
        $product_id = wp_insert_post( $post_data, true );
    }

    if ( is_wp_error( $product_id ) ) {
        $GLOBALS['galerie_client_service_errors'] = [ $product_id->get_error_message() ];
        return;
    }

    wp_set_object_terms( $product_id, 'simple', 'product_type' );
    wp_set_object_terms( $product_id, [ galerie_client_service_category_id() ], 'product_cat' );

    if ( $image_id ) {
        set_post_thumbnail( $product_id, $image_id );
    } else {
        delete_post_thumbnail( $product_id );
    }

    update_post_meta( $product_id, '_regular_price', $price );
    update_post_meta( $product_id, '_price', $price );
    update_post_meta( $product_id, '_manage_stock', 'no' );
    update_post_meta( $product_id, '_stock_status', 'instock' );
    update_post_meta( $product_id, '_virtual', 'yes' );
    update_post_meta( $product_id, '_downloadable', 'no' );
    update_post_meta( $product_id, '_sold_individually', 'yes' );
    update_post_meta( $product_id, '_visibility', 'visible' );
    update_post_meta( $product_id, '_service_type', 'cultural_service' );
    update_post_meta( $product_id, '_service_duration', $duration );
    update_post_meta( $product_id, '_service_format', $format );
    update_post_meta( $product_id, '_service_audience', $audience );
    update_post_meta( $product_id, '_service_booking', $booking );

    if ( function_exists( 'wc_delete_product_transients' ) ) {
        wc_delete_product_transients( $product_id );
    }

    wp_safe_redirect( admin_url( 'admin.php?page=galerie-service-form&product_id=' . (int) $product_id . '&saved=1' ) );
    exit;
} );

add_action( 'admin_post_galerie_delete_service', function (): void {
    $product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
    if (
        ! current_user_can( 'delete_post', $product_id ) ||
        ! $product_id ||
        ! galerie_client_product_is_service( $product_id ) ||
        ! isset( $_GET['_wpnonce'] ) ||
        ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'galerie_delete_service_' . $product_id )
    ) {
        wp_die( esc_html__( 'Action non autorisee.', 'kadence-child' ) );
    }

    $trashed = wp_trash_post( $product_id );
    if ( $trashed && function_exists( 'wc_delete_product_transients' ) ) {
        wc_delete_product_transients( $product_id );
    }

    wp_safe_redirect( admin_url( 'admin.php?page=galerie-services&updated=' . ( $trashed ? 'deleted' : 'delete-error' ) ) );
    exit;
} );

function galerie_client_services_page(): void {
    $query = new WP_Query( [
        'post_type'      => 'product',
        'post_status'    => [ 'publish', 'draft' ],
        'posts_per_page' => 50,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_key'       => '_service_type',
        'meta_value'     => 'cultural_service',
    ] );
    ?>
    <div class="wrap galerie-client-page">
        <h1>Mes prestations</h1>
        <p class="galerie-client-intro">Liste simplifiee des cours, visites, conferences et services culturels vendables ou reservables.</p>
        <p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=galerie-service-form' ) ); ?>">Ajouter une prestation</a></p>

        <?php if ( isset( $_GET['updated'] ) ) : ?>
            <?php $updated = sanitize_key( wp_unslash( $_GET['updated'] ) ); ?>
            <?php if ( 'deleted' === $updated ) : ?>
                <div class="notice notice-success is-dismissible"><p>Prestation deplacee dans la corbeille.</p></div>
            <?php elseif ( 'delete-error' === $updated ) : ?>
                <div class="notice notice-error is-dismissible"><p>Impossible de supprimer la prestation.</p></div>
            <?php else : ?>
                <div class="notice notice-success is-dismissible"><p>Prestation mise a jour.</p></div>
            <?php endif; ?>
        <?php endif; ?>

        <table class="widefat fixed striped galerie-artworks-table">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Prestation</th>
                    <th>Prix</th>
                    <th>Duree</th>
                    <th>Format</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $query->have_posts() ) : ?>
                    <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                        <?php
                        $product_id = get_the_ID();
                        $product    = wc_get_product( $product_id );
                        $is_public  = 'publish' === get_post_status( $product_id );
                        ?>
                        <tr>
                            <td><?php echo get_the_post_thumbnail( $product_id, [ 70, 70 ] ) ?: '<span class="galerie-no-image">Aucune image</span>'; ?></td>
                            <td><strong><?php echo esc_html( get_the_title() ?: '(sans titre)' ); ?></strong></td>
                            <td><?php echo $product ? wp_kses_post( $product->get_price_html() ) : '-'; ?></td>
                            <td><?php echo esc_html( get_post_meta( $product_id, '_service_duration', true ) ?: '-' ); ?></td>
                            <td><?php echo esc_html( get_post_meta( $product_id, '_service_format', true ) ?: '-' ); ?></td>
                            <td><?php echo esc_html( $is_public ? 'Publiee' : 'Brouillon' ); ?></td>
                            <td>
                                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=galerie-service-form&product_id=' . $product_id ) ); ?>">Modifier</a>
                                <?php if ( $is_public ) : ?>
                                    <a class="button" href="<?php echo esc_url( get_permalink( $product_id ) ); ?>" target="_blank" rel="noopener noreferrer">Voir</a>
                                <?php endif; ?>
                                <a class="button galerie-delete-artwork"
                                   href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=galerie_delete_service&product_id=' . $product_id ), 'galerie_delete_service_' . $product_id ) ); ?>"
                                   onclick="return confirm('Mettre cette prestation dans la corbeille ?');">Supprimer</a>
                            </td>
                        </tr>
                    <?php endwhile; wp_reset_postdata(); ?>
                <?php else : ?>
                    <tr><td colspan="7">Aucune prestation pour le moment.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function galerie_client_service_form_page(): void {
    $product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
    $product    = $product_id && galerie_client_product_is_service( $product_id ) ? wc_get_product( $product_id ) : null;
    $post       = $product ? get_post( $product_id ) : null;

    $title       = galerie_client_post_value( 'service_title', $post ? $post->post_title : '' );
    $price       = galerie_client_post_value( 'service_price', $product ? (string) $product->get_regular_price() : '' );
    $image_id    = isset( $_POST['service_image_id'] ) ? absint( $_POST['service_image_id'] ) : ( $product_id ? get_post_thumbnail_id( $product_id ) : 0 );
    $duration    = galerie_client_post_value( 'service_duration', $product_id ? get_post_meta( $product_id, '_service_duration', true ) : '' );
    $format      = galerie_client_post_value( 'service_format', $product_id ? get_post_meta( $product_id, '_service_format', true ) : '' );
    $audience    = galerie_client_post_value( 'service_audience', $product_id ? get_post_meta( $product_id, '_service_audience', true ) : '' );
    $booking     = galerie_client_post_value( 'service_booking', $product_id ? get_post_meta( $product_id, '_service_booking', true ) : '' );
    $excerpt     = galerie_client_textarea_value( 'service_excerpt', $post ? $post->post_excerpt : '' );
    $description = galerie_client_textarea_value( 'service_description', $post ? $post->post_content : '' );
    ?>
    <div class="wrap galerie-client-page">
        <h1><?php echo $product_id ? 'Modifier une prestation' : 'Ajouter une prestation'; ?></h1>
        <p class="galerie-client-intro">Cette interface cree une prestation WooCommerce virtuelle : cours, visite, conference, redaction ou accompagnement.</p>

        <?php if ( isset( $_GET['saved'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p>Prestation enregistree.</p></div>
        <?php endif; ?>

        <?php if ( ! empty( $GLOBALS['galerie_client_service_errors'] ) ) : ?>
            <div class="notice notice-error">
                <p><strong>Impossible de publier pour le moment :</strong></p>
                <ul>
                    <?php foreach ( $GLOBALS['galerie_client_service_errors'] as $error ) : ?>
                        <li><?php echo esc_html( $error ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="galerie-service-form">
            <?php wp_nonce_field( 'galerie_service_save', 'galerie_service_nonce' ); ?>
            <input type="hidden" name="service_product_id" value="<?php echo esc_attr( (string) $product_id ); ?>">

            <section class="galerie-form-card">
                <h2>1. Image</h2>
                <input type="hidden" id="service_image_id" name="service_image_id" value="<?php echo esc_attr( (string) $image_id ); ?>">
                <div id="service_image_preview" class="galerie-image-preview">
                    <?php echo $image_id ? wp_get_attachment_image( $image_id, 'medium' ) : '<span>Aucune image selectionnee</span>'; ?>
                </div>
                <p>
                    <button type="button" class="button" id="galerie_select_service_image">Choisir une image</button>
                    <button type="button" class="button" id="galerie_remove_service_image">Retirer</button>
                </p>
            </section>

            <section class="galerie-form-card">
                <h2>2. Informations</h2>
                <div class="galerie-form-grid">
                    <label>Titre <input type="text" name="service_title" value="<?php echo esc_attr( $title ); ?>" required></label>
                    <label>Prix (EUR) <input type="number" step="0.01" min="0" name="service_price" value="<?php echo esc_attr( $price ); ?>" required></label>
                    <label>Duree <input type="text" name="service_duration" value="<?php echo esc_attr( $duration ); ?>" placeholder="2h, forfait, sur devis"></label>
                    <label>Format <input type="text" name="service_format" value="<?php echo esc_attr( $format ); ?>" placeholder="Atelier, a distance, sur site"></label>
                    <label>Public <input type="text" name="service_audience" value="<?php echo esc_attr( $audience ); ?>" placeholder="Adultes, debutants, institutions"></label>
                    <label>Reservation <input type="text" name="service_booking" value="<?php echo esc_attr( $booking ); ?>" placeholder="Amelia, devis, contact"></label>
                </div>
            </section>

            <section class="galerie-form-card">
                <h2>3. Description</h2>
                <label>Resume court <textarea name="service_excerpt" rows="3"><?php echo esc_textarea( $excerpt ); ?></textarea></label>
                <label>Description complete <textarea name="service_description" rows="8"><?php echo esc_textarea( $description ); ?></textarea></label>
            </section>

            <div class="galerie-form-actions">
                <button type="submit" name="galerie_service_submit" value="draft" class="button">Enregistrer en brouillon</button>
                <button type="submit" name="galerie_service_submit" value="publish" class="button button-primary">Publier la prestation</button>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=galerie-services' ) ); ?>">Retour a mes prestations</a>
            </div>
        </form>
    </div>
    <?php
}

function galerie_client_blog_default_category_id(): int {
    $default = (int) get_option( 'default_category', 0 );
    if ( $default ) {
        return $default;
    }

    $term = term_exists( 'Actualités', 'category' );
    if ( ! $term ) {
        $term = wp_insert_term( 'Actualités', 'category', [ 'slug' => 'actualites' ] );
    }

    return is_array( $term ) ? (int) $term['term_id'] : (int) $term;
}

function galerie_client_blog_category_select( int $selected = 0 ): void {
    wp_dropdown_categories( [
        'taxonomy'          => 'category',
        'hide_empty'        => false,
        'orderby'           => 'name',
        'name'              => 'blog_category',
        'selected'          => $selected,
        'show_option_none'  => 'Choisir une catégorie',
        'option_none_value' => 0,
    ] );
}

add_action( 'admin_init', function (): void {
    if ( ! isset( $_POST['galerie_blog_submit'] ) ) {
        return;
    }

    if (
        ! current_user_can( 'edit_posts' ) ||
        ! isset( $_POST['galerie_blog_nonce'] ) ||
        ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['galerie_blog_nonce'] ) ), 'galerie_blog_save' )
    ) {
        wp_die( esc_html__( 'Action non autorisée.', 'kadence-child' ) );
    }

    $post_id = isset( $_POST['blog_post_id'] ) ? absint( $_POST['blog_post_id'] ) : 0;
    if ( $post_id && 'post' !== get_post_type( $post_id ) ) {
        wp_die( esc_html__( 'Cet article ne peut pas être modifié ici.', 'kadence-child' ) );
    }

    $title       = galerie_client_post_value( 'blog_title' );
    $content     = galerie_client_textarea_value( 'blog_content' );
    $excerpt     = galerie_client_textarea_value( 'blog_excerpt' );
    $submit_mode = galerie_client_post_value( 'galerie_blog_submit', 'draft' );
    $post_status = 'publish' === $submit_mode ? 'publish' : 'draft';
    $image_id    = isset( $_POST['blog_image_id'] ) ? absint( $_POST['blog_image_id'] ) : 0;
    $category_id = isset( $_POST['blog_category'] ) ? absint( $_POST['blog_category'] ) : 0;
    $tags        = galerie_client_post_value( 'blog_tags' );
    $errors      = [];

    if ( '' === $title ) {
        $errors[] = 'Ajoutez un titre.';
    }

    if ( 'publish' === $post_status && '' === trim( wp_strip_all_tags( $content ) ) ) {
        $errors[] = 'Ajoutez le contenu de l’article avant de publier.';
    }

    if ( $errors ) {
        $GLOBALS['galerie_client_blog_errors'] = $errors;
        return;
    }

    $post_data = [
        'post_type'    => 'post',
        'post_title'   => $title,
        'post_excerpt' => $excerpt,
        'post_content' => $content,
        'post_status'  => $post_status,
        'post_author'  => get_current_user_id(),
    ];

    if ( $post_id ) {
        $post_data['ID'] = $post_id;
        $post_id = wp_update_post( $post_data, true );
    } else {
        $post_id = wp_insert_post( $post_data, true );
    }

    if ( is_wp_error( $post_id ) ) {
        $GLOBALS['galerie_client_blog_errors'] = [ $post_id->get_error_message() ];
        return;
    }

    wp_set_post_categories( $post_id, [ $category_id ?: galerie_client_blog_default_category_id() ] );
    wp_set_post_tags( $post_id, $tags, false );

    if ( $image_id ) {
        set_post_thumbnail( $post_id, $image_id );
    } else {
        delete_post_thumbnail( $post_id );
    }

    wp_safe_redirect( admin_url( 'admin.php?page=galerie-blog-form&post_id=' . (int) $post_id . '&saved=1' ) );
    exit;
} );

add_action( 'admin_post_galerie_delete_blog_post', function (): void {
    $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
    if (
        ! current_user_can( 'delete_post', $post_id ) ||
        ! $post_id ||
        'post' !== get_post_type( $post_id ) ||
        ! isset( $_GET['_wpnonce'] ) ||
        ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'galerie_delete_blog_post_' . $post_id )
    ) {
        wp_die( esc_html__( 'Action non autorisée.', 'kadence-child' ) );
    }

    $trashed = wp_trash_post( $post_id );
    wp_safe_redirect( admin_url( 'admin.php?page=galerie-blog-posts&updated=' . ( $trashed ? 'deleted' : 'delete-error' ) ) );
    exit;
} );

function galerie_client_blog_posts_page(): void {
    $query = new WP_Query( [
        'post_type'      => 'post',
        'post_status'    => [ 'publish', 'draft' ],
        'posts_per_page' => 50,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ] );
    ?>
    <div class="wrap galerie-client-page">
        <h1>Mes articles</h1>
        <p class="galerie-client-intro">Liste simplifiée des textes du blog. Utilisez cette page pour relire, modifier, publier ou supprimer un article.</p>
        <p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=galerie-blog-form' ) ); ?>">Ajouter un article</a></p>

        <?php if ( isset( $_GET['updated'] ) ) : ?>
            <?php $updated = sanitize_key( wp_unslash( $_GET['updated'] ) ); ?>
            <?php if ( 'deleted' === $updated ) : ?>
                <div class="notice notice-success is-dismissible"><p>Article déplacé dans la corbeille.</p></div>
            <?php elseif ( 'delete-error' === $updated ) : ?>
                <div class="notice notice-error is-dismissible"><p>Impossible de supprimer l'article.</p></div>
            <?php else : ?>
                <div class="notice notice-success is-dismissible"><p>Article mis à jour.</p></div>
            <?php endif; ?>
        <?php endif; ?>

        <table class="widefat fixed striped galerie-artworks-table">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Article</th>
                    <th>Catégorie</th>
                    <th>Date</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $query->have_posts() ) : ?>
                    <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                        <?php
                        $post_id    = get_the_ID();
                        $categories = get_the_category( $post_id );
                        $category   = $categories ? $categories[0]->name : '-';
                        $is_public  = 'publish' === get_post_status( $post_id );
                        ?>
                        <tr>
                            <td><?php echo get_the_post_thumbnail( $post_id, [ 70, 70 ] ) ?: '<span class="galerie-no-image">Aucune image</span>'; ?></td>
                            <td>
                                <strong><?php echo esc_html( get_the_title() ?: '(sans titre)' ); ?></strong><br>
                                <span><?php echo esc_html( wp_trim_words( get_the_excerpt(), 14 ) ); ?></span>
                            </td>
                            <td><?php echo esc_html( $category ); ?></td>
                            <td><?php echo esc_html( get_the_date( 'd/m/Y', $post_id ) ); ?></td>
                            <td><?php echo esc_html( $is_public ? 'Publié' : 'Brouillon' ); ?></td>
                            <td>
                                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=galerie-blog-form&post_id=' . $post_id ) ); ?>">Modifier</a>
                                <?php if ( $is_public ) : ?>
                                    <a class="button" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" target="_blank" rel="noopener noreferrer">Voir</a>
                                <?php endif; ?>
                                <a class="button galerie-delete-artwork"
                                   href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=galerie_delete_blog_post&post_id=' . $post_id ), 'galerie_delete_blog_post_' . $post_id ) ); ?>"
                                   onclick="return confirm('Mettre cet article dans la corbeille ?');">Supprimer</a>
                            </td>
                        </tr>
                    <?php endwhile; wp_reset_postdata(); ?>
                <?php else : ?>
                    <tr><td colspan="6">Aucun article pour le moment.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function galerie_client_blog_form_page(): void {
    $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
    $post    = $post_id && 'post' === get_post_type( $post_id ) ? get_post( $post_id ) : null;

    $title       = galerie_client_post_value( 'blog_title', $post ? $post->post_title : '' );
    $image_id    = isset( $_POST['blog_image_id'] ) ? absint( $_POST['blog_image_id'] ) : ( $post_id ? get_post_thumbnail_id( $post_id ) : 0 );
    $excerpt     = galerie_client_textarea_value( 'blog_excerpt', $post ? $post->post_excerpt : '' );
    $content     = galerie_client_textarea_value( 'blog_content', $post ? $post->post_content : '' );
    $categories  = $post_id ? wp_get_post_categories( $post_id ) : [];
    $category_id = isset( $_POST['blog_category'] ) ? absint( $_POST['blog_category'] ) : ( $categories[0] ?? 0 );
    $tags        = galerie_client_post_value( 'blog_tags', $post_id ? implode( ', ', wp_get_post_tags( $post_id, [ 'fields' => 'names' ] ) ) : '' );
    ?>
    <div class="wrap galerie-client-page">
        <h1><?php echo $post_id ? 'Modifier un article' : 'Ajouter un article'; ?></h1>
        <p class="galerie-client-intro">Écrivez l’article simplement. WordPress gère la page blog, les archives et le référencement de base en arrière-plan.</p>

        <?php if ( isset( $_GET['saved'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p>Article enregistré.</p></div>
        <?php endif; ?>

        <?php if ( ! empty( $GLOBALS['galerie_client_blog_errors'] ) ) : ?>
            <div class="notice notice-error">
                <p><strong>Impossible de publier pour le moment :</strong></p>
                <ul>
                    <?php foreach ( $GLOBALS['galerie_client_blog_errors'] as $error ) : ?>
                        <li><?php echo esc_html( $error ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="galerie-blog-form">
            <?php wp_nonce_field( 'galerie_blog_save', 'galerie_blog_nonce' ); ?>
            <input type="hidden" name="blog_post_id" value="<?php echo esc_attr( (string) $post_id ); ?>">

            <section class="galerie-form-card">
                <h2>1. Image principale</h2>
                <input type="hidden" id="blog_image_id" name="blog_image_id" value="<?php echo esc_attr( (string) $image_id ); ?>">
                <div id="blog_image_preview" class="galerie-image-preview">
                    <?php echo $image_id ? wp_get_attachment_image( $image_id, 'medium' ) : '<span>Aucune image sélectionnée</span>'; ?>
                </div>
                <p>
                    <button type="button" class="button" id="galerie_select_blog_image">Choisir une image</button>
                    <button type="button" class="button" id="galerie_remove_blog_image">Retirer</button>
                </p>
            </section>

            <section class="galerie-form-card">
                <h2>2. Informations</h2>
                <div class="galerie-form-grid">
                    <label>Titre de l'article <input type="text" name="blog_title" value="<?php echo esc_attr( $title ); ?>" required></label>
                    <label>Catégorie <?php galerie_client_blog_category_select( (int) $category_id ); ?></label>
                    <label>Mots-clés <input type="text" name="blog_tags" value="<?php echo esc_attr( $tags ); ?>" placeholder="atelier, peinture, exposition"></label>
                </div>
            </section>

            <section class="galerie-form-card">
                <h2>3. Texte</h2>
                <label>Résumé court <textarea name="blog_excerpt" rows="3"><?php echo esc_textarea( $excerpt ); ?></textarea></label>
                <label>Article complet <textarea name="blog_content" rows="12" required><?php echo esc_textarea( $content ); ?></textarea></label>
            </section>

            <div class="galerie-form-actions">
                <button type="submit" name="galerie_blog_submit" value="draft" class="button">Enregistrer en brouillon</button>
                <button type="submit" name="galerie_blog_submit" value="publish" class="button button-primary">Publier l'article</button>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=galerie-blog-posts' ) ); ?>">Retour à mes articles</a>
            </div>
        </form>
    </div>
    <?php
}

add_action( 'admin_enqueue_scripts', function ( string $hook ): void {
    if ( ! isset( $_GET['page'] ) || ! in_array( sanitize_key( $_GET['page'] ), [ 'galerie-client', 'galerie-artworks', 'galerie-artwork-form', 'galerie-blog-posts', 'galerie-blog-form', 'galerie-services', 'galerie-service-form' ], true ) ) {
        return;
    }

    wp_enqueue_media();
    wp_add_inline_script(
        'jquery-core',
        "
        jQuery(function($) {
            var frame;
            var blogFrame;
            var serviceFrame;
            $('#galerie_select_image').on('click', function(e) {
                e.preventDefault();
                if (frame) { frame.open(); return; }
                frame = wp.media({ title: 'Choisir la photo de l\\'oeuvre', button: { text: 'Utiliser cette photo' }, multiple: false });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#artwork_image_id').val(attachment.id);
                    var url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
                    $('#artwork_image_preview').html('<img src=\"' + url + '\" alt=\"\">');
                });
                frame.open();
            });
            $('#galerie_remove_image').on('click', function(e) {
                e.preventDefault();
                $('#artwork_image_id').val('');
                $('#artwork_image_preview').html('<span>Aucune photo sélectionnée</span>');
            });
            $('#galerie_select_blog_image').on('click', function(e) {
                e.preventDefault();
                if (blogFrame) { blogFrame.open(); return; }
                blogFrame = wp.media({ title: 'Choisir l\\'image de l\\'article', button: { text: 'Utiliser cette image' }, multiple: false });
                blogFrame.on('select', function() {
                    var attachment = blogFrame.state().get('selection').first().toJSON();
                    $('#blog_image_id').val(attachment.id);
                    var url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
                    $('#blog_image_preview').html('<img src=\"' + url + '\" alt=\"\">');
                });
                blogFrame.open();
            });
            $('#galerie_remove_blog_image').on('click', function(e) {
                e.preventDefault();
                $('#blog_image_id').val('');
                $('#blog_image_preview').html('<span>Aucune image sélectionnée</span>');
            });
            $('#galerie_select_service_image').on('click', function(e) {
                e.preventDefault();
                if (serviceFrame) { serviceFrame.open(); return; }
                serviceFrame = wp.media({ title: 'Choisir l\\'image de la prestation', button: { text: 'Utiliser cette image' }, multiple: false });
                serviceFrame.on('select', function() {
                    var attachment = serviceFrame.state().get('selection').first().toJSON();
                    $('#service_image_id').val(attachment.id);
                    var url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
                    $('#service_image_preview').html('<img src=\"' + url + '\" alt=\"\">');
                });
                serviceFrame.open();
            });
            $('#galerie_remove_service_image').on('click', function(e) {
                e.preventDefault();
                $('#service_image_id').val('');
                $('#service_image_preview').html('<span>Aucune image selectionnee</span>');
            });
        });
        "
    );
    wp_add_inline_style(
        'common',
        '
        .galerie-form-card { background: #fff; border: 1px solid #dcdcde; margin: 18px 0; max-width: 980px; padding: 18px; }
        .galerie-form-card h2 { margin-top: 0; }
        .galerie-form-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .galerie-form-card label { display: block; font-weight: 600; }
        .galerie-form-card input[type=text],
        .galerie-form-card input[type=number],
        .galerie-form-card select,
        .galerie-form-card textarea { display: block; margin-top: 6px; width: 100%; }
        .galerie-checkbox { align-items: center; display: flex !important; gap: 8px; margin-top: 25px; }
        .galerie-checkbox input { margin: 0; }
        .galerie-image-preview { align-items: center; background: #f6f7f7; border: 1px dashed #b8b8b8; display: flex; min-height: 220px; justify-content: center; max-width: 340px; padding: 12px; }
        .galerie-image-preview img { display: block; height: auto; max-height: 300px; max-width: 100%; }
        .galerie-form-actions { align-items: center; display: flex; flex-wrap: wrap; gap: 10px; margin: 20px 0; }
        .galerie-artworks-table img { height: 70px; object-fit: cover; width: 70px; }
        .galerie-delete-artwork { border-color: #b32d2e !important; color: #b32d2e !important; }
        .galerie-delete-artwork:hover,
        .galerie-delete-artwork:focus { background: #b32d2e !important; border-color: #b32d2e !important; color: #fff !important; }
        .galerie-no-image { color: #646970; display: inline-block; font-size: 12px; width: 70px; }
        '
    );
} );
