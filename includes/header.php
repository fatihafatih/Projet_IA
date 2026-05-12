<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<link rel="stylesheet" href="../styles/style.css">

<header class="navbar-main">

    <a class="logo-link" href="../php/dashboard.php">
    <img src="../public/logo1.png" alt="SearchIA" style="width:50px; height:50px; object-fit:contain;">
        <span class="logo-name">Search<span>IA</span></span>
    </a>

    <nav class="nav-center">
        <a href="../php/dashboard.php" class="active">AI Tools</a>
        <a href="add-tool.php">Add Tool</a>
        <a href="news.php">News</a>
    </nav>

    <div class="navbar-right">

        <div class="notif-wrap">
            <button class="icon-btn" title="Notifications">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
            </button>
            <span class="notif-dot"></span>
        </div>

        <button class="icon-btn" id="themeToggle" title="Changer le thème">
            <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="5"/>
                <line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>
                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                <line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
            </svg>
            <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
            </svg>
            
        </button>

    </div>
<div class="navbar-right">
<?php include "../php/auth.php"; ?>

    </div>
</header>

<script>
    const html = document.documentElement;
    const themeToggle = document.getElementById('themeToggle');

    const saved = localStorage.getItem('tf-theme') || 'light';
    html.setAttribute('data-theme', saved);

    themeToggle.addEventListener('click', () => {
        const isDark = html.getAttribute('data-theme') === 'dark';
        const next = isDark ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        localStorage.setItem('tf-theme', next);
    });
</script>

