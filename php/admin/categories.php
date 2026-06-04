<?php
require_once '../../includes/auth_admin.php';
require_once '../../includes/connexionbd.php';
$msg=''; $msgType='success';
$action=$_GET['action']??''; $id=(int)($_GET['id']??0);
if($action==='supprimer'&&$id>0){
    $used=$pdo->prepare("SELECT COUNT(*) FROM outils_ia WHERE ID_CATEGORIE=?"); $used->execute([$id]); $used=$used->fetchColumn();
    if($used>0){$msg="⚠️ Impossible : $used outil(s) utilisent cette catégorie.";$msgType='warning';}
    else{$pdo->prepare("DELETE FROM categorie WHERE ID_CATEGORIE=?")->execute([$id]);header('Location: categories.php?msg='.urlencode('🗑 Catégorie supprimée.'));exit;}
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    $cid=(int)($_POST['cat_id']??0); $name=trim($_POST['cat_name']??''); $icon=trim($_POST['icon']??'');
    if(!$name){$msg='Nom obligatoire.';$msgType='danger';}
    else{
        if($cid>0) $pdo->prepare("UPDATE categorie SET name=?,icon=? WHERE ID_CATEGORIE=?")->execute([$name,$icon,$cid]);
        else $pdo->prepare("INSERT INTO categorie(name,icon) VALUES(?,?)")->execute([$name,$icon]);
        header('Location: categories.php?msg='.urlencode($cid>0?'✅ Mise à jour.':'✅ Catégorie ajoutée.')); exit;
    }
}
if(!$msg&&isset($_GET['msg'])){$msg=htmlspecialchars($_GET['msg']);$msgType=$_GET['type']??'success';}
$cats=$pdo->query("SELECT c.*,COUNT(o.ID_OUTILS_IA) AS nb FROM categorie c LEFT JOIN outils_ia o ON c.ID_CATEGORIE=o.ID_CATEGORIE GROUP BY c.ID_CATEGORIE ORDER BY c.name")->fetchAll();
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Catégories — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../styles/style.css">
<link rel="stylesheet" href="../../styles/admin.css"></head>
<body class="adm-body">
<?php include 'sidebar.php'; ?>
<div class="adm-main">
  <div class="adm-topbar">
    <div><h1 class="adm-title">Catégories</h1><p class="adm-sub"><?=count($cats)?> catégories</p></div>
    <button class="btn-adm-primary" onclick="openModal('addC')">+ Ajouter</button>
  </div>
  <?php if($msg): ?><div class="adm-alert adm-alert-<?=$msgType?>"><?=$msg?><button onclick="this.parentElement.remove()" style="float:right;background:none;border:none;cursor:pointer;font-size:1.1rem">×</button></div><?php endif; ?>
  <div class="adm-section"><div class="tbl-wrap"><table class="adm-table">
    <thead><tr><th>#</th><th>Icône</th><th>Nom</th><th>Outils</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if(empty($cats)): ?><tr><td colspan="5" style="text-align:center;padding:40px;color:var(--adm-muted)">Aucune catégorie.</td></tr><?php endif; ?>
    <?php foreach($cats as $c): ?>
    <tr>
      <td class="td-id"><?=$c['ID_CATEGORIE']?></td>
      <td style="font-size:1.4rem;text-align:center"><?=htmlspecialchars($c['icon']??'🤖')?></td>
      <td><strong><?=htmlspecialchars($c['name']??'')?></strong></td>
      <td><span class="badge-cat"><?=$c['nb']?> outil<?=$c['nb']>1?'s':''?></span></td>
      <td><div class="act-btns">
        <button type="button" class="btn-edt"
                onclick='openEditCat(<?= json_encode($c, JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_HEX_TAG) ?>)'>✎</button>
        <a href="categories.php?action=supprimer&id=<?=$c['ID_CATEGORIE']?>" class="btn-del" onclick="return confirm('Supprimer ?')">🗑</a>
      </div></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div></div>
</div>
<!-- Ajouter -->
<div class="adm-modal-overlay" id="addC" onclick="if(event.target===this)closeModal('addC')">
  <div class="adm-modal"><div class="adm-modal-head"><h3>Ajouter une catégorie</h3><button onclick="closeModal('addC')" class="adm-modal-close">×</button></div>
  <form method="POST" class="adm-modal-body"><input type="hidden" name="cat_id" value="0">
    <div class="adm-fg"><label>Nom *</label><input type="text" name="cat_name" required placeholder="Ex : Génération d'images"></div>
    <div class="adm-fg"><label>Icône (emoji)</label><input type="text" name="icon" placeholder="🖼️"></div>
    <div class="adm-modal-foot"><button type="button" class="btn-cancel" onclick="closeModal('addC')">Annuler</button><button type="submit" class="btn-adm-primary">Ajouter</button></div>
  </form></div>
</div>
<!-- Modifier -->
<div class="adm-modal-overlay" id="editC" onclick="if(event.target===this)closeModal('editC')">
  <div class="adm-modal"><div class="adm-modal-head"><h3>Modifier</h3><button onclick="closeModal('editC')" class="adm-modal-close">×</button></div>
  <form method="POST" class="adm-modal-body"><input type="hidden" name="cat_id" id="ec_id">
    <div class="adm-fg"><label>Nom *</label><input type="text" name="cat_name" id="ec_name" required></div>
    <div class="adm-fg"><label>Icône</label><input type="text" name="icon" id="ec_icon"></div>
    <div class="adm-modal-foot"><button type="button" class="btn-cancel" onclick="closeModal('editC')">Annuler</button><button type="submit" class="btn-adm-primary">Enregistrer</button></div>
  </form></div>
</div>
<script>
function openModal(id){document.getElementById(id).classList.add('open');document.body.style.overflow='hidden';}
function closeModal(id){document.getElementById(id).classList.remove('open');document.body.style.overflow='';}
function openEditCat(d){
    document.getElementById('ec_id').value=d.ID_CATEGORIE;
    document.getElementById('ec_name').value=d.name||'';
    document.getElementById('ec_icon').value=d.icon||'';
    openModal('editC');
}
</script>
</body></html>