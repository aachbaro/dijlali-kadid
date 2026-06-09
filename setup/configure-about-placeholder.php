<?php
/**
 * Remplace la page A propos par un placeholder lorem ipsum propre.
 *
 * Usage:
 *   wp eval-file setup/configure-about-placeholder.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function galerie_about_log( string $message ): void {
    echo "  • {$message}\n";
}

$about = get_page_by_path( 'a-propos', OBJECT, 'page' );
if ( ! $about instanceof WP_Post ) {
    galerie_about_log( 'Page A propos introuvable.' );
    return;
}

$gallery_url = esc_url( home_url( '/galerie/' ) );

$content = <<<HTML
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

$updated = wp_update_post( [
    'ID'           => $about->ID,
    'post_content' => $content,
    'post_status'  => 'publish',
], true );

if ( is_wp_error( $updated ) ) {
    galerie_about_log( 'Erreur pendant la mise a jour : ' . $updated->get_error_message() );
    return;
}

galerie_about_log( "Page A propos mise a jour (#{$about->ID})." );
echo "\n> URL : " . get_permalink( $about->ID ) . "\n";
