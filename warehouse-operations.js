/* ========================================================================
   Brew & Co. Warehouse Operations UI — Batch 5
   Progressive enhancement for warehouse, QC, transfer, request and stocktake
   screens. Existing server-side movement, QC and approval actions are untouched.
   ======================================================================== */
(function () {
  'use strict';

  function all(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }

  function isWarehouseScreen() {
    var path = (window.location.pathname || '').toLowerCase();
    var title = (document.title || '').toLowerCase();
    return /warehouse|stocktake|inventory-manager|inventory-clerk/.test(path) || /warehouse|stocktaking|inventory manager|inventory clerk/.test(title);
  }

  function markShell() {
    if (!isWarehouseScreen()) return false;
    document.body.classList.add('bc-warehouse-shell');
    return true;
  }

  function formNeedsWarehouseStyle(form) {
    var section = form.closest('#view-receive, #view-transfer, #view-requests, #view-qc, #view-ship, #view-request');
    if (section && form.querySelector('.field')) return true;
    return /stocktake\.php/.test(form.getAttribute('action') || '') && !!form.querySelector('.field');
  }

  function enhanceForms(root) {
    all('form', root).forEach(function (form) {
      if (formNeedsWarehouseStyle(form)) form.classList.add('bc-warehouse-form');
    });
  }

  function labelRows(table) {
    var heads = all('thead th', table).map(function (head) {
      return (head.textContent || '').trim().replace(/\s+/g, ' ') || 'Detail';
    });
    if (!heads.length) return;
    all('tbody tr', table).forEach(function (row) {
      all(':scope > td', row).forEach(function (cell, index) {
        cell.setAttribute('data-warehouse-label', heads[index] || 'Detail');
      });
    });
  }

  function stackTables(root) {
    var selectors = [
      '#view-receive table', '#view-transfer table', '#view-requests table',
      '#view-qc table', '#view-ship table', '#view-request table'
    ].join(',');
    all(selectors, root).forEach(function (table) {
      if (table.dataset.warehouseStack) return;
      table.dataset.warehouseStack = 'true';
      table.classList.add('warehouse-stack-table');
      labelRows(table);
    });

    all('.panel', root).forEach(function (panel) {
      var title = panel.querySelector(':scope > h2');
      if (!title || !/count sheet/i.test(title.textContent || '')) return;
      var table = panel.querySelector('table');
      if (!table || table.dataset.warehouseStack) return;
      table.dataset.warehouseStack = 'true';
      table.classList.add('warehouse-stack-table');
      panel.classList.add('bc-stocktake-sheet');
      labelRows(table);
    });
  }

  function flowStep(label, state) {
    var node = document.createElement('li');
    node.textContent = label;
    if (state) node.classList.add(state);
    return node;
  }

  function addFlow(panel, type) {
    if (!panel || panel.querySelector('.warehouse-flow')) return;
    var desc = panel.querySelector(':scope > .desc');
    var flow = document.createElement('ol');
    flow.className = 'warehouse-flow';
    flow.setAttribute('aria-label', 'Warehouse workflow');
    var steps;
    if (type === 'receiving') {
      steps = [['Delivery', 'is-done'], ['Quality check', 'is-current'], ['Receive', ''], ['Warehouse stock', '']];
    } else if (type === 'transfer') {
      steps = [['Clerk request', 'is-done'], ['Approve', 'is-done'], ['Transfer', 'is-current'], ['Cafe stock', '']];
    } else if (type === 'count') {
      steps = [['Start count', 'is-done'], ['Enter counts', 'is-current'], ['Review variance', ''], ['Finalize adjustment', '']];
    } else {
      steps = [['Request', 'is-current'], ['Manager gate', ''], ['Warehouse release', ''], ['Cafe stock', '']];
    }
    flow.style.setProperty('--warehouse-steps', String(steps.length));
    steps.forEach(function (step) { flow.appendChild(flowStep(step[0], step[1])); });
    if (desc) desc.insertAdjacentElement('afterend', flow);
    else panel.insertBefore(flow, panel.firstChild);
  }

  function addWorkflowContext(root) {
    all('.panel', root).forEach(function (panel) {
      var heading = panel.querySelector(':scope > h2');
      var label = heading ? (heading.textContent || '').trim().toLowerCase() : '';
      if (/pending deliveries|quality verification/.test(label)) addFlow(panel, 'receiving');
      else if (/transfer warehouse|stock requests from clerks/.test(label)) addFlow(panel, 'transfer');
      else if (/request stock from warehouse/.test(label)) addFlow(panel, 'request');
      else if (/count sheet/.test(label)) { panel.classList.add('bc-stocktake-sheet'); addFlow(panel, 'count'); }
    });
  }

  function init(root) {
    if (!markShell()) return;
    var scope = root || document;
    enhanceForms(scope);
    stackTables(scope);
    addWorkflowContext(scope);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(document); });
  else init(document);
})();
