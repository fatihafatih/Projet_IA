<?php
session_start();
require_once '../includes/connexionbd.php';

// ── Récupérer les plans + leurs caractéristiques ─────────────────────────
$stmt = $pdo->query("
    SELECT
        t.ID_TARIF,
        t.nom_plan,
        t.prix,
        t.devise,
        t.periode,
        GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR '||') AS features
    FROM tarif t
    LEFT JOIN tarif_caracteristiques tc ON tc.ID_TARIF = t.ID_TARIF
    LEFT JOIN caracteristiques c        ON c.ID_CAR    = tc.ID_CAR
    GROUP BY t.ID_TARIF
    ORDER BY t.prix ASC
");
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Stats globales pour la section preuves sociales ──────────────────────
$stats = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM outils_ia  WHERE status = 'actif') AS nb_outils,
        (SELECT COUNT(*) FROM users      WHERE role   = 'adherent') AS nb_users,
        (SELECT COUNT(*) FROM reviews)                              AS nb_reviews,
        (SELECT COUNT(*) FROM categorie)                            AS nb_cats
")->fetch(PDO::FETCH_ASSOC);

// ── Plan mis en avant (Pro = ID 3) ───────────────────────────────────────
$featured_id = 3;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tarifs — SearchIA</title>
  <link rel="stylesheet" href="../styles/dashboard.css">
  <style>
    /* ═══════════════════════════════════════════
       PAGE PRICING — SearchIA
    ═══════════════════════════════════════════ */
    *{box-sizing:border-box;margin:0;padding:0}

    :root{
      --navy:#1B2A4A;
      --gold:#F3E5AB;
      --coral:#E11D48;
      --slate:#64748B;
      --bg:#F8FAFC;
      --card:#FFFFFF;
      --radius:20px;
    }

    body{background:var(--bg);font-family:'Plus Jakarta Sans',sans-serif;color:var(--navy);}

    /* ── Hero ── */
    .pricing-hero{
      text-align:center;
      padding:80px 2rem 60px;
      background:linear-gradient(160deg,#0f1c3a 0%,#1B2A4A 55%,#2d1836 100%);
      position:relative;
      overflow:hidden;
    }
    .pricing-hero::before{
      content:'';position:absolute;top:-60px;left:50%;transform:translateX(-50%);
      width:600px;height:200px;
      background:radial-gradient(ellipse,rgba(243,229,171,.18) 0%,transparent 70%);
      pointer-events:none;
    }
    .pricing-eyebrow{
      display:inline-flex;align-items:center;gap:8px;
      font-size:.75rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;
      color:var(--gold);background:rgba(243,229,171,.1);
      border:1px solid rgba(243,229,171,.25);
      padding:6px 16px;border-radius:99px;margin-bottom:24px;
    }
    .pricing-hero h1{
      font-size:clamp(2rem,5vw,3.2rem);font-weight:900;
      color:#F8FAFC;line-height:1.15;margin-bottom:16px;
    }
    .pricing-hero h1 span{
      background:linear-gradient(135deg,var(--gold),var(--coral));
      -webkit-background-clip:text;-webkit-text-fill-color:transparent;
    }
    .pricing-hero p{font-size:1.05rem;color:#94A3B8;max-width:560px;margin:0 auto 40px;}

    /* Toggle mensuel/annuel */
    .toggle-wrap{
      display:inline-flex;align-items:center;gap:14px;
      background:rgba(255,255,255,.07);
      border:1px solid rgba(255,255,255,.12);
      padding:6px 20px;border-radius:99px;
    }
    .toggle-wrap span{font-size:.85rem;font-weight:600;color:#94A3B8;}
    .toggle-wrap span.on{color:#F8FAFC;}
    .toggle-switch{
      position:relative;width:46px;height:26px;cursor:pointer;
    }
    .toggle-switch input{opacity:0;width:0;height:0;}
    .toggle-track{
      position:absolute;inset:0;
      background:rgba(255,255,255,.15);border-radius:99px;
      transition:background .3s;
    }
    .toggle-switch input:checked ~ .toggle-track{
      background:linear-gradient(135deg,var(--gold),var(--coral));
    }
    .toggle-knob{
      position:absolute;top:3px;left:3px;
      width:20px;height:20px;border-radius:50%;
      background:#fff;transition:transform .3s;
      box-shadow:0 2px 6px rgba(0,0,0,.25);
    }
    .toggle-switch input:checked ~ .toggle-track .toggle-knob{transform:translateX(20px);}
    .save-badge{
      font-size:.7rem;font-weight:800;letter-spacing:.05em;
      background:var(--coral);color:#fff;
      padding:2px 8px;border-radius:99px;margin-left:4px;
    }

    /* ── Grille des plans ── */
    .pricing-section{padding:60px 2rem 80px;max-width:1200px;margin:0 auto;}

    .plans-grid{
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
      gap:24px;
      align-items:start;
    }

    .plan-card{
      background:var(--card);
      border:1.5px solid #E2E8F0;
      border-radius:var(--radius);
      padding:32px 28px;
      position:relative;
      transition:transform .25s,box-shadow .25s,border-color .25s;
    }
    .plan-card:hover{
      transform:translateY(-6px);
      box-shadow:0 20px 50px rgba(27,42,74,.13);
    }
    .plan-card.featured{
      border-color:var(--coral);
      background:linear-gradient(160deg,#fff 60%,rgba(225,29,72,.03));
      box-shadow:0 12px 40px rgba(225,29,72,.15);
      transform:scale(1.03);
    }
    .plan-card.featured:hover{transform:scale(1.03) translateY(-6px);}

    .featured-ribbon{
      position:absolute;top:-14px;left:50%;transform:translateX(-50%);
      background:linear-gradient(135deg,var(--gold),var(--coral));
      color:var(--navy);font-size:.72rem;font-weight:800;letter-spacing:.07em;
      padding:5px 20px;border-radius:99px;white-space:nowrap;
      box-shadow:0 4px 14px rgba(225,29,72,.3);
    }

    .plan-name{font-size:.8rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--slate);margin-bottom:12px;}
    .plan-price{display:flex;align-items:baseline;gap:4px;margin-bottom:6px;}
    .plan-price .amount{font-size:2.6rem;font-weight:900;color:var(--navy);line-height:1;}
    .plan-price .currency{font-size:1.1rem;font-weight:700;color:var(--slate);align-self:flex-start;margin-top:8px;}
    .plan-price .period{font-size:.8rem;color:var(--slate);margin-left:2px;}
    .plan-desc{font-size:.82rem;color:var(--slate);margin-bottom:24px;min-height:36px;}

    .plan-divider{border:none;border-top:1px solid #E2E8F0;margin-bottom:20px;}

    .plan-features{list-style:none;display:flex;flex-direction:column;gap:10px;margin-bottom:28px;}
    .plan-features li{
      display:flex;align-items:center;gap:10px;
      font-size:.84rem;color:#334155;font-weight:500;
    }
    .plan-features li::before{
      content:'✓';
      width:20px;height:20px;border-radius:50%;
      background:rgba(34,197,94,.1);color:#16A34A;
      display:flex;align-items:center;justify-content:center;
      font-size:.75rem;font-weight:900;flex-shrink:0;
    }
    .plan-features li.locked{color:#94A3B8;}
    .plan-features li.locked::before{content:'—';background:rgba(148,163,184,.1);color:#94A3B8;}

    .btn-plan{
      display:block;width:100%;text-align:center;
      padding:13px;border-radius:12px;
      font-size:.88rem;font-weight:700;letter-spacing:.03em;
      text-decoration:none;cursor:pointer;border:none;
      transition:all .25s;
    }
    .btn-plan-outline{
      background:transparent;
      border:1.5px solid #CBD5E1;
      color:var(--navy);
    }
    .btn-plan-outline:hover{border-color:var(--navy);background:rgba(27,42,74,.05);}
    .btn-plan-primary{
      background:linear-gradient(135deg,var(--gold) 0%,#FBBF24 50%,var(--coral) 100%);
      background-size:200% auto;
      color:var(--navy);
      box-shadow:0 6px 20px rgba(225,29,72,.25);
    }
    .btn-plan-primary:hover{background-position:right center;box-shadow:0 8px 28px rgba(225,29,72,.4);transform:translateY(-2px);}

    /* ── Stats bande ── */
    .stats-band{
      background:linear-gradient(135deg,var(--navy),#2d1836);
      padding:48px 2rem;
      display:flex;justify-content:center;gap:0;
      flex-wrap:wrap;
    }
    .stat-item{
      text-align:center;padding:0 48px;
      border-right:1px solid rgba(255,255,255,.08);
    }
    .stat-item:last-child{border-right:none;}
    .stat-num{font-size:2.4rem;font-weight:900;background:linear-gradient(135deg,var(--gold),var(--coral));-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
    .stat-label{font-size:.8rem;font-weight:600;color:#94A3B8;letter-spacing:.08em;text-transform:uppercase;margin-top:4px;}

    /* ── FAQ ── */
    .faq-section{max-width:760px;margin:0 auto;padding:70px 2rem;}
    .faq-section h2{font-size:1.8rem;font-weight:800;text-align:center;margin-bottom:40px;}
    .faq-item{border-bottom:1px solid #E2E8F0;overflow:hidden;}
    .faq-q{
      width:100%;display:flex;justify-content:space-between;align-items:center;
      padding:20px 0;background:none;border:none;cursor:pointer;
      font-size:.95rem;font-weight:700;color:var(--navy);text-align:left;
      transition:color .2s;
    }
    .faq-q:hover{color:var(--coral);}
    .faq-icon{font-size:1.4rem;color:var(--coral);transition:transform .3s;flex-shrink:0;margin-left:16px;}
    .faq-a{font-size:.88rem;color:var(--slate);line-height:1.7;max-height:0;overflow:hidden;transition:max-height .35s ease,padding .3s;}
    .faq-item.open .faq-a{max-height:200px;padding-bottom:20px;}
    .faq-item.open .faq-icon{transform:rotate(45deg);}

    /* ── CTA final ── */
    .pricing-cta{
      text-align:center;padding:70px 2rem;
      background:linear-gradient(160deg,#0f1c3a,#1B2A4A);
    }
    .pricing-cta h2{font-size:2rem;font-weight:900;color:#F8FAFC;margin-bottom:12px;}
    .pricing-cta p{color:#94A3B8;margin-bottom:32px;}
    .btn-cta-hero{
      display:inline-block;padding:16px 48px;
      background:linear-gradient(135deg,var(--gold),#FBBF24,var(--coral));
      background-size:200% auto;
      color:var(--navy);font-weight:800;font-size:1rem;
      border-radius:99px;text-decoration:none;
      box-shadow:0 8px 30px rgba(225,29,72,.3);
      transition:all .3s;
    }
    .btn-cta-hero:hover{background-position:right center;transform:translateY(-3px);box-shadow:0 12px 40px rgba(225,29,72,.5);}

    @media(max-width:768px){
      .plans-grid{grid-template-columns:1fr;}
      .plan-card.featured{transform:scale(1);}
      .stat-item{padding:20px 24px;border-right:none;border-bottom:1px solid rgba(255,255,255,.08);}
      .stat-item:last-child{border-bottom:none;}
    }
  </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<!-- ═══ HERO ═══════════════════════════════════════════════════════════ -->
<section class="pricing-hero">
  <div class="pricing-eyebrow">💎 Tarifs transparents</div>
  <h1>Un plan pour <span>chaque besoin</span></h1>
  <p>Accédez aux meilleurs outils IA, comparez-les et boostez votre productivité — à partir de 0 €.</p>

  <div class="toggle-wrap" id="billingToggle">
    <span class="on" id="lblMonthly">Mensuel</span>
    <label class="toggle-switch">
      <input type="checkbox" id="annualToggle">
      <div class="toggle-track"><div class="toggle-knob"></div></div>
    </label>
    <span id="lblAnnual">Annuel <span class="save-badge">-10%</span></span>
  </div>
</section>

<!-- ═══ PLANS ═══════════════════════════════════════════════════════════ -->
<section class="pricing-section">
  <div class="plans-grid">
    <?php foreach ($plans as $plan):
      $features = $plan['features'] ? explode('||', $plan['features']) : [];
      $isFeatured = ($plan['ID_TARIF'] == $featured_id);
      $isAnnual   = ($plan['periode'] === 'annuel');
      $isLifetime = ($plan['periode'] === 'a_vie');
      $isFree     = ($plan['periode'] === 'gratuit');

      // Prix annuel réduit affiché en /mois
      $displayPrice = (float)$plan['prix'];
      $priceLabel   = '';
      if ($plan['periode'] === 'mensuel') $priceLabel = '/mois';
      elseif ($isAnnual)                  $priceLabel = '/an';
      elseif ($isLifetime)                $priceLabel = 'une fois';

      // Descriptions courtes selon le plan
      $descs = [
        'Free'        => 'Explorez la plateforme sans engagement.',
        'Starter'     => 'Idéal pour les débutants et curieux de l\'IA.',
        'Pro'         => 'Le choix des professionnels et freelances.',
        'Pro Annuel'  => 'Toutes les fonctionnalités Pro avec réduction annuelle.',
        'Team'        => 'Collaborez à plusieurs sur vos projets IA.',
        'Enterprise'  => 'Solution sur mesure pour les grandes équipes.',
        'Lifetime'    => 'Accès à vie, paiement unique. Profitez-en !',
      ];
      $desc = $descs[$plan['nom_plan']] ?? '';

      // 4 caractéristiques fictives "verrouillées" pour les plans de base
      $locked = [];
      if ($isFree) $locked = ['API Access', 'Collections illimitées', 'Support prioritaire'];
    ?>
    <div class="plan-card <?= $isFeatured ? 'featured' : '' ?>" data-annual="<?= $isAnnual ? '1':'0' ?>">
      <?php if ($isFeatured): ?>
        <div class="featured-ribbon">⭐ Recommandé</div>
      <?php endif; ?>

      <div class="plan-name"><?= htmlspecialchars($plan['nom_plan']) ?></div>

      <div class="plan-price">
        <?php if ($isFree): ?>
          <span class="amount">0</span>
          <span class="currency">$</span>
          <span class="period">toujours</span>
        <?php elseif ($isLifetime): ?>
          <span class="currency">$</span>
          <span class="amount"><?= number_format($displayPrice, 0) ?></span>
          <span class="period">à vie</span>
        <?php else: ?>
          <span class="currency">$</span>
          <span class="amount"><?= number_format($displayPrice, 2) ?></span>
          <span class="period"><?= $priceLabel ?></span>
        <?php endif; ?>
      </div>

      <p class="plan-desc"><?= $desc ?></p>
      <hr class="plan-divider">

      <ul class="plan-features">
        <?php foreach ($features as $f): ?>
          <li><?= htmlspecialchars($f) ?></li>
        <?php endforeach; ?>
        <?php foreach ($locked as $l): ?>
          <li class="locked"><?= htmlspecialchars($l) ?></li>
        <?php endforeach; ?>
      </ul>

      <?php if ($isFree): ?>
        <a href="../php/dashboard.php" class="btn-plan btn-plan-outline">Commencer gratuitement</a>
      <?php elseif ($isFeatured): ?>
        <a href="../php/register.php" class="btn-plan btn-plan-primary">Choisir <?= htmlspecialchars($plan['nom_plan']) ?></a>
      <?php else: ?>
        <a href="../php/register.php" class="btn-plan btn-plan-outline">Choisir ce plan</a>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ═══ STATS ═══════════════════════════════════════════════════════════ -->
<div class="stats-band">
  <div class="stat-item">
    <div class="stat-num"><?= $stats['nb_outils'] ?>+</div>
    <div class="stat-label">Outils IA référencés</div>
  </div>
  <div class="stat-item">
    <div class="stat-num"><?= $stats['nb_users'] ?>+</div>
    <div class="stat-label">Utilisateurs actifs</div>
  </div>
  <div class="stat-item">
    <div class="stat-num"><?= $stats['nb_reviews'] ?>+</div>
    <div class="stat-label">Avis vérifiés</div>
  </div>
  <div class="stat-item">
    <div class="stat-num"><?= $stats['nb_cats'] ?></div>
    <div class="stat-label">Catégories couvertes</div>
  </div>
</div>

<!-- ═══ FAQ ═════════════════════════════════════════════════════════════ -->
<section class="faq-section">
  <h2>Questions fréquentes</h2>

  <?php
  $faqs = [
    ['Puis-je changer de plan à tout moment ?',
     'Oui, vous pouvez passer à un plan supérieur ou inférieur à tout moment depuis votre tableau de bord. La différence est calculée au prorata.'],
    ['Le plan gratuit est-il vraiment illimité ?',
     'Le plan Free vous donne accès à la navigation, la recherche et la consultation des outils. Certaines fonctionnalités avancées (API, collections illimitées) sont réservées aux plans payants.'],
    ['Comment fonctionne le plan Lifetime ?',
     'Le plan Lifetime est un paiement unique qui vous donne un accès permanent à toutes les fonctionnalités Pro, sans abonnement récurrent.'],
    ['Y a-t-il un remboursement possible ?',
     'Oui, nous offrons une garantie satisfait ou remboursé de 14 jours sur tous les plans payants, sans condition.'],
    ['Le plan Team inclut combien de membres ?',
     'Le plan Team est conçu pour jusqu\'à 10 membres. Au-delà, notre plan Enterprise s\'adapte à vos besoins avec un tarif personnalisé.'],
  ];
  foreach ($faqs as $faq): ?>
  <div class="faq-item">
    <button class="faq-q" onclick="toggleFaq(this)">
      <?= $faq[0] ?>
      <span class="faq-icon">+</span>
    </button>
    <div class="faq-a"><?= $faq[1] ?></div>
  </div>
  <?php endforeach; ?>
</section>



<?php include "../includes/footer.php"; ?>

<script>
// FAQ accordion
function toggleFaq(btn) {
  const item = btn.closest('.faq-item');
  const isOpen = item.classList.contains('open');
  document.querySelectorAll('.faq-item.open').forEach(i => i.classList.remove('open'));
  if (!isOpen) item.classList.add('open');
}

// Toggle annuel/mensuel (visuel uniquement — à brancher sur ta logique de paiement)
document.getElementById('annualToggle').addEventListener('change', function() {
  const isAnnual = this.checked;
  document.getElementById('lblMonthly').classList.toggle('on', !isAnnual);
  document.getElementById('lblAnnual').classList.toggle('on',  isAnnual);
});
</script>
</body>
</html>