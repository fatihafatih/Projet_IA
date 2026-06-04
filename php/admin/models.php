<?php
require_once '../../includes/auth_admin.php';
require_once '../../includes/connexionbd.php';

$msg=''; $msgType='success';
$action=$_GET['action']??''; $id=(int)($_GET['id']??0);

/* ── Suppression ── */
if($action==='supprimer' && $id>0){
    $pdo->prepare("DELETE FROM models WHERE ID_MODEL=?")->execute([$id]);
    $msg='🗑️ Modèle supprimé.'; $msgType='danger';
    header('Location: models.php?msg='.urlencode($msg).'&type='.$msgType); exit;
}
if(!$msg&&isset($_GET['msg'])){$msg=htmlspecialchars($_GET['msg']);$msgType=$_GET['type']??'success';}

/* ── POST : Ajouter / Modifier ── */
if($_SERVER['REQUEST_METHOD']==='POST'){
    $pid   = (int)($_POST['model_id']??0);
    $nom   = trim($_POST['name']??'');
    $desc  = trim($_POST['description']??'');
    $tags  = trim($_POST['tags']??'');
    $status= $_POST['status']??'actif';
    $prov  = (int)($_POST['ID_PROVIDERS']??0)?:null;
    $cat   = (int)($_POST['ID_CATEGORIE']??0)?:null;
    $carIds= array_map('intval',(array)($_POST['car_ids']??[]));

    if(!$nom){ $msg='Nom obligatoire.'; $msgType='danger'; }
    else {
        if($pid>0){
            $pdo->prepare(
                "UPDATE models SET name=?,description=?,tags=?,status=?,ID_PROVIDERS=?,ID_CATEGORIE=?
                 WHERE ID_MODEL=?"
            )->execute([$nom,$desc,$tags?:null,$status,$prov,$cat,$pid]);
            $pdo->prepare("DELETE FROM model_caracteristiques WHERE ID_MODEL=?")->execute([$pid]);
            foreach($carIds as $cid){
                if($cid>0) $pdo->prepare("INSERT IGNORE INTO model_caracteristiques(ID_MODEL,ID_CAR) VALUES(?,?)")->execute([$pid,$cid]);
            }
            $msg='✅ Modèle mis à jour.';
        } else {
            $pdo->prepare(
                "INSERT INTO models(name,description,tags,status,ID_PROVIDERS,ID_CATEGORIE)
                 VALUES(?,?,?,?,?,?)"
            )->execute([$nom,$desc,$tags?:null,$status,$prov,$cat]);
            $newId=(int)$pdo->lastInsertId();
            foreach($carIds as $cid){
                if($cid>0) $pdo->prepare("INSERT IGNORE INTO model_caracteristiques(ID_MODEL,ID_CAR) VALUES(?,?)")->execute([$newId,$cid]);
            }
            $msg='✅ Modèle ajouté.';
        }
        header('Location: models.php?msg='.urlencode($msg)); exit;
    }
}

/* ── Liste (paginée pour affichage) ── */
$q    = trim($_GET['q']??'');
$page = max(1,(int)($_GET['page']??1)); $pp=15;
$where='WHERE 1=1'; $params=[];
if($q){ $where.=" AND (m.name LIKE ? OR m.description LIKE ?)"; $params[]="%$q%"; $params[]="%$q%"; }

$total=$pdo->prepare("SELECT COUNT(*) FROM models m $where"); $total->execute($params); $total=$total->fetchColumn();
$pages=ceil($total/$pp); $offset=($page-1)*$pp;

/* Modèles de la page courante (affichage tableau) */
$models=$pdo->prepare(
    "SELECT m.*,p.name AS prov_name,c.name AS cat_name
     FROM models m
     LEFT JOIN providers p ON m.ID_PROVIDERS=p.ID_PROVIDERS
     LEFT JOIN categorie c ON m.ID_CATEGORIE=c.ID_CATEGORIE
     $where ORDER BY m.ID_MODEL DESC LIMIT $pp OFFSET $offset"
);
$models->execute($params); $models=$models->fetchAll();

