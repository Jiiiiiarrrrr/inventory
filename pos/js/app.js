// ===== POS SYSTEM APP - Connected to PHP Backend =====
// VERSION: MEMBERS-v4
console.log('%c POS app.js v3 loaded - stock auto-reloads on page switch ✓', 'background:#2C1A0E;color:#EDE0C4;padding:4px 8px;border-radius:4px');

// ===== API HELPERS =====
const API = {
  account:    'api/account.php',
  items:      'api/items.php',
  orders:     'api/orders.php',
  categories: 'api/categories.php',
  members:    'api/members.php',
  stock:      'api/stock.php',
};

async function apiFetch(url, options = {}) {
  try {
    // Always send the session cookie — every guarded endpoint on the server now
    // requires it. An explicit `credentials` in options still wins if a caller
    // ever needs to override this.
    const res = await fetch(url, { credentials: 'same-origin', ...options });
    if (!res.ok) {
      console.error('API HTTP error:', res.status, res.statusText, 'URL:', url);
      return { error: `HTTP ${res.status}: ${res.statusText}` };
    }
    const text = await res.text();
    try {
      return JSON.parse(text);
    } catch (parseErr) {
      console.error('API JSON parse error. Response was:', text.substring(0, 500));
      return { error: 'Server returned invalid response. Check PHP errors.' };
    }
  } catch (err) {
    console.error('API fetch error:', err, 'URL:', url);
    return { error: 'Network error. Please try again.' };
  }
}

// ===== AUTH =====
let currentUser = null;

async function attemptLogin() {
  const email    = document.getElementById('login-email').value.trim().toLowerCase();
  const password = document.getElementById('login-password').value;
  const errEl    = document.getElementById('login-error');
  const btn      = document.querySelector('#signin-form .login-btn');

  if (!email || !password) {
    errEl.textContent = 'Please enter your email and password.';
    return;
  }

  errEl.textContent = '';
  if (btn) { btn.disabled = true; btn.textContent = 'Signing in…'; }

  const data = await apiFetch(API.account + '?action=login', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ email, password }),
  });

  if (btn) { btn.disabled = false; btn.textContent = 'Sign in'; }

  if (data.error) {
    errEl.textContent = data.error;
    Swal.fire({
      icon:  'error',
      title: 'Login failed',
      text:  data.error,
      confirmButtonColor: '#2C1A0E',
    });
    return;
  }

  errEl.textContent = '';
  currentUser = data.data;
  // Login screen removed — handled by landing page in index.php
  document.getElementById('app').className = 'app-visible';
  applyRoleUI();

  // Cashier accounts go straight to cashier station - nothing else loads
  if (currentUser.role === 'Cashier') {
    showPage('page-cashier');
    return;
  }

  await loadCategories();
  await loadMenuItems();
  refreshItemsFilter();
  showPage('page-home');
}

async function attemptSignup() {
  const name     = document.getElementById('signup-name').value.trim();
  const email    = document.getElementById('signup-email').value.trim().toLowerCase();
  const password = document.getElementById('signup-password').value;
  const errEl    = document.getElementById('signup-error');
  const btn      = document.querySelector('#signup-form .login-btn');

  if (!name)     { errEl.textContent = 'Full name is required.';  return; }
  if (!email)    { errEl.textContent = 'Email is required.';      return; }
  if (!password) { errEl.textContent = 'Password is required.';   return; }
  if (password.length < 6) {
    errEl.textContent = 'Password must be at least 6 characters.';
    return;
  }

  errEl.textContent = '';
  if (btn) { btn.disabled = true; btn.textContent = 'Creating…'; }

  const data = await apiFetch(API.account, {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ full_name: name, email, password, role: 'Member' }),
  });

  if (btn) { btn.disabled = false; btn.textContent = 'Create account'; }

  if (data.error) {
    errEl.textContent = data.error;
    Swal.fire({
      icon:  'error',
      title: 'Sign up failed',
      text:  data.error,
      confirmButtonColor: '#2C1A0E',
    });
    return;
  }

  errEl.textContent = '';
  showSignin();
  document.getElementById('login-email').value = email;
  Swal.fire({
    icon:  'success',
    title: 'Account created!',
    text:  'You can now sign in.',
    confirmButtonColor: '#2C1A0E',
    timer: 2000,
    showConfirmButton: false,
  });
}

function logout() {
  // Handled by logoutPos() in index.php landing page
  if (typeof logoutPos === 'function') { logoutPos(); return; }
  currentUser     = null;
  currentOrder    = [];
  expandedOrderId = null;
  menuItems       = [];
  salesHistory    = [];
  document.getElementById('app').className = 'app-hidden';
}

function applyRoleUI() {
  if (!currentUser) return;
  const isAdmin   = currentUser.role === 'Admin';
  const isCashier = currentUser.role === 'Cashier';
  const navUserBadge = document.getElementById('nav-user-badge');
  const navAdminItem = document.getElementById('nav-admin-item');
  const navMembersItem = document.getElementById('nav-members-item');
  const navCashierItem = document.getElementById('nav-cashier-item');
  const accountName = document.getElementById('account-name');
  const accountEmail = document.getElementById('account-email');
  const accountRole = document.getElementById('account-role');
  const displayName = document.getElementById('display-name');
  const displayRole = document.getElementById('display-role');

  if (navUserBadge) navUserBadge.textContent = `👤 ${currentUser.full_name}`;
  if (navAdminItem) navAdminItem.style.display = isAdmin ? '' : 'none';
  if (navMembersItem) navMembersItem.style.display = isAdmin ? '' : 'none';
  if (navCashierItem) navCashierItem.style.display = isCashier ? '' : 'none';
  if (accountName) accountName.value = currentUser.full_name;
  if (accountEmail) accountEmail.value = currentUser.email;
  if (accountRole) accountRole.value = currentUser.role;
  if (displayName) displayName.textContent = currentUser.full_name;
  if (displayRole) displayRole.textContent = currentUser.role;
}

function toggleLoginPassword() {
  const inp = document.getElementById('login-password');
  inp.type = inp.type === 'password' ? 'text' : 'password';
}

function toggleSignupPassword() {
  const inp = document.getElementById('signup-password');
  inp.type = inp.type === 'password' ? 'text' : 'password';
}

function showSignup() {
  document.getElementById('signin-form').style.display = 'none';
  document.getElementById('signup-form').style.display = '';
  document.getElementById('login-card-title').textContent = 'Create Account';
  document.getElementById('login-card-sub').textContent   = 'Sign up to get started';
  document.getElementById('signup-error').textContent     = '';
}

function showSignin() {
  document.getElementById('signup-form').style.display = 'none';
  document.getElementById('signin-form').style.display = '';
  document.getElementById('login-card-title').textContent = 'POS Coffee';
  document.getElementById('login-card-sub').textContent   = 'Sign in to continue';
  document.getElementById('login-error').textContent      = '';
}

// ===== DATA =====
let menuItems    = [];
let salesHistory = [];

// ===== CATEGORY STATE (loaded from DB) =====
let allCategories = []; // [{ id, name, is_default }]

async function loadCategories() {
  const data = await apiFetch(API.categories);
  if (data.success) allCategories = data.data;
}

function getCategoryNames()  { return allCategories.map(c => c.name); }
function getCatIcon(name)    {
  const cat = allCategories.find(c => c.name === name);
  return cat?.emoji || '🏷️';
}

async function promptAddCategory() {
  const { value: formValues, isConfirmed } = await Swal.fire({
    title: 'Add new category',
    html: `
      <div style="display:flex;flex-direction:column;gap:0.75rem;text-align:left;margin-top:0.5rem">
        <div>
          <label style="font-size:0.85rem;font-weight:600;color:#2C1A0E;display:block;margin-bottom:0.3rem">Category name</label>
          <input id="swal-cat-name" class="swal2-input" placeholder="e.g. Smoothies" maxlength="50" style="margin:0;width:100%;box-sizing:border-box" />
        </div>
        <div>
          <label style="font-size:0.85rem;font-weight:600;color:#2C1A0E;display:block;margin-bottom:0.3rem">Emoji icon</label>
          <input id="swal-cat-emoji" class="swal2-input" placeholder="e.g. 🥤" maxlength="4" style="margin:0;width:100%;box-sizing:border-box;font-size:1.4rem;text-align:center" />
        </div>
      </div>`,
    focusConfirm: false,
    showCancelButton: true,
    confirmButtonText: 'Add',
    confirmButtonColor: '#2C1A0E',
    cancelButtonColor: '#7A5C3A',
    preConfirm: () => {
      const popup = Swal.getPopup();
      const name  = popup.querySelector('#swal-cat-name').value.trim();
      const emoji = popup.querySelector('#swal-cat-emoji').value.trim() || '🏷️';
      if (!name) { Swal.showValidationMessage('Category name cannot be empty.'); return false; }
      if (allCategories.some(c => c.name.toLowerCase() === name.toLowerCase())) {
        Swal.showValidationMessage('That category already exists.'); return false;
      }
      return { name, emoji };
    },
  });
  if (!isConfirmed || !formValues) return;

  const data = await apiFetch(API.categories, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name: formValues.name, emoji: formValues.emoji }),
  });
  if (data.error) { showToast(data.error); return; }

  await loadCategories();
  renderMenuEditor();
  refreshItemsFilter();
  Swal.fire({ toast:true, position:'top', icon:'success',
    title: `${formValues.emoji} "${formValues.name}" added!`,
    showConfirmButton:false, timer:2000, timerProgressBar:true });
}

