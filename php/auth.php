<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Authentification</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">


<style>

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: "Inter", Arial, sans-serif;
  background: #f4f6f9;
  min-height: 100vh;
}

/* Bouton "Se connecter" (non connecté) */
.btn-login-header {
  padding: 8px 20px;
  background: #F5B800;
  color: #1A1A2E;
  border: none;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 800;
  font-family: 'Nunito', sans-serif;
  cursor: pointer;
  transition: .15s;
  box-shadow: 0 3px 10px rgba(245,184,0,0.35);
}
.btn-login-header:hover { background: #E0A800; }

/* Avatar (connecté) */
.avatar-header {
  display: none;
  position: relative;
}
.avatar-circle {
  width: 42px; height: 42px;
  border-radius: 50%;
  background: #F5B800;
  display: flex; align-items: center; justify-content: center;
  color: #1A1A2E;
  font-size: 18px; font-weight: 800;
  font-family: 'Nunito', sans-serif;
  border: 2px solid rgba(255,255,255,0.25);
  cursor: pointer;
  transition: .2s;
  user-select: none;
}
.avatar-circle:hover { background: #E0A800; transform: scale(1.05); }

/* Dropdown profil (hover) */
.profile-dropdown {
  position: absolute;
  top: calc(100% + 12px);
  right: 0;
  width: 210px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  box-shadow: 0 16px 40px rgba(0,0,0,0.13);
  overflow: hidden;
  opacity: 0;
  pointer-events: none;
  transform: translateY(-6px);
  transition: opacity .2s, transform .2s;
  z-index: 200;
}
.avatar-header:hover .profile-dropdown,
.profile-dropdown:hover {
  opacity: 1;
  pointer-events: all;
  transform: translateY(0);
}

/* Flèche haut */
.profile-dropdown::before {
  content: '';
  position: absolute;
  top: -7px; right: 14px;
  width: 13px; height: 13px;
  background: #fff;
  border-left: 1px solid #e5e7eb;
  border-top: 1px solid #e5e7eb;
  transform: rotate(45deg);
  border-radius: 2px 0 0 0;
}

.pd-name {
  padding: 14px 16px 4px;
  font-size: 14px; font-weight: 700;
  color: #111827;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.pd-divider { height: 1px; background: #f3f4f6; margin: 8px 0; }

.pd-item {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 16px;
  font-size: 13px; font-weight: 600;
  color: #374151;
  cursor: pointer;
  transition: .15s;
  text-decoration: none;
  background: none; border: none; width: 100%; text-align: left;
}
.pd-item:hover { background: #f9fafb; }
.pd-item svg { flex-shrink: 0; opacity: .6; }

.pd-item.danger { color: #dc2626; }
.pd-item.danger:hover { background: #fef2f2; }
.pd-item.danger svg { opacity: .7; }

/* ── DROPDOWN AUTH (formulaire) ── */
.auth-dropdown {
  position: absolute;
  top: 68px; right: 24px;
  width: 330px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  box-shadow: 0 20px 50px rgba(0,0,0,0.13);
  overflow: hidden;
  display: none;
  z-index: 100;
  animation: fadeDown .2s ease;
}
@keyframes fadeDown {
  from { opacity:0; transform:translateY(-8px); }
  to   { opacity:1; transform:translateY(0); }
}

.dropdown-wave { position: relative; height: 90px; overflow: hidden; pointer-events: none; }
.dropdown-wave svg { position: absolute; top:0; left:0; width:100%; height:100%; }

.tabs { display: flex; position: relative; z-index: 2; margin-top: -2px; }
.tab {
  flex:1; padding:12px; border:none; background:transparent;
  cursor:pointer; font-size:14px; font-weight:700; color:#030303;
  transition:.15s; font-family:'Nunito',sans-serif;
}
.tab.active { border-bottom: 3px solid #F5B800; }
.tabs-divider { height:1px; background:#e5e7eb; }

.global-msg {
  margin:12px 16px 0; padding:9px 12px; border-radius:10px;
  font-size:12px; font-weight:500; display:none;
}
.global-msg.success { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; display:block; }
.global-msg.error   { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; display:block; }
.global-msg.info    { background:#fffbeb; color:#92400e; border:1px solid #fde68a; display:block; }

.form-body { padding:14px 16px 16px; display:none; }
.form-body.active { display:block; }

.google-btn {
  width:100%; padding:10px; margin-bottom:14px;
  display:flex; align-items:center; justify-content:center; gap:8px;
  border:1px solid #e5e7eb; border-radius:10px; background:#fff;
  cursor:pointer; font-size:13px; font-weight:600; color:#374151; transition:.15s;
}
.google-btn:hover { background:#f9fafb; border-color:#d1d5db; }

.divider { display:flex; align-items:center; gap:8px; margin-bottom:14px; }
.divider::before,.divider::after { content:''; flex:1; height:1px; background:#e5e7eb; }
.divider span { font-size:11px; color:#9ca3af; }

.field { margin-bottom:10px; }
.field input {
  width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:10px;
  background:#fff; color:#111827; font-size:13px; outline:none; transition:.2s;
}
.field input:focus { border-color:#F5B800; box-shadow:0 0 0 3px rgba(245,184,0,0.15); }
.field input.err-input { border-color:#ef4444; }
.field input.ok-input  { border-color:#22c55e; }

.field-msg { font-size:11.5px; margin-top:4px; display:none; }
.field-msg.error   { color:#ef4444; display:block; }
.field-msg.success { color:#16a34a; display:block; }
.field-msg.info    { color:#2563eb; display:block; }

.strength-bar { height:4px; background:#e5e7eb; border-radius:2px; margin-top:5px; overflow:hidden; }
.strength-fill { height:100%; width:0; border-radius:2px; transition:width .3s,background .3s; }

.otp-input {
  text-align:center; font-size:28px !important;
  letter-spacing:12px !important; font-weight:700 !important; color:#F5B800 !important;
}

.submit-btn {
  width:100%; padding:11px; background:#F5B800; color:#1A1A2E;
  border:none; border-radius:10px; font-size:13px; font-weight:800;
  cursor:pointer; transition:.15s; margin-top:4px;
  font-family:'Nunito',sans-serif; letter-spacing:0.5px;
  box-shadow:0 4px 14px rgba(245,184,0,0.35);
}
.submit-btn:hover  { background:#E0A800; }
.submit-btn:active { transform:scale(0.99); }
.submit-btn:disabled { background:#fde68a; cursor:not-allowed; box-shadow:none; }

.links-row { display:flex; justify-content:space-between; margin-top:12px; }
.lnk { font-size:12px; color:#E0A800; background:none; border:none; cursor:pointer; padding:0; font-weight:600; }
.lnk:hover { text-decoration:underline; }

.reset-desc { font-size:13px; color:#6b7280; line-height:1.55; margin-bottom:14px; }
.back-link { display:inline-block; margin-top:10px; font-size:12px; color:#6b7280; cursor:pointer; }
.back-link:hover { text-decoration:underline; }

/* GOOGLE POPUP */
.google-overlay {
  position:fixed; inset:0; background:rgba(0,0,0,0.5);
  display:none; align-items:center; justify-content:center; z-index:999;
}
.google-overlay.show { display:flex; }
.google-popup {
  background:#fff; border-radius:16px; padding:28px; width:320px;
  box-shadow:0 20px 60px rgba(0,0,0,0.2); text-align:center;
}
.google-popup h3 { font-size:16px; color:#111827; margin-bottom:6px; }
.google-popup p  { font-size:13px; color:#6b7280; margin-bottom:20px; }
.google-accounts { display:flex; flex-direction:column; gap:8px; margin-bottom:16px; }
.google-account {
  display:flex; align-items:center; gap:10px;
  padding:10px 12px; border:1px solid #e5e7eb;
  border-radius:10px; cursor:pointer; transition:.15s; text-align:left;
}
.google-account:hover { background:#f9fafb; border-color:#d1d5db; }
.google-avatar { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:15px; font-weight:600; color:#fff; }
.google-account-name  { font-size:13px; font-weight:600; color:#111827; }
.google-account-email { font-size:11px; color:#6b7280; }
.google-cancel { font-size:12px; color:#6b7280; background:none; border:none; cursor:pointer; }
.google-cancel:hover { text-decoration:underline; }

/* PAGE */
.page-content { max-width:600px; margin:60px auto; padding:0 20px; text-align:center; }
.page-content h1 { font-size:24px; color:#111827; margin-bottom:10px; }
.page-content p  { font-size:15px; color:#6b7280; }



/* ── Avatar header ── */
.avatar-header {
  position: relative;
}

.avatar-circle {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  background: #F5B800;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #1A1A2E;
  font-size: 17px;
  font-weight: 800;
  font-family: 'Nunito', sans-serif;
  cursor: pointer;
  border: 2px solid rgba(255, 255, 255, 0.3);
  transition: transform .2s, background .2s;
  user-select: none;
}
.avatar-circle:hover {
  background: #E0A800;
  transform: scale(1.06);
}

/* ── Profile dropdown ── */
.profile-dropdown {
  position: absolute;
  top: calc(100% + 12px);
  right: 0;
  width: 210px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.13);
  overflow: hidden;
  opacity: 0;
  pointer-events: none;
  transform: translateY(-6px);
  transition: opacity .2s, transform .2s;
  z-index: 300;
}
.profile-dropdown.open {
  opacity: 1;
  pointer-events: all;
  transform: translateY(0);
}

/* Flèche triangle */
.profile-dropdown::before {
  content: '';
  position: absolute;
  top: -7px;
  right: 14px;
  width: 13px;
  height: 13px;
  background: #fff;
  border-left: 1px solid #e5e7eb;
  border-top: 1px solid #e5e7eb;
  transform: rotate(45deg);
  border-radius: 2px 0 0 0;
}

.pd-name {
  padding: 14px 16px 2px;
  font-size: 14px;
  font-weight: 700;
  color: #111827;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.pd-email {
  padding: 0 16px 10px;
  font-size: 12px;
  color: #9ca3af;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.pd-divider {
  height: 1px;
  background: #f3f4f6;
}
.pd-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 11px 16px;
  font-size: 13px;
  font-weight: 600;
  color: #374151;
  cursor: pointer;
  transition: background .15s;
  text-decoration: none;
  background: none;
  border: none;
  width: 100%;
  text-align: left;
}
.pd-item:hover { background: #f9fafb; }
.pd-item svg   { flex-shrink: 0; opacity: .6; }
.pd-item.danger       { color: #dc2626; }
.pd-item.danger:hover { background: #fef2f2; }
.pd-item.danger svg   { opacity: .7; }
</style>
</head>
<body>
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isLoggedIn = isset($_SESSION['user_id']);

$userName   = $isLoggedIn ? $_SESSION['nom']   : '';
$userEmail  = $isLoggedIn ? $_SESSION['email'] : '';
$userRole   = $isLoggedIn ? $_SESSION['role']  : '';
$initial    = $isLoggedIn ? strtoupper(mb_substr($userName, 0, 1)) : '';
?>

<!-- Bouton connexion -->
<button 
  class="btn-login-header"
  id="btnLoginHeader"
  onclick="openAuthDropdown()"
  style="<?= $isLoggedIn ? 'display:none;' : '' ?>"
>
  Se connecter
</button>

<!-- Avatar profil -->
<div 
  class="avatar-header"
  id="avatarHeader"
  style="<?= $isLoggedIn ? 'display:block;' : 'display:none;' ?>"
>
  <div class="avatar-circle" id="avatarCircle" onclick="toggleProfileDropdown(event)">
    <?= $initial ?: '?' ?>
  </div>

 <!-- Remplace le bloc .profile-dropdown dans auth.php par celui-ci -->

<div class="profile-dropdown" id="profileDropdown">

  <div class="pd-name" id="pdName"><?= htmlspecialchars($userName) ?></div>
  <div class="pd-email" id="pdEmail"><?= htmlspecialchars($userEmail) ?></div>
  <div class="pd-divider"></div>

  <a class="pd-item"
   href="<?= $userRole === 'admin'
       ? '/Projet_IA/php/admin/index.php'
       : '/Projet_IA/php/user/dashboard.php' ?>">
    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
    Dashboard
  </a> 

<a class="pd-item"
   href="/Projet_IA/php/user/profil.php">    
  <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
    Mon Profil
  </a>

<a class="pd-item"
   href="<?= $userRole === 'admin'
       ? '/Projet_IA/php/admin/notifications.php'
       : '/Projet_IA/php/user/notifications.php' ?>">
    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
    Notifications
    <span id="notifBadge" style="margin-left:auto;background:#ef4444;color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:99px;display:none">0</span>
  </a>

  <div class="pd-divider"></div>

  <a class="pd-item danger" href="/Projet_IA/php/logout.php">
    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    Déconnexion
  </a>



  </div>
</div>

<!-- DROPDOWN AUTH -->
<div class="auth-dropdown" id="authDropdown">

  <div class="dropdown-wave" id="dropdownWave">
    <svg viewBox="0 0 330 90" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
      <rect width="330" height="90" fill="#FFF8DC"/>
      <path d="M0,90 L0,30 Q60,70 130,45 Q200,20 260,50 Q300,65 330,40 L330,90 Z" fill="#FFE57A" opacity="0.6"/>
      <path d="M0,0 L180,0 Q210,5 200,35 Q190,65 140,72 Q90,80 40,60 Q10,48 0,55 Z" fill="#F5B800"/>
      <path d="M0,0 L120,0 Q145,4 138,28 Q130,52 90,58 Q50,64 20,50 Q0,42 0,35 Z" fill="#F5B800" opacity="0.7"/>
      <path d="M330,90 L330,55 Q305,45 285,60 Q265,75 270,90 Z" fill="#F5B800" opacity="0.8"/>
      <path d="M330,90 L330,70 Q318,62 308,72 Q298,82 305,90 Z" fill="#F5B800"/>
    </svg>
  </div>

  <div class="tabs" id="tabsRow">
    <button class="tab active" id="tabLogin"  onclick="switchTab('login')">Connexion</button>
    <button class="tab"        id="tabSignup" onclick="switchTab('signup')">Inscription</button>
  </div>
  <div class="tabs-divider" id="tabsDivider"></div>

  <div id="globalMsg" class="global-msg"></div>

  <!-- LOGIN -->
  <div class="form-body active" id="loginForm">
    <button class="google-btn" onclick="loginWithGoogle()">
      <img src="https://developers.google.com/identity/images/g-logo.png" width="18">
      Continuer avec Google
    </button>
    <div class="divider"><span>ou</span></div>
    <div class="field">
      <input type="email" id="loginEmail" placeholder="Adresse email" oninput="clearField('loginEmail','loginEmailMsg')">
      <div class="field-msg" id="loginEmailMsg"></div>
    </div>
    <div class="field">
      <input type="password" id="loginPwd" placeholder="Mot de passe" oninput="clearField('loginPwd','loginPwdMsg')" onkeydown="if(event.key==='Enter') doLogin()">
      <div class="field-msg" id="loginPwdMsg"></div>
    </div>
    <button class="submit-btn" id="loginBtn" onclick="doLogin()">Se connecter</button>
    <div class="links-row">
      <button class="lnk" onclick="switchTab('reset')">Mot de passe oublié ?</button>
      <button class="lnk" onclick="switchTab('signup')">Créer un compte</button>
    </div>
  </div>

  <!-- SIGNUP -->
  <div class="form-body" id="signupForm">
    <div class="field">
      <input type="text" id="signupNom" placeholder="Nom complet" oninput="clearField('signupNom','signupNomMsg')">
      <div class="field-msg" id="signupNomMsg"></div>
    </div>
    <div class="field">
      <input type="email" id="signupEmail" placeholder="Adresse email" oninput="clearField('signupEmail','signupEmailMsg')">
      <div class="field-msg" id="signupEmailMsg"></div>
    </div>
    <div class="field">
      <input type="password" id="signupP1" placeholder="Mot de passe" oninput="checkStrength()">
      <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
      <div class="field-msg" id="strengthMsg"></div>
    </div>
    <div class="field">
      <input type="password" id="signupP2" placeholder="Confirmer le mot de passe" oninput="clearField('signupP2','signupP2Msg')" onkeydown="if(event.key==='Enter') doSignup()">
      <div class="field-msg" id="signupP2Msg"></div>
    </div>
    <button class="submit-btn" id="signupBtn" onclick="doSignup()">Créer mon compte</button>
    <div class="links-row">
      <button class="lnk" onclick="switchTab('login')">Déjà inscrit ? Se connecter</button>
    </div>
  </div>

  <!-- RESET -->
  <div class="form-body" id="resetForm">
    <div id="resetStep1">
      <p class="reset-desc">Entrez votre email pour recevoir un code de vérification.</p>
      <div class="field">
        <input type="email" id="resetEmail" placeholder="Adresse email" oninput="clearField('resetEmail','resetEmailMsg')" onkeydown="if(event.key==='Enter') doReset()">
        <div class="field-msg" id="resetEmailMsg"></div>
      </div>
      <button class="submit-btn" id="resetBtn" onclick="doReset()">Envoyer le code</button>
    </div>
    <div id="resetStep2" style="display:none">
      <p class="reset-desc">Entrez le code à 6 chiffres envoyé à votre email.</p>
      <div class="field">
        <input type="text" id="otpInput" placeholder="000000" maxlength="6" class="otp-input" oninput="clearField('otpInput','otpMsg')" onkeydown="if(event.key==='Enter') doVerifyOtp()">
        <div class="field-msg" id="otpMsg"></div>
      </div>
      <button class="submit-btn" id="otpBtn" onclick="doVerifyOtp()">Vérifier le code</button>
      <div style="text-align:center;margin-top:10px;">
        <button class="lnk" onclick="doReset()">Renvoyer le code</button>
      </div>
    </div>
    <div id="resetStep3" style="display:none">
      <p class="reset-desc">Choisissez un nouveau mot de passe.</p>
      <div class="field">
        <input type="password" id="newPwd1" placeholder="Nouveau mot de passe" oninput="clearField('newPwd1','newPwdMsg')">
        <div class="field-msg" id="newPwdMsg"></div>
      </div>
      <div class="field">
        <input type="password" id="newPwd2" placeholder="Confirmer le mot de passe" onkeydown="if(event.key==='Enter') doNewPassword()">
      </div>
      <button class="submit-btn" id="newPwdBtn" onclick="doNewPassword()">Enregistrer</button>
    </div>
    <div style="text-align:center;margin-top:10px;">
      <span class="back-link" onclick="resetSteps();switchTab('login')">&#8592; Retour à la connexion</span>
    </div>
  </div>



<script>
var BASE          = '/Projet_IA/php/';
var resetEmailVal = '';
var resetOtpVal   = '';

/* ── URL PARAMS ── */
/*window.addEventListener('load', function() {
  var params = new URLSearchParams(window.location.search);
  if (params.get('google_login') === 'success') {
    onLoginSuccess({ nom: params.get('nom'), email: params.get('email'), role: params.get('role') });
    window.history.replaceState({}, '', window.location.pathname);
  }
  if (params.get('error')) {
    openAuthDropdown();
    showGlobal('error', 'Erreur lors de la connexion Google.');
  }
});
*/

function post(url, data) {
  return fetch(BASE + url, { method: 'POST', body: new URLSearchParams(data) }).then(function(r) { return r.json(); });
}

/* ── OPEN AUTH DROPDOWN ── */
function openAuthDropdown() {
  var d = document.getElementById('authDropdown');
  d.style.display = d.style.display === 'block' ? 'none' : 'block';
}

/* Fermer au clic dehors */
window.addEventListener('click', function(e) {
  var d  = document.getElementById('authDropdown');
  var btn = document.getElementById('btnLoginHeader');
  if (!d.contains(e.target) && e.target !== btn) d.style.display = 'none';
});

/* ── SWITCH TAB ── */
function switchTab(tab) {
  ['loginForm','signupForm','resetForm'].forEach(function(id) {
    document.getElementById(id).classList.remove('active');
  });
  document.getElementById('tabLogin').classList.remove('active');
  document.getElementById('tabSignup').classList.remove('active');
  clearGlobal();

  var wave = document.getElementById('dropdownWave');
  var tabs = document.getElementById('tabsRow');
  var tdiv = document.getElementById('tabsDivider');

  if (tab === 'login') {
    document.getElementById('loginForm').classList.add('active');
    document.getElementById('tabLogin').classList.add('active');
    wave.style.display=''; tabs.style.display=''; tdiv.style.display='';
  } else if (tab === 'signup') {
    document.getElementById('signupForm').classList.add('active');
    document.getElementById('tabSignup').classList.add('active');
    wave.style.display=''; tabs.style.display=''; tdiv.style.display='';
  } else if (tab === 'reset') {
    document.getElementById('resetForm').classList.add('active');
    wave.style.display='none'; tabs.style.display='none'; tdiv.style.display='none';
  }
}

/* ── MESSAGES ── */
function showGlobal(type, text) {
  var el = document.getElementById('globalMsg');
  el.className = 'global-msg ' + type; el.textContent = text;
}
function clearGlobal() {
  var el = document.getElementById('globalMsg');
  el.className = 'global-msg'; el.textContent = '';
}
function fieldMsg(inputId, msgId, type, text) {
  document.getElementById(msgId).className   = 'field-msg ' + type;
  document.getElementById(msgId).textContent = text;
  document.getElementById(inputId).className = type === 'error' ? 'err-input' : (type === 'success' ? 'ok-input' : '');
}
function clearField(inputId, msgId) {
  document.getElementById(msgId).className='field-msg'; document.getElementById(msgId).textContent='';
  document.getElementById(inputId).className=''; clearGlobal();
}
function isValidEmail(e) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e); }
function setLoading(btnId, loading, label) {
  var btn = document.getElementById(btnId); btn.disabled=loading; btn.textContent=label;
}

/* ── LOGIN ── */
function doLogin() {
  clearGlobal();
  var email = document.getElementById('loginEmail').value.trim();
  var pwd   = document.getElementById('loginPwd').value;
  var ok=true;
  if (!email) { fieldMsg('loginEmail','loginEmailMsg','error','Email requis'); ok=false; }
  else if (!isValidEmail(email)) { fieldMsg('loginEmail','loginEmailMsg','error','Format invalide'); ok=false; }
  if (!pwd) { fieldMsg('loginPwd','loginPwdMsg','error','Mot de passe requis'); ok=false; }
  if (!ok) return;
  setLoading('loginBtn',true,'Connexion...');
  showGlobal('info','Connexion en cours...');
  post('login.php', { email:email, password:pwd })
    .then(function(data) {
      setLoading('loginBtn',false,'Se connecter');
      if (data.status==='success') onLoginSuccess(data.user);
      else showGlobal('error', data.message||'Email ou mot de passe incorrect.');
    })
    .catch(function() { setLoading('loginBtn',false,'Se connecter'); showGlobal('error','Erreur réseau.'); });
}

/* ── SIGNUP ── */
function doSignup() {
  clearGlobal();
  var nom=document.getElementById('signupNom').value.trim();
  var email=document.getElementById('signupEmail').value.trim();
  var p1=document.getElementById('signupP1').value;
  var p2=document.getElementById('signupP2').value;
  var ok=true;
  if (!nom) { fieldMsg('signupNom','signupNomMsg','error','Nom requis'); ok=false; }
  if (!email) { fieldMsg('signupEmail','signupEmailMsg','error','Email requis'); ok=false; }
  else if (!isValidEmail(email)) { fieldMsg('signupEmail','signupEmailMsg','error','Format invalide'); ok=false; }
  if (p1.length<6) { fieldMsg('signupP1','strengthMsg','error','Minimum 6 caractères'); ok=false; }
  if (!p2) { fieldMsg('signupP2','signupP2Msg','error','Confirmez le mot de passe'); ok=false; }
  else if (p1!==p2) { fieldMsg('signupP2','signupP2Msg','error','Les mots de passe ne correspondent pas'); ok=false; }
  if (!ok) return;
  setLoading('signupBtn',true,'Création...');
  showGlobal('info','Création du compte...');
  post('register.php', { nom:nom, email:email, password:p1 })
    .then(function(data) {
      setLoading('signupBtn',false,'Créer mon compte');
      if (data.status==='success') { showGlobal('success','Compte créé ! Connectez-vous.'); setTimeout(function(){ switchTab('login'); },2000); }
      else showGlobal('error', data.message||'Une erreur est survenue.');
    })
    .catch(function() { setLoading('signupBtn',false,'Créer mon compte'); showGlobal('error','Erreur réseau.'); });
}

/* ── RESET ── */
function doReset() {
  clearGlobal();
  var email=document.getElementById('resetEmail').value.trim();
  if (!email) { fieldMsg('resetEmail','resetEmailMsg','error','Email requis'); return; }
  if (!isValidEmail(email)) { fieldMsg('resetEmail','resetEmailMsg','error','Format invalide'); return; }
  setLoading('resetBtn',true,'Envoi...');
  post('reset.php',{email:email})
    .then(function(data) {
      setLoading('resetBtn',false,'Envoyer le code');
      if (data.status==='success') {
        resetEmailVal=email; showGlobal('success','Code envoyé !');
        document.getElementById('resetStep1').style.display='none';
        document.getElementById('resetStep2').style.display='block';
      } else showGlobal('error',data.message||'Email introuvable.');
    })
    .catch(function() { setLoading('resetBtn',false,'Envoyer le code'); showGlobal('error','Erreur réseau.'); });
}
function doVerifyOtp() {
  clearGlobal();
  var otp=document.getElementById('otpInput').value.trim();
  if (otp.length!==6) { fieldMsg('otpInput','otpMsg','error','Code à 6 chiffres requis'); return; }
  setLoading('otpBtn',true,'Vérification...');
  post('verify_otp.php',{email:resetEmailVal,otp:otp})
    .then(function(data) {
      setLoading('otpBtn',false,'Vérifier le code');
      if (data.status==='success') {
        resetOtpVal=otp; showGlobal('success','Code valide !');
        document.getElementById('resetStep2').style.display='none';
        document.getElementById('resetStep3').style.display='block';
      } else showGlobal('error',data.message||'Code incorrect.');
    })
    .catch(function() { setLoading('otpBtn',false,'Vérifier le code'); showGlobal('error','Erreur réseau.'); });
}
function doNewPassword() {
  clearGlobal();
  var p1=document.getElementById('newPwd1').value;
  var p2=document.getElementById('newPwd2').value;
  if (p1.length<6) { fieldMsg('newPwd1','newPwdMsg','error','Minimum 6 caractères'); return; }
  if (p1!==p2) { fieldMsg('newPwd1','newPwdMsg','error','Les mots de passe ne correspondent pas'); return; }
  setLoading('newPwdBtn',true,'Enregistrement...');
  post('new_password.php',{email:resetEmailVal,otp:resetOtpVal,password:p1})
    .then(function(data) {
      setLoading('newPwdBtn',false,'Enregistrer');
      if (data.status==='success') { showGlobal('success','Mot de passe mis à jour !'); setTimeout(function(){ resetSteps(); switchTab('login'); },2000); }
      else showGlobal('error',data.message||'Erreur.');
    })
    .catch(function() { setLoading('newPwdBtn',false,'Enregistrer'); showGlobal('error','Erreur réseau.'); });
}
function resetSteps() {
  document.getElementById('resetStep1').style.display='block';
  document.getElementById('resetStep2').style.display='none';
  document.getElementById('resetStep3').style.display='none';
  ['resetEmail','otpInput','newPwd1','newPwd2'].forEach(function(id){ document.getElementById(id).value=''; });
  resetEmailVal=''; resetOtpVal=''; clearGlobal();
}

function onLoginSuccess(user) {

  // cacher popup auth
  document.getElementById('authDropdown').style.display = 'none';

  // cacher bouton connexion
  document.getElementById('btnLoginHeader').style.display = 'none';

  // afficher avatar
  var ah = document.getElementById('avatarHeader');
  ah.style.display = 'block';

  // initiale
  document.getElementById('avatarCircle').textContent =
    user.nom.charAt(0).toUpperCase();

  // nom
  document.getElementById('pdName').textContent = user.nom;

  // email
  document.getElementById('pdEmail').textContent = user.email;

  // redirection selon le rôle
  setTimeout(function () {
    if (user.role === 'admin') {
      window.location.href = '/Projet_IA/php/admin/index.php';
    } else {
      window.location.href = '/Projet_IA/php/dashboard.php';
    }
  }, 500);
}

/* ── LOGOUT ── */
function doLogout() {
  document.getElementById('avatarHeader').style.display  = 'none';
  document.getElementById('btnLoginHeader').style.display = 'block';
  resetSteps();
  switchTab('login');
}

/* ── PROFIL ── */
function goToProfil() {
  window.location.href = '/PRJ_AI/Projet_IA/profil.php';
}

/* ── PASSWORD STRENGTH ── */
function checkStrength() {
  var p=document.getElementById('signupP1').value;
  var fill=document.getElementById('strengthFill');
  var msg=document.getElementById('strengthMsg');
  if (!p.length) { fill.style.width='0'; fill.style.background=''; msg.className='field-msg'; msg.textContent=''; return; }
  var score=0;
  if (p.length>=6) score++;
  if (p.length>=10) score++;
  if (/[A-Z]/.test(p)) score++;
  if (/[0-9]/.test(p)) score++;
  if (/[^A-Za-z0-9]/.test(p)) score++;
  fill.style.width=(score/5*100)+'%';
  if (score<=1) { fill.style.background='#ef4444'; msg.className='field-msg error'; msg.textContent='Trop faible'; }
  else if (score<=3) { fill.style.background='#f59e0b'; msg.className='field-msg info'; msg.textContent='Moyen'; }
  else { fill.style.background='#22c55e'; msg.className='field-msg success'; msg.textContent='Fort'; }
}

/* ── GOOGLE ── */
function loginWithGoogle() {

  // sauver page actuelle
  sessionStorage.setItem('redirect_after_login', window.location.href);

  // login google
  window.location.href = BASE + 'google_auth.php';
}

function closeGooglePopup() { document.getElementById('googleOverlay').classList.remove('show'); }
function selectGoogle(nom, email) {
  closeGooglePopup();
  showGlobal('info','Connexion Google...');
  post('register.php',{nom:nom,email:email,password:'google_'+email})
    .then(function(){ return post('login.php',{email:email,password:'google_'+email}); })
    .then(function(data){
      if (data.status==='success') onLoginSuccess(data.user);
      else showGlobal('error','Erreur Google.');
    })
    .catch(function(){ showGlobal('error','Erreur réseau.'); });
}


function toggleProfileDropdown(e) {
  e.stopPropagation();
  document.getElementById('profileDropdown').classList.toggle('open');
}

// Fermer au clic en dehors
document.addEventListener('click', function (e) {
  var dd = document.getElementById('profileDropdown');
  var av = document.getElementById('avatarCircle');
  if (dd && !dd.contains(e.target) && e.target !== av) {
    dd.classList.remove('open');
  }
});


</script>
</body>
</html>