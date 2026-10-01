<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="../favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="../favicon.ico?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HRMS Finance</title>
<style>
:root{--espresso:#3b2313;--cream:#faf6f0;--cream-soft:#f5ede3;--tan:#e8ddd0;--gold:#a9714a;--gold-dark:#8f5c39;--ink:#3b2313;--ink-soft:#7a6055;--line:#e8ddd0;--bg:#faf6f0;--sidebar-w:250px}
*{box-sizing:border-box;margin:0;padding:0}
body{height:100vh;background:var(--bg);font-family:Georgia,"Iowan Old Style",serif;color:var(--ink);overflow:hidden;display:flex}

/* ===== SIDEBAR ===== */
.sidebar{width:var(--sidebar-w);background:var(--cream);color:var(--ink);display:flex;flex-direction:column;padding:22px 16px;position:fixed;top:0;left:0;height:100vh;z-index:60;overflow-y:auto;overflow-x:hidden;border-right:1px solid var(--line);scrollbar-width:none}
.sidebar:hover{scrollbar-width:thin;scrollbar-color:rgba(139,111,79,.25) transparent}
.sidebar::-webkit-scrollbar{width:5px}
.sidebar::-webkit-scrollbar-thumb{background:transparent;border-radius:3px;transition:background .2s}
.sidebar:hover::-webkit-scrollbar-thumb{background:rgba(139,111,79,.25)}

.sidebar-brand{display:flex;align-items:center;gap:10px;padding:6px 10px 20px;border:none}
.sidebar-logo{width:36px;height:36px;border-radius:50%;background:var(--gold);display:flex;align-items:center;justify-content:center}
.sidebar-logo-dot{width:12px;height:12px;border-radius:50%;background:#fff}
.sidebar-title{font-size:16px;font-weight:900;letter-spacing:.01em;color:var(--ink)}

.sidebar-user{display:flex;align-items:center;gap:10px;padding:12px 10px;margin-bottom:4px;border-bottom:1px solid var(--line)}
.sidebar-avatar{width:34px;height:34px;border-radius:50%;background:var(--gold);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;flex-shrink:0}
.sidebar-user-name{font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sidebar-user-role{font-size:11px;color:var(--ink-soft)}

.sidebar-section{font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--ink-soft);opacity:.6;padding:14px 10px 6px}
.sidebar-divider{height:1px;background:var(--line);margin:6px 10px}

.sidebar-nav{flex:1;min-height:0;overflow-y:auto}
.sidebar-nav a{display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:10px;color:var(--ink);font-weight:500;font-size:14px;margin-bottom:2px;cursor:pointer;transition:all .15s;text-decoration:none;position:relative}
.sidebar-nav a:hover{background:rgba(169,113,74,.08);color:var(--gold)}
.sidebar-nav a.active{background:var(--gold);color:#fff;font-weight:700}
.sidebar-nav a .nav-icon{width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.sidebar-nav a .badge{position:absolute;right:10px;background:#a8492f;color:#fff;border-radius:999px;font-size:10px;font-weight:800;padding:1px 7px;min-width:18px;text-align:center;line-height:16px}
.sidebar-nav a.active .badge{background:#fff;color:#a8492f}

.sidebar-bottom{padding-top:10px;border-top:1px solid var(--line);margin-top:6px}
.sidebar-bottom a{display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:10px;color:var(--ink);font-weight:500;font-size:14px;cursor:pointer;text-decoration:none;transition:all .15s}
.sidebar-bottom a:hover{background:rgba(169,113,74,.08);color:var(--gold)}

/* ===== MOBILE TOGGLE ===== */
.sidebar-toggle{display:none;position:fixed;top:12px;left:12px;z-index:1001;width:44px;height:44px;border-radius:10px;background:var(--bg);color:var(--gold);border:1.5px solid var(--line);cursor:pointer;font-size:20px;align-items:center;justify-content:center;box-shadow:0 2px 10px rgba(59,35,19,.08)}
.sidebar-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.3);z-index:55}
.sidebar-backdrop.show{display:block}

/* ===== MAIN / IFRAME ===== */
.main{margin-left:var(--sidebar-w);flex:1;height:100vh}
iframe{width:100%;height:100%;border:0;background:var(--bg);display:block}

@media(max-width:820px){
  .sidebar{transform:translateX(-100%);transition:transform .3s;box-shadow:4px 0 30px rgba(59,35,19,.1)}
  .sidebar.open{transform:translateX(0)}
  .main{margin-left:0}
  .sidebar-toggle{display:flex}
}
</style>

<link rel="stylesheet" href="../responsive-ui.css">
<link rel="stylesheet" href="../dashboard-navigation.css">
<link rel="stylesheet" href="../hrms-responsive.css">
<link rel="stylesheet" href="../final-operations.css">
</head>
<body>

<!-- MOBILE TOGGLE -->
<button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()">
  <svg viewBox="0 0 24 24" style="width:22px;height:22px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
</button>
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar()"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <img src="../brewco-logo.svg" alt="Brew &amp; Co." style="width:36px;height:36px;border-radius:50%;background:var(--cream);padding:4px">
    <div class="sidebar-title">Brew &amp; Co.</div>
  </div>

  <div class="sidebar-user">
    <div class="sidebar-avatar" id="sidebarAvatar">F</div>
    <div>
      <div class="sidebar-user-name" id="sidebarName">Finance</div>
      <div class="sidebar-user-role">Finance Officer</div>
    </div>
  </div>

  <nav class="sidebar-nav" id="sidebarNav">
    <div class="sidebar-section">Main</div>
    <a href="#" data-page="dashboard" onclick="openPage('dashboard');return false;">
      <span class="nav-icon">📊</span> Dashboard
    </a>

    <div class="sidebar-section">Finance</div>
    <a href="#" data-page="budget" onclick="openPage('budget');return false;">
      <span class="nav-icon">💰</span> Budget
    </a>
    <a href="#" data-page="payroll" onclick="openPage('payroll');return false;">
      <span class="nav-icon">✅</span> Payroll Approvals <span class="badge" id="payBadge" style="display:none"></span>
    </a>

    <div class="sidebar-divider"></div>
    <a href="#" data-page="notifications" onclick="openPage('notifications');return false;">
      <span class="nav-icon">🔔</span> Notifications <span class="badge" id="notifBadge" style="display:none"></span>
    </a>
    <a href="#" data-page="profile" onclick="openPage('profile');return false;">
      <span class="nav-icon">👤</span> Profile
    </a>
  </nav>

  <div class="sidebar-bottom">
    <a href="#" onclick="doLogout();return false;">
      <span class="nav-icon">🚪</span> Logout
    </a>
  </div>
</aside>

<!-- MAIN CONTENT -->
<div class="main">
  <iframe id="frame"></iframe>
</div>

<script src="../alerts.js"></script>
<script>
const pages = {
  dashboard:'modules/finance-dashboard.php',
  budget:'modules/finance-budget.php',
  payroll:'modules/finance-payroll.php',
  notifications:'modules/notifications.php',
  profile:'modules/profile.php'
};

const frame = document.getElementById('frame');

async function requireLogin(){
  try {
    const r = await fetch('api/me.php',{credentials:'same-origin'}), d = await r.json();
    if (!d.success || d.user.role !== 'finance') { location.href='login.php'; return false; }
    const initials = (d.user.name||'F').split(' ').map(n=>n[0]).join('').substring(0,2).toUpperCase();
    document.getElementById('sidebarAvatar').textContent = initials;
    document.getElementById('sidebarName').textContent = d.user.name || 'Finance';
    return true;
  } catch(e) { location.href='login.php'; return false; }
}

function openPage(key){
  if (!pages[key]) key = 'dashboard';
  document.querySelectorAll('.sidebar-nav a[data-page]').forEach(a => a.classList.toggle('active', a.dataset.page === key));
  frame.src = pages[key];
  localStorage.setItem('HRMS_Finance_active_page', key);
  setTimeout(loadBadges, 500);
  closeSidebar();
}

async function loadBadges(){
  try {
    let n = await (await fetch('api/notifications.php',{credentials:'same-origin'})).json();
    let unread = (n.data||[]).filter(x => Number(x.is_read)===0).length;
    const nb = document.getElementById('notifBadge');
    nb.style.display = unread ? '' : 'none';
    nb.textContent = unread;
  } catch(e) {}
  try {
    let p = await (await fetch('api/payroll.php?action=queue',{credentials:'same-origin'})).json();
    let pending = p.count || 0;
    const pb = document.getElementById('payBadge');
    pb.style.display = pending ? '' : 'none';
    pb.textContent = pending;
  } catch(e) {}
}

function toggleSidebar(){
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebarBackdrop').classList.toggle('show');
}
function closeSidebar(){
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarBackdrop').classList.remove('show');
}

function tryLoadScript(src){
  return new Promise((resolve) => {
    const s = document.createElement('script');
    s.src = src;
    s.onload = () => resolve(true);
    s.onerror = () => resolve(false);
    document.head.appendChild(s);
  });
}

let swalLoadAttempted = false;
async function ensureSwal(){
  if (window.Swal) return true;
  if (swalLoadAttempted) return !!window.Swal;
  swalLoadAttempted = true;
  const candidates = ['../swal-compat.js', '../../swal-compat.js', 'swal-compat.js', '/swal-compat.js', '/INVENTORY/swal-compat.js'];
  for (const src of candidates) {
    const ok = await tryLoadScript(src);
    if (ok && window.Swal) return true;
  }
  return false;
}

async function doLogout(){
  const confirmed = window.swalAsk
    ? await swalAsk('Log out of your session?')
    : confirm('Log out of your session?');
  if (!confirmed) return;
  await fetch('api/logout.php',{credentials:'same-origin'});
  location.href = 'login.php';
}

requireLogin().then(ok => {
  if (ok) {
    openPage(localStorage.getItem('HRMS_Finance_active_page') || 'dashboard');
    loadBadges();
    setInterval(loadBadges, 30000);
  }
});
</script>

<script src="../responsive-ui.js" defer></script>
<script src="../dashboard-navigation.js" defer></script>
<script src="../hrms-responsive.js" defer></script>
<script src="../final-operations.js" defer></script>
</body>
</html>