async function promptDeleteCategory(id, catName) {
  const cat = allCategories.find(c => c.id == id);
  if (cat?.is_default == 1) {
    Swal.fire({ toast:true, position:'top', icon:'warning',
      title:'Built-in categories cannot be archived.',
      showConfirmButton:false, timer:2500 });
    return;
  }

  if (!await requireAdminPin(`archive category "${catName}"`)) return;

  const result = await Swal.fire({
    title: `Archive "${catName}"?`,
    html: `All items in <b>${catName}</b> will be <b>archived</b> and the category removed.<br>You can restore them from the Archive tab.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, archive items',
    confirmButtonColor: '#C0392B',
    cancelButtonColor: '#7A5C3A',
  });
  if (!result.isConfirmed) return;

  const data = await apiFetch(`${API.categories}?id=${id}`, { method: 'DELETE' });
  if (data.error) { showToast(data.error); return; }

  await loadCategories();
  await loadMenuItems();
  renderMenuEditor();
  refreshItemsFilter();
  renderCategoryTabs();

  // Always refresh archive data so it's up-to-date when user opens the Archive tab
  renderArchivedCategoriesGrid();
  renderArchiveGrid();

  Swal.fire({ toast:true, position:'top', icon:'success',
    title: `"${catName}" archived. ${data.archived_items || 0} item(s) moved to archive.`,
    showConfirmButton:false, timer:3000, timerProgressBar:true });
}

function refreshItemsFilter() {
  const sel = document.getElementById('items-category-filter');
  if (!sel) return;
  const cats = getCategoryNames();
  sel.innerHTML = '<option value="">All categories</option>' +
    cats.map(c => `<option value="${c}">${c}</option>`).join('');
}




async function loadFeatured() {
  try {
    const r = await fetch(`${API.items}?action=featured`, { credentials: 'same-origin' });
    const d = await r.json();
    const items = d.data || [];
    const grid = document.getElementById('featured-grid');
    if (!grid) return;
    if (!items.length) {
      grid.innerHTML = '<div class="empty-order" style="padding:40px;text-align:center;color:var(--text-muted)">No featured items yet.</div>';
      return;
    }
    grid.innerHTML = items.map(item => `
      <div class="featured-card" onclick="addToOrder(${item.id})">
        <div class="card-image">${itemMedia(item, 'featured-img')}</div>
        <div class="card-info">
          <h3>${item.name}</h3>
          <div class="price">₱${Number(item.price).toLocaleString(undefined, {minimumFractionDigits:2})}</div>
        </div>
      </div>
    `).join('');
  } catch (e) {
    const grid = document.getElementById('featured-grid');
    if (grid) grid.innerHTML = '<div class="empty-order" style="padding:40px;text-align:center;color:var(--text-muted)">Cannot load featured items.</div>';
  }
}

async function loadMenuItems() {
  const data = await apiFetch(API.items);
  if (data.success) {
    menuItems = data.data.map(item => ({
      ...item,
      id:       parseInt(item.id),
      price:    parseFloat(item.price),
      featured: item.featured == 1,
      stock:    parseInt(item.stock ?? 0),
    }));
  }
}

// Returns an <img> if item has an image, otherwise the emoji span
function itemMedia(item, extraClass = '') {
  if (item.image) {
    return `<img src="${item.image}" alt="${item.name}" class="item-img ${extraClass}" onerror="this.style.display='none';this.nextElementSibling.style.display='block'" /><span class="item-emoji ${extraClass}" style="display:none">${item.emoji}</span>`;
  }
  return `<span class="item-emoji ${extraClass}">${item.emoji}</span>`;
}

async function loadSalesHistory() {
  const data = await apiFetch(API.orders + '?status=Paid');
  if (data.success) {
    // Sort oldest first so ORD-001 is always the first paid order
    const sorted = [...data.data].sort((a, b) => new Date(a.paid_at || a.created_at) - new Date(b.paid_at || b.created_at));
    salesHistory = sorted.map((o, i) => ({
      id:       'ORD-' + String(i + 1).padStart(3, '0'), // sequential, no gaps
      realId:   o.order_code,                             // keep original for reference
      items:    o.items_summary || '',
      total:    parseFloat(o.total),
      date:     (o.paid_at || o.created_at) ? (o.paid_at || o.created_at).split(' ')[0] : '', // report by payment date
    }));
    // Reverse so newest shows first in tables
    salesHistory.reverse();
  }
}

// ===== NAVIGATION =====
let currentOrder    = [];
let expandedOrderId = null;
let pageNavigationId = 0;

function saveLastPage(pageId) {
  try {
    sessionStorage.setItem('pos_last_page', pageId);
    localStorage.setItem('pos_last_page', pageId);
  } catch (e) { /* storage unavailable — use the default page */ }
}

function getLastPage() {
  try {
    return sessionStorage.getItem('pos_last_page') || localStorage.getItem('pos_last_page');
  } catch (e) {
    return null;
  }
}

async function showPage(pageId) {
  // Role checks removed — landing page already controls which nav links are visible
  // If the user can see the link, they're allowed to access the page.
  const navigationId = ++pageNavigationId;
  const page = document.getElementById(pageId);
  if (!page) return;

  // Activate the requested page before waiting for API data. This prevents a
  // refresh from exposing the app shell while every page is still hidden.
  document.querySelectorAll('.page').forEach(p => {
    p.classList.remove('active');
    p.style.display = 'none';
  });
  page.classList.add('active');
  page.style.display = '';

  // Remember the current page so a refresh can restore it instead of always
  // landing back on Home. sessionStorage clears when the tab closes, which is
  // the right lifetime for "where was I in this session."
  saveLastPage(pageId);

  // Always reload fresh data on every page switch
  await loadMenuItems();
  await loadCategories();

  // A newer click or refresh restore owns the UI now. Do not let this older
  // request render into the page that replaced it.
  if (navigationId !== pageNavigationId) return;

  if (pageId === 'page-home')     renderHome();
  if (pageId === 'page-category') renderCategory();
  if (pageId === 'page-items')    renderItems();
  if (pageId === 'page-sales')    initSalesPage();
  if (pageId === 'page-admin')    renderAdmin();
  if (pageId === 'page-account')  renderAccount();
  if (pageId === 'page-cashier')  await initCashierPage();
  if (pageId === 'page-pending')  await initPendingPage();

  if (pageId !== 'page-sales' && salesAutoRefresh) {
    clearInterval(salesAutoRefresh);
    salesAutoRefresh = null;
  }
}

// ===== HOME =====
function renderHome() {
  const grid = document.getElementById('featured-grid');
  if (!grid) return;
  const featured = menuItems.filter(i => i.featured);
  grid.innerHTML = featured.map(item => `
    <div class="featured-card" onclick="goToFeaturedItem(${item.id})">
      <div class="card-image">${item.image ? `<img src="${item.image}" alt="${item.name}" class="item-img featured-img" onerror="this.style.display='none'" />` : item.emoji}</div>
      <div class="card-info">
        <h3>${item.name}</h3>
        <div class="price">₱${item.price}</div>
      </div>
    </div>
  `).join('') || '<p style="color:var(--text-muted);font-size:0.9rem;">No featured items.</p>';
}

function goToCategory() { showPage('page-category'); }

async function goToFeaturedItem(itemId) {
  await showPage('page-category');
  // Switch to the item's category tab
  const item = menuItems.find(i => i.id === itemId);
  if (item) {
    activeTab = item.category || 'All';
    renderCategoryTabs();
    renderItemsForCategory(activeTab);
  }
  // Highlight the specific card
  setTimeout(() => {
    const cards = document.querySelectorAll('#category-items-grid .item-card');
    cards.forEach(card => {
      const name = card.querySelector('h3')?.textContent;
      if (item && name === item.name) {
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        card.style.outline = '3px solid #D4BC8A';
        card.style.boxShadow = '0 0 0 6px rgba(212,188,138,0.3)';
        setTimeout(() => {
          card.style.outline = '';
          card.style.boxShadow = '';
        }, 2000);
      }
    });
  }, 150);
}

function handleHeroSearch() {
  const q = document.getElementById('hero-search-input').value.trim().toLowerCase();
  if (!q) return;
  // Pre-set the search input BEFORE navigating so renderItems picks it up
  const searchInput = document.getElementById('items-search-input');
  if (searchInput) searchInput.value = q;
  document.getElementById('items-category-filter').value = '';
  showPage('page-items');
}

// ===== MENU / CATEGORY PAGE =====
let activeTab = 'All';

function renderCategory() {
  renderCategoryTabs();
  renderItemsForCategory(activeTab);
  renderOrderSidebar();
}

function renderCategoryTabs() {
  const tabsEl = document.getElementById('category-tabs');
  if (!tabsEl) return;
  const tabs = ['All', ...getCategoryNames()];
  tabsEl.innerHTML = tabs.map(tab => `
    <button class="tab-btn ${tab === activeTab ? 'active' : ''}" onclick="selectTab('${tab}')">
      <span>${tab === 'All' ? '🔘' : getCatIcon(tab)}</span> ${tab}
    </button>
  `).join('');
}

function selectTab(tab) {
  activeTab = tab;
  renderCategoryTabs();
  renderItemsForCategory(tab);
}

function stockVisual(stock) {
  if (stock === 0) return '';
  return `<div style="font-size:0.75rem;color:var(--text-muted,#7A5C3A);font-weight:600;margin:4px 0 2px;">Stock: ${stock}</div>`;
}

function renderItemsForCategory(tab) {
  const grid = document.getElementById('category-items-grid');
  if (!grid) return;
  const normalizeStr = s => (s || '').trim().toLowerCase().replace(/\s+/g, ' ');
  const filtered = tab === 'All' ? menuItems : menuItems.filter(i => normalizeStr(i.category) === normalizeStr(tab));
  grid.innerHTML = filtered.map(item => {
    const outOfStock = item.stock === 0;
    const lowStock   = item.stock > 0 && item.stock <= 5;
    const stockClass = outOfStock ? 'out-of-stock' : lowStock ? 'low-stock' : '';
    const inCart     = currentOrder.find(o => o.id === item.id);
    const cartQty    = inCart ? inCart.qty : 0;

    const stockBadge = outOfStock
      ? `<div class="stock-badge out-badge">Out of stock</div>`
      : lowStock
      ? `<div class="stock-badge low-badge">Low stock: ${item.stock} left</div>`
      : '';

    // Visual stock bar/dots
    const visual = stockVisual(item.stock);

    return `
      <div class="item-card ${stockClass} ${cartQty > 0 ? 'item-card-active' : ''}" ${outOfStock ? '' : `onclick="cardIncrement(${item.id})"`} style="${outOfStock ? '' : 'cursor:pointer'}">
        ${cartQty > 0 ? `<div class="card-in-cart-badge">${cartQty}</div>` : ''}
        ${itemMedia(item)}
        <h3>${item.name}</h3>
        <div class="price">₱${item.price}</div>
        ${stockBadge}
        ${visual}
      </div>
    `;
  }).join('');
}

function cardIncrement(itemId) {
  const item = menuItems.find(i => i.id === itemId);
  if (!item || item.stock === 0) return;
  const existing = currentOrder.find(o => o.id === itemId);
  if (existing) { existing.qty += 1; }
  else { currentOrder.push({ ...item, qty: 1 }); }
  item.stock -= 1; // decrease displayed stock immediately
  renderOrderSidebar();
  renderItemsForCategory(activeTab);
}

function cardDecrement(itemId) {
  const item = menuItems.find(i => i.id === itemId);
  const idx  = currentOrder.findIndex(o => o.id === itemId);
  if (idx === -1) return;
  currentOrder[idx].qty -= 1;
  if (item) item.stock += 1; // restore displayed stock
  if (currentOrder[idx].qty <= 0) currentOrder.splice(idx, 1);
  renderOrderSidebar();
  renderItemsForCategory(activeTab);
}

// ===== ORDER MANAGEMENT =====
function addToOrder(itemId) {
  const item = menuItems.find(i => i.id === itemId);
  if (!item || item.stock === 0) return;
  const existing   = currentOrder.find(o => o.id === itemId);
  const currentQty = existing ? existing.qty : 0;
  if (currentQty >= item.stock) {
    Swal.fire({ icon: 'warning',
      title: 'Stock limit reached!',
      text: 'Only ' + item.stock + ' ' + item.name + ' available.',
      confirmButtonText: 'OK',
      confirmButtonColor: '#2C1A0E' });
    return;
  }
  if (existing) { existing.qty += 1; }
  else { currentOrder.push({ ...item, qty: 1 }); }
  renderOrderSidebar();
  Swal.fire({ icon: 'success',
    title: 'Added to order!',
    text: (item.emoji || '') + ' ' + item.name + ' has been added.',
    confirmButtonText: 'OK',
    confirmButtonColor: '#2C1A0E',
    timer: 1500,
    timerProgressBar: true });
}

function renderOrderSidebar() {
  const orderEl    = document.getElementById('order-items-list');
  const subtotalEl = document.getElementById('order-subtotal');
  const vatEl      = document.getElementById('order-vat');
  const totalEl    = document.getElementById('order-total');
  if (!orderEl) return;

  if (currentOrder.length === 0) {
    orderEl.innerHTML = '<div class="empty-order">No items yet. Tap an item to add.</div>';
    if (subtotalEl) subtotalEl.textContent = '₱0';
    if (vatEl)      vatEl.textContent      = '₱0';
    if (totalEl)    totalEl.textContent    = '₱0';
    expandedOrderId = null;
    updateOrderChangeEstimate();
    return;
  }

  orderEl.innerHTML = currentOrder.map(item => {
    const isExpanded = expandedOrderId === item.id;
    return `
      <div class="order-line ${isExpanded ? 'expanded' : ''}" onclick="toggleOrderItem(${item.id})">
        <span class="item-name">${item.name}</span>
        ${isExpanded ? `
          <div class="qty-controls" onclick="event.stopPropagation()">
            <button class="qty-btn" onclick="changeQty(${item.id}, -1)">−</button>
            <span class="qty-num">${item.qty}</span>
            <button class="qty-btn" onclick="changeQty(${item.id}, +1)">+</button>
          </div>
        ` : `<span class="item-qty-label">x${item.qty}</span>`}
        <span class="item-price">₱${item.price * item.qty}</span>
      </div>
    `;
  }).join('');

  const subtotal = currentOrder.reduce((sum, i) => sum + i.price * i.qty, 0);
  const vat      = Math.round(subtotal * 0.12);
  const total    = subtotal + vat;
  if (subtotalEl) subtotalEl.textContent = `₱${subtotal}`;
  if (vatEl)      vatEl.textContent      = `₱${vat}`;
  if (totalEl)    totalEl.textContent    = `₱${total}`;
  updateOrderChangeEstimate();
}

// Purely a self-serve preview for the cashier — never sent to the server or
// recorded. Recalculates whenever the cart changes or the "paying with" field is
// edited. The actual cash/change that gets recorded comes from the required
// numpad step in placeOrder() (see window.openCheckoutModal in index.php).
function updateOrderChangeEstimate() {
  const input = document.getElementById('order-cash-estimate');
  const row   = document.getElementById('order-change-estimate-row');
  const amountEl = document.getElementById('order-change-estimate-amount');
  if (!input || !row || !amountEl) return;

  const raw = input.value;
  if (raw === '') { row.style.display = 'none'; return; }

  const paying = parseFloat(raw);
  const subtotal = currentOrder.reduce((sum, i) => sum + i.price * i.qty, 0);
  const vat      = Math.round(subtotal * 0.12);
  const total    = subtotal + vat;

  if (isNaN(paying) || paying < 0) { row.style.display = 'none'; return; }

  const change = paying - total;
  row.style.display = 'flex';
  amountEl.textContent = `₱${Math.max(change, 0).toLocaleString()}`;
  amountEl.style.color = change < 0 ? '#c0392b' : 'var(--dark-brown)';
}

function toggleOrderItem(itemId) {
  expandedOrderId = expandedOrderId === itemId ? null : itemId;
  renderOrderSidebar();
}

function changeQty(itemId, delta) {
  const item = menuItems.find(i => i.id === itemId);
  const idx  = currentOrder.findIndex(o => o.id === itemId);
  if (idx === -1 || !item) return;
  if (delta > 0 && item.stock <= 0) {
    Swal.fire({ icon: 'warning',
      title: 'Stock limit reached!',
      text: 'No more ' + item.name + ' available.',
      confirmButtonText: 'OK',
      confirmButtonColor: '#2C1A0E' });
    return;
  }
  item.stock -= delta;
  currentOrder[idx].qty += delta;
  if (currentOrder[idx].qty <= 0) { currentOrder.splice(idx, 1); expandedOrderId = null; }
  renderOrderSidebar();
  renderItemsForCategory(activeTab);
}


async function placeOrder() {
  if (currentOrder.length === 0) {
    Swal.fire({ icon: 'warning',
      title: 'No items in order',
      text: 'Please add at least one item before placing an order.',
      confirmButtonText: 'OK',
      confirmButtonColor: '#2C1A0E' });
    return;
  }

  const subtotal = currentOrder.reduce((sum, i) => sum + i.price * i.qty, 0);
  const vat      = Math.round(subtotal * 0.12);
  const total     = subtotal + vat;

  // Customers only choose Dine In / Take Out — cash is collected later by
  // the cashier (window.openPaymentModal) when the Pending order is processed.
  // Staff/cashier placing a direct sale still collects cash right here.
  const isCustomer = (typeof currentMode !== 'undefined' && currentMode === 'customer');
  const checkoutResult = await window.openCheckoutModal(total, !isCustomer);
  if (checkoutResult === null) return; // cancelled

  const { selectedType, cashResult } = checkoutResult;

  const orderPayload = {
    items:         currentOrder.map(i => ({ id: i.id, qty: i.qty, price: i.price })),
    order_type:    selectedType,
    payment:       isCustomer ? 'Pay at Cashier' : 'Cash',
    cash_received: isCustomer ? null : cashResult,
  };

  const data = await apiFetch(API.orders, {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify(orderPayload),
  });

  if (!data.success) {
    showToast('Failed to place order: ' + (data.message || data.error || 'Unknown error'));
    return;
  }

  const orderId   = data.order_id;
  const orderDate = new Date();
  const dateStr   = orderDate.toISOString().split('T')[0];
  const timeStr   = orderDate.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  const typeIcon  = selectedType === 'Dine In' ? '🍽️' : '🥡';

  const ticketItems = currentOrder.map(i => `
    <div class="ticket-line">
      <span class="ticket-item-name">${i.emoji} ${i.name} <span class="ticket-qty">x${i.qty}</span></span>
      <span class="ticket-item-price">₱${(i.price * i.qty).toLocaleString()}</span>
    </div>
  `).join('');

  document.getElementById('modal-title').textContent   = '🎟️ Order Ticket';
  document.getElementById('modal-message').textContent = '';
  document.getElementById('modal-body').innerHTML = `
    <div class="ticket-wrap" id="ticket-printable">
      <div class="ticket-header">
        <div class="ticket-logo">☕</div>
        <div class="ticket-shop">POS Coffee</div>
        <div class="ticket-tagline">Freshly Brewed Daily</div>
      </div>
      <div class="ticket-divider">- - - - - - - - - - - - - - - -</div>
      <div class="ticket-meta">
        <div><span>Order</span><span class="ticket-bold">${orderId}</span></div>
        <div><span>Date</span><span>${dateStr}</span></div>
        <div><span>Time</span><span>${timeStr}</span></div>
        <div><span>Type</span><span class="ticket-bold">${typeIcon} ${selectedType}</span></div>
        <div><span>Ordered by</span><span>${currentUser?.full_name || 'Staff'}</span></div>
      </div>
      <div class="ticket-divider">- - - - - - - - - - - - - - - -</div>
      <div class="ticket-items">${ticketItems}</div>
      <div class="ticket-divider">- - - - - - - - - - - - - - - -</div>
      <div class="ticket-totals">
        <div class="ticket-total-row"><span>Subtotal</span><span>₱${subtotal.toLocaleString()}</span></div>
        <div class="ticket-total-row"><span>VAT (12%)</span><span>₱${vat.toLocaleString()}</span></div>
        <div class="ticket-total-row ticket-grand"><span>TOTAL</span><span>₱${total.toLocaleString()}</span></div>
      </div>
      <div class="ticket-divider">- - - - - - - - - - - - - - - -</div>
      ${isCustomer ? `
      <div style="text-align:center;font-size:0.9rem;color:#7A5C3A;font-weight:600;padding:0.4rem 0">
        Please proceed to the counter to pay.
      </div>` : `
      <div class="ticket-totals">
        <div class="ticket-total-row"><span>Cash received</span><span>₱${Number(data.cash_received ?? cashResult).toLocaleString()}</span></div>
        <div class="ticket-total-row"><span>Change</span><span>₱${Number(data.change_given ?? 0).toLocaleString()}</span></div>
      </div>
      <div class="ticket-divider">- - - - - - - - - - - - - - - -</div>
      <div style="text-align:center;font-size:0.85rem;color:#256b4d;font-weight:600;padding:0.3rem 0">
        ✓ Paid
      </div>`}
      <div class="ticket-footer">Thank you for your order!<br>See you again ☕</div>
    </div>
  `;
  document.getElementById('modal-actions').innerHTML = `
    <button class="btn-primary" onclick="downloadTicket('${orderId}')">⬇ Download ticket</button>
    <button class="btn-cancel" onclick="closeModal()">Close</button>
  `;
  document.getElementById('modal-overlay').classList.add('active');
  modalCallback = null;

  currentOrder    = [];
  expandedOrderId = null;
  renderOrderSidebar();
  renderItemsForCategory(activeTab);

  // The order is Paid the moment it's created, so today's totals just changed —
  // refresh the End-of-day sales summary immediately if it's the visible page.
  if (document.getElementById('page-cashier')?.classList.contains('active')) {
    loadCashierDailySummary();
  }
}

function downloadTicket(orderId) {
  const ticket = document.getElementById('ticket-printable');
  if (!ticket) return;
  const lines = [];
  ticket.querySelectorAll('.ticket-header .ticket-shop').forEach(el => lines.push(el.textContent));
  ticket.querySelectorAll('.ticket-tagline').forEach(el => lines.push(el.textContent));
  lines.push('================================');
  ticket.querySelectorAll('.ticket-meta div').forEach(el => {
    const spans = el.querySelectorAll('span');
    if (spans.length === 2) lines.push(`${spans[0].textContent.padEnd(10)} ${spans[1].textContent}`);
  });
  lines.push('================================');
  ticket.querySelectorAll('.ticket-line').forEach(el => {
    const name  = el.querySelector('.ticket-item-name')?.textContent?.trim() || '';
    const price = el.querySelector('.ticket-item-price')?.textContent?.trim() || '';
    lines.push(`${name.padEnd(24)} ${price}`);
  });
  lines.push('================================');
  ticket.querySelectorAll('.ticket-total-row').forEach(el => {
    const spans = el.querySelectorAll('span');
    if (spans.length === 2) lines.push(`${spans[0].textContent.padEnd(24)} ${spans[1].textContent}`);
  });
  lines.push('================================');
  lines.push('     Thank you for your order!');
  lines.push('          See you again ☕');
  const blob = new Blob([lines.join('\n')], { type: 'text/plain' });
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href     = url;
  a.download = `${orderId}.txt`;
  a.click();
  URL.revokeObjectURL(url);
  showToast('Ticket downloaded ✓');
}

// ===== ITEMS PAGE =====
function renderItems() {
  refreshItemsFilter();
  const q = (document.getElementById('items-search-input')?.value || '').trim().toLowerCase();
  const cat = document.getElementById('items-category-filter')?.value || '';
  renderItemsFiltered(cat, q);
}

function renderItemsFiltered(categoryFilter, searchQuery) {
  const grid = document.getElementById('items-full-grid');
  if (!grid) return;
  const normalizeStr = s => (s || '').trim().toLowerCase().replace(/\s+/g, ' ');
  const q = (searchQuery || '').trim().toLowerCase();
  let filtered = categoryFilter ? menuItems.filter(i => normalizeStr(i.category) === normalizeStr(categoryFilter)) : menuItems;
  if (q) filtered = filtered.filter(i => i.name.toLowerCase().includes(q) || (i.description || '').toLowerCase().includes(q) || i.category.toLowerCase().includes(q));
  if (filtered.length === 0) {
    grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:3rem;color:var(--text-muted);font-size:0.95rem;">No items found for "<b>${q || categoryFilter}</b>".</div>`;
    return;
  }
  grid.innerHTML = filtered.map(item => {
    const outOfStock = item.stock === 0;
    const lowStock   = item.stock > 0 && item.stock <= 5;
    const stockBadge = outOfStock
      ? `<div class="stock-badge out-badge">Out of stock</div>`
      : lowStock
      ? `<div class="stock-badge low-badge">Low stock: ${item.stock} left</div>`
      : '';
    const visual = stockVisual(item.stock);
    return `
      <div class="item-full-card ${outOfStock ? 'out-of-stock' : ''}">
        ${itemMedia(item)}
        <h3>${item.name}</h3>
        <div class="category-label">${item.category}</div>
        <div class="price">₱${item.price}</div>
        ${stockBadge}
        ${visual}
        <div class="item-desc-overlay">${item.description || item.category}</div>
      </div>
    `;
  }).join('');
}

function handleCategoryFilter(val) {
  const q = (document.getElementById('items-search-input')?.value || '').trim().toLowerCase();
  renderItemsFiltered(val, q);
}

// ===== SALES PAGE =====
let salesChartView   = 'month';
let salesAutoRefresh = null;

async function initSalesPage() {
  await loadSalesHistory();
  renderSalesSummary();
  renderSalesChart();
  filterSalesTable(document.getElementById('sales-search')?.value || '');
  startSalesAutoRefresh();
}

function startSalesAutoRefresh() {
  if (salesAutoRefresh) clearInterval(salesAutoRefresh);
  salesAutoRefresh = setInterval(async () => {
    if (document.getElementById('page-sales').classList.contains('active')) {
      await loadSalesHistory();
      renderSalesSummary();
      renderSalesChart();
      filterSalesTable(document.getElementById('sales-search')?.value || '');
    }
  }, 30000);
}

function renderSalesSummary() {
  const today     = new Date().toISOString().split('T')[0];
  const yearMonth = today.slice(0, 7);
  const todayOrders = salesHistory.filter(s => s.date === today);
  const monthOrders = salesHistory.filter(s => s.date.startsWith(yearMonth));
  const fmt = v => `₱${v.toLocaleString()}`;
  document.getElementById('summary-today').textContent        = fmt(todayOrders.reduce((s,o) => s + o.total, 0));
  document.getElementById('summary-today-orders').textContent = `${todayOrders.length} order${todayOrders.length !== 1 ? 's' : ''}`;
  document.getElementById('summary-month').textContent        = fmt(monthOrders.reduce((s,o) => s + o.total, 0));
  document.getElementById('summary-month-orders').textContent = `${monthOrders.length} order${monthOrders.length !== 1 ? 's' : ''}`;
  document.getElementById('summary-all').textContent          = fmt(salesHistory.reduce((s,o) => s + o.total, 0));
  document.getElementById('summary-all-orders').textContent   = `${salesHistory.length} order${salesHistory.length !== 1 ? 's' : ''}`;
}

function setChartView(view) {
  salesChartView = view;
  document.querySelectorAll('.chart-view-btn').forEach((btn, i) =>
    btn.classList.toggle('active', ['month','week'][i] === view)
  );
  renderSalesChart();
}

function renderSalesChart() {
  const today = new Date();
  let labels = [], data = [], titleText = '';
  if (salesChartView === 'month') {
    const year  = today.getFullYear();
    const month = today.getMonth();
    const days  = new Date(year, month + 1, 0).getDate();
    titleText = `Daily Sales - ${today.toLocaleString('default', { month: 'long', year: 'numeric' })}`;
    labels = Array.from({ length: days }, (_, i) => String(i + 1).padStart(2, '0'));
    const ym = today.toISOString().slice(0, 7);
    data = labels.map(d => {
      const dateStr = `${ym}-${d}`;
      return salesHistory.filter(s => s.date === dateStr).reduce((sum, s) => sum + s.total, 0);
    });
  } else {
    titleText = 'Daily Sales - Last 7 Days';
    for (let i = 6; i >= 0; i--) {
      const d = new Date(today);
      d.setDate(today.getDate() - i);
      const ds = d.toISOString().split('T')[0];
      labels.push(d.toLocaleDateString('default', { month: 'short', day: 'numeric' }));
      data.push(salesHistory.filter(s => s.date === ds).reduce((sum, s) => sum + s.total, 0));
    }
  }
  document.getElementById('chart-title').textContent = titleText;
  const wrap = document.getElementById('sales-chart-wrap');
  if (!wrap) return;
  const maxVal = Math.max(...data, 1);
  let html = '';
  if (data.every(v => v === 0)) {
    html = '<div class="css-chart-empty">No sales data for this period</div>';
  } else {
    data.forEach((val, i) => {
      const heightPct = (val / maxVal) * 100;
      const label = labels[i];
      html += `<div class="css-bar-col">
        <div class="css-bar-value">${val.toLocaleString()}</div>
        <div class="css-bar" style="height:${Math.max(heightPct, 2)}%"></div>
        <div class="css-bar-label">${label}</div>
      </div>`;
    });
  }
  wrap.innerHTML = html;
}

const MONTH_NAMES = ['january','february','march','april','may','june','july','august','september','october','november','december'];

function filterSalesTable(query) {
  const q = query.trim().toLowerCase();
  let filtered = salesHistory;
  if (q) {
    filtered = salesHistory.filter(s => {
      if (s.date.includes(q)) return true;
      if (s.date.startsWith(q)) return true;
      const monthIdx = MONTH_NAMES.findIndex(m => m.startsWith(q));
      if (monthIdx !== -1) {
        const mm = String(monthIdx + 1).padStart(2, '0');
        if (s.date.slice(5, 7) === mm) return true;
      }
      return false;
    });
  }
  const tbody = document.getElementById('sales-tbody');
  if (!tbody) return;
  if (filtered.length === 0) {
    tbody.innerHTML = '<tr class="sales-empty-row"><td colspan="4">No orders found for that search.</td></tr>';
    return;
  }
  tbody.innerHTML = filtered.map(s => `
    <tr>
      <td>${s.id}</td>
      <td>${s.items}</td>
      <td>₱${s.total.toLocaleString()}</td>
      <td>${s.date}</td>
    </tr>
  `).join('');
}

function clearSalesSearch() {
  const input = document.getElementById('sales-search');
  if (input) { input.value = ''; filterSalesTable(''); }
}

// ===== ACCOUNT PAGE =====
function renderAccount() {
  if (!currentUser) return;
  document.getElementById('account-name').value  = currentUser.full_name;
  document.getElementById('account-email').value = currentUser.email;
  document.getElementById('account-role').value  = currentUser.role;
  document.getElementById('account-password').value = '';
  document.getElementById('display-name').textContent = currentUser.full_name;
  document.getElementById('display-role').textContent = currentUser.role;
  // Show PIN section only for Admins
  const pinSection = document.getElementById('pin-section');
  if (pinSection) {
    pinSection.style.display = currentUser.role === 'Admin' ? '' : 'none';
    const pinInput = document.getElementById('account-pin');
    if (pinInput) pinInput.value = '';
  }
}

function togglePasswordVisibility() {
  const input = document.getElementById('account-password');
  const btn   = document.getElementById('toggle-pass-btn');
  if (input.type === 'password') { input.type = 'text';     btn.textContent = '🙈'; }
  else                           { input.type = 'password'; btn.textContent = '👁️'; }
}

function togglePinVisibility() {
  const input = document.getElementById('account-pin');
  if (!input) return;
  input.type = input.type === 'password' ? 'text' : 'password';
}

async function saveAdminPin() {
  const pin = (document.getElementById('account-pin')?.value || '').trim();
  if (!/^\d{4}$/.test(pin)) {
    Swal.fire({ icon: 'warning', title: 'Invalid PIN', text: 'PIN must be exactly 4 digits (numbers only).', confirmButtonColor: '#2C1A0E' });
    return;
  }
  const data = await apiFetch(`${API.account}?action=set_pin`, {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ id: currentUser.id, pin }),
  });
  if (data.error) { showToast(data.error); return; }
  document.getElementById('account-pin').value = '';
  Swal.fire({ toast: true, position: 'top', icon: 'success', title: 'Admin PIN set ✓', showConfirmButton: false, timer: 2000, timerProgressBar: true });
}

