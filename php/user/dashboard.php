<?php
$pageTitle = 'Dashboard';
require 'layout.php';
require_once '../../includes/connexionbd.php';

if (!isset($_SESSION['user_id'])) {

  header('Location: dashboard.php');
  exit;
}

$userId = $_SESSION['user_id'];

// ── 1. COMPTEURS DES STATS ───────────────────────────────────
$stmtO = $pdo->prepare("SELECT COUNT(*) FROM OUTILS_IA WHERE ID_USERS_CREATE = ?");
$stmtO->execute([$userId]);
$nbOutils = $stmtO->fetchColumn();

$stmtF = $pdo->prepare("SELECT COUNT(*) FROM FAVORIS WHERE ID_USERS = ?");
$stmtF->execute([$userId]);
$nbFavoris = $stmtF->fetchColumn();

$stmtD = $pdo->prepare("SELECT COUNT(*) FROM outils_ia WHERE ID_USERS_CREATE = ? AND status = 'en_attente'");
$stmtD->execute([$userId]);
$nbDemandes = $stmtD->fetchColumn();

$stmtN = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE ID_USERS = ?");
$stmtN->execute([$userId]);
$nbNotifs = $stmtN->fetchColumn();


// ── 2. ACTIVITÉ RÉCENTE SANS DATE (Trié par ID décroissant) ──
/* On utilise l'ID de chaque table pour trier l'UNION.
   Puisqu'on n'a pas de date, on affiche un texte fixe ou un indicateur générique.
*/
// ── 2. ACTIVITÉ RÉCENTE SANS DATE (Correction du mix de collations) ──
// ── 2. ACTIVITÉ RÉCENTE DYNAMIQUE (Version ultra-robuste pour les icônes) ──
$stmtActivites = $pdo->prepare("
    (SELECT 'outil' AS type_action, ID_OUTILS_IA AS tri_id, 
            CONVERT(CONCAT('Outil \"', nom, '\" ajouté') USING utf8mb4) AS texte
     FROM outils_ia 
     WHERE ID_USERS_CREATE = ? )
    UNION
    (SELECT 'favori' AS type_action, f.ID_FAVORIS AS tri_id, 
            CONVERT(CONCAT('IA \"', o.nom, '\" ajoutée aux favoris') USING utf8mb4) AS texte
     FROM favoris f
     INNER JOIN outils_ia o ON f.ID_OUTILS_IA = o.ID_OUTILS_IA
     WHERE f.ID_USERS = ?)
    UNION
    (SELECT 'notif' AS type_action, ID_NOTIF AS tri_id, 
            CONVERT(message USING utf8mb4) AS texte
     FROM notifications 
     WHERE ID_USERS = ?)
    ORDER BY tri_id DESC 
    LIMIT 5
");
$stmtActivites->execute([$userId, $userId, $userId]);
$activites = $stmtActivites->fetchAll(PDO::FETCH_ASSOC);


// ── 3. DEMANDES RÉCENTES SANS DATE ──────────────────────────
$stmtDemandes = $pdo->prepare("
    SELECT nom AS titre, status AS statut
    FROM outils_ia
    WHERE ID_USERS_CREATE = ?
    ORDER BY ID_OUTILS_IA DESC
    LIMIT 3
");
$stmtDemandes->execute([$userId]);
$demandes = $stmtDemandes->fetchAll(PDO::FETCH_ASSOC);

?>

<style>
  body {
    margin: 0;
    min-height: 100vh;
    font-family: 'Segoe UI', Roboto, sans-serif;
    color: #1E293B;
    background: #FAF9F6;
    background-image:
      radial-gradient(circle at 80% 20%, rgba(255, 217, 0, 0.1), transparent 40%),
      radial-gradient(circle at 15% 50%, rgba(250, 237, 205, 0.7), transparent 45%),
      radial-gradient(circle at 50% 80%, rgba(241, 245, 249, 1), transparent 50%);
    background-attachment: fixed;
    position: relative;
  }

  body::before {
    content: "";
    position: absolute;
    top: 0;
    left: 25%;
    right: 25%;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(245, 184, 0, 0.4), transparent);
    pointer-events: none;
  }
</style>
<div class="stats-grid fade-up">
  <div class="stat-card">
    <div class="stat-icon">
      <svg width="18" height="18" fill="none" stroke="#F5B800" stroke-width="2.2" viewBox="0 0 24 24">
        <path
          d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />
      </svg>
    </div>
    <div class="stat-value"><?= $nbOutils ?></div>
    <div class="stat-label">Outils ajoutés</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">
      <svg width="18" height="18" fill="none" stroke="#F5B800" stroke-width="2.2" viewBox="0 0 24 24">
        <path
          d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
      </svg>
    </div>
    <div class="stat-value"><?= $nbFavoris ?></div>
    <div class="stat-label">Favoris</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">
      <svg width="18" height="18" fill="none" stroke="#F5B800" stroke-width="2.2" viewBox="0 0 24 24">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
        <polyline points="14 2 14 8 20 8" />
      </svg>
    </div>
    <div class="stat-value"><?= $nbDemandes ?></div>
    <div class="stat-label">Demandes</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">
      <svg width="18" height="18" fill="none" stroke="#F5B800" stroke-width="2.2" viewBox="0 0 24 24">
        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
        <path d="M13.73 21a2 2 0 0 1-3.46 0" />
      </svg>
    </div>
    <div class="stat-value"><?= $nbNotifs ?></div>
    <div class="stat-label">Notifications</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:24px;">

  <div class="card fade-up-2">
    <div class="card-header">
      <div class="card-title">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
        </svg>
        Activité récente
      </div>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($activites)): ?>
        <div style="padding: 24px; text-align: center; color: var(--muted);">Aucune activité récente.</div>
      <?php else: ?>
        <?php foreach ($activites as $a): ?>
          <?php
          // On associe l'icône directement en PHP pour éviter les bugs d'encodage MySQL
          $icon = match ($a['type_action']) {
            'outil' => '🔧',
            'favori' => '❤️',
            'notif' => '🔔',
            default => '✨',
          };
          ?>
          <div style="display:flex;align-items:flex-start;gap:14px;padding:14px 24px;border-bottom:1px solid #F9FAFB;">
            <div
              style="width:36px;height:36px;border-radius:10px;background:#FFFBEB;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:16px;">
              <?= $icon ?>
            </div>
            <div style="flex:1;">
              <div style="font-size:13.5px;font-weight:600;color:#1E293B;"><?= htmlspecialchars($a['texte']) ?></div>
              <div style="font-size:12px;color:#64748B;margin-top:2px;">Récemment</div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="card fade-up-3" style="align-self:start;">
    <div class="card-header">
      <div class="card-title">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
          <polyline points="14 2 14 8 20 8" />
        </svg>
        Demandes récentes
      </div>
      <a href="/Projet_IA/php/user/demandes.php"
        style="font-size:12px;color:var(--gold);font-weight:700;text-decoration:none;">Voir tout →</a>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($demandes)): ?>
        <div style="padding: 20px; text-align: center; color: var(--muted);">Aucune demande.</div>
      <?php else: ?>
        <?php foreach ($demandes as $d): ?>
          <?php
          $statutFormate = strtolower($d['statut']);
          $badgeClass = match ($statutFormate) {
            'actif', 'actif', 'accepte', 'accepté' => 'badge-success',
            'inactif', 'inactif' => 'badge-danger',
            default => 'badge-warning',
          };
          ?>
          <div
            style="padding:14px 20px;border-bottom:1px solid #F9FAFB;display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <div>
              <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($d['titre']) ?></div>
              <div style="font-size:11px;color:var(--muted);margin-top:2px;">Suivi en cours</div>
            </div>
            <span class="badge <?= $badgeClass ?>"><?= ucfirst($statutFormate) ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>

<?php require 'layout_end.php'; ?>