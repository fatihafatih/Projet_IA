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
  <!-- <link rel="stylesheet" href="../styles/outil.css"> -->
  <link rel="stylesheet" href="../styles/favoris.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    /* ════════════════════════════════════════════════════════════════════════════
   REFERENTIEL IA - NAVY & BUTTER PREMIUM INTERFACE (LUXURY TECH)
   ════════════════════════════════════════════════════════════════════════════ */

:root {
    --bg-main: #0B111E;            /* Fond ultra-sombre bleuté */
    --navy-deep: #1B2A4A;          /* Votre Bleu Marine Profond Structurel */
    --navy-card: #131E33;          /* Fond des cartes Bento */
    --navy-card-hover: #192640;    
    --butter-premium: #F3E5AB;     /* Votre Couleur Maîtresse d'Illumination */
    --butter-glow: rgba(243, 229, 171, 0.12);
    --butter-muted: #D1C493;       /* Beurre adouci pour les états secondaires */
    --text-pure: #FFFFFF;          
    --text-muted: #8FA0BC;         /* Gris bleuté technique pour la lisibilité */
    --border-line: rgba(243, 229, 171, 0.05); /* Bordures subtiles teintées beurre */
    --border-line-hover: rgba(243, 229, 171, 0.3);
    --shadow-glow: 0 0 35px rgba(243, 229, 171, 0.08);
    --radius-premium: 20px;
    --radius-sm: 10px;
}

/* ── REINITIALISATION & TYPOGRAPHIE ──────────────────────────────────────── */
body {
    background-color: var(--bg-main);
    color: var(--text-pure);
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    margin: 0;
    padding: 0;
    -webkit-font-smoothing: antialiased;
}

/* ── HERO ARCHITECTURE (BUTTER AMBIENT GLOW) ────────────────────────────── */
.ot-hero {
    position: relative;
    background: radial-gradient(120% 150% at 50% 0%, #151E30 0%, var(--bg-main) 100%);
    border-bottom: 1px solid var(--border-line);
    padding: 9rem 2rem 5rem 2rem;
    overflow: hidden;
}

/* Halo lumineux Beurre Premium en arrière-plan */
.ot-hero::before {
    content: '';
    position: absolute;
    top: -40%;
    left: 50%;
    transform: translateX(-50%);
    width: 900px;
    height: 450px;
    background: radial-gradient(50% 50% at 50% 50%, var(--butter-glow) 0%, transparent 100%);
    pointer-events: none;
}

.ot-hero-inner {
    max-width: 1300px;
    margin: 0 auto;
    position: relative;
    z-index: 2;
}

/* Breadcrumb */
.ot-breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 2.5rem;
}

.ot-breadcrumb a {
    color: var(--text-muted);
    text-decoration: none;
    transition: color 0.2s;
}

.ot-breadcrumb a:hover {
    color: var(--butter-premium);
}

.ot-bc-sep {
    opacity: 0.3;
}

/* Layout Hero Body */
.ot-hero-body {
    display: flex;
    align-items: flex-start;
    gap: 40px;
}

/* Logo avec structure Navy & liseré Or Beurre */
.ot-logo {
    width: 110px;
    height: 110px;
    background: var(--navy-deep);
    border: 1px solid rgba(243, 229, 171, 0.25);
    border-radius: var(--radius-premium);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: var(--shadow-glow);
    flex-shrink: 0;
    overflow: hidden;
}

.ot-logo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.ot-logo span {
    font-size: 2.2rem;
    font-weight: 800;
    color: var(--butter-premium);
    letter-spacing: -1px;
}

/* Badges */
.ot-hero-meta {
    display: flex;
    gap: 10px;
    margin-bottom: 1rem;
}

.ot-cat-pill {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.06);
    color: var(--text-muted);
    padding: 6px 16px;
    font-size: 11px;
    font-weight: 700;
    border-radius: 99px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.ot-version-pill {
    background: var(--butter-glow);
    border: 1px solid rgba(243, 229, 171, 0.2);
    color: var(--butter-premium);
    padding: 6px 14px;
    font-size: 11px;
    font-weight: 700;
    border-radius: 99px;
}

/* Titre */
.ot-title {
    font-size: 3.4rem;
    font-weight: 800;
    line-height: 1.1;
    letter-spacing: -0.03em;
    margin: 0 0 1rem 0;
    background: linear-gradient(180deg, #FFFFFF 0%, #D5E1F2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.ot-subtitle {
    font-size: 1.15rem;
    color: var(--text-muted);
    max-width: 850px;
    line-height: 1.6;
    margin-bottom: 2.5rem;
}

/* Actions & Notes */
.ot-hero-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}

