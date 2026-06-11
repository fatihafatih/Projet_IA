/* ═══════════════════════════════════════════════════════════════
   dashboard.js — Référentiel IA
   Contient : filtres, tri, pagination, favoris, search history
═══════════════════════════════════════════════════════════════ */

const STEP = 6;
let activeCat = '', search = '', sortVal = 'default', all = [], filtered = [];

/* ══════════════════════════════════════════════════════════════
   HELPERS — FAVORIS
══════════════════════════════════════════════════════════════ */
function setFavActive(btn) {
    btn.classList.add('is-fav');
    btn.title = 'Retirer des favoris';
    const svg = btn.querySelector('svg');
    if (svg) { svg.setAttribute('fill', '#E8455A'); svg.setAttribute('stroke', '#E8455A'); }
}

function setFavInactive(btn) {
    btn.classList.remove('is-fav');
    btn.title = 'Ajouter aux favoris';
    const svg = btn.querySelector('svg');
    if (svg) { svg.setAttribute('fill', 'none'); svg.setAttribute('stroke', 'currentColor'); }
}

function animateFav(btn) {
    btn.classList.remove('fav-animate');
    void btn.offsetWidth;
    btn.classList.add('fav-animate');
    setTimeout(() => btn.classList.remove('fav-animate'), 400);
}

/* ══════════════════════════════════════════════════════════════
   MODAL CONFIRMATION SUPPRESSION FAVORI
══════════════════════════════════════════════════════════════ */
function showConfirmModal(collectionName, onConfirm) {
    const old = document.getElementById('confirmModal');
    if (old) old.remove();

    const overlay = document.createElement('div');
    overlay.id = 'confirmModal';
    overlay.style.cssText = `
        position:fixed;inset:0;z-index:9999;
        background:rgba(0,0,0,0.55);backdrop-filter:blur(4px);
        display:flex;align-items:center;justify-content:center;
    `;
    overlay.innerHTML = `
        <div style="background:#1C2740;border:1px solid rgba(232,201,122,0.2);
            border-radius:16px;padding:32px 28px;max-width:420px;width:90%;
            box-shadow:0 20px 60px rgba(0,0,0,0.5);font-family:'DM Sans',sans-serif;">
            <div style="font-size:2rem;text-align:center;margin-bottom:16px;">💔</div>
            <h3 style="margin:0 0 10px;text-align:center;font-size:1.1rem;font-weight:700;color:#F2F6FF;">
                Retirer des favoris</h3>
            <p style="margin:0 0 24px;text-align:center;font-size:14px;color:#8FA0BC;line-height:1.6;">
                Voulez-vous retirer cet outil de la collection
                <strong style="color:#E8C97A;">"${collectionName}"</strong> ?
                <br>Cette action supprimera l'entrée définitivement.</p>
            <div style="display:flex;gap:12px;justify-content:center;">
                <button id="confirmNo" style="flex:1;padding:11px 20px;border-radius:8px;cursor:pointer;
                    background:transparent;border:1px solid rgba(255,255,255,0.1);
                    color:#8FA0BC;font-size:14px;font-weight:600;transition:all .2s;">Annuler</button>
                <button id="confirmYes" style="flex:1;padding:11px 20px;border-radius:8px;cursor:pointer;
                    background:#E8455A;border:none;color:#fff;font-size:14px;font-weight:700;
                    transition:all .2s;">Retirer</button>
            </div>
        </div>`;
    document.body.appendChild(overlay);

    const btnNo  = overlay.querySelector('#confirmNo');
    const btnYes = overlay.querySelector('#confirmYes');
    btnNo.addEventListener('mouseenter',  () => { btnNo.style.background = 'rgba(255,255,255,0.05)'; btnNo.style.color = '#fff'; });
    btnNo.addEventListener('mouseleave',  () => { btnNo.style.background = 'transparent'; btnNo.style.color = '#8FA0BC'; });
    btnYes.addEventListener('mouseenter', () => { btnYes.style.background = '#ff6b7a'; });
    btnYes.addEventListener('mouseleave', () => { btnYes.style.background = '#E8455A'; });
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });
    btnNo.addEventListener('click',  () => overlay.remove());
    btnYes.addEventListener('click', () => { overlay.remove(); onConfirm(); });
}

/* ══════════════════════════════════════════════════════════════
   SEARCH HISTORY DROPDOWN
══════════════════════════════════════════════════════════════ */
let historyData = [];
let kbdIndex    = -1;

async function loadHistory() {
    if (!IS_LOGGED) { renderHistory([]); return; }
    try {
        const res   = await fetch('dashboard.php?action=get_history');
        historyData = await res.json();
    } catch (e) { historyData = []; }
    renderHistory(historyData);
}

