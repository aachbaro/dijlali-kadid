<?php
/**
 * configure-amelia.php
 * wp eval-file setup/configure-amelia.php
 *
 * Configure Amelia : paramètres globaux + catégorie + services de démo
 * + employé (l'artiste) + planning hebdomadaire.
 *
 * Amelia stocke ses données dans ses propres tables SQL et dans wp_options.
 * Compatible Amelia free 1.x / 2.x (détection des colonnes optionnelles).
 */

if ( ! defined( 'ABSPATH' ) ) {
    echo "Lancez via WP-CLI : wp eval-file configure-amelia.php\n";
    exit( 1 );
}

if ( ! defined( 'AMELIA_VERSION' ) && ! class_exists( '\AmeliaBooking\Plugin' ) ) {
    echo "Amelia n'est pas actif. Activez-le d'abord.\n";
    exit( 1 );
}

global $wpdb;
echo "> Configuration Amelia...\n";

$prefix = $wpdb->prefix . 'amelia_';
$site_name  = get_bloginfo( 'name' );
$admin_email = get_option( 'admin_email' );

// ── Vérification des tables ────────────────────────────────
$required_tables = [ 'categories', 'services', 'users', 'providers_to_services', 'providers_to_weekdays', 'providers_to_periods', 'providers_to_periods_services' ];
$missing = [];
foreach ( $required_tables as $t ) {
    $tbl = $prefix . $t;
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) ) !== $tbl ) {
        $missing[] = $tbl;
    }
}

if ( $missing && class_exists( '\AmeliaBooking\Infrastructure\WP\InstallActions\ActivationDatabaseHook' ) ) {
    echo "  Tables Amelia absentes, initialisation de la base Amelia...\n";
    \AmeliaBooking\Infrastructure\WP\InstallActions\ActivationDatabaseHook::init();

    if ( class_exists( '\AmeliaBooking\Infrastructure\WP\InstallActions\ActivationSettingsHook' ) ) {
        \AmeliaBooking\Infrastructure\WP\InstallActions\ActivationSettingsHook::init();
    }

    $missing = [];
    foreach ( $required_tables as $t ) {
        $tbl = $prefix . $t;
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) ) !== $tbl ) {
            $missing[] = $tbl;
        }
    }
}
if ( $missing ) {
    echo "  Tables Amelia manquantes : " . implode( ', ', $missing ) . "\n";
    echo "  Assurez-vous qu'Amelia est actif et que les tables ont été créées.\n";
    exit( 1 );
}

$amelia_settings_raw = get_option( 'amelia_settings', '' );
if (
    class_exists( '\AmeliaBooking\Infrastructure\WP\InstallActions\ActivationSettingsHook' )
    && ( '' === $amelia_settings_raw || '{}' === trim( (string) $amelia_settings_raw ) )
) {
    \AmeliaBooking\Infrastructure\WP\InstallActions\ActivationSettingsHook::init();
}

// ── 1. Paramètres globaux (wp_options) ────────────────────
$settings = [
    'general' => [
        'phoneDefaultCountryCode'                => 'FR',
        'phoneCountryCodeRequired'               => false,
        'defaultAppointmentStatus'               => 'approved',
        'minimumTimeRequirementPriorToBooking'   => 0,
        'minimumTimeRequirementPriorToCanceling' => 1440, // 24h avant
        'numberOfDaysAvailableForBooking'        => 180,
        'allowAdminBookingAtAnyTime'             => false,
        'requiredPhoneNumberField'               => false,
        'requiredEmailField'                     => true,
        'itemsPerPage'                           => 12,
        'appointmentsPerPage'                    => 25,
        'servicesPerPage'                        => 12,
        'showClientTimeZone'                     => false,
        'sendAllCF'                              => false,
    ],
    'payment' => [
        'currency'              => 'EUR',
        'symbol'                => '€',
        'priceSymbolPosition'   => 'right',
        'priceNumberOfDecimals' => 2,
        'priceSeparator'        => ',',
        'fullAmountPayOnSite'   => false, // Paiement non requis à la réservation (configurable)
        'depositEnabled'        => false,
        'onSite'                => true,  // Paiement sur place possible
        'stripe'  => [ 'enabled' => false, 'testMode' => false ],
        'payPal'  => [ 'enabled' => false ],
        'mollie'  => [ 'enabled' => false ],
        'wc'      => [ 'enabled' => false ],
    ],
    'notifications' => [
        'senderName'  => $site_name,
        'senderEmail' => $admin_email,
    ],
    'customization' => [
        'primaryColor'          => '#8B7355',
        'primaryGradientColor1' => '#8B7355',
        'primaryGradientColor2' => '#6B5840',
        'textColor'             => '#1A1814',
        'lightBgColor'          => '#FAF8F5',
    ],
    'roles' => [
        'allowWritingOnBufferTime' => false,
        'allowCustomerReschedule'  => true,
        'allowCustomerCancelForNoShow' => false,
        'customerCancellationRequirementPriorToAppointment' => 1440,
        'customerRescheduleRequirementPriorToAppointment'   => 1440,
    ],
    'appointments' => [
        'allowBookingIfNotMin' => false,
        'openedBookingAfterMin' => false,
    ],
];

