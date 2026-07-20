document.addEventListener('DOMContentLoaded', function () {
  const toggle = document.getElementById('togglePassword');
  const pwd = document.getElementById('password');
  const form = document.getElementById('loginForm');

  // ------------------------------------------------------------------
  // Password show/hide (unchanged behavior)
  // ------------------------------------------------------------------
  if (toggle && pwd) {
    toggle.addEventListener('click', function () {
      const type = pwd.getAttribute('type') === 'password' ? 'text' : 'password';
      pwd.setAttribute('type', type);
      const icon = toggle.querySelector('i');
      if (icon) {
        icon.className = type === 'password' ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash';
      }
      toggle.setAttribute('aria-pressed', type === 'text' ? 'true' : 'false');
      toggle.setAttribute('aria-label', type === 'text' ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
    });
  }

  // ------------------------------------------------------------------
  // Login / signup form validation (unchanged) + submit loading state
  // ------------------------------------------------------------------
  if (form) {
    form.addEventListener('submit', function (event) {
      const email = form.querySelector('#email');
      const password = form.querySelector('#password');
      let valid = true;

      if (email && (!email.value || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email.value))) {
        email.closest('.field').querySelector('.error').style.display = 'block';
        valid = false;
      } else if (email) {
        email.closest('.field').querySelector('.error').style.display = 'none';
      }

      if (password && !password.value) {
        password.closest('.field').querySelector('.error').style.display = 'block';
        valid = false;
      } else if (password) {
        password.closest('.field').querySelector('.error').style.display = 'none';
      }

      if (!valid) {
        event.preventDefault();
        return;
      }

      // Visual loading state while the browser navigates to login_process.php.
      // The class is purely cosmetic (button text hidden behind a spinner)
      // and never blocks the actual form submission.
      const submitBtn = form.querySelector('button[type="submit"]');
      if (submitBtn) {
        submitBtn.classList.add('is-loading');
        submitBtn.setAttribute('disabled', 'disabled');
      }
    });
  }

  // ------------------------------------------------------------------
  // Theme toggle: dark/light, persisted in localStorage, applied on
  // every page (the actual "no flicker" application happens via the
  // small inline script in includes/header.php that runs before the
  // stylesheet paints; this handler just keeps things in sync after
  // a user click and remembers the choice for next time).
  // ------------------------------------------------------------------
  const themeToggle = document.getElementById('themeToggle');
  const root = document.documentElement;

  function syncThemeIcon(theme) {
    if (!themeToggle) return;
    const icon = themeToggle.querySelector('i');
    if (icon) {
      icon.className = theme === 'dark' ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
    }
    themeToggle.setAttribute('aria-label', theme === 'dark' ? 'Basculer en mode clair' : 'Basculer en mode sombre');
  }

  // Reflect whatever theme the anti-flicker script already applied.
  syncThemeIcon(root.getAttribute('data-theme') === 'light' ? 'light' : 'dark');
  root.classList.remove('theme-loading');

  if (themeToggle) {
    themeToggle.addEventListener('click', function () {
      const current = root.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
      const next = current === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try {
        localStorage.setItem('gvp-theme', next);
      } catch (e) {
        // localStorage unavailable (private mode, quota, etc.) — theme still
        // applies for the current page load, it just won't persist.
      }
      syncThemeIcon(next);
    });
  }

  // ------------------------------------------------------------------
  // Sidebar toggle: on desktop this simply shows/hides the fixed
  // sidebar column; on mobile (see CSS @media 980px) it slides the
  // sidebar in/out as an overlay with a backdrop, instead of an
  // abrupt display:none jump.
  // ------------------------------------------------------------------
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebar = document.querySelector('.sidebar');

  if (sidebarToggle && sidebar) {
    let backdrop = document.querySelector('.sidebar-backdrop');
    if (!backdrop) {
      backdrop = document.createElement('div');
      backdrop.className = 'sidebar-backdrop';
      document.body.appendChild(backdrop);
    }

    function isMobile() {
      return window.matchMedia('(max-width: 980px)').matches;
    }

    function closeMobileSidebar() {
      sidebar.classList.remove('is-open');
      backdrop.classList.remove('is-visible');
    }

    sidebarToggle.addEventListener('click', function () {
      if (isMobile()) {
        const willOpen = !sidebar.classList.contains('is-open');
        sidebar.classList.toggle('is-open', willOpen);
        backdrop.classList.toggle('is-visible', willOpen);
      } else {
        sidebar.style.display = sidebar.style.display === 'none' ? 'flex' : 'none';
      }
    });

    backdrop.addEventListener('click', closeMobileSidebar);
    window.addEventListener('resize', function () {
      if (!isMobile()) {
        closeMobileSidebar();
        sidebar.style.display = '';
      }
    });
  }

  // ------------------------------------------------------------------
  // Live search — case-insensitive, partial match, no page reload.
  // Works against every <table> inside a .table-wrap on the current
  // page. This is purely a client-side filter layered on top of the
  // existing server-side "q" search forms (modules/*/index.php), so
  // nothing about the existing PHP filtering/pagination is removed —
  // it just becomes instant while the user types.
  // ------------------------------------------------------------------
  function getTables(scope) {
    return Array.prototype.slice.call((scope || document).querySelectorAll('.table-wrap table'));
  }

  function filterTable(table, term) {
    const tbody = table.querySelector('tbody');
    if (!tbody) return;

    let visibleCount = 0;
    const rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));

    rows.forEach(function (row) {
      if (row.classList.contains('gvp-no-results')) {
        row.remove();
        return;
      }
      const text = row.textContent.toLowerCase();
      const matches = term === '' || text.indexOf(term) !== -1;
      row.classList.toggle('gvp-hidden-row', !matches);
      if (matches) visibleCount += 1;
    });

    const remainingRows = tbody.querySelectorAll('tr:not(.gvp-hidden-row)').length;
    if (term !== '' && remainingRows === 0) {
      const colCount = (table.querySelectorAll('thead th').length) || 1;
      const tr = document.createElement('tr');
      tr.className = 'gvp-no-results';
      const td = document.createElement('td');
      td.colSpan = colCount;
      td.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i>Aucun résultat pour cette recherche.';
      tr.appendChild(td);
      tbody.appendChild(tr);
    }
  }

  function wireLiveSearch(input, scope) {
    if (!input || input.dataset.gvpSearchBound) return;
    input.dataset.gvpSearchBound = '1';
    input.addEventListener('input', function () {
      const term = input.value.trim().toLowerCase();
      getTables(scope).forEach(function (table) {
        filterTable(table, term);
      });
    });
  }

  // Per-module search box (modules/*/index.php): filters only its own panel.
  document.querySelectorAll('.search-inline input').forEach(function (input) {
    const panel = input.closest('.panel') || document;
    wireLiveSearch(input, panel);
  });

  // Global topbar search box (present on every page via includes/header.php):
  // filters every table currently on the page.
  const globalSearch = document.querySelector('.topbar-search input');
  wireLiveSearch(globalSearch, document.querySelector('.content-area') || document);

  // ------------------------------------------------------------------
  // Hover lift for cards/panels/buttons/nav links (unchanged).
  // ------------------------------------------------------------------
  document.querySelectorAll('.stat-card, .panel, .btn, .nav-link').forEach(function (el) {
    el.addEventListener('mouseenter', function () {
      el.style.transform = 'translateY(-2px)';
    });
    el.addEventListener('mouseleave', function () {
      el.style.transform = '';
    });
  });

  // ------------------------------------------------------------------
  // Optional: show a loading state on the "Actualiser les analytics"
  // button (dashboard_analytics.php) while the form submits, without
  // preventing subsequent clicks after the page reloads.
  // ------------------------------------------------------------------
  function resetRefreshButtonState() {
    document.querySelectorAll('button[name="refresh"]').forEach(function (btn) {
      btn.classList.remove('is-loading');
      btn.removeAttribute('disabled');
      btn.removeAttribute('data-submitting');
    });
  }

  window.addEventListener('pageshow', resetRefreshButtonState);
  resetRefreshButtonState();

  document.querySelectorAll('button[name="refresh"]').forEach(function (btn) {
    var form = btn.closest('form');
    if (!form) return;
    form.addEventListener('submit', function () {
      btn.classList.add('is-loading');
      btn.setAttribute('data-submitting', '1');
      btn.removeAttribute('disabled');
    });
  });
});
