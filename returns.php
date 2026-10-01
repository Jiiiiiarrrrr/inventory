<?php
require __DIR__ . '/db.php';
define('PAGE_ROLE', 'manager');

if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['manager','superadmin'])) {
    header('Location: login.php'); exit;
}
$u = $_SESSION['user'];
$user = ['firstName'=>$u['firstName'],'lastName'=>$u['lastName'],'role'=>'Inventory Manager'];
$initials = strtoupper(substr($u['firstName'],0,1).substr($u['lastName'],0,1));
$flash = $_GET['msg'] ?? ''; $flashBad = isset($_GET['bad']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_token($_POST['token'] ?? '')) { header('Location: returns.php?msg='.urlencode('Invalid form token.').'&bad=1'); exit; }
    if (db_ok()) {
        $a = $_POST['ret_action'] ?? '';
        if ($a === 'add_return') {
            $itemName = trim($_POST['item'] ?? ''); $qty = (float)($_POST['qty'] ?? 0);
            $unit = trim($_POST['unit'] ?? ''); $sellerName = trim($_POST['seller'] ?? '');
            $reason = trim($_POST['reason'] ?? ''); $batchRef = trim($_POST['batch_ref'] ?? '');
            $it = db_one('SELECT id, name, current_qty FROM items WHERE name=? LIMIT 1', [$itemName]);
            $seller = $sellerName ? db_one('SELECT id FROM sellers WHERE name=? LIMIT 1', [$sellerName]) : null;
            if ($it && $qty > 0) {
                $max = (int)(db_one("SELECT MAX(CAST(SUBSTRING(ref,5) AS UNSIGNED)) m FROM returns")['m'] ?? 0);
                $ref = 'RET-' . str_pad($max + 1, 4, '0', STR_PAD_LEFT);
                // Combine order/batch reference + reason for traceability
                $fullReason = trim(($batchRef ? '['.$batchRef.'] ' : '').$reason);
                db_exec('INSERT INTO returns (ref,item_id,qty,unit,seller_id,reason,status,returned_by)
                         VALUES (?,?,?,?,?,?,?,?)',
                        [$ref,$it['id'],$qty,$unit ?: 'unit',$seller?$seller['id']:null,$fullReason,'pending',(int)$u['id']]);
                // Reduce stock by the returned quantity (a removal)
                inv_record_movement((int)$it['id'], 'remove', $qty, $unit ?: 'unit', 'Returned to supplier ('.$ref.')', (int)$u['id']);
                audit($u, 'return.add', 'Created '.$ref.' — '.$it['name'].' x'.$qty.' '.$unit.' ('.$fullReason.')');
                header('Location: returns.php?msg='.urlencode('Return recorded. Stock reduced.')); exit;
            }
            header('Location: returns.php?msg='.urlencode('Please select a valid item and quantity.').'&bad=1'); exit;
        }
        elseif ($a === 'mark_returned') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id) { db_exec("UPDATE returns SET status='returned' WHERE id=?", [$id]); audit($u, 'return.complete', 'Marked return #'.$id.' as returned'); header('Location: returns.php?msg='.urlencode('Return marked as completed.')); exit; }
        }
    }
}

