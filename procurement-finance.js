/* ========================================================================
   Brew & Co. Procurement & Finance UI — Batch 4
   Progressive enhancement for procurement request, supplier and finance views.
   No calculations, approvals, permissions or network requests are modified.
   ======================================================================== */
(function () {
  'use strict';

  function all(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }

  function isProcurementScreen() {
    var path = (window.location.pathname || '').toLowerCase();
    var title = (document.title || '').toLowerCase();
    return /inventory-manager|finance-dashboard|reports/.test(path) || /inventory manager|finance dashboard|profitability/.test(title);
  }

  function markShell() {
    if (!isProcurementScreen()) return false;
    document.body.classList.add('bc-procurement-shell');
    return true;
  }

  function formNeedsProcurementStyle(form) {
    if (form.id === 'prForm') return true;
    var section = form.closest('#view-procure, #view-sellers, #view-ship, #view-budget, #view-prices');
    return !!(section && form.querySelector('.field'));
  }

  function enhanceForms(root) {
    all('form', root).forEach(function (form) {
      if (formNeedsProcurementStyle(form)) form.classList.add('bc-procurement-form');
    });
  }

  function labelRows(table) {
    var heads = all('thead th', table).map(function (cell) {
      return (cell.textContent || '').trim().replace(/\s+/g, ' ') || 'Detail';
    });
    if (!heads.length) return;
    all('tbody tr', table).forEach(function (row) {
      all(':scope > td', row).forEach(function (cell, index) {
        cell.setAttribute('data-procurement-label', heads[index] || 'Detail');
      });
    });
  }

  function stackTables(root) {
    var selectors = [
      '#view-procure table',
      '#view-sellers table',
      '#view-approvals table',
      '#view-prices table',
      '#view-budget table'
    ].join(',');
    all(selectors, root).forEach(function (table) {
      if (table.dataset.procurementStack) return;
      table.dataset.procurementStack = 'true';
      table.classList.add('procurement-stack-table');
      labelRows(table);
    });
  }

  function flowStep(label, state) {
    var li = document.createElement('li');
    li.textContent = label;
    if (state) li.classList.add(state);
    return li;
  }

  function addFlow(panel, kind) {
    if (!panel || panel.querySelector('.procurement-flow')) return;
    var description = panel.querySelector(':scope > .desc');
    var flow = document.createElement('ol');
    flow.className = 'procurement-flow';
    flow.setAttribute('aria-label', 'Procurement workflow');
    if (kind === 'request') {
      flow.appendChild(flowStep('Request', 'is-current'));
      flow.appendChild(flowStep('Finance review'));
      flow.appendChild(flowStep('Shipment'));
      flow.appendChild(flowStep('Quality check'));
      flow.appendChild(flowStep('Receive to stock'));
    } else {
      flow.appendChild(flowStep('Request', 'is-done'));
      flow.appendChild(flowStep('Finance review', 'is-current'));
      flow.appendChild(flowStep('Shipment'));
      flow.appendChild(flowStep('Quality check'));
      flow.appendChild(flowStep('Receive to stock'));
    }
    if (description) description.insertAdjacentElement('afterend', flow);
    else panel.insertBefore(flow, panel.firstChild);
  }

  function addWorkflowContext(root) {
    all('.panel', root).forEach(function (panel) {
      var title = panel.querySelector(':scope > h2');
      var text = title ? (title.textContent || '').trim().toLowerCase() : '';
      if (text === 'purchase requests' || text === 'new purchase request') addFlow(panel, 'request');
      if (text === 'budget approvals') addFlow(panel, 'approval');
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
