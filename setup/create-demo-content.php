<?php
/**
 * create-demo-content.php
 * Lancez avec : wp eval-file setup/create-demo-content.php
 *
 * Cree une fausse version visuelle du site local : pages remplies, menu,
 * produits WooCommerce et images abstraites de demonstration.
 */

if ( ! defined( 'ABSPATH' ) ) {
    echo "Ce fichier doit etre lance via WP-CLI.\n";
    exit( 1 );
}

if ( ! class_exists( 'WooCommerce' ) ) {
    echo "WooCommerce n'est pas actif.\n";
    exit( 1 );
}

echo "> Creation du contenu de demo...\n";

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

function demo_hex_color( string $hex ): array {
    $hex = ltrim( $hex, '#' );
    return [
        hexdec( substr( $hex, 0, 2 ) ),
        hexdec( substr( $hex, 2, 2 ) ),
        hexdec( substr( $hex, 4, 2 ) ),
    ];
}

function demo_color( GdImage $img, string $hex, int $alpha = 0 ): int {
    [ $r, $g, $b ] = demo_hex_color( $hex );
    return imagecolorallocatealpha( $img, $r, $g, $b, $alpha );
}

function demo_create_image( string $key, string $title, array $palette, int $seed ): int {
    $existing = get_posts( [
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => 1,
        'meta_key'       => '_demo_artwork_key',
        'meta_value'     => $key,
        'fields'         => 'ids',
    ] );

    if ( ! empty( $existing ) ) {
        return (int) $existing[0];
    }

    mt_srand( $seed );
    $width  = 1200;
    $height = 1500;
    $img    = imagecreatetruecolor( $width, $height );

    imageantialias( $img, true );
    imagefill( $img, 0, 0, demo_color( $img, $palette['background'] ) );

    for ( $i = 0; $i < 18; $i++ ) {
        $color = demo_color( $img, $palette['marks'][ $i % count( $palette['marks'] ) ], mt_rand( 18, 58 ) );
        $x1    = mt_rand( -120, $width - 100 );
        $y1    = mt_rand( -80, $height - 100 );
        $x2    = $x1 + mt_rand( 220, 720 );
        $y2    = $y1 + mt_rand( 80, 420 );

        if ( 0 === $i % 3 ) {
            imagefilledellipse( $img, $x1 + 240, $y1 + 180, mt_rand( 280, 620 ), mt_rand( 160, 520 ), $color );
        } elseif ( 1 === $i % 3 ) {
            imagefilledrectangle( $img, $x1, $y1, $x2, $y2, $color );
        } else {
            imagefilledpolygon(
                $img,
                [
                    $x1, $y1,
                    $x2, $y1 + mt_rand( 20, 240 ),
                    $x1 + mt_rand( 80, 420 ), $y2,
                ],
                $color
            );
        }
    }

    for ( $i = 0; $i < 7; $i++ ) {
        $line = demo_color( $img, $palette['line'], mt_rand( 8, 35 ) );
        imagesetthickness( $img, mt_rand( 4, 14 ) );
        imageline(
            $img,
            mt_rand( 0, $width ),
            mt_rand( 0, $height ),
            mt_rand( 0, $width ),
            mt_rand( 0, $height ),
            $line
        );
    }

    $uploads = wp_upload_dir();
    $dir     = trailingslashit( $uploads['basedir'] ) . 'demo-artworks';
    wp_mkdir_p( $dir );

    $file = trailingslashit( $dir ) . $key . '.png';
    imagepng( $img, $file, 8 );
    imagedestroy( $img );

    $attachment_id = wp_insert_attachment(
        [
            'post_mime_type' => 'image/png',
            'post_title'     => $title,
            'post_status'    => 'inherit',
        ],
        $file
    );

    $metadata = wp_generate_attachment_metadata( $attachment_id, $file );
    wp_update_attachment_metadata( $attachment_id, $metadata );
    update_post_meta( $attachment_id, '_demo_artwork_key', $key );

    return (int) $attachment_id;
}

function demo_term_id( string $taxonomy, string $name, string $slug = '' ): int {
    $term = term_exists( $name, $taxonomy );
    if ( ! $term ) {
        $term = wp_insert_term( $name, $taxonomy, $slug ? [ 'slug' => $slug ] : [] );
    }
    if ( is_wp_error( $term ) ) {
        echo "  ! Terme impossible a creer : $taxonomy / $name\n";
        return 0;
    }
    return (int) ( is_array( $term ) ? $term['term_id'] : $term );
}

function demo_page( string $slug ): ?WP_Post {
    $page = get_page_by_path( $slug );
    return $page instanceof WP_Post ? $page : null;
}

function demo_set_fr_language( int $post_id ): void {
    if ( function_exists( 'pll_set_post_language' ) ) {
        pll_set_post_language( $post_id, 'fr' );
    }
}

function demo_update_page( string $slug, string $title, string $content ): int {
    $page = demo_page( $slug );
    if ( $page ) {
        wp_update_post( [
            'ID'           => $page->ID,
            'post_title'   => $title,
            'post_content' => $content,
        ] );
        demo_set_fr_language( (int) $page->ID );
        return (int) $page->ID;
    }

    $page_id = (int) wp_insert_post( [
        'post_type'    => 'page',
        'post_status'  => 'publish',
        'post_name'    => $slug,
        'post_title'   => $title,
        'post_content' => $content,
    ] );

    demo_set_fr_language( $page_id );

    return $page_id;
}

function demo_set_rank_math_meta( int $post_id, string $title, string $description, string $keyword = '' ): void {
    update_post_meta( $post_id, 'rank_math_title', $title );
    update_post_meta( $post_id, 'rank_math_description', wp_strip_all_tags( $description ) );
    update_post_meta( $post_id, 'rank_math_focus_keyword', $keyword );
    update_post_meta( $post_id, 'rank_math_robots', [ 'index', 'follow' ] );
}

function demo_update_post( array $data ): int {
    $existing = get_page_by_path( $data['slug'], OBJECT, 'post' );
    $postarr  = [
        'post_type'    => 'post',
        'post_status'  => 'publish',
        'post_name'    => $data['slug'],
        'post_title'   => $data['title'],
        'post_excerpt' => $data['excerpt'],
        'post_content' => $data['content'],
        'post_date'    => $data['date'],
    ];

    if ( $existing instanceof WP_Post ) {
        $postarr['ID'] = $existing->ID;
        wp_update_post( $postarr );
        $post_id = (int) $existing->ID;
    } else {
        $post_id = (int) wp_insert_post( $postarr );
    }

    demo_set_fr_language( $post_id );

    if ( ! empty( $data['categories'] ) ) {
        wp_set_post_terms( $post_id, $data['categories'], 'category' );
    }
    if ( ! empty( $data['tags'] ) ) {
        wp_set_post_terms( $post_id, $data['tags'], 'post_tag' );
    }
    if ( ! empty( $data['image_id'] ) ) {
        set_post_thumbnail( $post_id, (int) $data['image_id'] );
    }

    demo_set_rank_math_meta(
        $post_id,
        $data['seo_title'] ?? ( $data['title'] . ' | ' . get_bloginfo( 'name' ) ),
        $data['seo_description'] ?? $data['excerpt'],
        $data['focus_keyword'] ?? ''
    );

    return $post_id;
}

