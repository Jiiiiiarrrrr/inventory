<?php
require __DIR__ . '/db.php';
define('PAGE_ROLE', 'reports'); // not a real role; we allow finance/superadmin only

if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['finance','superadmin'])) {
    header('Location: login.php'); exit;
}
$u = $_SESSION['user'];
$roleLabel = ['clerk'=>'Inventory Clerk','manager'=>'Inventory Manager','finance'=>'Finance Officer','superadmin'=>'Superadmin'];
$user = ['firstName'=>$u['firstName'], 'lastName'=>$u['lastName'], 'role'=>$roleLabel[$u['role']] ?? $u['role']];
$initials = strtoupper(substr($u['firstName'],0,1).substr($u['lastName'],0,1));

// ---- CSV export ----
if (isset($_GET['export']) && db_ok()) {
    if ($_GET['export'] === 'lowstock') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="low-stock-'.date('Y-m-d').'.csv"');
        $out = fopen('php://output','w');
        fputcsv($out, ['Code','Item','Category','Qty','Unit','Reorder Level','Status']);
        $rows = db_all("SELECT i.code, i.name, c.name cat, i.current_qty, i.unit, i.reorder_level
                        FROM items i JOIN categories c ON i.category_id=c.id
                        WHERE i.is_active=1 AND i.current_qty <= i.reorder_level
                        ORDER BY (i.current_qty / i.reorder_level)");
        foreach ($rows as $r) {
            fputcsv($out, [$r['code'], $r['name'], $r['cat'], $r['current_qty'], $r['unit'], $r['reorder_level'],
                            $r['current_qty'] <= 0 ? 'Out of stock' : 'Low stock']);
        }
        fclose($out); exit;
    }
    elseif ($_GET['export'] === 'items') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="stock-items-'.date('Y-m-d').'.csv"');
        $out = fopen('php://output','w');
        fputcsv($out, ['Code','Item','Category','Unit','Qty','Unit Cost (₱)','Stock Value (₱)','Reorder','Status']);
        $rows = db_all("SELECT i.code,i.name,c.name cat,i.unit,i.current_qty,i.cost,i.reorder_level,i.is_active
                        FROM items i JOIN categories c ON i.category_id=c.id ORDER BY i.name");
        foreach ($rows as $r) {
            fputcsv($out, [$r['code'],$r['name'],$r['cat'],$r['unit'],$r['current_qty'],$r['cost'],
                            $r['current_qty']*$r['cost'],$r['reorder_level'],$r['is_active']?'Active':'Inactive']);
        }
        fclose($out); exit;
    }
    elseif ($_GET['export'] === 'movements') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="stock-movements-'.date('Y-m-d').'.csv"');
        $out = fopen('php://output','w');
        fputcsv($out, ['Date','Item','Action','Qty','Unit','By','Note']);
        $rows = db_all("SELECT sm.created_at,i.name,sm.action,sm.qty,sm.unit,CONCAT(u.first_name,' ',u.last_name) byname,sm.note
                        FROM stock_movements sm JOIN items i ON sm.item_id=i.id JOIN users u ON sm.performed_by=u.id
                        ORDER BY sm.created_at DESC LIMIT 2000");
        foreach ($rows as $r) fputcsv($out, [$r['created_at'],$r['name'],$r['action'],$r['qty'],$r['unit'],$r['byname'],$r['note']]);
        fclose($out); exit;
    }
}

// ---- Gather data ----
$movementLabels = ['in','out','reduce','remove'];
$movementCounts = array_fill_keys($movementLabels, 0);
$movementUnits  = array_fill_keys($movementLabels, 0.0);
$totalMoves = 0;
if (db_ok()) {
    $rows = db_all("SELECT action, COUNT(*) c, COALESCE(SUM(qty),0) u FROM stock_movements GROUP BY action");
    foreach ($rows as $r) { $movementCounts[$r['action']] = (int)$r['c']; $movementUnits[$r['action']] = (float)$r['u']; $totalMoves += (int)$r['c']; }
}

