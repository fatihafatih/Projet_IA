<?php
require_once '../../includes/auth_admin.php';
require_once '../../includes/connexionbd.php';
require_once './notif_helper.php';

$msg=''; $msgType='success';
$action=$_GET['action']??''; $id=(int)($_GET['id']??0);

/* ── Valider / Refuser depuis notifications.php (boutons rapides) ── */
if(in_array($action,['valider','refuser']) && $id>0){
    $o=$pdo->prepare("SELECT * FROM outils_ia WHERE ID_OUTILS_IA=?");
    $o->execute([$id]); $o=$o->fetch();
    if($o){
        if($action==='valider'){
            $pdo->prepare("UPDATE outils_ia SET status='actif',ID_USERS_VALID=? WHERE ID_OUTILS_IA=?")
                ->execute([$_SESSION['user_id'],$id]);
            if($o['ID_USERS_CREATE']) notifierValidation($pdo,(int)$o['ID_USERS_CREATE'],$id,$o['nom']);
            $msg='✅ Outil validé — notification envoyée.';
        } else {
            $pdo->prepare("UPDATE outils_ia SET status='inactif' WHERE ID_OUTILS_IA=?")
                ->execute([$id]);
            if($o['ID_USERS_CREATE']) notifierRefus($pdo,(int)$o['ID_USERS_CREATE'],$id,$o['nom']);
            $msg='⛔ Outil refusé — notification envoyée.'; $msgType='warning';
        }
    }
    header('Location: notifications.php?msg='.urlencode($msg).'&type='.$msgType); exit;
}

/* ── Marquer une notification comme lue ── */
if($action==='mark_read' && $id>0){
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE ID_NOTIF=?")->execute([$id]);
    header('Location: notifications.php'); exit;
}
/* ── Marquer toutes comme lues ── */
if($action==='mark_all_read'){
    $pdo->query("UPDATE notifications SET is_read=1");
    header('Location: notifications.php'); exit;
}
/* ── Supprimer une notification ── */
if($action==='delete_notif' && $id>0){
    $pdo->prepare("DELETE FROM notifications WHERE ID_NOTIF=?")->execute([$id]);
    header('Location: notifications.php'); exit;
}

if(!$msg && isset($_GET['msg'])){
    $msg=htmlspecialchars($_GET['msg']);
    $msgType=$_GET['type']??'success';
}

/* ── Outils en attente soumis par des membres (non-admin) ── */
// ✅ Après
$pendingStmt=$pdo->query(
    "SELECT o.*,
            c.name  AS cat_name,
            u.nom   AS cr_nom,
            u.email AS cr_email,
            u.role  AS cr_role
     FROM outils_ia o
     LEFT JOIN categorie c ON o.ID_CATEGORIE=c.ID_CATEGORIE
     LEFT JOIN users u     ON o.ID_USERS_CREATE=u.id
     WHERE o.status='en_attente'
       AND u.role NOT IN ('admin', 'superadmin')
     ORDER BY o.ID_OUTILS_IA DESC"
);
$pending = $pendingStmt->fetchAll();

