<?php
require __DIR__ . '/db.php';

function role_home($role){
    switch ($role) {
        case 'clerk':      return 'inventory-clerk.php';
        case 'manager':    return 'inventory-manager.php';
        case 'finance':    return 'finance-dashboard.php';
        case 'superadmin': return 'user-management.php';
        case 'admin':      return 'pos/index.php';
        case 'cashier':
        case 'barista':
        case 'cleaner':    return 'pos/index.php';
        default:           return 'login.php';
    }
}

if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
$u = $_SESSION['user'];
$roleLabel = ['clerk'=>'Inventory Clerk','manager'=>'Inventory Manager','finance'=>'Finance Officer','superadmin'=>'Superadmin'];
$initials = strtoupper(substr($u['firstName'] ?? '?',0,1).substr($u['lastName'] ?? '?',0,1));

$stats = ['items'=>0,'low'=>0,'out'=>0,'value'=>0,'movements'=>0,'pending'=>0];
if (db_ok()) {
    $rows = db_all("SELECT current_qty, reorder_level, cost FROM items WHERE is_active=1");
    foreach ($rows as $r) {
        $stats['items']++;
        if ($r['current_qty'] <= 0) $stats['out']++;
        elseif ($r['current_qty'] <= $r['reorder_level']) $stats['low']++;
        $stats['value'] += $r['current_qty'] * $r['cost'];
    }
    $stats['movements'] = (int)db_one("SELECT COUNT(*) n FROM stock_movements")['n'];
    $stats['pending'] = (int)db_one("SELECT COUNT(*) n FROM purchase_requests WHERE status='pending'")['n'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="favicon.ico?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Brew &amp; Co. — Overview</title>
<style>
  :root{--accent:#a9714a;--accent-dark:#8f5c39;--cream:#efe3d3;--cream-2:#f6ecdd;--card:#fff;--ink:#33261d;--muted:#6f6055;--line:#eaded0;--ok:#256b4d;--warn:#96690a;--danger:#a23232;--radius:16px;--shadow:0 8px 24px rgba(74,47,34,.08)}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--cream);color:var(--ink);min-height:100vh}
  a{text-decoration:none;color:inherit}
  .top{background:linear-gradient(135deg,#3a241b,#5c3a29);color:#fff;padding:26px 34px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
  .top .logo{font-size:30px;font-weight:800;color:#d8a066}
  .top h1{font-size:22px;margin-top:2px}.top p{color:#d3c2b1;font-size:13px;margin-top:2px}
  .who{display:flex;align-items:center;gap:11px}.avatar{width:44px;height:44px;border-radius:50%;background:var(--accent);display:flex;align-items:center;justify-content:center;font-weight:700}
  .main{max-width:1100px;margin:0 auto;padding:26px 20px}
  .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:22px}
  .stat{background:var(--card);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
  .stat .t{font-size:12px;color:var(--muted)}.stat .v{font-size:26px;font-weight:800;margin-top:4px}
  .stat .v.warn{color:var(--warn)}.stat .v.danger{color:var(--danger)}.stat .v.ok{color:var(--ok)}
  .nav{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:22px}
  .nav a{background:var(--card);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow);border-left:4px solid var(--accent);font-weight:700}
  .nav a small{display:block;color:var(--muted);font-weight:500;margin-top:4px}
  .nav a:hover{transform:translateY(-2px);box-shadow:0 12px 30px rgba(74,47,34,.15)}
  .charts{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  .panel{background:var(--card);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow)}
  .panel h3{font-size:15px;margin-bottom:14px}
  .bar-row{display:flex;align-items:center;gap:10px;margin-bottom:10px;font-size:13px}
  .bar-row .lb{width:100px;color:var(--muted);flex-shrink:0}
  .bar-track{flex:1;height:16px;background:var(--cream-2);border-radius:8px;overflow:hidden}
  .bar-fill{height:100%;background:var(--accent);border-radius:8px}
  .bar-val{width:50px;text-align:right;font-weight:700}
  .foot{text-align:center;color:var(--muted);font-size:12px;padding:20px}
  @media(max-width:700px){.charts{grid-template-columns:1fr}}
.icon-btn{position:relative;width:44px;height:44px;border-radius:50%;background:#fff;box-shadow:0 8px 24px rgba(74,47,34,.08);display:flex;align-items:center;justify-content:center;cursor:pointer;border:none;color:#33261d}
</style>

<link rel="stylesheet" href="responsive-ui.css">
<link rel="stylesheet" href="dashboard-navigation.css">
<link rel="stylesheet" href="inventory-ui.css">
</head>
<body>
  <div class="top">
    <div><div class="logo">☕</div><h1>Brew &amp; Co. Inventory</h1><p>Overview &amp; quick access</p></div>
    <?= notification_bell($u["role"]) ?>
    <div class="who"><div class="avatar"><?= $initials ?></div><div><?= htmlspecialchars(($u['firstName'] ?? 'User') . ' ' . ($u['lastName'] ?? '')) ?><br><small style="color:#d3c2b1"><?= $roleLabel[$u['role']] ?? ucfirst($u['role'] ?? 'Guest') ?></small></div></div>
  </div>

  <div class="main">
    <div class="stats">
      <div class="stat"><div class="t">Stock Items</div><div class="v"><?= $stats['items'] ?></div></div>
      <div class="stat"><div class="t">Low Stock</div><div class="v warn"><?= $stats['low'] ?></div></div>
      <div class="stat"><div class="t">Out of Stock</div><div class="v danger"><?= $stats['out'] ?></div></div>
      <div class="stat"><div class="t">Stock Value</div><div class="v ok">₱<?= number_format($stats['value']) ?></div></div>
      <div class="stat"><div class="t">Movements</div><div class="v"><?= $stats['movements'] ?></div></div>
      <div class="stat"><div class="t">Pending PRs</div><div class="v warn"><?= $stats['pending'] ?></div></div>
    </div>

    <div class="nav">
      <?php if ($u['role']==='clerk' || $u['role']==='superadmin'): ?><a href="inventory-clerk.php">Inventory Clerk<small>Record &amp; view stock movements</small></a><?php endif; ?>
      <?php if (in_array($u['role'],['manager','superadmin'])): ?><a href="inventory-manager.php">Inventory Manager<small>Procurement, QC, shipments, stocktake</small></a><?php endif; ?>
      <?php if (in_array($u['role'],['finance','superadmin'])): ?><a href="finance-dashboard.php">Finance<small>Budget, approvals, menu costs</small></a><?php endif; ?>
      <?php if (in_array($u['role'],['finance','superadmin'])): ?><a href="reports.php">Reports<small>Charts, exports, profitability</small></a><?php endif; ?>
      <?php if ($u['role']==='superadmin'): ?><a href="user-management.php">User Management<small>Accounts &amp; access</small></a><a href="audit-trail.php">Audit Trail<small>Activity log</small></a><?php endif; ?>
      <?php if (in_array($u['role'],['cashier','barista','cleaner','admin','superadmin'])): ?><a href="pos/index.php" target="_blank">POS — New System<small>Open POS in new tab</small></a><?php endif; ?>
      <?php if (in_array($u['role'],['manager','superadmin','admin','finance'])): ?><a href="hrms/login.php" target="_blank">HRMS &amp; Payroll<small>HR, payroll, employees</small></a><?php endif; ?>
      <a href="hub.php">All Systems<small>Portal hub</small></a>
      <a href="logout.php">Logout<small>End your session</small></a>
    </div>

    <div class="charts">
      <div class="panel">
        <h3>Stock health</h3>
        <?php $total = max(1, $stats['items']); $ok = max(0,$stats['items']-$stats['low']-$stats['out']); ?>
        <div class="bar-row"><span class="lb">Healthy</span><div class="bar-track"><div class="bar-fill" style="width:<?= round($ok/$total*100) ?>%;background:var(--ok)"></div></div><span class="bar-val"><?= $ok ?></span></div>
        <div class="bar-row"><span class="lb">Low</span><div class="bar-track"><div class="bar-fill" style="width:<?= round($stats['low']/$total*100) ?>%;background:var(--warn)"></div></div><span class="bar-val"><?= $stats['low'] ?></span></div>
        <div class="bar-row"><span class="lb">Out</span><div class="bar-track"><div class="bar-fill" style="width:<?= round($stats['out']/$total*100) ?>%;background:var(--danger)"></div></div><span class="bar-val"><?= $stats['out'] ?></span></div>
      </div>
      <div class="panel">
        <h3>Activity</h3>
        <div class="bar-row"><span class="lb">Movements</span><div class="bar-track"><div class="bar-fill" style="width:100%"></div></div><span class="bar-val"><?= $stats['movements'] ?></span></div>
        <div class="bar-row"><span class="lb">Pending PRs</span><div class="bar-track"><div class="bar-fill" style="width:<?= min(100, max(4, $stats['pending']*10)) ?>%;background:var(--warn)"></div></div><span class="bar-val"><?= $stats['pending'] ?></span></div>
      </div>
    </div>
  </div>

  <div class="foot">Brew &amp; Co. Inventory Management System</div>
<script src="input-guard.js"></script>
<script src="datatable.js"></script>
<script src="alerts.js"></script>
<script src="logout-confirm.js"></script>
<?= notification_script() ?>

<script src="responsive-ui.js" defer></script>
<script src="dashboard-navigation.js" defer></script>
<script src="inventory-ui.js" defer></script>
</body>
</html>
