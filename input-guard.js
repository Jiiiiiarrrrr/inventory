/* =====================================================================
   input-guard.js — global input limits & value guards.
   1) Text inputs / textareas are capped at 500 characters.
   2) Number inputs cannot be negative (blocks typing "-" and clamps
      invalid pasted values to 0).
   3) QUANTITY fields are capped at a MAX VALUE of 500 (e.g. you can't
      enter 999...). Quantity fields are identified by their `name`.
      Cost / price / reorder / rating fields are NOT limited.
   Apply to any form; runs automatically on page load and for dynamically
   added inputs via MutationObserver.
   ===================================================================== */
(function () {
  var MAX_LEN = 500;        // text character limit
  var MAX_QTY = 500;        // max value for quantity fields
  var QTY_NAMES = ['qty', 'iqty', 'recv_qty', 'ship_qty', 'quantity', 'count'];

  function isQtyField(el) {
    var name = (el.getAttribute('name') || '').toLowerCase();
    return QTY_NAMES.indexOf(name) !== -1;
  }

  function guardInput(el) {
    if (el.dataset.guarded) return;
    el.dataset.guarded = '1';

    var type = (el.getAttribute('type') || 'text').toLowerCase();
    var isText = (type === 'text' || type === 'email' || type === 'search' ||
                  type === 'password' || type === 'tel' || type === 'url' || type === '');
    var tag = el.tagName.toLowerCase();

    // Cap text-like fields at 500 characters
    if (isText || tag === 'textarea') {
      if (!el.maxLength || parseInt(el.maxLength, 10) > MAX_LEN) el.maxLength = MAX_LEN;
      el.addEventListener('input', function () {
        if (el.value.length > MAX_LEN) el.value = el.value.slice(0, MAX_LEN);
      });
    }

    if (type === 'number' || type === 'range') {
      // No negatives
      if (el.getAttribute('min') === null) el.setAttribute('min', '0');
      el.addEventListener('keydown', function (e) {
        if (e.key === '-' || e.key === 'Subtract') e.preventDefault();
      });

      // Cap QUANTITY fields at MAX_QTY value
      if (isQtyField(el)) {
        if (!el.max || parseInt(el.max, 10) > MAX_QTY) el.setAttribute('max', MAX_QTY);
        el.addEventListener('input', function () {
          if (el.value !== '' && parseFloat(el.value) > MAX_QTY) el.value = MAX_QTY;
        });
        el.addEventListener('blur', function () {
          if (el.value !== '' && parseFloat(el.value) > MAX_QTY) el.value = MAX_QTY;
        });
      }

      // Clamp negatives to 0
      var clampNeg = function () {
        if (el.value !== '' && parseFloat(el.value) < 0) el.value = 0;
      };
      el.addEventListener('input', clampNeg);
      el.addEventListener('blur', clampNeg);
      el.addEventListener('wheel', function () { this.blur(); });
    }
  }

  function init() {
    document.querySelectorAll('input, textarea').forEach(guardInput);
    if (window.MutationObserver) {
      var mo = new MutationObserver(function () {
        document.querySelectorAll('input, textarea').forEach(guardInput);
      });
      mo.observe(document.body, { childList: true, subtree: true });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
