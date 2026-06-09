# Site artiste peintre et écosystème culturel WordPress/WooCommerce

Ce dépôt sert à construire un site WordPress complet pour une artiste peintre, avec une base évolutive pour vendre des œuvres, proposer des prestations culturelles, publier du contenu artistique et gérer les paiements/réservations.

Le projet ne doit pas être pensé comme une simple vitrine WordPress. L’objectif final est un écosystème artistique et culturel propriétaire : galerie, boutique, réservations, prestations, blog, SEO, facturation et évolutions futures possibles.

## Vision Produit

Le site doit permettre de centraliser :

- une présentation professionnelle de l’artiste ;
- une galerie d’œuvres originales ;
- la vente directe de tableaux, dessins, reproductions, livres ou produits numériques ;
- la vente de prestations culturelles : cours, conférences, coaching artistique, rédaction, interprétation musicale, visites d’atelier ;
- la réservation de créneaux et le paiement en ligne ;
- un blog artistique pour développer la visibilité Google ;
- une base bilingue FR/EN ;
- une administration simple pour une personne non technicienne.

À long terme, le site pourrait évoluer vers une galerie virtuelle, une académie artistique, une boutique culturelle, une plateforme de réservation, un magazine d’art ou même une marketplace d’artistes.

## État Actuel

Le projet est actuellement au stade **prototype local fonctionnel**.

Ce qui existe déjà :

- WordPress local créé avec Local by Flywheel ;
- WooCommerce installé et configuré ;
- thème Kadence + thème enfant `kadence-child` ;
- pages principales : Accueil, Galerie, Blog, À propos, Prestations, Contact, Mentions légales ; `Boutique` et `Réservations` restent en brouillon avec redirections ;
- Polylang installé pour le bilingue FR/EN ;
- Amelia installé et configuré avec 5 services de démonstration, un employé artiste et un planning mardi-samedi ;
- Rank Math installé pour le SEO ;
- Stripe préparé en mode test ;
- faux webhook local pour simuler les paiements ;
- WooCommerce PDF Invoices installé ;
- formulaire de contact Kadence opérationnel ;
- pages légales modèles créées : CGV, mentions légales, confidentialité, cookies ;
- bandeau cookies RGPD configuré ;
- WP Mail SMTP configuré pour Mailtrap en local, en attente de vrais identifiants Mailtrap ;
- interface admin simplifiée ;
- mode client dans l'admin avec raccourcis : ajouter/voir les oeuvres, ajouter/voir les articles, commandes, reservations Amelia, contact, voir le site ;
- contenu de démonstration : 6 œuvres fictives avec images générées ;
- script de test paiement validé : commande payée + stock décrémenté.

Sur cette machine, le prototype local est accessible ici :

```text
http://art-sell.local
http://art-sell.local/wp-admin
```

Identifiants de test local :

```text
admin
admin123
```

Important : dans Local, le site peut s’appeler `art-sell`, mais son domaine local généré est actuellement `art-sell.local`.

## Mise A Jour Recente - 8 juin 2026

Menu de navigation anglais cree :

- `setup/configure-en-menu.php` cree le menu "Menu principal (EN)" avec les items Gallery | Blog | Services | About | Contact.
- Les pages EN manquantes (Services, Legal Notice) sont creees automatiquement si absentes et liees aux pages FR via Polylang.
- Le menu est assigne a Polylang pour les emplacements `primary` et `mobile` EN.
- Redirections EN ajoutees : `/en/shop/` → `/en/gallery/` et `/en/bookings/` → `/en/services/`.
- Le switcher FR/EN dans la navbar reste gere par `functions.php` (aucun changement necessaire).

Commande a lancer une seule fois depuis le shell Local :

```bash
wp eval-file setup/configure-en-menu.php
```

Prerequis : `configure-polylang.php` et `configure-simplify-nav.php` doivent avoir ete joues avant.

## Mise A Jour Recente - 27 mai 2026

Les derniers changements importants sont maintenant :

