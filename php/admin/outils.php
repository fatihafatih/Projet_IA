<?php
require_once '../../includes/auth_admin.php';
require_once '../../includes/connexionbd.php';
require_once './notif_helper.php';

$msg = '';
$msgType = 'success';
$action = $_GET['action'] ?? '';
$id = (int) ($_GET['id'] ?? 0);

$hasRefusalCause = false;
try {
  $schemaStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'outils_ia' AND COLUMN_NAME = 'refusal_cause'"
  );
  $schemaStmt->execute();
  $hasRefusalCause = (bool) $schemaStmt->fetchColumn();
} catch (PDOException $e) {
  $hasRefusalCause = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'refuser') {
  $refuseId = (int) ($_POST['outil_id'] ?? 0);
  $cause = trim($_POST['refusal_cause'] ?? '');
  $filterRedirect = htmlspecialchars($_POST['filter'] ?? 'all');
  if ($refuseId > 0) {
    if ($hasRefusalCause) {
      $pdo->prepare("UPDATE outils_ia SET status='inactif', refusal_cause=? WHERE ID_OUTILS_IA=?")
        ->execute([$cause ?: null, $refuseId]);
    } else {
      $pdo->prepare("UPDATE outils_ia SET status='inactif' WHERE ID_OUTILS_IA=?")
        ->execute([$refuseId]);
    }
    $o = $pdo->prepare("SELECT * FROM outils_ia WHERE ID_OUTILS_IA=?");
    $o->execute([$refuseId]);
    $o = $o->fetch();
    if ($o && $o['ID_USERS_CREATE']) {
      notifierRefus($pdo, (int) $o['ID_USERS_CREATE'], $refuseId, $o['nom'], $cause);
    }
    $msg = '⛔ Outil refusé — notification envoyée au créateur.';
    $msgType = 'warning';
  }
  header('Location: outils.php?filter=' . $filterRedirect . '&msg=' . urlencode($msg) . '&type=' . $msgType);
  exit;
}

/* ── Actions rapides (valider / refuser / activer / supprimer) ── */
if (in_array($action, ['valider', 'refuser', 'supprimer', 'activer']) && $id > 0) {
  $o = $pdo->prepare("SELECT * FROM outils_ia WHERE ID_OUTILS_IA=?");
  $o->execute([$id]);
  $o = $o->fetch();
  if ($o)
    switch ($action) {
      case 'valider':
        $pdo->prepare("UPDATE outils_ia SET status='actif',ID_USERS_VALID=? WHERE ID_OUTILS_IA=?")->execute([$_SESSION['user_id'], $id]);
        if ($o['ID_USERS_CREATE'])
          notifierValidation($pdo, (int) $o['ID_USERS_CREATE'], $id, $o['nom']);
        $msg = '✅ Outil validé — notification envoyée au créateur.';
        break;
      case 'refuser':
        $pdo->prepare("UPDATE outils_ia SET status='inactif' WHERE ID_OUTILS_IA=?")->execute([$id]);
        if ($o['ID_USERS_CREATE'])
          notifierRefus($pdo, (int) $o['ID_USERS_CREATE'], $id, $o['nom']);
        $msg = '⛔ Outil refusé — notification envoyée au créateur.';
        $msgType = 'warning';
        break;
      case 'activer':
        $pdo->prepare("UPDATE outils_ia SET status='actif',ID_USERS_VALID=? WHERE ID_OUTILS_IA=?")->execute([$_SESSION['user_id'], $id]);
        $msg = '✅ Outil réactivé.';
        break;
      case 'supprimer':
        $pdo->prepare("DELETE FROM outils_ia WHERE ID_OUTILS_IA=?")->execute([$id]);
        $msg = '🗑️ Outil supprimé.';
        $msgType = 'danger';
        break;
    }
  header('Location: outils.php?filter=' . ($_GET['filter'] ?? 'all') . '&msg=' . urlencode($msg) . '&type=' . $msgType);
  exit;
}
if (!$msg && isset($_GET['msg'])) {
  $msg = htmlspecialchars($_GET['msg']);
  $msgType = $_GET['type'] ?? 'success';
}

