/* ========================================================================
   Brew & Co. HRMS UI — Batch 7
   Additive, client-side responsive helpers. No HRMS form, API, approval,
   attendance, scheduling, request, applicant or payroll data is changed.
   ======================================================================== */
(function () {
  'use strict';

  function all(selector, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(selector));
  }

  function cleanLabel(value, index) {
    var label = String(value || '').replace(/\s+/g, ' ').trim();
    return label || ('Field ' + (index + 1));
  }

  function markPage() {
    var body = document.body;
    if (!body) return;
    body.classList.add('bc-hrms-responsive');
    body.classList.add(document.querySelector('.sidebar') ? 'bc-hrms-shell' : 'bc-hrms-page');
  }

  function labelTable(table) {
    if (!table) return;
    var headers = all('thead th', table).map(function (header, index) {
      return cleanLabel(header.textContent, index);
    });
    if (!headers.length) return;

    if (table.dataset.hrmsResponsiveTable !== 'true') {
      table.dataset.hrmsResponsiveTable = 'true';
      /* Seven or fewer fields can become a readable mobile record. Wider HR
         reports (attendance, schedules, audit trails) retain scroll access. */
      table.classList.add(headers.length <= 7 ? 'bc-hrms-card-table' : 'bc-hrms-wide-table');
    }

    /* Lists are often injected after fetch calls. Revisit existing tables so
       fresh employee, request, or applicant rows receive their field labels. */
    all('tbody tr', table).forEach(function (row) {
      all(':scope > td', row).forEach(function (cell, index) {
        if (!cell.hasAttribute('data-hrms-label') && !cell.hasAttribute('colspan')) {
          cell.setAttribute('data-hrms-label', headers[index] || ('Field ' + (index + 1)));
        }
      });
    });
  }

  function enhanceTables(root) {
    all('table', root || document).forEach(labelTable);
  }

  function enhanceDialogs(root) {
    all('.modal, .detail-card, .sig-box, .kiosk-modal-box', root || document).forEach(function (dialog) {
      if (dialog.dataset.hrmsDialog) return;
      dialog.dataset.hrmsDialog = 'true';
      dialog.setAttribute('role', dialog.getAttribute('role') || 'dialog');
      dialog.setAttribute('aria-modal', dialog.getAttribute('aria-modal') || 'true');
      if (!dialog.getAttribute('aria-label') && !dialog.getAttribute('aria-labelledby')) {
        var heading = dialog.querySelector('h1, h2, h3');
        if (heading) {
          if (!heading.id) heading.id = 'hrms-dialog-title-' + Math.random().toString(36).slice(2, 9);
          dialog.setAttribute('aria-labelledby', heading.id);
        } else {
          dialog.setAttribute('aria-label', 'HRMS dialog');
        }
      }
    });
  }

  function setCurrentLink(link) {
    var nav = link.closest('.sidebar-nav, nav');
    if (!nav) return;
    all('a', nav).forEach(function (item) {
      if (item === link) item.setAttribute('aria-current', 'page');
      else item.removeAttribute('aria-current');
    });
  }

  function enhanceNavigation(root) {
    all('.sidebar-nav a, nav a', root || document).forEach(function (link) {
      if (link.dataset.hrmsNavBound) return;
      link.dataset.hrmsNavBound = 'true';
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
