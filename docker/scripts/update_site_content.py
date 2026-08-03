#!/usr/bin/env python3
"""
Met à jour le contenu du site galerie-djilali-kadid.com :
  - Télécharge la photo de profil + image de couverture d'Artmajeur
  - Met à jour la page Accueil (hero + biographie courte)
  - Met à jour la page À propos (bio complète + photo)
  - Corrige les statuts des produits (Gondoles/Venise → pas à vendre)
  - Ajoute les 10 œuvres "Pas à vendre" restantes
  - Modifie le plugin pour gérer l'affichage "pas à vendre"
"""
import subprocess, json

CONTAINER_WP = "djilali-kadid-wordpress-1"
WP_PATH      = "/var/www/html"
TMP_DIR      = "/var/www/html/wp-content/uploads/artmajeur_tmp"

# ── Bio complète ──────────────────────────────────────────────────────────────
BIO_FULL = """Djilali Kadid est un artiste peintre dont la pratique artistique se concentre principalement sur la réalisation de natures mortes et de paysages urbains, notamment des motifs vénitiens. Il travaille avec des techniques telles que la gouache, l'aquarelle et la tempera, appliquées sur des supports variés comme le carton gris ou le papier.

Son approche picturale s'inscrit dans une tradition figurative, avec une influence notable de l'impressionnisme et de l'école cézannienne, visible dans le traitement de la lumière et la matière. Kadid capte des instants fugaces et atmosphériques, que ce soit dans des scènes d'intérieur ou dans des vues de la ville de Venise, où il saisit la poésie des façades, des canaux et des reflets.

À travers son travail, Djilali Kadid explore la relation entre la lumière, la couleur et la texture, créant des compositions harmonieuses qui évoquent la douceur et la transparence. Son œuvre témoigne d'une sensibilité particulière à l'instant présent et à l'atmosphère des lieux, mêlant tradition et expression personnelle."""

BIO_SHORT = "Peintre figuratif installé à Meulan-en-Yvelines, Djilali Kadid puise son inspiration dans les natures mortes et les paysages vénitiens. Ses gouaches, aquarelles et temperas sur carton gris révèlent une sensibilité lumineuse héritée de l'impressionnisme et de l'école cézannienne."

# ── URLs Artmajeur ────────────────────────────────────────────────────────────
PROFILE_IMG_URL = "https://medias.artmajeur.com/profile/accimg_11401_profile_autoportrait-01kbz52ddx8at0b6fetph7m8md.jpg?v=1765203744"
COVER_IMG_URL   = "https://medias.artmajeur.com/cover/accimg_11401_cover_20180113-094748-2-01kbz52e7624c5ws26tqe1xn0k.jpg?v=1765203745"

