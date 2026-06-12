<?php
require_once '../../includes/auth_admin.php';
require_once '../../includes/connexionbd.php';

$current_user_id = (int) ($_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? 0);

$msg = '';
$msgType = 'success';
$action = $_GET['action'] ?? '';
$id = (int) ($_GET['id'] ?? 0);

if ($action && $id > 0 && $id !== $current_user_id) {
    switch ($action) {
        case 'promote':
            $pdo->prepare("UPDATE users SET role='admin' WHERE id=?")->execute([$id]);
            $msg = '✅ Promu administrateur.';
            break;
        case 'demote':
            $pdo->prepare("UPDATE users SET role='adherent' WHERE id=?")->execute([$id]);
            $msg = '✅ Rétrogradé.';
            break;
        case 'supprimer':
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
            $msg = '🗑 Supprimé.';
            $msgType = 'danger';
            break;
    }
    header('Location: users.php?msg=' . urlencode($msg) . '&type=' . $msgType);
    exit;
}

if (!$msg && isset($_GET['msg'])) {
    $msg = htmlspecialchars($_GET['msg']);
    $msgType = $_GET['type'] ?? 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'adherent';

    if (!$nom || !$email || !$pass) {
        $msg = 'Champs obligatoires.';
        $msgType = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = 'Email invalide.';
        $msgType = 'danger';
    } else {
        $ex = $pdo->prepare("SELECT id FROM users WHERE email=?");
        $ex->execute([$email]);
        if ($ex->fetch()) {
            $msg = 'Email déjà utilisé.';
            $msgType = 'danger';
        } else {
            $pdo->prepare("INSERT INTO users(nom, email, password, role) VALUES(?,?,?,?)")
                ->execute([$nom, $email, password_hash($pass, PASSWORD_BCRYPT), $role]);
            header('Location: users.php?msg=' . urlencode('✅ Utilisateur créé.'));
            exit;
        }
    }
}

