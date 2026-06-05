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

$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

// ── Filtre actif ────────────────────────────────────────────────
$filtre  = $_GET['filtre'] ?? 'tous';
$allowed = ['tous', 'actif', 'en_attente', 'refusé', 'inactif'];
if (!in_array($filtre, $allowed)) $filtre = 'tous';

// ── Récupérer les outils de l'utilisateur (tous statuts) ────────
$sql = "
    SELECT
        o.ID_OUTILS_IA,
        o.nom,
        o.description,
        o.logo_url,
        o.url,
        o.version,
        o.status,
        o.refusal_cause,           /* ← ajouter cette ligne */
        c.name AS categorie_name
    FROM OUTILS_IA o
    LEFT JOIN CATEGORIE c ON o.ID_CATEGORIE = c.ID_CATEGORIE
    WHERE o.ID_USERS_CREATE = ?
";
$params = [$userId];

if ($filtre !== 'tous') {

    if ($filtre === 'refusé') {
        $sql .= " AND o.status IN ('refusé','inactif')";
    } else {
        $sql    .= " AND o.status = ?";
        $params[] = $filtre;
    }
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$outils = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Compteurs par statut ────────────────────────────────────────
$stmtC = $pdo->prepare("
    SELECT
        COUNT(*)                          AS tous,
        SUM(status = 'actif')             AS actif,
        SUM(status = 'en_attente')        AS en_attente,
        SUM(status IN ('refusé','inactif')) AS refuse,
        SUM(status = 'inactif')           AS inactif
    FROM OUTILS_IA
    WHERE ID_USERS_CREATE = ?
");
$stmtC->execute([$userId]);
$counts = $stmtC->fetch(PDO::FETCH_ASSOC);

// ── Helpers de Style Contextuels ────────────────────────────────
$badgeMap = [
    'actif'      => 'badge-success',
    'en_attente' => 'badge-warning',
    'refusé'     => 'badge-danger',
    'inactif'    => 'badge-secondary',
];
$statusColors = [
    'actif'      => ['color' => '#16A34A', 'border' => '#BBF7D0', 'bg' => 'rgba(22, 163, 74, 0.04)'],
    'en_attente' => ['color' => '#D97706', 'border' => '#FDE68A', 'bg' => 'rgba(217, 119, 6, 0.04)'],
    'inactif'     => ['color' => '#DC2626', 'border' => '#FECACA', 'bg' => 'rgba(220, 38, 38, 0.04)'],
];
$labelMap = [
    'actif'      => 'Actif',
    'en_attente' => 'En attente',
    'refusé'     => 'Refusé',
    'inactif'    => 'Inactif',
];

$pageTitle = 'Mes Demandes';
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
.dem-container {
    max-width: 900px;
    margin: 0 auto;
}

/* ── Flash Alert ── */
.flash-success {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #FFFDF4;
    border: 1px solid #EFE4B5;
    border-radius: 12px;
    padding: 14px 18px;
    font-size: 13.5px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 24px;
    box-shadow: var(--shadow);
}

/* ── Header Area ── */
.dem-header-block {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: 28px;
}
.dem-title-h1 {
    font-family: 'Nunito', sans-serif;
    font-size: 26px;
    font-weight: 900;
    color: var(--text);
    letter-spacing: -0.5px;
    margin: 0;
}
.dem-total-span {
    font-size: 13px;
    color: var(--muted);
    font-weight: 700;
    background: var(--bg);
    padding: 4px 10px;
    border-radius: 8px;
}

/* ── Bouton Soumettre (Butter Yellow) ── */
.btn-submit-tool {
    background: #F3E5AB;
    color: var(--text);
    border: 1px solid #EFE4B5;
    padding: 12px 22px;
    border-radius: 12px;
    font-size: 13.5px;
    font-weight: 800;
    font-family: 'Nunito', sans-serif;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(243, 229, 171, 0.35);
}
.btn-submit-tool:hover {
    background: #EEDFA3;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(243, 229, 171, 0.5);
}

/* ── Barre de Filtres par Statut ── */
.dem-filtres-bar {
    display: flex;
    gap: 8px;
    background: #FFF;
    padding: 6px;
    border-radius: 14px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    margin-bottom: 24px;
    overflow-x: auto;
}
.dem-filtre-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    font-family: 'Nunito', sans-serif;
    text-decoration: none;
    color: var(--muted);
    transition: all 0.2s ease;
    white-space: nowrap;
}
.dem-filtre-link:hover {
    color: var(--primary);
    background: rgba(74, 107, 176, 0.04);
}
.dem-filtre-active {
    background: #F3E5AB !important;
    color: var(--text) !important;
    box-shadow: 0 4px 10px rgba(243, 229, 171, 0.4);
}
.dem-filtre-count {
    font-size: 11px;
    background: rgba(0, 0, 0, 0.05);
    padding: 2px 7px;
    border-radius: 6px;
    font-weight: 800;
}
.dem-filtre-active .dem-filtre-count {
    background: rgba(255, 255, 255, 0.6);
}

/* ── Cartes d'outils Premium ── */
.dem-card-ai {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    box-shadow: var(--shadow);
    transition: all 0.25s cubic-bezier(0.25, 0.8, 0.25, 1);
    position: relative;
    overflow: hidden;
    animation: fadeUp 0.4s ease both;
}
.dem-card-ai:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 28px rgba(27, 42, 74, 0.06);
}
.dem-card-body {
    padding: 24px;
}