/* ── Toutes les notifications système (100 dernières) ── */
$tab      = $_GET['tab']??'pending';
$nbUnread = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read=0")->fetchColumn();
$notifs   = $pdo->query(
    "SELECT n.*, u.nom AS u_nom
     FROM notifications n
     LEFT JOIN users u ON n.ID_USERS=u.id
     ORDER BY n.created_at DESC LIMIT 100"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Notifications — Admin SearchIA</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../styles/style.css">
<link rel="stylesheet" href="../../styles/admin.css">
<style>
/* ============================================================
   NOTIFICATIONS.CSS — Styles spécifiques à la page notifications.php
   À charger après admin.css
   ============================================================ */

/* ── Bouton secondaire (header) ── */
.btn-adm-secondary {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 10px 18px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 800;
  font-family: 'Nunito', sans-serif;
  border: 1px solid var(--adm-border);
  cursor: pointer;
  text-decoration: none;
  background: var(--adm-surface);
  color: var(--adm-text);
  transition: .2s ease;
}

.btn-adm-secondary:hover {
  background: var(--adm-bg);
  border-color: var(--primary);
  color: var(--primary);
}

/* ── Alertes ── */
.adm-alert {
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 13.5px;
  font-weight: 600;
  margin-bottom: 18px;
  border: 1px solid transparent;
}

.adm-alert-success {
  background: #F0FDF4;
  color: var(--adm-green);
  border-color: #BBF7D0;
}

.adm-alert-warning {
  background: #FFFBEB;
  color: var(--adm-orange);
  border-color: #FDE68A;
}

.adm-alert-danger {
  background: #FEF2F2;
  color: var(--adm-red);
  border-color: #FECACA;
}

/* ── Tabs ── */
.notif-tabs {
  display: flex;
  gap: 4px;
  margin-bottom: 20px;
  border-bottom: 2px solid var(--adm-border);
  padding-bottom: 0;
}

.notif-tab {
  padding: 8px 18px;
  font-size: .85rem;
  font-weight: 700;
  font-family: 'Nunito', sans-serif;
  cursor: pointer;
  border: none;
  background: none;
  color: var(--adm-muted);
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  transition: color .15s, border-color .15s;
  display: inline-flex;
  align-items: center;
}

.notif-tab.active {
  color: var(--primary);
  border-bottom-color: var(--primary);
}

.notif-tab:hover {
  color: var(--adm-text);
}

/* ── Badge compteur dans les tabs ── */
.adm-badge {
  background: var(--primary);
  color: #fff;
  font-size: 10px;
  font-weight: 800;
  padding: 2px 7px;
  border-radius: 999px;
  font-family: 'Nunito', sans-serif;
}

/* ── Note info (bandeau) ── */
.notif-note {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  background: #EFF6FF;
  border: 1px solid #BFDBFE;
  border-radius: 10px;
  font-size: 13px;
  color: #1E40AF;
  margin-bottom: 18px;
}

.notif-note svg {
  flex-shrink: 0;
}

/* ── Carte outil en attente ── */
.pending-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
  border: 1px solid var(--adm-border);
  border-radius: 12px;
  background: var(--adm-surface);
  margin-bottom: 10px;
  transition: box-shadow .15s;
}

.pending-card:hover {
  box-shadow: var(--adm-shadow);
}

.pending-thumb {
  width: 44px;
  height: 44px;
  object-fit: contain;
  border-radius: 8px;
  border: 1px solid var(--adm-border);
  background: #F8FAFC;
  padding: 3px;
  flex-shrink: 0;
}

.pending-thumb-ph {
  width: 44px;
  height: 44px;
  border-radius: 8px;
  background: var(--adm-bg);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--adm-muted);
  font-size: 1.2rem;
  flex-shrink: 0;
}

.pending-info {
  flex: 1;
  min-width: 0;
}

.pending-name {
  font-weight: 700;
  font-size: .9rem;
  margin-bottom: 2px;
}

.pending-meta {
  font-size: .75rem;
  color: var(--adm-muted);
}

.pending-actions {
  display: flex;
  gap: 6px;
  flex-shrink: 0;
  align-items: center;
}

/* ── Bouton Vérifier IA ── */
.btn-verify-ia {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 7px 14px;
  background: linear-gradient(135deg, #10B981, #059669);
  color: #fff !important;
  border-radius: 8px;
  font-size: .78rem;
  font-weight: 700;
  text-decoration: none;
  white-space: nowrap;
  transition: opacity .15s, transform .1s;
}

.btn-verify-ia:hover {
  opacity: .88;
  transform: translateY(-1px);
}

/* ── Ligne notification ── */
.notif-row {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 10px;
  margin-bottom: 6px;
  border: 1px solid var(--adm-border);
  background: var(--adm-surface);
  transition: background .15s;
}

.notif-row.unread {
  background: #EFF6FF;
  border-color: #BFDBFE;
}

.notif-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
  margin-top: 5px;
}

