# Setup local — Local by Flywheel

Guide pour tester le site complet en local avant de commander l'hébergement.

**Temps estimé : 30–45 minutes** (dont ~15 min de téléchargements de plugins)

---

## Prérequis à installer

| Outil | Où | Pourquoi |
|---|---|---|
| [Local by Flywheel](https://localwp.com) | localwp.com | WordPress local sans configuration serveur |
| Compte [Stripe](https://stripe.com) (gratuit) | stripe.com | Clés test pour simuler les paiements |
| Compte [Mailtrap](https://mailtrap.io) (gratuit) | mailtrap.io | Intercepter les emails WordPress en local |

---

## Étapes dans l'ordre

### 1. Installer Local by Flywheel

Téléchargez et installez depuis [localwp.com](https://localwp.com).

### 2. Créer le site dans Local

1. Ouvrez Local → bouton **+** (nouveau site)
2. Nom du site : `art-sell`
3. Environnement : **Preferred** (Apache + PHP 8.1 + MySQL)
4. Identifiants admin : laissez les valeurs par défaut ou notez-les
5. Cliquez **Start Site**

Local crée automatiquement :
- La base de données (`local` / `root` / `root`)
- Le wp-config.php
- L'installation WordPress de base

Le site tourne sur `http://art-sell.local`.

### 3. Récupérer les clés Stripe test

1. Créez un compte sur [stripe.com](https://stripe.com) (gratuit, pas de CB requise)
2. Dans le dashboard Stripe : activez le **mode test** (toggle en haut à droite)
3. Allez dans **Développeurs → Clés API**
4. Copiez :
   - **Clé publique** : `pk_test_...`
   - **Clé secrète** : `sk_test_...`

### 4. Récupérer les credentials Mailtrap

1. Créez un compte sur [mailtrap.io](https://mailtrap.io) (gratuit)
2. Allez dans **Email Testing → Inboxes → My Inbox**
3. Cliquez sur l'inbox → onglet **SMTP Settings**
4. Sélectionnez l'intégration **SMTP** dans la liste déroulante
5. Copiez **Username** et **Password**

### 5. Ouvrir le shell Local

Dans l'app Local : sélectionnez votre site → bouton **Open Shell** (icône terminal).

Vous êtes automatiquement dans le bon dossier WordPress.

### 6. Uploader les fichiers setup/

Copiez le dossier `setup/` dans le dossier `public_html/` de votre site Local.

**Trouver public_html :**
- Dans Local : clic droit sur le site → **Open site folder**
- Naviguez dans `app/public/`
- Collez le dossier `setup/` ici

**Ou via le shell :**
```bash
# Depuis votre machine, si le dossier setup/ est sur le bureau par exemple
cp -r ~/Desktop/setup/ ~/Local\ Sites/art-sell/app/public/setup/
```

### 7. Configurer .env.local

Dans le shell Local :
```bash
# Vérifier qu'on est au bon endroit
pwd   # doit afficher quelque chose comme .../art-sell/app/public

# Le fichier est déjà là si vous avez copié setup/
ls setup/.env.local
```

Ouvrez `setup/.env.local` et remplissez :

```bash
# Éditeur dans le shell Local
nano setup/.env.local
```

Valeurs à remplacer :
```
STRIPE_TEST_PUBLIC_KEY=pk_test_VOTRE_CLE_ICI
STRIPE_TEST_SECRET_KEY=sk_test_VOTRE_CLE_ICI
MAILTRAP_USER=VOTRE_USER_MAILTRAP
MAILTRAP_PASSWORD=VOTRE_PASSWORD_MAILTRAP
```

Sauvegardez : `Ctrl+O` → `Entrée` → `Ctrl+X`

### 8. Lancer le setup

```bash
bash setup/setup-local.sh
```

Ce script installe tous les plugins, configure WooCommerce, Polylang, Rank Math,
Stripe en mode test, Mailtrap, et place le fake webhook dans mu-plugins.

Durée : 5–10 minutes. Suivez les messages dans le terminal.

### 9. Ouvrir l'admin WordPress

Allez sur `http://art-sell.local/wp-admin`

Identifiants (depuis Local ou votre .env.local) :
- Login : `admin`
- Mot de passe : `admin123`

### 10. Configurer Stripe manuellement

Même avec le script, la configuration Stripe finale se fait dans l'admin :

1. **WooCommerce → Réglages → Paiements**
2. Activez **Stripe** (ou "Stripe Credit Card")
3. Cliquez **Gérer** / **Configurer**
4. Cochez **Mode test**
5. Collez vos clés `pk_test_...` et `sk_test_...`
6. Sauvegardez

### 11. Vérifier les emails Mailtrap

1. Allez sur [mailtrap.io](https://mailtrap.io) → **Email Testing → Inboxes**
2. Vous devriez voir un email de test envoyé par le script
3. Tous les emails WordPress (commandes, confirmations…) arriveront ici

### 12. Tester le tunnel de paiement

```bash
bash setup/test-payment-flow.sh
```

Ce script automatisé crée une œuvre, une commande, déclenche le fake webhook,
et vérifie que tout fonctionne. Résultat attendu : 5 ✅.

### 13. Test manuel complet

1. Allez sur `http://art-sell.local`
2. **Œuvres → Ajouter** : créez une vraie œuvre avec image et prix
3. Allez sur la page de l'œuvre → **Ajouter au panier**
4. **Panier → Commander** → remplissez l'adresse
5. Paiement avec la carte test Stripe :
   - Numéro : `4242 4242 4242 4242`
   - Expiration : `12/34` (n'importe quelle date future)
   - CVV : `123`
6. Si Stripe est en mode test mais que le vrai webhook ne peut pas être appelé,
   déclenchez le fake webhook manuellement (voir ci-dessous)

**Fake webhook manuel :**
```bash
# Récupérez l'ID de la commande dans wp-admin → Commandes
ORDER_ID=42   # remplacez par votre ID

curl -X POST "http://art-sell.local/fake-webhook?token=test123" \
     -H "Content-Type: application/json" \
     -d "{\"type\":\"checkout.session.completed\",\"data\":{\"object\":{\"payment_status\":\"paid\",\"metadata\":{\"order_id\":\"$ORDER_ID\"}}}}"
```

---

## Cartes de test Stripe

| Scénario | Numéro | Expiration | CVV |
|---|---|---|---|
| Paiement réussi | `4242 4242 4242 4242` | `12/34` | `123` |
| Paiement refusé | `4000 0000 0000 0002` | `12/34` | `123` |
| Authentification 3D Secure | `4000 0025 0000 3155` | `12/34` | `123` |
| Fonds insuffisants | `4000 0000 0000 9995` | `12/34` | `123` |

---

## Débogage

### Voir les logs WordPress
```bash
tail -f wp-content/debug.log
```

### Voir les logs du fake webhook
```bash
tail -f wp-content/fake-webhook.log
```

### Reconfigurer Stripe uniquement
```bash
wp eval-file setup/configure-stripe-test.php
```

### Reconfigurer Mailtrap uniquement
```bash
wp eval-file setup/configure-mailtrap.php
```

### Forcer la mise à jour du fake webhook
```bash
cp setup/fake-webhook.php wp-content/mu-plugins/fake-webhook.php
wp rewrite flush --hard
```

### Tester le webhook manuellement
```bash
curl -v -X POST "http://art-sell.local/fake-webhook?token=test123" \
     -H "Content-Type: application/json" \
     -d '{"type":"checkout.session.completed","data":{"object":{"payment_status":"paid","metadata":{"order_id":"1"}}}}'
```

---

## Avant la mise en production

Checklist à faire **avant** de déployer sur o2switch :

- [ ] Retirer `wp-content/mu-plugins/fake-webhook.php`
- [ ] Passer Stripe en mode **live** avec les vraies clés `pk_live_...` / `sk_live_...`
- [ ] Configurer le vrai webhook Stripe (URL publique requise)
- [ ] Remplacer Mailtrap par le SMTP réel (ou désactiver wp-mail-smtp)
- [ ] Désactiver `WP_DEBUG` dans wp-config
- [ ] Suivre les étapes du `README.md` principal pour le déploiement o2switch

---

## Structure des fichiers locaux

```
setup/
├── README-local.md              ← Ce fichier
├── setup-local.sh               ← Script d'installation local
├── .env.local                   ← Variables de dev (ne pas commiter)
├── configure-stripe-test.php    ← Stripe mode test
├── configure-mailtrap.php       ← Emails locaux via Mailtrap
├── test-payment-flow.sh         ← Test automatisé bout en bout
└── fake-webhook.php             ← Faux webhook (⚠️ dev seulement)
```
