<?php
require __DIR__ . '/db.php';
define('PAGE_ROLE', 'manager');

// Stocktaking is for manager (and superadmin). Clerk can't do physical counts.
if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['manager','superadmin'])) {
    header('Location: login.php'); exit;
}
$u = $_SESSION['user'];
$user = ['firstName'=>$u['firstName'],'lastName'=>$u['lastName'],'role'=>'Inventory Manager'];
$initials = strtoupper(substr($u['firstName'],0,1).substr($u['lastName'],0,1));
$flash = $_GET['msg'] ?? ''; $flashBad = isset($_GET['bad']);

$stID = (int)($_GET['st'] ?? $_POST['st_id'] ?? 0);

// ---- Actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_token($_POST['token'] ?? '')) { header('Location: stocktake.php?msg='.urlencode('Invalid form token.').'&bad=1'); exit; }
    if (db_ok()) {
    $a = $_POST['st_action'] ?? '';

    if ($a === 'start') {
        // Create a new draft stocktake snapshotting all active items
        $max = (int)(db_one("SELECT MAX(CAST(SUBSTRING(ref,4) AS UNSIGNED)) m FROM stocktakes")['m'] ?? 0);
        $ref = 'ST-' . str_pad($max + 1, 4, '0', STR_PAD_LEFT);
        db_exec('INSERT INTO stocktakes (ref,status,performed_by,note) VALUES (?,?,?,?)',
                [$ref,'draft',(int)$u['id'], trim($_POST['note'] ?? '')]);
        $sid = db_last_id();
        $items = db_all('SELECT id FROM items WHERE is_active=1');
        foreach ($items as $it) {
            $sys = db_one('SELECT current_qty FROM items WHERE id=?', [$it['id']])['current_qty'];
            db_exec('INSERT INTO stocktake_items (stocktake_id,item_id,system_qty,counted_qty) VALUES (?,?,?,NULL)',
                    [$sid,$it['id'],$sys]);
        }
        audit($u, 'stocktake.start', 'Started stocktake '.$ref);
        header('Location: stocktake.php?st='.$sid.'&msg='.urlencode('Stocktake '.$ref.' started. Enter physical counts.')); exit;
    }

    elseif ($a === 'save_count') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $count = ($_POST['count'] ?? '') === '' ? null : (float)$_POST['count'];
        if ($stID && $itemId) {
            db_exec('UPDATE stocktake_items SET counted_qty=? WHERE stocktake_id=? AND item_id=?',
                    [$count,$stID,$itemId]);
            header('Location: stocktake.php?st='.$stID.'&msg='.urlencode('Count saved.')); exit;
        }
    }

    elseif ($a === 'finalize') {
        $adjustments = tx(function() use ($stID,$u) {
            $rows = db_all('SELECT si.id, si.item_id, si.system_qty, si.counted_qty, i.unit
                            FROM stocktake_items si JOIN items i ON si.item_id=i.id
                            WHERE si.stocktake_id=? AND si.counted_qty IS NOT NULL', [$stID]);
            $n = 0;
            foreach ($rows as $r) {
                $variance = $r['counted_qty'] - $r['system_qty'];
                if (abs($variance) > 0.001) {
                    inv_record_movement((int)$r['item_id'], 'adjust', $variance, $r['unit'],
                                        'Stocktake adjustment (counted '.$r['counted_qty'].' vs '.$r['system_qty'].')', (int)$u['id']);
                    $n++;
                }
            }
            db_exec("UPDATE stocktakes SET status='finalized', finalized_at=NOW() WHERE id=?", [$stID]);
            audit($u, 'stocktake.finalize', 'Finalized stocktake #'.$stID.' with '.$n.' adjustment(s)');
            return $n;
        });
        header('Location: stocktake.php?msg='.urlencode('Stocktake finalized. '.$adjustments.' item(s) adjusted.')); exit;
    }
    }
}

