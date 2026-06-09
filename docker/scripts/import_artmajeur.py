#!/usr/bin/env python3
"""
Importe les vraies œuvres de Djilali Kadid depuis Artmajeur
dans WooCommerce (remplace les produits de démo).

Étapes :
  1. Télécharge les images HD depuis medias.artmajeur.com
  2. Les importe dans la médiathèque WordPress (wp media import)
  3. Met à jour / crée les produits WooCommerce
"""
import subprocess, json, os, sys, tempfile

CONTAINER_WP = "djilali-kadid-wordpress-1"
WP_PATH      = "/var/www/html"
TMP_DIR      = "/var/www/html/wp-content/uploads/artmajeur_tmp"

# ─── Données extraites d'Artmajeur ────────────────────────────────────────────
ARTWORKS = [
    {
        "title":  "Souvenir de Bruges",
        "medium": "Huile sur Carton",
        "dims":   "50x70 cm",
        "status": "disponible",   # → stock = 1
        "price":  565,
        "img":    "https://medias.artmajeur.com/master/7191532_20200111-141248.jpg?v=1739551152",
        "desc":   "Huile sur Carton, 50×70 cm.",
    },
    {
        "title":  "Rio San Barnaba",
        "medium": "Huile sur Toile",
        "dims":   "72x40 cm",
        "status": "disponible",
        "price":  200,
        "img":    "https://medias.artmajeur.com/master/7191535_20200111-133216.jpg?v=1739540819",
        "desc":   "Huile sur Toile, 72×40 cm.",
    },
    {
        "title":  "Vue d'Venise au couchant",
        "medium": "Huile",
        "dims":   "65x85 cm",
        "status": "vendu",        # → stock = 0
        "price":  480,
        "img":    "https://medias.artmajeur.com/master/7191682_vue-de-venise-au-couchant-gouache-sur-carton-gris-2013.jpg?v=1739543529",
        "desc":   "Huile, 65×85 cm.",
    },
    {
        "title":  "Gondole et reflet",
        "medium": "Tempera sur Toile de lin",
        "dims":   "71x58 cm",
        "status": "vendu",
        "price":  520,
        "img":    "https://medias.artmajeur.com/master/7193068_img-1741.jpg?v=1739551088",
        "desc":   "Tempera sur Toile de lin, 71×58 cm.",
    },
    {
        "title":  "Façades et reflet — motif vénitien",
        "medium": "Aquarelle sur Papier",
        "dims":   "15x21 cm",
        "status": "vendu",
        "price":  180,
        "img":    "https://medias.artmajeur.com/master/7193305_20180612-170715-2.jpg?v=1739550919",
        "desc":   "Aquarelle sur Papier, 15×21 cm.",
    },
    {
        "title":  "Façade et gondole — motif vénitien",
        "medium": "Aquarelle sur Papier",
        "dims":   "16x21 cm",
        "status": "vendu",
        "price":  160,
        "img":    "https://medias.artmajeur.com/master/7194598_20180612-170621-3.jpg?v=1739540699",
        "desc":   "Aquarelle sur Papier, 16×21 cm.",
    },
    {
        "title":  "Gondoles au repos",
        "medium": "Gouache sur Carton",
        "dims":   "85x65 cm",
        "status": "disponible",
        "price":  650,
        "img":    "https://medias.artmajeur.com/master/7192918_013.jpg?v=1739565888",
        "desc":   "Gouache sur Carton, 85×65 cm.",
    },
    {
        "title":  "Venise la blanche — effet de neige",
        "medium": "Tempera sur Carton",
        "dims":   "75x105 cm",
        "status": "disponible",
        "price":  720,
        "img":    "https://medias.artmajeur.com/master/7193128_img-1642.jpg?v=1739551086",
        "desc":   "Tempera sur Carton, 75×105 cm.",
    },
]

# ─── Helpers ──────────────────────────────────────────────────────────────────