- navigation publique simplifiee : `Galerie | Blog | A propos | Prestations | Contact` ;
- la page `Boutique` n'est plus dans le menu, reste en brouillon et redirige en 301 vers `/galerie/` ;
- la page `Reservations` n'est plus dans le menu, reste en brouillon et redirige en 301 vers `/prestations/` ;
- la page `Prestations` contient le shortcode Amelia `[ameliabooking]` pour reserver directement depuis cette page ;
- `Galerie` est maintenant la page boutique WooCommerce officielle ;
- l'ancien shortcode catalogue de la page Galerie a ete retire pour eviter le double affichage et le bloc "Showing all results" ;
- la boucle WooCommerce de Galerie est filtree pour n'afficher que les oeuvres originales, pas les prestations ;
- les images de Galerie utilisent une taille plus nette pour eviter les miniatures floues ;
- `Mode Client > Mes oeuvres` permet maintenant de supprimer une oeuvre, avec envoi dans la corbeille ;
- `Mode Client > Ajouter un article` et `Mode Client > Mes articles` ajoutent une interface blog simplifiee ;
- les articles peuvent etre crees, modifies, publies, mis en brouillon et envoyes dans la corbeille depuis le Mode Client ;
- `Mode Client > Ajouter une prestation` et `Mode Client > Mes prestations` ajoutent une interface simplifiee pour les cours, visites, conferences et services culturels ;
- un role WordPress dedie `Client Galerie` limite l'usage quotidien aux ecrans utiles ;
- le compte local de test `client / client123` est cree par `setup/configure-client-user.php` ;
- le guide client a ete mis a jour : `setup/CLIENT-GUIDE.md`.

Scripts/fichiers concernes :

- `setup/configure-simplify-nav.php`
- `setup/configure-client-user.php`
- `setup/kadence-child/functions.php`
- `setup/kadence-child/admin-simplify.php`
- `setup/CLIENT-GUIDE.md`

## Stack Actuelle

| Fonction | Outil retenu |
|---|---|
| CMS | WordPress |
| Boutique | WooCommerce |
| Paiement | Stripe via plugin WooCommerce |
| Réservations | Amelia |
| Bilingue | Polylang |
| SEO | Rank Math |
| Factures PDF | WooCommerce PDF Invoices & Packing Slips |
| Sécurité | Wordfence |
| Cache | WP Super Cache |
| Thème | Kadence Theme |
| Construction pages | Gutenberg + Kadence Blocks |
| Hébergement cible | o2switch |
| Environnement local | Local by Flywheel |

## Décisions Techniques

Le prompt initial demandait **Kadence + Gutenberg**, sans Elementor. Le projet actuel suit cette direction.

Une autre recommandation externe mentionne **Elementor** avec une formation WPChef. C’est une option possible plus tard, surtout si l’objectif devient un design très visuel et facile à éditer en drag-and-drop. Pour l’instant, ne mélangez pas Elementor avec Kadence/Gutenberg sans décision explicite, sinon le site deviendra plus lourd et plus difficile à maintenir.

Décision actuelle :

- garder Kadence + Gutenberg pour le prototype ;
- garder WooCommerce comme cœur e-commerce ;
- utiliser Amelia pour les réservations plutôt que coder un système maison ;
- éviter les plugins superflus ;
- garder l’admin simple pour une personne non technicienne ;
- ne pas utiliser Docker ou Node.js côté serveur ;
- rester compatible o2switch : PHP 8.1+, MySQL, Apache, WP-CLI, hébergement mutualisé.

## Périmètre Fonctionnel Final

### Artiste Peintre

Fonctions attendues :

- galerie d’œuvres ;
- fiches œuvres détaillées ;
- vente directe ;
- certificat d’authenticité ;
- statut disponible/vendu/réservé ;
- photos multiples ;
- dimensions, technique, année ;
- commande personnalisée ;
- impressions d’art ou reproductions.

### Boutique

Types de produits possibles :

- tableaux originaux ;
- dessins ;
- aquarelles ;
- reproductions ;
- livres ;
- produits numériques ;
- prestations culturelles ;
- cours particuliers ;
- conférences ;
- rédaction d’articles ou d’ouvrages ;
- interprétation vocale ou musicale.

WooCommerce doit gérer :

- produits physiques ;
- produits numériques ;
- prestations de service ;
- stocks ;
- paiements Stripe ;
- factures automatiques ;
- emails clients ;
- commandes et remboursements.

### Réservations

Amelia doit gérer :

- cours de dessin ;
- cours d’histoire de l’art ;
- visites d’atelier ;
- coaching artistique ;
- conférences privées ;
- rendez-vous culturels ;
- disponibilités ;
- paiement éventuel à la réservation.

### Blog et SEO

WordPress + Rank Math doivent servir à publier :

