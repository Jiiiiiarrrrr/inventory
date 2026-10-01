<?php
// ===== POS LANDING / APP SHELL =====
// Restored shell for the JS POS app (js/app.js + api/*). Handles: session
// logout, the sign-in / sign-up landing card, the navbar + page containers the
// app renders into, and the checkout / payment numpad modals app.js expects
// (window.openCheckoutModal / window.openPaymentModal / logoutPos).
session_start();
if (isset($_GET['logout'])) {
    unset($_SESSION['user_id'], $_SESSION['role']);
    header('Location: index.php'); exit;
}
$posLoggedIn = !empty($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="../favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="../favicon.ico?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Brew &amp; Co. POS</title>
<link rel="stylesheet" href="css/style.css">
<style>
  .order-sidebar{display:none}
  #app:has(#page-home.active) .order-sidebar,
  #app:has(#page-category.active) .order-sidebar{display:flex}
  .login-form label{display:block;font-size:.85rem;font-weight:600;color:var(--text-dark);margin-bottom:.25rem}
  .login-form input{width:100%;padding:.7rem .9rem;border:2px solid var(--warm-beige);border-radius:10px;font:inherit;background:var(--white);color:var(--text-dark)}
  .pass-wrap{position:relative}
  .pass-wrap button{position:absolute;right:8px;top:50%;transform:translateY(-50%);border:none;background:none;cursor:pointer;font-size:1rem}
  .np-wrap{display:flex;flex-direction:column;gap:10px;align-items:center;margin-top:6px}
  .np-display{font-size:1.6rem;font-weight:800;color:var(--dark-brown);background:var(--cream-bg);border:2px solid var(--warm-beige);border-radius:10px;padding:6px 18px;min-width:220px;text-align:right}
  .np-grid{display:grid;grid-template-columns:repeat(3,72px);gap:8px}
  .np-grid button{padding:12px 0;font-size:1.15rem;font-weight:700;border:1.5px solid var(--warm-beige);border-radius:10px;background:var(--white);color:var(--dark-brown);cursor:pointer}
  .np-grid button:hover{background:var(--cream-bg)}
  .np-types{display:flex;gap:8px;justify-content:center;margin-bottom:4px}
  .np-types button{padding:8px 16px;border-radius:10px;border:1.5px solid var(--warm-beige);background:var(--white);font-weight:700;cursor:pointer;color:var(--dark-brown)}
  .np-types button.sel{background:var(--dark-brown);color:var(--cream-bg);border-color:var(--dark-brown)}

  /* ===== Landing: same split brand layout as the other Brew & Co. systems ===== */
  .ls-wrap{display:flex;width:100%;height:100%}
  .ls-left{flex:0 0 55%;background:linear-gradient(160deg, rgba(58,36,27,.85) 0%, rgba(92,58,41,.7) 40%, rgba(122,79,56,.6) 70%, rgba(169,113,74,.75) 100%), url('../coffee-bg.jpg') center/cover no-repeat;display:flex;align-items:center;justify-content:center;padding:40px;position:relative;overflow:hidden}
  .ls-left::before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 20% 30%, rgba(216,160,102,.2) 0%, transparent 50%),radial-gradient(circle at 80% 70%, rgba(169,113,74,.15) 0%, transparent 50%)}
  .ls-left::after{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at center, transparent 40%, rgba(0,0,0,.3) 100%);pointer-events:none}
  .ls-content{position:relative;z-index:2;text-align:center;color:#fff}
  .ls-logo{width:120px;height:120px;margin:0 auto 24px}.ls-logo img{width:100%;height:100%}
  .ls-brand{font-size:36px;font-weight:800;letter-spacing:.02em;margin-bottom:8px;color:#fff}
  .ls-tagline{font-size:14px;font-weight:700;letter-spacing:.25em;text-transform:uppercase;color:var(--gold,#d8a066);margin-bottom:32px}
  .ls-divider{width:60px;height:2px;background:var(--gold,#d8a066);margin:0 auto 24px;opacity:.6}
  .ls-quote{font-size:16px;font-style:italic;color:rgba(255,255,255,.7);line-height:1.6;max-width:300px;margin:0 auto}
  .ls-quote span{color:var(--gold,#d8a066);font-weight:700;font-style:normal}
  .ls-right{flex:1;display:flex;align-items:center;justify-content:center;padding:60px 24px;background:var(--cream-bg);overflow:auto}
  .ls-card{max-width:440px}
  .ls-card-logo{width:64px;height:64px;margin:0 auto .5rem}.ls-card-logo img{width:100%;height:100%}
  .ls-choice{display:flex;align-items:center;gap:14px;width:100%;text-align:left;background:var(--white);border:2px solid var(--warm-beige);border-radius:14px;padding:14px 16px;margin-bottom:12px;cursor:pointer;font:inherit;color:var(--text-dark);text-decoration:none;transition:border-color .15s,transform .15s}
  .ls-choice:hover{border-color:var(--dark-brown);transform:translateY(-1px)}
  .ls-choice-ico{font-size:1.6rem}
  .ls-choice-txt{flex:1;display:flex;flex-direction:column;gap:2px}
  .ls-choice-txt b{font-size:1.02rem}
  .ls-choice-txt small{color:var(--text-muted);font-size:.8rem}
  .ls-choice-arrow{font-weight:800;color:var(--text-muted)}
  .ls-kiosk{text-align:center;margin-top:6px;font-size:.82rem;color:var(--text-muted)}
  .ls-kiosk a{color:var(--dark-brown);font-weight:600}
  @media(max-width:860px){
    .ls-wrap{flex-direction:column}
    .ls-left{flex:0 0 auto;min-height:230px;padding:24px}
    .ls-logo{width:56px;height:56px;margin-bottom:10px}
    .ls-brand{font-size:22px}.ls-tagline{font-size:11px;margin-bottom:0}
    .ls-divider,.ls-quote{display:none}
    .ls-right{padding:24px}
    .ls-card{max-width:100%}
  }
</style>

<link rel="stylesheet" href="../responsive-ui.css">
<link rel="stylesheet" href="../dashboard-navigation.css">
<link rel="stylesheet" href="../pos-responsive.css">
</head>
<body>

<!-- ============ SIGN-IN / SIGN-UP LANDING ============ -->
<div class="login-screen<?= $posLoggedIn ? '' : ' active' ?>" id="login-screen">
  <div class="ls-wrap">
    <!-- LEFT: brand panel (same look as login.php / hrms-login.php) -->
    <div class="ls-left">
      <div class="ls-content">
        <div class="ls-logo"><img src="brewco-logo.svg" alt="Brew &amp; Co."></div>
        <div class="ls-brand">Brew &amp; Co.</div>
        <div class="ls-tagline">Coffee, Done Right</div>
        <div class="ls-divider"></div>
        <div class="ls-quote"><span>"</span>Every cup tells a story. Brewed fresh, ordered your way.<span>"</span></div>
      </div>
    </div>
    <!-- RIGHT: customer / staff chooser + staff sign-in -->
    <div class="ls-right">
      <div class="login-card ls-card">

        <div id="chooser-view">
          <div class="ls-card-logo"><img src="brewco-logo.svg" alt=""></div>
          <h1 class="login-title">Welcome to Brew &amp; Co.</h1>
          <p class="login-sub" style="margin-bottom:1.25rem">Choose how you want to continue</p>
          <a class="ls-choice" href="kiosk.php">
            <span class="ls-choice-ico">🛒</span>
            <span class="ls-choice-txt"><b>Customer</b><small>Browse the menu &amp; place your order</small></span>
            <span class="ls-choice-arrow">→</span>
          </a>
          <button type="button" class="ls-choice" onclick="showStaffView()">
            <span class="ls-choice-ico">💼</span>
            <span class="ls-choice-txt"><b>Staff</b><small>Cashier / Admin — sign in to the POS</small></span>
            <span class="ls-choice-arrow">→</span>
          </button>
          <div class="ls-kiosk">Self-order station? <a href="kiosk.php">Open the Kiosk →</a></div>
        </div>

        <div id="staff-view" style="display:none">
          <div class="ls-card-logo"><img src="brewco-logo.svg" alt=""></div>
          <h1 class="login-title" id="login-card-title">Staff Sign-in</h1>
          <p class="login-sub" id="login-card-sub">Sign in to your POS account</p>

          <form class="login-form" id="signin-form" onsubmit="event.preventDefault();landingSignIn();">
            <div>
              <label for="login-email">Email</label>
              <input id="login-email" type="email" autocomplete="username" placeholder="you@brewco.ph" required>
            </div>
            <div>
              <label for="login-password">Password</label>
              <div class="pass-wrap">
                <input id="login-password" type="password" autocomplete="current-password" placeholder="••••••••" required>
                <button type="button" id="toggle-pass-btn" title="Show/hide password" onclick="toggleLoginPassword()">👁</button>
              </div>
            </div>
            <div class="login-error" id="login-error"></div>
            <button type="submit" class="btn-primary login-btn">Sign in</button>
            <div class="login-switch">No account? <a href="#" onclick="event.preventDefault();showSignup()">Create one</a></div>
          </form>

          <form class="login-form" id="signup-form" style="display:none" onsubmit="event.preventDefault();attemptSignup();">
            <div>
              <label for="signup-name">Full name</label>
              <input id="signup-name" type="text" placeholder="Juan Dela Cruz" required>
            </div>
            <div>
              <label for="signup-email">Email</label>
              <input id="signup-email" type="email" autocomplete="username" placeholder="you@brewco.ph" required>
            </div>
            <div>
              <label for="signup-password">Password (min 6 chars)</label>
              <div class="pass-wrap">
                <input id="signup-password" type="password" autocomplete="new-password" placeholder="••••••••" required>
                <button type="button" title="Show/hide password" onclick="toggleSignupPassword()">👁</button>
              </div>
            </div>
            <div class="login-error" id="signup-error"></div>
            <button type="submit" class="btn-primary login-btn">Create account</button>
            <div class="login-switch">Have an account? <a href="#" onclick="event.preventDefault();showSignin()">Sign in</a></div>
          </form>

          <p class="login-hint">Staff accounts (Cashier / Admin) are managed by your Admin.<br>Sign-ups start as Member accounts.</p>
          <div class="login-switch" style="margin-top:.5rem"><a href="#" onclick="event.preventDefault();showChooser()">← Back: choose Customer or Staff</a></div>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- ============ APP SHELL ============ -->
<div id="app" class="<?= $posLoggedIn ? 'app-visible' : 'app-hidden' ?>">
  <nav class="navbar">
    <a class="logo" href="#" onclick="event.preventDefault();showPage('page-home')"><img src="brewco-logo.svg" alt="" style="height:26px;width:26px;vertical-align:-6px;margin-right:6px"> Brew &amp; Co. POS</a>
    <ul class="nav-links">
      <li><a href="#" onclick="event.preventDefault();showPage('page-home')">Home</a></li>
      <li><a href="#" onclick="event.preventDefault();showPage('page-category')">Menu</a></li>
      <li><a href="#" onclick="event.preventDefault();showPage('page-items')">Items</a></li>
      <li><a href="#" onclick="event.preventDefault();showPage('page-pending')">Pending <span id="side-pending-count" class="nav-badge" style="background:var(--dark-brown);color:var(--cream-bg);border-radius:10px;padding:1px 8px;font-size:.75rem;font-weight:800"></span></a></li>
      <li id="nav-cashier-item"><a href="#" onclick="event.preventDefault();showPage('page-cashier')">Cashier Station</a></li>
      <li><a href="#" onclick="event.preventDefault();showPage('page-sales')">Sales</a></li>
      <li id="nav-members-item"><a href="#" onclick="event.preventDefault();showPage('page-members');renderMembersPage();">Members</a></li>
      <li id="nav-admin-item"><a href="#" onclick="event.preventDefault();showPage('page-admin')">Admin</a></li>
      <li><a href="#" onclick="event.preventDefault();showPage('page-account')">Account</a></li>
    </ul>
    <div class="navbar-right">
      <span class="nav-user-badge" id="nav-user-badge"></span>
      <button class="btn-cancel" onclick="logout()">Logout</button>
    </div>
  </nav>

  <div style="display:flex;align-items:flex-start">
    <main style="flex:1;min-width:0">

      <!-- HOME -->
      <section class="page" id="page-home">
        <div class="hero">
          <div class="eyebrow">Brew &amp; Co. Coffee Shop</div>
          <h1>What are you craving today?</h1>
          <p>Freshly brewed, made to order.</p>
          <div class="hero-search">
            <input id="hero-search-input" placeholder="Search drinks &amp; pastries…" oninput="refreshItemsFilter()">
            <button type="button" onclick="refreshItemsFilter()">Search</button>
          </div>
        </div>
        <div style="padding:1.5rem"><div class="featured-grid" id="featured-grid"></div></div>
      </section>

      <!-- MENU / CATEGORY -->
      <section class="page" id="page-category">
        <div class="category-main">
          <div class="category-tabs" id="category-tabs"></div>
          <div class="items-grid" id="category-items-grid" style="margin-top:1rem"></div>
        </div>
      </section>

      <!-- ITEMS -->
      <section class="page" id="page-items">
        <div class="category-main">
          <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1rem">
            <select id="items-category-filter" onchange="refreshItemsFilter()" style="padding:.6rem .8rem;border:2px solid var(--warm-beige);border-radius:10px;font:inherit;background:var(--white)"></select>
            <input id="items-search-input" placeholder="Search items…" oninput="refreshItemsFilter()" style="flex:1;min-width:200px;padding:.6rem .9rem;border:2px solid var(--warm-beige);border-radius:10px;font:inherit;background:var(--white)">
          </div>
          <div class="items-grid" id="items-full-grid"></div>
        </div>
      </section>

      <!-- SALES -->
      <section class="page" id="page-sales">
        <div class="category-main">
          <div class="sales-header"><h2>End-of-day Sales</h2></div>
          <div class="summary-cards" style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin:1rem 0">
            <div class="chart-card"><div class="chart-card-header"><strong>Today</strong></div><div style="padding:0 1rem 1rem"><div style="font-size:1.6rem;font-weight:800" id="summary-today">₱0</div><div style="color:var(--text-muted);font-size:.85rem"><span id="summary-today-orders">0</span> orders</div></div></div>
            <div class="chart-card"><div class="chart-card-header"><strong>This month</strong></div><div style="padding:0 1rem 1rem"><div style="font-size:1.6rem;font-weight:800" id="summary-month">₱0</div><div style="color:var(--text-muted);font-size:.85rem"><span id="summary-month-orders">0</span> orders</div></div></div>
            <div class="chart-card"><div class="chart-card-header"><strong>All time</strong></div><div style="padding:0 1rem 1rem"><div style="font-size:1.6rem;font-weight:800" id="summary-all">₱0</div><div style="color:var(--text-muted);font-size:.85rem"><span id="summary-all-orders">0</span> orders</div></div></div>
          </div>
          <div class="chart-card" style="margin-bottom:1rem">
            <div class="chart-card-header"><h3 id="chart-title">Sales</h3></div>
            <div id="sales-chart-wrap" style="padding:0 1rem 1rem"></div>
          </div>
          <div class="staff-table-wrap">
            <div style="padding:1rem"><input id="sales-search" placeholder="Search receipts…" oninput="filterSalesTable(this.value)" style="width:100%;padding:.6rem .9rem;border:2px solid var(--warm-beige);border-radius:10px;font:inherit;background:var(--white)"></div>
            <table style="width:100%;border-collapse:collapse"><tbody id="sales-tbody"></tbody></table>
          </div>
        </div>
      </section>

      <!-- PENDING (kiosk orders awaiting payment) -->
      <section class="page" id="page-pending">
        <div class="category-main">
          <div class="sales-header"><h2>Pending Orders</h2></div>
          <p style="color:var(--text-muted);margin:.5rem 0 1rem">Orders placed at the kiosk, waiting for payment collection.</p>
          <div id="cashier-pending-list"></div>
        </div>
      </section>

      <!-- CASHIER STATION -->
      <section class="page" id="page-cashier">
        <div class="category-main">
          <div class="sales-header"><h2>Cashier Station</h2></div>
          <div style="display:flex;gap:.5rem;margin:1rem 0">
            <button class="btn-primary" id="cashier-view-tab-sold" onclick="selectCashierView('sold')">Today's sold</button>
            <button class="btn-cancel" id="cashier-view-tab-receipts" onclick="selectCashierView('receipts')">Receipts</button>
          </div>
          <div id="cashier-panel-sold">
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1rem">
              <div class="chart-card"><div style="padding:1rem"><div style="color:var(--text-muted);font-size:.85rem">Revenue (<span id="cashier-closing-date">—</span>)</div><div style="font-size:1.5rem;font-weight:800" id="cashier-closing-revenue">₱0</div></div></div>
              <div class="chart-card"><div style="padding:1rem"><div style="color:var(--text-muted);font-size:.85rem">Cost / Orders</div><div style="font-size:1.5rem;font-weight:800" id="cashier-closing-cost">₱0</div><div style="color:var(--text-muted);font-size:.85rem"><span id="cashier-closing-orders">0</span> orders</div></div></div>
              <div class="chart-card"><div style="padding:1rem"><div style="color:var(--text-muted);font-size:.85rem" id="cashier-closing-profit-label">Profit</div><div style="font-size:1.5rem;font-weight:800" id="cashier-closing-profit">₱0</div></div></div>
            </div>
            <div class="staff-table-wrap"><div id="cashier-items-sold-list" style="padding:1rem"></div></div>
          </div>
          <div id="cashier-panel-receipts" style="display:none"><div class="staff-table-wrap"><div id="cashier-receipts-list" style="padding:1rem"></div></div></div>
        </div>
      </section>

      <!-- MEMBERS -->
      <section class="page" id="page-members">
        <div class="category-main">
          <div class="sales-header"><h2>Members</h2></div>
          <div class="staff-table-wrap" style="margin-top:1rem">
            <table style="width:100%;border-collapse:collapse">
              <thead><tr><th style="text-align:left;padding:.8rem 1rem">Name</th><th style="text-align:left">Email</th><th style="text-align:left">Role</th><th style="text-align:left">Joined</th><th></th></tr></thead>
              <tbody id="members-tbody"></tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- ADMIN -->
      <section class="page" id="page-admin">
        <div class="category-main">
          <div class="sales-header"><h2>Admin</h2></div>
          <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin:1rem 0">
            <button class="admin-tab-btn btn-cancel" onclick="switchAdminTab('stock')">Stock</button>
            <button class="admin-tab-btn btn-cancel" onclick="switchAdminTab('menu')">Menu editor</button>
            <button class="admin-tab-btn btn-cancel" onclick="switchAdminTab('sales')">Sales</button>
            <button class="admin-tab-btn btn-cancel" onclick="switchAdminTab('archive')">Archive</button>
            <button class="admin-tab-btn btn-cancel" onclick="switchAdminTab('staff')">Staff</button>
          </div>

          <div class="admin-tab-panel" id="admin-stock">
            <div id="stock-confirm-bar"></div>
            <div class="stock-grid" id="stock-grid"></div>
          </div>

          <div class="admin-tab-panel" id="admin-menu">
            <div class="menu-editor-grid" id="menu-editor-grid"></div>
          </div>

          <div class="admin-tab-panel" id="admin-sales">
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1rem">
              <div class="chart-card"><div style="padding:1rem"><div style="font-size:1.5rem;font-weight:800" id="admin-summary-today">₱0</div><div style="color:var(--text-muted);font-size:.85rem"><span id="admin-summary-today-orders">0</span> orders today</div></div></div>
              <div class="chart-card"><div style="padding:1rem"><div style="font-size:1.5rem;font-weight:800" id="admin-summary-month">₱0</div><div style="color:var(--text-muted);font-size:.85rem"><span id="admin-summary-month-orders">0</span> orders this month</div></div></div>
              <div class="chart-card"><div style="padding:1rem"><div style="font-size:1.5rem;font-weight:800" id="admin-summary-all">₱0</div><div style="color:var(--text-muted);font-size:.85rem"><span id="admin-summary-all-orders">0</span> orders all time</div></div></div>
            </div>
            <div class="chart-card" style="margin-bottom:1rem">
              <div class="chart-card-header"><h3 id="admin-chart-title">Sales</h3></div>
              <div id="admin-sales-chart-wrap" style="padding:0 1rem 1rem"></div>
            </div>
            <div class="staff-table-wrap">
              <div style="padding:1rem"><input id="admin-sales-search" placeholder="Search receipts…" oninput="filterAdminSalesTable(this.value)" style="width:100%;padding:.6rem .9rem;border:2px solid var(--warm-beige);border-radius:10px;font:inherit;background:var(--white)"></div>
              <table style="width:100%;border-collapse:collapse"><tbody id="admin-sales-tbody"></tbody></table>
            </div>
          </div>

          <div class="admin-tab-panel" id="admin-archive">
            <div class="archive-grid" id="archive-grid"></div>
            <h3 style="margin:1.25rem 0 .5rem">Archived categories</h3>
            <div id="archived-categories-grid"></div>
            <h3 style="margin:1.25rem 0 .5rem">Archived staff</h3>
            <div id="archived-staff-grid"></div>
          </div>

          <div class="admin-tab-panel" id="admin-staff">
            <div class="staff-table-wrap">
              <table style="width:100%;border-collapse:collapse">
                <thead><tr><th style="text-align:left;padding:.8rem 1rem">Name</th><th style="text-align:left">Email</th><th style="text-align:left">Role</th><th></th></tr></thead>
                <tbody id="staff-tbody"></tbody>
              </table>
            </div>
          </div>
        </div>
      </section>

      <!-- ACCOUNT -->
      <section class="page" id="page-account">
        <div class="category-main" style="max-width:640px">
          <div class="chart-card" style="padding:1.5rem;margin-bottom:1rem">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:1rem">
              <div style="width:56px;height:56px;border-radius:50%;background:var(--dark-brown);color:var(--cream-bg);display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:800">👤</div>
              <div><div style="font-size:1.2rem;font-weight:800" id="display-name">—</div><div style="color:var(--text-muted)" id="display-role">—</div></div>
            </div>
            <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.25rem">Full name</label>
            <input id="account-name" style="width:100%;padding:.6rem .9rem;border:2px solid var(--warm-beige);border-radius:10px;font:inherit;margin-bottom:.75rem">
            <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.25rem">Email</label>
            <input id="account-email" style="width:100%;padding:.6rem .9rem;border:2px solid var(--warm-beige);border-radius:10px;font:inherit;margin-bottom:.75rem">
            <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.25rem">Role</label>
            <input id="account-role" readonly style="width:100%;padding:.6rem .9rem;border:2px solid var(--warm-beige);border-radius:10px;font:inherit;margin-bottom:.75rem;background:var(--cream-bg)">
            <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.25rem">New password (leave blank to keep)</label>
            <input id="account-password" type="password" autocomplete="new-password" style="width:100%;padding:.6rem .9rem;border:2px solid var(--warm-beige);border-radius:10px;font:inherit;margin-bottom:1rem">
            <div id="pin-section">
              <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.25rem">Admin PIN (4 digits)</label>
              <input id="account-pin" inputmode="numeric" maxlength="4" placeholder="••••" style="width:140px;padding:.6rem .9rem;border:2px solid var(--warm-beige);border-radius:10px;font:inherit;letter-spacing:.3rem;margin-bottom:1rem">
            </div>
            <button class="btn-primary" onclick="saveAccountChanges()">Save changes</button>
          </div>
        </div>
      </section>

    </main>

    <!-- CURRENT ORDER SIDEBAR -->
    <aside class="order-sidebar">
      <h3>Current order</h3>
      <div class="order-items" id="order-items-list"></div>
      <hr class="order-divider">
      <div class="order-subtotal-row"><span>Subtotal</span><span id="order-subtotal">₱0</span></div>
      <div class="order-vat-row"><span>VAT (12%)</span><span id="order-vat">₱0</span></div>
      <div class="order-subtotal-row" style="font-weight:800;color:var(--dark-brown)"><span>Total</span><span id="order-total">₱0</span></div>
      <div id="order-change-estimate-row" style="display:none;font-size:.82rem;color:var(--text-muted)">
        <label for="order-cash-estimate" style="display:block;margin-bottom:.25rem">Paying with (estimate)</label>
        <input id="order-cash-estimate" type="number" min="0" style="width:100%;padding:.4rem .6rem;border:1.5px solid var(--warm-beige);border-radius:8px;font:inherit" oninput="updateOrderChangeEstimate()">
        <div style="margin-top:.35rem">Change: <b id="order-change-estimate-amount">₱0</b></div>
      </div>
      <button class="btn-primary" onclick="placeOrder()">Place order</button>
      <button class="btn-cancel" onclick="clearOrder()">Clear</button>
    </aside>
  </div>
</div>

<!-- confirm modal + toast (app.js driven) -->
<div class="modal-overlay" id="modal-overlay">
  <div class="modal-box">
    <h3 id="modal-title">Confirm</h3>
    <p id="modal-message"></p>
    <div id="modal-body"></div>
    <div class="modal-actions" id="modal-actions"></div>
  </div>
</div>
<div class="toast" id="toast"></div>

<script src="js/swal-compat.js"></script>
<script src="js/app.js"></script>
<script>
/* ===== Landing helpers (index.php shell contract with app.js) ===== */
function hideLanding(){ document.getElementById('login-screen').classList.remove('active'); }
function clearOrder(){ if (typeof currentOrder === 'undefined') return; currentOrder.length = 0; renderOrderSidebar(); }
function showChooser(){ document.getElementById('chooser-view').style.display=''; document.getElementById('staff-view').style.display='none'; }
function showStaffView(){ document.getElementById('chooser-view').style.display='none'; document.getElementById('staff-view').style.display=''; showSignin(); }
/* Rebrand the titles app.js sets when switching sign-in / sign-up */
(function(){
  const _si = window.showSignin, _su = window.showSignup;
  window.showSignin = function(){ _si(); document.getElementById('login-card-title').textContent='Staff Sign-in'; document.getElementById('login-card-sub').textContent='Sign in to your POS account'; };
  window.showSignup = function(){ _su(); document.getElementById('login-card-title').textContent='Create Account'; document.getElementById('login-card-sub').textContent='Members sign up here — staff accounts are managed by Admin'; };
})();
async function landingSignIn(){
  await attemptLogin();
  if (window.currentUser) { hideLanding(); }
}
function logoutPos(){
  Swal.fire({
    title: 'Logout?', text: 'You will need to sign in again to use the POS.',
    icon: 'question', showCancelButton: true,
    confirmButtonText: 'Logout', confirmButtonColor: '#2C1A0E', cancelButtonColor: '#7A5C3A'
  }).then(r => { if (r.isConfirmed) location.href = 'index.php?logout=1'; });
}

/* ===== Numpad cash modal shared by checkout + pending payment ===== */
function npOpenModal(title, subtitle, collectCash, onDone){
  let entered = '', selectedType = 'Dine In';
  const wrap = document.createElement('div');
  wrap.className = 'np-wrap';
  wrap.innerHTML =
    (collectCash ? '<div class="np-types"><button type="button" class="sel" data-t="Dine In">🍽️ Dine In</button><button type="button" data-t="Take Out">🥡 Take Out</button></div>' : '') +
    '<div class="np-display">₱0</div>' +
    '<div class="np-grid">' +
      ['1','2','3','4','5','6','7','8','9','C','0','⌫'].map(k => '<button type="button" data-k="'+k+'">'+k+'</button>').join('') +
    '</div>' +
    '<div style="font-size:.85rem;color:var(--text-muted)">' + subtitle + '</div>';
  Swal.fire({
    title: title, html: wrap, showCancelButton: true,
    confirmButtonText: 'Confirm payment', confirmButtonColor: '#2C1A0E', cancelButtonColor: '#7A5C3A',
    didOpen: () => {
      const disp = wrap.querySelector('.np-display');
      const render = () => { disp.textContent = '₱' + Number(entered || 0).toLocaleString(); };
      wrap.querySelectorAll('.np-grid button').forEach(b => b.addEventListener('click', () => {
        const k = b.dataset.k;
        if (k === 'C') entered = '';
        else if (k === '⌫') entered = entered.slice(0, -1);
        else if (entered.length < 7) entered = String(parseInt((entered + k), 10));
        render();
      }));
      wrap.querySelectorAll('.np-types button').forEach(b => b.addEventListener('click', () => {
        wrap.querySelectorAll('.np-types button').forEach(x => x.classList.remove('sel'));
        b.classList.add('sel'); selectedType = b.dataset.t;
      }));
    },
    preConfirm: () => {
      const cash = parseInt(entered || '0', 10);
      if (collectCash && (!cash || cash <= 0)) { Swal.showValidationMessage('Enter the cash received using the numpad.'); return false; }
      return collectCash ? { selectedType, cash } : { selectedType, cash: null };
    }
  }).then(r => onDone(r.isConfirmed ? r.value : null));
}
window.openCheckoutModal = function(total, collectCash){
  return new Promise(resolve => {
    npOpenModal('Checkout — ₱' + Number(total).toLocaleString(),
      'Total due ₱' + Number(total).toLocaleString() + (collectCash ? ' — enter cash received.' : ' — choose order type; cash is collected at the cashier.'),
      !!collectCash,
      v => resolve(v === null ? null : { selectedType: v.selectedType, cashResult: v.cash }));
  });
};
window.openPaymentModal = function(total, orderCode){
  return new Promise(resolve => {
    npOpenModal('Collect payment — ' + (orderCode || ''),
      'Total due ₱' + Number(total).toLocaleString() + ' — enter cash received.',
      true,
      v => resolve(v === null ? null : v.cash));
  });
};

/* ===== Boot: restore session + last page ===== */
document.addEventListener('DOMContentLoaded', async () => {
  <?php if ($posLoggedIn): ?>
  try {
    const me = await apiFetch(API.account + '?action=me');
    if (me && me.data) {
      currentUser = me.data;
      document.getElementById('app').className = 'app-visible';
      hideLanding();
      applyRoleUI();
      const last = getLastPage();
      const start = (currentUser.role === 'Cashier') ? 'page-cashier'
        : (last && document.getElementById(last) ? last : 'page-home');
      if (start === 'page-members') renderMembersPage();
      showPage(start);
    } else {
      document.getElementById('app').className = 'app-hidden';
      document.getElementById('login-screen').classList.add('active');
    }
  } catch (e) {
    document.getElementById('login-screen').classList.add('active');
  }
  <?php endif; ?>
});
</script>

<script src="../responsive-ui.js" defer></script>
<script src="../dashboard-navigation.js" defer></script>
<script src="../pos-responsive.js" defer></script>
</body>
</html>
