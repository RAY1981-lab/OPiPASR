(() => {
  const yearEl = document.getElementById('year');
  if (yearEl) yearEl.textContent = String(new Date().getFullYear());

  const toggle = document.querySelector('.nav-toggle');
  const nav = document.getElementById('site-nav');

  if (!toggle || !nav) return;

  const setState = (open) => {
    nav.classList.toggle('is-open', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  };

  toggle.addEventListener('click', () => {
    const open = !nav.classList.contains('is-open');
    setState(open);
  });

  // Close on outside click (mobile)
  document.addEventListener('click', (e) => {
    if (!nav.classList.contains('is-open')) return;
    const target = e.target;
    if (target instanceof Element) {
      if (nav.contains(target) || toggle.contains(target)) return;
    }
    setState(false);
  });

  // Close on ESC
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') setState(false);
  });
})();
