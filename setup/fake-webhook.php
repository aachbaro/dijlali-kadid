<?php
/**
 * fake-webhook.php — Faux webhook Stripe pour développement local
 *
 * ╔══════════════════════════════════════════════════════════════╗
 * ║  ⚠️  DÉVELOPPEMENT LOCAL UNIQUEMENT — RETIREZ AVANT PROD  ⚠️  ║
 * ║                                                              ║
 * ║  Ce fichier simule les webhooks Stripe sans connexion        ║
 * ║  réseau. Il expose un endpoint public non authentifié        ║
 * ║  (sauf token) qui marque des commandes comme payées.         ║
 * ║                                                              ║
 * ║  AVANT DE METTRE EN PRODUCTION :                             ║
 * ║  rm wp-content/mu-plugins/fake-webhook.php                   ║
 * ╚══════════════════════════════════════════════════════════════╝
 *
 * Endpoint : http://art-sell.local/fake-webhook?token=test123
 *
 * Payload attendu (POST JSON) :
 * {
 *   "type": "checkout.session.completed",
 *   "data": {
 *     "object": {
 *       "payment_status": "paid",
 *       "metadata": { "order_id": "42" }
 *     }
 *   }
 * }
 *
 * @package kadence-child
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Enregistrement du rewrite endpoint ────────────────────
add_action( 'init', function (): void {
    add_rewrite_rule( '^fake-webhook/?$', 'index.php?fake_webhook=1', 'top' );
} );

add_filter( 'query_vars', function ( array $vars ): array {
    $vars[] = 'fake_webhook';
    return $vars;
} );

// ── Flush des règles si nécessaire ────────────────────────
add_action( 'wp_loaded', function (): void {
    $rules = get_option( 'rewrite_rules', [] );
    if ( ! isset( $rules['^fake-webhook/?$'] ) ) {
        flush_rewrite_rules( false );
    }
} );

// ── Traitement de la requête ───────────────────────────────
add_action( 'template_redirect', function (): void {
    if ( ! get_query_var( 'fake_webhook' ) ) {
        return;
    }

    // ── Sécurité : token ────────────────────────────────────
    $expected_token = defined( 'FAKE_WEBHOOK_TOKEN' ) ? FAKE_WEBHOOK_TOKEN : 'test123';
    $received_token = $_GET['token'] ?? '';

    if ( ! hash_equals( $expected_token, $received_token ) ) {
        _fwh_log( 'REFUS', 'Token invalide : ' . substr( $received_token, 0, 20 ) );
        http_response_code( 403 );
        header( 'Content-Type: application/json' );
        echo wp_json_encode( [ 'error' => 'Forbidden', 'hint' => 'Token invalide' ] );
        exit;
    }

    // ── Lecture du payload ──────────────────────────────────
    if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
        http_response_code( 405 );
        header( 'Content-Type: application/json' );
        echo wp_json_encode( [ 'error' => 'POST requis' ] );
        exit;
    }

    $body = file_get_contents( 'php://input' );
    if ( empty( $body ) ) {
        http_response_code( 400 );
        echo wp_json_encode( [ 'error' => 'Corps vide' ] );
        exit;
    }

    $payload = json_decode( $body, true );
    if ( ! $payload || json_last_error() !== JSON_ERROR_NONE ) {
        _fwh_log( 'ERREUR', 'JSON invalide : ' . substr( $body, 0, 200 ) );
        http_response_code( 400 );
        echo wp_json_encode( [ 'error' => 'JSON invalide' ] );
        exit;
    }

    _fwh_log( 'RECU', 'Type : ' . ( $payload['type'] ?? '?' ) . ' | Payload : ' . substr( $body, 0, 500 ) );

    // ── Dispatch selon le type d'événement ─────────────────
    $event_type     = $payload['type']                                    ?? '';
    $session        = $payload['data']['object']                          ?? [];
    $payment_status = $session['payment_status']                          ?? '';
    $order_id       = (int) ( $session['metadata']['order_id']            ?? 0 );

    // Support alternatif : payment_intent.succeeded
    if ( $event_type === 'payment_intent.succeeded' ) {
        $order_id = (int) ( $session['metadata']['order_id'] ?? $session['metadata']['woo_order_id'] ?? 0 );
        $payment_status = 'paid';
    }

    if ( ! in_array( $event_type, [ 'checkout.session.completed', 'payment_intent.succeeded' ], true ) ) {
        _fwh_log( 'IGNORE', "Evenement '$event_type' non geré" );
        http_response_code( 200 );
        echo wp_json_encode( [ 'status' => 'ignored', 'type' => $event_type ] );
        exit;
    }

    if ( $payment_status !== 'paid' ) {
        _fwh_log( 'IGNORE', "payment_status = '$payment_status' (pas 'paid')" );
        http_response_code( 200 );
        echo wp_json_encode( [ 'status' => 'ignored', 'reason' => 'payment_status != paid' ] );
        exit;
    }

    if ( ! $order_id ) {
        _fwh_log( 'ERREUR', 'order_id manquant dans metadata' );
        http_response_code( 400 );
        echo wp_json_encode( [ 'error' => 'order_id manquant dans metadata' ] );
        exit;
    }

    // ── Traitement de la commande ───────────────────────────
    $result = _fwh_process_order( $order_id );

    http_response_code( $result['success'] ? 200 : 422 );
    header( 'Content-Type: application/json' );
    echo wp_json_encode( $result );
    exit;
} );

// ── Traitement : marquer la commande comme payée ──────────
function _fwh_process_order( int $order_id ): array {
    $order = wc_get_order( $order_id );

    if ( ! $order ) {
        _fwh_log( 'ERREUR', "Commande #$order_id introuvable" );
        return [ 'success' => false, 'error' => "Commande #$order_id introuvable" ];
    }

    $current_status = $order->get_status();
    _fwh_log( 'INFO', "Commande #$order_id trouvee — statut actuel : $current_status" );

    // Eviter le double-traitement
    if ( in_array( $current_status, [ 'processing', 'completed' ], true ) ) {
        _fwh_log( 'IGNORE', "Commande #$order_id deja traitee (statut: $current_status)" );
        return [
            'success'  => true,
            'order_id' => $order_id,
            'status'   => $current_status,
            'message'  => 'Commande deja traitee',
        ];
    }

    // 1. Marquer comme payée → déclenche réduction de stock + email confirmation
    $transaction_id = 'fake_' . uniqid( '', true );
    $order->payment_complete( $transaction_id );

    // 2. Note de commande pour tracer l'origine
    $order->add_order_note(
        '[FAKE WEBHOOK] Paiement Stripe simulé en mode dev. ' .
        'Transaction ID : ' . $transaction_id
    );

    // 3. Sauvegarder l'ID de transaction fictif
    $order->set_transaction_id( $transaction_id );
    $order->save();

    $new_status = $order->get_status();
    _fwh_log( 'OK', "Commande #$order_id : $current_status → $new_status | TX: $transaction_id" );

    // Vérification du stock
    $stock_log = [];
    foreach ( $order->get_items() as $item ) {
        $product = $item->get_product();
        if ( $product && $product->managing_stock() ) {
            $stock_log[] = $product->get_name() . ' → stock : ' . $product->get_stock_quantity();
        }
    }
    if ( $stock_log ) {
        _fwh_log( 'STOCK', implode( ' | ', $stock_log ) );
    }

    return [
        'success'        => true,
        'order_id'       => $order_id,
        'previous_status' => $current_status,
        'new_status'     => $new_status,
        'transaction_id' => $transaction_id,
        'stock'          => $stock_log,
    ];
}

// ── Logger ─────────────────────────────────────────────────
function _fwh_log( string $level, string $message ): void {
    $log_file = WP_CONTENT_DIR . '/fake-webhook.log';
    $line = sprintf(
        "[%s] [%-6s] %s\n",
        date( 'Y-m-d H:i:s' ),
        $level,
        $message
    );
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
    file_put_contents( $log_file, $line, FILE_APPEND | LOCK_EX );
}

// ── Alerte dans l'admin : rappel de désinstallation ───────
add_action( 'admin_notices', function (): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    echo '<div class="notice notice-error" style="border-left-color:#c0392b;">';
    echo '<p><strong>⚠️ DEV UNIQUEMENT :</strong> Le fake webhook Stripe est actif (<code>mu-plugins/fake-webhook.php</code>). ';
    echo '<strong>Retirez ce fichier avant la mise en production.</strong></p>';
    echo '</div>';
} );