/* Row supérieur de la carte */
.dem-card-top-row {
    display: flex;
    align-items: flex-start;
    gap: 18px;
    margin-bottom: 16px;
}

/* Logo de l'IA */
.dem-logo-box {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    border: 1px solid var(--border);
    background: #FAFAFA;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
    box-shadow: inset 0 -2px 4px rgba(0,0,0,0.02);
}
.dem-logo-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    padding: 2px;
}
.dem-initiale-box {
    font-size: 20px;
    font-weight: 900;
    color: var(--primary);
    font-family: 'Nunito', sans-serif;
}

/* Contenu textuel */
.dem-title-area {
    flex: 1;
    min-width: 0;
}
.dem-tool-name {
    font-family: 'Nunito', sans-serif;
    font-weight: 800;
    font-size: 17px;
    color: var(--text);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 6px;
    flex-wrap: wrap;
}

/* Statuts Badges */
.status-pill {
    font-size: 12px;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--bg);
    border: 1px solid var(--border);
}

.dem-meta-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}
.dem-tag-cat {
    font-size: 11px;
    font-weight: 700;
    color: var(--primary);
    background: rgba(74, 107, 176, 0.06);
    border-radius: 6px;
    padding: 3px 9px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.dem-tag-ver {
    font-size: 11px;
    font-weight: 700;
    color: var(--muted);
    background: #F3F4F6;
    border-radius: 6px;
    padding: 3px 9px;
}
.dem-desc-text {
    font-size: 13.5px;
    color: #4A5568;
    line-height: 1.6;
    margin: 0;
}

/* ── Bannières de Statut Internes ── */
.dem-status-banner {
    display: flex;
    align-items: center;
    gap: 10px;
    border-radius: 12px;
    padding: 12px 16px;
    font-size: 13px;
    font-weight: 600;
    margin-top: 14px;
}

/* ── Pied de la carte ── */
.dem-card-action-bar {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid var(--border);
}
.dem-btn-visit {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 800;
    color: var(--text);
    background: #FFF;
    border: 1px solid var(--border);
    text-decoration: none;
    padding: 8px 16px;
    border-radius: 10px;
    transition: all 0.2s ease;
}
.dem-btn-visit:hover {
    background: var(--primary);
    color: #FFF;
    border-color: var(--primary);
    transform: translateY(-1px);
}

/* Empty State Modernisé */
.empty-box-state {
    text-align: center;
    padding: 60px 20px;
    background: #FFF;
    border-radius: 20px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
}
</style>

<div class="dem-container">

    <?php if ($flashSuccess): ?>
    <div class="flash-success fade-up" id="flashMsg">
        <svg width="16" height="16" fill="none" stroke="var(--primary)" stroke-width="2.5" viewBox="0 0 24 24">
            <polyline points="20 6 9 17 4 12"/>
        </svg>
        <?= htmlspecialchars($flashSuccess) ?>
    </div>
    <?php endif; ?>

    <div class="dem-header-block fade-up">
        <div>
            <h1 class="dem-title-h1">Mes Outils Soumis</h1>
            <span class="dem-total-span"><?= (int)$counts['tous'] ?> outil<?= $counts['tous'] > 1 ? 's' : '' ?> au total</span>
        </div>
        <a href="addOutil.php" class="btn-submit-tool">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Soumettre un outil
        </a>
    </div>

    <div class="dem-filtres-bar fade-up">
        <?php
        $filtreLabels = [
            'tous'       => ['label' => 'Tous',        'count' => $counts['tous']],
            'actif'      => ['label' => 'Actifs',       'count' => $counts['actif']],
            'en_attente' => ['label' => 'En attente',   'count' => $counts['en_attente']],
            'inactif'    => ['label' => 'Inactifs',     'count' => $counts['inactif']],
        ];
        foreach ($filtreLabels as $key => $f):
            $isActive = $filtre === $key;
        ?>
        <a href="?filtre=<?= urlencode($key) ?>" class="dem-filtre-link <?= $isActive ? 'dem-filtre-active' : '' ?>">
            <?= $f['label'] ?>
            <span class="dem-filtre-count"><?= (int)$f['count'] ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <div style="display:flex;flex-direction:column;gap:16px;">
        <?php if (empty($outils)): ?>
            <div class="empty-box-state fade-up">
                <div style="width:70px;height:70px;background:#FFFDF4;border:1px solid #EFE4B5;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                    <svg width="28" height="28" fill="none" stroke="var(--primary)" stroke-width="1.5" viewBox="0 0 24 24">
                        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                    </svg>
                </div>
                <h3 style="font-family:'Nunito',sans-serif; color:var(--text); margin-bottom:6px; font-weight:800;">Aucun outil trouvé</h3>
                <p style="color:var(--muted);font-size:14px;margin:0 0 20px;">
                    <?= $filtre === 'tous' ? 'Vous n\'avez encore soumis aucun outil sur la plateforme.' : 'Aucune demande ne correspond à ce filtre actuellement.' ?>
                </p>
                <?php if ($filtre === 'tous'): ?>
                <a href="addOutil.php" class="btn-submit-tool">
                    Soumettre mon tout premier outil
                </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($outils as $i => $o):
                $status = $o['status'] ?? 'en_attente';
                $styleConfig = $statusColors[$status] ?? $statusColors['en_attente'];
            ?>
            <div class="dem-card-ai" style="border-left: 6px solid <?= $styleConfig['color'] ?>; animation-delay:<?= $i * 0.05 ?>s;">
                <div class="dem-card-body">
                    
                    <div class="dem-card-top-row">
                        <div class="dem-logo-box">
                            <?php if (!empty($o['logo_url'])): ?>
                                <img src="<?= htmlspecialchars($o['logo_url']) ?>" alt="<?= htmlspecialchars($o['nom']) ?>" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                <span class="dem-initiale-box" style="display:none;"><?= strtoupper(substr($o['nom'], 0, 1)) ?></span>
                            <?php else: ?>
                                <span class="dem-initiale-box"><?= strtoupper(substr($o['nom'], 0, 1)) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="dem-title-area">
                            <div class="dem-tool-name">
                                <?= htmlspecialchars($o['nom']) ?>
                                <span class="status-pill">
                                    <span style="color:<?= $styleConfig['color'] ?>;">●</span>
                                    <?= $labelMap[$status] ?? ucfirst($status) ?>
                                </span>
                            </div>
                            
                            <div class="dem-meta-row">
                                <?php if (!empty($o['categorie_name'])): ?>
                                    <span class="dem-tag-cat"><?= htmlspecialchars($o['categorie_name']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($o['version'])): ?>
                                    <span class="dem-tag-ver">v<?= htmlspecialchars($o['version']) ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <p class="dem-desc-text"><?= htmlspecialchars($o['description']) ?></p>
                        </div>
                    </div>

                  <?php if ($status === 'en_attente'): ?>
    <div class="dem-status-banner" style="background:#FFFBEB;color:#92400E;">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        Analyse en cours par nos modérateurs. Votre outil sera bientôt en ligne.
    </div>

<?php elseif (in_array($status, ['refusé','inactif'])): ?>
    <div style="border-radius:14px;overflow:hidden;margin-top:14px;border:1px solid #FECACA;">

        <div style="background:#FEF2F2;border-bottom:1px solid #FECACA;padding:14px 18px;display:flex;align-items:center;gap:12px;">
            <div style="width:36px;height:36px;border-radius:50%;background:#FCEBEB;border:1px solid #FCA5A5;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg width="16" height="16" fill="none" stroke="#A32D2D" stroke-width="2.5" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
            </div>
            <div style="flex:1;">
                <p style="margin:0;font-size:13px;font-weight:800;color:#7F1D1D;">Demande refusée</p>
                <p style="margin:0;font-size:12px;color:#B91C1C;font-weight:600;">Cet outil ne respecte pas nos critères d'éligibilité</p>
            </div>
            <span style="background:#FCEBEB;border:1px solid #FCA5A5;border-radius:999px;padding:3px 12px;font-size:11px;font-weight:800;color:#7F1D1D;white-space:nowrap;font-family:'Nunito',sans-serif;">
                Inactif
            </span>
        </div>

        <div style="padding:16px 18px;background:#fff;">
            <p style="margin:0 0 10px;font-size:11px;font-weight:800;color:#94A3B8;text-transform:uppercase;letter-spacing:.6px;">Motif de refus</p>

            <?php if (!empty($o['refusal_cause'])): ?>
                <div style="background:#FEF2F2;border-left:4px solid #E24B4A;border-radius:0 10px 10px 0;padding:14px 16px;">
                    <p style="margin:0;font-size:13.5px;color:#7F1D1D;line-height:1.7;font-weight:600;">
                        <?= htmlspecialchars($o['refusal_cause']) ?>
                    </p>
                </div>
            <?php else: ?>
                <div style="background:#FEF2F2;border-left:4px solid #E24B4A;border-radius:0 10px 10px 0;padding:14px 16px;">
                    <p style="margin:0;font-size:13.5px;color:#B91C1C;line-height:1.7;font-style:italic;">
                        Aucun motif précisé par l'équipe de modération.
                    </p>
                </div>
            <?php endif; ?>

            <div style="margin-top:12px;display:flex;align-items:center;gap:8px;">
                <svg width="14" height="14" fill="none" stroke="#94A3B8" stroke-width="2" viewBox="0 0 24 24">
                    <polyline points="23 4 23 10 17 10"/>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                </svg>
                <span style="font-size:12px;color:#94A3B8;font-weight:600;">Vous pouvez corriger les informations et resoumettre l'outil</span>
            </div>
        </div>

    </div>

<?php elseif ($status === 'actif'): ?>
    <div class="dem-status-banner" style="background:#F0FDF4;color:#15803D;">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <polyline points="20 6 9 17 4 12"/>
        </svg>
        Félicitations ! Votre outil est officiellement publié et accessible.
    </div>

<?php endif; ?>

                    <?php if (!empty($o['url'])): ?>
                    <div class="dem-card-action-bar">
                        <a href="<?= htmlspecialchars($o['url']) ?>" target="_blank" rel="noopener" class="dem-btn-visit">
                            Visiter le site
                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                <polyline points="15 3 21 3 21 9"/>
                                <line x1="10" y1="14" x2="21" y2="3"/>
                            </svg>
                        </a>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
// Auto-suppression élégante du message flash
(function() {
    var flash = document.getElementById('flashMsg');
    if (!flash) return;
    setTimeout(function() {
        flash.style.transition = 'all .4s ease';
        flash.style.opacity = '0';
        flash.style.transform = 'translateY(-10px)';
        setTimeout(function(){ flash.remove(); }, 400);
    }, 4000);
})();
</script>

<?php require 'layout_end.php'; ?>