$artworks = [
    [
        'sku'       => 'demo-art-001',
        'title'     => 'Lumiere sur l atelier',
        'price'     => '420',
        'height'    => '60',
        'width'     => '80',
        'depth'     => '2',
        'year'      => '2025',
        'technique' => 'Acrylique',
        'theme'     => 'Abstrait',
        'format'    => 'Moyen format (30-80 cm)',
        'desc'      => 'Composition abstraite aux tons doux, pensee comme une fenetre ouverte sur un atelier calme.',
        'palette'   => [ 'background' => '#F7F1E8', 'marks' => [ '#202020', '#B88A63', '#D8C7A3', '#6E7F80' ], 'line' => '#292522' ],
    ],
    [
        'sku'       => 'demo-art-002',
        'title'     => 'Jardin suspendu',
        'price'     => '360',
        'height'    => '50',
        'width'     => '70',
        'depth'     => '2',
        'year'      => '2024',
        'technique' => 'Huile',
        'theme'     => 'Floral',
        'format'    => 'Moyen format (30-80 cm)',
        'desc'      => 'Variation florale lumineuse, avec une matiere dense et des passages presque transparents.',
        'palette'   => [ 'background' => '#FBF8EF', 'marks' => [ '#536B57', '#C9A66B', '#E6CFBC', '#25332E' ], 'line' => '#6D513F' ],
    ],
    [
        'sku'       => 'demo-art-003',
        'title'     => 'Marine du soir',
        'price'     => '510',
        'height'    => '80',
        'width'     => '100',
        'depth'     => '3',
        'year'      => '2025',
        'technique' => 'Technique mixte',
        'theme'     => 'Marine',
        'format'    => 'Grand format (> 80 cm)',
        'desc'      => 'Grande toile inspiree des reflets de fin de jour sur l eau.',
        'palette'   => [ 'background' => '#F2F4F1', 'marks' => [ '#213A49', '#7DA2AA', '#D9B46F', '#EEE2D4' ], 'line' => '#17222B' ],
    ],
    [
        'sku'       => 'demo-art-004',
        'title'     => 'Nature morte aux ocres',
        'price'     => '290',
        'height'    => '40',
        'width'     => '50',
        'depth'     => '2',
        'year'      => '2023',
        'technique' => 'Aquarelle',
        'theme'     => 'Nature morte',
        'format'    => 'Moyen format (30-80 cm)',
        'desc'      => 'Etude delicate d objets quotidiens, portee par une gamme chaude et sobre.',
        'palette'   => [ 'background' => '#FAF5EA', 'marks' => [ '#9F6F43', '#D0A166', '#F0D7A8', '#3A3028' ], 'line' => '#5B4433' ],
    ],
    [
        'sku'       => 'demo-art-005',
        'title'     => 'Portrait interieur',
        'price'     => '470',
        'height'    => '73',
        'width'     => '60',
        'depth'     => '2',
        'year'      => '2024',
        'technique' => 'Huile',
        'theme'     => 'Portrait',
        'format'    => 'Moyen format (30-80 cm)',
        'desc'      => 'Portrait suggere, plus atmospherique que narratif, avec une presence silencieuse.',
        'palette'   => [ 'background' => '#F8F2EC', 'marks' => [ '#2D2525', '#A66B5B', '#DCC7B3', '#7E8C89' ], 'line' => '#221E1D' ],
    ],
    [
        'sku'       => 'demo-art-006',
        'title'     => 'Petit paysage de mai',
        'price'     => '180',
        'height'    => '24',
        'width'     => '30',
        'depth'     => '1.5',
        'year'      => '2026',
        'technique' => 'Dessin',
        'theme'     => 'Paysage',
        'format'    => 'Petit format (< 30 cm)',
        'desc'      => 'Petit format vif, entre croquis de promenade et souvenir de lumiere.',
        'palette'   => [ 'background' => '#F9F8F2', 'marks' => [ '#344D3A', '#B7C09B', '#C7A96F', '#5E6E7A' ], 'line' => '#252A22' ],
    ],
];

$cat_id = demo_term_id( 'product_cat', 'Oeuvres originales', 'oeuvres-originales' );
$image_ids = [];

foreach ( $artworks as $index => $data ) {
    $image_id = demo_create_image( $data['sku'], $data['title'], $data['palette'], 900 + $index );
    $image_ids[] = $image_id;

    $product_id = wc_get_product_id_by_sku( $data['sku'] );
    $product    = $product_id ? wc_get_product( $product_id ) : new WC_Product_Simple();

    $product->set_name( $data['title'] );
    $product->set_slug( sanitize_title( $data['title'] ) );
    $product->set_status( 'publish' );
    $product->set_catalog_visibility( 'visible' );
    $product->set_sku( $data['sku'] );
    $product->set_regular_price( $data['price'] );
    $product->set_price( $data['price'] );
    $product->set_manage_stock( true );
    $product->set_stock_quantity( 1 );
    $product->set_stock_status( 'instock' );
    $product->set_sold_individually( true );
    $product->set_virtual( false );
    $product->set_downloadable( false );
    $product->set_image_id( $image_id );
    $product->set_category_ids( $cat_id ? [ $cat_id ] : [] );
    $product->set_description( $data['desc'] );
    $product->set_short_description( $data['technique'] . ' · ' . $data['height'] . ' x ' . $data['width'] . ' cm · ' . $data['year'] );

    $product_id = $product->save();
    demo_set_fr_language( $product_id );

    update_post_meta( $product_id, '_artwork_height', $data['height'] );
    update_post_meta( $product_id, '_artwork_width', $data['width'] );
    update_post_meta( $product_id, '_artwork_depth', $data['depth'] );
    update_post_meta( $product_id, '_artwork_year', $data['year'] );
    update_post_meta( $product_id, '_artwork_certificate', '1' );
    update_post_meta( $product_id, '_artwork_status', 'available' );
    demo_set_rank_math_meta(
        $product_id,
        $data['title'] . ' - œuvre originale | ' . get_bloginfo( 'name' ),
        $data['desc'] . ' Œuvre originale en ' . strtolower( $data['technique'] ) . ', format ' . strtolower( $data['format'] ) . '.',
        strtolower( $data['title'] . ', ' . $data['technique'] . ', œuvre originale' )
    );

    demo_term_id( 'technique', $data['technique'] );
    demo_term_id( 'theme_oeuvre', $data['theme'] );
    demo_term_id( 'format_oeuvre', $data['format'] );

    wp_set_object_terms( $product_id, $data['technique'], 'technique' );
    wp_set_object_terms( $product_id, $data['theme'], 'theme_oeuvre' );
    wp_set_object_terms( $product_id, $data['format'], 'format_oeuvre' );

    echo "  • Produit demo : {$data['title']} (#$product_id)\n";
}

