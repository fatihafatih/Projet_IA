<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ─── Connexion DB ──────────────────────────────────────────────────────────────
require_once '../includes/connexionbd.php';

// ─── Filtre status : enum('actif','inactif','en_attente') ─────────────────────
// Dans outils_ia, les outils publiés ont status = 'actif'
$status_filter = "WHERE o.status = 'actif'";
$AND           = "AND";

// ─── Récupérer TOUTES les statistiques ────────────────────────────────────────
$stats = [];
try {

    // ── KPIs Outils ─────────────────────────────────────────────────────────────
    $stats['total_models']   = (int) $pdo->query(
        "SELECT COUNT(*) FROM outils_ia o WHERE o.status = 'actif'"
    )->fetchColumn();

    $stats['total_en_attente'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM outils_ia WHERE status = 'en_attente'"
    )->fetchColumn();

    $stats['total_inactif'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM outils_ia WHERE status = 'inactif'"
    )->fetchColumn();

    // ── KPIs Users ───────────────────────────────────────────────────────────────
    $stats['total_users']     = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['total_adherents'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='adherent'")->fetchColumn();
    $stats['total_visitors']  = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='visiteur'")->fetchColumn();
    $stats['total_admins']    = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();

    // ── Par Catégorie (JOIN categorie) ───────────────────────────────────────────
    $stats['categories'] = $pdo->query(
        "SELECT c.name AS categorie, COUNT(o.ID_OUTILS_IA) AS count
         FROM categorie c
         LEFT JOIN outils_ia o ON o.ID_CATEGORIE = c.ID_CATEGORIE AND o.status = 'actif'
         GROUP BY c.ID_CATEGORIE, c.name
         HAVING count > 0
         ORDER BY count DESC"
    )->fetchAll() ?: [];

    // ── Tarifs : Gratuits vs Payants ─────────────────────────────────────────────
    $row = $pdo->query(
        "SELECT
            SUM(CASE WHEN prix = 0 OR prix IS NULL THEN 1 ELSE 0 END) AS free,
            SUM(CASE WHEN prix  > 0                THEN 1 ELSE 0 END) AS paid
         FROM tarif"
    )->fetch();
    $stats['pricing'] = [
        'free' => (int)($row['free'] ?? 0),
        'paid' => (int)($row['paid'] ?? 0),
    ];
    $stats['total_tarifs'] = $stats['pricing']['free'] + $stats['pricing']['paid'];

    // ── Distribution des périodes de tarif ───────────────────────────────────────
    $stats['tarif_periodes'] = $pdo->query(
        "SELECT periode, COUNT(*) AS count FROM tarif GROUP BY periode ORDER BY count DESC"
    )->fetchAll() ?: [];

    // ── Tranches de prix (tarif) ─────────────────────────────────────────────────
    $stats['price_ranges'] = $pdo->query(
        "SELECT
            SUM(CASE WHEN prix = 0 OR prix IS NULL THEN 1 ELSE 0 END) AS gratuit,
            SUM(CASE WHEN prix > 0 AND prix <= 10  THEN 1 ELSE 0 END) AS low,
            SUM(CASE WHEN prix > 10 AND prix <= 20 THEN 1 ELSE 0 END) AS mid,
            SUM(CASE WHEN prix > 20                THEN 1 ELSE 0 END) AS high
         FROM tarif"
    )->fetch();

    // ── Performance : moyennes globales ─────────────────────────────────────────
    $row = $pdo->query(
        "SELECT
            ROUND(AVG(p.rapidite),    1) AS avg_rapidite,
            ROUND(AVG(p.qualite),     1) AS avg_qualite,
            ROUND(AVG(p.qualite_image),1) AS avg_qualite_image,
            ROUND(AVG(p.credibilite), 1) AS avg_credibilite,
            ROUND(AVG(p.score_global),1) AS avg_score_global
         FROM performance p
         INNER JOIN outils_ia o ON o.ID_OUTILS_IA = p.ID_OUTILS_IA
         WHERE o.status = 'actif'"
    )->fetch();
    $stats['perf_avg'] = [
        'rapidite'      => $row['avg_rapidite']      ?? 0,
        'qualite'       => $row['avg_qualite']        ?? 0,
        'qualite_image' => $row['avg_qualite_image']  ?? 0,
        'credibilite'   => $row['avg_credibilite']    ?? 0,
        'score_global'  => $row['avg_score_global']   ?? 0,
    ];

    // ── Performance par catégorie ────────────────────────────────────────────────
    $stats['perf_by_category'] = $pdo->query(
        "SELECT c.name AS categorie,
                ROUND(AVG(p.score_global), 1) AS avg_score,
                ROUND(AVG(p.rapidite),     1) AS avg_rapidite
         FROM performance p
         INNER JOIN outils_ia o ON o.ID_OUTILS_IA = p.ID_OUTILS_IA
         INNER JOIN categorie c  ON c.ID_CATEGORIE  = o.ID_CATEGORIE
         WHERE o.status = 'actif'
         GROUP BY c.ID_CATEGORIE, c.name
         ORDER BY avg_score DESC"
    )->fetchAll() ?: [];

    // ── Top 5 Score Global ───────────────────────────────────────────────────────
    $stats['top_score'] = $pdo->query(
        "SELECT o.nom, o.logo_url AS logo, c.name AS categorie,
                p.score_global, p.rapidite, p.qualite
         FROM performance p
         INNER JOIN outils_ia o ON o.ID_OUTILS_IA = p.ID_OUTILS_IA
         LEFT  JOIN categorie c  ON c.ID_CATEGORIE  = o.ID_CATEGORIE
         WHERE o.status = 'actif'
         ORDER BY p.score_global DESC
         LIMIT 5"
    )->fetchAll() ?: [];

    // ── Top 5 Rapidité ───────────────────────────────────────────────────────────
    $stats['top_rapid'] = $pdo->query(
        "SELECT o.nom, o.logo_url AS logo, c.name AS categorie, p.rapidite
         FROM performance p
         INNER JOIN outils_ia o ON o.ID_OUTILS_IA = p.ID_OUTILS_IA
         LEFT  JOIN categorie c  ON c.ID_CATEGORIE  = o.ID_CATEGORIE
         WHERE o.status = 'actif' AND p.rapidite IS NOT NULL
         ORDER BY p.rapidite DESC
         LIMIT 5"
    )->fetchAll() ?: [];

    // ── Top 5 Qualité ────────────────────────────────────────────────────────────
    $stats['top_qualite'] = $pdo->query(
        "SELECT o.nom, o.logo_url AS logo, c.name AS categorie, p.qualite
         FROM performance p
         INNER JOIN outils_ia o ON o.ID_OUTILS_IA = p.ID_OUTILS_IA
         LEFT  JOIN categorie c  ON c.ID_CATEGORIE  = o.ID_CATEGORIE
         WHERE o.status = 'actif' AND p.qualite IS NOT NULL
         ORDER BY p.qualite DESC
         LIMIT 5"
    )->fetchAll() ?: [];

    // ── Top 5 Global Rating (outils_ia.global_rating) ───────────────────────────
    $stats['top_rating'] = $pdo->query(
        "SELECT o.nom, o.logo_url AS logo, c.name AS categorie, o.global_rating
         FROM outils_ia o
         LEFT JOIN categorie c ON c.ID_CATEGORIE = o.ID_CATEGORIE
         WHERE o.status = 'actif' AND o.global_rating IS NOT NULL
         ORDER BY o.global_rating DESC
         LIMIT 5"
    )->fetchAll() ?: [];

    // ── Reviews : moyenne par outil ──────────────────────────────────────────────
    $stats['top_reviewed'] = $pdo->query(
        "SELECT o.nom, o.logo_url AS logo, c.name AS categorie,
                ROUND(AVG(r.rating), 2) AS avg_rating,
                COUNT(r.ID_REVIEW) AS nb_reviews
         FROM reviews r
         INNER JOIN outils_ia o ON o.ID_OUTILS_IA = r.ID_OUTILS_IA
         LEFT  JOIN categorie c  ON c.ID_CATEGORIE  = o.ID_CATEGORIE
         WHERE o.status = 'actif'
         GROUP BY o.ID_OUTILS_IA, o.nom, o.logo_url, c.name
         ORDER BY avg_rating DESC, nb_reviews DESC
         LIMIT 5"
    )->fetchAll() ?: [];

    // ── Distribution des statuts d'outils ────────────────────────────────────────
    $stats['status_dist'] = $pdo->query(
        "SELECT status, COUNT(*) AS count FROM outils_ia GROUP BY status"
    )->fetchAll() ?: [];

    // ── Contributions : outils créés par utilisateur ─────────────────────────────
    $stats['models_by_user'] = $pdo->query(
        "SELECT u.nom, COUNT(o.ID_OUTILS_IA) AS count, u.role
         FROM users u
         LEFT JOIN outils_ia o ON u.id = o.ID_USERS_CREATE
         GROUP BY u.id, u.nom, u.role
         ORDER BY count DESC
         LIMIT 8"
    )->fetchAll() ?: [];

    // ── Tarif : prix moyen par période ───────────────────────────────────────────
    $stats['avg_price_by_periode'] = $pdo->query(
        "SELECT periode, ROUND(AVG(prix), 2) AS avg_price, COUNT(*) AS count
         FROM tarif
         WHERE prix > 0
         GROUP BY periode
         ORDER BY avg_price DESC"
    )->fetchAll() ?: [];

    // ── Statistiques globales ────────────────────────────────────────────────────
    $row = $pdo->query(
        "SELECT
            COUNT(DISTINCT o.ID_CATEGORIE)           AS nb_categories,
            ROUND(AVG(o.global_rating), 2)           AS avg_global_rating,
            (SELECT ROUND(AVG(prix),2) FROM tarif WHERE prix>0) AS avg_price_paid,
            (SELECT MIN(prix) FROM tarif WHERE prix>0)          AS min_price,
            (SELECT MAX(prix) FROM tarif)                        AS max_price
         FROM outils_ia o
         WHERE o.status = 'actif'"
    )->fetch();
    $stats['global'] = [
        'nb_categories'    => (int)($row['nb_categories']    ?? 0),
        'avg_global_rating'=> (float)($row['avg_global_rating'] ?? 0),
        'avg_price_paid'   => (float)($row['avg_price_paid']   ?? 0),
        'min_price'        => (float)($row['min_price']        ?? 0),
        'max_price'        => (float)($row['max_price']        ?? 0),
        'avg_rapidite'     => (float)($stats['perf_avg']['rapidite'] ?? 0),
    ];

} catch (PDOException $e) {
    die("Erreur SQL : " . $e->getMessage());
}

// ─── Préparer les données JS ───────────────────────────────────────────────────
$js = [
    'categories'      => array_column($stats['categories'],          'categorie'),
    'category_counts' => array_map('intval', array_column($stats['categories'], 'count')),
    'perf_cat_labels' => array_column($stats['perf_by_category'],    'categorie'),
    'perf_cat_scores' => array_map('floatval', array_column($stats['perf_by_category'], 'avg_score')),
    'perf_cat_rapid'  => array_map('floatval', array_column($stats['perf_by_category'], 'avg_rapidite')),
    'periode_labels'  => array_column($stats['tarif_periodes'],      'periode'),
    'periode_counts'  => array_map('intval', array_column($stats['tarif_periodes'], 'count')),
    'price_per_labels'=> array_column($stats['avg_price_by_periode'],'periode'),
    'price_per_values'=> array_map('floatval', array_column($stats['avg_price_by_periode'], 'avg_price')),
    'user_labels'     => array_column($stats['models_by_user'],      'nom'),
    'user_counts'     => array_map('intval', array_column($stats['models_by_user'], 'count')),
    'status_labels'   => array_column($stats['status_dist'],         'status'),
    'status_counts'   => array_map('intval', array_column($stats['status_dist'], 'count')),
];
foreach ($js as &$v) { if (empty($v)) $v = []; }
unset($v);
$json = array_map('json_encode', $js);

// ─── Helpers ──────────────────────────────────────────────────────────────────
function model_img(string $img, string $alt, string $size = '44px'): string {
    $ph = 'https://ui-avatars.com/api/?name='.urlencode($alt).'&background=1a1f2e&color=6c8ebf&size=128&bold=true&length=2';
    $src = !empty($img) ? htmlspecialchars($img) : $ph;
    return '<img src="'.$src.'" alt="'.htmlspecialchars($alt).'"
                 onerror="this.src=\''.addslashes($ph).'\'"
                 style="width:'.$size.';height:'.$size.';object-fit:cover;border-radius:10px;border:2px solid rgba(255,255,255,.08);flex-shrink:0;">';
}

function rating_badge(float $v): string {
    if ($v >= 4)   return '<span class="ib ib-s1"><i class="fas fa-star"></i> '.number_format($v,2).'</span>';
    if ($v >= 3)   return '<span class="ib ib-s2"><i class="fas fa-star-half-alt"></i> '.number_format($v,2).'</span>';
    if ($v >= 2)   return '<span class="ib ib-s3"><i class="fas fa-chart-line"></i> '.number_format($v,2).'</span>';
    return '<span class="ib ib-s4"><i class="fas fa-seedling"></i> '.number_format($v,2).'</span>';
}

function score_bar(float $v, float $max=10, string $color='--ac1'): string {
    $pct = $max > 0 ? min(100, ($v/$max)*100) : 0;
    return '<div class="sbar"><div class="sbar-fill" style="width:'.$pct.'%;background:var('.$color.')"></div></div>';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Statistiques – AI Models Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<link rel="stylesheet" href="../styles/statistique.css">
</head>
<body>

<!-- HEADER -->
<div class="hdr">
  <div class="hdr-tag"><i class="fas fa-chart-mixed"></i> Intelligence Dashboard</div>
  <h1>Statistiques <span>AI OUTILS</span></h1>
  <p>Analyse complète de votre bibliothèque de modèles d'intelligence artificielle</p>
  <div class="status-pill ok"><i class="fas fa-circle-check"></i> Filtre actif : status = actif</div>
</div>

<div class="wrap">

<!-- ═══════════════════════════════════════════════════════════
     KPI – VUE D'ENSEMBLE
═══════════════════════════════════════════════════════════ -->
<div class="sec" data-reveal>
  <div class="sec-title">
    <div class="dot" style="background:var(--ac1)"></div>
    <h2>Vue d'ensemble</h2>
    <span class="count"><?php echo date('d/m/Y'); ?></span>
  </div>
  <div class="kpi-grid">
    <div class="kpi c1">
      <div class="kpi-top">
        <div>
          <div class="kpi-num" data-target="<?php echo $stats['total_models']; ?>">0</div>
          <div class="kpi-label">Outils IA actifs</div>
        </div>
        <div class="kpi-ico"><i class="fas fa-robot"></i></div>
      </div>
      <div class="kpi-sub">
        <span class="neutral"><i class="fas fa-clock"></i> <?php echo $stats['total_en_attente']; ?> en attente</span>
      </div>
    </div>
    <div class="kpi c2">
      <div class="kpi-top">
        <div>
          <div class="kpi-num" data-target="<?php echo $stats['global']['nb_categories']; ?>">0</div>
          <div class="kpi-label">Catégories</div>
        </div>
        <div class="kpi-ico"><i class="fas fa-layer-group"></i></div>
      </div>
      <div class="kpi-sub"><span class="neutral"><i class="fas fa-tag"></i> types distincts</span></div>
    </div>
    <div class="kpi c3">
      <div class="kpi-top">
        <div>
          <div class="kpi-num" data-target="<?php echo $stats['pricing']['free']; ?>">0</div>
          <div class="kpi-label">Plans Gratuits</div>
        </div>
        <div class="kpi-ico"><i class="fas fa-gift"></i></div>
      </div>
      <div class="kpi-sub">
        <span class="up"><?php echo $stats['total_tarifs'] > 0 ? round(($stats['pricing']['free']/$stats['total_tarifs'])*100) : 0; ?>%</span>
        <span class="neutral"> des plans</span>
      </div>
    </div>
    <div class="kpi c4">
      <div class="kpi-top">
        <div>
          <div class="kpi-num" data-target="<?php echo $stats['pricing']['paid']; ?>">0</div>
          <div class="kpi-label">Plans Payants</div>
        </div>
        <div class="kpi-ico"><i class="fas fa-credit-card"></i></div>
      </div>
      <div class="kpi-sub">
        <span class="neutral">Moy. <?php echo number_format($stats['global']['avg_price_paid'],2); ?> €/plan</span>
      </div>
    </div>
    <div class="kpi c5">
      <div class="kpi-top">
        <div>
          <div class="kpi-num" data-target="<?php echo $stats['total_users']; ?>">0</div>
          <div class="kpi-label">Utilisateurs</div>
        </div>
        <div class="kpi-ico"><i class="fas fa-users"></i></div>
      </div>
      <div class="kpi-sub"><span class="neutral"><i class="fas fa-user-shield"></i> <?php echo $stats['total_admins']; ?> admins</span></div>
    </div>
    <div class="kpi c6">
      <div class="kpi-top">
        <div>
          <div class="kpi-num" data-target="<?php echo $stats['total_adherents']; ?>">0</div>
          <div class="kpi-label">Adhérents</div>
        </div>
        <div class="kpi-ico"><i class="fas fa-id-card"></i></div>
      </div>
      <div class="kpi-sub"><span class="neutral"><?php echo $stats['total_visitors']; ?> visiteurs</span></div>
    </div>
    <div class="kpi c7">
      <div class="kpi-top">
        <div>
          <div class="kpi-num"><?php echo number_format($stats['perf_avg']['score_global'],1); ?></div>
          <div class="kpi-label">Score Global Moy. /10</div>
        </div>
        <div class="kpi-ico"><i class="fas fa-bolt"></i></div>
      </div>
      <div class="kpi-sub"><span class="up"><i class="fas fa-tachometer-alt"></i> Performance globale</span></div>
    </div>
    <div class="kpi c8">
      <div class="kpi-top">
        <div>
          <div class="kpi-num"><?php echo number_format($stats['global']['avg_global_rating'],2); ?></div>
          <div class="kpi-label">Rating Global Moy.</div>
        </div>
        <div class="kpi-ico"><i class="fas fa-star"></i></div>
      </div>
      <div class="kpi-sub"><span class="neutral">Note moyenne des outils</span></div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     CHARTS – DISTRIBUTION
═══════════════════════════════════════════════════════════ -->
<div class="sec" data-reveal>
  <div class="sec-title">
    <div class="dot" style="background:var(--ac3)"></div>
    <h2>Distribution & Répartition</h2>
  </div>
  <div class="g2 g3" style="grid-template-columns:repeat(auto-fit,minmax(300px,1fr))">

    <!-- Outils par Catégorie -->
    <div class="cc">
      <div class="cc-head">
        <div class="cc-title"><i class="fas fa-chart-pie" style="color:var(--ac1)"></i> Outils par Catégorie</div>
        <button class="cc-dl" onclick="dlChart('catChart')" title="Télécharger"><i class="fas fa-download"></i></button>
      </div>
      <div class="cc-body">
        <?php if(!empty($stats['categories'])): ?>
          <canvas id="catChart"></canvas>
        <?php else: ?><div class="nodata"><i class="fas fa-inbox"></i>Aucune donnée</div><?php endif; ?>
      </div>
    </div>

    <!-- Plans Gratuits vs Payants -->
    <div class="cc">
      <div class="cc-head">
        <div class="cc-title"><i class="fas fa-circle-half-stroke" style="color:var(--ac3)"></i> Plans Gratuits vs Payants</div>
        <button class="cc-dl" onclick="dlChart('priceDoughnut')" title="Télécharger"><i class="fas fa-download"></i></button>
      </div>
      <div class="cc-body donut-wrap">
        <?php if($stats['total_tarifs'] > 0): ?>
          <canvas id="priceDoughnut"></canvas>
          <div class="donut-center">
            <strong><?php echo $stats['total_tarifs']; ?></strong>
            <small>plans</small>
          </div>
        <?php else: ?><div class="nodata"><i class="fas fa-inbox"></i>Aucune donnée</div><?php endif; ?>
      </div>
    </div>

    <!-- Statut des outils -->
    <div class="cc">
      <div class="cc-head">
        <div class="cc-title"><i class="fas fa-circle-check" style="color:var(--ac5)"></i> Statut des Outils</div>
        <button class="cc-dl" onclick="dlChart('statusChart')" title="Télécharger"><i class="fas fa-download"></i></button>
      </div>
      <div class="cc-body donut-wrap">
        <?php if(!empty($stats['status_dist'])): ?>
          <canvas id="statusChart"></canvas>
        <?php else: ?><div class="nodata"><i class="fas fa-inbox"></i>Aucune donnée</div><?php endif; ?>
      </div>
    </div>

    <!-- Score par Catégorie -->
    <div class="cc">
      <div class="cc-head">
        <div class="cc-title"><i class="fas fa-tachometer-alt" style="color:var(--ac2)"></i> Score Global / Catégorie</div>
        <button class="cc-dl" onclick="dlChart('perfCatChart')" title="Télécharger"><i class="fas fa-download"></i></button>
      </div>
      <div class="cc-body">
        <?php if(!empty($stats['perf_by_category'])): ?>
          <canvas id="perfCatChart"></canvas>
        <?php else: ?><div class="nodata"><i class="fas fa-inbox"></i>Aucune donnée performance</div><?php endif; ?>
      </div>
    </div>

    <!-- Périodes de tarif -->
    <div class="cc">
      <div class="cc-head">
        <div class="cc-title"><i class="fas fa-calendar-alt" style="color:var(--ac6)"></i> Plans par Période</div>
        <button class="cc-dl" onclick="dlChart('periodeChart')" title="Télécharger"><i class="fas fa-download"></i></button>
      </div>
      <div class="cc-body donut-wrap">
        <?php if(!empty($stats['tarif_periodes'])): ?>
          <canvas id="periodeChart"></canvas>
        <?php else: ?><div class="nodata"><i class="fas fa-inbox"></i>Aucune donnée</div><?php endif; ?>
      </div>
    </div>

    <!-- Prix moyen par période -->
    <div class="cc">
      <div class="cc-head">
        <div class="cc-title"><i class="fas fa-euro-sign" style="color:var(--ac4)"></i> Prix Moyen par Période</div>
        <button class="cc-dl" onclick="dlChart('pricePeriodeChart')" title="Télécharger"><i class="fas fa-download"></i></button>
      </div>
      <div class="cc-body">
        <?php if(!empty($stats['avg_price_by_periode'])): ?>
          <canvas id="pricePeriodeChart"></canvas>
        <?php else: ?><div class="nodata"><i class="fas fa-inbox"></i>Aucun plan payant</div><?php endif; ?>
      </div>
    </div>

    <!-- Rapidité par catégorie -->
    <div class="cc">
      <div class="cc-head">
        <div class="cc-title"><i class="fas fa-rocket" style="color:var(--ac3)"></i> Rapidité Moy. / Catégorie</div>
        <button class="cc-dl" onclick="dlChart('rapCatChart')" title="Télécharger"><i class="fas fa-download"></i></button>
      </div>
      <div class="cc-body">
        <?php if(!empty($stats['perf_by_category'])): ?>
          <canvas id="rapCatChart"></canvas>
        <?php else: ?><div class="nodata"><i class="fas fa-inbox"></i>Aucune donnée</div><?php endif; ?>
      </div>
    </div>

    <!-- Contributions utilisateurs -->
    <div class="cc">
      <div class="cc-head">
        <div class="cc-title"><i class="fas fa-users" style="color:var(--ac5)"></i> Outils créés par Utilisateur</div>
        <button class="cc-dl" onclick="dlChart('userChart')" title="Télécharger"><i class="fas fa-download"></i></button>
      </div>
      <div class="cc-body">
        <?php if(!empty($stats['models_by_user'])): ?>
          <canvas id="userChart"></canvas>
        <?php else: ?><div class="nodata"><i class="fas fa-inbox"></i>Aucune donnée</div><?php endif; ?>
      </div>
    </div>

  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     TRANCHES DE PRIX
═══════════════════════════════════════════════════════════ -->
<div class="sec" data-reveal>
  <div class="sec-title">
    <div class="dot" style="background:var(--ac4)"></div>
    <h2>Analyse des Plans Tarifaires</h2>
  </div>
  <div class="cc" style="margin-bottom:0">
    <div class="cc-head"><div class="cc-title"><i class="fas fa-tags" style="color:var(--ac4)"></i> Tranches de Prix</div></div>
    <div class="price-range-grid">
      <?php
        $pr = $stats['price_ranges'] ?? [];
        $pr_data = [
          ['label'=>'Gratuits',  'val'=>(int)($pr['gratuit']??0), 'color'=>'var(--ac3)', 'icon'=>'fa-gift'],
          ['label'=>'≤ 10 €',   'val'=>(int)($pr['low']??0),     'color'=>'var(--ac1)', 'icon'=>'fa-euro-sign'],
          ['label'=>'10 – 20 €','val'=>(int)($pr['mid']??0),     'color'=>'var(--ac4)', 'icon'=>'fa-coins'],
          ['label'=>'> 20 €',   'val'=>(int)($pr['high']??0),    'color'=>'var(--ac2)', 'icon'=>'fa-gem'],
        ];
        foreach($pr_data as $p): ?>
        <div class="pr-pill">
          <div style="color:<?php echo $p['color']; ?>;font-size:1.1rem;margin-bottom:6px"><i class="fas <?php echo $p['icon']; ?>"></i></div>
          <div class="pr-n" style="color:<?php echo $p['color']; ?>"><?php echo $p['val']; ?></div>
          <div class="pr-l"><?php echo $p['label']; ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     CLASSEMENTS TOP 5
═══════════════════════════════════════════════════════════ -->
<div class="sec" data-reveal>
  <div class="sec-title">
    <div class="dot" style="background:var(--ac4)"></div>
    <h2>Classements Top 5</h2>
  </div>
  <div class="g2" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr))">

    <!-- Top Score Global -->
    <div class="tc">
      <div class="tc-head"><div class="tc-title"><i class="fas fa-trophy" style="color:var(--ac4)"></i> Meilleur Score Global</div></div>
      <?php if(!empty($stats['top_score'])): ?>
      <div class="rlist">
        <?php foreach($stats['top_score'] as $i => $m): ?>
        <div class="ri">
          <div class="ri-rank"><?php echo $i+1; ?></div>
          <?php echo model_img($m['logo']??'', $m['nom']??'AI', '38px'); ?>
          <div class="ri-info">
            <div class="ri-name"><?php echo htmlspecialchars($m['nom']??'N/A'); ?></div>
            <div class="ri-sub"><?php echo htmlspecialchars($m['categorie']??'—'); ?></div>
            <?php echo score_bar((float)($m['score_global']??0), 10, '--ac1'); ?>
          </div>
          <div class="ri-val" style="color:var(--ac1)"><?php echo number_format($m['score_global']??0,1); ?>/10</div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?><div class="nodata"><i class="fas fa-trophy"></i>Aucune donnée performance</div><?php endif; ?>
    </div>

    <!-- Top Rapidité -->
    <div class="tc">
      <div class="tc-head"><div class="tc-title"><i class="fas fa-rocket" style="color:var(--ac2)"></i> Top Rapidité</div></div>
      <?php if(!empty($stats['top_rapid'])): ?>
      <div class="rlist">
        <?php foreach($stats['top_rapid'] as $i => $m): ?>
        <div class="ri">
          <div class="ri-rank"><?php echo $i+1; ?></div>
          <?php echo model_img($m['logo']??'', $m['nom']??'AI', '38px'); ?>
          <div class="ri-info">
            <div class="ri-name"><?php echo htmlspecialchars($m['nom']??'N/A'); ?></div>
            <div class="ri-sub"><?php echo htmlspecialchars($m['categorie']??'—'); ?></div>
            <?php echo score_bar((float)($m['rapidite']??0), 10, '--ac2'); ?>
          </div>
          <div class="ri-val" style="color:var(--ac2)"><?php echo number_format($m['rapidite']??0,1); ?>/10</div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?><div class="nodata"><i class="fas fa-rocket"></i>Aucune donnée</div><?php endif; ?>
    </div>

    <!-- Top Qualité -->
    <div class="tc">
      <div class="tc-head"><div class="tc-title"><i class="fas fa-star" style="color:var(--ac5)"></i> Top Qualité</div></div>
      <?php if(!empty($stats['top_qualite'])): ?>
      <div class="rlist">
        <?php foreach($stats['top_qualite'] as $i => $m): ?>
        <div class="ri">
          <div class="ri-rank"><?php echo $i+1; ?></div>
          <?php echo model_img($m['logo']??'', $m['nom']??'AI', '38px'); ?>
          <div class="ri-info">
            <div class="ri-name"><?php echo htmlspecialchars($m['nom']??'N/A'); ?></div>
            <div class="ri-sub"><?php echo htmlspecialchars($m['categorie']??'—'); ?></div>
            <?php echo score_bar((float)($m['qualite']??0), 10, '--ac5'); ?>
          </div>
          <div class="ri-val" style="color:var(--ac5)"><?php echo number_format($m['qualite']??0,1); ?>/10</div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?><div class="nodata"><i class="fas fa-star"></i>Aucune donnée</div><?php endif; ?>
    </div>

    <!-- Top Global Rating -->
    <div class="tc">
      <div class="tc-head"><div class="tc-title"><i class="fas fa-medal" style="color:var(--ac3)"></i> Top Rating Global</div></div>
      <?php if(!empty($stats['top_rating'])): ?>
      <div class="rlist">
        <?php foreach($stats['top_rating'] as $i => $m): ?>
        <div class="ri">
          <div class="ri-rank"><?php echo $i+1; ?></div>
          <?php echo model_img($m['logo']??'', $m['nom']??'AI', '38px'); ?>
          <div class="ri-info">
            <div class="ri-name"><?php echo htmlspecialchars($m['nom']??'N/A'); ?></div>
            <div class="ri-sub"><?php echo htmlspecialchars($m['categorie']??'—'); ?></div>
            <?php echo score_bar((float)($m['global_rating']??0), 5, '--ac3'); ?>
          </div>
          <div class="ri-val" style="color:var(--ac3)"><?php echo number_format($m['global_rating']??0,2); ?>/5</div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?><div class="nodata"><i class="fas fa-medal"></i>Aucune donnée</div><?php endif; ?>
    </div>

    <!-- Top Reviews -->
    <div class="tc">
      <div class="tc-head"><div class="tc-title"><i class="fas fa-comment-star" style="color:var(--ac6)"></i> Mieux Notés (Reviews)</div></div>
      <?php if(!empty($stats['top_reviewed'])): ?>
      <div class="rlist">
        <?php foreach($stats['top_reviewed'] as $i => $m): ?>
        <div class="ri">
          <div class="ri-rank"><?php echo $i+1; ?></div>
          <?php echo model_img($m['logo']??'', $m['nom']??'AI', '38px'); ?>
          <div class="ri-info">
            <div class="ri-name"><?php echo htmlspecialchars($m['nom']??'N/A'); ?></div>
            <div class="ri-sub"><?php echo (int)$m['nb_reviews']; ?> avis · <?php echo htmlspecialchars($m['categorie']??'—'); ?></div>
          </div>
          <div><?php echo rating_badge((float)($m['avg_rating']??0)); ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?><div class="nodata"><i class="fas fa-comment"></i>Aucun avis</div><?php endif; ?>
    </div>

    <!-- Contributions utilisateurs -->
    <div class="tc">
      <div class="tc-head"><div class="tc-title"><i class="fas fa-user-pen" style="color:var(--ac5)"></i> Contributions Utilisateurs</div></div>
      <?php if(!empty($stats['models_by_user'])):
        $max_contrib = max(array_column($stats['models_by_user'], 'count') ?: [1]);
      ?>
      <div>
        <?php foreach($stats['models_by_user'] as $u):
          $initials = implode('', array_map(fn($w)=>strtoupper($w[0]), array_slice(explode(' ', $u['nom']??'?'),0,2)));
          $pct = $max_contrib > 0 ? round(($u['count']/$max_contrib)*100) : 0;
          $role_badge = match($u['role']??'') {
            'admin'    => '<span class="badge r">admin</span>',
            'adherent' => '<span class="badge b">adhérent</span>',
            default    => '<span class="badge" style="background:rgba(255,255,255,.06);color:var(--muted)">visiteur</span>'
          };
        ?>
        <div class="user-row">
          <div class="user-avatar"><?php echo $initials; ?></div>
          <div style="flex:1;min-width:0">
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px">
              <span class="user-name"><?php echo htmlspecialchars($u['nom']??''); ?></span>
              <?php echo $role_badge; ?>
            </div>
            <div class="pbar">
              <div class="pbar-track"><div class="pbar-fill" style="width:<?php echo $pct; ?>%"></div></div>
              <span><?php echo (int)$u['count']; ?></span>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?><div class="nodata"><i class="fas fa-user"></i>Aucune donnée</div><?php endif; ?>
    </div>

  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     STATISTIQUES GLOBALES
