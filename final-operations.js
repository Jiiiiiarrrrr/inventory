/* ========================================================================
   Brew & Co. Final Operations UI — Batch 8
   Progressive responsive helpers for payroll, reports and administration.
   Does not submit, approve, reject, calculate or otherwise modify records.
   ======================================================================== */
(function () {
  'use strict';

  function all(selector, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(selector));
  }

  function labelText(value, index) {
    var label = String(value || '').replace(/\s+/g, ' ').trim();
    return label || ('Field ' + (index + 1));
  }

  function markPage() {
    if (document.body) document.body.classList.add('bc-final-responsive');
  }

  function enhanceTable(table) {
    if (!table) return;
    var headers = all('thead th', table).map(function (header, index) {
      return labelText(header.textContent, index);
    });
    if (!headers.length) return;

    if (table.dataset.finalResponsiveTable !== 'true') {
      table.dataset.finalResponsiveTable = 'true';
      /* Payroll, audit, report and account lists of up to seven fields remain
         readable as records on phones. Wider financial reports keep scrolling. */
      table.classList.add(headers.length <= 7 ? 'bc-final-card-table' : 'bc-final-wide-table');
    }

    all('tbody tr', table).forEach(function (row) {
      all(':scope > td', row).forEach(function (cell, index) {
        if (!cell.hasAttribute('data-final-label') && !cell.hasAttribute('colspan')) {
          cell.setAttribute('data-final-label', headers[index] || ('Field ' + (index + 1)));
        }
      });
    });
  }

  function enhanceTables(root) {
    all('table', root || document).forEach(enhanceTable);
  }

  function enhanceDialogs(root) {
    all('.modal, .modal-bg > div, .modal-backdrop > div', root || document).forEach(function (dialog) {
      if (dialog.dataset.finalDialog) return;
      dialog.dataset.finalDialog = 'true';
      dialog.setAttribute('role', dialog.getAttribute('role') || 'dialog');
      dialog.setAttribute('aria-modal', dialog.getAttribute('aria-modal') || 'true');
      if (!dialog.getAttribute('aria-label') && !dialog.getAttribute('aria-labelledby')) {
        var heading = dialog.querySelector('h1, h2, h3');
        if (heading) {
          if (!heading.id) heading.id = 'final-dialog-title-' + Math.random().toString(36).slice(2, 9);
          dialog.setAttribute('aria-labelledby', heading.id);
        } else {
          dialog.setAttribute('aria-label', 'Dialog');
        }
      }
    });
  }

  function setCurrentLink(link) {
    var nav = link.closest('.nav, .sidebar-nav, nav');
    if (!nav) return;
    all('a', nav).forEach(function (item) {
      if (item === link) item.setAttribute('aria-current', 'page');
      else item.removeAttribute('aria-current');
    });
  }

  function enhanceNavigation(root) {
    all('.nav a, .sidebar-nav a, nav a', root || document).forEach(function (link) {
      if (link.dataset.finalNavBound) return;
      link.dataset.finalNavBound = 'true';
      if (link.classList.contains('active')) link.setAttribute('aria-current', 'page');
      link.addEventListener('click', function () {
        window.setTimeout(function () { setCurrentLink(link); }, 0);
      });
    });
  }

  function init(root) {
    markPage();
    enhanceTables(root || document);
    enhanceDialogs(root || document);
    enhanceNavigation(root || document);
  }

  function observeDynamicContent() {
    if (!window.MutationObserver || !document.body) return;
    var queued = false;
    new MutationObserver(function (changes) {
      if (queued || !changes.some(function (change) { return change.addedNodes && change.addedNodes.length; })) return;
      queued = true;
      window.requestAnimationFrame(function () {
        queued = false;
        init(document);
      });
    }).observe(document.body, { childList: true, subtree: true });
  }

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
