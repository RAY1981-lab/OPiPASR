(() => {
  const yearEl = document.getElementById('year');
  if (yearEl) yearEl.textContent = String(new Date().getFullYear());

  // Sidebar toggle (authorized layout)
  const sideToggle = document.querySelector('.sidebar-toggle');
  const sideNav = document.getElementById('side-nav');

  const setSidebar = (open) => {
    document.body.classList.toggle('sidebar-open', open);
    if (sideToggle) sideToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  };

  if (sideToggle && sideNav) {
    sideToggle.addEventListener('click', () => {
      const open = !document.body.classList.contains('sidebar-open');
      setSidebar(open);
    });

    document.addEventListener('click', (e) => {
      if (!document.body.classList.contains('sidebar-open')) return;
      const target = e.target;
      if (target instanceof Element) {
        if (sideNav.contains(target) || sideToggle.contains(target)) return;
      }
      setSidebar(false);
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') setSidebar(false);
    });
  }
})();
