<?php
require_once '../includes/connexionbd.php';

// Récupérer les outils actifs avec leur catégorie et score de performance moyen
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
        -- Tarif minimum associé via les modèles (optionnel, si vous liez tarifs aux outils plus tard)
        -- Pour l'instant on affiche juste le rating et la catégorie
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
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Référentiel d'outils IA</title>
  <link rel="stylesheet" href="../styles/style.css">
</head>

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
            <!-- data-* est une famille d’attributs HTML personnalisés qui permet de stocker des données 
            supplémentaires sur un élément, souvent pour JavaScript.
            En JavaScript on y accède avec :element.dataset.cat -->
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
        <option value="rating_asc">Moins bien notés</option>
        <option value="perf_desc">Performance</option>
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
        ?>
        <div class="model-item <?= $hidden ?>" data-nom="<?= strtolower(htmlspecialchars($o['nom'])) ?>"
          data-cat="<?= strtolower(htmlspecialchars($o['categorie'] ?? '')) ?>"
          data-desc="<?= strtolower(htmlspecialchars($o['description'] ?? '')) ?>"
          data-rating="<?= (float) $o['global_rating'] ?>">

          <div class="card">
            <div class="card-top">
              <!-- Logo -->
              <div class="c-logo">
                <?php if ($o['logo_url']): ?>
                  <img src="<?= htmlspecialchars($o['logo_url']) ?>" alt="<?= htmlspecialchars($o['nom']) ?>">
                <?php else: ?>
                  <div class="c-logo-placeholder">IA</div>
                <?php endif; ?>
              </div>

              <!-- Nom + catégorie + version -->
              <div style="min-width:0;">
                <div class="c-name"><?= htmlspecialchars($o['nom']) ?></div>
                <span class="c-cat"><?= htmlspecialchars($o['categorie'] ?? 'Non classé') ?></span>
                <?php if ($version): ?>
                  <span class="c-version"><?= $version ?></span>
                <?php endif; ?>
              </div>
            </div>

            <!-- Description -->
            <p class="c-desc">
              <?= htmlspecialchars($o['description'] ?: 'Aucune description disponible.') ?>
            </p>

            <!-- Footer carte : note + lien -->
            <div class="c-foot">
              <!-- Note globale avec étoile -->
              <span class="c-rating">
                ★ <?= $rating ?>
              </span>

              <?php if ($o['url']): ?>
                <a class="btn-see" href="<?= htmlspecialchars($o['url']) ?>" target="_blank" rel="noopener">
                  Voir →
                </a>
              <?php else: ?>
                <span class="btn-see btn-off">Indisponible</span>
              <?php endif; ?>
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

  <?php include "../includes/footer.php"; ?>

  <script src="../js/dashboard.js"></script>
</body>

</html>