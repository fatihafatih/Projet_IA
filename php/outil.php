<?php
session_start();
require_once '../includes/connexionbd.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) {
  header('Location: dashboard.php');
  exit;
}

// ── Soumission d'un avis ──────────────────────────────────────────────────────
$review_error = '';
$review_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rating'])) {
  if (isset($_SESSION['user_id'])) {
    $rating = isset($_POST['rating']) ? (int) $_POST['rating'] : 0;
    $comment = trim($_POST['comment'] ?? '');

    if ($rating < 1 || $rating > 5) {
      $review_error = 'Veuillez sélectionner une note entre 1 et 5.';
    } else {
      $ins = $pdo->prepare("
                    INSERT INTO reviews (ID_OUTILS_IA, ID_USERS, rating, comment)
                    VALUES (?, ?, ?, ?)
                ");
      $ins->execute([$id, $_SESSION['user_id'], $rating, $comment ?: null]);

      // Recalcule la note globale
      $pdo->prepare("
                    UPDATE outils_ia
                    SET global_rating = (
                        SELECT AVG(rating) FROM reviews WHERE ID_OUTILS_IA = ?
                    )
                    WHERE ID_OUTILS_IA = ?
                ")->execute([$id, $id]);

      $review_success = 'Votre avis a bien été publié !';
      header("Location: outil.php?id=$id#avis");
      exit;

    }
  }
}

// ── Suppression d'un avis ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_review'])) {
  if (isset($_SESSION['user_id'])) {
    $review_id = (int) $_POST['delete_review'];
    $pdo->prepare("
      DELETE FROM reviews WHERE ID_REVIEW = ? AND ID_USERS = ?
    ")->execute([$review_id, $_SESSION['user_id']]);

    $pdo->prepare("
      UPDATE outils_ia
      SET global_rating = (SELECT AVG(rating) FROM reviews WHERE ID_OUTILS_IA = ?)
      WHERE ID_OUTILS_IA = ?
    ")->execute([$id, $id]);

    header("Location: outil.php?id=$id#avis");
    exit;
  }
}

// ── Modification d'un avis ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_review'])) {
  if (isset($_SESSION['user_id'])) {
    $review_id = (int) $_POST['edit_review'];
    $rating_edit = (int) $_POST['rating_edit'];
    $comment_edit = trim($_POST['comment_edit'] ?? '');

    if ($rating_edit >= 1 && $rating_edit <= 5) {
      $pdo->prepare("
        UPDATE reviews SET rating = ?, comment = ?
        WHERE ID_REVIEW = ? AND ID_USERS = ?
      ")->execute([$rating_edit, $comment_edit ?: null, $review_id, $_SESSION['user_id']]);

      $pdo->prepare("
        UPDATE outils_ia
        SET global_rating = (SELECT AVG(rating) FROM reviews WHERE ID_OUTILS_IA = ?)
        WHERE ID_OUTILS_IA = ?
      ")->execute([$id, $id]);

      header("Location: outil.php?id=$id#avis");
      exit;
    }
  }
}

// ── Outil principal ──────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT o.*, c.name AS categorie
    FROM outils_ia o
    LEFT JOIN categorie c ON o.ID_CATEGORIE = c.ID_CATEGORIE
    WHERE o.ID_OUTILS_IA = ? AND o.status = 'actif'
");
$stmt->execute([$id]);
$outil = $stmt->fetch();
if (!$outil) {
  header('Location: dashboard.php');
  exit;
}

// ── Performances ─────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT AVG(rapidite) AS rapidite, AVG(qualite) AS qualite,
           AVG(qualite_image) AS qualite_image, AVG(credibilite) AS credibilite,
           AVG(score_global) AS score_global, COUNT(*) AS nb_evals
    FROM performance WHERE ID_OUTILS_IA = ?
");
$stmt->execute([$id]);
$perf = $stmt->fetch();

// ── Avantages / Inconvénients ─────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT * FROM avantages_inconvenients WHERE ID_OUTILS_IA = ? ORDER BY type
");
$stmt->execute([$id]);
$ais = $stmt->fetchAll();
$avantages = array_filter($ais, fn($r) => $r['type'] === 'avantage');
$inconvenients = array_filter($ais, fn($r) => $r['type'] === 'inconvenient');

// ── Avis ──────────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT r.*, u.nom AS user_nom, u.image AS user_image
    FROM reviews r
    JOIN users u ON r.ID_USERS = u.id
    WHERE r.ID_OUTILS_IA = ?
    ORDER BY r.ID_REVIEW DESC
");
$stmt->execute([$id]);
$reviews = $stmt->fetchAll();

// ── Modèles utilisés ──────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT m.*, p.name AS provider_name, p.logo_url AS provider_logo,
           cat.name AS categorie
    FROM tool_models tm
    JOIN models m ON tm.ID_MODEL = m.ID_MODEL
    LEFT JOIN providers p ON m.ID_PROVIDERS = p.ID_PROVIDERS
    LEFT JOIN categorie cat ON m.ID_CATEGORIE = cat.ID_CATEGORIE
    WHERE tm.ID_OUTILS_IA = ? AND m.status='actif'
");
$stmt->execute([$id]);
$modeles = $stmt->fetchAll();

// ── Caractéristiques ──────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT car.name, car.description
    FROM model_caracteristiques mc
    JOIN caracteristiques car ON mc.ID_CAR = car.ID_CAR
    JOIN tool_models tm ON mc.ID_MODEL = tm.ID_MODEL
    WHERE tm.ID_OUTILS_IA = ?
    GROUP BY car.ID_CAR
");
$stmt->execute([$id]);
$cars = $stmt->fetchAll();

// ── Disponibilités ────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT d.url, ta.name AS type_name
    FROM tool_caracteristiques tc
    JOIN disponibilite d ON tc.ID_DIS = d.ID_DIS
    LEFT JOIN type_application ta ON d.ID_TA = ta.ID_TA
    JOIN models m ON tc.ID_MODEL = m.ID_MODEL
    JOIN tool_models tm ON m.ID_MODEL = tm.ID_MODEL
    WHERE tm.ID_OUTILS_IA = ?
    GROUP BY d.ID_DIS
");
$stmt->execute([$id]);
$dispos = $stmt->fetchAll();

// ── Note moyenne des reviews ───────────────────────────────────────────────────
$avg_review = count($reviews)
  ? round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1)
  : null;



?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($outil['nom']) ?> — Référentiel IA</title>
  <link rel="stylesheet" href="../styles/outil.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body>

  <?php include "../includes/header.php"; ?>

  <!-- ══ HERO DE L'OUTIL ═══════════════════════════════════════════════════════ -->
  <div class="ot-hero">
    <div class="ot-hero-inner">

      <!-- Fil d'Ariane -->
      <nav class="ot-breadcrumb">
        <a href="dashboard.php">Accueil</a>
        <span class="ot-bc-sep">›</span>
        <a href="dashboard.php"><?= htmlspecialchars($outil['categorie'] ?? 'Outils') ?></a>
        <span class="ot-bc-sep">›</span>
        <span><?= htmlspecialchars($outil['nom']) ?></span>
      </nav>

      <div class="ot-hero-body">
        <!-- Logo -->
        <div class="ot-logo">
          <?php if ($outil['logo_url']): ?>
            <img src="<?= htmlspecialchars($outil['logo_url']) ?>" alt="<?= htmlspecialchars($outil['nom']) ?>">
          <?php else: ?>
            <span><?= strtoupper(substr($outil['nom'], 0, 2)) ?></span>
          <?php endif; ?>
        </div>

        <!-- Infos principales -->
        <div class="ot-hero-info">
          <div class="ot-hero-meta">
            <span class="ot-cat-pill"><?= htmlspecialchars($outil['categorie'] ?? 'Non classé') ?></span>
            <?php if ($outil['version']): ?>
              <span class="ot-version-pill">v<?= number_format($outil['version'], 1) ?></span>
            <?php endif; ?>

          </div>

          <h1 class="ot-title"><?= htmlspecialchars($outil['nom']) ?></h1>
          <p class="ot-subtitle"><?= htmlspecialchars($outil['description'] ?? '') ?></p>

          <!-- Score + CTA -->
          <div class="ot-hero-actions">
            <div class="ot-score-big">
              <span class="ot-star-big">★</span>
              <span class="ot-score-num"><?= number_format($outil['global_rating'], 1) ?></span>
              <span class="ot-score-label">/5</span>
              <?php if (count($reviews)): ?>
                <span class="ot-score-count">(<?= count($reviews) ?> avis)</span>
              <?php endif; ?>
            </div>

            <?php if ($outil['url']): ?>
              <a class="ot-btn-primary" href="<?= htmlspecialchars($outil['url']) ?>" target="_blank" rel="noopener">
                Visiter le site
                <svg viewBox="0 0 24 24">
                  <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14 21 3" />
                </svg>
              </a>
            <?php endif; ?>
            <a class="ot-btn-ghost" href="dashboard.php">← Retour</a>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- ══ CONTENU PRINCIPAL ═════════════════════════════════════════════════════ -->
  <div class="ot-page">
    <div class="ot-layout">

      <!-- ── Colonne gauche (principale) ──────────────────────────────────────── -->
      <div class="ot-col-main">

        <!-- PERFORMANCES -->
        <?php if ($perf && $perf['nb_evals'] > 0): ?>
          <section class="ot-section">
            <h2 class="ot-section-title">
              <span class="ot-section-icon">📊</span> Performances
            </h2>
            <div class="ot-perf-grid">
              <?php
              $metrics = [
                ['label' => 'Rapidité', 'val' => $perf['rapidite'], 'icon' => '⚡'],
                ['label' => 'Qualité', 'val' => $perf['qualite'], 'icon' => '✨'],
                ['label' => 'Crédibilité', 'val' => $perf['credibilite'], 'icon' => '🛡️'],
                ['label' => 'Score global', 'val' => $perf['score_global'], 'icon' => '🏆'],
              ];
              if ($perf['qualite_image'] > 0)
                $metrics[] = ['label' => 'Qualité image', 'val' => $perf['qualite_image'], 'icon' => '🎨'];
              foreach ($metrics as $m):
                if (!$m['val'])
                  continue;
                $pct = round(($m['val'] / 5) * 100);
                ?>
                <div class="ot-perf-card">
                  <div class="ot-perf-top">
                    <span class="ot-perf-icon"><?= $m['icon'] ?></span>
                    <span class="ot-perf-label"><?= $m['label'] ?></span>
                    <span class="ot-perf-val"><?= number_format($m['val'], 1) ?></span>
                  </div>
                  <div class="ot-bar-track">
                    <div class="ot-bar-fill" style="--pct:<?= $pct ?>%"></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

        <!-- AVANTAGES / INCONVÉNIENTS -->
        <?php if ($avantages || $inconvenients): ?>
          <section class="ot-section">
            <h2 class="ot-section-title">
              <span class="ot-section-icon">⚖️</span> Avantages & Inconvénients
            </h2>
            <div class="ot-pros-cons">
              <?php if ($avantages): ?>
                <div class="ot-pros">
                  <div class="ot-pc-head ot-pc-head--pro">
                    <span>✅</span> Avantages
                  </div>
                  <ul class="ot-pc-list">
                    <?php foreach ($avantages as $a): ?>
                      <li><?= htmlspecialchars($a['description']) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>
              <?php if ($inconvenients): ?>
                <div class="ot-cons">
                  <div class="ot-pc-head ot-pc-head--con">
                    <span>❌</span> Inconvénients
                  </div>
                  <ul class="ot-pc-list">
                    <?php foreach ($inconvenients as $i): ?>
                      <li><?= htmlspecialchars($i['description']) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>
            </div>
          </section>
        <?php endif; ?>

        <!-- MODÈLES IA UTILISÉS -->
        <?php if ($modeles): ?>
          <section class="ot-section">
            <h2 class="ot-section-title">
              <span class="ot-section-icon">🤖</span> Modèles utilisés
            </h2>
            <div class="grid ot-model-grid">
              <?php foreach ($modeles as $mod):
                $tags = array_filter(explode(',', $mod['tags'] ?? ''));
                ?>
                <div class="model-item">
                  <div class="card ot-model-card">
                    <div class="card-top">
                      <div class="c-logo">
                        <?php if ($mod['provider_logo']): ?>
                          <img src="<?= htmlspecialchars($mod['provider_logo']) ?>" alt="">
                        <?php else: ?>
                          <span><?= strtoupper(substr($mod['name'], 0, 2)) ?></span>
                        <?php endif; ?>
                      </div>
                      <div style="min-width:0">
                        <div class="c-name"><?= htmlspecialchars($mod['name']) ?></div>
                        <span class="c-cat"><?= htmlspecialchars($mod['provider_name'] ?? 'Inconnu') ?></span>
                      </div>
                    </div>
                    <p class="c-desc"><?= htmlspecialchars($mod['description'] ?? 'Aucune description.') ?></p>
                    <?php if ($tags): ?>
                      <div class="ot-tag-row">
                        <?php foreach ($tags as $t): ?>
                          <span class="ot-tag"><?= htmlspecialchars(trim($t)) ?></span>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                    <div class="c-foot">
                      <a class="btn-see" href="modele.php?id=<?= $mod['ID_MODEL'] ?>">Voir →</a>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

        <!-- AVIS UTILISATEURS -->

        <section class="ot-section" id="avis">
          <h2 class="ot-section-title">
            <span class="ot-section-icon">💬</span> Avis utilisateurs
            <?php if ($avg_review): ?>
              <span class="ot-avg-badge">★ <?= $avg_review ?></span>
            <?php endif; ?>
          </h2>

          <!-- Formulaire ajout d'avis -->
          <?php if (isset($_SESSION['user_id'])): ?>
            <div class="ot-review-form-wrap">
              <h3 class="ot-review-form-title">Laisser un avis</h3>

              <?php if ($review_error): ?>
                <div class="ot-alert ot-alert--error"><?= htmlspecialchars($review_error) ?></div>
              <?php endif; ?>

              <form class="ot-review-form" method="POST" action="outil.php?id=<?= $id ?>#avis">
                <input type="hidden" name="action" value="add_review">

                <!-- Étoiles interactives -->
                <div class="ot-star-picker" id="starPicker">
                  <span class="ot-sp-label">Votre note</span>
                  <div class="ot-stars-row">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                      <label class="ot-sp-star" for="star<?= $s ?>" data-val="<?= $s ?>">
                        <input type="radio" name="rating" id="star<?= $s ?>" value="<?= $s ?>" required>
                        <svg viewBox="0 0 24 24">
                          <path
                            d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                        </svg>
                      </label>
                    <?php endfor; ?>
                  </div>
                  <span class="ot-sp-hint" id="starHint">Cliquez pour noter</span>
                </div>

                <!-- Commentaire -->
                <div class="ot-form-group">
                  <label class="ot-form-label" for="reviewComment">Commentaire <span
                      class="ot-optional">(optionnel)</span></label>
                  <textarea class="ot-form-textarea" id="reviewComment" name="comment" rows="4" maxlength="1000"
                    placeholder="Partagez votre expérience avec cet outil…"><?= htmlspecialchars($_POST['comment'] ?? '') ?></textarea>
                  <span class="ot-char-count"><span id="charCount">0</span>/1000</span>
                </div>

                <button type="submit" class="ot-btn-submit">
                  <i class="bi bi-send-fill"></i>
                  Publier mon avis
                </button>
              </form>
            </div>
          <?php endif; ?>

          <!-- Liste des avis -->
          <?php if ($reviews): ?>
            <div class="ot-reviews">
              <?php foreach ($reviews as $rev):
                $stars = round($rev['rating']);
                $is_own = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $rev['ID_USERS'];
                ?>
                <div class="ot-review-card" id="review-<?= $rev['ID_REVIEW'] ?>">
                  <div class="ot-rev-header">
                    <div class="ot-rev-avatar">
                      <?php if ($rev['user_image']): ?>
                        <img src="<?= htmlspecialchars($rev['user_image']) ?>" alt="">
                      <?php else: ?>
                        <?= strtoupper(substr($rev['user_nom'], 0, 1)) ?>
                      <?php endif; ?>
                    </div>
                    <div class="ot-rev-meta">
                      <span class="ot-rev-name"><?= htmlspecialchars($rev['user_nom']) ?></span>
                      <div class="ot-rev-stars">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                          <span class="<?= $s <= $stars ? 'ot-star-on' : 'ot-star-off' ?>">★</span>
                        <?php endfor; ?>
                        <span class="ot-rev-score"><?= number_format($rev['rating'], 1) ?></span>
                      </div>
                    </div>

                    <!-- Actions si c'est son propre avis -->
                    <?php if ($is_own): ?>
                      <div class="ot-rev-actions">

                        <button type="button" class="ot-rev-btn ot-rev-btn--edit js-edit-btn"
                          data-id="<?= $rev['ID_REVIEW'] ?>" title="Modifier">
                          <i class="bi bi-pencil-fill"></i>
                        </button>
                        <form method="POST" action="outil.php?id=<?= $id ?>" style="display:inline"
                          onsubmit="return confirm('Supprimer cet avis ?')">
                          <input type="hidden" name="delete_review" value="<?= $rev['ID_REVIEW'] ?>">
                          <button type="submit" class="ot-rev-btn ot-rev-btn--delete" title="Supprimer">
                            <i class="bi bi-trash-fill"></i>
                          </button>
                        </form>
                      </div>
                    <?php endif; ?>
                  </div>

                  <?php if ($rev['comment']): ?>
                    <p class="ot-rev-comment" id="comment-text-<?= $rev['ID_REVIEW'] ?>">
                      "<?= htmlspecialchars($rev['comment']) ?>"
                    </p>
                  <?php endif; ?>

                  <!-- Formulaire d'édition (caché par défaut) -->
                  <?php if ($is_own): ?>
                    <div class="ot-edit-form" id="edit-form-<?= $rev['ID_REVIEW'] ?>" style="display:none">
                      <form method="POST" action="outil.php?id=<?= $id ?>">
                        <input type="hidden" name="edit_review" value="<?= $rev['ID_REVIEW'] ?>">

                        <!-- Étoiles édition -->
                        <div class="ot-star-picker" id="editStarPicker-<?= $rev['ID_REVIEW'] ?>">
                          <span class="ot-sp-label">Modifier la note</span>
                          <div class="ot-stars-row">
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                              <label class="ot-sp-star" for="edit-star-<?= $rev['ID_REVIEW'] ?>-<?= $s ?>">
                                <input type="radio" name="rating_edit" id="edit-star-<?= $rev['ID_REVIEW'] ?>-<?= $s ?>"
                                  value="<?= $s ?>" <?= $s == round($rev['rating']) ? 'checked' : '' ?>>
                                <svg viewBox="0 0 24 24">
                                  <path
                                    d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                </svg>
                              </label>
                            <?php endfor; ?>
                          </div>
                        </div>

                        <textarea class="ot-form-textarea" name="comment_edit" rows="3"
                          placeholder="Modifier votre commentaire…"><?= htmlspecialchars($rev['comment'] ?? '') ?></textarea>

                        <div class="ot-edit-actions">
                          <button type="submit" class="ot-btn-submit">
                            <i class="bi bi-check-lg"></i> Enregistrer
                          </button>
                          <button type="button" class="ot-btn-ghost js-cancel-btn" data-id="<?= $rev['ID_REVIEW'] ?>">
                            Annuler
                          </button>
                        </div>
                      </form>
                    </div>
                  <?php endif; ?>

                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="ot-empty-state">
              <span class="ot-empty-icon">💭</span>
              <p>Aucun avis pour cet outil pour le moment.</p>
            </div>
          <?php endif; ?>

        </section>
      </div><!-- /col-main -->

      <!-- ── Colonne droite (sidebar) ─────────────────────────────────────────── -->
      <aside class="ot-col-side">

        <!-- Carte infos rapides -->
        <div class="ot-side-card">
          <h3 class="ot-side-title">Informations</h3>
          <ul class="ot-info-list">
            <li>
              <span class="ot-info-label">Catégorie</span>
              <span class="ot-info-val"><?= htmlspecialchars($outil['categorie'] ?? '—') ?></span>
            </li>
            <li>
              <span class="ot-info-label">Version</span>
              <span class="ot-info-val">
                <?= $outil['version'] ? 'v' . number_format($outil['version'], 1) : '—' ?>
              </span>
            </li>
            <li>
              <span class="ot-info-label">Note globale</span>
              <span class="ot-info-val ot-star-inline">★ <?= number_format($outil['global_rating'], 1) ?></span>
            </li>
            <?php if (count($reviews)): ?>
              <li>
                <span class="ot-info-label">Nb d'avis</span>
                <span class="ot-info-val"><?= count($reviews) ?></span>
              </li>
            <?php endif; ?>
          </ul>
        </div>

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
                    <svg viewBox="0 0 24 24">
                      <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14 21 3" />
                    </svg>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

      </aside>

    </div><!-- /layout -->
  </div><!-- /page -->

  <?php include "../includes/footer.php"; ?>
  <script src="../js/outils.js"></script>

</body>

</html>