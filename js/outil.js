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
const starLabels = ['Mauvais', 'Moyen', 'Correct', 'Bien', 'Excellent'];
const stars = document.querySelectorAll('.ot-sp-star');
const hint = document.getElementById('starHint');

stars.forEach((lbl, i) => {
  lbl.addEventListener('click', () => {
    // Coche le bon radio
    const radio = lbl.querySelector('input[type="radio"]');
    if (radio) radio.checked = true;

    // Allume les étoiles jusqu'à i
    stars.forEach((s, j) => {
      const svg = s.querySelector('svg path');
      if (j <= i) {
        s.querySelector('svg').style.fill = '#f59e0b';
        s.querySelector('svg').style.stroke = '#f59e0b';
      } else {
        s.querySelector('svg').style.fill = 'var(--border, #d1d5db)';
        s.querySelector('svg').style.stroke = 'var(--border, #d1d5db)';
      }
    });

    if (hint) {
      hint.textContent = starLabels[i] + ' (' + (i + 1) + '/5)';
      hint.style.color = '#f59e0b';
    }
  });

  lbl.addEventListener('mouseenter', () => {
    stars.forEach((s, j) => {
      if (j <= i) {
        s.querySelector('svg').style.fill = '#f59e0b';
        s.querySelector('svg').style.stroke = '#f59e0b';
      } else {
        s.querySelector('svg').style.fill = 'var(--border, #d1d5db)';
        s.querySelector('svg').style.stroke = 'var(--border, #d1d5db)';
      }
    });
    if (hint) hint.textContent = starLabels[i] + ' (' + (i + 1) + '/5)';
  });

  lbl.addEventListener('mouseleave', () => {
    // Remet l'état selon la valeur cochée
    const checked = document.querySelector('input[name="rating"]:checked');
    const checkedVal = checked ? parseInt(checked.value) : 0;
    stars.forEach((s, j) => {
      if (j < checkedVal) {
        s.querySelector('svg').style.fill = '#f59e0b';
        s.querySelector('svg').style.stroke = '#f59e0b';
      } else {
        s.querySelector('svg').style.fill = 'var(--border, #d1d5db)';
        s.querySelector('svg').style.stroke = 'var(--border, #d1d5db)';
      }
    });
    if (hint) hint.textContent = checked ? starLabels[checkedVal - 1] + ' (' + checkedVal + '/5)' : 'Cliquez pour noter';
  });
});