// outil.js
console.log('outil.js chargé');
document.addEventListener('DOMContentLoaded', () => {

  // ── Anime les barres de performance ────────────────────────────────────
  const bars = document.querySelectorAll('.ot-bar-fill');
  if (bars.length) {
    setTimeout(() => {
      bars.forEach(bar => bar.classList.add('ot-bar-animate'));
    }, 200);
  }

  // ── Apparition progressive des cartes ──────────────────────────────────
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
    cards.forEach(card => card.classList.add('ot-visible'));
  }

  // ── Smooth scroll ───────────────────────────────────────────────────────
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
      const target = document.querySelector(a.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

  // ── Étoiles du formulaire d'ajout ───────────────────────────────────────
  const starLabels = ['Mauvais', 'Moyen', 'Correct', 'Bien', 'Excellent'];
  const stars = document.querySelectorAll('#starPicker .ot-sp-star'); // ← ciblé sur le formulaire d'ajout uniquement
  const hint = document.getElementById('starHint');

  stars.forEach((lbl, i) => {
    lbl.addEventListener('click', () => {
      const radio = lbl.querySelector('input[type="radio"]');
      if (radio) radio.checked = true;

      stars.forEach((s, j) => {
        s.querySelector('svg').style.fill = j <= i ? '#f59e0b' : '#d1d5db';
        s.querySelector('svg').style.stroke = j <= i ? '#f59e0b' : '#d1d5db';
      });

      if (hint) {
        hint.textContent = starLabels[i] + ' (' + (i + 1) + '/5)';
        hint.style.color = '#f59e0b';
      }
    });

    lbl.addEventListener('mouseenter', () => {
      stars.forEach((s, j) => {
        s.querySelector('svg').style.fill = j <= i ? '#f59e0b' : '#d1d5db';
        s.querySelector('svg').style.stroke = j <= i ? '#f59e0b' : '#d1d5db';
      });
      if (hint) hint.textContent = starLabels[i] + ' (' + (i + 1) + '/5)';
    });

    lbl.addEventListener('mouseleave', () => {
      const checked = document.querySelector('input[name="rating"]:checked');
      const checkedVal = checked ? parseInt(checked.value) : 0;
      stars.forEach((s, j) => {
        s.querySelector('svg').style.fill = j < checkedVal ? '#f59e0b' : '#d1d5db';
        s.querySelector('svg').style.stroke = j < checkedVal ? '#f59e0b' : '#d1d5db';
      });
      if (hint) hint.textContent = checked
        ? starLabels[checkedVal - 1] + ' (' + checkedVal + '/5)'
        : 'Cliquez pour noter';
    });
  });
  // À la fin du DOMContentLoaded, avant la fermeture });

  // ── Boutons modifier / annuler ──────────────────────────────────────────
// ── Boutons modifier / annuler ──────────────────────────
document.querySelectorAll('.js-edit-btn, .js-cancel-btn').forEach(btn => {

  btn.addEventListener('click', () => {

    const reviewId = btn.dataset.id;
    const form = document.getElementById('edit-form-' + reviewId);

    if (!form) return;

    // Vérifie le vrai display calculé
    const isHidden = window.getComputedStyle(form).display === 'none';

    form.style.display = isHidden ? 'block' : 'none';

    // Si on ouvre le formulaire
    if (isHidden) {

      const editStars = form.querySelectorAll('.ot-sp-star');

      editStars.forEach((star, index) => {

        const radio = star.querySelector('input');

        // état initial
        if (radio.checked) {
          for (let i = 0; i <= index; i++) {
            editStars[i].querySelector('svg').style.fill = '#f59e0b';
            editStars[i].querySelector('svg').style.stroke = '#f59e0b';
          }
        }

        // click
        star.addEventListener('click', () => {

          radio.checked = true;

          editStars.forEach((s, j) => {

            const svg = s.querySelector('svg');

            svg.style.fill = j <= index ? '#f59e0b' : '#d1d5db';
            svg.style.stroke = j <= index ? '#f59e0b' : '#d1d5db';
          });
        });

        // hover
        star.addEventListener('mouseenter', () => {

          editStars.forEach((s, j) => {

            const svg = s.querySelector('svg');

            svg.style.fill = j <= index ? '#f59e0b' : '#d1d5db';
            svg.style.stroke = j <= index ? '#f59e0b' : '#d1d5db';
          });
        });

        // leave
        star.addEventListener('mouseleave', () => {

          const checked = form.querySelector('input[type="radio"]:checked');
          const checkedVal = checked ? parseInt(checked.value) : 0;

          editStars.forEach((s, j) => {

            const svg = s.querySelector('svg');

            svg.style.fill = j < checkedVal ? '#f59e0b' : '#d1d5db';
            svg.style.stroke = j < checkedVal ? '#f59e0b' : '#d1d5db';
          });
        });

      });
    }

  });

});

}); // ← fin DOMContentLoaded
