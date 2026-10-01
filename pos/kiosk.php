<?php
/**
 * Kiosk — standalone customer self-order station.
 * No login required. Orders placed here are created as Pending (unpaid) via
 * api/orders.php, and appear in the Cashier's "Pending orders" queue for
 * payment collection. Stock is NOT deducted here — only once the Cashier
 * collects payment.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="../favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="../favicon.ico?v=2">
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <title>Kiosk — Brew & Co.</title>
  <link rel="stylesheet" href="css/style.css" />
  <style>
    /* ============================================================
       KIOSK — standalone touch-screen ordering UI
       Reuses the POS brand tokens (--dark-brown, --cream-bg, --accent,
       --warm-beige, --card-bg …) from style.css, but every rule here is
       scoped under body.kiosk so it never leaks into the staff app.
       ============================================================ */

    body.kiosk {
      background: var(--cream-bg);
      height: 100vh;
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }

    /* ---------- Header ---------- */
    .kiosk-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 18px 32px;
      background: var(--dark-brown);
      flex-shrink: 0;
      box-shadow: 0 2px 10px rgba(0,0,0,.25);
      z-index: 5;
    }
    .kiosk-header .logo { display: flex; align-items: center; gap: 12px; }
    .kiosk-header .logo .mark {
      width: 46px; height: 46px; border-radius: 50%;
      background: var(--warm-beige);
      display: flex; align-items: center; justify-content: center;
      font-size: 22px; flex-shrink: 0;
      overflow: hidden;
    }
    .kiosk-header .logo .mark img { width: 100%; height: 100%; object-fit: cover; }
    .kiosk-header .logo .title-block { line-height: 1.15; }
    .kiosk-header .logo .brand { font-family: Georgia, 'Times New Roman', serif; font-size: 1.4rem; font-weight: 700; color: var(--cream-bg); }
    .kiosk-header .logo .tag { font-size: .78rem; color: var(--warm-beige); letter-spacing: .01em; }
    .kiosk-header .help-pill {
      display: flex; align-items: center; gap: 8px;
      border: 1.5px solid rgba(237,224,196,.35);
      color: var(--cream-bg);
      padding: 8px 16px; border-radius: 30px;
      font-size: .85rem;
    }
    .kiosk-header .help-pill .dot {
      width: 8px; height: 8px; border-radius: 50%; background: var(--success);
      box-shadow: 0 0 0 3px rgba(39,174,96,.25);
    }

    /* ---------- Body layout: rail | items | cart ---------- */
    .kiosk-body {
      flex: 1;
      display: grid;
      grid-template-columns: 128px 1fr 340px;
      min-height: 0;
    }

    /* ---------- Category rail ---------- */
    .kiosk-rail {
      background: var(--card-bg);
      border-right: 1px solid var(--warm-beige);
      padding: 18px 10px;
      display: flex; flex-direction: column; gap: 8px;
      overflow-y: auto;
    }
    .rail-btn {
      display: flex; flex-direction: column; align-items: center; gap: 6px;
      padding: 14px 6px 12px;
      border: none; background: transparent; color: var(--text-muted);
      border-radius: 14px; cursor: pointer;
      font-size: .74rem; font-weight: 700;
      transition: background .15s, color .15s, transform .1s;
      line-height: 1.15;
    }
    .rail-btn .rail-icon { font-size: 1.7rem; }
    .rail-btn:active { transform: scale(.95); }
    .rail-btn:hover { background: rgba(196,154,90,.18); color: var(--dark-brown); }
    .rail-btn.active {
      background: var(--dark-brown); color: var(--cream-bg);
      box-shadow: 0 4px 14px rgba(44,26,14,.28);
    }

    /* ---------- Items area ---------- */
    .kiosk-items-wrap { padding: 22px 26px; overflow-y: auto; min-height: 0; }
    .items-heading { display: flex; align-items: baseline; gap: 10px; margin-bottom: 16px; }
    .items-heading h2 {
      font-family: Georgia, 'Times New Roman', serif;
      font-size: 1.5rem; color: var(--dark-brown); font-weight: 700;
    }
    .items-heading .count { font-size: .82rem; color: var(--text-muted); }

    .kiosk-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(168px, 1fr));
      gap: 16px;
    }

    .kiosk-item {
      position: relative;
      background: var(--white);
      border: 1.5px solid var(--warm-beige);
      border-radius: 16px;
      padding: 14px 14px 16px;
      cursor: pointer;
      text-align: left;
      transition: transform .12s, box-shadow .12s, border-color .12s;
      display: flex; flex-direction: column;
    }
    .kiosk-item:hover { border-color: var(--accent); box-shadow: 0 8px 20px rgba(44,26,14,.12); transform: translateY(-2px); }
    .kiosk-item:active { transform: translateY(0) scale(.98); }

    .kiosk-item .thumb {
      width: 100%; aspect-ratio: 1 / 1;
      border-radius: 12px;
      background: var(--card-bg);
      display: flex; align-items: center; justify-content: center;
      font-size: 2.6rem;
      margin-bottom: 10px;
      overflow: hidden;
      position: relative;
    }
    .kiosk-item .thumb img { width: 100%; height: 100%; object-fit: cover; }

    .kiosk-item .qty-chip {
      position: absolute; top: 10px; right: 10px;
      background: var(--dark-brown); color: var(--cream-bg);
      min-width: 26px; height: 26px; border-radius: 50%;
      font-size: .82rem; font-weight: 800;
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 2px 6px rgba(0,0,0,.25);
      z-index: 2;
    }

    .kiosk-item .name { font-size: .92rem; font-weight: 700; color: var(--dark-brown); margin-bottom: 2px; }
    .kiosk-item .price { font-size: .9rem; color: var(--accent); font-weight: 700; }

    .kiosk-item .stock-note { margin-top: 6px; font-size: .72rem; font-weight: 700; }
    .kiosk-item .stock-note.low { color: #b45309; }

    .kiosk-item.out {
      cursor: not-allowed; background: #F4F1EA;
    }
    .kiosk-item.out:hover { transform: none; box-shadow: none; border-color: var(--warm-beige); }
    .kiosk-item.out .thumb, .kiosk-item.out .name { filter: grayscale(1); opacity: .55; }
    .kiosk-item.out .price { opacity: .45; }
    .kiosk-item.out .out-tag {
      position: absolute; inset: 0;
      display: flex; align-items: center; justify-content: center;
      background: rgba(240,232,213,.55);
      border-radius: 16px;
    }
    .kiosk-item.out .out-tag span {
      background: var(--dark-brown); color: var(--cream-bg);
      font-size: .72rem; font-weight: 800; letter-spacing: .02em;
      padding: 5px 12px; border-radius: 20px;
    }

    .kiosk-empty-cat {
      grid-column: 1 / -1;
      text-align: center; padding: 60px 20px;
      color: var(--text-muted);
    }
    .kiosk-empty-cat .icon { font-size: 2.4rem; margin-bottom: 10px; }

    /* ---------- Cart panel ---------- */
    .kiosk-cart {
      background: var(--white);
      border-left: 1px solid var(--warm-beige);
      display: flex; flex-direction: column;
      min-height: 0;
    }
    .cart-head {
      padding: 20px 22px 14px;
      border-bottom: 1px dashed var(--warm-beige);
      flex-shrink: 0;
    }
    .cart-head h3 {
      font-family: Georgia, serif; font-size: 1.15rem; color: var(--dark-brown);
      display: flex; align-items: center; gap: 8px;
    }
    .cart-head .sub { font-size: .78rem; color: var(--text-muted); margin-top: 2px; }

    .cart-list {
      flex: 1; overflow-y: auto;
      padding: 8px 16px;
      display: flex; flex-direction: column; gap: 4px;
    }
    .cart-empty {
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      height: 100%; text-align: center; color: var(--text-muted); padding: 20px;
      gap: 8px;
    }
    .cart-empty .icon { font-size: 2.2rem; opacity: .6; }
    .cart-empty .msg { font-size: .88rem; }

    .cart-row {
      display: grid;
      grid-template-columns: 1fr auto auto;
      align-items: center;
      gap: 10px;
      padding: 10px 6px;
      border-bottom: 1px solid #F3ECDB;
    }
    .cart-row .ci-name { font-size: .87rem; font-weight: 600; color: var(--text-dark); line-height: 1.3; }
    .cart-row .ci-unit { font-size: .74rem; color: var(--text-muted); }
    .cart-row .ci-qty {
      display: flex; align-items: center; gap: 6px;
    }
    .ci-qty button {
      width: 26px; height: 26px; border-radius: 50%;
      border: 1.5px solid var(--accent); background: transparent;
      color: var(--dark-brown); font-weight: 800; font-size: .95rem;
      cursor: pointer; display: flex; align-items: center; justify-content: center;
      transition: background .12s;
    }
    .ci-qty button:hover { background: var(--warm-beige); }
    .ci-qty .n { min-width: 16px; text-align: center; font-weight: 700; font-size: .85rem; }
    .cart-row .ci-total { font-size: .87rem; font-weight: 800; color: var(--dark-brown); text-align: right; min-width: 62px; }

    .cart-summary {
      flex-shrink: 0;
      padding: 16px 22px 20px;
      border-top: 1px solid var(--warm-beige);
      background: var(--card-bg);
    }
    .summary-row { display: flex; justify-content: space-between; font-size: .85rem; color: var(--text-muted); margin-bottom: 6px; }
    .summary-row.total { font-size: 1.15rem; font-weight: 800; color: var(--dark-brown); margin-top: 8px; padding-top: 10px; border-top: 1px dashed var(--warm-beige); margin-bottom: 14px; }
    .summary-row.total span:last-child { color: var(--accent); }

    .btn-place-order {
      width: 100%;
      background: var(--dark-brown);
      color: var(--cream-bg);
      border: none;
      padding: 15px;
      border-radius: 14px;
      font-size: 1rem;
      font-weight: 800;
      letter-spacing: .01em;
      cursor: pointer;
      transition: background .15s, transform .08s;
      display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .btn-place-order:hover { background: #3d2415; }
    .btn-place-order:active { transform: scale(.98); }
    .btn-place-order:disabled { opacity: .5; cursor: not-allowed; }

    /* ---------- Order type modal ---------- */
    .kiosk-modal-overlay {
      display: none;
      position: fixed; inset: 0; background: rgba(44,26,14,.55);
      align-items: center; justify-content: center; z-index: 9000; padding: 20px;
    }
    .kiosk-modal-overlay.active { display: flex; }
    .kiosk-modal-box {
      background: var(--white); border-radius: 22px; padding: 36px 32px;
      max-width: 420px; width: 100%; text-align: center;
      box-shadow: 0 24px 64px rgba(0,0,0,.35);
    }
    .kiosk-modal-box h3 { font-family: Georgia, serif; font-size: 1.35rem; color: var(--dark-brown); margin-bottom: 6px; }
    .kiosk-modal-box p { color: var(--text-muted); font-size: .88rem; margin-bottom: 22px; }
    .type-choice-row { display: flex; gap: 12px; }
    .type-choice {
      flex: 1;
      border: 2px solid var(--warm-beige);
      background: var(--cream-bg);
      border-radius: 16px;
      padding: 22px 10px 16px;
      cursor: pointer;
      display: flex; flex-direction: column; align-items: center; gap: 8px;
      transition: all .15s;
      font-weight: 700; color: var(--dark-brown); font-size: .92rem;
    }
    .type-choice .em { font-size: 2rem; }
    .type-choice:hover { border-color: var(--dark-brown); background: var(--warm-beige); transform: translateY(-2px); }
    .kiosk-modal-cancel {
      margin-top: 18px; background: none; border: none;
      color: var(--text-muted); font-size: .85rem; cursor: pointer; text-decoration: underline;
    }

    /* ---------- Confirmation screen ---------- */
    .kiosk-confirmation-wrap {
      flex: 1; display: flex; align-items: center; justify-content: center;
      padding: 30px;
    }
    .kiosk-ticket {
      background: var(--white);
      border-radius: 24px;
      max-width: 460px; width: 100%;
      box-shadow: 0 24px 64px rgba(44,26,14,.22);
      overflow: hidden;
      text-align: center;
      animation: ticketPop .35s cubic-bezier(.2,.9,.3,1.2);
    }
    @keyframes ticketPop { from { opacity: 0; transform: translateY(14px) scale(.97); } to { opacity: 1; transform: translateY(0) scale(1); } }
    .kiosk-ticket .ticket-top {
      background: var(--dark-brown); color: var(--cream-bg);
      padding: 34px 32px 26px;
    }
    .kiosk-ticket .check-badge {
      width: 62px; height: 62px; border-radius: 50%;
      background: var(--success); color: #fff;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.8rem; margin: 0 auto 14px;
      box-shadow: 0 0 0 6px rgba(39,174,96,.22);
    }
    .kiosk-ticket .ticket-top h2 { font-family: Georgia, serif; font-size: 1.5rem; margin-bottom: 4px; }
    .kiosk-ticket .ticket-top .sub { font-size: .85rem; color: var(--warm-beige); }
    .kiosk-ticket .ticket-body { padding: 28px 32px 34px; }
    .kiosk-ticket .order-code-label { font-size: .76rem; text-transform: uppercase; letter-spacing: .1em; color: var(--text-muted); margin-bottom: 6px; }
    .kiosk-ticket .order-code {
      font-family: Georgia, serif; font-size: 2.6rem; font-weight: 800; color: var(--dark-brown);
      letter-spacing: .02em; margin-bottom: 18px;
    }
    .kiosk-ticket .instruction {
      background: var(--card-bg); border-radius: 14px; padding: 14px 18px;
      font-size: .9rem; color: var(--dark-brown); margin-bottom: 24px; line-height: 1.5;
    }
    .kiosk-ticket .btn-new-order {
      width: 100%; background: var(--dark-brown); color: var(--cream-bg);
      border: none; padding: 14px; border-radius: 14px; font-weight: 800; font-size: .95rem;
      cursor: pointer; transition: background .15s;
    }
    .kiosk-ticket .btn-new-order:hover { background: #3d2415; }

    /* ---------- Scrollbars (kiosk touch feel) ---------- */
    .kiosk-rail::-webkit-scrollbar, .kiosk-items-wrap::-webkit-scrollbar, .cart-list::-webkit-scrollbar { width: 8px; }
    .kiosk-rail::-webkit-scrollbar-thumb, .kiosk-items-wrap::-webkit-scrollbar-thumb, .cart-list::-webkit-scrollbar-thumb {
      background: var(--warm-beige); border-radius: 8px;
    }

    /* ---------- Responsive (portrait / narrower kiosks) ---------- */
    @media (max-width: 900px) {
      .kiosk-body { grid-template-columns: 96px 1fr; }
      .kiosk-cart {
        position: fixed; right: 0; top: 0; bottom: 0; width: min(360px, 90vw);
        transform: translateX(100%); transition: transform .25s ease; z-index: 40;
        box-shadow: -8px 0 30px rgba(0,0,0,.25);
      }
      .kiosk-cart.open { transform: translateX(0); }
      .cart-toggle-bar {
        position: fixed; left: 0; right: 0; bottom: 0;
        background: var(--dark-brown); color: var(--cream-bg);
        padding: 14px 22px; display: flex; align-items: center; justify-content: space-between;
        z-index: 30; cursor: pointer; font-weight: 700;
      }
      .cart-toggle-bar .amt { color: var(--warm-beige); }
      .kiosk-items-wrap { padding-bottom: 90px; }
    }
    @media (min-width: 901px) { .cart-toggle-bar { display: none; } }
  </style>

<link rel="stylesheet" href="../responsive-ui.css">
<link rel="stylesheet" href="../dashboard-navigation.css">
<link rel="stylesheet" href="../pos-responsive.css">
</head>
<body class="kiosk">

  <div class="kiosk-header">
    <div class="logo">
      <div class="mark"><img src="../coffee-cup.png" alt="" onerror="this.parentElement.textContent='☕'" /></div>
      <div class="title-block">
        <div class="brand">Brew & Co.</div>
        <div class="tag">Self-order kiosk</div>
      </div>
    </div>
    <div class="help-pill"><span class="dot"></span> Tap items to add them to your order</div>
  </div>

  <div id="kiosk-order-page" style="flex:1; min-height:0; display:flex;">
    <div class="kiosk-body" style="flex:1;">
      <div class="kiosk-rail" id="category-tabs"></div>

      <div class="kiosk-items-wrap">
        <div class="items-heading">
          <h2 id="items-heading-title">All items</h2>
          <span class="count" id="items-heading-count"></span>
        </div>
        <div class="kiosk-grid" id="category-items-grid"></div>
      </div>

      <div class="kiosk-cart" id="kiosk-cart-panel">
        <div class="cart-head">
          <h3>🧾 Your order</h3>
          <div class="sub" id="cart-item-count">No items yet</div>
        </div>
        <div class="cart-list" id="order-items-list">
          <div class="cart-empty">
            <div class="icon">🛒</div>
            <div class="msg">Nothing here yet.<br>Tap an item to get started.</div>
          </div>
        </div>
        <div class="cart-summary">
          <div class="summary-row"><span>Subtotal</span><span id="order-subtotal">₱0</span></div>
          <div class="summary-row"><span>VAT (12%)</span><span id="order-vat">₱0</span></div>
          <div class="summary-row total"><span>Total</span><span id="order-total">₱0</span></div>
          <button class="btn-place-order" id="place-order-btn" onclick="kioskPlaceOrder()">Place order →</button>
        </div>
      </div>
    </div>
  </div>

  <div class="cart-toggle-bar" id="cart-toggle-bar" onclick="toggleKioskCart()">
    <span id="toggle-bar-label">🛒 View order</span>
    <span class="amt" id="toggle-bar-total">₱0</span>
  </div>

  <div id="kiosk-confirmation" style="display:none; flex:1;">
    <div class="kiosk-confirmation-wrap">
      <div class="kiosk-ticket">
        <div class="ticket-top">
          <div class="check-badge">✓</div>
          <h2>Order sent!</h2>
          <div class="sub">We've got it — thank you</div>
        </div>
        <div class="ticket-body">
          <div class="order-code-label">Order number</div>
          <div class="order-code" id="kiosk-order-code"></div>
          <div class="instruction">Please proceed to the cashier and give them your order number to pay.</div>
          <button class="btn-new-order" onclick="kioskStartNewOrder()">Start a new order</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Order type modal (Dine In / Take Out only — no payment here) -->
  <div class="kiosk-modal-overlay" id="kiosk-type-overlay">
    <div class="kiosk-modal-box">
      <h3>How will you have it?</h3>
      <p>Choose one to send your order to the cashier.</p>
      <div class="type-choice-row">
        <button class="type-choice" onclick="kioskConfirmType('Take Out')"><span class="em">🥡</span> Take Out</button>
        <button class="type-choice" onclick="kioskConfirmType('Dine In')"><span class="em">🍽️</span> Dine In</button>
      </div>
      <button class="kiosk-modal-cancel" onclick="kioskCloseTypeModal()">Cancel</button>
    </div>
  </div>

  <script src="js/swal-compat.js"></script>
  <script>
    // ===== KIOSK: minimal, self-contained ordering flow =====
    // Deliberately independent from app.js — the kiosk has no login, no admin
    // features, and a different checkout (no cash numpad, always ends Pending).
    const API = {
      items: 'api/items.php',
      categories: 'api/categories.php',
      orders: 'api/orders.php',
    };

    async function apiFetch(url, options = {}) {
      const res = await fetch(url, { credentials: 'same-origin', ...options });
      return res.json();
    }

    let allCategories = [];
    let menuItems = [];
    let currentOrder = []; // [{ id, name, emoji, price, qty }]
    let activeCategoryTab = 'All';

    function getCategoryNames() { return allCategories.map(c => c.name); }
    function getCatIcon(name) {
      const cat = allCategories.find(c => c.name === name);
      return cat ? cat.emoji : '🏷️';
    }

    async function loadCategories() {
      const data = await apiFetch(API.categories);
      if (data.success) allCategories = data.data;
    }

    async function loadMenuItems() {
      const data = await apiFetch(API.items);
      if (data.success) {
        menuItems = data.data.map(item => ({
          id: item.id,
          name: item.name,
          price: Number(item.price),
          emoji: item.emoji,
          image: item.image,
          category: item.category,
          stock: Number(item.stock),
          featured: !!Number(item.featured),
        }));
      }
    }

    function renderCategoryTabs() {
      const tabsEl = document.getElementById('category-tabs');
      const tabs = ['All', ...getCategoryNames()];
      tabsEl.innerHTML = tabs.map(tab => `
        <button class="rail-btn ${tab === activeCategoryTab ? 'active' : ''}" onclick="selectKioskTab('${tab}')">
          <span class="rail-icon">${tab === 'All' ? '🍴' : getCatIcon(tab)}</span>
          <span>${tab}</span>
        </button>
      `).join('');
    }

    function selectKioskTab(tab) {
      activeCategoryTab = tab;
      renderCategoryTabs();
      renderItemsForCategory(tab);
    }

    function renderItemsForCategory(tab) {
      const grid = document.getElementById('category-items-grid');
      const items = tab === 'All' ? menuItems : menuItems.filter(i => i.category === tab);

      document.getElementById('items-heading-title').textContent = tab === 'All' ? 'All items' : tab;
      document.getElementById('items-heading-count').textContent = items.length
        ? `${items.length} item${items.length === 1 ? '' : 's'}`
        : '';

      if (!items.length) {
        grid.innerHTML = `
          <div class="kiosk-empty-cat">
            <div class="icon">🍽️</div>
            <div>No items in this category yet.</div>
          </div>`;
        return;
      }

      grid.innerHTML = items.map(item => {
        const inCart = currentOrder.find(i => i.id === item.id);
        const outOfStock = item.stock <= 0;
        const lowStock = !outOfStock && item.stock <= 5;
        return `
          <div class="kiosk-item ${outOfStock ? 'out' : ''}" onclick="${outOfStock ? '' : `kioskAddToOrder(${item.id})`}">
            ${inCart ? `<div class="qty-chip">${inCart.qty}</div>` : ''}
            <div class="thumb">${item.image ? `<img src="${item.image}" alt="${item.name}">` : (item.emoji || '☕')}</div>
            <div class="name">${item.name}</div>
            <div class="price">₱${item.price.toLocaleString()}</div>
            ${lowStock ? `<div class="stock-note low">Only ${item.stock} left</div>` : ''}
            ${outOfStock ? `<div class="out-tag"><span>Out of stock</span></div>` : ''}
          </div>
        `;
      }).join('');
    }

    function kioskAddToOrder(itemId) {
      const item = menuItems.find(i => i.id === itemId);
      if (!item || item.stock <= 0) return;
      const existing = currentOrder.find(i => i.id === itemId);
      const currentQty = existing ? existing.qty : 0;
      if (currentQty + 1 > item.stock) {
        Swal.fire({ icon: 'warning', title: 'Not enough stock', text: `Only ${item.stock} left.`, confirmButtonColor: '#2C1A0E' });
        return;
      }
      if (existing) existing.qty += 1;
      else currentOrder.push({ id: item.id, name: item.name, emoji: item.emoji, price: item.price, qty: 1 });
      renderItemsForCategory(activeCategoryTab);
      renderOrderSidebar();
    }

    function kioskRemoveFromOrder(itemId) {
      const idx = currentOrder.findIndex(i => i.id === itemId);
      if (idx === -1) return;
      currentOrder[idx].qty -= 1;
      if (currentOrder[idx].qty <= 0) currentOrder.splice(idx, 1);
      renderItemsForCategory(activeCategoryTab);
      renderOrderSidebar();
    }

    function renderOrderSidebar() {
      const list = document.getElementById('order-items-list');
      const placeBtn = document.getElementById('place-order-btn');
      const totalQty = currentOrder.reduce((sum, i) => sum + i.qty, 0);

      if (!currentOrder.length) {
        list.innerHTML = `
          <div class="cart-empty">
            <div class="icon">🛒</div>
            <div class="msg">Nothing here yet.<br>Tap an item to get started.</div>
          </div>`;
        document.getElementById('cart-item-count').textContent = 'No items yet';
      } else {
        list.innerHTML = currentOrder.map(item => `
          <div class="cart-row">
            <div>
              <div class="ci-name">${item.emoji ? item.emoji + ' ' : ''}${item.name}</div>
              <div class="ci-unit">₱${item.price.toLocaleString()} each</div>
            </div>
            <div class="ci-qty">
              <button onclick="kioskRemoveFromOrder(${item.id})" aria-label="Remove one">−</button>
              <span class="n">${item.qty}</span>
              <button onclick="kioskAddToOrder(${item.id})" aria-label="Add one">+</button>
            </div>
            <div class="ci-total">₱${(item.price * item.qty).toLocaleString()}</div>
          </div>
        `).join('');
        document.getElementById('cart-item-count').textContent = `${totalQty} item${totalQty === 1 ? '' : 's'} in your order`;
      }

      const subtotal = currentOrder.reduce((sum, i) => sum + i.price * i.qty, 0);
      const vat = Math.round(subtotal * 0.12);
      const total = subtotal + vat;
      document.getElementById('order-subtotal').textContent = `₱${subtotal.toLocaleString()}`;
      document.getElementById('order-vat').textContent = `₱${vat.toLocaleString()}`;
      document.getElementById('order-total').textContent = `₱${total.toLocaleString()}`;
      placeBtn.disabled = currentOrder.length === 0;

      // Mobile toggle bar
      document.getElementById('toggle-bar-label').textContent = totalQty
        ? `🛒 View order (${totalQty})`
        : '🛒 View order';
      document.getElementById('toggle-bar-total').textContent = `₱${total.toLocaleString()}`;
    }

    function toggleKioskCart() {
      document.getElementById('kiosk-cart-panel').classList.toggle('open');
    }

    // ---- Checkout: order type only, no payment ----
    let kioskTypeResolve = null;
    function kioskOpenTypeModal() {
      return new Promise(resolve => {
        kioskTypeResolve = resolve;
        document.getElementById('kiosk-type-overlay').classList.add('active');
      });
    }
    function kioskConfirmType(type) {
      document.getElementById('kiosk-type-overlay').classList.remove('active');
      if (kioskTypeResolve) kioskTypeResolve(type);
      kioskTypeResolve = null;
    }
    function kioskCloseTypeModal() {
      document.getElementById('kiosk-type-overlay').classList.remove('active');
      if (kioskTypeResolve) kioskTypeResolve(null);
      kioskTypeResolve = null;
    }

    async function kioskPlaceOrder() {
      if (!currentOrder.length) {
        Swal.fire({ icon: 'info', title: 'Your order is empty', text: 'Tap an item to add it first.', confirmButtonColor: '#2C1A0E' });
        return;
      }

      const orderType = await kioskOpenTypeModal();
      if (!orderType) return; // cancelled

      const placeBtn = document.getElementById('place-order-btn');
      placeBtn.disabled = true;
      placeBtn.textContent = 'Sending order…';

      try {
        const data = await apiFetch(API.orders, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          // No cash_received -> orders.php creates this as Pending.
          body: JSON.stringify({
            items: currentOrder.map(i => ({ id: i.id, name: i.name, qty: i.qty, price: i.price })),
            order_type: orderType,
          }),
        });

        if (!data.success) {
          Swal.fire({ icon: 'error', title: 'Could not place order', text: data.message || 'Please try again.', confirmButtonColor: '#2C1A0E' });
          placeBtn.disabled = false;
          placeBtn.textContent = 'Place order →';
          return;
        }

        document.getElementById('kiosk-order-code').textContent = data.order_id;
        document.getElementById('kiosk-order-page').style.display = 'none';
        document.getElementById('cart-toggle-bar').style.display = 'none';
        document.getElementById('kiosk-confirmation').style.display = 'flex';
      } catch (err) {
        console.error('kioskPlaceOrder failed:', err);
        Swal.fire({ icon: 'error', title: 'Network error', text: 'Please try again.', confirmButtonColor: '#2C1A0E' });
      } finally {
        placeBtn.disabled = currentOrder.length === 0;
        placeBtn.textContent = 'Place order →';
      }
    }

    function kioskStartNewOrder() {
      currentOrder = [];
      document.getElementById('kiosk-confirmation').style.display = 'none';
      document.getElementById('kiosk-order-page').style.display = 'flex';
      document.getElementById('kiosk-cart-panel').classList.remove('open');
      renderOrderSidebar();
      loadMenuItems().then(() => renderItemsForCategory(activeCategoryTab));
    }

    (async function initKiosk() {
      await Promise.all([loadCategories(), loadMenuItems()]);
      renderCategoryTabs();
      renderItemsForCategory('All');
      renderOrderSidebar();
      // Keep the menu fresh in case stock/items change while the kiosk sits idle.
      setInterval(async () => {
        if (document.getElementById('kiosk-order-page').style.display !== 'none') {
          await loadMenuItems();
          renderItemsForCategory(activeCategoryTab);
        }
      }, 20000);
    })();
  </script>

<script src="../responsive-ui.js" defer></script>
<script src="../dashboard-navigation.js" defer></script>
<script src="../pos-responsive.js" defer></script>
</body>
</html>