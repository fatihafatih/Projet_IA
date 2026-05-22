<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Authentification</title>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../styles/auth.css">

</head>

<body>
  <?php
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
  $isLoggedIn = isset($_SESSION['user_id']);

  $userName = $isLoggedIn ? $_SESSION['nom'] : '';
  $userEmail = $isLoggedIn ? $_SESSION['email'] : '';
  $initial = $isLoggedIn ? $_SESSION['image'] : '';
  ?>

  <!-- Bouton connexion -->
  <button class="btn-login-header" id="btnLoginHeader" onclick="openAuthDropdown()"
    style="<?= $isLoggedIn ? 'display:none;' : '' ?>">
    Se connecter
  </button>

  <!-- Avatar profil -->
  <div class="avatar-header" id="avatarHeader" style="<?= $isLoggedIn ? 'display:block;' : 'display:none;' ?>">
    <div class="avatar-circle" id="avatarCircle" onclick="toggleProfileDropdown(event)">
      <?php if ($initial): ?>
        <img src="<?= htmlspecialchars($initial) ?>" alt="avatar"
          style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
      <?php else: ?>
        <?= strtoupper(substr($userName, 0, 1)) ?: '?' ?>
      <?php endif; ?>
    </div>

    <div class="profile-dropdown" id="profileDropdown">

      <div class="pd-name" id="pdName">
        <?= htmlspecialchars($userName) ?>
      </div>

      <div class="pd-email" id="pdEmail">
        <?= htmlspecialchars($userEmail) ?>
      </div>

      <div class="pd-divider"></div>

      <a class="pd-item" href="../php/profil.php">
        Profil
      </a>

      <a class="pd-item danger" href="../php/logout.php">
        Déconnexion
      </a>

    </div>
  </div>

  <!-- DROPDOWN AUTH -->
  <div class="auth-dropdown" id="authDropdown">

    <div class="dropdown-wave" id="dropdownWave">
      <svg viewBox="0 0 330 90" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
        <rect width="330" height="90" fill="#FFF8DC" />
        <path d="M0,90 L0,30 Q60,70 130,45 Q200,20 260,50 Q300,65 330,40 L330,90 Z" fill="#FFE57A" opacity="0.6" />
        <path d="M0,0 L180,0 Q210,5 200,35 Q190,65 140,72 Q90,80 40,60 Q10,48 0,55 Z" fill="#F5B800" />
        <path d="M0,0 L120,0 Q145,4 138,28 Q130,52 90,58 Q50,64 20,50 Q0,42 0,35 Z" fill="#F5B800" opacity="0.7" />
        <path d="M330,90 L330,55 Q305,45 285,60 Q265,75 270,90 Z" fill="#F5B800" opacity="0.8" />
        <path d="M330,90 L330,70 Q318,62 308,72 Q298,82 305,90 Z" fill="#F5B800" />
      </svg>
    </div>

    <div class="tabs" id="tabsRow">
      <button class="tab active" id="tabLogin" onclick="switchTab('login')">Connexion</button>
      <button class="tab" id="tabSignup" onclick="switchTab('signup')">Inscription</button>
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
        <input type="email" id="loginEmail" placeholder="Adresse email"
          oninput="clearField('loginEmail','loginEmailMsg')">
        <div class="field-msg" id="loginEmailMsg"></div>
      </div>
      <div class="field">
        <input type="password" id="loginPwd" placeholder="Mot de passe" oninput="clearField('loginPwd','loginPwdMsg')"
          onkeydown="if(event.key==='Enter') doLogin()">
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
        <input type="email" id="signupEmail" placeholder="Adresse email"
          oninput="clearField('signupEmail','signupEmailMsg')">
        <div class="field-msg" id="signupEmailMsg"></div>
      </div>
      <div class="field">
        <input type="password" id="signupP1" placeholder="Mot de passe" oninput="checkStrength()">
        <div class="strength-bar">
          <div class="strength-fill" id="strengthFill"></div>
        </div>
        <div class="field-msg" id="strengthMsg"></div>
      </div>
      <div class="field">
        <input type="password" id="signupP2" placeholder="Confirmer le mot de passe"
          oninput="clearField('signupP2','signupP2Msg')" onkeydown="if(event.key==='Enter') doSignup()">
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
          <input type="email" id="resetEmail" placeholder="Adresse email"
            oninput="clearField('resetEmail','resetEmailMsg')" onkeydown="if(event.key==='Enter') doReset()">
          <div class="field-msg" id="resetEmailMsg"></div>
        </div>
        <button class="submit-btn" id="resetBtn" onclick="doReset()">Envoyer le code</button>
      </div>
      <div id="resetStep2" style="display:none">
        <p class="reset-desc">Entrez le code à 6 chiffres envoyé à votre email.</p>
        <div class="field">
          <input type="text" id="otpInput" placeholder="000000" maxlength="6" class="otp-input"
            oninput="clearField('otpInput','otpMsg')" onkeydown="if(event.key==='Enter') doVerifyOtp()">
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
          <input type="password" id="newPwd1" placeholder="Nouveau mot de passe"
            oninput="clearField('newPwd1','newPwdMsg')">
          <div class="field-msg" id="newPwdMsg"></div>
        </div>
        <div class="field">
          <input type="password" id="newPwd2" placeholder="Confirmer le mot de passe"
            onkeydown="if(event.key==='Enter') doNewPassword()">
        </div>
        <button class="submit-btn" id="newPwdBtn" onclick="doNewPassword()">Enregistrer</button>
      </div>
      <div style="text-align:center;margin-top:10px;">
        <span class="back-link" onclick="resetSteps();switchTab('login')">&#8592; Retour à la connexion</span>
      </div>
    </div>



    <script>
      var BASE = '/Projet_IA/php/';
      var resetEmailVal = '';
      var resetOtpVal = '';

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
        return fetch(BASE + url, { method: 'POST', body: new URLSearchParams(data) }).then(function (r) { return r.json(); });
      }

      /* ── OPEN AUTH DROPDOWN ── */
      function openAuthDropdown() {
        var d = document.getElementById('authDropdown');
        d.style.display = d.style.display === 'block' ? 'none' : 'block';
      }

      /* Fermer au clic dehors */
      window.addEventListener('click', function (e) {
        var d = document.getElementById('authDropdown');
        var btn = document.getElementById('btnLoginHeader');
        if (!d.contains(e.target) && e.target !== btn) d.style.display = 'none';
      });

      /* ── SWITCH TAB ── */
      function switchTab(tab) {
        ['loginForm', 'signupForm', 'resetForm'].forEach(function (id) {
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
          wave.style.display = ''; tabs.style.display = ''; tdiv.style.display = '';
        } else if (tab === 'signup') {
          document.getElementById('signupForm').classList.add('active');
          document.getElementById('tabSignup').classList.add('active');
          wave.style.display = ''; tabs.style.display = ''; tdiv.style.display = '';
        } else if (tab === 'reset') {
          document.getElementById('resetForm').classList.add('active');
          wave.style.display = 'none'; tabs.style.display = 'none'; tdiv.style.display = 'none';
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
        document.getElementById(msgId).className = 'field-msg ' + type;
        document.getElementById(msgId).textContent = text;
        document.getElementById(inputId).className = type === 'error' ? 'err-input' : (type === 'success' ? 'ok-input' : '');
      }
      function clearField(inputId, msgId) {
        document.getElementById(msgId).className = 'field-msg'; document.getElementById(msgId).textContent = '';
        document.getElementById(inputId).className = ''; clearGlobal();
      }
      function isValidEmail(e) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e); }
      function setLoading(btnId, loading, label) {
        var btn = document.getElementById(btnId); btn.disabled = loading; btn.textContent = label;
      }

      /* ── LOGIN ── */
      function doLogin() {
        clearGlobal();
        var email = document.getElementById('loginEmail').value.trim();
        var pwd = document.getElementById('loginPwd').value;
        var ok = true;
        if (!email) { fieldMsg('loginEmail', 'loginEmailMsg', 'error', 'Email requis'); ok = false; }
        else if (!isValidEmail(email)) { fieldMsg('loginEmail', 'loginEmailMsg', 'error', 'Format invalide'); ok = false; }
        if (!pwd) { fieldMsg('loginPwd', 'loginPwdMsg', 'error', 'Mot de passe requis'); ok = false; }
        if (!ok) return;
        setLoading('loginBtn', true, 'Connexion...');
        showGlobal('info', 'Connexion en cours...');
        post('login.php', { email: email, password: pwd })
          .then(function (data) {
            setLoading('loginBtn', false, 'Se connecter');
            if (data.status === 'success') onLoginSuccess(data.user);
            else showGlobal('error', data.message || 'Email ou mot de passe incorrect.');
          })
          .catch(function () { setLoading('loginBtn', false, 'Se connecter'); showGlobal('error', 'Erreur réseau.'); });
      }

      /* ── SIGNUP ── */
      function doSignup() {
        clearGlobal();
        var nom = document.getElementById('signupNom').value.trim();
        var email = document.getElementById('signupEmail').value.trim();
        var p1 = document.getElementById('signupP1').value;
        var p2 = document.getElementById('signupP2').value;
        var ok = true;
        if (!nom) { fieldMsg('signupNom', 'signupNomMsg', 'error', 'Nom requis'); ok = false; }
        if (!email) { fieldMsg('signupEmail', 'signupEmailMsg', 'error', 'Email requis'); ok = false; }
        else if (!isValidEmail(email)) { fieldMsg('signupEmail', 'signupEmailMsg', 'error', 'Format invalide'); ok = false; }
        if (p1.length < 6) { fieldMsg('signupP1', 'strengthMsg', 'error', 'Minimum 6 caractères'); ok = false; }
        if (!p2) { fieldMsg('signupP2', 'signupP2Msg', 'error', 'Confirmez le mot de passe'); ok = false; }
        else if (p1 !== p2) { fieldMsg('signupP2', 'signupP2Msg', 'error', 'Les mots de passe ne correspondent pas'); ok = false; }
        if (!ok) return;
        setLoading('signupBtn', true, 'Création...');
        showGlobal('info', 'Création du compte...');
        post('register.php', { nom: nom, email: email, password: p1 })
          .then(function (data) {
            setLoading('signupBtn', false, 'Créer mon compte');
            if (data.status === 'success') { showGlobal('success', 'Compte créé ! Connectez-vous.'); setTimeout(function () { switchTab('login'); }, 2000); }
            else showGlobal('error', data.message || 'Une erreur est survenue.');
          })
          .catch(function () { setLoading('signupBtn', false, 'Créer mon compte'); showGlobal('error', 'Erreur réseau.'); });
      }

      /* ── RESET ── */
      function doReset() {
        clearGlobal();
        var email = document.getElementById('resetEmail').value.trim();
        if (!email) { fieldMsg('resetEmail', 'resetEmailMsg', 'error', 'Email requis'); return; }
        if (!isValidEmail(email)) { fieldMsg('resetEmail', 'resetEmailMsg', 'error', 'Format invalide'); return; }
        setLoading('resetBtn', true, 'Envoi...');
        post('reset.php', { email: email })
          .then(function (data) {
            setLoading('resetBtn', false, 'Envoyer le code');
            if (data.status === 'success') {
              resetEmailVal = email; showGlobal('success', 'Code envoyé !');
              document.getElementById('resetStep1').style.display = 'none';
              document.getElementById('resetStep2').style.display = 'block';
            } else showGlobal('error', data.message || 'Email introuvable.');
          })
          .catch(function () { setLoading('resetBtn', false, 'Envoyer le code'); showGlobal('error', 'Erreur réseau.'); });
      }
      function doVerifyOtp() {
        clearGlobal();
        var otp = document.getElementById('otpInput').value.trim();
        if (otp.length !== 6) { fieldMsg('otpInput', 'otpMsg', 'error', 'Code à 6 chiffres requis'); return; }
        setLoading('otpBtn', true, 'Vérification...');
        post('verify_otp.php', { email: resetEmailVal, otp: otp })
          .then(function (data) {
            setLoading('otpBtn', false, 'Vérifier le code');
            if (data.status === 'success') {
              resetOtpVal = otp; showGlobal('success', 'Code valide !');
              document.getElementById('resetStep2').style.display = 'none';
              document.getElementById('resetStep3').style.display = 'block';
            } else showGlobal('error', data.message || 'Code incorrect.');
          })
          .catch(function () { setLoading('otpBtn', false, 'Vérifier le code'); showGlobal('error', 'Erreur réseau.'); });
      }
      function doNewPassword() {
        clearGlobal();
        var p1 = document.getElementById('newPwd1').value;
        var p2 = document.getElementById('newPwd2').value;
        if (p1.length < 6) { fieldMsg('newPwd1', 'newPwdMsg', 'error', 'Minimum 6 caractères'); return; }
        if (p1 !== p2) { fieldMsg('newPwd1', 'newPwdMsg', 'error', 'Les mots de passe ne correspondent pas'); return; }
        setLoading('newPwdBtn', true, 'Enregistrement...');
        post('new_password.php', { email: resetEmailVal, otp: resetOtpVal, password: p1 })
          .then(function (data) {
            setLoading('newPwdBtn', false, 'Enregistrer');
            if (data.status === 'success') { showGlobal('success', 'Mot de passe mis à jour !'); setTimeout(function () { resetSteps(); switchTab('login'); }, 2000); }
            else showGlobal('error', data.message || 'Erreur.');
          })
          .catch(function () { setLoading('newPwdBtn', false, 'Enregistrer'); showGlobal('error', 'Erreur réseau.'); });
      }
      function resetSteps() {
        document.getElementById('resetStep1').style.display = 'block';
        document.getElementById('resetStep2').style.display = 'none';
        document.getElementById('resetStep3').style.display = 'none';
        ['resetEmail', 'otpInput', 'newPwd1', 'newPwd2'].forEach(function (id) { document.getElementById(id).value = ''; });
        resetEmailVal = ''; resetOtpVal = ''; clearGlobal();
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
        var avatarCircle = document.getElementById('avatarCircle');
        if (user.image) {
          avatarCircle.innerHTML = '<img src="' + user.image + '" alt="avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">';
        } else {
          avatarCircle.textContent = user.nom.charAt(0).toUpperCase();
        }

        // nom
        document.getElementById('pdName').textContent = user.nom;

        // email
        document.getElementById('pdEmail').textContent = user.email;

        // redirection
        setTimeout(function () {
          window.location.href = '/Projet_IA/php/dashboard.php';
        }, 500);
      }

      /* ── LOGOUT ── */
      function doLogout() {
        document.getElementById('avatarHeader').style.display = 'none';
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
        var p = document.getElementById('signupP1').value;
        var fill = document.getElementById('strengthFill');
        var msg = document.getElementById('strengthMsg');
        if (!p.length) { fill.style.width = '0'; fill.style.background = ''; msg.className = 'field-msg'; msg.textContent = ''; return; }
        var score = 0;
        if (p.length >= 6) score++;
        if (p.length >= 10) score++;
        if (/[A-Z]/.test(p)) score++;
        if (/[0-9]/.test(p)) score++;
        if (/[^A-Za-z0-9]/.test(p)) score++;
        fill.style.width = (score / 5 * 100) + '%';
        if (score <= 1) { fill.style.background = '#ef4444'; msg.className = 'field-msg error'; msg.textContent = 'Trop faible'; }
        else if (score <= 3) { fill.style.background = '#f59e0b'; msg.className = 'field-msg info'; msg.textContent = 'Moyen'; }
        else { fill.style.background = '#22c55e'; msg.className = 'field-msg success'; msg.textContent = 'Fort'; }
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
        showGlobal('info', 'Connexion Google...');
        post('register.php', { nom: nom, email: email, password: 'google_' + email })
          .then(function () { return post('login.php', { email: email, password: 'google_' + email }); })
          .then(function (data) {
            if (data.status === 'success') onLoginSuccess(data.user);
            else showGlobal('error', 'Erreur Google.');
          })
          .catch(function () { showGlobal('error', 'Erreur réseau.'); });
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