/* Tous les modèles sans limite (pour JS modal modifier) */
$allModelsForJs=$pdo->query(
    "SELECT m.*,p.name AS prov_name,c.name AS cat_name
     FROM models m
     LEFT JOIN providers p ON m.ID_PROVIDERS=p.ID_PROVIDERS
     LEFT JOIN categorie c ON m.ID_CATEGORIE=c.ID_CATEGORIE
     ORDER BY m.ID_MODEL DESC"
)->fetchAll();

/* Caractéristiques par modèle (affichage) */
$carByModel=[];
$carRows=$pdo->query("SELECT mc.ID_MODEL,ca.name FROM model_caracteristiques mc JOIN caracteristiques ca ON mc.ID_CAR=ca.ID_CAR")->fetchAll();
foreach($carRows as $r) $carByModel[$r['ID_MODEL']][]=$r['name'];

/* IDs caractéristiques par modèle (pour JS) — SANS JSON_FORCE_OBJECT pour garder des arrays JS */
$carIdsByModel=[];
$carIdRows=$pdo->query("SELECT ID_MODEL,ID_CAR FROM model_caracteristiques")->fetchAll();
foreach($carIdRows as $r) $carIdsByModel[$r['ID_MODEL']][]=(int)$r['ID_CAR'];

/* Données formulaire */
$providers  = $pdo->query("SELECT * FROM providers ORDER BY name")->fetchAll();
$categories = $pdo->query("SELECT * FROM categorie ORDER BY name")->fetchAll();
$allCars    = $pdo->query("SELECT * FROM caracteristiques WHERE statut='actif' ORDER BY name")->fetchAll();
?>
<!DOCTYPE html><html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Modèles IA — Admin SearchIA</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../styles/style.css">
<link rel="stylesheet" href="../../styles/admin.css">
<style>
.form-group{margin-bottom:16px}
.form-label{display:block;font-size:.8rem;font-weight:600;margin-bottom:5px;color:var(--adm-text,#1e293b)}
.form-control{width:100%;padding:8px 10px;border:1px solid var(--adm-border,#e2e8f0);border-radius:8px;font-size:.875rem;background:var(--adm-surface,#fff);color:var(--adm-text,#1e293b);box-sizing:border-box}
.form-control:focus{outline:none;border-color:var(--adm-blue,#3b82f6)}
.cars-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:7px;max-height:180px;overflow-y:auto;padding:3px}
.car-cb-label{display:flex;align-items:center;gap:7px;padding:6px 9px;border:1px solid var(--adm-border,#e2e8f0);border-radius:7px;cursor:pointer;font-size:.8rem;transition:background .15s,border-color .15s}
.car-cb-label:hover{background:var(--adm-hover,#f1f5f9);border-color:var(--adm-blue,#3b82f6)}
.car-cb-label input{accent-color:var(--adm-blue,#3b82f6);width:14px;height:14px;flex-shrink:0}
.badge-car{font-size:.63rem;font-weight:600;padding:2px 6px;border-radius:20px;background:#ecfdf5;color:#065f46;white-space:nowrap}
.badge-status-actif{background:#dcfce7;color:#166534;font-size:.72rem;font-weight:600;padding:3px 9px;border-radius:20px}
.badge-status-inactif{background:#fee2e2;color:#991b1b;font-size:.72rem;font-weight:600;padding:3px 9px;border-radius:20px}
.badge-status-en_cours{background:#fef9c3;color:#854d0e;font-size:.72rem;font-weight:600;padding:3px 9px;border-radius:20px}
.badge-prov{font-size:.7rem;color:var(--adm-muted,#64748b);background:var(--adm-hover,#f1f5f9);padding:2px 7px;border-radius:12px}
</style>
</head>
<body class="adm-body">
<?php include 'sidebar.php'; ?>
<div class="adm-main">
  <div class="adm-topbar">
    <div>
      <h1 class="adm-title">Modèles IA</h1>
      <p class="adm-sub">Gérez les modèles disponibles sur la plateforme</p>
    </div>
    <button class="btn-adm-primary" onclick="openModal('addModal')">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Ajouter
    </button>
  </div>

  <?php if($msg): ?>
  <div class="adm-alert adm-alert-<?=$msgType?>"><?=$msg?>
    <button onclick="this.parentElement.remove()" style="float:right;background:none;border:none;cursor:pointer;font-size:1.1rem">×</button>
  </div>
  <?php endif; ?>

  <!-- Recherche -->
  <form method="GET" class="adm-search">
    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="text" name="q" placeholder="Rechercher un modèle…" value="<?=htmlspecialchars($q)?>">
    <button type="submit">Rechercher</button>
    <?php if($q): ?><a href="models.php">✕</a><?php endif; ?>
  </form>

  <!-- Tableau -->
  <div class="adm-section">
    <div class="adm-sec-head"><h2><?=$total?> modèle<?=$total>1?'s':''?></h2></div>
    <div class="tbl-wrap"><table class="adm-table">
      <thead>
        <tr><th>#</th><th>Nom</th><th>Provider</th><th>Catégorie</th><th>Tags</th><th>Caractéristiques</th><th>Statut</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php if(empty($models)): ?>
        <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--adm-muted)">Aucun modèle.</td></tr>
      <?php endif; ?>
      <?php foreach($models as $m): ?>
      <tr>
        <td class="td-id"><?=$m['ID_MODEL']?></td>
        <td style="font-weight:600;font-size:.875rem"><?=htmlspecialchars($m['name'])?></td>
        <td><span class="badge-prov"><?=htmlspecialchars($m['prov_name']??'—')?></span></td>
        <td><span class="badge-cat"><?=htmlspecialchars($m['cat_name']??'—')?></span></td>
        <td style="font-size:.75rem;color:var(--adm-muted)"><?=htmlspecialchars($m['tags']??'—')?></td>
        <td>
          <div style="display:flex;flex-wrap:wrap;gap:3px">
            <?php foreach(($carByModel[$m['ID_MODEL']]??[]) as $cn): ?>
              <span class="badge-car"><?=htmlspecialchars($cn)?></span>
            <?php endforeach; ?>
            <?php if(empty($carByModel[$m['ID_MODEL']])): ?><span style="font-size:.75rem;color:var(--adm-muted)">—</span><?php endif; ?>
          </div>
        </td>
        <td><span class="badge-status-<?=$m['status']?>"><?=ucfirst(str_replace('_',' ',$m['status']))?></span></td>
        <td>
          <div class="act-btns">
            <button class="btn-edt" data-id="<?=$m['ID_MODEL']?>" onclick="openEditById(this.dataset.id)">✎</button>
            <a href="models.php?action=supprimer&id=<?=$m['ID_MODEL']?>" class="btn-del" onclick="return confirm('Supprimer ce modèle ?')">🗑</a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php if($pages>1): ?>
    <div class="adm-pag">
      <?php for($p=1;$p<=$pages;$p++): ?>
        <a href="?q=<?=urlencode($q)?>&page=<?=$p?>" class="pag-btn <?=$p===$page?'active':''?>"><?=$p?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ══ Modal Ajouter ══ -->
<div class="adm-modal-overlay" id="addModal" onclick="if(event.target===this)closeModal('addModal')">
  <div class="adm-modal">
    <div class="adm-modal-head"><h3>Ajouter un modèle</h3><button onclick="closeModal('addModal')" class="adm-modal-close">×</button></div>
    <form method="POST" class="adm-modal-body">
      <input type="hidden" name="model_id" value="0">
      <div class="form-group">
        <label class="form-label">Nom du modèle *</label>
        <input class="form-control" type="text" name="name" required maxlength="150" placeholder="Ex : GPT-4o, Claude 3…">
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea class="form-control" name="description" rows="2" placeholder="Brève description…"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Tags <small style="font-weight:400;color:var(--adm-muted)">(séparés par virgule)</small></label>
        <input class="form-control" type="text" name="tags" placeholder="texte,code,vision…">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label class="form-label">Provider</label>
          <select class="form-control" name="ID_PROVIDERS">
            <option value="">— Sélectionner —</option>
            <?php foreach($providers as $p): ?>
              <option value="<?=$p['ID_PROVIDERS']?>"><?=htmlspecialchars($p['name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Catégorie</label>
          <select class="form-control" name="ID_CATEGORIE">
            <option value="">— Sélectionner —</option>
            <?php foreach($categories as $c): ?>
              <option value="<?=$c['ID_CATEGORIE']?>"><?=htmlspecialchars($c['name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <input type="hidden" name="status" value="actif">
      <div class="form-group">
        <label class="form-label">Caractéristiques</label>
        <div class="cars-grid">
          <?php foreach($allCars as $car): ?>
            <label class="car-cb-label">
              <input type="checkbox" name="car_ids[]" value="<?=$car['ID_CAR']?>">
              <?=htmlspecialchars($car['name'])?>
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

<!-- ══ Modal Modifier ══ -->
<div class="adm-modal-overlay" id="editModal" onclick="if(event.target===this)closeModal('editModal')">
  <div class="adm-modal">
    <div class="adm-modal-head"><h3>Modifier le modèle</h3><button onclick="closeModal('editModal')" class="adm-modal-close">×</button></div>
    <form method="POST" class="adm-modal-body">
      <input type="hidden" name="model_id" id="e_mid">
      <div class="form-group">
        <label class="form-label">Nom du modèle *</label>
        <input class="form-control" type="text" id="e_name" name="name" required maxlength="150">
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea class="form-control" id="e_description" name="description" rows="2"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Tags <small style="font-weight:400;color:var(--adm-muted)">(séparés par virgule)</small></label>
        <input class="form-control" type="text" id="e_tags" name="tags">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label class="form-label">Provider</label>
          <select class="form-control" id="e_ID_PROVIDERS" name="ID_PROVIDERS">
            <option value="">— Sélectionner —</option>
            <?php foreach($providers as $p): ?>
              <option value="<?=$p['ID_PROVIDERS']?>"><?=htmlspecialchars($p['name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Catégorie</label>
          <select class="form-control" id="e_ID_CATEGORIE" name="ID_CATEGORIE">
            <option value="">— Sélectionner —</option>
            <?php foreach($categories as $c): ?>
              <option value="<?=$c['ID_CATEGORIE']?>"><?=htmlspecialchars($c['name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Statut</label>
        <select class="form-control" id="e_status" name="status">
          <option value="actif">Actif</option>
          <option value="inactif">Inactif</option>
          <option value="en_cours">En cours</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Caractéristiques</label>
        <div class="cars-grid">
          <?php foreach($allCars as $car): ?>
            <label class="car-cb-label">
              <input type="checkbox" class="e_car_cb" name="car_ids[]" value="<?=$car['ID_CAR']?>">
              <?=htmlspecialchars($car['name'])?>
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

<!-- ══ Script UNIQUE ════════════════════════════════════════════ -->
<script>
/* CORRECTION : json_encode sans JSON_FORCE_OBJECT pour que les valeurs
   restent des arrays JS ([] au lieu de {}) et que .map() fonctionne */
var carIdsByModel = <?= json_encode($carIdsByModel) ?>;
var allModelsData = <?= json_encode(array_column($allModelsForJs, null, 'ID_MODEL'), JSON_UNESCAPED_UNICODE) ?>;

function openModal(id){ document.getElementById(id).classList.add('open'); document.body.style.overflow='hidden'; }
function closeModal(id){ document.getElementById(id).classList.remove('open'); document.body.style.overflow=''; }

function openEditById(mid){
    var d = allModelsData[mid];
    if(!d){ alert('Modèle introuvable'); return; }

    document.getElementById('e_mid').value          = d.ID_MODEL;
    document.getElementById('e_name').value         = d.name         || '';
    document.getElementById('e_description').value  = d.description  || '';
    document.getElementById('e_tags').value         = d.tags         || '';
    document.getElementById('e_ID_PROVIDERS').value = d.ID_PROVIDERS || '';
    document.getElementById('e_ID_CATEGORIE').value = d.ID_CATEGORIE || '';
    document.getElementById('e_status').value       = d.status       || 'actif';

    /* carIdsByModel[mid] est maintenant un vrai array JS grâce au fix PHP */
    var linked = (carIdsByModel[mid] || []).map(Number);
    document.querySelectorAll('.e_car_cb').forEach(function(cb){
        cb.checked = linked.indexOf(parseInt(cb.value)) !== -1;
    });

    openModal('editModal');
}
</script>
</body>
</html>