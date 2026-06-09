# Checklist — Test Stripe complet dans le navigateur

À faire **dans le navigateur** sur `http://art-sell.local` après avoir configuré
Stripe en mode test (clés `pk_test_...` / `sk_test_...`).

---

## Prérequis

- [ ] Le site est démarré dans Local by Flywheel
- [ ] Stripe est configuré en mode test dans WooCommerce → Réglages → Paiements
- [ ] Mailtrap est configuré (voir configure-mailtrap.php)
- [ ] Au moins une œuvre est publiée avec un prix et stock = 1

---

## Scénario 1 — Achat réussi (carte valide)

### Étape 1 : Ajouter au panier
- [ ] Aller sur une fiche œuvre (ex: `/boutique/`)
- [ ] Cliquer **Ajouter au panier**
- [ ] Vérifier que le compteur du panier en header se met à jour
- [ ] Cliquer sur **Voir le panier** ou aller sur `/panier/`

### Étape 2 : Panier
- [ ] Le produit apparaît avec le bon prix
- [ ] La quantité est 1 (et ne peut pas dépasser 1 pour une œuvre unique)
- [ ] Les frais de livraison s'affichent
- [ ] Cliquer **Procéder au paiement**

### Étape 3 : Checkout
- [ ] La page `/commander/` se charge correctement
- [ ] Remplir les champs obligatoires :
  - Prénom, Nom : `Jean Test`
  - Email : `jean.test@example.com`
  - Pays : France
  - Adresse : `1 Rue de la Paix, 75001 Paris`
- [ ] La case **"J'accepte les CGV"** est présente (si configuré)
- [ ] Sélectionner **Stripe / Carte bancaire**

### Étape 4 : Paiement Stripe (iframe)
- [ ] Le formulaire Stripe s'affiche correctement (pas de JS error)
- [ ] Entrer la carte de test :
  ```
  Numéro : 4242 4242 4242 4242
  Expiration : 12/34
  CVV : 123
  Nom : Jean Test
  ```
- [ ] Cliquer **Passer la commande**

### Étape 5 : Confirmation
- [ ] Redirection vers la page de confirmation (`/commander/order-received/...`)
- [ ] Le numéro de commande s'affiche
- [ ] Le récapitulatif de la commande est correct
- [ ] Le message de succès est en français

### Étape 6 : Vérifications post-achat
- [ ] **Mailtrap** : email "Nouvelle commande" reçu côté admin
- [ ] **Mailtrap** : email de confirmation reçu côté client (jean.test@example.com)
- [ ] **WP-Admin → WooCommerce → Commandes** : commande en statut `En cours`
- [ ] **Stock** : l'œuvre est passée à 0 (visible sur la fiche produit)
- [ ] **Fiche œuvre** : le bouton "Ajouter au panier" est remplacé par "Rupture de stock" ou "Vendu"

---

## Scénario 2 — Carte refusée

- [ ] Reprendre depuis Étape 3 avec la carte :
  ```
  Numéro : 4000 0000 0000 0002
  Expiration : 12/34
  CVV : 123
  ```
- [ ] Vérifier qu'un message d'erreur clair s'affiche en français
- [ ] Vérifier que la commande n'est PAS créée (ou reste en "Pending")
- [ ] Vérifier que le stock n'a pas changé

---

## Scénario 3 — Authentification 3D Secure

- [ ] Reprendre avec la carte :
  ```
  Numéro : 4000 0025 0000 3155
  Expiration : 12/34
  CVV : 123
  ```
- [ ] La popup 3D Secure de Stripe s'affiche
- [ ] Cliquer **Complete** (test réussi) ou **Fail** (test échoué)
- [ ] Vérifier le comportement dans chaque cas

---

## Scénario 4 — Panier avec plusieurs produits

- [ ] Ajouter 2 œuvres différentes au panier
- [ ] Vérifier que le total est correct
- [ ] Passer commande → les 2 stocks doivent descendre à 0 après paiement

---

## Scénario 5 — Retrait sur place (livraison gratuite)

- [ ] Au checkout, sélectionner **Retrait sur place**
- [ ] Vérifier que le total se met à jour (frais = 0)
- [ ] Passer commande avec la carte `4242 4242 4242 4242`
- [ ] Vérifier la confirmation

---

## Scénario 6 — Email de confirmation d'expédition (manuel)

- [ ] WP-Admin → WooCommerce → Commandes → ouvrir une commande
- [ ] Changer le statut en **Expédiée**
- [ ] Cliquer sur **Régénérer les permissions de téléchargement** (si applicable)
- [ ] Sauvegarder → vérifier Mailtrap (email "Commande expédiée" au client)

---

## Scénario 7 — Remboursement

- [ ] WP-Admin → WooCommerce → Commandes → ouvrir une commande payée
- [ ] Cliquer **Rembourser**
- [ ] Entrer le montant
- [ ] Choisir **Rembourser via Stripe**
- [ ] Vérifier dans le dashboard Stripe test que le remboursement apparaît

---

## Tests d'interface

- [ ] La page Boutique affiche correctement les 6 œuvres
- [ ] Le filtre par technique fonctionne (si configuré)
- [ ] La fiche œuvre affiche : image principale, titre, technique, dimensions, année, prix, bouton
- [ ] La lightbox photo fonctionne (clic sur l'image principale)
- [ ] Les miniatures changent l'image principale
- [ ] Les œuvres similaires s'affichent en bas de fiche

---

## Résultat attendu

Tous les scénarios doivent passer avant de considérer le tunnel de paiement
comme prêt pour la mise en production.

---

## Cartes de test Stripe — Référence rapide

| Scénario | Numéro | Exp | CVV |
|---|---|---|---|
| Paiement réussi | `4242 4242 4242 4242` | `12/34` | `123` |
| Refus generic | `4000 0000 0000 0002` | `12/34` | `123` |
| 3DS requis | `4000 0025 0000 3155` | `12/34` | `123` |
| Fonds insuffisants | `4000 0000 0000 9995` | `12/34` | `123` |
| Erreur traitement | `4000 0000 0000 0119` | `12/34` | `123` |
| Carte expirée | `4000 0000 0000 0069` | `12/34` | `123` |

Référence complète : [stripe.com/docs/testing](https://stripe.com/docs/testing)
