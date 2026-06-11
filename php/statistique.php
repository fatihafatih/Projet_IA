<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ─── Connexion DB ──────────────────────────────────────────────────────────────
require_once '../includes/connexionbd.php';

// ─── Filtre status : enum('actif','inactif','en_attente') ─────────────────────
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

    // ── Contributions : outils créés par utilisateur ─────────────────────────────
    $stats['models_by_user'] = $pdo->query(
        "SELECT u.nom, COUNT(o.ID_OUTILS_IA) AS count, u.role
         FROM users u
         LEFT JOIN outils_ia o ON u.id = o.ID_USERS_CREATE
         GROUP BY u.id, u.nom, u.role
         ORDER BY count DESC
         LIMIT 8"
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
    'user_labels'     => array_column($stats['models_by_user'],      'nom'),
    'user_counts'     => array_map('intval', array_column($stats['models_by_user'], 'count')),
];
foreach ($js as &$v) { if (empty($v)) $v = []; }
unset($v);
$json = array_map('json_encode', $js);

// ─── Helpers ──────────────────────────────────────────────────────────────────
function model_img(string $img, string $alt, string $size = '44px'): string {
    $ph = 'https://ui-avatars.com/api/?name='.urlencode($alt).'&background=1B2A4A&color=F3E5AB&size=128&bold=true&length=2';
    $src = !empty($img) ? htmlspecialchars($img) : $ph;
    return '<img src="'.$src.'" alt="'.htmlspecialchars($alt).'"
                 onerror="this.src=\''.addslashes($ph).'\'"
                 style="width:'.$size.';height:'.$size.';object-fit:cover;border-radius:10px;border:2px solid rgba(243,229,171,.12);flex-shrink:0;">';
}

function rating_badge(float $v): string {
    if ($v >= 4)   return '<span class="ib ib-s1"><i class="fas fa-star"></i> '.number_format($v,2).'</span>';
    if ($v >= 3)   return '<span class="ib ib-s2"><i class="fas fa-star-half-alt"></i> '.number_format($v,2).'</span>';
    if ($v >= 2)   return '<span class="ib ib-s3"><i class="fas fa-chart-line"></i> '.number_format($v,2).'</span>';
    return '<span class="ib ib-s4"><i class="fas fa-seedling"></i> '.number_format($v,2).'</span>';
}

