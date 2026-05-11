
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
    if (sortVal === 'rating_asc')  filtered.sort((a, b) => +a.dataset.rating - +b.dataset.rating);

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