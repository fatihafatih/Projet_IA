// outil.js
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
  // ── Gestion des favoris (même logique que dashboard) ───────────────────
  let currentOutilId = null;

  document.querySelectorAll('.js-fav-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      currentOutilId = btn.dataset.id;

      const res = await fetch(`../includes/favoris.php?action=get_collections&id_outil=${currentOutilId}`);
      const data = await res.json();

      if (data.error === 'non_connecte') {
        window.location.href = 'login.php';
        return;
      }

      // Remplit la liste des collections
      const container = document.getElementById('favCollections');
      container.innerHTML = '';
      data.collections.forEach(col => {
        const div = document.createElement('div');
        div.className = 'fav-col-item' + (col.is_fav == 1 ? ' is-fav' : '');
        div.dataset.colId = col.ID_COLLECTIONS;
        div.innerHTML = `
                <span>${col.name}</span>
                <span class="fav-col-check">${col.is_fav == 1 ? '❤️' : '🤍'}</span>
            `;
        div.addEventListener('click', () => toggleFavOutil(col.ID_COLLECTIONS, div, btn));
        container.appendChild(div);
      });

      // État initial du bouton
      const anyFav = data.collections.some(c => c.is_fav == 1);
      btn.classList.toggle('is-fav', anyFav);

      document.getElementById('favNewInput').value = '';
      document.getElementById('favNewError').textContent = '';
      document.getElementById('favOverlay').style.display = 'flex';
    });
  });

  async function toggleFavOutil(colId, divEl, btn) {
    const formData = new FormData();
    formData.append('action', 'toggle_favori');
    formData.append('id_outil', currentOutilId);
    formData.append('id_collection', colId);

    const res = await fetch('../includes/favoris.php', { method: 'POST', body: formData });
    const data = await res.json();

    if (data.success) {
      const isFav = data.state === 'added';
      divEl.classList.toggle('is-fav', isFav);
      divEl.querySelector('.fav-col-check').textContent = isFav ? '❤️' : '🤍';

      // Met à jour le bouton hero
      const anyFav = [...document.querySelectorAll('#favCollections .fav-col-item')]
        .some(d => d.classList.contains('is-fav'));
      btn.classList.toggle('is-fav', anyFav);
    }
  }

  document.getElementById('favNewBtn')?.addEventListener('click', async () => {
    const input = document.getElementById('favNewInput');
    const error = document.getElementById('favNewError');
    const name = input.value.trim();

    if (!name) { error.textContent = 'Entrez un nom de collection.'; return; }

    const formData = new FormData();
    formData.append('action', 'create_collection');
    formData.append('name', name);

    const res = await fetch('../includes/favoris.php', { method: 'POST', body: formData });
    const data = await res.json();

    if (data.error === 'existe_deja') { error.textContent = 'Cette collection existe déjà.'; return; }

    if (data.success) {
      error.textContent = '';
      input.value = '';
      const container = document.getElementById('favCollections');
      const div = document.createElement('div');
      div.className = 'fav-col-item';
      div.dataset.colId = data.id;
      div.innerHTML = `<span>${data.name}</span><span class="fav-col-check">🤍</span>`;
      // Récupère le bouton actif pour le passer au toggle
      const btn = document.querySelector('.js-fav-btn');
      div.addEventListener('click', () => toggleFavOutil(data.id, div, btn));
      container.appendChild(div);
    }
  });

  document.getElementById('favClose')?.addEventListener('click', () => {
    document.getElementById('favOverlay').style.display = 'none';
  });

  document.getElementById('favOverlay')?.addEventListener('click', (e) => {
    if (e.target === document.getElementById('favOverlay')) {
      document.getElementById('favOverlay').style.display = 'none';
    }
  });

}); // ← fin DOMContentLoaded