function score_bar(float $v, float $max=10, string $color='--ac1'): string {
    $pct = $max > 0 ? min(100, ($v/$max)*100) : 0;
    return '<div class="sbar"><div class="sbar-fill" style="width:'.$pct.'%"></div></div>';
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
<style>
/* ============================================================
   LIGHT CARDS — NAVY #1B2A4A / GOLD #F3E5AB
   ============================================================ */
:root{
  --bg-page:#f0ece0;
  --bg-card:#fffdf5;
  --bg-card2:#fdf8ec;
  --navy:#1B2A4A;
  --navy-mid:#263d6b;
  --navy-light:#e8edf5;
  --gold:#b8940a;
  --gold-soft:#F3E5AB;
  --gold-bg:#fdf5d0;
  --text:#1a2236;
  --text-muted:#6b7a94;
  --border:rgba(27,42,74,.10);
  --border-hv:rgba(27,42,74,.22);
  --ac2:#c9a80e;
  --ac3:#1B2A4A;
  --ac4:#d97706;
  --ac5:#0d7a55;
  --ac6:#7c3aed;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{
  background:
    radial-gradient(ellipse at top left,rgba(243,229,171,.35) 0%,transparent 45%),
    radial-gradient(ellipse at bottom right,rgba(27,42,74,.12) 0%,transparent 50%),
    var(--bg-page);
  color:var(--text);
  font-family:'DM Sans',sans-serif;
  min-height:100vh;
  line-height:1.6;
}

/* HEADER */
.hdr{
  position:relative;overflow:hidden;min-height:300px;
  display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;
  padding:60px 24px 50px;
  background:linear-gradient(160deg,var(--navy) 0%,#0f1e38 100%);
  border-bottom:3px solid var(--gold-soft);
}
.hdr::before{content:'';position:absolute;width:600px;height:600px;border-radius:50%;background:radial-gradient(circle,rgba(243,229,171,.10),transparent 70%);top:-300px;left:-150px;pointer-events:none}
.hdr::after{content:'AI ANALYTICS';position:absolute;font-size:9rem;font-weight:800;font-family:'Syne',sans-serif;opacity:.04;white-space:nowrap;pointer-events:none;color:var(--gold-soft);letter-spacing:.15em}
.hdr-tag{display:inline-flex;align-items:center;gap:8px;font-size:.75rem;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:var(--gold-soft);background:rgba(243,229,171,.12);border:1px solid rgba(243,229,171,.28);border-radius:100px;padding:6px 16px;margin-bottom:22px}
.hdr h1{font-family:'Syne',sans-serif;font-size:clamp(2rem,5vw,3.4rem);font-weight:800;color:#fff;letter-spacing:-.01em;line-height:1.15;margin-bottom:14px}
.hdr h1 span{color:var(--gold-soft);-webkit-text-fill-color:var(--gold-soft)}
.hdr p{color:rgba(243,229,171,.65);font-size:1rem;max-width:500px;margin-bottom:24px}
.status-pill{display:inline-flex;align-items:center;gap:7px;font-size:.78rem;font-weight:500;padding:7px 18px;border-radius:100px}
.status-pill.ok{background:rgba(13,122,85,.15);border:1px solid rgba(13,122,85,.35);color:#10b981}

/* WRAPPER */
.wrap{max-width:1400px;margin:0 auto;padding:50px 28px 80px}

/* SECTION */
.sec{margin-bottom:56px;opacity:0;transform:translateY(28px);transition:opacity .55s ease,transform .55s ease}
.sec.visible{opacity:1;transform:none}
.sec-title{display:flex;align-items:center;gap:12px;margin-bottom:28px}
.sec-title .dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;box-shadow:0 0 12px currentColor}
.sec-title h2{font-family:'Syne',sans-serif;font-size:1.05rem;letter-spacing:.12em;text-transform:uppercase;color:var(--navy);font-weight:700}

/* GRIDS */
.g2{display:grid;gap:22px}
.g3{grid-template-columns:repeat(auto-fit,minmax(300px,1fr))}
.g4{grid-template-columns:repeat(auto-fit,minmax(200px,1fr))}

/* KPI */
.kpi{position:relative;overflow:hidden;background:var(--bg-card) !important;border:1px solid var(--border);border-radius:20px;padding:26px 24px;transition:.32s ease;box-shadow:0 2px 12px rgba(27,42,74,.07)}
.kpi::before{content:'';position:absolute;inset:0;border-radius:inherit;background:linear-gradient(135deg,rgba(243,229,171,.18),transparent 60%);pointer-events:none}
.kpi:hover{transform:translateY(-7px);border-color:var(--border-hv);box-shadow:0 18px 40px rgba(27,42,74,.14),0 0 0 3px rgba(243,229,171,.4)}
.kpi-icon{width:44px;height:44px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;margin-bottom:16px;background:var(--navy);color:var(--gold-soft)}
.kpi-num{font-family:'Syne',sans-serif;font-size:2.4rem;font-weight:800;color:var(--navy);line-height:1;margin-bottom:6px}
.kpi-label{font-size:.8rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--text-muted)}
.kpi-sub{font-size:.78rem;color:var(--text-muted);margin-top:4px}

/* CHART & TOP CARDS */
.cc,.tc{
  position:relative;overflow:hidden;
  background:var(--bg-card) !important;
  border:1px solid var(--border);
  border-radius:20px;
  transition:.32s ease;
  box-shadow:0 2px 12px rgba(27,42,74,.07);
}
.cc:hover,.tc:hover{transform:translateY(-7px);border-color:var(--border-hv);box-shadow:0 18px 40px rgba(27,42,74,.12),0 0 0 3px rgba(243,229,171,.45)}
/* bande navy+or en haut */
.cc::before,.tc::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--navy),var(--gold-soft));border-radius:20px 20px 0 0;pointer-events:none}

.cc-head,.tc-head{display:flex;align-items:center;justify-content:space-between;padding:22px 22px 14px;border-bottom:1px solid var(--border);background:var(--bg-card)}
.tc-head{justify-content:flex-start}
.cc-title,.tc-title{display:flex;align-items:center;gap:9px;font-size:.85rem;font-weight:700;color:var(--navy);letter-spacing:.03em}

.cc-body{padding:20px;height:260px;position:relative;background:var(--bg-card)}
.cc-body canvas{max-height:100%}
.donut-wrap{display:flex;align-items:center;justify-content:center;position:relative}
.donut-center{position:absolute;text-align:center;pointer-events:none}
.donut-center strong{display:block;font-family:'Syne',sans-serif;font-size:1.9rem;font-weight:800;color:var(--navy)}
.donut-center small{font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted)}

