<?php
// ── Connexion + session AVANT tout output ───────────────────────
require_once '../../includes/connexionbd.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../dashboard.php');
    exit;
}

$userId = $_SESSION['user_id'];
$errors = [];
$old    = [];

// ── Charger les catégories ──────────────────────────────────────
$categories = $pdo->query("SELECT ID_CATEGORIE, name FROM CATEGORIE ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// ── Traitement POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;

    $nom         = trim($_POST['nom']         ?? '');
    $description = trim($_POST['description'] ?? '');
    $logo_url    = trim($_POST['logo_url']    ?? '');
    $url         = trim($_POST['url']         ?? '');
    $version     = trim($_POST['version']     ?? '');
    $id_cat      = $_POST['id_categorie']     ?? '';

    if (strlen($nom) < 2)   $errors['nom'] = 'Au moins 2 caractères.';
    if (strlen($nom) > 150) $errors['nom'] = 'Maximum 150 caractères.';
    if (strlen($description) < 10) $errors['description'] = 'Au moins 10 caractères.';
    if (!empty($url) && !filter_var($url, FILTER_VALIDATE_URL))
        $errors['url'] = 'URL invalide (http:// ou https://).';
    if (!empty($logo_url) && !filter_var($logo_url, FILTER_VALIDATE_URL))
        $errors['logo_url'] = 'URL du logo invalide.';
    if (!empty($version) && !is_numeric($version))
        $errors['version'] = 'Doit être un nombre (ex: 1.0).';
    if (empty($id_cat) || !is_numeric($id_cat))
        $errors['id_categorie'] = 'Veuillez choisir une catégorie.';

    if (empty($errors['nom'])) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM OUTILS_IA WHERE nom = ?");
        $check->execute([$nom]);
        if ($check->fetchColumn() > 0)
            $errors['nom'] = 'Un outil avec ce nom existe déjà.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO OUTILS_IA (nom, description, logo_url, url, version, status, ID_CATEGORIE, ID_USERS_CREATE)
            VALUES (?, ?, ?, ?, ?, 'en_attente', ?, ?)
        ");
        $stmt->execute([$nom, $description, $logo_url ?: null, $url ?: null, $version ?: null, $id_cat, $userId]);
        $_SESSION['flash_success'] = "L'outil \"$nom\" a été soumis avec succès. Il sera visible après validation.";
        header('Location: outils.php');
        exit;
    }
}

$pageTitle = 'Ajouter un outil';
require 'layout.php';
?>

