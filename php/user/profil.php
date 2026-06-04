<?php

$pageTitle = 'Profil';
$extraCss = '../../styles/profil.css';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ./dashboard.php');
    exit;
}

require_once '../../includes/connexionbd.php';

require 'layout.php';


// Récupérer les infos utilisateur
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: ../dashboard.php');
    exit;
}

$success = '';
$error   = '';

// ── Traitement formulaire ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Modifier infos
    if (isset($_POST['action']) && $_POST['action'] === 'update_info') {
        $nom    = trim($_POST['nom']    ?? '');
        $email  = trim($_POST['email']  ?? '');

        if (!$nom || !$email) {
            $error = "Nom et email sont requis.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Format email invalide.";
        } else {
            // Vérifier email unique
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->execute([$email, $user['id']]);
            if ($check->fetch()) {
                $error = "Cet email est déjà utilisé.";
            } else {
                $pdo->prepare("UPDATE users SET nom = ?, email = ? WHERE id = ?")
                    ->execute([$nom, $email, $user['id']]);
                $_SESSION['nom']   = $nom;
                $_SESSION['email'] = $email;
                $success = "Informations mises à jour avec succès !";
                // Recharger les données
                $stmt->execute([$user['id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    }

    // Changer mot de passe
    if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $current  = $_POST['current_pwd']  ?? '';
        $new      = $_POST['new_pwd']      ?? '';
        $confirm  = $_POST['confirm_pwd']  ?? '';

        if (!$current || !$new || !$confirm) {
            $error = "Tous les champs sont requis.";
        } elseif (strlen($new) < 6) {
            $error = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
        } elseif ($new !== $confirm) {
            $error = "Les mots de passe ne correspondent pas.";
        } else {
            $valid = password_verify($current, $user['password']) || $current === $user['password'];
            if (!$valid) {
                $error = "Mot de passe actuel incorrect.";
            } else {
                $hash = password_hash($new, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
                    ->execute([$hash, $user['id']]);
                $success = "Mot de passe modifié avec succès !";
            }
        }
    }

    // Upload photo
    if (isset($_POST['action']) && $_POST['action'] === 'upload_photo') {
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
            $allowed   = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $maxSize   = 3 * 1024 * 1024; // 3MB
            $fileType  = mime_content_type($_FILES['photo']['tmp_name']);
            $fileSize  = $_FILES['photo']['size'];

            if (!in_array($fileType, $allowed)) {
                $error = "Format non supporté. Utilisez JPG, PNG ou WEBP.";
            } elseif ($fileSize > $maxSize) {
                $error = "Image trop lourde (max 3MB).";
            } else {
                $ext      = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $filename = 'user_' . $user['id'] . '_' . time() . '.' . $ext;
                $uploadDir = '../uploads/avatars/';

                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

             if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {

    // supprimer ancienne image
    if ($user['image'] && file_exists($uploadDir . $user['image'])) {
        unlink($uploadDir . $user['image']);
    }

    $pdo->prepare("UPDATE users SET image = ? WHERE id = ?")
        ->execute([$filename, $user['id']]);

    // 🔥 AJOUT IMPORTANT
    $_SESSION['image'] = $filename;

    $success = "Photo mise à jour !";
                    $stmt->execute([$user['id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error = "Erreur lors de l'upload.";
                }
            }
        } else {
            $error = "Aucun fichier sélectionné.";
        }
    }

    // Supprimer photo
    if (isset($_POST['action']) && $_POST['action'] === 'delete_photo') {
        $uploadDir = '../uploads/avatars/';
        if ($user['image'] && file_exists($uploadDir . $user['image'])) {
            unlink($uploadDir . $user['image']);
        }
        $pdo->prepare("UPDATE users SET image = NULL WHERE id = ?")
            ->execute([$user['id']]);
        $success = "Photo supprimée.";
        $stmt->execute([$user['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$initial   = strtoupper(mb_substr($user['nom'], 0, 1));
$avatarSrc = $user['image'] ? '../uploads/avatars/' . $user['image'] : null;
$joinDate  = date('d/m/Y', strtotime($user['created_at']));
?>
<style>
:root {
    /* ── PALETTE PREMIUM BUTTER YELLOW & BLEU ACIER ── */
    --gold:        #F3E5AB; /* Butter Yellow principal */
    --gold-dark:   #DAB86B; /* Doré ambré texturé pour les textes/icones */
    --gold-light:  #FFFDF4; /* Crème ultra-doux pour les fonds de cartes actives */
    --gold-soft:   #FAF4D3; /* Teinte d'accentuation intermédiaire */
    
    /* Couleurs de structures sombres */
    --dark:        #1B2A4A; /* Bleu acier profond (remplace le noir) */
    --dark2:       #24355A;
    --dark3:       #2E406A;
    
    /* Textes & Nuances de gris */
    --text:        #2D3748; /* Anthracite doux */
    --text2:       #4A5568; 
    --text3:       #A0AEC0; 
    --border:      #E2E8F0; /* Bordures plus discrètes */
    --bg:          #F7FAFC; /* Fond de page frais */
    --white:       #FFFFFF;
    
    /* Statuts */
    --green:       #16A34A;
    --red:         #DC2626;
    
    /* Rayons & Ombres modernisés */
    --radius:      16px;
    --shadow:      0 10px 25px rgba(27, 42, 74, 0.04);
    --shadow-lg:   0 16px 36px rgba(27, 42, 74, 0.08);
}

* { 
    box-sizing: border-box; 
    margin: 0; 
    padding: 0; 
}

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

/* ── LAYOUT ── */
.page {
    max-width: 1100px;
    margin: 0 auto;
    padding: 40px 24px 60px;
}

.page-title {
    font-family: 'Syne', sans-serif;
    font-size: 30px;
    font-weight: 800;
    color: var(--dark);
    letter-spacing: -0.5px;
    margin-bottom: 6px;
}
.page-subtitle {
    font-size: 14px;
    color: var(--text3);
    font-weight: 500;
    margin-bottom: 32px;
}

.grid {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 30px;
    align-items: start;
}

@media (max-width: 768px) {
    .grid { grid-template-columns: 1fr; gap: 24px; }
    .page { padding: 24px 16px 40px; }
}

/* ── CARDS (CARTES) ── */
.card {
    background: var(--white);
    border: 2px solid var(--text);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card-header {
    padding: 22px 24px 18px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 12px;
}
.card-header-icon {
    width: 38px;
    height: 38px;
    background: var(--gold-light);
    border: 1px solid var(--gold-soft);
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gold-dark);
}
.card-title {
    font-family: 'Syne', sans-serif;
    font-size: 15.5px;
    font-weight: 800;
    color: var(--dark);
    letter-spacing: -0.2px;
}
.card-body { 
    padding: 26px 24px; 
}

/* ── AVATAR CARD ── */
.avatar-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 14px;
}

.avatar-wrapper {
    position: relative;
    width: 115px;
    height: 115px;
}
.avatar-img, .avatar-placeholder {
    width: 115px;
    height: 115px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid var(--white);
    box-shadow: 0 0 0 4px var(--gold), var(--shadow);
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.avatar-placeholder {
    background: var(--gold);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Syne', sans-serif;
    font-size: 42px;
    font-weight: 800;
    color: var(--dark);
    box-shadow: 0 0 0 4px var(--gold-soft);
}
.avatar-upload-btn {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 34px;
    height: 34px;
    background: var(--dark);
    border: 3px solid var(--white);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    color: var(--white);
    box-shadow: var(--shadow);
}
.avatar-upload-btn:hover { 
    background: var(--gold-dark); 
    color: var(--dark);
    transform: scale(1.05);
}

.avatar-name {
    font-family: 'Syne', sans-serif;
    font-size: 19px;
    font-weight: 800;
    color: var(--dark);
    margin-top: 4px;
}
.avatar-email {
    font-size: 13.5px;
    color: var(--text3);
    font-weight: 500;
}

/* Badges de rôles au design translucide pur */
.role-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.role-admin    { background: #FFF9E6; color: #B37D00; border: 1px solid #FFEBAA; }
.role-adherent { background: #F0FDF4; color: #16A34A; border: 1px solid #BBF7D0; }
.role-visiteur { background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; }

.avatar-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    width: 100%;
    margin-top: 12px;
}
.stat-box {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px 10px;
    text-align: center;
    transition: border-color 0.2s;
}
.stat-box:hover {
    border-color: var(--gold-dark);
}
.stat-val {
    font-family: 'Syne', sans-serif;
    font-size: 20px;
    font-weight: 800;
    color: var(--dark);
}
.stat-label {
    font-size: 11px;
    font-weight: 700;
    color: var(--text3);
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-top: 3px;
}

.delete-photo-btn {
    background: none;
    border: none;
    font-size: 12.5px;
    color: var(--red);
    cursor: pointer;
    font-family: 'DM Sans', sans-serif;
    font-weight: 600;
    margin-top: 6px;
    text-decoration: none;
    transition: opacity 0.2s;
}
.delete-photo-btn:hover { 
    opacity: 0.7;
    text-decoration: underline;
}

/* ── TABS (ONGLETS BAR) ── */
.tabs {
    display: flex;
    gap: 6px;
    background: rgba(160, 174, 192, 0.08);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 5px;
    margin-bottom: 26px;
}
.tab-btn {
    flex: 1;
    padding: 11px 18px;
    border: none;
    border-radius: 10px;
    background: transparent;
    font-size: 13.5px;
    font-weight: 700;
    font-family: 'DM Sans', sans-serif;
    color: var(--text2);
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.tab-btn:hover {
    color: var(--dark);
}
.tab-btn.active {
    background: var(--white);
    color: var(--dark);
    box-shadow: 0 4px 12px rgba(27, 42, 74, 0.05);
}
.tab-btn.active svg { 
    color: var(--gold-dark); 
}

.tab-panel { display: none; }
.tab-panel.active { display: block; animation: fadeIn 0.3s ease both; }

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ── FORMULAIRES ── */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}
.form-row.full { grid-template-columns: 1fr; }

@media (max-width: 600px) {
    .form-row { grid-template-columns: 1fr; gap: 16px; }
}

.field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.field label {
    font-size: 11.5px;
    font-weight: 700;
    color: var(--text2);
    text-transform: uppercase;
    letter-spacing: 0.8px;
}
.field input,
.field textarea,
.field select {
    padding: 13px 16px;
    border: 1.5px solid var(--border);
    border-radius: 12px;
    font-size: 14.5px;
    font-family: 'DM Sans', sans-serif;
    color: var(--text);
    background: var(--white);
    outline: none;
    transition: all 0.2s ease;
    resize: none;
}
.field input:focus,
.field textarea:focus,
.field select:focus {
    border-color: #DAB86B; /* Éclat d'accent chaud au focus */
    background: var(--gold-light);
    box-shadow: 0 0 0 4px rgba(243, 229, 171, 0.35);
}
.field textarea { min-height: 105px; line-height: 1.5; }

.field-hint {
    font-size: 11.5px;
    color: var(--text3);
    margin-top: 2px;
}

/* Mot de passe Toggle */
.pwd-wrapper { position: relative; }
.pwd-wrapper input { width: 100%; padding-right: 46px; }
.pwd-toggle {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    color: var(--text3);
    padding: 4px;
    display: flex;
    transition: color 0.15s;
}
.pwd-toggle:hover { color: var(--dark); }

/* Force du mot de passe */
.strength-bar {
    height: 5px;
    background: var(--border);
    border-radius: 10px;
    margin-top: 8px;
    overflow: hidden;
}
.strength-fill {
    height: 100%;
    width: 0;
    border-radius: 10px;
    transition: width .4s cubic-bezier(0.16, 1, 0.3, 1), background .3s;
}
.strength-text {
    font-size: 11.5px;
    font-weight: 600;
    margin-top: 5px;
}

/* ── BOUTONS STRUCTURÉS ── */
.btn-primary {
    padding: 13px 32px;
    background: #F3E5AB; /* Butter Yellow Actif */
    color: var(--dark);
    border: 1px solid #EFE4B5;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 800;
    font-family: 'Syne', sans-serif;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 4px 14px rgba(243, 229, 171, 0.4);
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-primary:hover { 
    background: #EEDFA3; 
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(243, 229, 171, 0.55);
}
.btn-primary:active { transform: translateY(0); }

.btn-danger {
    padding: 13px 32px;
    background: #FFF5F5;
    color: var(--red);
    border: 1.5px solid #FECACA;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 800;
    font-family: 'Syne', sans-serif;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-danger:hover { 
    background: #FEF2F2; 
    border-color: #FFAAAA;
}

.btn-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 12px;
    flex-wrap: wrap;
    gap: 12px;
}

/* ── ALERTS (NOTIFICATIONS FLASH) ── */
.alert {
    padding: 14px 18px;
    border-radius: 12px;
    font-size: 13.5px;
    font-weight: 600;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: var(--shadow);
    animation: slideIn .3s cubic-bezier(0.16, 1, 0.3, 1) both;
}
@keyframes slideIn {
    from { opacity: 0; transform: translateY(-10px); }
    to   { opacity: 1; transform: translateY(0); }
}
.alert-success { background: #F0FDF4; color: #16A34A; border: 1px solid #BBF7D0; }
.alert-error   { background: #FEF2F2; color: var(--red); border: 1px solid #FECACA; }

/* ── ZONE CRITIQUE (DANGER ZONE) ── */
.danger-zone {
    border: 1.5px dashed #FECACA;
    border-radius: var(--radius);
    padding: 24px;
    background: #FFFDFD;
}
.danger-title {
    font-family: 'Syne', sans-serif;
    font-size: 15px;
    font-weight: 800;
    color: var(--red);
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.danger-desc {
    font-size: 13.5px;
    color: var(--text2);
    margin-bottom: 18px;
    line-height: 1.6;
}

/* ── SEPARATEURS OPTIQUES ── */
.divider {
    height: 1px;
    background: var(--border);
    margin: 24px 0;
}

/* ── PARAMÈTRES DE SÉCURITÉ ── */
.security-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 0;
    border-bottom: 1px solid var(--border);
}
.security-item:last-child { border-bottom: none; }
.security-label {
    font-size: 14px;
    font-weight: 700;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 12px;
}
.security-label svg { color: var(--text3); }
.security-value {
    font-size: 13px;
    color: var(--text2);
    font-weight: 500;
}
.badge-ok {
    background: #DCFCE7;
    color: #15803D;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
}
.badge-warn {
    background: #FEF3C7;
    color: #92400E;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
}

/* ── BOITE D'AVANT-PREMIÈRE DE TÉLÉCHARGEMENT ── */
#photoPreviewBox {
    display: none;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    padding: 24px;
    background: var(--bg);
    border: 2px dashed var(--border);
    border-radius: var(--radius);
    margin-bottom: 18px;
}
#photoPreviewBox.has-file {
    border-color: var(--gold-dark);
    background: var(--gold-light);
}
#photoPreview {
    width: 85px;
    height: 85px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--white);
    box-shadow: 0 0 0 3px var(--gold);
}

/* Zone Drag & Drop Multi-Teintes */
.drop-zone {
    border: 2px dashed var(--border);
    border-radius: var(--radius);
    padding: 36px 24px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    background: var(--bg);
}
.drop-zone:hover, .drop-zone.dragover {
    border-color: var(--gold-dark);
    background: var(--gold-light);
}
.drop-zone svg { color: var(--gold-dark); margin-bottom: 10px; }
.drop-zone p { font-size: 14px; color: var(--dark); font-weight: 700; margin-bottom: 4px; }
.drop-zone span { font-size: 12px; color: var(--text3); font-weight: 500; }
    
</style>
<div class="page">
    <p class="page-subtitle">Gérez vos informations personnelles et paramètres de sécurité</p>

    <?php if ($success): ?>
    <div class="alert alert-success">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path d="M20 6L9 17l-5-5"/>
        </svg>
        <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>
        </svg>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div class="grid">

        <!-- ── COLONNE GAUCHE : Avatar ── -->
        <div style="display:flex;flex-direction:column;gap:20px;">

            <div class="card">
                <div class="card-body" >
                    <div class="avatar-section">

                        <!-- Avatar -->
                        <div class="avatar-wrapper">
                            <?php if ($avatarSrc): ?>
                                <img src="<?= htmlspecialchars($avatarSrc) ?>" class="avatar-img" id="mainAvatar" alt="Avatar">
                            <?php else: ?>
                                <div class="avatar-placeholder" id="mainAvatar"><?= $initial ?></div>
                            <?php endif; ?>

                            <label for="quickPhotoInput" class="avatar-upload-btn" title="Changer la photo">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/>
                                    <circle cx="12" cy="13" r="4"/>
                                </svg>
                            </label>
                        </div>

                        <!-- Upload rapide caché -->
                        <form method="POST" enctype="multipart/form-data" id="quickPhotoForm">
                            <input type="hidden" name="action" value="upload_photo">
                            <input type="file" id="quickPhotoInput" name="photo" accept="image/*" style="display:none" onchange="quickUpload(this)">
                        </form>

                        <div class="avatar-name"><?= htmlspecialchars($user['nom']) ?></div>
                        <div class="avatar-email"><?= htmlspecialchars($user['email']) ?></div>

                        <div class="role-badge role-<?= $user['role'] ?>">
                            <?php
                            $icons = ['admin' => '⚡', 'adherent' => '✓', 'visiteur' => '👁'];
                            echo ($icons[$user['role']] ?? '') . ' ' . ucfirst($user['role']);
                            ?>
                        </div>
                        
                        <?php if ($user['image']): ?>
                        <form method="POST" style="margin-top:4px;">
                            <input type="hidden" name="action" value="delete_photo">
                            <button type="submit" class="delete-photo-btn" onclick="return confirm('Supprimer la photo ?')">
                                Supprimer la photo
                            </button>
                        </form>
                        <?php endif; ?>

                        <div class="avatar-stats">
                            <div class="stat-box">
                                <div class="stat-val"><?= $joinDate ?></div>
                                <div class="stat-label">Membre depuis</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-val" style="color:<?= $user['status']==='active' ? 'var(--green)' : 'var(--red)' ?>">
                                    <?= ucfirst($user['status'] ?? 'active') ?>
                                </div>
                                <div class="stat-label">Statut</div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Sécurité rapide -->
            <div class="card">
                <div class="card-header">
                    <div class="card-header-icon">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                    </div>
                    <div class="card-title">Sécurité</div>
                </div>
                <div class="card-body" style="padding:16px 20px;">
                    <div class="security-item">
                        <div class="security-label">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
                            </svg>
                            Mot de passe
                        </div>
                        <span class="badge-ok">Défini</span>
                    </div>
                    <div class="security-item">
                        <div class="security-label">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                            </svg>
                            Email vérifié
                        </div>
                        <span class="badge-ok">✓</span>
                    </div>
                   
                </div>
            </div>

        </div>

        <!-- ── COLONNE DROITE : Formulaires ── -->
        <div class="card">
            <div class="card-body" style="padding:28px;">

                <!-- TABS -->
                <div class="tabs">
                    <button class="tab-btn active" onclick="switchTab('info', this)">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>
                        </svg>
                        Informations
                    </button>
                    <button class="tab-btn" onclick="switchTab('photo', this)">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/>
                        </svg>
                        Photo
                    </button>
                    <button class="tab-btn" onclick="switchTab('password', this)">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
                        </svg>
                        Mot de passe
                    </button>
                    <button class="tab-btn" onclick="switchTab('danger', this)">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                        Danger
                    </button>
                </div>

                <!-- ── TAB : INFORMATIONS ── -->
                <div class="tab-panel active" id="panel-info">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_info">

                        <div class="form-row">
                            <div class="field">
                                <label>Nom complet *</label>
                                <input type="text" name="nom" value="<?= htmlspecialchars($user['nom']) ?>" placeholder="Votre nom" required>
                            </div>
                            <div class="field">
                                <label>Adresse email *</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" placeholder="email@exemple.com" required>
                            </div>
                        </div>

                        <div class="form-row">
                        
                            <div class="field">
                                <label>Rôle</label>
                                <input type="text" value="<?= ucfirst($user['role']) ?>" disabled style="background:var(--bg);color:var(--text3);">
                                <span class="field-hint">Le rôle est géré par un administrateur.</span>
                            </div>
                        </div>

                     
                        <div class="btn-row">
                            <span style="font-size:12px;color:var(--text3);">* Champs obligatoires</span>
                            <button type="submit" class="btn-primary">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                                </svg>
                                Enregistrer
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── TAB : PHOTO ── -->
                <div class="tab-panel" id="panel-photo">
                    <form method="POST" enctype="multipart/form-data" id="photoForm">
                        <input type="hidden" name="action" value="upload_photo">

                        <!-- Prévisualisation -->
                        <div id="photoPreviewBox">
                            <img id="photoPreview" src="" alt="Aperçu">
                            <span style="font-size:13px;color:var(--text2);font-weight:500;" id="photoFileName"></span>
                        </div>

                        <!-- Drop zone -->
                        <div class="drop-zone" id="dropZone" onclick="document.getElementById('photoInput').click()">
                            <svg width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 10px;display:block;">
                                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                            <p><b>Cliquez</b> ou glissez une image ici</p>
                            <span>JPG, PNG, WEBP — max 3MB</span>
                        </div>

                        <input type="file" id="photoInput" name="photo" accept="image/*" style="display:none" onchange="previewPhoto(this)">

                        <div style="margin-top:16px;display:flex;gap:12px;flex-wrap:wrap;">
                            <button type="submit" class="btn-primary" id="uploadBtn" disabled>
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                                </svg>
                                Envoyer la photo
                            </button>

                            <?php if ($user['image']): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete_photo">
                                <button type="submit" class="btn-danger" onclick="return confirm('Supprimer votre photo de profil ?')">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/>
                                    </svg>
                                    Supprimer
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>

                        <div class="divider"></div>
                        <p style="font-size:12px;color:var(--text3);line-height:1.7;">
                            Votre photo sera visible par les autres utilisateurs. Utilisez une image carrée pour un meilleur rendu. Formats acceptés : JPG, PNG, WEBP. Taille maximale : 3MB.
                        </p>
                    </form>
                </div>

                <!-- ── TAB : MOT DE PASSE ── -->
                <div class="tab-panel" id="panel-password">
                    <form method="POST" onsubmit="return validatePasswordForm()">
                        <input type="hidden" name="action" value="change_password">

                        <div class="form-row full">
                            <div class="field">
                                <label>Mot de passe actuel *</label>
                                <div class="pwd-wrapper">
                                    <input type="password" name="current_pwd" id="currentPwd" placeholder="••••••••" required>
                                    <button type="button" class="pwd-toggle" onclick="togglePwd('currentPwd', this)">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="field">
                                <label>Nouveau mot de passe *</label>
                                <div class="pwd-wrapper">
                                    <input type="password" name="new_pwd" id="newPwd" placeholder="••••••••" oninput="checkPwdStrength(this.value)" required>
                                    <button type="button" class="pwd-toggle" onclick="togglePwd('newPwd', this)">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                        </svg>
                                    </button>
                                </div>
                                <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                                <div class="strength-text" id="strengthText"></div>
                            </div>
                            <div class="field">
                                <label>Confirmer le mot de passe *</label>
                                <div class="pwd-wrapper">
                                    <input type="password" name="confirm_pwd" id="confirmPwd" placeholder="••••••••" oninput="checkMatch()" required>
                                    <button type="button" class="pwd-toggle" onclick="togglePwd('confirmPwd', this)">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                        </svg>
                                    </button>
                                </div>
                                <div class="field-hint" id="matchHint"></div>
                            </div>
                        </div>

                        <div style="background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:14px 16px;margin-bottom:20px;">
                            <p style="font-size:12px;color:var(--text2);font-weight:600;margin-bottom:8px;">Recommandations :</p>
                            <ul style="font-size:12px;color:var(--text3);line-height:2;padding-left:16px;">
                                <li>Au moins 8 caractères</li>
                                <li>Mélanger majuscules et minuscules</li>
                                <li>Inclure des chiffres et symboles</li>
                                <li>Ne pas réutiliser un ancien mot de passe</li>
                            </ul>
                        </div>

                        <div class="btn-row">
                            <span></span>
                            <button type="submit" class="btn-primary">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
                                </svg>
                                Changer le mot de passe
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── TAB : DANGER ZONE ── -->
                <div class="tab-panel" id="panel-danger">

                    <div class="danger-zone">
                        <div class="danger-title">⚠ Zone de danger</div>
                        <div class="danger-desc">
                            Ces actions sont irréversibles. Veuillez lire attentivement avant de continuer.
                        </div>

                        <!-- Déconnexion partout -->
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 0;border-top:1px solid #FECACA;flex-wrap:wrap;gap:10px;">
                            <div>
                                <p style="font-size:13px;font-weight:600;color:var(--text);">Se déconnecter partout</p>
                                <p style="font-size:12px;color:var(--text3);margin-top:3px;">Ferme toutes les sessions actives sur tous les appareils.</p>
                            </div>
                            <a href="logout.php" class="btn-danger" style="text-decoration:none;">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                                </svg>
                                Déconnexion
                            </a>
                        </div>

                        <!-- Supprimer compte -->
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 0;border-top:1px solid #FECACA;flex-wrap:wrap;gap:10px;">
                            <div>
                                <p style="font-size:13px;font-weight:600;color:var(--red);">Supprimer mon compte</p>
                                <p style="font-size:12px;color:var(--text3);margin-top:3px;">Supprime définitivement votre compte et toutes vos données.</p>
                            </div>
                            <button class="btn-danger" onclick="confirmDelete()">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/>
                                </svg>
                                Supprimer le compte
                            </button>
                        </div>
                    </div>

                </div>

            </div>
        </div>

    </div>
</div>

<!-- MODAL SUPPRESSION COMPTE -->
<div id="deleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:32px;width:380px;max-width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="text-align:center;margin-bottom:20px;">
            <div style="width:56px;height:56px;background:#FEF2F2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                <svg width="24" height="24" fill="none" stroke="#EF4444" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </div>
            <h3 style="font-family:'Syne',sans-serif;font-size:17px;font-weight:700;color:#111827;margin-bottom:8px;">Supprimer le compte ?</h3>
            <p style="font-size:13px;color:#6B7280;line-height:1.6;">Cette action est <b>irréversible</b>. Toutes vos données seront supprimées définitivement.</p>
        </div>
        <div style="margin-bottom:16px;">
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">
                Tapez <b>SUPPRIMER</b> pour confirmer :
            </label>
            <input type="text" id="deleteConfirmInput" placeholder="SUPPRIMER"
                style="width:100%;padding:10px 14px;border:1.5px solid #D1D5DB;border-radius:10px;font-size:14px;outline:none;font-family:'DM Sans',sans-serif;"
                oninput="document.getElementById('confirmDeleteBtn').disabled = this.value !== 'SUPPRIMER'">
        </div>
        <div style="display:flex;gap:10px;">
            <button onclick="closeDeleteModal()" style="flex:1;padding:11px;background:#F9FAFB;border:1px solid #E5E7EB;border-radius:10px;font-size:13px;font-weight:600;font-family:'DM Sans',sans-serif;cursor:pointer;">
                Annuler
            </button>
            <form method="POST" style="flex:1;">
                <input type="hidden" name="action" value="delete_account">
                <button type="submit" id="confirmDeleteBtn" disabled
                    style="width:100%;padding:11px;background:#EF4444;color:#fff;border:none;border-radius:10px;font-size:13px;font-weight:700;font-family:'Syne',sans-serif;cursor:pointer;opacity:.5;"
                    onclick="this.style.opacity='1'">
                    Supprimer définitivement
                </button>
            </form>
        </div>
    </div>
</div>

</div>

</div> <!-- content -->
</div> <!-- main -->

<script>


function switchTab(name, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('panel-' + name).classList.add('active');
    btn.classList.add('active');
}

// ── TOGGLE PASSWORD ──
function togglePwd(inputId, btn) {
    var input = document.getElementById(inputId);
    var show  = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.innerHTML = show
        ? '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
        : '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
}

// ── FORCE MOT DE PASSE ──
function checkPwdStrength(p) {
    var fill = document.getElementById('strengthFill');
    var text = document.getElementById('strengthText');
    if (!p) { fill.style.width = '0'; text.textContent = ''; return; }
    var score = 0;
    if (p.length >= 6)  score++;
    if (p.length >= 10) score++;
    if (/[A-Z]/.test(p)) score++;
    if (/[0-9]/.test(p)) score++;
    if (/[^A-Za-z0-9]/.test(p)) score++;
    var pct = (score / 5 * 100) + '%';
    var colors = ['#EF4444','#EF4444','#F59E0B','#10B981','#10B981'];
    var labels = ['','Très faible','Faible','Moyen','Fort','Très fort'];
    var tcolors = ['','#EF4444','#EF4444','#F59E0B','#10B981','#10B981'];
    fill.style.width = pct;
    fill.style.background = colors[score - 1] || '#EF4444';
    text.textContent = labels[score] || '';
    text.style.color = tcolors[score] || '#EF4444';
}

// ── CORRESPONDANCE MDP ──
function checkMatch() {
    var p1 = document.getElementById('newPwd').value;
    var p2 = document.getElementById('confirmPwd').value;
    var hint = document.getElementById('matchHint');
    if (!p2) { hint.textContent = ''; return; }
    if (p1 === p2) {
        hint.textContent = '✓ Les mots de passe correspondent';
        hint.style.color = '#10B981';
    } else {
        hint.textContent = '✗ Ne correspondent pas';
        hint.style.color = '#EF4444';
    }
}

function validatePasswordForm() {
    var p1 = document.getElementById('newPwd').value;
    var p2 = document.getElementById('confirmPwd').value;
    if (p1.length < 6) { alert('Minimum 6 caractères.'); return false; }
    if (p1 !== p2) { alert('Les mots de passe ne correspondent pas.'); return false; }
    return true;
}

// ── PREVIEW PHOTO ──
function previewPhoto(input) {
    if (!input.files || !input.files[0]) return;
    var file = input.files[0];
    var reader = new FileReader();
    reader.onload = function(e) {
        var box = document.getElementById('photoPreviewBox');
        var img = document.getElementById('photoPreview');
        var name = document.getElementById('photoFileName');
        img.src = e.target.result;
        name.textContent = file.name + ' (' + (file.size / 1024).toFixed(0) + ' KB)';
        box.style.display = 'flex';
        box.classList.add('has-file');
        document.getElementById('uploadBtn').disabled = false;
    };
    reader.readAsDataURL(file);
}

// ── QUICK UPLOAD (clic avatar) ──
function quickUpload(input) {
    if (input.files && input.files[0]) {
        document.getElementById('quickPhotoForm').submit();
    }
}

// ── DRAG & DROP ──
var dropZone = document.getElementById('dropZone');
if (dropZone) {
    dropZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });
    dropZone.addEventListener('dragleave', function() {
        dropZone.classList.remove('dragover');
    });
    dropZone.addEventListener('drop', function(e) {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        var files = e.dataTransfer.files;
        if (files.length > 0) {
            document.getElementById('photoInput').files = files;
            previewPhoto(document.getElementById('photoInput'));
        }
    });
}

// ── MODAL SUPPRESSION ──
function confirmDelete() {
    var modal = document.getElementById('deleteModal');
    modal.style.display = 'flex';
    document.getElementById('deleteConfirmInput').value = '';
    document.getElementById('confirmDeleteBtn').disabled = true;
    document.getElementById('confirmDeleteBtn').style.opacity = '.5';
}
function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

// ── AUTO-HIDE ALERT ──
setTimeout(function() {
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(a) {
        a.style.transition = 'opacity .5s';
        a.style.opacity = '0';
        setTimeout(function() { a.remove(); }, 500);
    });
}, 4000);

</script>
<?php require 'layout_end.php'; ?>