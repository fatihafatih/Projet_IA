<style>

  
:root {
  --primary: #4A6BB0;
  --primary-dark: #39558f;

  --accent: #F3E5AB;

  --adm-bg: #FAF8F5;
  --adm-surface: #FFFFFF;

  --adm-text: #1B2A4A;
  --adm-muted: #667085;

  --adm-border: #E5E7EB;

  --adm-red: #DC2626;
  --adm-green: #16A34A;
  --adm-orange: #D97706;
  --adm-blue: #2563EB;

  --adm-radius: 14px;
  --adm-shadow: 0 4px 24px rgba(27, 42, 74, 0.08);

  --adm-sidebar-bg: #1B2A4A;
  --adm-sidebar-hover: rgba(255,255,255,0.08);
}

*, *::before, *::after {
  box-sizing: border-box;
}

.adm-body {
  margin: 0;
  display: flex;
  min-height: 100vh;
  font-family: 'DM Sans', sans-serif;
  background: var(--adm-bg);
  color: var(--adm-text);
}

/* ============================================================
   SIDEBAR
   ============================================================ */
.adm-sidebar {
  width: 240px;
  min-height: 100vh;
  background: var(--adm-sidebar-bg);
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0;
  left: 0;
  bottom: 0;
  z-index: 50;
  padding: 0 0 24px;
  box-shadow: 4px 0 18px rgba(0,0,0,0.04);
}

.adm-logo {
  padding: 26px 24px 20px;
  display: flex;
  align-items: center;
  gap: 12px;
  border-bottom: 1px solid rgba(255,255,255,0.08);
}

.adm-logo span {
  font-family: 'Nunito', sans-serif;
  font-size: 19px;
  font-weight: 900;
  color: #fff;
  letter-spacing: -0.5px;
}

.adm-logo em {
  font-style: normal;
  font-weight: 700;
  font-size: 12px;
  color: var(--accent);
  margin-left: 2px;
}

.adm-nav {
  padding: 18px 12px 6px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.adm-nav-lbl {
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: rgba(255,255,255,0.45);
  padding: 14px 12px 8px;
}

.adm-nav a {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 11px 12px;
  border-radius: 12px;
  color: rgba(255,255,255,0.75);
  font-size: 14px;
  font-weight: 700;
  text-decoration: none;
  transition: all .2s ease;
  position: relative;
}

.adm-nav a svg {
  flex-shrink: 0;
  opacity: .8;
}

.adm-nav a:hover {
  background: var(--adm-sidebar-hover);
  color: #fff;
  transform: translateX(2px);
}

.adm-nav a.active {
  background: var(--accent);
  color: var(--adm-text);
  box-shadow: 0 6px 14px rgba(243,229,171,0.25);
}

.adm-nav a.active svg {
  opacity: 1;
}

.adm-badge {
  margin-left: auto;
  background: var(--primary);
  color: #fff;
  font-size: 10px;
  font-weight: 800;
  padding: 2px 7px;
  border-radius: 999px;
  font-family: 'Nunito', sans-serif;
}

.adm-nav a.active .adm-badge {
  background: var(--primary);
  color: #fff;
}

/* ── Footer sidebar (user info) ── */
.adm-foot {
  margin-top: auto;
  padding: 16px 12px 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.adm-user {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px;
  border-radius: 14px;
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.08);
}

.adm-av {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--accent);
  color: var(--adm-text);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 900;
  font-family: 'Nunito', sans-serif;
  flex-shrink: 0;
}

.adm-uname {
  color: #fff;
  font-size: 13px;
  font-weight: 700;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.adm-urole {
  color: rgba(255,255,255,0.5);
  font-size: 11px;
}

.adm-logout {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px;
  border-radius: 10px;
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.08);
  color: rgba(255,255,255,0.7);
  text-decoration: none;
  font-size: 12px;
  font-weight: 700;
  transition: .2s ease;
}

.adm-logout:hover {
  background: var(--accent);
  color: var(--adm-text);
  border-color: var(--accent);
}

</style>
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