<!-- Wrapper centré pleine page -->
<div class="ao-wrapper">

    <!-- Breadcrumb -->
    <div class="ao-breadcrumb">
        <a href="outils.php" class="ao-back">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
            Mes Outils
        </a>
        <span class="ao-sep">/</span>
        <span class="ao-current">Ajouter un outil</span>
    </div>

    <!-- Titre centré -->
    <div class="ao-hero">
       
        <h1 class="ao-title">Soumettre un outil IA</h1>
    </div>

    <!-- Banner attente -->
    <div class="ao-notice">
       
        <span>Votre outil passera en statut <strong>Actif</strong> après approbation de l'équipe.</span>
    </div>

    <!-- Card formulaire -->
    <div class="ao-card">

        <form method="POST" id="addOutilForm" novalidate>

            <!-- Ligne 1 : Nom + Catégorie -->
            <div class="ao-row-2">
                <div class="ao-field">
                    <label class="ao-label">Nom de l'outil <span class="ao-req">*</span></label>
                    <input
                        type="text"
                        name="nom"
                        value="<?= htmlspecialchars($old['nom'] ?? '') ?>"
                        placeholder="Ex : ChatGPT, Midjourney…"
                        class="ao-input <?= isset($errors['nom']) ? 'ao-input-err' : '' ?>"
                        oninput="clearErr(this)"
                        autocomplete="off"
                    >
                    <?php if (isset($errors['nom'])): ?>
                        <div class="ao-err"><?= $errors['nom'] ?></div>
                    <?php endif; ?>
                </div>

                <div class="ao-field">
                    <label class="ao-label">Catégorie <span class="ao-req">*</span></label>
                    <div class="ao-select-wrap">
                        <select
                            name="id_categorie"
                            class="ao-input ao-select <?= isset($errors['id_categorie']) ? 'ao-input-err' : '' ?>"
                            onchange="clearErr(this)"
                        >
                            <option value="">— Choisir —</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['ID_CATEGORIE'] ?>"
                                    <?= (isset($old['id_categorie']) && $old['id_categorie'] == $cat['ID_CATEGORIE']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <svg class="ao-select-icon" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </div>
                    <?php if (isset($errors['id_categorie'])): ?>
                        <div class="ao-err"><?= $errors['id_categorie'] ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Description -->
            <div class="ao-field">
                <label class="ao-label">
                    Description <span class="ao-req">*</span>
                    <span id="descCount" class="ao-charcount">0/500</span>
                </label>
                <textarea
                    name="description"
                    rows="5"
                    maxlength="500"
                    placeholder="Décrivez ce que fait cet outil, ses points forts, son cas d'usage…"
                    class="ao-input ao-textarea <?= isset($errors['description']) ? 'ao-input-err' : '' ?>"
                    oninput="clearErr(this);updateCount(this,500,'descCount')"
                ><?= htmlspecialchars($old['description'] ?? '') ?></textarea>
                <?php if (isset($errors['description'])): ?>
                    <div class="ao-err"><?= $errors['description'] ?></div>
                <?php endif; ?>
            </div>

            <!-- Ligne 2 : URL + Logo -->
            <div class="ao-row-2">
                <div class="ao-field">
                    <label class="ao-label">URL de l'outil</label>
                    <div class="ao-input-icon-wrap">
                        <svg class="ao-input-icon" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                        </svg>
                        <input
                            type="url"
                            name="url"
                            value="<?= htmlspecialchars($old['url'] ?? '') ?>"
                            placeholder="https://exemple.com"
                            class="ao-input ao-input-has-icon <?= isset($errors['url']) ? 'ao-input-err' : '' ?>"
                            oninput="clearErr(this)"
                        >
                    </div>
                    <?php if (isset($errors['url'])): ?>
                        <div class="ao-err"><?= $errors['url'] ?></div>
                    <?php endif; ?>
                </div>

                <div class="ao-field">
                    <label class="ao-label">URL du logo</label>
                    <div class="ao-input-icon-wrap">
                        <svg class="ao-input-icon" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/>
                            <polyline points="21 15 16 10 5 21"/>
                        </svg>
                        <input
                            type="url"
                            name="logo_url"
                            value="<?= htmlspecialchars($old['logo_url'] ?? '') ?>"
                            placeholder="https://exemple.com/logo.png"
                            class="ao-input ao-input-has-icon <?= isset($errors['logo_url']) ? 'ao-input-err' : '' ?>"
                            oninput="clearErr(this);previewLogo(this.value)"
                        >
                    </div>
                    <?php if (isset($errors['logo_url'])): ?>
                        <div class="ao-err"><?= $errors['logo_url'] ?></div>
                    <?php endif; ?>

                    <!-- Logo preview -->
                    <div id="logoPreview" class="ao-logo-preview" style="display:<?= !empty($old['logo_url']) ? 'flex' : 'none' ?>;">
                        <img id="logoImg"
                            src="<?= htmlspecialchars($old['logo_url'] ?? '') ?>"
                            alt="aperçu"
                            onerror="document.getElementById('logoPreview').style.display='none'">
                        <span>Aperçu du logo</span>
                    </div>
                </div>
            </div>

            <!-- Version -->
            <div class="ao-field ao-field-sm">
                <label class="ao-label">Version</label>
                <input
                    type="text"
                    name="version"
                    value="<?= htmlspecialchars($old['version'] ?? '') ?>"
                    placeholder="Ex : 1.0, 2.5"
                    class="ao-input <?= isset($errors['version']) ? 'ao-input-err' : '' ?>"
                    oninput="clearErr(this)"
                >
                <?php if (isset($errors['version'])): ?>
                    <div class="ao-err"><?= $errors['version'] ?></div>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="ao-actions">
                <a href="outils.php" class="ao-btn-cancel">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <polyline points="15 18 9 12 15 6"/>
                    </svg>
                    Annuler
                </a>
                <button type="submit" class="ao-btn-submit" id="submitBtn">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <line x1="22" y1="2" x2="11" y2="13"/>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                    </svg>
                    Soumettre l'outil
                </button>
            </div>

        </form>
    </div>

</div>

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

/* ───────────────────────────── */
/* WRAPPER */
/* ───────────────────────────── */

.ao-wrapper{
    width:100%;
    display:flex;
    flex-direction:column;
    align-items:center;
    padding:10px 20px 60px;
}

/* ───────────────────────────── */
/* BREADCRUMB */
/* ───────────────────────────── */

.ao-breadcrumb{
    width:100%;
    max-width:700px;
    margin-bottom:30px;
    display:flex;
    align-items:center;
    gap:8px;
    font-size:13px;
}

.ao-back{
    text-decoration:none;
    color:var(--muted);
    display:flex;
    align-items:center;
    gap:5px;
    font-weight:600;
    transition:.2s;
}

.ao-back:hover{
    color:var(--primary);
}

.ao-current{
    font-weight:800;
    color:var(--navy);
}

/* ───────────────────────────── */
/* HERO */
/* ───────────────────────────── */

.ao-hero{
    text-align:center;
    margin-bottom:35px;
}

.ao-hero-icon{
    width:64px;
    height:64px;
    border-radius:18px;

    background:linear-gradient(
        135deg,
        #F5B800,
        #FFD95E
    );

    display:flex;
    align-items:center;
    justify-content:center;

    color:#fff;

    margin:auto auto 16px;

    box-shadow:
        0 12px 35px rgba(245,184,0,.25);
}

.ao-title{
    font-size:32px;
    font-weight:900;
    letter-spacing:-1px;
    color:var(--navy);
    margin-bottom:8px;
}

.ao-subtitle{
    font-size:14px;
    color:var(--muted);
    max-width:560px;
    margin:auto;
    line-height:1.7;
}

/* ───────────────────────────── */
/* NOTICE */
/* ───────────────────────────── */

.ao-notice{
    width:100%;
    max-width:700px;

    display:flex;
    align-items:center;
    gap:10px;

    padding:14px 18px;

    border-radius:14px;

    background:linear-gradient(
        135deg,
        #FFF7D6,
        #FFF1B8
    );

    color:#92400E;

    margin-bottom:24px;

    box-shadow:
        0 8px 25px rgba(245,184,0,.15);
}

/* ───────────────────────────── */
/* CARD */
/* ───────────────────────────── */

.ao-card{

    width:100%;
    max-width:700px;

    padding:34px;

    border-radius:24px;

    background:rgba(255,255,255,.92);

    backdrop-filter:blur(16px);

    border:2px solid #1B2A4A;

    box-shadow:
        0 20px 60px rgba(0,0,0,.08),
        0 4px 16px rgba(0,0,0,.03);

    animation:fadeUp .45s ease;
}

/* ───────────────────────────── */
/* FORM */
/* ───────────────────────────── */

.ao-row-2{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
}

.ao-field{
    display:flex;
    flex-direction:column;
    margin-bottom:22px;
}

.ao-field-sm{
    max-width:220px;
}

/* ───────────────────────────── */
/* LABEL */
/* ───────────────────────────── */

.ao-label{
    font-size:12px;
    font-weight:800;
    color:#334155;
    margin-bottom:8px;

    display:flex;
    align-items:center;
    gap:6px;

    text-transform:uppercase;
    letter-spacing:.5px;
}

.ao-charcount{
    margin-left:auto;
    font-size:11px;
    color:var(--muted);
    text-transform:none;
}

.ao-req{
    color:#EF4444;
}

/* ───────────────────────────── */
/* INPUTS */
/* ───────────────────────────── */

.ao-input{

    width:100%;

    padding:13px 15px;

    border-radius:14px;

    border:1.5px solid #1B2A4A;

    background:#fff;

    font-size:14px;

    transition:.2s;

    box-sizing:border-box;
}

.ao-input:hover{
    border-color:#D1D5DB;
}

.ao-input:focus{

    outline:none;

    border-color:#F5B800;

    box-shadow:
        0 0 0 4px rgba(245,184,0,.15);
}

.ao-input::placeholder{
    color:#B8C1CC;
}

.ao-input-err{
    border-color:#EF4444 !important;
    background:#FFF6F6 !important;
}

.ao-input-err:focus{
    box-shadow:
        0 0 0 4px rgba(239,68,68,.12);
}

.ao-textarea{
    resize:vertical;
    min-height:130px;
    line-height:1.7;
}

.ao-select-wrap{
    position:relative;
}

.ao-select{
    appearance:none;
    padding-right:38px;
}

.ao-select-icon{
    position:absolute;
    right:14px;
    top:50%;
    transform:translateY(-50%);
    color:#94A3B8;
    pointer-events:none;
}

.ao-input-icon-wrap{
    position:relative;
}

.ao-input-icon{
    position:absolute;
    left:14px;
    top:50%;
    transform:translateY(-50%);
    color:#94A3B8;
}

.ao-input-has-icon{
    padding-left:40px;
}

/* ───────────────────────────── */
/* PREVIEW LOGO */
/* ───────────────────────────── */

.ao-logo-preview{

    display:flex;
    align-items:center;
    gap:10px;

    margin-top:10px;

    padding:10px;

    background:#F8FAFC;

    border-radius:12px;

    border:1px solid #E5E7EB;
}

.ao-logo-preview img{

    width:40px;
    height:40px;

    border-radius:10px;

    object-fit:contain;

    background:#fff;

    border:1px solid #E5E7EB;
}

/* ───────────────────────────── */
/* ERROR */
/* ───────────────────────────── */

.ao-err{

    margin-top:6px;

    color:#EF4444;

    font-size:12px;

    font-weight:600;
}

/* ───────────────────────────── */
/* ACTIONS */
/* ───────────────────────────── */

.ao-actions{

    margin-top:28px;

    padding-top:24px;

    border-top:1px solid #EEF2F7;

    display:flex;
    justify-content:space-between;
    align-items:center;
}

.ao-btn-cancel{

    padding:12px 18px;

    border-radius:12px;

    border:1px solid #E5E7EB;

    text-decoration:none;

    color:#64748B;

    font-weight:700;

    transition:.2s;

    display:flex;
    align-items:center;
    gap:6px;
}

.ao-btn-cancel:hover{
    border-color:#CBD5E1;
    background: #c4c7ca;
}

.ao-btn-submit{

    padding:13px 28px;

    border:none;

    border-radius:14px;

    cursor:pointer;

    font-weight:800;

    color:#fff;

    display:flex;
    align-items:center;
    gap:8px;

    background:linear-gradient(
        135deg,
        #1B2A4A,
        #1B2A4A
    );

    box-shadow:
        0 10px 30px rgba(68, 98, 168, 0.25);

    transition:.2s;
}

.ao-btn-submit:hover{

    transform:translateY(-2px);

    box-shadow:
        0 15px 35px rgba(15,23,42,.30);
}

.ao-btn-submit:disabled{
    opacity:.7;
    cursor:not-allowed;
}

/* ───────────────────────────── */
/* ANIMATION */
/* ───────────────────────────── */

@keyframes fadeUp{

    from{
        opacity:0;
        transform:translateY(20px);
    }

    to{
        opacity:1;
        transform:translateY(0);
    }
}

/* ───────────────────────────── */
/* MOBILE */
/* ───────────────────────────── */

@media(max-width:768px){

    .ao-row-2{
        grid-template-columns:1fr;
    }

    .ao-card{
        padding:22px;
    }

    .ao-actions{
        flex-direction:column;
        gap:12px;
    }

    .ao-btn-cancel,
    .ao-btn-submit{
        width:100%;
        justify-content:center;
    }

    .ao-field-sm{
        max-width:100%;
    }
}

</style>

<script>
function clearErr(el) {
    el.classList.remove('ao-input-err');
    var parent = el.closest('.ao-field') || el.parentElement.closest('.ao-field');
    if (parent) {
        var err = parent.querySelector('.ao-err');
        if (err) err.remove();
    }
}
function updateCount(el, max, id) {
    var n = el.value.length;
    var span = document.getElementById(id);
    if (!span) return;
    span.textContent = n + '/' + max;
    span.style.color = n > max * 0.9 ? '#D97706' : 'var(--muted)';
}
function previewLogo(url) {
    var preview = document.getElementById('logoPreview');
    var img     = document.getElementById('logoImg');
    if (url && url.startsWith('http')) {
        img.src = url;
        preview.style.display = 'flex';
    } else {
        preview.style.display = 'none';
    }
}
// Init compteur
(function() {
    var ta = document.querySelector('[name="description"]');
    if (ta) updateCount(ta, 500, 'descCount');
})();
// Anti double-submit
document.getElementById('addOutilForm').addEventListener('submit', function() {
    var btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="animation:ao-spin 1s linear infinite"><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0"/></svg> Envoi en cours…';
});
</script>

<?php require 'layout_end.php'; ?>