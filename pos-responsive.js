/* ========================================================================
   Brew & Co. POS UI — Batch 6
   Progressive enhancement for POS browsing, cart access, cashier, kiosk and
   menu-management screens. Does not change prices, cart data, payment or APIs.
   ======================================================================== */
(function () {
  'use strict';

  function all(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }

  function isPosPage() {
    var path = (window.location.pathname || '').toLowerCase();
    var title = (document.title || '').toLowerCase();
    return /\/pos(?:\/|$)|pos-client|pos-cashier|pos-admin/.test(path) || /\bpos\b|cashier|menu management|self-order|order$/.test(title) || !!document.querySelector('.kiosk, .order-sidebar, .griditems');
  }

  function markShell() {
    if (!isPosPage()) return false;
    document.body.classList.add('bc-pos-responsive');
    return true;
  }

  function enhanceProductCards(root) {
    var selectors = [
      '.item-card[onclick]',
      '.kiosk-item[onclick]',
      '.griditems .card[onclick]'
    ].join(',');
    all(selectors, root).forEach(function (card) {
      if (card.dataset.posKeyboard) return;
      card.dataset.posKeyboard = 'true';
      card.setAttribute('tabindex', '0');
      card.setAttribute('role', 'button');
      if (!card.getAttribute('aria-label')) {
        var label = (card.textContent || '').trim().replace(/\s+/g, ' ');
        card.setAttribute('aria-label', label || 'Add item to order');
      }
      card.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          card.click();
        }
      });
    });
  }

  function markCarts(root) {
    all('.order-sidebar, .order, .kiosk-cart', root).forEach(function (cart) {
      cart.classList.add('bc-pos-cart');
      if (!cart.getAttribute('aria-label')) cart.setAttribute('aria-label', 'Current order');
    });
  }

  function updateNavState(link) {
    var scope = link.closest('.nav-links, .pos-nav');
    if (!scope) return;
    all('a', scope).forEach(function (item) {
      item.classList.toggle('active', item === link);
      if (item === link) item.setAttribute('aria-current', 'page');
      else item.removeAttribute('aria-current');
    });
  }

  function enhanceNavigation(root) {
    all('.nav-links a, .pos-nav a', root).forEach(function (link) {
      if (link.dataset.posNav) return;
      link.dataset.posNav = 'true';
      link.addEventListener('click', function () {
        window.setTimeout(function () { updateNavState(link); }, 0);
      });
    });
  }

  function cartItemCount() {
    var visible = all('.order-line, .oline, .cart-row').filter(function (row) {
      return row.offsetParent !== null;
    });
    return visible.length;
  }

  function addCartShortcut() {
    var cart = document.querySelector('.order-sidebar');
    if (!cart || document.querySelector('.cart-toggle-bar, .bc-pos-cart-toggle')) return;
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'bc-pos-cart-toggle';
    button.setAttribute('aria-controls', cart.id || '');
    if (!cart.id) cart.id = 'pos-current-order';
    button.setAttribute('aria-controls', cart.id);

    function refresh() {
      var count = cartItemCount();
      button.textContent = count ? '🛒 View order · ' + count + (count === 1 ? ' item' : ' items') : '🛒 View order';
    }
    button.addEventListener('click', function () {
      cart.scrollIntoView({ behavior: 'smooth', block: 'start' });
      var first = cart.querySelector('button, input, [tabindex]');
      if (first) window.setTimeout(function () { first.focus({ preventScroll: true }); }, 450);
    });
    refresh();
    document.body.appendChild(button);

    if (window.MutationObserver) {
      new MutationObserver(refresh).observe(cart, { childList: true, subtree: true, characterData: true });
    }
  }

  function observeDynamicProducts() {
    if (!window.MutationObserver) return;
    var grids = all('#category-items-grid, #items-full-grid, .items-grid, .kiosk-grid, .griditems');
    grids.forEach(function (grid) {
      new MutationObserver(function () { enhanceProductCards(grid); }).observe(grid, { childList: true, subtree: true });
    });
  }

  function init(root) {
    if (!markShell()) return;
    var scope = root || document;
    enhanceProductCards(scope);
    markCarts(scope);
    enhanceNavigation(scope);
    addCartShortcut();
    observeDynamicProducts();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(document); });
  else init(document);
})();
