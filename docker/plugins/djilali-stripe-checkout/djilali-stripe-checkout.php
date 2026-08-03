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

    // Bloquer les œuvres exposées (non à vendre) et les prestations
    if ($product->get_meta('_exhibition_only') === 'yes' || $product->is_virtual()) {
        wp_safe_redirect(get_permalink($product_id) ?: home_url('/galerie'));
        exit;
    }

    // Vérifier que le prix est valide (Stripe exige min 0,50 €)
    $price_cents = intval(round(floatval($product->get_price()) * 100));
    if ($price_cents < 50) {
        wp_safe_redirect(get_permalink($product_id) ?: home_url('/galerie'));
        exit;
    }

    $secret_key = djilali_stripe_secret_key();
    if (!$secret_key) {
        wp_safe_redirect(wc_get_checkout_url());
        exit;
    }

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

    // ── Pays acceptés (France + Europe + international) ──────────────────
    $countries = [
        // France et territoires
        'FR','GP','MQ','GF','RE','YT','PM','BL','MF','NC','PF','WF',
        // Europe
        'BE','LU','CH','DE','ES','IT','PT','NL','AT','SE','DK','FI',
        'NO','IE','PL','CZ','HU','RO','GR','HR','SK','SI','BG','EE',
        'LV','LT','CY','MT','IS','LI','AL','BA','ME','MK','RS','XK',
        'MD','UA','GB',
        // Amérique du Nord
        'US','CA','MX',
        // Amérique du Sud
        'BR','AR','CL','CO','PE',
        // Moyen-Orient & Afrique du Nord
        'MA','DZ','TN','LY','EG','AE','SA','QA','KW','BH','OM','JO','LB',
        // Afrique subsaharienne
        'SN','CI','CM','GA','CG','MG','MU',
        // Asie-Pacifique
        'JP','KR','CN','HK','TW','SG','AU','NZ','IN',
    ];
    foreach ($countries as $i => $code) {
        $body["shipping_address_collection[allowed_countries][$i]"] = $code;
    }

    // ── Options de livraison ──────────────────────────────────────────────
    // 0 : Retrait sur place
    $body['shipping_options[0][shipping_rate_data][type]']                                   = 'fixed_amount';
    $body['shipping_options[0][shipping_rate_data][display_name]']                           = 'Retrait sur place (gratuit)';
    $body['shipping_options[0][shipping_rate_data][fixed_amount][amount]']                   = 0;
    $body['shipping_options[0][shipping_rate_data][fixed_amount][currency]']                 = 'eur';
    $body['shipping_options[0][shipping_rate_data][delivery_estimate][minimum][unit]']       = 'business_day';
    $body['shipping_options[0][shipping_rate_data][delivery_estimate][minimum][value]']      = 1;
    $body['shipping_options[0][shipping_rate_data][delivery_estimate][maximum][unit]']       = 'business_day';
    $body['shipping_options[0][shipping_rate_data][delivery_estimate][maximum][value]']      = 5;

    // 1 : Colissimo France
    $body['shipping_options[1][shipping_rate_data][type]']                                   = 'fixed_amount';
    $body['shipping_options[1][shipping_rate_data][display_name]']                           = 'Colissimo suivi — France (3-5 jours)';
    $body['shipping_options[1][shipping_rate_data][fixed_amount][amount]']                   = 1500;
    $body['shipping_options[1][shipping_rate_data][fixed_amount][currency]']                 = 'eur';
    $body['shipping_options[1][shipping_rate_data][delivery_estimate][minimum][unit]']       = 'business_day';
    $body['shipping_options[1][shipping_rate_data][delivery_estimate][minimum][value]']      = 3;
    $body['shipping_options[1][shipping_rate_data][delivery_estimate][maximum][unit]']       = 'business_day';
    $body['shipping_options[1][shipping_rate_data][delivery_estimate][maximum][value]']      = 5;

    // 2 : Colissimo Europe
    $body['shipping_options[2][shipping_rate_data][type]']                                   = 'fixed_amount';
    $body['shipping_options[2][shipping_rate_data][display_name]']                           = 'Colissimo International — Europe (5-10 jours)';
    $body['shipping_options[2][shipping_rate_data][fixed_amount][amount]']                   = 4000;
    $body['shipping_options[2][shipping_rate_data][fixed_amount][currency]']                 = 'eur';
    $body['shipping_options[2][shipping_rate_data][delivery_estimate][minimum][unit]']       = 'business_day';
    $body['shipping_options[2][shipping_rate_data][delivery_estimate][minimum][value]']      = 5;
    $body['shipping_options[2][shipping_rate_data][delivery_estimate][maximum][unit]']       = 'business_day';
    $body['shipping_options[2][shipping_rate_data][delivery_estimate][maximum][value]']      = 10;

    // 3 : International hors Europe
    $body['shipping_options[3][shipping_rate_data][type]']                                   = 'fixed_amount';
    $body['shipping_options[3][shipping_rate_data][display_name]']                           = 'Expédition internationale — Hors Europe (7-15 jours)';
    $body['shipping_options[3][shipping_rate_data][fixed_amount][amount]']                   = 8000;
    $body['shipping_options[3][shipping_rate_data][fixed_amount][currency]']                 = 'eur';
    $body['shipping_options[3][shipping_rate_data][delivery_estimate][minimum][unit]']       = 'business_day';
    $body['shipping_options[3][shipping_rate_data][delivery_estimate][minimum][value]']      = 7;
    $body['shipping_options[3][shipping_rate_data][delivery_estimate][maximum][unit]']       = 'business_day';
    $body['shipping_options[3][shipping_rate_data][delivery_estimate][maximum][value]']      = 15;

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

    // Frais de port choisis par le client dans Stripe
    $shipping_cents  = intval($session['shipping_cost']['amount_total'] ?? 0);
    $shipping_euros  = $shipping_cents / 100;
    $shipping_name   = djilali_shipping_label($shipping_cents);

    // Créer la commande WooCommerce
    $order = wc_create_order();
    $order->add_product($product, 1, ['subtotal' => $product->get_price(), 'total' => $product->get_price()]);

    // Ajouter la ligne livraison si non-gratuit ou si retrait sur place
    $shipping_item = new WC_Order_Item_Shipping();
    $shipping_item->set_method_title($shipping_name);
    $shipping_item->set_method_id('djilali_stripe_shipping');
    $shipping_item->set_total($shipping_euros);
    $order->add_item($shipping_item);

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
    $order->update_meta_data('_djilali_shipping_method', $shipping_name);
    $order->calculate_totals();
    $order->save();

    // Note interne avec le détail de la livraison
    if ($shipping_cents > 0) {
        $order->add_order_note(
            sprintf('Livraison choisie : %s — %s €', $shipping_name, number_format($shipping_euros, 2, ',', ' ')),
            false, // note admin uniquement (pas visible client)
            true   // ajoutée par le système
        );
    }

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