# ── Œuvres "Pas à vendre" à ajouter ──────────────────────────────────────────
PAS_A_VENDRE = [
    {"title": "Grande nature morte",              "medium": "Peinture",              "dims": "80x110 cm", "img": "https://medias.artmajeur.com/mini/7216915_grande-nature-morte.jpg?v=1739542688"},
    {"title": "Burano, la sieste",                "medium": "Aquarelle sur Papier",  "dims": "21x15 cm",  "img": "https://medias.artmajeur.com/mini/7193164_20180612-170745-2-copie.jpg?v=1739544349"},
    {"title": "Vue de Venise",                    "medium": "Aquarelle sur Papier",  "dims": "15x40 cm",  "img": "https://medias.artmajeur.com/mini/7193209_20180612-170946-2.jpg?v=1739564846"},
    {"title": "Nature morte dans l'atelier",      "medium": "Peinture",              "dims": "80x120 cm", "img": "https://medias.artmajeur.com/mini/7220947_f1000023-nature-morte-dans-l-atelier.jpg?v=1739564063"},
    {"title": "Façade et reflet — Venise",        "medium": "Tempera sur Toile de lin", "dims": "71x62 cm", "img": "https://medias.artmajeur.com/mini/7193095_img-1739.jpg?v=1739550829"},
    {"title": "Nature morte à la bouilloire rouge","medium": "Peinture",             "dims": "80x120 cm", "img": "https://medias.artmajeur.com/mini/7220962_nmbouilloirerouge.jpg?v=1739542736"},
    {"title": "Paysage aux deux ponts",           "medium": "Huile",                 "dims": "75x98 cm",  "img": "https://medias.artmajeur.com/mini/7191550_paysage-aux-deux-ponts-gouache-sur-carton-gris-2012.jpg?v=1739544399"},
    {"title": "Le Pont du Rialto à Venise",       "medium": "Aquarelle sur Papier",  "dims": "15x21 cm",  "img": "https://medias.artmajeur.com/mini/7193368_20180612-170920-3-copie.jpg?v=1739565873"},
    {"title": "Flânerie vénitienne",              "medium": "Huile",                 "dims": "90x70 cm",  "img": "https://medias.artmajeur.com/mini/7191583_flanerie-venitienne-gouache-sur-carton-gris-2013.jpg?v=1739543537"},
    {"title": "Le petit pont — motif vénitien",   "medium": "Aquarelle sur Papier",  "dims": "21x15 cm",  "img": "https://medias.artmajeur.com/mini/7193158_20180612-170857-2-copie.jpg?v=1739564847"},
]

# ─── Helpers ──────────────────────────────────────────────────────────────────
def wp(cmd):
    full = f"docker exec {CONTAINER_WP} wp --allow-root --path={WP_PATH} {cmd}"
    r = subprocess.run(full, shell=True, capture_output=True, text=True)
    if r.returncode != 0 and r.stderr.strip():
        print(f"  ⚠  {r.stderr.strip()[:200]}")
    return r.stdout.strip()

def docker_run(cmd):
    r = subprocess.run(f"docker exec {CONTAINER_WP} bash -c {repr(cmd)}", shell=True,
                       capture_output=True, text=True)
    return r.stdout.strip(), r.stderr.strip()

def eval_file(php_code):
    """Exécute du PHP via wp eval-file (évite les problèmes de quotes)."""
    import tempfile, os
    with tempfile.NamedTemporaryFile(mode='w', suffix='.php', delete=False, encoding='utf-8') as f:
        f.write('<?php\n' + php_code)
        fname = f.name
    subprocess.run(f"docker cp {fname} {CONTAINER_WP}:/tmp/_wp_eval.php", shell=True)
    result = wp("eval-file /tmp/_wp_eval.php")
    os.unlink(fname)
    return result

def download_img(url, filename):
    ua  = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36"
    ref = "https://www.artmajeur.com/"
    fpath = f"{TMP_DIR}/{filename}"
    docker_run(f'mkdir -p {TMP_DIR}')
    docker_run(f'curl -sL --max-time 30 -H "User-Agent: {ua}" -H "Referer: {ref}" -o "{fpath}" "{url}"')
    size_out, _ = docker_run(f"wc -c < {repr(fpath)}")
    size = int(size_out.strip()) if size_out.strip().isdigit() else 0
    return fpath if size > 5000 else None

def import_media(fpath, title):
    attach_id = wp(f"media import {fpath} --title={repr(title)} --porcelain")
    return attach_id if attach_id.isdigit() else None


# ═══════════════════════════════════════════════════════════════════════════════
print("═══ 1. Téléchargement photo de profil + bannière ═══")
# ═══════════════════════════════════════════════════════════════════════════════

profile_path = download_img(PROFILE_IMG_URL, "djilali-kadid-photo.jpg")
cover_path   = download_img(COVER_IMG_URL,   "djilali-kadid-couverture.jpg")
print(f"  Photo profil : {'OK' if profile_path else 'ECHEC'}")
print(f"  Bannière     : {'OK' if cover_path else 'ECHEC'}")

profile_id = import_media(profile_path, "Djilali Kadid — Portrait") if profile_path else None
cover_id   = import_media(cover_path,   "Djilali Kadid — Couverture") if cover_path else None
print(f"  Média profil ID : {profile_id}")
print(f"  Média couverture ID : {cover_id}")

