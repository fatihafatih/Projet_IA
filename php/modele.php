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

$stmt = $pdo->prepare("
    SELECT car.ID_CAR, car.name, car.description
    FROM model_caracteristiques mc
    JOIN caracteristiques car ON mc.ID_CAR = car.ID_CAR
    WHERE mc.ID_MODEL = ?
    ORDER BY car.name
");
$stmt->execute([$id]);
$cars = $stmt->fetchAll();

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

$tags = array_filter(array_map('trim', explode(',', $model['tags'] ?? '')));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($model['name']) ?> — Référentiel IA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../styles/style.css">
  <style>
/* ═══════════════════════════════════════════════════════════════
   RÉFÉRENTIEL IA — PREMIUM NAVY & GOLD — modele.php
═══════════════════════════════════════════════════════════════ */
:root {
  --navy:      #1B2A4A;
  --navy-deep: #0f1a2e;
  --navy-hover:#243658;
  --navy-card: #223458;
  --butter:    #D4AA60;
  --butter-l:  #F5EDD8;
  --butter-b:  #E8C97A;
  --ivory:     #FAFAF7;
  --white:     #FFFFFF;
  --surface:   #F2F4F8;
  --muted:     #6B7A99;
  --border:    #DDE2EE;
  --text:      #1A2340;
  --r20: 20px; --r14: 14px; --r8: 8px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  background: var(--ivory);
  color: var(--text);
  font-family: 'Plus Jakarta Sans', sans-serif;
  -webkit-font-smoothing: antialiased;
}

/* ─────────────────────────────────────────────────────────────
   HERO — classes ria-* avec !important pour résister aux CSS externes
───────────────────────────────────────────────────────────── */
.ria-hero {
  background: #0f1a2e !important;
  background-image: linear-gradient(135deg, #0f1a2e 0%, #1B2A4A 60%, #1e3055 100%) !important;
  padding: 36px 52px 44px !important;
  border-bottom: 1px solid rgba(212,170,96,.15) !important;
  position: relative !important;
  overflow: hidden !important;
  width: 100% !important;
  box-sizing: border-box !important;
}
.ria-hero::before {
  content: '' !important;
  position: absolute !important;
  top: -100px !important; right: -100px !important;
  width: 500px !important; height: 500px !important;
  background: radial-gradient(circle, rgba(212,170,96,.08) 0%, transparent 65%) !important;
  pointer-events: none !important;
  z-index: 0 !important;
}
.ria-hero-inner {
  max-width: 1320px !important;
  margin: 0 auto !important;
  position: relative !important;
  z-index: 1 !important;
}

/* Fil d'ariane */
.ria-bc {
  display: flex !important;
  align-items: center !important;
  gap: 6px !important;
  margin-bottom: 28px !important;
  list-style: none !important;
  padding: 0 !important;
  flex-wrap: wrap !important;
  justify-content: flex-start !important;
}
.ria-bc a { font-size: 12px !important; font-weight: 600 !important; color: rgba(255,255,255,.38) !important; text-decoration: none !important; transition: color .18s !important; }
.ria-bc a:hover { color: #D4AA60 !important; }
.ria-bc-sep { font-size: 10px !important; color: rgba(255,255,255,.2) !important; }
.ria-bc-cur { font-size: 12px !important; font-weight: 700 !important; color: rgba(255,255,255,.72) !important; }

/* Grille hero : logo | centre | stats */
.ria-hero-grid {
  display: grid !important;
  grid-template-columns: 110px 1fr 250px !important;
  gap: 32px !important;
  align-items: start !important;
}

/* Logo provider */
.ria-logo {
  width: 110px !important; height: 110px !important;
  background: #1c2d48 !important;
  border: 1.5px solid rgba(212,170,96,.3) !important;
  border-radius: 22px !important;
  display: flex !important; align-items: center !important; justify-content: center !important;
  font-family: 'Plus Jakarta Sans', sans-serif !important;
  font-weight: 800 !important; font-size: 26px !important; color: #D4AA60 !important;
  overflow: hidden !important; flex-shrink: 0 !important;
  box-shadow: 0 0 0 6px rgba(212,170,96,.07), 0 20px 40px rgba(0,0,0,.35) !important;
}
.ria-logo img { width: 100% !important; height: 100% !important; object-fit: contain !important; display: block !important; padding: 10px !important; background: #fff !important; }

/* Centre */
.ria-center {
  display: flex !important; flex-direction: column !important;
  align-items: flex-start !important; text-align: left !important;
}

/* Pills */
.ria-pills { display: flex !important; align-items: center !important; gap: 7px !important; margin-bottom: 14px !important; flex-wrap: wrap !important; }
.ria-pill {
  display: inline-flex !important; align-items: center !important; gap: 5px !important;
  padding: 5px 13px !important; font-size: 11px !important; font-weight: 700 !important;
  border-radius: 99px !important; letter-spacing: .04em !important; line-height: 1 !important;
}
.ria-pill-cat  { background: rgba(255,255,255,.08) !important; border: 1px solid rgba(255,255,255,.13) !important; color: rgba(255,255,255,.85) !important; }
.ria-pill-prov { background: rgba(212,170,96,.14) !important; border: 1px solid rgba(212,170,96,.28) !important; color: #E8C97A !important; }
.ria-pill-live { background: rgba(15,122,78,.2) !important; border: 1px solid rgba(75,212,154,.3) !important; color: #4BD49A !important; }
.ria-pill-live-dot {
  width: 6px !important; height: 6px !important; background: #4BD49A !important;
  border-radius: 50% !important; display: inline-block !important;
  animation: ria-blink 2s infinite !important;
}
@keyframes ria-blink { 0%,100%{opacity:1} 50%{opacity:.25} }

/* Tags dans le hero */
.ria-tags { display: flex !important; flex-wrap: wrap !important; gap: 7px !important; margin-bottom: 22px !important; }
.ria-tag {
  background: rgba(255,255,255,.06) !important;
  border: 1px solid rgba(255,255,255,.1) !important;
  color: rgba(255,255,255,.65) !important;
  font-size: 11px !important; font-weight: 600 !important;
  padding: 4px 11px !important; border-radius: 6px !important;
  letter-spacing: .03em !important;
}

/* Titre */
.ria-title {
  font-family: 'Plus Jakarta Sans', sans-serif !important;
  font-size: 42px !important; font-weight: 800 !important;
  letter-spacing: -.03em !important; line-height: 1.1 !important;
  color: #ffffff !important; margin: 0 0 12px 0 !important;
  padding: 0 !important; text-align: left !important;
}

/* Description */
.ria-desc {
  font-size: 14.5px !important; color: rgba(255,255,255,.5) !important;
  max-width: 660px !important; line-height: 1.7 !important;
  font-weight: 500 !important; margin: 0 0 22px 0 !important;
  padding: 0 !important; text-align: left !important;
}

/* Actions */
.ria-actions { display: flex !important; align-items: center !important; gap: 10px !important; flex-wrap: wrap !important; margin-top: 4px !important; }

/* Boutons */
.ria-btn-primary {
  background: #D4AA60 !important; color: #0f1a2e !important;
  font-family: 'Plus Jakarta Sans', sans-serif !important;
  font-weight: 800 !important; font-size: 13.5px !important;
  padding: 11px 20px !important; border-radius: 8px !important;
  text-decoration: none !important; display: inline-flex !important;
  align-items: center !important; gap: 7px !important;
  border: none !important; cursor: pointer !important;
  transition: background .18s, transform .15s !important; line-height: 1 !important;
}
.ria-btn-primary:hover { background: #fff !important; transform: translateY(-1px) !important; color: #0f1a2e !important; }

.ria-btn-ghost {
  color: rgba(255,255,255,.38) !important;
  font-family: 'Plus Jakarta Sans', sans-serif !important;
  font-weight: 600 !important; font-size: 13px !important;
  padding: 11px 8px !important; text-decoration: none !important;
  display: inline-flex !important; align-items: center !important; gap: 5px !important;
  background: transparent !important; border: none !important;
  cursor: pointer !important; transition: color .18s !important; line-height: 1 !important;
}
.ria-btn-ghost:hover { color: rgba(255,255,255,.8) !important; }

/* Séparateur */
.ria-divider {
  height: 1px !important;
  background: linear-gradient(90deg, rgba(212,170,96,.25), rgba(212,170,96,.07) 60%, transparent) !important;
  margin-top: 32px !important; border: none !important;
}

/* Stat cards (col droite du hero) */
.ria-stats { display: flex !important; flex-direction: column !important; gap: 10px !important; padding-top: 2px !important; }
.ria-stat {
  background: rgba(255,255,255,.03) !important;
  border: 1px solid rgba(212,170,96,.12) !important;
  border-left: 2.5px solid rgba(212,170,96,.38) !important;
  border-radius: 13px !important; padding: 14px 16px !important;
  transition: background .2s, border-left-color .2s !important;
}
.ria-stat:hover { background: rgba(255,255,255,.05) !important; border-left-color: #D4AA60 !important; }
.ria-stat-label { font-size: 10px !important; font-weight: 700 !important; text-transform: uppercase !important; letter-spacing: .09em !important; color: rgba(255,255,255,.32) !important; margin-bottom: 4px !important; display: block !important; }
.ria-stat-val   { font-size: 22px !important; font-weight: 800 !important; color: #ffffff !important; letter-spacing: -.02em !important; line-height: 1.1 !important; display: block !important; }
.ria-stat-val.gold { color: #D4AA60 !important; }
.ria-stat-sub   { font-size: 11px !important; color: rgba(255,255,255,.3) !important; font-weight: 600 !important; margin-top: 2px !important; display: block !important; }
.ria-stat-up    { color: #4BD49A !important; }

/* Responsive hero */
@media (max-width: 1150px) {
  .ria-hero-grid { grid-template-columns: 110px 1fr !important; }
  .ria-stats     { display: none !important; }
}
@media (max-width: 768px) {
  .ria-hero      { padding: 24px 20px 32px !important; }
  .ria-hero-grid { grid-template-columns: 1fr !important; gap: 20px !important; }
  .ria-title     { font-size: 30px !important; }
  .ria-logo      { width: 86px !important; height: 86px !important; border-radius: 18px !important; }
}

/* ─────────────────────────────────────────────────────────────
   CONTENU PRINCIPAL
───────────────────────────────────────────────────────────── */
.belt       { background: var(--ivory); padding: 40px 52px 72px; }
.belt-inner { max-width: 1320px; margin: 0 auto; }
.main-grid  { display: grid; grid-template-columns: 1fr 320px; gap: 26px; align-items: start; }

/* Section card */
.sc { background: var(--white); border: 1px solid var(--border); border-radius: var(--r20); padding: 28px; margin-bottom: 22px; }
.sc-head  { display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; }
.sc-title { font-size: 15px; font-weight: 800; color: var(--navy); display: flex; align-items: center; gap: 10px; }
.sc-icon  { width: 30px; height: 30px; background: var(--navy); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; color: var(--butter); flex-shrink: 0; }
.sc-badge { font-size: 11.5px; color: var(--muted); font-weight: 600; background: var(--surface); padding: 4px 10px; border-radius: 5px; }

/* Grille outils / modèles */
.og { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 14px; }
.oc {
  background: var(--ivory); border: 1px solid var(--border);
  border-radius: var(--r14); padding: 20px;
  display: flex; flex-direction: column;
  transition: border-color .2s, background .2s, transform .2s;
}
.oc:hover { border-color: var(--butter-b); background: var(--butter-l); transform: translateY(-2px); }
.oc-top   { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.oc-logo  {
  width: 44px; height: 44px; background: var(--navy); border-radius: 11px;
  display: flex; align-items: center; justify-content: center;
  font-weight: 800; font-size: 13px; color: var(--butter);
  flex-shrink: 0; overflow: hidden;
}
.oc-logo img { width: 100%; height: 100%; object-fit: cover; }
.oc-name  { font-size: 14px; font-weight: 800; color: var(--navy); margin-bottom: 2px; }
.oc-sub   { font-size: 11.5px; color: var(--muted); font-weight: 600; }
.oc-desc  { font-size: 12.5px; color: var(--muted); line-height: 1.55; font-weight: 500; flex-grow: 1; margin-bottom: 13px; }
.oc-tags  { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 12px; }
.oc-tag   { background: rgba(27,42,74,.06); border: 1px solid rgba(27,42,74,.1); color: var(--navy); font-size: 10.5px; font-weight: 700; padding: 3px 8px; border-radius: 4px; }
.oc-foot  { display: flex; align-items: center; justify-content: space-between; margin-top: auto; }
.oc-rating { font-size: 13px; font-weight: 800; color: var(--butter); }
.oc-link  { color: var(--butter); font-size: 12.5px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; transition: color .2s; }
.oc-link:hover { color: var(--navy); }

/* Empty state */
.empty-state { text-align: center; padding: 56px 20px; color: var(--muted); }
.empty-icon  { font-size: 44px; display: block; margin-bottom: 12px; opacity: .22; }

/* ─────────────────────────────────────────────────────────────
   SIDEBAR
───────────────────────────────────────────────────────────── */
.side { display: flex; flex-direction: column; gap: 16px; }

.scard-dark { background: var(--navy-deep); border: 1px solid rgba(255,255,255,.06); border-radius: var(--r20); overflow: hidden; }
.scard-dark-head { background: rgba(255,255,255,.03); padding: 14px 20px; border-bottom: 1px solid rgba(255,255,255,.06); }
.scard-dark-title { font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .1em; color: rgba(255,255,255,.35); }
.scard-dark-body  { padding: 18px 20px; display: flex; flex-direction: column; gap: 13px; }
.sdi { display: flex; align-items: center; justify-content: space-between; }
.sdk { font-size: 13px; font-weight: 600; color: rgba(255,255,255,.4); }
.sdv { font-size: 13px; font-weight: 800; color: #fff; }
.sdv-gold { color: var(--butter); }
.sdv-link { color: var(--butter); text-decoration: none; font-weight: 800; font-size: 13px; transition: color .2s; }
.sdv-link:hover { color: #fff; }

.scard-cta { background: var(--navy-card); border: 1px solid rgba(212,170,96,.18); border-radius: var(--r20); padding: 22px; text-align: center; }
.scard-cta p   { font-size: 12.5px; color: rgba(255,255,255,.45); font-weight: 500; line-height: 1.6; margin-bottom: 14px; }
.scard-cta a   { display: block; background: var(--butter); color: var(--navy); font-weight: 800; font-size: 13.5px; padding: 12px; border-radius: 8px; text-decoration: none; transition: background .2s; }
.scard-cta a:hover { background: #fff; }

.scard  { background: var(--white); border: 1px solid var(--border); border-radius: var(--r20); padding: 22px; }
.stitle { font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .1em; color: var(--muted); padding-bottom: 11px; border-bottom: 1px solid var(--border); margin-bottom: 14px; }

.car-pills { display: flex; flex-wrap: wrap; gap: 7px; }
.car-pill  {
  background: var(--navy-deep); color: rgba(255,255,255,.78);
  padding: 6px 12px; font-size: 11.5px; font-weight: 700;
  border-radius: 6px; border: 1px solid rgba(255,255,255,.06);
  cursor: default; transition: background .2s, color .2s;
}
.car-pill:hover { background: var(--navy-hover); color: #fff; }

.dispo-list { display: flex; flex-direction: column; gap: 9px; list-style: none; padding: 0; }
.dispo-item { background: var(--surface); border: 1px solid var(--border); border-radius: var(--r8); padding: 11px 14px; display: flex; align-items: center; justify-content: space-between; transition: border-color .2s, background .2s; }
.dispo-item:hover { border-color: var(--butter-b); background: var(--butter-l); }
.dispo-type { font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); }
.dispo-link { font-size: 12.5px; font-weight: 700; color: var(--navy); text-decoration: none; display: flex; align-items: center; gap: 5px; transition: color .2s; }
.dispo-link:hover { color: var(--butter); }

/* Responsive contenu */
@media (max-width: 1150px) { .main-grid { grid-template-columns: 1fr; } }
@media (max-width: 768px)  {
  .belt { padding-left: 20px; padding-right: 20px; }
  .og   { grid-template-columns: 1fr; }
}
  </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<!-- ══ HERO ═══════════════════════════════════════════════════════════════ -->
<div class="ria-hero">
  <div class="ria-hero-inner">

    <!-- Fil d'ariane -->
    <nav class="ria-bc" aria-label="Fil d'ariane">
      <a href="dashboard.php">Accueil</a>
      <span class="ria-bc-sep" aria-hidden="true">›</span>
      <span class="ria-bc-cur">Modèles</span>
      <?php if ($model['provider_name']): ?>
        <span class="ria-bc-sep" aria-hidden="true">›</span>
        <span class="ria-bc-cur"><?= htmlspecialchars($model['provider_name']) ?></span>
      <?php endif; ?>
      <span class="ria-bc-sep" aria-hidden="true">›</span>
      <span class="ria-bc-cur" aria-current="page"><?= htmlspecialchars($model['name']) ?></span>
    </nav>

    <div class="ria-hero-grid">

      <!-- Logo provider -->
      <div class="ria-logo" role="img" aria-label="Logo <?= htmlspecialchars($model['provider_name'] ?? $model['name']) ?>">
        <?php if ($model['provider_logo']): ?>
          <img src="<?= htmlspecialchars($model['provider_logo']) ?>" alt="<?= htmlspecialchars($model['provider_name'] ?? '') ?>">
        <?php else: ?>
          <?= strtoupper(substr($model['name'], 0, 2)) ?>
        <?php endif; ?>
      </div>

      <!-- Centre -->
      <div class="ria-center">

        <div class="ria-pills">
          <?php if ($model['categorie']): ?>
            <span class="ria-pill ria-pill-cat"><?= htmlspecialchars($model['categorie']) ?></span>
          <?php endif; ?>
          <?php if ($model['provider_name']): ?>
            <span class="ria-pill ria-pill-prov">
              <i class="bi bi-building" aria-hidden="true" style="font-size:10px"></i>
              <?= htmlspecialchars($model['provider_name']) ?>
            </span>
          <?php endif; ?>
          <span class="ria-pill ria-pill-live">
            <span class="ria-pill-live-dot"></span>Actif
          </span>
        </div>

        <h1 class="ria-title"><?= htmlspecialchars($model['name']) ?></h1>

        <?php if ($model['description']): ?>
          <p class="ria-desc"><?= htmlspecialchars($model['description']) ?></p>
        <?php endif; ?>

        <!-- Tags -->
        <?php if ($tags): ?>
        <div class="ria-tags">
          <?php foreach ($tags as $t): ?>
            <span class="ria-tag"><?= htmlspecialchars($t) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Boutons -->
        <div class="ria-actions">
          <?php if ($model['provider_url']): ?>
            <a class="ria-btn-primary" href="<?= htmlspecialchars($model['provider_url']) ?>" target="_blank" rel="noopener noreferrer">
              <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Voir le provider
            </a>
          <?php endif; ?>
          <a class="ria-btn-ghost" href="javascript:history.back()">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Retour
          </a>
        </div>
      </div>

      <!-- Stats sidebar -->
      <div class="ria-stats" aria-label="Informations rapides">
        <div class="ria-stat">
          <span class="ria-stat-label">Provider</span>
          <span class="ria-stat-val" style="font-size:16px !important"><?= htmlspecialchars($model['provider_name'] ?? '—') ?></span>
          <span class="ria-stat-sub">Fournisseur du modèle</span>
        </div>
        <?php if (count($outils)): ?>
        <div class="ria-stat">
          <span class="ria-stat-label">Utilisé par</span>
          <span class="ria-stat-val gold"><?= count($outils) ?></span>
          <span class="ria-stat-sub">outil<?= count($outils) > 1 ? 's' : '' ?> IA</span>
        </div>
        <?php endif; ?>
        <?php if (count($autres)): ?>
        <div class="ria-stat">
          <span class="ria-stat-label">Autres modèles</span>
          <span class="ria-stat-val"><?= count($autres) ?></span>
          <span class="ria-stat-sub ria-stat-up">↑ Même provider</span>
        </div>
        <?php endif; ?>
      </div>

    </div><!-- /ria-hero-grid -->

    <div class="ria-divider" aria-hidden="true"></div>

  </div>
</div><!-- /ria-hero -->

<!-- ══ CONTENU ════════════════════════════════════════════════════════════ -->
<div class="belt">
  <div class="belt-inner">
    <div class="main-grid">

      <!-- ─── Colonne principale ─────────────────────────────────────── -->
      <main>

        <!-- OUTILS QUI UTILISENT CE MODÈLE -->
        <?php if ($outils): ?>
        <div class="sc">
          <div class="sc-head">
            <div class="sc-title">
              <div class="sc-icon" aria-hidden="true">🛠️</div>
              Outils utilisant ce modèle
            </div>
            <span class="sc-badge"><?= count($outils) ?> outil<?= count($outils) > 1 ? 's' : '' ?></span>
          </div>
          <div class="og">
            <?php foreach ($outils as $o):
              $rating  = number_format($o['global_rating'], 1);
              $version = $o['version'] ? 'v' . number_format($o['version'], 1) : '';
            ?>
            <div class="oc">
              <div class="oc-top">
                <div class="oc-logo">
                  <?php if ($o['logo_url']): ?>
                    <img src="<?= htmlspecialchars($o['logo_url']) ?>" alt="<?= htmlspecialchars($o['nom']) ?>">
                  <?php else: ?>
                    <?= strtoupper(substr($o['nom'], 0, 2)) ?>
                  <?php endif; ?>
                </div>
                <div style="min-width:0">
                  <div class="oc-name"><?= htmlspecialchars($o['nom']) ?></div>
                  <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                    <span class="oc-sub"><?= htmlspecialchars($o['categorie'] ?? 'Non classé') ?></span>
                    <?php if ($version): ?>
                      <span style="font-size:10.5px;color:var(--muted);font-weight:600"><?= $version ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <p class="oc-desc"><?= htmlspecialchars($o['description'] ?? 'Aucune description disponible.') ?></p>
              <div class="oc-foot">
                <span class="oc-rating">★ <?= $rating ?></span>
                <a class="oc-link" href="outil.php?id=<?= $o['ID_OUTILS_IA'] ?>">
                  Voir l'outil <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- AUTRES MODÈLES DU MÊME PROVIDER -->
        <?php if ($autres): ?>
        <div class="sc">
          <div class="sc-head">
            <div class="sc-title">
              <div class="sc-icon" aria-hidden="true">🔗</div>
              Autres modèles de <?= htmlspecialchars($model['provider_name']) ?>
            </div>
            <span class="sc-badge"><?= count($autres) ?> modèle<?= count($autres) > 1 ? 's' : '' ?></span>
          </div>
          <div class="og">
            <?php foreach ($autres as $am):
              $atags = array_filter(array_map('trim', explode(',', $am['tags'] ?? '')));
            ?>
            <div class="oc">
              <div class="oc-top">
                <div class="oc-logo">
                  <?php if ($model['provider_logo']): ?>
                    <img src="<?= htmlspecialchars($model['provider_logo']) ?>" alt="">
                  <?php else: ?>
                    <?= strtoupper(substr($am['name'], 0, 2)) ?>
                  <?php endif; ?>
                </div>
                <div style="min-width:0">
                  <div class="oc-name"><?= htmlspecialchars($am['name']) ?></div>
                  <span class="oc-sub"><?= htmlspecialchars($am['categorie'] ?? '—') ?></span>
                </div>
              </div>
              <p class="oc-desc"><?= htmlspecialchars($am['description'] ?? 'Aucune description disponible.') ?></p>
              <?php if ($atags): ?>
              <div class="oc-tags">
                <?php foreach ($atags as $t): ?>
                  <span class="oc-tag"><?= htmlspecialchars($t) ?></span>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
              <div class="oc-foot">
                <span></span>
                <a class="oc-link" href="modele.php?id=<?= $am['ID_MODEL'] ?>">
                  Voir le modèle <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- État vide -->
        <?php if (!$outils && !$autres): ?>
        <div class="empty-state">
          <span class="empty-icon" aria-hidden="true">🔍</span>
          <p style="font-size:14px;font-weight:700;color:var(--navy);margin-bottom:6px">Aucun outil associé</p>
          <p style="font-size:13px">Ce modèle n'est encore utilisé par aucun outil répertorié.</p>
        </div>
        <?php endif; ?>

      </main>

      <!-- ─── Sidebar ──────────────────────────────────────────────── -->
      <aside class="side">

        <!-- Infos clés -->
        <div class="scard-dark">
          <div class="scard-dark-head">
            <div class="scard-dark-title">Informations</div>
          </div>
          <div class="scard-dark-body">
            <div class="sdi">
              <span class="sdk">Nom</span>
              <span class="sdv"><?= htmlspecialchars($model['name']) ?></span>
            </div>
            <div class="sdi">
              <span class="sdk">Provider</span>
              <span class="sdv">
                <?php if ($model['provider_url']): ?>
                  <a class="sdv-link" href="<?= htmlspecialchars($model['provider_url']) ?>" target="_blank" rel="noopener noreferrer">
                    <?= htmlspecialchars($model['provider_name'] ?? '—') ?> ↗
                  </a>
                <?php else: ?>
                  <?= htmlspecialchars($model['provider_name'] ?? '—') ?>
                <?php endif; ?>
              </span>
            </div>
            <div class="sdi">
              <span class="sdk">Catégorie</span>
              <span class="sdv"><?= htmlspecialchars($model['categorie'] ?? '—') ?></span>
            </div>
            <?php if (count($outils)): ?>
            <div class="sdi">
              <span class="sdk">Utilisé par</span>
              <span class="sdv sdv-gold"><?= count($outils) ?> outil<?= count($outils) > 1 ? 's' : '' ?></span>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- CTA provider -->
        <?php if ($model['provider_url']): ?>
        <div class="scard-cta">
          <p>Accédez au site officiel du provider pour en savoir plus.</p>
          <a href="<?= htmlspecialchars($model['provider_url']) ?>" target="_blank" rel="noopener noreferrer">
            Visiter <?= htmlspecialchars($model['provider_name'] ?? 'le provider') ?> →
          </a>
        </div>
        <?php endif; ?>

        <!-- Tags -->
        <?php if ($tags): ?>
        <div class="scard">
          <div class="stitle">Tags</div>
          <div class="car-pills">
            <?php foreach ($tags as $t): ?>
              <span class="car-pill"><?= htmlspecialchars($t) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Caractéristiques -->
        <?php if ($cars): ?>
        <div class="scard">
          <div class="stitle">Caractéristiques</div>
          <div class="car-pills">
            <?php foreach ($cars as $car): ?>
              <span class="car-pill" title="<?= htmlspecialchars($car['description'] ?? '') ?>">
                <?= htmlspecialchars($car['name']) ?>
              </span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Disponibilités -->
        <?php if ($dispos): ?>
        <div class="scard">
          <div class="stitle">Disponibilités</div>
          <ul class="dispo-list">
            <?php foreach ($dispos as $d): ?>
            <li class="dispo-item">
              <span class="dispo-type"><?= htmlspecialchars($d['type_name'] ?? 'Lien') ?></span>
              <a href="<?= htmlspecialchars($d['url']) ?>" target="_blank" rel="noopener noreferrer" class="dispo-link">
                <?= htmlspecialchars(parse_url($d['url'], PHP_URL_HOST)) ?>
                <i class="bi bi-box-arrow-up-right" style="font-size:11px" aria-hidden="true"></i>
              </a>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

      </aside>

    </div><!-- /main-grid -->
  </div><!-- /belt-inner -->
</div><!-- /belt -->

<?php include "../includes/footer.php"; ?>
</body>
</html>