/* ── POST : Ajouter / Modifier ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $pid = (int) ($_POST['outil_id'] ?? 0);
  $nom = trim($_POST['nom'] ?? '');
  $desc = trim($_POST['description'] ?? '');
  $logo = trim($_POST['logo_url'] ?? '');
  $url = trim($_POST['url'] ?? '');
  $ver = trim($_POST['version'] ?? '');
  $cat = (int) ($_POST['ID_CATEGORIE'] ?? 0) ?: null;
  $modelIds = array_map('intval', (array) ($_POST['model_ids'] ?? []));

  if (!$nom) {
    $msg = 'Nom obligatoire.';
    $msgType = 'danger';
  } else {
    if ($pid > 0) {
      $pdo->prepare(
        "UPDATE outils_ia SET nom=?,description=?,logo_url=?,url=?,version=?,ID_CATEGORIE=?,ID_USERS_VALID=?
                 WHERE ID_OUTILS_IA=?"
      )->execute([$nom, $desc, $logo ?: null, $url ?: null, $ver ?: null, $cat, $_SESSION['user_id'], $pid]);
      $pdo->prepare("DELETE FROM tool_models WHERE ID_OUTILS_IA=?")->execute([$pid]);
      foreach ($modelIds as $mid) {
        if ($mid > 0)
          $pdo->prepare("INSERT IGNORE INTO tool_models(ID_OUTILS_IA,ID_MODEL) VALUES(?,?)")->execute([$pid, $mid]);
      }
      $msg = '✅ Outil mis à jour.';
    } else {
      $pdo->prepare(
        "INSERT INTO outils_ia(nom,description,logo_url,url,version,status,ID_CATEGORIE,ID_USERS_VALID,ID_USERS_CREATE)
                 VALUES(?,?,?,?,?,'actif',?,?,?)"
      )->execute([$nom, $desc, $logo ?: null, $url ?: null, $ver ?: null, $cat, $_SESSION['user_id'], $_SESSION['user_id']]);
      $newId = (int) $pdo->lastInsertId();
      foreach ($modelIds as $mid) {
        if ($mid > 0)
          $pdo->prepare("INSERT IGNORE INTO tool_models(ID_OUTILS_IA,ID_MODEL) VALUES(?,?)")->execute([$newId, $mid]);
      }
      $msg = '✅ Outil ajouté et publié.';
    }
    header('Location: outils.php?msg=' . urlencode($msg));
    exit;
  }
}

/* ── Liste / filtres / pagination ── */
$filter = $_GET['filter'] ?? 'all';
$q = trim($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$pp = 12;
$where = 'WHERE 1=1';
$params = [];
if ($filter === 'en_attente') {
  $where .= " AND o.status='en_attente'";
} elseif ($filter === 'actif') {
  $where .= " AND o.status='actif'";
} elseif ($filter === 'inactif') {
  $where .= " AND o.status='inactif'";
}
if ($q) {
  $where .= " AND (o.nom LIKE ? OR o.description LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
}

$total = $pdo->prepare("SELECT COUNT(*) FROM outils_ia o $where");
$total->execute($params);
$total = $total->fetchColumn();
$pages = ceil($total / $pp);
$offset = ($page - 1) * $pp;

/* Outils de la page courante (affichage tableau) */
$outils = $pdo->prepare(
  "SELECT o.*,c.name AS cat_name,u.nom AS cr_nom
     FROM outils_ia o
     LEFT JOIN categorie c ON o.ID_CATEGORIE=c.ID_CATEGORIE
     LEFT JOIN users u ON o.ID_USERS_CREATE=u.id
     $where ORDER BY o.ID_OUTILS_IA DESC LIMIT $pp OFFSET $offset"
);
$outils->execute($params);
$outils = $outils->fetchAll();

/* Tous les outils sans limite (pour JS modal modifier) */
$allOutilsForJs = $pdo->query(
  "SELECT o.*,c.name AS cat_name,u.nom AS cr_nom
     FROM outils_ia o
     LEFT JOIN categorie c ON o.ID_CATEGORIE=c.ID_CATEGORIE
     LEFT JOIN users u ON o.ID_USERS_CREATE=u.id
     ORDER BY o.ID_OUTILS_IA DESC"
)->fetchAll();

$counts = [
  'all' => $pdo->query("SELECT COUNT(*) FROM outils_ia")->fetchColumn(),
  'actif' => $pdo->query("SELECT COUNT(*) FROM outils_ia WHERE status='actif'")->fetchColumn(),
  'en_attente' => $pdo->query("SELECT COUNT(*) FROM outils_ia WHERE status='en_attente'")->fetchColumn(),
  'inactif' => $pdo->query("SELECT COUNT(*) FROM outils_ia WHERE status='inactif'")->fetchColumn()
];
$categories = $pdo->query("SELECT * FROM categorie ORDER BY name")->fetchAll();

/* Modèles par outil (affichage tableau) */
$modelsByTool = [];
$tmRows = $pdo->query("SELECT tm.ID_OUTILS_IA, m.name FROM tool_models tm JOIN models m ON tm.ID_MODEL=m.ID_MODEL")->fetchAll();
foreach ($tmRows as $r)
  $modelsByTool[$r['ID_OUTILS_IA']][] = $r['name'];

/* IDs modèles par outil (pour JS) — SANS JSON_FORCE_OBJECT pour garder des arrays JS */
$toolModelIds = [];
$tmIds = $pdo->query("SELECT ID_OUTILS_IA, ID_MODEL FROM tool_models")->fetchAll();
foreach ($tmIds as $r)
  $toolModelIds[$r['ID_OUTILS_IA']][] = (int) $r['ID_MODEL'];

/* Tous les modèles actifs (pour les modaux) */
$allModels = $pdo->query(
  "SELECT m.ID_MODEL,m.name,p.name AS provider_name
     FROM models m LEFT JOIN providers p ON m.ID_PROVIDERS=p.ID_PROVIDERS
     WHERE m.status='actif' ORDER BY p.name,m.name"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Gestion Outils — Admin SearchIA</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="../../styles/style.css">
  <link rel="stylesheet" href="../../styles/admin.css">
  <style>
    .t-thumb {
      width: 38px;
      height: 38px;
      object-fit: contain;
      border-radius: 8px;
      border: 1px solid var(--adm-border, #e2e8f0);
      background: #f8fafc;
      padding: 3px;
      flex-shrink: 0
    }

    .tool-cell {
      display: flex;
      align-items: center;
      gap: 10px
    }

    .model-badges {
      display: flex;
      flex-wrap: wrap;
      gap: 4px
    }

    .badge-model {
      font-size: .65rem;
      font-weight: 600;
      padding: 2px 7px;
      border-radius: 20px;
      background: var(--adm-badge-bg, #ede9fe);
      color: var(--adm-badge-text, #5b21b6);
      white-space: nowrap
    }

    .img-preview-wrap {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-top: 6px
    }

    .img-preview {
      width: 56px;
      height: 56px;
      object-fit: contain;
      border: 1px solid var(--adm-border, #e2e8f0);
      border-radius: 10px;
      background: #f8fafc;
      padding: 4px;
      display: none
    }

    .img-preview-placeholder {
      width: 56px;
      height: 56px;
      border: 1.5px dashed var(--adm-border, #cbd5e1);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--adm-muted, #94a3b8);
      font-size: .68rem;
      text-align: center;
      line-height: 1.3;
      padding: 4px
    }

    .models-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 8px;
      max-height: 220px;
      overflow-y: auto;
      padding: 4px 2px
    }

    .model-cb-label {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 7px 10px;
      border: 1px solid var(--adm-border, #e2e8f0);
      border-radius: 8px;
      cursor: pointer;
      font-size: .82rem;
      transition: background .15s, border-color .15s;
      line-height: 1.3
    }

    .model-cb-label:hover {
      background: var(--adm-hover, #f1f5f9);
      border-color: var(--adm-blue, #3b82f6)
    }

    .model-cb-label input[type="checkbox"] {
      accent-color: var(--adm-blue, #3b82f6);
      width: 15px;
      height: 15px;
      flex-shrink: 0
    }

    .model-provider-tag {
      font-size: .68rem;
      color: var(--adm-muted, #64748b);
      display: block
    }

    .form-group {
      margin-bottom: 16px
    }

    .form-label {
      display: block;
      font-size: .8rem;
      font-weight: 600;
      margin-bottom: 5px;
      color: var(--adm-text, #1e293b)
    }

    .form-control {
      width: 100%;
      padding: 8px 10px;
      border: 1px solid var(--adm-border, #e2e8f0);
      border-radius: 8px;
      font-size: .875rem;
      background: var(--adm-surface, #fff);
      color: var(--adm-text, #1e293b);
      box-sizing: border-box
    }

    .form-control:focus {
      outline: none;
      border-color: var(--adm-blue, #3b82f6)
    }

    .refusal-error {
      margin-top: 8px;
      color: #b91c1c;
      font-size: .82rem;
      line-height: 1.4
    }
  </style>
</head>

<body class="adm-body">
  <?php include 'sidebar.php'; ?>
  <div class="adm-main">
    <div class="adm-topbar">
      <div>
        <h1 class="adm-title">Gestion des Outils IA</h1>
        <p class="adm-sub">Valider/refuser envoie automatiquement une notification au créateur</p>
      </div>
      <button class="btn-adm-primary" onclick="openModal('addModal')">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <line x1="12" y1="5" x2="12" y2="19" />
          <line x1="5" y1="12" x2="19" y2="12" />
        </svg>Ajouter
      </button>
    </div>

    <?php if ($msg): ?>
      <div class="adm-alert adm-alert-<?= $msgType ?>">
        <?= $msg ?>
        <button onclick="this.parentElement.remove()"
          style="float:right;background:none;border:none;cursor:pointer;font-size:1.1rem">×</button>
      </div>
    <?php endif; ?>

    <div class="notif-note">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
        <path d="M13.73 21a2 2 0 0 1-3.46 0" />
      </svg>
      <strong>Notifications auto :</strong>&nbsp;Valider → notif ✅ au créateur&nbsp;|&nbsp;Refuser → notif ❌ au créateur
    </div>

    <!-- Filtres -->
    <div class="stabs">
      <?php foreach (['all' => 'Tous', 'actif' => 'Actifs', 'en_attente' => 'En attente', 'inactif' => 'Inactifs'] as $k => $l): ?>
        <a href="outils.php?filter=<?= $k ?>" class="stab <?= $filter === $k ? 'active' : '' ?>"><?= $l ?> <span
            class="stab-n"><?= $counts[$k] ?></span></a>
      <?php endforeach; ?>
    </div>

    <!-- Recherche -->
    <form method="GET" class="adm-search">
      <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="8" />
        <line x1="21" y1="21" x2="16.65" y2="16.65" />
      </svg>
      <input type="text" name="q" placeholder="Rechercher…" value="<?= htmlspecialchars($q) ?>">
      <button type="submit">Rechercher</button>
      <?php if ($q): ?><a href="outils.php?filter=<?= $filter ?>">✕</a><?php endif; ?>
    </form>

    <!-- Tableau -->
    <div class="adm-section">
      <div class="adm-sec-head">
        <h2><?= $total ?> outil<?= $total > 1 ? 's' : '' ?></h2>
      </div>
      <div class="tbl-wrap">
        <table class="adm-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Outil</th>
              <th>Catégorie</th>
              <th>Modèles</th>
              <th>Statut</th>
              <th>Créé par</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($outils)): ?>
              <tr>
                <td colspan="7" style="text-align:center;padding:40px;color:var(--adm-muted)">Aucun outil.</td>
              </tr>
            <?php endif; ?>
            <?php foreach ($outils as $o): ?>
              <tr>
                <td class="td-id"><?= $o['ID_OUTILS_IA'] ?></td>
                <td>
                  <div class="tool-cell">
                    <?php if (!empty($o['logo_url'])): ?>
                      <img src="<?= htmlspecialchars($o['logo_url']) ?>" class="t-thumb" alt=""
                        onerror="this.style.display='none'">
                    <?php endif; ?>
                    <div>
                      <div style="font-weight:600;font-size:.875rem"><?= htmlspecialchars($o['nom']) ?></div>
                      <?php if ($o['url']): ?>
                        <a href="<?= htmlspecialchars($o['url']) ?>" target="_blank"
                          style="font-size:.72rem;color:var(--adm-blue);text-decoration:none">↗ Site</a>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>
                <td><span class="badge-cat"><?= htmlspecialchars($o['cat_name'] ?? '—') ?></span></td>
                <td>
                  <div class="model-badges">
                    <?php foreach (($modelsByTool[$o['ID_OUTILS_IA']] ?? []) as $mn): ?>
                      <span class="badge-model"><?= htmlspecialchars($mn) ?></span>
                    <?php endforeach; ?>
                    <?php if (empty($modelsByTool[$o['ID_OUTILS_IA']])): ?><span
                        style="font-size:.75rem;color:var(--adm-muted)">—</span><?php endif; ?>
                  </div>
                </td>
                <td><span
                    class="st-pill <?= $o['status'] ?>"><?= $o['status'] === 'en_attente' ? 'En attente' : ucfirst($o['status']) ?></span>
                </td>
                <td style="font-size:.8rem;color:var(--adm-muted)"><?= htmlspecialchars($o['cr_nom'] ?? '—') ?></td>
                <td>
                  <div class="act-btns">
                    <?php if ($o['status'] === 'en_attente'): ?>
                      <a href="verifier_outil.php?id=<?= $o['ID_OUTILS_IA'] ?>" class="btn-val">✓ Valider</a>
                      <button type="button" class="btn-ref" onclick="openRefusalModal(<?= $o['ID_OUTILS_IA'] ?>)">✗
                        Refuser</button>
                    <?php elseif ($o['status'] === 'actif'): ?>
                      <a href="outils.php?action=refuser&id=<?= $o['ID_OUTILS_IA'] ?>&filter=<?= $filter ?>" class="btn-ref"
                        title="Désactiver" onclick="return confirm('Désactiver ?')">⊘</a>
                    <?php else: ?>
                      <a href="outils.php?action=activer&id=<?= $o['ID_OUTILS_IA'] ?>&filter=<?= $filter ?>" class="btn-val"
                        onclick="return confirm('Réactiver ?')">↺</a>
                    <?php endif; ?>
                    <button class="btn-edt" data-id="<?= $o['ID_OUTILS_IA'] ?>"
                      onclick="openEditById(this.dataset.id)">✎</button>
                    <a href="outils.php?action=supprimer&id=<?= $o['ID_OUTILS_IA'] ?>&filter=<?= $filter ?>" class="btn-del"
                      onclick="return confirm('Supprimer définitivement ?')">🗑</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($pages > 1): ?>
        <div class="adm-pag">
          <?php for ($p = 1; $p <= $pages; $p++): ?>
            <a href="?filter=<?= $filter ?>&q=<?= urlencode($q) ?>&page=<?= $p ?>"
              class="pag-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    </div>
  </div><!-- /adm-main -->

  <!-- ══ Modal Ajouter ══════════════════════════════════════════════ -->
  <div class="adm-modal-overlay" id="addModal" onclick="if(event.target===this)closeModal('addModal')">
    <div class="adm-modal">
      <div class="adm-modal-head">
        <h3>Ajouter un outil</h3>
        <button onclick="closeModal('addModal')" class="adm-modal-close">×</button>
      </div>
      <form method="POST" class="adm-modal-body">
        <input type="hidden" name="outil_id" value="0">
        <div class="form-group">
          <label class="form-label">Nom de l'outil *</label>
          <input class="form-control" type="text" name="nom" required maxlength="150" placeholder="Ex : ChatGPT…">
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea class="form-control" name="description" rows="3"
            placeholder="Décrivez brièvement l'outil…"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">URL du logo</label>
          <input class="form-control" type="url" id="a_logo_url" name="logo_url"
            placeholder="https://exemple.com/logo.png" oninput="previewImage(this,'a_preview','a_ph')">
          <div class="img-preview-wrap">
            <img id="a_preview" class="img-preview" alt=""
              onerror="this.style.display='none';document.getElementById('a_ph').style.display='flex'">
            <div id="a_ph" class="img-preview-placeholder">Aperçu image</div>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">URL du site</label>
          <input class="form-control" type="url" name="url" placeholder="https://…">
        </div>
        <div class="form-group">
          <label class="form-label">Version</label>
          <input class="form-control" type="number" name="version" step="0.01" min="0" placeholder="Ex : 4.00">
        </div>
        <div class="form-group">
          <label class="form-label">Catégorie</label>
          <select class="form-control" name="ID_CATEGORIE">
            <option value="">— Sélectionner —</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['ID_CATEGORIE'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Modèles associés</label>
          <div class="models-grid">
            <?php foreach ($allModels as $m): ?>
              <label class="model-cb-label">
                <input type="checkbox" name="model_ids[]" value="<?= $m['ID_MODEL'] ?>">
                <span><?= htmlspecialchars($m['name']) ?><span
                    class="model-provider-tag"><?= htmlspecialchars($m['provider_name'] ?? '—') ?></span></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="adm-modal-foot">
          <button type="button" class="btn-cancel" onclick="closeModal('addModal')">Annuler</button>
          <button type="submit" class="btn-adm-primary">Ajouter</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ══ Modal Modifier ════════════════════════════════════════════ -->
  <div class="adm-modal-overlay" id="editModal" onclick="if(event.target===this)closeModal('editModal')">
    <div class="adm-modal">
      <div class="adm-modal-head">
        <h3>Modifier l'outil</h3>
        <button onclick="closeModal('editModal')" class="adm-modal-close">×</button>
      </div>
      <form method="POST" class="adm-modal-body">
        <input type="hidden" name="outil_id" id="e_id">
        <div class="form-group">
          <label class="form-label">Nom de l'outil *</label>
          <input class="form-control" type="text" id="e_nom" name="nom" required maxlength="150">
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea class="form-control" id="e_description" name="description" rows="3"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">URL du logo</label>
          <input class="form-control" type="url" id="e_logo_url" name="logo_url"
            oninput="previewImage(this,'e_preview','e_ph')">
          <div class="img-preview-wrap">
            <img id="e_preview" class="img-preview" alt=""
              onerror="this.style.display='none';document.getElementById('e_ph').style.display='flex'">
            <div id="e_ph" class="img-preview-placeholder">Aperçu image</div>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">URL du site</label>
          <input class="form-control" type="url" id="e_url" name="url">
        </div>
        <div class="form-group">
          <label class="form-label">Version</label>
          <input class="form-control" type="number" id="e_version" name="version" step="0.01" min="0">
        </div>
        <div class="form-group">
          <label class="form-label">Catégorie</label>
          <select class="form-control" id="e_ID_CATEGORIE" name="ID_CATEGORIE">
            <option value="">— Sélectionner —</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['ID_CATEGORIE'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Modèles associés</label>
          <div class="models-grid">
            <?php foreach ($allModels as $m): ?>
              <label class="model-cb-label">
                <input type="checkbox" class="e_model_cb" name="model_ids[]" value="<?= $m['ID_MODEL'] ?>">
                <span><?= htmlspecialchars($m['name']) ?><span
                    class="model-provider-tag"><?= htmlspecialchars($m['provider_name'] ?? '—') ?></span></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="adm-modal-foot">
          <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Annuler</button>
          <button type="submit" class="btn-adm-primary">Enregistrer</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ══ Modal Refus ══════════════════════════════════════════════ -->
  <div class="adm-modal-overlay" id="refuseModal" onclick="if(event.target===this)closeModal('refuseModal')">
    <div class="adm-modal">
      <div class="adm-modal-head">
        <h3>Refuser l'outil</h3>
        <button onclick="closeModal('refuseModal')" class="adm-modal-close">×</button>
      </div>
      <form method="POST" id="refuseForm" class="adm-modal-body" onsubmit="return validateRefusalForm()">
        <input type="hidden" name="action" value="refuser">
        <input type="hidden" name="outil_id" id="refuse_tool_id" value="0">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <div class="form-group">
          <label class="form-label">Motif du refus *</label>
          <textarea id="refusalCauseInput" name="refusal_cause" class="form-control" rows="4"
            placeholder="Expliquez pourquoi l'outil est refusé."></textarea>
          <div id="refusalError" class="refusal-error hidden">Veuillez remplir le motif du refus.</div>
        </div>
        <div class="adm-modal-foot">
          <button type="button" class="btn-cancel" onclick="closeModal('refuseModal')">Annuler</button>
          <button type="submit" class="btn-adm-primary">Envoyer le refus</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ══ Script UNIQUE ════════════════════════════════════════════ -->
  <script>
    /* CORRECTION : json_encode sans JSON_FORCE_OBJECT pour que les valeurs
       restent des arrays JS ([] au lieu de {}) et que .map() fonctionne */
    var toolModelIds = <?= json_encode($toolModelIds) ?>;
    var allOutilsData = <?= json_encode(array_column($allOutilsForJs, null, 'ID_OUTILS_IA'), JSON_UNESCAPED_UNICODE) ?>;

    function openModal(id) { document.getElementById(id).classList.add('open'); document.body.style.overflow = 'hidden'; }
    function closeModal(id) { document.getElementById(id).classList.remove('open'); document.body.style.overflow = ''; }

    function previewImage(input, previewId, phId) {
      var img = document.getElementById(previewId), ph = document.getElementById(phId);
      var url = input.value.trim();
      if (!url) { img.style.display = 'none'; if (ph) ph.style.display = 'flex'; return; }
      img.src = url; img.style.display = 'block'; if (ph) ph.style.display = 'none';
    }

    function openEditById(oid) {
      var d = allOutilsData[oid];
      if (!d) { alert('Outil introuvable'); return; }

      document.getElementById('e_id').value = d.ID_OUTILS_IA;
      document.getElementById('e_nom').value = d.nom || '';
      document.getElementById('e_description').value = d.description || '';
      document.getElementById('e_url').value = d.url || '';
      document.getElementById('e_version').value = d.version || '';
      document.getElementById('e_ID_CATEGORIE').value = d.ID_CATEGORIE || '';

      var logoInput = document.getElementById('e_logo_url');
      logoInput.value = d.logo_url || '';
      previewImage(logoInput, 'e_preview', 'e_ph');

      /* toolModelIds[oid] est maintenant un vrai array JS grâce au fix PHP */
      var linked = (toolModelIds[oid] || []).map(Number);
      document.querySelectorAll('.e_model_cb').forEach(function (cb) {
        cb.checked = linked.indexOf(parseInt(cb.value)) !== -1;
      });

      openModal('editModal');
    }

    function openRefusalModal(oid) {
      document.getElementById('refuse_tool_id').value = oid;
      document.getElementById('refusalCauseInput').value = '';
      document.getElementById('refusalError').classList.add('hidden');
      openModal('refuseModal');
    }

    function validateRefusalForm() {
      var textarea = document.getElementById('refusalCauseInput');
      var error = document.getElementById('refusalError');
      if (!textarea.value.trim()) {
        error.classList.remove('hidden');
        error.textContent = 'Veuillez remplir le motif du refus.';
        textarea.focus();
        return false;
      }
      return confirm('Refuser cet outil et notifier le créateur ?');
    }
  </script>
</body>

</html>