/**
 * Retourne le libellé de livraison à partir du montant en centimes.
 * Correspond aux options définies dans la session Stripe.
 */
function djilali_shipping_label(int $cents): string {
    return match($cents) {
        0    => 'Retrait sur place',
        1500 => 'Colissimo suivi — France',
        4000 => 'Colissimo International — Europe',
        8000 => 'Expédition internationale — Hors Europe',
        default => 'Livraison (' . number_format($cents / 100, 2, ',', ' ') . ' €)',
    };
}

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
// 5. Fiche produit : bouton acheter / badge exposition / lien contact
//    - Œuvres disponibles  → redirige vers Stripe
//    - Œuvres exposition   → badge "non disponible à la vente"
//    - Prestations         → bouton "Nous contacter" vers /contact/
// ─────────────────────────────────────────────
add_action('wp_footer', function () {
    if (!is_product()) return;
    global $product;
    if (!$product) return;

    $exhibition_only = $product->get_meta('_exhibition_only') === 'yes';
    $is_virtual      = $product->is_virtual();

    if ($exhibition_only) {
        ?>
        <style>
        .djilali-badge-expo {
            display: inline-block;
            margin: 1rem 0;
            padding: .65rem 1.4rem;
            background: #f5f1eb;
            border: 1.5px solid #c9bfa9;
            border-radius: 4px;
            color: #6b5f4e;
            font-size: .92rem;
            font-style: italic;
            letter-spacing: .02em;
        }
        </style>
        <script>
        (function() {
            var form = document.querySelector('form.cart');
            if (!form) return;
            var badge = document.createElement('div');
            badge.className = 'djilali-badge-expo';
            badge.textContent = "Cette œuvre est exposée et n'est pas disponible à la vente.";
            form.replaceWith(badge);
        })();
        </script>
        <?php
    } elseif ($is_virtual) {
        // Prestation sur devis : bouton "Faire une demande" → formulaire prestations
        $demande_url = esc_url(home_url('/prestations/#demande-prestation'));
        ?>
        <script>
        (function() {
            var wrap = document.createElement('div');
            wrap.style.cssText = 'margin:1.5rem 0';
            var btn = document.createElement('a');
            btn.href = <?php echo json_encode($demande_url); ?>;
            btn.className = 'button';
            btn.textContent = 'Faire une demande';
            wrap.appendChild(btn);

            // WooCommerce n'affiche pas form.cart pour les produits sans prix
            var form = document.querySelector('form.cart');
            if (form) {
                form.replaceWith(wrap);
            } else {
                // Injecter après le bloc prix (thème enfant ou thème standard)
                var priceBlock = document.querySelector(
                    '.artwork-single__price, .summary .price, .summary .djilali-sur-devis'
                );
                if (priceBlock) {
                    priceBlock.insertAdjacentElement('afterend', wrap);
                } else {
                    var summary = document.querySelector('.summary, .product .entry-summary');
                    if (summary) summary.appendChild(wrap);
                }
            }
        })();
        </script>
        <?php
    } else {
        // Œuvre disponible : lien direct vers Stripe
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
    }
});