def wp(cmd):
    """Lance une commande wp-cli dans le container WordPress."""
    full = f"docker exec {CONTAINER_WP} wp --allow-root --path={WP_PATH} {cmd}"
    r = subprocess.run(full, shell=True, capture_output=True, text=True)
    if r.returncode != 0:
        print(f"  ⚠  wp erreur : {r.stderr.strip()[:200]}")
    return r.stdout.strip()

def docker_run(cmd):
    """Lance une commande shell dans le container."""
    r = subprocess.run(f"docker exec {CONTAINER_WP} bash -c {repr(cmd)}", shell=True,
                       capture_output=True, text=True)
    return r.stdout.strip(), r.stderr.strip()

# ─── 1. Préparer le dossier tmp dans le container ────────────────────────────
print("== Préparation ==")
docker_run(f"mkdir -p {TMP_DIR}")

# ─── 2. Supprimer les anciens produits de démo ────────────────────────────────
print("\n== Suppression des anciens produits ==")
old_ids = wp("wc product list --user=1 --fields=id --format=csv").strip().split("\n")[1:]
for pid in old_ids:
    pid = pid.strip()
    if pid:
        wp(f"wc product delete {pid} --user=1 --force=true")
        print(f"  ✓ Produit {pid} supprimé")

# ─── 3. Import de chaque œuvre ────────────────────────────────────────────────
print("\n== Import des œuvres ==")
for art in ARTWORKS:
    slug  = art["title"].lower().replace(" ", "-").replace("'", "").replace("—", "").replace("é","e").replace("è","e").replace("ê","e").replace("â","a").replace("î","i").replace("ô","o").replace("û","u").replace("ç","c")
    fname = f"{slug}.jpg"
    fpath = f"{TMP_DIR}/{fname}"

    # 3a. Télécharger l'image
    print(f"\n  → {art['title']}")
    ua  = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36"
    ref = "https://www.artmajeur.com/"
    img_mini = art["img"].replace("/master/", "/mini/")
    curl_cmd = f'curl -sL --max-time 30 -H "User-Agent: {ua}" -H "Referer: {ref}" -o "{fpath}" "{img_mini}"'
    out, err = docker_run(curl_cmd)
    size_out, _ = docker_run(f"wc -c < {repr(fpath)}")
    size = int(size_out.strip()) if size_out.strip().isdigit() else 0
    if size < 5000:
        print(f"    ⚠  Image trop petite ({size} bytes) — skip")
        continue
    print(f"    ✓ Image téléchargée ({size} bytes)")

    # 3b. Importer dans la médiathèque
    attach_id = wp(f"media import {fpath} --title={repr(art['title'])} --porcelain")
    if not attach_id.isdigit():
        print(f"    ⚠  Import média échoué : {attach_id}")
        continue
    print(f"    ✓ Média importé (ID {attach_id})")

    # 3c. Créer le produit WooCommerce via wp eval (PHP)
    stock_qty  = 1 if art["status"] == "disponible" else 0
    stock_stat = "instock" if art["status"] == "disponible" else "outofstock"

    php = (
        f'$p = new WC_Product_Simple();'
        f'$p->set_name({json.dumps(art["title"])});'
        f'$p->set_regular_price("{art["price"]}");'
        f'$p->set_description({json.dumps(art["medium"] + " — " + art["dims"] + ".")});'
        f'$p->set_short_description({json.dumps(art["medium"] + ", " + art["dims"])});'
        f'$p->set_manage_stock(true);'
        f'$p->set_stock_quantity({stock_qty});'
        f'$p->set_stock_status("{stock_stat}");'
        f'$p->set_image_id({attach_id});'
        f'$id = $p->save();'
        f'echo $id;'
    )
    pid = wp(f"eval {repr(php)}")
    if pid.isdigit():
        print(f"    ✓ Produit créé (ID {pid}), statut={art['status']}, prix={art['price']}€")
    else:
        print(f"    ⚠  Création produit échouée : {pid}")

# ─── 4. Nettoyage ─────────────────────────────────────────────────────────────
docker_run(f"rm -rf {TMP_DIR}")
print("\n== Import terminé ==")