function renderHistory(items, highlight = '') {
    const list  = document.getElementById('sdList');
    const empty = document.getElementById('sdEmpty');
    list.innerHTML = '';
    kbdIndex = -1;

    const visible = items.filter(h => h.query && h.query.trim() !== '');
    if (!visible.length) {
        list.style.display  = 'none';
        empty.style.display = 'block';
        return;
    }
    list.style.display  = '';
    empty.style.display = 'none';

    visible.forEach(item => {
        const li    = document.createElement('li');
        const nb    = item.nb_resultats;
        const nbTxt = nb > 0 ? `${nb} résultat${nb > 1 ? 's' : ''}` : 'aucun résultat';

        const queryHtml = highlight
            ? escHtml(item.query).replace(new RegExp(`(${escRegex(highlight)})`, 'gi'), '<mark>$1</mark>')
            : escHtml(item.query);

        const catChip = item.categorie
            ? `<span class="sd-cat-chip">· ${escHtml(item.categorie)}</span>` : '';

        li.innerHTML = `
            <div class="sd-item-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
            <div class="sd-item-text">
                <div class="sd-item-query">${queryHtml}${catChip}</div>
                <div class="sd-item-meta">${timeAgo(item.searched_at)}</div>
            </div>
            <span class="sd-item-badge">${nbTxt}</span>
            <button class="sd-item-del" title="Supprimer" data-id="${item.id}">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>`;

        li.addEventListener('click', e => {
            if (e.target.closest('.sd-item-del')) return;
            pickHistory(item);
        });
        li.querySelector('.sd-item-del').addEventListener('click', e => {
            e.stopPropagation();
            deleteHistoryItem(item.id, li);
        });
        list.appendChild(li);
    });
}

function pickHistory(item) {
    document.getElementById('searchInput').value = item.query;
    if (item.categorie) {
        document.querySelectorAll('.pill').forEach(p => p.classList.remove('active'));
        const pill = document.querySelector(`.pill[data-cat="${item.categorie}"]`);
        if (pill) { pill.classList.add('active'); activeCat = item.categorie.toLowerCase(); }
    }
    closeDropdown();
    search = item.query.toLowerCase().trim();
    apply();
}

// Frappe → recherche en temps réel + filtrage historique
function onSearchTyping() {
    const q = document.getElementById('searchInput').value.trim();

    // ── Recherche en temps réel ──
    search = q.toLowerCase();
    apply();

    // ── Filtrage de l'historique dans la dropdown ──
    if (!q) {
        renderHistory(historyData);
    } else {
        const ql = q.toLowerCase();
        renderHistory(historyData.filter(h => (h.query || '').toLowerCase().includes(ql)), q);
    }
    document.getElementById('searchDropdown').classList.add('open');
}

// Navigation clavier
function onSearchKeydown(e) {
    const dd    = document.getElementById('searchDropdown');
    const items = [...document.querySelectorAll('#sdList li')];
    const input = document.getElementById('searchInput');

    if (!dd.classList.contains('open')) {
        if (e.key === 'ArrowDown') { openDropdown(); return; }
        if (e.key === 'Enter')     { closeDropdown(); doSearch(); return; }
        return;
    }

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        kbdIndex = Math.min(kbdIndex + 1, items.length - 1);
        highlightKbd(items, input);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        kbdIndex = Math.max(kbdIndex - 1, -1);
        highlightKbd(items, input);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (kbdIndex >= 0 && items[kbdIndex]) items[kbdIndex].click();
        else { closeDropdown(); doSearch(); }
    } else if (e.key === 'Escape') {
        closeDropdown();
    }
}

function highlightKbd(items, input) {
    items.forEach((li, i) => li.classList.toggle('kbd-active', i === kbdIndex));
    if (kbdIndex >= 0 && items[kbdIndex]) {
        const raw = items[kbdIndex].querySelector('.sd-item-query')?.textContent?.split('·')[0]?.trim();
        if (raw) input.value = raw;
    }
}

async function openDropdown() {
    await loadHistory();
    document.getElementById('searchDropdown').classList.add('open');
}

function closeDropdown() {
    document.getElementById('searchDropdown').classList.remove('open');
    kbdIndex = -1;
}

async function deleteHistoryItem(id, li) {
    li.style.opacity = '.4';
    li.style.transition = 'opacity .2s';
    try {
        await fetch('dashboard.php?action=delete_history', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
    } catch (e) {}
    historyData = historyData.filter(h => h.id !== id);
    const q = document.getElementById('searchInput').value.trim();
    if (q) {
        const ql = q.toLowerCase();
        renderHistory(historyData.filter(h => (h.query || '').toLowerCase().includes(ql)), q);
    } else {
        renderHistory(historyData);
    }
}

async function clearAllHistory(e) {
    e.stopPropagation();
    if (!confirm('Effacer tout l\'historique de recherche ?')) return;
    try {
        await fetch('dashboard.php?action=delete_history', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ all: true })
        });
    } catch (e) {}
    historyData = [];
    renderHistory([]);
}

