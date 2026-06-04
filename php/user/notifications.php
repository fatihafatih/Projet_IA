<?php
// ── Session + connexion AVANT tout output ───────────────────────
require_once '../../includes/connexionbd.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// ── Marquer toutes comme lues ───────────────────────────────────
if (isset($_GET['mark_all_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE ID_USERS = ?")
        ->execute([$userId]);
    header('Location: notifications.php');
    exit;
}

// ── Marquer une seule comme lue ─────────────────────────────────
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE ID_NOTIF = ? AND ID_USERS = ?")
        ->execute([$_GET['mark_read'], $userId]);
    header('Location: notifications.php');
    exit;
}

// ── Supprimer une notification ──────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $pdo->prepare("DELETE FROM notifications WHERE ID_NOTIF = ? AND ID_USERS = ?")
        ->execute([$_GET['delete'], $userId]);
    header('Location: notifications.php');
    exit;
}

// ── Gestion du Filtre Temporel ──────────────────────────────────
$filter = $_GET['filter'] ?? 'all';
$dateCondition = "";

switch ($filter) {
    Case 'today':
        $dateCondition = "AND DATE(created_at) = CURRENT_DATE()";
        break;
    Case 'week':
        $dateCondition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    Case 'older':
        $dateCondition = "AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
}

// ── Récupérer les notifications filtrées ───────────────────────
$stmt = $pdo->prepare("
    SELECT ID_NOTIF, titre, message, type, is_read, created_at
    FROM notifications
    WHERE ID_USERS = ? $dateCondition
    ORDER BY is_read ASC, created_at DESC
");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Formater la date relative ───────────────────────────────────
function timeAgo(string $dateStr): string {
    $diff = time() - strtotime($dateStr);
    if ($diff < 60)          return 'À l\'instant';
    if ($diff < 3600)        return 'Il y a ' . floor($diff / 60) . ' min';
    if ($diff < 86400)       return 'Il y a ' . floor($diff / 3600) . 'h';
    if ($diff < 172800)      return 'Hier';
    if ($diff < 604800)      return 'Il y a ' . floor($diff / 86400) . ' jours';
    return date('d/m/Y', strtotime($dateStr));
}

$unreadCount = count(array_filter($notifications, fn($n) => !$n['is_read']));

// ── Styles par type combinés avec le Jaune Beurre ────────────────
$typeStyles = [
    'success' => ['icon_bg' => '#DCFCE7', 'icon_color' => '#16A34A', 'glow' => 'rgba(22, 163, 74, 0.3)'],
    'danger'  => ['icon_bg' => '#FEE2E2', 'icon_color' => '#DC2626', 'glow' => 'rgba(220, 38, 38, 0.3)'],
    'info'    => ['icon_bg' => '#EBF1FF', 'icon_color' => '#4A6BB0', 'glow' => 'rgba(74, 107, 176, 0.3)'],
    'warning' => ['icon_bg' => '#FEF3C7', 'icon_color' => '#D97706', 'glow' => 'rgba(217, 119, 6, 0.3)'],
];
$typeIcons = [
    'success' => '<polyline points="20 6 9 17 4 12"/>',
    'danger'  => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
    'info'    => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
    'warning' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
];

$pageTitle = 'Notifications';
require 'layout.php';
?>

<style>

body {
    margin: 0;
    min-height: 100vh;
    font-family: 'Segoe UI', Roboto, sans-serif;
    color: #1E293B;
    background: #FAF9F6; /* Blanc cassé luxury */
    background-image: 
        radial-gradient(circle at 80% 20%, rgba(255, 217, 0, 0.1), transparent 40%),
        radial-gradient(circle at 15% 50%, rgba(250, 237, 205, 0.7), transparent 45%),
        radial-gradient(circle at 50% 80%, rgba(241, 245, 249, 1), transparent 50%);
    background-attachment: fixed;
    position: relative;
}

/* Un petit effet de lueur subtile en haut de l'écran */
body::before {
    content: "";
    position: absolute;
    top: 0; left: 25%; right: 25%; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(245, 184, 0, 0.4), transparent);
    pointer-events: none;
}
.notif-container {
    display: flex;
    flex-direction: column;
    gap: 16px;
    max-width: 850px;
    margin: 0 auto;
}

