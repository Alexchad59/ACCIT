/* ============================================================
   ACCIT — Scripts principaux
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {

  /* ── Navigation mobile ─────────────────────────────────── */
  const toggle = document.querySelector('.nav-toggle');
  const navLinks = document.querySelector('.nav-links');
  const navCta = document.querySelector('.nav-cta');

  toggle?.addEventListener('click', () => {
    navLinks?.classList.toggle('open');
    navCta?.classList.toggle('open');
    const isOpen = navLinks?.classList.contains('open');
    toggle.setAttribute('aria-expanded', isOpen);
  });

  /* ── Lien actif ────────────────────────────────────────── */
  const currentPath = window.location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.nav-links a').forEach(link => {
    const href = link.getAttribute('href');
    if (href === currentPath || (currentPath === '' && href === 'index.html')) {
      link.classList.add('active');
    }
  });

  /* ── Formulaire de contact ─────────────────────────────── */
  const form = document.getElementById('contact-form');
  form?.addEventListener('submit', async e => {
    e.preventDefault();
    if (!form.checkValidity()) { form.reportValidity(); return; }

    const btn = form.querySelector('[type=submit]');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Envoi en cours…';

    // Collecte des données + honeypot anti-spam
    const data = {
      prenom:    form.prenom.value.trim(),
      nom:       form.nom.value.trim(),
      email:     form.email.value.trim(),
      telephone: form.telephone.value.trim(),
      societe:   form.societe.value.trim(),
      sujet:     form.sujet.value,
      message:   form.message.value.trim(),
      rgpd:      form.rgpd.checked,
      website:   form.website?.value ?? '', // honeypot
    };

    try {
      const res  = await fetch('send-contact.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(data),
      });
      const json = await res.json();

      if (json.success) {
        btn.textContent = 'Message envoyé ✓';
        btn.style.background = '#16A34A';
        form.reset();
        // Affiche un message de succès sous le bouton
        let msg = form.querySelector('.form-feedback');
        if (!msg) {
          msg = document.createElement('p');
          msg.className = 'form-feedback';
          msg.style.cssText = 'margin-top:.75rem;font-size:.875rem;color:#16A34A;font-weight:600;';
          btn.parentNode.insertBefore(msg, btn.nextSibling);
        }
        msg.textContent = json.message;
        setTimeout(() => {
          btn.textContent = originalText;
          btn.disabled = false;
          btn.style.background = '';
          msg.textContent = '';
        }, 6000);
      } else {
        btn.textContent = originalText;
        btn.disabled = false;
        alert(json.message || 'Une erreur est survenue. Veuillez réessayer.');
      }
    } catch {
      btn.textContent = originalText;
      btn.disabled = false;
      alert('Impossible d\'envoyer le message. Vérifiez votre connexion et réessayez.');
    }
  });

  /* ── Scroll reveal (Intersection Observer) ─────────────── */
  const revealEls = document.querySelectorAll('.card, .team-card, .step, .metric-card, .value-card, .pricing-card');
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.style.opacity = '1';
          entry.target.style.transform = 'translateY(0)';
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1 });

    revealEls.forEach(el => {
      el.style.opacity = '0';
      el.style.transform = 'translateY(20px)';
      el.style.transition = 'opacity .5s ease, transform .5s ease';
      observer.observe(el);
    });
  }

  /* ── Compteur animé (stats hero) ──────────────────────── */
  function animateCount(el, target, suffix) {
    const duration = 1800;
    const start = performance.now();
    const update = now => {
      const t = Math.min((now - start) / duration, 1);
      const ease = 1 - Math.pow(1 - t, 3);
      el.textContent = Math.round(ease * target) + suffix;
      if (t < 1) requestAnimationFrame(update);
    };
    requestAnimationFrame(update);
  }

  const statsObserver = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      entry.target.querySelectorAll('[data-count]').forEach(el => {
        const [target, suffix] = el.dataset.count.split('|');
        animateCount(el, parseInt(target), suffix || '');
      });
      statsObserver.unobserve(entry.target);
    });
  }, { threshold: 0.5 });

  document.querySelectorAll('.hero-stats, .why-visual').forEach(el => statsObserver.observe(el));
});
