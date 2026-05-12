<?php
require_once '../includes/connexionbd.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header('Location: dashboard.php'); exit; }

$stmt = $pdo->prepare("
    SELECT m.*,
           p.name        AS provider_name,
           p.website_url AS provider_url,
           p.logo_url    AS provider_logo,
           cat.name      AS categorie
    FROM models m
    LEFT JOIN providers  p   ON m.ID_PROVIDERS = p.ID_PROVIDERS
    LEFT JOIN categorie  cat ON m.ID_CATEGORIE  = cat.ID_CATEGORIE
    WHERE m.ID_MODEL = ? AND status='actif'
");
$stmt->execute([$id]);
$model = $stmt->fetch();
if (!$model) { header('Location: dashboard.php'); exit; }

// ── Caractéristiques du modèle ───────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT car.ID_CAR, car.name, car.description
    FROM model_caracteristiques mc
    JOIN caracteristiques car ON mc.ID_CAR = car.ID_CAR
    WHERE mc.ID_MODEL = ?
    ORDER BY car.name
");
$stmt->execute([$id]);
$cars = $stmt->fetchAll();

// ── Disponibilités liées à ce modèle ─────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT d.url, ta.name AS type_name
    FROM tool_caracteristiques tc
    JOIN disponibilite     d  ON tc.ID_DIS = d.ID_DIS
    LEFT JOIN type_application ta ON d.ID_TA  = ta.ID_TA
    WHERE tc.ID_MODEL = ?
    GROUP BY d.ID_DIS
");
$stmt->execute([$id]);
$dispos = $stmt->fetchAll();

// ── Outils IA qui utilisent ce modèle ────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT o.ID_OUTILS_IA, o.nom, o.description, o.logo_url,
           o.url, o.global_rating, o.version, o.status,
           cat.name AS categorie
    FROM tool_models tm
    JOIN outils_ia o   ON tm.ID_OUTILS_IA = o.ID_OUTILS_IA
    LEFT JOIN categorie cat ON o.ID_CATEGORIE = cat.ID_CATEGORIE
    WHERE tm.ID_MODEL = ?
    ORDER BY o.global_rating DESC
");
$stmt->execute([$id]);
$outils = $stmt->fetchAll();

// ── Autres modèles du même provider ──────────────────────────────────────────
$autres = [];
if ($model['ID_PROVIDERS']) {
    $stmt = $pdo->prepare("
        SELECT m.ID_MODEL, m.name, m.description, m.tags, m.status,
               cat.name AS categorie
        FROM models m
        LEFT JOIN categorie cat ON m.ID_CATEGORIE = cat.ID_CATEGORIE
        WHERE m.ID_PROVIDERS = ? AND m.ID_MODEL != ? AND status='actif'
        ORDER BY m.status ASC, m.ID_MODEL DESC
        LIMIT 4
    ");
    $stmt->execute([$model['ID_PROVIDERS'], $id]);
    $autres = $stmt->fetchAll();
}

// Tags parsés
$tags = array_filter(array_map('trim', explode(',', $model['tags'] ?? '')));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($model['name']) ?> — Référentiel IA</title>
  <link rel="stylesheet" href="../styles/style.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<?php include "../includes/header.php"; ?>

<!-- ══ HERO ══════════════════════════════════════════════════════════════════ -->
<div class="ot-hero md-hero">
  <div class="ot-hero-inner">

    <!-- Fil d'Ariane -->
    <nav class="ot-breadcrumb">
      <a href="dashboard.php">Accueil</a>
      <span class="ot-bc-sep">›</span>
      <span>Modèles</span>
      <span class="ot-bc-sep">›</span>
      <?php if ($model['provider_name']): ?>
        <span><?= htmlspecialchars($model['provider_name']) ?></span>
        <span class="ot-bc-sep">›</span>
      <?php endif; ?>
      <span><?= htmlspecialchars($model['name']) ?></span>
    </nav>

    <div class="ot-hero-body">

      <!-- Logo provider -->
      <div class="ot-logo md-logo">
        <?php if ($model['provider_logo']): ?>
          <img src="<?= htmlspecialchars($model['provider_logo']) ?>" alt="<?= htmlspecialchars($model['provider_name']) ?>">
        <?php else: ?>
          <span><?= strtoupper(substr($model['name'], 0, 2)) ?></span>
        <?php endif; ?>
      </div>

      <div class="ot-hero-info">
        <div class="ot-hero-meta">
          <?php if ($model['categorie']): ?>
            <span class="ot-cat-pill"><?= htmlspecialchars($model['categorie']) ?></span>
          <?php endif; ?>
          <?php if ($model['provider_name']): ?>
            <span class="md-provider-pill">
              <?= htmlspecialchars($model['provider_name']) ?>
            </span>
          <?php endif; ?>
        </div>

        <h1 class="ot-title"><?= htmlspecialchars($model['name']) ?></h1>
        <p class="ot-subtitle"><?= htmlspecialchars($model['description'] ?? '') ?></p>

        <!-- Tags -->
        <?php if ($tags): ?>
        <div class="ot-tag-row md-tags">
          <?php foreach ($tags as $t): ?>
            <span class="ot-tag"><?= htmlspecialchars($t) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="ot-hero-actions" style="margin-top:20px">
          <?php if ($model['provider_url']): ?>
            <a class="ot-btn-primary" href="<?= htmlspecialchars($model['provider_url']) ?>" target="_blank" rel="noopener">
              Voir le provider
              <svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14 21 3"/></svg>
            </a>
          <?php endif; ?>
          <a class="ot-btn-ghost" href="javascript:history.back()">← Retour</a>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ══ CONTENU ═══════════════════════════════════════════════════════════════ -->
<div class="ot-page">
  <div class="ot-layout">

    <!-- ── Colonne principale ────────────────────────────────────────────── -->
    <div class="ot-col-main">

      <!-- OUTILS QUI UTILISENT CE MODÈLE -->
      <?php if ($outils): ?>
      <section class="ot-section">
        <h2 class="ot-section-title">
          <span class="ot-section-icon">🛠️</span>
          Outils utilisant ce modèle
          <span class="ot-avg-badge" style="background:var(--blue-l);color:var(--blue)">
            <?= count($outils) ?> outil<?= count($outils) > 1 ? 's' : '' ?>
          </span>
        </h2>
        <div class="grid ot-model-grid">
          <?php foreach ($outils as $o):
            $rating  = number_format($o['global_rating'], 1);
            $version = $o['version'] ? 'v'.number_format($o['version'],1) : '';
          ?>
          <div class="model-item">
            <div class="card ot-model-card">
              <div class="card-top">
                <div class="c-logo">
                  <?php if ($o['logo_url']): ?>
                    <img src="<?= htmlspecialchars($o['logo_url']) ?>" alt="<?= htmlspecialchars($o['nom']) ?>">
                  <?php else: ?>
                    <span><?= strtoupper(substr($o['nom'],0,2)) ?></span>
                  <?php endif; ?>
                </div>
                <div style="min-width:0">
                  <div class="c-name"><?= htmlspecialchars($o['nom']) ?></div>
                  <span class="c-cat"><?= htmlspecialchars($o['categorie'] ?? 'Non classé') ?></span>
                  <?php if ($version): ?>
                    <span class="c-version" style="font-size:.66rem;color:var(--hint);margin-left:4px"><?= $version ?></span>
                  <?php endif; ?>
                </div>
              </div>
              <p class="c-desc"><?= htmlspecialchars($o['description'] ?? 'Aucune description.') ?></p>
              <div class="c-foot">
                <span class="c-rating">★ <?= $rating ?></span>
                <a class="btn-see" href="outil.php?id=<?= $o['ID_OUTILS_IA'] ?>">Voir →</a>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <!-- AUTRES MODÈLES DU MÊME PROVIDER -->
      <?php if ($autres): ?>
      <section class="ot-section">
        <h2 class="ot-section-title">
          <span class="ot-section-icon">🔗</span>
          Autres modèles de <?= htmlspecialchars($model['provider_name']) ?>
        </h2>
        <div class="grid ot-model-grid">
          <?php foreach ($autres as $am):
            $atags = array_filter(array_map('trim', explode(',', $am['tags'] ?? '')));
          ?>
          <div class="model-item">
            <div class="card ot-model-card">
              <div class="card-top">
                <div class="c-logo">
                  <?php if ($model['provider_logo']): ?>
                    <img src="<?= htmlspecialchars($model['provider_logo']) ?>" alt="">
                  <?php else: ?>
                    <span><?= strtoupper(substr($am['name'],0,2)) ?></span>
                  <?php endif; ?>
                </div>
                <div style="min-width:0">
                  <div class="c-name"><?= htmlspecialchars($am['name']) ?></div>
                  <span class="c-cat"><?= htmlspecialchars($am['categorie'] ?? '—') ?></span>
                </div>
              </div>
              <p class="c-desc"><?= htmlspecialchars($am['description'] ?? 'Aucune description.') ?></p>
              <?php if ($atags): ?>
              <div class="ot-tag-row">
                <?php foreach ($atags as $t): ?>
                  <span class="ot-tag"><?= htmlspecialchars($t) ?></span>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
              <div class="c-foot">
                <a class="btn-see" href="modele.php?id=<?= $am['ID_MODEL'] ?>">Voir →</a>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <!-- État vide : aucun outil ni autre modèle -->
      <?php if (!$outils && !$autres): ?>
      <div class="ot-empty-state" style="margin-top:24px">
        <span class="ot-empty-icon">🔍</span>
        <p>Aucun outil ou modèle associé trouvé pour le moment.</p>
      </div>
      <?php endif; ?>

    </div><!-- /col-main -->

    <!-- ── Sidebar ────────────────────────────────────────────────────────── -->
    <aside class="ot-col-side">

      <!-- Infos rapides -->
      <div class="ot-side-card">
        <h3 class="ot-side-title">Informations</h3>
        <ul class="ot-info-list">
          <li>
            <span class="ot-info-label">Nom</span>
            <span class="ot-info-val"><?= htmlspecialchars($model['name']) ?></span>
          </li>
          <li>
            <span class="ot-info-label">Provider</span>
            <span class="ot-info-val">
              <?php if ($model['provider_url']): ?>
                <a href="<?= htmlspecialchars($model['provider_url']) ?>" target="_blank"
                   style="color:var(--blue);text-decoration:none;font-weight:600">
                  <?= htmlspecialchars($model['provider_name'] ?? '—') ?> ↗
                </a>
              <?php else: ?>
                <?= htmlspecialchars($model['provider_name'] ?? '—') ?>
              <?php endif; ?>
            </span>
          </li>
          <li>
            <span class="ot-info-label">Catégorie</span>
            <span class="ot-info-val"><?= htmlspecialchars($model['categorie'] ?? '—') ?></span>
          </li>
          <?php if ($outils): ?>
          <li>
            <span class="ot-info-label">Utilisé par</span>
            <span class="ot-info-val"><?= count($outils) ?> outil<?= count($outils)>1?'s':'' ?></span>
          </li>
          <?php endif; ?>
        </ul>
      </div>

      <!-- Tags -->
      <?php if ($tags): ?>
      <div class="ot-side-card">
        <h3 class="ot-side-title">Tags</h3>
        <div class="ot-car-list">
          <?php foreach ($tags as $t): ?>
            <span class="ot-car-pill"><?= htmlspecialchars($t) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Caractéristiques -->
      <?php if ($cars): ?>
      <div class="ot-side-card">
        <h3 class="ot-side-title">Caractéristiques</h3>
        <div class="ot-car-list">
          <?php foreach ($cars as $car): ?>
            <span class="ot-car-pill" title="<?= htmlspecialchars($car['description'] ?? '') ?>">
              <?= htmlspecialchars($car['name']) ?>
            </span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Disponibilités -->
      <?php if ($dispos): ?>
      <div class="ot-side-card">
        <h3 class="ot-side-title">Disponibilités</h3>
        <ul class="ot-dispo-list">
          <?php foreach ($dispos as $d): ?>
          <li>
            <span class="ot-dispo-type"><?= htmlspecialchars($d['type_name'] ?? 'Lien') ?></span>
            <a href="<?= htmlspecialchars($d['url']) ?>" target="_blank" rel="noopener" class="ot-dispo-url">
              <?= htmlspecialchars(parse_url($d['url'], PHP_URL_HOST)) ?>
              <svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14 21 3"/></svg>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

    </aside>

  </div>
</div>

<?php include "../includes/footer.php"; ?>
<script src="../js/outil.js"></script>
</body>
</html>