/* ──── FILTRES DE DATE PREMUM ──── */
.filter-bar {
    display: flex;
    gap: 10px;
    background: #FFF;
    padding: 6px;
    border-radius: 14px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    max-width: 850px;
    margin: 0 auto 24px;
}
.filter-btn {
    padding: 8px 16px;
    font-family: 'Nunito', sans-serif;
    font-weight: 700;
    font-size: 13px;
    color: var(--muted);
    text-decoration: none;
    border-radius: 10px;
    transition: all 0.2s ease;
}
.filter-btn:hover {
    background: rgba(74, 107, 176, 0.05);
    color: var(--primary);
}
.filter-btn.active {
    background: #F3E5AB; /* Butter Yellow Actif */
    color: var(--text);
    box-shadow: 0 4px 12px #F3E5AB;
}

/* ──── CARTES NOTIFICATIONS INTERACTIVES ──── */
.notif-item {
    border-radius: 16px;
    padding: 22px 24px;
    display: flex;
    align-items: flex-start;
    gap: 18px;
    position: relative;
    transition: all 0.25s cubic-bezier(0.25, 0.8, 0.25, 1);
    border: 3px solid var(--border);
    box-shadow: var(--shadow);
    animation: fadeUp .4s cubic-bezier(0.16, 1, 0.3, 1) both;
}
.notif-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(27, 42, 74, 0.06);
}

/* ──── DISTINCTION BUTTER YELLOW POUR LES NON LUES ──── */
.notif-unread {
    background: #FFFDF4 !important; /* Fond chaud très clair décliné du jaune beurre */
    border-color: #EFE4B5 !important;
    border-left: 6px solid var(--primary) !important; /* Ligne gauche bleu acier */
}
.notif-unread .notif-titre {
    font-weight: 800;
    color: var(--text);
}
.notif-unread .notif-texte {
    color: #2D3748;
}

/* ──── STYLE TAMISÉ POUR LES LUES ──── */
.notif-lu {
    background: #FFFFFF !important;
    border-color: var(--border) !important;
    border-left: 6px solid #E6E2DC !important;
    opacity: 0.65;
}
.notif-lu:hover { opacity: 0.95; }
.notif-lu .notif-titre, .notif-lu .notif-texte { color: var(--muted); }
.notif-lu .notif-icon-wrapper { filter: grayscale(100%); opacity: 0.5; }

/* Éléments Internes */
.notif-icon-wrapper {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.notif-body { flex: 1; min-width: 0; }
.notif-header-line { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 5px; }
.notif-titre { font-family: 'Nunito', sans-serif; font-size: 15.5px; display: flex; align-items: center; gap: 8px; }

.notif-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #F3E5AB; /* Point d'or jaune */
    box-shadow: 0 0 10px rgba(224, 199, 90, 0.8);
    animation: pulse-dot 1.5s infinite;
}
@keyframes pulse-dot {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.4); opacity: 0.4; }
}

.notif-texte { font-size: 14px; line-height: 1.6; margin-bottom: 8px; }
.notif-date { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }

/* Actions */
.notif-actions { display: flex; gap: 6px; opacity: 0; transition: opacity 0.2s ease; align-self: center; }
.notif-item:hover .notif-actions { opacity: 1; }
.notif-btn {
    width: 32px; height: 32px; border-radius: 9px;
    background: #FFF; display: flex; align-items: center; justify-content: center;
    color: var(--muted); text-decoration: none; transition: all 0.2s; border: 1px solid var(--border);
    
}
.notif-btn:hover { background: var(--success); color: #fff; border-color: var(--success); }
.notif-btn-del:hover { background: var(--danger) !important; color: #fff !important; border-color: var(--danger) !important; }

/* En-tête */
.header-badge {
    background: #F3E5AB;
    color: var(--text);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 800;
    font-family: 'Nunito', sans-serif;
    box-shadow: 0 2px 8px rgba(243, 229, 171, 0.3);
}
.btn-mark-all {
    background: #F3E5AB;
    color: var(--text);
    border: 1px solid #EFE4B5;
    padding: 10px 20px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
    text-decoration: none;
}
.btn-mark-all:hover {
    background: #F3E5AB;
    transform: translateY(-1px);
}
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;max-width:850px;margin-left:auto;margin-right:auto;" class="fade-up">
    <div style="display:flex;align-items:center;gap:14px;">
        <div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:26px;color:var(--text);letter-spacing:-0.5px;">
            Centre de Notifications
        </div>
        <?php if ($unreadCount > 0): ?>
            <span class="header-badge"><?= $unreadCount ?> nouvelle<?= $unreadCount > 1 ? 's' : '' ?></span>
        <?php endif; ?>
    </div>
    <?php if ($unreadCount > 0): ?>
        <a href="?mark_all_read=1" class="btn-mark-all">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            Tout marquer comme lu
        </a>
    <?php endif; ?>
</div>

<div class="filter-bar class=fade-up">
    <a href="?filter=all" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">Toutes</a>
    <a href="?filter=today" class="filter-btn <?= $filter === 'today' ? 'active' : '' ?>">Aujourd'hui</a>
    <a href="?filter=week" class="filter-btn <?= $filter === 'week' ? 'active' : '' ?>">Dernière semaine</a>
    <a href="?filter=older" class="filter-btn <?= $filter === 'older' ? 'active' : '' ?>">Plus anciennes</a>
</div>

<div class="notif-container">
<?php if (empty($notifications)): ?>

    <div class="card" style="border-radius:20px;">
        <div class="empty-state" style="padding:60px 20px;">
            <div style="width: 70px; height: 70px; background: #FFFDF4; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; border: 1px solid #EFE4B5;">
                <svg width="32" height="32" fill="none" stroke="var(--primary)" stroke-width="1.5" viewBox="0 0 24 24">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
            </div>
            <h3 style="font-family:'Nunito',sans-serif; color:var(--text); margin-bottom:6px; font-weight:800;">Aucune notification</h3>
            <p style="color:var(--muted);font-size:14px;margin:0;">Il n'y a rien à afficher pour cette période.</p>
        </div>
    </div>

<?php else: ?>

    <?php foreach ($notifications as $i => $notif):
        $type = $notif['type'] ?? 'info';
        $s    = $typeStyles[$type] ?? $typeStyles['info'];
        $icon = $typeIcons[$type]  ?? $typeIcons['info'];
        $isLu = (bool) $notif['is_read'];
        $delay = $i * 0.04;
    ?>

    <div class="notif-item <?= $isLu ? 'notif-lu' : 'notif-unread' ?>"
         style="
            --active-glow: <?= $s['glow'] ?>;
            animation-delay: <?= $delay ?>s;
         ">
        
        <div class="notif-icon-wrapper" style="background:<?= $s['icon_bg'] ?>;">
            <svg width="18" height="18" fill="none" stroke="<?= $s['icon_color'] ?>" stroke-width="2.2" viewBox="0 0 24 24">
                <?= $icon ?>
            </svg>
        </div>

        <div class="notif-body">
            <div class="notif-header-line">
                <div class="notif-titre">
                    <?= htmlspecialchars($notif['titre']) ?>
                    <?php if (!$isLu): ?>
                        <span class="notif-dot"></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="notif-texte"><?= htmlspecialchars($notif['message']) ?></div>
            <div class="notif-date" style="color: <?= $isLu ? 'var(--muted)' : 'var(--primary)' ?>;">
                <?= timeAgo($notif['created_at']) ?>
            </div>
        </div>

        <div class="notif-actions">
            <?php if (!$isLu): ?>
                <a href="?mark_read=<?= $notif['ID_NOTIF'] ?>" class="notif-btn" title="Marquer comme lu">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </a>
            <?php endif; ?>

            <a href="?delete=<?= $notif['ID_NOTIF'] ?>" class="notif-btn notif-btn-del"
               title="Supprimer"
               onclick="return confirm('Supprimer définitivement cette notification ?')">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6l-1 14H6L5 6"/>
                    <path d="M10 11v6"/><path d="M14 11v6"/>
                    <path d="M9 6V4h6v2"/>
                </svg>
            </a>
        </div>
    </div>

    <?php endforeach; ?>

<?php endif; ?>
</div>

<?php require 'layout_end.php'; ?>