$services = [
    [
        'sku'      => 'demo-service-001',
        'title'    => 'Cours particulier de dessin',
        'price'    => '65',
        'duration' => '1h30',
        'format'   => 'Atelier ou visioconférence',
        'audience' => 'Débutant à intermédiaire',
        'booking'  => 'Réservation Amelia',
        'desc'     => 'Séance individuelle pour travailler observation, composition, valeurs, perspective et confiance dans le trait.',
        'palette'  => [ 'background' => '#F7F2EA', 'marks' => [ '#2F2B25', '#BAA27C', '#E4D6BF', '#6D7A72' ], 'line' => '#1F1D19' ],
    ],
    [
        'sku'      => 'demo-service-002',
        'title'    => 'Cours de peinture en atelier',
        'price'    => '85',
        'duration' => '2h',
        'format'   => 'Atelier',
        'audience' => 'Adultes et adolescents',
        'booking'  => 'Réservation Amelia',
        'desc'     => 'Accompagnement sur la couleur, la matière et la construction d’une toile, avec conseils personnalisés.',
        'palette'  => [ 'background' => '#F5F1E8', 'marks' => [ '#7C5944', '#CDAA75', '#EADBC4', '#3A4B45' ], 'line' => '#332820' ],
    ],
    [
        'sku'      => 'demo-service-003',
        'title'    => 'Visite privée d’atelier',
        'price'    => '25',
        'duration' => '45 min',
        'format'   => 'Atelier',
        'audience' => 'Collectionneurs, curieux, petits groupes',
        'booking'  => 'Réservation Amelia',
        'desc'     => 'Découverte de l’atelier, des œuvres disponibles, des étapes de travail et des possibilités de commande.',
        'palette'  => [ 'background' => '#FAF6ED', 'marks' => [ '#2A2A2A', '#B8875F', '#D5C9B0', '#879184' ], 'line' => '#211F1B' ],
    ],
    [
        'sku'      => 'demo-service-004',
        'title'    => 'Commande personnalisée',
        'price'    => '120',
        'duration' => 'Devis après échange',
        'format'   => 'Rendez-vous préparatoire',
        'audience' => 'Particuliers, entreprises, cadeaux',
        'booking'  => 'Acompte ou devis',
        'desc'     => 'Premier rendez-vous pour définir un format, une intention, une palette, un calendrier et un budget.',
        'palette'  => [ 'background' => '#F8F3E9', 'marks' => [ '#573B2E', '#A98259', '#E1C7A8', '#65716D' ], 'line' => '#2B221D' ],
    ],
    [
        'sku'      => 'demo-service-005',
        'title'    => 'Conférence histoire de l’art',
        'price'    => '280',
        'duration' => '1h à 1h30',
        'format'   => 'Sur place ou en ligne',
        'audience' => 'Associations, écoles, lieux culturels',
        'booking'  => 'Devis',
        'desc'     => 'Intervention culturelle autour d’un mouvement, d’un artiste, d’une période ou d’un thème choisi.',
        'palette'  => [ 'background' => '#F3F5EF', 'marks' => [ '#263B43', '#758C84', '#D7BA7A', '#EEE3D1' ], 'line' => '#18262B' ],
    ],
    [
        'sku'      => 'demo-service-006',
        'title'    => 'Rédaction culturelle',
        'price'    => '180',
        'duration' => 'Forfait de départ',
        'format'   => 'À distance',
        'audience' => 'Artistes, galeries, projets éditoriaux',
        'booking'  => 'Devis',
        'desc'     => 'Rédaction de notices, textes d’exposition, articles, biographies courtes ou présentations d’œuvres.',
        'palette'  => [ 'background' => '#F8F8F2', 'marks' => [ '#30302C', '#AFA47B', '#D9D2BE', '#5E6B74' ], 'line' => '#24231F' ],
    ],
];

$service_cat_id   = demo_term_id( 'product_cat', 'Prestations culturelles', 'prestations-culturelles' );
$service_image_ids = [];

foreach ( $services as $index => $data ) {
    $image_id = demo_create_image( $data['sku'], $data['title'], $data['palette'], 1200 + $index );
    $service_image_ids[] = $image_id;

    $product_id = wc_get_product_id_by_sku( $data['sku'] );
    $product    = $product_id ? wc_get_product( $product_id ) : new WC_Product_Simple();

    $product->set_name( $data['title'] );
    $product->set_slug( sanitize_title( $data['title'] ) );
    $product->set_status( 'publish' );
    $product->set_catalog_visibility( 'visible' );
    $product->set_sku( $data['sku'] );
    $product->set_regular_price( $data['price'] );
    $product->set_price( $data['price'] );
    $product->set_manage_stock( false );
    $product->set_stock_status( 'instock' );
    $product->set_sold_individually( true );
    $product->set_virtual( true );
    $product->set_downloadable( false );
    $product->set_image_id( $image_id );
    $product->set_category_ids( $service_cat_id ? [ $service_cat_id ] : [] );
    $product->set_description( $data['desc'] );
    $product->set_short_description( $data['duration'] . ' · ' . $data['format'] );

    $product_id = $product->save();
    demo_set_fr_language( $product_id );

    update_post_meta( $product_id, '_service_type', 'cultural_service' );
    update_post_meta( $product_id, '_service_duration', $data['duration'] );
    update_post_meta( $product_id, '_service_format', $data['format'] );
    update_post_meta( $product_id, '_service_audience', $data['audience'] );
    update_post_meta( $product_id, '_service_booking', $data['booking'] );
    demo_set_rank_math_meta(
        $product_id,
        $data['title'] . ' | Prestation artistique',
        $data['desc'] . ' Durée : ' . $data['duration'] . '. Format : ' . $data['format'] . '.',
        strtolower( $data['title'] . ', prestation artistique' )
    );

    wp_set_object_terms( $product_id, 'simple', 'product_type' );

    echo "  • Prestation demo : {$data['title']} (#$product_id)\n";
}

$blog_categories = [
    'Carnet d’atelier'        => [ 'slug' => 'carnet-atelier', 'description' => 'Notes de travail, gestes, matières et coulisses de création.' ],
    'Conseils collectionneurs' => [ 'slug' => 'conseils-collectionneurs', 'description' => 'Repères pour choisir, acheter, encadrer et conserver une œuvre.' ],
    'Techniques & matières'   => [ 'slug' => 'techniques-matieres', 'description' => 'Articles pédagogiques sur les techniques picturales et les supports.' ],
    'Histoire de l’art'       => [ 'slug' => 'histoire-art', 'description' => 'Textes d’introduction aux mouvements, sujets et références artistiques.' ],
    'Prestations culturelles' => [ 'slug' => 'prestations-culturelles-blog', 'description' => 'Contenus liés aux cours, conférences, visites d’atelier et commandes.' ],
    'Actualités'              => [ 'slug' => 'actualites', 'description' => 'Nouvelles de l’atelier, expositions, événements et publications.' ],
];