$returns = db_ok() ? db_all("SELECT r.id, r.ref, i.name item, r.qty, r.unit, COALESCE(s.name,'—') seller,
                                    r.reason, r.status, r.created_at
                             FROM returns r JOIN items i ON r.item_id=i.id LEFT JOIN sellers s ON r.seller_id=s.id
                             ORDER BY r.created_at DESC LIMIT 100") : [];
$items = db_ok() ? db_all("SELECT id, name, unit FROM items WHERE is_active=1 ORDER BY name") : [];
$sellers = db_ok() ? db_all("SELECT id, name FROM sellers WHERE is_active=1 ORDER BY name") : [];
// Recent order/shipment references (so the user can identify which batch they're returning)
$refs = db_ok() ? db_all("SELECT sh.ref, sh.item_desc, COALESCE(s.name,'—') seller, i.name item
                          FROM shipments sh LEFT JOIN sellers s ON sh.seller_id=s.id
                          LEFT JOIN items i ON sh.item_id=i.id
                          WHERE sh.status IN ('Delivered','Received')
                          ORDER BY sh.id DESC LIMIT 30") : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="favicon.ico?v=2">
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Brew &amp; Co. — Returns to Supplier</title>
<style>
  :root{--brown-900:#faf6f0;--brown-700:#7a5040;--brown-500:#a9714a;--accent:#a9714a;--accent-dark:#8f5c39;
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
  .grid{display:grid;grid-template-columns:1.3fr .7fr;gap:18px}
  .table-wrap{overflow-x:auto}
  table{width:100%;border-collapse:collapse;font-size:14px;min-width:560px}
  thead th{text-align:left;font-size:11.5px;letter-spacing:.05em;text-transform:uppercase;color:var(--muted);padding:0 10px 12px}
  tbody td{padding:12px 10px;border-top:1px solid var(--line);vertical-align:middle}
  .pill{display:inline-flex;font-size:12px;font-weight:700;padding:4px 10px;border-radius:20px}
  .pill.ok{background:#e2f0e8;color:var(--ok)}.pill.warn{background:#f8eecf;color:var(--warn)}
  .field{margin-bottom:14px}.field label{display:block;font-size:13px;font-weight:600;margin-bottom:6px}.field .req{color:var(--danger)}
  .field input,.field select,.field textarea{width:100%;border:1px solid var(--line);border-radius:10px;padding:11px 12px;font-size:14px;background:#fff;color:var(--ink);outline:none;font-family:inherit}
  .btn-primary{width:100%;background:var(--accent);color:#fff;border:none;border-radius:10px;padding:13px;font-size:15px;font-weight:700;cursor:pointer}
  .btn-primary:hover{background:var(--accent-dark)}
  .btn-sm{border:1px solid var(--line);background:#fff;border-radius:8px;padding:6px 10px;font-size:12px;font-weight:700;cursor:pointer;color:var(--ink)}
  .btn-sm.ok{color:#fff;background:var(--ok);border-color:var(--ok)}
  .alert{background:#e2f0e8;color:var(--ok);border-radius:10px;padding:12px 14px;font-size:13px;margin-bottom:18px}
  .alert.bad{background:#f6e0e0;color:var(--danger)}
  .row2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .empty{padding:30px 20px;text-align:center;color:var(--muted);font-size:14px}
  @media(max-width:900px){.grid{grid-template-columns:1fr}.sidebar{position:fixed;left:0;top:0;transform:translateX(-100%)}.main{margin-left:0;padding:18px}.who .n,.who .r{display:none}}
.icon-btn{position:relative;width:44px;height:44px;border-radius:50%;background:#fff;box-shadow:0 8px 24px rgba(74,47,34,.08);display:flex;align-items:center;justify-content:center;cursor:pointer;border:none;color:#33261d}
  .user-area{display:flex;align-items:center;gap:18px}
  .pos{position:relative}
</style>

<link rel="stylesheet" href="responsive-ui.css">
<link rel="stylesheet" href="dashboard-navigation.css">
<link rel="stylesheet" href="inventory-ui.css">
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
    <a href="returns.php" class="active" aria-current="page"><svg viewBox="0 0 24 24"><path d="M3 3v18h18M7 12l4-4 4 4M11 8v8"/></svg> Returns to Supplier</a>
    <a href="stocktake.php"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4M11 8v6M8 11h6"/></svg> Stocktaking</a>
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
    <div><h1>Returns to Supplier</h1><div class="sub">Send defective or expired goods back for credit</div></div>
    <div class="user-area">
      <?= notification_bell($u["role"]) ?>
      <div class="who"><div class="avatar"><?= $initials ?></div>
    </div><div><div class="n"><?= htmlspecialchars($user['firstName'].' '.$user['lastName']) ?></div><div class="r"><?= htmlspecialchars($user['role']) ?></div></div></div>
  </div>

  <?php if ($flash): ?><div class="alert <?= $flashBad ? 'bad' : '' ?>"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

  <div class="grid">
    <div class="panel">
      <h2>Return History</h2>
      <div class="desc">Goods returned to suppliers. Recording a return reduces your stock.</div>
      <div class="table-wrap">
        <table class="dt">
          <thead><tr><th>Ref</th><th>Item</th><th>Qty</th><th>Seller</th><th>Reason</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach($returns as $r): ?>
            <tr>
              <td><b><?= htmlspecialchars($r['ref']) ?></b></td>
              <td><?= htmlspecialchars($r['item']) ?></td>
              <td><?= $r['qty'] ?> <?= htmlspecialchars($r['unit']) ?></td>
              <td><?= htmlspecialchars($r['seller']) ?></td>
              <td style="color:var(--muted)"><?= htmlspecialchars($r['reason']) ?></td>
              <td><span class="pill <?= $r['status']==='returned'?'ok':'warn' ?>"><?= $r['status'] ?></span></td>
              <td><?php if($r['status']==='pending'): ?>
                <form method="post" action="returns.php" style="display:inline"><?= token_field() ?>
                  <input type="hidden" name="ret_action" value="mark_returned"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn-sm ok" type="submit">Mark returned</button>
                </form>
              <?php else: ?><span style="color:var(--muted);font-size:12px">✓</span><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(!$returns): ?><tr><td colspan="7" class="empty">No returns yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="panel">
      <h2>Record a Return</h2>
      <div class="desc">Return defective/expired goods. Stock is reduced automatically.</div>
      <form method="post" action="returns.php"><?= token_field() ?>
        <input type="hidden" name="ret_action" value="add_return">
        <div class="field"><label>Order / Batch reference <span class="req">*</span></label>
          <select name="batch_ref" id="batchRef" required onchange="fillItemFromBatch()"><option value="">— Select order/batch —</option>
            <?php foreach($refs as $ref): ?>
            <option value="<?= htmlspecialchars($ref['ref'].' — '.$ref['item_desc'].' ('.$ref['seller'].')') ?>" data-item="<?= htmlspecialchars($ref['item']) ?>"><?= htmlspecialchars($ref['ref'].' — '.$ref['item_desc'].' ('.$ref['seller'].')') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Item <span class="req">*</span></label>
          <select name="item" id="itemSel" required><option value="">— Select item —</option><?php foreach($items as $it): ?><option value="<?= htmlspecialchars($it['name']) ?>"><?= htmlspecialchars($it['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="row2">
          <div class="field"><label>Quantity <span class="req">*</span></label><input name="qty" type="number" min="1" step="any" required></div>
          <div class="field"><label>Unit <span class="req">*</span></label><select name="unit" required><option value="">—</option><option>kg</option><option>L</option><option>pcs</option><option>bottle</option></select></div>
        </div>
        <div class="field"><label>Supplier <span class="req">*</span></label>
          <select name="seller" required><option value="">— Select seller —</option><?php foreach($sellers as $s): ?><option><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="field"><label>Reason</label><textarea name="reason" rows="3" placeholder="e.g. Defective batch, expired, damaged..."></textarea></div>
        <button class="btn-primary" type="submit">+ Record Return</button>
      </form>
    </div>
  </div>
</main>
<script src="input-guard.js"></script>
<script src="datatable.js"></script>
<script src="alerts.js"></script>
<script>function goHome(e){ if(e) e.preventDefault(); location.href='finance-dashboard.php'; }</script>
<script src="logout-confirm.js"></script>
<script>window.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('.sidebar nav').forEach(function(n){n.scrollTop=0;});});</script>
<?= notification_script() ?>
<script>
function fillItemFromBatch(){
  var br=document.getElementById('batchRef');
  var item=document.getElementById('itemSel');
  if(!br||!item) return;
  var opt=br.options[br.selectedIndex];
  if(!opt||!opt.dataset.item){ item.value=''; return; }
  item.value = opt.dataset.item;  // auto-select the item in this batch
}
</script>
<script src="ux-improvements.js"></script>

<script src="responsive-ui.js" defer></script>
<script src="dashboard-navigation.js" defer></script>
<script src="inventory-ui.js" defer></script>
</body>
</html>