═══════════════════════════════════════════════════════════ -->
<div class="sec" data-reveal>
  <div class="sec-title">
    <div class="dot" style="background:var(--ac2)"></div>
    <h2>Statistiques Globales</h2>
  </div>
  <div class="cc">
    <div class="gstat-grid">
      <div class="gstat">
        <div class="gstat-lbl">Rapidité Moyenne</div>
        <div class="gstat-val" style="color:var(--ac1)"><?php echo number_format($stats['perf_avg']['rapidite'],1); ?><small>/10</small></div>
        <div class="stars">
          <?php $r=(float)$stats['perf_avg']['rapidite'];
          for($i=1;$i<=5;$i++){
            if($i<=$r/2) echo '<i class="fas fa-star"></i>';
            elseif($i-0.5<=$r/2) echo '<i class="fas fa-star-half-alt"></i>';
            else echo '<i class="far fa-star"></i>';
          } ?>
        </div>
      </div>
      <div class="gstat">
        <div class="gstat-lbl">Qualité Moyenne</div>
        <div class="gstat-val" style="color:var(--ac5)"><?php echo number_format($stats['perf_avg']['qualite'],1); ?><small>/10</small></div>
        <div class="gstat-sub">score de qualité moyen</div>
      </div>
      <div class="gstat">
        <div class="gstat-lbl">Crédibilité Moyenne</div>
        <div class="gstat-val" style="color:var(--ac6)"><?php echo number_format($stats['perf_avg']['credibilite'],1); ?><small>/10</small></div>
        <div class="gstat-sub">indice de fiabilité</div>
      </div>
      <div class="gstat">
        <div class="gstat-lbl">Score Global Moyen</div>
        <div class="gstat-val" style="color:var(--ac3)"><?php echo number_format($stats['perf_avg']['score_global'],1); ?><small>/10</small></div>
        <div class="gstat-sub">performance globale</div>
      </div>
      <div class="gstat">
        <div class="gstat-lbl">Prix Moyen (Payants)</div>
        <div class="gstat-val" style="color:var(--ac4)"><?php echo number_format($stats['global']['avg_price_paid'],2); ?><small> €</small></div>
        <div class="gstat-sub">par plan payant</div>
      </div>
      <div class="gstat">
        <div class="gstat-lbl">Prix Minimum</div>
        <div class="gstat-val" style="color:var(--ac3)"><?php echo number_format($stats['global']['min_price'],2); ?><small> €</small></div>
        <div class="gstat-sub">plan le moins cher</div>
      </div>
      <div class="gstat">
        <div class="gstat-lbl">Prix Maximum</div>
        <div class="gstat-val" style="color:var(--ac2)"><?php echo number_format($stats['global']['max_price'],2); ?><small> €</small></div>
        <div class="gstat-sub">plan le plus cher</div>
      </div>
      <div class="gstat">
        <div class="gstat-lbl">Utilisateurs Total</div>
        <div class="gstat-val" style="color:var(--ac5)"><?php echo $stats['total_users']; ?></div>
        <div class="gstat-sub"><?php echo $stats['total_admins']; ?> admins · <?php echo $stats['total_adherents']; ?> adhérents</div>
      </div>
    </div>
  </div>