- actualités de l’artiste ;
- articles sur les œuvres ;
- articles d’histoire de l’art ;
- textes de démarche artistique ;
- contenus optimisés pour Google ;
- pages piliers SEO.

## Roadmap Conseillée

### Phase 1 - MVP Artiste Peintre

Objectif : avoir un site artiste crédible et vendable.

État local actuel : phase 1 lancée sur `http://art-sell.local`.

- Accueil, Galerie, Boutique, Réservations, À propos, Contact et pages juridiques de base créées.
- 6 œuvres de démonstration vendables via WooCommerce, avec prix, stock unique, image, technique, thème, format, dimensions, année et certificat.
- WooCommerce ouvert au public en local (`woocommerce_coming_soon=no`).
- Fiches œuvres et grille boutique enrichies dans le thème enfant Kadence.
- Test de paiement local validé avec le fake webhook : commande passée en payée et stock passé à 0.

Reste à faire avec les vraies informations :

- Finaliser design Accueil/Galerie/Boutique.
- Ajouter les vraies œuvres.
- Ajouter les vraies photos.
- Finaliser les fiches œuvres.
- Tester panier, commande, paiement Stripe test.
- Vérifier les emails de commande.
- Préparer mentions légales, CGV, confidentialité.

### Phase 2 - Prestations Culturelles

Objectif : transformer le site en écosystème culturel.

État local actuel : phase 2 créée en version fictive sur `http://art-sell.local`.

- Page `Prestations` ajoutée avec vue d'ensemble de l'offre culturelle.
- Pages dédiées créées : `Cours`, `Commande personnalisée`, `Conférences`, `Rédaction culturelle`, `Coaching artistique`.
- 6 prestations WooCommerce virtuelles créées dans la catégorie `Prestations culturelles`.
- Chaque prestation contient un prix fictif, une durée, un format, un public cible et une modalité de réservation.
- Page `Réservations` enrichie pour préparer la future configuration Amelia.
- Navigation principale mise à jour : Accueil, Galerie, Boutique, Prestations, Réservations, À propos, Contact.
- Boutique et Galerie filtrées sur les œuvres originales pour éviter de mélanger tableaux et services.

Reste à faire avec l'artiste :

- Définir les vraies prestations.
- Valider les vrais tarifs, durées, lieux, jauges et conditions d'annulation.
- Créer les services réels dans Amelia.
- Clarifier réservation seule vs réservation + paiement.
- Remplacer les textes fictifs par les textes validés.

### Phase 3 - Contenu et Visibilité

Objectif : préparer l’acquisition organique.

État local actuel : phase 3 créée en version fictive sur `http://art-sell.local`.

- Page `Blog` ajoutée à la navigation principale.
- 6 catégories éditoriales créées : Carnet d’atelier, Conseils collectionneurs, Techniques & matières, Histoire de l’art, Prestations culturelles, Actualités.
- 8 articles de démonstration créés avec images, extraits, catégories, tags et métas Rank Math.
- Accueil enrichi avec les derniers articles.
- Pages, produits, prestations et articles reçoivent des titres SEO, descriptions et mots-clés fictifs.
- `configure-rankmath.php` renforcé : sitemap pages/articles/produits, taxonomies utiles, robots.txt, titres et métas par défaut.
- Checklist SEO ajoutée : `setup/SEO-checklist.md`.

Reste à faire avec l’artiste :

- Remplacer les articles fictifs par des textes réels.
- Valider les mots-clés principaux et zones géographiques.
- Ajouter les vraies photos d’atelier et d’œuvres.
- Connecter Rank Math à Google Search Console.
- Soumettre le sitemap en production.
- Suivre les requêtes et améliorer les pages après indexation.

### Phase 3.5 - Stabilisation MVP Avant Livraison

Objectif : combler les trous fonctionnels visibles dans les 10 premières minutes de test.

État local actuel : phase 3.5 installée sur `http://art-sell.local`.