$blog_category_ids = [];
foreach ( $blog_categories as $name => $settings ) {
    $term_id = demo_term_id( 'category', $name, $settings['slug'] );
    if ( $term_id ) {
        wp_update_term( $term_id, 'category', [
            'description' => $settings['description'],
            'slug'        => $settings['slug'],
        ] );
        $blog_category_ids[ $name ] = $term_id;
    }
}

foreach ( [ 'hello-world', 'bonjour-tout-le-monde' ] as $sample_slug ) {
    $sample_post = get_page_by_path( $sample_slug, OBJECT, 'post' );
    if ( $sample_post instanceof WP_Post ) {
        wp_delete_post( $sample_post->ID, true );
    }
}

$blog_posts = [
    [
        'slug'          => 'choisir-oeuvre-originale-interieur',
        'title'         => 'Comment choisir une œuvre originale pour son intérieur',
        'excerpt'       => 'Quelques repères simples pour choisir une peinture qui dialogue avec un lieu, une lumière et une histoire personnelle.',
        'date'          => '2026-01-12 10:00:00',
        'categories'    => [ 'Conseils collectionneurs' ],
        'tags'          => [ 'collection', 'achat art', 'décoration', 'œuvre originale' ],
        'image_key'     => 'blog-choisir-oeuvre',
        'focus_keyword' => 'choisir une œuvre originale',
        'content'       => '<h2>Regarder avant de mesurer</h2><p>Choisir une œuvre commence rarement par une dimension parfaite. Il s’agit d’abord de comprendre ce que l’on veut ressentir dans une pièce : calme, énergie, profondeur, présence discrète ou point focal fort.</p><h2>Tenir compte de la lumière</h2><p>Une peinture change avec la lumière du matin, du soir ou d’un éclairage artificiel. Avant de décider, il est utile d’observer le mur à différents moments de la journée.</p><h2>Penser au dialogue avec le lieu</h2><p>Une œuvre n’a pas besoin de reprendre les couleurs exactes d’un intérieur. Elle peut aussi créer un contraste, ouvrir une respiration ou introduire une matière différente.</p><h2>Questions à poser</h2><p>Demandez la technique, les dimensions, les conditions d’accrochage, le certificat d’authenticité et les options de retrait ou d’expédition.</p>',
        'seo_description' => 'Guide pour choisir une œuvre originale : format, lumière, dialogue avec l’intérieur, certificat et questions à poser avant achat.',
    ],
    [
        'slug'          => 'etapes-peinture-abstraite-atelier',
        'title'         => 'Dans l’atelier : les étapes d’une peinture abstraite',
        'excerpt'       => 'Du premier geste aux dernières reprises, aperçu fictif mais réaliste d’une toile en construction.',
        'date'          => '2026-01-26 10:00:00',
        'categories'    => [ 'Carnet d’atelier' ],
        'tags'          => [ 'atelier', 'peinture abstraite', 'processus créatif' ],
        'image_key'     => 'blog-etapes-abstraction',
        'focus_keyword' => 'étapes peinture abstraite',
        'content'       => '<h2>Préparer le support</h2><p>Le travail commence par le choix du format, du grain de la toile et d’une première atmosphère colorée. Cette préparation donne une direction sans figer l’œuvre.</p><h2>Construire les masses</h2><p>Les premières couches installent les rapports de densité, les zones de silence et les tensions principales. À ce stade, la toile accepte encore les accidents.</p><h2>Faire apparaître la lumière</h2><p>Les glacis, retraits, frottements et reprises permettent de faire circuler le regard. Une peinture abstraite avance par décisions successives.</p><h2>Savoir s’arrêter</h2><p>La fin arrive quand les éléments ne demandent plus à être expliqués. La toile trouve son équilibre entre matière, rythme et respiration.</p>',
        'seo_description' => 'Découvrir les étapes d’une peinture abstraite en atelier : support, couches, lumière, reprises et équilibre final.',
    ],
    [
        'slug'          => 'huile-acrylique-aquarelle-differences',
        'title'         => 'Huile, acrylique, aquarelle : comprendre les différences',
        'excerpt'       => 'Un article pédagogique pour expliquer les techniques picturales aux visiteurs et futurs acheteurs.',
        'date'          => '2026-02-09 10:00:00',
        'categories'    => [ 'Techniques & matières' ],
        'tags'          => [ 'huile', 'acrylique', 'aquarelle', 'techniques' ],
        'image_key'     => 'blog-techniques',
        'focus_keyword' => 'huile acrylique aquarelle',
        'content'       => '<h2>La peinture à l’huile</h2><p>L’huile permet des transitions fines, des reprises lentes et une profondeur particulière. Elle demande du temps de séchage mais offre une grande richesse de matière.</p><h2>L’acrylique</h2><p>L’acrylique sèche vite et autorise des superpositions rapides. Elle convient aux compositions spontanées, aux aplats et aux recherches de texture.</p><h2>L’aquarelle</h2><p>L’aquarelle joue avec l’eau, la transparence et le papier. Elle garde souvent la trace d’un geste immédiat.</p><h2>Technique mixte</h2><p>La technique mixte associe plusieurs matériaux : peinture, dessin, collage, encre ou pastel. Elle ouvre un champ plus expérimental.</p>',
        'seo_description' => 'Différences entre huile, acrylique, aquarelle et technique mixte : matière, séchage, rendu et usages artistiques.',
    ],
    [
        'slug'          => 'certificat-authenticite-oeuvre-art',
        'title'         => 'Pourquoi un certificat d’authenticité accompagne une œuvre',
        'excerpt'       => 'Le certificat d’authenticité rassure l’acheteur et documente l’œuvre dans le temps.',
        'date'          => '2026-02-23 10:00:00',
        'categories'    => [ 'Conseils collectionneurs' ],
        'tags'          => [ 'certificat', 'authenticité', 'collection', 'vente œuvre' ],
        'image_key'     => 'blog-certificat',
        'focus_keyword' => 'certificat authenticité œuvre',
        'content'       => '<h2>Un document de référence</h2><p>Le certificat d’authenticité accompagne une œuvre originale et confirme son auteur, son titre, sa technique, son format et son année de création.</p><h2>Une mémoire de l’œuvre</h2><p>Il permet de conserver les informations essentielles même si l’œuvre change de lieu ou de propriétaire.</p><h2>Un outil de confiance</h2><p>Pour l’acheteur, le certificat clarifie la provenance et renforce la valeur documentaire de la pièce.</p><h2>Ce qu’il peut contenir</h2><p>On y trouve généralement une photo, une description, la signature de l’artiste et parfois des conseils de conservation.</p>',
        'seo_description' => 'Pourquoi fournir un certificat d’authenticité avec une œuvre originale : confiance, provenance, informations et conservation.',
    ],
    [
        'slug'          => 'preparer-visite-atelier-artiste',
        'title'         => 'Préparer une visite d’atelier : ce qu’il faut regarder',
        'excerpt'       => 'Une visite d’atelier permet de comprendre les œuvres, les gestes et le rythme de création.',
        'date'          => '2026-03-09 10:00:00',
        'categories'    => [ 'Prestations culturelles', 'Carnet d’atelier' ],
        'tags'          => [ 'visite atelier', 'rencontre artiste', 'œuvres disponibles' ],
        'image_key'     => 'blog-visite-atelier',
        'focus_keyword' => 'visite atelier artiste',
        'content'       => '<h2>Entrer dans le lieu de travail</h2><p>Une visite d’atelier n’est pas seulement une présentation d’œuvres. C’est une rencontre avec un espace, des outils, des essais et des tableaux en cours.</p><h2>Observer les séries</h2><p>Regarder plusieurs œuvres ensemble aide à percevoir les constantes : palette, formats, sujets, gestes et préoccupations.</p><h2>Poser des questions concrètes</h2><p>Technique, dimensions, prix, disponibilité, expédition, commande personnalisée : la visite permet d’obtenir des réponses directes.</p><h2>Prendre le temps</h2><p>Une œuvre se découvre souvent lentement. Il est normal de revenir vers une pièce après une première impression.</p>',
        'seo_description' => 'Comment préparer une visite d’atelier d’artiste : observer les œuvres, poser les bonnes questions et comprendre la démarche.',
    ],
    [
        'slug'          => 'commande-oeuvre-personnalisee-etapes',
        'title'         => 'Commander une œuvre personnalisée : étapes et questions utiles',
        'excerpt'       => 'Une commande personnalisée se construit avec un échange précis, un cadre clair et une intention partagée.',
        'date'          => '2026-03-23 10:00:00',
        'categories'    => [ 'Prestations culturelles', 'Conseils collectionneurs' ],
        'tags'          => [ 'commande personnalisée', 'devis', 'œuvre sur mesure' ],
        'image_key'     => 'blog-commande',
        'focus_keyword' => 'commande œuvre personnalisée',
        'content'       => '<h2>Clarifier l’intention</h2><p>Avant de parler technique, il faut définir ce que l’œuvre doit accompagner : un lieu, un souvenir, un cadeau, une ambiance ou un projet professionnel.</p><h2>Définir le cadre</h2><p>Format, budget, calendrier, couleurs à éviter, mode de livraison et niveau de liberté artistique doivent être formulés tôt.</p><h2>Valider une proposition</h2><p>Selon le projet, l’artiste peut proposer une note d’intention, une palette, une esquisse ou simplement un devis détaillé.</p><h2>Suivre la réalisation</h2><p>Certains projets prévoient un point intermédiaire. D’autres demandent une plus grande confiance dans le processus créatif.</p>',
        'seo_description' => 'Étapes pour commander une œuvre personnalisée : intention, format, budget, devis, acompte, réalisation et livraison.',
    ],
    [
        'slug'          => 'nature-morte-peinture-regard-contemporain',
        'title'         => 'Petite histoire de la nature morte en peinture',
        'excerpt'       => 'Un sujet classique qui continue d’inspirer les peintres par sa simplicité apparente et sa profondeur symbolique.',
        'date'          => '2026-04-06 10:00:00',
        'categories'    => [ 'Histoire de l’art' ],
        'tags'          => [ 'nature morte', 'histoire de l’art', 'peinture' ],
        'image_key'     => 'blog-nature-morte',
        'focus_keyword' => 'nature morte peinture',
        'content'       => '<h2>Un genre du quotidien</h2><p>La nature morte rassemble objets, fruits, fleurs, livres ou instruments. Elle semble modeste, mais elle parle du temps, de la présence et de la fragilité.</p><h2>Composition et silence</h2><p>Ce genre donne une grande importance à l’équilibre des formes, aux ombres, aux matières et à la lumière.</p><h2>Une lecture contemporaine</h2><p>Aujourd’hui encore, la nature morte permet de regarder autrement les objets ordinaires et d’y trouver une charge poétique.</p><h2>Dans l’atelier</h2><p>Elle peut devenir un terrain d’étude pour les couleurs, les volumes et les relations entre abstraction et figuration.</p>',
        'seo_description' => 'Introduction à la nature morte en peinture : histoire, composition, symbolique, lumière et regard contemporain.',
    ],
    [
        'slug'          => 'commencer-carnet-croquis',
        'title'         => 'Commencer un carnet de croquis sans pression',
        'excerpt'       => 'Le carnet de croquis est un espace d’observation, d’essais et de liberté, loin de l’obligation de résultat.',
        'date'          => '2026-04-20 10:00:00',
        'categories'    => [ 'Techniques & matières', 'Prestations culturelles' ],
        'tags'          => [ 'carnet de croquis', 'dessin', 'cours dessin' ],
        'image_key'     => 'blog-carnet-croquis',
        'focus_keyword' => 'carnet de croquis',
        'content'       => '<h2>Un outil de regard</h2><p>Un carnet sert d’abord à regarder plus attentivement. Il n’a pas besoin d’être beau, propre ou terminé.</p><h2>Commencer petit</h2><p>Dix minutes suffisent : une tasse, une chaise, une fenêtre, une main. La régularité compte plus que le résultat.</p><h2>Varier les exercices</h2><p>Traits rapides, valeurs, détails, compositions, notes de couleur : le carnet accepte toutes les formes d’essai.</p><h2>Garder les pages imparfaites</h2><p>Les pages maladroites documentent la progression. Elles font partie du travail.</p>',
        'seo_description' => 'Conseils pour commencer un carnet de croquis : observation, exercices simples, régularité et liberté de dessiner.',
    ],
];

