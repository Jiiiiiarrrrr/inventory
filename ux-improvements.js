/* =====================================================================
   ux-improvements.js — Enhanced UX behaviors for Brew & Co.
   Loading spinners, collapsible panels, better empty states
   ===================================================================== */
(function () {
  /* ---- Loading spinner on form submit ----
     Changes button text to "Saving..." on submit.
     If the form is still in the DOM after 500ms (i.e. submission was
     prevented by validation), reverts the button so the user can fix
     errors and retry.                                         */
  function addLoadingToForms() {
    document.querySelectorAll('form').forEach(function (form) {
      if (form.dataset.uxLoading) return;
      form.dataset.uxLoading = '1';
      form.addEventListener('submit', function () {
        var btn = form.querySelector('button[type="submit"]');
        if (!btn || btn.dataset.loading) return;
        btn.dataset.loading = '1';
        btn.dataset.original = btn.innerHTML;
        btn.innerHTML = '<span class="spinner"></span> Saving...';
        /* If form is still in the DOM after 500ms, submission was
           prevented by client-side validation — revert the button so
           the user can fix the error and try again. */
        setTimeout(function () {
          if (document.body.contains(form)) {
            btn.innerHTML = btn.dataset.original || btn.innerHTML;
            btn.disabled = false;
            delete btn.dataset.loading;
            delete btn.dataset.original;
          }
        }, 500);
      });
    });
  }

  /* ---- Collapsible panels ---- */
  window.togglePanel = function (btn) {
    var panel = btn.closest('.panel');
    if (!panel) return;
    panel.classList.toggle('collapsed');
    btn.classList.toggle('collapsed');
    var svg = btn.querySelector('svg');
    if (svg) svg.style.transform = panel.classList.contains('collapsed') ? 'rotate(-90deg)' : '';
  };

  /* ---- Better number input UX ---- */
  function enhanceInputs() {
    document.querySelectorAll('input[type="number"]').forEach(function (el) {
      if (el.dataset.uxEnhanced) return;
      el.dataset.uxEnhanced = '1';
      /* Remove spinner arrows for cleaner look */
      el.style.mozAppearance = 'textfield';
    });
  }

  /* ---- Auto-focus first input on view switch ---- */
  window.autoFocusFirst = function (viewName) {
    setTimeout(function () {
      var view = document.getElementById('view-' + viewName);
      if (view) {
        var input = view.querySelector('input:not([type="hidden"]), select, textarea');
        if (input && !input.disabled) input.focus();
      }
    }, 300);
  };

  /* ---- Initialize ---- */
  function init() {
    addLoadingToForms();
    enhanceInputs();
    /* Observe for dynamically added forms */
    if (window.MutationObserver) {
      var mo = new MutationObserver(function () {
        addLoadingToForms();
        enhanceInputs();
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
