<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentification</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Syne:wght@700;800&display=swap"
        rel="stylesheet">

    <style>
        /* ════════════════════════════════════════════════════════════════════════════
    VARIABLES COMPATIBLES BENTO GLOW & PALETTE PREMIUM
    ════════════════════════════════════════════════════════════════════════════ */
        :root {
            --auth-bg: rgba(27, 42, 74, 0.95);
            /* Navy Blue Profond */
            --auth-card: #15223F;
            /* Sous-couche bento */
            --auth-border: rgba(255, 255, 255, 0.08);
            --auth-text: #F8FAFC;
            --auth-muted: #94A3B8;
            --auth-active: #F3E5AB;
            /* Butter Yellow */
            --auth-coral: #E11D48;
            /* Accent Électrique */
        }

        [data-theme="light"] {
            --auth-bg: rgba(252, 250, 247, 0.98);
            /* Ivoire clair dépoli */
            --auth-card: #FFFFFF;
            --auth-border: rgba(27, 42, 74, 0.08);
            --auth-text: #1B2A4A;
            /* Navy Blue Principal */
            --auth-muted: #627293;
            --auth-active: #E6D594;
            /* Butter Yellow adapté clair */
            --auth-coral: #E11D48;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "Plus Jakarta Sans", sans-serif;
            background: #0f172a;
            min-height: 100vh;
        }

        /* ── BOUTON SE CONNECTER (NON CONNECTÉ) ── */
        .btn-login-header {
            padding: 10px 24px;
            background: var(--auth-active);
            color: #1B2A4A;
            border: none;
            border-radius: 99px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 4px 15px rgba(243, 229, 171, 0.25);
        }

        .btn-login-header:hover {
            transform: translateY(-1px);
            filter: brightness(1.05);
            box-shadow: 0 6px 20px rgba(243, 229, 171, 0.4);
        }

        /* ── AVATAR (CONNECTÉ) ── */
        /* Ajuste ces valeurs pour qu'elles correspondent à la hauteur de ton header */
        .avatar-header img {
            width: 56px;
            /* Taille idéale pour un header */
            height: 60px;
            border-radius: 50%;
            /* Pour le rendre rond */
            object-fit: cover;
            /* Important : recadre l'image sans la déformer */
            border: 2px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            transition: transform 0.2s;
        }

        .avatar-header img:hover {
            transform: scale(1.05);
        }

        .avatar-circle {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: var(--auth-active);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1B2A4A;
            font-size: 16px;
            font-weight: 800;
            border: 2px solid var(--auth-border);
            cursor: pointer;
            transition: all 0.3s ease;
            user-select: none;
        }

        .avatar-circle:hover {
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(243, 229, 171, 0.3);
        }

        /* ── DROPDOWN PROFIL (CONNECTÉ) ── */
        .profile-dropdown {
            position: absolute;
            top: calc(100% + 14px);
            right: 0;
            width: 230px;
            background: var(--auth-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--auth-border);
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            opacity: 0;
            pointer-events: none;
            transform: translateY(-10px);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            z-index: 300;
        }

        .profile-dropdown.open {
            opacity: 1;
            pointer-events: all;
            transform: translateY(0);
        }

        .pd-name {
            padding: 16px 18px 2px;
            font-size: 14px;
            font-weight: 700;
            color: var(--auth-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pd-email {
            padding: 0 18px 12px;
            font-size: 11.5px;
            color: var(--auth-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pd-divider {
            height: 1px;
            background: var(--auth-border);
        }

        .pd-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 18px;
            font-size: 13px;
            font-weight: 600;
            color: var(--auth-text);
            cursor: pointer;
            transition: background .2s;
            text-decoration: none;
            background: none;
            border: none;
            width: 100%;
            text-align: left;
        }

        .pd-item:hover {
            background: rgba(255, 255, 255, 0.04);
        }

        [data-theme="light"] .pd-item:hover {
            background: rgba(27, 42, 74, 0.03);
        }

        .pd-item svg {
            flex-shrink: 0;
            opacity: .7;
            color: var(--auth-muted);
        }

        .pd-item.danger {
            color: var(--auth-coral);
        }

        .pd-item.danger:hover {
            background: rgba(225, 29, 72, 0.08);
        }

        .pd-item.danger svg {
            opacity: .9;
            color: var(--auth-coral);
        }

        /* ── DROPDOWN POPUP D'AUTHENTIFICATION ── */
        .auth-dropdown {
            position: absolute;
            top: 84px;
            right: 4rem;
            width: 340px;
            background: var(--auth-bg);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid var(--auth-border);
            border-radius: 20px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.35);
            overflow: hidden;
            display: none;
            z-index: 1000;
            animation: bentoFade .3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes bentoFade {
            from {
                opacity: 0;
                transform: translateY(-12px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* En-tête stylisé à la place des anciennes vagues complexes */
        .dropdown-premium-header {
            position: relative;
            height: 60px;
            background: linear-gradient(135deg, rgba(243, 229, 171, 0.1), rgba(225, 29, 72, 0.05));
            border-bottom: 1px solid var(--auth-border);
        }

        .dropdown-premium-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--auth-active), transparent);
        }

        .tabs {
            display: flex;
            padding: 6px;
            gap: 4px;
            background: rgba(0, 0, 0, 0.15);
            margin: 12px 16px 4px;
            border-radius: 12px;
        }

        [data-theme="light"] .tabs {
            background: rgba(27, 42, 74, 0.04);
        }

        .tab {
            flex: 1;
            padding: 10px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 13px;
            font-weight: 700;
            color: var(--auth-muted);
            border-radius: 8px;
            transition: all .2s ease;
        }

        .tab.active {
            background: var(--auth-card);
            color: var(--auth-text);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .global-msg {
            margin: 12px 16px 0;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            display: none;
        }

        .global-msg.success {
            background: rgba(34, 197, 94, 0.15);
            color: #4ade80;
            border: 1px solid rgba(34, 197, 94, 0.2);
            display: block;
        }

        .global-msg.error {
            background: rgba(225, 29, 72, 0.15);
            color: #f87171;
            border: 1px solid rgba(225, 29, 72, 0.2);
            display: block;
        }

        .global-msg.info {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.2);
            display: block;
        }

        .form-body {
            padding: 16px;
            display: none;
        }

        .form-body.active {
            display: block;
        }

        /* BOUTON GOOGLE ULTRA MODERNE */
        .google-btn {
            width: 100%;
            padding: 11px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border: 1px solid var(--auth-border);
            border-radius: 12px;
            background: var(--auth-card);
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            color: var(--auth-text);
            transition: all .2s;
        }

        .google-btn:hover {
            background: rgba(255, 255, 255, 0.02);
            border-color: var(--auth-muted);
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--auth-border);
        }

        .divider span {
            font-size: 11px;
            color: var(--auth-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* FORM FIELDS */
        .field {
            margin-bottom: 12px;
        }

        .field input {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid var(--auth-border);
            border-radius: 12px;
            background: var(--auth-card);
            color: var(--auth-text);
            font-size: 13.5px;
            outline: none;
            transition: all .2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .field input:focus {
            border-color: var(--auth-active);
            box-shadow: 0 0 0 3px rgba(243, 229, 171, 0.15);
        }

        .field input.err-input {
            border-color: var(--auth-coral);
        }

        .field input.ok-input {
            border-color: #22c55e;
        }

        .field-msg {
            font-size: 11px;
            margin-top: 5px;
            padding-left: 2px;
            display: none;
        }

        .field-msg.error {
            color: #f87171;
            display: block;
        }

        .field-msg.success {
            color: #4ade80;
            display: block;
        }

        .strength-bar {
            height: 4px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            margin-top: 6px;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            width: 0;
            transition: all .3s ease;
        }

        .otp-input {
            text-align: center;
            font-size: 24px !important;
            letter-spacing: 8px !important;
            font-weight: 700 !important;
            color: var(--auth-active) !important;
        }

        .submit-btn {
            width: 100%;
            padding: 12px;
            background: var(--auth-active);
            color: #1B2A4A;
            border: none;
            border-radius: 12px;
            font-size: 13.5px;
            font-weight: 700;
            cursor: pointer;
            transition: all .2s;
            margin-top: 6px;
            box-shadow: 0 4px 12px rgba(243, 229, 171, 0.2);
        }

        .submit-btn:hover {
            filter: brightness(1.06);
            transform: translateY(-1px);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .submit-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            box-shadow: none;
        }

        .links-row {
            display: flex;
            justify-content: space-between;
            margin-top: 14px;
        }

        .lnk {
            font-size: 12px;
            color: var(--auth-muted);
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 500;
        }

        .lnk:hover {
            color: var(--auth-active);
            text-decoration: underline;
        }

        .reset-desc {
            font-size: 12.5px;
            color: var(--auth-muted);
            line-height: 1.5;
            margin-bottom: 14px;
        }

        .back-link {
            display: inline-block;
            margin-top: 12px;
            font-size: 12px;
            color: var(--auth-muted);
            cursor: pointer;
        }

        .back-link:hover {
            color: var(--auth-text);
        }

        /* MICRO REDIRECTION POPUP GOOGLE OVERLAY */
        .google-overlay {
            position: fixed;
            inset: 0;
            background: rgba(6, 11, 25, 0.6);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .google-overlay.show {
            display: flex;
        }

        .google-popup {
            background: var(--auth-bg);
            border: 1px solid var(--auth-border);
            border-radius: 20px;
            padding: 24px;
            width: 330px;
            text-align: center;
        }

        .google-popup h3 {
            font-size: 16px;
            color: var(--auth-text);
            margin-bottom: 6px;
        }

        .google-popup p {
            font-size: 13px;
            color: var(--auth-muted);
            margin-bottom: 20px;
        }

        .google-accounts {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .google-account {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            border: 1px solid var(--auth-border);
            border-radius: 12px;
            background: var(--auth-card);
            cursor: pointer;
            text-align: left;
            transition: transform 0.2s;
        }

        .google-account:hover {
            transform: translateY(-1px);
            border-color: var(--auth-active);
        }

        @media (max-width: 768px) {
            .auth-dropdown {
                right: 1.5rem;
                width: calc(100% - 3rem);
            }
        }
    </style>
</head>

<body>

    <?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }



    // Maintenant, on définit la variable pour l'affichage
// Remplacez '../uploads/avatars/' par le chemin absolu à partir de la racine
    $isLoggedIn = isset($_SESSION['user_id']);
    $userName = $isLoggedIn ? $_SESSION['nom'] : '';
    $userEmail = $isLoggedIn ? $_SESSION['email'] : '';
    $userRole = $isLoggedIn ? $_SESSION['role'] : '';
    $initial = $isLoggedIn ? strtoupper(mb_substr($userName, 0, 1)) : '';

    $imagePath = !empty($_SESSION['image']) ? "/Projet_IA/php/uploads/avatars/" . htmlspecialchars($_SESSION['image']) : '';

    // On vérifie si le fichier existe réellement sur le serveur pour éviter l'icône "image cassée"
    $fullServerPath = $_SERVER['DOCUMENT_ROOT'] . "/Projet_IA/php/uploads/avatars/" . ($_SESSION['image'] ?? '');
    $imageExists = !empty($_SESSION['image']) && file_exists($fullServerPath);
    ?>

    <button class="btn-login-header" id="btnLoginHeader" onclick="openAuthDropdown()"
        style="<?= $isLoggedIn ? 'display:none;' : '' ?>">
        Se connecter
    </button>
    <div class="avatar-header" id="avatarHeader">
        <?php if ($isLoggedIn): ?>
            <div id="avatarCircle" onclick="toggleProfileDropdown(event)" style="cursor: pointer;">

                <?php if ($imageExists): ?>
                    <img src="<?= $imagePath ?>" alt="Profil"
                        style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--auth-active);">
                <?php else: ?>
                    <div class="avatar-circle">
                        <?= htmlspecialchars($initial) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>


    </div>

    <div class="profile-dropdown" id="profileDropdown">
        <div class="pd-name" id="pdName"><?= htmlspecialchars($userName) ?></div>
        <div class="pd-email" id="pdEmail"><?= htmlspecialchars($userEmail) ?></div>
        <div class="pd-divider"></div>

        <a class="pd-item"
            href="<?= $userRole === 'admin' ? '/Projet_IA/php/admin/index.php' : '/Projet_IA/php/user/dashboard.php' ?>">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="3" y="3" width="7" height="7" rx="1" />
                <rect x="14" y="3" width="7" height="7" rx="1" />
                <rect x="3" y="14" width="7" height="7" rx="1" />
                <rect x="14" y="14" width="7" height="7" rx="1" />
            </svg>
            Dashboard
        </a>

        <a class="pd-item" href="/Projet_IA/php/user/profil.php">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="8" r="4" />
                <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" />
            </svg>
            Mon Profil
        </a>

        <a class="pd-item"
            href="<?= $userRole === 'admin' ? '/Projet_IA/php/admin/notifications.php' : '/Projet_IA/php/user/notifications.php' ?>">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
            </svg>
            Notifications
            <span id="notifBadge"
                style="margin-left:auto;background:#e11d48;color:#fff;font-size:10px;font-weight:700;padding:2px 6px;border-radius:99px;display:none">0</span>
        </a>

        <div class="pd-divider"></div>

        <a class="pd-item danger" href="/Projet_IA/php/logout.php">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                <polyline points="16 17 21 12 16 7" />
                <line x1="21" y1="12" x2="9" y2="12" />
            </svg>
            Déconnexion
        </a>
    </div>
    </div>

    <div class="auth-dropdown" id="authDropdown">

        <div class="dropdown-premium-header" id="dropdownWave"></div>

        <div class="tabs" id="tabsRow">
            <button class="tab active" id="tabLogin" onclick="switchTab('login')">Connexion</button>
            <button class="tab" id="tabSignup" onclick="switchTab('signup')">Inscription</button>
        </div>

        <div id="globalMsg" class="global-msg"></div>

        <div class="form-body active" id="loginForm">
            <button class="google-btn" onclick="loginWithGoogle()">
                <img src="https://developers.google.com/identity/images/g-logo.png" width="16" alt="Google">
                Continuer avec Google
            </button>
            <div class="divider"><span>ou</span></div>

            <div class="field">
                <input type="email" id="loginEmail" placeholder="Adresse email"
                    oninput="clearField('loginEmail','loginEmailMsg')">
                <div class="field-msg" id="loginEmailMsg"></div>
            </div>
            <div class="field">
                <input type="password" id="loginPwd" placeholder="Mot de passe"
                    oninput="clearField('loginPwd','loginPwdMsg')" onkeydown="if(event.key==='Enter') doLogin()">
                <div class="field-msg" id="loginPwdMsg"></div>
            </div>

            <button class="submit-btn" id="loginBtn" onclick="doLogin()">Se connecter</button>

            <div class="links-row">
                <button class="lnk" onclick="switchTab('reset')">Mot de passe oublié ?</button>
                <button class="lnk" onclick="switchTab('signup')">Créer un compte</button>
            </div>
        </div>

        <div class="form-body" id="signupForm">
            <div class="field">
                <input type="text" id="signupNom" placeholder="Nom complet"
                    oninput="clearField('signupNom','signupNomMsg')">
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

        <div class="form-body" id="resetForm">
            <div id="resetStep1">
                <p class="reset-desc">Entrez votre email pour recevoir un code de vérification.</p>
                <div class="field">
                    <input type="email" id="resetEmail" placeholder="Adresse email"
                        oninput="clearField('resetEmail','resetEmailMsg')"
                        onkeydown="if(event.key==='Enter') doReset()">
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
                <span class="back-link" onclick="resetSteps();switchTab('login')">&#8594; Retour à la connexion</span>
            </div>
        </div>

    </div>

    <script>
        var BASE = '/Projet_IA/php/';
        var resetEmailVal = '';
        var resetOtpVal = '';

        function post(url, data) {
            return fetch(BASE + url, { method: 'POST', body: new URLSearchParams(data) }).then(function (r) { return r.json(); });
        }

        function openAuthDropdown() {
            var d = document.getElementById('authDropdown');
            d.style.display = d.style.display === 'block' ? 'none' : 'block';
        }

        /* Fermer proprement au clic extérieur */
        window.addEventListener('click', function (e) {
            var d = document.getElementById('authDropdown');
            var btn = document.getElementById('btnLoginHeader');
            if (d && !d.contains(e.target) && e.target !== btn) d.style.display = 'none';
        });

        function switchTab(tab) {
            ['loginForm', 'signupForm', 'resetForm'].forEach(function (id) {
                document.getElementById(id).classList.remove('active');
            });
            document.getElementById('tabLogin').classList.remove('active');
            document.getElementById('tabSignup').classList.remove('active');
            clearGlobal();

            var wave = document.getElementById('dropdownWave');
            var tabs = document.getElementById('tabsRow');

            if (tab === 'login') {
                document.getElementById('loginForm').classList.add('active');
                document.getElementById('tabLogin').classList.add('active');
                wave.style.display = ''; tabs.style.display = '';
            } else if (tab === 'signup') {
                document.getElementById('signupForm').classList.add('active');
                document.getElementById('tabSignup').classList.add('active');
                wave.style.display = ''; tabs.style.display = '';
            } else if (tab === 'reset') {
                document.getElementById('resetForm').classList.add('active');
                wave.style.display = 'none'; tabs.style.display = 'none';
            }
        }

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

        /* ── INTERACTION SCRIPTS (LOGIN & REGISTER) ── */
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

        /* ── PROCESSUS MOT DE PASSE OUBLIÉ ── */
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
            document.getElementById('authDropdown').style.display = 'none';
            document.getElementById('btnLoginHeader').style.display = 'none';

            var ah = document.getElementById('avatarHeader');
            if (ah) ah.style.display = 'block';

            var avCircle = document.getElementById('avatarCircle');
            if (avCircle) avCircle.textContent = user.nom.charAt(0).toUpperCase();

            document.getElementById('pdName').textContent = user.nom;
            document.getElementById('pdEmail').textContent = user.email;

            setTimeout(function () {
                if (user.role === 'admin') {
                    window.location.href = '/Projet_IA/php/admin/index.php';
                } else {
                    window.location.href = '/Projet_IA/php/dashboard.php';
                }
            }, 500);
        }

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

        function loginWithGoogle() {
            sessionStorage.setItem('redirect_after_login', window.location.href);
            window.location.href = BASE + 'google_auth.php';
        }

        function toggleProfileDropdown(e) {
            e.stopPropagation();
            document.getElementById('profileDropdown').classList.toggle('open');
        }

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