$blog_palettes = [
    'blog-choisir-oeuvre'      => [ 'background' => '#F8F4EC', 'marks' => [ '#2B2824', '#B38A64', '#DED1BA', '#6D7973' ], 'line' => '#25221F' ],
    'blog-etapes-abstraction'  => [ 'background' => '#F5F0E7', 'marks' => [ '#252525', '#C19A6B', '#E1CDB1', '#71817E' ], 'line' => '#1E1C1A' ],
    'blog-techniques'          => [ 'background' => '#F8F6EF', 'marks' => [ '#6F4D39', '#BCA16B', '#E6D4B9', '#3F5554' ], 'line' => '#30261F' ],
    'blog-certificat'          => [ 'background' => '#FAF7F1', 'marks' => [ '#302B25', '#B99B78', '#D9CBB6', '#77827D' ], 'line' => '#25211D' ],
    'blog-visite-atelier'      => [ 'background' => '#F6F2EA', 'marks' => [ '#2E322F', '#A7835B', '#D8C2A4', '#7B8A83' ], 'line' => '#202320' ],
    'blog-commande'            => [ 'background' => '#F9F3EA', 'marks' => [ '#4D372C', '#B88962', '#E0C9AA', '#697872' ], 'line' => '#2B211C' ],
    'blog-nature-morte'        => [ 'background' => '#FBF6EA', 'marks' => [ '#8C5D38', '#CDA66F', '#EBD7AF', '#3B3028' ], 'line' => '#5B3D2A' ],
    'blog-carnet-croquis'      => [ 'background' => '#F8F8F1', 'marks' => [ '#31302C', '#9E9878', '#D6D0BE', '#607080' ], 'line' => '#25231F' ],
];

