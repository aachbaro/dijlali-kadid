/* Djilali – Mode Édition frontend (détection automatique de tout le contenu) */
(function () {
    'use strict';

    var isActive    = false;
    var currentZone = null;
    var globalFiles = [];   // fichiers cumulés pour l'ajout global

    // Sélecteurs de texte éditables (éléments "feuilles" contenant du texte)
    var TEXT_TAGS = ['H1','H2','H3','H4','H5','H6','P','LI','BLOCKQUOTE','FIGCAPTION','TD','TH','DT','DD'];
    // Boutons / liens d'action
    var BUTTON_SEL = 'a.button, .wp-block-button__link, button.mvp-hero-button, a.mvp-hero-button';

    // Zones à NE JAMAIS toucher (UI du plugin, admin bar, formulaires, nav technique)
    var EXCLUDE_ANCESTORS = [
        '#wpadminbar', '#djs-overlay', '#djs-toggle', '#djs-badge', '#djs-global-btn',
        'script', 'style', 'noscript', 'form', '.djs-skip',
        '.woocommerce-cart-form', '#djs-modal', '#djs-global-modal',
        // Galerie d'œuvres : Djilali gère les œuvres lui-même (admin) → non éditable ici
        '.woocommerce ul.products', '.artworks-grid', '.artwork-card'
    ];

    // ── Création des éléments UI ─────────────────────────────────────────────
    function buildUI() {
        var btn = document.createElement('button');
        btn.id = 'djs-toggle';
        btn.className = 'djs-skip';
        btn.title = 'Mode Édition';
        btn.innerHTML = '✏️';
        btn.addEventListener('click', toggleMode);
        document.body.appendChild(btn);

        // Bouton "ajout global" (visible seulement en mode édition, à gauche du ✏️)
        var globalBtn = document.createElement('button');
        globalBtn.id = 'djs-global-btn';
        globalBtn.className = 'djs-skip';
        globalBtn.title = 'Proposer un ajout de contenu sur cette page';
        globalBtn.innerHTML = '➕';
        globalBtn.addEventListener('click', openGlobalModal);
        document.body.appendChild(globalBtn);

        // Petit badge discret (remplace l'ancienne bannière pleine largeur)
        var badge = document.createElement('div');
        badge.id = 'djs-badge';
        badge.className = 'djs-skip';
        badge.innerHTML = '<span class="djs-badge-dot"></span> Mode Édition — cliquez un élément, ou ➕ pour un ajout';
        document.body.appendChild(badge);

        var overlay = document.createElement('div');
        overlay.id = 'djs-overlay';
        overlay.className = 'djs-hidden djs-skip';
        overlay.innerHTML = [
            '<div id="djs-modal">',
            '  <div id="djs-modal-header">',
            '    <h2 id="djs-modal-title">Proposer une modification</h2>',
            '    <button id="djs-modal-close" title="Fermer">&times;</button>',
            '  </div>',
            '  <div id="djs-modal-body">',
            '    <span class="djs-field-label">Zone concernée</span>',
            '    <div id="djs-zone-label" style="font-weight:600;font-size:14px;margin-bottom:12px"></div>',
            '    <span class="djs-field-label" id="djs-current-label">Contenu actuel</span>',
            '    <div id="djs-current-display"></div>',
            '    <span class="djs-field-label" id="djs-proposed-label">Nouveau contenu proposé</span>',
            '    <textarea id="djs-proposed" placeholder="Écrivez ici le nouveau texte…"></textarea>',
            '    <div id="djs-file-wrap" style="display:none">',
            '      <input type="file" id="djs-file-input" accept="image/*">',
            '      <img id="djs-img-preview" alt="Aperçu">',
            '    </div>',
            '    <span class="djs-field-label" style="margin-top:14px">Note (facultatif)</span>',
            '    <textarea id="djs-note" placeholder="Précisions pour Adam…"></textarea>',
            '  </div>',
            '  <div id="djs-modal-footer">',
            '    <button id="djs-cancel">Annuler</button>',
            '    <button id="djs-submit">Envoyer la suggestion ➜</button>',
            '  </div>',
            '  <div id="djs-feedback"></div>',
            '</div>',
        ].join('\n');
        document.body.appendChild(overlay);

        // Overlay + modale "ajout global"
        var gOverlay = document.createElement('div');
        gOverlay.id = 'djs-global-overlay';
        gOverlay.className = 'djs-hidden djs-skip';
        gOverlay.innerHTML = [
            '<div id="djs-global-modal">',
            '  <div id="djs-modal-header">',
            '    <h2>➕ Proposer un ajout de contenu</h2>',
            '    <button id="djs-global-close" title="Fermer">&times;</button>',
            '  </div>',
            '  <div id="djs-modal-body">',
            '    <p style="font-size:13px;color:#666;margin:0 0 12px">',
            '      Décrivez ce que vous aimeriez ajouter ou changer sur cette page ',
            '      (un nouveau texte, une section, une photo, un son…). ',
            '      Vous pouvez joindre des fichiers. Adam s\'en chargera.',
            '    </p>',
            '    <span class="djs-field-label">Votre demande</span>',
            '    <textarea id="djs-global-message" placeholder="Ex : J\'aimerais ajouter une section « Actualités » avec ce texte et cette photo…"></textarea>',
            '    <span class="djs-field-label" style="margin-top:14px">Fichiers à joindre (facultatif)</span>',
            '    <input type="file" id="djs-global-files" multiple accept="image/*,audio/*,video/*,.pdf,.doc,.docx,.txt">',
            '    <div id="djs-global-filelist"></div>',
            '  </div>',
            '  <div id="djs-modal-footer">',
            '    <button id="djs-global-cancel">Annuler</button>',
            '    <button id="djs-global-submit">Envoyer la demande ➜</button>',
            '  </div>',
            '  <div id="djs-global-feedback"></div>',
            '</div>',
        ].join('\n');
        document.body.appendChild(gOverlay);

        document.getElementById('djs-modal-close').addEventListener('click', closeModal);
        document.getElementById('djs-cancel').addEventListener('click', closeModal);
        overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(); });
        document.getElementById('djs-submit').addEventListener('click', submitSuggestion);

        document.getElementById('djs-global-close').addEventListener('click', closeGlobalModal);
        document.getElementById('djs-global-cancel').addEventListener('click', closeGlobalModal);
        gOverlay.addEventListener('click', function (e) { if (e.target === gOverlay) closeGlobalModal(); });
        document.getElementById('djs-global-submit').addEventListener('click', submitGlobal);
        document.getElementById('djs-global-files').addEventListener('change', function () {
            // Ajoute les nouveaux fichiers à la liste (sans remplacer les précédents)
            Array.prototype.forEach.call(this.files, function (f) {
                var dup = globalFiles.some(function (g) {
                    return g.name === f.name && g.size === f.size;
                });
                if (!dup) globalFiles.push(f);
            });
            this.value = '';           // réinitialise pour pouvoir re-sélectionner
            renderFileList();
        });

        document.getElementById('djs-file-input').addEventListener('change', function () {
            var file = this.files[0];
            if (!file) return;
            var preview = document.getElementById('djs-img-preview');
            preview.src = URL.createObjectURL(file);
            preview.style.display = 'block';
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { closeModal(); closeGlobalModal(); }
        });
    }

    // ── Modale "ajout global" ────────────────────────────────────────────────
    function humanSize(bytes) {
        if (bytes < 1024) return bytes + ' o';
        if (bytes < 1048576) return Math.round(bytes / 1024) + ' Ko';
        return (bytes / 1048576).toFixed(1) + ' Mo';
    }

    function renderFileList() {
        var list = document.getElementById('djs-global-filelist');
        list.innerHTML = '';
        globalFiles.forEach(function (f, idx) {
            var chip = document.createElement('div');
            chip.className = 'djs-file-chip';
            var name = document.createElement('span');
            name.textContent = '📎 ' + f.name + ' (' + humanSize(f.size) + ')';
            var rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'djs-file-remove';
            rm.setAttribute('aria-label', 'Retirer ' + f.name);
            rm.textContent = '×';
            rm.addEventListener('click', function () {
                globalFiles.splice(idx, 1);
                renderFileList();
            });
            chip.appendChild(name);
            chip.appendChild(rm);
            list.appendChild(chip);
        });
    }

    function openGlobalModal() {
        globalFiles = [];
        document.getElementById('djs-global-message').value = '';
        document.getElementById('djs-global-files').value   = '';
        document.getElementById('djs-global-filelist').innerHTML = '';
        var fb = document.getElementById('djs-global-feedback');
        fb.className = ''; fb.textContent = '';
        var btn = document.getElementById('djs-global-submit');
        btn.disabled = false; btn.textContent = 'Envoyer la demande ➜';
        document.getElementById('djs-global-overlay').classList.remove('djs-hidden');
        setTimeout(function () { document.getElementById('djs-global-message').focus(); }, 50);
    }

    function closeGlobalModal() {
        var el = document.getElementById('djs-global-overlay');
        if (el) el.classList.add('djs-hidden');
    }

    function submitGlobal() {
        var msg  = (document.getElementById('djs-global-message').value || '').trim();
        var btn  = document.getElementById('djs-global-submit');
        var fb   = document.getElementById('djs-global-feedback');

        if (!msg) {
            fb.className = 'djs-error';
            fb.textContent = '✗ Décrivez votre demande avant d\'envoyer.';
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Envoi en cours…';
        fb.className = ''; fb.textContent = '';

        var fd = new FormData();
        fd.append('message', msg);
        fd.append('page_url', DJS.pageUrl);
        fd.append('page_title', document.title || '');
        globalFiles.forEach(function (f, i) {
            fd.append('file' + i, f);
        });

        fetch(DJS.restUrl + 'suggestion-global', {
            method: 'POST',
            headers: { 'X-WP-Nonce': DJS.nonce },
            body: fd,
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) throw new Error();
            fb.className   = 'djs-success';
            fb.textContent = '✓ Demande envoyée ! Adam en a été notifié par email.';
            btn.textContent = 'Envoyé ✓';
            setTimeout(closeGlobalModal, 2200);
        })
        .catch(function () {
            fb.className   = 'djs-error';
            fb.textContent = '✗ Une erreur est survenue. Réessayez.';
            btn.disabled   = false;
            btn.textContent = 'Envoyer la demande ➜';
        });
    }

    // ── Toggle mode ──────────────────────────────────────────────────────────
    function toggleMode() {
        isActive = !isActive;
        document.getElementById('djs-toggle').classList.toggle('djs-active', isActive);
        document.getElementById('djs-badge').classList.toggle('djs-visible', isActive);
        document.body.classList.toggle('djs-edit-mode', isActive);

        if (isActive) activateZones();
        else          deactivateZones();
    }

    // ── Détection : un élément est-il exclu ? ─────────────────────────────────
    function isExcluded(el) {
        for (var i = 0; i < EXCLUDE_ANCESTORS.length; i++) {
            if (el.closest(EXCLUDE_ANCESTORS[i])) return true;
        }
        return false;
    }

    // Un élément texte est "feuille" s'il ne contient pas d'autre bloc de texte
    function isLeafText(el) {
        var text = (el.innerText || '').trim();
        if (!text || text.length < 2) return false;
        // S'il contient un autre élément de la liste TEXT_TAGS, ce n'est pas une feuille
        for (var i = 0; i < TEXT_TAGS.length; i++) {
            if (el.querySelector(TEXT_TAGS[i].toLowerCase())) return false;
        }
        return true;
    }

    // ── Activation des zones (scan dynamique) ────────────────────────────────
    function activateZones() {
        var seen = [];

        // 1) Textes
        var textSelector = TEXT_TAGS.join(',') + ',' + BUTTON_SEL;
        Array.prototype.forEach.call(document.querySelectorAll(textSelector), function (el) {
            if (isExcluded(el)) return;
            if (el.dataset.djsZone) return;
            if (seen.indexOf(el) !== -1) return;

            var isButton = el.matches(BUTTON_SEL);
            if (!isButton && !isLeafText(el)) return;
            if (isButton && !(el.innerText || '').trim()) return;

            seen.push(el);
            markZone(el, 'text', labelFor(el, 'text'));
        });

        // 2) Images (hors icônes minuscules, hors UI)
        Array.prototype.forEach.call(document.querySelectorAll('img'), function (img) {
            if (isExcluded(img)) return;
            if (img.dataset.djsZone) return;
            // Ignore les tout petits (icônes, avatars, pictos < 60px)
            var w = img.naturalWidth || img.width || 0;
            var h = img.naturalHeight || img.height || 0;
            if (w && w < 60 && h && h < 60) return;
            markZone(img, 'image', labelFor(img, 'image'));
        });

        // 3) Images de fond (hero) — éléments avec background-image
        Array.prototype.forEach.call(document.querySelectorAll('.mvp-hero, [style*="background-image"], .wp-block-cover'), function (el) {
            if (isExcluded(el)) return;
            if (el.dataset.djsZone) return;
            var bg = window.getComputedStyle(el).backgroundImage;
            if (!bg || bg === 'none') return;
            markZone(el, 'image', 'Image de fond', extractBgUrl(bg));
        });
    }

    function markZone(el, type, label, bgUrl) {
        el.dataset.djsZone  = cssPath(el);
        el.dataset.djsLabel = label;
        el.dataset.djsType  = type;
        if (bgUrl) el.dataset.djsBg = bgUrl;
        el.classList.add(type === 'image' ? 'djs-editable-img' : 'djs-editable');
        el._djsHandler = function (e) { e.preventDefault(); e.stopPropagation(); onZoneClick(el); };
        el.addEventListener('click', el._djsHandler, true);
    }

    function deactivateZones() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-djs-zone]'), function (el) {
            el.classList.remove('djs-editable', 'djs-editable-img');
            if (el._djsHandler) el.removeEventListener('click', el._djsHandler, true);
            delete el.dataset.djsZone;
            delete el.dataset.djsLabel;
            delete el.dataset.djsType;
            delete el.dataset.djsBg;
        });
    }

    // ── Génération de libellé lisible ────────────────────────────────────────
    function labelFor(el, type) {
        if (type === 'image') {
            var alt = (el.getAttribute && el.getAttribute('alt')) || '';
            if (alt) return 'Image : ' + trim(alt, 40);
            var src = el.currentSrc || el.src || '';
            var file = src.split('/').pop().split('?')[0];
            return 'Image : ' + trim(file, 40);
        }
        var txt = trim((el.innerText || '').trim(), 45);
        var tag = el.tagName;
        if (/^H[1-6]$/.test(tag))                return 'Titre « ' + txt + ' »';
        if (el.matches(BUTTON_SEL))              return 'Bouton « ' + txt + ' »';
        if (tag === 'LI')                        return 'Élément de liste « ' + txt + ' »';
        if (tag === 'BLOCKQUOTE')                return 'Citation « ' + txt + ' »';
        if (tag === 'FIGCAPTION')                return 'Légende « ' + txt + ' »';
        return 'Texte « ' + txt + ' »';
    }

    function trim(s, n) { s = String(s); return s.length > n ? s.slice(0, n) + '…' : s; }

    function extractBgUrl(bg) {
        var m = bg.match(/url\(["']?(.*?)["']?\)/);
        return m ? m[1] : '';
    }

    // ── Chemin CSS unique (pour qu'Adam retrouve l'élément) ───────────────────
    function cssPath(el) {
        if (!(el instanceof Element)) return '';
        var path = [];
        while (el && el.nodeType === 1 && path.length < 5) {
            var sel = el.nodeName.toLowerCase();
            if (el.id) { sel += '#' + el.id; path.unshift(sel); break; }
            var cls = (el.className && typeof el.className === 'string')
                ? el.className.trim().split(/\s+/).filter(function (c) {
                    return c && c.indexOf('djs-') !== 0;
                  }).slice(0, 2).join('.')
                : '';
            if (cls) sel += '.' + cls;
            var parent = el.parentNode;
            if (parent) {
                var sibs = Array.prototype.filter.call(parent.children, function (c) {
                    return c.nodeName === el.nodeName;
                });
                if (sibs.length > 1) sel += ':nth-of-type(' + (sibs.indexOf(el) + 1) + ')';
            }
            path.unshift(sel);
            el = el.parentNode;
        }
        return path.join(' > ');
    }

    // ── Clic sur une zone ────────────────────────────────────────────────────
    function onZoneClick(el) {
        var type = el.dataset.djsType;
        var current = '';
        if (type === 'image') {
            if (el.dataset.djsBg) current = el.dataset.djsBg;
            else if (el.tagName === 'IMG') current = el.currentSrc || el.src;
            else { var i = el.querySelector('img'); current = i ? (i.currentSrc || i.src) : ''; }
        } else {
            current = (el.innerText || el.textContent || '').trim();
        }
        currentZone = {
            id:      el.dataset.djsZone,
            label:   el.dataset.djsLabel,
            type:    type,
            current: current,
            el:      el,
        };
        showModal(currentZone);
    }

    // ── Modal ────────────────────────────────────────────────────────────────
    function showModal(zone) {
        document.getElementById('djs-zone-label').textContent = zone.label;
        document.getElementById('djs-feedback').className     = '';
        document.getElementById('djs-feedback').textContent   = '';
        document.getElementById('djs-note').value             = '';
        document.getElementById('djs-submit').disabled        = false;
        document.getElementById('djs-submit').textContent     = 'Envoyer la suggestion ➜';

        var currentDisplay = document.getElementById('djs-current-display');
        var proposedArea   = document.getElementById('djs-proposed');
        var fileWrap       = document.getElementById('djs-file-wrap');
        var proposedLabel  = document.getElementById('djs-proposed-label');

        if (zone.type === 'image') {
            currentDisplay.innerHTML = zone.current
                ? '<img class="djs-current-img" src="' + escHtml(zone.current) + '" alt="Image actuelle">'
                : '<em style="color:#999">Pas d\'image actuelle</em>';
            proposedArea.style.display = 'none';
            fileWrap.style.display     = 'block';
            proposedLabel.textContent  = 'Nouvelle image à proposer';
            document.getElementById('djs-file-input').value = '';
            document.getElementById('djs-img-preview').style.display = 'none';
        } else {
            currentDisplay.innerHTML   = '<div class="djs-current-text">' + escHtml(zone.current || '(vide)') + '</div>';
            proposedArea.value         = zone.current || '';
            proposedArea.style.display = 'block';
            fileWrap.style.display     = 'none';
            proposedLabel.textContent  = 'Nouveau texte proposé';
        }

        document.getElementById('djs-overlay').classList.remove('djs-hidden');
        if (zone.type !== 'image') setTimeout(function () { proposedArea.focus(); }, 50);
    }

    function closeModal() {
        document.getElementById('djs-overlay').classList.add('djs-hidden');
        currentZone = null;
    }

    // ── Envoi ────────────────────────────────────────────────────────────────
    function submitSuggestion() {
        if (!currentZone) return;
        var submitBtn = document.getElementById('djs-submit');
        var feedback  = document.getElementById('djs-feedback');
        var note      = (document.getElementById('djs-note').value || '').trim();

        submitBtn.disabled = true;
        submitBtn.textContent = 'Envoi en cours…';
        feedback.className = '';
        feedback.textContent = '';

        if (currentZone.type === 'image') {
            sendImage(currentZone, note, submitBtn, feedback);
        } else {
            var proposed = (document.getElementById('djs-proposed').value || '').trim();
            if (!proposed) {
                showError(feedback, submitBtn, 'Veuillez saisir le nouveau texte avant d\'envoyer.');
                return;
            }
            if (proposed === (currentZone.current || '').trim()) {
                showError(feedback, submitBtn, 'Le texte est identique à l\'actuel — modifiez-le avant d\'envoyer.');
                return;
            }
            sendText(currentZone, proposed, note, submitBtn, feedback);
        }
    }

    function showError(feedback, btn, msg) {
        feedback.className   = 'djs-error';
        feedback.textContent = '✗ ' + msg;
        btn.disabled         = false;
        btn.textContent      = 'Envoyer la suggestion ➜';
    }

    function sendText(zone, proposed, note, btn, feedback) {
        fetch(DJS.restUrl + 'suggestion', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': DJS.nonce },
            body: JSON.stringify({
                zone_id: zone.id, zone_label: zone.label, current: zone.current,
                proposed: proposed, note: note, page_url: DJS.pageUrl,
            }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) throw new Error();
            feedback.className   = 'djs-success';
            feedback.textContent = '✓ Suggestion envoyée ! Adam en a été notifié par email.';
            btn.textContent      = 'Envoyé ✓';
            setTimeout(closeModal, 2000);
        })
        .catch(function () { showError(feedback, btn, 'Une erreur est survenue. Réessayez.'); });
    }

    function sendImage(zone, note, btn, feedback) {
        var fileInput = document.getElementById('djs-file-input');
        var file = fileInput.files[0];
        if (!file && !note) {
            showError(feedback, btn, 'Choisissez une image ou ajoutez une note.');
            return;
        }
        var fd = new FormData();
        fd.append('zone_id', zone.id);
        fd.append('zone_label', zone.label);
        fd.append('current', zone.current);
        fd.append('note', note);
        fd.append('page_url', DJS.pageUrl);
        if (file) fd.append('image', file);

        fetch(DJS.restUrl + 'suggestion-image', {
            method: 'POST',
            headers: { 'X-WP-Nonce': DJS.nonce },
            body: fd,
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) throw new Error();
            feedback.className   = 'djs-success';
            feedback.textContent = '✓ Image envoyée ! Adam en a été notifié par email.';
            btn.textContent      = 'Envoyé ✓';
            setTimeout(closeModal, 2000);
        })
        .catch(function () { showError(feedback, btn, 'Une erreur est survenue. Réessayez.'); });
    }

    // ── Utils ────────────────────────────────────────────────────────────────
    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;')
                          .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Init ─────────────────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', buildUI);
    } else {
        buildUI();
    }
}());
