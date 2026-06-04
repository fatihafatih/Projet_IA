<?php
require_once '../../includes/connexionbd.php';
//session_start();

// Protection de la page : l'utilisateur doit être connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

/* =========================================================
   SUPPRESSION D'UN FAVORI
========================================================= */
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {

    $stmt = $pdo->prepare("
        DELETE FROM favoris
        WHERE ID_OUTILS_IA = ?
        AND ID_USERS = ?
    ");

    $stmt->execute([
        $_GET['remove'],
        $userId
    ]);

    $catParam = isset($_GET['cat']) ? '&cat=' . urlencode($_GET['cat']) : '';
    $colParam = isset($_GET['col']) ? '&col=' . urlencode($_GET['col']) : '';
    header('Location: favoris.php?' . $catParam . $colParam);
    exit;
}

$pageTitle = 'Mon Espace Favoris';
require 'layout.php';

/* =========================================================
   RECUPERER LES COLLECTIONS DE L'UTILISATEUR (Colonnes Corrigées)
========================================================= */
$stmtCol = $pdo->prepare("
    SELECT ID_COLLECTIONS, name 
    FROM collections 
    WHERE ID_USERS = ? 
    ORDER BY name ASC
");
$stmtCol->execute([$userId]);
$userCollections = $stmtCol->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   RECUPERER LES FAVORIS AVEC JOINTURES EXACTES
========================================================= */
$stmt = $pdo->prepare("
    SELECT
        o.ID_OUTILS_IA,
        o.nom,
        o.description,
        o.logo_url,
        o.url,
        o.global_rating,
        c.name AS categorie,
        col.name AS collection_nom,
        col.ID_COLLECTIONS AS ID_COLLECTIONS
    FROM favoris f

    INNER JOIN outils_ia o
        ON o.ID_OUTILS_IA = f.ID_OUTILS_IA

    LEFT JOIN categorie c
        ON c.ID_CATEGORIE = o.ID_CATEGORIE

    LEFT JOIN collections col
        ON col.ID_COLLECTIONS = f.ID_COLLECTIONS

    WHERE f.ID_USERS = ?

    ORDER BY o.nom ASC
");

$stmt->execute([$userId]);
$favoris = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   TRAITEMENT DES FILTRES
========================================================= */
$categories = array_unique(array_filter(array_column($favoris, 'categorie')));

$filtreCategorie = $_GET['cat'] ?? 'tous';
$filtreCollection = $_GET['col'] ?? 'tous';

$favorisAffiches = array_filter($favoris, function($f) use ($filtreCategorie, $filtreCollection) {
    $matchCat = ($filtreCategorie === 'tous' || $f['categorie'] === $filtreCategorie);
    $matchCol = ($filtreCollection === 'tous' || (string)$f['ID_COLLECTIONS'] === $filtreCollection);
    return $matchCat && $matchCol;
});

?>
<style>

body {
    margin: 0;
    min-height: 100vh;
    font-family: 'Segoe UI', Roboto, sans-serif;
    color: #1E293B;
    background: #FAF9F6; /* Blanc cassé luxury */
    background-image: 
        radial-gradient(circle at 80% 20%, rgba(255, 217, 0, 0.1), transparent 40%),
        radial-gradient(circle at 15% 50%, rgba(250, 237, 205, 0.7), transparent 45%),
        radial-gradient(circle at 50% 80%, rgba(241, 245, 249, 1), transparent 50%);
    background-attachment: fixed;
    position: relative;
}

/* Un petit effet de lueur subtile en haut de l'écran */
body::before {
    content: "";
    position: absolute;
    top: 0; left: 25%; right: 25%; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(245, 184, 0, 0.4), transparent);
    pointer-events: none;
}
.category-badge {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12.5px;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid rgba(0,0,0,0.05);
    transition: all 0.2s ease;
}

/* --- DESIGN DES COLLECTIONS --- */
.collection-tabs-container {
    display: flex;
    gap: 6px;
    overflow-x: auto;
    margin-top: 15px;
    border-bottom: 2px solid #E2E8F0;
}
.collection-tab {
    position: relative;
    padding: 10px 20px 8px 16px;
    font-size: 13.5px;
    font-weight: 700;
    text-decoration: none;
    color: #64748B;
    background: #E2E8F0;
    border-radius: 12px 12px 0 0;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.25s ease;
    top: 2px;
}
.collection-tab.active {
    background: #FFFFFF;
    color: #0F172A;
    border: 1px solid #E2E8F0;
    border-bottom: none;
    padding-bottom: 10px;
    top: 0;
    z-index: 2;
}
.collection-tab.active::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--gold, #F5B800);
    border-radius: 12px 12px 0 0;
}

.card-collection-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    background: #FFFBEB;
    color: #B45309;
    border: 1px solid #FEF3C7;
    border-radius: 6px;
    font-size: 10.5px;
    font-weight: 700;
    margin-top: 6px;
}
</style>