- Amelia est configuré avec 5 services fictifs : cours particulier, atelier collectif, visite d'atelier, coaching artistique, conférence.
- La page `Prestations` charge maintenant le shortcode Amelia ; `Réservations` reste en brouillon avec redirection vers `/prestations/`.
- La page `Contact` contient un formulaire Kadence avec email admin et réponse automatique.
- Les pages `CGV`, `Mentions légales`, `Politique de confidentialité` et `Politique de cookies` existent.
- WooCommerce pointe vers la page CGV et WordPress vers la politique de confidentialité.
- Le bandeau cookies est configuré avec le plugin Cookie Notice.
- Le menu Footer contient les liens légaux.
- `setup/STRIPE-TEST-CHECKLIST.md` documente le test Stripe complet dans un vrai navigateur.
- Le test fake webhook reste validé : commande en `processing`, stock à `0`.
- L'admin contient un `Mode Client` pensé pour une utilisatrice non technique.
- `Mode Client > Ajouter une oeuvre` remplace l'écran WooCommerce complexe par un formulaire guidé : photo, titre, prix, dimensions, technique, statut, description.
- `Mode Client > Mes oeuvres` affiche une liste simplifiée avec modification, aperçu, action "marquer vendue" et suppression vers la corbeille.
- `Mode Client > Ajouter un article` remplace l'écran WordPress complexe par un formulaire guidé : image, titre, catégorie, résumé, contenu, mots-clés.
- `Mode Client > Mes articles` affiche une liste simplifiée avec modification, aperçu et suppression vers la corbeille.
- `Mode Client > Ajouter une prestation` remplace l'écran WooCommerce complexe par un formulaire guidé : image, titre, prix, durée, format, public, réservation, description.
- `Mode Client > Mes prestations` affiche une liste simplifiée avec modification, aperçu et suppression vers la corbeille.
- Un rôle dédié `Client Galerie` peut être installé avec `wp eval-file setup/configure-client-user.php`.
- Un guide d'utilisation simple est disponible : `setup/CLIENT-GUIDE.md`.

Commande locale à relancer si besoin :

```bash
bash setup/setup-missing-features.sh
```

Points encore manuels avant mise en production :

- Remplacer tous les textes légaux modèles et variables entre crochets par les vraies informations.
- Ajouter les vrais identifiants Mailtrap dans `.env.local` pour tester les emails localement.
- En production, créer une vraie adresse email o2switch puis lancer la configuration SMTP.
- Dans Amelia, personnaliser les notifications de réservation, les vrais tarifs, les durées et les disponibilités.
- Tester Stripe dans le navigateur avec la checklist, pas seulement avec le fake webhook.

### Phase 4 - Production o2switch

Objectif : mettre en ligne proprement.

- Créer base MySQL dans cPanel.
- Remplir `.env`.
- Lancer `setup.sh` sur o2switch.
- Retirer le fake webhook.
- Passer Stripe en live.
- Configurer le vrai webhook Stripe.
- Configurer le SMTP réel avec `bash setup/setup-missing-features.sh --production`.
- Vérifier SSL, cache, sécurité.

### Phase 5 - Évolutions

Possibilités futures :

- newsletter ;
- billetterie ;
- cours vidéo ;
- espace membre ;
- vente de partitions/livres numériques ;
- marketplace d’artistes ;
- magazine culturel.

## Structure du Dossier

```text
setup/
├── setup.sh                    # Installation production o2switch via WP-CLI
├── setup-local.sh              # Installation locale pour Local by Flywheel
├── README-local.md             # Guide local détaillé
├── .env.example                # Variables production
├── .env.local                  # Variables local/dev
├── configure-woocommerce.php   # Boutique, devise, stock, expédition, emails
├── configure-polylang.php      # Langues FR/EN et pages traduites
├── configure-rankmath.php      # SEO, sitemap, robots.txt
├── SEO-checklist.md            # Checklist éditoriale et Search Console
├── configure-stripe-test.php   # Stripe test local
├── configure-mailtrap.php      # SMTP Mailtrap local
├── configure-amelia.php        # Services, employé et planning Amelia
├── configure-contact-form.php  # Formulaire de contact Kadence
├── configure-legal-pages.php   # CGV, mentions, confidentialité, cookies
├── configure-cookies.php       # Bandeau cookies RGPD
├── configure-smtp-production.php # SMTP o2switch
├── configure-client-user.php   # Role et compte client dedie
├── configure-en-menu.php       # Menu navigation anglais + pages EN manquantes
├── setup-missing-features.sh   # Orchestration features bloquantes
├── STRIPE-TEST-CHECKLIST.md    # Tests Stripe navigateur
├── CLIENT-GUIDE.md             # Mode d'emploi très simple pour la cliente
├── create-demo-content.php     # Contenu fictif pour visualiser le site
├── create-test-order.php       # Helper test commande WooCommerce
├── get-order-status.php        # Helper test statut commande WooCommerce
├── test-payment-flow.sh        # Test paiement automatisé
├── fake-webhook.php            # Faux webhook Stripe local, à retirer en prod
├── wp-config-template.php      # Modèle wp-config sécurisé
├── .htaccess                   # Apache/o2switch
└── kadence-child/
    ├── style.css
    ├── functions.php
    ├── admin-simplify.php
    └── woocommerce/
        └── single-product.php
```

