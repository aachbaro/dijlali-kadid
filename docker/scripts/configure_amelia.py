#!/usr/bin/env python3
"""
Configure Amelia pour la galerie Djilali Kadid :
  1. Met à jour l'email du provider (Djilali)
  2. Met à jour les settings Amelia (sender, company, devise)
  3. Traduit les notifications en français (client + provider)
  4. Met à jour la page Prestations (intro réelle + booking form)
  5. Assigne les services à la catégorie "Prestations artistiques"
"""
import subprocess, json, re

CONTAINER_WP = "djilali-kadid-wordpress-1"
CONTAINER_DB = "djilali-kadid-db-1"
WP_PATH      = "/var/www/html"

GALLERY_EMAIL = "djilali.kadid.galerie@gmail.com"
GALLERY_NAME  = "Galerie Djilali Kadid"
GALLERY_URL   = "https://galerie-djilali-kadid.com"

# ─── Helpers ──────────────────────────────────────────────────────────────────
def wp(cmd):
    r = subprocess.run(
        f"docker exec {CONTAINER_WP} wp --allow-root --path={WP_PATH} {cmd}",
        shell=True, capture_output=True, text=True)
    return r.stdout.strip()

def mysql(sql):
    r = subprocess.run(
        ["docker", "exec", CONTAINER_DB, "mysql",
         "-uwordpress", "-pDji1ali_WP_2026!",
         "--default-character-set=utf8mb4", "wordpress",
         "-sN", "-e", sql],
        capture_output=True, text=True)
    return r.stdout.strip()

def mysql_exec(sql):
    r = subprocess.run(
        ["docker", "exec", "-i", CONTAINER_DB, "mysql",
         "-uwordpress", "-pDji1ali_WP_2026!",
         "--default-character-set=utf8mb4", "wordpress"],
        input=sql + "\n", capture_output=True, text=True)
    return r.returncode == 0

def eval_file(php_code):
    import tempfile, os
    with tempfile.NamedTemporaryFile(mode='w', suffix='.php', delete=False, encoding='utf-8') as f:
        f.write('<?php\n' + php_code)
        fname = f.name
    subprocess.run(f"docker cp {fname} {CONTAINER_WP}:/tmp/_amelia_eval.php", shell=True)
    result = wp("eval-file /tmp/_amelia_eval.php")
    os.unlink(fname)
    return result


# ═══════════════════════════════════════════════════════════════════════════════
print("═══ 1. Email du provider Djilali Kadid ═══")
# ═══════════════════════════════════════════════════════════════════════════════
ok = mysql_exec(f"""
UPDATE wp_amelia_users
SET email='{GALLERY_EMAIL}'
WHERE id=1 AND type='provider';
""")
print(f"  Email provider : {'OK' if ok else 'ECHEC'}")


# ═══════════════════════════════════════════════════════════════════════════════
print("\n═══ 2. Settings Amelia (sender, company, devise) ═══")
# ═══════════════════════════════════════════════════════════════════════════════
raw = mysql("SELECT option_value FROM wp_options WHERE option_name='amelia_settings';")
settings = json.loads(raw)

# Notifications sender
settings["notifications"]["senderName"]  = GALLERY_NAME
settings["notifications"]["senderEmail"] = GALLERY_EMAIL
settings["notifications"]["mailService"] = ""   # utilise le mailer WP (Brevo via notre hook)
settings["notifications"]["replyTo"]     = ""

# Company info
settings["company"]["name"]    = GALLERY_NAME
settings["company"]["website"] = GALLERY_URL
settings["company"]["phone"]   = ""
settings["company"]["address"] = "Meulan-en-Yvelines, France"

# Devise (déjà EUR dans payment mais pas dans general payments)
if "payments" in settings:
    settings["payments"]["currency"] = "EUR"
    settings["payments"]["symbol"]   = "€"

# Supprimer le backlink Amelia (on est en version gratuite, il reste visible côté front
# mais on peut désactiver le label dans les settings)
if "backLink" in settings.get("general", {}):
    settings["general"]["backLink"]["enabled"] = False

new_val = json.dumps(settings, ensure_ascii=False)
ok = mysql_exec(f"UPDATE wp_options SET option_value=%s WHERE option_name='amelia_settings';"
                .replace("%s", "'" + new_val.replace("\\", "\\\\").replace("'", "\\'") + "'"))
print(f"  Settings : {'OK' if ok else 'ECHEC'}")


# ═══════════════════════════════════════════════════════════════════════════════
print("\n═══ 3. Notifications email en français ═══")
# ═══════════════════════════════════════════════════════════════════════════════

ADMIN_URL = f"{GALLERY_URL}/wp-admin/admin.php?page=wpamelia-appointments"