// ===== PIN CONFIRMATION GATE =====
// Returns true if PIN verified, false if cancelled/wrong.
// Caches a short-lived approval so rapid back-to-back actions don't re-prompt.
let _pinApprovedUntil = 0;

async function requireAdminPin(actionLabel = 'this action') {
  if (!currentUser || currentUser.role !== 'Admin') return true; // non-admins skip PIN
  if (Date.now() < _pinApprovedUntil) return true; // still within grace period

  const { value: pin, isConfirmed } = await Swal.fire({
    title: '🔐 Admin PIN required',
    html: `Enter your PIN to <b>${actionLabel}</b>.`,
    input: 'password',
    inputAttributes: { maxlength: 4, inputmode: 'numeric', pattern: '\\d{4}', autocomplete: 'off', placeholder: '• • • •' },
    inputAutoTrim: true,
    showCancelButton: true,
    confirmButtonText: 'Confirm',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#2C1A0E',
    cancelButtonColor: '#7A5C3A',
    customClass: { input: 'pin-input-swal' },
    didOpen: () => {
      // restrict to digits only
      const inp = Swal.getInput();
      inp.addEventListener('input', () => { inp.value = inp.value.replace(/\D/g, '').slice(0, 4); });
    },
    preConfirm: (val) => {
      if (!/^\d{4}$/.test(val || '')) {
        Swal.showValidationMessage('PIN must be 4 digits.');
        return false;
      }
      return val;
    },
  });

  if (!isConfirmed || !pin) return false;

  const data = await apiFetch(`${API.account}?action=verify_pin`, {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ id: currentUser.id, pin }),
  });

  if (data.error) {
    Swal.fire({ icon: 'error', title: 'Wrong PIN', text: data.error, confirmButtonColor: '#2C1A0E', timer: 2000, showConfirmButton: false });
    return false;
  }

  // Grace period: 60 seconds before re-prompting
  _pinApprovedUntil = Date.now() + 60000;
  return true;
}