$existing_settings_raw = get_option( 'amelia_settings', '{}' );
$existing_settings = is_string( $existing_settings_raw ) ? json_decode( $existing_settings_raw, true ) : $existing_settings_raw;
update_option( 'amelia_settings', wp_json_encode( array_replace_recursive( is_array( $existing_settings ) ? $existing_settings : [], $settings ) ) );
echo "  • Paramètres globaux sauvegardés (devise EUR, notifications activées)\n";

// ── 2. Catégorie ───────────────────────────────────────────
$cat_exists = $wpdb->get_var( "SELECT id FROM {$prefix}categories WHERE name = 'Prestations artistiques' LIMIT 1" );

if ( $cat_exists ) {
    $cat_id = (int) $cat_exists;
    echo "  • Catégorie 'Prestations artistiques' existe (ID: $cat_id)\n";
} else {
    $wpdb->insert(
        $prefix . 'categories',
        [
            'name'     => 'Prestations artistiques',
            'status'   => 'visible',
            'position' => 1,
        ],
        [ '%s', '%s', '%d' ]
    );
    $cat_id = $wpdb->insert_id;
    echo "  • Catégorie créée (ID: $cat_id)\n";
}

// ── 3. Services de démonstration ──────────────────────────
// Colonnes optionnelles (Amelia 2.x+)
$columns = $wpdb->get_col( "DESCRIBE {$prefix}services", 0 );

$services_data = [
    [
        'categoryId'  => $cat_id,
        'name'        => 'Cours particulier de dessin',
        'description' => 'Cours individuel adapté à votre niveau et vos envies. Techniques académiques ou expérimentales, portrait, nature morte, paysage. Matériel fourni.',
        'color'       => '#8B7355',
        'price'       => 65.00,
        'duration'    => 5400, // 1h30 en secondes
        'minCapacity' => 1,
        'maxCapacity' => 1,
    ],
    [
        'categoryId'  => $cat_id,
        'name'        => 'Atelier collectif (4 personnes max)',
        'description' => 'Séance de peinture en groupe dans l\'atelier de l\'artiste. Thème mensuel. Matériel et boisson offerts. Idéal pour débutants et amateurs.',
        'color'       => '#6B5840',
        'price'       => 40.00,
        'duration'    => 7200, // 2h
        'minCapacity' => 2,
        'maxCapacity' => 4,
    ],
    [
        'categoryId'  => $cat_id,
        'name'        => 'Visite privée de l\'atelier',
        'description' => 'Découverte de l\'atelier et des œuvres en cours, échanges sur la démarche artistique. Possibilité d\'achat direct. Sur rendez-vous.',
        'color'       => '#D4C4A8',
        'price'       => 0.00,  // Gratuit
        'duration'    => 3600,  // 1h
        'minCapacity' => 1,
        'maxCapacity' => 6,
    ],
    [
        'categoryId'  => $cat_id,
        'name'        => 'Coaching artistique',
        'description' => 'Accompagnement personnalisé pour développer votre pratique artistique : portfolio, technique, style, projets. Séance en atelier ou en visioconférence.',
        'color'       => '#4A5E4A',
        'price'       => 80.00,
        'duration'    => 3600,
        'minCapacity' => 1,
        'maxCapacity' => 1,
    ],
    [
        'categoryId'  => $cat_id,
        'name'        => 'Conférence histoire de l\'art',
        'description' => 'Conférence thématique sur mesure pour entreprises, associations, écoles. Durée et thème à définir ensemble. Devis sur demande.',
        'color'       => '#2C2822',
        'price'       => 150.00,
        'duration'    => 5400,
        'minCapacity' => 5,
        'maxCapacity' => 30,
    ],
];