## Utilisation Locale

Le site local a été préparé avec Local by Flywheel.

Chemin WordPress local sur cette machine :

```text
C:\Users\adama\Local Sites\art-sell\app\public
```

Pour relancer le setup local depuis le shell Local :

```bash
cd ~/Local\ Sites/art-sell/app/public
bash setup/setup-local.sh
```

Pour recréer le contenu de démonstration :

```bash
wp eval-file setup/create-demo-content.php
```

Pour tester le paiement simulé :

```bash
bash setup/test-payment-flow.sh
```

Pour installer ou relancer les features bloquantes du MVP :

```bash
bash setup/setup-missing-features.sh
```

Cela configure Amelia, le formulaire de contact, les pages légales, le bandeau cookies et les emails locaux via Mailtrap.

Le faux webhook local est disponible ici :

```text
http://art-sell.local/fake-webhook?token=test123
```

Attention : `fake-webhook.php` est strictement réservé au développement local. Il doit être supprimé avant toute mise en production.

## Installation Production o2switch

### 1. Créer la base de données

Dans cPanel :

1. ouvrir **MySQL Databases** ;
2. créer une base, par exemple `nomcpanel_galerie` ;
3. créer un utilisateur ;
4. associer l’utilisateur à la base avec tous les privilèges.

### 2. Configurer les variables

```bash
cp setup/.env.example setup/.env
nano setup/.env
```

Variables minimales :

| Variable | Exemple |
|---|---|
| `DB_NAME` | `nomcpanel_galerie` |
| `DB_USER` | `nomcpanel_user` |
| `DB_PASSWORD` | mot de passe fort |
| `WP_HOME` | `https://monsite.fr` |
| `ADMIN_USER` | `admin_galerie` |
| `ADMIN_PASSWORD` | mot de passe fort |
| `ADMIN_EMAIL` | `contact@monsite.fr` |
| `SITE_TITLE` | `Galerie Djilali Kadid` |

### 3. Uploader les fichiers

Via SSH :

```bash
rsync -avz setup/ votrelogin@monsite.fr:~/public_html/setup/
```

Ou via FTP : uploader `setup/` dans `public_html/`.

### 4. Lancer l’installation

```bash
ssh votrelogin@monsite.fr
cd ~/public_html
chmod +x setup/setup.sh
bash setup/setup.sh
wp config shuffle-salts
```

### 5. Finaliser manuellement

- Configurer Stripe live.
- Configurer le vrai webhook Stripe.
- Ajuster Amelia : vrais services, tarifs, durées, disponibilités, notifications.
- Configurer le SMTP o2switch :

```bash
bash setup/setup-missing-features.sh --production
```

- Connecter Rank Math à Google Search Console.
- Vérifier Polylang et les menus FR/EN.
- Vérifier les emails.
- Retirer tout outil local/dev.

## Ajouter une Œuvre

Pour une vente WooCommerce, créer l’œuvre comme **produit WooCommerce simple**.

Étapes :

1. Aller dans **Produits > Ajouter**.
2. Titre = nom de l’œuvre.
3. Image produit = photo principale.
4. Prix = prix de vente.
5. Inventaire : gérer le stock, quantité `1`.
6. Ajouter les métadonnées œuvre si disponibles : dimensions, année, certificat.
7. Ajouter les taxonomies : technique, format, thème.
8. Publier.

Note importante : le menu custom **Œuvres** existe dans le thème enfant, mais le panier/commande WooCommerce repose sur les **produits WooCommerce**. Pour vendre, privilégier les produits WooCommerce.

## Configuration Stripe

En local :

- utiliser uniquement des clés `pk_test_...` et `sk_test_...` ;
- ne jamais mettre de clés live dans `.env.local` ;
- le fake webhook permet de simuler `checkout.session.completed`.

En production :

- supprimer `wp-content/mu-plugins/fake-webhook.php` ;
- renseigner les clés live dans WooCommerce ;
- configurer le webhook public Stripe ;
- tester une petite transaction réelle si nécessaire.

