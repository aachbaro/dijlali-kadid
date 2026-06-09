<?php
/**
 * wp-config-template.php — Template sécurisé
 *
 * Renommez en wp-config.php à la racine WordPress.
 * Ne commitez JAMAIS wp-config.php dans Git.
 *
 * Génération des clés de sécurité :
 *   wp config shuffle-salts
 * ou : https://api.wordpress.org/secret-key/1.1/salt/
 */

// ── Chargement du .env (si présent à la racine) ────────────
$_env_file = __DIR__ . '/.env';
if ( file_exists( $_env_file ) ) {
    foreach ( file( $_env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $_line ) {
        if ( str_starts_with( trim( $_line ), '#' ) || ! str_contains( $_line, '=' ) ) {
            continue;
        }
        [ $_k, $_v ] = explode( '=', $_line, 2 );
        putenv( trim( $_k ) . '=' . trim( $_v ) );
    }
}

function _env( string $key, $default = null ) {
    $v = getenv( $key );
    return $v !== false ? $v : $default;
}

// ── Base de données ────────────────────────────────────────
define( 'DB_NAME',     _env( 'DB_NAME',     'REMPLACEZ' ) );
define( 'DB_USER',     _env( 'DB_USER',     'REMPLACEZ' ) );
define( 'DB_PASSWORD', _env( 'DB_PASSWORD', 'REMPLACEZ' ) );
define( 'DB_HOST',     _env( 'DB_HOST',     'localhost' ) );
define( 'DB_CHARSET',  'utf8mb4' );
define( 'DB_COLLATE',  'utf8mb4_unicode_ci' );

// ── Préfixe de table ───────────────────────────────────────
$table_prefix = _env( 'TABLE_PREFIX', 'art_' );

// ── Clés de sécurité ───────────────────────────────────────
// OBLIGATOIRE : remplacez toutes ces valeurs avant mise en prod.
// Commande : wp config shuffle-salts
define( 'AUTH_KEY',         'REMPLACEZ_PAR_UNE_VALEUR_UNIQUE_1' );
define( 'SECURE_AUTH_KEY',  'REMPLACEZ_PAR_UNE_VALEUR_UNIQUE_2' );
define( 'LOGGED_IN_KEY',    'REMPLACEZ_PAR_UNE_VALEUR_UNIQUE_3' );
define( 'NONCE_KEY',        'REMPLACEZ_PAR_UNE_VALEUR_UNIQUE_4' );
define( 'AUTH_SALT',        'REMPLACEZ_PAR_UNE_VALEUR_UNIQUE_5' );
define( 'SECURE_AUTH_SALT', 'REMPLACEZ_PAR_UNE_VALEUR_UNIQUE_6' );
define( 'LOGGED_IN_SALT',   'REMPLACEZ_PAR_UNE_VALEUR_UNIQUE_7' );
define( 'NONCE_SALT',       'REMPLACEZ_PAR_UNE_VALEUR_UNIQUE_8' );

// ── URLs ───────────────────────────────────────────────────
define( 'WP_HOME',    _env( 'WP_HOME',    'https://monsite.fr' ) );
define( 'WP_SITEURL', _env( 'WP_SITEURL', 'https://monsite.fr' ) );

// ── Sécurité ───────────────────────────────────────────────
define( 'DISALLOW_FILE_EDIT', true );   // Désactive l'éditeur de fichiers dans l'admin
define( 'DISALLOW_FILE_MODS', false );  // Garde la possibilité d'installer des plugins
define( 'FORCE_SSL_ADMIN',    true );   // Admin toujours en HTTPS

// ── Performances ───────────────────────────────────────────
define( 'WP_POST_REVISIONS', 5 );       // 5 révisions max par article
define( 'EMPTY_TRASH_DAYS',  30 );      // Corbeille vidée après 30 jours
define( 'WP_AUTO_UPDATE_CORE', 'minor' ); // Mises à jour mineures automatiques
define( 'AUTOSAVE_INTERVAL', 120 );     // Autosave toutes les 2 min
define( 'WP_MEMORY_LIMIT',       '256M' );
define( 'WP_MAX_MEMORY_LIMIT',   '512M' );

// ── Debug ──────────────────────────────────────────────────
$_debug = filter_var( _env( 'WP_DEBUG', 'false' ), FILTER_VALIDATE_BOOLEAN );
define( 'WP_DEBUG',         $_debug );
define( 'WP_DEBUG_LOG',     $_debug );    // Logs dans /wp-content/debug.log
define( 'WP_DEBUG_DISPLAY', false );      // Ne jamais afficher les erreurs en prod
define( 'SCRIPT_DEBUG',     false );
@ini_set( 'display_errors', 0 );

// ── Chemin WordPress ───────────────────────────────────────
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

require_once ABSPATH . 'wp-settings.php';