$stockValue = 0; $lowCount = 0; $outCount = 0; $totalItems = 0; $stockByCat = [];
$ingredientCogs = 0; $cogsPeriod = 0;
if (db_ok()) {
    $rows = db_all("SELECT i.current_qty, i.reorder_level, i.unit, i.cost, c.name cat
                    FROM items i JOIN categories c ON i.category_id=c.id WHERE i.is_active=1");
    $totalItems = count($rows);
    foreach ($rows as $r) {
        if ($r['current_qty'] <= 0) $outCount++;
        elseif ($r['current_qty'] <= $r['reorder_level']) $lowCount++;
        $stockByCat[$r['cat']] = ($stockByCat[$r['cat']] ?? 0) + (float)$r['current_qty'];
        $stockValue += (float)$r['current_qty'] * (float)$r['cost'];
    }
    // Ingredient-level COGS: cost of all stock used out / reduced / removed
    $ingredientCogs = (float)db_one("SELECT COALESCE(SUM(sm.qty * i.cost),0) n
        FROM stock_movements sm JOIN items i ON sm.item_id=i.id
        WHERE sm.action IN ('out','reduce','remove')")['n'];
    $cogsPeriod = (float)db_one("SELECT COALESCE(SUM(sm.qty * i.cost),0) n
        FROM stock_movements sm JOIN items i ON sm.item_id=i.id
        WHERE sm.action IN ('out','reduce','remove') AND sm.created_at >= NOW() - INTERVAL 30 DAY")['n'];
}
arsort($stockByCat);

// Menu profitability (finance-ish)
$menuStats = ['revenue'=>0,'cogs'=>0,'gross'=>0];
$menuItems = [];
if (db_ok()) {
    $menuItems = db_all("SELECT name, price, cost, sold FROM menu_items WHERE is_active=1 ORDER BY (price*sold) DESC");
    foreach ($menuItems as $m) {
        $menuStats['revenue'] += (float)$m['price'] * (int)$m['sold'];
        $menuStats['cogs']    += (float)$m['cost'] * (int)$m['sold'];
    }
}
$menuStats['gross'] = $menuStats['revenue'] - $menuStats['cogs'];
$menuStats['margin'] = $menuStats['revenue'] > 0 ? round($menuStats['gross']/$menuStats['revenue']*100,1) : 0;

// Low-stock list for the on-screen table
$lowStockRows = [];
if (db_ok()) {
    $lowStockRows = db_all("SELECT i.code, i.name, c.name cat, i.current_qty, i.unit, i.reorder_level
                            FROM items i JOIN categories c ON i.category_id=c.id
                            WHERE i.is_active=1 AND i.current_qty <= i.reorder_level
                            ORDER BY (i.current_qty / i.reorder_level) LIMIT 30");
}

// Movement history for the recent-activity chart (last 7 days)
$recentMoves = [];
if (db_ok()) {
    $recentMoves = db_all("SELECT DATE(created_at) d, action, SUM(qty) q FROM stock_movements
                           WHERE created_at >= CURDATE() - INTERVAL 6 DAY
                           GROUP BY DATE(created_at), action ORDER BY d");
}
$chartDays = [];
for ($i=6; $i>=0; $i--) { $chartDays[date('Y-m-d', strtotime("-$i days"))] = 0; }
$chartIn = $chartDays; $chartOut = $chartDays;
foreach ($recentMoves as $m) {
    if (isset($chartIn[$m['d']])) {
        if ($m['action'] === 'in') $chartIn[$m['d']] = (float)$m['q'];
        elseif (in_array($m['action'],['out','reduce','remove'])) $chartOut[$m['d']] = (float)$m['q'];
    }
}
function maxVal($arr){ return max(1, max($arr)); }
function barH($v,$max,$min=0){ return max($min, round($v/$max*100)); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="favicon.ico?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Brew &amp; Co. — Reports &amp; Insights</title>
<style>
  :root{--brown-900:#faf6f0;--brown-800:#5c3a29;--brown-700:#7a5040;
    --brown-500:#a9714a;--accent:#a9714a;--accent-dark:#8f5c39;
    --cream:#faf6f0;--cream-2:#f5ede3;--card:#fff;--ink:#3b2313;--muted:#7a6055;--line:#e8ddd0;
    --ok:#256b4d;--warn:#96690a;--danger:#a23232;--radius:16px;--shadow:0 8px 24px rgba(74,47,34,.08);}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--cream);color:var(--ink);display:flex;min-height:100vh}
  a{text-decoration:none;color:inherit}
  svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
  :focus-visible{outline:3px solid rgba(169,113,74,.45);outline-offset:2px;border-radius:6px}
  .sidebar{width:250px;background:var(--cream);color:var(--ink);border-right:1px solid var(--line);display:flex;flex-direction:column;padding:22px 16px;position:fixed;top:0;left:0;height:100vh;z-index:60}

    .logo{padding:16px 10px 24px;display:flex;flex-direction:column;align-items:center;gap:4px}
  .logo img{width:56px;height:56px;margin-bottom:4px}
  .logo .brand-name{font-size:20px;color:#e9dccd;font-weight:800;letter-spacing:0.02em;line-height:1.2}
  .logo .brand-tagline{font-size:9px;color:#d8a066;font-weight:700;letter-spacing:0.25em;text-transform:uppercase;margin-top:2px}
  .nav a{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:10px;color:var(--ink);font-weight:500;font-size:14.5px;margin-bottom:4px}
  .nav a:hover{background:rgba(169,113,74,.08);color:var(--accent)}.nav a.active{background:var(--accent);color:#fff;font-weight:600}
  .nav-section-label{font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);padding:14px 14px 6px;opacity:0.7;font-weight:700}
  .nav-divider{height:1px;background:var(--line);margin:6px 14px}

  .main{flex:1;margin-left:250px;padding:26px 34px;min-width:0}
  .topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;gap:12px}
  .topbar h1{font-size:26px;font-weight:800}.topbar .sub{color:var(--muted);font-size:13px;margin-top:2px}
  .who{display:flex;align-items:center;gap:11px}.avatar{width:44px;height:44px;border-radius:50%;background:var(--brown-500);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700}
  .who .n{font-weight:700;font-size:14px}.who .r{font-size:12px;color:var(--muted)}
  .user-area{display:flex;align-items:center;gap:18px}
  .pos{position:relative}
  .view{display:none}.view.active{display:block;animation:fade .25s ease}
  @keyframes fade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
  .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:22px}
  .stat{background:var(--card);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow)}
  .stat .t{font-size:13px;color:var(--muted);margin-bottom:10px}.stat .v{font-size:28px;font-weight:800}
  .stat .v.ok{color:var(--ok)}.stat .v.warn{color:var(--warn)}.stat .v.danger{color:var(--danger)}.stat .sm{font-size:12px;color:var(--muted);margin-top:6px}
  .grid{display:grid;grid-template-columns:1.2fr .8fr;gap:18px}.grid2{display:grid;grid-template-columns:1fr 1fr;gap:18px}
  .panel{background:var(--card);border-radius:var(--radius);padding:22px;box-shadow:var(--shadow);margin-bottom:18px}
  .panel h2{font-size:18px;font-weight:800;margin-bottom:4px}.panel .desc{color:var(--muted);font-size:13px;margin-bottom:16px}
  .table-wrap{overflow-x:auto}
  table{width:100%;border-collapse:collapse;font-size:14px;min-width:420px}
  thead th{text-align:left;font-size:11.5px;letter-spacing:.05em;text-transform:uppercase;color:var(--muted);padding:0 10px 12px}
  tbody td{padding:12px 10px;border-top:1px solid var(--line);vertical-align:middle}
  .pill{display:inline-flex;font-size:12px;font-weight:700;padding:4px 10px;border-radius:20px}
  .pill.ok{background:#e2f0e8;color:var(--ok)}.pill.warn{background:#f8eecf;color:var(--warn)}.pill.danger{background:#f6e0e0;color:var(--danger)}
  .btn{border:1px solid var(--line);background:#fff;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:700;cursor:pointer;color:var(--ink);display:inline-flex;align-items:center;gap:8px}
  .btn.primary{background:var(--accent);color:#fff;border-color:var(--accent)}.btn:hover{background:var(--cream-2)}
  /* bar chart */
  .bars{display:flex;align-items:flex-end;gap:14px;height:180px;padding-top:10px}
  .bar-group{flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;height:100%;justify-content:flex-end}
  .bars-inner{display:flex;gap:4px;align-items:flex-end;width:100%;height:150px}
  .bar{flex:1;border-radius:6px 6px 0 0;position:relative}
  .bar.in{background:var(--ok)}.bar.out{background:var(--accent)}
  .bar-label{font-size:10.5px;color:var(--muted);white-space:nowrap;text-align:center}
  .legend{display:flex;gap:18px;margin-top:14px;font-size:12.5px;color:var(--muted)}
  .legend span{display:flex;align-items:center;gap:6px}.dot{width:11px;height:11px;border-radius:3px;display:inline-block}
  .minichart{display:flex;flex-direction:column;gap:10px}
  .mc{display:flex;align-items:center;gap:10px;font-size:12.5px}
  .mc .lb{width:90px;color:var(--muted);flex-shrink:0}.mc .track{flex:1;height:16px;background:var(--cream-2);border-radius:8px;overflow:hidden}
  .mc .fill{height:100%;border-radius:8px}
  .mc .val{width:70px;text-align:right;color:var(--ink);font-weight:700}
  .empty{padding:34px 20px;text-align:center;color:var(--muted);font-size:14px}
  @media(max-width:1050px){.stats{grid-template-columns:repeat(2,1fr)}.grid,.grid2{grid-template-columns:1fr}}
  @media(max-width:820px){.sidebar{position:fixed;left:0;top:0;transform:translateX(-100%);transition:.3s}.main{margin-left:0;padding:18px}.who .n,.who .r{display:none}}
  @media print{ .sidebar,.topbar .who,.nav,.help,.btn{display:none!important} body{background:#fff} .main{padding:0} .panel{box-shadow:none} }
.icon-btn{position:relative;width:44px;height:44px;border-radius:50%;background:#fff;box-shadow:0 8px 24px rgba(74,47,34,.08);display:flex;align-items:center;justify-content:center;cursor:pointer;border:none;color:#33261d}
</style>

<link rel="stylesheet" href="responsive-ui.css">
<link rel="stylesheet" href="dashboard-navigation.css">
<link rel="stylesheet" href="inventory-ui.css">
<link rel="stylesheet" href="procurement-finance.css">
<link rel="stylesheet" href="final-operations.css">
</head>
<body>
<aside class="sidebar" id="sidebar">
    <a href="#" class="logo" onclick="goHome(event)" title="Go to dashboard" style="text-decoration:none;cursor:pointer;">
    <img src="brewco-logo.svg" alt="Brew & Co.">
    <span class="brand-name">Brew &amp; Co.</span>
    <span class="brand-tagline">Coffee Shop</span>
  </a>
  <nav class="nav" aria-label="Main navigation">
    <div class="nav-section-label">Overview</div>
    <a href="finance-dashboard.php"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg> Finance Dashboard</a>

    <div class="nav-divider"></div>
    <div class="nav-section-label">Approvals</div>
    <a href="finance-dashboard.php#approvals"><svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg> Budget Approvals</a>
    <a href="finance-dashboard.php#prices"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg> Item Price Approvals</a>

    <div class="nav-divider"></div>
    <div class="nav-section-label">Settings</div>
    <a href="finance-dashboard.php#budget"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg> Budget Settings</a>
    <a href="finance-dashboard.php#menu"><svg viewBox="0 0 24 24"><path d="M18 8h1a4 4 0 0 1 0 8h-1M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4zM6 1v3M10 1v3M14 1v3"/></svg> Menu-Level Costs</a>

    <div class="nav-divider"></div>
    <div class="nav-section-label">Reports</div>
    <a href="reports.php" class="active" aria-current="page"><svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="M7 15l4-6 4 3 4-5"/></svg> Reports</a>

    <div class="nav-divider"></div>
    <div class="nav-section-label">Account</div>
    <a href="logout.php"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg> Logout</a>
  </nav></aside>

<main class="main">
  <div class="topbar">
    <div><h1 id="pageTitle">Profitability</h1><div class="sub" id="pageSub">Menu item costs, margins &amp; revenue</div></div>
    <div class="user-area">
      <?= notification_bell($u["role"]) ?>
      <div class="who"><div class="avatar"><?= $initials ?></div><div><div class="n"><?= htmlspecialchars($user['firstName'].' '.$user['lastName']) ?></div><div class="r"><?= htmlspecialchars($user['role']) ?></div></div></div>
    </div>
  </div>

  <!-- PROFITABILITY -->
  <section class="view active" id="view-menu">
    <div class="stats">
      <div class="stat"><div class="t">Revenue (from menu)</div><div class="v ok">₱<?= number_format($menuStats['revenue']) ?></div></div>
      <div class="stat"><div class="t">Cost of Goods Sold</div><div class="v warn">₱<?= number_format($menuStats['cogs']) ?></div></div>
      <div class="stat"><div class="t">Gross Profit</div><div class="v ok">₱<?= number_format($menuStats['gross']) ?></div><div class="sm">Margin: <?= $menuStats['margin'] ?>%</div></div>
    </div>
    <div class="panel">
      <h2>Menu Item Profitability</h2>
      <div class="desc">Revenue contribution and margin per product.</div>
      <div class="table-wrap">
        <table class="dt">
          <thead><tr><th>Product</th><th>Price</th><th>Cost</th><th>Margin</th><th>Sold</th><th>Revenue</th><th>Verdict</th></tr></thead>
          <tbody>
            <?php foreach ($menuItems as $m): $marg = $m['price']>0 ? round((($m['price']-$m['cost'])/$m['price'])*100) : 0; $good = $marg>=45; ?>
            <tr><td><b><?= htmlspecialchars($m['name']) ?></b></td><td>₱<?= number_format($m['price']) ?></td><td>₱<?= number_format($m['cost']) ?></td><td class="<?= $good?'color:var(--ok)':'color:var(--warn)' ?>" style="<?= $good?'color:var(--ok)':'color:var(--warn)' ?>"><?= $marg ?>%</td><td><?= $m['sold'] ?></td><td>₱<?= number_format($m['price']*$m['sold']) ?></td><td><span class="pill <?= $good?'ok':'warn' ?>"><?= $good?'Profitable':'Low margin' ?></span></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</main>

<script>
// Reports now shows Profitability only.
</script>
<script src="input-guard.js"></script>
<script src="datatable.js"></script>
<script src="alerts.js"></script>
<script>function goHome(e){ if(e) e.preventDefault(); location.href='finance-dashboard.php'; }</script>
<script src="logout-confirm.js"></script>
<?= notification_script() ?>
<script src="ux-improvements.js"></script>

<script src="responsive-ui.js" defer></script>
<script src="dashboard-navigation.js" defer></script>
<script src="inventory-ui.js" defer></script>
<script src="procurement-finance.js" defer></script>
<script src="final-operations.js" defer></script>
</body>
</html>