Carte de test Stripe :

```text
4242 4242 4242 4242
12/34
123
```

## Règles Pour Collaborateurs et Générateurs de Code

Respecter ces règles quand vous reprenez le projet :

- Ne pas remplacer la stack sans décision explicite.
- Ne pas ajouter Elementor tant que le choix Kadence/Gutenberg reste actif.
- Ne pas ajouter Docker, Node.js serveur ou build complexe.
- Ne pas multiplier les plugins.
- Ne pas coder un système maison si WooCommerce/Amelia le font déjà correctement.
- Ne pas supprimer Polylang : le site cible est bilingue.
- Ne jamais garder `fake-webhook.php` en production.
- Ne jamais commiter de vraies clés Stripe, SMTP ou mots de passe.
- Garder l’administration simple pour une personne non technique.
- Favoriser WP-CLI pour les scripts d’installation/configuration.
- Garder la compatibilité o2switch mutualisé.
- Documenter toute décision structurante dans ce README.

## Audit Contenu — Corrections Avant Livraison

Audit réalisé en navigation réelle sur `http://art-sell.local` (mai 2026).
Le contenu demo a été généré automatiquement. Voici ce qui doit être corrigé avant de montrer le site à l'artiste ou à un tiers.

### Corrections urgentes (visibles par les visiteurs)

- **Nom pas entièrement remplacé** : l'admin affiche "Galerie Djilali Kadid" mais le hero de la page d'accueil et le `<title>` HTML affichent encore "Galerie Joelle". Corriger directement dans l'éditeur de la page Accueil.

- **Texte développeur visible sur la page Prestations** : plusieurs blocs ne sont pas destinés au public et doivent être supprimés de la page :
  - *"Cette première version présente l'écosystème culturel complet autour de l'artiste..."*
  - Section **LOGIQUE MÉTIER** entière
  - *"À remplacer plus tard — Durées, tarifs, conditions d'annulation..."*

- **Email fictif visible sur la page Contact** : l'adresse `dev-email@wpengine.local` s'affiche publiquement. À remplacer par la vraie adresse de contact de l'artiste.

- **Texte placeholder visible sur la page À propos** : la phrase *"Cette page pourra ensuite accueillir la biographie complète, les expositions, les influences et les photos de l'atelier."* est visible par les visiteurs. À supprimer ou remplacer.

### Incohérences structurelles

Note 27 mai 2026 : les points `Galerie/Boutique` et `Navigation : 8 items` ci-dessous ont ete traites. Le menu public final est `Galerie | Blog | Prestations | A propos | Contact`, `Boutique` redirige vers `/galerie/`, et `Reservations` redirige vers `/prestations/`.

Note 1 juin 2026 : la home MVP a ete simplifiee. La section intro affiche maintenant `Biographie courte` avec texte placeholder, la section oeuvres s'appelle `Galerie`, les trois blocs explicatifs ont ete retires, et la section services s'appelle `Prestations`. Les cartes de prestations affichent le titre directement et revelent la description au survol.

Note 1 juin 2026 : la page Galerie est passee en mode immersif. Le texte d'introduction en haut de page est retire, la grille utilise deux tres grandes colonnes sur desktop, une colonne sur mobile, et WooCommerce charge l'image `artwork-main` pour eviter d'agrandir des miniatures.

Note 1 juin 2026 : la typographie visuelle a ete harmonisee. Les titres sur images, cartes galerie, prestations, prix, fiches oeuvres et articles recents utilisent `Raleway`/`DM Sans`, dans l'esprit du titre de site en navbar. L'ancienne typo serif/italic n'est plus chargee.

Note 1 juin 2026 : la page A propos utilise temporairement du lorem ipsum plutot que du texte genere. Le bouton principal reprend le style epure des CTA de la home et pointe vers `/galerie/`.

Note 1 juin 2026 : le texte descriptif de la home est passe en lorem ipsum temporaire. Les mentions visibles et SEO liees a l'atelier ont ete retirees de l'accueil.

Note 1 juin 2026 : la page Contact a ete nettoyee. Les mentions liees a un lieu de travail physique ont ete retirees, les titres inline utilisent `Raleway`/`DM Sans`, et les metas SEO Contact ont ete mises a jour.

