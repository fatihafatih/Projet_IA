<?php
require_once '../../includes/connexionbd.php';

// php/user/_layout.php  — inclure en haut de chaque page user
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /Projet_IA/php/login.php');
    exit;
}

$userName  = $_SESSION['nom']   ?? 'Utilisateur';
$userEmail = $_SESSION['email'] ?? '';
$initial   = strtoupper(mb_substr($userName, 0, 1));

$stmt = $pdo->prepare("
    SELECT image
    FROM users
    WHERE id = ?
");

$stmt->execute([$_SESSION['user_id']]);

$photo = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer nb notifications non lues (adapter selon ta BDD)
// $unread = $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id={$_SESSION['user_id']} AND lu=0")->fetchColumn();
$unread = 0; // placeholder

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?? 'Espace personnel' ?> — MonApp</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>

:root {
  /* ─────────────────────────────
     Palette Harmonisée
  ───────────────────────────── */
  --primary: #4A6BB0;
  --primary-dark: #39558f;

  --accent: #F3E5AB;

  --bg: #FAF8F5;
  --surface: #FFFFFF;

  --text: #1B2A4A;
  --muted: #667085;

  --border: #1B2A4A;

  --danger: #DC2626;
  --success: #16A34A;
  --warning: #D97706;
  --info: #2563EB;

  --radius: 14px;

  --shadow: 0 4px 24px rgba(27, 42, 74, 0.08);

  --sidebar-bg: #1B2A4A;
  --sidebar-hover: rgba(255,255,255,0.08);
  --topbar-bg: #1B2A4A;
}

*, *::before, *::after {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

body {
  font-family: 'DM Sans', sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  display: flex;
}

/* ─────────────────────────────
   SIDEBAR
───────────────────────────── */
.sidebar {
  width: 240px;
  min-height: 100vh;
  background: var(--sidebar-bg);
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

.sidebar-logo {
  padding: 26px 24px 20px;
  display: flex;
  align-items: center;
  gap: 12px;
  border-bottom: 1px solid rgba(255,255,255,0.08);
}

.sidebar-logo-icon {
  width: 38px;
  height: 38px;
  border-radius: 12px;
  background: rgba(255,255,255,0.12);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  backdrop-filter: blur(4px);
}

.sidebar-logo-text {
  font-family: 'Nunito', sans-serif;
  font-size: 19px;
  font-weight: 900;
  color: #fff;
  letter-spacing: -0.5px;
}

.sidebar-section {
  padding: 18px 12px 6px;
}

.sidebar-section-label {
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: rgba(255,255,255,0.45);
  padding: 0 12px;
  margin-bottom: 8px;
}

.nav-item {
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

.nav-item svg {
  flex-shrink: 0;
  opacity: .8;
}

.nav-item:hover {
  background: var(--sidebar-hover);
  color: #fff;
  transform: translateX(2px);
}

.nav-item.active {
  background: var(--accent);
  color: var(--text);
  box-shadow: 0 6px 14px rgba(243,229,171,0.25);
}

.nav-item.active svg {
  opacity: 1;
}

.nav-badge {
  margin-left: auto;
  background: var(--primary);
  color: #fff;
  font-size: 10px;
  font-weight: 800;
  padding: 2px 7px;
  border-radius: 999px;
  font-family: 'Nunito', sans-serif;
}

.sidebar-bottom {
  margin-top: auto;
  padding: 0 12px;
}

.sidebar-user {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px;
  border-radius: 14px;
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.08);
}

.sidebar-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--accent);
  color: var(--text);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 900;
  font-family: 'Nunito', sans-serif;
}

.sidebar-user-info {
  flex: 1;
  min-width: 0;
}

.sidebar-user-name {
  color: #fff;
  font-size: 13px;
  font-weight: 700;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sidebar-user-role {
  color: rgba(255,255,255,0.5);
  font-size: 11px;
}

/* ─────────────────────────────
   MAIN
───────────────────────────── */
.main {
  margin-left: 240px;
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

/* ─────────────────────────────
   TOPBAR
───────────────────────────── */
.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: relative;
  padding: 18px 30px;
  background: var(--topbar-bg);
  border-bottom: 1px solid rgba(255,255,255,0.05);
}

.topbar-title {
  position: absolute;
  left: 50%;
  transform: translateX(-50%);

  font-family: 'Nunito', sans-serif;
  font-size: 21px;
  font-weight: 900;
  color: #F3E5AB;
  letter-spacing: -.4px;
}

.topbar-right {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-left: auto;
}

.logout-btn {
  background: var(--accent);
  color: var(--text);
  border: none;
  padding: 9px 16px;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 800;
  text-decoration: none;
  transition: .2s ease;
}

.logout-btn:hover {
  background: #e8d58a;
  transform: translateY(-1px);
}

/* ─────────────────────────────
   CONTENT
───────────────────────────── */
.content {
  padding: 32px;
  flex: 1;
}

/* ─────────────────────────────
   CARDS
───────────────────────────── */
.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  overflow: hidden;
}

.card-header {
  padding: 20px 24px 16px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.card-title {
  font-family: 'Nunito', sans-serif;
  font-size: 16px;
  font-weight: 800;
  color: var(--text);
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-body {
  padding: 24px;
}

/* ─────────────────────────────
   STATS
───────────────────────────── */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 18px;
  margin-bottom: 28px;
}

.stat-card {
  position: relative;
  overflow: hidden;

  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);

  padding: 22px;

  box-shadow: var(--shadow);

  transition: .2s ease;
}

.stat-card:hover {
  transform: translateY(-3px);
}

.stat-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: var(--primary);
}

