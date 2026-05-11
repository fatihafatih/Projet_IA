// outil.js — animations & interactions pour la page détail outil

document.addEventListener('DOMContentLoaded', () => {

  // ── Anime les barres de performance au chargement ────────────────────────
  const bars = document.querySelectorAll('.ot-bar-fill');
  if (bars.length) {
    // On lance l'animation après un léger délai pour qu'elle soit visible
    setTimeout(() => {
      bars.forEach(bar => bar.classList.add('ot-bar-animate'));
    }, 200);
  }

  // ── Apparition progressive des cartes (Intersection Observer) ────────────
  const cards = document.querySelectorAll(
    '.ot-perf-card, .ot-review-card, .ot-model-card, .ot-side-card'
  );

  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('ot-visible');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1 });

    cards.forEach((card, i) => {
      card.style.transitionDelay = `${i * 40}ms`;
      io.observe(card);
    });
  } else {
    // Fallback : tout afficher directement
    cards.forEach(card => card.classList.add('ot-visible'));
  }

  // ── Smooth scroll sur les ancres internes ────────────────────────────────
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
      const target = document.querySelector(a.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

});