foreach ( $blog_posts as $index => $post_data ) {
    $category_ids = [];
    foreach ( $post_data['categories'] as $category_name ) {
        if ( isset( $blog_category_ids[ $category_name ] ) ) {
            $category_ids[] = $blog_category_ids[ $category_name ];
        }
    }

    $image_id = demo_create_image(
        $post_data['image_key'],
        $post_data['title'],
        $blog_palettes[ $post_data['image_key'] ],
        1500 + $index
    );

    $post_id = demo_update_post( array_merge( $post_data, [
        'categories' => $category_ids,
        'image_id'   => $image_id,
    ] ) );

    echo "  • Article demo : {$post_data['title']} (#$post_id)\n";
}

$hero_url        = $image_ids ? esc_url( wp_get_attachment_image_url( $image_ids[0], 'full' ) ) : '';
$shop_url        = esc_url( home_url( '/boutique/' ) );
$gallery_url     = esc_url( home_url( '/galerie/' ) );
$services_url    = esc_url( home_url( '/prestations/' ) );
$bookings_url    = esc_url( home_url( '/reservations/' ) );
$blog_url        = esc_url( home_url( '/blog/' ) );
$courses_url     = esc_url( home_url( '/cours/' ) );
$conferences_url = esc_url( home_url( '/conferences/' ) );
$writing_url     = esc_url( home_url( '/redaction-culturelle/' ) );
$coaching_url    = esc_url( home_url( '/coaching-artistique/' ) );
$commission_url  = esc_url( home_url( '/commande-personnalisee/' ) );
$about_url       = esc_url( home_url( '/a-propos/' ) );
$contact_url     = esc_url( home_url( '/contact/' ) );
$contact_email   = esc_html( get_option( 'admin_email', 'djilali@test.local' ) );
$contact_mailto  = esc_url( 'mailto:' . $contact_email );

$home = <<<HTML
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

$gallery = '';

$shop_page = <<<HTML
<section class="mvp-section mvp-section--intro">
    <p class="mvp-kicker">Boutique</p>
    <h1>Acheter une oeuvre</h1>
    <p>Chaque tableau est une pièce unique : le stock est limité à un exemplaire, avec panier, commande et paiement en ligne.</p>
</section>
<section class="mvp-section">[products category="oeuvres-originales" limit="12" columns="3" orderby="date" order="DESC"]</section>
HTML;

$services_page = <<<HTML
<section class="mvp-section mvp-section--intro">
    <p class="mvp-kicker">Prestations</p>
    <h1>Prestations</h1>
    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer laoreet, eros eu facilisis luctus, neque libero posuere arcu, vitae tempor mi sapien non nibh.</p>
</section>
<section class="mvp-section mvp-prestations-grid">[products category="prestations-culturelles" limit="12" columns="3" orderby="date" order="ASC"]</section>
<section class="mvp-section mvp-split">
    <div>
        <h2>Lorem ipsum</h2>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Praesent vitae sem vitae lacus gravida luctus. Sed posuere, mi vitae fermentum pretium, nibh arcu cursus nibh.</p>
    </div>
    <div>
        <h2>Dolor sit amet</h2>
        <p>Curabitur lacinia, nisl sed tempor consequat, augue mi faucibus massa, ac interdum erat lorem vitae sem. Donec non neque eget sapien porta volutpat.</p>
        <p><a class="button mvp-page-button" href="{$contact_url}">Envoyer un message</a></p>
    </div>
</section>
HTML;

$courses_page = <<<HTML
<section class="mvp-section mvp-section--intro">
    <p class="mvp-kicker">Cours artistiques</p>
    <h1>Cours de dessin et peinture</h1>
    <p>Parcours fictif pour présenter des cours individuels ou en petit groupe : observation, couleur, composition, carnet de croquis et développement d'une pratique personnelle.</p>
</section>
<section class="mvp-section mvp-feature-grid">
    <article><h3>Cours particulier</h3><p>Séance centrée sur les besoins de l'élève, avec exercices et conseils personnalisés.</p></article>
    <article><h3>Atelier peinture</h3><p>Travail autour de la matière, des mélanges, du geste et de la construction d'une toile.</p></article>
    <article><h3>Suivi de projet</h3><p>Accompagnement sur plusieurs séances pour construire une série ou préparer un dossier.</p></article>
</section>
<section class="mvp-section">[products skus="demo-service-001,demo-service-002" columns="2"]</section>
HTML;

$commission_page = <<<HTML
<section class="mvp-section mvp-section--intro">
    <p class="mvp-kicker">Commande personnalisée</p>
    <h1>Imaginer une oeuvre sur mesure</h1>
    <p>Page de démonstration pour expliquer le processus : échange initial, intentions, format, palette, devis, acompte, réalisation et livraison.</p>
</section>
<section class="mvp-section mvp-timeline">
    <article><span>1</span><h3>Échange</h3><p>Comprendre le lieu, l'envie, les couleurs, le format et le budget.</p></article>
    <article><span>2</span><h3>Proposition</h3><p>Préparer une direction artistique, un calendrier et un devis clair.</p></article>
    <article><span>3</span><h3>Réalisation</h3><p>Peinture, validation intermédiaire si prévue, finalisation et certificat.</p></article>
    <article><span>4</span><h3>Remise</h3><p>Retrait atelier ou expédition sécurisée selon le format.</p></article>
</section>
<section class="mvp-section">[products skus="demo-service-004" columns="1"]</section>
HTML;

$conferences_page = <<<HTML
<section class="mvp-section mvp-section--intro">
    <p class="mvp-kicker">Conférences</p>
    <h1>Interventions autour de l'art</h1>
    <p>Format fictif pour lieux culturels, associations, écoles ou événements privés : histoire de l'art, présentation d'un mouvement, lecture d'oeuvres ou atelier-conférence.</p>
</section>
<section class="mvp-section mvp-split">
    <div>
        <h2>Formats possibles</h2>
        <ul class="mvp-check-list">
            <li>Conférence d'initiation</li>
            <li>Intervention thématique</li>
            <li>Rencontre autour de la création</li>
            <li>Atelier-conférence avec pratique</li>
        </ul>
    </div>
    <div class="mvp-note">
        <p>Les sujets, supports, tarifs et frais de déplacement seront définis après échange. Cette page sert à valider la structure et le parcours de demande.</p>
    </div>
</section>
<section class="mvp-section">[products skus="demo-service-005" columns="1"]</section>
HTML;

$writing_page = <<<HTML
<section class="mvp-section mvp-section--intro">
    <p class="mvp-kicker">Rédaction culturelle</p>
    <h1>Textes d'art, notices et contenus éditoriaux</h1>
    <p>Service fictif pour tester une offre de rédaction : texte d'exposition, biographie courte, article de blog, notice d'oeuvre ou présentation de projet.</p>
