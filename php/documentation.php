<?php
session_start();
require_once '../includes/connexionbd.php';

// ── Données dynamiques ───────────────────────────────────────────────────
$categories = $pdo->query("
    SELECT c.ID_CATEGORIE, c.name, c.description,
           COUNT(o.ID_OUTILS_IA) AS nb_outils
    FROM categorie c
    LEFT JOIN outils_ia o ON o.ID_CATEGORIE = c.ID_CATEGORIE AND o.status = 'actif'
    GROUP BY c.ID_CATEGORIE
    ORDER BY nb_outils DESC
")->fetchAll(PDO::FETCH_ASSOC);

$caracteristiques = $pdo->query("
    SELECT * FROM caracteristiques WHERE statut = 'actif' ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

$providers = $pdo->query("
    SELECT p.*, ta.name AS type_name,
           COUNT(DISTINCT tm.ID_OUTILS_IA) AS nb_outils
    FROM providers p
    LEFT JOIN type_application ta ON ta.ID_TA = p.ID_TA
    LEFT JOIN models m  ON m.ID_PROVIDERS = p.ID_PROVIDERS
    LEFT JOIN tool_models tm ON tm.ID_MODEL = m.ID_MODEL
    GROUP BY p.ID_PROVIDERS
    ORDER BY nb_outils DESC
")->fetchAll(PDO::FETCH_ASSOC);

$type_apps = $pdo->query("
    SELECT * FROM type_application ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Documentation — SearchIA</title>
  <link rel="stylesheet" href="../styles/dashboard.css">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    :root{--navy:#1B2A4A;--gold:#F3E5AB;--coral:#E11D48;--slate:#64748B;--bg:#F8FAFC;}
    body{background:var(--bg);font-family:'Plus Jakarta Sans',sans-serif;color:var(--navy);}

    /* ── Hero ── */
    .docs-hero{
      background:linear-gradient(160deg,#0a1628 0%,#1B2A4A 60%,#2d1836 100%);
      padding:80px 2rem 70px;text-align:center;
    }
    .docs-eyebrow{
      display:inline-flex;align-items:center;gap:8px;
      font-size:.75rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;
      color:var(--gold);background:rgba(243,229,171,.1);
      border:1px solid rgba(243,229,171,.25);
      padding:6px 18px;border-radius:99px;margin-bottom:22px;
    }
    .docs-hero h1{font-size:clamp(2rem,5vw,3rem);font-weight:900;color:#F8FAFC;margin-bottom:12px;}
    .docs-hero h1 span{background:linear-gradient(135deg,var(--gold),var(--coral));-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
    .docs-hero p{color:#94A3B8;max-width:560px;margin:0 auto 36px;}

    /* Barre de recherche docs */
    .docs-search{
      display:flex;align-items:center;gap:0;
      max-width:500px;margin:0 auto;
      background:rgba(255,255,255,.07);
      border:1px solid rgba(255,255,255,.15);
      border-radius:99px;overflow:hidden;padding:4px;
    }
    .docs-search input{
      flex:1;background:none;border:none;outline:none;
      padding:10px 18px;font-size:.9rem;
      color:#F8FAFC;font-family:'Plus Jakarta Sans',sans-serif;
    }
    .docs-search input::placeholder{color:#64748B;}
    .docs-search button{
      padding:10px 24px;border:none;cursor:pointer;
      background:linear-gradient(135deg,var(--gold),var(--coral));
      color:var(--navy);font-weight:700;font-size:.85rem;
      border-radius:99px;font-family:'Plus Jakarta Sans',sans-serif;
      white-space:nowrap;transition:opacity .2s;
    }
    .docs-search button:hover{opacity:.9;}

    /* ── Layout ── */
    .docs-layout{
      max-width:1200px;margin:0 auto;
      display:grid;grid-template-columns:260px 1fr;gap:40px;
      padding:56px 2rem 80px;align-items:start;
    }

    /* ── Sidebar ── */
    .docs-sidebar{
      position:sticky;top:100px;
      background:#fff;border:1.5px solid #E2E8F0;
      border-radius:18px;padding:24px 20px;
    }
    .sidebar-title{
      font-size:.7rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;
      color:var(--slate);margin-bottom:12px;padding-left:4px;
    }
    .sidebar-menu{list-style:none;}
    .sidebar-menu li a{
      display:flex;align-items:center;gap:10px;
      padding:9px 12px;border-radius:10px;
      font-size:.85rem;font-weight:600;color:var(--slate);
      text-decoration:none;transition:all .18s;
    }
    .sidebar-menu li a:hover,.sidebar-menu li a.active{
      background:rgba(225,29,72,.06);color:var(--coral);
    }
    .sidebar-menu li a .icon{font-size:1rem;flex-shrink:0;}
    .sidebar-menu li a .badge{
      margin-left:auto;font-size:.68rem;font-weight:700;
      background:rgba(225,29,72,.1);color:var(--coral);
      padding:2px 8px;border-radius:99px;
    }
    .sidebar-sep{border:none;border-top:1px solid #E2E8F0;margin:12px 0;}

    /* ── Contenu docs ── */
    .docs-content{}
    .docs-section{margin-bottom:56px;scroll-margin-top:100px;}

    .section-header{
      display:flex;align-items:center;gap:14px;margin-bottom:24px;
    }
    .section-icon-big{
      width:52px;height:52px;border-radius:16px;flex-shrink:0;
      background:linear-gradient(135deg,rgba(243,229,171,.4),rgba(225,29,72,.15));
      display:flex;align-items:center;justify-content:center;font-size:1.4rem;
    }
    .section-header h2{font-size:1.4rem;font-weight:800;margin-bottom:4px;}
    .section-header p{font-size:.85rem;color:var(--slate);}

    /* ── Cards grille ── */
    .docs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;}
    .doc-card{
      background:#fff;border:1.5px solid #E2E8F0;border-radius:14px;
      padding:20px;transition:transform .2s,box-shadow .2s,border-color .2s;
    }
    .doc-card:hover{transform:translateY(-3px);box-shadow:0 10px 28px rgba(27,42,74,.09);border-color:rgba(225,29,72,.2);}
    .doc-card-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
    .doc-card h4{font-size:.92rem;font-weight:700;}
    .doc-card p{font-size:.8rem;color:var(--slate);line-height:1.6;}
    .doc-badge{
      font-size:.7rem;font-weight:700;padding:3px 10px;border-radius:99px;
      background:rgba(27,42,74,.06);color:var(--navy);white-space:nowrap;
    }

    /* ── Tableau des providers ── */
    .providers-table{width:100%;border-collapse:collapse;}
    .providers-table th{
      text-align:left;padding:11px 16px;
      font-size:.72rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;
      color:var(--slate);background:#F8FAFC;border-bottom:2px solid #E2E8F0;
    }
    .providers-table td{
      padding:13px 16px;border-bottom:1px solid #F1F5F9;
      font-size:.85rem;vertical-align:middle;
    }
    .providers-table tr:hover td{background:#FAFBFC;}
    .provider-logo-cell{display:flex;align-items:center;gap:10px;}
    .provider-avatar{
      width:34px;height:34px;border-radius:10px;
      background:linear-gradient(135deg,rgba(243,229,171,.3),rgba(225,29,72,.1));
      display:flex;align-items:center;justify-content:center;
      font-size:.7rem;font-weight:800;color:var(--navy);flex-shrink:0;
    }
    .provider-link{color:var(--coral);text-decoration:none;font-weight:600;font-size:.8rem;}
    .provider-link:hover{text-decoration:underline;}

    /* ── Types pills ── */
    .types-list{display:flex;flex-wrap:wrap;gap:12px;}
    .type-pill{
      display:flex;align-items:center;gap:10px;
      background:#fff;border:1.5px solid #E2E8F0;
      padding:12px 18px;border-radius:14px;
      transition:transform .2s,border-color .2s;
    }
    .type-pill:hover{transform:translateY(-2px);border-color:var(--coral);}
    .type-emoji{font-size:1.2rem;}
    .type-name{font-size:.88rem;font-weight:700;}
    .type-desc{font-size:.75rem;color:var(--slate);max-width:200px;}

    /* ── Guide rapide (steps) ── */
    .steps{display:flex;flex-direction:column;gap:16px;}
    .step{
      display:flex;gap:18px;align-items:flex-start;
      background:#fff;border:1.5px solid #E2E8F0;border-radius:14px;padding:20px;
    }
    .step-num{
      width:36px;height:36px;border-radius:50%;flex-shrink:0;
      background:linear-gradient(135deg,var(--gold),var(--coral));
      color:var(--navy);font-weight:900;font-size:.9rem;
      display:flex;align-items:center;justify-content:center;
      box-shadow:0 4px 12px rgba(225,29,72,.2);
    }
    .step h4{font-size:.9rem;font-weight:800;margin-bottom:5px;}
    .step p{font-size:.82rem;color:var(--slate);line-height:1.65;}
    .step code{
      background:rgba(27,42,74,.06);padding:2px 8px;border-radius:5px;
      font-size:.8rem;font-family:monospace;color:var(--coral);
    }

    /* ── Caractéristiques ── */
    .chars-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;}
    .char-item{
      display:flex;align-items:center;gap:10px;
      background:#fff;border:1.5px solid #E2E8F0;border-radius:12px;padding:14px 16px;
    }
    .char-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,var(--gold),var(--coral));}
    .char-name{font-size:.84rem;font-weight:700;}
    .char-desc{font-size:.75rem;color:var(--slate);}

    @media(max-width:900px){
      .docs-layout{grid-template-columns:1fr;}
      .docs-sidebar{position:static;margin-bottom:32px;}
    }
  </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<!-- ═══ HERO ═══════════════════════════════════════════════════ -->
<section class="docs-hero">
  <div class="docs-eyebrow">📚 Documentation</div>
  <h1>Tout ce que vous devez <span>savoir</span></h1>
  <p>Guide complet pour utiliser SearchIA : catégories, providers, fonctionnalités et bien plus.</p>

 
</section>

<!-- ═══ LAYOUT ══════════════════════════════════════════════════ -->
<div class="docs-layout">

  <!-- ── Sidebar ── -->
  <nav class="docs-sidebar">
    <div class="sidebar-title">Sections</div>
    <ul class="sidebar-menu">
      <li><a href="#guide" class="active"><span class="icon">🚀</span> Guide de démarrage</a></li>
      <li><a href="#categories"><span class="icon">🏷️</span> Catégories <span class="badge"><?= count($categories) ?></span></a></li>
      <li><a href="#caracteristiques"><span class="icon">⚙️</span> Caractéristiques</a></li>
      <li><a href="#providers"><span class="icon">🏢</span> Providers IA</a></li>
    </ul>
    <hr class="sidebar-sep">
    <div class="sidebar-title">Liens utiles</div>
    <ul class="sidebar-menu">
      <li><a href="../php/dashboard.php"><span class="icon">🔍</span> Explorer les outils</a></li>
      <li><a href="../php/pricing.php"><span class="icon">💎</span> Nos tarifs</a></li>
      <li><a href="../php/contact.php"><span class="icon">✉️</span> Nous contacter</a></li>
    </ul>
  </nav>

  <!-- ── Contenu ── -->
  <div class="docs-content">

    <!-- Guide de démarrage -->
    <section class="docs-section" id="guide">
      <div class="section-header">
        <div class="section-icon-big">🚀</div>
        <div>
          <h2>Guide de démarrage</h2>
          <p>Découvrez comment utiliser SearchIA en 4 étapes simples.</p>
        </div>
      </div>
      <div class="steps">
        <div class="step">
          <div class="step-num">1</div>
          <div>
            <h4>Créez votre compte</h4>
            <p>Inscrivez-vous gratuitement sur <a href="../php/register.php" style="color:var(--coral)">la page d'inscription</a>. Aucune carte de crédit requise pour le plan Free.</p>
          </div>
        </div>
        <div class="step">
          <div class="step-num">2</div>
          <div>
            <h4>Explorez les outils</h4>
            <p>Depuis le <a href="../php/dashboard.php" style="color:var(--coral)">tableau de bord</a>, utilisez la barre de recherche ou filtrez par catégorie pour trouver les outils qui vous correspondent.</p>
          </div>
        </div>
        <div class="step">
          <div class="step-num">3</div>
          <div>
            <h4>Consultez les fiches détaillées</h4>
            <p>Chaque outil dispose d'une fiche avec : description, modèles IA utilisés, avantages/inconvénients, performances et avis de la communauté. Cliquez sur <code>Voir →</code>.</p>
          </div>
        </div>
        <div class="step">
          <div class="step-num">4</div>
          <div>
            <h4>Sauvegardez vos favoris</h4>
            <p>Cliquez sur ❤️ pour ajouter un outil à vos favoris, puis organisez-les dans des <strong>collections personnalisées</strong> depuis votre profil.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Catégories -->
    <section class="docs-section" id="categories">
      <div class="section-header">
        <div class="section-icon-big">🏷️</div>
        <div>
          <h2>Catégories d'outils</h2>
          <p><?= count($categories) ?> catégories couvrent l'ensemble de l'écosystème IA.</p>
        </div>
      </div>
      <div class="docs-grid">
        <?php foreach ($categories as $c): ?>
        <div class="doc-card">
          <div class="doc-card-head">
            <h4><?= htmlspecialchars($c['name']) ?></h4>
            <span class="doc-badge"><?= $c['nb_outils'] ?> outil<?= $c['nb_outils'] > 1 ? 's' : '' ?></span>
          </div>
          <p><?= htmlspecialchars($c['description'] ?? 'Catégorie d\'outils IA spécialisés.') ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Caractéristiques -->
    <section class="docs-section" id="caracteristiques">
      <div class="section-header">
        <div class="section-icon-big">⚙️</div>
        <div>
          <h2>Caractéristiques des outils</h2>
          <p>Critères utilisés pour qualifier et filtrer les outils IA.</p>
        </div>
      </div>
      <div class="chars-grid">
        <?php foreach ($caracteristiques as $c): ?>
        <div class="char-item">
          <div class="char-dot"></div>
          <div>
            <div class="char-name"><?= htmlspecialchars($c['name']) ?></div>
            <div class="char-desc"><?= htmlspecialchars($c['description'] ?? '') ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Providers -->
    <section class="docs-section" id="providers">
      <div class="section-header">
        <div class="section-icon-big">🏢</div>
        <div>
          <h2>Providers IA référencés</h2>
          <p>Les entreprises dont les modèles et outils sont indexés sur SearchIA.</p>
        </div>
      </div>
      <div style="overflow-x:auto;border-radius:14px;border:1.5px solid #E2E8F0;">
        <table class="providers-table">
          <thead>
            <tr>
              <th>Provider</th>
              <th>Type</th>
              <th>Outils indexés</th>
              <th>Site officiel</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($providers as $p): ?>
            <tr>
              <td>
                <div class="provider-logo-cell">
                  <div class="provider-avatar"><?= strtoupper(substr($p['name'],0,2)) ?></div>
                  <strong><?= htmlspecialchars($p['name']) ?></strong>
                </div>
              </td>
              <td><span class="doc-badge"><?= htmlspecialchars($p['type_name'] ?? '—') ?></span></td>
              <td><?= $p['nb_outils'] ?> outil<?= $p['nb_outils'] > 1 ? 's' : '' ?></td>
              <td>
                <?php if ($p['website_url']): ?>
                  <a href="<?= htmlspecialchars($p['website_url']) ?>" target="_blank" class="provider-link">
                    Visiter ↗
                  </a>
                <?php else: ?>—<?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

  

  </div>
</div>

<?php include "../includes/footer.php"; ?>

<script>
// Scroll spy pour la sidebar
const sections = document.querySelectorAll('.docs-section');
const links    = document.querySelectorAll('.sidebar-menu a');

window.addEventListener('scroll', () => {
  let current = '';
  sections.forEach(s => {
    if (window.scrollY >= s.offsetTop - 130) current = s.id;
  });
  links.forEach(l => {
    l.classList.toggle('active', l.getAttribute('href') === '#' + current);
  });
});

// Filtrage simple
function filterDocs() {
  const q = document.getElementById('docsSearchInput').value.toLowerCase();
  document.querySelectorAll('.doc-card, .char-item, .type-pill').forEach(el => {
    el.style.display = el.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}
document.getElementById('docsSearchInput').addEventListener('keyup', e => {
  if (e.key === 'Enter') filterDocs();
});
</script>
</body>
</html>