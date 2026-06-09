<?php
/**
 * Plugin Name: Djilali – Stripe Direct Checkout
 * Description: Remplace le flux WooCommerce cart/checkout par Stripe Checkout hébergé.
 *              Flow : bouton "Acheter l'oeuvre" → Stripe (adresse + paiement) → webhook → commande WC + stock + email.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

// ─────────────────────────────────────────────
// 1. Endpoint dédié /acheter/{product_id}
//    Bypass complet de WooCommerce add-to-cart
// ─────────────────────────────────────────────
add_action('init', function () {
    add_rewrite_rule('^acheter/(\d+)/?$', 'index.php?djilali_buy=$matches[1]', 'top');
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'djilali_buy';
    $vars[] = 'djilali_merci';
    return $vars;
});

add_action('template_redirect', function () {
    $product_id = intval(get_query_var('djilali_buy'));
    if (!$product_id) return;

    $product = wc_get_product($product_id);
    if (!$product || !$product->is_in_stock()) {
        wp_safe_redirect(get_permalink($product_id) ?: home_url('/galerie'));
        exit;
    }

    $secret_key = djilali_stripe_secret_key();
    if (!$secret_key) {
        wp_safe_redirect(wc_get_checkout_url());
        exit;
    }

    $price_cents = intval(round(floatval($product->get_price()) * 100));
    $cancel_url  = get_permalink($product_id);
    $success_url = home_url('/commande-confirmee/?session_id={CHECKOUT_SESSION_ID}');

    $body = [
        'mode'                             => 'payment',
        'success_url'                      => $success_url,
        'cancel_url'                       => $cancel_url,
        'metadata[product_id]'             => $product_id,
        'phone_number_collection[enabled]' => 'true',
        'line_items[0][quantity]'                       => 1,
        'line_items[0][price_data][currency]'           => 'eur',
        'line_items[0][price_data][unit_amount]'        => $price_cents,
        'line_items[0][price_data][product_data][name]' => $product->get_name(),
    ];

    $countries = ['FR','BE','LU','CH','DE','ES','IT','PT','NL',
                  'AT','SE','DK','FI','NO','IE','PL','CZ','HU',
                  'RO','GR','HR','SK','SI','BG','EE','LV','LT'];
    foreach ($countries as $i => $code) {
        $body["shipping_address_collection[allowed_countries][$i]"] = $code;
    }

    $image_id  = $product->get_image_id();
    $image_url = $image_id ? wp_get_attachment_url($image_id) : '';
    if ($image_url) {
        $body['line_items[0][price_data][product_data][images][0]'] = $image_url;
    }

    $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', [
        'timeout' => 15,
        'headers' => [
            'Authorization' => 'Bearer ' . $secret_key,
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ],
        'body' => http_build_query($body),
    ]);

    if (is_wp_error($response)) {
        wp_safe_redirect($cancel_url);
        exit;
    }

    $session = json_decode(wp_remote_retrieve_body($response), true);

    if (!empty($session['url'])) {
        wp_redirect($session['url']);
        exit;
    }

    error_log('[djilali-stripe] Erreur : ' . ($session['error']['message'] ?? 'unknown'));
    wp_safe_redirect($cancel_url);
    exit;
});


// ─────────────────────────────────────────────
// 2. Page "Commande confirmée"
// ─────────────────────────────────────────────
add_action('init', function () {
    add_rewrite_rule('^commande-confirmee/?$', 'index.php?djilali_merci=1', 'top');
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'djilali_merci';
    return $vars;
});

add_action('template_redirect', function () {
    if (!get_query_var('djilali_merci')) return;

    status_header(200);
    get_header();
    echo '<main style="max-width:640px;margin:80px auto;padding:2rem;text-align:center;">';
    echo '<h1>Merci pour votre achat&nbsp;!</h1>';
    echo '<p>Vous recevrez une confirmation par email. Djilali vous contactera pour organiser la livraison.</p>';
    echo '<p><a href="' . home_url('/galerie') . '">← Retour à la galerie</a></p>';
    echo '</main>';
    get_footer();
    exit;
});


// ─────────────────────────────────────────────
// 3. Webhook Stripe → commande WC + stock + email
// ─────────────────────────────────────────────
add_action('rest_api_init', function () {
    register_rest_route('djilali/v1', '/stripe-webhook', [
        'methods'             => 'POST',
        'callback'            => 'djilali_handle_webhook',
        'permission_callback' => '__return_true',
    ]);
});

function djilali_handle_webhook(WP_REST_Request $request) {
    $payload    = $request->get_body();
    $sig_header = $request->get_header('stripe-signature') ?? '';
    $secret     = get_option('djilali_stripe_webhook_secret', '');

    // Vérification signature (obligatoire en prod, optionnel si secret vide)
    if ($secret && !djilali_verify_stripe_sig($payload, $sig_header, $secret)) {
        return new WP_REST_Response(['error' => 'Invalid signature'], 400);
    }

    $event = json_decode($payload, true);

    if (($event['type'] ?? '') !== 'checkout.session.completed') {
        return new WP_REST_Response(['received' => true]);
    }

    $session    = $event['data']['object'];
    $product_id = intval($session['metadata']['product_id'] ?? 0);
    $product    = $product_id ? wc_get_product($product_id) : null;

    if (!$product) {
        return new WP_REST_Response(['received' => true]);
    }

    // Éviter le double traitement
    $session_id = $session['id'];
    if (get_posts(['post_type' => 'shop_order', 'meta_key' => '_stripe_session_id',
                   'meta_value' => $session_id, 'numberposts' => 1])) {
        return new WP_REST_Response(['received' => true]);
    }

    // Infos client
    $customer   = $session['customer_details'] ?? [];
    $shipping   = $session['shipping_details'] ?? $customer;
    $email      = $customer['email'] ?? '';
    $name       = $customer['name']  ?? '';
    $addr       = $shipping['address'] ?? [];

    // Créer la commande WooCommerce
    $order = wc_create_order();
    $order->add_product($product, 1, ['subtotal' => $product->get_price(), 'total' => $product->get_price()]);

    $order->set_billing_email($email);
    $order->set_billing_first_name($name);
    $order->set_billing_address_1($addr['line1']       ?? '');
    $order->set_billing_address_2($addr['line2']       ?? '');
    $order->set_billing_city($addr['city']             ?? '');
    $order->set_billing_postcode($addr['postal_code']  ?? '');
    $order->set_billing_country($addr['country']       ?? '');
    $order->set_shipping_first_name($name);
    $order->set_shipping_address_1($addr['line1']      ?? '');
    $order->set_shipping_address_2($addr['line2']      ?? '');
    $order->set_shipping_city($addr['city']            ?? '');
    $order->set_shipping_postcode($addr['postal_code'] ?? '');
    $order->set_shipping_country($addr['country']      ?? '');

    $order->set_payment_method('stripe');
    $order->set_payment_method_title('Stripe');
    $order->update_meta_data('_stripe_session_id', $session_id);
    $order->calculate_totals();
    $order->save();

    // Marquer comme payé → déclenche email "Nouvelle commande" à l'admin + confirmation client
    $payment_intent = $session['payment_intent'] ?? $session_id;
    $order->payment_complete($payment_intent);

    // Mettre le stock à 0 (œuvre vendue)
    $product->set_stock_quantity(0);
    $product->set_stock_status('outofstock');
    $product->save();

    return new WP_REST_Response(['received' => true]);
}

function djilali_verify_stripe_sig(string $payload, string $sig_header, string $secret): bool {
    $timestamp  = null;
    $signatures = [];

    foreach (explode(',', $sig_header) as $part) {
        if (str_starts_with($part, 't=')) {
            $timestamp = substr($part, 2);
        } elseif (str_starts_with($part, 'v1=')) {
            $signatures[] = substr($part, 3);
        }
    }

    if (!$timestamp || empty($signatures)) return false;
    if (abs(time() - intval($timestamp)) > 300) return false;

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

    foreach ($signatures as $sig) {
        if (hash_equals($expected, $sig)) return true;
    }
    return false;
}


// ─────────────────────────────────────────────
// 4. Helpers
// ─────────────────────────────────────────────
function djilali_stripe_secret_key(): string {
    // Priorité : option dédiée > clé du plugin woo-stripe-payment
    $key = get_option('djilali_stripe_secret_key', '');
    if ($key) return $key;

    // Lire la clé du plugin woo-stripe-payment déjà configuré
    $api = get_option('woocommerce_stripe_api_settings', []);
    $mode = $api['mode'] ?? 'test';
    return $mode === 'live' ? ($api['live_secret_key'] ?? '') : ($api['test_secret_key'] ?? '');
}


// ─────────────────────────────────────────────
// 5. Remplacer le formulaire add-to-cart par un lien direct /acheter/{id}
// ─────────────────────────────────────────────
add_action('wp_footer', function () {
    if (!is_product()) return;
    global $product;
    if (!$product) return;
    $url = esc_url(home_url('/acheter/' . $product->get_id() . '/'));
    ?>
    <script>
    (function() {
        var form = document.querySelector('form.cart');
        if (!form) return;
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            window.location.href = <?php echo json_encode($url); ?>;
        });
    })();
    </script>
    <?php
});


// ─────────────────────────────────────────────
// 6. Flush rewrite rules à l'activation
// ─────────────────────────────────────────────
register_activation_hook(__FILE__, function () {
    add_rewrite_rule('^commande-confirmee/?$', 'index.php?djilali_merci=1', 'top');
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, 'flush_rewrite_rules');