// Prix "Sur devis" pour les produits virtuels sans prix
add_filter('woocommerce_get_price_html', function ($price_html, $product) {
    if ($product->is_virtual() && '' === $product->get_price()) {
        return '<span class="djilali-sur-devis">Sur devis</span>';
    }
    return $price_html;
}, 10, 2);

// CSS : forcer la visibilité permanente des boutons sur la page Prestations
add_action('wp_head', function () {
    if (!is_page('prestations')) return;
    echo '<style>
.woocommerce ul.products li.product .product-action-wrap {
    position: static !important;
    opacity: 1 !important;
    bottom: auto !important;
    width: 100% !important;
    padding: 0 !important;
    margin-top: .8rem !important;
    transition: none !important;
}
</style>' . "\n";
});

// Bouton "Faire une demande" dans les grilles WooCommerce (shortcode, archives)
// Remplace "Acheter l'oeuvre" pour les prestations (produits virtuels)
add_filter('woocommerce_loop_add_to_cart_link', function ($link, $product, $args) {
    if (!$product->is_virtual()) return $link;

    $url   = esc_url(home_url('/prestations/#demande-prestation'));
    $class = isset($args['class']) ? esc_attr($args['class']) : 'button';

    return sprintf(
        '<a href="%s" class="%s">Faire une demande</a>',
        $url,
        $class
    );
}, 10, 3);


// ─────────────────────────────────────────────
// 6. SMTP via Brevo (credentials stockés en wp_options)
// ─────────────────────────────────────────────
add_action('phpmailer_init', function ($mailer) {
    $host = get_option('djilali_smtp_host', '');
    $user = get_option('djilali_smtp_user', '');
    $pass = get_option('djilali_smtp_pass', '');
    if (!$host || !$user || !$pass) return;

    $mailer->isSMTP();
    $mailer->Host       = $host;
    $mailer->SMTPAuth   = true;
    $mailer->Port       = 465;
    $mailer->Username   = $user;
    $mailer->Password   = $pass;
    $mailer->SMTPSecure = 'ssl'; // SSL direct (port 465)
    $mailer->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ],
    ];
    // Brevo exige que le From soit toujours l'adresse authentifiée — on le force
    $mailer->From     = 'djilali.kadid.galerie@gmail.com';
    $mailer->FromName = $mailer->FromName ?: 'Galerie Djilali Kadid';
});


