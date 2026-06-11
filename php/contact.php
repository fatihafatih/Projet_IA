<?php
session_start();
require_once '../includes/connexionbd.php';

$success = '';
$errors  = [];

// ── Traitement du formulaire ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
    $nom     = trim($_POST['nom']     ?? '');
    $email   = trim($_POST['email']   ?? '');
    $sujet   = trim($_POST['sujet']   ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!$nom)                              $errors[] = 'Le nom est requis.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';
    if (!$sujet)                            $errors[] = 'Le sujet est requis.';
    if (strlen($message) < 20)             $errors[] = 'Le message doit contenir au moins 20 caractères.';

    if (empty($errors)) {
        // En production : envoyer un mail via mail() ou PHPMailer
        // mail('contact@searchai.ma', "Contact: $sujet", $message, "From: $email");
        $success = "Merci $nom ! Votre message a bien été envoyé. Nous vous répondrons sous 24h.";
    }
}

// Catégories pour le sujet
$sujets = [
    'question'   => '❓ Question générale',
    'bug'        => '🐛 Signaler un bug',
    'suggestion' => '💡 Suggestion d\'amélioration',
    'partenariat'=> '🤝 Proposition de partenariat',
    'presse'     => '📰 Contact presse / média',
    'autre'      => '📬 Autre',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Contact — SearchIA</title>
  <link rel="stylesheet" href="../styles/dashboard.css">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    :root{--navy:#1B2A4A;--gold:#F3E5AB;--coral:#E11D48;--slate:#64748B;--bg:#F8FAFC;}
    body{background:var(--bg);font-family:'Plus Jakarta Sans',sans-serif;color:var(--navy);}

    /* ── Hero ── */
    .contact-hero{
      background:linear-gradient(160deg,#0a1628 0%,#1B2A4A 60%,#2d1836 100%);
      padding:80px 2rem 70px;text-align:center;
    }
    .contact-eyebrow{
      display:inline-flex;align-items:center;gap:8px;
      font-size:.75rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;
      color:var(--gold);background:rgba(243,229,171,.1);
      border:1px solid rgba(243,229,171,.25);
      padding:6px 18px;border-radius:99px;margin-bottom:22px;
    }
    .contact-hero h1{font-size:clamp(2rem,5vw,3rem);font-weight:900;color:#F8FAFC;margin-bottom:12px;}
    .contact-hero h1 span{background:linear-gradient(135deg,var(--gold),var(--coral));-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
    .contact-hero p{color:#94A3B8;font-size:1rem;max-width:520px;margin:0 auto;}

    /* ── Layout principal ── */
    .contact-layout{
    max-width:900px;
    margin:0 auto;
    padding:70px 20px;
}

    /* ── Infos contact ── */
    .contact-info h2{font-size:1.3rem;font-weight:800;margin-bottom:6px;}
    .contact-info > p{font-size:.88rem;color:var(--slate);margin-bottom:32px;line-height:1.7;}

    .info-card{
      background:#fff;border:1.5px solid #E2E8F0;border-radius:16px;
      padding:20px;display:flex;align-items:center;gap:16px;
      margin-bottom:14px;transition:transform .2s,box-shadow .2s;
    }
    .info-card:hover{transform:translateX(4px);box-shadow:0 6px 20px rgba(27,42,74,.08);}
    .info-icon{
      width:46px;height:46px;border-radius:14px;flex-shrink:0;
      background:linear-gradient(135deg,rgba(243,229,171,.4),rgba(225,29,72,.1));
      display:flex;align-items:center;justify-content:center;font-size:1.2rem;
    }
    .info-card h4{font-size:.85rem;font-weight:700;margin-bottom:3px;}
    .info-card p{font-size:.82rem;color:var(--slate);}
    .info-card a{color:var(--coral);text-decoration:none;font-weight:600;}

    .social-row{display:flex;gap:10px;margin-top:28px;}
    .social-btn{
      display:flex;align-items:center;gap:8px;
      padding:10px 18px;border-radius:12px;
      border:1.5px solid #E2E8F0;background:#fff;
      font-size:.82rem;font-weight:600;color:var(--navy);
      text-decoration:none;transition:all .2s;
    }
    .social-btn:hover{border-color:var(--coral);color:var(--coral);background:rgba(225,29,72,.03);}

    /* ── Formulaire ── */
   .contact-form-wrap{
    max-width:850px;
    margin:auto;
    padding:50px;
}
    .form-title{font-size:1.2rem;font-weight:800;margin-bottom:6px;}
    .form-sub{font-size:.83rem;color:var(--slate);margin-bottom:30px;}

    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
    .form-group{margin-bottom:18px;}
    .form-group label{
      display:block;font-size:.78rem;font-weight:700;
      letter-spacing:.05em;text-transform:uppercase;color:var(--slate);
      margin-bottom:7px;
    }
    .form-group input,
    .form-group select,
    .form-group textarea{
      width:100%;padding:13px 16px;
      border:1.5px solid #E2E8F0;border-radius:12px;
      font-family:'Plus Jakarta Sans',sans-serif;
      font-size:.9rem;color:var(--navy);background:#FAFBFC;
      transition:border-color .2s,box-shadow .2s;outline:none;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus{
      border-color:var(--coral);
      box-shadow:0 0 0 3px rgba(225,29,72,.08);
      background:#fff;
    }
    .form-group textarea{resize:vertical;min-height:130px;}

    /* Remplissage auto si connecté */
    .prefill-notice{
      display:flex;align-items:center;gap:8px;
      font-size:.78rem;color:#16A34A;font-weight:600;
      background:rgba(34,197,94,.07);border:1px solid rgba(34,197,94,.2);
      padding:9px 14px;border-radius:10px;margin-bottom:20px;
    }

    .btn-submit{
      width:100%;padding:15px;border:none;cursor:pointer;
      background:linear-gradient(135deg,var(--gold) 0%,#FBBF24 50%,var(--coral) 100%);
      background-size:200% auto;
      color:var(--navy);font-family:'Plus Jakarta Sans',sans-serif;
      font-size:.95rem;font-weight:800;border-radius:14px;
      box-shadow:0 6px 22px rgba(225,29,72,.25);
      transition:all .3s;
    }
    .btn-submit:hover{background-position:right center;transform:translateY(-2px);box-shadow:0 10px 32px rgba(225,29,72,.4);}

    /* Messages ── */
    .alert{padding:14px 18px;border-radius:12px;font-size:.88rem;font-weight:600;margin-bottom:20px;}
    .alert-success{background:rgba(34,197,94,.1);color:#16A34A;border:1px solid rgba(34,197,94,.25);}
    .alert-error{background:rgba(225,29,72,.08);color:var(--coral);border:1px solid rgba(225,29,72,.2);}
    .alert ul{margin:6px 0 0 16px;}
    .alert li{margin-bottom:3px;}

    /* Compteur caractères */
    .char-count{font-size:.72rem;color:var(--slate);text-align:right;margin-top:4px;}

    @media(max-width:900px){
      .contact-layout{grid-template-columns:1fr;}
      .form-row{grid-template-columns:1fr;}
    }
    @media(max-width:480px){.contact-form-wrap{padding:28px 20px;}}
  </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<!-- ═══ HERO ═══════════════════════════════════════════════════ -->
<section class="contact-hero">
  <h1>Une question ? <span>On vous répond.</span></h1>
  <p>Notre équipe est disponible 7j/7. Réponse garantie sous 24 heures ouvrées.</p>
</section>

<!-- ═══ LAYOUT ══════════════════════════════════════════════════ -->
<div class="contact-layout">

 

  <!-- ── Formulaire ── -->
  <div class="contact-form-wrap">
    <div class="form-title">Envoyez-nous un message</div>
    <div class="form-sub">Remplissez le formulaire ci-dessous et nous vous répondrons rapidement.</div>

    <?php if ($success): ?>
      <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php elseif ($errors): ?>
      <div class="alert alert-error">
        ❌ Merci de corriger les erreurs suivantes :
        <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['user_id']) && isset($_SESSION['user_email'])): ?>
      <div class="prefill-notice">✅ Formulaire pré-rempli avec vos informations de compte.</div>
    <?php endif; ?>

    <form method="POST" action="" novalidate>
      <div class="form-row">
        <div class="form-group">
          <label for="nom">Nom complet *</label>
          <input type="text" id="nom" name="nom" placeholder="Votre nom"
            value="<?= htmlspecialchars($_SESSION['user_nom'] ?? $_POST['nom'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label for="email">Email *</label>
          <input type="email" id="email" name="email" placeholder="votre@email.com"
            value="<?= htmlspecialchars($_SESSION['user_email'] ?? $_POST['email'] ?? '') ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label for="sujet">Sujet *</label>
        <select id="sujet" name="sujet" required>
          <option value="">-- Choisissez un sujet --</option>
          <?php foreach ($sujets as $val => $label): ?>
            <option value="<?= $val ?>" <?= (($_POST['sujet'] ?? '') === $val) ? 'selected' : '' ?>>
              <?= $label ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="message">Message * <span style="font-weight:400;text-transform:none">(20 caractères min.)</span></label>
        <textarea id="message" name="message" placeholder="Décrivez votre demande en détail…" maxlength="2000" oninput="updateCount(this)" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
        <div class="char-count"><span id="charCount">0</span> / 2000 caractères</div>
      </div>

      <button type="submit" name="submit_contact" class="btn-submit">
        Envoyer le message →
      </button>
    </form>
  </div>
</div>

<?php include "../includes/footer.php"; ?>

<script>
function updateCount(el) {
  document.getElementById('charCount').textContent = el.value.length;
}
// Init au chargement si textarea pré-rempli
document.addEventListener('DOMContentLoaded', () => {
  const ta = document.getElementById('message');
  if (ta) document.getElementById('charCount').textContent = ta.value.length;
});
</script>
</body>
</html>