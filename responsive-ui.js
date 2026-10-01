/* ========================================================================
   Brew & Co. responsive foundation — Batch 1
   Adds safe mobile navigation behaviour and scrollable data tables without
   changing server-side workflows or API calls.
   ======================================================================== */
(function () {
  'use strict';

  var MOBILE_BREAKPOINT = 820;

  function isMobile() {
    return window.matchMedia && window.matchMedia('(max-width: ' + MOBILE_BREAKPOINT + 'px)').matches;
  }

  function all(selector, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(selector));
  }

  function setExpanded(button, isOpen) {
    if (!button) return;
    button.setAttribute('aria-expanded', String(Boolean(isOpen)));
    button.setAttribute('aria-label', isOpen ? 'Close menu' : 'Open menu');
  }

  function closeSidebars() {
    all('.sidebar.open').forEach(function (sidebar) { sidebar.classList.remove('open'); });
    all('.bc-nav-backdrop.is-visible').forEach(function (backdrop) { backdrop.classList.remove('is-visible'); });
    all('.sidebar-backdrop.show, .backdrop.show').forEach(function (backdrop) { backdrop.classList.remove('show'); });
    all('.bc-mobile-menu, .sidebar-toggle, .hamburger').forEach(function (button) { setExpanded(button, false); });
  }

  function attachExistingNavigation() {
    all('.sidebar-toggle, .hamburger, [data-nav-toggle]').forEach(function (button) {
      if (button.dataset.responsiveNavBound) return;
      button.dataset.responsiveNavBound = 'true';
      button.setAttribute('aria-controls', button.getAttribute('aria-controls') || 'sidebar');
      setExpanded(button, button.closest('body') && !!document.querySelector('.sidebar.open'));

      button.addEventListener('click', function () {
        window.setTimeout(function () {
          setExpanded(button, !!document.querySelector('.sidebar.open'));
        }, 0);
      });
    });
  }

  function createNavigationForLegacyPages() {
    var sidebar = document.querySelector('.sidebar');
    if (!sidebar || document.querySelector('.hamburger, .sidebar-toggle, [data-nav-toggle], .bc-mobile-menu')) return;

    if (!sidebar.id) sidebar.id = 'sidebar';
    var backdrop = document.createElement('div');
    backdrop.className = 'bc-nav-backdrop';
    backdrop.setAttribute('aria-hidden', 'true');

    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'bc-mobile-menu';
    button.innerHTML = '<span aria-hidden="true">☰</span>';
    button.setAttribute('aria-controls', sidebar.id);
    setExpanded(button, false);

    function toggle() {
      var open = !sidebar.classList.contains('open');
      sidebar.classList.toggle('open', open);
      backdrop.classList.toggle('is-visible', open);
      setExpanded(button, open);
    }
    button.addEventListener('click', toggle);
    backdrop.addEventListener('click', closeSidebars);
    sidebar.addEventListener('click', function (event) {
      if (isMobile() && event.target.closest('a[href], button[data-close-menu]')) closeSidebars();
    });

    document.body.appendChild(backdrop);
    document.body.appendChild(button);
  }

  function makeTablesScrollable(root) {
    all('table', root || document).forEach(function (table) {
      if (table.dataset.responsiveTable || table.closest('.bc-table-scroll, .table-wrap, .table-responsive, .data-table-wrap, .sales-table-wrap')) return;
      table.dataset.responsiveTable = 'true';
      var parent = table.parentNode;
      if (!parent || !parent.insertBefore) return;
      var wrapper = document.createElement('div');
      wrapper.className = 'bc-table-scroll';
      wrapper.setAttribute('tabindex', '0');
      wrapper.setAttribute('role', 'region');
      wrapper.setAttribute('aria-label', table.getAttribute('aria-label') || 'Scrollable data table');
      parent.insertBefore(wrapper, table);
      wrapper.appendChild(table);
    });
  }

  function init(root) {
    attachExistingNavigation();
    createNavigationForLegacyPages();
    makeTablesScrollable(root || document);
  }

  function observeDynamicContent() {
    if (!window.MutationObserver || !document.body) return;
    var queued = false;
    var observer = new MutationObserver(function (changes) {
      if (queued) return;
      for (var i = 0; i < changes.length; i += 1) {
        if (changes[i].addedNodes && changes[i].addedNodes.length) {
          queued = true;
          window.requestAnimationFrame(function () {
            queued = false;
            init(document);
          });
          break;
        }
      }
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeSidebars();
  });

  window.addEventListener('resize', function () {
    if (!isMobile()) closeSidebars();
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      init(document);
      observeDynamicContent();
    });
  } else {
    init(document);
    observeDynamicContent();
  }
})();