$service_ids = [];
$service_payloads = [];
foreach ( $services_data as $svc ) {
    $existing_id = $wpdb->get_var(
        $wpdb->prepare( "SELECT id FROM {$prefix}services WHERE name = %s LIMIT 1", $svc['name'] )
    );

    if ( $existing_id ) {
        $existing_id = (int) $existing_id;
        $service_ids[] = $existing_id;
        $service_payloads[ $existing_id ] = $svc;
        echo "  • Service '{$svc['name']}' existe (ID: $existing_id)\n";
        continue;
    }

    $insert_data = [
        'categoryId'    => $svc['categoryId'],
        'name'          => $svc['name'],
        'description'   => $svc['description'],
        'color'         => $svc['color'],
        'price'         => $svc['price'],
        'status'        => 'visible',
        'duration'      => $svc['duration'],
        'minCapacity'   => $svc['minCapacity'],
        'maxCapacity'   => $svc['maxCapacity'],
        'timeBefore'    => 0,
        'timeAfter'     => 0,
        'bringingAnyone' => 0,
        'priority'      => 'least_expensive',
        'show'          => 1,
        'aggregatedPrice' => 1,
        'fullPayment'   => 0,
        'depositPayment' => 'percentage',
        'deposit'       => 50.00,
    ];

    // Colonnes optionnelles (Amelia 2.x)
    if ( in_array( 'position', $columns, true ) ) {
        $insert_data['position'] = 1;
    }
    if ( in_array( 'maxExtraPeople', $columns, true ) ) {
        $insert_data['maxExtraPeople'] = 0;
    }

    $formats_by_key = [
        'categoryId'      => '%d',
        'name'            => '%s',
        'description'     => '%s',
        'color'           => '%s',
        'price'           => '%f',
        'status'          => '%s',
        'duration'        => '%d',
        'minCapacity'     => '%d',
        'maxCapacity'     => '%d',
        'timeBefore'      => '%d',
        'timeAfter'       => '%d',
        'bringingAnyone'  => '%d',
        'priority'        => '%s',
        'show'            => '%d',
        'aggregatedPrice' => '%d',
        'fullPayment'     => '%d',
        'depositPayment'  => '%s',
        'deposit'         => '%f',
        'position'        => '%d',
        'maxExtraPeople'  => '%d',
    ];
    $formats = array_map( static fn ( string $key ): string => $formats_by_key[ $key ] ?? '%s', array_keys( $insert_data ) );

    $wpdb->insert( $prefix . 'services', $insert_data, $formats );
    $new_id = $wpdb->insert_id;
    $service_ids[] = $new_id;
    $service_payloads[ $new_id ] = $svc;
    $price_label = $svc['price'] > 0 ? number_format( $svc['price'], 0, ',', ' ' ) . ' €' : 'gratuit';
    echo "  • Service créé : '{$svc['name']}' ({$price_label}, " . ( $svc['duration'] / 60 ) . " min) ID: $new_id\n";
}

// ── 4. Employé (l'artiste) ────────────────────────────────
$admin = get_userdata( 1 );
$admin_first = $admin ? $admin->first_name ?: 'Djilali' : 'Djilali';
$admin_last  = $admin ? $admin->last_name  ?: 'Kadid' : 'Kadid';

$provider_id = $wpdb->get_var(
    $wpdb->prepare( "SELECT id FROM {$prefix}users WHERE email = %s AND type = 'provider' LIMIT 1", $admin_email )
);

