/* ========================================================================
   Brew & Co. dashboard and role navigation — Batch 2
   Adds visual workspace context, an accessible system-hub shortcut, current
   navigation hints, and mobile-friendly nav behaviour without changing PHP.
   ======================================================================== */
(function () {
  'use strict';

  function qs(selector, root) { return (root || document).querySelector(selector); }
  function all(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }

  function currentPath() {
    var path = (window.location.pathname || '').replace(/\/+$/, '');
    return path || '/';
  }

  function workspace() {
    var title = (document.title || '').toLowerCase();
    var path = (window.location.pathname || '').toLowerCase();
    if (path.indexOf('/pos') !== -1 || title.indexOf('pos') !== -1 || title.indexOf('cashier') !== -1) {
      return { name: 'Point of Sale', color: '#8f5c39', hub: path.indexOf('/pos/') !== -1 ? '../hub.php' : 'hub.php' };
    }
    // inventory-manager.php retains the legacy “HRMS — Inventory Manager”
    // browser title, so identify its explicit route before title-based HRMS
    // matching. This keeps the sidebar workspace label accurate.
    if (path.indexOf('inventory-manager') !== -1 || title.indexOf('inventory manager') !== -1) {
      return { name: 'Inventory workspace', color: '#a9714a', hub: 'hub.php' };
    }
    if (path.indexOf('/hrms/') !== -1 || title.indexOf('hrms') !== -1 || title.indexOf('payroll') !== -1) {
      return { name: 'HRMS & Payroll', color: '#71518e', hub: '../hub.php' };
    }
    if (title.indexOf('finance') !== -1) {
      return { name: 'Finance workspace', color: '#256b4d', hub: 'hub.php' };
    }
    if (title.indexOf('inventory') !== -1 || path.indexOf('warehouse') !== -1 || path.indexOf('stocktake') !== -1) {
      return { name: 'Inventory workspace', color: '#a9714a', hub: 'hub.php' };
    }
    return { name: 'Brew & Co. workspace', color: '#a9714a', hub: 'hub.php' };
  }

  function applyShellClasses() {
    var body = document.body;
    if (!body) return;
    if (qs('.sidebar')) body.classList.add('bc-role-shell');
    if (qs('.stats, .stat-row, .summary-cards, .sales-summary-cards, .panel')) body.classList.add('bc-dashboard-shell');
    if (qs('.navbar, .category-page, .order-sidebar')) body.classList.add('bc-pos-shell');
    if (qs('.portal')) body.classList.add('bc-portal');
  }

  function addWorkspaceContext() {
    var sidebar = qs('.sidebar');
    if (!sidebar || qs('.bc-workspace-chip', sidebar)) return;
    var placeAfter = qs('.sidebar-user', sidebar) || qs('.logo', sidebar) || qs('.sidebar-brand', sidebar);
    if (!placeAfter) return;
    var info = workspace();
    var chip = document.createElement('div');
    chip.className = 'bc-workspace-chip';
    chip.style.setProperty('--bc-workspace-color', info.color);
    chip.innerHTML = '<span class="bc-workspace-dot" aria-hidden="true"></span>' +
      '<span class="bc-workspace-copy"><small>Current workspace</small><strong></strong></span>';
    qs('strong', chip).textContent = info.name;
    placeAfter.insertAdjacentElement('afterend', chip);
  }

  function markCurrentLink() {
    var pagePath = currentPath();
    all('.sidebar a[href], .navbar a[href], header a[href]').forEach(function (link) {
      var href = link.getAttribute('href');
      if (!href || href === '#' || href.charAt(0) === '#') return;
      var path = href.split('?')[0].replace(/\/+$/, '');
      if (!path || path.indexOf('://') !== -1) return;
      var normalized = path.charAt(0) === '/' ? path : pagePath.replace(/\/[^/]*$/, '/') + path;
      if (normalized.replace(/\/index(?:\.php)?$/, '') === pagePath.replace(/\/index(?:\.php)?$/, '')) {
        link.classList.add('is-current');
        link.setAttribute('aria-current', 'page');
      }
    });
  }

  function enhanceExistingControls() {
    all('.sidebar-toggle, .hamburger, [data-nav-toggle]').forEach(function (button) {
      if (!button.getAttribute('aria-label')) button.setAttribute('aria-label', 'Open navigation');
      if (!button.getAttribute('aria-expanded')) button.setAttribute('aria-expanded', 'false');
    });
    all('.sidebar a, .sidebar-nav a, .nav a').forEach(function (link) {
      if (!link.getAttribute('title')) {
        var label = (link.textContent || '').trim().replace(/\s+/g, ' ');
        if (label) link.setAttribute('title', label);
      }
    });
  }

  function closeNavAfterChoice() {
    all('.sidebar a[href]').forEach(function (link) {
      if (link.dataset.bcCloseNav) return;
      link.dataset.bcCloseNav = 'true';
      link.addEventListener('click', function () {
        if (!window.matchMedia('(max-width: 820px)').matches) return;
        window.setTimeout(function () {
          all('.sidebar.open').forEach(function (sidebar) { sidebar.classList.remove('open'); });
          all('.sidebar-backdrop.show, .backdrop.show, .bc-nav-backdrop.is-visible').forEach(function (backdrop) {
            backdrop.classList.remove('show');
            backdrop.classList.remove('is-visible');
          });
        }, 0);
      });
    });
  }

  function keepActiveNavVisible() {
    var active = qs('.sidebar a.active, .sidebar a.is-current');
    if (!active || !active.scrollIntoView) return;
    if (window.matchMedia('(min-width: 821px)').matches) {
      active.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    }
  }

  function init() {
    applyShellClasses();
    addWorkspaceContext();
    markCurrentLink();
    enhanceExistingControls();
    closeNavAfterChoice();
    keepActiveNavVisible();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
