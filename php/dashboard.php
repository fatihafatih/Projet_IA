<?php
session_start();
require_once '../includes/connexionbd.php';

// Récupérer les outils actifs
$stmt = $pdo->prepare("
    SELECT 
        o.ID_OUTILS_IA,
        o.nom,
        o.description,
        o.logo_url,
        o.url,
        o.global_rating,
        o.version,
        o.status,
        c.name AS categorie
    FROM outils_ia o
    LEFT JOIN categorie c ON o.ID_CATEGORIE = c.ID_CATEGORIE
    LEFT JOIN performance p ON p.ID_OUTILS_IA = o.ID_OUTILS_IA
    WHERE o.status = 'actif'
    GROUP BY o.ID_OUTILS_IA
    ORDER BY o.ID_OUTILS_IA DESC
");
$stmt->execute();
$outils = $stmt->fetchAll();
$total = count($outils);

// ── IDs des outils déjà en favoris pour l'utilisateur connecté ──────────────
// Table : favoris(ID_FAVORIS, ID_USERS, ID_OUTILS_IA, ID_COLLECTIONS)
$fav_ids = [];
if (isset($_SESSION['user_id'])) {
  $fav_stmt = $pdo->prepare("
        SELECT DISTINCT ID_OUTILS_IA
        FROM favoris
        WHERE ID_USERS = ?
    ");
  $fav_stmt->execute([$_SESSION['user_id']]);
  $fav_ids = array_map('intval', $fav_stmt->fetchAll(PDO::FETCH_COLUMN));
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Référentiel d'outils IA</title>
  <link rel="stylesheet" href="../styles/dashboard.css">
  <link rel="stylesheet" href="../styles/favoris.css">
</head>

<!-- ══ MODAL COLLECTIONS ══════════════════════════════════════════════════ -->
<div class="fav-overlay" id="favOverlay" style="display:none">
  <div class="fav-modal" id="favModal">
    <div class="fav-modal-head">
      <span>💾 Sauvegarder dans…</span>
      <button class="fav-close" id="favClose">✕</button>
    </div>
    <div class="fav-collections" id="favCollections"></div>
    <div class="fav-new" id="favNewWrap">
      <input type="text" id="favNewInput" placeholder="Nom de la nouvelle collection…" maxlength="100">
      <button id="favNewBtn">Créer</button>
    </div>
    <p class="fav-new-error" id="favNewError"></p>
  </div>
</div>

<body>

  <?php include "../includes/header.php"; ?>

  <!-- Hero / Recherche -->
  <section class="hero">
    <div class="hero-inner">
      <div class="eyebrow"><span class="eyebrow-dot"></span>Référentiel IA</div>
      <h1>Les meilleurs outils<br><b>d'intelligence artificielle</b></h1>
      <p class="hero-sub">
        Explorez, comparez et accédez aux outils IA les plus performants, réunis en un seul endroit.
      </p>

      <div class="search-wrap">
        <img src="../public/recherche.png" alt="logo recherche" class="search-icon">
        <input type="text" id="searchInput" placeholder="Rechercher un outil, une catégorie…">
        <button class="btn-search" onclick="doSearch()">Rechercher</button>
      </div>

      <!-- Filtres par catégorie -->
      <div class="filters" id="filterBar">
        <span class="pill active" data-cat="">Tous</span>
        <?php
        $cats = array_unique(array_column($outils, 'categorie'));
        foreach ($cats as $c):
          if ($c): ?>
            <span class="pill" data-cat="<?= htmlspecialchars($c) ?>">
              <?= htmlspecialchars($c) ?>
            </span>
          <?php endif; endforeach; ?>
      </div>
    </div>
  </section>

  <!-- Liste des outils -->
  <main class="main">

    <div class="toolbar">
      <div class="toolbar-l">
        <h2>Outils disponibles</h2>
        <span class="count-badge" id="countBadge">
          <?= $total ?> outil<?= $total > 1 ? 's' : '' ?>
        </span>
      </div>
      <select class="sort-select" onchange="doSort(this.value)">
        <option value="default">Par défaut</option>
        <option value="rating_desc">Mieux notés</option>
        <option value="rating_asc">Moins notés</option>
      </select>
    </div>

    <div class="grid" id="grid">
      <div class="no-results" id="noResults" style="display:none;">
        <p>Aucun outil trouvé — essayez un autre mot-clé.</p>
      </div>

      <?php foreach ($outils as $i => $o):
        $hidden = $i >= 6 ? 'd-none' : '';
        $rating = number_format($o['global_rating'], 1);
        $version = $o['version'] ? 'v' . number_format($o['version'], 1) : '';
        $is_fav = in_array((int) $o['ID_OUTILS_IA'], $fav_ids);
        ?>
        <div class="model-item <?= $hidden ?>" data-nom="<?= strtolower(htmlspecialchars($o['nom'])) ?>"
          data-cat="<?= strtolower(htmlspecialchars($o['categorie'] ?? '')) ?>"
          data-desc="<?= strtolower(htmlspecialchars($o['description'] ?? '')) ?>"
          data-rating="<?= (float) $o['global_rating'] ?>">

          <div class="card">
            <div class="card-top">
              <div class="c-logo">
                <?php if ($o['logo_url']): ?>
                  <img src="<?= htmlspecialchars($o['logo_url']) ?>" alt="<?= htmlspecialchars($o['nom']) ?>">
                <?php else: ?>
                  <div class="c-logo-placeholder">IA</div>
                <?php endif; ?>
              </div>
              <div style="min-width:0;">
                <div class="c-name"><?= htmlspecialchars($o['nom']) ?></div>
                <span class="c-cat"><?= htmlspecialchars($o['categorie'] ?? 'Non classé') ?></span>
                <?php if ($version): ?>
                  <span class="c-version"><?= $version ?></span>
                <?php endif; ?>
              </div>
            </div>

            <p class="c-desc">
              <?= htmlspecialchars($o['description'] ?: 'Aucune description disponible.') ?>
            </p>

            <div class="c-foot">
              <span class="c-rating">★ <?= $rating ?></span>

              <div style="display:flex; align-items:center; gap:8px;">
                <?php if (isset($_SESSION['user_id'])): ?>
                  <button class="btn-fav js-fav-btn <?= $is_fav ? 'is-fav' : '' ?>" data-id="<?= $o['ID_OUTILS_IA'] ?>"
                    title="<?= $is_fav ? 'Retirer des favoris' : 'Ajouter aux favoris' ?>">
                    <svg viewBox="0 0 24 24" fill="<?= $is_fav ? '#E8455A' : 'none' ?>"
                      stroke="<?= $is_fav ? '#E8455A' : 'currentColor' ?>" stroke-width="2">
                      <path
                        d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
                    </svg>
                  </button>
                <?php endif; ?>

                <?php if ($o['url']): ?>
                  <a class="btn-see" href="outil.php?id=<?= $o['ID_OUTILS_IA'] ?>">Voir →</a>
                <?php else: ?>
                  <span class="btn-see btn-off">Indisponible</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($total > 6): ?>
      <div class="show-more" id="showMoreWrap">
        <button id="showMoreBtn" onclick="showMore()">
          Voir plus d'outils
          <span class="rem-pill" id="remCount"><?= $total - 6 ?> restants</span>
        </button>
      </div>
    <?php endif; ?>

  </main>

  <?php include 'statistique.php'; ?>
  <?php include "../includes/footer.php"; ?>

  <script src="../js/dashboard.js"></script>
</body>

</html>