// ─────────────────────────────────────────────
// 7. Endpoint REST : formulaire de contact
//    POST /wp-json/djilali/v1/contact
//    Champs : name, email, subject, message
// ─────────────────────────────────────────────
add_action('rest_api_init', function () {
    register_rest_route('djilali/v1', '/contact', [
        'methods'             => 'POST',
        'callback'            => 'djilali_handle_contact',
        'permission_callback' => '__return_true',
    ]);
});

function djilali_handle_contact(WP_REST_Request $request) {
    // Formulaire public — pas de nonce requis, validation des champs uniquement
    $name    = sanitize_text_field($request->get_param('name')    ?? '');
    $email   = sanitize_email($request->get_param('email')        ?? '');
    $subject = sanitize_text_field($request->get_param('subject') ?? '');
    $message = sanitize_textarea_field($request->get_param('message') ?? '');

    if (!$name || !$email || !$message) {
        return new WP_REST_Response(['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.'], 400);
    }
    if (!is_email($email)) {
        return new WP_REST_Response(['success' => false, 'message' => 'Adresse email invalide.'], 400);
    }

    $to         = 'djilali.kadid.galerie@gmail.com';
    $subj_admin = $subject ? "Message de $name : $subject" : "Message de $name via le site";
    $body_admin = "Nom : $name\nEmail : $email\nSujet : $subject\n\n$message";
    $headers    = ['Content-Type: text/plain; charset=UTF-8', "Reply-To: $name <$email>"];

    $sent = wp_mail($to, $subj_admin, $body_admin, $headers);

    if ($sent) {
        // Confirmation automatique à l'expéditeur
        $subj_auto = 'Merci pour votre message - Galerie Djilali Kadid';
        $body_auto = "Bonjour $name,\n\nMerci pour votre message. Je vous répondrai rapidement.\n\nBien cordialement,\nGalerie Djilali Kadid";
        wp_mail($email, $subj_auto, $body_auto, ['Content-Type: text/plain; charset=UTF-8']);

        return new WP_REST_Response(['success' => true, 'message' => 'Votre message a bien été envoyé.'], 200);
    }

    return new WP_REST_Response(['success' => false, 'message' => 'Une erreur est survenue. Merci de réessayer.'], 500);
}


// ─────────────────────────────────────────────
// 8. Endpoint REST : demande de prestation
//    POST /wp-json/djilali/v1/prestation
//    Champs : prestation, dates, people, description, name, email
// ─────────────────────────────────────────────
add_action('rest_api_init', function () {
    register_rest_route('djilali/v1', '/prestation', [
        'methods'             => 'POST',
        'callback'            => 'djilali_handle_prestation',
        'permission_callback' => '__return_true',
    ]);
});

function djilali_handle_prestation(WP_REST_Request $request) {
    $prestation  = sanitize_text_field($request->get_param('prestation')  ?? '');
    $dates       = sanitize_text_field($request->get_param('dates')        ?? '');
    $people      = sanitize_text_field($request->get_param('people')       ?? '');
    $description = sanitize_textarea_field($request->get_param('description') ?? '');
    $name        = sanitize_text_field($request->get_param('name')         ?? '');
    $email       = sanitize_email($request->get_param('email')             ?? '');

    if (!$prestation || !$name || !$email || !$description) {
        return new WP_REST_Response(['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.'], 400);
    }
    if (!is_email($email)) {
        return new WP_REST_Response(['success' => false, 'message' => 'Adresse email invalide.'], 400);
    }

    $to      = 'djilali.kadid.galerie@gmail.com';
    $subject = "Demande de prestation : $prestation — $name";
    $body    = "Prestation souhaitée : $prestation\n"
             . "Dates souhaitées     : $dates\n"
             . "Nombre de personnes  : $people\n\n"
             . "Description du projet :\n$description\n\n"
             . "Contact :\nNom : $name\nEmail : $email";
    $headers = ['Content-Type: text/plain; charset=UTF-8', "Reply-To: $name <$email>"];

    $sent = wp_mail($to, $subject, $body, $headers);

    if ($sent) {
        $subj_auto = 'Votre demande de prestation - Galerie Djilali Kadid';
        $body_auto = "Bonjour $name,\n\nMerci pour votre demande concernant : $prestation.\n\nDjilali Kadid reviendra vers vous rapidement pour discuter de votre projet et vous proposer un devis.\n\nBien cordialement,\nGalerie Djilali Kadid";
        wp_mail($email, $subj_auto, $body_auto, ['Content-Type: text/plain; charset=UTF-8']);

        return new WP_REST_Response(['success' => true, 'message' => 'Votre demande a bien été envoyée.'], 200);
    }

    return new WP_REST_Response(['success' => false, 'message' => 'Une erreur est survenue. Merci de réessayer.'], 500);
}


