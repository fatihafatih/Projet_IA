<?php
session_start();
require_once '../includes/connexionbd.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) { header('Location: dashboard.php'); exit; }

$review_error = '';
$review_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rating'])) {
  if (isset($_SESSION['user_id'])) {
    $rating = (int) $_POST['rating'];
    $comment = trim($_POST['comment'] ?? '');
    if ($rating < 1 || $rating > 5) {
      $review_error = 'Veuillez sélectionner une note entre 1 et 5.';
    } else {
      $ins = $pdo->prepare("INSERT INTO reviews (ID_OUTILS_IA, ID_USERS, rating, comment) VALUES (?, ?, ?, ?)");
      $ins->execute([$id, $_SESSION['user_id'], $rating, $comment ?: null]);
      $pdo->prepare("UPDATE outils_ia SET global_rating = (SELECT AVG(rating) FROM reviews WHERE ID_OUTILS_IA = ?) WHERE ID_OUTILS_IA = ?")->execute([$id, $id]);
      header("Location: outil.php?id=$id#avis"); exit;
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_review'])) {
  if (isset($_SESSION['user_id'])) {
    $review_id = (int) $_POST['delete_review'];
    $pdo->prepare("DELETE FROM reviews WHERE ID_REVIEW = ? AND ID_USERS = ?")->execute([$review_id, $_SESSION['user_id']]);
    $pdo->prepare("UPDATE outils_ia SET global_rating = (SELECT AVG(rating) FROM reviews WHERE ID_OUTILS_IA = ?) WHERE ID_OUTILS_IA = ?")->execute([$id, $id]);
    header("Location: outil.php?id=$id#avis"); exit;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_review'])) {
  if (isset($_SESSION['user_id'])) {
    $review_id = (int) $_POST['edit_review'];
    $rating_edit = (int) $_POST['rating_edit'];
    $comment_edit = trim($_POST['comment_edit'] ?? '');
    if ($rating_edit >= 1 && $rating_edit <= 5) {
      $pdo->prepare("UPDATE reviews SET rating = ?, comment = ? WHERE ID_REVIEW = ? AND ID_USERS = ?")->execute([$rating_edit, $comment_edit ?: null, $review_id, $_SESSION['user_id']]);
      $pdo->prepare("UPDATE outils_ia SET global_rating = (SELECT AVG(rating) FROM reviews WHERE ID_OUTILS_IA = ?) WHERE ID_OUTILS_IA = ?")->execute([$id, $id]);
      header("Location: outil.php?id=$id#avis"); exit;
    }
  }
}

$stmt = $pdo->prepare("SELECT o.*, c.name AS categorie FROM outils_ia o LEFT JOIN categorie c ON o.ID_CATEGORIE = c.ID_CATEGORIE WHERE o.ID_OUTILS_IA = ? AND o.status = 'actif'");
$stmt->execute([$id]);
$outil = $stmt->fetch();
if (!$outil) { header('Location: dashboard.php'); exit; }

$stmt = $pdo->prepare("SELECT AVG(rapidite) AS rapidite, AVG(qualite) AS qualite, AVG(qualite_image) AS qualite_image, AVG(credibilite) AS credibilite, AVG(score_global) AS score_global, COUNT(*) AS nb_evals FROM performance WHERE ID_OUTILS_IA = ?");
$stmt->execute([$id]);
$perf = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM avantages_inconvenients WHERE ID_OUTILS_IA = ? ORDER BY type");
$stmt->execute([$id]);
$ais = $stmt->fetchAll();
$avantages     = array_filter($ais, fn($r) => $r['type'] === 'avantage');
$inconvenients = array_filter($ais, fn($r) => $r['type'] === 'inconvenient');

$stmt = $pdo->prepare("
    SELECT r.*, u.nom AS user_nom, u.image AS user_image
    FROM reviews r
    JOIN users u ON r.ID_USERS = u.id
    WHERE r.ID_OUTILS_IA = ?
    ORDER BY r.ID_REVIEW DESC
");
$stmt->execute([$id]);
$reviews = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT m.*, p.name AS provider_name, p.logo_url AS provider_logo, cat.name AS categorie FROM tool_models tm JOIN models m ON tm.ID_MODEL = m.ID_MODEL LEFT JOIN providers p ON m.ID_PROVIDERS = p.ID_PROVIDERS LEFT JOIN categorie cat ON m.ID_CATEGORIE = cat.ID_CATEGORIE WHERE tm.ID_OUTILS_IA = ? AND m.status='actif'");
$stmt->execute([$id]);
$modeles = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT car.name, car.description FROM model_caracteristiques mc JOIN caracteristiques car ON mc.ID_CAR = car.ID_CAR JOIN tool_models tm ON mc.ID_MODEL = tm.ID_MODEL WHERE tm.ID_OUTILS_IA = ? GROUP BY car.ID_CAR");
$stmt->execute([$id]);
$cars = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT d.url, ta.name AS type_name FROM tool_caracteristiques tc JOIN disponibilite d ON tc.ID_DIS = d.ID_DIS LEFT JOIN type_application ta ON d.ID_TA = ta.ID_TA JOIN models m ON tc.ID_MODEL = m.ID_MODEL JOIN tool_models tm ON m.ID_MODEL = tm.ID_MODEL WHERE tm.ID_OUTILS_IA = ? GROUP BY d.ID_DIS");
$stmt->execute([$id]);
$dispos = $stmt->fetchAll();

$avg_review = count($reviews) ? round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1) : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($outil['nom']) ?> — Référentiel IA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../styles/favoris.css">
  <style>
/* ═══════════════════════════════════════════════════════════════
   RÉFÉRENTIEL IA — PREMIUM NAVY & GOLD THEME
═══════════════════════════════════════════════════════════════ */
:root {
  --navy:       #1B2A4A;
  --navy-deep:  #0f1a2e;
  --navy-mid:   #192640;
  --navy-hover: #243658;
  --navy-card:  #223458;
  --butter:     #D4AA60;
  --butter-l:   #F5EDD8;
  --butter-b:   #E8C97A;
  --ivory:      #FAFAF7;
  --white:      #FFFFFF;
  --surface:    #F2F4F8;
  --muted:      #6B7A99;
  --border:     #DDE2EE;
  --text:       #1A2340;
  --green:      #0F7A4E;
  --red:        #C0392B;
  --r20: 20px;
  --r14: 14px;
  --r8:   8px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  background: var(--ivory);
  color: var(--text);
  font-family: 'Plus Jakarta Sans', sans-serif;
  -webkit-font-smoothing: antialiased;
}

/* ─────────────────────────────────────────────────────────────
   HERO — styles forcés pour résister aux CSS externes
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
  flex-wrap: nowrap !important;
  justify-content: flex-start !important;
}
.ria-bc a {
  font-size: 12px !important;
  font-weight: 600 !important;
  color: rgba(255,255,255,.38) !important;
  text-decoration: none !important;
}
.ria-bc a:hover { color: #D4AA60 !important; }
.ria-bc-sep { font-size: 10px !important; color: rgba(255,255,255,.2) !important; }
.ria-bc-cur { font-size: 12px !important; font-weight: 700 !important; color: rgba(255,255,255,.72) !important; }

/* Grille : logo | centre | stats */
.ria-hero-grid {
  display: grid !important;
  grid-template-columns: 110px 1fr 250px !important;
  gap: 32px !important;
  align-items: start !important;
}

/* Logo */
.ria-logo {
  width: 110px !important;
  height: 110px !important;
  background: #1c2d48 !important;
  border: 1.5px solid rgba(212,170,96,.3) !important;
  border-radius: 22px !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  font-family: 'Plus Jakarta Sans', sans-serif !important;
  font-weight: 800 !important;
  font-size: 26px !important;
  color: #D4AA60 !important;
  overflow: hidden !important;
  flex-shrink: 0 !important;
  box-shadow: 0 0 0 6px rgba(212,170,96,.07), 0 20px 40px rgba(0,0,0,.35) !important;
}
.ria-logo img {
  width: 100% !important;
  height: 100% !important;
  object-fit: cover !important;
  display: block !important;
}

/* Bloc centre */
.ria-center {
  display: flex !important;
  flex-direction: column !important;
  align-items: flex-start !important;
  text-align: left !important;
}

/* Pills */
.ria-pills {
  display: flex !important;
  align-items: center !important;
  gap: 7px !important;
  margin-bottom: 14px !important;
  flex-wrap: wrap !important;
}
.ria-pill {
  display: inline-flex !important;
  align-items: center !important;
  gap: 5px !important;
  padding: 5px 13px !important;
  font-size: 11px !important;
  font-weight: 700 !important;
  border-radius: 99px !important;
  letter-spacing: .04em !important;
  line-height: 1 !important;
}
.ria-pill-cat {
  background: rgba(255,255,255,.08) !important;
  border: 1px solid rgba(255,255,255,.13) !important;
  color: rgba(255,255,255,.85) !important;
}
.ria-pill-ver {
  background: rgba(212,170,96,.14) !important;
  border: 1px solid rgba(212,170,96,.28) !important;
  color: #E8C97A !important;
}
.ria-pill-live {
  background: rgba(15,122,78,.2) !important;
  border: 1px solid rgba(75,212,154,.3) !important;
  color: #4BD49A !important;
}
.ria-pill-live-dot {
  width: 6px !important; height: 6px !important;
  background: #4BD49A !important;
  border-radius: 50% !important;
  display: inline-block !important;
  animation: ria-blink 2s infinite !important;
}
@keyframes ria-blink { 0%,100%{opacity:1} 50%{opacity:.25} }

/* Titre */
.ria-title {
  font-family: 'Plus Jakarta Sans', sans-serif !important;
  font-size: 42px !important;
  font-weight: 800 !important;
  letter-spacing: -.03em !important;
  line-height: 1.1 !important;
  color: #ffffff !important;
  margin: 0 0 12px 0 !important;
  padding: 0 !important;
  text-align: left !important;
}

/* Description */
.ria-desc {
  font-size: 14.5px !important;
  color: rgba(255,255,255,.5) !important;
  max-width: 660px !important;
  line-height: 1.7 !important;
  font-weight: 500 !important;
  margin: 0 0 26px 0 !important;
  padding: 0 !important;
  text-align: left !important;
}

/* Actions */
.ria-actions {
  display: flex !important;
  align-items: center !important;
  gap: 10px !important;
  flex-wrap: wrap !important;
}

/* Score chip */
.ria-score {
  display: inline-flex !important;
  align-items: center !important;
  gap: 8px !important;
  background: rgba(255,255,255,.05) !important;
  border: 1px solid rgba(255,255,255,.1) !important;
  padding: 9px 15px !important;
  border-radius: 10px !important;
}
.ria-score-star { color: #D4AA60 !important; font-size: 16px !important; line-height: 1 !important; }
.ria-score-num  { font-size: 21px !important; font-weight: 800 !important; color: #fff !important; letter-spacing: -.02em !important; line-height: 1 !important; }
.ria-score-den  { font-size: 11px !important; color: rgba(255,255,255,.35) !important; font-weight: 600 !important; }
.ria-score-sep  { width: 1px !important; height: 22px !important; background: rgba(255,255,255,.12) !important; }
.ria-score-rev  { font-size: 11px !important; color: rgba(255,255,255,.4) !important; font-weight: 600 !important; line-height: 1.4 !important; }
.ria-score-rev strong { display: block !important; font-size: 15px !important; font-weight: 800 !important; color: #fff !important; }

/* Boutons */
.ria-btn-primary {
  background: #D4AA60 !important;
  color: #0f1a2e !important;
  font-family: 'Plus Jakarta Sans', sans-serif !important;
  font-weight: 800 !important;
  font-size: 13.5px !important;
  padding: 11px 20px !important;
  border-radius: 8px !important;
  text-decoration: none !important;
  display: inline-flex !important;
  align-items: center !important;
  gap: 7px !important;
  border: none !important;
  cursor: pointer !important;
  transition: background .18s, transform .15s !important;
  line-height: 1 !important;
}
.ria-btn-primary:hover { background: #fff !important; transform: translateY(-1px) !important; color: #0f1a2e !important; }

.ria-btn-secondary {
  background: transparent !important;
  border: 1px solid rgba(255,255,255,.16) !important;
  color: rgba(255,255,255,.85) !important;
  font-family: 'Plus Jakarta Sans', sans-serif !important;
  font-weight: 700 !important;
  font-size: 13.5px !important;
  padding: 11px 18px !important;
  border-radius: 8px !important;
  display: inline-flex !important;
  align-items: center !important;
  gap: 7px !important;
  text-decoration: none !important;
  cursor: pointer !important;
  transition: border-color .18s, background .18s !important;
  line-height: 1 !important;
}
.ria-btn-secondary:hover { border-color: #E8C97A !important; background: rgba(255,255,255,.06) !important; color: #fff !important; }

.ria-btn-ghost {
  color: rgba(255,255,255,.38) !important;
  font-family: 'Plus Jakarta Sans', sans-serif !important;
  font-weight: 600 !important;
  font-size: 13px !important;
  padding: 11px 8px !important;
  text-decoration: none !important;
  display: inline-flex !important;
  align-items: center !important;
  gap: 5px !important;
  background: transparent !important;
  border: none !important;
  cursor: pointer !important;
  transition: color .18s !important;
  line-height: 1 !important;
}
.ria-btn-ghost:hover { color: rgba(255,255,255,.8) !important; }

/* Séparateur */
.ria-divider {
  height: 1px !important;
  background: linear-gradient(90deg, rgba(212,170,96,.25), rgba(212,170,96,.07) 60%, transparent) !important;
  margin-top: 32px !important;
  border: none !important;
}

/* Stat cards (colonne droite du hero) */
.ria-stats {
  display: flex !important;
  flex-direction: column !important;
  gap: 10px !important;
  padding-top: 2px !important;
}
.ria-stat {
  background: rgba(255,255,255,.03) !important;
  border: 1px solid rgba(212,170,96,.12) !important;
  border-left: 2.5px solid rgba(212,170,96,.38) !important;
  border-radius: 13px !important;
  padding: 14px 16px !important;
  transition: background .2s, border-left-color .2s !important;
}
.ria-stat:hover { background: rgba(255,255,255,.05) !important; border-left-color: #D4AA60 !important; }
.ria-stat-label {
  font-size: 10px !important; font-weight: 700 !important;
  text-transform: uppercase !important; letter-spacing: .09em !important;
  color: rgba(255,255,255,.32) !important; margin-bottom: 4px !important;
  display: block !important;
}
.ria-stat-val {
  font-size: 22px !important; font-weight: 800 !important;
  color: #ffffff !important; letter-spacing: -.02em !important;
  line-height: 1.1 !important; display: block !important;
}
.ria-stat-val.gold { color: #D4AA60 !important; }
.ria-stat-sub {
  font-size: 11px !important; color: rgba(255,255,255,.3) !important;
  font-weight: 600 !important; margin-top: 2px !important; display: block !important;
}
.ria-stat-up { color: #4BD49A !important; }

/* Responsive hero */
@media (max-width: 1150px) {
  .ria-hero-grid { grid-template-columns: 110px 1fr !important; }
  .ria-stats { display: none !important; }
}
@media (max-width: 768px) {
  .ria-hero { padding: 24px 20px 32px !important; }
  .ria-hero-grid { grid-template-columns: 1fr !important; gap: 20px !important; }
  .ria-title { font-size: 32px !important; }
  .ria-logo { width: 86px !important; height: 86px !important; border-radius: 18px !important; }
}

/* ─────────────────────────────────────────────────────────────
   CONTENU PRINCIPAL
───────────────────────────────────────────────────────────── */
.belt { background: var(--ivory); padding: 40px 52px 72px; }
.belt-inner { max-width: 1320px; margin: 0 auto; }
.main-grid { display: grid; grid-template-columns: 1fr 320px; gap: 26px; align-items: start; }

/* Section card */
.sc {
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: var(--r20);
  padding: 28px;
  margin-bottom: 22px;
}
.sc-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; }
.sc-title { font-size: 15px; font-weight: 800; color: var(--navy); display: flex; align-items: center; gap: 10px; }
.sc-icon {
  width: 30px; height: 30px;
  background: var(--navy);
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; color: var(--butter);
  flex-shrink: 0;
}
.sc-badge {
  font-size: 11.5px; color: var(--muted); font-weight: 600;
  background: var(--surface); padding: 4px 10px; border-radius: 5px;
}

/* ── Performances ── */
.perf-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 12px; }
.pc {
  background: var(--ivory);
  border: 1px solid var(--border);
  border-radius: var(--r14);
  padding: 18px;
  transition: border-color .2s, background .2s, transform .15s;
}
.pc:hover { border-color: var(--butter-b); background: var(--butter-l); transform: translateY(-2px); }
.pc-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.pc-label { font-size: 12px; font-weight: 700; color: var(--muted); }
.pc-val   { font-size: 21px; font-weight: 800; color: var(--navy); }
.pc-icon  { font-size: 15px; margin-right: 6px; }
.bar { background: #E4E8F0; height: 4px; border-radius: 99px; overflow: hidden; margin-top: 2px; }
.bar-f { background: linear-gradient(90deg, var(--butter), #E8A830); width: var(--pct); height: 100%; border-radius: 99px; }

/* ── Avantages / Inconvénients ── */
.pc-wrap { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.pcb { border-radius: var(--r14); padding: 20px; border: 1px solid; }
.pcb-pro { background: #F0FBF6; border-color: #A3DCC0; }
.pcb-con { background: #FFF3F2; border-color: #F5C0BA; }
.pcb-head {
  font-size: 11.5px; font-weight: 800; text-transform: uppercase;
  letter-spacing: .07em; margin-bottom: 14px;
  display: flex; align-items: center; gap: 7px;
}
.pcb-head-pro { color: #0A6E45; }
.pcb-head-con { color: #A8291E; }
.pcb-items { display: flex; flex-direction: column; gap: 9px; }
.pcb-item {
  display: flex; align-items: flex-start; gap: 9px;
  font-size: 13px; font-weight: 500; color: #3D4F6E; line-height: 1.55;
}
.pcb-dot { width: 5px; height: 5px; border-radius: 50%; flex-shrink: 0; margin-top: 6px; }
.dot-pro { background: #0A6E45; }
.dot-con { background: #A8291E; }

/* ── Modèles IA ── */
.mg { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; }
.mc {
  background: var(--ivory);
  border: 1px solid var(--border);
  border-radius: var(--r14);
  padding: 20px;
  display: flex; flex-direction: column;
  transition: border-color .2s, background .2s, transform .2s;
}
.mc:hover { border-color: var(--butter-b); background: var(--butter-l); transform: translateY(-2px); }
.mc-top { display: flex; align-items: center; gap: 11px; margin-bottom: 12px; }
.mc-logo {
  width: 40px; height: 40px;
  background: var(--navy); border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-weight: 800; font-size: 12px; color: var(--butter);
  flex-shrink: 0; overflow: hidden;
}
.mc-logo img { width: 100%; height: 100%; object-fit: cover; }
.mc-name { font-size: 14px; font-weight: 800; color: var(--navy); margin-bottom: 2px; }
.mc-prov { font-size: 11.5px; color: var(--muted); font-weight: 600; }
.mc-desc { font-size: 12.5px; color: var(--muted); line-height: 1.55; font-weight: 500; flex-grow: 1; margin-bottom: 12px; }
.mc-tags { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 12px; }
.mc-tag {
  background: rgba(27,42,74,.06);
  border: 1px solid rgba(27,42,74,.1);
  color: var(--navy); font-size: 10.5px; font-weight: 700;
  padding: 3px 8px; border-radius: 4px;
}
.mc-link {
  color: var(--butter); font-size: 12px; font-weight: 700;
  text-decoration: none; display: inline-flex; align-items: center;
  gap: 4px; align-self: flex-end; transition: color .2s;
}
.mc-link:hover { color: var(--navy); }

/* ── Avis ── */
.rev-badge {
  background: var(--butter-l); border: 1px solid var(--butter-b);
  color: #9A7020; padding: 4px 12px; border-radius: 7px;
  font-size: 13px; font-weight: 800;
}
.rev-form {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--r14); padding: 22px; margin-bottom: 24px;
}
.rev-form-title { font-size: 14px; font-weight: 800; color: var(--navy); margin-bottom: 16px; }
.sp {
  display: flex; align-items: center; gap: 11px;
  background: var(--white); border: 1px solid var(--border);
  padding: 10px 14px; border-radius: var(--r8); margin-bottom: 14px;
}
.sp-label { font-size: 12.5px; font-weight: 700; color: var(--muted); }
.sp-stars { display: flex; gap: 2px; }
.sp-s { cursor: pointer; font-size: 22px; color: #D5DAEA; transition: color .15s; user-select: none; }
.sp-s:hover, .sp-s.on { color: var(--butter); }
.sp-hint { font-size: 11.5px; color: var(--muted); font-weight: 600; margin-left: auto; }
.form-label {
  display: block; font-size: 11.5px; font-weight: 700;
  color: var(--muted); margin-bottom: 7px;
  text-transform: uppercase; letter-spacing: .05em;
}
.form-ta {
  width: 100%; background: var(--white); border: 1px solid var(--border);
  border-radius: var(--r8); padding: 12px; font-family: inherit;
  font-size: 13.5px; color: var(--text); resize: vertical; outline: none;
  transition: border-color .2s; min-height: 80px;
}
.form-ta:focus { border-color: var(--butter-b); }
.char-c { display: block; font-size: 11px; color: var(--muted); text-align: right; margin-top: 4px; font-weight: 600; }
.btn-submit {
  background: var(--navy); color: #fff; border: none;
  padding: 10px 20px; border-radius: var(--r8);
  font-weight: 800; font-size: 13.5px; cursor: pointer;
  display: inline-flex; align-items: center; gap: 7px;
  transition: background .2s; margin-top: 12px;
}
.btn-submit:hover { background: var(--navy-hover); }

.rev-list { display: flex; flex-direction: column; }
.rev-card { padding: 18px 0; border-bottom: 1px solid var(--border); }
.rev-card:last-child { border: none; padding-bottom: 0; }
.rev-hdr { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.rev-av {
  width: 38px; height: 38px; border-radius: 50%;
  background: var(--navy); font-weight: 800; font-size: 13px;
  color: var(--butter); display: flex; align-items: center;
  justify-content: center; flex-shrink: 0; overflow: hidden;
}
.rev-av img { width: 100%; height: 100%; object-fit: cover; }
.rev-name { font-size: 13.5px; font-weight: 800; color: var(--navy); display: block; margin-bottom: 2px; }
.rev-stars { display: flex; align-items: center; gap: 2px; font-size: 12px; }
.son { color: var(--butter); }
.soff { color: #D5DAEA; }
.rev-score { font-size: 11px; color: var(--muted); margin-left: 5px; font-weight: 700; }
.rev-actions { display: flex; gap: 3px; margin-left: auto; }
.rev-btn {
  background: transparent; border: none; cursor: pointer;
  width: 28px; height: 28px; border-radius: 6px;
  display: flex; align-items: center; justify-content: center;
  transition: background .2s; font-size: 12.5px;
}
.rev-btn-e { color: var(--muted); }
.rev-btn-e:hover { background: var(--surface); color: var(--navy); }
.rev-btn-d { color: var(--red); }
.rev-btn-d:hover { background: #FFF0EE; }
.rev-comment {
  font-size: 13px; color: var(--muted); line-height: 1.65;
  font-weight: 500; padding-left: 48px; font-style: italic;
}
.empty-state { text-align: center; padding: 48px 20px; color: var(--muted); }
.empty-icon { font-size: 40px; display: block; margin-bottom: 12px; opacity: .25; }

.ot-edit-form {
  display: none; margin-top: 12px; padding-top: 14px;
  border-top: 1px solid var(--border);
}
.ot-edit-actions { display: flex; align-items: center; gap: 10px; margin-top: 10px; }

.alert { padding: 11px 15px; border-radius: var(--r8); font-size: 13.5px; font-weight: 600; margin-bottom: 14px; }
.alert-err { background: #FFF0EE; border: 1px solid #F5C0BA; color: #A8291E; }
.alert-ok  { background: #F0FBF6; border: 1px solid #A3DCC0; color: #0A6E45; }

/* ─────────────────────────────────────────────────────────────
   SIDEBAR
───────────────────────────────────────────────────────────── */
.side { display: flex; flex-direction: column; gap: 16px; }

/* Carte navy sombre */
.scard-dark {
  background: var(--navy-deep);
  border: 1px solid rgba(255,255,255,.06);
  border-radius: var(--r20); overflow: hidden;
}
.scard-dark-head {
  background: rgba(255,255,255,.03);
  padding: 14px 20px;
  border-bottom: 1px solid rgba(255,255,255,.06);
}
.scard-dark-title {
  font-size: 10.5px; font-weight: 800; text-transform: uppercase;
  letter-spacing: .1em; color: rgba(255,255,255,.35);
}
.scard-dark-body {
  padding: 18px 20px;
  display: flex; flex-direction: column; gap: 13px;
}
.sdi { display: flex; align-items: center; justify-content: space-between; }
.sdk { font-size: 13px; font-weight: 600; color: rgba(255,255,255,.4); }
.sdv { font-size: 13px; font-weight: 800; color: #fff; }
.sdv-gold { color: var(--butter); }

/* CTA card */
.scard-cta {
  background: var(--navy-card);
  border: 1px solid rgba(212,170,96,.18);
  border-radius: var(--r20); padding: 22px; text-align: center;
}
.scard-cta p {
  font-size: 12.5px; color: rgba(255,255,255,.45);
  font-weight: 500; line-height: 1.6; margin-bottom: 14px;
}
.scard-cta a {
  display: block; background: var(--butter); color: var(--navy);
  font-weight: 800; font-size: 13.5px; padding: 12px;
  border-radius: 8px; text-decoration: none; transition: background .2s;
}
.scard-cta a:hover { background: #fff; }

/* Carte blanche */
.scard {
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: var(--r20); padding: 22px;
}
.stitle {
  font-size: 10.5px; font-weight: 800; text-transform: uppercase;
  letter-spacing: .1em; color: var(--muted);
  padding-bottom: 11px; border-bottom: 1px solid var(--border);
  margin-bottom: 14px;
}
.car-pills { display: flex; flex-wrap: wrap; gap: 7px; }
.car-pill {
  background: var(--navy-deep); color: rgba(255,255,255,.78);
  padding: 6px 12px; font-size: 11.5px; font-weight: 700;
  border-radius: 6px; border: 1px solid rgba(255,255,255,.06);
  cursor: default; transition: background .2s, color .2s;
}
.car-pill:hover { background: var(--navy-hover); color: #fff; }

.dispo-list { display: flex; flex-direction: column; gap: 9px; list-style: none; padding: 0; }
.dispo-item {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--r8); padding: 11px 14px;
  display: flex; align-items: center; justify-content: space-between;
  transition: border-color .2s, background .2s;
}
.dispo-item:hover { border-color: var(--butter-b); background: var(--butter-l); }
.dispo-type {
  font-size: 10.5px; font-weight: 800; text-transform: uppercase;
  letter-spacing: .06em; color: var(--muted);
}
.dispo-link {
  font-size: 12.5px; font-weight: 700; color: var(--navy);
  text-decoration: none; display: flex; align-items: center; gap: 5px;
  transition: color .2s;
}
.dispo-link:hover { color: var(--butter); }

/* ─────────────────────────────────────────────────────────────
   FOOTER
───────────────────────────────────────────────────────────── */
.ft {
  background: var(--navy-deep);
  border-top: 1px solid rgba(255,255,255,.06);
  padding: 26px 52px;
  display: flex; align-items: center; justify-content: space-between;
}
.ft-left { font-size: 12.5px; color: rgba(255,255,255,.32); font-weight: 600; }
.ft-left span { color: var(--butter); font-weight: 800; }
.ft-links { display: flex; gap: 22px; }
.ft-links a {
  font-size: 12px; color: rgba(255,255,255,.3);
  text-decoration: none; font-weight: 600; transition: color .2s;
}
.ft-links a:hover { color: rgba(255,255,255,.75); }

/* ─────────────────────────────────────────────────────────────
   RESPONSIVENESS
───────────────────────────────────────────────────────────── */
@media (max-width: 1150px) {
  .main-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
  .hero, .belt, .ft { padding-left: 20px; padding-right: 20px; }
  .belt  { padding-left: 20px; padding-right: 20px; }
  .ft    { padding-left: 20px; padding-right: 20px; }
  .h-title   { font-size: 30px; }
  .pc-wrap   { grid-template-columns: 1fr; }
  .ft        { flex-direction: column; gap: 14px; text-align: center; }
  .ft-links  { flex-wrap: wrap; justify-content: center; }
}
  </style>
</head>

<?php if (isset($_SESSION['user_id'])): ?>
<div class="fav-overlay" id="favOverlay" style="display:none">
  <div class="fav-modal" id="favModal">
    <div class="fav-modal-head">
      <span>💾 Sauvegarder dans…</span>
      <button class="fav-close" id="favClose">✕</button>
    </div>
    <div class="fav-collections" id="favCollections"></div>
    <div class="fav-new">
      <input type="text" id="favNewInput" placeholder="Nouvelle collection…" maxlength="100">
      <button id="favNewBtn">Créer</button>
    </div>
    <p class="fav-new-error" id="favNewError"></p>
  </div>
</div>
<?php endif; ?>

<body>

<?php include "../includes/header.php"; ?>

<!-- ══ HERO ═══════════════════════════════════════════════════════════ -->
<div class="ria-hero">
  <div class="ria-hero-inner">

    <nav class="ria-bc" aria-label="Fil d'ariane">
      <a href="dashboard.php">Accueil</a>
      <span class="ria-bc-sep" aria-hidden="true">›</span>
      <a href="dashboard.php"><?= htmlspecialchars($outil['categorie'] ?? 'Outils') ?></a>
      <span class="ria-bc-sep" aria-hidden="true">›</span>
      <span class="ria-bc-cur" aria-current="page"><?= htmlspecialchars($outil['nom']) ?></span>
    </nav>

    <div class="ria-hero-grid">

      <!-- Logo -->
      <div class="ria-logo" role="img" aria-label="Logo <?= htmlspecialchars($outil['nom']) ?>">
        <?php if ($outil['logo_url']): ?>
          <img src="<?= htmlspecialchars($outil['logo_url']) ?>" alt="<?= htmlspecialchars($outil['nom']) ?>">
        <?php else: ?>
          <?= strtoupper(substr($outil['nom'], 0, 2)) ?>
        <?php endif; ?>
      </div>

      <!-- Centre -->
      <div class="ria-center">
        <div class="ria-pills">
          <span class="ria-pill ria-pill-cat"><?= htmlspecialchars($outil['categorie'] ?? 'Non classé') ?></span>
          <?php if ($outil['version']): ?>
            <span class="ria-pill ria-pill-ver">v<?= number_format($outil['version'], 1) ?></span>
          <?php endif; ?>
          <span class="ria-pill ria-pill-live">
            <span class="ria-pill-live-dot"></span>Actif
          </span>
        </div>

        <h1 class="ria-title"><?= htmlspecialchars($outil['nom']) ?></h1>
        <p class="ria-desc"><?= htmlspecialchars($outil['description'] ?? '') ?></p>

        <div class="ria-actions">
          <div class="ria-score" aria-label="Note <?= number_format($outil['global_rating'], 1) ?> sur 5">
            <span class="ria-score-star" aria-hidden="true">★</span>
            <span class="ria-score-num"><?= number_format($outil['global_rating'], 1) ?></span>
            <span class="ria-score-den">/5</span>
            <?php if (count($reviews)): ?>
              <div class="ria-score-sep" aria-hidden="true"></div>
              <div class="ria-score-rev"><strong><?= count($reviews) ?></strong>avis</div>
            <?php endif; ?>
          </div>

          <?php if ($outil['url']): ?>
            <a class="ria-btn-primary" href="<?= htmlspecialchars($outil['url']) ?>" target="_blank" rel="noopener noreferrer">
              <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Visiter le site
            </a>
          <?php endif; ?>

          <?php if (isset($_SESSION['user_id'])): ?>
            <button class="ria-btn-secondary js-fav-btn" data-id="<?= $outil['ID_OUTILS_IA'] ?>" type="button">
              <i class="bi bi-heart" aria-hidden="true"></i> Sauvegarder
            </button>
          <?php endif; ?>

          <a class="ria-btn-ghost" href="dashboard.php">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Retour
          </a>
        </div>
      </div>

      <!-- Stats sidebar (masqué < 1150px) -->
      <div class="ria-stats" aria-label="Statistiques">
        <div class="ria-stat">
          <span class="ria-stat-label">Note globale</span>
          <span class="ria-stat-val gold">★ <?= number_format($outil['global_rating'], 1) ?></span>
          <span class="ria-stat-sub">sur 5 étoiles</span>
        </div>
        <?php if (count($reviews)): ?>
        <div class="ria-stat">
          <span class="ria-stat-label">Avis</span>
          <span class="ria-stat-val"><?= count($reviews) ?></span>
          <span class="ria-stat-sub">utilisateurs</span>
        </div>
        <?php endif; ?>
        <?php if ($perf && $perf['nb_evals'] > 0): ?>
        <div class="ria-stat">
          <span class="ria-stat-label">Évaluations</span>
          <span class="ria-stat-val"><?= $perf['nb_evals'] ?></span>
          <span class="ria-stat-sub ria-stat-up">↑ Performances</span>
        </div>
        <?php endif; ?>
      </div>

    </div><!-- /ria-hero-grid -->

    <div class="ria-divider" aria-hidden="true"></div>

  </div><!-- /ria-hero-inner -->
</div><!-- /ria-hero -->

<!-- ══ CONTENU PRINCIPAL ══════════════════════════════════════════════ -->
<div class="belt">
  <div class="belt-inner">
    <div class="main-grid">

      <!-- ─── Colonne principale ──────────────────────────────────── -->
      <main>

        <!-- PERFORMANCES -->
        <?php if ($perf && $perf['nb_evals'] > 0): ?>
        <div class="sc">
          <div class="sc-head">
            <div class="sc-title">
              <div class="sc-icon" aria-hidden="true">📊</div>
              Performances
            </div>
            <span class="sc-badge"><?= $perf['nb_evals'] ?> évaluations</span>
          </div>
          <div class="perf-grid">
            <?php
            $metrics = [
              ['label' => 'Rapidité',     'val' => $perf['rapidite'],     'icon' => '⚡'],
              ['label' => 'Qualité',      'val' => $perf['qualite'],      'icon' => '✨'],
              ['label' => 'Crédibilité',  'val' => $perf['credibilite'],  'icon' => '🛡️'],
              ['label' => 'Score global', 'val' => $perf['score_global'], 'icon' => '🏆'],
            ];
            if ($perf['qualite_image'] > 0)
              $metrics[] = ['label' => 'Qualité image', 'val' => $perf['qualite_image'], 'icon' => '🎨'];
            foreach ($metrics as $m):
              if (!$m['val']) continue;
              $pct = round(($m['val'] / 5) * 100);
            ?>
            <div class="pc">
              <div class="pc-top">
                <div style="display:flex;align-items:center">
                  <span class="pc-icon" aria-hidden="true"><?= $m['icon'] ?></span>
                  <span class="pc-label"><?= $m['label'] ?></span>
                </div>
                <span class="pc-val"><?= number_format($m['val'], 1) ?></span>
              </div>
              <div class="bar" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="bar-f" style="--pct:<?= $pct ?>%"></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- AVANTAGES / INCONVÉNIENTS -->
        <?php if ($avantages || $inconvenients): ?>
        <div class="sc">
          <div class="sc-head">
            <div class="sc-title">
              <div class="sc-icon" aria-hidden="true">⚖️</div>
              Avantages &amp; Inconvénients
            </div>
          </div>
          <div class="pc-wrap">
            <?php if ($avantages): ?>
            <div class="pcb pcb-pro">
              <div class="pcb-head pcb-head-pro">
                <i class="bi bi-check-circle-fill" aria-hidden="true"></i> Avantages
              </div>
              <div class="pcb-items">
                <?php foreach ($avantages as $a): ?>
                <div class="pcb-item">
                  <div class="pcb-dot dot-pro" aria-hidden="true"></div>
                  <?= htmlspecialchars($a['description']) ?>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>
            <?php if ($inconvenients): ?>
            <div class="pcb pcb-con">
              <div class="pcb-head pcb-head-con">
                <i class="bi bi-x-circle-fill" aria-hidden="true"></i> Inconvénients
              </div>
              <div class="pcb-items">
                <?php foreach ($inconvenients as $inc): ?>
                <div class="pcb-item">
                  <div class="pcb-dot dot-con" aria-hidden="true"></div>
                  <?= htmlspecialchars($inc['description']) ?>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- MODÈLES IA -->
        <?php if ($modeles): ?>
        <div class="sc">
          <div class="sc-head">
            <div class="sc-title">
              <div class="sc-icon" aria-hidden="true">🤖</div>
              Modèles utilisés
            </div>
            <span class="sc-badge"><?= count($modeles) ?> modèle<?= count($modeles) > 1 ? 's' : '' ?></span>
          </div>
          <div class="mg">
            <?php foreach ($modeles as $mod):
              $tags = array_filter(explode(',', $mod['tags'] ?? ''));
            ?>
            <div class="mc">
              <div class="mc-top">
                <div class="mc-logo">
                  <?php if ($mod['provider_logo']): ?>
                    <img src="<?= htmlspecialchars($mod['provider_logo']) ?>" alt="<?= htmlspecialchars($mod['provider_name'] ?? '') ?>">
                  <?php else: ?>
                    <?= strtoupper(substr($mod['name'], 0, 2)) ?>
                  <?php endif; ?>
                </div>
                <div>
                  <div class="mc-name"><?= htmlspecialchars($mod['name']) ?></div>
                  <div class="mc-prov"><?= htmlspecialchars($mod['provider_name'] ?? 'Inconnu') ?></div>
                </div>
              </div>
              <p class="mc-desc"><?= htmlspecialchars($mod['description'] ?? 'Aucune description disponible.') ?></p>
              <?php if ($tags): ?>
              <div class="mc-tags">
                <?php foreach ($tags as $t): ?>
                  <span class="mc-tag"><?= htmlspecialchars(trim($t)) ?></span>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
              <a class="mc-link" href="modele.php?id=<?= $mod['ID_MODEL'] ?>">
                Voir le modèle <i class="bi bi-arrow-right" aria-hidden="true"></i>
              </a>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- AVIS UTILISATEURS -->
        <div class="sc" id="avis">
          <div class="sc-head">
            <div class="sc-title">
              <div class="sc-icon" aria-hidden="true">💬</div>
              Avis utilisateurs
            </div>
            <?php if ($avg_review): ?>
              <span class="rev-badge">★ <?= $avg_review ?></span>
            <?php endif; ?>
          </div>

          <!-- Formulaire ajout avis -->
          <?php if (isset($_SESSION['user_id'])): ?>
          <div class="rev-form">
            <div class="rev-form-title">Laisser un avis</div>
            <?php if ($review_error): ?>
              <div class="alert alert-err" role="alert"><?= htmlspecialchars($review_error) ?></div>
            <?php endif; ?>
            <form method="POST" action="outil.php?id=<?= $id ?>#avis">
              <div class="sp">
                <span class="sp-label">Votre note</span>
                <div class="sp-stars" id="spStars" role="group" aria-label="Sélectionner une note">
                  <?php for ($s = 1; $s <= 5; $s++): ?>
                  <label class="sp-s" data-v="<?= $s ?>" for="star<?= $s ?>" aria-label="<?= $s ?> étoile<?= $s > 1 ? 's' : '' ?>">
                    <input type="radio" name="rating" id="star<?= $s ?>" value="<?= $s ?>" required style="display:none">★
                  </label>
                  <?php endfor; ?>
                </div>
                <span class="sp-hint" id="spHint" aria-live="polite">Cliquez pour noter</span>
              </div>
              <div style="position:relative">
                <label class="form-label" for="reviewTa">
                  Commentaire <span style="opacity:.5;text-transform:none;font-size:11px;font-weight:400">(optionnel)</span>
                </label>
                <textarea
                  class="form-ta"
                  name="comment"
                  id="reviewTa"
                  rows="3"
                  maxlength="1000"
                  placeholder="Partagez votre expérience avec cet outil…"
                ><?= htmlspecialchars($_POST['comment'] ?? '') ?></textarea>
                <span class="char-c"><span id="charCnt">0</span>/1000</span>
              </div>
              <button type="submit" class="btn-submit">
                <i class="bi bi-send-fill" aria-hidden="true"></i> Publier mon avis
              </button>
            </form>
          </div>
          <?php endif; ?>

          <!-- Liste des avis -->
          <?php if ($reviews): ?>
          <div class="rev-list">
            <?php foreach ($reviews as $rev):
              $stars  = round($rev['rating']);
              $is_own = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $rev['ID_USERS'];
              $avatarWebPath    = "/Projet_IA/php/uploads/avatars/" . $rev['user_image'];
              $avatarServerPath = $_SERVER['DOCUMENT_ROOT'] . "/Projet_IA/php/uploads/avatars/" . $rev['user_image'];
            ?>
            <div class="rev-card" id="review-<?= $rev['ID_REVIEW'] ?>">
              <div class="rev-hdr">
                <div class="rev-av">
                  <?php if (!empty($rev['user_image']) && file_exists($avatarServerPath)): ?>
                    <img src="<?= htmlspecialchars($avatarWebPath) ?>" alt="<?= htmlspecialchars($rev['user_nom']) ?>">
                  <?php else: ?>
                    <?= strtoupper(substr($rev['user_nom'], 0, 1)) ?>
                  <?php endif; ?>
                </div>
                <div style="flex-grow:1">
                  <span class="rev-name"><?= htmlspecialchars($rev['user_nom']) ?></span>
                  <div class="rev-stars" aria-label="Note : <?= $stars ?> sur 5">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                      <span class="<?= $s <= $stars ? 'son' : 'soff' ?>" aria-hidden="true">★</span>
                    <?php endfor; ?>
                    <span class="rev-score"><?= number_format($rev['rating'], 1) ?></span>
                  </div>
                </div>
                <?php if ($is_own): ?>
                <div class="rev-actions">
                  <button
                    class="rev-btn rev-btn-e js-edit-btn"
                    data-id="<?= $rev['ID_REVIEW'] ?>"
                    type="button"
                    title="Modifier cet avis"
                    aria-label="Modifier cet avis"
                  ><i class="bi bi-pencil-fill" aria-hidden="true"></i></button>
                  <form method="POST" action="outil.php?id=<?= $id ?>" style="display:inline" onsubmit="return confirm('Supprimer cet avis définitivement ?')">
                    <input type="hidden" name="delete_review" value="<?= $rev['ID_REVIEW'] ?>">
                    <button type="submit" class="rev-btn rev-btn-d" title="Supprimer cet avis" aria-label="Supprimer cet avis">
                      <i class="bi bi-trash-fill" aria-hidden="true"></i>
                    </button>
                  </form>
                </div>
                <?php endif; ?>
              </div>

              <?php if ($rev['comment']): ?>
                <p class="rev-comment" id="comment-text-<?= $rev['ID_REVIEW'] ?>">
                  <?= htmlspecialchars($rev['comment']) ?>
                </p>
              <?php endif; ?>

              <!-- Formulaire modification -->
              <?php if ($is_own): ?>
              <div class="ot-edit-form" id="edit-form-<?= $rev['ID_REVIEW'] ?>">
                <form method="POST" action="outil.php?id=<?= $id ?>">
                  <input type="hidden" name="edit_review" value="<?= $rev['ID_REVIEW'] ?>">
                  <div class="sp" style="margin-bottom:12px">
                    <span class="sp-label">Modifier la note</span>
                    <div class="sp-stars" role="group" aria-label="Modifier la note">
                      <?php for ($s = 1; $s <= 5; $s++): ?>
                      <label class="sp-s <?= $s <= round($rev['rating']) ? 'on' : '' ?>" for="edit-star-<?= $rev['ID_REVIEW'] ?>-<?= $s ?>">
                        <input
                          type="radio"
                          name="rating_edit"
                          id="edit-star-<?= $rev['ID_REVIEW'] ?>-<?= $s ?>"
                          value="<?= $s ?>"
                          <?= $s == round($rev['rating']) ? 'checked' : '' ?>
                          style="display:none"
                        >★
                      </label>
                      <?php endfor; ?>
                    </div>
                  </div>
                  <textarea
                    class="form-ta"
                    name="comment_edit"
                    rows="3"
                    placeholder="Modifier votre commentaire…"
                  ><?= htmlspecialchars($rev['comment'] ?? '') ?></textarea>
                  <div class="ot-edit-actions">
                    <button type="submit" class="btn-submit">
                      <i class="bi bi-check-lg" aria-hidden="true"></i> Enregistrer
                    </button>
                    <button
                      type="button"
                      class="js-cancel-btn"
                      data-id="<?= $rev['ID_REVIEW'] ?>"
                      style="color:var(--muted);font-size:13px;font-weight:600;background:none;border:none;cursor:pointer"
                    >Annuler</button>
                  </div>
                </form>
              </div>
              <?php endif; ?>

            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <div class="empty-state">
            <span class="empty-icon" aria-hidden="true">💭</span>
            <p style="font-size:14px;font-weight:700;color:var(--navy);margin-bottom:6px">Aucun avis pour le moment</p>
            <p style="font-size:13px">Soyez le premier à partager votre expérience !</p>
          </div>
          <?php endif; ?>
        </div>

      </main><!-- /main -->

      <!-- ─── Sidebar ─────────────────────────────────────────────── -->
      <aside class="side">

        <!-- Infos clés -->
        <div class="scard-dark">
          <div class="scard-dark-head">
            <div class="scard-dark-title">Informations</div>
          </div>
          <div class="scard-dark-body">
            <div class="sdi">
              <span class="sdk">Catégorie</span>
              <span class="sdv"><?= htmlspecialchars($outil['categorie'] ?? '—') ?></span>
            </div>
            <div class="sdi">
              <span class="sdk">Version</span>
              <span class="sdv"><?= $outil['version'] ? 'v' . number_format($outil['version'], 1) : '—' ?></span>
            </div>
            <div class="sdi">
              <span class="sdk">Note globale</span>
              <span class="sdv sdv-gold">★ <?= number_format($outil['global_rating'], 1) ?></span>
            </div>
            <?php if (count($reviews)): ?>
            <div class="sdi">
              <span class="sdk">Avis utilisateurs</span>
              <span class="sdv"><?= count($reviews) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($perf && $perf['nb_evals'] > 0): ?>
            <div class="sdi">
              <span class="sdk">Évaluations</span>
              <span class="sdv"><?= $perf['nb_evals'] ?></span>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- CTA -->
        <?php if ($outil['url']): ?>
        <div class="scard-cta">
          <p>Accédez directement à la plateforme officielle de <?= htmlspecialchars($outil['nom']) ?>.</p>
          <a href="<?= htmlspecialchars($outil['url']) ?>" target="_blank" rel="noopener noreferrer">
            Visiter <?= htmlspecialchars($outil['nom']) ?> →
          </a>
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

<script src="../js/outils.js"></script>
<script>
/* ── Star picker (nouveau formulaire) ── */
(function () {
  const container = document.getElementById('spStars');
  if (!container) return;
  const stars = container.querySelectorAll('.sp-s');
  const hint  = document.getElementById('spHint');
  const labels = ['Mauvais', 'Passable', 'Correct', 'Bon', 'Excellent'];
  let selected = 0;

  stars.forEach((s, i) => {
    s.addEventListener('mouseenter', () => {
      stars.forEach((x, j) => x.classList.toggle('on', j <= i));
      hint.textContent = labels[i];
    });
    s.addEventListener('click', () => {
      selected = i + 1;
      hint.textContent = labels[i] + ' — sélectionné';
    });
  });

  container.addEventListener('mouseleave', () => {
    stars.forEach((x, j) => x.classList.toggle('on', j < selected));
    hint.textContent = selected ? labels[selected - 1] : 'Cliquez pour noter';
  });
})();

/* ── Compteur caractères ── */
(function () {
  const ta  = document.getElementById('reviewTa');
  const cnt = document.getElementById('charCnt');
  if (ta && cnt) ta.addEventListener('input', () => { cnt.textContent = ta.value.length; });
})();

/* ── Toggle formulaire de modification ── */
document.querySelectorAll('.js-edit-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const ef = document.getElementById('edit-form-' + btn.dataset.id);
    if (!ef) return;
    const isOpen = ef.style.display === 'block';
    ef.style.display = isOpen ? 'none' : 'block';
    btn.setAttribute('aria-expanded', String(!isOpen));
  });
});

document.querySelectorAll('.js-cancel-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const ef = document.getElementById('edit-form-' + btn.dataset.id);
    if (ef) ef.style.display = 'none';
  });
});

/* ── Star picker formulaires de modification ── */
document.querySelectorAll('.ot-edit-form').forEach(form => {
  const stars = form.querySelectorAll('.sp-s');
  stars.forEach((s, i) => {
    s.addEventListener('mouseenter', () => stars.forEach((x, j) => x.classList.toggle('on', j <= i)));
    s.addEventListener('click',      () => stars.forEach((x, j) => x.classList.toggle('on', j <= i)));
    s.addEventListener('mouseleave', () => {
      const checked = form.querySelector('input[name="rating_edit"]:checked');
      if (checked) {
        const v = parseInt(checked.value, 10);
        stars.forEach((x, j) => x.classList.toggle('on', j < v));
      }
    });
  });
});
</script>
</body>
</html>