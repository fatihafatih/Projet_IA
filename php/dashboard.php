<?php
session_start();
require_once '../includes/connexionbd.php';

// ══════════════════════════════════════════════════════════════════
//  AJAX — traité avant tout HTML
// ══════════════════════════════════════════════════════════════════
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    switch ($_GET['action']) {

        case 'get_history':
            if (!isset($_SESSION['user_id'])) { echo json_encode([]); exit; }
            $stmt = $pdo->prepare("
                SELECT ID_HR, filtres_json, nb_resultats, searched_at
                FROM historique_recherche
                WHERE ID_USERS = ?
                ORDER BY searched_at DESC
                LIMIT 10
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $rows   = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $result = [];
            foreach ($rows as $r) {
                $filtres  = json_decode($r['filtres_json'], true);
                $result[] = [
                    'id'           => (int)$r['ID_HR'],
                    'query'        => $filtres['query']     ?? '',
                    'categorie'    => $filtres['categorie'] ?? '',
                    'nb_resultats' => (int)$r['nb_resultats'],
                    'searched_at'  => $r['searched_at'],
                ];
            }
            echo json_encode($result);
            exit;

        case 'save_history':
            if (!isset($_SESSION['user_id'])) { echo json_encode(['ok' => false]); exit; }
            $data      = json_decode(file_get_contents('php://input'), true);
            $query     = trim($data['query']     ?? '');
            $categorie = trim($data['categorie'] ?? '');
            $nb        = (int)($data['nb_resultats'] ?? 0);
            if ($query === '') { echo json_encode(['ok' => false]); exit; }
            $filtres_json = json_encode(['query' => $query, 'categorie' => $categorie]);
            $check = $pdo->prepare("
                SELECT ID_HR FROM historique_recherche
                WHERE ID_USERS = ? AND filtres_json = ?
                ORDER BY searched_at DESC LIMIT 1
            ");
            $check->execute([$_SESSION['user_id'], $filtres_json]);
            if ($check->fetch()) { echo json_encode(['ok' => true, 'skipped' => true]); exit; }
            $stmt = $pdo->prepare("
                INSERT INTO historique_recherche (ID_USERS, filtres_json, nb_resultats, searched_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$_SESSION['user_id'], $filtres_json, $nb]);
            echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
            exit;

        case 'delete_history':
            if (!isset($_SESSION['user_id'])) { echo json_encode(['ok' => false]); exit; }
            $data = json_decode(file_get_contents('php://input'), true);
            $id   = (int)($data['id']  ?? 0);
            $all  = (bool)($data['all'] ?? false);
            if ($all) {
                $stmt = $pdo->prepare("DELETE FROM historique_recherche WHERE ID_USERS = ?");
                $stmt->execute([$_SESSION['user_id']]);
            } elseif ($id > 0) {
                $stmt = $pdo->prepare("DELETE FROM historique_recherche WHERE ID_HR = ? AND ID_USERS = ?");
                $stmt->execute([$id, $_SESSION['user_id']]);
            }
            echo json_encode(['ok' => true]);
            exit;
    }
    echo json_encode(['error' => 'action inconnue']);
    exit;
}

// ══════════════════════════════════════════════════════════════════
//  DONNÉES — outils triés : favoris en premier, puis le reste
// ══════════════════════════════════════════════════════════════════
$user_id = $_SESSION['user_id'] ?? null;

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
        c.name AS categorie,
        CASE WHEN f.ID_OUTILS_IA IS NOT NULL THEN 0 ELSE 1 END AS fav_order
    FROM outils_ia o
    LEFT JOIN categorie c    ON o.ID_CATEGORIE  = c.ID_CATEGORIE
    LEFT JOIN performance p  ON p.ID_OUTILS_IA  = o.ID_OUTILS_IA
    LEFT JOIN favoris f      ON f.ID_OUTILS_IA  = o.ID_OUTILS_IA
                             AND f.ID_USERS      = :user_id
    WHERE o.status = 'actif'
    GROUP BY o.ID_OUTILS_IA
    ORDER BY fav_order ASC, o.ID_OUTILS_IA DESC
");
$stmt->execute([':user_id' => $user_id]);
$outils = $stmt->fetchAll();
$total  = count($outils);

// IDs des favoris pour l'utilisateur connecté
$fav_ids = [];
if ($user_id) {
    $fav_stmt = $pdo->prepare("
        SELECT DISTINCT ID_OUTILS_IA FROM favoris WHERE ID_USERS = ?
    ");
    $fav_stmt->execute([$user_id]);
    $fav_ids = array_map('intval', $fav_stmt->fetchAll(PDO::FETCH_COLUMN));
}

$is_logged = $user_id ? 'true' : 'false';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Référentiel d'outils IA</title>
  <link rel="stylesheet" href="../styles/dashboard.css">
  <link rel="stylesheet" href="../styles/favoris.css">
  <style>
  /* ══ SEARCH DROPDOWN ═════════════════════════════════════════════ */
  .search-wrap { position: relative; }

  .search-dropdown {
    display: none;
    position: absolute;
    top: calc(100% + 10px);
    left: 0; right: 0;
    background: #fff;
    border: 1px solid rgba(27,42,74,.13);
    border-radius: 18px;
    box-shadow: 0 20px 60px rgba(27,42,74,.18), 0 2px 8px rgba(27,42,74,.08);
    z-index: 9999;
    overflow: hidden;
    animation: sdFadeIn .17s ease;
  }
  @keyframes sdFadeIn {
    from { opacity:0; transform:translateY(-8px); }
    to   { opacity:1; transform:translateY(0); }
  }
  .search-dropdown.open { display: block; }

  .sd-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 18px 9px;
    border-bottom: 1px solid rgba(27,42,74,.07);
  }
  .sd-header-label {
    display: flex; align-items: center; gap: 6px;
    font-size: .72rem; font-weight: 700;
    letter-spacing: .09em; text-transform: uppercase; color: #8a99b4;
  }
  .sd-header-label svg { width:13px; height:13px; stroke:#8a99b4; flex-shrink:0; }

  .sd-clear {
    background: none; border: none;
    font-size: .72rem; font-weight: 700; color: #E8455A;
    cursor: pointer; padding: 3px 10px; border-radius: 6px;
    letter-spacing: .04em; transition: background .15s;
  }
  .sd-clear:hover { background: rgba(232,69,90,.08); }

  .sd-list {
    list-style: none; margin: 0; padding: 6px 0;
    max-height: 320px; overflow-y: auto;
  }
  .sd-list::-webkit-scrollbar { width: 5px; }
  .sd-list::-webkit-scrollbar-thumb { background: rgba(27,42,74,.15); border-radius: 10px; }

  .sd-list li {
    display: flex; align-items: center; gap: 11px;
    padding: 9px 16px; cursor: pointer; transition: background .13s; position: relative;
  }
  .sd-list li:hover, .sd-list li.kbd-active { background: rgba(27,42,74,.05); }

  .sd-item-icon {
    width:34px; height:34px; border-radius:10px;
    background: rgba(27,42,74,.07);
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
  }
  .sd-item-icon svg { width:15px; height:15px; stroke:#1B2A4A; }

  .sd-item-text { flex:1; min-width:0; }
  .sd-item-query {
    font-size:.88rem; font-weight:600; color:#1B2A4A;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  }
  .sd-item-query .sd-cat-chip { font-size:.72rem; font-weight:500; color:#8a99b4; margin-left:6px; }
  .sd-item-meta { font-size:.71rem; color:#aab5ca; margin-top:1px; }

  .sd-item-badge {
    font-size:.7rem; font-weight:700; padding:3px 10px; border-radius:100px;
    background: rgba(27,42,74,.07); color:#3a4e70; flex-shrink:0; white-space:nowrap;
  }

  .sd-item-del {
    background:none; border:none; cursor:pointer; padding:5px; border-radius:6px;
    opacity:0; transition:opacity .13s, background .13s;
    display:flex; align-items:center; justify-content:center; color:#b0bdd0; flex-shrink:0;
  }
  .sd-item-del svg { width:13px; height:13px; stroke:currentColor; }
  .sd-list li:hover .sd-item-del { opacity:1; }
  .sd-item-del:hover { color:#E8455A; background:rgba(232,69,90,.09); }

  .sd-empty {
    padding:26px 18px; text-align:center; font-size:.82rem; color:#b0bdd0;
  }
  .sd-empty svg { display:block; margin:0 auto 8px; width:24px; height:24px; stroke:#c8d3e3; }

  .sd-item-query mark {
    background: rgba(184,148,10,.18); color:#1B2A4A;
    border-radius:3px; padding:0 1px; font-weight:700;
  }

  /* ══ CARTE FAVORI — badge + bordure ══════════════════════════ */
  .model-item[data-fav="1"] .card {
    border: 1.5px solid rgba(232,69,90,.30);
    box-shadow: 0 0 0 3px rgba(232,69,90,.06), 0 4px 20px rgba(0,0,0,.08);
  }
  .fav-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: .67rem; font-weight: 700; letter-spacing: .05em;
    color: #E8455A;
    background: rgba(232,69,90,.08);
    border: 1px solid rgba(232,69,90,.20);
    padding: 2px 9px; border-radius: 100px;
    margin-bottom: 8px;
  }
  </style>
</head>

<!-- ══ MODAL COLLECTIONS ════════════════════════════════════════════════════ -->
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

  <!-- ══ HERO / RECHERCHE ══════════════════════════════════════════════════ -->
  <section class="hero">
    <div class="hero-inner">
      <div class="eyebrow"><span class="eyebrow-dot"></span>Référentiel IA</div>
      <h1>Les meilleurs outils<br><b>d'intelligence artificielle</b></h1>
      <p class="hero-sub">
        Explorez, comparez et accédez aux outils IA les plus performants, réunis en un seul endroit.
      </p>

      <!-- Barre de recherche + dropdown historique -->
      <div class="search-wrap" id="searchContainer">
        <img src="../public/recherche.png" alt="logo recherche" class="search-icon">
        <input
          type="text"
          id="searchInput"
          placeholder="Rechercher un outil, une catégorie…"
          autocomplete="off"
        >
        <button class="btn-search" onclick="doSearch()">Rechercher</button>

        <!-- Dropdown historique -->
        <div class="search-dropdown" id="searchDropdown">
          <div class="sd-header">
            <span class="sd-header-label">
              <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
              </svg>
              Recherches récentes
            </span>
            <?php if ($user_id): ?>
              <button class="sd-clear" id="sdClearAll">Tout effacer</button>
            <?php endif; ?>
          </div>
          <ul class="sd-list" id="sdList"></ul>
          <div class="sd-empty" id="sdEmpty" style="display:none;">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
              <line x1="8" y1="8" x2="14" y2="14"/><line x1="14" y1="8" x2="8" y2="14"/>
            </svg>
            Aucun historique pour l'instant
          </div>
        </div>
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
          <?php endif;
        endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ══ LISTE DES OUTILS ══════════════════════════════════════════════════ -->
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
        $hidden  = $i >= 6 ? 'd-none' : '';
        $rating  = number_format($o['global_rating'], 1);
        $version = $o['version'] ? 'v' . number_format($o['version'], 1) : '';
        $is_fav  = in_array((int)$o['ID_OUTILS_IA'], $fav_ids);
      ?>
        <div class="model-item <?= $hidden ?>"
          data-nom="<?= strtolower(htmlspecialchars($o['nom'])) ?>"
          data-cat="<?= strtolower(htmlspecialchars($o['categorie'] ?? '')) ?>"
          data-desc="<?= strtolower(htmlspecialchars($o['description'] ?? '')) ?>"
          data-rating="<?= (float)$o['global_rating'] ?>"
          data-fav="<?= $is_fav ? '1' : '0' ?>">

          <div class="card">

            <?php if ($is_fav): ?>
              <span class="fav-badge">❤️ Favori</span>
            <?php endif; ?>

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
                <?php if ($user_id): ?>
                  <button
                    class="btn-fav js-fav-btn <?= $is_fav ? 'is-fav' : '' ?>"
                    data-id="<?= $o['ID_OUTILS_IA'] ?>"
                    title="<?= $is_fav ? 'Retirer des favoris' : 'Ajouter aux favoris' ?>">
                    <svg viewBox="0 0 24 24"
                         fill="<?= $is_fav ? '#E8455A' : 'none' ?>"
                         stroke="<?= $is_fav ? '#E8455A' : 'currentColor' ?>"
                         stroke-width="2">
                      <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
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

  <script>const IS_LOGGED = <?= $is_logged ?>;</script>
  <script src="../js/dashboard.js"></script>
</body>
</html>