<?php
require __DIR__ . '/db.php';
define('PAGE_ROLE', 'clerk');

// Role guard: clerk (daily stock manager) and superadmin only.
if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], [PAGE_ROLE, 'superadmin'])) {
    header('Location: login.php'); exit;
}
$u = $_SESSION['user'];
$user = ['firstName'=>$u['firstName'],'lastName'=>$u['lastName'],'role'=>'Inventory Clerk'];
$initials = strtoupper(substr($u['firstName'],0,1).substr($u['lastName'],0,1));
$flash = $_GET['msg'] ?? ''; $flashBad = isset($_GET['bad']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_token($_POST['token'] ?? '')) { header('Location: expiry.php?msg='.urlencode('Invalid form token.').'&bad=1'); exit; }
    if (db_ok()) {
        $a = $_POST['exp_action'] ?? '';
        if ($a === 'add_batch') {
            $itemName = trim($_POST['item'] ?? ''); $ref = trim($_POST['ref'] ?? '');
            $qty = (float)($_POST['qty'] ?? 0); $exp = trim($_POST['expiry'] ?? '');
            $it = db_one('SELECT id FROM items WHERE name=? AND is_active=1 LIMIT 1', [$itemName]);
            if ($it && $ref && $qty > 0) {
                db_exec('INSERT INTO batches (item_id, batch_ref, qty, expiry_date) VALUES (?,?,?,?)',
                        [$it['id'], $ref, $qty, $exp ?: null]);
                audit($u, 'batch.add', 'Added batch '.$ref.' ('.$itemName.', '.$qty.' qty'.($exp?', expiry '.$exp:'').')');
                header('Location: expiry.php?msg='.urlencode('Batch added.')); exit;
            }
            header('Location: expiry.php?msg='.urlencode('Please fill in item, reference and a valid quantity.').'&bad=1'); exit;
        }
        elseif ($a === 'adjust_batch') {
            $id = (int)($_POST['id'] ?? 0); $qty = (float)($_POST['qty'] ?? 0);
            if ($id && $qty >= 0) { db_exec('UPDATE batches SET qty=? WHERE id=?', [$qty, $id]); header('Location: expiry.php?msg='.urlencode('Batch quantity updated.')); exit; }
        }
    }
}