.ot-score-big {
    display: flex;
    align-items: center;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid var(--border-line);
    padding: 10px 20px;
    border-radius: var(--radius-sm);
    gap: 6px;
}

.ot-star-big {
    color: var(--butter-premium);
    font-size: 1.2rem;
}

.ot-score-num {
    font-size: 1.4rem;
    font-weight: 800;
}

.ot-score-label {
    color: var(--text-muted);
    font-weight: 600;
    opacity: 0.5;
}

.ot-score-count {
    font-size: 13px;
    color: var(--text-muted);
    margin-left: 6px;
    border-left: 1px solid rgba(255, 255, 255, 0.1);
    padding-left: 12px;
}

/* Bouton Contrasté Signature Beurre */
.ot-btn-primary {
    background-color: var(--butter-premium) !important;
    color: var(--navy-deep) !important; /* Texte sombre pour un contraste maximal réglementaire */
    font-weight: 800;
    padding: 14px 28px;
    border-radius: var(--radius-sm);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 10px 25px rgba(243, 229, 171, 0.15);
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.ot-btn-primary:hover {
    transform: translateY(-2px);
    background-color: #FFF !important;
    box-shadow: 0 15px 30px rgba(243, 229, 171, 0.25);
}

.ot-btn-primary svg {
    width: 16px;
    height: 16px;
    stroke: currentColor;
    stroke-width: 2.5;
    fill: none;
}

.ot-btn-fav {
    background: var(--navy-deep) !important;
    border: 1px solid var(--border-line) !important;
    color: #FFFFFF !important;
    font-weight: 700;
    padding: 14px 24px;
    border-radius: var(--radius-sm);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: all 0.2s ease;
}

.ot-btn-fav:hover {
    background: var(--navy-card-hover) !important;
    border-color: rgba(243, 229, 171, 0.2) !important;
}

.ot-btn-fav svg {
    width: 18px;
    height: 18px;
}

.ot-btn-ghost {
    color: var(--text-muted) !important;
    text-decoration: none;
    font-weight: 700;
    font-size: 14px;
    padding: 14px 20px;
    transition: color 0.2s;
}

.ot-btn-ghost:hover {
    color: var(--butter-premium) !important;
}

/* ── STRUCTURE 2 COLONNES ASYMÉTRIQUES ───────────────────────────────────── */
.ot-page {
    max-width: 1340px;
    margin: 0 auto;
    padding: 4rem 2rem;
}

.ot-layout {
    display: grid;
    grid-template-columns: 1.3fr 0.7fr;
    gap: 40px;
    align-items: start;
}

/* Modules Bento Navy */
.ot-section {
    background: var(--navy-card);
    border: 1px solid var(--border-line);
    border-radius: var(--radius-premium);
    padding: 32px;
    margin-bottom: 32px;
}

.ot-section-title {
    font-size: 1.3rem;
    font-weight: 800;
    letter-spacing: -0.01em;
    margin: 0 0 24px 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.ot-section-icon {
    color: var(--butter-premium);
}

/* ── BLOCK PERFORMANCES GRID ────────────────────────────────────────────── */
.ot-perf-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
}

.ot-perf-card {
    background: rgba(11, 17, 30, 0.4);
    border: 1px solid var(--border-line);
    padding: 20px;
    border-radius: var(--radius-sm);
    transition: border-color 0.3s;
}

.ot-perf-card:hover {
    border-color: rgba(243, 229, 171, 0.2);
}

.ot-perf-top {
    display: flex;
    align-items: center;
    margin-bottom: 14px;
}

.ot-perf-icon {
    color: var(--butter-premium);
    margin-right: 8px;
}

.ot-perf-label {
    font-size: 13.5px;
    font-weight: 700;
    color: var(--text-muted);
    flex-grow: 1;
}

.ot-perf-val {
    font-size: 15px;
    font-weight: 800;
    color: var(--text-pure);
}

/* Barre de charge Beurre Lumineux */
.ot-bar-track {
    background: rgba(255, 255, 255, 0.05);
    height: 4px;
    border-radius: 99px;
    overflow: hidden;
}

.ot-bar-fill {
    background: var(--butter-premium);
    box-shadow: 0 0 10px rgba(243, 229, 171, 0.4);
    width: var(--pct);
    height: 100%;
    border-radius: 99px;
}

/* ── BENTO AVANTAGES / INCONVÉNIENTS ─────────────────────────────────────── */
.ot-pros-cons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

@media (max-width: 768px) {
    .ot-pros-cons { grid-template-columns: 1fr; }
}

.ot-pros, .ot-cons {
    background: rgba(0, 0, 0, 0.12);
    border: 1px solid var(--border-line);
    border-radius: var(--radius-sm);
    padding: 24px;
}

.ot-pc-head {
    font-weight: 800;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.ot-pc-head--pro { color: #10B981; }
.ot-pc-head--con { color: #F43F5E; }

.ot-pc-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.ot-pc-list li {
    font-size: 14px;
    font-weight: 600;
    line-height: 1.5;
    position: relative;
    padding-left: 16px;
    color: var(--text-muted);
}

.ot-pc-list li::before {
    content: '';
    position: absolute;
    left: 0;
    top: 8px;
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: var(--butter-premium);
    opacity: 0.4;
}

/* ── GRILLE DES MODELES ASSOCIES ─────────────────────────────────────────── */
.ot-model-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.ot-model-card {
    background: rgba(11, 17, 30, 0.3);
    border: 1px solid var(--border-line);
    border-radius: var(--radius-sm);
    padding: 20px;
    display: flex;
    flex-direction: column;
    height: 100%;
    box-sizing: border-box;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.ot-model-card:hover {
    background: var(--navy-card-hover);
    border-color: var(--border-line-hover);
    box-shadow: var(--shadow-glow);
    transform: translateY(-2px);
}

.card-top {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
}

.c-logo {
    width: 44px;
    height: 44px;
    background: var(--navy-deep);
    border: 1px solid var(--border-line);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 13px;
    color: var(--butter-premium);
    overflow: hidden;
    flex-shrink: 0;
}

.c-logo img { width: 100%; height: 100%; object-fit: cover; }

.c-name { font-weight: 800; font-size: 15px; color: var(--text-pure); }
.c-cat { font-size: 12px; font-weight: 600; color: var(--text-muted); }

.c-desc {
    font-size: 13.5px;
    line-height: 1.5;
    color: var(--text-muted);
    margin: 0 0 16px 0;
    flex-grow: 1;
}

.ot-tag-row {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 16px;
}

.ot-tag {
    background: rgba(255, 255, 255, 0.03);
    color: var(--text-pure);
    font-size: 11px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 4px;
    border: 1px solid var(--border-line);
}

.c-foot { margin-top: auto; text-align: right; }
.btn-see {
    color: var(--butter-premium);
    text-decoration: none;
    font-weight: 700;
    font-size: 13px;
    transition: color 0.2s;
}
.btn-see:hover { color: #FFFFFF; }

/* ── REVIEWS CRITICS SYSTEM ─────────────────────────────────────────────── */
.ot-avg-badge {
    background: var(--butter-glow);
    border: 1px solid rgba(243, 229, 171, 0.2);
    color: var(--butter-premium);
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 800;
    margin-left: auto;
}

/* Zone Formulaire */
.ot-review-form-wrap {
    background: rgba(0, 0, 0, 0.15);
    border: 1px solid var(--border-line);
    border-radius: var(--radius-sm);
    padding: 24px;
    margin-bottom: 32px;
}

.ot-review-form-title {
    font-size: 1.1rem;
    font-weight: 800;
    margin: 0 0 20px 0;
}

/* Sélecteur d'étoiles Beurre */
.ot-star-picker {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
    background: rgba(11, 17, 30, 0.4);
    padding: 12px 18px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border-line);
}

.ot-sp-label { font-size: 13.5px; font-weight: 700; color: var(--text-muted); }
.ot-stars-row { display: flex; gap: 4px; }

.ot-sp-star { cursor: pointer; }
.ot-sp-star input { display: none; }
.ot-sp-star svg {
    width: 24px;
    height: 24px;
    fill: rgba(255, 255, 255, 0.06);
    transition: transform 0.1s, fill 0.2s;
}

.ot-sp-star:hover svg { transform: scale(1.1); fill: var(--butter-premium); }
.ot-sp-star.is-active svg,
.ot-sp-star input:checked ~ svg {
    fill: var(--butter-premium);
}

.ot-sp-hint { font-size: 12px; font-weight: 600; color: var(--text-muted); }

/* Inputs Textarea */
.ot-form-group { position: relative; margin-bottom: 20px; }
.ot-form-label { display: block; font-size: 13.5px; font-weight: 700; margin-bottom: 8px; color: var(--text-muted); }

.ot-form-textarea {
    width: 100%;
    box-sizing: border-box;
    background: rgba(11, 17, 30, 0.4);
    border: 1px solid var(--border-line);
    border-radius: 8px;
    padding: 14px;
    font-family: inherit;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-pure);
    resize: vertical;
    outline: none;
    transition: border-color 0.2s;
}

.ot-form-textarea:focus { border-color: var(--butter-premium); }
.ot-char-count { font-size: 11px; color: var(--text-muted); position: absolute; bottom: -18px; right: 0; font-weight: 600; }

.ot-btn-submit {
    background: var(--butter-premium);
    color: var(--navy-deep);
    border: none;
    padding: 12px 24px;
    border-radius: 6px;
    font-weight: 800;
    font-size: 14px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background-color 0.2s;
}
.ot-btn-submit:hover { background-color: #FFFFFF; }

/* Flux d'avis */
.ot-reviews { display: flex; flex-direction: column; gap: 16px; margin-top: 24px; }
.ot-review-card { border-bottom: 1px solid var(--border-line); padding-bottom: 20px; }
.ot-review-card:last-child { border: none; padding-bottom: 0; }

.ot-rev-header { display: flex; align-items: center; gap: 14px; margin-bottom: 12px; }

.ot-rev-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--navy-deep);
    color: var(--butter-premium);
    border: 1px solid var(--border-line);
    font-weight: 800;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.ot-rev-meta { flex-grow: 1; }
.ot-rev-name { font-weight: 800; font-size: 14.5px; display: block; margin-bottom: 2px; }

.ot-rev-stars { display: flex; align-items: center; gap: 2px; font-size: 13px; }
.ot-star-on { color: var(--butter-premium); }
.ot-star-off { color: rgba(255, 255, 255, 0.06); }

.ot-rev-comment {
    font-size: 14px;
    line-height: 1.5;
    font-weight: 600;
    color: var(--text-muted);
    margin: 0;
    padding-left: 54px;
}

/* Modérateurs avis */
.ot-rev-actions { display: flex; gap: 4px; }
.ot-rev-btn {
    background: transparent;
    border: none;
    cursor: pointer;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}
.ot-rev-btn--edit { color: var(--text-muted); }
.ot-rev-btn--edit:hover { background: rgba(255, 255, 255, 0.04); color: #FFF; }
.ot-rev-btn--delete { color: #F43F5E; }
.ot-rev-btn--delete:hover { background: rgba(244, 63, 94, 0.1); }

/* ── SIDEBAR CARDS (NAVY HOOD) ──────────────────────────────────────────── */
.ot-col-side {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.ot-side-card {
    background: var(--navy-card);
    border: 1px solid var(--border-line);
    border-radius: var(--radius-premium);
    padding: 24px;
}

.ot-side-title {
    font-size: 13px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-pure);
    margin: 0 0 20px 0;
    border-bottom: 1px solid var(--border-line);
    padding-bottom: 12px;
}

/* Listes d'infos clés */
.ot-info-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.ot-info-list li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 14px;
}

.ot-info-label { font-weight: 600; color: var(--text-muted); }
.ot-info-val { font-weight: 800; color: var(--text-pure); }
.ot-star-inline { color: var(--butter-premium); }

/* Caractéristiques Pills Group */
.ot-car-list { display: flex; flex-wrap: wrap; gap: 8px; }
.ot-car-pill {
    background: rgba(0, 0, 0, 0.15);
    border: 1px solid var(--border-line);
    color: var(--text-pure);
    padding: 8px 14px;
    font-size: 12px;
    font-weight: 700;
    border-radius: 6px;
}

/* Accès Liens Externes */
.ot-dispo-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.ot-dispo-list li {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: rgba(0, 0, 0, 0.15);
    padding: 12px;
    border-radius: 6px;
    border: 1px solid var(--border-line);
}

.ot-dispo-type { font-size: 11px; font-weight: 800; text-transform: uppercase; color: var(--text-muted); }

.ot-dispo-url {
    font-size: 13.5px;
    font-weight: 700;
    color: var(--text-pure);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: color 0.2s;
}
.ot-dispo-url:hover { color: var(--butter-premium); }
.ot-dispo-url svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2.5; }

/* ── COMPATIBILITÉ MOBILE ────────────────────────────────────────────────── */
@media (max-width: 992px) {
    .ot-layout { grid-template-columns: 1fr; }
    .ot-hero-body { flex-direction: column; gap: 20px; }
    .ot-title { font-size: 2.5rem; }
    .ot-hero { padding: 8rem 1.5rem 4rem 1.5rem; }
    .ot-page { padding: 2rem 1.5rem; }
}
  </style>
</head>

<!-- ══ MODAL COLLECTIONS ══════════════════════════════════════════════════ -->
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

          <!-- Score + CTA + ajout favoris -->
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

            <!-- ❤ Bouton favori -->
            <?php if (isset($_SESSION['user_id'])): ?>
              <button class="ot-btn-fav js-fav-btn" data-id="<?= $outil['ID_OUTILS_IA'] ?>"
                title="Sauvegarder dans une collection">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path
                    d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
                </svg>
                Sauvegarder
              </button>
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