</div>

</div><!-- /.wrap -->

<script>
const D = {
  cats:         <?php echo $json['categories']; ?>,
  catC:         <?php echo $json['category_counts']; ?>,
  perfCatL:     <?php echo $json['perf_cat_labels']; ?>,
  perfCatS:     <?php echo $json['perf_cat_scores']; ?>,
  perfCatR:     <?php echo $json['perf_cat_rapid']; ?>,
  periodeL:     <?php echo $json['periode_labels']; ?>,
  periodeC:     <?php echo $json['periode_counts']; ?>,
  pricePL:      <?php echo $json['price_per_labels']; ?>,
  pricePV:      <?php echo $json['price_per_values']; ?>,
  userL:        <?php echo $json['user_labels']; ?>,
  userC:        <?php echo $json['user_counts']; ?>,
  statusL:      <?php echo $json['status_labels']; ?>,
  statusC:      <?php echo $json['status_counts']; ?>,
  free:         <?php echo (int)$stats['pricing']['free']; ?>,
  paid:         <?php echo (int)$stats['pricing']['paid']; ?>,
};

const PAL = ['#4d9de0','#3ecf8e','#e15f41','#f7b731','#9b59b6','#1abc9c','#e74c3c','#2ecc71'];

Chart.defaults.color       = '#6b7b96';
Chart.defaults.borderColor = 'rgba(255,255,255,.06)';
Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.font.size   = 12;