.stat-icon {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  background: rgba(74,107,176,0.12);
  color: var(--primary);

  display: flex;
  align-items: center;
  justify-content: center;

  margin-bottom: 14px;
}

.stat-value {
  font-family: 'Nunito', sans-serif;
  font-size: 30px;
  font-weight: 900;
  color: var(--text);
  line-height: 1;
}

.stat-label {
  margin-top: 6px;
  font-size: 12px;
  font-weight: 600;
  color: var(--muted);
}

/* ─────────────────────────────
   BADGES
───────────────────────────── */
.badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;

  padding: 4px 10px;

  border-radius: 999px;

  font-size: 11px;
  font-weight: 800;
  font-family: 'Nunito', sans-serif;
}

.badge-success {
  background: #F0FDF4;
  color: #16A34A;
}

.badge-danger {
  background: #FEF2F2;
  color: #DC2626;
}

.badge-warning {
  background: #FFF7ED;
  color: #D97706;
}

.badge-info {
  background: #EFF6FF;
  color: #2563EB;
}

/* ─────────────────────────────
   TABLES
───────────────────────────── */
.table-wrap {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th {
  text-align: left;
  padding: 12px 16px;

  font-size: 11px;
  font-weight: 800;

  text-transform: uppercase;
  letter-spacing: .7px;

  color: var(--muted);

  border-bottom: 1px solid var(--border);
}

td {
  padding: 14px 16px;
  font-size: 13.5px;
  border-bottom: 1px solid var(--border);
}

tr:last-child td {
  border-bottom: none;
}

tr:hover td {
  background: rgba(74,107,176,0.03);
}

/* ─────────────────────────────
   BUTTONS
───────────────────────────── */
.btn {
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

  transition: .2s ease;
}

.btn-primary {
  background: var(--primary);
  color: #fff;
  box-shadow: 0 4px 14px rgba(74,107,176,0.25);
}

.btn-primary:hover {
  background: var(--primary-dark);
  transform: translateY(-1px);
}

.btn-ghost {
  background: transparent;
  color: var(--muted);
  border: 1px solid var(--border);
}

.btn-ghost:hover {
  background: var(--bg);
  color: var(--text);
}

.btn-danger {
  background: #FEF2F2;
  color: var(--danger);
}

.btn-danger:hover {
  background: #FEE2E2;
}

/* ─────────────────────────────
   EMPTY STATE
───────────────────────────── */
.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: var(--muted);
}

.empty-state svg {
  opacity: .3;
  margin-bottom: 12px;
}

