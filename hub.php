<?php
/**
 * Portal / Hub — Central navigation for Brew & Co. systems
 * Bookmark this page: http://localhost/INVENTORY/hub.php
 *
 * Public landing page: it only links out to Inventory, POS, and HRMS —
 * each of those systems gates itself behind its own login. No need to
 * force a session before someone can even see the navigation.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="favicon.ico?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Brew & Co. — Portal</title>
<style>
  :root {
    --brown-900:#faf6f0; --brown-800:#5c3a29; --brown-700:#7a5040;
    --brown-500:#a9714a; --accent:#a9714a; --accent-dark:#8f5c39;
    --gold:#d8a066; --cream:#faf6f0; --cream-2:#f5ede3;
    --card:#fff; --ink:#3b2313; --muted:#7a6055; --line:#e8ddd0;
    --shadow:0 12px 40px rgba(74,47,34,.15);
  }
  *{box-sizing:border-box;margin:0;padding:0}
  body {
    font-family:'Segoe UI',system-ui,-apple-system,sans-serif;
    background:linear-gradient(160deg,#faf6f0 0%,#f5ede3 40%,#efe4d5 70%,#e8ddd0 100%);
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:40px 20px;
    position:relative;
  }
  body::before {
    content:'';
    position:fixed;
    inset:0;
    opacity:.05;
    background-image:
      radial-gradient(ellipse 3px 6px at 10% 15%,#fff 50%,transparent 51%),
      radial-gradient(ellipse 3px 6px at 25% 40%,#fff 50%,transparent 51%),
      radial-gradient(ellipse 3px 6px at 5% 60%,#fff 50%,transparent 51%),
      radial-gradient(ellipse 3px 6px at 30% 75%,#fff 50%,transparent 51%),
      radial-gradient(ellipse 3px 6px at 15% 90%,#fff 50%,transparent 51%),
      radial-gradient(ellipse 3px 6px at 35% 20%,#fff 50%,transparent 51%);
    background-size:60px 60px;
    pointer-events:none;
    z-index:0;
  }
  .portal {
    position:relative;
    z-index:1;
    width:100%;
    max-width:1000px;
  }

  /* Header */
  .portal-header {
    text-align:center;
    color:#3b2313;
    margin-bottom:48px;
  }
  .portal-logo {
    width:88px;
    height:88px;
    margin:0 auto 18px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:rgba(255,255,255,.1);
    border-radius:50%;
    border:2px solid rgba(255,255,255,.2);
    backdrop-filter:blur(4px);
  }
  .portal-logo img {
    width:54px;
    height:54px;
  }
  .portal-title {
    font-size:36px;
    font-weight:800;
    letter-spacing:0.01em;
    margin-bottom:6px;
    color:#3b2313;
  }
  .portal-tagline {
    font-size:13px;
    font-weight:700;
    letter-spacing:0.3em;
    text-transform:uppercase;
    color:var(--gold);
  }
  .portal-subtitle {
    font-size:16px;
    color:rgba(255,255,255,.55);
    margin-top:14px;
    font-style:italic;
  }

  /* Cards Grid */
  .cards {
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:28px;
    margin-bottom:36px;
  }

  .portal-card {
    background:#fff !important;
    border-radius:20px;
    padding:36px 26px 30px;
    text-decoration:none;
    color:inherit;
    transition:transform .22s ease, box-shadow .22s ease;
    box-shadow:var(--shadow);
    display:flex;
    flex-direction:column;
    align-items:center;
    text-align:center;
    position:relative;
    overflow:hidden;
  }
  .portal-card {
    border: 1px solid #e8ddd0;
    box-shadow: 0 4px 20px rgba(59,35,19,.06);
  }
  .portal-card::after {
    content:'';
    position:absolute;
    top:0;left:0;right:0;
    height:5px;
  }
  .portal-card.inventory::after { background:linear-gradient(90deg,#a9714a,#d8a066); }
  .portal-card.pos::after { background:linear-gradient(90deg,#5c3a29,#a9714a); }
  .portal-card.login::after { background:linear-gradient(90deg,#256b4d,#2ecc71); }

  .portal-card:hover {
    transform:translateY(-6px);
    box-shadow:0 20px 50px rgba(0,0,0,.3);
  }

  .card-icon {
    width:80px;
    height:80px;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    margin-bottom:22px;
    flex-shrink:0;
  }
  .card-icon svg {
    width:40px;
    height:40px;
  }
  .portal-card.inventory .card-icon { background:#f6ecdd; color:#a9714a; }
  .portal-card.pos .card-icon { background:#efe3d3; color:#5c3a29; }
  .portal-card.login .card-icon { background:#e2f0e8; color:#256b4d; }
  .portal-card.hrms .card-icon { background:#f0e8f6; color:#6b3fa0; }
  .portal-card.hrms::after { background:linear-gradient(90deg,#6b3fa0,#a9714a); }

  .card-title {
    font-size:22px;
    font-weight:800;
    color:#33261d;
    margin-bottom:6px;
  }
  .card-role {
    font-size:11px;
    font-weight:700;
    letter-spacing:0.12em;
    text-transform:uppercase;
    color:#6f6055;
    margin-bottom:14px;
  }
  .card-desc {
    font-size:14px;
    color:#6f6055;
    line-height:1.65;
    margin-bottom:24px;
    flex:1;
  }
  .card-arrow {
    display:inline-flex;
    align-items:center;
    gap:6px;
    font-size:14px;
    font-weight:700;
    color:var(--accent);
    transition:gap .2s ease;
  }
  .portal-card:hover .card-arrow { gap:12px; }

  /* Footer */
  .portal-footer {
    text-align:center;
    color:rgba(255,255,255,.35);
    font-size:13px;
  }
  .portal-footer a {
    color:var(--gold);
    text-decoration:none;
  }
  .portal-footer a:hover { text-decoration:underline; }

  @media(max-width:780px) {
    .cards { grid-template-columns:1fr; max-width:360px; margin-left:auto; margin-right:auto; }
    .portal-title { font-size:26px; }
    .portal-card { padding:28px 20px; }
  }
  @media(min-width:781px) and (max-width:1100px) {
    .cards { grid-template-columns:repeat(2,1fr); }
  }
  @media(min-width:1101px) {
    .cards { grid-template-columns:repeat(3,1fr); max-width:1100px; }
  }
</style>

<link rel="stylesheet" href="responsive-ui.css">
<link rel="stylesheet" href="dashboard-navigation.css">
</head>
<body>

<div class="portal">

  <!-- Header -->
  <div class="portal-header">
    <div class="portal-logo">
      <img src="brewco-logo.svg" alt="Brew & Co.">
    </div>
    <div class="portal-title">Brew &amp; Co.</div>
    <div class="portal-tagline">Coffee, Done Right</div>
    <div class="portal-subtitle">&ldquo;Every cup tells a story.&rdquo;</div>
  </div>

  <!-- System Cards -->
  <div class="cards">

    <!-- Inventory System -->
    <a href="inventory.php" class="portal-card inventory">
      <div class="card-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
      </div>
      <div class="card-title">Inventory System</div>
      <div class="card-role">Stock &amp; Procurement</div>
      <div class="card-desc">
        Track stock levels, manage procurement, shipments, quality checks,
        and monitor warehouse operations. For managers and clerks.
      </div>
      <span class="card-arrow">Open Inventory <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
    </a>

    <!-- POS System -->
    <a href="pos/pos.php" class="portal-card pos">
      <div class="card-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 14h4"/><path d="M14 14h4"/><path d="M14 18v4"/><path d="M10 18v4"/></svg>
      </div>
      <div class="card-title">POS System</div>
      <div class="card-role">Sales &amp; Ordering</div>
      <div class="card-desc">
        Take customer orders, process payments, manage the menu,
        and view sales reports. For cashiers and admins.
      </div>
      <span class="card-arrow">Open POS <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
    </a>

    <!-- HRMS System -->
    <a href="hrms/login.php" class="portal-card hrms">
      <div class="card-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <div class="card-title">HRMS &amp; Payroll</div>
      <div class="card-role">HR &amp; Payroll</div>
      <div class="card-desc">
        Manage employees, payroll, schedules, attendance, leave requests,
        applicants, and audit logs. For HR, admin, and superadmin.
      </div>
      <span class="card-arrow">Open HRMS <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
    </a>

  </div>

  <!-- Footer -->
  <div class="portal-footer">
    Brew &amp; Co. HRMS &copy; 2026
  </div>

</div>


<script src="responsive-ui.js" defer></script>
<script src="dashboard-navigation.js" defer></script>
</body>
</html>