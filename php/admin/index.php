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
    <link rel="stylesheet" href="../../styles/style.css">
    <link rel="stylesheet" href="../../styles/admin.css">
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