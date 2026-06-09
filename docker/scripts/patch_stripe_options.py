#!/usr/bin/env python3
"""
Désactive Google Pay / Apple Pay / Payment Request dans WooCommerce Stripe,
corrige l'email admin, active le redirect direct vers checkout.
"""
import subprocess

CONTAINER = "djilali-kadid-db-1"
DB_USER   = "wordpress"
DB_PASS   = "Dji1ali_WP_2026!"
DB_NAME   = "wordpress"

def mysql_query(sql):
    r = subprocess.run(
        ["docker", "exec", CONTAINER, "mysql",
         f"-u{DB_USER}", f"-p{DB_PASS}",
         "--default-character-set=utf8mb4", DB_NAME,
         "-sN", "-e", sql],
        capture_output=True, text=True
    )
    return r.stdout.strip()

def mysql_exec(sql):
    r = subprocess.run(
        ["docker", "exec", "-i", CONTAINER, "mysql",
         f"-u{DB_USER}", f"-p{DB_PASS}",
         "--default-character-set=utf8mb4", DB_NAME],
        input=sql + "\n",
        capture_output=True, text=True
    )
    return r.returncode == 0

# 1. Désactiver les boutons express Stripe
EXPRESS = [
    "woocommerce_stripe_googlepay_settings",
    "woocommerce_stripe_applepay_settings",
    "woocommerce_stripe_payment_request_settings",
]
for name in EXPRESS:
    val = mysql_query(f"SELECT option_value FROM wp_options WHERE option_name='{name}'")
    new_val = val.replace('s:7:"enabled";s:3:"yes"', 's:7:"enabled";s:2:"no"')
    if new_val != val:
        ok = mysql_exec(f"UPDATE wp_options SET option_value='{new_val}' WHERE option_name='{name}'")
        print(f"{'✓' if ok else '✗'} Désactivé : {name}")
    else:
        print(f"- Déjà désactivé : {name}")

# 2. Email admin
mysql_exec("UPDATE wp_options SET option_value='djilali.kadid.galerie@gmail.com' WHERE option_name='admin_email'")
print("✓ admin_email mis à jour")

# 3. Redirect direct vers checkout après "Ajouter au panier"
mysql_exec("""
    INSERT INTO wp_options (option_name, option_value, autoload)
    VALUES ('woocommerce_cart_redirect_after_add', 'yes', 'yes')
    ON DUPLICATE KEY UPDATE option_value='yes'
""")
print("✓ Redirect vers checkout activé")

# 4. Email de nouvelle commande WooCommerce
val = mysql_query("SELECT option_value FROM wp_options WHERE option_name='woocommerce_email_new_order_settings'")
if val:
    import re
    new_val = re.sub(
        r's:9:"recipient";s:\d+:"[^"]*"',
        's:9:"recipient";s:34:"djilali.kadid.galerie@gmail.com"',
        val
    )
    if new_val != val:
        ok = mysql_exec(f"UPDATE wp_options SET option_value='{new_val}' WHERE option_name='woocommerce_email_new_order_settings'")
        print(f"{'✓' if ok else '✗'} Email nouvelle commande mis à jour")
    else:
        print("- Email nouvelle commande déjà configuré (ou champ absent)")
else:
    # Créer le setting
    setting = 'a:3:{s:7:"enabled";s:3:"yes";s:9:"recipient";s:34:"djilali.kadid.galerie@gmail.com";s:7:"subject";s:0:"";}'
    mysql_exec(f"INSERT INTO wp_options (option_name, option_value, autoload) VALUES ('woocommerce_email_new_order_settings', '{setting}', 'yes') ON DUPLICATE KEY UPDATE option_value='{setting}'")
    print("✓ Email nouvelle commande créé")

# Vérification finale
print("\n=== Vérification ===")
checks = [
    "admin_email",
    "woocommerce_cart_redirect_after_add",
    "woocommerce_stripe_googlepay_settings",
    "woocommerce_stripe_applepay_settings",
    "woocommerce_stripe_payment_request_settings",
    "woocommerce_email_new_order_settings",
]
for name in checks:
    val = mysql_query(f"SELECT SUBSTRING(option_value,1,80) FROM wp_options WHERE option_name='{name}'")
    print(f"  {name}: {val[:80]}")