if ( $provider_id ) {
    $provider_id = (int) $provider_id;
    echo "  • Employé/artiste existe (ID: $provider_id)\n";
} else {
    $user_cols = $wpdb->get_col( "DESCRIBE {$prefix}users", 0 );

    $user_data = [
        'firstName' => $admin_first,
        'lastName'  => $admin_last,
        'email'     => $admin_email,
        'status'    => 'visible',
        'type'      => 'provider',
        'note'      => 'Compte créé automatiquement via setup.',
    ];

    $user_formats = [ '%s', '%s', '%s', '%s', '%s', '%s' ];

    if ( in_array( 'countryPhoneIso', $user_cols, true ) ) {
        $user_data['countryPhoneIso'] = 'FR';
        $user_formats[] = '%s';
    }

    $wpdb->insert( $prefix . 'users', $user_data, $user_formats );
    $provider_id = $wpdb->insert_id;
    echo "  • Artiste/employé créé : $admin_first $admin_last (ID: $provider_id)\n";
}

// ── 5. Lier l'artiste aux services ────────────────────────
foreach ( $service_ids as $svc_id ) {
    $exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$prefix}providers_to_services WHERE userId = %d AND serviceId = %d",
            $provider_id, $svc_id
        )
    );
    if ( ! $exists ) {
        $svc_payload = $service_payloads[ $svc_id ] ?? [
            'price'       => 0,
            'minCapacity' => 1,
            'maxCapacity' => 1,
        ];

        $wpdb->insert(
            $prefix . 'providers_to_services',
            [
                'userId'        => $provider_id,
                'serviceId'     => $svc_id,
                'price'         => $svc_payload['price'],
                'minCapacity'   => $svc_payload['minCapacity'],
                'maxCapacity'   => $svc_payload['maxCapacity'],
                'customPricing' => null,
            ],
            [ '%d', '%d', '%f', '%d', '%d', '%s' ]
        );
    }
}
echo "  • Artiste assignée à " . count( $service_ids ) . " services\n";

// ── 6. Planning hebdomadaire (Mar–Sam, 10h–18h) ───────────
$schedule_exists = $wpdb->get_var(
    $wpdb->prepare( "SELECT COUNT(*) FROM {$prefix}providers_to_weekdays WHERE userId = %d", $provider_id )
);

if ( ! $schedule_exists ) {
    // Amelia : 1=Lun, 2=Mar, 3=Mer, 4=Jeu, 5=Ven, 6=Sam, 7=Dim
    $working_days = [ 2, 3, 4, 5, 6 ]; // Mardi à Samedi
    foreach ( $working_days as $day ) {
        $wpdb->insert(
            $prefix . 'providers_to_weekdays',
            [
                'userId'     => $provider_id,
                'dayIndex'   => $day,
                'startTime'  => '10:00:00',
                'endTime'    => '18:00:00',
            ],
            [ '%d', '%d', '%s', '%s' ]
        );

        $weekday_id = (int) $wpdb->insert_id;
        $wpdb->insert(
            $prefix . 'providers_to_periods',
            [
                'weekDayId'  => $weekday_id,
                'locationId' => null,
                'startTime'  => '10:00:00',
                'endTime'    => '18:00:00',
            ],
            [ '%d', '%s', '%s', '%s' ]
        );

        $period_id = (int) $wpdb->insert_id;
        foreach ( $service_ids as $svc_id ) {
            $wpdb->insert(
                $prefix . 'providers_to_periods_services',
                [
                    'periodId'  => $period_id,
                    'serviceId' => $svc_id,
                ],
                [ '%d', '%d' ]
            );
        }
    }
    echo "  • Planning : Mardi–Samedi 10h–18h\n";
} else {
    echo "  • Planning existe déjà ($schedule_exists jours configurés)\n";
}

echo "\nAmelia configuré.\n";
echo "  5 services créés dans la catégorie 'Prestations artistiques'\n";
echo "  Artiste : $admin_first $admin_last ($admin_email)\n";
echo "\n  Étapes manuelles (admin) :\n";
echo "  1. Amelia > Services : ajustez les vrais tarifs et durées\n";
echo "  2. Amelia > Employés : ajoutez photo et description de l'artiste\n";
echo "  3. Amelia > Réglages > Notifications : activez et personnalisez les emails\n";
echo "  4. Page Réservations : vérifiez que le shortcode [ameliabooking] s'affiche\n";
