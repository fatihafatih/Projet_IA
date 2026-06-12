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
  /* ============================================================
   MODELS.CSS — Styles spécifiques à la page models.php
   À charger après admin.css
   ============================================================ */

/* ── Bouton principal (header) ── */
.btn-adm-primary {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 10px 18px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 800;
  font-family: 'Nunito', sans-serif;
  border: none;
  cursor: pointer;
  text-decoration: none;
  background: var(--primary);
  color: #fff;
  box-shadow: 0 4px 14px rgba(74,107,176,0.25);
  transition: .2s ease;
}

.btn-adm-primary:hover {
  background: var(--primary-dark);
  transform: translateY(-1px);
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

/* ── Barre de recherche ── */
.adm-search {
  display: flex;
  align-items: center;
  gap: 10px;
  background: var(--adm-surface);
  border: 1px solid var(--adm-border);
  border-radius: 10px;
  padding: 10px 14px;
  margin-bottom: 18px;
  max-width: 480px;
}

.adm-search svg {
  color: var(--adm-muted);
  flex-shrink: 0;
}

.adm-search input[type="text"] {
  flex: 1;
  border: none;
  outline: none;
  background: transparent;
  font-size: 13.5px;
  font-family: 'DM Sans', sans-serif;
  color: var(--adm-text);
}

.adm-search input::placeholder {
  color: var(--adm-muted);
}

.adm-search button {
  border: none;
  background: var(--primary);
  color: #fff;
  font-size: 12px;
  font-weight: 800;
  font-family: 'Nunito', sans-serif;
  padding: 7px 14px;
  border-radius: 8px;
  cursor: pointer;
  transition: .2s ease;
  white-space: nowrap;
}

.adm-search button:hover {
  background: var(--primary-dark);
}

.adm-search a {
  color: var(--adm-muted);
  text-decoration: none;
  font-size: 14px;
  font-weight: 700;
  padding: 0 4px;
}

.adm-search a:hover {
  color: var(--adm-red);
}

/* ── Badges provider / catégorie / caractéristiques ── */
.badge-prov {
  font-size: .7rem;
  color: var(--adm-muted);
  background: var(--adm-bg);
  padding: 2px 7px;
  border-radius: 12px;
}

.badge-car {
  font-size: .63rem;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 20px;
  background: #ECFDF5;
  color: #065F46;
  white-space: nowrap;
}

/* ── Badges statut ── */
.badge-status-actif {
  background: #DCFCE7;
  color: #166534;
  font-size: .72rem;
  font-weight: 600;
  padding: 3px 9px;
  border-radius: 20px;
}

.badge-status-inactif {
  background: #FEE2E2;
  color: #991B1B;
  font-size: .72rem;
  font-weight: 600;
  padding: 3px 9px;
  border-radius: 20px;
}

.badge-status-en_cours {
  background: #FEF9C3;
  color: #854D0E;
  font-size: .72rem;
  font-weight: 600;
  padding: 3px 9px;
  border-radius: 20px;
}

/* ── Boutons d'action tableau ── */
.btn-edt,
.btn-del {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  font-size: 13px;
  border: none;
  cursor: pointer;
  text-decoration: none;
  transition: .2s ease;
}

.btn-edt {
  background: #EFF4FF;
  color: var(--primary);
}

.btn-edt:hover {
  background: #DBEAFE;
  transform: translateY(-1px);
}

.btn-del {
  background: #FEF2F2;
  color: var(--adm-red);
}

.btn-del:hover {
  background: #FEE2E2;
  transform: translateY(-1px);
}

/* ── Pagination ── */
.adm-pag {
  display: flex;
  gap: 6px;
  justify-content: center;
  margin-top: 20px;
  flex-wrap: wrap;
}

.pag-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 34px;
  height: 34px;
  padding: 0 8px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 700;
  font-family: 'Nunito', sans-serif;
  text-decoration: none;
  color: var(--adm-text);
  background: var(--adm-surface);
  border: 1px solid var(--adm-border);
  transition: .2s ease;
}

.pag-btn:hover {
  border-color: var(--primary);
  color: var(--primary);
}

.pag-btn.active {
  background: var(--primary);
  color: #fff;
  border-color: var(--primary);
}

/* ── Modales ── */
.adm-modal-overlay {
  display: none;
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(27, 42, 74, 0.45);
  z-index: 1000;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.adm-modal-overlay.open {
  display: flex;
}

.adm-modal {
  background: var(--adm-surface);
  border-radius: var(--adm-radius);
  width: 100%;
  max-width: 560px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px rgba(27,42,74,0.25);
  animation: admModalIn .2s ease;
}

@keyframes admModalIn {
  from { opacity: 0; transform: translateY(12px) scale(.98); }
  to   { opacity: 1; transform: translateY(0) scale(1); }
}

.adm-modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 22px;
  border-bottom: 1px solid var(--adm-border);
}

.adm-modal-head h3 {
  font-family: 'Nunito', sans-serif;
  font-size: 16px;
  font-weight: 800;
  color: var(--adm-text);
  margin: 0;
}

.adm-modal-close {
  border: none;
  background: var(--adm-bg);
  color: var(--adm-muted);
  width: 30px;
  height: 30px;
  border-radius: 8px;
  font-size: 18px;
  line-height: 1;
  cursor: pointer;
  transition: .2s ease;
}

.adm-modal-close:hover {
  background: #FEF2F2;
  color: var(--adm-red);
}

.adm-modal-body {
  padding: 22px;
  overflow-y: auto;
  flex: 1;
}

.adm-modal-foot {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 22px;
  border-top: 1px solid var(--adm-border);
}

.btn-cancel {
  display: inline-flex;
  align-items: center;
  padding: 10px 18px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 800;
  font-family: 'Nunito', sans-serif;
  border: 1px solid var(--adm-border);
  background: var(--adm-surface);
  color: var(--adm-muted);
  cursor: pointer;
  transition: .2s ease;
}

.btn-cancel:hover {
  background: var(--adm-bg);
  color: var(--adm-text);
}

/* ── Champs de formulaire ── */
.form-group {
  margin-bottom: 16px;
}

.form-label {
  display: block;
  font-size: .8rem;
  font-weight: 600;
  margin-bottom: 5px;
  color: var(--adm-text);
}

.form-control {
  width: 100%;
  padding: 8px 10px;
  border: 1px solid var(--adm-border);
  border-radius: 8px;
  font-size: .875rem;
  background: var(--adm-surface);
  color: var(--adm-text);
  box-sizing: border-box;
}

.form-control:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(74,107,176,0.12);
}

/* ── Grille de sélection des caractéristiques ── */
.cars-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
  gap: 7px;
  max-height: 180px;
  overflow-y: auto;
  padding: 3px;
}

.car-cb-label {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 6px 9px;
  border: 1px solid var(--adm-border);
  border-radius: 7px;
  cursor: pointer;
  font-size: .8rem;
  transition: background .15s, border-color .15s;
}

.car-cb-label:hover {
  background: var(--adm-bg);
  border-color: var(--primary);
}

.car-cb-label input {
  accent-color: var(--primary);
  width: 14px;
  height: 14px;
  flex-shrink: 0;
}
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