async function saveAccountChanges() {
  const name     = document.getElementById('account-name').value.trim();
  const email    = document.getElementById('account-email').value.trim();
  const password = document.getElementById('account-password').value;
  if (!name)  { showToast('Please enter your full name.'); return; }
  if (!email) { showToast('Please enter your email.'); return; }

  const payload = { full_name: name, email };
  if (password) payload.password = password;

  const data = await apiFetch(`${API.account}?id=${currentUser.id}`, {
    method:  'PUT',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify(payload),
  });

  if (data.error) { showToast(data.error); return; }

  currentUser.full_name = name;
  currentUser.email     = email;
  document.getElementById('display-name').textContent       = name;
  document.getElementById('nav-user-badge').textContent     = `👤 ${name}`;
  showToast('Account updated successfully! ✓');
}

// ===== ADMIN =====
let currentAdminTab = 'stock';

async function renderAdmin() {
  await loadInventoryStock();
  switchAdminTab(currentAdminTab);
}

async function switchAdminTab(tab) {
  currentAdminTab = tab;
  const tabOrder = ['stock','menu','sales','archive','staff'];
  document.querySelectorAll('.admin-tab-btn').forEach((btn, i) => {
    btn.classList.toggle('active', tabOrder[i] === tab);
  });
  document.querySelectorAll('.admin-tab-panel').forEach(p => p.classList.remove('active'));
  document.getElementById(`admin-${tab}`).classList.add('active');
  if (tab === 'stock')   { await loadInventoryStock(); renderStockGrid(); }
  if (tab === 'menu')    renderMenuEditor();
  if (tab === 'sales')   initAdminSalesTab();
  if (tab === 'archive') { renderArchivedCategoriesGrid(); renderArchiveGrid(); renderArchivedStaffGrid(); }
  if (tab === 'staff')   loadAndRenderStaff();
}

// ===== ADMIN SALES =====
let adminSalesChartView = 'month';

async function initAdminSalesTab() {
  await loadSalesHistory();
  renderAdminSalesSummary();
  renderAdminSalesChart();
  filterAdminSalesTable(document.getElementById('admin-sales-search')?.value || '');
}

function renderAdminSalesSummary() {
  const today     = new Date().toISOString().split('T')[0];
  const yearMonth = today.slice(0, 7);
  const todayOrders = salesHistory.filter(s => s.date === today);
  const monthOrders = salesHistory.filter(s => s.date.startsWith(yearMonth));
  const fmt = v => `₱${v.toLocaleString()}`;
  document.getElementById('admin-summary-today').textContent        = fmt(todayOrders.reduce((s,o) => s + o.total, 0));
  document.getElementById('admin-summary-today-orders').textContent = `${todayOrders.length} order${todayOrders.length !== 1 ? 's' : ''}`;
  document.getElementById('admin-summary-month').textContent        = fmt(monthOrders.reduce((s,o) => s + o.total, 0));
  document.getElementById('admin-summary-month-orders').textContent = `${monthOrders.length} order${monthOrders.length !== 1 ? 's' : ''}`;
  document.getElementById('admin-summary-all').textContent          = fmt(salesHistory.reduce((s,o) => s + o.total, 0));
  document.getElementById('admin-summary-all-orders').textContent   = `${salesHistory.length} order${salesHistory.length !== 1 ? 's' : ''}`;
}

function setAdminChartView(view) {
  adminSalesChartView = view;
  document.querySelectorAll('#admin-sales .chart-view-btn').forEach((btn, i) =>
    btn.classList.toggle('active', ['month','week'][i] === view)
  );
  renderAdminSalesChart();
}

function renderAdminSalesChart() {
  const today = new Date();
  let labels = [], data = [], titleText = '';
  if (adminChartView === 'month') {
    const year  = today.getFullYear();
    const month = today.getMonth();
    const days  = new Date(year, month + 1, 0).getDate();
    titleText = `Daily Sales - ${today.toLocaleString('default', { month: 'long', year: 'numeric' })}`;
    labels = Array.from({ length: days }, (_, i) => String(i + 1).padStart(2, '0'));
    const ym = today.toISOString().slice(0, 7);
    data = labels.map(d => {
      const dateStr = `${ym}-${d}`;
      return salesHistory.filter(s => s.date === dateStr).reduce((sum, s) => sum + s.total, 0);
    });
  } else {
    titleText = 'Daily Sales - Last 7 Days';
    for (let i = 6; i >= 0; i--) {
      const d = new Date(today);
      d.setDate(today.getDate() - i);
      const ds = d.toISOString().split('T')[0];
      labels.push(d.toLocaleDateString('default', { month: 'short', day: 'numeric' }));
      data.push(salesHistory.filter(s => s.date === ds).reduce((sum, s) => sum + s.total, 0));
    }
  }
  document.getElementById('admin-chart-title').textContent = titleText;
  const wrap = document.getElementById('admin-sales-chart-wrap');
  if (!wrap) return;
  const maxVal = Math.max(...data, 1);
  let html = '';
  if (data.every(v => v === 0)) {
    html = '<div class="css-chart-empty">No sales data for this period</div>';
  } else {
    data.forEach((val, i) => {
      const heightPct = (val / maxVal) * 100;
      const label = labels[i];
      html += `<div class="css-bar-col">
        <div class="css-bar-value">${val.toLocaleString()}</div>
        <div class="css-bar" style="height:${Math.max(heightPct, 2)}%"></div>
        <div class="css-bar-label">${label}</div>
      </div>`;
    });
  }
  wrap.innerHTML = html;
}