.cc-dl{width:34px;height:34px;border:1px solid var(--border);cursor:pointer;border-radius:10px;background:var(--navy-light);color:var(--navy);font-size:.8rem;transition:.22s ease;display:flex;align-items:center;justify-content:center}
.cc-dl:hover{background:var(--navy);color:var(--gold-soft);transform:scale(1.08)}

/* RANKING LIST */
.rlist{display:flex;flex-direction:column;gap:10px;padding:16px 18px;background:var(--bg-card)}
.ri{display:flex;align-items:center;gap:12px;padding:11px 14px;background:var(--bg-card2);border:1px solid var(--border);border-radius:14px;transition:.22s ease}
.ri:hover{transform:translateX(6px);background:var(--navy-light);border-color:rgba(27,42,74,.25)}
.ri-rank{width:26px;height:26px;border-radius:8px;background:var(--navy);color:var(--gold-soft);font-size:.76rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.ri-info{flex:1;min-width:0}
.ri-name{font-size:.88rem;font-weight:700;color:var(--navy);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ri-sub{font-size:.73rem;color:var(--text-muted);margin-top:2px}
.ri-val{font-size:.82rem;font-weight:700;white-space:nowrap;flex-shrink:0}

/* BARS */
.sbar{height:5px;background:rgba(27,42,74,.10);border-radius:20px;margin-top:6px;overflow:hidden}
.sbar-fill{height:100%;border-radius:20px;background:linear-gradient(90deg,var(--navy),#F3E5AB) !important;transition:width .8s cubic-bezier(.4,0,.2,1)}
.pbar{height:7px;background:rgba(27,42,74,.08);border-radius:20px;overflow:hidden}
.pbar-fill{height:100%;border-radius:20px;background:linear-gradient(90deg,var(--navy),#4d9de0,#F3E5AB);transition:width .9s cubic-bezier(.4,0,.2,1)}

/* BADGES */
.ib{display:inline-flex;align-items:center;gap:5px;font-size:.75rem;font-weight:700;padding:4px 10px;border-radius:100px}
.ib-s1{background:var(--gold-bg);color:var(--gold);border:1px solid rgba(184,148,10,.25)}
.ib-s2{background:#fef3c7;color:#92400e;border:1px solid rgba(146,64,14,.2)}
.ib-s3{background:#d1fae5;color:#065f46;border:1px solid rgba(6,95,70,.2)}
.ib-s4{background:var(--navy-light);color:var(--navy-mid);border:1px solid var(--border)}

/* NO DATA */
.nodata{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;height:100%;color:var(--text-muted);font-size:.85rem}
.nodata i{font-size:1.6rem;opacity:.35}

/* SUMMARY */
.dashboard-summary{margin-top:60px;display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:22px}
.summary-card{position:relative;overflow:hidden;padding:30px 26px;border-radius:20px;text-align:center;background:var(--navy);border:1px solid rgba(243,229,171,.18);transition:.32s ease;box-shadow:0 4px 20px rgba(27,42,74,.20)}
.summary-card:hover{transform:translateY(-8px);box-shadow:0 20px 44px rgba(27,42,74,.28),0 0 0 3px rgba(243,229,171,.30)}
.summary-card::before{content:'';position:absolute;width:180px;height:180px;border-radius:50%;background:rgba(243,229,171,.05);top:-90px;right:-90px}
.summary-card i{font-size:2rem;margin-bottom:12px;color:var(--gold-soft)}
.summary-card h3{font-family:'Syne',sans-serif;font-size:2.8rem;font-weight:800;color:var(--gold-soft)}
.summary-card span{font-size:.8rem;color:rgba(243,229,171,.60);letter-spacing:.06em;text-transform:uppercase}

/* SCROLLBAR */
::-webkit-scrollbar{width:8px}
::-webkit-scrollbar-track{background:var(--bg-page)}
::-webkit-scrollbar-thumb{background:rgba(27,42,74,.25);border-radius:20px}
::-webkit-scrollbar-thumb:hover{background:var(--navy)}
</style>
</head>
<body>

<!-- HEADER -->
<div class="hdr">
  <h1>Statistiques <span>AI OUTILS</span></h1>
  <p>Analyse complète de votre bibliothèque de modèles d'intelligence artificielle</p>
</div>

<div class="wrap">

<!-- ═══════════════════════════════════════════════════════════
     CHARTS – DISTRIBUTION
═══════════════════════════════════════════════════════════ -->
<div class="sec" data-reveal>
  <div class="sec-title">
    <div class="dot" style="background:var(--ac2)"></div>
    <h2>Distribution & Répartition</h2>
  </div>
  <div class="g2 g3">

    <!-- Outils par Catégorie -->
    <div class="cc">
      <div class="cc-head">
        <div class="cc-title"><i class="fas fa-chart-pie" style="color:var(--gold)"></i> Outils par Catégorie</div>
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
        <div class="cc-title"><i class="fas fa-circle-half-stroke" style="color:var(--ac4)"></i> Plans Gratuits vs Payants</div>
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

    <!-- Score par Catégorie -->
    <div class="cc">
      <div class="cc-head">
        <div class="cc-title"><i class="fas fa-tachometer-alt" style="color:var(--ac3)"></i> Score Global / Catégorie</div>
        <button class="cc-dl" onclick="dlChart('perfCatChart')" title="Télécharger"><i class="fas fa-download"></i></button>
      </div>
      <div class="cc-body">
        <?php if(!empty($stats['perf_by_category'])): ?>
          <canvas id="perfCatChart"></canvas>
        <?php else: ?><div class="nodata"><i class="fas fa-inbox"></i>Aucune donnée performance</div><?php endif; ?>
      </div>
    </div>

    <!-- Rapidité par catégorie -->
    <div class="cc">
      <div class="cc-head">
        <div class="cc-title"><i class="fas fa-rocket" style="color:var(--ac2)"></i> Rapidité Moy. / Catégorie</div>
        <button class="cc-dl" onclick="dlChart('rapCatChart')" title="Télécharger"><i class="fas fa-download"></i></button>
      </div>
      <div class="cc-body">
        <?php if(!empty($stats['perf_by_category'])): ?>
          <canvas id="rapCatChart"></canvas>
        <?php else: ?><div class="nodata"><i class="fas fa-inbox"></i>Aucune donnée</div><?php endif; ?>
      </div>
    </div>

  

  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     CLASSEMENTS TOP 5
═══════════════════════════════════════════════════════════ -->
<div class="sec" data-reveal>
  <div class="sec-title">
    <div class="dot" style="background:var(--gold)"></div>
    <h2>Classements Top 5</h2>
  </div>
  <div class="g2" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr))">

    <!-- Top Score Global -->
    <div class="tc">
      <div class="tc-head"><div class="tc-title"><i class="fas fa-trophy" style="color:var(--gold)"></i> Meilleur Score Global</div></div>
      <?php if(!empty($stats['top_score'])): ?>
      <div class="rlist">
        <?php foreach($stats['top_score'] as $i => $m): ?>
        <div class="ri">
          <div class="ri-rank"><?php echo $i+1; ?></div>
          <?php echo model_img($m['logo']??'', $m['nom']??'AI', '38px'); ?>
          <div class="ri-info">
            <div class="ri-name"><?php echo htmlspecialchars($m['nom']??'N/A'); ?></div>
            <div class="ri-sub"><?php echo htmlspecialchars($m['categorie']??'—'); ?></div>
            <?php echo score_bar((float)($m['score_global']??0), 10); ?>
          </div>
          <div class="ri-val" style="color:var(--gold)"><?php echo number_format($m['score_global']??0,1); ?>/10</div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?><div class="nodata" style="padding:30px"><i class="fas fa-trophy"></i>Aucune donnée performance</div><?php endif; ?>
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
            <?php echo score_bar((float)($m['rapidite']??0), 10); ?>
          </div>
          <div class="ri-val" style="color:var(--ac2)"><?php echo number_format($m['rapidite']??0,1); ?>/10</div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?><div class="nodata" style="padding:30px"><i class="fas fa-rocket"></i>Aucune donnée</div><?php endif; ?>
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
            <?php echo score_bar((float)($m['qualite']??0), 10); ?>
          </div>
          <div class="ri-val" style="color:var(--ac5)"><?php echo number_format($m['qualite']??0,1); ?>/10</div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?><div class="nodata" style="padding:30px"><i class="fas fa-star"></i>Aucune donnée</div><?php endif; ?>
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
      <?php else: ?><div class="nodata" style="padding:30px"><i class="fas fa-comment"></i>Aucun avis</div><?php endif; ?>
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
  userL:        <?php echo $json['user_labels']; ?>,
  userC:        <?php echo $json['user_counts']; ?>,
  free:         <?php echo (int)$stats['pricing']['free']; ?>,
  paid:         <?php echo (int)$stats['pricing']['paid']; ?>,
};

// Palette navy-or pour thème clair
const PAL = ['#1B2A4A','#263d6b','#4d78b8','#b8940a','#e8a44a','#0d7a55','#7c3aed','#c97fd4'];

Chart.defaults.color       = '#6b7a94';
Chart.defaults.borderColor = 'rgba(27,42,74,.08)';
Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.font.size   = 12;

const defaults = (extra={}) => Object.assign({
  responsive:true, maintainAspectRatio:false,
  plugins:{ legend:{ position:'bottom', labels:{ padding:14, usePointStyle:true, pointStyle:'circle', boxWidth:8, color:'#a0b0c8' } } }
}, extra);

function dlChart(id){ const c=document.getElementById(id); if(!c)return; const a=document.createElement('a'); a.download=id+'.png'; a.href=c.toDataURL('image/png',1); a.click(); }
function mkPie(id, labels, data, opts={}){
  const el=document.getElementById(id); if(!el||!labels.length) return;
  return new Chart(el,{type:'doughnut', data:{labels, datasets:[{data, backgroundColor:PAL, borderColor:'#fffdf5', borderWidth:3, hoverBorderWidth:0, hoverOffset:6}]}, options:defaults(opts)});
}

document.addEventListener('DOMContentLoaded',()=>{

  // 1. Outils par Catégorie
  if(D.cats.length) mkPie('catChart', D.cats, D.catC);

  // 2. Gratuits vs Payants
  if(D.free+D.paid>0) new Chart(document.getElementById('priceDoughnut'),{type:'doughnut',
    data:{labels:['Gratuits','Payants'],datasets:[{data:[D.free,D.paid],backgroundColor:['#0d7a55','#b8940a'],borderColor:'#fffdf5',borderWidth:3,hoverOffset:6}]},
    options:defaults({cutout:'68%'})
  });

  // 3. Score Global par Catégorie
  if(D.perfCatL.length) new Chart(document.getElementById('perfCatChart'),{type:'bar',
    data:{labels:D.perfCatL, datasets:[{label:'Score Global',data:D.perfCatS,
      backgroundColor: D.perfCatS.map((_,i) => PAL[i % PAL.length]),
      borderRadius:8, borderWidth:0}]},
    options:defaults({plugins:{legend:{display:false}}, scales:{
      y:{min:0,max:10,grid:{color:'rgba(27,42,74,.06)'}, ticks:{color:'#6b7a94'}},
      x:{grid:{display:false}, ticks:{color:'#6b7a94'}}
    }})
  });

  // 4. Rapidité Moy. par catégorie
  if(D.perfCatL.length) new Chart(document.getElementById('rapCatChart'),{type:'line',
    data:{labels:D.perfCatL, datasets:[{data:D.perfCatR,
      borderColor:'#1B2A4A',
      backgroundColor:'rgba(27,42,74,.07)',
      pointBackgroundColor:'#F3E5AB',
      pointBorderColor:'#1B2A4A',
      pointBorderWidth:2, pointRadius:5,
      tension:.4, fill:true, borderWidth:2.5}]},
    options:defaults({plugins:{legend:{display:false}},
      scales:{
        y:{min:0,max:10, title:{display:true,text:'Rapidité /10',color:'#6b7a94'}, grid:{color:'rgba(27,42,74,.06)'}, ticks:{color:'#6b7a94'}},
        x:{grid:{display:false}, ticks:{color:'#6b7a94'}}
      }
    })
  });

  // 6. Contributions utilisateurs
  if(D.userL.length) new Chart(document.getElementById('userChart'),{type:'bar',
    data:{labels:D.userL, datasets:[{data:D.userC,
      backgroundColor:'rgba(27,42,74,.15)',
      borderColor:'#1B2A4A',
      borderWidth:1.5,
      borderRadius:8}]},
    options:defaults({indexAxis:'y', plugins:{legend:{display:false}},
      scales:{
        x:{beginAtZero:true,ticks:{stepSize:1,color:'#6b7a94'}, grid:{color:'rgba(27,42,74,.06)'}},
        y:{grid:{display:false}, ticks:{color:'#6b7a94'}}
      }
    })
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