<div style="margin-bottom: 30px;">
    
    <div style="margin-bottom: 25px; background: rgba(255,255,255,0.6); padding: 14px; border-radius: 12px; border: 1px solid rgba(0,0,0,0.02);">
        <span style="font-size: 11px; font-weight: 800; color: #94A3B8; display: block; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.8px;">Filtrer par type d'IA</span>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="?cat=tous&col=<?= urlencode($filtreCollection) ?>" 
               class="category-badge"
               style="background: <?= $filtreCategorie === 'tous' ? 'var(--dark, #0F172A)' : '#FFFFFF' ?>; color: <?= $filtreCategorie === 'tous' ? '#FFFFFF' : '#475569' ?>;">
               Tous les types
            </a>
            <?php foreach ($categories as $cat): ?>
                <a href="?cat=<?= urlencode($cat) ?>&col=<?= urlencode($filtreCollection) ?>" 
                   class="category-badge"
                   style="background: <?= $filtreCategorie === $cat ? 'var(--dark, #0F172A)' : '#FFFFFF' ?>; color: <?= $filtreCategorie === $cat ? '#FFFFFF' : '#475569' ?>;">
                   <?= htmlspecialchars($cat) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div>
        <span style="font-size: 11px; font-weight: 800; color: #94A3B8; display: block; margin-left: 4px; text-transform: uppercase; letter-spacing: 0.8px;">Mes Collections Personnelles</span>
        
        <div class="collection-tabs-container">
            <a href="?cat=<?= urlencode($filtreCategorie) ?>&col=tous" 
               class="collection-tab <?= $filtreCollection === 'tous' ? 'active' : '' ?>">
                <span></span> Toutes les sélections
            </a>

            <?php foreach ($userCollections as $col): ?>
                <?php $isCurrent = ($filtreCollection == $col['ID_COLLECTIONS']); ?>
                <a href="?cat=<?= urlencode($filtreCategorie) ?>&col=<?= $col['ID_COLLECTIONS'] ?>" 
                   class="collection-tab <?= $isCurrent ? 'active' : '' ?>">
                    <span><?= $isCurrent ? '📂' : '📁' ?></span>
                    <?= htmlspecialchars($col['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 0 4px;">
    <h2 style="font-size: 16px; font-weight: 800; color: #1E293B; margin: 0;">
        <?= $filtreCollection === 'tous' ? 'Tous mes favoris' : 'Contenu de la collection' ?>
    </h2>
    <span style="font-size: 12.5px; color: #64748B; font-weight: 600; background: #EDF2F7; padding: 4px 10px; border-radius: 8px;">
        <?= count($favorisAffiches) ?> outil<?= count($favorisAffiches) > 1 ? 's' : '' ?>
    </span>
</div>

<?php if (empty($favorisAffiches)): ?>

    <div style="padding: 50px 20px; text-align: center; background: white; border-radius: 16px; border: 1px solid #E2E8F0;">
        <div style="font-size: 45px; margin-bottom: 14px;">📁</div>
        <h3 style="margin: 0; font-size: 16px; color: #334155; font-weight: 700;">Aucun outil ici</h3>
        <p style="color: #94A3B8; font-size: 13px; margin-top: 6px;">
            Pour voir des dossiers s'afficher ici, crée une collection en base de données avec ton `ID_USERS` (actuellement : <?= $userId ?>).
        </p>
    </div>

<?php else: ?>

<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 20px;">

<?php foreach ($favorisAffiches as $fav): ?>

    <div class="card" style="position: relative; display: flex; flex-direction: column; background: white; border-radius: 16px; border: 1px solid #E2E8F0; box-shadow: 0 4px 20px rgba(0,0,0,0.02); overflow: hidden;">

        <a href="?remove=<?= $fav['ID_OUTILS_IA'] ?>&cat=<?= urlencode($filtreCategorie) ?>&col=<?= urlencode($filtreCollection) ?>"
           onclick="return confirm('Retirer cet outil ?')"
           style="position: absolute; top: 14px; right: 14px; width: 26px; height: 26px; background: #FEE2E2; color: #DC2626; border-radius: 50%; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 11px; font-weight: bold; z-index: 10;">
            ✕
        </a>

        <div style="padding: 20px; display: flex; gap: 14px; align-items: flex-start;">
            <div style="width: 52px; height: 52px; border-radius: 12px; overflow: hidden; background: #F8FAFC; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border: 1px solid #E2E8F0;">
                <?php if (!empty($fav['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($fav['logo_url']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <span style="font-size: 22px;">🤖</span>
                <?php endif; ?>
            </div>

            <div style="flex-grow: 1; min-width: 0;">
                <div style="font-weight: 800; font-size: 15px; color: #0F172A; line-height: 1.3; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    <?= htmlspecialchars($fav['nom']) ?>
                </div>
                <div style="font-size: 11.5px; color: #64748B; margin-top: 2px;">
                    <?= htmlspecialchars($fav['categorie'] ?? 'Général') ?>
                </div>

                <?php if (!empty($fav['collection_nom'])): ?>
                    <div>
                        <div class="card-collection-tag">
                            📁 <?= htmlspecialchars($fav['collection_nom']) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div style="padding: 0 20px 14px; color: #475569; font-size: 13.5px; line-height: 1.4; flex-grow: 1;">
            <?= htmlspecialchars($fav['description']) ?>
        </div>

        <div style="padding: 0 20px 16px; color: #F5B800; font-size: 13px;">
            <?= str_repeat('★', round($fav['global_rating'])) ?>
            <?= str_repeat('☆', 5 - round($fav['global_rating'])) ?>
        </div>

        <div style="margin-top: auto; padding: 14px 20px; background: #F8FAFC; border-top: 1px solid #E2E8F0;">
            <a href="<?= htmlspecialchars($fav['url']) ?>" target="_blank"
               style="display: block; text-align: center; background: var(--gold, #fae799); padding: 10px; border-radius: 10px; text-decoration: none; font-weight: 700; color: #0F172A; font-size: 13px;">
                Ouvrir l'application
            </a>
        </div>
    </div>

<?php endforeach; ?>

</div>

<?php endif; ?>

<?php require 'layout_end.php'; ?>