function filterAdminSalesTable(query) {
  const tbody = document.getElementById('admin-sales-tbody');
  if (!tbody) return;
  const q = query.toLowerCase().trim();
  const filtered = q ? salesHistory.filter(s =>
    s.date.includes(q) ||
    new Date(s.date).toLocaleString('default', { month: 'long' }).toLowerCase().includes(q)
  ) : salesHistory;
  tbody.innerHTML = filtered.map(s => `
    <tr>
      <td>${s.id}</td>
      <td>${s.items}</td>
      <td>₱${s.total.toLocaleString()}</td>
      <td>${s.date}</td>
    </tr>
  `).join('') || '<tr><td colspan="4" style="text-align:center;color:var(--text-muted)">No results found.</td></tr>';
}

function clearAdminSalesSearch() {
  const el = document.getElementById('admin-sales-search');
  if (el) { el.value = ''; filterAdminSalesTable(''); }
}

// ===== STOCK MANAGER =====
// ===== INVENTORY STOCK (raw ingredients from items table) =====
let inventoryStock = []; // [{id, name, unit, current_qty, reorder_level, category, status}]
let pendingStockChanges = {}; // { itemId: new_qty }

async function loadInventoryStock() {
  try {
    const data = await apiFetch(API.stock);
    if (data && data.success) inventoryStock = data.data || [];
  } catch (e) { inventoryStock = []; }
}

async function renderStockGrid() {
  const grid = document.getElementById('stock-grid');
  if (!grid) return;

  // Load fresh data if not loaded yet
  if (inventoryStock.length === 0) await loadInventoryStock();

  const existingBar = document.getElementById('stock-confirm-bar');
  if (existingBar) existingBar.remove();

  if (inventoryStock.length === 0) {
    grid.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-muted)">No inventory items found.</div>';
    return;
  }

  // Group by category
  const byCategory = {};
  inventoryStock.forEach(item => {
    const cat = item.category || 'Other';
    if (!byCategory[cat]) byCategory[cat] = [];
    byCategory[cat].push(item);
  });

  let html = '';
  for (const [category, items] of Object.entries(byCategory)) {
    html += `<div style="margin-bottom:24px"><h3 style="font-size:16px;font-weight:800;color:var(--accent);margin-bottom:12px;border-bottom:1px solid var(--border);padding-bottom:6px">${category}</h3>`;
    html += '<div class="stock-grid-inner" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px">';
    for (const item of items) {
      const displayQty = pendingStockChanges[item.id] !== undefined ? pendingStockChanges[item.id] : item.current_qty;
      const changed = pendingStockChanges[item.id] !== undefined;
      const status = item.current_qty <= 0 ? 'out' : item.current_qty <= item.reorder_level ? 'low' : 'ok';
      const statusColors = { ok: '#4f7a4a', low: '#c99a5b', out: '#a8492f' };
      const statusBg = { ok: '#e2ecdf', low: '#fff8ea', out: '#f6e3dc' };
      const statusText = { ok: `★ ${item.current_qty} ${item.unit}`, low: `⚠ Low: ${item.current_qty}/${item.reorder_level} ${item.unit}`, out: `✗ Out of stock` };
      html += `
        <div class="stock-card ${changed ? 'stock-card-changed' : ''}" style="background:#fff;border:1px solid var(--border);border-radius:12px;padding:16px;${changed ? 'border-color:#8a5a2b' : ''}">
          <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px">
            <div><strong style="font-size:15px">${item.name}</strong><div style="font-size:12px;color:var(--text-muted);margin-top:2px">${item.code || ''} · ${item.unit}</div></div>
            <span style="display:inline-block;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;background:${statusBg[status]};color:${statusColors[status]}">${statusText[status]}</span>
          </div>
          <div style="display:flex;align-items:center;gap:8px;margin-top:8px">
            <button onclick="adjustStock(${item.id},-1)" style="width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:var(--bg);font-size:16px;font-weight:700;cursor:pointer">−</button>
            <input type="number" min="0" value="${displayQty}" onchange="setStockPending(${item.id},this.value)" oninput="this.value=Math.max(0,parseInt(this.value)||0);setStockPending(${item.id},this.value)" style="width:70px;text-align:center;padding:6px;border:1px solid var(--border);border-radius:8px;font-size:15px;font-weight:700" />
            <button onclick="adjustStock(${item.id},1)" style="width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:var(--bg);font-size:16px;font-weight:700;cursor:pointer">+</button>
            <span style="font-size:12px;color:var(--text-muted);margin-left:4px">${item.unit}</span>
          </div>
          <div style="font-size:11px;color:var(--text-muted);margin-top:6px">Reorder at: ${item.reorder_level} ${item.unit}</div>
          ${changed ? `<div style="display:flex;gap:8px;margin-top:10px"><button onclick="discardOneStock(${item.id})" style="flex:1;padding:8px;border-radius:8px;border:1px solid var(--border);background:transparent;font-size:12px;font-weight:700;cursor:pointer">✕ Discard</button><button onclick="saveOneStock(${item.id})" style="flex:1;padding:8px;border-radius:8px;border:none;background:#3b2313;color:#fff;font-size:12px;font-weight:700;cursor:pointer">✓ Save</button></div>` : ''}
        </div>`;
    }
    html += '</div></div>';
  }

  grid.innerHTML = html;
}

function adjustStock(itemId, delta) {
  const item = inventoryStock.find(i => i.id === itemId);
  if (!item) return;
  const current = pendingStockChanges[itemId] !== undefined ? pendingStockChanges[itemId] : item.current_qty;
  pendingStockChanges[itemId] = Math.max(0, current + delta);
  renderStockGrid();
}

function setStockPending(itemId, value) {
  const parsed = parseFloat(value);
  if (!isNaN(parsed)) pendingStockChanges[itemId] = Math.max(0, parsed);
}

function discardOneStock(itemId) {
  delete pendingStockChanges[itemId];
  renderStockGrid();
}

async function saveOneStock(itemId) {
  const newQty = pendingStockChanges[itemId];
  if (newQty === undefined) return;
  const item = inventoryStock.find(i => i.id === itemId);
  if (!item) return;

  try {
    const r = await fetch(API.stock, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ id: itemId, current_qty: newQty, reorder_level: item.reorder_level })
    });
    const d = await r.json();
    if (d.success) {
      item.current_qty = newQty;
      delete pendingStockChanges[itemId];
      renderStockGrid();
      showToast(`${item.name} updated to ${newQty} ${item.unit}.`);
    } else {
      showToast(d.message || 'Save failed.', true);
    }
  } catch (e) { showToast('Cannot save stock.', true); }
}

// ===== STAFF MANAGEMENT =====
let staffList = [];

async function loadAndRenderStaff() {
  const data = await apiFetch(`${API.account}?action=list`);
  if (data.success) {
    staffList = data.data;
    renderStaffTable();
  }
}

function renderStaffTable() {
  const tbody = document.getElementById('staff-tbody');
  if (!tbody) return;
  tbody.innerHTML = staffList.map(s => `
    <tr>
      <td>${s.full_name}</td>
      <td>${s.email}</td>
      <td><span class="role-pill role-${s.role.toLowerCase()}">${s.role}</span></td>
      <td>
        <button class="btn-primary btn-sm" onclick="openEditStaff(${s.id})">Edit</button>
        ${parseInt(s.id) !== parseInt(currentUser.id)
          ? `<button class="btn-danger btn-sm" style="margin-left:0.4rem" onclick="deleteStaff(${s.id})">Remove</button>`
          : `<span style="font-size:0.78rem;color:var(--text-muted);margin-left:0.5rem">(you)</span>`}
      </td>
    </tr>
  `).join('');
}

function openAddStaff()    { openStaffModal(null); }
function openEditStaff(id) { openStaffModal(staffList.find(s => parseInt(s.id) === parseInt(id))); }

function splitStaffName(fullName) {
  const parts = String(fullName || '').trim().split(/\s+/).filter(Boolean);
  return { firstName: parts.shift() || '', lastName: parts.join(' ') };
}

function openStaffModal(staff) {
  const isEdit = !!staff;
  const name = splitStaffName(staff?.full_name);
  document.getElementById('modal-title').textContent   = isEdit ? 'Edit staff' : 'Add staff';
  document.getElementById('modal-message').textContent = '';
  document.getElementById('modal-body').innerHTML = `
    <div class="modal-form">
      <div class="staff-name-row">
        <div class="form-group">
          <label for="mf-first-name">First name</label>
          <input id="mf-first-name" type="text" value="${escapeHtml(name.firstName)}" placeholder="First name" autocomplete="given-name" />
        </div>
        <div class="form-group">
          <label for="mf-last-name">Last name</label>
          <input id="mf-last-name" type="text" value="${escapeHtml(name.lastName)}" placeholder="Last name" autocomplete="family-name" />
        </div>
      </div>
      <div class="form-group">
        <label for="mf-email">Email</label>
        <input id="mf-email" type="email" value="${escapeHtml(staff?.email || '')}" placeholder="email@example.com" autocomplete="email" />
      </div>
      <div class="form-group">
        <label for="mf-password">${isEdit ? 'New password (leave blank to keep)' : 'Password'}</label>
        <input id="mf-password" type="password" placeholder="${isEdit ? 'New password' : 'Password'}" autocomplete="new-password" />
      </div>
      <div class="form-group">
        <label for="mf-role">Role</label>
        <select id="mf-role">
          <option value="Cashier" ${(staff?.role === 'Cashier' || !staff) ? 'selected' : ''}>Cashier</option>
          <option value="Admin"   ${staff?.role === 'Admin'   ? 'selected' : ''}>Admin</option>
        </select>
      </div>
    </div>
  `;
  document.getElementById('modal-actions').innerHTML = `
    <button class="btn-primary" onclick="saveStaff(${staff?.id || 'null'})">
      ${isEdit ? 'Save changes' : 'Add staff'}
    </button>
    <button class="btn-cancel" onclick="closeModal()">Cancel</button>
  `;
  document.getElementById('modal-overlay').classList.add('active');
  modalCallback = null;
}

async function saveStaff(id) {
  const firstName = document.getElementById('mf-first-name').value.trim();
  const lastName  = document.getElementById('mf-last-name').value.trim();
  const name      = [firstName, lastName].filter(Boolean).join(' ');
  const email     = document.getElementById('mf-email').value.trim().toLowerCase();
  const password  = document.getElementById('mf-password').value;
  const role      = document.getElementById('mf-role').value;

  // Collect all empty required fields
  const emptyFields = [];
  if (!firstName) emptyFields.push('First name');
  if (!lastName)  emptyFields.push('Last name');
  if (!email) emptyFields.push('Email');
  if (!id && !password) emptyFields.push('Password');

  if (emptyFields.length > 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Oops! A few fields need your attention',
      html: `Please fill in the following field${emptyFields.length > 1 ? 's' : ''}: <br><b>${emptyFields.join(', ')}</b>`,
      confirmButtonColor: '#2C1A0E',
      confirmButtonText: 'Got it',
    });
    return;
  }

  if (id) {
    // Edit existing via PUT
    const payload = { full_name: name, email, role };
    if (password) payload.password = password;
    const data = await apiFetch(`${API.account}?id=${id}`, {
      method:  'PUT',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload),
    });
    if (data.error) {
      Swal.fire({ icon: 'error', title: 'Update failed', text: data.error, confirmButtonColor: '#2C1A0E' });
      return;
    }
    Swal.fire({ toast: true, position: 'top', icon: 'success', title: 'Staff updated ✓', showConfirmButton: false, timer: 2000, timerProgressBar: true });
  } else {
    // Add new via POST (admin_create - tagged as 'admin_created' in DB)
    const data = await apiFetch(`${API.account}?action=admin_create`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ full_name: name, email, password, role }),
    });
    if (data.error) {
      Swal.fire({ icon: 'error', title: 'Could not add staff', text: data.error, confirmButtonColor: '#2C1A0E' });
      return;
    }
    Swal.fire({ toast: true, position: 'top', icon: 'success', title: 'Staff added ✓', showConfirmButton: false, timer: 2000, timerProgressBar: true });
  }
  closeModal();
  await loadAndRenderStaff();
}

async function deleteStaff(id) {
  if (!await requireAdminPin('archive this staff account')) return;
  const result = await Swal.fire({
    title: 'Archive staff?',
    text: 'This will archive the staff account. They will no longer be able to log in.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, archive',
    confirmButtonColor: '#C0392B',
    cancelButtonColor: '#7A5C3A',
  });
  if (!result.isConfirmed) return;

  const data = await apiFetch(`${API.account}?action=archive&id=${id}`, { method: 'DELETE' });
  if (data.error) { showToast(data.error); return; }
  Swal.fire({ toast: true, position: 'top', icon: 'success', title: 'Staff archived.', showConfirmButton: false, timer: 2000, timerProgressBar: true });
  await loadAndRenderStaff();
}