const defaults = (extra={}) => Object.assign({
  responsive:true, maintainAspectRatio:false,
  plugins:{ legend:{ position:'bottom', labels:{ padding:14, usePointStyle:true, pointStyle:'circle', boxWidth:8 } } }
}, extra);

function dlChart(id){ const c=document.getElementById(id); if(!c)return; const a=document.createElement('a'); a.download=id+'.png'; a.href=c.toDataURL('image/png',1); a.click(); }
function mkPie(id, labels, data, opts={}){
  const el=document.getElementById(id); if(!el||!labels.length) return;
  return new Chart(el,{type:'doughnut', data:{labels, datasets:[{data, backgroundColor:PAL, borderColor:'#121826', borderWidth:3, hoverBorderWidth:0, hoverOffset:6}]}, options:defaults(opts)});
}

document.addEventListener('DOMContentLoaded',()=>{

  // 1. Outils par Catégorie
  if(D.cats.length) mkPie('catChart', D.cats, D.catC);

  // 2. Gratuits vs Payants (plans tarif)
  if(D.free+D.paid>0) new Chart(document.getElementById('priceDoughnut'),{type:'doughnut',
    data:{labels:['Gratuits','Payants'],datasets:[{data:[D.free,D.paid],backgroundColor:['#3ecf8e','#e15f41'],borderColor:'#121826',borderWidth:3,hoverOffset:6}]},
    options:defaults({cutout:'68%'})
  });

  // 3. Statut des outils
  if(D.statusL.length){
    const colors = D.statusL.map(s => s==='actif'?'#3ecf8e': s==='en_attente'?'#f7b731':'#e15f41');
    new Chart(document.getElementById('statusChart'),{type:'doughnut',
      data:{labels:D.statusL, datasets:[{data:D.statusC, backgroundColor:colors, borderColor:'#121826', borderWidth:3, hoverOffset:6}]},
      options:defaults({cutout:'60%'})
    });
  }

  // 4. Score Global par Catégorie
  if(D.perfCatL.length) new Chart(document.getElementById('perfCatChart'),{type:'bar',
    data:{labels:D.perfCatL, datasets:[{label:'Score Global',data:D.perfCatS, backgroundColor:PAL, borderRadius:8, borderWidth:0}]},
    options:defaults({plugins:{legend:{display:false}}, scales:{y:{min:0,max:10,grid:{color:'rgba(255,255,255,.04)'}}, x:{grid:{display:false}}}})
  });

  // 5. Plans par Période
  if(D.periodeL.length) mkPie('periodeChart', D.periodeL, D.periodeC);

  // 6. Prix moyen par période
  if(D.pricePL.length) new Chart(document.getElementById('pricePeriodeChart'),{type:'bar',
    data:{labels:D.pricePL, datasets:[{data:D.pricePV, backgroundColor:D.pricePL.map((_,i)=>PAL[i%PAL.length]), borderRadius:8, borderWidth:0}]},
    options:defaults({indexAxis:'y', plugins:{legend:{display:false}}, scales:{x:{beginAtZero:true, title:{display:true,text:'€'}, grid:{color:'rgba(255,255,255,.04)'}}, y:{grid:{display:false}}}})
  });

  // 7. Rapidité Moy. par catégorie
  if(D.perfCatL.length) new Chart(document.getElementById('rapCatChart'),{type:'line',
    data:{labels:D.perfCatL, datasets:[{data:D.perfCatR, borderColor:'#e15f41', backgroundColor:'rgba(225,95,65,.1)', pointBackgroundColor:'#e15f41', pointBorderColor:'#121826', pointBorderWidth:2, pointRadius:5, tension:.4, fill:true, borderWidth:2.5}]},
    options:defaults({plugins:{legend:{display:false}}, scales:{y:{min:0,max:10, title:{display:true,text:'Rapidité /10'}, grid:{color:'rgba(255,255,255,.04)'}}, x:{grid:{display:false}}}})
  });

  // 8. Contributions utilisateurs
  if(D.userL.length) new Chart(document.getElementById('userChart'),{type:'bar',
    data:{labels:D.userL, datasets:[{data:D.userC, backgroundColor:'rgba(155,89,182,.6)', borderColor:'#9b59b6', borderRadius:8, borderWidth:0}]},
    options:defaults({indexAxis:'y', plugins:{legend:{display:false}}, scales:{x:{beginAtZero:true,ticks:{stepSize:1}, grid:{color:'rgba(255,255,255,.04)'}}, y:{grid:{display:false}}}})
  });

  // ── Compteurs KPI ──
  document.querySelectorAll('.kpi-num[data-target]').forEach(el=>{
    const target=parseInt(el.dataset.target,10)||0;
    if(!target){el.textContent='0';return;}
    let cur=0; const step=Math.max(1,Math.ceil(target/50));
    const t=setInterval(()=>{ cur=Math.min(cur+step,target); el.textContent=cur; if(cur>=target)clearInterval(t); },18);
  });

  // ── Progress bars animation ──
  setTimeout(()=>{
    document.querySelectorAll('.pbar-fill,.sbar-fill').forEach(b=>b.style.transition='width .9s cubic-bezier(.4,0,.2,1)');
  },200);

  // ── Reveal on scroll ──
  const obs=new IntersectionObserver(entries=>{
    entries.forEach(e=>{ if(e.isIntersecting){ e.target.classList.add('visible'); obs.unobserve(e.target); } });
  },{threshold:.1});
  document.querySelectorAll('[data-reveal]').forEach(el=>obs.observe(el));
});
</script>
</body>
</html>