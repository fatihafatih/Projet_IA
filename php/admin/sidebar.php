<?php $cur = basename($_SERVER['PHP_SELF']); ?>
<aside class="adm-sidebar">
  <div class="adm-logo">
    <a href="/Projet_IA/php/dashboard.php"><img src="/Projet_IA/public/logo1.png" alt="" style="width:30px;height:30px;"></a>
    <span>Search<b>IA</b> <em>Admin</em></span>
  </div>
  <nav class="adm-nav"> 

    <div class="adm-nav-lbl">Principal</div>
    <a href="index.php" class="<?=$cur==='index.php'?'active':''?>">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      Tableau de bord
    </a>

    <div class="adm-nav-lbl">Gestion</div>

    <a href="outils.php" class="<?=$cur==='outils.php'?'active':''?>">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
      Outils IA
      <?php if(isset($pdo)):
        $nb=$pdo->query("SELECT COUNT(*) FROM outils_ia WHERE status='en_attente'")->fetchColumn();
        if($nb>0):?>
          <span class="adm-badge"><?=$nb?></span>
      <?php endif;endif;?>
    </a>

    <a href="models.php" class="<?=$cur==='models.php'?'active':''?>">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
      Modèles IA
    </a>

    <a href="users.php" class="<?=$cur==='users.php'?'active':''?>">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Utilisateurs
    </a>

    <a href="notifications.php" class="<?=$cur==='notifications.php'?'active':''?>">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      Notifications
      <?php if(isset($pdo)):
        $nbNotif=$pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read=0")->fetchColumn();
        if($nbNotif>0):?>
          <span class="adm-badge"><?=$nbNotif?></span>
      <?php endif;endif;?>
    </a>

    <div class="adm-nav-lbl">Accès rapide</div>
    <a href="/Projet_IA/php/dashboard.php" target="_blank">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
      Voir le site
    </a>

  </nav>
  <div class="adm-foot">
    <div class="adm-user">
      <div class="adm-av"><?=strtoupper(substr($_SESSION['nom']??'A',0,1))?></div>
      <div>
        <div class="adm-uname"><?=htmlspecialchars($_SESSION['nom']??'Admin')?></div>
        <div class="adm-urole">🛡 Administrateur</div>
      </div>
    </div>
    <a href="/Projet_IA/php/logout.php" class="adm-logout" title="Déconnexion">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    </a>
  </div>
</aside>