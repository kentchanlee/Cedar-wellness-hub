<?php
/** Expects $page_title and optional $breadcrumb */
?>
<script>
(function () {
  try {
    const savedTheme = localStorage.getItem('cwh-theme') || 'light';
    const resolvedTheme = savedTheme === 'system'
      ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
      : savedTheme;
    document.documentElement.dataset.theme = resolvedTheme;
    document.documentElement.dataset.themeMode = savedTheme;
  } catch (error) {}
})();
</script>
<header class="topbar">
  <button class="mobile-menu-btn" type="button" aria-label="Open navigation" aria-controls="appSidebar" aria-expanded="false" data-sidebar-open>
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
  </button>
  <div class="topbar-title">
    <h2><?= e($page_title ?? 'Dashboard') ?></h2>
    <?php if (!empty($breadcrumb)): ?><div class="breadcrumb"><?= e($breadcrumb) ?></div><?php endif; ?>
  </div>
  <div class="flex gap-12">
    <label class="theme-picker" title="Choose color theme">
      <span class="theme-icon" aria-hidden="true">&#9680;</span>
      <span class="sr-only">Color theme</span>
      <select id="themeSelect" aria-label="Color theme">
        <option value="system">System</option>
        <option value="light">Light</option>
        <option value="dark">Dark</option>
      </select>
    </label>
    <span class="badge badge-sage"><?= date('l, F j, Y') ?></span>
  </div>
</header>
<script>
(function () {
  const picker = document.getElementById('themeSelect');
  if (!picker) return;

  let savedTheme = 'light';
  try { savedTheme = localStorage.getItem('cwh-theme') || 'light'; } catch (error) {}
  picker.value = ['light', 'dark', 'system'].includes(savedTheme) ? savedTheme : 'light';

  picker.addEventListener('change', function () {
    const theme = this.value;
    document.documentElement.dataset.theme = theme === 'system'
      ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
      : theme;
    document.documentElement.dataset.themeMode = theme;
    try { localStorage.setItem('cwh-theme', theme); } catch (error) {}
  });

  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (event) {
    let mode = 'light';
    try { mode = localStorage.getItem('cwh-theme') || 'light'; } catch (error) {}
    if (mode === 'system') document.documentElement.dataset.theme = event.matches ? 'dark' : 'light';
  });
})();
</script>
<script>
(function () {
  const sidebar = document.getElementById('appSidebar');
  const scrim = document.getElementById('sidebarScrim');
  const openButton = document.querySelector('[data-sidebar-open]');
  const closeButton = document.querySelector('[data-sidebar-close]');
  if (!sidebar || !scrim || !openButton) return;

  function setOpen(open) {
    document.body.classList.toggle('sidebar-open', open);
    openButton.setAttribute('aria-expanded', String(open));
    scrim.setAttribute('aria-hidden', String(!open));
  }
  openButton.addEventListener('click', function () { setOpen(true); });
  if (closeButton) closeButton.addEventListener('click', function () { setOpen(false); });
  scrim.addEventListener('click', function () { setOpen(false); });
  document.addEventListener('keydown', function (event) { if (event.key === 'Escape') setOpen(false); });

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.alert').forEach(function (alert) {
      alert.classList.add('toast-alert');
      window.setTimeout(function () { alert.classList.add('toast-hide'); }, 5000);
    });
    document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
      backdrop.addEventListener('click', function (event) {
        if (event.target === backdrop) backdrop.classList.remove('open');
      });
    });
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') document.querySelectorAll('.modal-backdrop.open').forEach(function (modal) { modal.classList.remove('open'); });
  });
})();
</script>