// ===== MENU EDITOR =====
function renderMenuEditor() {
  renderMenuEditorHeader();
  const grid = document.getElementById('menu-editor-grid');
  if (!grid) return;
  grid.innerHTML = menuItems.map(item => `
    <div class="menu-editor-card">
      <span class="menu-editor-emoji">${item.image ? `<img src="${item.image}" alt="${item.name}" class="item-img editor-img" onerror="this.style.display='none'" />` : item.emoji}</span>
      <div class="menu-editor-info">
        <h4>${item.name}</h4>
        <div class="cat">${item.category}</div>
        <div class="pri">₱${item.price} · Stock: ${item.stock}</div>
      </div>
      <div class="menu-editor-actions">
        <button class="btn-primary btn-sm" onclick="openEditItem(${item.id})">Edit</button>
        <button class="btn-danger btn-sm"  onclick="deleteItem(${item.id})">Del</button>
      </div>
    </div>
  `).join('') || '<p style="color:var(--text-muted);padding:1rem;font-size:0.9rem">No items yet.</p>';
}

function renderMenuEditorHeader() {
  const hdr = document.querySelector('#admin-menu .admin-section-header');
  if (!hdr) return;
  // Remove old tag list if present
  hdr.querySelector('.cat-tag-list')?.remove();
  const customCats = allCategories.filter(c => c.is_default == 0);
  if (customCats.length > 0) {
    const tagList = document.createElement('div');
    tagList.className = 'cat-tag-list';
    tagList.innerHTML = customCats.map(c =>
      `<span class="cat-tag">${c.name}<button class="cat-tag-del" onclick="promptDeleteCategory(${c.id},'${c.name.replace(/'/g,"\\'")}')">✕</button></span>`
    ).join('');
    hdr.appendChild(tagList);
  }
}

async function openAddItem()    { await loadCategories(); openItemModal(null); }
async function openEditItem(id) { await loadCategories(); openItemModal(menuItems.find(i => i.id === id)); }

function openItemModal(item) {
  const isEdit = !!item;
  const cats   = getCategoryNames();
  document.getElementById('modal-title').textContent   = isEdit ? 'Edit item' : 'Add item';
  document.getElementById('modal-message').textContent = '';

  const currentImage = item?.image
    ? `<div class="img-preview-wrap" id="img-preview-wrap">
         <img id="img-preview" src="${item.image}" alt="Current image" />
         <button type="button" class="img-remove-btn" onclick="removeItemImage()">✕ Remove</button>
       </div>`
    : `<div class="img-preview-wrap" id="img-preview-wrap" style="display:none">
         <img id="img-preview" src="" alt="Preview" />
         <button type="button" class="img-remove-btn" onclick="removeItemImage()">✕ Remove</button>
       </div>`;

  document.getElementById('modal-body').innerHTML = `
    <div class="modal-form">
      <div class="form-group">
        <label>Name</label>
        <input id="mi-name" type="text" value="${item?.name || ''}" placeholder="Item name" />
      </div>
      <div class="form-group">
        <label>Category</label>
        <select id="mi-category">
          <option value="" disabled ${!item?.category ? 'selected' : ''}>-- Select category --</option>
          ${(() => {
            const itemCat = (item?.category || '').trim();
            const allOpts = [...cats];
            // If item's category isn't in the list, add it so it stays selected
            if (itemCat && !allOpts.some(c => c.trim().toLowerCase() === itemCat.toLowerCase())) {
              allOpts.push(itemCat);
            }
            return allOpts.map(c => `<option value="${c}" ${itemCat.toLowerCase() === c.trim().toLowerCase() ? 'selected' : ''}>${c}</option>`).join('');
          })()}
        </select>
      </div>
      <div class="form-group">
        <label>Price (₱)</label>
        <input id="mi-price" type="number" min="1" max="100000" step="0.01" value="${item?.price || ''}" placeholder="0" oninput="if(parseFloat(this.value)>100000)this.value=100000;if(parseFloat(this.value)<1||this.value==='-')this.value=''" onkeydown="if(event.key==='-'||event.key==='e')event.preventDefault()" />
      </div>
      <div class="form-group">
        <label>Image <span style="font-size:0.8rem;color:var(--text-muted)">(JPG/PNG/WEBP, max 5 MB)</span></label>
        ${currentImage}
        <label class="img-upload-btn" for="mi-image">📷 ${item?.image ? 'Change image' : 'Upload image'}</label>
        <input id="mi-image" type="file" accept="image/*" style="display:none" onchange="previewItemImage(this)" />
        <input id="mi-remove-image" type="hidden" value="0" />
      </div>
      <div class="form-group">
        <label>Emoji <span style="font-size:0.8rem;color:var(--text-muted)">(used if no image)</span></label>
        <input id="mi-emoji" type="text" value="${item?.emoji || '☕'}" placeholder="☕" maxlength="4" />
      </div>
      <div class="form-group">
        <label>Stock quantity <span style="font-size:0.8rem;color:#7A5C3A">(0–99)</span></label>
        <input id="mi-stock" type="number" min="0" max="99" value="${item?.stock ?? ''}" placeholder="0"
          oninput="this.value = Math.min(99, Math.max(0, parseInt(this.value)||0))" />
      </div>
      <div class="form-group" style="flex-direction:row;align-items:center;gap:0.5rem">
        <input id="mi-featured" type="checkbox" ${item?.featured ? 'checked' : ''} style="width:auto;cursor:pointer" />
        <label for="mi-featured" style="cursor:pointer">Featured on home page</label>
      </div>
      <div class="form-group">
        <label>Short description</label>
        <input id="mi-desc" type="text" value="${item?.description || ''}" placeholder="Brief item description" maxlength="100" />
      </div>
    </div>
  `;
  document.getElementById('modal-actions').innerHTML = `
    <button class="btn-primary" onclick="saveItem(${item?.id || 'null'})">
      ${isEdit ? 'Save changes' : 'Add item'}
    </button>
    <button class="btn-cancel" onclick="closeModal()">Cancel</button>
  `;
  document.getElementById('modal-overlay').classList.add('active');
  modalCallback = null;

  // Set category dropdown value after DOM is ready
  const targetCategory = item?.category || '';
  setTimeout(() => {
    const sel = document.getElementById('mi-category');
    if (!sel) return;
    // Try exact match first
    let matched = false;
    for (let i = 0; i < sel.options.length; i++) {
      if (sel.options[i].value.trim().toLowerCase() === targetCategory.trim().toLowerCase()) {
        sel.selectedIndex = i;
        matched = true;
        break;
      }
    }
    // If no match found, keep first option but warn
    if (!matched && targetCategory) {
      console.warn('Category not found in options:', targetCategory, [...sel.options].map(o => o.value));
    }
  }, 0);
}

function previewItemImage(input) {
  if (!input.files || !input.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => {
    const wrap = document.getElementById('img-preview-wrap');
    const img  = document.getElementById('img-preview');
    img.src = e.target.result;
    wrap.style.display = '';
    document.getElementById('mi-remove-image').value = '0';
  };
  reader.readAsDataURL(input.files[0]);
}

function removeItemImage() {
  document.getElementById('img-preview-wrap').style.display = 'none';
  document.getElementById('img-preview').src = '';
  const fileInput = document.getElementById('mi-image');
  if (fileInput) fileInput.value = '';
  document.getElementById('mi-remove-image').value = '1';
}

async function saveItem(id) {
  const name        = document.getElementById('mi-name').value.trim();
  const sel         = document.getElementById('mi-category');
  const category    = sel ? sel.options[sel.selectedIndex].value.trim() : '';
  console.log('Saving item - category selected:', category, '| selectedIndex:', sel?.selectedIndex);
  const price       = parseFloat(document.getElementById('mi-price').value);
  const emoji       = document.getElementById('mi-emoji').value.trim() || '☕';
  const stock       = parseInt(document.getElementById('mi-stock').value, 10) || 0;
  const featured    = document.getElementById('mi-featured').checked ? 1 : 0;
  const description = document.getElementById('mi-desc').value.trim();
  const removeImage = document.getElementById('mi-remove-image')?.value === '1' ? '1' : '0';
  const imageFile   = document.getElementById('mi-image')?.files?.[0];

  const emptyFields = [];
  if (!name)                              emptyFields.push('Item name');
  if (!category)                          emptyFields.push('Category');
  if (isNaN(price) || price < 1 || price > 100000) emptyFields.push('Price (must be ₱1 – ₱100,000)');
  if (stock < 0 || stock > 99)            emptyFields.push('Stock (must be 0–99)');

  if (emptyFields.length > 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Please fill in all required fields',
      html: emptyFields.map(f => `• <b>${f}</b>`).join('<br>'),
      confirmButtonColor: '#2C1A0E',
      confirmButtonText: 'Got it',
    });
    return;
  }
  // Use FormData so we can attach the image file
  const fd = new FormData();
  fd.append('name',         name);
  fd.append('category',     category);
  fd.append('price',        price);
  fd.append('emoji',        emoji);
  fd.append('stock',        stock);
  fd.append('featured',     featured);
  fd.append('description',  description);
  fd.append('remove_image', removeImage);
  if (imageFile) fd.append('image', imageFile);

  // For updates we tunnel through POST with ?id= and PHP reads $_POST + $_FILES
  const url  = id ? `${API.items}?id=${id}&_update=1` : API.items;
  const data = await apiFetch(url, { method: 'POST', body: fd });
  if (data.error) { showToast(data.error); return; }
  showToast(id ? 'Item updated ✓' : 'Item added ✓');

  closeModal();
  await loadMenuItems();
  renderMenuEditor();
  renderCategoryTabs();
  renderItemsForCategory(activeTab);
}

async function deleteItem(id) {
  const item = menuItems.find(i => i.id === id);
  if (!await requireAdminPin(`archive "${item?.name}"`)) return;
  const result = await Swal.fire({
    title: '🗂️ Archive item?',
    html: `<b>${item?.name}</b> will be removed from the menu and moved to the Archive. You can restore it later.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, archive it',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#7A5C3A',
    cancelButtonColor: '#2C1A0E',
  });
  if (!result.isConfirmed) return;
  const data = await apiFetch(`${API.items}?id=${id}`, { method: 'DELETE' });
  if (data.error) { showToast(data.error); return; }
  await loadMenuItems();
  renderMenuEditor();
  showToast(`"${item?.name}" archived. Find it in 🗂️ Archive.`);
}

// ===== ARCHIVE =====
let archivedItems = [];

async function renderArchivedCategoriesGrid() {
  const grid = document.getElementById('archived-categories-grid');
  if (!grid) return;
  grid.innerHTML = '<div class="archive-empty">Loading…</div>';

  const data = await apiFetch(`${API.categories}?action=archived`);
  if (!data.success) {
    grid.innerHTML = '<div class="archive-empty">Failed to load archived categories.</div>';
    return;
  }

  if (!data.data || data.data.length === 0) {
    grid.innerHTML = `
      <div class="archive-empty">
        <div style="font-size:2.5rem;margin-bottom:0.75rem">🏷️</div>
        <div style="font-weight:700;font-size:1rem;margin-bottom:0.25rem">No archived categories</div>
        <div style="font-size:0.88rem;color:var(--text-muted)">Categories you remove will appear here.</div>
      </div>`;
    return;
  }

  grid.innerHTML = data.data.map(cat => {
    const archivedOn = cat.deleted_at
      ? new Date(cat.deleted_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
      : 'Unknown';
    return `
      <div class="archive-card">
        <div class="archive-card-emoji" style="font-size:2rem">${cat.emoji || '🏷️'}</div>
        <div class="archive-card-info">
          <div class="archive-card-name">${escapeHtml(cat.name)}</div>
          <div class="archive-card-date">Archived on ${archivedOn}</div>
        </div>
        <div class="archive-card-actions">
          <button class="btn-restore" onclick="restoreCategoryFromArchive(${cat.id}, '${escapeHtml(cat.name)}')">\u21a9 Restore</button>
        </div>
      </div>
    `;
  }).join('');
}

async function restoreCategoryFromArchive(id, name) {
  const swalResult = await Swal.fire({
    title: `\u21a9 Restore "${name}"?`,
    html: 'Do you also want to restore all items that were archived along with this category?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Restore category + items',
    denyButtonText: 'Category only',
    cancelButtonText: 'Cancel',
    showDenyButton: true,
    confirmButtonColor: '#2C1A0E',
    denyButtonColor: '#7A5C3A',
    cancelButtonColor: '#aaa',
  });
  if (swalResult.isDismissed) return;

  const data = await apiFetch(`${API.categories}?id=${id}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ restore_items: swalResult.isConfirmed }),
  });
  if (data.error) { showToast(data.error); return; }

  const itemMsg = swalResult.isConfirmed && data.restored_items > 0
    ? ` ${data.restored_items} item(s) also restored.`
    : '';

  await loadCategories();
  await loadMenuItems();
  renderArchivedCategoriesGrid();
  renderArchiveGrid();
  renderMenuEditor();
  refreshItemsFilter();
  renderCategoryTabs();

  Swal.fire({ toast: true, position: 'top', icon: 'success',
    title: `"${name}" restored.${itemMsg}`,
    showConfirmButton: false, timer: 3000, timerProgressBar: true });
}