$rf = $_GET['role'] ?? 'all';
$q = trim($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$pp = 12;

// Exclure les visiteurs partout
$where = "WHERE u.role != 'visiteur'";
$params = [];
if ($q) {
    $where .= " AND (u.nom LIKE ? OR u.email LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($rf === 'admin') {
    $where .= " AND u.role='admin'";   // pas de paramètre, valeur fixe
} elseif ($rf === 'adherent') {
    $where .= " AND u.role='adherent'"; // idem
}

$total = $pdo->prepare("SELECT COUNT(*) FROM users u $where");
$total->execute($params);
$total = $total->fetchColumn();
$pages = ceil($total / $pp);
$offset = ($page - 1) * $pp;

$sql = "SELECT u.*,
        (SELECT COUNT(*) FROM outils_ia WHERE ID_USERS_CREATE = u.id) AS nb_outils,
        (SELECT COUNT(*) FROM notifications WHERE ID_USERS = u.id AND is_read = 0) AS nb_notifs
        FROM users u 
        $where 
        ORDER BY u.id DESC 
        LIMIT $pp OFFSET $offset";

$users = $pdo->prepare($sql);
$users->execute($params);
$users = $users->fetchAll();

$counts = [
    'all' => $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'visiteur'")->fetchColumn(),
    'admin' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
    'adherent' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'adherent'")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Utilisateurs — Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../../styles/style.css">
    <link rel="stylesheet" href="../../styles/admin.css">
    <style>

        /* ============================================================
   USERS.CSS — Styles spécifiques à la page users.php
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

/* ── Tabs de filtres (stabs) ── */
.stabs {
  display: flex;
  gap: 8px;
  margin-bottom: 18px;
  flex-wrap: wrap;
}

.stab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 16px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  font-family: 'Nunito', sans-serif;
  text-decoration: none;
  color: var(--adm-muted);
  background: var(--adm-surface);
  border: 1px solid var(--adm-border);
  transition: .2s ease;
}

.stab:hover {
  color: var(--adm-text);
  border-color: var(--primary);
}

.stab.active {
  background: var(--primary);
  color: #fff;
  border-color: var(--primary);
}

.stab-n {
  font-size: 11px;
  font-weight: 800;
  padding: 1px 7px;
  border-radius: 999px;
  background: rgba(0,0,0,0.08);
}

.stab.active .stab-n {
  background: rgba(255,255,255,0.25);
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

/* ── Grille utilisateurs ── */
.users-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 16px;
}

.ucard {
  background: var(--adm-surface);
  border: 1px solid var(--adm-border);
  border-radius: var(--adm-radius);
  padding: 18px;
  transition: .2s ease;
}

.ucard:hover {
  transform: translateY(-3px);
  box-shadow: var(--adm-shadow);
}

.ucard-head {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}

.ucard-av {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  color: #fff;
  font-weight: 800;
  font-family: 'Nunito', sans-serif;
  font-size: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.ucard-name {
  font-weight: 700;
  font-size: 14px;
  color: var(--adm-text);
  display: flex;
  align-items: center;
  gap: 6px;
}

.ucard-email {
  font-size: 12px;
  color: var(--adm-muted);
  margin-top: 1px;
  word-break: break-all;
}

.badge-self {
  font-size: 10px;
  font-weight: 800;
  padding: 1px 7px;
  border-radius: 999px;
  background: var(--accent);
  color: var(--adm-text);
}

.ucard-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
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

/* ── Actions carte utilisateur ── */
.ucard-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.btn-promote,
.btn-demote,
.btn-del-sm {
  flex: 1;
  text-align: center;
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 800;
  font-family: 'Nunito', sans-serif;
  text-decoration: none;
  transition: .2s ease;
  white-space: nowrap;
}

.btn-promote {
  background: #EFF4FF;
  color: var(--primary);
}

.btn-promote:hover {
  background: #DBEAFE;
}

.btn-demote {
  background: #FFFBEB;
  color: var(--adm-orange);
}

.btn-demote:hover {
  background: #FEF3C7;
}

.btn-del-sm {
  background: #FEF2F2;
  color: var(--adm-red);
}

.btn-del-sm:hover {
  background: #FEE2E2;
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

/* ── Modale Ajouter utilisateur ── */
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
  max-width: 480px;
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

/* ── Champs de formulaire (modale ajout) ── */
.adm-fg {
  margin-bottom: 16px;
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.adm-fg label {
  font-size: 12px;
  font-weight: 700;
  color: var(--adm-text);
}

.adm-fg input,
.adm-fg select,
.adm-fg textarea {
  padding: 9px 12px;
  border: 1px solid var(--adm-border);
  border-radius: 8px;
  font-size: 13.5px;
  font-family: 'DM Sans', sans-serif;
  color: var(--adm-text);
  background: var(--adm-surface);
  outline: none;
  transition: .2s ease;
}

.adm-fg input:focus,
.adm-fg select:focus,
.adm-fg textarea:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(74,107,176,0.12);
}

.adm-row2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

@media (max-width: 480px) {
  .adm-row2 {
    grid-template-columns: 1fr;
  }
}
    </style>
</head>

<body class="adm-body">
    <?php include 'sidebar.php'; ?>
    <div class="adm-main">
        <div class="adm-topbar">
            <div>
                <h1 class="adm-title">Utilisateurs</h1>
                <p class="adm-sub"><?= $counts['all'] ?> inscrits</p>
            </div>
            <button class="btn-adm-primary" onclick="openModal('addU')">+ Ajouter</button>
        </div>

        <?php if ($msg): ?>
            <div class="adm-alert adm-alert-<?= $msgType ?>">
                <?= $msg ?>
                <button onclick="this.parentElement.remove()"
                    style="float:right;background:none;border:none;cursor:pointer;font-size:1.1rem">×</button>
            </div>
        <?php endif; ?>

        <div class="stabs">
            <?php foreach (['all' => 'Tous', 'admin' => 'Admins', 'adherent' => 'Adhérents'] as $k => $l): ?>
                <a href="users.php?role=<?= $k ?>" class="stab <?= $rf === $k ? 'active' : '' ?>">
                    <?= $l ?> <span class="stab-n"><?= $counts[$k] ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <form method="GET" class="adm-search">
            <input type="hidden" name="role" value="<?= htmlspecialchars($rf) ?>">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="11" cy="11" r="8" />
                <line x1="21" y1="21" x2="16.65" y2="16.65" />
            </svg>
            <input type="text" name="q" placeholder="Rechercher…" value="<?= htmlspecialchars($q) ?>">
            <button type="submit">Rechercher</button>
            <?php if ($q): ?>
                <a href="users.php?role=<?= $rf ?>">✕</a>
            <?php endif; ?>
        </form>

        <div class="adm-section">
            <div class="users-grid">
                <?php if (empty($users)): ?>
                    <p style="padding:32px;color:var(--adm-muted);grid-column:1/-1;text-align:center">Aucun utilisateur.</p>
                <?php endif; ?>

                <?php foreach ($users as $u):
                    $self = $u['id'] === $current_user_id;
                    $cols = ['#2563EB', '#059669', '#D97706', '#DC2626', '#7C3AED'];
                    $c = $cols[$u['id'] % count($cols)];
                    $ini = strtoupper(substr($u['nom'] ?? '?', 0, 1));
                    ?>
                    <div class="ucard">
                        <div class="ucard-head">
                            <div class="ucard-av" style="background:<?= $c ?>"><?= $ini ?></div>
                            <div>
                                <div class="ucard-name">
                                    <?= htmlspecialchars($u['nom'] ?? '') ?>
                                    <?php if ($self): ?>
                                        <span class="badge-self">Moi</span>
                                    <?php endif; ?>
                                </div>
                                <div class="ucard-email"><?= htmlspecialchars($u['email'] ?? '') ?></div>
                            </div>
                        </div>
                        <div class="ucard-meta">
                            <span class="role-badge <?= $u['role'] === 'admin' ? 'admin' : 'adherent' ?>">
                                <?= $u['role'] === 'admin' ? '🛡 Admin' : '👤 Adhérent' ?>
                            </span>
                            <span style="font-size:.75rem;color:var(--adm-muted)">💻 <?= $u['nb_outils'] ?>
                                outil<?= $u['nb_outils'] > 1 ? 's' : '' ?></span>
                            <?php if (($u['nb_notifs'] ?? 0) > 0): ?>
                                <span
                                    style="font-size:.72rem;background:#FEF2F2;color:#DC2626;border-radius:99px;padding:2px 8px;font-weight:700">
                                    🔔 <?= $u['nb_notifs'] ?> non lue<?= $u['nb_notifs'] > 1 ? 's' : '' ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if (!$self): ?>
                            <div class="ucard-actions">
                                <?php if ($u['role'] !== 'admin'): ?>
                                    <a href="users.php?action=promote&id=<?= $u['id'] ?>" class="btn-promote"
                                        onclick="return confirm('Promouvoir admin ?')">Promouvoir</a>
                                <?php else: ?>
                                    <a href="users.php?action=demote&id=<?= $u['id'] ?>" class="btn-demote"
                                        onclick="return confirm('Rétrograder ?')">Rétrograder</a>
                                <?php endif; ?>
                                <a href="users.php?action=supprimer&id=<?= $u['id'] ?>" class="btn-del-sm"
                                    onclick="return confirm('Supprimer ?')">Supprimer</a>
                            </div>
                        <?php endif; ?>
                        <div style="font-size:.68rem;color:var(--adm-muted);margin-top:8px">
                            Inscrit le <?= isset($u['created_at']) ? date('d/m/Y', strtotime($u['created_at'])) : '—' ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($pages > 1): ?>
                <div class="adm-pag">
                    <?php for ($p = 1; $p <= $pages; $p++): ?>
                        <a href="?role=<?= $rf ?>&q=<?= urlencode($q) ?>&page=<?= $p ?>"
                            class="pag-btn <?= $p === $page ? 'active' : '' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Ajouter -->
    <div class="adm-modal-overlay" id="addU" onclick="if(event.target===this)closeModal('addU')">
        <div class="adm-modal">
            <div class="adm-modal-head">
                <h3>Ajouter un utilisateur</h3>
                <button onclick="closeModal('addU')" class="adm-modal-close">×</button>
            </div>
            <form method="POST" class="adm-modal-body">
                <div class="adm-fg">
                    <label>Nom *</label>
                    <input type="text" name="nom" required placeholder="Nom complet">
                </div>
                <div class="adm-fg">
                    <label>Email *</label>
                    <input type="email" name="email" required placeholder="email@exemple.com">
                </div>
                <div class="adm-row2">
                    <div class="adm-fg">
                        <label>Mot de passe *</label>
                        <input type="password" name="password" required placeholder="Min. 6 caractères">
                    </div>
                    <div class="adm-fg">
                        <label>Rôle</label>
                        <select name="role">
                            <option value="adherent">Adhérent</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="adm-modal-foot">
                    <button type="button" class="btn-cancel" onclick="closeModal('addU')">Annuler</button>
                    <button type="submit" class="btn-adm-primary">Créer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).classList.add('open');
            document.body.style.overflow = 'hidden';
        }
        function closeModal(id) {
            document.getElementById(id).classList.remove('open');
            document.body.style.overflow = '';
        }
    </script>
</body>

</html>