NOTIFS = [
    # ── CLIENT ──
    {
        "id": 1,  # customer_appointment_approved
        "subject": "Votre réservation est confirmée — %service_name%",
        "content": (
            "Bonjour <strong>%customer_full_name%</strong>,<br><br>"
            "Votre réservation pour <strong>%service_name%</strong> avec "
            "<strong>%employee_full_name%</strong> est confirmée.<br><br>"
            "<strong>Date&nbsp;:</strong> %appointment_date%<br>"
            "<strong>Heure&nbsp;:</strong> %appointment_start_time%<br>"
            "<strong>Durée&nbsp;:</strong> %appointment_duration%<br><br>"
            "Pour modifier ou annuler, contactez-nous au moins 24h à l'avance.<br><br>"
            "À très bientôt,<br>"
            f"<strong>{GALLERY_NAME}</strong><br>"
            f'<a href="{GALLERY_URL}">{GALLERY_URL}</a>'
        ),
    },
    {
        "id": 2,  # customer_appointment_pending
        "subject": "Votre demande de réservation est bien reçue — %service_name%",
        "content": (
            "Bonjour <strong>%customer_full_name%</strong>,<br><br>"
            "Votre demande de réservation pour <strong>%service_name%</strong> avec "
            "<strong>%employee_full_name%</strong> a bien été reçue et est en attente de confirmation.<br><br>"
            "<strong>Date souhaitée&nbsp;:</strong> %appointment_date%<br>"
            "<strong>Heure&nbsp;:</strong> %appointment_start_time%<br><br>"
            "Vous recevrez un email de confirmation dès validation de votre créneau.<br><br>"
            "Cordialement,<br>"
            f"<strong>{GALLERY_NAME}</strong>"
        ),
    },
    {
        "id": 4,  # customer_appointment_canceled
        "subject": "Votre réservation a été annulée — %service_name%",
        "content": (
            "Bonjour <strong>%customer_full_name%</strong>,<br><br>"
            "Votre réservation pour <strong>%service_name%</strong> du <strong>%appointment_date%</strong> "
            "à <strong>%appointment_start_time%</strong> a été annulée.<br><br>"
            "Pour toute question ou pour reprendre un rendez-vous, n'hésitez pas à nous contacter.<br><br>"
            "Cordialement,<br>"
            f"<strong>{GALLERY_NAME}</strong>"
        ),
    },
    {
        "id": 5,  # customer_appointment_rescheduled
        "subject": "Votre réservation a été modifiée — %service_name%",
        "content": (
            "Bonjour <strong>%customer_full_name%</strong>,<br><br>"
            "Votre réservation pour <strong>%service_name%</strong> a été reportée.<br><br>"
            "<strong>Nouvelle date&nbsp;:</strong> %appointment_date%<br>"
            "<strong>Nouvelle heure&nbsp;:</strong> %appointment_start_time%<br><br>"
            "Cordialement,<br>"
            f"<strong>{GALLERY_NAME}</strong>"
        ),
    },
    {
        "id": 6,  # customer_appointment_next_day_reminder
        "subject": "Rappel — votre rendez-vous demain : %service_name%",
        "content": (
            "Bonjour <strong>%customer_full_name%</strong>,<br><br>"
            "Rappel&nbsp;: votre rendez-vous pour <strong>%service_name%</strong> "
            "a lieu <strong>demain</strong>.<br><br>"
            "<strong>Date&nbsp;:</strong> %appointment_date%<br>"
            "<strong>Heure&nbsp;:</strong> %appointment_start_time%<br><br>"
            "À demain !<br>"
            f"<strong>{GALLERY_NAME}</strong>"
        ),
    },
    # ── PROVIDER (Djilali) ──
    {
        "id": 9,  # provider_appointment_approved
        "subject": "Nouvelle réservation confirmée — %service_name%",
        "content": (
            "Bonjour <strong>%employee_full_name%</strong>,<br><br>"
            "Nouvelle réservation confirmée pour <strong>%service_name%</strong>.<br><br>"
            "<strong>Client&nbsp;:</strong> %customer_full_name%<br>"
            "<strong>Email&nbsp;:</strong> %customer_email%<br>"
            "<strong>Téléphone&nbsp;:</strong> %customer_phone%<br>"
            "<strong>Date&nbsp;:</strong> %appointment_date%<br>"
            "<strong>Heure&nbsp;:</strong> %appointment_start_time%<br>"
            "<strong>Durée&nbsp;:</strong> %appointment_duration%<br><br>"
            f'<a href="{ADMIN_URL}">Voir toutes les réservations</a>'
        ),
    },
    {
        "id": 10,  # provider_appointment_pending
        "subject": "Nouvelle demande de réservation — %service_name%",
        "content": (
            "Bonjour <strong>%employee_full_name%</strong>,<br><br>"
            "Nouvelle demande de réservation reçue pour <strong>%service_name%</strong>.<br><br>"
            "<strong>Client&nbsp;:</strong> %customer_full_name%<br>"
            "<strong>Email&nbsp;:</strong> %customer_email%<br>"
            "<strong>Date souhaitée&nbsp;:</strong> %appointment_date%<br>"
            "<strong>Heure&nbsp;:</strong> %appointment_start_time%<br><br>"
            f'<a href="{ADMIN_URL}">Confirmer ou refuser la demande</a>'
        ),
    },
    {
        "id": 12,  # provider_appointment_canceled
        "subject": "Réservation annulée — %service_name%",
        "content": (
            "Bonjour <strong>%employee_full_name%</strong>,<br><br>"
            "La réservation de <strong>%customer_full_name%</strong> pour <strong>%service_name%</strong> "
            "du <strong>%appointment_date%</strong> à <strong>%appointment_start_time%</strong> "
            "a été annulée.<br><br>"
            f'<a href="{ADMIN_URL}">Voir les réservations</a>'
        ),
    },
    {
        "id": 14,  # provider_appointment_next_day_reminder
        "subject": "Rappel — réservation demain : %service_name%",
        "content": (
            "Bonjour <strong>%employee_full_name%</strong>,<br><br>"
            "Rappel&nbsp;: vous avez une réservation demain pour <strong>%service_name%</strong>.<br><br>"
            "<strong>Client&nbsp;:</strong> %customer_full_name%<br>"
            "<strong>Date&nbsp;:</strong> %appointment_date%<br>"
            "<strong>Heure&nbsp;:</strong> %appointment_start_time%<br><br>"
            f'<a href="{ADMIN_URL}">Voir les réservations</a>'
        ),
    },
]