cover_url = wp(f"eval 'echo wp_get_attachment_url({cover_id});'") if cover_id else COVER_IMG_URL
profile_url = wp(f"eval 'echo wp_get_attachment_url({profile_id});'") if profile_id else PROFILE_IMG_URL


# ═══════════════════════════════════════════════════════════════════════════════
print("\n═══ 2. Mise à jour de la page Accueil (ID 10) ═══")
# ═══════════════════════════════════════════════════════════════════════════════

HOME_CONTENT = f"""<section class="mvp-hero" style="background-image:linear-gradient(90deg, rgba(26,24,20,.82), rgba(26,24,20,.22)), url('{cover_url}');">
    <div class="mvp-hero__inner">
        <h1 class="mvp-hero-title--stacked"><span>Galerie</span><span>Djilali</span><span>Kadid</span></h1>
        <p>Peintures figuratives — natures mortes &amp; paysages vénitiens</p>
        <div class="mvp-actions">
            <a class="button mvp-hero-button mvp-hero-button--primary" href="https://galerie-djilali-kadid.com/galerie/">Voir les oeuvres</a>
            <a class="button mvp-hero-button mvp-hero-button--secondary" href="https://galerie-djilali-kadid.com/a-propos/">À propos</a>
        </div>
    </div>
</section>
<section class="mvp-section mvp-section--intro">
    <h2>À propos de l'artiste</h2>
    <p>{BIO_SHORT}</p>
</section>
<section class="mvp-section">
    <div class="mvp-section__head">
        <div>
            <h2>Galerie</h2>
        </div>
        <a href="https://galerie-djilali-kadid.com/galerie/">Toute la galerie</a>
    </div>
    [products category="oeuvres-originales" limit="6" columns="3" orderby="date" order="DESC"]
</section>
<section class="mvp-section mvp-home-services">
    <div class="mvp-section__head">
        <div>
            <h2>Prestations</h2>
        </div>
        <a href="https://galerie-djilali-kadid.com/prestations/">Voir les prestations</a>
    </div>
    [products category="prestations-culturelles" limit="3" columns="3" orderby="date" order="DESC"]
</section>
<section class="mvp-section">
    <div class="mvp-section__head">
        <div>
            <p class="mvp-kicker">Journal</p>
            <h2>Derniers articles</h2>
        </div>
        <a href="https://galerie-djilali-kadid.com/blog/">Lire le blog</a>
    </div>
    <!-- wp:latest-posts {{"postsToShow":3,"displayPostContent":true,"excerptLength":26,"displayFeaturedImage":true,"featuredImageSizeSlug":"medium","addLinkToFeaturedImage":true,"className":"mvp-latest-posts"}} /-->
</section>"""

result = eval_file(f"""
$content = {json.dumps(HOME_CONTENT, ensure_ascii=False)};
wp_update_post(['ID' => 10, 'post_content' => $content]);
echo 'OK';
""")
print(f"  Accueil mis à jour : {result}")


# ═══════════════════════════════════════════════════════════════════════════════
print("\n═══ 3. Mise à jour de la page À propos (ID 15) ═══")
# ═══════════════════════════════════════════════════════════════════════════════

bio_para1, bio_para2, bio_para3 = BIO_FULL.strip().split('\n\n')

ABOUT_CONTENT = f"""<section class="mvp-section mvp-section--intro">
    <p class="mvp-kicker">À propos</p>
    <h1>Biographie</h1>
    <p>{bio_para1}</p>
</section>
<section class="mvp-section mvp-split">
    <div>
        <h2>Parcours &amp; démarche</h2>
        <p>{bio_para2}</p>
        <p>{bio_para3}</p>
        <p><a class="button mvp-page-button" href="https://galerie-djilali-kadid.com/galerie/">Découvrir les oeuvres</a></p>
    </div>
    <div>
        <img src="{profile_url}" alt="Djilali Kadid, artiste peintre" style="width:100%;border-radius:8px;object-fit:cover;" />
    </div>
</section>"""