Note 2 juin 2026 : la page Prestations a ete harmonisee avec la Galerie et la home. Le contenu visible est en lorem ipsum temporaire, les cartes sont centrees avec grandes images, les titres restent visibles avant survol, et la description apparait au hover avec le meme style typographique moderne.

Note 2 juin 2026 : la page Galerie a ete corrigee apres verification visuelle. Comme Galerie est l'archive boutique WooCommerce, les styles doivent cibler `woocommerce-shop` directement et le `style.css` du theme enfant est maintenant charge apres `kadence-woocommerce.css`. Les oeuvres s'affichent en 3 grandes colonnes centrees sur desktop, puis 2 colonnes tablette et 1 colonne mobile.

- **Galerie et Boutique affichent exactement le même contenu** (les 6 mêmes œuvres, mêmes prix, mêmes boutons). La distinction n'existe que dans le texte d'intro. Décision à prendre : soit supprimer l'une des deux, soit les différencier (Galerie = vue artistique sans achat direct, Boutique = vue e-commerce). Voir aussi la section "Points À Clarifier".

- **Blog : 8 articles en base, 1 seul visible en frontend**. La page `/blog/` n'affiche qu'un article alors qu'il y en a 8 publiés. Vérifier dans Réglages → Lecture que la page blog est bien configurée, et le nombre d'articles par page.

- **8 articles sans auteur et sans catégorie assignée**. Tous les articles affichent "(aucun auteur ou autrice)" et aucune des catégories créées (Carnet d'atelier, Histoire de l'art, Techniques & matières, Conseils collectionneurs) n'est assignée. À corriger avant mise en ligne.

- **Djilali Kadid n'est jamais nommé dans le contenu** : la page À propos parle de "l'artiste" de manière générique. Le nom de l'artiste doit apparaître au moins dans le hero, dans le titre de page À propos, et dans la biographie.

- **Navigation : 8 items dont 2 redondants** (GALERIE et BOUTIQUE montrent le même contenu). À simplifier une fois la décision Galerie/Boutique prise.

### Contenu à remplacer intégralement

Le contenu démo a été généré automatiquement et n'a pas vocation à rester :

- Toutes les œuvres (titres, descriptions, images, dimensions, prix) → à remplacer par les vraies œuvres de Djilali Kadid.
- Toutes les prestations (titres, tarifs, durées, descriptions) → à valider avec l'artiste.
- Tous les articles de blog (8 articles fictifs) → à remplacer par de vrais textes, ou à supprimer et publier progressivement.
- La page À propos → à réécrire entièrement avec la vraie biographie.
- Les textes des pages légales (CGV, mentions, confidentialité) → les crochets `[...]` doivent être remplis avec les vraies informations (SIRET, adresse, etc.).

## Points À Clarifier

Avant la version finale, il faut trancher :

- Kadence/Gutenberg ou migration Elementor ?
- Amelia gratuit suffit-il ou faut-il Amelia Pro ?
- Besoin d’un module de devis ?
- Prestations vendues comme produits WooCommerce, services Amelia, ou les deux ?
- Besoin de PayPal en plus de Stripe ?
- Besoin d’une newsletter ?
- Besoin de cours vidéo ou espace membre ?
- Politique de livraison réelle : France, Europe, international ?
- TVA : artiste non assujettie ou assujettie ?
- Textes juridiques : CGV, confidentialité, cookies, mentions légales.

## Maintenance

Mises à jour :

```bash
wp core update
wp plugin update --all
wp theme update --all
```

Debug temporaire :

```bash
wp config set WP_DEBUG true --type=constant
tail -f wp-content/debug.log
wp config set WP_DEBUG false --type=constant
```

Sauvegardes :

- o2switch fournit JetBackup ;
- faire un export manuel de la base avant toute grosse modification ;
- sauvegarder `wp-content/uploads` avant migration.

## Formation et Montée en Compétence

Pour ce projet, il faut apprendre plus que WordPress seul :

- WordPress comme CMS ;
- WooCommerce pour produits physiques, numériques et services ;
- réservations avec Amelia ou Bookly ;
- SEO avec Rank Math ;
- logique de contenu culturel ;
- design éditorial/artiste ;
- paiement, facturation, livraison ;
- maintenance et sécurité.

Formation conseillée côté business WordPress/WooCommerce : WPChef.

Formation possible si priorité au design visuel et à Elementor : Dyma ou formation dédiée Elementor.

Décision actuelle du dépôt : continuer sur Kadence/Gutenberg jusqu’à décision contraire.
