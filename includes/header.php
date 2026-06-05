<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<style>
    /* ════════════════════════════════════════════════════════════════════════════
    BARRE DE NAVIGATION - BENTO GLOW ÉLECTRIQUE (CORRIGÉ & OPALISÉ)
    ════════════════════════════════════════════════════════════════════════════ */

    :root {
        /* Palette Premium Néon Chic */
        --neon-gold: #F3E5AB;       /* Butter Yellow */
        --neon-coral: #E11D48;      /* Corail Électrique */
        --glass-bg: rgba(27, 42, 74, 0.85); /* Navy Blue #1B2A4A propre en RGBA */
        --glass-border: rgba(255, 255, 255, 0.08);
        --text-pure: #F8FAFC;
        --text-muted: #94A3B8;
    }

    [data-theme="light"] {
        --glass-bg: rgba(253, 251, 247, 0.82);
        --glass-border: rgba(27, 42, 74, 0.08); /* Utilisation propre du Navy Blue */
        --text-pure: #1B2A4A;                   /* Navy Blue Pur */
        --text-muted: #627293;
    }
.navbar-main {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 4rem;
        height: 78px;
        
        /* On force le fond et l'ombre du hover en dur ici */
        background: rgba(27, 42, 74, 0.92) !important; 
        box-shadow: 0 20px 40px rgba(11, 19, 43, 0.25) !important;
        
        backdrop-filter: blur(24px) saturate(200%);
        -webkit-backdrop-filter: blur(24px) saturate(200%);
        position: sticky;
        top: 0;
        z-index: 1000;
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        border-bottom: 1px solid var(--glass-border);
    }

    /* Halo lumineux en arrière-plan (effet lévitation magique) */
    .navbar-main::before {
        content: '';
        position: absolute;
        top: -20px;
        left: 10%;
        right: 10%;
        height: 40px;
        background: linear-gradient(90deg, transparent, var(--neon-gold), var(--neon-coral), transparent);
        filter: blur(40px);
        opacity: 0.15;
        pointer-events: none;
        z-index: -1;
        transition: opacity 0.4s ease;
    }

    .navbar-main:hover::before {
        opacity: 0.3; /* La lueur s'intensifie quand on approche la souris */
    }

    .navbar-main:hover {
        background: rgba(27, 42, 74, 0.92); /* Navy Blue légèrement plus opaque au survol */
        box-shadow: 0 20px 40px rgba(11, 19, 43, 0.25);
    }

    /* Logo Animé & Texturé */
    .logo-link {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
    }

    .logo-link img {
        width: 42px;
        height: 42px;
        object-fit: contain;
        transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .logo-link:hover img {
        transform: scale(1.12) rotate(-6deg);
        filter: drop-shadow(0 0 12px var(--neon-gold));
    }

    .logo-name {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 21px;
        font-weight: 800;
        color: var(--text-pure);
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

    /* Onglet Central - Le Bento Pill Électrique */
    .nav-center {
        display: flex;
        align-items: center;
        padding: 2px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--glass-border);
        border-radius: 99px;
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .nav-center a {
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        text-decoration: none;
        padding: 10px 26px;
        border-radius: 99px;
        color: #1B2A4A !important; /* Écrit en Navy Blue sur le bouton brillant */
        background: linear-gradient(135deg, var(--neon-gold) 0%, #FBBF24 50%, var(--neon-coral) 100%);
        background-size: 200% auto;
        box-shadow: 0 4px 20px rgba(243, 229, 171, 0.4);
        transition: all 0.4s ease;
    }

    .nav-center a:hover {
        transform: translateY(-2px);
        background-position: right center; /* Fait glisser le dégradé de couleur */
        box-shadow: 0 8px 25px rgba(225, 29, 72, 0.6);
    }

    /* Zone Droite (Boutons d'Auth) */
    .navbar-right {
        display: flex;
        align-items: center;
    }

    /* Responsive Design fluide */
    @media (max-width: 768px) {
        .navbar-main {
            padding: 0 2rem;
            height: 72px;
        }
        .logo-name {
            font-size: 18px;
        }
    }
</style>

<header class="navbar-main">

    <!-- Logo Électrique -->
    <a class="logo-link" href="../php/dashboard.php">
        <img src="../public/logo1.png" alt="SearchIA Premium">
        <span class="logo-name">Search<span>IA</span></span>
    </a>

    <!-- Bouton Dégradé Liquide -->
    <nav class="nav-center">
        <a href="../php/dashboard.php">AI Tools</a>
    </nav>

    <!-- Espace Authentification -->
    <div class="navbar-right">
         <?php include "../php/auth.php"; ?>
    </div>

</header>