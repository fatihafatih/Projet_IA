
const STEP = 6;
let activeCat = '', search = '', sortVal = 'default', all = [], filtered = [];

document.addEventListener('DOMContentLoaded', () => {
    all = [...document.querySelectorAll('.model-item')]; // ... => le spread operator
    // transformer le NodeList retourné par querySelectorAll() en vrai tableau.

    // Recherche en temps réel
    document.getElementById('searchInput').addEventListener('input', () => {
        search = document.getElementById('searchInput').value.toLowerCase().trim();
        apply();
    });

    // Recherche à la touche Entrée (déjà présente, on garde)
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
    // ── Gestion des favoris ─────────────────────────────────────────────────
    let currentOutilId = null;

    // Ouvre le modal au clic sur un cœur
    document.querySelectorAll('.js-fav-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();

            currentOutilId = btn.dataset.id;

            // Charge les collections via l'API
            const res = await fetch(`../includes/favoris.php?action=get_collections&id_outil=${currentOutilId}`);
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
                div.dataset.colId = col.ID_COLLECTIONS;
                div.innerHTML = `
                <span>${col.name}</span>
                <span class="fav-col-check">${col.is_fav == 1 ? '❤️' : '🤍'}</span>
            `;
                div.addEventListener('click', () => toggleFavori(col.ID_COLLECTIONS, div));
                container.appendChild(div);
            });

            // Met à jour l'état du cœur de la carte
            const anyFav = data.collections.some(c => c.is_fav == 1);
            btn.classList.toggle('is-fav', anyFav);

            // Affiche le modal
            document.getElementById('favNewInput').value = '';
            document.getElementById('favNewError').textContent = '';
            document.getElementById('favOverlay').style.display = 'flex';
        });
    });

    // Toggle favori dans une collection
    async function toggleFavori(colId, divEl) {
        const formData = new FormData();
        formData.append('action', 'toggle_favori');
        formData.append('id_outil', currentOutilId);
        formData.append('id_collection', colId);

        const res = await fetch('../includes/favoris.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            const isFav = data.state === 'added';
            divEl.classList.toggle('is-fav', isFav);
            divEl.querySelector('.fav-col-check').textContent = isFav ? '❤️' : '🤍';

            // Met à jour le cœur de la carte
            const btn = document.querySelector(`.js-fav-btn[data-id="${currentOutilId}"]`);
            const anyFav = [...document.querySelectorAll(`#favCollections .fav-col-item`)]
                .some(d => d.classList.contains('is-fav'));
            if (btn) btn.classList.toggle('is-fav', anyFav);
        }
    }

    // Créer une nouvelle collection
    document.getElementById('favNewBtn').addEventListener('click', async () => {
        const input = document.getElementById('favNewInput');
        const error = document.getElementById('favNewError');
        const name = input.value.trim();

        if (!name) { error.textContent = 'Entrez un nom de collection.'; return; }

        const formData = new FormData();
        formData.append('action', 'create_collection');
        formData.append('name', name);

        const res = await fetch('../includes/favoris.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.error === 'existe_deja') {
            error.textContent = 'Cette collection existe déjà.';
            return;
        }

        if (data.success) {
            error.textContent = '';
            input.value = '';

            // Ajoute la nouvelle collection dans la liste
            const container = document.getElementById('favCollections');
            const div = document.createElement('div');
            div.className = 'fav-col-item';
            div.dataset.colId = data.id;
            div.innerHTML = `<span>${data.name}</span><span class="fav-col-check">🤍</span>`;
            div.addEventListener('click', () => toggleFavori(data.id, div));
            container.appendChild(div);
        }
    });

    // Fermer le modal
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
        //si active est remplie=> !true || 
        //retourne si la categorie de lelement de la grid contient la cat du activeCat
        const ms = !search || [el.dataset.nom, el.dataset.cat, el.dataset.desc]
            //un tableau contenant les infos de l'outil             
            .some(s => s && s.includes(search)); // l'attribut est existant et contient le mot cle

        //retourne si le mot cle entree dans la barre de recherche se trouve
        //dans le nom/categorie ou description de l'outil
        return mc && ms;
    });

    // Tris
    if (sortVal === 'rating_desc') filtered.sort((a, b) => +b.dataset.rating - +a.dataset.rating);
    //a et b sont deux elements de filtered / le + sert a convertir le rating en float
    //sort() utilise : résultat négatif → a avant b / positif → b avant a / 0 → égaux
    if (sortVal === 'rating_asc') filtered.sort((a, b) => +a.dataset.rating - +b.dataset.rating);

    // Masquer tous les éléments
    all.forEach(el => el.classList.add('d-none'));

    // Réordonner dans le DOM selon le tri, et afficher les STEP premiers
    filtered.forEach((el, i) => {
        grid.appendChild(el); // réordonne
        if (i < STEP) el.classList.remove('d-none');
    });

    const n = filtered.length;
    document.getElementById('countBadge').textContent = n + ' outil' + (n > 1 ? 's' : '');
    document.getElementById('noResults').style.display = n ? 'none' : 'block';

    const wrap = document.getElementById('showMoreWrap');
    if (wrap) {
        wrap._filtered = filtered;
        //stocke dans lelement html la liste des elements a afficher
        wrap._shown = STEP;
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
    const f = wrap._filtered || all;
    wrap._shown = (wrap._shown || STEP) + STEP; // Augmenter le nombre d'éléments affichés
    f.forEach((el, i) => { if (i < wrap._shown) el.classList.remove('d-none'); });
    const rem = f.length - wrap._shown;
    if (rem <= 0) wrap.classList.add('d-none');
    else document.getElementById('remCount').textContent = rem + ' restants';
}