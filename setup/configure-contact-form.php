<?php
/**
 * configure-contact-form.php
 * wp eval-file setup/configure-contact-form.php
 *
 * Crée ou met à jour la page Contact avec un formulaire Kadence Blocks.
 * Aucun plugin supplémentaire requis — Kadence Blocks free suffit.
 *
 * Le formulaire envoie les messages à l'email admin.
 * Réponse automatique envoyée au visiteur.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo "> Configuration de la page Contact...\n";

$admin_email = get_option( 'admin_email' );
$site_name   = get_bloginfo( 'name' );
$uid         = 'contact_' . substr( md5( $site_name ), 0, 8 );

// ── Bloc formulaire Kadence ────────────────────────────────
// Les attributs JSON sont stockés dans le commentaire de bloc.
// Kadence rend le HTML dynamiquement via PHP côté serveur.
$form_attrs = [
    'uniqueID' => $uid,
    'style'    => 'underline',
    'fields'   => [
        [
            'label'      => 'Votre nom',
            'type'       => 'text',
            'required'   => true,
            'placeholder' => 'Marie Martin',
            'default'    => '',
            'rows'       => 4,
            'options'    => [],
            'width'      => [ 100, 100, 100 ],
            'auto'       => 'name',
            'uniqueID'   => $uid . '_name',
            'showLabel'  => true,
        ],
        [
            'label'      => 'Email',
            'type'       => 'email',
            'required'   => true,
            'placeholder' => 'marie@exemple.fr',
            'default'    => '',
            'rows'       => 4,
            'options'    => [],
            'width'      => [ 100, 100, 100 ],
            'auto'       => 'email',
            'uniqueID'   => $uid . '_email',
            'showLabel'  => true,
        ],
        [
            'label'      => 'Sujet',
            'type'       => 'text',
            'required'   => false,
            'placeholder' => 'Demande d\'information / Commande sur mesure / Réservation',
            'default'    => '',
            'rows'       => 4,
            'options'    => [],
            'width'      => [ 100, 100, 100 ],
            'auto'       => '',
            'uniqueID'   => $uid . '_subject',
            'showLabel'  => true,
        ],
        [
            'label'      => 'Message',
            'type'       => 'textarea',
            'required'   => true,
            'placeholder' => 'Votre message...',
            'default'    => '',
            'rows'       => 7,
            'options'    => [],
            'width'      => [ 100, 100, 100 ],
            'auto'       => '',
            'uniqueID'   => $uid . '_message',
            'showLabel'  => true,
        ],
    ],
    'submit' => [
        'text'      => 'Envoyer le message',
        'fixedWidth' => false,
        'width'     => [ 50, 100, 100 ],
        'btnSize'   => 'standard',
        'btnStyle'  => 'filled',
        'loadingLabel' => 'Envoi en cours…',
    ],
    'email' => [
        'emailTo'       => $admin_email,
        'subject'       => "Message de {field:" . $uid . "_name} — $site_name",
        'fromEmail'     => '',
        'fromName'      => "{field:" . $uid . "_name}",
        'cc'            => '',
        'bcc'           => '',
        'html'          => true,
        'replyTo'       => $uid . '_email',
        'sendAutoEmail' => true,
        'autoSubject'   => "Merci pour votre message — $site_name",
        'autoMessage'   => "Bonjour,\n\nMerci pour votre message. Je reviendrai vers vous dans les meilleurs délais.\n\nBien cordialement,\n$site_name",
    ],
    'messages' => [
        'success' => 'Merci, votre message a bien été envoyé ! Je vous répondrai rapidement.',
        'error'   => 'Une erreur est survenue. Merci de réessayer ou d\'envoyer un email directement.',
    ],
    'honeypot'      => true,
    'recaptcha'     => false,
    'requiredField' => true,
    'inputBorderWidth'  => [ 0, 0, 0, 1 ],
    'inputBorderRadius' => [ 2, 2, 2, 2 ],
    'submitBorderRadius' => [ 2, 2, 2, 2 ],
];

$form_json = wp_json_encode( $form_attrs, JSON_UNESCAPED_UNICODE );

// ── Contenu complet de la page Contact ────────────────────
$contact_content = <<<CONTENT
<!-- wp:group {"layout":{"type":"constrained","contentSize":"860px"}} -->
<div class="wp-block-group">

<!-- wp:heading {"level":1,"style":{"typography":{"fontFamily":"var(--font-display)","fontWeight":"300","letterSpacing":"0.02em"}}} -->
<h1 class="wp-block-heading" style="font-family:var(--font-display);font-weight:300;letter-spacing:0.02em">Contact</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"color":{"text":"var(--color-text-muted)"}}} -->
<p style="color:var(--color-text-muted)">Une question sur une œuvre, une commande personnalisée, une réservation ou une collaboration ? Écrivez-moi — je vous répondrai dans les 48h.</p>
<!-- /wp:paragraph -->

<!-- wp:spacer {"height":"24px"} -->
<div style="height:24px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:kadence/form $form_json -->
<!-- /wp:kadence/form -->

<!-- wp:spacer {"height":"48px"} -->
<div style="height:48px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:group {"style":{"border":{"top":{"width":"1px"},"right":{"width":"0px"},"bottom":{"width":"0px"},"left":{"width":"0px"},"color":"var(--color-border)"},"spacing":{"padding":{"top":"var:preset|spacing|40"}}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
<div class="wp-block-group" style="border-top:1px solid var(--color-border);padding-top:var(--wp--preset--spacing--40,2rem)">

<!-- wp:group -->
<div class="wp-block-group">
<!-- wp:heading {"level":3,"style":{"typography":{"fontFamily":"var(--font-display)","fontSize":"1rem","fontWeight":"300","letterSpacing":"0.12em","textTransform":"uppercase"}}} -->
<h3 style="font-family:var(--font-display);font-size:1rem;font-weight:300;letter-spacing:0.12em;text-transform:uppercase">Rendez-vous</h3>
<!-- /wp:heading -->
<!-- wp:paragraph {"style":{"color":{"text":"var(--color-text-muted)"},"typography":{"fontSize":"14px"}}} -->
<p style="color:var(--color-text-muted);font-size:14px">Modalités à préciser<br>Sur demande</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:group -->
<div class="wp-block-group">
<!-- wp:heading {"level":3,"style":{"typography":{"fontFamily":"var(--font-display)","fontSize":"1rem","fontWeight":"300","letterSpacing":"0.12em","textTransform":"uppercase"}}} -->
<h3 style="font-family:var(--font-display);font-size:1rem;font-weight:300;letter-spacing:0.12em;text-transform:uppercase">Email</h3>
<!-- /wp:heading -->
<!-- wp:paragraph {"style":{"color":{"text":"var(--color-text-muted)"},"typography":{"fontSize":"14px"}}} -->
<p style="color:var(--color-text-muted);font-size:14px"><a href="mailto:$admin_email">$admin_email</a></p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:group -->
<div class="wp-block-group">
<!-- wp:heading {"level":3,"style":{"typography":{"fontFamily":"var(--font-display)","fontSize":"1rem","fontWeight":"300","letterSpacing":"0.12em","textTransform":"uppercase"}}} -->
<h3 style="font-family:var(--font-display);font-size:1rem;font-weight:300;letter-spacing:0.12em;text-transform:uppercase">Réponse</h3>
<!-- /wp:heading -->
<!-- wp:paragraph {"style":{"color":{"text":"var(--color-text-muted)"},"typography":{"fontSize":"14px"}}} -->
<p style="color:var(--color-text-muted);font-size:14px">Sous 48h ouvrées</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

</div>
<!-- /wp:group -->

</div>
<!-- /wp:group -->
CONTENT;

// Remplacer le placeholder par le JSON réel (sans les quotes de la variable PHP)
$contact_content = str_replace( '$form_json', $form_json, $contact_content );

// ── Trouver la page Contact ────────────────────────────────
$contact_page = get_page_by_path( 'contact' );
if ( ! $contact_page ) {
    $contact_page = get_page_by_path( 'contact', OBJECT, 'page' );
}
if ( ! $contact_page ) {
    // Chercher par titre
    $pages = get_posts( [ 'post_type' => 'page', 'post_status' => 'publish', 's' => 'Contact', 'numberposts' => 1 ] );
    $contact_page = $pages[0] ?? null;
}

if ( $contact_page ) {
    wp_update_post( [
        'ID'           => $contact_page->ID,
        'post_content' => $contact_content,
    ] );
    $page_id = (int) $contact_page->ID;
    echo "  • Page Contact mise à jour (ID: {$contact_page->ID})\n";
} else {
    $page_id = wp_insert_post( [
        'post_title'   => 'Contact',
        'post_name'    => 'contact',
        'post_type'    => 'page',
        'post_status'  => 'publish',
        'post_content' => $contact_content,
    ] );
    echo "  • Page Contact créée (ID: $page_id)\n";
}

if ( ! is_wp_error( $page_id ) && $page_id ) {
    update_post_meta( (int) $page_id, 'rank_math_title', 'Contact | Galerie Djilali Kadid' );
    update_post_meta( (int) $page_id, 'rank_math_description', 'Contacter l’artiste pour une œuvre, une commande personnalisée ou une prestation culturelle.' );
    update_post_meta( (int) $page_id, 'rank_math_focus_keyword', 'contact artiste peintre' );
}

echo "\nFormulaire de contact configuré.\n";
echo "  Emails de contact → $admin_email\n";
echo "  Réponse automatique activée\n";
echo "  Vérifiez la page : " . home_url( '/contact/' ) . "\n";
echo "\n  Si le formulaire n'apparaît pas, vérifiez que Kadence Blocks est actif.\n";