async function saveSearch(query, categorie, nbResultats) {
    if (!IS_LOGGED || !query.trim()) return;
    try {
        await fetch('dashboard.php?action=save_history', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query, categorie, nb_resultats: nbResultats })
        });
    } catch (e) {}
}

/* ══════════════════════════════════════════════════════════════
   HELPERS GÉNÉRAUX
══════════════════════════════════════════════════════════════ */
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escRegex(s) {
    return String(s).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
function timeAgo(dateStr) {
    if (!dateStr) return '';
    const diff = (Date.now() - new Date(dateStr)) / 1000;
    if (diff < 60)     return 'À l\'instant';
    if (diff < 3600)   return `Il y a ${Math.floor(diff / 60)} min`;
    if (diff < 86400)  return `Il y a ${Math.floor(diff / 3600)} h`;
    if (diff < 604800) return `Il y a ${Math.floor(diff / 86400)} j`;
    return new Date(dateStr).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
}

/* ══════════════════════════════════════════════════════════════
   FILTRES, TRI, PAGINATION
══════════════════════════════════════════════════════════════ */
function doSearch() {
    search = document.getElementById('searchInput').value.toLowerCase().trim();
    closeDropdown();
    apply();
    // Sauvegarde uniquement au clic / Entrée
    if (search) saveSearch(search, activeCat, filtered.length);
}

function doSort(v) { sortVal = v; apply(); }

function apply() {
    const grid = document.getElementById('grid');

    filtered = all.filter(el => {
        const mc = !activeCat || el.dataset.cat.includes(activeCat);
        const ms = !search || [el.dataset.nom, el.dataset.cat, el.dataset.desc]
            .some(s => s && s.includes(search));
        return mc && ms;
    });

    // Tri : favoris d'abord, puis critère choisi
    filtered.sort((a, b) => {
        const fa = a.dataset.fav === '1' ? 0 : 1;
        const fb = b.dataset.fav === '1' ? 0 : 1;
        if (fa !== fb) return fa - fb;           // favoris en tête
        if (sortVal === 'rating_desc') return +b.dataset.rating - +a.dataset.rating;
        if (sortVal === 'rating_asc')  return +a.dataset.rating - +b.dataset.rating;
        return 0;
    });

    all.forEach(el => el.classList.add('d-none'));
    filtered.forEach((el, i) => {
        grid.appendChild(el);
        if (i < STEP) el.classList.remove('d-none');
    });

    const n = filtered.length;
    document.getElementById('countBadge').textContent = n + ' outil' + (n > 1 ? 's' : '');
    document.getElementById('noResults').style.display = n ? 'none' : 'block';

    const wrap = document.getElementById('showMoreWrap');
    if (wrap) {
        wrap._filtered = filtered;
        wrap._shown    = STEP;
        const rem = n - STEP;
        if (rem > 0) {
            wrap.classList.remove('d-none');
            document.getElementById('remCount').textContent = rem + ' restants';
        } else {
            wrap.classList.add('d-none');
        }
    }
}

function showMore() {
    const wrap  = document.getElementById('showMoreWrap');
    const f     = wrap._filtered || all;
    wrap._shown = (wrap._shown || STEP) + STEP;
    f.forEach((el, i) => { if (i < wrap._shown) el.classList.remove('d-none'); });
    const rem = f.length - wrap._shown;
    if (rem <= 0) wrap.classList.add('d-none');
    else document.getElementById('remCount').textContent = rem + ' restants';
}

/* ══════════════════════════════════════════════════════════════
   INIT
══════════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
    all = [...document.querySelectorAll('.model-item')];

    /* ── Recherche ─────────────────────────────────────────── */
    const input = document.getElementById('searchInput');
    input.addEventListener('input',   onSearchTyping);
    input.addEventListener('focus',   openDropdown);
    input.addEventListener('keydown', onSearchKeydown);

    const clearBtn = document.getElementById('sdClearAll');
    if (clearBtn) clearBtn.addEventListener('click', clearAllHistory);

    document.addEventListener('click', e => {
        if (!document.getElementById('searchContainer').contains(e.target)) closeDropdown();
    });

    /* ── Filtres catégorie ─────────────────────────────────── */
    document.querySelectorAll('.pill').forEach(p => p.addEventListener('click', () => {
        document.querySelectorAll('.pill').forEach(x => x.classList.remove('active'));
        p.classList.add('active');
        activeCat = p.dataset.cat.toLowerCase();
        apply();
    }));

    /* ── Favoris ───────────────────────────────────────────── */
    let currentOutilId = null;

    document.querySelectorAll('.js-fav-btn').forEach(btn => {
        btn.addEventListener('click', async e => {
            e.preventDefault();
            e.stopPropagation();
            animateFav(btn);
            currentOutilId = btn.dataset.id;

            const res  = await fetch(`../includes/favoris.php?action=get_collections&id_outil=${currentOutilId}`);
            const data = await res.json();
            if (data.error === 'non_connecte') { window.location.href = 'login.php'; return; }

            const container = document.getElementById('favCollections');
            container.innerHTML = '';
            data.collections.forEach(col => {
                const div = document.createElement('div');
                div.className       = 'fav-col-item' + (col.is_fav == 1 ? ' is-fav' : '');
                div.dataset.colId   = col.ID_COLLECTIONS;
                div.dataset.colName = col.name;
                div.innerHTML = `<span>${col.name}</span><span class="fav-col-check">${col.is_fav == 1 ? '❤️' : '🤍'}</span>`;
                div.addEventListener('click', () => toggleFavori(col.ID_COLLECTIONS, col.name, div));
                container.appendChild(div);
            });

            const anyFav = data.collections.some(c => c.is_fav == 1);
            if (anyFav) setFavActive(btn); else setFavInactive(btn);

            document.getElementById('favNewInput').value       = '';
            document.getElementById('favNewError').textContent = '';
            document.getElementById('favOverlay').style.display = 'flex';
        });
    });

    async function toggleFavori(colId, colName, divEl) {
        if (divEl.classList.contains('is-fav')) {
            showConfirmModal(colName, async () => { await doToggle(colId, divEl); });
        } else {
            await doToggle(colId, divEl);
        }
    }

    async function doToggle(colId, divEl) {
        const formData = new FormData();
        formData.append('action',        'toggle_favori');
        formData.append('id_outil',      currentOutilId);
        formData.append('id_collection', colId);

        const res  = await fetch('../includes/favoris.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            const isFav  = data.state === 'added';
            divEl.classList.toggle('is-fav', isFav);
            divEl.querySelector('.fav-col-check').textContent = isFav ? '❤️' : '🤍';

            const btn    = document.querySelector(`.js-fav-btn[data-id="${currentOutilId}"]`);
            const card   = btn?.closest('.model-item');
            const anyFav = [...document.querySelectorAll('#favCollections .fav-col-item')]
                .some(d => d.classList.contains('is-fav'));

            if (btn) {
                animateFav(btn);
                if (anyFav) setFavActive(btn); else setFavInactive(btn);
            }

            // Met à jour data-fav sur la carte pour que le tri fonctionne
            if (card) {
                card.dataset.fav = anyFav ? '1' : '0';
                // Met à jour le badge visuel sans recharger
                const existingBadge = card.querySelector('.fav-badge');
                if (anyFav && !existingBadge) {
                    const badge = document.createElement('span');
                    badge.className   = 'fav-badge';
                    badge.textContent = '❤️ Favori';
                    card.querySelector('.card').prepend(badge);
                    card.querySelector('.card').style.border      = '1.5px solid rgba(232,69,90,.30)';
                    card.querySelector('.card').style.boxShadow   = '0 0 0 3px rgba(232,69,90,.06)';
                } else if (!anyFav && existingBadge) {
                    existingBadge.remove();
                    card.querySelector('.card').style.border    = '';
                    card.querySelector('.card').style.boxShadow = '';
                }
            }

            // Re-trier les cartes en live
            apply();
        }
    }

    document.getElementById('favNewBtn').addEventListener('click', async () => {
        const input = document.getElementById('favNewInput');
        const error = document.getElementById('favNewError');
        const name  = input.value.trim();
        if (!name) { error.textContent = 'Entrez un nom de collection.'; return; }

        const formData = new FormData();
        formData.append('action', 'create_collection');
        formData.append('name',   name);

        const res  = await fetch('../includes/favoris.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.error === 'existe_deja') { error.textContent = 'Cette collection existe déjà.'; return; }
        if (data.success) {
            error.textContent = '';
            input.value = '';
            const container = document.getElementById('favCollections');
            const div       = document.createElement('div');
            div.className       = 'fav-col-item';
            div.dataset.colId   = data.id;
            div.dataset.colName = data.name;
            div.innerHTML = `<span>${data.name}</span><span class="fav-col-check">🤍</span>`;
            div.addEventListener('click', () => toggleFavori(data.id, data.name, div));
            container.appendChild(div);
        }
    });

    document.getElementById('favClose').addEventListener('click', () => {
        document.getElementById('favOverlay').style.display = 'none';
    });
    document.getElementById('favOverlay').addEventListener('click', e => {
        if (e.target === document.getElementById('favOverlay'))
            document.getElementById('favOverlay').style.display = 'none';
    });

    // Tri initial : favoris en tête au chargement
    apply();
});