$batches = db_ok() ? db_all("SELECT b.id, b.batch_ref, i.name item, i.unit, b.qty, b.expiry_date,
                                    DATEDIFF(b.expiry_date, CURDATE()) days_left
                             FROM batches b JOIN items i ON b.item_id=i.id
                             ORDER BY b.expiry_date IS NULL, b.expiry_date") : [];
$items = db_ok() ? db_all("SELECT id, name, unit FROM items WHERE is_active=1 ORDER BY name") : [];

$expiring = []; $expired = [];
foreach ($batches as $b) {
    if ($b['expiry_date']) {
        if ($b['days_left'] < 0) $expired[] = $b;
        elseif ($b['days_left'] <= 14) $expiring[] = $b;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="favicon.ico?v=2">
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Brew &amp; Co. — Batch &amp; Expiry</title>
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
  table{width:100%;border-collapse:collapse;font-size:14px;min-width:540px}
  thead th{text-align:left;font-size:11.5px;letter-spacing:.05em;text-transform:uppercase;color:var(--muted);padding:0 10px 12px}
  tbody td{padding:12px 10px;border-top:1px solid var(--line);vertical-align:middle}
  .pill{display:inline-flex;font-size:12px;font-weight:700;padding:4px 10px;border-radius:20px}
  .pill.ok{background:#e2f0e8;color:var(--ok)}.pill.warn{background:#f8eecf;color:var(--warn)}.pill.danger{background:#f6e0e0;color:var(--danger)}
  .field{margin-bottom:14px}.field label{display:block;font-size:13px;font-weight:600;margin-bottom:6px}.field .req{color:var(--danger)}
  .field input,.field select{width:100%;border:1px solid var(--line);border-radius:10px;padding:11px 12px;font-size:14px;background:#fff;color:var(--ink);outline:none;font-family:inherit}
  .btn-primary{width:100%;background:var(--accent);color:#fff;border:none;border-radius:10px;padding:13px;font-size:15px;font-weight:700;cursor:pointer}
  .btn-primary:hover{background:var(--accent-dark)}
  .btn-sm{border:1px solid var(--line);background:#fff;border-radius:8px;padding:6px 10px;font-size:12px;font-weight:700;cursor:pointer;color:var(--ink)}
  .alert{background:#e2f0e8;color:var(--ok);border-radius:10px;padding:12px 14px;font-size:13px;margin-bottom:18px}
  .alert.bad{background:#f6e0e0;color:var(--danger)}
  .warning-box{background:#fdf3e3;border:1px solid var(--warn);border-radius:12px;padding:14px;margin-bottom:16px;font-size:13px}
  .empty{padding:30px 20px;text-align:center;color:var(--muted);font-size:14px}
  .icon-btn{position:relative;width:44px;height:44px;border-radius:50%;background:#fff;box-shadow:0 8px 24px rgba(74,47,34,.08);display:flex;align-items:center;justify-content:center;cursor:pointer;border:none;color:#33261d}
  @media(max-width:900px){.grid{grid-template-columns:1fr}.sidebar{position:fixed;left:0;top:0;transform:translateX(-100%)}.main{margin-left:0;padding:18px}.who .n,.who .r{display:none}}
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
    <a href="inventory-clerk.php"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg> Inventory Dashboard</a>

    <div class="nav-divider"></div>
    <div class="nav-section-label">Actions</div>
    <a href="inventory-clerk.php#movement"><svg viewBox="0 0 24 24"><path d="M7 10l-4 4 4 4M3 14h14M17 14l4-4-4-4M21 10H7"/></svg> Stock In / Out</a>
    <a href="inventory-clerk.php#request"><svg viewBox="0 0 24 24"><path d="M4 17h16M4 17l4-4M4 17l4 4M20 7H4M20 7l-4-4M20 7l-4 4"/></svg> Request from Warehouse</a>

    <div class="nav-divider"></div>
    <div class="nav-section-label">Monitor</div>
    <a href="inventory-clerk.php#stocks"><svg viewBox="0 0 24 24"><path d="M3 3h18v18H3zM3 9h18M3 15h18"/></svg> Current Stocks</a>
    <a href="inventory-clerk.php#logs"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg> Stock Logs</a>
    <a href="expiry.php" class="active" aria-current="page"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg> Batch &amp; Expiry</a>

    <div class="nav-divider"></div>
    <div class="nav-section-label">Account</div>
    <a href="inventory-clerk.php#profile"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></svg> My Profile</a>
    <a href="logout.php"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg> Logout</a>
  </nav>
</aside>

<main class="main">
  <div class="topbar">
    <div><h1>Batch &amp; Expiry Tracking</h1><div class="sub">FEFO — use the nearest-to-expire lots first</div></div>
    <div class="user-area">
      <?= notification_bell($u["role"]) ?>
      <div class="who"><div class="avatar"><?= $initials ?></div><div><div class="n"><?= htmlspecialchars($user['firstName'].' '.$user['lastName']) ?></div><div class="r"><?= htmlspecialchars($user['role']) ?></div></div></div>
    </div>
  </div>

  <?php if ($flash): ?><div class="alert <?= $flashBad ? 'bad' : '' ?>"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

  <?php if ($expired): ?><div class="warning-box"><b>⚠ Expired batches:</b> <?= count($expired) ?> batch(es) past their expiry date. Consider disposing them.</div><?php endif; ?>
  <?php if ($expiring): ?><div class="warning-box"><b>⚠ Expiring within 14 days:</b> <?= count($expiring) ?> batch(es) should be prioritized for use.</div><?php endif; ?>

  <div class="grid">
    <div class="panel">
      <h2>Batches / Lots</h2>
      <div class="desc">Each lot with its quantity and expiry. Use nearest-expiry first (FEFO).</div>
      <div class="table-wrap">
        <table class="dt">
          <thead><tr><th>Item</th><th>Batch Ref</th><th>Qty</th><th>Expiry</th><th>Days left</th><th>Status</th><th>Qty</th></tr></thead>
          <tbody>
            <?php foreach($batches as $b): ?>
            <tr>
              <td><b><?= htmlspecialchars($b['item']) ?></b></td>
              <td><?= htmlspecialchars($b['batch_ref']) ?></td>
              <td><?= $b['qty'] ?> <?= htmlspecialchars($b['unit']) ?></td>
              <td><?= $b['expiry_date'] ?: '—' ?></td>
              <td>
                <?php if($b['expiry_date'] && $b['days_left'] < 0): ?><span class="pill danger"><?= $b['days_left'] ?>d (expired)</span>
                <?php elseif($b['expiry_date'] && $b['days_left'] <= 14): ?><span class="pill warn"><?= $b['days_left'] ?>d</span>
                <?php elseif($b['expiry_date']): ?><span class="pill ok"><?= $b['days_left'] ?>d</span>
                <?php else: ?><span style="color:var(--muted)">—</span><?php endif; ?>
              </td>
              <td>
                <?php if($b['expiry_date'] && $b['days_left'] < 0): ?><span class="pill danger">Expired</span>
                <?php elseif($b['expiry_date'] && $b['days_left'] <= 14): ?><span class="pill warn">Expiring</span>
                <?php else: ?><span class="pill ok">Fresh</span><?php endif; ?>
              </td>
              <td>
                <form method="post" action="expiry.php" style="display:flex;gap:4px;align-items:center"><?= token_field() ?>
                  <input type="hidden" name="exp_action" value="adjust_batch"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                  <input type="number" step="any" min="0" name="qty" value="<?= $b['qty'] ?>" style="width:70px;padding:5px;border:1px solid var(--line);border-radius:6px">
                  <button class="btn-sm" type="submit">Set</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if(!$batches): ?><tr><td colspan="7" class="empty">No batches yet. Add a lot on the right when you receive goods with an expiry date.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="panel">
      <h2>Add Batch / Lot</h2>
      <div class="desc">Register an incoming lot with its expiry date.</div>
      <form method="post" action="expiry.php"><?= token_field() ?>
        <input type="hidden" name="exp_action" value="add_batch">
        <div class="field"><label>Item <span class="req">*</span></label>
          <select name="item" required><option value="">— Select item —</option><?php foreach($items as $it): ?><option value="<?= htmlspecialchars($it['name']) ?>"><?= htmlspecialchars($it['name']) ?> (<?= $it['unit'] ?>)</option><?php endforeach; ?></select>
        </div>
        <div class="field"><label>Batch reference <span class="req">*</span></label><input name="ref" required placeholder="e.g. LOT-0725"></div>
        <div class="field"><label>Quantity <span class="req">*</span></label><input name="qty" type="number" min="1" step="any" max="500" required></div>
        <div class="field"><label>Expiry date</label><input name="expiry" type="date" min="<?= date('Y-m-d') ?>"></div>
        <button class="btn-primary" type="submit">+ Add Batch</button>
      </form>
      <p style="font-size:12px;color:var(--muted);margin-top:14px;line-height:1.7">Batches track which lot will expire first. Use them to prioritize stock (FEFO).</p>
    </div>
  </div>
</main>
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
</body>
</html>