.nd-info { background: #3B82F6; }
.nd-alerte { background: #F59E0B; }
.nd-promotion { background: #8B5CF6; }
.nd-systeme { background: #6B7280; }

.notif-body {
  flex: 1;
  min-width: 0;
}

.notif-title {
  font-size: .85rem;
  font-weight: 600;
  margin-bottom: 2px;
  color: var(--adm-text);
}

.notif-msg {
  font-size: .78rem;
  color: var(--adm-muted);
  margin-bottom: 4px;
}

.notif-footer {
  font-size: .7rem;
  color: var(--adm-muted);
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}

.notif-badge {
  font-size: .65rem;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 12px;
  text-transform: uppercase;
  letter-spacing: .03em;
}

.nb-info { background: #DBEAFE; color: #1E40AF; }
.nb-alerte { background: #FEF3C7; color: #92400E; }
.nb-promotion { background: #EDE9FE; color: #5B21B6; }
.nb-systeme { background: #F1F5F9; color: #475569; }

/* ── Empty state ── */
.empty-state {
  text-align: center;
  padding: 48px 20px;
  color: var(--adm-muted);
}

.empty-state svg {
  margin-bottom: 10px;
  opacity: .35;
}

.empty-state p {
  font-size: 14px;
  margin: 0;
}

.hidden {
  display: none !important;
}
</style>
</head>
<body class="adm-body">
<?php include 'sidebar.php'; ?>
<div class="adm-main">
  <div class="adm-topbar">
    <div>
      <h1 class="adm-title">Notifications</h1>
      <p class="adm-sub">Outils en attente de validation + historique des notifications</p>
    </div>
    <?php if($nbUnread>0): ?>
      <a href="notifications.php?action=mark_all_read"
         class="btn-adm-secondary"
         onclick="return confirm('Marquer toutes les notifications comme lues ?')"
         style="font-size:.82rem;padding:7px 14px">
        ✓ Tout marquer lu
      </a>
    <?php endif; ?>
  </div>

  <?php if($msg): ?>
  <div class="adm-alert adm-alert-<?=$msgType?>"><?=$msg?>
    <button onclick="this.parentElement.remove()"
            style="float:right;background:none;border:none;cursor:pointer;font-size:1.1rem">×</button>
  </div>
  <?php endif; ?>

  <!-- Tabs -->
  <div class="notif-tabs">
    <button class="notif-tab <?=$tab==='pending'?'active':''?>" onclick="switchTab('pending')">
      ⏳ En attente
      <?php if(count($pending)>0): ?>
        <span class="adm-badge" style="position:relative;top:0;margin-left:4px"><?=count($pending)?></span>
      <?php endif; ?>
    </button>
    <button class="notif-tab <?=$tab==='notifs'?'active':''?>" onclick="switchTab('notifs')">
      🔔 Toutes les notifications
      <?php if($nbUnread>0): ?>
        <span class="adm-badge" style="position:relative;top:0;margin-left:4px"><?=$nbUnread?></span>
      <?php endif; ?>
    </button>
  </div>

  <!-- ══ Panneau : Outils en attente ══ -->
  <div id="panel-pending" class="<?=$tab!=='pending'?'hidden':''?>">
    <?php if(empty($pending)): ?>
      <div class="empty-state">
        <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
          <path d="M9 12l2 2 4-4"/>
          <path d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z"/>
        </svg>
        <p style="font-weight:600;margin-bottom:4px">Aucun outil en attente</p>
        <p style="font-size:.82rem">Tous les outils soumis par les utilisateurs ont été traités.</p>
      </div>
    <?php else: ?>
      <div class="notif-note">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        Ces outils ont été soumis par des <strong>&nbsp;membres&nbsp;</strong> et attendent votre validation.
        Cliquez sur <strong>&nbsp;🤖 Vérifier&nbsp;</strong> pour lancer l'analyse IA automatique —
        <strong>le score sera calculé et l'outil sera refusé automatiquement si le score est inférieur à 50 %.</strong>
      </div>

      <?php foreach($pending as $o): ?>
      <div class="pending-card">
        <?php if(!empty($o['logo_url'])): ?>
          <img src="<?=htmlspecialchars($o['logo_url'])?>" class="pending-thumb" alt=""
               onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
          <div class="pending-thumb-ph" style="display:none">🤖</div>
        <?php else: ?>
          <div class="pending-thumb-ph">🤖</div>
        <?php endif; ?>

        <div class="pending-info">
          <div class="pending-name"><?=htmlspecialchars($o['nom'])?></div>
          <div class="pending-meta">
            Soumis par <strong><?=htmlspecialchars($o['cr_nom']??'—')?></strong>
            (<?=htmlspecialchars($o['cr_email']??'—')?>) &nbsp;·&nbsp;
            Catégorie : <?=htmlspecialchars($o['cat_name']??'—')?>
            <?php if($o['url']): ?>
              &nbsp;·&nbsp;
              <a href="<?=htmlspecialchars($o['url'])?>" target="_blank"
                 style="color:var(--adm-blue);text-decoration:none">↗ Voir le site</a>
            <?php endif; ?>
          </div>
          <?php if(!empty($o['description'])): ?>
            <div style="font-size:.78rem;color:var(--adm-muted);margin-top:4px;line-height:1.4">
              <?=htmlspecialchars(mb_substr($o['description'],0,140)).(mb_strlen($o['description'])>140?'…':'')?>
            </div>
          <?php endif; ?>
        </div>

        <div class="pending-actions">
          <!-- 🤖 Bouton principal : vérification IA automatique -->
          <a href="verifier_outil.php?id=<?=$o['ID_OUTILS_IA']?>"
             class="btn-verify-ia"
             title="Lancer la vérification IA automatique (refus auto si score < 50%)">
            🤖 Vérifier
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- ══ Panneau : Toutes les notifications ══ -->
  <div id="panel-notifs" class="<?=$tab!=='notifs'?'hidden':''?>">
    <?php if(empty($notifs)): ?>
      <div class="empty-state">
        <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        <p style="font-weight:600;margin-bottom:4px">Aucune notification</p>
      </div>
    <?php else: ?>
      <?php foreach($notifs as $n): ?>
      <div class="notif-row <?=$n['is_read']?'':'unread'?>">
        <div class="notif-dot nd-<?=$n['type']?>"></div>
        <div class="notif-body">
          <div class="notif-title"><?=htmlspecialchars($n['titre'])?></div>
          <?php if(!empty($n['message'])): ?>
            <div class="notif-msg"><?=htmlspecialchars($n['message'])?></div>
          <?php endif; ?>
          <div class="notif-footer">
            <span class="notif-badge nb-<?=$n['type']?>"><?=$n['type']?></span>
            <span>Pour : <?=htmlspecialchars($n['u_nom']??'—')?></span>
            <span><?=date('d/m/Y H:i',strtotime($n['created_at']))?></span>
            <?php if(!$n['is_read']): ?>
              <a href="notifications.php?action=mark_read&id=<?=$n['ID_NOTIF']?>"
                 style="color:var(--adm-blue);font-size:.7rem;text-decoration:none">
                ✓ Marquer lu
              </a>
            <?php endif; ?>
            <a href="notifications.php?action=delete_notif&id=<?=$n['ID_NOTIF']?>"
               onclick="return confirm('Supprimer cette notification ?')"
               style="color:#dc2626;font-size:.7rem;text-decoration:none;margin-left:auto">
              🗑 Supprimer
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div><!-- /adm-main -->

<script>
function switchTab(t){
    document.getElementById('panel-pending').classList.toggle('hidden', t!=='pending');
    document.getElementById('panel-notifs').classList.toggle('hidden', t!=='notifs');
    document.querySelectorAll('.notif-tab').forEach(function(el,i){
        el.classList.toggle('active',(i===0&&t==='pending')||(i===1&&t==='notifs'));
    });
    history.replaceState(null,'','notifications.php?tab='+t);
}
</script>
</body>
</html>