</section>
<section class="mvp-section mvp-feature-grid">
    <article><h3>Notices d'oeuvres</h3><p>Présenter une oeuvre avec précision, contexte et sensibilité.</p></article>
    <article><h3>Articles culturels</h3><p>Créer du contenu éditorial pour nourrir le blog et le référencement.</p></article>
    <article><h3>Textes d'exposition</h3><p>Structurer une démarche, un accrochage ou une série.</p></article>
</section>
<section class="mvp-section">[products skus="demo-service-006" columns="1"]</section>
HTML;

$coaching_page = <<<HTML
<section class="mvp-section mvp-section--intro">
    <p class="mvp-kicker">Coaching artistique</p>
    <h1>Accompagnement de pratique créative</h1>
    <p>Page fictive pour proposer un suivi artistique : clarifier une démarche, organiser une série, préparer une exposition ou retrouver un rythme de création.</p>
</section>
<section class="mvp-section mvp-split">
    <div>
        <h2>Pour qui ?</h2>
        <p>Artistes débutants, amateurs avancés, personnes en reprise créative ou profils culturels qui veulent structurer un projet.</p>
    </div>
    <div>
        <h2>Ce que l'on travaille</h2>
        <ul class="mvp-check-list">
            <li>Intention et cohérence de série</li>
            <li>Choix techniques et supports</li>
            <li>Organisation d'un projet</li>
            <li>Présentation orale ou écrite</li>
        </ul>
    </div>
</section>
<section class="mvp-section"><p><a class="button" href="{$contact_url}">Demander un accompagnement</a></p></section>
HTML;

$bookings = <<<HTML
<section class="mvp-section mvp-section--intro">
    <p class="mvp-kicker">Rendez-vous</p>
    <h1>Réservations</h1>
    <p>Cette page prépare le parcours Amelia : choisir une prestation, sélectionner un créneau, confirmer la demande et payer en ligne si nécessaire.</p>
</section>
<section class="mvp-section">
    <div class="mvp-section__head">
        <div>
            <p class="mvp-kicker">Réservable</p>
            <h2>Prestations à connecter à Amelia</h2>
        </div>
        <a href="{$services_url}">Toutes les prestations</a>
    </div>
    [products category="prestations-culturelles" limit="6" columns="3" orderby="date" order="ASC"]
</section>
<section class="mvp-section mvp-split">
    <div>
        <h2>Configuration Amelia à prévoir</h2>
        <ul class="mvp-check-list">
            <li>Services : cours, visite, rendez-vous de commande, coaching</li>
            <li>Durées : 45 min, 1h30, 2h ou sur devis</li>
            <li>Horaires réels de l'atelier</li>
            <li>Email de confirmation et rappel automatique</li>
        </ul>
    </div>
    <div class="mvp-note">
        <h3>Module Amelia</h3>
        <p>Le shortcode ci-dessous est prêt. La configuration finale se fera dans Amelia une fois les horaires, tarifs, lieux et conditions de réservation validés.</p>
        [ameliabooking]
    </div>
</section>
HTML;

$about = <<<HTML
<section class="mvp-section mvp-section--intro">
    <p class="mvp-kicker">À propos</p>
    <h1>Biographie</h1>
    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer laoreet, eros eu facilisis luctus, neque libero posuere arcu, vitae tempor mi sapien non nibh.</p>
</section>
<section class="mvp-section mvp-split">
    <div>
        <h2>Parcours</h2>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Praesent vitae sem vitae lacus gravida luctus. Sed posuere, mi vitae fermentum pretium, nibh arcu cursus nibh, non tincidunt erat lorem non justo.</p>
        <p>Curabitur lacinia, nisl sed tempor consequat, augue mi faucibus massa, ac interdum erat lorem vitae sem. Donec non neque eget sapien porta volutpat.</p>
    </div>
    <div>
        <h2>Démarche</h2>
        <p>Aliquam erat volutpat. Morbi dignissim, lorem a malesuada luctus, purus elit faucibus augue, non pretium neque augue at libero. Vestibulum ante ipsum primis in faucibus orci luctus.</p>
        <p><a class="button mvp-page-button" href="{$gallery_url}">Découvrir les oeuvres</a></p>
    </div>
</section>
HTML;

$contact = <<<HTML
<section class="mvp-section mvp-section--intro">
    <p class="mvp-kicker">Contact</p>
    <h1>Contact</h1>
    <p>Pour une question sur une oeuvre, une commande personnalisée, une prestation culturelle ou une collaboration, envoyez un message.</p>
    <p><a class="button mvp-page-button" href="{$contact_mailto}">Écrire un message</a></p>
</section>
<section class="mvp-section mvp-feature-grid">
    <article>
        <h3>Achat d'une oeuvre</h3>
        <p>Demande d'informations, disponibilité, emballage, retrait ou expédition.</p>
    </article>
    <article>
        <h3>Commande personnalisée</h3>
        <p>Brief, format, technique, calendrier et devis avant réalisation.</p>
    </article>
    <article>
        <h3>Prestation culturelle</h3>
        <p>Cours, conférence, prestation privée ou intervention autour de l'art.</p>
    </article>
</section>
HTML;

$legal = <<<HTML
<section class="mvp-section mvp-section--intro">
    <h1>Mentions légales</h1>
    <p>Contenu de démonstration à remplacer avant la mise en production : identité de l'éditeur, hébergeur, responsable de publication et contact.</p>
</section>
HTML;

$terms = <<<HTML
<section class="mvp-section mvp-section--intro">
    <h1>Conditions générales de vente</h1>
    <p>Page à compléter avant production : commande, paiement, livraison, retrait atelier, droit applicable, retours éventuels et cas des oeuvres uniques.</p>
</section>
HTML;

$privacy = <<<HTML
<section class="mvp-section mvp-section--intro">
    <h1>Politique de confidentialité</h1>
    <p>Page à compléter avant production : données collectées, formulaires, commandes WooCommerce, réservations Amelia, cookies, durée de conservation et droits des utilisateurs.</p>
</section>
HTML;

$blog_page = <<<HTML
<section class="mvp-section mvp-section--intro">
    <p class="mvp-kicker">Blog artistique</p>
    <h1>Journal de l'atelier</h1>
    <p>Articles de démonstration pour préparer la visibilité Google : œuvres, techniques, conseils collectionneurs, histoire de l'art et prestations culturelles.</p>
</section>
HTML;