async function renderArchiveGrid() {
  const grid = document.getElementById('archive-grid');
  if (!grid) return;
  grid.innerHTML = '<div class="archive-empty">Loading…</div>';

  const data = await apiFetch(`${API.items}?action=archive`);
  if (!data.success) {
    grid.innerHTML = '<div class="archive-empty">Failed to load archived items.</div>';
    return;
  }

  archivedItems = data.data;

  if (archivedItems.length === 0) {
    grid.innerHTML = `
      <div class="archive-empty">
        <div style="font-size:2.5rem;margin-bottom:0.75rem">🗂️</div>
        <div style="font-weight:700;font-size:1rem;margin-bottom:0.25rem">No archived items</div>
        <div style="font-size:0.88rem;color:var(--text-muted)">Items you delete from the Menu Editor will appear here.</div>
      </div>`;
    return;
  }

  grid.innerHTML = archivedItems.map(item => {
    const archivedOn = item.deleted_at
      ? new Date(item.deleted_at).toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' })
      : 'Unknown';
    return `
      <div class="archive-card">
        <div class="archive-card-emoji">${item.image ? `<img src="${item.image}" alt="${item.name}" class="item-img archive-img" onerror="this.style.display='none'" />` : item.emoji}</div>
        <div class="archive-card-info">
          <div class="archive-card-name">${item.name}</div>
          <div class="archive-card-meta">${item.category} · ₱${parseFloat(item.price).toLocaleString()}</div>
          <div class="archive-card-date">Archived on ${archivedOn}</div>
        </div>
        <div class="archive-card-actions">
          <button class="btn-restore" onclick="restoreItem(${item.id})">↩ Restore</button>
        </div>
      </div>
    `;
  }).join('');
}

async function restoreItem(id) {
  const item = archivedItems.find(i => parseInt(i.id) === parseInt(id));
  const result = await Swal.fire({
    title: '↩ Restore item?',
    html: `<b>${item?.name}</b> will be moved back to the active menu.`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Yes, restore',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#2C1A0E',
    cancelButtonColor: '#7A5C3A',
  });
  if (!result.isConfirmed) return;

  const data = await apiFetch(`${API.items}?id=${id}&action=restore`, { method: 'PUT' });
  if (data.error) { showToast(data.error); return; }
  await loadMenuItems();
  renderArchiveGrid();
  showToast(`"${item?.name}" restored to the menu ✓`);
}

// ===== ARCHIVED STAFF =====
async function renderArchivedStaffGrid() {
  const grid = document.getElementById('archived-staff-grid');
  if (!grid) return;
  grid.innerHTML = '<div class="archive-empty">Loading…</div>';

  const data = await apiFetch(`${API.account}?action=archived`);
  if (!data.success || !data.data) {
    grid.innerHTML = '<div class="archive-empty">Failed to load archived staff.</div>';
    return;
  }

  if (data.data.length === 0) {
    grid.innerHTML = `
      <div class="archive-empty">
        <div style="font-size:2.5rem;margin-bottom:0.75rem">👥</div>
        <div style="font-weight:700;font-size:1rem;margin-bottom:0.25rem">No archived staff</div>
        <div style="font-size:0.88rem;color:var(--text-muted)">Staff you archive will appear here.</div>
      </div>`;
    return;
  }

  grid.innerHTML = data.data.map(s => `
    <div class="archive-card">
      <div class="archive-card-emoji" style="font-size:2rem">👤</div>
      <div class="archive-card-info">
        <div class="archive-card-name">${escapeHtml(s.full_name)}</div>
        <div class="archive-card-meta">${escapeHtml(s.email)} · <span class="role-pill role-${(s.role||'staff').toLowerCase()}">${s.role}</span></div>
      </div>
      <div class="archive-card-actions">
        <button class="btn-restore" onclick="restoreStaffAccount(${s.id}, '${escapeHtml(s.full_name)}')">↩ Restore</button>
      </div>
    </div>
  `).join('');
}

async function restoreStaffAccount(id, name) {
  const result = await Swal.fire({
    title: `Restore ${name}?`,
    text: 'This will reactivate their staff account and allow them to log in again.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Yes, restore',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#2C1A0E',
    cancelButtonColor: '#7A5C3A',
  });
  if (!result.isConfirmed) return;

  const newSource = 'admin_created';
  const data = await apiFetch(`${API.account}?action=restore_staff&id=${id}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ source: newSource }),
  });
  if (data.error) { showToast('Error: ' + data.error); return; }
  Swal.fire({ toast: true, position: 'top', icon: 'success', title: `${name} restored ✓`, showConfirmButton: false, timer: 2000, timerProgressBar: true });
  renderArchivedStaffGrid();
  loadAndRenderStaff();
}

async function permanentDeleteItem(id) {
  const item = archivedItems.find(i => parseInt(i.id) === parseInt(id));
  const result = await Swal.fire({
    title: '⚠️ Permanently delete?',
    html: `<b>${item?.name}</b> will be removed forever. This cannot be undone.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, delete permanently',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#C0392B',
    cancelButtonColor: '#7A5C3A',
  });
  if (!result.isConfirmed) return;

  const data = await apiFetch(`${API.items}?id=${id}&action=permanent`, { method: 'DELETE' });
  if (data.error) { showToast(data.error); return; }
  renderArchiveGrid();
  showToast(`"${item?.name}" permanently deleted.`);
}

// ===== TOAST =====
function showToast(message) {
  const toast = document.getElementById('toast');
  toast.textContent = message;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 2500);
}

// ===== MODAL =====
let modalCallback = null;
function showModal(title, message, onConfirm) {
  document.getElementById('modal-title').textContent   = title;
  document.getElementById('modal-message').textContent = message;
  document.getElementById('modal-body').innerHTML = '';
  document.getElementById('modal-actions').innerHTML = `
    <button class="btn-primary" onclick="confirmModal()">Yes, confirm</button>
    <button class="btn-cancel"  onclick="closeModal()">Cancel</button>
  `;
  document.getElementById('modal-overlay').classList.add('active');
  modalCallback = onConfirm;
}
function confirmModal() { if (modalCallback) modalCallback(); closeModal(); }
function closeModal()   { document.getElementById('modal-overlay').classList.remove('active'); modalCallback = null; }

// ===== PENDING ORDERS PAGE (Kiosk-placed, awaiting payment) =====
// Its own sidebar page, separate from End-of-day sales, since it's the
// most time-sensitive thing a Cashier needs to see.
let pendingAutoRefresh = null;

async function initPendingPage() {
  await loadCashierPending();
  if (pendingAutoRefresh) clearInterval(pendingAutoRefresh);
  pendingAutoRefresh = setInterval(() => {
    if (document.getElementById('page-pending')?.classList.contains('active')) {
      loadCashierPending();
    }
  }, 10000);
}

// ===== END-OF-DAY SALES PAGE =====
// Two views: Items sold today, and Receipt history.
let cashierAutoRefresh = null;
let cashierActiveView = 'sold'; // 'sold' | 'receipts'

async function initCashierPage() {
  await Promise.all([loadCashierDailySummary(), loadCashierReceipts()]);
  // Refresh the report periodically while it is open.
  if (cashierAutoRefresh) clearInterval(cashierAutoRefresh);
  cashierAutoRefresh = setInterval(() => {
    if (document.getElementById('page-cashier')?.classList.contains('active')) {
      loadCashierDailySummary();
      loadCashierReceipts();
    }
  }, 15000);
}

function selectCashierView(view) {
  cashierActiveView = view;
  document.getElementById('cashier-view-tab-sold').classList.toggle('active', view === 'sold');
  document.getElementById('cashier-view-tab-receipts').classList.toggle('active', view === 'receipts');
  document.getElementById('cashier-panel-sold').style.display = view === 'sold' ? '' : 'none';
  document.getElementById('cashier-panel-receipts').style.display = view === 'receipts' ? '' : 'none';
}

// ---- Pending orders (placed on the Kiosk, awaiting payment) ----
async function loadCashierPending() {
  const list = document.getElementById('cashier-pending-list');
  const countBadge = document.getElementById('side-pending-count');
  if (!list) return;
  try {
    const data = await apiFetch(`${API.orders}?action=pending`);
    if (!data.success || !Array.isArray(data.data)) {
      list.innerHTML = '<div class="cashier-empty">Unable to load pending orders.</div>';
      return;
    }

    const orders = data.data;
    if (countBadge) {
      countBadge.style.display = orders.length ? '' : 'none';
      countBadge.textContent = orders.length ? `(${orders.length})` : '';
    }

    if (!orders.length) {
      list.innerHTML = '<div class="cashier-empty">No pending orders right now.</div>';
      return;
    }

    const money = value => `₱${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
    const typeIcon = t => t === 'Dine In' ? '🍽️' : '🥡';
    const time = iso => {
      const d = new Date(iso.replace(' ', 'T'));
      return isNaN(d) ? '' : d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
    };
    // JSON is HTML-encoded because it is placed in an inline click handler.
    // This lets the payment counter receive all order details without a second
    // request and safely supports product names containing quotes or ampersands.
    const handlerJson = value => JSON.stringify(value)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    list.innerHTML = orders.map(o => {
      const paymentDetails = {
        orderType: o.order_type || 'Order type unavailable',
        itemsSummary: o.items_summary || '',
        createdAt: time(o.created_at) || 'Time unavailable',
      };
      return `
        <div class="cashier-order-card">
          <div class="cashier-order-card-header">
            <span class="cashier-order-card-code">${escapeHtml(o.order_code || 'Order')}</span>
            <span class="cashier-order-card-type">${typeIcon(o.order_type)} ${escapeHtml(o.order_type || 'Order type')}</span>
          </div>
          <div class="cashier-order-card-time">${time(o.created_at)}</div>
          <div class="cashier-order-card-items">${escapeHtml(o.items_summary || '—')}</div>
          <div class="cashier-order-card-footer">
            <span class="cashier-order-card-total">${money(o.total)}</span>
            <div class="cashier-order-actions">
              <button type="button" class="cashier-cancel-order-btn" onclick="cancelPendingOrder(${Number(o.id)}, ${handlerJson(String(o.order_code || 'Order'))})">Cancel</button>
              <button type="button" class="btn-primary cashier-collect-order-btn" onclick="collectOrderPayment(${Number(o.id)}, ${handlerJson(String(o.order_code || ''))}, ${Number(o.total) || 0}, ${handlerJson(paymentDetails)})">Collect payment</button>
            </div>
          </div>
        </div>
      `;
    }).join('');
  } catch (err) {
    console.error('loadCashierPending failed:', err);
    list.innerHTML = '<div class="cashier-empty">Unable to load pending orders.</div>';
  }
}

async function cancelPendingOrder(orderId, orderCode) {
  const result = await Swal.fire({
    icon: 'warning',
    title: `Cancel ${orderCode}?`,
    text: 'This pending order will be cancelled. Its reserved menu stock and all linked recipe ingredients will be returned to inventory.',
    showCancelButton: true,
    confirmButtonText: 'Yes, cancel order',
    cancelButtonText: 'Keep order',
    confirmButtonColor: '#C0392B',
    cancelButtonColor: '#7A5C3A',
  });
  if (!result.isConfirmed) return;

  try {
    const data = await apiFetch(`${API.orders}?id=${Number(orderId)}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ status: 'Voided' }),
    });
    if (!data.success) {
      Swal.fire({ icon: 'error', title: 'Could not cancel order', text: data.message || 'Please try again.', confirmButtonText: 'OK', confirmButtonColor: '#2C1A0E' });
      return;
    }

    // The API restores both menu-item stock and every recipe ingredient in the
    // same database transaction. Clear the cached raw-stock view so it reloads
    // fresh if the user opens Inventory afterwards.
    if (typeof inventoryStock !== 'undefined') inventoryStock = [];
    if (typeof pendingStockChanges !== 'undefined') pendingStockChanges = {};
    Swal.fire({
      icon: 'success',
      title: 'Order cancelled',
      text: `${orderCode} was cancelled. The reserved products and recipe ingredients are back in stock.`,
      confirmButtonText: 'OK',
      confirmButtonColor: '#2C1A0E',
    });
    loadCashierPending();
    loadCashierReceipts();
    loadCashierDailySummary();
  } catch (err) {
    console.error('cancelPendingOrder failed:', err);
    Swal.fire({ icon: 'error', title: 'Could not cancel order', text: 'A network error occurred.', confirmButtonText: 'OK', confirmButtonColor: '#2C1A0E' });
  }
}