for n in NOTIFS:
    # Échapper les apostrophes pour MySQL
    subject = n["subject"].replace("'", "\\'")
    content = n["content"].replace("'", "\\'")
    ok = mysql_exec(
        f"UPDATE wp_amelia_notifications SET subject='{subject}', content='{content}' WHERE id={n['id']};"
    )
    print(f"  Notif {n['id']:2d} : {'OK' if ok else 'ECHEC'}")


# ═══════════════════════════════════════════════════════════════════════════════
print("\n═══ 4. Services → catégorie 'Prestations artistiques' (ID 2) ═══")
# ═══════════════════════════════════════════════════════════════════════════════
ok = mysql_exec("UPDATE wp_amelia_services SET categoryId=2 WHERE categoryId IS NULL OR categoryId=1;")
print(f"  Catégorie services : {'OK' if ok else 'ECHEC'}")


# ═══════════════════════════════════════════════════════════════════════════════
print("\n═══ 5. Mise à jour page Prestations (ID 86) ═══")
# ═══════════════════════════════════════════════════════════════════════════════

PRESTATIONS_CONTENT = """<section class="mvp-section mvp-section--intro">
    <p class="mvp-kicker">Prestations</p>
    <h1>Prestations artistiques</h1>
    <p>Cours de dessin, visites privées de l'atelier, conférences sur l'histoire de l'art&nbsp;: Djilali Kadid partage son savoir-faire et sa passion pour les arts plastiques. Chaque prestation est adaptée à votre niveau et à vos objectifs.</p>
</section>
<section class="mvp-section mvp-prestations-grid">
    [products category="prestations-culturelles" limit="6" columns="3" orderby="date" order="ASC"]
</section>
<section class="mvp-section">
    <div class="mvp-section__head">
        <div>
            <p class="mvp-kicker">Réservation</p>
            <h2>Choisissez votre créneau</h2>
        </div>
    </div>
    <p style="color:var(--color-text-muted);margin-bottom:2rem;">Sélectionnez une prestation, choisissez la date et l'heure qui vous conviennent, et recevez une confirmation par email. Le règlement s'effectue sur place le jour du rendez-vous.</p>
    [ameliabooking]
</section>
<section class="mvp-section mvp-split" style="margin-top:3rem;">
    <div>
        <h2>Conditions</h2>
        <p>Annulation ou report possible jusqu'à <strong>24h avant</strong> le rendez-vous. Pour toute demande spécifique (groupe, déplacement, conférence sur mesure), utilisez le formulaire de contact.</p>
        <p><a class="button mvp-page-button" href="https://galerie-djilali-kadid.com/contact/">Envoyer un message</a></p>
    </div>
    <div>
        <h2>Horaires d'accueil</h2>
        <p>Du mardi au samedi, de 10h à 18h.<br>Sur rendez-vous uniquement.</p>
    </div>
</section>"""

result = eval_file(f"""
$content = {json.dumps(PRESTATIONS_CONTENT, ensure_ascii=False)};
wp_update_post(['ID' => 86, 'post_content' => $content]);
echo 'OK';
""")
print(f"  Page Prestations mise à jour : {result}")


# ═══════════════════════════════════════════════════════════════════════════════
print("\n═══ 6. Vérification finale ═══")
# ═══════════════════════════════════════════════════════════════════════════════
email_check = mysql("SELECT email FROM wp_amelia_users WHERE id=1;")
print(f"  Email provider : {email_check}")

sender_raw = mysql("SELECT option_value FROM wp_options WHERE option_name='amelia_settings';")
sender_settings = json.loads(sender_raw)
print(f"  Sender name    : {sender_settings['notifications']['senderName']}")
print(f"  Sender email   : {sender_settings['notifications']['senderEmail']}")
print(f"  Company name   : {sender_settings['company']['name']}")

notif_check = mysql("SELECT id, SUBSTRING(subject,1,60) FROM wp_amelia_notifications WHERE type='email' AND id IN (1,9);")
for line in notif_check.splitlines():
    print(f"  Notif {line}")

print("\n✓ Configuration Amelia terminée.")
