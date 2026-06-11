<?php
session_start();
require_once '../includes/connexionbd.php';

// ── Les derniers outils ajoutés = articles "nouveautés" ─────────────────
$nouveautes = $pdo->query("
    SELECT o.ID_OUTILS_IA, o.nom, o.description, o.logo_url, o.global_rating,
           o.status, c.name AS categorie,
           u.nom AS auteur, u.image AS auteur_img
    FROM outils_ia o
    LEFT JOIN categorie c ON c.ID_CATEGORIE = o.ID_CATEGORIE
    LEFT JOIN users u     ON u.id = o.ID_USERS_CREATE
    WHERE o.status = 'actif'
    ORDER BY o.ID_OUTILS_IA DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

// ── Top reviews = "à la une" ─────────────────────────────────────────────
$top_reviews = $pdo->query("
    SELECT r.comment, r.rating, r.ID_REVIEW,
           u.nom AS user_nom, u.image AS user_img,
           o.nom AS outil_nom, o.logo_url AS outil_logo,
           c.name AS categorie
    FROM reviews r
    JOIN users u    ON u.id = r.ID_USERS
    JOIN outils_ia o ON o.ID_OUTILS_IA = r.ID_OUTILS_IA
    LEFT JOIN categorie c ON c.ID_CATEGORIE = o.ID_CATEGORIE
    WHERE r.rating >= 4.5
    ORDER BY r.rating DESC
    LIMIT 4
")->fetchAll(PDO::FETCH_ASSOC);

// ── Stats globales ───────────────────────────────────────────────────────
$total_outils = $pdo->query("SELECT COUNT(*) FROM outils_ia WHERE status='actif'")->fetchColumn();
$total_cats   = $pdo->query("SELECT COUNT(*) FROM categorie")->fetchColumn();

// ── Catégories pour les tags ─────────────────────────────────────────────
$tags = $pdo->query("
    SELECT c.name, COUNT(o.ID_OUTILS_IA) AS nb
    FROM categorie c
    LEFT JOIN outils_ia o ON o.ID_CATEGORIE = c.ID_CATEGORIE AND o.status='actif'
    GROUP BY c.ID_CATEGORIE HAVING nb > 0 ORDER BY nb DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Blog & Actualités IA — SearchIA</title>
  <link rel="stylesheet" href="../styles/dashboard.css">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    :root{--navy:#1B2A4A;--gold:#F3E5AB;--coral:#E11D48;--slate:#64748B;--bg:#F8FAFC;}
    body{background:var(--bg);font-family:'Plus Jakarta Sans',sans-serif;color:var(--navy);}

    /* ── Hero ── */
    .blog-hero{
      background:linear-gradient(160deg,#0a1628 0%,#1B2A4A 60%,#2d1836 100%);
      padding:80px 2rem 70px;text-align:center;position:relative;overflow:hidden;
    }
    .blog-hero::before{
      content:'';position:absolute;top:0;left:0;right:0;bottom:0;
      background:radial-gradient(ellipse 80% 60% at 50% 0%,rgba(243,229,171,.07) 0%,transparent 70%);
      pointer-events:none;
    }
    .blog-eyebrow{
      display:inline-flex;align-items:center;gap:8px;
      font-size:.75rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;
      color:var(--gold);background:rgba(243,229,171,.1);
      border:1px solid rgba(243,229,171,.25);
      padding:6px 18px;border-radius:99px;margin-bottom:22px;
    }
    .blog-hero h1{font-size:clamp(2rem,5vw,3rem);font-weight:900;color:#F8FAFC;margin-bottom:12px;}
    .blog-hero h1 span{background:linear-gradient(135deg,var(--gold),var(--coral));-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
    .blog-hero p{color:#94A3B8;max-width:540px;margin:0 auto 36px;}

    /* Tags hero */
    .hero-tags{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;}
    .hero-tag{
      font-size:.75rem;font-weight:600;
      padding:5px 14px;border-radius:99px;cursor:pointer;
      border:1px solid rgba(255,255,255,.12);color:rgba(248,250,252,.7);
      background:rgba(255,255,255,.05);
      transition:all .2s;
    }
    .hero-tag:hover,.hero-tag.active{
      background:rgba(243,229,171,.15);border-color:var(--gold);color:var(--gold);
    }

    /* ── Layout principal ── */
    .blog-layout{
      max-width:1200px;margin:0 auto;padding:56px 2rem 80px;
      display:grid;grid-template-columns:1fr 320px;gap:48px;align-items:start;
    }

    /* ── À la une (featured) ── */
    .featured-label{
      font-size:.72rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;
      color:var(--coral);margin-bottom:16px;display:flex;align-items:center;gap:8px;
    }
    .featured-label::before{content:'';display:inline-block;width:24px;height:2px;background:var(--coral);}

    .featured-card{
      background:#fff;border:1.5px solid #E2E8F0;border-radius:22px;
      overflow:hidden;margin-bottom:48px;
      display:grid;grid-template-columns:1fr 1fr;
      transition:box-shadow .25s;
    }
    .featured-card:hover{box-shadow:0 16px 48px rgba(27,42,74,.12);}
    .featured-visual{
      background:linear-gradient(135deg,#0f1c3a,#1B2A4A,#2d1836);
      display:flex;align-items:center;justify-content:center;
      min-height:280px;position:relative;overflow:hidden;
    }
    .featured-visual::before{
      content:'';position:absolute;inset:0;
      background:radial-gradient(ellipse 80% 80% at 50% 50%,rgba(243,229,171,.12),transparent);
    }
    .featured-logo-big{
      width:90px;height:90px;border-radius:24px;
      background:rgba(255,255,255,.1);
      display:flex;align-items:center;justify-content:center;
      font-size:2rem;font-weight:900;color:#F8FAFC;
      box-shadow:0 8px 32px rgba(0,0,0,.3);z-index:1;
    }
    .featured-logo-big img{width:90px;height:90px;border-radius:24px;object-fit:cover;}
    .featured-body{padding:32px;}
    .featured-cat{
      display:inline-block;font-size:.7rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;
      background:rgba(225,29,72,.08);color:var(--coral);
      padding:3px 12px;border-radius:99px;margin-bottom:12px;
    }
    .featured-body h2{font-size:1.3rem;font-weight:800;margin-bottom:10px;line-height:1.3;}
    .featured-body p{font-size:.85rem;color:var(--slate);line-height:1.7;margin-bottom:20px;}
    .featured-meta{display:flex;align-items:center;gap:12px;margin-bottom:20px;}
    .author-avatar{
      width:32px;height:32px;border-radius:50%;
      background:linear-gradient(135deg,var(--gold),var(--coral));
      display:flex;align-items:center;justify-content:center;
      font-size:.75rem;font-weight:800;color:var(--navy);flex-shrink:0;
    }
    .author-name{font-size:.8rem;font-weight:600;}
    .rating-star{font-size:.8rem;color:var(--coral);font-weight:700;}
    .btn-read{
      display:inline-flex;align-items:center;gap:6px;
      padding:10px 22px;border-radius:99px;
      background:linear-gradient(135deg,var(--gold),var(--coral));
      color:var(--navy);font-size:.82rem;font-weight:700;
      text-decoration:none;transition:all .25s;
      box-shadow:0 4px 14px rgba(225,29,72,.2);
    }
    .btn-read:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(225,29,72,.35);}

    /* ── Grille articles ── */
    .articles-title{
      font-size:1rem;font-weight:800;margin-bottom:20px;
      display:flex;align-items:center;justify-content:space-between;
    }
    .articles-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
    .article-card{
      background:#fff;border:1.5px solid #E2E8F0;border-radius:18px;
      overflow:hidden;transition:transform .2s,box-shadow .2s;
    }
    .article-card:hover{transform:translateY(-4px);box-shadow:0 12px 36px rgba(27,42,74,.1);}
    .article-thumb{
      height:120px;
      background:linear-gradient(135deg,#0f1c3a,#1B2A4A);
      display:flex;align-items:center;justify-content:center;
      position:relative;overflow:hidden;
    }
    .article-thumb::after{
      content:'';position:absolute;bottom:-1px;left:0;right:0;height:24px;
      background:#fff;clip-path:ellipse(55% 100% at 50% 100%);
    }
    .article-thumb-logo{
      width:56px;height:56px;border-radius:16px;
      background:rgba(255,255,255,.1);
      display:flex;align-items:center;justify-content:center;
      font-weight:900;color:#F8FAFC;font-size:1rem;
    }
    .article-thumb-logo img{width:56px;height:56px;border-radius:16px;object-fit:cover;}
    .article-body{padding:16px 18px 18px;}
    .article-cat{
      font-size:.68rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;
      color:var(--coral);margin-bottom:7px;
    }
    .article-body h3{font-size:.9rem;font-weight:800;line-height:1.35;margin-bottom:7px;}
    .article-body p{font-size:.78rem;color:var(--slate);line-height:1.6;
      display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
    .article-foot{
      display:flex;align-items:center;justify-content:space-between;
      padding:10px 18px;border-top:1px solid #F1F5F9;
    }
    .art-rating{font-size:.78rem;font-weight:700;color:var(--coral);}
    .art-link{font-size:.75rem;font-weight:700;color:var(--navy);text-decoration:none;
      padding:5px 12px;border:1px solid #E2E8F0;border-radius:99px;transition:all .15s;}
    .art-link:hover{border-color:var(--coral);color:var(--coral);}

    /* ── Sidebar ── */
    .blog-sidebar{display:flex;flex-direction:column;gap:24px;}

    .sidebar-widget{
      background:#fff;border:1.5px solid #E2E8F0;border-radius:18px;padding:22px;
    }
    .widget-title{
      font-size:.72rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;
      color:var(--slate);margin-bottom:16px;
      display:flex;align-items:center;gap:8px;
    }
    .widget-title::before{content:'';display:inline-block;width:18px;height:2px;background:linear-gradient(90deg,var(--gold),var(--coral));}

    /* Newsletter */
    .newsletter-text{font-size:.83rem;color:var(--slate);margin-bottom:14px;line-height:1.6;}
    .newsletter-form{display:flex;flex-direction:column;gap:10px;}
    .newsletter-form input{
      padding:11px 14px;border:1.5px solid #E2E8F0;border-radius:10px;
      font-size:.85rem;font-family:'Plus Jakarta Sans',sans-serif;outline:none;
      transition:border-color .2s;
    }
    .newsletter-form input:focus{border-color:var(--coral);}
    .newsletter-form button{
      padding:11px;border:none;cursor:pointer;border-radius:10px;
      background:linear-gradient(135deg,var(--gold),var(--coral));
      color:var(--navy);font-weight:700;font-size:.85rem;
      font-family:'Plus Jakarta Sans',sans-serif;
      transition:opacity .2s;
    }
    .newsletter-form button:hover{opacity:.9;}

    /* Tags sidebar */
    .tag-cloud{display:flex;flex-wrap:wrap;gap:8px;}
    .tag-btn{
      font-size:.75rem;font-weight:600;padding:5px 12px;border-radius:99px;
      border:1.5px solid #E2E8F0;background:#fff;color:var(--navy);
      cursor:pointer;transition:all .15s;text-decoration:none;
    }
    .tag-btn:hover{border-color:var(--coral);color:var(--coral);background:rgba(225,29,72,.04);}

    /* Stats sidebar */
    .mini-stats{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
    .mini-stat{
      background:linear-gradient(135deg,rgba(27,42,74,.04),rgba(225,29,72,.03));
      border:1.5px solid #E2E8F0;border-radius:12px;
      text-align:center;padding:14px;
    }
    .mini-stat .n{font-size:1.5rem;font-weight:900;background:linear-gradient(135deg,var(--navy),var(--coral));-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
    .mini-stat .l{font-size:.7rem;font-weight:600;color:var(--slate);text-transform:uppercase;letter-spacing:.06em;margin-top:2px;}

    @media(max-width:1024px){
      .blog-layout{grid-template-columns:1fr;}
      .featured-card{grid-template-columns:1fr;}
      .featured-visual{min-height:180px;}
    }
    @media(max-width:600px){
      .articles-grid{grid-template-columns:1fr;}
    }
  </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<!-- ═══ HERO ═══════════════════════════════════════════════════ -->
<section class="blog-hero">
  <div class="blog-eyebrow">📰 Blog & Actualités</div>
  <h1>L'actualité de <span>l'IA</span> en un coup d'œil</h1>
  <p>Découvrez les derniers outils référencés, les meilleures critiques et les tendances de l'intelligence artificielle.</p>

  <div class="hero-tags">
    <span class="hero-tag active" onclick="filterTag(this,'')">Tous</span>
    <?php foreach (array_slice($tags,0,6) as $t): ?>
      <span class="hero-tag" onclick="filterTag(this,'<?= htmlspecialchars($t['name']) ?>')">
        <?= htmlspecialchars($t['name']) ?>
      </span>
    <?php endforeach; ?>
  </div>
</section>

<!-- ═══ LAYOUT ══════════════════════════════════════════════════ -->
<div class="blog-layout">

  <!-- ── Articles ── -->
  <main>
    <!-- À la une : meilleure review -->
    <?php if (!empty($top_reviews)): $r = $top_reviews[0]; ?>
    <div class="featured-label">À la une</div>
    <div class="featured-card">
      <div class="featured-visual">
        <div class="featured-logo-big">
          <?php if ($r['outil_logo']): ?>
<?php
$logo = $r['outil_logo'];

if (filter_var($logo, FILTER_VALIDATE_URL)) {
    $imgSrc = $logo;
} else {
    $imgSrc = "../public/" . $logo;
}
?>

<img src="<?= htmlspecialchars($imgSrc) ?>" alt="">          <?php else: ?>
            <?= strtoupper(substr($r['outil_nom'],0,2)) ?>
          <?php endif; ?>
        </div>
      </div>
      <div class="featured-body">
        <span class="featured-cat"><?= htmlspecialchars($r['categorie'] ?? '') ?></span>
        <h2>Avis communauté : <?= htmlspecialchars($r['outil_nom']) ?></h2>
        <p><?= htmlspecialchars($r['comment'] ?? '') ?></p>
        <div class="featured-meta">
          <div class="author-avatar"><?= strtoupper(substr($r['user_nom'],0,2)) ?></div>
          <span class="author-name"><?= htmlspecialchars($r['user_nom']) ?></span>
          <span class="rating-star">★ <?= number_format($r['rating'],1) ?>/5</span>
        </div>
        <a href="../php/outil.php?id=<?= $r['ID_REVIEW'] ?>" class="btn-read">
          Lire la fiche complète →
        </a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Grille des derniers outils -->
    <div class="articles-title">
      <span>🆕 Derniers outils référencés</span>
      <a href="../php/dashboard.php" style="font-size:.8rem;font-weight:600;color:var(--coral);text-decoration:none;">Voir tout →</a>
    </div>

    <div class="articles-grid" id="articlesGrid">
      <?php foreach ($nouveautes as $o): ?>
      <div class="article-card" data-cat="<?= htmlspecialchars($o['categorie'] ?? '') ?>">
        <div class="article-thumb">
          <div class="article-thumb-logo">
            <?php if ($o['logo_url']): ?>
<?php
$logo = $o['logo_url'];

if (filter_var($logo, FILTER_VALIDATE_URL)) {
    $imgSrc = $logo;
} else {
    $imgSrc = "../public/" . $logo;
}
?>

<img src="<?= htmlspecialchars($imgSrc) ?>" alt="">            <?php else: ?>
              <?= strtoupper(substr($o['nom'],0,2)) ?>
            <?php endif; ?>
          </div>
        </div>
        <div class="article-body">
          <div class="article-cat"><?= htmlspecialchars($o['categorie'] ?? 'IA') ?></div>
          <h3><?= htmlspecialchars($o['nom']) ?></h3>
          <p><?= htmlspecialchars($o['description'] ?? '') ?></p>
        </div>
        <div class="article-foot">
          <span class="art-rating">★ <?= number_format($o['global_rating'],1) ?></span>
          <a href="../php/outil.php?id=<?= $o['ID_OUTILS_IA'] ?>" class="art-link">Voir →</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </main>

  <!-- ── Sidebar ── -->
  <aside class="blog-sidebar">


    <!-- Stats -->
    <div class="sidebar-widget">
      <div class="widget-title">SearchIA en chiffres</div>
      <div class="mini-stats">
        <div class="mini-stat">
          <div class="n"><?= $total_outils ?></div>
          <div class="l">Outils</div>
        </div>
        <div class="mini-stat">
          <div class="n"><?= $total_cats ?></div>
          <div class="l">Catégories</div>
        </div>
        <div class="mini-stat">
          <div class="n"><?= count($top_reviews) ?>+</div>
          <div class="l">Top avis</div>
        </div>
        <div class="mini-stat">
          <div class="n">24h</div>
          <div class="l">Mise à jour</div>
        </div>
      </div>
    </div>

    <!-- Tags -->
    <div class="sidebar-widget">
      <div class="widget-title">Catégories</div>
      <div class="tag-cloud">
        <?php foreach ($tags as $t): ?>
          <a href="../php/dashboard.php?cat=<?= urlencode($t['name']) ?>" class="tag-btn">
            <?= htmlspecialchars($t['name']) ?> <small style="opacity:.6">(<?= $t['nb'] ?>)</small>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Top avis -->
    <?php if (count($top_reviews) > 1): ?>
    <div class="sidebar-widget">
      <div class="widget-title">Top avis communauté</div>
      <?php foreach (array_slice($top_reviews,1) as $r): ?>
      <div style="padding:10px 0;border-bottom:1px solid #F1F5F9;last-child:border-none">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;">
          <div class="author-avatar" style="width:28px;height:28px;font-size:.65rem;"><?= strtoupper(substr($r['user_nom'],0,2)) ?></div>
          <strong style="font-size:.82rem"><?= htmlspecialchars($r['user_nom']) ?></strong>
          <span class="art-rating" style="margin-left:auto">★ <?= number_format($r['rating'],1) ?></span>
        </div>
        <p style="font-size:.78rem;color:var(--slate);line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">
          <?= htmlspecialchars($r['comment']) ?>
        </p>
        <a href="../php/outil.php?id=<?= $r['ID_REVIEW'] ?>" style="font-size:.75rem;color:var(--coral);text-decoration:none;font-weight:600;">
          → <?= htmlspecialchars($r['outil_nom']) ?>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </aside>
</div>

<?php include "../includes/footer.php"; ?>

<script>
function filterTag(el, cat) {
  document.querySelectorAll('.hero-tag').forEach(t => t.classList.remove('active'));
  el.classList.add('active');

  document.querySelectorAll('#articlesGrid .article-card').forEach(card => {
    const match = !cat || card.dataset.cat === cat;
    card.style.display = match ? '' : 'none';
  });
}
</script>
</body>
</html>