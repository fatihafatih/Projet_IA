<?php
$pageTitle = 'Mes Outils';

require_once '../../includes/connexionbd.php';
require 'layout.php';

$userId = $_SESSION['user_id'];

/* ─────────────────────────────────────────────
   Flash message
───────────────────────────────────────────── */
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

/* ─────────────────────────────────────────────
   Récupérer les outils ACTIFS de l'utilisateur
───────────────────────────────────────────── */
$stmt = $pdo->prepare("
    SELECT
        o.ID_OUTILS_IA,
        o.nom,
        o.description,
        o.logo_url,
        o.url,
        o.version,
        o.status,
        c.name AS categorie_name
    FROM OUTILS_IA o
    LEFT JOIN CATEGORIE c ON o.ID_CATEGORIE = c.ID_CATEGORIE
    WHERE o.ID_USERS_CREATE = ?
      AND o.status = 'actif'
    ORDER BY o.nom ASC
");

$stmt->execute([$userId]);
$outils = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Flash success -->
<?php if ($flashSuccess): ?>
<div
    class="flash-success fade-up"
    id="flashMsg"
>
    <svg width="16" height="16" fill="none" stroke="currentColor"
        stroke-width="2" viewBox="0 0 24 24">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
        <polyline points="22 4 12 13.01 9 10.01"/>
    </svg>
    <?= htmlspecialchars($flashSuccess) ?>
</div>
<?php endif; ?>

<!-- Header -->
<div
    class="fade-up"
    style="
        display:flex;
        align-items:center;
        justify-content:space-between;
        margin-bottom:28px;
        flex-wrap:wrap;
        gap:12px;
    "
>
    <div>
        <h1
            style="
                font-size:22px;
                font-weight:800;
                color:var(--navy);
                margin:0 0 4px;
                letter-spacing:-.3px;
            "
        >
            Mes Outils IA
        </h1>
        <p style="font-size:13px;color:var(--muted);margin:0;">
            <?= count($outils) ?> outil<?= count($outils) > 1 ? 's' : '' ?> actif<?= count($outils) > 1 ? 's' : '' ?>
        </p>
    </div>

    <a href="addOutil.php" class="btn btn-primary">
        <svg width="14" height="14" fill="none" stroke="currentColor"
            stroke-width="2.5" viewBox="0 0 24 24">
            <line x1="12" y1="5" x2="12" y2="19"/>
            <line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Ajouter un outil
    </a>
</div>

<!-- Contenu -->
<?php if (empty($outils)): ?>

    <!-- État vide -->
    <div
        class="card fade-up"
        style="
            text-align:center;
            padding:56px 32px;
        "
    >
       

        <h3
            style="
                font-size:16px;
                font-weight:700;
                color:var(--navy);
                margin:0 0 8px;
            "
        >
            Aucun outil actif
        </h3>

        <p
            style="
                font-size:13px;
                color:var(--muted);
                margin:0 0 24px;
                line-height:1.6;
                max-width:320px;
                margin-left:auto;
                margin-right:auto;
            "
        >
            Vous n'avez pas encore d'outil actif. Soumettez un outil,
            il sera publié après validation par l'équipe.
        </p>

        <a href="addOutil.php" class="btn btn-primary" style="display:inline-flex;">
            <svg width="14" height="14" fill="none" stroke="currentColor"
                stroke-width="2.5" viewBox="0 0 24 24">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Soumettre mon premier outil
        </a>
    </div>

<?php else: ?>

    <!-- Grille des outils -->
    <div class="outils-grid">

        <?php foreach ($outils as $i => $outil): ?>

            <div
                class="outil-card fade-up"
                style="animation-delay: <?= $i * 0.06 ?>s"
            >
                <!-- Header card -->
                <div class="outil-card-header">

                    <!-- Logo / Initiale -->
                    <div class="outil-logo">
                        <?php if (!empty($outil['logo_url'])): ?>
                            <img
                                src="<?= htmlspecialchars($outil['logo_url']) ?>"
                                alt="<?= htmlspecialchars($outil['nom']) ?>"
                                onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"
                            >
                            <span class="outil-initiale" style="display:none;">
                                <?= strtoupper(substr($outil['nom'], 0, 1)) ?>
                            </span>
                        <?php else: ?>
                            <span class="outil-initiale">
                                <?= strtoupper(substr($outil['nom'], 0, 1)) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Nom + badge -->
                    <div style="flex:1;min-width:0;">
                        <div
                            style="
                                font-size:14.5px;
                                font-weight:700;
                                color:var(--navy);
                                white-space:nowrap;
                                overflow:hidden;
                                text-overflow:ellipsis;
                            "
                        >
                            <?= htmlspecialchars($outil['nom']) ?>
                        </div>

                        <?php if (!empty($outil['categorie_name'])): ?>
                            <span class="badge-cat">
                                <?= htmlspecialchars($outil['categorie_name']) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Badge actif -->
                    <span class="badge-actif">
                        <span class="badge-dot"></span>
                        Actif
                    </span>

                </div>

                <!-- Description -->
                <p class="outil-desc">
                    <?= htmlspecialchars($outil['description']) ?>
                </p>

                <!-- Footer -->
                <div class="outil-footer">

                    <?php if (!empty($outil['version'])): ?>
                        <span class="outil-version">
                            v<?= htmlspecialchars($outil['version']) ?>
                        </span>
                    <?php endif; ?>

                    <?php if (!empty($outil['url'])): ?>
                        <a
                            href="<?= htmlspecialchars($outil['url']) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="outil-link"
                        >
                            Visiter
                            <svg width="11" height="11" fill="none" stroke="currentColor"
                                stroke-width="2" viewBox="0 0 24 24">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                <polyline points="15 3 21 3 21 9"/>
                                <line x1="10" y1="14" x2="21" y2="3"/>
                            </svg>
                        </a>
                    <?php endif; ?>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

<?php endif; ?>

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
/* ── Flash ── */
.flash-success {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #F0FDF4;
    border: 1px solid #BBF7D0;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 13px;
    font-weight: 600;
    color: #15803D;
    margin-bottom: 20px;
}

/* ── Grille ── */
.outils-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 18px;
}

