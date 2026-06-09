<?php
/**
 * configure-mailtrap.php
 * Lancez avec : wp eval-file setup/configure-mailtrap.php
 *
 * Configure wp-mail-smtp avec Mailtrap pour intercepter les emails en local.
 * Tous les emails WordPress seront visibles dans votre inbox Mailtrap.
 */

if ( ! defined( 'ABSPATH' ) ) {
    echo "Ce fichier doit etre lance via WP-CLI.\n";
    exit( 1 );
}

if ( ! is_plugin_active( 'wp-mail-smtp/wp_mail_smtp.php' ) ) {
    echo "WP Mail SMTP n'est pas actif. Activez-le d'abord.\n";
    exit( 1 );
}

echo "> Configuration Mailtrap (interception emails locaux)...\n";

// ── Lecture des credentials depuis .env.local ──────────────
function _mt_get_env( string $key, string $default = '' ): string {
    $env_file = ABSPATH . 'setup/.env.local';
    if ( ! file_exists( $env_file ) ) {
        $env_file = dirname( ABSPATH ) . '/setup/.env.local';
    }
    static $vars = null;
    if ( $vars === null ) {
        $vars = [];
        if ( file_exists( $env_file ) ) {
            foreach ( file( $env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
                if ( str_starts_with( trim( $line ), '#' ) || ! str_contains( $line, '=' ) ) {
                    continue;
                }
                [ $k, $v ] = explode( '=', $line, 2 );
                $vars[ trim( $k ) ] = trim( $v );
            }
        }
    }
    return $vars[ $key ] ?? getenv( $key ) ?: $default;
}

$smtp_host = _mt_get_env( 'MAILTRAP_HOST', 'smtp.mailtrap.io' );
$smtp_port = (int) _mt_get_env( 'MAILTRAP_PORT', '2525' );
$smtp_user = _mt_get_env( 'MAILTRAP_USER', '' );
$smtp_pass = _mt_get_env( 'MAILTRAP_PASSWORD', '' );
$from_email = _mt_get_env( 'CONTACT_EMAIL', get_option( 'admin_email' ) );
$from_name  = get_bloginfo( 'name' );
$admin_email = _mt_get_env( 'ADMIN_EMAIL', get_option( 'admin_email' ) );

if ( ! $smtp_user || str_contains( $smtp_user, 'REMPLACER' ) ) {
    echo "\nATTENTION : credentials Mailtrap non configures dans .env.local\n";
    echo "  1. Creez un compte gratuit sur mailtrap.io\n";
    echo "  2. Allez dans Email Testing > Inboxes > SMTP Settings\n";
    echo "  3. Copiez MAILTRAP_USER et MAILTRAP_PASSWORD dans .env.local\n\n";
}

// ── Configuration wp-mail-smtp ─────────────────────────────
// wp-mail-smtp stocke ses réglages dans l'option 'wp_mail_smtp'
$mailtrap_config = [
    'mail' => [
        'from_email'      => $from_email,
        'from_name'       => $from_name,
        'mailer'          => 'smtp',       // Utiliser SMTP
        'return_path'     => false,
        'from_email_force' => true,        // Forcer cet email partout
        'from_name_force'  => true,
    ],
    'smtp' => [
        'host'            => $smtp_host,
        'port'            => $smtp_port,
        'encryption'      => 'tls',        // Mailtrap supporte TLS
        'auth'            => true,
        'user'            => $smtp_user,
        'pass'            => $smtp_pass,   // Stocké chiffré par le plugin
        'autotls'         => true,
    ],
    'general' => [
        'am_notifications_hidden' => true, // Cacher les pubs du plugin
    ],
];

update_option( 'wp_mail_smtp', $mailtrap_config );
echo "  • Option 'wp_mail_smtp' configuree\n";

// Certaines versions stockent le mot de passe séparément (chiffré)
// On met aussi la version en clair pour compatibilité
$current = get_option( 'wp_mail_smtp', [] );
if ( isset( $current['smtp'] ) ) {
    $current['smtp']['pass'] = $smtp_pass;
    update_option( 'wp_mail_smtp', $current );
}

// ── Email de test ──────────────────────────────────────────
if ( $smtp_user && ! str_contains( $smtp_user, 'REMPLACER' ) ) {
    echo "  • Envoi d'un email de test vers $admin_email...\n";

    $result = wp_mail(
        $admin_email,
        '[TEST] Configuration Mailtrap — ' . get_bloginfo( 'name' ),
        implode( "\n\n", [
            'Bonjour,',
            'Si vous lisez cet email dans Mailtrap, la configuration est correcte.',
            'Tous les emails WordPress (commandes, réservations, etc.) seront interceptés ici en local.',
            '-- Galerie dev',
        ] )
    );

    if ( $result ) {
        echo "  • Email de test envoye avec succes !\n";
        echo "    Verifiez votre inbox Mailtrap : mailtrap.io → Email Testing → Inboxes\n";
    } else {
        global $phpmailer;
        $error = isset( $phpmailer ) ? $phpmailer->ErrorInfo : 'Erreur inconnue';
        echo "  • Echec envoi email de test : $error\n";
        echo "    Verifiez les credentials Mailtrap dans .env.local\n";
    }
} else {
    echo "  • Email de test ignore (credentials non configures)\n";
}

echo "\nMailtrap configure.\n";
echo "  Host : $smtp_host : $smtp_port\n";
echo "  Tous les emails seront visibles sur mailtrap.io\n";
