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
$avantages    = array_filter($ais, fn($r) => $r['type'] === 'avantage');
$inconvenients= array_filter($ais, fn($r) => $r['type'] === 'inconvenient');
 $imagePath = !empty($_SESSION['image']) ? "/Projet_IA/php/uploads/avatars/" . htmlspecialchars($_SESSION['image']) : '';

$stmt = $pdo->prepare("
    SELECT
        r.*,
        u.nom AS user_nom,
        u.image AS user_image
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
   RÉFÉRENTIEL IA — PREMIUM INTERFACE  Navy #1B2A4A + #192640
   Palette : Navy profond, Ivoire chaud, accents or/ambre
═══════════════════════════════════════════════════════════════ */
:root{
  --navy:       #1B2A4A;
  --navy-deep:  #192640;
  --navy-hover: #243658;
  --navy-card:  #1E3050;
  --butter:     #D4AA60;
  --butter-l:   #F5EDD8;
  --butter-b:   #E8C97A;
  --ivory:      #FAFAF7;
  --white:      #FFFFFF;
  --surface:    #F2F4F8;
  --muted:      #6B7A99;
  --border:     #DDE2EE;
  --border-d:   rgba(255,255,255,.08);
  --text:       #1A2340;
  --green:      #0F7A4E;
  --red:        #C0392B;
  --r20:20px; --r14:14px; --r8:8px;
}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--ivory);color:var(--text);font-family:'Plus Jakarta Sans',sans-serif;-webkit-font-smoothing:antialiased}


/* ──── HERO BAND ───────────────────────────────── */
.hero{background:var(--navy-deep);padding:52px 48px 0}
.hero-inner{max-width:1300px;margin:0 auto}

.bc{display:flex;align-items:center;gap:7px;margin-bottom:32px}
.bc a{font-size:12px;font-weight:600;color:rgba(255,255,255,.45);text-decoration:none;transition:color .2s}
.bc a:hover{color:rgba(255,255,255,.85)}
.bc-sep{font-size:11px;color:rgba(255,255,255,.2)}
.bc-cur{font-size:12px;font-weight:700;color:rgba(255,255,255,.75)}

/* Hero 3-col grid */
.hero-grid{display:grid;grid-template-columns:108px 1fr 210px;gap:36px;align-items:start;padding-bottom:44px}

/* Logo */
.h-logo{
  width:108px;height:108px;
  background:var(--navy-card);
  border:1px solid rgba(212,170,96,.3);
  border-radius:22px;
  display:flex;align-items:center;justify-content:center;
  font-weight:800;font-size:24px;color:var(--butter);
  overflow:hidden;flex-shrink:0;
}
.h-logo img{width:100%;height:100%;object-fit:cover}

