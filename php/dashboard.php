<?php
require_once '../includes/connexionbd.php';
$stmt = $pdo->prepare("SELECT id, nom, description, categorie, prix, rapidite, intelligence, langage, logo, lien FROM ai_models WHERE status = 'valide' ORDER BY id DESC");
$stmt->execute();
$models = $stmt->fetchAll();
$total = count($models);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Référentiel de modèles IA</title>
  <link rel="stylesheet" href="../styles/style.css">

</head>

<body>
  <!-- header -->
  <?php
  include "../includes/header.php";
  ?>

  <!-- body -->

  <!-- section de recherche -->
  <section class="hero">
    <div class="hero-inner">
      <div class="eyebrow"><span class="eyebrow-dot"></span>Référentiel IA</div>
      <h1>Les meilleurs modèles<br><b>d'intelligence artificielle</b></h1>
      <p class="hero-sub">Explorez, comparez et accédez aux modèles IA les plus performants, réunis en un seul endroit.
      </p>

      <div class="search-wrap">
        <img src="../public/recherche.png" alt="logo recherche" class="search-icon">
        <input type="text" id="searchInput" placeholder="Rechercher un modèle, une catégorie…">
        <button class="btn-search" onclick="doSearch()">Rechercher</button>
      </div>

      <div class="filters" id="filterBar">
        <span class="pill active" data-cat="">Tous</span>
        <?php
        $cats = array_unique(array_column($models, 'categorie'));
        foreach ($cats as $c):
          if ($c):
            ?>
            <span class="pill" data-cat="<?= $c ?>"><?= $c ?></span>
            <!-- data-* est une famille d’attributs HTML personnalisés qui permet de stocker des données 
            supplémentaires sur un élément, souvent pour JavaScript.
            En JavaScript on y accède avec :element.dataset.cat -->
          <?php endif; endforeach; ?>
      </div>
    </div>
  </section>

  <!-- section modeles -->

  <main class="main">

    <div class="toolbar">
      <div class="toolbar-l">
        <h2>Modèles disponibles</h2>
        <span class="count-badge" id="countBadge"><?= $total ?> modèle<?= $total > 1 ? 's' : '' ?></span>
      </div>
      <select class="sort-select" onchange="doSort(this.value)">
        <option value="default">Par défaut</option>
        <option value="price_asc">Prix croissant</option>
        <option value="price_desc">Prix décroissant</option>
        <option value="speed">Rapidité</option>
      </select>
    </div>

    <div class="grid" id="grid">
      <div class="no-results" id="noResults">
        <p>Aucun modèle trouvé — essayez un autre mot-clé.</p>
      </div>

      <?php foreach ($models as $i => $m):
        $hidden = $i >= 6 ? 'd-none' : ''; //affichage uniquement de 6 modeles a la fois
        $logoHtml = '<img src="' . $m['logo'] . '" alt="' . $m['nom'] . '" >';
        $isFree = !$m['prix'];
        $prix = $isFree ? 'Gratuit' : '$' . number_format($m['prix'], 2);
        $speed = $m['rapidite'];
        ?>
        <div class="model-item <?= $hidden ?>" data-nom="<?= strtolower($m['nom']) ?>"
          data-cat="<?= strtolower($m['categorie']) ?>" data-lang="<?= strtolower($m['langage']) ?>"
          data-desc="<?= strtolower($m['description']) ?>" data-prix="<?= (float) $m['prix'] ?>"
          data-speed="<?= $speed ?>">
          <div class="card">
            <div class="card-top">
              <div class="c-logo"><?= $logoHtml ?></div>
              <div style="min-width:0;">
                <div class="c-name"><?= $m['nom'] ?></div>
                <span class="c-cat"><?= $m['categorie'] ?></span>
              </div>
            </div>
            <p class="c-desc"><?= $m['description'] ?: 'Aucune description disponible.' ?></p>
            <div class="c-foot">
              <span class="c-price <?= $isFree ? 'free' : '' ?>"><?= $prix ?></span>
              <?php if ($m['lien']): ?>
                <a class="btn-see" href="<?= htmlspecialchars($m['lien']) ?>" target="_blank" rel="noopener">
                  Voir ->
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
          Voir plus de modèles
          <span class="rem-pill" id="remCount"><?= $total - 6 ?> restants</span>
        </button>
      </div>
    <?php endif; ?>
  </main>

  <!-- footer -->
  <?php
  include "../includes/footer.php"
    ?>
  
  <script src="../js/dashboard.js"></script>
</body>

</html>