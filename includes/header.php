<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Détection de la page active
$current_page = basename($_SERVER['PHP_SELF']);
$current_path = $_SERVER['REQUEST_URI'];

function isActive(string $page): string {
    global $current_page, $current_path;
    return (str_contains($current_path, $page)) ? 'nav-active' : '';
}
?>

<style>
    /* ════════════════════════════════════════════════════════════════════
    NAVBAR — BENTO GLOW ÉLECTRIQUE  (version multi-liens)
    ════════════════════════════════════════════════════════════════════ */

    :root {
        --neon-gold:   #F3E5AB;
        --neon-coral:  #E11D48;
        --glass-bg:    rgba(27, 42, 74, 0.92);
        --glass-border:rgba(255, 255, 255, 0.08);
        --text-pure:   #F8FAFC;
        --text-muted:  #94A3B8;
        --navy:        #1B2A4A;
    }

    /* ── Structure principale ─────────────────────────────────────── */
    .navbar-main {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 3.5rem;
        height: 78px;
        background: var(--glass-bg) !important;
        box-shadow: 0 20px 40px rgba(11, 19, 43, 0.25) !important;
        backdrop-filter: blur(24px) saturate(200%);
        -webkit-backdrop-filter: blur(24px) saturate(200%);
        position: sticky;
        top: 0;
        z-index: 1000;
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        border-bottom: 1px solid var(--glass-border);
    }

    /* Halo lumineux */
    .navbar-main::before {
        content: '';
        position: absolute;
        top: -20px; left: 10%; right: 10%;
        height: 40px;
        background: linear-gradient(90deg, transparent, var(--neon-gold), var(--neon-coral), transparent);
        filter: blur(40px);
        opacity: 0.15;
        pointer-events: none;
        z-index: -1;
        transition: opacity 0.4s ease;
    }
    .navbar-main:hover::before { opacity: 0.28; }

    /* ── Logo ─────────────────────────────────────────────────────── */
    .logo-link {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        flex-shrink: 0;
    }
    .logo-link img {
        width: 42px; height: 42px;
        object-fit: contain;
        transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .logo-link:hover img {
        transform: scale(1.12) rotate(-6deg);
        filter: drop-shadow(0 0 12px var(--neon-gold));
    }
    .logo-name {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 21px; font-weight: 800;
        letter-spacing: -0.03em;
        background: linear-gradient(180deg, var(--text-pure) 30%, var(--text-muted));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .logo-name span {
        font-weight: 900;
        background: linear-gradient(135deg, var(--neon-gold), var(--neon-coral));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    /* ── Pill central — multi-liens ──────────────────────────────── */
    .nav-center {
        display: flex;
        align-items: center;
        gap: 2px;
        padding: 4px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--glass-border);
        border-radius: 99px;
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.12);
    }

    /* Lien neutre */
    .nav-center .nav-link {
        font-size: 12.5px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        text-decoration: none;
        padding: 9px 20px;
        border-radius: 99px;
        color: rgba(248, 250, 252, 0.72) !important;
        transition: all 0.25s ease;
        white-space: nowrap;
        position: relative;
    }
    .nav-center .nav-link:hover {
        color: #fff !important;
        background: rgba(255, 255, 255, 0.08);
    }

    /* Lien actif — dégradé doré/corail (même style qu'avant) */
    .nav-center .nav-link.nav-active,
    .nav-center .nav-link.nav-highlight {
        color: var(--navy) !important;
        background: linear-gradient(135deg, var(--neon-gold) 0%, #FBBF24 50%, var(--neon-coral) 100%);
        background-size: 200% auto;
        box-shadow: 0 4px 18px rgba(243, 229, 171, 0.35);
    }
    .nav-center .nav-link.nav-active:hover,
    .nav-center .nav-link.nav-highlight:hover {
        transform: translateY(-2px);
        background-position: right center;
        box-shadow: 0 8px 25px rgba(225, 29, 72, 0.55);
    }

    /* Séparateur discret entre groupes */
    .nav-sep {
        width: 1px; height: 20px;
        background: rgba(255, 255, 255, 0.10);
        margin: 0 4px;
        border-radius: 1px;
        flex-shrink: 0;
    }

    /* Badge "New" sur un lien */
    .nav-badge-new {
        display: inline-block;
        font-size: 9px; font-weight: 800;
        letter-spacing: .06em;
        background: var(--neon-coral);
        color: #fff;
        padding: 1px 5px;
        border-radius: 99px;
        margin-left: 5px;
        vertical-align: middle;
        text-transform: uppercase;
    }

    /* ── Zone droite ─────────────────────────────────────────────── */
    .navbar-right {
        display: flex;
        align-items: center;
        flex-shrink: 0;
    }

    /* ── Hamburger (mobile) ──────────────────────────────────────── */
    .nav-hamburger {
        display: none;
        flex-direction: column;
        gap: 5px;
        background: none;
        border: none;
        cursor: pointer;
        padding: 8px;
        border-radius: 10px;
        transition: background .2s;
    }
    .nav-hamburger span {
        display: block;
        width: 22px; height: 2px;
        background: var(--text-pure);
        border-radius: 2px;
        transition: all .3s ease;
    }
    .nav-hamburger:hover { background: rgba(255,255,255,.08); }

    /* Menu mobile déroulant */
    .nav-mobile-menu {
        display: none;
        flex-direction: column;
        gap: 4px;
        position: absolute;
        top: 78px; left: 0; right: 0;
        background: rgba(18, 28, 55, 0.97);
        backdrop-filter: blur(20px);
        border-bottom: 1px solid var(--glass-border);
        padding: 16px 2rem 20px;
        z-index: 999;
    }
    .nav-mobile-menu.open { display: flex; }

    .nav-mobile-menu .nav-link {
        font-size: 14px; font-weight: 600;
        letter-spacing: .04em; text-transform: uppercase;
        text-decoration: none;
        color: rgba(248,250,252,.75) !important;
        padding: 12px 18px;
        border-radius: 12px;
        transition: background .2s, color .2s;
    }
    .nav-mobile-menu .nav-link:hover,
    .nav-mobile-menu .nav-link.nav-active {
        background: rgba(255,255,255,.07);
        color: #fff !important;
    }
    .nav-mobile-menu .nav-sep { display: none; }

    /* ── Responsive ──────────────────────────────────────────────── */
    @media (max-width: 960px) {
        .nav-center { display: none; }
        .nav-hamburger { display: flex; }
        .navbar-main { padding: 0 2rem; }
    }
    @media (max-width: 480px) {
        .logo-name { font-size: 18px; }
    }
</style>

<header class="navbar-main">

    <!-- ── Logo ── -->
    <a class="logo-link" href="../php/dashboard.php">
        <img src="../public/logo1.png" alt="SearchIA">
        <span class="logo-name">Search<span>IA</span></span>
    </a>

    <!-- ── Pill central multi-liens ── -->
    <nav class="nav-center" aria-label="Navigation principale">

        <!-- Groupe 1 : Produit -->
        <a href="../php/dashboard.php"
           class="nav-link nav-highlight <?= isActive('dashboard') ?>">
            AI Tools
        </a>

        <a href="../php/pricing.php"
           class="nav-link <?= isActive('pricing') ?>">
            Tarifs
        </a>

        <!-- Séparateur visuel -->
        <span class="nav-sep" aria-hidden="true"></span>

        <!-- Groupe 2 : Ressources -->
        <a href="../php/blog.php"
           class="nav-link <?= isActive('blog') ?>">
            Blog
            <span class="nav-badge-new">New</span>
        </a>

        <a href="../php/documentation.php"
           class="nav-link <?= isActive('documentation') ?>">
            Docs
        </a>

        <!-- Séparateur visuel -->
        <span class="nav-sep" aria-hidden="true"></span>

        <!-- Groupe 3 : Société -->
        <a href="../php/about.php"
           class="nav-link <?= isActive('about') ?>">
            À propos
        </a>

        <a href="../php/contact.php"
           class="nav-link <?= isActive('contact') ?>">
            Contact
        </a>

    </nav>

    <!-- ── Zone auth ── -->
    <div class="navbar-right">
        <?php include "../php/auth.php"; ?>
    </div>

    <!-- ── Bouton hamburger (mobile) ── -->
    <button class="nav-hamburger" id="navHamburger" aria-label="Ouvrir le menu" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>

</header>

<!-- ── Menu mobile déroulant ── -->
<nav class="nav-mobile-menu" id="navMobileMenu" aria-label="Menu mobile">
    <a href="../php/dashboard.php" class="nav-link <?= isActive('dashboard') ?>">AI Tools</a>
    <a href="../php/pricing.php"   class="nav-link <?= isActive('pricing') ?>">Tarifs</a>
    <a href="../php/blog.php"      class="nav-link <?= isActive('blog') ?>">Blog</a>
    <a href="../php/documentation.php" class="nav-link <?= isActive('documentation') ?>">Documentation</a>
    <a href="../php/about.php"     class="nav-link <?= isActive('about') ?>">À propos</a>
    <a href="../php/contact.php"   class="nav-link <?= isActive('contact') ?>">Contact</a>
</nav>

<script>
// Hamburger toggle
(function () {
    const btn  = document.getElementById('navHamburger');
    const menu = document.getElementById('navMobileMenu');
    if (!btn || !menu) return;

    btn.addEventListener('click', () => {
        const open = menu.classList.toggle('open');
        btn.setAttribute('aria-expanded', open);
        // Animation des barres
        const spans = btn.querySelectorAll('span');
        if (open) {
            spans[0].style.transform = 'translateY(7px) rotate(45deg)';
            spans[1].style.opacity   = '0';
            spans[2].style.transform = 'translateY(-7px) rotate(-45deg)';
        } else {
            spans.forEach(s => { s.style.transform = ''; s.style.opacity = ''; });
        }
    });

    // Fermer si clic en dehors
    document.addEventListener('click', e => {
        if (!btn.contains(e.target) && !menu.contains(e.target)) {
            menu.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
            btn.querySelectorAll('span').forEach(s => { s.style.transform = ''; s.style.opacity = ''; });
        }
    });
})();
</script>