/* ── Card outil ── */
.outil-card {
    background: #fff;
    border: 2px solid var(--border);
    border-radius: 14px;
    padding: 18px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    transition: box-shadow .2s, transform .2s;
    cursor: default;
}

.outil-card:hover {
    box-shadow: 0 6px 24px rgba(0,0,0,.08);
    transform: translateY(-2px);
}

/* ── Header ── */
.outil-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
}

/* ── Logo ── */
.outil-logo {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: #F9FAFB;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
}

.outil-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 4px;
}

.outil-initiale {
    font-size: 17px;
    font-weight: 800;
    color: var(--gold, #F5B800);
    font-family: 'DM Sans', sans-serif;
}

/* ── Badges ── */
.badge-cat {
    display: inline-block;
    font-size: 10.5px;
    font-weight: 600;
    color: var(--muted);
    background: #F3F4F6;
    border-radius: 6px;
    padding: 2px 7px;
    margin-top: 4px;
    text-transform: uppercase;
    letter-spacing: .3px;
}

.badge-actif {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 700;
    color: #15803D;
    background: #F0FDF4;
    border: 1px solid #BBF7D0;
    border-radius: 20px;
    padding: 3px 9px;
    flex-shrink: 0;
    white-space: nowrap;
}

.badge-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #22C55E;
    animation: pulse-dot 2s infinite;
}

@keyframes pulse-dot {
    0%, 100% { opacity: 1; }
    50%       { opacity: .4; }
}

/* ── Description ── */
.outil-desc {
    font-size: 13px;
    color: var(--muted, #6B7280);
    line-height: 1.6;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* ── Footer ── */
.outil-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: auto;
    padding-top: 12px;
    border-top: 1px solid var(--border);
}

.outil-version {
    font-size: 11.5px;
    font-weight: 600;
    color: var(--muted);
    background: #F3F4F6;
    border-radius: 6px;
    padding: 3px 8px;
}

.outil-link {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12.5px;
    font-weight: 700;
    color: var(--gold, #F5B800);
    text-decoration: none;
    padding: 4px 10px;
    border-radius: 7px;
    transition: background .15s;
}

.outil-link:hover {
    background: #FFFBEB;
}

/* ── Responsive ── */
@media (max-width: 640px) {
    .outils-grid {
        grid-template-columns: 1fr;
    }
}

</style>

<script>
/* Auto-hide flash après 4s */
(function () {
    const flash = document.getElementById('flashMsg');
    if (!flash) return;
    setTimeout(() => {
        flash.style.transition = 'opacity .4s';
        flash.style.opacity    = '0';
        setTimeout(() => flash.remove(), 400);
    }, 4000);
})();
</script>

<?php require 'layout_end.php'; ?>