result = eval_file(f"""
$content = {json.dumps(ABOUT_CONTENT, ensure_ascii=False)};
wp_update_post(['ID' => 15, 'post_content' => $content]);
echo 'OK';
""")
print(f"  À propos mis à jour : {result}")


# ═══════════════════════════════════════════════════════════════════════════════
print("\n═══ 4. Correction statut œuvres « pas à vendre » déjà importées ═══")
# ═══════════════════════════════════════════════════════════════════════════════
# Gondoles au repos et Venise la blanche étaient marquées disponibles — corrigé

FIX_TITLES = ["Gondoles au repos", "Venise la blanche"]
for title in FIX_TITLES:
    result = eval_file(f"""
$posts = get_posts([
    'post_type'   => 'product',
    'post_status' => 'any',
    's'           => {json.dumps(title, ensure_ascii=False)},
    'numberposts' => 1,
]);
if (empty($posts)) {{ echo 'NOT_FOUND'; return; }}
$p = wc_get_product($posts[0]->ID);
$p->set_stock_quantity(0);
$p->set_stock_status('outofstock');
$p->set_regular_price('');
$p->update_meta_data('_exhibition_only', 'yes');
$p->save();
echo $posts[0]->ID;
""")
    print(f"  {title} → pas à vendre (ID: {result})")


# ═══════════════════════════════════════════════════════════════════════════════
print("\n═══ 5. Import des 10 œuvres Pas à vendre ═══")
# ═══════════════════════════════════════════════════════════════════════════════

for art in PAS_A_VENDRE:
    slug  = art["title"].lower()
    for ch, rep in [(' ','_'), ("'",""), ('—',''), ('é','e'), ('è','e'), ('ê','e'), ('â','a'), ('î','i'), ('ô','o'), ('û','u'), ('ç','c'), ('à','a')]:
        slug = slug.replace(ch, rep)
    fname = slug[:40] + ".jpg"
    fpath = f"{TMP_DIR}/{fname}"

    print(f"\n  → {art['title']}")
    ua  = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36"
    ref = "https://www.artmajeur.com/"
    docker_run(f'curl -sL --max-time 30 -H "User-Agent: {ua}" -H "Referer: {ref}" -o "{fpath}" "{art["img"]}"')
    size_out, _ = docker_run(f"wc -c < {repr(fpath)}")
    size = int(size_out.strip()) if size_out.strip().isdigit() else 0
    if size < 5000:
        print(f"    ⚠  Image invalide ({size} bytes)")
        continue
    print(f"    ✓ Image ({size} bytes)")

    attach_id = import_media(fpath, art["title"])
    if not attach_id:
        print(f"    ⚠  Import média échoué")
        continue
    print(f"    ✓ Média ID {attach_id}")

    result = eval_file(f"""
$p = new WC_Product_Simple();
$p->set_name({json.dumps(art['title'], ensure_ascii=False)});
$p->set_regular_price('');
$p->set_description({json.dumps(art['medium'] + ' — ' + art['dims'] + '.', ensure_ascii=False)});
$p->set_short_description({json.dumps(art['medium'] + ', ' + art['dims'], ensure_ascii=False)});
$p->set_manage_stock(true);
$p->set_stock_quantity(0);
$p->set_stock_status('outofstock');
$p->set_image_id({attach_id});
$p->update_meta_data('_exhibition_only', 'yes');
$id = $p->save();
echo $id;
""")
    if result.isdigit():
        print(f"    ✓ Produit créé (ID {result})")
    else:
        print(f"    ⚠  Échec : {result}")


# ═══════════════════════════════════════════════════════════════════════════════
print("\n═══ 6. Nettoyage tmp ═══")
# ═══════════════════════════════════════════════════════════════════════════════
docker_run(f"rm -rf {TMP_DIR}")
print("  ✓ Terminé")
