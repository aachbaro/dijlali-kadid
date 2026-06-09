<?php
/**
 * single-product.php — Fiche œuvre personnalisée
 * Thème enfant Kadence — Galerie d'art
 *
 * @package kadence-child
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

while ( have_posts() ) :
    the_post();

    global $product;
    $product = wc_get_product( get_the_ID() );

    if ( ! $product ) {
        continue;
    }

    $post_id    = get_the_ID();
    $is_service = 'cultural_service' === get_post_meta( $post_id, '_service_type', true );

    // ── Métadonnées ────────────────────────────────────────
    $height  = get_post_meta( $post_id, '_artwork_height',      true );
    $width   = get_post_meta( $post_id, '_artwork_width',       true );
    $depth   = get_post_meta( $post_id, '_artwork_depth',       true );
    $year    = get_post_meta( $post_id, '_artwork_year',        true );
    $cert    = get_post_meta( $post_id, '_artwork_certificate', true );
    $gallery_raw = get_post_meta( $post_id, '_artwork_gallery', true );
    $service_duration = get_post_meta( $post_id, '_service_duration', true );
    $service_format   = get_post_meta( $post_id, '_service_format', true );
    $service_audience = get_post_meta( $post_id, '_service_audience', true );
    $service_booking  = get_post_meta( $post_id, '_service_booking', true );
    $gallery_ids = $gallery_raw
        ? array_filter( array_map( 'absint', explode( ',', $gallery_raw ) ) )
        : [];

    $techniques = get_the_terms( $post_id, 'technique' );
    $themes     = get_the_terms( $post_id, 'theme_oeuvre' );

    $technique_label = ( ! empty( $techniques ) && ! is_wp_error( $techniques ) )
        ? implode( ', ', wp_list_pluck( $techniques, 'name' ) )
        : '';

    $in_stock       = $product->is_in_stock();
    $artwork_status = get_post_meta( $post_id, '_artwork_status', true ) ?: 'available';

    // Dimensions lisibles
    $dimensions = '';
    if ( $height || $width ) {
        $parts = [];
        if ( $height ) { $parts[] = $height . ' cm (H)'; }
        if ( $width )  { $parts[] = $width  . ' cm (L)'; }
        if ( $depth )  { $parts[] = $depth  . ' cm (P)'; }
        $dimensions = implode( ' × ', $parts );
    }

    // Images
    $main_id      = get_post_thumbnail_id( $post_id );
    $main_src     = $main_id ? wp_get_attachment_image_url( $main_id, 'artwork-main' ) : wc_placeholder_img_src();
    $main_full    = $main_id ? wp_get_attachment_image_url( $main_id, 'full' )         : $main_src;
    $main_alt     = $main_id ? get_post_meta( $main_id, '_wp_attachment_image_alt', true ) : '';
    if ( ! $main_alt ) { $main_alt = get_the_title(); }

    // Galerie complète (image principale + extras, dédupliqués, max 10)
    $all_ids = [];
    if ( $main_id ) { $all_ids[] = $main_id; }
    foreach ( $gallery_ids as $gid ) {
        if ( $gid !== $main_id ) { $all_ids[] = $gid; }
    }
    $all_ids = array_slice( $all_ids, 0, 10 );
    ?>

    <main id="main" class="site-main artwork-single-page">
        <article id="artwork-<?php echo esc_attr( (string) $post_id ); ?>" class="artwork-single">

            <!-- ╔══════════════ GALERIE ══════════════╗ -->
            <div class="artwork-single__gallery">

                <div class="artwork-single__main-image">
                    <a href="<?php echo esc_url( $main_full ); ?>"
                       class="artwork-lightbox-trigger"
                       aria-label="Voir l'image en grand">
                        <img id="artwork-main-img"
                             src="<?php echo esc_url( $main_src ); ?>"
                             alt="<?php echo esc_attr( $main_alt ); ?>"
                             loading="eager">
                    </a>
                </div>

                <?php if ( count( $all_ids ) > 1 ) : ?>
                <div class="artwork-single__thumbnails" role="list">
                    <?php foreach ( $all_ids as $i => $img_id ) :
                        $t_src  = wp_get_attachment_image_url( $img_id, 'thumbnail' );
                        $t_full = wp_get_attachment_image_url( $img_id, 'artwork-main' );
                        $t_max  = wp_get_attachment_image_url( $img_id, 'full' );
                        $t_alt  = get_post_meta( $img_id, '_wp_attachment_image_alt', true ) ?: get_the_title();
                    ?>
                    <button class="artwork-single__thumb <?php echo $i === 0 ? 'active' : ''; ?>"
                            data-full="<?php echo esc_url( $t_full ); ?>"
                            data-lightbox="<?php echo esc_url( $t_max ); ?>"
                            data-alt="<?php echo esc_attr( $t_alt ); ?>"
                            aria-label="Photo <?php echo esc_attr( (string) ( $i + 1 ) ); ?>"
                            role="listitem">
                        <img src="<?php echo esc_url( $t_src ); ?>"
                             alt="<?php echo esc_attr( $t_alt ); ?>"
                             loading="lazy"
                             width="72" height="72">
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            </div><!-- /.artwork-single__gallery -->

            <!-- ╔══════════════ INFORMATIONS ══════════════╗ -->
            <div class="artwork-single__info">

                <?php if ( $is_service ) : ?>
                <p class="artwork-single__technique-label">
                    Prestation culturelle
                </p>
                <?php elseif ( $technique_label ) : ?>
                <p class="artwork-single__technique-label">
                    <?php echo esc_html( $technique_label ); ?>
                </p>
                <?php endif; ?>

                <h1 class="artwork-single__title"><?php the_title(); ?></h1>

                <!-- Métadonnées -->
                <?php if ( $is_service && ( $service_duration || $service_format || $service_audience || $service_booking ) ) : ?>
                <ul class="artwork-single__metadata service-single__metadata">
                    <?php if ( $service_duration ) : ?>
                    <li>
                        <span class="label">Durée</span>
                        <span class="value"><?php echo esc_html( $service_duration ); ?></span>
                    </li>
                    <?php endif; ?>
                    <?php if ( $service_format ) : ?>
                    <li>
                        <span class="label">Format</span>
                        <span class="value"><?php echo esc_html( $service_format ); ?></span>
                    </li>
                    <?php endif; ?>
                    <?php if ( $service_audience ) : ?>
                    <li>
                        <span class="label">Public</span>
                        <span class="value"><?php echo esc_html( $service_audience ); ?></span>
                    </li>
                    <?php endif; ?>
                    <?php if ( $service_booking ) : ?>
                    <li>
                        <span class="label">Réservation</span>
                        <span class="value"><?php echo esc_html( $service_booking ); ?></span>
                    </li>
                    <?php endif; ?>
                </ul>
                <?php elseif ( $dimensions || $year || $technique_label ) : ?>
                <ul class="artwork-single__metadata">
                    <?php if ( $technique_label ) : ?>
                    <li>
                        <span class="label">Technique</span>
                        <span class="value"><?php echo esc_html( $technique_label ); ?></span>
                    </li>
                    <?php endif; ?>
                    <?php if ( $dimensions ) : ?>
                    <li>
                        <span class="label">Dimensions</span>
                        <span class="value"><?php echo esc_html( $dimensions ); ?></span>
                    </li>
                    <?php endif; ?>
                    <?php if ( $year ) : ?>
                    <li>
                        <span class="label">Année</span>
                        <span class="value"><?php echo esc_html( $year ); ?></span>
                    </li>
                    <?php endif; ?>
                    <?php $w = $product->get_weight(); if ( $w ) : ?>
                    <li>
                        <span class="label">Poids</span>
                        <span class="value"><?php echo esc_html( $w ); ?> kg</span>
                    </li>
                    <?php endif; ?>
                </ul>
                <?php endif; ?>

                <!-- Prix -->
                <div class="artwork-single__price">
                    <?php echo $product->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                </div>

                <!-- Certificat d'authenticité -->
                <?php if ( ! $is_service && '1' === $cert ) : ?>
                <div class="artwork-certificate">
                    Livré avec certificat d'authenticité signé
                </div>
                <?php endif; ?>

                <!-- Bouton achat / statut -->
                <?php if ( $in_stock && 'sold' !== $artwork_status ) : ?>
                    <div class="artwork-single__add-to-cart">
                        <?php woocommerce_template_single_add_to_cart(); ?>
                    </div>
                <?php else : ?>
                    <div class="artwork-single__sold-notice">
                        <button class="button disabled" disabled aria-disabled="true">
                            <?php echo 'reserved' === $artwork_status ? 'Œuvre réservée' : 'Œuvre vendue'; ?>
                        </button>
                        <p class="artwork-sold-message">
                            Cette œuvre n'est plus disponible.
                            <a href="<?php echo esc_url( get_permalink( get_page_by_path( 'contact' ) ) ); ?>">
                                Contactez-moi</a> pour des œuvres similaires.
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Description -->
                <?php $desc = $product->get_description(); if ( $desc || get_the_content() ) : ?>
                <div class="artwork-single__description">
                    <?php
                    if ( $desc ) {
                        echo wp_kses_post( wpautop( $desc ) );
                    } else {
                        the_content();
                    }
                    ?>
                </div>
                <?php endif; ?>

                <!-- Info livraison -->
                <div class="artwork-shipping-info">
                    <?php if ( $is_service ) : ?>
                    <p>Prestation réservable selon disponibilités. Les créneaux définitifs seront gérés avec Amelia.</p>
                    <?php else : ?>
                    <p>Emballage soigné adapté aux œuvres d'art, expédition sécurisée.</p>
                    <?php endif; ?>
                </div>

            </div><!-- /.artwork-single__info -->
        </article>

        <!-- ╔══════════════ ŒUVRES SIMILAIRES ══════════════╗ -->
        <?php
        $tax_query = [];
        if ( $is_service ) {
            $service_term = get_term_by( 'slug', 'prestations-culturelles', 'product_cat' );
            if ( $service_term ) {
                $tax_query[] = [
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => [ (int) $service_term->term_id ],
                ];
            }
        } elseif ( ! empty( $techniques ) && ! is_wp_error( $techniques ) ) {
            $tax_query[] = [
                'taxonomy' => 'technique',
                'field'    => 'term_id',
                'terms'    => wp_list_pluck( $techniques, 'term_id' ),
            ];
        }
        if ( ! empty( $themes ) && ! is_wp_error( $themes ) ) {
            $tax_query[] = [
                'taxonomy' => 'theme_oeuvre',
                'field'    => 'term_id',
                'terms'    => wp_list_pluck( $themes, 'term_id' ),
            ];
        }
        if ( count( $tax_query ) > 1 ) {
            $tax_query['relation'] = 'OR';
        }

        $related = new WP_Query( [
            'post_type'      => get_post_type( $post_id ),
            'post_status'    => 'publish',
            'posts_per_page' => 4,
            'post__not_in'   => [ $post_id ],
            'orderby'        => 'rand',
            'tax_query'      => $tax_query ?: [],
        ] );

        if ( $related->have_posts() ) :
        ?>
        <section class="related-artworks">
            <h2 class="related-artworks__title"><?php echo $is_service ? 'Autres prestations' : 'Vous aimerez aussi'; ?></h2>
            <div class="artworks-grid">
                <?php while ( $related->have_posts() ) : $related->the_post();
                    $rel_id      = get_the_ID();
                    $rel_product = wc_get_product( $rel_id );
                    $rel_sold    = $rel_product && ! $rel_product->is_in_stock();
                    $rel_status  = get_post_meta( $rel_id, '_artwork_status', true );
                    $rel_tech    = get_the_terms( $rel_id, 'technique' );
                ?>
                <article class="artwork-card">
                    <?php if ( $rel_sold || $rel_status === 'sold' ) : ?>
                    <span class="artwork-sold-overlay">Vendue</span>
                    <?php endif; ?>

                    <a href="<?php the_permalink(); ?>" class="artwork-card__image">
                        <?php if ( has_post_thumbnail() ) :
                            the_post_thumbnail( 'artwork-card', [ 'loading' => 'lazy' ] );
                        else : ?>
                            <img src="<?php echo esc_url( wc_placeholder_img_src() ); ?>"
                                 alt="<?php the_title_attribute(); ?>" loading="lazy">
                        <?php endif; ?>
                    </a>

                    <div class="artwork-card__body">
                        <h3 class="artwork-card__title">
                            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                        </h3>
                        <?php if ( ! empty( $rel_tech ) && ! is_wp_error( $rel_tech ) ) : ?>
                        <p class="artwork-card__meta"><?php echo esc_html( $rel_tech[0]->name ); ?></p>
                        <?php endif; ?>
                        <?php if ( $rel_product ) : ?>
                        <div class="artwork-card__price">
                            <?php echo $rel_product->get_price_html(); // phpcs:ignore ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
        </section>
        <?php endif; ?>

    </main>

<?php endwhile; ?>

<!-- ── Script miniatures + lightbox natif ────────────────── -->
<script>
(function () {
    'use strict';

    var mainImg   = document.getElementById('artwork-main-img');
    var thumbBtns = document.querySelectorAll('.artwork-single__thumb');
    var overlay   = null;

    if (!mainImg) return;

    // Changement de miniature
    thumbBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            thumbBtns.forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            mainImg.src = btn.dataset.full;
            mainImg.alt = btn.dataset.alt;
            mainImg.closest('a').href = btn.dataset.lightbox;
        });
    });

    // Lightbox au clic sur l'image principale
    var trigger = document.querySelector('.artwork-lightbox-trigger');
    if (trigger) {
        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            openLightbox(this.href, mainImg.alt);
        });
    }

    function openLightbox(src, alt) {
        if (!overlay) {
            overlay = document.createElement('div');
            Object.assign(overlay.style, {
                position: 'fixed', inset: '0', zIndex: '99999',
                background: 'rgba(0,0,0,0.92)',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                cursor: 'zoom-out', padding: '1rem',
            });
            overlay.innerHTML = '<img style="max-height:90vh;max-width:90vw;object-fit:contain;border-radius:2px;" alt="">';
            overlay.addEventListener('click', closeLightbox);
            document.addEventListener('keydown', function (ev) {
                if (ev.key === 'Escape') closeLightbox();
            });
            document.body.appendChild(overlay);
        }
        overlay.querySelector('img').src = src;
        overlay.querySelector('img').alt = alt;
        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        if (overlay) {
            overlay.style.display = 'none';
            document.body.style.overflow = '';
        }
    }
}());
</script>

<?php get_footer(); ?>
