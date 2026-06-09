<?php
/**
 * configure-smtp-production.php
 * wp eval-file setup/configure-smtp-production.php
 *
 * Configure wp-mail-smtp pour l'envoi d'emails en production sur o2switch.
 * o2switch fournit un SMTP via l'hébergement : mail.votredomaine.fr
 *
 * Variables requises dans .env (production) :
 *   SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASSWORD, CONTACT_EMAIL
 *
 * ⚠️  Ne lancez PAS ce script en local (il utilise les variables de prod).
 *     En local, utilisez configure-mailtrap.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo "> Configuration SMTP production (o2switch)...\n";

// ── Lecture des variables ──────────────────────────────────
function _smtp_env( string $key, string $default = '' ): string {
    // 1. Variable shell
    $val = getenv( $key );
    if ( $val !== false && $val !== '' ) {
        return $val;
    }
    // 2. Fichier .env à la racine WordPress
    $env_file = ABSPATH . '.env';
    if ( ! file_exists( $env_file ) ) {
        $env_file = dirname( ABSPATH ) . '/.env';
    }
    static $parsed = null;
    if ( $parsed === null ) {
        $parsed = [];
        if ( file_exists( $env_file ) ) {
            foreach ( file( $env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
                if ( str_starts_with( trim( $line ), '#' ) || ! str_contains( $line, '=' ) ) {
                    continue;
                }
                [ $k, $v ] = explode( '=', $line, 2 );
                $parsed[ trim( $k ) ] = trim( $v );
            }
        }
    }
    return $parsed[ $key ] ?? $default;
}

$site_url     = home_url();
$site_domain  = wp_parse_url( $site_url, PHP_URL_HOST );
$admin_email  = get_option( 'admin_email' );
$site_name    = get_bloginfo( 'name' );

// Variables SMTP (priorité : .env > valeurs dérivées du domaine)
$smtp_host    = _smtp_env( 'SMTP_HOST',     "mail.$site_domain" );
$smtp_port    = (int) _smtp_env( 'SMTP_PORT',     '587' );
$smtp_enc     = _smtp_env( 'SMTP_ENCRYPTION', 'tls' );  // tls (587) ou ssl (465)
$smtp_user    = _smtp_env( 'SMTP_USER',     _smtp_env( 'CONTACT_EMAIL', $admin_email ) );
$smtp_pass    = _smtp_env( 'SMTP_PASSWORD', '' );
$from_email   = _smtp_env( 'CONTACT_EMAIL', $admin_email );
$from_name    = _smtp_env( 'SITE_TITLE',    $site_name );

echo "  SMTP host : $smtp_host\n";
echo "  SMTP port : $smtp_port ($smtp_enc)\n";
echo "  From      : $from_name <$from_email>\n";

if ( ! $smtp_pass ) {
    echo "\n  ⚠️  SMTP_PASSWORD non défini dans .env\n";
    echo "      Ajoutez ces variables à setup/.env :\n";
    echo "        SMTP_HOST=$smtp_host\n";
    echo "        SMTP_PORT=587\n";
    echo "        SMTP_ENCRYPTION=tls\n";
    echo "        SMTP_USER=$smtp_user\n";
    echo "        SMTP_PASSWORD=votre_motdepasse_email\n";
    echo "        CONTACT_EMAIL=$from_email\n\n";
    echo "  Sur o2switch : cPanel → Comptes de messagerie → créez contact@$site_domain\n";
    echo "  Le mot de passe SMTP = mot de passe du compte email créé dans cPanel.\n\n";
}

// ── Vérification wp-mail-smtp ──────────────────────────────
if ( ! is_plugin_active( 'wp-mail-smtp/wp_mail_smtp.php' ) ) {
    echo "  wp-mail-smtp n'est pas actif.\n";
    echo "  Installez-le : wp plugin install wp-mail-smtp --activate\n";
    echo "  Puis relancez ce script.\n";
}

// ── Configuration wp-mail-smtp ────────────────────────────
// wp-mail-smtp stocke les réglages dans l'option 'wp_mail_smtp'
$smtp_config = [
    'mail' => [
        'from_email'        => $from_email,
        'from_name'         => $from_name,
        'mailer'            => 'smtp',
        'return_path'       => false,
        'from_email_force'  => true,
        'from_name_force'   => true,
    ],
    'smtp' => [
        'host'       => $smtp_host,
        'port'       => $smtp_port,
        'encryption' => $smtp_enc,  // 'tls', 'ssl', or 'none'
        'auth'       => true,
        'user'       => $smtp_user,
        'pass'       => $smtp_pass,
        'autotls'    => $smtp_enc === 'tls',
    ],
    'general' => [
        'am_notifications_hidden' => true,
        'email_test_address'      => $admin_email,
    ],
];

update_option( 'wp_mail_smtp', $smtp_config );
echo "  • Options wp-mail-smtp sauvegardées\n";

// ── Mettre à jour .env.example avec les variables SMTP ────
$env_example = ABSPATH . 'setup/.env.example';
if ( file_exists( $env_example ) ) {
    $content = file_get_contents( $env_example );
    $smtp_block = "\n# ── SMTP Production (o2switch) ────────────────────────────\n"
        . "# cPanel → Comptes de messagerie → créer contact@mondomaine.fr\n"
        . "# Le SMTP host o2switch est toujours mail.votredomaine.fr\n"
        . "SMTP_HOST=mail.monsite.fr\n"
        . "SMTP_PORT=587\n"
        . "SMTP_ENCRYPTION=tls\n"
        . "SMTP_USER=contact@monsite.fr\n"
        . "SMTP_PASSWORD=motdepasse_email_cpanel\n";

    if ( ! str_contains( $content, 'SMTP_HOST' ) ) {
        file_put_contents( $env_example, $content . $smtp_block );
        echo "  • Variables SMTP ajoutées dans .env.example\n";
    }
}

// ── Email de test ──────────────────────────────────────────
if ( $smtp_pass ) {
    echo "  • Envoi d'un email de test vers $admin_email...\n";
    $result = wp_mail(
        $admin_email,
        "[TEST SMTP] $site_name — Configuration o2switch",
        "Bonjour,\n\nSi vous recevez cet email, le SMTP o2switch est correctement configuré.\n\nServeur : $smtp_host:$smtp_port ($smtp_enc)\nExpéditeur : $from_name <$from_email>\n\n-- $site_name"
    );
    if ( $result ) {
        echo "  ✅ Email de test envoyé avec succès.\n";
    } else {
        global $phpmailer;
        $error = isset( $phpmailer ) ? $phpmailer->ErrorInfo : 'Erreur inconnue';
        echo "  ❌ Échec : $error\n";
        echo "     Vérifiez les credentials SMTP dans .env\n";
        echo "     et que le compte email existe dans cPanel.\n";
    }
} else {
    echo "  • Email de test ignoré (mot de passe SMTP non configuré)\n";
}

echo "\nSMTP production configuré.\n";
echo "\n  Checklist o2switch :\n";
echo "  1. cPanel → Comptes de messagerie → Créer contact@$site_domain\n";
echo "  2. Notez le mot de passe dans setup/.env (SMTP_PASSWORD=...)\n";
echo "  3. wp eval-file setup/configure-smtp-production.php\n";
echo "  4. Passez une commande test et vérifiez l'email de confirmation\n";
echo "\n  Ports o2switch disponibles :\n";
echo "  • 587 avec TLS (recommandé)\n";
echo "  • 465 avec SSL\n";
echo "  • 25 (parfois bloqué par les FAI)\n";
