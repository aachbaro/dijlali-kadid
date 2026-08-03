<?php
/**
 * Plugin Name: Djilali – Suggestions de contenu
 * Description: Permet à Djilali de proposer des modifications de textes et d'images directement depuis le site.
 * Version:     1.3.0
 * Author:      Adam Achbarou
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'DJS_VERSION',    '1.3.0' );
define( 'DJS_URL',        plugin_dir_url( __FILE__ ) );
define( 'DJS_ADAM_EMAIL', 'adam.achbarou@gmail.com' );

// ── 1. Custom Post Type : suggestions ────────────────────────────────────────
add_action( 'init', function () {
    register_post_type( 'djs_suggestion', [
        'labels'       => [
            'name'          => 'Suggestions',
            'singular_name' => 'Suggestion',
            'menu_name'     => '✏️ Suggestions',
            'all_items'     => 'Toutes les suggestions',
            'add_new'       => 'Nouvelle suggestion',
        ],
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => true,
        'menu_icon'    => 'dashicons-edit-large',
        'supports'     => [ 'title', 'editor', 'custom-fields' ],
        'capabilities' => [ 'create_posts' => 'do_not_allow' ],
        'map_meta_cap' => true,
    ] );
} );

// ── 2. REST API ───────────────────────────────────────────────────────────────
add_action( 'rest_api_init', function () {

    // Suggestion texte
    register_rest_route( 'djilali/v1', '/suggestion', [
        'methods'             => 'POST',
        'callback'            => 'djs_save_text_suggestion',
        'permission_callback' => fn() => current_user_can( 'edit_posts' ),
        'args'                => [
            'zone_id'    => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
            'zone_label' => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
            'current'    => [ 'required' => false, 'sanitize_callback' => 'sanitize_textarea_field' ],
            'proposed'   => [ 'required' => true,  'sanitize_callback' => 'sanitize_textarea_field' ],
            'note'       => [ 'required' => false, 'sanitize_callback' => 'sanitize_textarea_field' ],
            'page_url'   => [ 'required' => false, 'sanitize_callback' => 'esc_url_raw' ],
        ],
    ] );

    // Suggestion image (multipart)
    register_rest_route( 'djilali/v1', '/suggestion-image', [
        'methods'             => 'POST',
        'callback'            => 'djs_save_image_suggestion',
        'permission_callback' => fn() => current_user_can( 'edit_posts' ),
    ] );

    // Suggestion globale : ajout libre de contenu + fichiers (multipart)
    register_rest_route( 'djilali/v1', '/suggestion-global', [
        'methods'             => 'POST',
        'callback'            => 'djs_save_global_suggestion',
        'permission_callback' => fn() => current_user_can( 'edit_posts' ),
    ] );
} );

function djs_save_text_suggestion( WP_REST_Request $req ) {
    $zone_id    = $req['zone_id'];
    $zone_label = $req['zone_label'];
    $current    = $req['current']  ?? '';
    $proposed   = $req['proposed'];
    $note       = $req['note']     ?? '';
    $page_url   = $req['page_url'] ?? '';

    $post_id = wp_insert_post( [
        'post_type'    => 'djs_suggestion',
        'post_title'   => $zone_label,
        'post_content' => $proposed,
        'post_status'  => 'publish',
        'meta_input'   => [
            '_djs_zone_id'  => $zone_id,
            '_djs_type'     => 'text',
            '_djs_current'  => $current,
            '_djs_note'     => $note,
            '_djs_page_url' => $page_url,
            '_djs_status'   => 'pending',
        ],
    ] );

    if ( is_wp_error( $post_id ) ) {
        return new WP_REST_Response( [ 'success' => false ], 500 );
    }

    djs_send_email( $zone_label, $page_url, $current, $proposed, $note, $post_id, 'text' );

    return new WP_REST_Response( [ 'success' => true, 'id' => $post_id ], 200 );
}

function djs_save_image_suggestion( WP_REST_Request $req ) {
    $zone_id    = sanitize_text_field( $req->get_param( 'zone_id' ) );
    $zone_label = sanitize_text_field( $req->get_param( 'zone_label' ) );
    $note       = sanitize_textarea_field( $req->get_param( 'note' ) ?? '' );
    $page_url   = esc_url_raw( $req->get_param( 'page_url' ) ?? '' );
    $current    = sanitize_text_field( $req->get_param( 'current' ) ?? '' );

    $files = $req->get_file_params();
    $attachment_id = null;
    $img_url = '';

    if ( ! empty( $files['image'] ) && empty( $files['image']['error'] ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $_FILES['djs_image'] = $files['image'];
        $attachment_id = media_handle_upload( 'djs_image', 0, [
            'post_title' => 'Suggestion – ' . $zone_label,
        ] );
        if ( ! is_wp_error( $attachment_id ) ) {
            $img_url = wp_get_attachment_url( $attachment_id );
        }
    }

    $post_id = wp_insert_post( [
        'post_type'    => 'djs_suggestion',
        'post_title'   => $zone_label,
        'post_content' => $img_url ?: '(image uploadée — voir pièce jointe)',
        'post_status'  => 'publish',
        'meta_input'   => [
            '_djs_zone_id'     => $zone_id,
            '_djs_type'        => 'image',
            '_djs_current'     => $current,
            '_djs_note'        => $note,
            '_djs_page_url'    => $page_url,
            '_djs_status'      => 'pending',
            '_djs_attachment'  => $attachment_id,
        ],
    ] );

    if ( is_wp_error( $post_id ) ) {
        return new WP_REST_Response( [ 'success' => false ], 500 );
    }

    djs_send_email( $zone_label, $page_url, $current, $img_url ?: '(voir image uploadée)', $note, $post_id, 'image' );

    return new WP_REST_Response( [ 'success' => true, 'id' => $post_id, 'img_url' => $img_url ], 200 );
}

function djs_save_global_suggestion( WP_REST_Request $req ) {
    $message  = sanitize_textarea_field( $req->get_param( 'message' ) ?? '' );
    $page_url = esc_url_raw( $req->get_param( 'page_url' ) ?? '' );
    $title    = sanitize_text_field( $req->get_param( 'page_title' ) ?? 'Ajout de contenu' );

    if ( ! $message ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'empty' ], 400 );
    }

    // Upload de plusieurs fichiers (input multiple : files[0], files[1], …)
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $files       = $req->get_file_params();
    $attach_ids  = [];
    $attach_urls = [];

    foreach ( $files as $key => $file ) {
        if ( strpos( $key, 'file' ) !== 0 ) continue;
        if ( ! empty( $file['error'] ) ) continue;

        $_FILES['djs_upload'] = $file;
        $aid = media_handle_upload( 'djs_upload', 0, [
            'post_title' => 'Ajout Djilali – ' . $title,
        ] );
        if ( ! is_wp_error( $aid ) ) {
            $attach_ids[]  = $aid;
            $attach_urls[] = wp_get_attachment_url( $aid );
        }
    }

    $body = $message;
    if ( $attach_urls ) {
        $body .= "\n\nFichiers joints :\n" . implode( "\n", $attach_urls );
    }

    $post_id = wp_insert_post( [
        'post_type'    => 'djs_suggestion',
        'post_title'   => 'Ajout de contenu — ' . $title,
        'post_content' => $body,
        'post_status'  => 'publish',
        'meta_input'   => [
            '_djs_zone_id'    => 'global',
            '_djs_type'       => 'global',
            '_djs_current'    => '',
            '_djs_note'       => $message,
            '_djs_page_url'   => $page_url,
            '_djs_status'     => 'pending',
            '_djs_attachments'=> implode( ',', $attach_ids ),
        ],
    ] );

    if ( is_wp_error( $post_id ) ) {
        return new WP_REST_Response( [ 'success' => false ], 500 );
    }

    djs_send_email(
        'Ajout de contenu — ' . $title,
        $page_url,
        '',
        $attach_urls ? implode( "\n", $attach_urls ) : '(aucun fichier)',
        $message,
        $post_id,
        'global'
    );

    return new WP_REST_Response( [ 'success' => true, 'id' => $post_id, 'files' => count( $attach_ids ) ], 200 );
}

function djs_send_email( $label, $page_url, $current, $proposed, $note, $post_id, $type ) {
    $emoji   = $type === 'image' ? '🖼️' : ( $type === 'global' ? '➕' : '✏️' );
    $subject = $type === 'global'
        ? "➕ Djilali propose un ajout de contenu"
        : "$emoji Nouvelle suggestion : $label";

    $lines   = [
        $type === 'global' ? "Djilali propose d'ajouter du contenu :" : "Djilali a proposé une modification :",
        "",
        "Zone    : $label",
        "Page    : $page_url",
        "Type    : $type",
        "",
    ];
    if ( $type === 'text' ) {
        $lines[] = "Contenu actuel :";
        $lines[] = $current ?: "(vide)";
        $lines[] = "";
        $lines[] = "Nouveau contenu proposé :";
        $lines[] = $proposed;
    } elseif ( $type === 'global' ) {
        $lines[] = "Demande de Djilali :";
        $lines[] = $note;
        $lines[] = "";
        $lines[] = "Fichiers joints :";
        $lines[] = $proposed;
    } else {
        $lines[] = "Nouvelle image proposée :";
        $lines[] = $proposed;
    }
    if ( $note && $type !== 'global' ) {
        $lines[] = "";
        $lines[] = "Note de Djilali : $note";
    }
    $lines[] = "";
    $lines[] = "Voir dans l'admin : " . admin_url( "post.php?post=$post_id&action=edit" );

    wp_mail( DJS_ADAM_EMAIL, $subject, implode( "\n", $lines ) );
}

// ── 3. Admin column : statut ──────────────────────────────────────────────────
add_filter( 'manage_djs_suggestion_posts_columns', function ( $cols ) {
    return [
        'cb'       => $cols['cb'],
        'title'    => 'Zone',
        'type'     => 'Type',
        'current'  => 'Actuel',
        'proposed' => 'Proposé',
        'page'     => 'Page',
        'date'     => $cols['date'],
    ];
} );

add_action( 'manage_djs_suggestion_posts_custom_column', function ( $col, $post_id ) {
    switch ( $col ) {
        case 'type':
            $t = get_post_meta( $post_id, '_djs_type', true );
            echo $t === 'image' ? '🖼️ Image' : ( $t === 'global' ? '➕ Ajout' : '✏️ Texte' );
            break;
        case 'current':
            $v = get_post_meta( $post_id, '_djs_current', true );
            echo '<span style="color:#888;font-size:12px">' . esc_html( mb_substr( $v, 0, 80 ) ) . ( strlen( $v ) > 80 ? '…' : '' ) . '</span>';
            break;
        case 'proposed':
            $v = get_post( $post_id )->post_content;
            echo '<strong>' . esc_html( mb_substr( $v, 0, 80 ) ) . ( strlen( $v ) > 80 ? '…' : '' ) . '</strong>';
            break;
        case 'page':
            $url = get_post_meta( $post_id, '_djs_page_url', true );
            echo $url ? '<a href="' . esc_url( $url ) . '" target="_blank">Voir</a>' : '—';
            break;
    }
}, 10, 2 );

// ── 4. Meta box détail dans l'édition ────────────────────────────────────────
add_action( 'add_meta_boxes', function () {
    add_meta_box( 'djs_detail', 'Détail de la suggestion', 'djs_meta_box_detail', 'djs_suggestion', 'normal', 'high' );
} );

function djs_meta_box_detail( $post ) {
    $zone    = get_post_meta( $post->ID, '_djs_zone_id',  true );
    $type    = get_post_meta( $post->ID, '_djs_type',     true );
    $current = get_post_meta( $post->ID, '_djs_current',  true );
    $note    = get_post_meta( $post->ID, '_djs_note',     true );
    $page    = get_post_meta( $post->ID, '_djs_page_url', true );
    $att_id  = get_post_meta( $post->ID, '_djs_attachment', true );
    $att_ids = array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $post->ID, '_djs_attachments', true ) ) ) );

    $type_label = $type === 'image' ? '🖼️ Image' : ( $type === 'global' ? '➕ Ajout de contenu' : '✏️ Texte' );

    echo '<table style="border-collapse:collapse;width:100%">';
    $rows = [
        'Page concernée' => $page ? "<a href='$page' target='_blank'>$page</a>" : '—',
        'Zone ID'        => esc_html( $zone ),
        'Type'           => $type_label,
    ];
    if ( $type === 'global' ) {
        $rows['Demande de Djilali'] = '<pre style="background:#eef7e8;padding:8px;white-space:pre-wrap">' . esc_html( $note ?: '—' ) . '</pre>';
    } else {
        $rows['Contenu actuel']  = '<pre style="background:#f5f5f5;padding:8px;white-space:pre-wrap">' . esc_html( $current ?: '—' ) . '</pre>';
        $rows['Contenu proposé'] = '<pre style="background:#eef7e8;padding:8px;white-space:pre-wrap">' . esc_html( $post->post_content ) . '</pre>';
        $rows['Note de Djilali'] = esc_html( $note ?: '—' );
    }
    foreach ( $rows as $label => $value ) {
        echo "<tr><td style='padding:6px 10px;font-weight:bold;width:160px;vertical-align:top;border-top:1px solid #eee'>$label</td>"
           . "<td style='padding:6px 10px;vertical-align:top;border-top:1px solid #eee'>$value</td></tr>";
    }
    if ( $att_id && ! is_wp_error( $att_id ) ) {
        echo '<tr><td style="padding:6px 10px;font-weight:bold;vertical-align:top;border-top:1px solid #eee">Image uploadée</td>'
           . '<td style="padding:6px 10px;border-top:1px solid #eee">'
           . wp_get_attachment_image( $att_id, 'medium' )
           . '</td></tr>';
    }
    if ( $att_ids ) {
        $html = '';
        foreach ( $att_ids as $aid ) {
            $mime = get_post_mime_type( $aid );
            $url  = wp_get_attachment_url( $aid );
            if ( strpos( (string) $mime, 'image/' ) === 0 ) {
                $html .= '<div style="margin-bottom:8px">' . wp_get_attachment_image( $aid, 'medium' ) . '</div>';
            } elseif ( strpos( (string) $mime, 'audio/' ) === 0 ) {
                $html .= '<div style="margin-bottom:8px"><audio controls src="' . esc_url( $url ) . '" style="max-width:100%"></audio><br><a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( basename( $url ) ) . '</a></div>';
            } else {
                $html .= '<div style="margin-bottom:6px">📎 <a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( basename( $url ) ) . '</a></div>';
            }
        }
        echo '<tr><td style="padding:6px 10px;font-weight:bold;vertical-align:top;border-top:1px solid #eee">Fichiers joints</td>'
           . '<td style="padding:6px 10px;border-top:1px solid #eee">' . $html . '</td></tr>';
    }
    echo '</table>';
}

// ── 5. Assets frontend ───────────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', function () {
    if ( ! current_user_can( 'edit_posts' ) ) return;

    wp_enqueue_style(  'djs-editeur', DJS_URL . 'assets/editeur.css', [], DJS_VERSION );
    wp_enqueue_script( 'djs-editeur', DJS_URL . 'assets/editeur.js',  [], DJS_VERSION, true );

    wp_localize_script( 'djs-editeur', 'DJS', [
        'nonce'   => wp_create_nonce( 'wp_rest' ),
        'restUrl' => rest_url( 'djilali/v1/' ),
        'wpMedia' => rest_url( 'wp/v2/media' ),
        'pageUrl' => ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
        'zones'   => djs_get_zones(),
    ] );
} );

function djs_get_zones() {
    return [
        // ── Page d'accueil ──────────────────────────────────────────
        [
            'id'       => 'home-hero-subtitle',
            'label'    => "Accueil — Sous-titre du bandeau",
            'selector' => '.mvp-hero p, .mvp-hero__subtitle',
            'type'     => 'text',
        ],
        [
            'id'       => 'home-about-text',
            'label'    => "Accueil — Texte court à propos de l'artiste",
            'selector' => '.mvp-home-about p, .home .entry-content > p',
            'type'     => 'text',
        ],
        // ── Page À propos ───────────────────────────────────────────
        [
            'id'       => 'about-bio',
            'label'    => "À propos — Biographie principale",
            'selector' => '.page-id-15 .entry-content > p, .page-slug-a-propos .entry-content > p',
            'type'     => 'text',
        ],
        // ── Page Prestations ────────────────────────────────────────
        [
            'id'       => 'prestations-intro',
            'label'    => "Prestations — Texte d'introduction",
            'selector' => '.page-id-86 .entry-content > p, .page-slug-prestations .entry-content > p',
            'type'     => 'text',
        ],
        // ── Fiche oeuvre / prestation ───────────────────────────────
        [
            'id'       => 'product-title',
            'label'    => "Titre de l'œuvre / prestation",
            'selector' => '.artwork-single__title, h1.product_title',
            'type'     => 'text',
        ],
        [
            'id'       => 'product-description',
            'label'    => "Description de l'œuvre / prestation",
            'selector' => '.artwork-single__description p, .artwork-single__description',
            'type'     => 'text',
        ],
        [
            'id'       => 'product-image',
            'label'    => "Photo de l'œuvre / prestation",
            'selector' => '.artwork-single__main-image img',
            'type'     => 'image',
        ],
        // ── Contact ─────────────────────────────────────────────────
        [
            'id'       => 'contact-intro',
            'label'    => "Contact — Texte d'introduction",
            'selector' => '.page-id-260 .entry-content > p, .page-slug-contact .entry-content > p',
            'type'     => 'text',
        ],
    ];
}