// ─────────────────────────────────────────────
// 9. Endpoint REST : formulaire de collaboration
//    POST /wp-json/djilali/v1/collaboration
// ─────────────────────────────────────────────
add_action('rest_api_init', function () {
    register_rest_route('djilali/v1', '/collaboration', [
        'methods'             => 'POST',
        'callback'            => 'djilali_handle_collaboration',
        'permission_callback' => '__return_true',
    ]);
});

function djilali_handle_collaboration(WP_REST_Request $request) {
    $type    = sanitize_text_field($request->get_param('type')    ?? '');
    $org     = sanitize_text_field($request->get_param('org')     ?? '');
    $name    = sanitize_text_field($request->get_param('name')    ?? '');
    $email   = sanitize_email($request->get_param('email')        ?? '');
    $message = sanitize_textarea_field($request->get_param('message') ?? '');

    if (!$name || !$email || !$message) {
        return new WP_REST_Response(['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.'], 400);
    }
    if (!is_email($email)) {
        return new WP_REST_Response(['success' => false, 'message' => 'Adresse email invalide.'], 400);
    }

    $to      = 'djilali.kadid.galerie@gmail.com';
    $subject = 'Proposition de collaboration : ' . ($type ?: 'Non précisé') . ' — ' . $name . ($org ? " ($org)" : '');
    $body    = "Type : $type\nOrganisation : $org\n\nMessage :\n$message\n\nContact :\nNom : $name\nEmail : $email";
    $headers = ['Content-Type: text/plain; charset=UTF-8', "Reply-To: $name <$email>"];

    $sent = wp_mail($to, $subject, $body, $headers);

    if ($sent) {
        $subj_auto = 'Votre proposition de collaboration - Galerie Djilali Kadid';
        $body_auto = "Bonjour $name,\n\nMerci pour votre proposition. Djilali Kadid vous répondra dans les meilleurs délais.\n\nBien cordialement,\nGalerie Djilali Kadid";
        wp_mail($email, $subj_auto, $body_auto, ['Content-Type: text/plain; charset=UTF-8']);
        return new WP_REST_Response(['success' => true, 'message' => 'Votre proposition a bien été envoyée.'], 200);
    }

    return new WP_REST_Response(['success' => false, 'message' => 'Une erreur est survenue. Merci de réessayer.'], 500);
}


// ─────────────────────────────────────────────
// 10. URLs REST injectées dans <head>
// ─────────────────────────────────────────────
add_action('wp_head', function () {
    if (is_page('contact')) {
        $url = esc_url(rest_url('djilali/v1/contact'));
        echo '<script>window.djilaliContactUrl=' . json_encode($url) . ';</script>' . "\n";
    }
    if (is_page('prestations') || is_page(86)) {
        $url = esc_url(rest_url('djilali/v1/prestation'));
        echo '<script>window.djilaliPrestationUrl=' . json_encode($url) . ';</script>' . "\n";
    }
    if (is_page('collaborations')) {
        $url = esc_url(rest_url('djilali/v1/collaboration'));
        echo '<script>window.djilaliCollaborationUrl=' . json_encode($url) . ';</script>' . "\n";
    }
});


// ─────────────────────────────────────────────
// 9. Flush rewrite rules à l'activation
// ─────────────────────────────────────────────
register_activation_hook(__FILE__, function () {
    add_rewrite_rule('^acheter/(\d+)/?$', 'index.php?djilali_buy=$matches[1]', 'top');
    add_rewrite_rule('^commande-confirmee/?$', 'index.php?djilali_merci=1', 'top');
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, 'flush_rewrite_rules');
