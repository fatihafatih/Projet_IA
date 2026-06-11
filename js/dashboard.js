const STEP = 6;
let activeCat = '', search = '', sortVal = 'default', all = [], filtered = [];

// ── Helpers visuels pour le cœur ─────────────────────────────────────────────
function setFavActive(btn) {
    btn.classList.add('is-fav');
    btn.title = 'Retirer des favoris';
    const svg = btn.querySelector('svg');
    if (svg) {
        svg.setAttribute('fill', '#E8455A');
        svg.setAttribute('stroke', '#E8455A');
    }
}

function setFavInactive(btn) {
    btn.classList.remove('is-fav');
    btn.title = 'Ajouter aux favoris';
    const svg = btn.querySelector('svg');
    if (svg) {
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
    }
}

function animateFav(btn) {
    btn.classList.remove('fav-animate');
    void btn.offsetWidth;
    btn.classList.add('fav-animate');
    setTimeout(() => btn.classList.remove('fav-animate'), 400);
}

// ── Modal de confirmation suppression ────────────────────────────────────────
function showConfirmModal(collectionName, onConfirm) {
    // Supprime un ancien modal s'il existe
    const old = document.getElementById('confirmModal');
    if (old) old.remove();

    const overlay = document.createElement('div');
    overlay.id = 'confirmModal';
    overlay.style.cssText = `
        position:fixed; inset:0; z-index:9999;
        background:rgba(0,0,0,0.55); backdrop-filter:blur(4px);
        display:flex; align-items:center; justify-content:center;
    `;

    overlay.innerHTML = `
        <div style="
            background:#1C2740; border:1px solid rgba(232,201,122,0.2);
            border-radius:16px; padding:32px 28px; max-width:420px; width:90%;
            box-shadow:0 20px 60px rgba(0,0,0,0.5);
            font-family:'DM Sans',sans-serif;
        ">
            <div style="font-size:2rem; text-align:center; margin-bottom:16px;">💔</div>
            <h3 style="
                margin:0 0 10px; text-align:center;
                font-size:1.1rem; font-weight:700; color:#F2F6FF;
            ">Retirer des favoris</h3>
            <p style="
                margin:0 0 24px; text-align:center;
                font-size:14px; color:#8FA0BC; line-height:1.6;
            ">
                Voulez-vous retirer cet outil de la collection
                <strong style="color:#E8C97A;">"${collectionName}"</strong> ?
                <br>Cette action supprimera l'entrée définitivement.
            </p>
            <div style="display:flex; gap:12px; justify-content:center;">
                <button id="confirmNo" style="
                    flex:1; padding:11px 20px; border-radius:8px; cursor:pointer;
                    background:transparent; border:1px solid rgba(255,255,255,0.1);
                    color:#8FA0BC; font-size:14px; font-weight:600;
                    transition:all .2s;
                ">Annuler</button>
                <button id="confirmYes" style="
                    flex:1; padding:11px 20px; border-radius:8px; cursor:pointer;
                    background:#E8455A; border:none;
                    color:#fff; font-size:14px; font-weight:700;
                    transition:all .2s;
                ">Retirer</button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);

    // Hover sur les boutons
    const btnNo  = overlay.querySelector('#confirmNo');
    const btnYes = overlay.querySelector('#confirmYes');

    btnNo.addEventListener('mouseenter', () => { btnNo.style.background  = 'rgba(255,255,255,0.05)'; btnNo.style.color = '#fff'; });
    btnNo.addEventListener('mouseleave', () => { btnNo.style.background  = 'transparent';             btnNo.style.color = '#8FA0BC'; });
    btnYes.addEventListener('mouseenter',() => { btnYes.style.background = '#ff6b7a'; });
    btnYes.addEventListener('mouseleave',() => { btnYes.style.background = '#E8455A'; });

    // Fermer sur clic extérieur
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) overlay.remove();
    });

    btnNo.addEventListener('click',  () => overlay.remove());
    btnYes.addEventListener('click', () => {
        overlay.remove();
        onConfirm();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    all = [...document.querySelectorAll('.model-item')];

    // Recherche en temps réel
    document.getElementById('searchInput').addEventListener('input', () => {
        search = document.getElementById('searchInput').value.toLowerCase().trim();
        apply();
    });

    // Recherche à la touche Entrée
    document.getElementById('searchInput').addEventListener('keydown', e => {
        if (e.key === 'Enter') {
            search = document.getElementById('searchInput').value.toLowerCase().trim();
            apply();
        }
    });

    // Filtres par catégorie
    document.querySelectorAll('.pill').forEach(p => p.addEventListener('click', () => {
        document.querySelectorAll('.pill').forEach(x => x.classList.remove('active'));
        p.classList.add('active');
        activeCat = p.dataset.cat.toLowerCase();
        apply();
    }));

    // ── Gestion des favoris ───────────────────────────────────────────────────
    let currentOutilId = null;

    // Ouvre le modal au clic sur un cœur
    document.querySelectorAll('.js-fav-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();

            animateFav(btn);
            currentOutilId = btn.dataset.id;

            const res  = await fetch(`../includes/favoris.php?action=get_collections&id_outil=${currentOutilId}`);
            const data = await res.json();

            if (data.error === 'non_connecte') {
                window.location.href = 'login.php';
                return;
            }

            // Remplit la liste des collections
            const container = document.getElementById('favCollections');
            container.innerHTML = '';
            data.collections.forEach(col => {
                const div = document.createElement('div');
                div.className = 'fav-col-item' + (col.is_fav == 1 ? ' is-fav' : '');
                div.dataset.colId   = col.ID_COLLECTIONS;
                div.dataset.colName = col.name;
                div.innerHTML = `
                    <span>${col.name}</span>
                    <span class="fav-col-check">${col.is_fav == 1 ? '❤️' : '🤍'}</span>
                `;
                div.addEventListener('click', () => toggleFavori(col.ID_COLLECTIONS, col.name, div));
                container.appendChild(div);
            });

            // Synchronise l'état visuel du cœur
            const anyFav = data.collections.some(c => c.is_fav == 1);
            if (anyFav) setFavActive(btn);
            else setFavInactive(btn);

            document.getElementById('favNewInput').value  = '';
            document.getElementById('favNewError').textContent = '';
            document.getElementById('favOverlay').style.display = 'flex';
        });
    });

    // ── Toggle favori dans une collection ────────────────────────────────────
    async function toggleFavori(colId, colName, divEl) {
        const isCurrent = divEl.classList.contains('is-fav');

        // Si l'outil EST déjà dans cette collection → demander confirmation avant de supprimer
        if (isCurrent) {
            showConfirmModal(colName, async () => {
                await doToggle(colId, divEl);
            });
        } else {
            // Ajout direct, pas de confirmation nécessaire
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
            const isFav = data.state === 'added';
            divEl.classList.toggle('is-fav', isFav);
            divEl.querySelector('.fav-col-check').textContent = isFav ? '❤️' : '🤍';

            // Met à jour le cœur de la carte
            const btn    = document.querySelector(`.js-fav-btn[data-id="${currentOutilId}"]`);
            const anyFav = [...document.querySelectorAll('#favCollections .fav-col-item')]
                .some(d => d.classList.contains('is-fav'));

            if (btn) {
                animateFav(btn);
                if (anyFav) setFavActive(btn);
                else        setFavInactive(btn);
            }
        }
    }

    // ── Créer une nouvelle collection ─────────────────────────────────────────
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

        if (data.error === 'existe_deja') {
            error.textContent = 'Cette collection existe déjà.';
            return;
        }

        if (data.success) {
            error.textContent = '';
            input.value = '';

            const container = document.getElementById('favCollections');
            const div = document.createElement('div');
            div.className       = 'fav-col-item';
            div.dataset.colId   = data.id;
            div.dataset.colName = data.name;
            div.innerHTML = `<span>${data.name}</span><span class="fav-col-check">🤍</span>`;
            div.addEventListener('click', () => toggleFavori(data.id, data.name, div));
            container.appendChild(div);
        }
    });

    // ── Fermer le modal collections ───────────────────────────────────────────
    document.getElementById('favClose').addEventListener('click', () => {
        document.getElementById('favOverlay').style.display = 'none';
    });

    document.getElementById('favOverlay').addEventListener('click', (e) => {
        if (e.target === document.getElementById('favOverlay')) {
            document.getElementById('favOverlay').style.display = 'none';
        }
    });
});

function doSearch() {
    search = document.getElementById('searchInput').value.toLowerCase().trim();
    apply();
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

    if (sortVal === 'rating_desc') filtered.sort((a, b) => +b.dataset.rating - +a.dataset.rating);
    if (sortVal === 'rating_asc')  filtered.sort((a, b) => +a.dataset.rating - +b.dataset.rating);

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
    const wrap = document.getElementById('showMoreWrap');
    const f    = wrap._filtered || all;
    wrap._shown = (wrap._shown || STEP) + STEP;
    f.forEach((el, i) => { if (i < wrap._shown) el.classList.remove('d-none'); });
    const rem = f.length - wrap._shown;
    if (rem <= 0) wrap.classList.add('d-none');
    else document.getElementById('remCount').textContent = rem + ' restants';
}