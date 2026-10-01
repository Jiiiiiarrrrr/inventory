/* ========================================================================
   Brew & Co. Inventory UI — Batch 3
   Progressive enhancement for inventory, warehouse and stock-control screens.
   No database/API/PHP behaviours are changed.
   ======================================================================== */
(function () {
  'use strict';

  function all(selector, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(selector));
  }

  function inventoryPage() {
    var path = (window.location.pathname || '').toLowerCase();
    var title = (document.title || '').toLowerCase();
    return /inventory|warehouse|stocktake|expiry|returns|report/.test(path + ' ' + title) ||
      !!document.querySelector('.rec-line, #newRec, #editRec');
  }

  function markShell() {
    if (!inventoryPage()) return false;
    document.body.classList.add('bc-inventory-shell');
    return true;
  }

  function formHasFields(form) {
    return !!form.querySelector('.field, .seg');
  }

  function updateFieldState(field) {
    var control = field.querySelector('input:not([type="hidden"]), select, textarea');
    if (!control) return;
    var filled = control.type === 'checkbox' || control.type === 'radio' ? control.checked : String(control.value || '').trim() !== '';
    field.classList.toggle('is-filled', filled);
  }

  function enhanceForms(root) {
    all('form', root).forEach(function (form) {
      if (!formHasFields(form)) return;
      form.classList.add('bc-inventory-form');
      all('.field', form).forEach(function (field) {
        if (field.dataset.inventoryField) return;
        field.dataset.inventoryField = 'true';
        updateFieldState(field);
        all('input, select, textarea', field).forEach(function (control) {
          control.addEventListener('focus', function () { field.classList.add('is-active'); });
          control.addEventListener('blur', function () { field.classList.remove('is-active'); updateFieldState(field); });
          control.addEventListener('input', function () { updateFieldState(field); });
          control.addEventListener('change', function () { updateFieldState(field); });
        });
      });
    });
  }

  function syncSegment(segment) {
    all('label', segment).forEach(function (label) {
      var input = label.querySelector('input[type="radio"], input[type="checkbox"]');
      label.classList.toggle('is-selected', !!(input && input.checked));
    });
  }

  function enhanceSegments(root) {
    all('.seg', root).forEach(function (segment) {
      if (segment.dataset.inventorySegment) return;
      segment.dataset.inventorySegment = 'true';
      syncSegment(segment);
      all('input', segment).forEach(function (input) {
        input.addEventListener('change', function () { syncSegment(segment); });
      });
    });
  }

  function tableLabels(table) {
    var headings = all('thead th', table).map(function (head) {
      return (head.textContent || '').trim().replace(/\s+/g, ' ') || 'Detail';
    });
    if (!headings.length) return;
    all('tbody tr', table).forEach(function (row) {
      all(':scope > td', row).forEach(function (cell, index) {
        cell.setAttribute('data-inventory-label', headings[index] || 'Detail');
      });
    });
  }

  function enhanceStockTables(root) {
    all('#stockTbl, #wsTbl, #view-items table, #sellerTbl', root).forEach(function (table) {
      if (table.dataset.inventoryStack) return;
      table.dataset.inventoryStack = 'true';
      table.classList.add('inventory-stack-table');
      tableLabels(table);
    });
  }

  function classifyQuickActions(root) {
    all('.panel', root).forEach(function (panel) {
      var heading = panel.querySelector(':scope > h2');
      if (!heading || !/quick actions/i.test(heading.textContent || '')) return;
      var candidate = all(':scope > div', panel).filter(function (div) {
        return div.querySelectorAll(':scope > button.btn-primary').length >= 2;
      })[0];
      if (candidate) candidate.classList.add('inventory-quick-actions');
    });

    all('.panel', root).forEach(function (panel) {
      var heading = panel.querySelector(':scope > h2');
      if (!heading || !/low stock|needs attention/i.test(heading.textContent || '')) return;
      all('div', panel).forEach(function (item) {
        var style = item.getAttribute('style') || '';
        if (/justify-content:\s*space-between/i.test(style) && item.querySelector('.pill')) item.classList.add('inventory-alert-item');
      });
      var items = all('.inventory-alert-item', panel);
      if (items.length) {
        var parent = items[0].parentElement;
        if (parent) parent.classList.add('inventory-alert-list');
      }
    });
  }

  function init(root) {
    if (!markShell()) return;
    var scope = root || document;
    enhanceForms(scope);
    enhanceSegments(scope);
    enhanceStockTables(scope);
    classifyQuickActions(scope);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { init(document); });
  } else {
    init(document);
  }
})();