$page_ids = [
    'accueil'                     => demo_update_page( 'accueil', 'Accueil', $home ),
    'galerie'                     => demo_update_page( 'galerie', 'Galerie', $gallery ),
    'boutique'                    => demo_update_page( 'boutique', 'Boutique', $shop_page ),
    'prestations'                 => demo_update_page( 'prestations', 'Prestations', $services_page ),
    'reservations'                => demo_update_page( 'reservations', 'Reservations', $bookings ),
    'blog'                        => demo_update_page( 'blog', 'Blog', $blog_page ),
    'cours'                       => demo_update_page( 'cours', 'Cours', $courses_page ),
    'commande-personnalisee'      => demo_update_page( 'commande-personnalisee', 'Commande personnalisee', $commission_page ),
    'conferences'                 => demo_update_page( 'conferences', 'Conferences', $conferences_page ),
    'redaction-culturelle'        => demo_update_page( 'redaction-culturelle', 'Redaction culturelle', $writing_page ),
    'coaching-artistique'         => demo_update_page( 'coaching-artistique', 'Coaching artistique', $coaching_page ),
    'a-propos'                    => demo_update_page( 'a-propos', 'A propos', $about ),
    'contact'                     => demo_update_page( 'contact', 'Contact', $contact ),
    'mentions-legales'            => demo_update_page( 'mentions-legales', 'Mentions legales', $legal ),
    'conditions-generales-de-vente' => demo_update_page( 'conditions-generales-de-vente', 'Conditions generales de vente', $terms ),
    'politique-de-confidentialite'  => demo_update_page( 'politique-de-confidentialite', 'Politique de confidentialite', $privacy ),
];

$page_seo = [
    'accueil' => [ 'Galerie Djilali Kadid | Peintures originales', 'Galerie d’artiste peintre : œuvres originales, boutique en ligne, cours et prestations culturelles.', 'artiste peintre, galerie en ligne' ],
    'galerie' => [ 'Galerie d’œuvres originales | Galerie Djilali Kadid', 'Sélection de peintures originales : formats, techniques, thèmes, prix et disponibilités.', 'oeuvres originales' ],
    'boutique' => [ 'Boutique d’art en ligne | Galerie Djilali Kadid', 'Acheter une peinture originale en ligne : œuvres uniques, certificat d’authenticité, panier et paiement.', 'boutique art en ligne' ],
    'prestations' => [ 'Prestations culturelles et artistiques | Galerie Djilali Kadid', 'Cours de dessin, peinture, visite d’atelier, conférence, rédaction culturelle et commande personnalisée.', 'prestations culturelles' ],
    'reservations' => [ 'Réservations atelier et cours | Galerie Djilali Kadid', 'Réserver une visite d’atelier, un cours de dessin, une commande personnalisée ou une prestation culturelle.', 'réservation atelier artiste' ],
    'blog' => [ 'Blog artistique | Galerie Djilali Kadid', 'Articles sur la peinture, les techniques, l’atelier, l’histoire de l’art et les conseils pour collectionneurs.', 'blog artistique' ],
    'cours' => [ 'Cours de dessin et peinture | Galerie Djilali Kadid', 'Cours individuels de dessin et peinture : observation, couleur, composition et accompagnement artistique.', 'cours dessin peinture' ],
    'commande-personnalisee' => [ 'Commande d’œuvre personnalisée | Galerie Djilali Kadid', 'Commander une œuvre sur mesure : échange, format, palette, devis, réalisation et certificat.', 'commande œuvre personnalisée' ],
    'conferences' => [ 'Conférences histoire de l’art | Galerie Djilali Kadid', 'Interventions culturelles autour de l’art : conférences, rencontres, ateliers et événements privés.', 'conférence histoire de l art' ],
    'redaction-culturelle' => [ 'Rédaction culturelle et textes d’art | Galerie Djilali Kadid', 'Rédaction de notices d’œuvres, textes d’exposition, articles culturels et biographies artistiques.', 'rédaction culturelle' ],
    'coaching-artistique' => [ 'Coaching artistique | Galerie Djilali Kadid', 'Accompagnement créatif pour clarifier une démarche, structurer une série ou préparer une exposition.', 'coaching artistique' ],
    'a-propos' => [ 'À propos de l’artiste | Galerie Djilali Kadid', 'Découvrir la démarche, les techniques, l’atelier et l’univers pictural de l’artiste.', 'artiste peintre' ],
    'contact' => [ 'Contact | Galerie Djilali Kadid', 'Contacter l’artiste pour une œuvre, une commande personnalisée ou une prestation culturelle.', 'contact artiste peintre' ],
];

foreach ( $page_seo as $slug => $seo ) {
    if ( isset( $page_ids[ $slug ] ) ) {
        demo_set_rank_math_meta( $page_ids[ $slug ], $seo[0], $seo[1], $seo[2] );
    }
}

$home_id = $page_ids['accueil'];

update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $home_id );
update_option( 'page_for_posts', $page_ids['blog'] );
update_option( 'woocommerce_coming_soon', 'no' );
update_option( 'permalink_structure', '/%postname%/' );

global $wp_rewrite;
if ( $wp_rewrite instanceof WP_Rewrite ) {
    $wp_rewrite->set_permalink_structure( '/%postname%/' );
}

$menu_name = 'Menu principal';
$menu      = wp_get_nav_menu_object( $menu_name );
$menu_id   = $menu ? (int) $menu->term_id : (int) wp_create_nav_menu( $menu_name );
$existing_items = wp_get_nav_menu_items( $menu_id );

if ( $existing_items ) {
    foreach ( $existing_items as $item ) {
        wp_delete_post( (int) $item->ID, true );
    }
}

foreach ( [ 'galerie', 'blog', 'prestations', 'a-propos', 'contact' ] as $slug ) {
    $page = demo_page( $slug );
    if ( ! $page ) {
        continue;
    }
    wp_update_nav_menu_item( $menu_id, 0, [
        'menu-item-title'     => $page->post_title,
        'menu-item-object-id' => $page->ID,
        'menu-item-object'    => 'page',
        'menu-item-type'      => 'post_type',
        'menu-item-status'    => 'publish',
    ] );
}

$locations = get_theme_mod( 'nav_menu_locations', [] );
foreach ( get_registered_nav_menus() as $location => $description ) {
    $haystack = strtolower( $location . ' ' . $description );
    if ( str_contains( $haystack, 'primary' ) || str_contains( $haystack, 'main' ) || str_contains( $haystack, 'principal' ) ) {
        $locations[ $location ] = $menu_id;
    }
}
if ( empty( $locations ) ) {
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

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
    $admin_id = wp_create_user( 'admin', 'admin123', 'admin@test.local' );
    if ( ! is_wp_error( $admin_id ) ) {
        ( new WP_User( $admin_id ) )->set_role( 'administrator' );
        echo "  • Compte admin cree : admin / admin123\n";
    }
} else {
    wp_set_password( 'admin123', $admin->ID );
    ( new WP_User( $admin->ID ) )->set_role( 'administrator' );
    echo "  • Compte admin mis a jour : admin / admin123\n";
}

wc_delete_product_transients();
flush_rewrite_rules();

echo "\nContenu de demo cree.\n";
echo "  Accueil : " . home_url( '/' ) . "\n";
echo "  Boutique : " . home_url( '/boutique/' ) . "\n";
echo "  Admin : " . admin_url() . "\n";
