<?php
require_once '../../includes/auth_admin.php';
require_once '../../includes/connexionbd.php';
require_once './notif_helper.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: notifications.php'); exit; }

$stmt = $pdo->prepare("
    SELECT o.*,
           c.name  AS cat_name,
           u.nom   AS cr_nom,
           u.email AS cr_email,
           u.role  AS cr_role
    FROM outils_ia o
    LEFT JOIN categorie c ON o.ID_CATEGORIE = c.ID_CATEGORIE
    LEFT JOIN users     u ON o.ID_USERS_CREATE = u.id
    WHERE o.ID_OUTILS_IA = ?
");
$stmt->execute([$id]);
$outil = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$outil) { header('Location: notifications.php'); exit; }

/* ── Actions POST ── */
$action = $_POST['action'] ?? '';

if ($action === 'valider') {
    $pdo->prepare("UPDATE outils_ia SET status='actif', ID_USERS_VALID=? WHERE ID_OUTILS_IA=?")
        ->execute([$_SESSION['user_id'], $id]);
    if ($outil['ID_USERS_CREATE'])
        notifierValidation($pdo, (int)$outil['ID_USERS_CREATE'], $id, $outil['nom']);
    header('Location: notifications.php?msg='.urlencode('Outil valide et publie !').'&type=success');
    exit;
}

if ($action === 'refuser') {
    $cause = trim($_POST['refusal_cause'] ?? '');
    $pdo->prepare("UPDATE outils_ia SET status='inactif', refusal_cause=? WHERE ID_OUTILS_IA=?")
        ->execute([$cause ?: null, $id]);
    if ($outil['ID_USERS_CREATE'])
        notifierRefus($pdo, (int)$outil['ID_USERS_CREATE'], $id, $outil['nom'], $cause);
    header('Location: notifications.php?msg='.urlencode('Outil refuse.').'&type=warning');
    exit;
}

