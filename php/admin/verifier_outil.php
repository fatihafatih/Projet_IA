<?php
require_once '../../includes/auth_admin.php';
require_once '../../includes/connexionbd.php';
require_once './notif_helper.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: notifications.php'); exit; }

/* ── Récupérer l'outil + infos créateur ── */
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

$hasRefusalCause = false;
try {
    $schemaStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'outils_ia' AND COLUMN_NAME = 'refusal_cause'"
    );
    $schemaStmt->execute();
    $hasRefusalCause = (bool)$schemaStmt->fetchColumn();
} catch (PDOException $e) {
    $hasRefusalCause = false;
}

/* ── Actions POST : valider / refuser ── */
$action = $_POST['action'] ?? '';
$cause = trim($_POST['refusal_cause'] ?? '');
if ($action === 'valider') {
    $pdo->prepare("UPDATE outils_ia SET status='actif', ID_USERS_VALID=? WHERE ID_OUTILS_IA=?")
        ->execute([$_SESSION['user_id'], $id]);
    if ($outil['ID_USERS_CREATE'])
        notifierValidation($pdo, (int)$outil['ID_USERS_CREATE'], $id, $outil['nom']);
    header('Location: notifications.php?msg='.urlencode('✅ Outil validé — notification envoyée.').'&type=success');
    exit;
}
if ($action === 'refuser') {
    if ($hasRefusalCause) {
        $pdo->prepare("UPDATE outils_ia SET status='inactif', refusal_cause=? WHERE ID_OUTILS_IA=?")
            ->execute([$cause ?: null, $id]);
    } else {
        $pdo->prepare("UPDATE outils_ia SET status='inactif' WHERE ID_OUTILS_IA=?")
            ->execute([$id]);
    }
    if ($outil['ID_USERS_CREATE'])
        notifierRefus($pdo, (int)$outil['ID_USERS_CREATE'], $id, $outil['nom'], $cause);
    header('Location: notifications.php?msg='.urlencode('⛔ Outil refusé — notification envoyée.').'&type=warning');
    exit;
}

/* ── Avantages / inconvénients ── */
$avStmt = $pdo->prepare("SELECT * FROM avantages_inconvenients WHERE ID_OUTILS_IA=?");
$avStmt->execute([$id]);
$avants = $avStmt->fetchAll(PDO::FETCH_ASSOC);

/* ── Performance existante ── */
$perfStmt = $pdo->prepare("SELECT * FROM performance WHERE ID_OUTILS_IA=? LIMIT 1");
$perfStmt->execute([$id]);
$perf = $perfStmt->fetch(PDO::FETCH_ASSOC);

