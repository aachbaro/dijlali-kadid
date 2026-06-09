<?php
/**
 * Nettoie la page Prestations :
 * - contenu placeholder en lorem ipsum ;
 * - catalogue centre avec classe dediee ;
 * - bouton coherent avec la home.
 *
 * Usage:
 *   wp eval-file setup/configure-prestations-refinements.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function galerie_prestations_log( string $message ): void {
    echo "  • {$message}\n";
}

$page = get_page_by_path( 'prestations', OBJECT, 'page' );
if ( ! $page instanceof WP_Post ) {
    galerie_prestations_log( 'Page Prestations introuvable.' );
    return;
}

$contact_url = esc_url( home_url( '/contact/' ) );

$content = <<<HTML
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

$updated = wp_update_post( [
    'ID'           => $page->ID,
    'post_content' => $content,
    'post_status'  => 'publish',
], true );

if ( is_wp_error( $updated ) ) {
    galerie_prestations_log( 'Erreur pendant la mise a jour : ' . $updated->get_error_message() );
    return;
}

update_post_meta( $page->ID, 'rank_math_title', 'Prestations | Galerie Djilali Kadid' );
update_post_meta( $page->ID, 'rank_math_description', 'Prestations artistiques et culturelles de Djilali Kadid.' );
update_post_meta( $page->ID, 'rank_math_focus_keyword', 'prestations artistiques' );

galerie_prestations_log( "Page Prestations mise a jour (#{$page->ID})." );
echo "\n> URL : " . get_permalink( $page->ID ) . "\n";