if ($action === 'corriger_valider') {
    $nom         = trim($_POST['corr_nom'] ?? $outil['nom']);
    $description = trim($_POST['corr_description'] ?? $outil['description']);
    $url         = trim($_POST['corr_url'] ?? $outil['url']);
    $logo_url    = trim($_POST['corr_logo_url'] ?? $outil['logo_url']);
    $version     = trim($_POST['corr_version'] ?? $outil['version']);
    $id_cat      = (int)($_POST['corr_categorie'] ?? $outil['ID_CATEGORIE']);

    $pdo->prepare("
        UPDATE outils_ia
        SET nom=?, description=?, url=?, logo_url=?, version=?, ID_CATEGORIE=?, status='actif', ID_USERS_VALID=?
        WHERE ID_OUTILS_IA=?
    ")->execute([$nom, $description, $url, $logo_url ?: null, $version ?: null, $id_cat ?: null, $_SESSION['user_id'], $id]);

    if ($outil['ID_USERS_CREATE'])
        notifierValidation($pdo, (int)$outil['ID_USERS_CREATE'], $id, $nom);

    header('Location: notifications.php?msg='.urlencode('Corrections appliquees - outil publie !').'&type=success');
    exit;
}

/* ── Données annexes ── */
$avStmt = $pdo->prepare("SELECT * FROM avantages_inconvenients WHERE ID_OUTILS_IA=?");
$avStmt->execute([$id]);
$avants = $avStmt->fetchAll(PDO::FETCH_ASSOC);

$perfStmt = $pdo->prepare("SELECT * FROM performance WHERE ID_OUTILS_IA=? LIMIT 1");
$perfStmt->execute([$id]);
$perf = $perfStmt->fetch(PDO::FETCH_ASSOC);

$modStmt = $pdo->prepare("
    SELECT m.name FROM tool_models tm
    JOIN models m ON tm.ID_MODEL = m.ID_MODEL
    WHERE tm.ID_OUTILS_IA = ?
");
$modStmt->execute([$id]);
$models = $modStmt->fetchAll(PDO::FETCH_COLUMN);

$categories = $pdo->query("SELECT ID_CATEGORIE, name FROM categorie ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Vérification IA — <?=htmlspecialchars($outil['nom'])?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../styles/style.css">
<link rel="stylesheet" href="../../styles/admin.css">
<style>
.ver-wrap{max-width:980px;margin:0 auto;padding:24px 0 60px}
.ver-breadcrumb{display:flex;align-items:center;gap:6px;font-size:.78rem;color:var(--adm-muted,#64748b);margin-bottom:20px}
.ver-breadcrumb a{color:var(--adm-blue,#3b82f6);text-decoration:none}
.ver-breadcrumb a:hover{text-decoration:underline}
.outil-card{background:var(--adm-surface,#fff);border:1px solid var(--adm-border,#e2e8f0);border-radius:16px;padding:22px;display:flex;gap:18px;align-items:flex-start;margin-bottom:22px}
.outil-logo{width:64px;height:64px;object-fit:contain;border-radius:10px;border:1px solid var(--adm-border,#e2e8f0);background:#f8fafc;padding:5px;flex-shrink:0}
.outil-logo-ph{width:64px;height:64px;border-radius:10px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:1.8rem;flex-shrink:0}
.outil-name{font-size:1.2rem;font-weight:800;color:var(--adm-text,#1e293b);margin-bottom:4px}
.badge-status{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.7rem;font-weight:700;text-transform:uppercase}
.bs-en_attente{background:#fef3c7;color:#92400e}
.bs-actif{background:#dcfce7;color:#166534}
.bs-inactif{background:#fee2e2;color:#991b1b}
.ver-section{background:var(--adm-surface,#fff);border:1px solid var(--adm-border,#e2e8f0);border-radius:16px;overflow:hidden;margin-bottom:22px}
.ver-section-head{padding:14px 22px;border-bottom:1px solid var(--adm-border,#e2e8f0);display:flex;align-items:center;justify-content:space-between;gap:10px}
.ver-section-title{font-size:.9rem;font-weight:700;color:var(--adm-text,#1e293b)}
.ver-section-body{padding:20px 22px}
.score-row{display:flex;align-items:center;gap:32px;flex-wrap:wrap;margin-bottom:22px}
.score-ring-container{position:relative;width:130px;height:130px;flex-shrink:0}
.score-ring-container svg{width:130px;height:130px}
.score-number{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:1.9rem;font-weight:800;line-height:1}
.score-info{flex:1;min-width:200px}
.score-verdict{font-size:1rem;font-weight:700;margin-bottom:6px}
.score-detail{font-size:.83rem;color:var(--adm-muted);line-height:1.55}
.criteria-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;margin-bottom:20px}
.crit-card{border:1px solid var(--adm-border,#e2e8f0);border-radius:10px;padding:11px 13px;background:#f8fafc}
.crit-label{font-size:.68rem;font-weight:700;color:var(--adm-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px}
.crit-bar-bg{height:5px;background:#e2e8f0;border-radius:3px;overflow:hidden;margin-bottom:4px}
.crit-bar-fill{height:100%;border-radius:3px;transition:width 1s ease}
.crit-score{font-size:.8rem;font-weight:700}
.verdict-box{border-radius:10px;padding:13px 17px;margin-bottom:18px;font-size:.84rem;line-height:1.55}
.verdict-pass{background:#f0fdf4;border:1px solid #86efac;color:#14532d}
.verdict-fail{background:#fff1f2;border:1px solid #fecdd3;color:#881337}
.verdict-warn{background:#fffbeb;border:1px solid #fde68a;color:#78350f}

/* ══ Bannières ══ */
.banner{display:flex;align-items:center;gap:12px;border-radius:12px;padding:14px 18px;margin:0 22px 16px;font-size:.88rem;font-weight:700}
.banner-success{background:#f0fdf4;border:1px solid #86efac;color:#14532d}
.banner-danger{background:#fff1f2;border:1px solid #fecdd3;color:#881337;font-size:.86rem;font-weight:600}
.banner-warn{background:#fffbeb;border:1px solid #fde68a;color:#78350f}
.countdown-bar-bg{flex:1;height:5px;background:#fecdd3;border-radius:3px;overflow:hidden;min-width:80px}
.countdown-bar-fill{height:100%;background:#dc2626;border-radius:3px;transition:width .1s linear}

/* ══ Corrections ══ */
.correction-section{background:var(--adm-surface,#fff);border:2px solid #f59e0b;border-radius:16px;overflow:hidden;margin-bottom:22px}
.correction-head{padding:14px 22px;background:#fffbeb;border-bottom:1px solid #fde68a;display:flex;align-items:center;gap:10px}
.correction-head-title{font-size:.9rem;font-weight:800;color:#78350f}
.correction-body{padding:20px 22px}
.fields-summary{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:22px}
.fields-summary-box{border-radius:12px;padding:14px 16px}
.fields-summary-ok{background:#f0fdf4;border:1px solid #86efac}
.fields-summary-err{background:#fff1f2;border:1px solid #fecdd3}
.fields-summary-title{font-size:.75rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px}
.fields-summary-ok .fields-summary-title{color:#166534}
.fields-summary-err .fields-summary-title{color:#991b1b}
.fields-summary-item{font-size:.79rem;margin-bottom:4px;display:flex;align-items:center;gap:6px}
.fields-summary-ok .fields-summary-item{color:#14532d}
.fields-summary-err .fields-summary-item{color:#881337}
.field-row{margin-bottom:18px;border:1px solid var(--adm-border,#e2e8f0);border-radius:12px;overflow:hidden}
.field-row-header{display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid var(--adm-border,#e2e8f0);flex-wrap:wrap}
.field-row-header-ok{background:#f0fdf4;border-bottom-color:#86efac}
.field-row-header-err{background:#fff7ed;border-bottom-color:#fde68a}
.field-row-body{padding:12px 14px;background:#fff}
.field-row label{font-size:.8rem;font-weight:700;color:var(--adm-text,#1e293b);flex:1}
.field-original{font-size:.78rem;color:var(--adm-muted);background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 10px;margin-bottom:8px;line-height:1.45}
.field-original em{font-style:italic;color:#dc2626}
.field-input{width:100%;padding:9px 11px;border:1.5px solid #f59e0b;border-radius:8px;font-size:.88rem;line-height:1.5;font-family:inherit;background:#fff;color:var(--adm-text,#1e293b);box-sizing:border-box;resize:vertical;transition:border-color .15s}
.field-input:focus{outline:none;border-color:#d97706}
.field-badge-ok{display:inline-flex;align-items:center;gap:4px;font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:20px;background:#dcfce7;color:#166534}
.field-badge-err{display:inline-flex;align-items:center;gap:4px;font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:20px;background:#fee2e2;color:#991b1b}
.field-badge-req{display:inline-flex;align-items:center;gap:4px;font-size:.65rem;font-weight:600;padding:1px 6px;border-radius:20px;background:#fef3c7;color:#92400e}
.ai-suggestion-label{font-size:.75rem;color:#d97706;font-weight:700;margin-bottom:5px;display:flex;align-items:center;gap:4px}
.btn-primary-lg{padding:11px 26px;background:#16a34a;color:#fff;border:none;border-radius:10px;font-size:.9rem;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s}
.btn-primary-lg:hover:not(:disabled){background:#15803d}
.btn-primary-lg:disabled{opacity:.4;cursor:not-allowed}
.btn-danger-lg{padding:11px 26px;background:#dc2626;color:#fff;border:none;border-radius:10px;font-size:.9rem;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s}
.btn-danger-lg:hover:not(:disabled){background:#b91c1c}
.btn-back{padding:10px 16px;background:transparent;color:var(--adm-muted);border:1px solid var(--adm-border,#e2e8f0);border-radius:10px;font-size:.84rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:5px;transition:background .15s;font-family:inherit}
.btn-back:hover{background:#f1f5f9}
.action-bar{display:flex;gap:10px;align-items:center;padding:18px 22px;background:#f8fafc;border-top:1px solid var(--adm-border,#e2e8f0);flex-wrap:wrap}
.action-bar-sep{width:1px;height:28px;background:var(--adm-border,#e2e8f0);margin:0 4px}

.result-placeholder{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 20px;color:var(--adm-muted);text-align:center;gap:8px}
.dot-loader{display:inline-flex;gap:4px;align-items:center}
.dot-loader span{width:6px;height:6px;border-radius:50%;background:currentColor;opacity:.5;animation:dotpulse 1.2s ease-in-out infinite}
.dot-loader span:nth-child(2){animation-delay:.2s}
.dot-loader span:nth-child(3){animation-delay:.4s}
@keyframes dotpulse{0%,80%,100%{transform:scale(.8);opacity:.4}40%{transform:scale(1.2);opacity:1}}
@keyframes spin{to{transform:rotate(360deg)}}
.spin{animation:spin 1s linear infinite}

/* ══ Champ refus ══ */
.refusal-field{margin:0 22px 16px}
.refusal-field label{display:block;font-size:.82rem;font-weight:700;margin-bottom:5px;color:#1e293b}
.refusal-field textarea{width:100%;min-height:88px;padding:9px 11px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:.88rem;resize:vertical;font-family:inherit;box-sizing:border-box;transition:border-color .15s}
.refusal-field textarea:focus{outline:none;border-color:#dc2626}
.refusal-field-hint{font-size:.75rem;color:#64748b;margin-top:5px}

.hidden{display:none!important}
.model-tag{display:inline-block;padding:2px 8px;border-radius:20px;font-size:.7rem;font-weight:600;background:#f1f5f9;color:#475569;margin:2px}
.pill{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:.72rem;font-weight:600}
.pill-av{background:#dcfce7;color:#166534}
.pill-inc{background:#fee2e2;color:#991b1b}
.optional-info-section{background:#f8fafc;border:1px solid var(--adm-border,#e2e8f0);border-radius:12px;padding:14px 16px;margin-bottom:16px}
.optional-info-title{font-size:.78rem;font-weight:700;color:var(--adm-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px}
.optional-item{display:flex;align-items:flex-start;gap:8px;font-size:.82rem;margin-bottom:6px;color:var(--adm-text,#1e293b)}
.optional-item-label{font-weight:600;min-width:100px;color:var(--adm-muted);font-size:.78rem}

/* Validating spinner overlay */
.validating-overlay{display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:9999;align-items:center;justify-content:center;flex-direction:column;gap:14px}
.validating-overlay.active{display:flex}
.validating-box{background:#fff;border-radius:16px;padding:28px 36px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.18)}
.validating-box p{margin:10px 0 0;font-size:.9rem;font-weight:700;color:#1e293b}
.validating-spinner{width:44px;height:44px;border:4px solid #e2e8f0;border-top-color:#16a34a;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto}

/* ══ Légende score ══ */
.score-legend{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:14px}
.score-legend-item{display:flex;align-items:center;gap:6px;font-size:.75rem;font-weight:600;color:#475569}
.score-legend-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
</style>
</head>
<body class="adm-body">
<?php include 'sidebar.php'; ?>

<!-- Overlay de validation/refus automatique -->
<div class="validating-overlay" id="validatingOverlay">
  <div class="validating-box">
    <div class="validating-spinner"></div>
    <p id="validatingMsg">Traitement en cours…</p>
  </div>
</div>

<div class="adm-main">
<div class="ver-wrap">

  <div class="ver-breadcrumb">
    <a href="notifications.php">Notifications</a>
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
    <strong><?=htmlspecialchars($outil['nom'])?></strong>
  </div>

  <div class="adm-topbar" style="margin-bottom:20px">
    <div>
      <h1 class="adm-title">Vérification &amp; Correction IA</h1>
      <p class="adm-sub">Analyse automatique des champs de l'outil et prise de décision selon le score obtenu.</p>
    </div>
  </div>

  <!-- Légende des seuils -->
  <div class="score-legend" style="margin-bottom:18px">
    <div class="score-legend-item"><div class="score-legend-dot" style="background:#16a34a"></div>Score 100% → Validation automatique</div>
    <div class="score-legend-item"><div class="score-legend-dot" style="background:#d97706"></div>Score 51–99% → Corrections + choix publier / refuser</div>
    <div class="score-legend-item"><div class="score-legend-dot" style="background:#dc2626"></div>Score ≤ 50% → Refus automatique avec cause</div>
  </div>

  <!-- Carte outil -->
  <div class="outil-card">
    <?php if(!empty($outil['logo_url'])): ?>
      <img src="<?=htmlspecialchars($outil['logo_url'])?>" class="outil-logo" alt=""
           onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
      <div class="outil-logo-ph" style="display:none">&#x1F916;</div>
    <?php else: ?>
      <div class="outil-logo-ph">&#x1F916;</div>
    <?php endif; ?>
    <div style="flex:1;min-width:0">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px">
        <div class="outil-name"><?=htmlspecialchars($outil['nom'])?></div>
        <span class="badge-status bs-<?=htmlspecialchars($outil['status'])?>"><?=htmlspecialchars($outil['status'])?></span>
        <?php if($outil['version']): ?><span style="font-size:.72rem;background:#f1f5f9;padding:2px 8px;border-radius:20px;color:#475569">v<?=number_format((float)$outil['version'],2)?></span><?php endif; ?>
      </div>
      <div style="font-size:.78rem;color:var(--adm-muted);margin-bottom:8px;display:flex;flex-wrap:wrap;gap:10px">
        <?php if($outil['cat_name']): ?><span>&#x1F4C2; <?=htmlspecialchars($outil['cat_name'])?></span><?php endif; ?>
        <?php if($outil['url']): ?><span>&#x1F310; <a href="<?=htmlspecialchars($outil['url'])?>" target="_blank" style="color:var(--adm-blue)"><?=htmlspecialchars($outil['url'])?></a></span><?php endif; ?>
        <?php if($outil['cr_nom']): ?><span>&#x1F464; <strong><?=htmlspecialchars($outil['cr_nom'])?></strong></span><?php endif; ?>
      </div>
      <?php if(!empty($outil['description'])): ?><div style="font-size:.82rem;color:var(--adm-muted);line-height:1.5"><?=htmlspecialchars($outil['description'])?></div><?php endif; ?>
      <?php if(!empty($models)): ?><div style="margin-top:8px"><?php foreach($models as $m): ?><span class="model-tag"><?=htmlspecialchars($m)?></span><?php endforeach; ?></div><?php endif; ?>
      <?php if(!empty($avants)): ?>
        <div style="display:flex;flex-wrap:wrap;gap:5px;margin-top:8px">
          <?php foreach($avants as $a): ?><span class="pill pill-<?=$a['type']==='avantage'?'av':'inc'?>"><?=$a['type']==='avantage'?'&#x2713;':'&#x2717;'?> <?=htmlspecialchars(mb_substr($a['description'],0,50))?></span><?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ══ PARTIE 1 : Score IA ══ -->
  <div class="ver-section" id="sectionScore">
    <div class="ver-section-head">
      <div class="ver-section-title">&#x1F916; Partie 1 — Score de compatibilité IA</div>
      <button class="btn-back" id="btnReVerify" onclick="lancerVerification()" disabled style="border-color:#10b981;color:#10b981">
        <svg id="spinIcon" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.36"/></svg>
        Re-analyser
      </button>
    </div>
    <div class="ver-section-body">
      <div id="stateLoading" class="result-placeholder">
        <div class="dot-loader" style="color:#10b981;margin-bottom:10px"><span></span><span></span><span></span></div>
        <p style="font-weight:700;font-size:.9rem;margin:0 0 4px">Analyse IA en cours…</p>
        <p style="font-size:.8rem;color:var(--adm-muted);margin:0" id="loadingMsg">Vérification du nom et de la description…</p>
      </div>
      <div id="statePlaceholder" class="result-placeholder hidden">
        <p style="font-weight:600;font-size:.85rem;margin:0">Cliquez sur <strong>Re-analyser</strong> pour relancer.</p>
      </div>
      <div id="stateResult" class="hidden">
        <div class="score-row">
          <div class="score-ring-container">
            <svg viewBox="0 0 130 130">
              <circle cx="65" cy="65" r="50" fill="none" stroke="#e2e8f0" stroke-width="11"/>
              <circle cx="65" cy="65" r="50" id="scoreCircle" fill="none" stroke="#16a34a" stroke-width="11"
                stroke-linecap="round" stroke-dasharray="314.2" stroke-dashoffset="314.2"
                transform="rotate(-90 65 65)" style="transition:stroke-dashoffset 1.3s ease,stroke .4s ease"/>
            </svg>
            <div class="score-number" id="scoreNumber" style="color:#16a34a">—</div>
          </div>
          <div class="score-info">
            <div class="score-verdict" id="scoreVerdict">—</div>
            <div class="score-detail" id="scoreDetail">—</div>
          </div>
        </div>
        <div class="criteria-grid" id="criteriaGrid"></div>
        <div id="verdictBox" class="verdict-box verdict-warn"></div>
        <details>
          <summary style="cursor:pointer;font-size:.8rem;font-weight:600;color:#10b981;user-select:none;padding:4px 0">&#x25B8; Analyse complète</summary>
          <div style="margin-top:10px;padding:14px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0">
            <div id="analysisText" style="font-size:.82rem;line-height:1.65;white-space:pre-wrap"></div>
          </div>
        </details>
      </div>
    </div>

    <!-- Bannière score 100% → validation auto -->
    <div id="bannerPerfect" class="banner banner-success hidden">
      <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
      <span>Score <strong>100%</strong> — Toutes les informations sont parfaites. Validation automatique en cours…</span>
    </div>

    <!-- Bannière score ≤ 50% → refus auto avec cause -->
    <div id="bannerRefusAuto" class="banner banner-danger hidden">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      <span>Score &le; 50% — Refus automatique dans <strong><span id="countdownSec">5</span>s</strong></span>
      <div class="countdown-bar-bg"><div class="countdown-bar-fill" id="countdownBar" style="width:100%"></div></div>
    </div>

    <!-- Cause de refus automatique (affichée en lecture seule) -->
    <div id="refusAutoCauseDisplay" class="hidden" style="margin:0 22px 16px;padding:12px 16px;background:#fff1f2;border:1px solid #fecdd3;border-radius:10px;font-size:.84rem;color:#881337">
      <strong>Cause du refus automatique :</strong>
      <div id="refusAutoCauseText" style="margin-top:6px;line-height:1.55;white-space:pre-wrap"></div>
    </div>

    <!-- Bannière 50 < score < 100 → choix -->
    <div id="bannerChoix" class="banner banner-warn hidden">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <span id="bannerChoixText">Score insuffisant — appliquez les corrections IA ou refusez l'outil.</span>
    </div>

    <!-- Formulaire refus manuel (visible si 50 < score < 100 seulement) -->
    <div id="refusalField" class="refusal-field hidden">
      <label>&#x1F4DD; Motif du refus <span style="color:#dc2626">*</span></label>
      <textarea id="refusalCause" name="refusal_cause" form="formRefuser"
        placeholder="Expliquez le motif du refus à l'auteur de l'outil…"></textarea>
      <div class="refusal-field-hint">Ce message sera envoyé à l'auteur de l'outil pour qu'il puisse corriger sa soumission.</div>
      <div id="refusalError" style="color:#dc2626;font-size:.8rem;margin-top:6px;font-weight:600;display:flex;align-items:center;gap:5px" class="hidden">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Le motif du refus est obligatoire.
      </div>
    </div>

    <div id="actionBarRefus" class="action-bar hidden">
      <a href="notifications.php" class="btn-back">← Retour</a>
      <form method="POST" id="formRefuser" onsubmit="return validateRefusForm(event)">
        <input type="hidden" name="action" value="refuser">
        <button type="submit" class="btn-danger-lg">&#x2717; Refuser l'outil</button>
      </form>
      <div class="action-bar-sep"></div>
      <span style="font-size:.78rem;color:var(--adm-muted)">— ou appliquez les corrections IA ci-dessous pour publier —</span>
    </div>

  </div><!-- /sectionScore -->

  <!-- ══ PARTIE 2 : Corrections IA ══ -->
  <div class="correction-section hidden" id="sectionCorrections">
    <div class="correction-head">
      <svg width="18" height="18" fill="none" stroke="#d97706" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      <div class="correction-head-title">Partie 2 — Corrections des champs invalides</div>
      <span id="correctionScoreBadge" style="font-size:.75rem;font-weight:700;padding:3px 10px;border-radius:20px;background:#fef3c7;color:#92400e;margin-left:auto"></span>
    </div>
    <div class="correction-body">
      <div class="fields-summary" id="fieldsSummary" style="display:none"></div>
      <p style="font-size:.84rem;color:var(--adm-muted);margin:0 0 18px;line-height:1.55">
        Les <strong>champs obligatoires</strong> <span class="field-badge-req">Obligatoire</span>
        sont : <em>nom, description, URL, logo, version, catégorie</em>.<br>
        L'IA a généré des corrections pour les champs invalides. Vérifiez, ajustez si besoin, puis publiez.
      </p>
      <div id="correctionLoading" class="result-placeholder hidden">
        <div class="dot-loader" style="color:#f59e0b"><span></span><span></span><span></span></div>
        <p style="font-size:.84rem;font-weight:700;margin:0 0 4px">Génération des corrections…</p>
        <p style="font-size:.78rem;color:var(--adm-muted);margin:0">L'IA corrige les champs invalides…</p>
      </div>
      <div id="correctionFieldsContainer"></div>
      <div id="optionalInfoSection" class="optional-info-section hidden">
        <div class="optional-info-title">&#x1F4CB; Informations optionnelles (lecture seule)</div>
        <div id="optionalInfoContent"></div>
      </div>
    </div>
    <form method="POST" id="formCorrection" onsubmit="return confirm('Appliquer les corrections et publier l\'outil ?')">
      <input type="hidden" name="action" value="corriger_valider">
      <div class="action-bar">
        <a href="notifications.php" class="btn-back">← Retour</a>
        <button type="submit" class="btn-primary-lg" id="btnApplyCorrections" disabled>
          &#x2713; Appliquer corrections et publier
        </button>
        <span style="font-size:.78rem;color:var(--adm-muted);margin-left:auto" id="correctionNote">Patientez…</span>
      </div>
    </form>
  </div>

  <!-- Formulaire refus auto caché -->
  <form method="POST" id="formRefusAuto" style="display:none">
    <input type="hidden" name="action" value="refuser">
    <input type="hidden" name="refusal_cause" id="refusAutoCauseInput" value="">
  </form>

  <!-- Formulaire validation auto caché -->
  <form method="POST" id="formValiderAuto" style="display:none">
    <input type="hidden" name="action" value="valider">
  </form>

</div>
</div>

<script>
const OUTIL = {
    id:            <?=json_encode($id)?>,
    nom:           <?=json_encode($outil['nom'])?>,
    description:   <?=json_encode($outil['description']??'')?>,
    url:           <?=json_encode($outil['url']??'')?>,
    logo_url:      <?=json_encode($outil['logo_url']??'')?>,
    version:       <?=json_encode((string)($outil['version']??''))?>,
    id_categorie:  <?=json_encode((int)($outil['ID_CATEGORIE']??0))?>,
    rating:        <?=json_encode((float)($outil['global_rating']??0))?>,
    avantages:     <?=json_encode(array_values(array_filter($avants,fn($a)=>$a['type']==='avantage')))?>,
    inconvenients: <?=json_encode(array_values(array_filter($avants,fn($a)=>$a['type']==='inconvenient')))?>,
    models:        <?=json_encode($models)?>,
    perf:          <?=json_encode($perf?:null)?>
};
const CATEGORIES = <?=json_encode(array_column($categories, 'name', 'ID_CATEGORIE'))?>;

const REQUIRED_FIELDS = {
    nom:         { label: "Nom de l'outil",  inputName: 'corr_nom',         type: 'input',    getValue: () => OUTIL.nom },
    description: { label: 'Description',      inputName: 'corr_description', type: 'textarea', getValue: () => OUTIL.description },
    url:         { label: 'URL officielle',   inputName: 'corr_url',         type: 'input',    getValue: () => OUTIL.url },
    logo_url:    { label: 'Logo (URL image)', inputName: 'corr_logo_url',    type: 'input',    getValue: () => OUTIL.logo_url },
    version:     { label: 'Version',          inputName: 'corr_version',     type: 'input',    getValue: () => OUTIL.version },
    categorie:   { label: 'Catégorie',        inputName: 'corr_categorie',   type: 'select',   getValue: () => String(OUTIL.id_categorie) }
};

let _analysisResult = null;
let _countdownTimer = null;

window.addEventListener('DOMContentLoaded', () => lancerVerification());

/* ════════════════════════════════════════════
   PARTIE 1 — Analyse IA
════════════════════════════════════════════ */
async function lancerVerification() {
    document.getElementById('btnReVerify').disabled = true;
    document.getElementById('spinIcon').classList.add('spin');
    setState('loading');

    // Masquer tout ce qui dépend du résultat précédent
    ['sectionCorrections','bannerPerfect','bannerRefusAuto','bannerChoix',
     'refusalField','actionBarRefus','refusAutoCauseDisplay'].forEach(id =>
        document.getElementById(id).classList.add('hidden'));

    if (_countdownTimer) { clearInterval(_countdownTimer); _countdownTimer = null; }

    const msgs = [
        'Vérification du nom et de la description…',
        'Analyse de l\'URL et du logo…',
        'Évaluation des avantages et catégorie…',
        'Calcul du score global…'
    ];
    let mi = 0;
    const msgEl = document.getElementById('loadingMsg');
    const iv = setInterval(() => { msgEl.textContent = msgs[++mi % msgs.length]; }, 2200);

    try {
        const result = await callAnalyseIA();
        _analysisResult = result;
        clearInterval(iv);
        appliquerLogique(result);
    } catch(err) {
        clearInterval(iv);
        afficherErreur(err.message);
    } finally {
        document.getElementById('btnReVerify').disabled = false;
        document.getElementById('spinIcon').classList.remove('spin');
    }
}

async function callAnalyseIA() {
    const av   = OUTIL.avantages.map(a => '  + ' + a.description).join('\n') || '  (aucun)';
    const inc  = OUTIL.inconvenients.map(a => '  - ' + a.description).join('\n') || '  (aucun)';
    const mods = OUTIL.models.length ? OUTIL.models.join(', ') : 'Non renseignés';
    const perfTxt = OUTIL.perf
        ? `Rapidité=${OUTIL.perf.rapidite}|Qualité=${OUTIL.perf.qualite}|Crédibilité=${OUTIL.perf.credibilite}|Score=${OUTIL.perf.score_global}`
        : 'Pas de données';

    const res = await fetch('openai_proxy.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            model: 'llama-3.3-70b-versatile',
            max_tokens: 1000,
            temperature: 0.2,
            messages: [
                {
                    role: 'system',
                    content: 'Tu es un expert en outils IA. Tu réponds UNIQUEMENT en JSON valide, sans markdown ni backticks.'
                },
                {
                    role: 'user',
                    content: `Analyse cet outil IA et retourne un JSON.

Données :
- Nom: ${OUTIL.nom}
- Description: ${OUTIL.description || 'Non renseignée'}
- URL: ${OUTIL.url || 'Non renseignée'}
- Logo URL: ${OUTIL.logo_url || 'Non renseignée'}
- Catégorie: ${OUTIL.id_categorie ? (CATEGORIES[OUTIL.id_categorie]||'Non trouvée') : 'Non renseignée'}
- Version: ${OUTIL.version || 'Non renseignée'}
- Note: ${OUTIL.rating}/5
- Modèles: ${mods}
- Avantages:\n${av}
- Inconvénients:\n${inc}
- Performance: ${perfTxt}

CHAMPS OBLIGATOIRES : nom, description, url, logo_url, version, categorie
- Invalide si : vide, description < 50 mots, URL malformée, logo absent/non-image, version absente, catégorie non définie.
- Valide si : présent, cohérent, suffisamment détaillé.

JSON attendu :
{
  "score_global": <0-100>,
  "criteres": {
    "coherence_nom_description": <0-100>,
    "validite_url": <0-100>,
    "validite_logo": <0-100>,
    "pertinence_categorie": <0-100>,
    "qualite_description": <0-100>,
    "coherence_avantages": <0-100>
  },
  "champs_invalides": [],
  "champs_valides": [],
  "verdict": "<phrase courte expliquant le score>",
  "analyse": "<3-5 phrases détaillées>"
}
score_global=100 uniquement si TOUS les champs obligatoires sont parfaits et cohérents.`
                }
            ]
        })
    });

    if (!res.ok) throw new Error(`Erreur HTTP ${res.status}`);
    const data = await res.json();
    const raw  = data?.choices?.[0]?.message?.content ?? '';
    if (!raw) throw new Error('Réponse vide de l\'IA.');
    const clean = raw.replace(/```json|```/gi,'').trim();
    try { return JSON.parse(clean); }
    catch { throw new Error('Réponse non parsable : ' + raw.slice(0,200)); }
}

/* ════════════════════════════════════════════
   Logique des 3 cas selon le score
════════════════════════════════════════════ */
function appliquerLogique(data) {
    setState('result');

    const score     = Math.min(100, Math.max(0, Math.round(data.score_global ?? 0)));
    const isPerfect = score === 100;           // CAS 1 : validation auto
    const isRefus   = score <= 50;             // CAS 3 : refus auto
    const isChoix   = !isPerfect && !isRefus;  // CAS 2 : 51–99% → corrections + choix

    afficherRing(score);
    afficherCriteres(data);
    afficherVerdict(data, score);

    /* ── CAS 1 : 100% → validation automatique ── */
    if (isPerfect) {
        document.getElementById('bannerPerfect').classList.remove('hidden');
        document.getElementById('validatingMsg').textContent = 'Score parfait — validation automatique en cours…';
        document.getElementById('validatingOverlay').classList.add('active');
        setTimeout(() => document.getElementById('formValiderAuto').submit(), 2000);
        return;
    }

    /* ── CAS 3 : ≤ 50% → refus automatique avec cause IA ── */
    if (isRefus) {
        // Construire la cause automatique depuis l'analyse IA
        const champsInvalides = (data.champs_invalides ?? []).join(', ') || 'champs non renseignés';
        const causeAuto = `Refus automatique — Score IA : ${score}%\n`
            + `Verdict : ${data.verdict ?? ''}\n`
            + `Champs invalides : ${champsInvalides}\n`
            + (data.analyse ? `\nDétail : ${data.analyse}` : '');

        document.getElementById('bannerRefusAuto').classList.remove('hidden');
        document.getElementById('refusAutoCauseText').textContent = causeAuto;
        document.getElementById('refusAutoCauseDisplay').classList.remove('hidden');
        document.getElementById('refusAutoCauseInput').value = causeAuto;

        let restant = 5;
        document.getElementById('countdownSec').textContent = restant;
        document.getElementById('countdownBar').style.width = '100%';

        _countdownTimer = setInterval(() => {
            restant--;
            document.getElementById('countdownSec').textContent = restant;
            document.getElementById('countdownBar').style.width = (restant / 5 * 100) + '%';
            if (restant <= 0) {
                clearInterval(_countdownTimer);
                document.getElementById('validatingMsg').textContent = 'Refus automatique en cours…';
                document.getElementById('validatingOverlay').classList.add('active');
                setTimeout(() => document.getElementById('formRefusAuto').submit(), 600);
            }
        }, 1000);
        return;
    }

    /* ── CAS 2 : 51–99% → corrections disponibles + choix refuser/publier ── */
    document.getElementById('bannerChoix').classList.remove('hidden');
    document.getElementById('bannerChoixText').textContent =
        `Score ${score}% — Corrigez les champs invalides et publiez, ou refusez l'outil en indiquant le motif.`;

    // Pré-remplir le textarea de refus avec le verdict IA (modifiable par l'admin)
    const causePreRemplie = `Score IA : ${score}% — ${data.verdict ?? ''}`
        + ((data.champs_invalides ?? []).length
            ? `\nChamps invalides : ${data.champs_invalides.join(', ')}`
            : '');
    document.getElementById('refusalCause').value = causePreRemplie;

    document.getElementById('refusalField').classList.remove('hidden');
    document.getElementById('actionBarRefus').classList.remove('hidden');

    // Lancer les corrections IA pour la partie 2
    lancerCorrections(data);
}

/* ── Ring animé avec couleur selon score ── */
function afficherRing(score) {
    // Vert = 100%, Orange = 51-99%, Rouge = ≤ 50%
    const couleur = score === 100 ? '#16a34a' : score > 50 ? '#d97706' : '#dc2626';
    const circ    = 314.2;
    const circle  = document.getElementById('scoreCircle');
    circle.style.stroke = couleur;
    setTimeout(() => {
        circle.style.strokeDashoffset = circ - (score / 100) * circ;
    }, 80);

    const numEl = document.getElementById('scoreNumber');
    numEl.style.color = couleur;
    let cur = 0;
    const ctr = setInterval(() => {
        cur = Math.min(cur + Math.ceil(score / 30), score);
        numEl.textContent = cur + '%';
        if (cur >= score) clearInterval(ctr);
    }, 28);
}

/* ── Critères avec couleur selon valeur ── */
function afficherCriteres(data) {
    const labels = {
        coherence_nom_description: 'Cohérence nom/desc.',
        validite_url:              'Validité URL',
        validite_logo:             'Validité Logo',
        pertinence_categorie:      'Pertinence catégorie',
        qualite_description:       'Qualité description',
        coherence_avantages:       'Cohérence avantages'
    };
    const grid = document.getElementById('criteriaGrid');
    grid.innerHTML = '';
    for (const [k, v] of Object.entries(data.criteres ?? {})) {
        const val = Math.round(v);
        // Vert > 50%, Orange entre 51-99%, Rouge ≤ 50%
        const c = val === 100 ? '#16a34a' : val > 50 ? '#d97706' : '#dc2626';
        const badge = val === 100
            ? '<span style="float:right;font-size:.65rem;background:#dcfce7;color:#166534;padding:1px 6px;border-radius:20px;font-weight:700">100%</span>'
            : val > 50
            ? '<span style="float:right;font-size:.65rem;background:#fef3c7;color:#92400e;padding:1px 6px;border-radius:20px;font-weight:700">⚠</span>'
            : '<span style="float:right;font-size:.65rem;background:#fee2e2;color:#991b1b;padding:1px 6px;border-radius:20px;font-weight:700">✗</span>';
        grid.innerHTML += `
        <div class="crit-card">
          <div class="crit-label">${labels[k]||k} ${badge}</div>
          <div class="crit-bar-bg"><div class="crit-bar-fill" style="width:0%;background:${c}" data-w="${val}"></div></div>
          <div class="crit-score" style="color:${c}">${val}%</div>
        </div>`;
    }
    setTimeout(() => grid.querySelectorAll('.crit-bar-fill').forEach(b => b.style.width = b.dataset.w + '%'), 120);
}

/* ── Bloc verdict ── */
function afficherVerdict(data, score) {
    const verd = document.getElementById('scoreVerdict');
    const det  = document.getElementById('scoreDetail');

    if (score === 100) {
        verd.textContent = '✓ Score parfait — validation automatique';
        verd.style.color = '#16a34a';
        det.textContent  = 'Tous les champs sont corrects et complets. L\'outil sera validé et publié automatiquement.';
    } else if (score > 50) {
        verd.textContent = `⚠ Score ${score}% — corrections disponibles`;
        verd.style.color = '#d97706';
        det.textContent  = 'Certains champs sont invalides. Appliquez les corrections IA et publiez, ou refusez l\'outil en indiquant le motif.';
    } else {
        verd.textContent = `✗ Score ${score}% — refus automatique`;
        verd.style.color = '#dc2626';
        det.textContent  = 'Trop de champs invalides ou incorrects. L\'outil sera refusé automatiquement avec la cause générée par l\'IA.';
    }

    const vb = document.getElementById('verdictBox');
    vb.textContent = data.verdict ?? '';
    vb.className   = 'verdict-box ' + (score === 100 ? 'verdict-pass' : score > 50 ? 'verdict-warn' : 'verdict-fail');
    document.getElementById('analysisText').textContent = data.analyse ?? '';
}

/* ════════════════════════════════════════════
   PARTIE 2 — Corrections IA
════════════════════════════════════════════ */
async function lancerCorrections(data) {
    const invalid = data.champs_invalides ?? [];
    const valid   = data.champs_valides   ?? [];
    const score   = Math.round(data.score_global ?? 0);

    document.getElementById('sectionCorrections').classList.remove('hidden');
    document.getElementById('correctionScoreBadge').textContent = `Score : ${score}%`;
    document.getElementById('correctionFieldsContainer').innerHTML = '';
    document.getElementById('correctionLoading').classList.remove('hidden');
    document.getElementById('btnApplyCorrections').disabled = true;
    document.getElementById('correctionNote').textContent = 'Génération des corrections IA…';

    afficherFieldsSummary(valid, invalid);

    try {
        const corrections = await callCorrectionsIA(invalid);
        afficherCorrectionFields(invalid, valid, corrections);
    } catch(err) {
        document.getElementById('correctionLoading').classList.add('hidden');
        document.getElementById('correctionFieldsContainer').innerHTML =
            `<div style="color:#dc2626;font-size:.84rem;padding:12px 16px;background:#fff1f2;border:1px solid #fecdd3;border-radius:10px">
              <strong>Erreur :</strong> ${escHtml(err.message)}
              <button onclick="lancerCorrections(_analysisResult)"
                style="margin-left:10px;padding:5px 12px;border:1px solid #fecdd3;border-radius:7px;cursor:pointer;font-family:inherit;background:#fff;color:#dc2626;font-weight:600">
                Réessayer
              </button>
             </div>`;
    }
}

function afficherFieldsSummary(valid, invalid) {
    const summary = document.getElementById('fieldsSummary');
    const allLabels = { nom:'Nom', description:'Description', url:'URL', logo_url:'Logo', version:'Version', categorie:'Catégorie' };
    summary.style.display = 'grid';
    summary.innerHTML = `
      <div class="fields-summary-box fields-summary-ok">
        <div class="fields-summary-title">&#x2713; Champs corrects (${valid.length})</div>
        ${valid.map(f=>`<div class="fields-summary-item"><span>&#x2713;</span> <strong>${allLabels[f]||f}</strong></div>`).join('')
          || '<div class="fields-summary-item" style="opacity:.6">Aucun champ correct</div>'}
      </div>
      <div class="fields-summary-box fields-summary-err">
        <div class="fields-summary-title">&#x2717; À corriger (${invalid.length})</div>
        ${invalid.map(f=>`<div class="fields-summary-item"><span>&#x2717;</span> <strong>${allLabels[f]||f}</strong></div>`).join('')
          || '<div class="fields-summary-item" style="opacity:.6">Aucun champ à corriger</div>'}
      </div>`;
}

async function callCorrectionsIA(champsInvalides) {
    const catList = Object.entries(CATEGORIES).map(([id,name]) => `${id}: ${name}`).join(', ');

    const res = await fetch('openai_proxy.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            model: 'llama-3.3-70b-versatile',
            max_tokens: 1500,
            temperature: 0.3,
            messages: [
                {
                    role: 'system',
                    content: 'Tu es un expert en outils IA. Tu réponds UNIQUEMENT en JSON valide, sans markdown ni backticks.'
                },
                {
                    role: 'user',
                    content: `Outil IA avec champs invalides. Génère des corrections professionnelles.

Données actuelles :
- Nom: ${OUTIL.nom}
- Description: ${OUTIL.description || 'Non renseignée'}
- URL: ${OUTIL.url || 'Non renseignée'}
- Logo URL: ${OUTIL.logo_url || 'Non renseignée'}
- Version: ${OUTIL.version || 'Non renseignée'}
- Catégorie ID: ${OUTIL.id_categorie}
- Champs invalides à corriger: ${champsInvalides.join(', ')}
- Catégories disponibles: ${catList}

JSON attendu (null pour les champs NON invalides) :
{
  "nom": null,
  "description": null,
  "url": null,
  "logo_url": null,
  "version": null,
  "categorie_id": null,
  "explications": {
    "nom": null,
    "description": null,
    "url": null,
    "logo_url": null,
    "version": null,
    "categorie": null
  }
}`
                }
            ]
        })
    });

    if (!res.ok) throw new Error(`Erreur HTTP ${res.status}`);
    const d   = await res.json();
    const raw = d?.choices?.[0]?.message?.content ?? '';
    if (!raw) throw new Error('Réponse vide de l\'IA.');
    const clean = raw.replace(/```json|```/gi,'').trim();
    return JSON.parse(clean);
}

function afficherCorrectionFields(champsInvalides, champsValides, corrections) {
    document.getElementById('correctionLoading').classList.add('hidden');
    const container = document.getElementById('correctionFieldsContainer');
    container.innerHTML = '';

    /* Champs invalides → correction IA éditable */
    for (const champ of champsInvalides) {
        const def = REQUIRED_FIELDS[champ];
        if (!def) continue;

        let corrVal = champ === 'categorie'
            ? (corrections.categorie_id ? String(corrections.categorie_id) : def.getValue())
            : (corrections[champ] || def.getValue());
        const expl    = corrections.explications?.[champ] ?? '';
        const origVal = def.getValue();

        let inputHtml = '';
        if (def.type === 'textarea') {
            inputHtml = `<textarea class="field-input" name="${def.inputName}" form="formCorrection" rows="5">${escHtml(corrVal)}</textarea>`;
        } else if (def.type === 'select') {
            let opts = '';
            for (const [catId, catName] of Object.entries(CATEGORIES)) {
                const sel = (corrections.categorie_id && parseInt(catId) === corrections.categorie_id)
                    ? 'selected'
                    : (catId == OUTIL.id_categorie ? 'selected' : '');
                opts += `<option value="${catId}" ${sel}>${escHtml(catName)}</option>`;
            }
            inputHtml = `<select class="field-input" name="${def.inputName}" form="formCorrection" style="cursor:pointer">${opts}</select>`;
        } else {
            inputHtml = `<input type="text" class="field-input" name="${def.inputName}" form="formCorrection" value="${escHtml(corrVal)}">`;
        }

        container.innerHTML += `
        <div class="field-row">
          <div class="field-row-header field-row-header-err">
            <label>${def.label}</label>
            <span class="field-badge-err">&#x2717; À corriger</span>
            <span class="field-badge-req">Obligatoire</span>
          </div>
          <div class="field-row-body">
            <div class="field-original">
              <strong>Valeur actuelle :</strong> <em>${escHtml(origVal || 'Non renseignée')}</em>
              ${expl ? `<br><strong style="color:#dc2626">Problème :</strong> ${escHtml(expl)}` : ''}
            </div>
            <div class="ai-suggestion-label">&#x2728; Correction IA suggérée (modifiable) :</div>
            ${inputHtml}
          </div>
        </div>`;
    }

    /* Champs valides → lecture seule avec valeur conservée */
    for (const champ of champsValides) {
        const def = REQUIRED_FIELDS[champ];
        if (!def) continue;
        const origVal    = def.getValue();
        const displayVal = champ === 'categorie' && OUTIL.id_categorie
            ? (CATEGORIES[OUTIL.id_categorie] || origVal)
            : origVal;
        container.innerHTML += `
        <div class="field-row">
          <div class="field-row-header field-row-header-ok">
            <label>${def.label}</label>
            <span class="field-badge-ok">&#x2713; Correct</span>
            <span class="field-badge-req">Obligatoire</span>
          </div>
          <div class="field-row-body" style="background:#f8fffa">
            <div class="field-original" style="background:#fff;border-color:#86efac">${escHtml(displayVal || '—')}</div>
            <input type="hidden" name="${def.inputName}" form="formCorrection" value="${escHtml(origVal)}">
          </div>
        </div>`;
    }

    /* Informations optionnelles */
    let optHtml = '';
    if (OUTIL.avantages.length || OUTIL.inconvenients.length) {
        optHtml += `<div class="optional-item"><span class="optional-item-label">Avantages</span><span>
            ${OUTIL.avantages.map(a=>`<span class="pill pill-av">&#x2713; ${escHtml(a.description.slice(0,60))}</span>`).join(' ') || '—'}
            </span></div>`;
        optHtml += `<div class="optional-item"><span class="optional-item-label">Inconvénients</span><span>
            ${OUTIL.inconvenients.map(a=>`<span class="pill pill-inc">&#x2717; ${escHtml(a.description.slice(0,60))}</span>`).join(' ') || '—'}
            </span></div>`;
        optHtml += `<div style="font-size:.73rem;color:#64748b;margin-top:6px">ℹ️ Conservés tels quels — non modifiables dans cette interface.</div>`;
    }
    if (OUTIL.perf) {
        optHtml += `<div class="optional-item" style="margin-top:8px">
            <span class="optional-item-label">Performance</span>
            <span style="font-size:.78rem">Rapidité: ${OUTIL.perf.rapidite} | Qualité: ${OUTIL.perf.qualite} | Crédibilité: ${OUTIL.perf.credibilite} | Score: ${OUTIL.perf.score_global}</span>
            </div>`;
    }
    if (optHtml) {
        document.getElementById('optionalInfoContent').innerHTML = optHtml;
        document.getElementById('optionalInfoSection').classList.remove('hidden');
    }

    document.getElementById('btnApplyCorrections').disabled = false;
    document.getElementById('correctionNote').innerHTML =
        `<span style="color:#16a34a;font-weight:700">&#x2713; ${champsInvalides.length} champ(s) à corriger — vérifiez puis publiez</span>`;
}

/* ════════════════════════════════════════════
   Utilitaires
════════════════════════════════════════════ */
function escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function setState(s) {
    document.getElementById('statePlaceholder').classList.toggle('hidden', s !== 'idle');
    document.getElementById('stateLoading').classList.toggle('hidden',     s !== 'loading');
    document.getElementById('stateResult').classList.toggle('hidden',      s !== 'result');
}

function validateRefusForm(event) {
    const cause = document.getElementById('refusalCause').value.trim();
    const errEl = document.getElementById('refusalError');
    if (!cause) {
        errEl.classList.remove('hidden');
        event.preventDefault();
        return false;
    }
    errEl.classList.add('hidden');
    return confirm('Confirmer le refus de cet outil ?\n\nMotif : ' + cause.slice(0, 120) + (cause.length > 120 ? '…' : ''));
}

function afficherErreur(msg) {
    setState('idle');
    document.getElementById('statePlaceholder').innerHTML = `
      <svg width="32" height="32" fill="none" stroke="#dc2626" stroke-width="2" viewBox="0 0 24 24" style="margin-bottom:6px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <p style="font-weight:700;color:#dc2626;margin:0">Erreur d'analyse IA</p>
      <p style="font-size:.8rem;max-width:400px;color:var(--adm-muted);line-height:1.5">${escHtml(msg)}</p>
      <button onclick="lancerVerification()"
        style="margin-top:8px;padding:7px 16px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;font-size:.82rem;font-family:inherit;font-weight:600">
        ↺ Réessayer
      </button>`;
}
</script>
</body>
</html>