/* ── Modèles associés ── */
$modStmt = $pdo->prepare("
    SELECT m.name FROM tool_models tm
    JOIN models m ON tm.ID_MODEL = m.ID_MODEL
    WHERE tm.ID_OUTILS_IA = ?
");
$modStmt->execute([$id]);
$models = $modStmt->fetchAll(PDO::FETCH_COLUMN);
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
.ver-wrap{max-width:960px;margin:0 auto;padding:24px 0 48px}

/* Breadcrumb */
.ver-breadcrumb{display:flex;align-items:center;gap:6px;font-size:.78rem;
                color:var(--adm-muted,#64748b);margin-bottom:20px}
.ver-breadcrumb a{color:var(--adm-blue,#3b82f6);text-decoration:none}
.ver-breadcrumb a:hover{text-decoration:underline}

/* Carte outil */
.outil-card{background:var(--adm-surface,#fff);border:1px solid var(--adm-border,#e2e8f0);
            border-radius:16px;padding:24px;display:flex;gap:20px;
            align-items:flex-start;margin-bottom:24px}
.outil-logo{width:72px;height:72px;object-fit:contain;border-radius:12px;
            border:1px solid var(--adm-border,#e2e8f0);background:#f8fafc;padding:6px;flex-shrink:0}
.outil-logo-ph{width:72px;height:72px;border-radius:12px;background:var(--adm-hover,#f1f5f9);
               display:flex;align-items:center;justify-content:center;font-size:2rem;flex-shrink:0}
.outil-name{font-size:1.3rem;font-weight:800;color:var(--adm-text,#1e293b);margin-bottom:4px}
.outil-meta{display:flex;flex-wrap:wrap;gap:10px;font-size:.78rem;
            color:var(--adm-muted);margin-bottom:10px}
.outil-desc{font-size:.84rem;color:var(--adm-muted);line-height:1.55}
.badge-status{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.7rem;
              font-weight:700;letter-spacing:.03em;text-transform:uppercase}
.bs-en_attente{background:#fef3c7;color:#92400e}
.bs-actif{background:#dcfce7;color:#166534}
.bs-inactif{background:#fee2e2;color:#991b1b}

/* Section vérif */
.ver-section{background:var(--adm-surface,#fff);border:1px solid var(--adm-border,#e2e8f0);
             border-radius:16px;overflow:hidden;margin-bottom:24px}
.ver-section-head{padding:16px 24px;border-bottom:1px solid var(--adm-border,#e2e8f0);
                  display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.ver-section-title{font-size:.9rem;font-weight:700;color:var(--adm-text,#1e293b)}
.ver-section-body{padding:20px 24px}

/* Badge OpenAI */
.badge-openai{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;
              background:#f0fdf4;border:1px solid #86efac;border-radius:20px;
              font-size:.72rem;font-weight:700;color:#15803d}

/* Bouton re-vérifier */
.btn-verify{display:inline-flex;align-items:center;gap:8px;padding:10px 22px;
            background:linear-gradient(135deg,#10b981,#059669);color:#fff;
            border:none;border-radius:10px;font-size:.85rem;font-weight:700;
            cursor:pointer;transition:opacity .15s,transform .1s;font-family:inherit}
.btn-verify:hover:not(:disabled){opacity:.9;transform:translateY(-1px)}
.btn-verify:active{transform:scale(.98)}
.btn-verify:disabled{opacity:.5;cursor:not-allowed;transform:none}
@keyframes spin{to{transform:rotate(360deg)}}
.spin{animation:spin 1s linear infinite}

/* Score ring */
.score-wrapper{display:flex;flex-direction:column;align-items:center;gap:4px;margin-bottom:24px}
.score-ring-container{position:relative;width:140px;height:140px}
.score-ring-container svg{width:140px;height:140px}
.score-number{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
              font-size:2rem;font-weight:800;line-height:1}
.score-label{font-size:.78rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;margin-top:6px}
.score-pass{color:#16a34a}.score-warn{color:#d97706}.score-fail{color:#dc2626}

/* Critères */
.criteria-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:10px;margin-bottom:20px}
.crit-card{border:1px solid var(--adm-border,#e2e8f0);border-radius:10px;
           padding:12px 14px;background:var(--adm-hover,#f8fafc)}
.crit-label{font-size:.7rem;font-weight:600;color:var(--adm-muted);
            text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px}
.crit-bar-bg{height:6px;background:var(--adm-border,#e2e8f0);border-radius:3px;overflow:hidden;margin-bottom:4px}
.crit-bar-fill{height:100%;border-radius:3px;transition:width 1s ease}
.crit-score{font-size:.8rem;font-weight:700}

/* Verdict */
.verdict-box{border-radius:12px;padding:16px 20px;margin-bottom:20px;font-size:.85rem;line-height:1.55}
.verdict-pass{background:#f0fdf4;border:1px solid #86efac;color:#14532d}
.verdict-fail{background:#fff1f2;border:1px solid #fecdd3;color:#881337}
.verdict-warn{background:#fffbeb;border:1px solid #fde68a;color:#78350f}

/* Analyse */
.analysis-text{font-size:.84rem;line-height:1.65;color:var(--adm-text,#1e293b);white-space:pre-wrap}

/* Bannière refus automatique */
.auto-refus-banner{
    display:flex;align-items:center;gap:10px;
    background:#fff1f2;border:1px solid #fecdd3;color:#881337;
    border-radius:12px;padding:14px 20px;margin-bottom:16px;
    font-size:.87rem;font-weight:600;
}

/* Barre progression countdown */
.countdown-bar-bg{flex:1;height:6px;background:#fecdd3;border-radius:3px;overflow:hidden;min-width:100px}
.countdown-bar-fill{height:100%;background:#dc2626;border-radius:3px;
                    transition:width .1s linear}

/* Action bar */
.action-bar{display:flex;gap:12px;align-items:center;padding:20px 24px;
            background:var(--adm-hover,#f8fafc);border-top:1px solid var(--adm-border,#e2e8f0);flex-wrap:wrap}
.btn-val-lg{padding:11px 28px;background:#16a34a;color:#fff;border:none;border-radius:10px;
            font-size:.9rem;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s,transform .1s}
.btn-val-lg:hover:not(:disabled){background:#15803d;transform:translateY(-1px)}
.btn-val-lg:disabled{opacity:.35;cursor:not-allowed}
.btn-ref-lg{padding:11px 28px;background:#dc2626;color:#fff;border:none;border-radius:10px;
            font-size:.9rem;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s,transform .1s}
.btn-ref-lg:hover:not(:disabled){background:#b91c1c;transform:translateY(-1px)}
.btn-ref-lg:disabled{opacity:.35;cursor:not-allowed}
.btn-back{padding:10px 18px;background:transparent;color:var(--adm-muted);
          border:1px solid var(--adm-border,#e2e8f0);border-radius:10px;font-size:.85rem;
          font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;
          align-items:center;gap:6px;transition:background .15s;font-family:inherit}
.btn-back:hover{background:var(--adm-hover,#f1f5f9)}
.action-note{font-size:.75rem;color:var(--adm-muted);flex:1;text-align:right;min-width:200px}

/* Placeholder */
.result-placeholder{display:flex;flex-direction:column;align-items:center;
                    justify-content:center;padding:40px 20px;color:var(--adm-muted);
                    text-align:center;gap:10px}
.result-placeholder svg{opacity:.3}

/* Dots loader */
.dot-loader{display:inline-flex;gap:4px;align-items:center}
.dot-loader span{width:6px;height:6px;border-radius:50%;background:currentColor;opacity:.5;
                 animation:dotpulse 1.2s ease-in-out infinite}
.dot-loader span:nth-child(2){animation-delay:.2s}
.dot-loader span:nth-child(3){animation-delay:.4s}
@keyframes dotpulse{0%,80%,100%{transform:scale(.8);opacity:.4}40%{transform:scale(1.2);opacity:1}}

/* Pills */
.ai-pills{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}
.pill{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;
      border-radius:20px;font-size:.73rem;font-weight:600}
.pill-av{background:#dcfce7;color:#166534}
.pill-inc{background:#fee2e2;color:#991b1b}

/* Tags modèles */
.model-tag{display:inline-block;padding:2px 8px;border-radius:20px;font-size:.7rem;
           font-weight:600;background:#f1f5f9;color:#475569;margin:2px}

.refusal-error{margin-top:8px;color:#b91c1c;font-size:.82rem;line-height:1.4}
.hidden{display:none!important}
</style>
</head>
<body class="adm-body">
<?php include 'sidebar.php'; ?>

<div class="adm-main">
<div class="ver-wrap">

  <!-- Breadcrumb -->
  <div class="ver-breadcrumb">
    <a href="notifications.php">Notifications</a>
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
    <span>Vérification IA</span>
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
    <strong><?=htmlspecialchars($outil['nom'])?></strong>
  </div>

  <div class="adm-topbar" style="margin-bottom:20px">
    <div>
      <h1 class="adm-title">Vérification de l'outil</h1>
      <p class="adm-sub">L'analyse IA se lance automatiquement. Si le score est inférieur à 50 %, l'outil est refusé automatiquement.</p>
    </div>
    <span class="badge-openai">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
        <path d="M22.28 9.29a5.86 5.86 0 0 0-.5-4.83 6 6 0 0 0-6.45-2.87 5.86 5.86 0 0 0-4.41-2 6 6 0 0 0-5.72 4.15 5.88 5.88 0 0 0-3.93 2.84 6 6 0 0 0 .74 7.03 5.85 5.85 0 0 0 .5 4.83 6 6 0 0 0 6.45 2.87 5.85 5.85 0 0 0 4.41 2 6 6 0 0 0 5.73-4.16 5.87 5.87 0 0 0 3.92-2.84 6 6 0 0 0-.74-7.02zm-8.78 12.3a4.47 4.47 0 0 1-2.87-1.04l.14-.08 4.76-2.75a.79.79 0 0 0 .4-.69v-6.7l2.01 1.16a.07.07 0 0 1 .04.06v5.56a4.51 4.51 0 0 1-4.48 4.48zm-9.63-4.13a4.47 4.47 0 0 1-.54-3.01l.14.09 4.76 2.75a.78.78 0 0 0 .79 0l5.82-3.36v2.33a.08.08 0 0 1-.03.07L9.97 19.6a4.51 4.51 0 0 1-6.1-2.14zm-1.25-10.4a4.47 4.47 0 0 1 2.34-1.97v5.67a.77.77 0 0 0 .39.68l5.81 3.35-2.01 1.16a.08.08 0 0 1-.08 0L4.3 12.6a4.51 4.51 0 0 1-.68-5.54zm16.53 3.86-5.82-3.36 2.01-1.16a.08.08 0 0 1 .08 0l4.77 2.76a4.5 4.5 0 0 1-.7 8.12V11.6a.79.79 0 0 0-.34-.68zm2-3.02-.14-.09-4.75-2.76a.79.79 0 0 0-.8 0L9.63 8.41V6.08a.08.08 0 0 1 .03-.07l4.77-2.75a4.51 4.51 0 0 1 6.7 4.67zm-12.6 4.14-2.02-1.16a.08.08 0 0 1-.04-.06V5.46a4.51 4.51 0 0 1 7.39-3.46l-.14.08-4.76 2.75a.79.79 0 0 0-.4.69zm1.1-2.36 2.59-1.5 2.59 1.49v2.98l-2.59 1.5-2.59-1.5z"/>
      </svg>
      Analyse automatique
    </span>
  </div>

  <!-- ══ Carte outil ══ -->
  <div class="outil-card">
    <?php if(!empty($outil['logo_url'])): ?>
      <img src="<?=htmlspecialchars($outil['logo_url'])?>" class="outil-logo" alt=""
           onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
      <div class="outil-logo-ph" style="display:none">🤖</div>
    <?php else: ?>
      <div class="outil-logo-ph">🤖</div>
    <?php endif; ?>

    <div style="flex:1;min-width:0">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;flex-wrap:wrap">
        <div class="outil-name"><?=htmlspecialchars($outil['nom'])?></div>
        <span class="badge-status bs-<?=htmlspecialchars($outil['status'])?>"><?=htmlspecialchars($outil['status'])?></span>
        <?php if($outil['version']): ?>
          <span style="font-size:.73rem;background:#f1f5f9;padding:2px 8px;border-radius:20px;color:#475569">
            v<?=number_format((float)$outil['version'],2)?>
          </span>
        <?php endif; ?>
      </div>

      <div class="outil-meta">
        <?php if($outil['cat_name']): ?>
          <span>📂 <?=htmlspecialchars($outil['cat_name'])?></span>
        <?php endif; ?>
        <?php if($outil['url']): ?>
          <span>🌐 <a href="<?=htmlspecialchars($outil['url'])?>" target="_blank"
               style="color:var(--adm-blue);text-decoration:none"><?=htmlspecialchars($outil['url'])?></a></span>
        <?php endif; ?>
        <?php if($outil['cr_nom']): ?>
          <span>👤 <strong><?=htmlspecialchars($outil['cr_nom'])?></strong>
            &lt;<?=htmlspecialchars($outil['cr_email']??'')?>&gt;
            — <?=htmlspecialchars($outil['cr_role']??'')?></span>
        <?php endif; ?>
        <?php if((float)$outil['global_rating']>0): ?>
          <span>⭐ <?=number_format((float)$outil['global_rating'],2)?>/5</span>
        <?php endif; ?>
      </div>

      <?php if(!empty($outil['description'])): ?>
        <div class="outil-desc"><?=htmlspecialchars($outil['description'])?></div>
      <?php endif; ?>

      <?php if(!empty($models)): ?>
        <div style="margin-top:10px">
          <?php foreach($models as $m): ?>
            <span class="model-tag"><?=htmlspecialchars($m)?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if(!empty($avants)): ?>
        <div class="ai-pills">
          <?php foreach($avants as $a): ?>
            <span class="pill pill-<?=$a['type']==='avantage'?'av':'inc'?>">
              <?=$a['type']==='avantage'?'✓':'✗'?>
              <?=htmlspecialchars(mb_substr($a['description'],0,55))?>
            </span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ══ Section vérification IA ══ -->
  <div class="ver-section">
    <div class="ver-section-head">
      <div class="ver-section-title">🤖 Vérification automatique par IA</div>
      <button class="btn-verify" id="btnVerify" onclick="lancerVerification()" disabled>
        <svg id="spinIcon" width="16" height="16" fill="none" stroke="currentColor"
             stroke-width="2.5" viewBox="0 0 24 24">
          <polyline points="1 4 1 10 7 10"/>
          <path d="M3.51 15a9 9 0 1 0 .49-4.36"/>
        </svg>
        Re-vérifier
      </button>
    </div>

    <div class="ver-section-body">

      <!-- Chargement (affiché en premier car auto-lancement) -->
      <div id="stateLoading" class="result-placeholder">
        <div class="dot-loader" style="color:#10b981;margin-bottom:12px">
          <span></span><span></span><span></span>
        </div>
        <p style="font-weight:700;font-size:.9rem;margin-bottom:4px">Analyse IA en cours…</p>
        <p style="font-size:.8rem;color:var(--adm-muted)" id="loadingMsg">Vérification du nom et de la description…</p>
      </div>

      <!-- État idle (après erreur ou re-vérification) -->
      <div id="statePlaceholder" class="result-placeholder hidden">
        <svg width="56" height="56" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          <path d="M9 12l2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <p style="font-weight:700;font-size:.9rem;margin-bottom:4px">Aucune vérification lancée</p>
        <p style="font-size:.8rem">
          Cliquez sur <strong>Re-vérifier</strong> pour relancer l'analyse IA.
        </p>
      </div>

      <!-- Résultat -->
      <div id="stateResult" class="hidden">

        <!-- Jauge -->
        <div class="score-wrapper">
          <div class="score-ring-container">
            <svg viewBox="0 0 140 140">
              <circle cx="70" cy="70" r="54" fill="none" stroke="#e2e8f0" stroke-width="12"/>
              <circle cx="70" cy="70" r="54"
                      id="scoreCircle"
                      fill="none"
                      stroke="#16a34a"
                      stroke-width="12"
                      stroke-linecap="round"
                      stroke-dasharray="339.3"
                      stroke-dashoffset="339.3"
                      transform="rotate(-90 70 70)"
                      style="transition:stroke-dashoffset 1.3s ease,stroke .4s ease"/>
            </svg>
            <div class="score-number" id="scoreNumber" style="color:#16a34a">—</div>
          </div>
          <div class="score-label" id="scoreLabel">En attente d'analyse</div>
        </div>

        <!-- Critères -->
        <div class="criteria-grid" id="criteriaGrid"></div>

        <!-- Verdict -->
        <div id="verdictBox" class="verdict-box verdict-warn"></div>

        <!-- Analyse détaillée -->
        <details style="margin-bottom:16px">
          <summary style="cursor:pointer;font-size:.82rem;font-weight:600;
                          color:#10b981;user-select:none;padding:4px 0">
            ▸ Voir l'analyse complète de l'IA
          </summary>
          <div style="margin-top:12px;padding:16px;background:var(--adm-hover,#f8fafc);
                      border-radius:10px;border:1px solid var(--adm-border,#e2e8f0)">
            <div class="analysis-text" id="analysisText"></div>
          </div>
        </details>

      </div><!-- /stateResult -->
    </div>

    <!-- ══ Bannière refus automatique (score < 50) ══ -->
    <div id="autoRefusBanner" class="auto-refus-banner hidden" style="margin:0 24px 16px">
      <span>⛔</span>
      <span>Score inférieur à 50 % — <strong>Refus automatique dans <span id="countdownSec">5</span> secondes…</strong></span>
      <div class="countdown-bar-bg">
        <div class="countdown-bar-fill" id="countdownBar" style="width:100%"></div>
      </div>
      <button onclick="annulerRefusAuto()"
              style="margin-left:8px;padding:5px 12px;background:#fff;border:1px solid #fecdd3;
                     border-radius:8px;color:#881337;font-size:.78rem;font-weight:700;
                     cursor:pointer;white-space:nowrap;font-family:inherit">
        ✕ Annuler
      </button>
    </div>

    <!-- ══ Barre d'actions (score ≥ 50 uniquement) ══ -->
    <div class="action-bar" id="actionBar" style="display:none;flex-wrap:wrap;align-items:center">
      <a href="notifications.php" class="btn-back">← Retour</a>

      <form method="POST" style="display:inline"
            onsubmit="return confirm('Valider cet outil et notifier le créateur ?')">
        <input type="hidden" name="action" value="valider">
        <button type="submit" class="btn-val-lg" id="btnValider" disabled>✓ Valider l'outil</button>
      </form>

      <form method="POST" style="display:inline" id="formRefuser"
            onsubmit="return validateRefus(event)">
        <input type="hidden" name="action" value="refuser">
        <button type="submit" class="btn-ref-lg" id="btnRefuser" disabled>✗ Refuser</button>
      </form>

      <div class="action-note" id="actionNote">Analyse en cours…</div>
    </div>

    <div id="refusalField" style="display:none;margin:14px 24px 0;max-width:640px;">
      <label for="refusalCause" style="font-size:.85rem;font-weight:700;color:var(--adm-text,#1e293b);margin-bottom:6px;display:block;">Motif du refus (obligatoire)</label>
      <textarea id="refusalCause" name="refusal_cause" form="formRefuser" rows="3" placeholder="Expliquez le motif du refus pour que le créateur puisse s'ajuster." style="width:100%;min-height:96px;padding:10px;border:1px solid var(--adm-border,#e2e8f0);border-radius:12px;font-size:.9rem;resize:vertical;color:var(--adm-text,#1e293b)"></textarea>
      <div id="refusalError" class="refusal-error hidden">Veuillez remplir le motif du refus.</div>
    </div>
  </div><!-- /ver-section -->

</div>
</div>

<script>
/* ════════ Données PHP → JS ════════ */
const OUTIL = {
    nom:           <?=json_encode($outil['nom'])?>,
    description:   <?=json_encode($outil['description']??'')?>,
    url:           <?=json_encode($outil['url']??'')?>,
    categorie:     <?=json_encode($outil['cat_name']??'')?>,
    version:       <?=json_encode((string)($outil['version']??''))?>,
    rating:        <?=json_encode((float)($outil['global_rating']??0))?>,
    cr_nom:        <?=json_encode($outil['cr_nom']??'')?>,
    cr_role:       <?=json_encode($outil['cr_role']??'')?>,
    avantages:     <?=json_encode(array_values(array_filter($avants,fn($a)=>$a['type']==='avantage')))?>,
    inconvenients: <?=json_encode(array_values(array_filter($avants,fn($a)=>$a['type']==='inconvenient')))?>,
    models:        <?=json_encode($models)?>,
    perf:          <?=json_encode($perf?:null)?>
};

/* ════════ Countdown refus auto ════════ */
let _countdownTimer  = null;
let _countdownCancel = false;

function demarrerCountdownRefus() {
    const DUREE = 5; // secondes
    let restant = DUREE;

    document.getElementById('autoRefusBanner').classList.remove('hidden');
    document.getElementById('countdownSec').textContent = restant;
    document.getElementById('countdownBar').style.width  = '100%';

    _countdownCancel = false;

    _countdownTimer = setInterval(() => {
        if (_countdownCancel) { clearInterval(_countdownTimer); return; }
        restant--;
        document.getElementById('countdownSec').textContent = restant;
        document.getElementById('countdownBar').style.width = (restant / DUREE * 100) + '%';
        if (restant <= 0) {
            clearInterval(_countdownTimer);
            if (!_countdownCancel) {
                document.getElementById('refusalCause').value = 'Refus automatique : score IA inférieur à 50 %.';
                document.getElementById('formRefuser').submit();
            }
        }
    }, 1000);
}

function annulerRefusAuto() {
    _countdownCancel = true;
    clearInterval(_countdownTimer);
    document.getElementById('autoRefusBanner').classList.add('hidden');
    /* Laisser l'admin choisir manuellement */
    document.getElementById('btnValider').disabled = true; // score < 50, pas de validation
    document.getElementById('btnRefuser').disabled = false;
    document.getElementById('actionBar').style.display = 'flex';
    document.getElementById('actionNote').innerHTML =
        '<span style="color:#d97706;font-weight:700">⚠ Score &lt; 50 % — Refus recommandé (annulé manuellement)</span>';
}

/* ════════ Auto-lancement au chargement ════════ */
window.addEventListener('DOMContentLoaded', () => {
    const causeEl = document.getElementById('refusalCause');
    if (causeEl) {
        causeEl.addEventListener('input', () => {
            document.getElementById('refusalError').classList.add('hidden');
        });
    }
    lancerVerification();
});

/* ════════ Lancer vérification ════════ */
async function lancerVerification() {
    const btn = document.getElementById('btnVerify');
    btn.disabled = true;
    document.getElementById('spinIcon').classList.add('spin');

    setState('loading');

    const msgs = [
        'Vérification du nom et de la description…',
        'Analyse de l\'URL et de la catégorie…',
        'Évaluation des avantages et inconvénients…',
        'Comparaison avec les données de référence…',
        'Calcul du score de compatibilité…'
    ];
    let mi = 0;
    const msgEl = document.getElementById('loadingMsg');
    const iv = setInterval(() => { msgEl.textContent = msgs[++mi % msgs.length]; }, 2500);

    try {
        const result = await callOpenAI();
        clearInterval(iv);
        afficherResultat(result);
    } catch(err) {
        clearInterval(iv);
        afficherErreur(err.message);
    } finally {
        btn.disabled = false;
        document.getElementById('spinIcon').classList.remove('spin');
    }
}

/* ════════ Appel API via proxy PHP ════════ */
async function callOpenAI() {
    const av  = OUTIL.avantages.map(a => '  + ' + a.description).join('\n') || '  (aucun)';
    const inc = OUTIL.inconvenients.map(a => '  - ' + a.description).join('\n') || '  (aucun)';
    const mods = OUTIL.models.length ? OUTIL.models.join(', ') : 'Non renseignés';
    const perfTxt = OUTIL.perf
        ? `Rapidité=${OUTIL.perf.rapidite} | Qualité=${OUTIL.perf.qualite} | Crédibilité=${OUTIL.perf.credibilite} | Score global=${OUTIL.perf.score_global}`
        : 'Pas de données de performance';

    const systemPrompt = `Tu es un expert en outils d'intelligence artificielle chargé de vérifier la cohérence des informations soumises par un utilisateur sur une plateforme de référencement d'outils IA. Tu réponds UNIQUEMENT en JSON valide, sans markdown, sans backticks, sans texte avant ou après.`;

    const userPrompt = `Analyse les informations suivantes soumises pour un outil IA et retourne un objet JSON.

## INFORMATIONS SOUMISES :
- Nom de l'outil       : ${OUTIL.nom}
- Description          : ${OUTIL.description || 'Non renseignée'}
- URL officielle       : ${OUTIL.url || 'Non renseignée'}
- Catégorie déclarée   : ${OUTIL.categorie || 'Non renseignée'}
- Version déclarée     : ${OUTIL.version || 'Non renseignée'}
- Note globale déclarée: ${OUTIL.rating}/5
- Modèles associés     : ${mods}
- Avantages déclarés :
${av}
- Inconvénients déclarés :
${inc}
- Données de performance : ${perfTxt}
- Soumis par           : ${OUTIL.cr_nom} (rôle: ${OUTIL.cr_role})

## INSTRUCTIONS :
Analyse la cohérence et la qualité selon 5 critères (score 0–100 chacun) :
- coherence_nom_description : Le nom et la description sont-ils cohérents et descriptifs ?
- validite_url               : L'URL semble-t-elle valide et correspondre à l'outil décrit ?
- pertinence_categorie       : La catégorie est-elle correcte par rapport à l'outil ?
- qualite_description        : La description est-elle suffisamment détaillée et professionnelle ?
- coherence_avantages        : Les avantages et inconvénients sont-ils réalistes et cohérents ?

Retourne UNIQUEMENT ce JSON (pas de markdown, pas de backticks) :
{
  "score_global": <nombre 0-100>,
  "criteres": {
    "coherence_nom_description": <nombre 0-100>,
    "validite_url": <nombre 0-100>,
    "pertinence_categorie": <nombre 0-100>,
    "qualite_description": <nombre 0-100>,
    "coherence_avantages": <nombre 0-100>
  },
  "verdict": "<phrase courte résumant la qualité globale>",
  "recommandation": "valider" ou "refuser",
  "analyse": "<3 à 5 phrases d'analyse détaillée en français>"
}`;

    const res = await fetch('openai_proxy.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            model: 'llama-3.3-70b-versatile',
            max_tokens: 1000,
            temperature: 0.2,
            messages: [
                { role: 'system', content: systemPrompt },
                { role: 'user',   content: userPrompt   }
            ]
        })
    });

    if (!res.ok) {
        const e = await res.json().catch(() => ({}));
        throw new Error(e.error?.message || `Erreur HTTP ${res.status}`);
    }

    const data = await res.json();
    const raw  = data?.choices?.[0]?.message?.content ?? '';
    if (!raw) throw new Error('Réponse vide de l\'API.');

    const clean = raw.replace(/```json|```/gi, '').trim();
    try {
        return JSON.parse(clean);
    } catch {
        throw new Error('Réponse non parsable. Début : ' + raw.slice(0, 300));
    }
}

/* ════════ Afficher résultat ════════ */
function afficherResultat(data) {
    setState('result');

    const score       = Math.min(100, Math.max(0, Math.round(data.score_global ?? 0)));
    const scoreOk     = score >= 50;
    const couleur     = score >= 70 ? '#16a34a' : score >= 50 ? '#d97706' : '#dc2626';
    const circumference = 339.3;

    /* Jauge animée */
    const circle = document.getElementById('scoreCircle');
    circle.style.stroke = couleur;
    setTimeout(() => {
        circle.style.strokeDashoffset = circumference - (score / 100) * circumference;
    }, 80);

    const numEl = document.getElementById('scoreNumber');
    numEl.style.color = couleur;
    let cur = 0;
    const ctr = setInterval(() => {
        cur = Math.min(cur + Math.ceil(score / 35), score);
        numEl.textContent = cur + '%';
        if (cur >= score) clearInterval(ctr);
    }, 28);

    /* Label global */
    const lbl = document.getElementById('scoreLabel');
    if (score >= 70)      { lbl.textContent = '✓ Très bonne compatibilité';    lbl.className = 'score-label score-pass'; }
    else if (score >= 50) { lbl.textContent = '⚠ Compatibilité acceptable';    lbl.className = 'score-label score-warn'; }
    else                  { lbl.textContent = '✗ Compatibilité insuffisante';  lbl.className = 'score-label score-fail'; }

    /* Critères */
    const labels = {
        coherence_nom_description : 'Cohérence nom / desc.',
        validite_url               : 'Validité de l\'URL',
        pertinence_categorie       : 'Pertinence catégorie',
        qualite_description        : 'Qualité description',
        coherence_avantages        : 'Cohérence avantages'
    };
    const grid = document.getElementById('criteriaGrid');
    grid.innerHTML = '';
    for (const [k, v] of Object.entries(data.criteres ?? {})) {
        const val = Math.round(v);
        const c   = val >= 70 ? '#16a34a' : val >= 50 ? '#d97706' : '#dc2626';
        grid.innerHTML += `
        <div class="crit-card">
          <div class="crit-label">${labels[k] || k}</div>
          <div class="crit-bar-bg">
            <div class="crit-bar-fill" style="width:0%;background:${c}" data-w="${val}"></div>
          </div>
          <div class="crit-score" style="color:${c}">${val}%</div>
        </div>`;
    }
    setTimeout(() => {
        grid.querySelectorAll('.crit-bar-fill').forEach(b => b.style.width = b.dataset.w + '%');
    }, 120);

    /* Verdict */
    const vb = document.getElementById('verdictBox');
    vb.textContent = data.verdict ?? '';
    vb.className   = 'verdict-box ' + (score >= 70 ? 'verdict-pass' : score >= 50 ? 'verdict-warn' : 'verdict-fail');

    /* Analyse détaillée */
    document.getElementById('analysisText').textContent = data.analyse ?? '';

    /* ── Logique score ── */
    if (!scoreOk) {
        /* Score < 50 : refus automatique avec countdown */
        demarrerCountdownRefus();
    } else {
        /* Score ≥ 50 : admin choisit */
        document.getElementById('actionBar').style.display = 'flex';
        document.getElementById('refusalField').style.display = 'block';
        document.getElementById('refusalCause').required = true;
        document.getElementById('btnValider').disabled = false;
        document.getElementById('btnRefuser').disabled = false;
        document.getElementById('actionNote').innerHTML =
            `<span style="color:#16a34a;font-weight:700">✓ Score ≥ 50 % — Choisissez une action</span>`;
    }
}

/* ════════ Gestion des états d'affichage ════════ */
function validateRefus(event) {
    const causeEl = document.getElementById('refusalCause');
    const errorEl = document.getElementById('refusalError');
    const value = causeEl.value.trim();

    if (!value) {
        errorEl.classList.remove('hidden');
        errorEl.textContent = 'Veuillez remplir le motif du refus.';
        causeEl.focus();
        event.preventDefault();
        return false;
    }

    errorEl.classList.add('hidden');
    return confirm('Refuser cet outil et notifier le créateur ?');
}

function setState(s) {
    document.getElementById('statePlaceholder').classList.toggle('hidden', s !== 'idle');
    document.getElementById('stateLoading').classList.toggle('hidden',     s !== 'loading');
    document.getElementById('stateResult').classList.toggle('hidden',      s !== 'result');
    if (s !== 'result') {
        document.getElementById('actionBar').style.display = 'none';
        document.getElementById('autoRefusBanner').classList.add('hidden');
        document.getElementById('refusalField').style.display = 'none';
    }
}

function afficherErreur(msg) {
    setState('idle');
    document.getElementById('statePlaceholder').innerHTML = `
      <svg width="48" height="48" fill="none" stroke="#dc2626" stroke-width="1.5" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="10"/>
        <line x1="12" y1="8" x2="12" y2="12"/>
        <line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      <p style="font-weight:700;color:#dc2626;margin:0">Erreur lors de la vérification</p>
      <p style="font-size:.8rem;max-width:400px;color:var(--adm-muted)">${msg}</p>
      <button onclick="lancerVerification()"
              style="margin-top:8px;padding:7px 16px;background:#f1f5f9;border:1px solid #e2e8f0;
                     border-radius:8px;cursor:pointer;font-size:.8rem;font-family:inherit">
        Réessayer
      </button>`;
}
</script>
</body>
</html>