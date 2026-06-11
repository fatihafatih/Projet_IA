<?php
session_start();
require_once '../includes/connexionbd.php';

// ── Stats dynamiques ────────────────────────────────────────────────────
$stats = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM outils_ia  WHERE status='actif')         AS nb_outils,
        (SELECT COUNT(*) FROM users      WHERE role='adherent')         AS nb_users,
        (SELECT COUNT(*) FROM reviews)                                  AS nb_reviews,
        (SELECT COUNT(*) FROM categorie)                                AS nb_cats,
        (SELECT COUNT(*) FROM providers)                                AS nb_providers,
        (SELECT ROUND(AVG(global_rating),1) FROM outils_ia WHERE status='actif') AS avg_rating
")->fetch(PDO::FETCH_ASSOC);

// ── Top outils par rating ───────────────────────────────────────────────
$top_tools = $pdo->query("
    SELECT o.nom, o.logo_url, o.global_rating, c.name AS cat
    FROM outils_ia o
    LEFT JOIN categorie c ON c.ID_CATEGORIE = o.ID_CATEGORIE
    WHERE o.status = 'actif'
    ORDER BY o.global_rating DESC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

// ── Catégories ──────────────────────────────────────────────────────────
$categories = $pdo->query("
    SELECT c.name, COUNT(o.ID_OUTILS_IA) AS nb
    FROM categorie c
    LEFT JOIN outils_ia o ON o.ID_CATEGORIE = c.ID_CATEGORIE AND o.status = 'actif'
    GROUP BY c.ID_CATEGORIE
    ORDER BY nb DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>À propos — SearchIA</title>
  <link rel="stylesheet" href="../styles/dashboard.css">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    :root{--navy:#1B2A4A;--gold:#F3E5AB;--coral:#E11D48;--slate:#64748B;--bg:#F8FAFC;}
    body{background:var(--bg);font-family:'Plus Jakarta Sans',sans-serif;color:var(--navy);}

    /* ── Hero ── */
    .about-hero{
      position:relative;overflow:hidden;
      background:linear-gradient(160deg,#0a1628 0%,#1B2A4A 60%,#2d1836 100%);
      padding:90px 2rem 80px;text-align:center;
    }
    .about-hero::after{
      content:'';position:absolute;bottom:-1px;left:0;right:0;height:60px;
      background:var(--bg);clip-path:ellipse(55% 100% at 50% 100%);
    }
    .about-eyebrow{
      display:inline-flex;align-items:center;gap:8px;
      font-size:.75rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;
      color:var(--gold);background:rgba(243,229,171,.1);
      border:1px solid rgba(243,229,171,.25);
      padding:6px 18px;border-radius:99px;margin-bottom:24px;
    }
    .about-hero h1{
      font-size:clamp(2.2rem,5vw,3.4rem);font-weight:900;color:#F8FAFC;
      line-height:1.12;margin-bottom:18px;
    }
    .about-hero h1 span{
      background:linear-gradient(135deg,var(--gold),var(--coral));
      -webkit-background-clip:text;-webkit-text-fill-color:transparent;
    }
    .about-hero p{font-size:1.08rem;color:#94A3B8;max-width:580px;margin:0 auto;}

    /* ── Section générique ── */
    .section{max-width:1100px;margin:0 auto;padding:70px 2rem;}
    .section-title{
      font-size:1.7rem;font-weight:800;margin-bottom:12px;
      position:relative;display:inline-block;
    }
    .section-title::after{
      content:'';display:block;height:3px;width:50px;
      background:linear-gradient(90deg,var(--gold),var(--coral));
      border-radius:2px;margin-top:8px;
    }
    .section-sub{color:var(--slate);font-size:.95rem;margin-bottom:44px;max-width:600px;}

    /* ── Mission cards ── */
    .mission-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;}
    .mission-card{
      background:#fff;border:1.5px solid #E2E8F0;border-radius:20px;
      padding:32px 28px;transition:transform .25s,box-shadow .25s;
    }
    .mission-card:hover{transform:translateY(-5px);box-shadow:0 16px 40px rgba(27,42,74,.1);}
    .mission-icon{
      font-size:2rem;width:58px;height:58px;
      background:linear-gradient(135deg,rgba(243,229,171,.3),rgba(225,29,72,.1));
      border-radius:16px;display:flex;align-items:center;justify-content:center;
      margin-bottom:18px;
    }
    .mission-card h3{font-size:1.05rem;font-weight:800;margin-bottom:8px;}
    .mission-card p{font-size:.88rem;color:var(--slate);line-height:1.7;}

    /* ── Stats ── */
    .stats-section{
      background:linear-gradient(135deg,var(--navy),#2d1836);
      padding:64px 2rem;
    }
    .stats-inner{max-width:1000px;margin:0 auto;}
    .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0;}
    .stat-box{
      text-align:center;padding:28px 20px;
      border-right:1px solid rgba(255,255,255,.08);
    }
    .stat-box:last-child{border-right:none;}
    .stat-num{
      font-size:2.6rem;font-weight:900;
      background:linear-gradient(135deg,var(--gold),var(--coral));
      -webkit-background-clip:text;-webkit-text-fill-color:transparent;
    }
    .stat-lbl{font-size:.78rem;font-weight:600;color:#94A3B8;letter-spacing:.08em;text-transform:uppercase;margin-top:6px;}

    /* ── Top outils ── */
    .top-tools{display:flex;gap:20px;flex-wrap:wrap;margin-top:8px;}
    .top-tool-card{
      display:flex;align-items:center;gap:14px;
      background:#fff;border:1.5px solid #E2E8F0;border-radius:14px;
      padding:16px 20px;flex:1;min-width:200px;
      transition:transform .2s,box-shadow .2s;
    }
    .top-tool-card:hover{transform:translateY(-3px);box-shadow:0 10px 30px rgba(27,42,74,.1);}
    .tool-logo-sm{width:44px;height:44px;border-radius:12px;object-fit:cover;background:#f1f5f9;}
    .tool-logo-placeholder{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,var(--gold),var(--coral));display:flex;align-items:center;justify-content:center;color:var(--navy);font-weight:900;font-size:.85rem;}
    .tool-info-sm .name{font-size:.9rem;font-weight:700;}
    .tool-info-sm .cat{font-size:.75rem;color:var(--slate);}
    .tool-rating{margin-left:auto;font-size:.85rem;font-weight:800;color:var(--coral);}

    /* ── Catégories pills ── */
    .cat-pills{display:flex;flex-wrap:wrap;gap:10px;margin-top:8px;}
    .cat-pill{
      display:inline-flex;align-items:center;gap:8px;
      background:#fff;border:1.5px solid #E2E8F0;
      padding:8px 18px;border-radius:99px;
      font-size:.82rem;font-weight:600;color:var(--navy);
      transition:border-color .2s,background .2s;
    }
    .cat-pill:hover{border-color:var(--coral);background:rgba(225,29,72,.04);}
    .cat-pill .nb{
      font-size:.72rem;font-weight:800;
      background:rgba(225,29,72,.1);color:var(--coral);
      padding:2px 8px;border-radius:99px;
    }

    /* ── Valeurs ── */
    .values-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;}
    .value-item{
      display:flex;gap:14px;align-items:flex-start;
      background:#fff;border:1.5px solid #E2E8F0;border-radius:16px;padding:24px;
    }
    .value-dot{
      width:12px;height:12px;border-radius:50%;margin-top:4px;flex-shrink:0;
      background:linear-gradient(135deg,var(--gold),var(--coral));
    }
    .value-item h4{font-size:.9rem;font-weight:800;margin-bottom:5px;}
    .value-item p{font-size:.82rem;color:var(--slate);line-height:1.65;}

    /* ── CTA ── */
    .about-cta{
      text-align:center;padding:72px 2rem;
      background:linear-gradient(160deg,#0a1628,#1B2A4A);
    }
    .about-cta h2{font-size:2rem;font-weight:900;color:#F8FAFC;margin-bottom:12px;}
    .about-cta p{color:#94A3B8;margin-bottom:30px;}
    .btn-gold{
      display:inline-block;padding:15px 44px;
      background:linear-gradient(135deg,var(--gold),#FBBF24,var(--coral));
      background-size:200%;color:var(--navy);font-weight:800;font-size:1rem;
      border-radius:99px;text-decoration:none;
      box-shadow:0 8px 28px rgba(225,29,72,.28);
      transition:all .3s;
    }
    .btn-gold:hover{background-position:right center;transform:translateY(-3px);}

    @media(max-width:640px){
      .stat-box{border-right:none;border-bottom:1px solid rgba(255,255,255,.08);}
      .stat-box:last-child{border-bottom:none;}
      .top-tools{flex-direction:column;}
    }
  </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<!-- ═══ HERO ═══════════════════════════════════════════════════ -->
<section class="about-hero">
  <h1>Le référentiel IA <span>le plus complet</span><br>du Maroc & du monde francophone</h1>
  <p>SearchIA centralise, évalue et compare les outils d'intelligence artificielle pour que vous puissiez choisir les meilleurs en toute confiance.</p>
</section>

<!-- ═══ MISSION ═════════════════════════════════════════════════ -->
<section class="section">
  <div class="section-title">Pourquoi SearchIA ?</div>
  <p class="section-sub">Dans un univers IA en explosion, trouver le bon outil prend du temps. Nous l'avons fait pour vous.</p>

  <div class="mission-grid">
    <div class="mission-card">
      <div class="mission-icon">🔍</div>
      <h3>Recherche intelligente</h3>
      <p>Notre moteur de recherche avancé vous permet de filtrer par catégorie, note, prix et caractéristiques pour trouver exactement ce dont vous avez besoin.</p>
    </div>
    <div class="mission-card">
      <div class="mission-icon">⭐</div>
      <h3>Évaluations vérifiées</h3>
      <p>Chaque outil est évalué sur la rapidité, la qualité, la crédibilité et la performance globale par notre communauté d'utilisateurs réels.</p>
    </div>

    <div class="mission-card">
      <div class="mission-icon">❤️</div>
      <h3>Collections personnelles</h3>
      <p>Sauvegardez vos outils favoris, créez des collections thématiques et retrouvez rapidement vos ressources préférées.</p>
    </div>
  </div>
</section>

<!-- ═══ STATS ════════════════════════════════════════════════════ -->
<div class="stats-section">
  <div class="stats-inner">
    <div class="stats-grid">
      <div class="stat-box">
        <div class="stat-num"><?= $stats['nb_outils'] ?>+</div>
        <div class="stat-lbl">Outils référencés</div>
      </div>
      <div class="stat-box">
        <div class="stat-num"><?= $stats['nb_users'] ?>+</div>
        <div class="stat-lbl">Utilisateurs actifs</div>
      </div>
      <div class="stat-box">
        <div class="stat-num"><?= $stats['nb_reviews'] ?>+</div>
        <div class="stat-lbl">Avis publiés</div>
      </div>
      <div class="stat-box">
        <div class="stat-num"><?= $stats['nb_cats'] ?></div>
        <div class="stat-lbl">Catégories</div>
      </div>
      <div class="stat-box">
        <div class="stat-num"><?= $stats['avg_rating'] ?>/5</div>
        <div class="stat-lbl">Note moyenne</div>
      </div>
      <div class="stat-box">
        <div class="stat-num"><?= $stats['nb_providers'] ?></div>
        <div class="stat-lbl">Providers IA</div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ TOP OUTILS ═══════════════════════════════════════════════ -->
<section class="section">
  <div class="section-title">Les outils les mieux notés</div>
  <p class="section-sub">Sélectionnés par notre communauté sur la base des performances réelles.</p>

  <div class="top-tools">
    <?php foreach ($top_tools as $t): ?>
    <div class="top-tool-card">
    <?php if ($t['logo_url']): ?>

    <?php
    $logo = $t['logo_url'];

    if (filter_var($logo, FILTER_VALIDATE_URL)) {
        $src = $logo;
    } else {
        $src = "../public/" . $logo;
    }
    ?>

    <img src="<?= htmlspecialchars($src) ?>" class="tool-logo-sm" alt="">

<?php else: ?>

    <div class="tool-logo-placeholder">
        <?= strtoupper(substr($t['nom'],0,2)) ?>
    </div>

<?php endif; ?>

      <div class="tool-info-sm">
        <div class="name"><?= htmlspecialchars($t['nom']) ?></div>
        <div class="cat"><?= htmlspecialchars($t['cat'] ?? '') ?></div>
      </div>
      <div class="tool-rating">★ <?= number_format($t['global_rating'],1) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ═══ CATÉGORIES ═══════════════════════════════════════════════ -->
<section class="section" style="padding-top:0">
  <div class="section-title">Catégories couvertes</div>
  <p class="section-sub">De la génération de texte à la vidéo, nous couvrons tout l'écosystème IA.</p>

  <div class="cat-pills">
    <?php foreach ($categories as $c): ?>
    <a href="../php/dashboard.php?cat=<?= urlencode($c['name']) ?>" class="cat-pill">
      <?= htmlspecialchars($c['name']) ?>
      <span class="nb"><?= $c['nb'] ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</section>

<!-- ═══ VALEURS ══════════════════════════════════════════════════ -->
<section class="section" style="padding-top:0">
  <div class="section-title">Nos valeurs</div>
  <p class="section-sub">Ce qui guide nos décisions au quotidien.</p>
  <div class="values-grid">
    <?php
    $values = [
      ['Transparence','Toutes nos évaluations sont basées sur des données réelles et des avis d\'utilisateurs vérifiés.'],
      ['Indépendance','Nous ne recevons aucune rémunération des providers pour nos classements et évaluations.'],
      ['Accessibilité','Nous croyons que l\'IA doit être accessible à tous, quel que soit votre niveau technique.'],
      ['Communauté','Notre plateforme vit grâce à la contribution de notre communauté d\'utilisateurs passionnés.'],
    ];
    foreach ($values as $v): ?>
    <div class="value-item">
      <div class="value-dot"></div>
      <div>
        <h4><?= $v[0] ?></h4>
        <p><?= $v[1] ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>



<?php include "../includes/footer.php"; ?>
</body>
</html>