async function collectOrderPayment(orderId, orderCode, total, details = {}) {
  const cashReceived = await window.openPaymentModal(total, orderCode, details);
  if (cashReceived === null) return; // cancelled

  try {
    const data = await apiFetch(`${API.orders}?action=collect_payment&id=${orderId}`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ cash_received: cashReceived }),
    });
    if (data.success) {
      Swal.fire({
        icon: 'success', title: 'Payment collected',
        text: `${orderCode} is now Paid. Change due: ₱${Number(data.change_given || 0).toLocaleString()}`,
        confirmButtonText: 'OK', confirmButtonColor: '#2C1A0E',
      });
      loadCashierPending();
      loadCashierReceipts();
      loadCashierDailySummary();
    } else {
      Swal.fire({ icon: 'error', title: 'Could not collect payment', text: data.message || 'Please try again.', confirmButtonText: 'OK', confirmButtonColor: '#2C1A0E' });
    }
  } catch (err) {
    console.error('collectOrderPayment failed:', err);
    Swal.fire({ icon: 'error', title: 'Could not collect payment', text: 'A network error occurred.', confirmButtonText: 'OK', confirmButtonColor: '#2C1A0E' });
  }
}

// ---- Receipt history (all of today's orders, any status) ----
async function loadCashierReceipts() {
  const list = document.getElementById('cashier-receipts-list');
  if (!list) return;
  try {
    const today = new Date().toISOString().slice(0, 10);
    const data = await apiFetch(`${API.orders}?date=${today}`);
    if (!data.success || !Array.isArray(data.data)) {
      list.innerHTML = '<div class="cashier-empty">Unable to load receipt history.</div>';
      return;
    }

    const orders = data.data;
    if (!orders.length) {
      list.innerHTML = '<div class="cashier-empty">No orders yet today.</div>';
      return;
    }

    const money = value => `₱${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
    const typeIcon = t => t === 'Dine In' ? '🍽️' : '🥡';
    const time = iso => {
      const d = new Date(iso.replace(' ', 'T'));
      return isNaN(d) ? '' : d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
    };
    const receiptStatus = status => {
      const normalized = String(status || 'Pending').toLowerCase();
      return ['paid', 'pending', 'voided'].includes(normalized) ? normalized : 'pending';
    };
    const receiptItems = summary => String(summary || '').split(',').map(line => line.trim()).filter(Boolean);
    const inlineJson = value => JSON.stringify(value)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    list.className = 'cashier-receipt-list';
    list.innerHTML = orders.map(o => {
      const status = receiptStatus(o.status);
      const items = receiptItems(o.items_summary);
      const itemMarkup = items.length
        ? items.map(line => {
            const match = line.match(/^(.*)\s+x(\d+)$/i);
            const name = match ? match[1] : line;
            const qty = match ? `×${match[2]}` : '';
            return `<span class="cashier-receipt-item"><span>${escapeHtml(name)}</span>${qty ? `<strong>${qty}</strong>` : ''}</span>`;
          }).join('')
        : '<span class="cashier-receipt-no-items">No item details available.</span>';
      return `
        <article class="cashier-receipt-card ${status === 'voided' ? 'is-voided' : status === 'pending' ? 'is-pending' : ''}">
          <header class="cashier-receipt-header">
            <div>
              <div class="cashier-receipt-code">${escapeHtml(o.order_code || 'Order')}</div>
              <div class="cashier-receipt-meta">
                <span>${time(o.created_at) || 'Time unavailable'}</span>
                <span>${typeIcon(o.order_type)} ${escapeHtml(o.order_type || 'Order type')}</span>
              </div>
            </div>
            <span class="cashier-receipt-status ${status}">${escapeHtml(o.status || 'Pending')}</span>
          </header>
          <section class="cashier-receipt-items">
            <div class="cashier-receipt-items-label">Order items</div>
            <div class="cashier-receipt-item-list">${itemMarkup}</div>
          </section>
          <footer class="cashier-receipt-footer">
            <div>
              <span class="cashier-receipt-total-label">${status === 'paid' ? 'Amount paid' : 'Order total'}</span>
              <strong class="cashier-receipt-total">${money(o.total)}</strong>
            </div>
            <div class="cashier-receipt-actions">
              ${o.status === 'Paid'
                ? `<button class="btn-outline cashier-void-btn" onclick="voidOrder(${Number(o.id)}, ${inlineJson(String(o.order_code || 'Order'))})">Void</button>`
                : ''}
            </div>
          </footer>
        </article>
      `;
    }).join('');
  } catch (err) {
    console.error('loadCashierReceipts failed:', err);
    list.innerHTML = '<div class="cashier-empty">Unable to load receipt history.</div>';
  }
}

function voidOrder(orderId, orderCode) {
  showModal(
    'Void this order?',
    `${orderCode} will be marked as voided and its stock will be restored. This cannot be undone.`,
    async () => {
      try {
        const data = await apiFetch(`${API.orders}?id=${orderId}`, {
          method:  'PUT',
          headers: { 'Content-Type': 'application/json' },
          body:    JSON.stringify({ status: 'Voided' }),
        });
        if (data.success) {
          Swal.fire({ icon: 'success', title: 'Order voided', text: `${orderCode} has been voided.`, confirmButtonText: 'OK', confirmButtonColor: '#2C1A0E' });
          loadCashierReceipts();
          loadCashierDailySummary();
        } else {
          Swal.fire({ icon: 'error', title: 'Could not void order', text: data.message || 'Please try again.', confirmButtonText: 'OK', confirmButtonColor: '#2C1A0E' });
        }
      } catch (err) {
        console.error('voidOrder failed:', err);
        Swal.fire({ icon: 'error', title: 'Could not void order', text: 'A network error occurred.', confirmButtonText: 'OK', confirmButtonColor: '#2C1A0E' });
      }
    }
  );
}

// ---- Items sold today ----
async function loadCashierDailySummary() {
  const list = document.getElementById('cashier-items-sold-list');
  if (!list) return;
  try {
    const data = await apiFetch(`${API.orders}?action=daily_summary`);
    if (!data.success || !data.data) {
      list.innerHTML = '<div class="cashier-empty">Unable to load today\'s sales summary.</div>';
      return;
    }

    const summary = data.data;
    const money = value => `₱${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
    document.getElementById('cashier-closing-date').textContent = summary.date;
    document.getElementById('cashier-closing-revenue').textContent = money(summary.revenue);
    document.getElementById('cashier-closing-cost').textContent = money(summary.cost);
    document.getElementById('cashier-closing-orders').textContent = `${summary.order_count} order${summary.order_count === 1 ? '' : 's'}`;

    const profit = Number(summary.profit || 0);
    const profitEl = document.getElementById('cashier-closing-profit');
    const profitLabel = document.getElementById('cashier-closing-profit-label');
    profitEl.textContent = money(Math.abs(profit));
    profitEl.style.color = profit < 0 ? '#a23232' : '#256b4d';
    profitLabel.textContent = profit < 0 ? 'Loss today' : profit > 0 ? 'Gain today' : 'Break-even';

    const items = Array.isArray(summary.items) ? summary.items : [];
    if (!items.length) {
      list.innerHTML = '<div class="cashier-empty">No paid items today.</div>';
      return;
    }
    list.innerHTML = items.map(item => `
      <div class="cashier-sold-row">
        <span>${item.name}</span>
        <strong>${Number(item.quantity)} sold</strong>
        <span>${Number(item.stock)} in stock</span>
        <span>${money(item.revenue)}</span>
      </div>
    `).join('');
  } catch (err) {
    console.error('loadCashierDailySummary failed:', err);
    list.innerHTML = '<div class="cashier-empty">Unable to load today\'s sales summary.</div>';
  }
}

// ===== MEMBERS PAGE =====
async function renderMembersPage() {
  const tbody = document.getElementById('members-tbody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:1.5rem">Loading…</td></tr>';

  const data = await apiFetch(API.members);
  if (data.error) {
    tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:#C0392B;padding:1.5rem">⚠️ Failed to load members: ${data.error}</td></tr>`;
    return;
  }
  if (!data.success || !data.data || data.data.length === 0) {
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:1.5rem">No members yet. Members appear here when users sign up on the login screen.</td></tr>';
    return;
  }

  tbody.innerHTML = data.data.map(m => {
    const joined = m.created_at ? m.created_at.split(' ')[0] : '-';
    return `
      <tr>
        <td>${escapeHtml(m.full_name)}</td>
        <td>${escapeHtml(m.email)}</td>
        <td><span class="role-pill role-${(m.role||'member').toLowerCase()}">${m.role || 'Member'}</span></td>
        <td style="font-size:0.85rem;color:var(--text-muted)">${joined}</td>
        <td>
          <button class="btn-danger btn-sm" onclick="deleteMember(${m.id}, '${escapeHtml(m.full_name)}')">Remove</button>
        </td>
      </tr>
    `;
  }).join('');
}

function escapeHtml(str) {
  return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

async function deleteMember(id, name) {
  if (!await requireAdminPin(`remove member "${name}"`)) return;
  const result = await Swal.fire({
    title: `Archive ${name}?`,
    text: 'This will archive the member account. They will no longer be able to log in.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, archive',
    confirmButtonColor: '#C0392B',
    cancelButtonColor: '#7A5C3A',
  });
  if (!result.isConfirmed) return;

  const data = await apiFetch(`${API.members}?id=${id}`, { method: 'DELETE' });
  if (data.error) { showToast('Error: ' + data.error); return; }
  showToast(`${name} removed.`);
  renderMembersPage();
}

// ===== INIT =====
document.addEventListener('DOMContentLoaded', () => {
  // Inject card qty control styles
  const style = document.createElement('style');
  style.textContent = `
    /* PIN input in Swal */
    .pin-input-swal {
      font-size: 1.5rem !important;
      letter-spacing: 0.5rem !important;
      text-align: center !important;
      font-weight: 700 !important;
      width: 140px !important;
      margin: 0 auto !important;
    }

    /* Card in-cart highlight */
    .item-card-active {
      border: 2px solid #D4BC8A !important;
      box-shadow: 0 0 0 3px rgba(212,188,138,0.18) !important;
    }

    /* Floating cart-count badge on card */
    .item-card { position: relative; }
    .card-in-cart-badge {
      position: absolute;
      top: 8px; right: 8px;
      background: #2C1A0E;
      color: #EDE0C4;
      font-size: 0.7rem;
      font-weight: 800;
      width: 20px; height: 20px;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      z-index: 2;
      box-shadow: 0 1px 4px rgba(0,0,0,0.25);
    }

    /* +/- row at bottom of card */
    .card-qty-controls {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      margin-top: 6px;
    }
    .card-qty-btn {
      width: 28px; height: 28px;
      border-radius: 50%;
      border: 1.5px solid #D4BC8A;
      background: transparent;
      color: #2C1A0E;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      transition: background 0.15s, color 0.15s;
      flex-shrink: 0;
    }
    .card-qty-btn:hover:not(:disabled) {
      background: #2C1A0E;
      color: #EDE0C4;
    }
    .card-qty-btn:disabled {
      opacity: 0.3;
      cursor: not-allowed;
    }
    .card-qty-add {
      background: #2C1A0E;
      color: #EDE0C4;
    }
    .card-qty-add:hover:not(:disabled) {
      background: #4a2e18;
    }
    .card-qty-num {
      font-size: 0.95rem;
      font-weight: 700;
      min-width: 20px;
      text-align: center;
      color: #2C1A0E;
    }
  `;
  document.head.appendChild(style);
});