// ---- Load data ----
$stocks = [];
$currentST = null;
if ($stID) {
    $currentST = db_one('SELECT * FROM stocktakes WHERE id=?', [$stID]);
    $stocks = db_all("SELECT si.id si_id, si.item_id, i.name, i.code, i.unit, si.system_qty, si.counted_qty,
                             (si.counted_qty - si.system_qty) variance
                      FROM stocktake_items si JOIN items i ON si.item_id=i.id
                      WHERE si.stocktake_id=? ORDER BY i.name", [$stID]);
}
$history = db_ok() ? db_all("SELECT st.id, st.ref, st.status, st.note, st.created_at, st.finalized_at,
                                    CONCAT(u.first_name,' ',u.last_name) byname,
                                    (SELECT COUNT(*) FROM stocktake_items si WHERE si.stocktake_id=st.id) items
                             FROM stocktakes st JOIN users u ON st.performed_by=u.id
                             ORDER BY st.created_at DESC LIMIT 20") : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="favicon.ico?v=2">
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Brew &amp; Co. — Stocktaking</title>
<style>
  :root{--brown-900:#faf6f0;--brown-800:#5c3a29;--brown-700:#7a5040;--brown-500:#a9714a;--accent:#a9714a;--accent-dark:#8f5c39;
    --cream:#faf6f0;--cream-2:#f5ede3;--card:#fff;--ink:#3b2313;--muted:#7a6055;--line:#e8ddd0;
    --ok:#256b4d;--warn:#96690a;--danger:#a23232;--radius:16px;--shadow:0 8px 24px rgba(74,47,34,.08);}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--cream);color:var(--ink);display:flex;min-height:100vh}
  a{text-decoration:none;color:inherit}
  svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
  .sidebar{width:250px;background:var(--cream);color:var(--ink);border-right:1px solid var(--line);display:flex;flex-direction:column;padding:22px 16px;position:fixed;top:0;left:0;height:100vh;z-index:60;overflow-y:auto;overflow-x:hidden;scrollbar-width:none}
  .sidebar:hover{scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.25) transparent}
  .sidebar::-webkit-scrollbar{width:5px}
  .sidebar:hover::-webkit-scrollbar{background:transparent}
  .sidebar::-webkit-scrollbar-thumb{background:transparent;border-radius:3px;transition:background .2s ease}
  .sidebar:hover::-webkit-scrollbar-thumb{background:rgba(255,255,255,.25)}
  .nav{flex:1;min-height:0;overflow-y:auto}

    .logo{padding:16px 10px 24px;display:flex;flex-direction:column;align-items:center;gap:4px}
  .logo img{width:56px;height:56px;margin-bottom:4px}
  .logo .brand-name{font-size:20px;color:#e9dccd;font-weight:800;letter-spacing:0.02em;line-height:1.2}
  .logo .brand-tagline{font-size:9px;color:#d8a066;font-weight:700;letter-spacing:0.25em;text-transform:uppercase;margin-top:2px}
  .nav-section-label{font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);padding:14px 14px 6px;opacity:0.7;font-weight:700}
  .nav a{display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:10px;color:var(--ink);font-weight:500;font-size:14px;margin-bottom:2px;cursor:pointer;transition:all .15s ease}
  .nav a:hover{background:rgba(169,113,74,.08);color:var(--accent)}
  .nav a.active{background:var(--accent);color:#fff;font-weight:600}
  .nav-divider{height:1px;background:var(--line);margin:6px 14px}

  .main{flex:1;margin-left:250px;padding:26px 34px;min-width:0}
  .topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}
  .topbar h1{font-size:26px;font-weight:800}.topbar .sub{color:var(--muted);font-size:13px;margin-top:2px}
  .who{display:flex;align-items:center;gap:11px}.avatar{width:44px;height:44px;border-radius:50%;background:var(--brown-500);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700}
  .who .n{font-weight:700;font-size:14px}.who .r{font-size:12px;color:var(--muted)}
  .panel{background:var(--card);border-radius:var(--radius);padding:22px;box-shadow:var(--shadow);margin-bottom:18px}
  .panel h2{font-size:18px;font-weight:800;margin-bottom:4px}.panel .desc{color:var(--muted);font-size:13px;margin-bottom:16px}
  .btn{display:inline-flex;align-items:center;gap:8px;border:1px solid var(--line);background:#fff;border-radius:10px;padding:10px 16px;font-size:14px;font-weight:700;cursor:pointer;color:var(--ink)}
  .btn.primary{background:var(--accent);color:#fff;border-color:var(--accent)}.btn.ok{background:var(--ok);color:#fff;border-color:var(--ok)}
  .table-wrap{overflow-x:auto}
  table{width:100%;border-collapse:collapse;font-size:14px;min-width:520px}
  thead th{text-align:left;font-size:11.5px;letter-spacing:.05em;text-transform:uppercase;color:var(--muted);padding:0 10px 12px}
  tbody td{padding:12px 10px;border-top:1px solid var(--line);vertical-align:middle}
  .pill{display:inline-flex;font-size:12px;font-weight:700;padding:4px 10px;border-radius:20px;text-transform:capitalize}
  .pill.ok{background:#e2f0e8;color:var(--ok)}.pill.warn{background:#f8eecf;color:var(--warn)}
  .pill.danger{background:#f6e0e0;color:var(--danger)}
  .var-pos{color:var(--ok);font-weight:700}.var-neg{color:var(--danger);font-weight:700}.var-zero{color:var(--muted)}
  .count-input{width:110px;border:1px solid var(--line);border-radius:8px;padding:8px 10px;font-size:14px;color:var(--ink);background:#fff}
  .alert{background:#e2f0e8;color:var(--ok);border-radius:10px;padding:12px 14px;font-size:13px;margin-bottom:18px}
  .alert.bad{background:#f6e0e0;color:var(--danger)}
  .grid{display:grid;grid-template-columns:1.3fr .7fr;gap:18px}
  .empty{padding:30px 20px;text-align:center;color:var(--muted);font-size:14px}
  .field{margin-bottom:14px}.field label{display:block;font-size:13px;font-weight:600;margin-bottom:6px}
  .field input,.field textarea{width:100%;border:1px solid var(--line);border-radius:10px;padding:11px 12px;font-size:14px;background:#fff;color:var(--ink);outline:none;font-family:inherit}
  @media(max-width:900px){.grid{grid-template-columns:1fr}.sidebar{position:fixed;left:0;top:0;transform:translateX(-100%)}.main{margin-left:0;padding:18px}.who .n,.who .r{display:none}}
  @media print{ .sidebar,.topbar .who,.btn{display:none!important} body{background:#fff} .main{padding:0} .panel{box-shadow:none;padding:0} }

.icon-btn{position:relative;width:44px;height:44px;border-radius:50%;background:#fff;box-shadow:0 8px 24px rgba(74,47,34,.08);display:flex;align-items:center;justify-content:center;cursor:pointer;border:none;color:#33261d}
  .user-area{display:flex;align-items:center;gap:18px}
  .pos{position:relative}
</style>

<link rel="stylesheet" href="responsive-ui.css">
<link rel="stylesheet" href="dashboard-navigation.css">
<link rel="stylesheet" href="inventory-ui.css">
<link rel="stylesheet" href="warehouse-operations.css">
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
    <a href="inventory-manager.php#dashboard"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg> Manager Dashboard</a>

    <div class="nav-divider"></div>
    <div class="nav-section-label">Procurement</div>
    <a href="inventory-manager.php#procure"><svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/></svg> Procurement</a>
    <a href="inventory-manager.php#ship"><svg viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg> Shipment Tracking</a>
    <a href="inventory-manager.php#qc"><svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg> Quality Check</a>

    <div class="nav-divider"></div>
    <div class="nav-section-label">Manage</div>
    <a href="inventory-manager.php#sellers"><svg viewBox="0 0 24 24"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.6A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg> Seller Contacts</a>
    <a href="inventory-manager.php#items"><svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/></svg> Items &amp; Categories</a>
    <a href="returns.php"><svg viewBox="0 0 24 24"><path d="M3 3v18h18M7 12l4-4 4 4M11 8v8"/></svg> Returns to Supplier</a>
    <a href="stocktake.php" class="active" aria-current="page"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4M11 8v6M8 11h6"/></svg> Stocktaking</a>
    <a href="warehouse.php"><svg viewBox="0 0 24 24"><path d="M3 21V8l9-5 9 5v13M3 21h18M7 21v-8M12 21v-8M17 21v-8"/></svg> Warehouse</a>

    <div class="nav-divider"></div>
    <div class="nav-section-label">Monitor</div>
    <a href="inventory-manager.php#oversight"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> Clerk Oversight</a>
    <a href="inventory-manager.php#profile"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></svg> My Profile</a>

    <div class="nav-divider"></div>
    <div class="nav-section-label">Account</div>
    <a href="logout.php"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg> Logout</a>
  </nav>
</aside>

<main class="main">
  <div class="topbar">
    <div><h1>Stocktaking</h1><div class="sub">Physical count vs. system — detect shrinkage &amp; adjust stock</div></div>
    <div class="user-area">
      <?= notification_bell($u["role"]) ?>
      <div class="who"><div class="avatar"><?= $initials ?></div>
    </div><div><div class="n"><?= htmlspecialchars($user['firstName'].' '.$user['lastName']) ?></div><div class="r"><?= htmlspecialchars($user['role']) ?></div></div></div>
  </div>

  <?php if ($flash): ?><div class="alert <?= $flashBad ? 'bad' : '' ?>"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

  <?php if ($currentST && $currentST['status'] === 'draft'): ?>
  <div class="panel">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
      <div>
        <h2>Count Sheet — <?= htmlspecialchars($currentST['ref']) ?></h2>
        <div class="desc">Enter the physical quantity you counted for each item. Uncounted items are left unchanged on finalize.</div>
      </div>
      <div style="display:flex;gap:8px">
        <button class="btn" onclick="window.print()">🖨 Print count sheet</button>
        <a class="btn" href="stocktake.php">← Back to history</a>
        <form method="post" action="stocktake.php" onsubmit="return sweetConfirmSubmit(event,'Finalize this stocktake? Differences will be applied to stock.')"><?= token_field() ?>
          <input type="hidden" name="st_action" value="finalize">
          <input type="hidden" name="st_id" value="<?= $stID ?>">
          <button class="btn ok" type="submit">Finalize Stocktake</button>
        </form>
      </div>
    </div>
    <div class="table-wrap" style="margin-top:14px">
      <table class="dt" >
        <thead><tr><th>Item</th><th>System Qty</th><th>Physical Count</th><th>Variance</th></tr></thead>
        <tbody>
          <?php foreach($stocks as $r): ?>
          <tr>
            <td><b><?= htmlspecialchars($r['name']) ?></b><br><small style="color:var(--muted)"><?= $r['code'] ?></small></td>
            <td><?= $r['system_qty'] ?> <?= $r['unit'] ?></td>
            <td>
              <form method="post" action="stocktake.php" style="display:flex;gap:6px;align-items:center"><?= token_field() ?>
                <input type="hidden" name="st_action" value="save_count">
                <input type="hidden" name="st_id" value="<?= $stID ?>">
                <input type="hidden" name="item_id" value="<?= (int)$r['item_id'] ?>">
                <input class="count-input" type="number" step="any" min="0" name="count" value="<?= $r['counted_qty'] !== null ? $r['counted_qty'] : '' ?>" placeholder="—">
                <button class="btn" type="submit">Save</button>
              </form>
            </td>
            <td>
              <?php if($r['counted_qty'] !== null): $v = $r['variance'];
                if($v > 0.001): ?><span class="var-pos">+<?= $v ?></span>
                <?php elseif($v < -0.001): ?><span class="var-neg"><?= $v ?></span>
                <?php else: ?><span class="var-zero">0</span><?php endif; ?>
              <?php else: ?><span style="color:var(--muted)">—</span><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php else: ?>

  <div class="grid">
    <div class="panel">
      <h2>Stocktake History</h2>
      <div class="desc">Past physical counts and their results.</div>
      <div class="table-wrap">
        <table class="dt">
          <thead><tr><th>Ref</th><th>Status</th><th>Items</th><th>By</th><th>Date</th><th></th></tr></thead>
          <tbody>
            <?php foreach($history as $h): ?>
            <tr>
              <td><b><?= htmlspecialchars($h['ref']) ?></b></td>
              <td><span class="pill <?= $h['status']==='finalized'?'ok':'warn' ?>"><?= $h['status'] ?></span></td>
              <td><?= $h['items'] ?></td>
              <td><?= htmlspecialchars($h['byname']) ?></td>
              <td><?= date('Y-m-d', strtotime($h['created_at'])) ?></td>
              <td><?php if($h['status']==='draft'): ?><a class="btn" href="?st=<?= (int)$h['id'] ?>">Continue</a><?php else: ?><a class="btn" href="?st=<?= (int)$h['id'] ?>">View</a><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(!$history): ?><tr><td colspan="6" class="empty">No stocktakes yet. Start your first one on the right.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="panel">
      <h2>Start a New Stocktake</h2>
      <div class="desc">Begins a count sheet listing all active items with their current system quantities.</div>
      <form method="post" action="stocktake.php"><?= token_field() ?>
        <input type="hidden" name="st_action" value="start">
        <div class="field"><label>Note (optional)</label><textarea name="note" rows="3" placeholder="e.g. Month-end physical count"></textarea></div>
        <button class="btn primary" type="submit">+ Start Stocktake</button>
      </form>
      <p style="font-size:12.5px;color:var(--muted);margin-top:14px;line-height:1.7">After entering counts, <b>Finalize</b> applies the differences to stock and records them as adjustments.</p>
    </div>
  </div>

  <?php endif; ?>
</main>

<script src="input-guard.js"></script>
<script src="datatable.js"></script>
<script src="alerts.js"></script>
<script>function goHome(e){ if(e) e.preventDefault(); location.href='finance-dashboard.php'; }</script>
<script src="logout-confirm.js"></script>
<script>window.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('.sidebar nav').forEach(function(n){n.scrollTop=0;});});</script>
<?= notification_script() ?>
<script src="ux-improvements.js"></script>

<script src="responsive-ui.js" defer></script>
<script src="dashboard-navigation.js" defer></script>
<script src="inventory-ui.js" defer></script>
<script src="warehouse-operations.js" defer></script>
</body>
</html>
