<?php
require_once '../../includes/auth_admin.php';
require_once '../../includes/connexionbd.php';

$stats = [
    'total'   => $pdo->query("SELECT COUNT(*) FROM outils_ia")->fetchColumn(),
    'actifs'  => $pdo->query("SELECT COUNT(*) FROM outils_ia WHERE status='actif'")->fetchColumn(),
    'attente' => $pdo->query("SELECT COUNT(*) FROM outils_ia WHERE status='en_attente'")->fetchColumn(),
    'inactifs'=> $pdo->query("SELECT COUNT(*) FROM outils_ia WHERE status='inactif'")->fetchColumn(),
    'users'   => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'adherent'")->fetchColumn(),
    'admins'  => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
    'cats'    => $pdo->query("SELECT COUNT(*) FROM categorie")->fetchColumn(),
    'avg'     => $pdo->query("SELECT ROUND(AVG(global_rating),2) FROM outils_ia WHERE status='actif'")->fetchColumn() ?? 0,
];

$stats['notifs'] = $pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0")->fetchColumn();

$pending = $pdo->query("
    SELECT o.*, c.name AS cat_name, u.nom AS cr_nom 
    FROM outils_ia o 
    LEFT JOIN categorie c ON o.ID_CATEGORIE = c.ID_CATEGORIE 
    LEFT JOIN users u ON o.ID_USERS_CREATE = u.id 
    WHERE o.status = 'en_attente' 
    AND u.role = 'adherent'
    ORDER BY o.ID_OUTILS_IA DESC LIMIT 8
")->fetchAll();

$recent_users = $pdo->query("
    SELECT u.*, (SELECT COUNT(*) FROM outils_ia WHERE ID_USERS_CREATE = u.id) AS nb 
    FROM users u 
    WHERE u.role = 'adherent'
    ORDER BY u.id DESC LIMIT 5
")->fetchAll();

$bycat = $pdo->query("
    SELECT c.name, COUNT(o.ID_OUTILS_IA) AS cnt 
    FROM categorie c 
    LEFT JOIN outils_ia o ON c.ID_CATEGORIE = o.ID_CATEGORIE AND o.status = 'actif' 
    GROUP BY c.ID_CATEGORIE 
    ORDER BY cnt DESC LIMIT 7
")->fetchAll();

try {
    $recent_notifs = $pdo->query("
        SELECT n.*, u.nom 
        FROM notifications n 
        JOIN users u ON n.user_id = u.id 
        WHERE u.role = 'adherent'
        ORDER BY n.created_at DESC LIMIT 7
    ")->fetchAll();
} catch(PDOException $e) {
    try {
        $recent_notifs = $pdo->query("
            SELECT n.*, u.nom 
            FROM notifications n 
            JOIN users u ON n.ID_USERS = u.id 
            WHERE u.role = 'adherent'
            ORDER BY n.created_at DESC LIMIT 7
        ")->fetchAll();
    } catch(PDOException $e2) {
        $recent_notifs = $pdo->query("
            SELECT n.*, 'Système' as nom 
            FROM notifications n 
            ORDER BY n.created_at DESC LIMIT 7
        ")->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin — SearchIA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
   MAIN AREA
   ============================================================ */
.adm-main {
  margin-left: 240px;
  flex: 1;
  padding: 32px;
  min-height: 100vh;
}

.adm-topbar {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 28px;
  flex-wrap: wrap;
}

.adm-title {
  font-family: 'Nunito', sans-serif;
  font-size: 26px;
  font-weight: 900;
  color: var(--adm-text);
  letter-spacing: -0.5px;
  margin: 0 0 4px;
}

.adm-sub {
  font-size: 13.5px;
  color: var(--adm-muted);
  margin: 0;
}

.adm-alert-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 10px;
  background: #FFFBEB;
  color: var(--adm-orange);
  font-size: 13px;
  font-weight: 800;
  font-family: 'Nunito', sans-serif;
  text-decoration: none;
  border: 1px solid #FDE68A;
  transition: .2s ease;
  white-space: nowrap;
}

.adm-alert-btn:hover {
  background: #FEF3C7;
  transform: translateY(-1px);
}

/* ============================================================
   KPI GRID
   ============================================================ */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 18px;
  margin-bottom: 28px;
}

.kpi-card {
  display: flex;
  align-items: center;
  gap: 14px;
  background: var(--adm-surface);
  border: 1px solid var(--adm-border);
  border-radius: var(--adm-radius);
  box-shadow: var(--adm-shadow);
  padding: 20px;
  transition: .2s ease;
}

.kpi-card:hover {
  transform: translateY(-3px);
}

.kpi-ico {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  flex-shrink: 0;
}

.kpi-lbl {
  font-size: 12px;
  font-weight: 600;
  color: var(--adm-muted);
  margin-bottom: 4px;
}

.kpi-val {
  font-family: 'Nunito', sans-serif;
  font-size: 24px;
  font-weight: 900;
  color: var(--adm-text);
  line-height: 1;
}

/* ============================================================
   SECTIONS / CARDS
   ============================================================ */
.adm-section {
  background: var(--adm-surface);
  border: 1px solid var(--adm-border);
  border-radius: var(--adm-radius);
  box-shadow: var(--adm-shadow);
  padding: 22px;
  margin-bottom: 24px;
}

.adm-sec-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
  flex-wrap: wrap;
  gap: 8px;
}

.adm-sec-head h2 {
  font-family: 'Nunito', sans-serif;
  font-size: 16px;
  font-weight: 800;
  color: var(--adm-text);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}

.link-more {
  font-size: 12px;
  font-weight: 700;
  color: var(--primary);
  text-decoration: none;
}

.link-more:hover {
  color: var(--primary-dark);
  text-decoration: underline;
}

.two-col {
  display: grid;
  grid-template-columns: 1.4fr 1fr;
  gap: 24px;
  margin-bottom: 24px;
}

/* ============================================================
   TABLES
   ============================================================ */
.tbl-wrap {
  overflow-x: auto;
}

.adm-table {
  width: 100%;
  border-collapse: collapse;
}

.adm-table th {
  text-align: left;
  padding: 12px 14px;
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .7px;
  color: var(--adm-muted);
  border-bottom: 1px solid var(--adm-border);
  white-space: nowrap;
}

.adm-table td {
  padding: 13px 14px;
  font-size: 13.5px;
  border-bottom: 1px solid var(--adm-border);
  vertical-align: middle;
}

.adm-table tr:last-child td {
  border-bottom: none;
}

.adm-table tr:hover td {
  background: rgba(74,107,176,0.03);
}

.td-id {
  color: var(--adm-muted);
  font-size: 12.5px;
  font-weight: 700;
}

/* ── Cellule outil (logo + nom) ── */
.tool-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}

.t-thumb {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  object-fit: cover;
  border: 1px solid var(--adm-border);
  flex-shrink: 0;
}

/* ── Badge catégorie ── */
.badge-cat {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  background: #EFF4FF;
  color: var(--primary);
}

/* ── Badge rôle ── */
.role-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 800;
  font-family: 'Nunito', sans-serif;
  white-space: nowrap;
}

.role-badge.adherent {
  background: #F0FDF4;
  color: var(--adm-green);
}

.role-badge.admin {
  background: #EFF4FF;
  color: var(--adm-blue);
}

/* ============================================================
   ACTION BUTTONS (Valider / Refuser)
   ============================================================ */
.act-btns {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.btn-val,
.btn-ref {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 7px 13px;
  border-radius: 8px;
  font-size: 11.5px;
  font-weight: 800;
  font-family: 'Nunito', sans-serif;
  text-decoration: none;
  border: none;
  transition: .2s ease;
  white-space: nowrap;
}

.btn-val {
  background: #F0FDF4;
  color: var(--adm-green);
}

.btn-val:hover {
  background: #DCFCE7;
  transform: translateY(-1px);
}

.btn-ref {
  background: #FEF2F2;
  color: var(--adm-red);
}

.btn-ref:hover {
  background: #FEE2E2;
  transform: translateY(-1px);
}

/* ============================================================
   BAR LIST (Outils par catégorie)
   ============================================================ */
.bar-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.bar-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.bar-lbl {
  flex: 0 0 110px;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--adm-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.bar-track {
  flex: 1;
  height: 8px;
  border-radius: 999px;
  background: var(--adm-bg);
  overflow: hidden;
}

.bar-fill {
  height: 100%;
  border-radius: 999px;
  background: linear-gradient(90deg, var(--primary), var(--primary-dark));
  transition: width .4s ease;
}

.bar-val {
  flex: 0 0 30px;
  text-align: right;
  font-size: 12.5px;
  font-weight: 800;
  color: var(--adm-muted);
  font-family: 'Nunito', sans-serif;
}

/* ============================================================
   GENERIC BUTTONS (réutilisables sur autres pages admin)
   ============================================================ */
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
  color: var(--adm-muted);
  border: 1px solid var(--adm-border);
}

.btn-ghost:hover {
  background: var(--adm-bg);
  color: var(--adm-text);
}

.btn-danger-fill {
  background: #FEF2F2;
  color: var(--adm-red);
}

.btn-danger-fill:hover {
  background: #FEE2E2;
}

/* ============================================================
   EMPTY STATE
   ============================================================ */
.adm-empty {
  text-align: center;
  padding: 50px 20px;
  color: var(--adm-muted);
}

.adm-empty svg {
  opacity: .3;
  margin-bottom: 12px;
}

.adm-empty p {
  font-size: 14px;
  margin: 0;
}

/* ============================================================
   FORMS (filtres, recherche admin)
   ============================================================ */
.adm-input,
.adm-select {
  width: 100%;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--adm-border);
  background: var(--adm-surface);
  font-size: 13.5px;
  font-family: 'DM Sans', sans-serif;
  color: var(--adm-text);
  outline: none;
  transition: .2s ease;
}

.adm-input:focus,
.adm-select:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(74,107,176,0.12);
}

.adm-input::placeholder {
  color: var(--adm-muted);
}

/* ============================================================
   RESPONSIVE
   ============================================================ */
@media (max-width: 992px) {
  .two-col {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .adm-sidebar {
    width: 68px;
  }

  .adm-logo span,
  .adm-nav a span,
  .adm-nav-lbl,
  .adm-uname,
  .adm-urole,
  .adm-user > div:last-child {
    display: none;
  }

  .adm-logo {
    justify-content: center;
    padding: 20px 0;
  }

  .adm-nav a {
    justify-content: center;
    padding: 12px;
  }

  .adm-badge {
    position: absolute;
    top: 4px;
    right: 4px;
    margin-left: 0;
  }

  .adm-main {
    margin-left: 68px;
    padding: 20px;
  }

  .adm-title {
    font-size: 22px;
  }

  .kpi-grid {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 480px) {
  .kpi-grid {
    grid-template-columns: 1fr;
  }

  .act-btns {
    flex-direction: column;
  }
}
    </style>
</head>
<body class="adm-body">
<?php include 'sidebar.php'; ?>
<div class="adm-main">

    <div class="adm-topbar">
        <div>
            <h1 class="adm-title">Tableau de bord</h1>
            <p class="adm-sub">Bienvenue <b><?= htmlspecialchars($_SESSION['nom'] ?? 'Admin') ?></b> — Vue d'ensemble SearchIA</p>
        </div>
        <?php if ($stats['attente'] > 0): ?>
            <a href="outils.php?filter=en_attente" class="adm-alert-btn">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
                <?= $stats['attente'] ?> outil<?= $stats['attente'] > 1 ? 's' : '' ?> en attente
            </a>
        <?php endif; ?>
    </div>

    <!-- KPIs -->
    <div class="kpi-grid">
        <?php 
        $kpis = [
            ['💻', 'Total outils', $stats['total'], '#EFF4FF'],
            ['✅', 'Actifs', $stats['actifs'], '#ECFDF5'],
            ['⏳', 'En attente', $stats['attente'], '#FFFBEB'],
            ['❌', 'Inactifs', $stats['inactifs'], '#FEF2F2'],
            ['👥', 'Adhérents', $stats['users'], '#F5F3FF'],
            ['🛡', 'Admins', $stats['admins'], '#EFF4FF'],
            ['📂', 'Catégories', $stats['cats'], '#F0FDFA'],
            ['⭐', 'Note moy.', $stats['avg'] . '/5', '#FEFCE8'],
        ]; 
        foreach ($kpis as $k): ?>
            <div class="kpi-card">
                <div class="kpi-ico" style="background:<?= $k[3] ?>"><?= $k[0] ?></div>
                <div>
                    <div class="kpi-lbl"><?= $k[1] ?></div>
                    <div class="kpi-val"><?= $k[2] ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Outils en attente -->
    <?php if (!empty($pending)): ?>
        <div class="adm-section">
            <div class="adm-sec-head">
                <h2>⏳ Outils en attente de validation</h2>
                <a href="outils.php?filter=en_attente" class="link-more">Voir tous →</a>
            </div>
            <div class="tbl-wrap">
                <table class="adm-table">
                    <thead>
                        <tr><th>#</th><th>Outil</th><th>Catégorie</th><th>Soumis par</th><th>Version</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending as $p): ?>
                            <tr>
                                <td class="td-id"><?= $p['ID_OUTILS_IA'] ?></td>
                                <td>
                                    <div class="tool-cell">
                                        <?php if ($p['logo_url']): ?>
                                            <img src="../../public/<?= htmlspecialchars($p['logo_url']) ?>" class="t-thumb" alt="" onerror="this.style.display='none'">
                                        <?php endif; ?>
                                        <span style="font-weight:600"><?= htmlspecialchars($p['nom']) ?></span>
                                    </div>
                                </td>
                                <td><span class="badge-cat"><?= htmlspecialchars($p['cat_name'] ?? '—') ?></span></td>
                                <td style="font-size:.82rem;color:var(--adm-muted)"><?= htmlspecialchars($p['cr_nom'] ?? '—') ?></td>
                                <td style="font-size:.82rem"><?= $p['version'] ? 'v' . $p['version'] : '—' ?></td>
                                <td>
                                    <div class="act-btns">
                                        <a href="outils.php?action=valider&id=<?= $p['ID_OUTILS_IA'] ?>" class="btn-val" onclick="return confirm('Valider et notifier le créateur ?')">✓ Valider</a>
                                        <a href="outils.php?action=refuser&id=<?= $p['ID_OUTILS_IA'] ?>" class="btn-ref" onclick="return confirm('Refuser et notifier le créateur ?')">✗ Refuser</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <div class="two-col">
        <div class="adm-section">
            <div class="adm-sec-head"><h2>📊 Outils par catégorie</h2></div>
            <?php $mx = max(1, max(array_column($bycat, 'cnt') ?? [1])); ?>
            <div class="bar-list">
                <?php foreach ($bycat as $b): ?>
                    <div class="bar-row">
                        <span class="bar-lbl"><?= htmlspecialchars($b['name'] ?? '—') ?></span>
                        <div class="bar-track">
                            <div class="bar-fill" style="width:<?= round($b['cnt'] / $mx * 100) ?>%"></div>
                        </div>
                        <span class="bar-val"><?= $b['cnt'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="adm-section">
            <div class="adm-sec-head"><h2>👥 Derniers adhérents inscrits</h2><a href="users.php" class="link-more">Voir tous →</a></div>
            <?php 
            $cols = ['#2563EB', '#059669', '#D97706', '#DC2626', '#7C3AED']; 
            foreach ($recent_users as $u): 
                $c = $cols[$u['id'] % count($cols)]; ?>
                <div style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--adm-border)">
                    <div style="width:34px;height:34px;border-radius:50%;background:<?= $c ?>;color:#fff;font-size:.78rem;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <?= strtoupper(substr($u['nom'] ?? '?', 0, 1)) ?>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="font-weight:700;font-size:.875rem"><?= htmlspecialchars($u['nom'] ?? '') ?></div>
                        <div style="font-size:.72rem;color:var(--adm-muted)"><?= htmlspecialchars($u['email'] ?? '') ?></div>
                    </div>
                    <span class="role-badge adherent">👤 Adhérent</span>
                    <span style="font-size:.72rem;color:var(--adm-muted)"><?= $u['nb'] ?> outil<?= $u['nb'] > 1 ? 's' : '' ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Notifications récentes -->
    <div class="adm-section">
        <div class="adm-sec-head"><h2>🔔 Notifications récentes</h2></div>
        <?php if (empty($recent_notifs)): ?>
            <p style="color:var(--adm-muted);text-align:center;padding:20px">Aucune notification.</p>
        <?php else:
            $tico = ['soumission' => '#D97706', 'validation' => '#059669', 'refus' => '#DC2626', 'info' => '#2563EB'];
            $tlbl = ['soumission' => '⏳ Soumission', 'validation' => '✅ Validation', 'refus' => '❌ Refus', 'info' => 'ℹ️ Info'];
        ?>
            <div class="tbl-wrap">
                <table class="adm-table">
                    <thead>
                        <tr><th>Destinataire</th><th>Type</th><th>Titre</th><th>Date</th><th>Lu</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_notifs as $n): 
                            $c = $tico[$n['type'] ?? 'info'] ?? '#2563EB'; ?>
                            <tr>
                                <td style="font-size:.85rem;font-weight:600"><?= htmlspecialchars($n['nom'] ?? 'Système') ?></td>
                                <td><span style="font-size:.7rem;font-weight:700;padding:2px 9px;border-radius:99px;background:<?= $c ?>22;color:<?= $c ?>"><?= $tlbl[$n['type'] ?? 'info'] ?? $n['type'] ?></span></td>
                                <td style="font-size:.83rem"><?= htmlspecialchars($n['titre'] ?? 'Notification') ?></td>
                                <td style="font-size:.78rem;color:var(--adm-muted)"><?= date('d/m/Y H:i', strtotime($n['created_at'] ?? 'now')) ?></td>
                                <td><?= ($n['is_read'] ?? 0) ? '<span style="color:var(--adm-green);font-size:.8rem">✓ Lu</span>' : '<span style="color:var(--adm-orange);font-size:.8rem">● Non lu</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>