/* Infos centre */
.h-pills{display:flex;align-items:center;gap:8px;margin-bottom:14px;flex-wrap:wrap}
.pill{padding:4px 12px;font-size:11px;font-weight:700;border-radius:99px;letter-spacing:.04em}
.pill-cat{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);color:rgba(255,255,255,.7)}
.pill-ver{background:rgba(212,170,96,.15);border:1px solid rgba(212,170,96,.3);color:var(--butter)}
.pill-live{background:rgba(15,122,78,.2);border:1px solid rgba(15,122,78,.35);color:#4BD49A;display:flex;align-items:center;gap:5px}
.pill-live::before{content:'';width:5px;height:5px;background:#4BD49A;border-radius:50%;animation:blink 2s infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.25}}

.h-title{font-size:48px;font-weight:800;letter-spacing:-.04em;line-height:1;color:#fff;margin-bottom:12px}
.h-sub{font-size:14.5px;color:rgba(255,255,255,.55);max-width:640px;line-height:1.65;font-weight:500;margin-bottom:26px}

.h-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.score-chip{display:inline-flex;align-items:center;gap:10px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);padding:9px 15px;border-radius:10px}
.sc-star{color:var(--butter);font-size:17px}
.sc-num{font-size:22px;font-weight:800;color:#fff;letter-spacing:-.03em}
.sc-denom{font-size:12px;color:rgba(255,255,255,.4);font-weight:600}
.sc-sep{width:1px;height:24px;background:rgba(255,255,255,.1)}
.sc-rev{font-size:11.5px;color:rgba(255,255,255,.45);font-weight:600;line-height:1.4}
.sc-rev span{display:block;font-size:16px;font-weight:800;color:#fff}

.btn-butter{background:var(--butter);color:var(--navy);font-weight:800;font-size:13.5px;padding:11px 22px;border-radius:var(--r8);text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:all .2s;border:none;cursor:pointer}
.btn-butter:hover{background:#fff}
.btn-outline{background:transparent;border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.8);font-weight:700;font-size:13.5px;padding:11px 18px;border-radius:var(--r8);display:inline-flex;align-items:center;gap:7px;text-decoration:none;transition:all .2s;cursor:pointer}
.btn-outline:hover{border-color:rgba(255,255,255,.35);background:rgba(255,255,255,.06);color:#fff}
.btn-ghost-white{color:rgba(255,255,255,.45);font-weight:600;font-size:13px;padding:11px 12px;text-decoration:none;transition:color .2s;display:inline-flex;align-items:center;gap:5px}
.btn-ghost-white:hover{color:rgba(255,255,255,.85)}

/* Stats side hero */
.h-stats{display:flex;flex-direction:column;gap:10px}
.hst{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);border-radius:12px;padding:14px 16px}
.hst-label{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:rgba(53, 71, 184, 0.35);margin-bottom:5px}
.hst-val{font-size:20px;font-weight:800;color: #31306c;letter-spacing:-.02em}
.hst-sub{font-size:11px;color:rgba(255,255,255,.35);font-weight:600;margin-top:2px}
.hst-up{color: #4BD49A}

/* ──── CONTENT BELT (white wave) ─────────────── */
.belt{background:var(--ivory);padding:40px 48px 64px}
.belt-inner{max-width:1300px;margin:0 auto}
.main-grid{display:grid;grid-template-columns:1fr 300px;gap:28px;align-items:start}

/* ──── SECTION CARDS ──────────────────────────── */
.sc{background:var(--white);border:1px solid var(--border);border-radius:var(--r20);padding:28px;margin-bottom:22px}
.sc-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}
.sc-title{font-size:15.5px;font-weight:800;color:var(--navy);display:flex;align-items:center;gap:10px}
.sc-icon{width:30px;height:30px;background:var(--navy);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.sc-badge{font-size:11.5px;color:var(--muted);font-weight:600;background:var(--surface);padding:4px 10px;border-radius:5px}

/* ──── PERFORMANCES ───────────────────────────── */
.perf-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(175px,1fr));gap:12px}
.pc{background:var(--ivory);border:1px solid var(--border);border-radius:var(--r14);padding:18px;transition:all .2s}
.pc:hover{border-color:var(--butter-b);background:var(--butter-l);transform:translateY(-1px)}
.pc-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.pc-label{font-size:12px;font-weight:700;color:var(--muted)}
.pc-val{font-size:21px;font-weight:800;color:var(--navy)}
.pc-icon{font-size:15px;margin-right:6px}
.bar{background:#E4E8F0;height:3px;border-radius:99px;overflow:hidden;margin-top:2px}
.bar-f{background:linear-gradient(90deg,var(--butter),#E8A830);width:var(--pct);height:100%;border-radius:99px}

/* ──── AVANTAGES / INCONVÉNIENTS ─────────────── */
.pc-wrap{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.pcb{border-radius:var(--r14);padding:20px;border:1px solid}
.pcb-pro{background:#F0FBF6;border-color:#A3DCC0}
.pcb-con{background:#FFF3F2;border-color:#F5C0BA}
.pcb-head{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.07em;margin-bottom:14px;display:flex;align-items:center;gap:7px}
.pcb-head-pro{color:#0A6E45}
.pcb-head-con{color:#A8291E}
.pcb-items{display:flex;flex-direction:column;gap:9px}
.pcb-item{display:flex;align-items:flex-start;gap:9px;font-size:13px;font-weight:500;color:#3D4F6E;line-height:1.5}
.pcb-dot{width:5px;height:5px;border-radius:50%;flex-shrink:0;margin-top:6px}
.dot-pro{background:#0A6E45}.dot-con{background:#A8291E}

/* ──── MODÈLES ────────────────────────────────── */
.mg{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:14px}
.mc{background:var(--ivory);border:1px solid var(--border);border-radius:var(--r14);padding:20px;display:flex;flex-direction:column;transition:all .25s}
.mc:hover{border-color:var(--butter-b);background:var(--butter-l);transform:translateY(-2px)}
.mc-top{display:flex;align-items:center;gap:11px;margin-bottom:12px}
.mc-logo{width:40px;height:40px;background:var(--navy);border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;color:var(--butter);flex-shrink:0;overflow:hidden}
.mc-logo img{width:100%;height:100%;object-fit:cover}
.mc-name{font-size:14px;font-weight:800;color:var(--navy);margin-bottom:2px}
.mc-prov{font-size:11.5px;color:var(--muted);font-weight:600}
.mc-desc{font-size:12.5px;color:var(--muted);line-height:1.55;font-weight:500;flex-grow:1;margin-bottom:12px}
.mc-tags{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:12px}
.mc-tag{background:rgba(27,42,74,.06);border:1px solid rgba(27,42,74,.1);color:var(--navy);font-size:10.5px;font-weight:700;padding:3px 8px;border-radius:4px}
.mc-link{color:var(--butter);font-size:12px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:4px;align-self:flex-end;transition:color .2s}
.mc-link:hover{color:var(--navy)}

/* ──── AVIS ───────────────────────────────────── */
.rev-badge{background:var(--butter-l);border:1px solid var(--butter-b);color:#9A7020;padding:4px 12px;border-radius:7px;font-size:13px;font-weight:800}
.rev-form{background:var(--surface);border:1px solid var(--border);border-radius:var(--r14);padding:22px;margin-bottom:22px}
.rev-form-title{font-size:14px;font-weight:800;color:var(--navy);margin-bottom:16px}
.sp{display:flex;align-items:center;gap:11px;background:var(--white);border:1px solid var(--border);padding:10px 14px;border-radius:var(--r8);margin-bottom:14px}
.sp-label{font-size:12.5px;font-weight:700;color:var(--muted)}
.sp-stars{display:flex;gap:2px}
.sp-s{cursor:pointer;font-size:22px;color:#D5DAEA;transition:all .15s;user-select:none}
.sp-s:hover,.sp-s.on{color:var(--butter)}
.sp-hint{font-size:11.5px;color:var(--muted);font-weight:600;margin-left:auto}
.form-label{display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:7px;text-transform:uppercase;letter-spacing:.05em}
.form-ta{width:100%;background:var(--white);border:1px solid var(--border);border-radius:var(--r8);padding:12px;font-family:inherit;font-size:13.5px;color:var(--text);resize:vertical;outline:none;transition:border-color .2s;min-height:80px}
.form-ta:focus{border-color:var(--butter-b)}
.char-c{display:block;font-size:11px;color:var(--muted);text-align:right;margin-top:4px;font-weight:600}
.btn-submit{background:var(--navy);color:#fff;border:none;padding:10px 20px;border-radius:var(--r8);font-weight:800;font-size:13.5px;cursor:pointer;display:inline-flex;align-items:center;gap:7px;transition:background .2s;margin-top:12px}
.btn-submit:hover{background:var(--navy-hover)}

.rev-list{display:flex;flex-direction:column}
.rev-card{padding:16px 0;border-bottom:1px solid var(--border)}
.rev-card:last-child{border:none}
.rev-hdr{display:flex;align-items:center;gap:10px;margin-bottom:8px}
.rev-av{width:36px;height:36px;border-radius:50%;background:var(--navy);font-weight:800;font-size:12.5px;color:var(--butter);display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden}
.rev-av img{width:100%;height:100%;object-fit:cover}
.rev-name{font-size:13.5px;font-weight:800;color:var(--navy);display:block;margin-bottom:2px}
.rev-stars{display:flex;align-items:center;gap:2px;font-size:12px}
.son{color:var(--butter)}.soff{color:#D5DAEA}
.rev-score{font-size:11px;color:var(--muted);margin-left:5px;font-weight:700}
.rev-actions{display:flex;gap:3px;margin-left:auto}
.rev-btn{background:transparent;border:none;cursor:pointer;width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;transition:background .2s;font-size:12.5px}
.rev-btn-e{color:var(--muted)}.rev-btn-e:hover{background:var(--surface);color:var(--navy)}
.rev-btn-d{color:var(--red)}.rev-btn-d:hover{background:#FFF0EE}
.rev-comment{font-size:13px;color:var(--muted);line-height:1.6;font-weight:500;padding-left:46px;font-style:italic}
.empty-state{text-align:center;padding:40px;color:var(--muted)}

/* ──── SIDEBAR ─────────────────────────────────── */
.side{display:flex;flex-direction:column;gap:16px}

/* Carte navy sombre pour la sidebar principale */
.scard-dark{background:var(--navy-deep);border:1px solid rgba(255,255,255,.06);border-radius:var(--r20);overflow:hidden}
.scard-dark-head{background:var(--navy);padding:16px 20px;border-bottom:1px solid rgba(255,255,255,.06)}
.scard-dark-title{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.4)}
.scard-dark-body{padding:18px 20px;display:flex;flex-direction:column;gap:12px}
.sdi{display:flex;align-items:center;justify-content:space-between}
.sdk{font-size:13px;font-weight:600;color:rgba(255,255,255,.45)}
.sdv{font-size:13px;font-weight:800;color:#fff}
.sdv-gold{color:var(--butter)}

/* Carte CTA navy */
.scard-cta{background:var(--navy-card);border:1px solid rgba(212,170,96,.2);border-radius:var(--r20);padding:20px;text-align:center}
.scard-cta p{font-size:12.5px;color:rgba(255,255,255,.5);font-weight:500;line-height:1.55;margin-bottom:14px}
.scard-cta a{display:block;background:var(--butter);color:var(--navy);font-weight:800;font-size:13.5px;padding:11px;border-radius:8px;text-decoration:none;transition:all .2s}
.scard-cta a:hover{background:#fff}

/* Carte claire pour carac / dispos */
.scard{background:var(--white);border:1px solid var(--border);border-radius:var(--r20);padding:22px}
.stitle{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);padding-bottom:11px;border-bottom:1px solid var(--border);margin-bottom:14px}
.car-pills{display:flex;flex-wrap:wrap;gap:7px}
.car-pill{background:var(--navy-deep);color:rgba(255,255,255,.8);padding:6px 12px;font-size:11.5px;font-weight:700;border-radius:6px;border:1px solid rgba(255,255,255,.05);cursor:default;transition:all .2s}
.car-pill:hover{background:var(--navy-hover);color:#fff}

.dispo-list{display:flex;flex-direction:column;gap:9px;list-style:none;padding:0}
.dispo-item{background:var(--surface);border:1px solid var(--border);border-radius:var(--r8);padding:11px 13px;display:flex;align-items:center;justify-content:space-between;transition:all .2s}
.dispo-item:hover{border-color:var(--butter-b);background:var(--butter-l)}
.dispo-type{font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)}
.dispo-link{font-size:12.5px;font-weight:700;color:var(--navy);text-decoration:none;display:flex;align-items:center;gap:5px;transition:color .2s}
.dispo-link:hover{color:var(--butter)}

/* Edit form */
.ot-edit-form{display:none;margin-top:12px;padding-top:12px;border-top:1px solid var(--border)}
.ot-edit-actions{display:flex;align-items:center;gap:10px;margin-top:10px}

/* Alerts */
.alert{padding:11px 15px;border-radius:var(--r8);font-size:13.5px;font-weight:600;margin-bottom:14px}
.alert-err{background:#FFF0EE;border:1px solid #F5C0BA;color:#A8291E}
.alert-ok{background:#F0FBF6;border:1px solid #A3DCC0;color:#0A6E45}

/* ──── FOOTER ─────────────────────────────────── */
.ft{background:var(--navy-deep);border-top:1px solid rgba(255,255,255,.06);padding:26px 48px;display:flex;align-items:center;justify-content:space-between}
.ft-left{font-size:13px;color:rgba(255,255,255,.35);font-weight:600}
.ft-left span{color:var(--butter);font-weight:800}
.ft-links{display:flex;gap:22px}
.ft-links a{font-size:12.5px;color:rgba(255,255,255,.35);text-decoration:none;font-weight:600;transition:color .2s}
.ft-links a:hover{color:rgba(255,255,255,.8)}

/* ──── RESPONSIVE ─────────────────────────────── */
@media(max-width:1100px){
  .main-grid{grid-template-columns:1fr}
  .hero-grid{grid-template-columns:108px 1fr}
  .h-stats{display:none}
}
@media(max-width:768px){
  .nb,.hero,.belt,.ft{padding-left:20px;padding-right:20px}
  .hero-grid{grid-template-columns:1fr}
  .h-logo{width:80px;height:80px;font-size:18px}
  .h-title{font-size:34px}
  .pc-wrap,.mg,.perf-grid{grid-template-columns:1fr}
  .h-actions{flex-wrap:wrap}
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



<!-- ══ HERO NAVY ════════════════════════════════════════════════════════ -->
<div class="hero">
  <div class="hero-inner">

    <nav class="bc">
      <a href="dashboard.php">Accueil</a>
      <span class="bc-sep">›</span>
      <a href="dashboard.php"><?= htmlspecialchars($outil['categorie'] ?? 'Outils') ?></a>
      <span class="bc-sep">›</span>
      <span class="bc-cur"><?= htmlspecialchars($outil['nom']) ?></span>
    </nav>

    <div class="hero-grid">

      <!-- Logo outil -->
      <div class="h-logo">
        <?php if ($outil['logo_url']): ?>
          <img src="<?= htmlspecialchars($outil['logo_url']) ?>" alt="<?= htmlspecialchars($outil['nom']) ?>">
        <?php else: ?>
          <?= strtoupper(substr($outil['nom'], 0, 2)) ?>
        <?php endif; ?>
      </div>

      <!-- Centre : titre + actions -->
      <div>
        <div class="h-pills">
          <span class="pill pill-cat"><?= htmlspecialchars($outil['categorie'] ?? 'Non classé') ?></span>
          <?php if ($outil['version']): ?>
            <span class="pill pill-ver">v<?= number_format($outil['version'], 1) ?></span>
          <?php endif; ?>
          <span class="pill pill-live">Actif</span>
        </div>

        <h1 class="h-title"><?= htmlspecialchars($outil['nom']) ?></h1>
        <p class="h-sub"><?= htmlspecialchars($outil['description'] ?? '') ?></p>

        <div class="h-actions">
          <div class="score-chip">
            <span class="sc-star">★</span>
            <span class="sc-num"><?= number_format($outil['global_rating'], 1) ?></span>
            <span class="sc-denom">/5</span>
            <?php if (count($reviews)): ?>
              <div class="sc-sep"></div>
              <div class="sc-rev"><span><?= count($reviews) ?></span>avis</div>
            <?php endif; ?>
          </div>

          <?php if ($outil['url']): ?>
            <a class="btn-butter" href="<?= htmlspecialchars($outil['url']) ?>" target="_blank" rel="noopener">
              <i class="bi bi-box-arrow-up-right"></i> Visiter le site
            </a>
          <?php endif; ?>

          <?php if (isset($_SESSION['user_id'])): ?>
            <button class="btn-outline js-fav-btn" data-id="<?= $outil['ID_OUTILS_IA'] ?>">
              <i class="bi bi-heart"></i> Sauvegarder
            </button>
          <?php endif; ?>

          <a class="btn-ghost-white" href="dashboard.php">
            <i class="bi bi-arrow-left"></i> Retour
          </a>
        </div>
      </div>

      <!-- Stats latérales -->
      <div class="h-stats">
        <div class="hst">
          <div class="hst-label">Note globale</div>
          <div class="hst-val" style="color:var(--butter)">★ <?= number_format($outil['global_rating'], 1) ?></div>
          <div class="hst-sub">sur 5</div>
        </div>
        <?php if (count($reviews)): ?>
        <div class="hst">
          <div class="hst-label">Avis</div>
          <div class="hst-val"><?= count($reviews) ?></div>
          <div class="hst-sub">utilisateurs</div>
        </div>
        <?php endif; ?>
        <?php if ($perf && $perf['nb_evals'] > 0): ?>
        <div class="hst">
          <div class="hst-label">Évaluations</div>
          <div class="hst-val"><?= $perf['nb_evals'] ?></div>
          <div class="hst-sub hst-up">↑ Performances</div>
        </div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<!-- ══ CONTENU PRINCIPAL ════════════════════════════════════════════════ -->
<div class="belt">
  <div class="belt-inner">
    <div class="main-grid">

      <!-- ─── Colonne principale ───────────────────────────── -->
      <div>

        <!-- PERFORMANCES -->
        <?php if ($perf && $perf['nb_evals'] > 0): ?>
        <div class="sc">
          <div class="sc-head">
            <div class="sc-title"><div class="sc-icon">📊</div> Performances</div>
            <span class="sc-badge"><?= $perf['nb_evals'] ?> évaluations</span>
          </div>
          <div class="perf-grid">
            <?php
            $metrics = [
              ['label'=>'Rapidité',     'val'=>$perf['rapidite'],    'icon'=>'⚡'],
              ['label'=>'Qualité',      'val'=>$perf['qualite'],     'icon'=>'✨'],
              ['label'=>'Crédibilité',  'val'=>$perf['credibilite'], 'icon'=>'🛡️'],
              ['label'=>'Score global', 'val'=>$perf['score_global'],'icon'=>'🏆'],
            ];
            if ($perf['qualite_image'] > 0)
              $metrics[] = ['label'=>'Qualité image','val'=>$perf['qualite_image'],'icon'=>'🎨'];
            foreach ($metrics as $m):
              if (!$m['val']) continue;
              $pct = round(($m['val']/5)*100);
            ?>
            <div class="pc">
              <div class="pc-top">
                <div style="display:flex;align-items:center">
                  <span class="pc-icon"><?= $m['icon'] ?></span>
                  <span class="pc-label"><?= $m['label'] ?></span>
                </div>
                <span class="pc-val"><?= number_format($m['val'],1) ?></span>
              </div>
              <div class="bar"><div class="bar-f" style="--pct:<?= $pct ?>%"></div></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- AVANTAGES / INCONVÉNIENTS -->
        <?php if ($avantages || $inconvenients): ?>
        <div class="sc">
          <div class="sc-head">
            <div class="sc-title"><div class="sc-icon">⚖️</div> Avantages & Inconvénients</div>
          </div>
          <div class="pc-wrap">
            <?php if ($avantages): ?>
            <div class="pcb pcb-pro">
              <div class="pcb-head pcb-head-pro"><i class="bi bi-check-circle-fill"></i> Avantages</div>
              <div class="pcb-items">
                <?php foreach ($avantages as $a): ?>
                <div class="pcb-item"><div class="pcb-dot dot-pro"></div><?= htmlspecialchars($a['description']) ?></div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>
            <?php if ($inconvenients): ?>
            <div class="pcb pcb-con">
              <div class="pcb-head pcb-head-con"><i class="bi bi-x-circle-fill"></i> Inconvénients</div>
              <div class="pcb-items">
                <?php foreach ($inconvenients as $i): ?>
                <div class="pcb-item"><div class="pcb-dot dot-con"></div><?= htmlspecialchars($i['description']) ?></div>
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
            <div class="sc-title"><div class="sc-icon">🤖</div> Modèles utilisés</div>
            <span class="sc-badge"><?= count($modeles) ?> modèle<?= count($modeles)>1?'s':'' ?></span>
          </div>
          <div class="mg">
            <?php foreach ($modeles as $mod):
              $tags = array_filter(explode(',', $mod['tags'] ?? ''));
            ?>
            <div class="mc">
              <div class="mc-top">
                <div class="mc-logo">
                  <?php if ($mod['provider_logo']): ?>
                    <img src="<?= htmlspecialchars($mod['provider_logo']) ?>" alt="">
                  <?php else: ?>
                    <?= strtoupper(substr($mod['name'],0,2)) ?>
                  <?php endif; ?>
                </div>
                <div>
                  <div class="mc-name"><?= htmlspecialchars($mod['name']) ?></div>
                  <div class="mc-prov"><?= htmlspecialchars($mod['provider_name'] ?? 'Inconnu') ?></div>
                </div>
              </div>
              <p class="mc-desc"><?= htmlspecialchars($mod['description'] ?? 'Aucune description.') ?></p>
              <?php if ($tags): ?>
              <div class="mc-tags">
                <?php foreach ($tags as $t): ?>
                  <span class="mc-tag"><?= htmlspecialchars(trim($t)) ?></span>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
              <a class="mc-link" href="modele.php?id=<?= $mod['ID_MODEL'] ?>">Voir le modèle <i class="bi bi-arrow-right"></i></a>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- AVIS UTILISATEURS -->
        <div class="sc" id="avis">
          <div class="sc-head">
            <div class="sc-title"><div class="sc-icon">💬</div> Avis utilisateurs</div>
            <?php if ($avg_review): ?>
              <span class="rev-badge">★ <?= $avg_review ?></span>
            <?php endif; ?>
          </div>

          <!-- Formulaire -->
          <?php if (isset($_SESSION['user_id'])): ?>
          <div class="rev-form">
            <div class="rev-form-title">Laisser un avis</div>
            <?php if ($review_error): ?>
              <div class="alert alert-err"><?= htmlspecialchars($review_error) ?></div>
            <?php endif; ?>
            <form method="POST" action="outil.php?id=<?= $id ?>#avis">
              <div class="sp">
                <span class="sp-label">Votre note</span>
                <div class="sp-stars" id="spStars">
                  <?php for ($s=1;$s<=5;$s++): ?>
                  <label class="sp-s" data-v="<?= $s ?>" for="star<?= $s ?>">
                    <input type="radio" name="rating" id="star<?= $s ?>" value="<?= $s ?>" required style="display:none">★
                  </label>
                  <?php endfor; ?>
                </div>
                <span class="sp-hint" id="spHint">Cliquez pour noter</span>
              </div>
              <div style="position:relative">
                <label class="form-label">Commentaire <span style="opacity:.5;text-transform:none;font-size:11px">(optionnel)</span></label>
                <textarea class="form-ta" name="comment" id="reviewTa" rows="3" maxlength="1000" placeholder="Partagez votre expérience…"><?= htmlspecialchars($_POST['comment'] ?? '') ?></textarea>
                <span class="char-c"><span id="charCnt">0</span>/1000</span>
              </div>
              <button type="submit" class="btn-submit"><i class="bi bi-send-fill"></i> Publier mon avis</button>
            </form>
          </div>
          <?php endif; ?>

          <!-- Liste des avis -->
          <?php if ($reviews): ?>

          <div class="rev-list">
            
            <?php foreach ($reviews as $rev):
            
              $stars  = round($rev['rating']);
              $is_own = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $rev['ID_USERS'];
            ?>
            <div class="rev-card" id="review-<?= $rev['ID_REVIEW'] ?>">
              <div class="rev-hdr">
               <div class="rev-av">

<?php
$avatarWebPath = "/Projet_IA/php/uploads/avatars/" . $rev['user_image'];

$avatarServerPath =
$_SERVER['DOCUMENT_ROOT']
. "/Projet_IA/php/uploads/avatars/"
. $rev['user_image'];
?>

<?php if (
    !empty($rev['user_image'])
    && file_exists($avatarServerPath)
): ?>

<img
    src="<?= htmlspecialchars($avatarWebPath) ?>"
    alt="<?= htmlspecialchars($rev['user_nom']) ?>">

<?php else: ?>

<?= strtoupper(substr($rev['user_nom'],0,1)) ?>

<?php endif; ?>

</div>
                <div style="flex-grow:1">
                  <span class="rev-name"><?= htmlspecialchars($rev['user_nom']) ?></span>
                  <div class="rev-stars">
                    <?php for ($s=1;$s<=5;$s++): ?>
                      <span class="<?= $s<=$stars?'son':'soff' ?>">★</span>
                    <?php endfor; ?>
                    <span class="rev-score"><?= number_format($rev['rating'],1) ?></span>
                  </div>
                </div>
                <?php if ($is_own): ?>
                <div class="rev-actions">
                  <button class="rev-btn rev-btn-e js-edit-btn" data-id="<?= $rev['ID_REVIEW'] ?>" title="Modifier"><i class="bi bi-pencil-fill"></i></button>
                  <form method="POST" action="outil.php?id=<?= $id ?>" style="display:inline" onsubmit="return confirm('Supprimer cet avis ?')">
                    <input type="hidden" name="delete_review" value="<?= $rev['ID_REVIEW'] ?>">
                    <button type="submit" class="rev-btn rev-btn-d" title="Supprimer"><i class="bi bi-trash-fill"></i></button>
                  </form>
                </div>
                <?php endif; ?>
              </div>

              <?php if ($rev['comment']): ?>
                <p class="rev-comment" id="comment-text-<?= $rev['ID_REVIEW'] ?>"><?= htmlspecialchars($rev['comment']) ?></p>
              <?php endif; ?>

              <?php if ($is_own): ?>
              <div class="ot-edit-form" id="edit-form-<?= $rev['ID_REVIEW'] ?>">
                <form method="POST" action="outil.php?id=<?= $id ?>">
                  <input type="hidden" name="edit_review" value="<?= $rev['ID_REVIEW'] ?>">
                  <div class="sp" style="margin-bottom:12px">
                    <span class="sp-label">Modifier</span>
                    <div class="sp-stars">
                      <?php for ($s=1;$s<=5;$s++): ?>
                      <label class="sp-s <?= $s<=round($rev['rating'])?'on':'' ?>" for="edit-star-<?= $rev['ID_REVIEW'] ?>-<?= $s ?>">
                        <input type="radio" name="rating_edit" id="edit-star-<?= $rev['ID_REVIEW'] ?>-<?= $s ?>" value="<?= $s ?>" <?= $s==round($rev['rating'])?'checked':'' ?> style="display:none">★
                      </label>
                      <?php endfor; ?>
                    </div>
                  </div>
                  <textarea class="form-ta" name="comment_edit" rows="3" placeholder="Modifier votre commentaire…"><?= htmlspecialchars($rev['comment'] ?? '') ?></textarea>
                  <div class="ot-edit-actions">
                    <button type="submit" class="btn-submit"><i class="bi bi-check-lg"></i> Enregistrer</button>
                    <button type="button" class="btn-ghost-white js-cancel-btn" data-id="<?= $rev['ID_REVIEW'] ?>" style="color:var(--muted);font-size:13px;font-weight:600;background:none;border:none;cursor:pointer">Annuler</button>
                  </div>
                </form>
              </div>
              <?php endif; ?>

            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <div class="empty-state">
            <span style="font-size:36px;display:block;margin-bottom:10px;opacity:.3">💭</span>
            <p style="font-size:14px;font-weight:600">Aucun avis pour le moment. Soyez le premier !</p>
          </div>
          <?php endif; ?>
        </div>

      </div><!-- /col-main -->

      <!-- ─── Colonne sidebar ──────────────────────────────── -->
      <aside class="side">

        <!-- Infos clés — carte navy -->
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
              <span class="sdv"><?= $outil['version'] ? 'v'.number_format($outil['version'],1) : '—' ?></span>
            </div>
            <div class="sdi">
              <span class="sdk">Note globale</span>
              <span class="sdv sdv-gold">★ <?= number_format($outil['global_rating'],1) ?></span>
            </div>
            <?php if (count($reviews)): ?>
            <div class="sdi">
              <span class="sdk">Avis</span>
              <span class="sdv"><?= count($reviews) ?></span>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- CTA -->
        <?php if ($outil['url']): ?>
        <div class="scard-cta">
          <p>Accédez directement à la plateforme officielle.</p>
          <a href="<?= htmlspecialchars($outil['url']) ?>" target="_blank" rel="noopener">
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
              <a href="<?= htmlspecialchars($d['url']) ?>" target="_blank" rel="noopener" class="dispo-link">
                <?= htmlspecialchars(parse_url($d['url'], PHP_URL_HOST)) ?>
                <i class="bi bi-box-arrow-up-right" style="font-size:11px"></i>
              </a>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

      </aside>

    </div>
  </div>
</div>

<?php include "../includes/footer.php"; ?>
<script src="../js/outils.js"></script>
<script>
/* ── Star picker ── */
const stars = document.querySelectorAll('#spStars .sp-s');
const hints = ['Mauvais','Passable','Correct','Bon','Excellent'];
let sel = 0;
stars.forEach((s,i) => {
  s.addEventListener('mouseenter', () => { stars.forEach((x,j) => x.classList.toggle('on', j<=i)); document.getElementById('spHint').textContent = hints[i]; });
  s.addEventListener('click', () => { sel = i+1; document.getElementById('spHint').textContent = hints[i]+' — sélectionné'; });
});
document.getElementById('spStars')?.addEventListener('mouseleave', () => {
  stars.forEach((x,j) => x.classList.toggle('on', j<sel));
  document.getElementById('spHint').textContent = sel ? hints[sel-1] : 'Cliquez pour noter';
});

/* ── Char count ── */
const ta = document.getElementById('reviewTa');
if (ta) ta.addEventListener('input', () => { document.getElementById('charCnt').textContent = ta.value.length; });

/* ── Edit toggle ── */
document.querySelectorAll('.js-edit-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const ef = document.getElementById('edit-form-' + btn.dataset.id);
    if (ef) ef.style.display = ef.style.display === 'none' || !ef.style.display ? 'block' : 'none';
  });
});
document.querySelectorAll('.js-cancel-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const ef = document.getElementById('edit-form-' + btn.dataset.id);
    if (ef) ef.style.display = 'none';
  });
});
</script>
</body>
</html>