.empty-state p {
  font-size: 14px;
}

/* ─────────────────────────────
   ANIMATIONS
───────────────────────────── */
@keyframes fadeUp {
  from {
    opacity: 0;
    transform: translateY(14px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.fade-up {
  animation: fadeUp .35s ease both;
}

.fade-up-2 {
  animation: fadeUp .35s .1s ease both;
}

.fade-up-3 {
  animation: fadeUp .35s .2s ease both;
}

.fade-up-4 {
  animation: fadeUp .35s .3s ease both;
}

/* ─────────────────────────────
   RESPONSIVE
───────────────────────────── */
@media (max-width: 768px) {

  .sidebar {
    width: 68px;
  }

  .sidebar-logo-text,
  .nav-item span,
  .sidebar-user-info,
  .sidebar-section-label {
    display: none;
  }

  .main {
    margin-left: 68px;
  }

  .content {
    padding: 20px;
  }

  .topbar {
    padding: 16px 18px;
  }

  .topbar-title {
    font-size: 17px;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }
}

</style>
<?php if (!empty($extraCss)): ?>
<link rel="stylesheet" href="<?= $extraCss ?>">
<?php endif; ?>

</head>
<body>

<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-icon"></div>
    <div class="sidebar-logo-text">SearchIA</div>
  </div>

  <div class="sidebar-section">
    <div class="sidebar-section-label">Navigation</div>
     <a class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'dashboardd.php') ? 'active' : '' ?>" href="/Projet_IA/php/dashboard.php">
      <span> ⬅  Retour à acceuil</span>
    </a>



    <a class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'dashboard.php') ? 'active' : '' ?>" href="/Projet_IA/php/user/dashboard.php">
      <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      <span>Dashboard</span>
    </a>

    <a class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'profil.php') ? 'active' : '' ?>" href="/Projet_IA/php/user/profil.php">
      <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
      <span>Mon Profil</span>
    </a>

    <a class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'outils.php') ? 'active' : '' ?>" href="/Projet_IA/php/user/outils.php">
      <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
      <span>Mes Outils</span>
    </a>

    <a class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'favoris.php') ? 'active' : '' ?>" href="/Projet_IA/php/user/favoris.php">
      <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
      <span>Favoris</span>
    </a>

    <a class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'notifications.php') ? 'active' : '' ?>" href="/Projet_IA/php/user/notifications.php">
      <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <span>Notifications</span>
      <?php if ($unread > 0): ?>
        <span class="nav-badge"><?= $unread ?></span>
      <?php endif; ?>
    </a>


    <a class="nav-item <?= (basename($_SERVER['PHP_SELF']) === 'demandes.php') ? 'active' : '' ?>" href="/Projet_IA/php/user/demandes.php">
      <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      <span>Mes Demandes</span>
    </a>
  </div>

  <div class="sidebar-bottom">
    <div class="sidebar-user">
<div class="sidebar-avatar">
    <?php if (!empty($Photo)): ?>
         <img src="/Projet_IA/uploads/avatars/<?= htmlspecialchars($Photo) ?>"
             alt="Photo de profil">
    <?php else: ?>
        <?= $initial ?>
    <?php endif; ?>
</div>      <div class="sidebar-user-info">
        <div class="sidebar-user-name"><?= htmlspecialchars($userName) ?></div>
        <div class="sidebar-user-role">Membre</div>
      </div>
    </div>
  </div>
</aside>


<div class="main">
  <div class="topbar">
    <div class="topbar-title"><?= $pageTitle ?? 'Espace personnel' ?></div>
    <div class="topbar-right">
      <a href="/Projet_IA/php/user/notifications.php" style="position:relative;color:#FFFFFF;line-height:0;">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <?php if ($unread > 0): ?>
          <span style="position:absolute;top:-3px;right:-4px;width:8px;height:8px;background:var(--accent);border-radius:50%;border:2px solid var(--primary);"></span>
        <?php endif; ?>
      </a>
      <a href="/Projet_IA/php/logout.php" class="logout-btn">Déconnexion</a>
    </div>
  </div>
  <div class="content">