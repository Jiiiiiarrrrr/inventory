<?php
require __DIR__ . '/db.php';
require __DIR__ . '/signature-panel.php';
define('PAGE_ROLE', 'clerk');

// Role guard: only a logged-in clerk may view this page.
if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], [PAGE_ROLE, 'superadmin'])) {
    if (db_ok()) { header('Location: login.php'); exit; }
    // demo fallback (DB down) so the page can still be previewed
    $_SESSION['user'] = ['id'=>0,'firstName'=>'Jenwin','lastName'=>'Docabo','email'=>'jenwin@brewco.ph','role'=>'clerk'];
}
$u = $_SESSION['user'];
$user = ['firstName' => $u['firstName'], 'lastName' => $u['lastName'], 'role' => 'Inventory Clerk'];
inv_ensure_purchase_flow();
$initials = strtoupper(substr($user['firstName'], 0, 1) . substr($user['lastName'], 0, 1));
change_password_handler($u, 'inventory-clerk.php'); // allow clerk to change their own password

// ---- Demo data (used only when the database is unreachable) ----
$demoStocks = [
    ['id' => 'ING-001', 'name' => 'Arabica Beans',   'category' => 'Coffee',   'unit' => 'kg',    'qty' => 42,  'reorder' => 15],
    ['id' => 'ING-002', 'name' => 'Robusta Beans',   'category' => 'Coffee',   'unit' => 'kg',    'qty' => 8,   'reorder' => 15],
    ['id' => 'ING-003', 'name' => 'Fresh Milk',      'category' => 'Dairy',    'unit' => 'L',     'qty' => 30,  'reorder' => 20],
    ['id' => 'ING-004', 'name' => 'Oat Milk',        'category' => 'Dairy',    'unit' => 'L',     'qty' => 12,  'reorder' => 10],
    ['id' => 'ING-005', 'name' => 'Caramel Syrup',   'category' => 'Syrup',    'unit' => 'bottle','qty' => 5,   'reorder' => 6],
    ['id' => 'ING-006', 'name' => 'Paper Cups 12oz', 'category' => 'Supplies', 'unit' => 'pcs',   'qty' => 340, 'reorder' => 200],
];
$demoLogs = [
    ['date' => '2026-07-28 09:14', 'item' => 'Arabica Beans',   'action' => 'in',     'qty' => '+20 kg',   'by' => 'Jenwin Docabo', 'note' => 'Delivery received'],
    ['date' => '2026-07-28 11:02', 'item' => 'Fresh Milk',      'action' => 'out',    'qty' => '-6 L',     'by' => 'Jenwin Docabo', 'note' => 'Used for AM shift'],
    ['date' => '2026-07-27 16:45', 'item' => 'Caramel Syrup',   'action' => 'reduce', 'qty' => '-1 bottle','by' => 'Jayr Fabon',     'note' => 'Spillage / wastage'],
    ['date' => '2026-07-27 08:30', 'item' => 'Paper Cups 12oz', 'action' => 'in',     'qty' => '+500 pcs', 'by' => 'Jenwin Docabo', 'note' => 'Restock'],
    ['date' => '2026-07-26 14:20', 'item' => 'Robusta Beans',   'action' => 'remove', 'qty' => '-2 kg',    'by' => 'Lavish Ancero',  'note' => 'Expired / disposed'],
    ['date' => '2026-07-26 10:05', 'item' => 'Oat Milk',        'action' => 'out',    'qty' => '-4 L',     'by' => 'Jenwin Docabo', 'note' => 'PM shift usage'],
];

// ---- Handle "Record Stock Movement" form submission ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $name   = trim($_POST['item'] ?? '');
    $qty    = (float)($_POST['qty'] ?? 0);
    $unit   = trim($_POST['unit'] ?? '');
    $note   = trim($_POST['note'] ?? '');

    if (!verify_token($_POST['token'] ?? '')) {
        header('Location: inventory-clerk.php?view=movement&msg='.urlencode('Form token invalid — please try again.').'&bad=1'); exit;
    }
    if (db_ok()) {
        $item = db_one('SELECT id, name, current_qty FROM items WHERE name=? AND is_active=1 LIMIT 1', [$name]);
        if (!$item) {
            header('Location: inventory-clerk.php?view=movement&msg='.urlencode('Item not found.').'&bad=1'); exit;
        }
        if ($qty <= 0 || !in_array($action, ['in','out','reduce','remove'])) {
            header('Location: inventory-clerk.php?view=movement&msg='.urlencode('Enter a valid quantity.').'&bad=1'); exit;
        }
        // Prevent negative stock on outgoing/reduce/remove
        if ($action !== 'in' && $qty > (float)$item['current_qty']) {
            header('Location: inventory-clerk.php?view=movement&msg='.urlencode('Insufficient stock — only '.$item['current_qty'].' available.').'&bad=1'); exit;
        }
        $ok = tx(function() use ($item,$action,$qty,$unit,$note,$u) {
            inv_record_movement((int)$item['id'], $action, $qty, $unit ?: 'unit', $note, (int)$u['id']);
            audit($u, 'stock.'.$action, ucfirst($action).': '.$item['name'].' x'.$qty.' '.($unit?:'unit').' '.trim($note ? '— '.$note : ''));
            return true;
        });
        header('Location: inventory-clerk.php?view=logs&'.($ok?'saved=1':'msg='.urlencode('Failed to save movement.').'&bad=1')); exit;
    }
}

// ---- Handle: Clerk requests stock from the warehouse ----
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['wh_action']) && $_POST['wh_action']==='clerk_request') {
    if (!verify_token($_POST['token'] ?? '')) { header('Location: inventory-clerk.php?view=request&bad=1&msg='.urlencode('Invalid form token.')); exit; }
    if (db_ok()) {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $qty = (float)($_POST['rqty'] ?? 0);
        $note = trim($_POST['rnote'] ?? '');
        $item = $itemId ? db_one('SELECT id, name, unit FROM items WHERE id=? AND is_active=1', [$itemId]) : null;
        if (!$item || $qty <= 0) { header('Location: inventory-clerk.php?view=request&bad=1&msg='.urlencode('Select an item and enter a quantity.')); exit; }
        $ws = $itemId ? db_one('SELECT qty FROM warehouse_stock WHERE item_id=?', [$itemId]) : null;
        $avail = $ws ? (float)$ws['qty'] : 0;
        $unit = $item['unit'];
        // Prevent requesting more than what's available in the warehouse
        if ($qty > $avail) { header('Location: inventory-clerk.php?view=request&bad=1&msg='.urlencode('Cannot request '.$qty.' '.$unit.' — warehouse only has '.$avail.' '.$unit.' available.')); exit; }
        if ($avail <= 0) { header('Location: inventory-clerk.php?view=request&bad=1&msg='.urlencode('Warehouse has no stock for '.$item['name'].'. Cannot request.')); exit; }
        $max = (int)(db_one("SELECT MAX(CAST(SUBSTRING(ref,5) AS UNSIGNED)) m FROM warehouse_requests")['m'] ?? 0);
        $ref = 'REQ-' . str_pad($max + 1, 4, '0', STR_PAD_LEFT);
        db_exec('INSERT INTO warehouse_requests (ref,item_id,qty,unit,note,requested_by,status) VALUES (?,?,?,?,?,?,?)',
                [$ref,$itemId,$qty,$unit,$note,(int)$u['id'],'pending']);
        audit($u,'warehouse.request','Clerk requested '.$ref.': '.$item['name'].' x'.$qty.' '.$unit.' from warehouse (available '.$avail.' '.$unit.')');
        header('Location: inventory-clerk.php?view=request&saved=1'); exit;
    }
}

// ---- Load CURRENT STOCKS from the database (fallback to demo) ----
$stocks = $demoStocks;
if (db_ok()) {
    $rows = db_all('SELECT i.id, i.code, i.name, c.name AS category, i.unit,
                           i.current_qty AS qty, i.reorder_level AS reorder
                    FROM items i JOIN categories c ON i.category_id = c.id
                    WHERE i.is_active = 1 ORDER BY i.id');
    if ($rows) {
        $stocks = array_map(function ($r) {
            return ['id' => $r['code'], 'name' => $r['name'], 'category' => $r['category'],
                    'unit' => $r['unit'], 'qty' => (float)$r['qty'], 'reorder' => (float)$r['reorder']];
        }, $rows);
    }
}

// ---- Load STOCK LOGS from the database (fallback to demo) ----
$logs = $demoLogs;
$logPage = max(1, (int)($_GET['logpage'] ?? 1));
$perPage = 12;
$totalLogs = 0;
$totalPages = 1;
if (db_ok()) {
    $totalLogs = (int)db_one("SELECT COUNT(*) n FROM stock_movements")['n'];
    $totalPages = max(1, (int)ceil($totalLogs / $perPage));
    $logPage = min($logPage, $totalPages);
    $offset = ($logPage - 1) * $perPage;
    $rows = db_all("SELECT sm.created_at, i.name AS item, sm.action, sm.qty, sm.unit,
                           CONCAT(u.first_name,' ',u.last_name) AS who, sm.note
                    FROM stock_movements sm
                    JOIN items i ON sm.item_id = i.id
                    JOIN users u ON sm.performed_by = u.id
                    ORDER BY sm.created_at DESC LIMIT $perPage OFFSET $offset");
    if ($rows) {
        $logs = array_map(function ($r) {
            $sign = $r['action'] === 'in' ? '+' : '-';
            return ['date' => date('Y-m-d H:i', strtotime($r['created_at'])),
                    'item' => $r['item'], 'action' => $r['action'],
                    'qty' => $sign . $r['qty'] . ' ' . $r['unit'],
                    'by' => $r['who'], 'note' => $r['note']];
        }, $rows);
    }
}

function stockStatus($qty, $reorder) {
    if ($qty <= 0)        return ['Out of stock', 'danger'];
    if ($qty <= $reorder) return ['Low stock', 'warn'];
    return ['In stock', 'ok'];
}
$lowCount = 0; $outCount = 0;
foreach ($stocks as $s) {
    if ($s['qty'] <= $s['reorder']) $lowCount++;
    if ($s['qty'] <= 0) $outCount++;
}
$movesToday = 0;
if (db_ok()) {
    $movesToday = (int)db_one("SELECT COUNT(*) n FROM stock_movements WHERE DATE(created_at)=CURDATE()")['n'];
}

// Warehouse availability for each item + this clerk's own request history
$whAvail = [];
$myRequests = [];
if (db_ok()) {
    $whAvail = db_all("SELECT i.id, i.name, i.unit, COALESCE(ws.qty,0) wqty
                       FROM items i LEFT JOIN warehouse_stock ws ON ws.item_id=i.id
                       WHERE i.is_active=1 ORDER BY i.name");
    $myRequests = db_all("SELECT r.ref, i.name item, r.qty, r.unit, r.status, r.admin_status, r.fulfilled_qty, r.note, DATE(r.created_at) date
                          FROM warehouse_requests r JOIN items i ON i.id=r.item_id
                          WHERE r.requested_by=? ORDER BY r.created_at DESC LIMIT 50", [(int)$u['id']]);
}
function reqStatusCls($s){ return $s==='approved'?'ok':($s==='declined'?'danger':'warn'); }
function reqGateLabel($r){
    $adm = $r['admin_status'] ?? 'approved';
    if ($adm === 'pending')  return ['Waiting for admin approval', 'warn'];
    if ($adm === 'rejected') return ['Returned by admin', 'danger'];
    if ($r['status'] === 'pending')  return ['With warehouse', 'warn'];
    if ($r['status'] === 'approved') return ['Approved', 'ok'];
    return ['Declined by warehouse', 'danger'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="favicon.ico?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Brew &amp; Co. HRMS — Inventory Clerk</title>
<style>
  :root{
    --brown-900:#faf6f0; --brown-800:#5c3a29; --brown-700:#7a5040;
    --brown-500:#a9714a; --accent:#a9714a; --accent-dark:#8f5c39;
    --cream:#faf6f0; --cream-2:#f5ede3; --card:#ffffff;
    --ink:#3b2313; --muted:#7a6055; --line:#e8ddd0;
    --ok:#256b4d; --warn:#96690a; --danger:#a23232;
    --radius:16px; --shadow:0 8px 24px rgba(74,47,34,.08);
  }
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',system-ui,-apple-system,sans-serif;background:var(--cream);color:var(--ink);display:flex;min-height:100vh}
  a{text-decoration:none;color:inherit}
  svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
  :focus-visible{outline:3px solid rgba(169,113,74,.45);outline-offset:2px;border-radius:6px}

  .sidebar{width:250px;background:var(--cream);color:var(--ink);border-right:1px solid var(--line);display:flex;flex-direction:column;padding:22px 16px;position:fixed;top:0;left:0;height:100vh;z-index:60;overflow-y:auto;overflow-x:hidden;scrollbar-width:none}
  
  .sidebar:hover{scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.25) transparent}
  .sidebar::-webkit-scrollbar{width:5px}
  .sidebar:hover::-webkit-scrollbar{background:transparent}
  .sidebar::-webkit-scrollbar-thumb{background:transparent;border-radius:3px;transition:background .2s ease}
  .sidebar:hover::-webkit-scrollbar-thumb{background:rgba(255,255,255,.25)}

  .logo{padding:16px 10px 24px;display:flex;flex-direction:column;align-items:center;gap:4px}
  .logo img{width:56px;height:56px;margin-bottom:4px}
  .logo .brand-name{font-size:20px;color:#e9dccd;font-weight:800;letter-spacing:0.02em;line-height:1.2}
  .logo .brand-tagline{font-size:9px;color:#d8a066;font-weight:700;letter-spacing:0.25em;text-transform:uppercase;margin-top:2px}
  .nav-section-label{font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);padding:14px 14px 6px;opacity:0.7;font-weight:700}
  .nav a{display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:10px;color:var(--ink);font-weight:500;font-size:14px;margin-bottom:2px;cursor:pointer;transition:all .15s ease}
  .nav a:hover{background:rgba(169,113,74,.08);color:var(--accent)}
  .nav a.active{background:var(--accent);color:#fff;font-weight:600}
  .nav-divider{height:1px;background:var(--line);margin:6px 14px}

  .backdrop{display:none}

  .main{flex:1;margin-left:250px;padding:26px 34px;min-width:0}
  .topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;gap:12px}
  .topbar h1{font-size:26px;font-weight:800}
  .topbar .sub{color:var(--muted);font-size:13px;margin-top:2px}
  .hamburger{display:none;background:#fff;border:1px solid var(--line);border-radius:10px;width:44px;height:44px;align-items:center;justify-content:center;cursor:pointer;color:var(--ink)}
  .user-area{display:flex;align-items:center;gap:18px}
  .icon-btn{position:relative;width:44px;height:44px;border-radius:50%;background:#fff;box-shadow:var(--shadow);display:flex;align-items:center;justify-content:center;cursor:pointer;border:none;color:var(--ink)}
  .badge{position:absolute;top:-4px;right:-4px;background:var(--danger);color:#fff;font-size:11px;min-width:19px;height:19px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:700;padding:0 4px}
  .who{display:flex;align-items:center;gap:11px}
  .avatar{width:44px;height:44px;border-radius:50%;background:var(--brown-500);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700}
  .who .n{font-weight:700;font-size:14px}
  .who .r{font-size:12px;color:var(--muted)}

  .view{display:none}
  .view.active{display:block;animation:fade .25s ease}
  @keyframes fade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}

  .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:22px}
  .stat{background:var(--card);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow)}
  .stat .t{font-size:13px;color:var(--muted);margin-bottom:10px}
  .stat .v{font-size:34px;font-weight:800}
  .stat .v.warn{color:var(--warn)} .stat .v.danger{color:var(--danger)}

  .grid{display:grid;grid-template-columns:1.15fr .85fr;gap:18px}
  .panel{background:var(--card);border-radius:var(--radius);padding:22px;box-shadow:var(--shadow);margin-bottom:18px}
  .panel h2{font-size:18px;font-weight:800;margin-bottom:4px}
  .panel .desc{color:var(--muted);font-size:13px;margin-bottom:16px}

  .tools{display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap}
  .search{flex:1;min-width:180px;display:flex;align-items:center;gap:8px;background:var(--cream-2);border:1px solid var(--line);border-radius:10px;padding:9px 12px;color:var(--muted)}
  .search input{border:none;background:transparent;outline:none;width:100%;font-size:14px;color:var(--ink)}
  select.filter{border:1px solid var(--line);background:#fff;border-radius:10px;padding:9px 12px;font-size:14px;color:var(--ink)}
  .table-wrap{overflow-x:auto}
  table{width:100%;border-collapse:collapse;font-size:14px;min-width:420px}
  thead th{text-align:left;font-size:11.5px;letter-spacing:.05em;text-transform:uppercase;color:var(--muted);padding:0 10px 12px}
  tbody td{padding:13px 10px;border-top:1px solid var(--line);vertical-align:middle}
  tbody tr:hover{background:var(--cream-2)}
  .pill{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:700;padding:4px 10px;border-radius:20px}
  .pill.ok{background:#e2f0e8;color:var(--ok)}
  .pill.warn{background:#f8eecf;color:var(--warn)}
  .pill.danger{background:#f6e0e0;color:var(--danger)}
  .tag{font-size:12px;font-weight:700;padding:3px 9px;border-radius:6px}
  .tag.in{background:#e2f0e8;color:var(--ok)} .tag.out{background:#e6e9f5;color:#3f4f9e}
  .tag.reduce,.tag.remove{background:#f6e0e0;color:var(--danger)}
  .empty{padding:34px 20px;text-align:center;color:var(--muted);font-size:14px}
  .empty svg{width:34px;height:34px;stroke:var(--muted);opacity:.6;margin-bottom:10px}
  .empty.hide{display:none}
  .alert{background:#e2f0e8;color:var(--ok);border-radius:10px;padding:12px 14px;font-size:13px;margin-bottom:16px}
  .alert.bad{background:#f6e0e0;color:var(--danger)}

  .field{margin-bottom:14px}
  .field label{display:block;font-size:13px;font-weight:600;margin-bottom:6px}
  .field .req{color:var(--danger)}
  .field input,.field select,.field textarea{width:100%;border:1px solid var(--line);border-radius:10px;padding:11px 12px;font-size:14px;background:#fff;color:var(--ink);outline:none;font-family:inherit}
  .field input:focus,.field select:focus,.field textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(169,113,74,.15)}
  .field .err{display:none;color:var(--danger);font-size:12px;margin-top:5px}
  .field.invalid input,.field.invalid select{border-color:var(--danger);background:#fff8f8}
  .field.invalid .err{display:flex;align-items:center;gap:5px}
  .row2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .btn-primary{width:100%;display:flex;align-items:center;justify-content:center;gap:8px;background:var(--accent);color:#fff;border:none;border-radius:10px;padding:13px;font-size:15px;font-weight:700;cursor:pointer;margin-top:4px}
  .btn-primary:hover{background:var(--accent-dark)}
  .btn-primary:disabled{opacity:.7;cursor:not-allowed}
  .spinner{width:16px;height:16px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:none}
  @keyframes spin{to{transform:rotate(360deg)}}
  .btn-primary.loading .spinner{display:block}
  .seg{display:flex;gap:8px;margin-bottom:16px}
  .seg label{flex:1;position:relative;cursor:pointer}
  .seg input{position:absolute;opacity:0}
  .seg span{display:block;text-align:center;padding:10px;border:1px solid var(--line);border-radius:10px;font-size:13.5px;font-weight:600;color:var(--muted)}
  .seg input:checked + span{background:var(--accent);color:#fff;border-color:var(--accent)}
  .seg input:focus-visible + span{outline:3px solid rgba(169,113,74,.45)}

  .profile-card{display:flex;align-items:center;gap:18px;margin-bottom:20px}
  .profile-card .big{width:72px;height:72px;border-radius:50%;background:var(--brown-500);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:26px}
  .prow{display:flex;justify-content:space-between;padding:13px 0;border-top:1px solid var(--line);font-size:14px}
  .prow:first-of-type{border-top:none}
  .prow .k{color:var(--muted)}

  .toast{position:fixed;top:22px;right:22px;background:var(--ok);color:#fff;padding:14px 18px;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);font-size:14px;font-weight:600;display:flex;align-items:center;gap:10px;transform:translateX(140%);transition:.4s;z-index:120}
  .toast.show{transform:translateX(0)}
  .dropdown{position:absolute;top:52px;right:0;width:300px;background:#fff;border-radius:14px;box-shadow:0 14px 40px rgba(74,47,34,.18);padding:8px;display:none;z-index:50}
  .dropdown.show{display:block}
  .dropdown h4{font-size:13px;color:var(--muted);padding:10px 12px 6px}
  .noti{display:flex;gap:10px;padding:11px 12px;border-radius:10px}
  .noti:hover{background:var(--cream-2)}
  .noti .dot{width:8px;height:8px;border-radius:50%;background:var(--danger);margin-top:6px;flex-shrink:0}
  .noti .txt{font-size:13px} .noti .txt small{color:var(--muted);display:block;margin-top:2px}
  .pos{position:relative}

  .modal-bg{position:fixed;inset:0;background:rgba(40,26,18,.5);display:none;align-items:center;justify-content:center;z-index:130;padding:20px}
  .modal-bg.show{display:flex}
  .modal{background:#fff;border-radius:16px;padding:26px;max-width:400px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.3)}
  .modal .mi{width:48px;height:48px;border-radius:50%;background:#f6e0e0;color:var(--danger);display:flex;align-items:center;justify-content:center;margin-bottom:14px}
  .modal h3{font-size:18px;margin-bottom:8px}
  .modal p{font-size:14px;color:var(--muted);margin-bottom:20px}
  .modal .actions{display:flex;gap:10px}
  .modal button{flex:1;padding:11px;border-radius:10px;font-weight:700;font-size:14px;cursor:pointer;border:1px solid var(--line);background:#fff;color:var(--ink)}
  .modal button.danger{background:var(--danger);color:#fff;border-color:var(--danger)}

  @media(max-width:1050px){.stats{grid-template-columns:repeat(2,1fr)}.grid{grid-template-columns:1fr}}
  @media(max-width:820px){
    .sidebar{position:fixed;left:0;top:0;transform:translateX(-100%);transition:.3s;box-shadow:0 0 40px rgba(0,0,0,.3)}
    .sidebar.open{transform:translateX(0)}
    .backdrop.show{display:block;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:55}
    .hamburger{display:flex}
    .main{margin-left:0;padding:18px}
    .who .n,.who .r{display:none}
  }
</style>

<link rel="stylesheet" href="responsive-ui.css">
<link rel="stylesheet" href="dashboard-navigation.css">
<link rel="stylesheet" href="inventory-ui.css">
<link rel="stylesheet" href="warehouse-operations.css">
</head>
<body>

<div class="backdrop" id="backdrop" onclick="closeSidebar()"></div>

<aside class="sidebar" id="sidebar">
  <a href="#" class="logo" onclick="goHome(event)" title="Go to dashboard" style="text-decoration:none;cursor:pointer;">
    <img src="brewco-logo.svg" alt="Brew & Co.">
    <span class="brand-name">Brew &amp; Co.</span>
    <span class="brand-tagline">Coffee Shop</span>
  </a>
  <nav class="nav" aria-label="Main navigation">
    <div class="nav-section-label">Overview</div>
    <a data-view="dashboard" class="active" aria-current="page"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg> Inventory Dashboard</a>

    <div class="nav-divider"></div>
    <div class="nav-section-label">Actions</div>
    <a data-view="movement"><svg viewBox="0 0 24 24"><path d="M7 10l-4 4 4 4M3 14h14M17 14l4-4-4-4M21 10H7"/></svg> Stock In / Out</a>
    <a data-view="request"><svg viewBox="0 0 24 24"><path d="M4 17h16M4 17l4-4M4 17l4 4M20 7H4M20 7l-4-4M20 7l-4 4"/></svg> Request from Warehouse</a>

    <div class="nav-divider"></div>
    <div class="nav-section-label">Monitor</div>
    <a data-view="stocks"><svg viewBox="0 0 24 24"><path d="M3 3h18v18H3zM3 9h18M3 15h18"/></svg> Current Stocks</a>
    <a data-view="logs"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg> Stock Logs</a>
    <a href="expiry.php"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg> Batch &amp; Expiry</a>

    <div class="nav-divider"></div>
    <div class="nav-section-label">Account</div>
    <a data-view="profile"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></svg> My Profile</a>
    <a href="logout.php"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg> Logout</a>
  </nav>
</aside>

<main class="main">
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:14px">
      <button class="hamburger" onclick="openSidebar()" aria-label="Open menu"><svg viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button>
      <div>
        <h1 id="pageTitle">Inventory Dashboard</h1>
        <div class="sub" id="pageSub">Stock replenishment, movements &amp; records</div>
      </div>
    </div>
    <div class="user-area">
      <?= notification_bell($u['role']) ?>
      <div class="who">
        <div class="avatar"><?= $initials ?></div>
        <div>
          <div class="n"><?= htmlspecialchars($user['firstName'].' '.$user['lastName']) ?></div>
          <div class="r"><?= htmlspecialchars($user['role']) ?></div>
        </div>
      </div>
    </div>
  </div>

  <section class="view active" id="view-dashboard">
    <!-- Stat Cards Row -->
    <div class="stats">
      <div class="stat"><div class="t">Total Items</div><div class="v"><?= count($stocks) ?></div></div>
      <div class="stat"><div class="t">Low Stock</div><div class="v warn"><?= $lowCount ?></div></div>
      <div class="stat"><div class="t">Movements Today</div><div class="v"><?= $movesToday ?></div></div>
      <div class="stat"><div class="t">Out of Stock</div><div class="v danger"><?= $outCount ?></div></div>
    </div>

    <!-- 2-Column Grid: Quick Actions + Needs Attention -->
    <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 20px;">
      <!-- Left: Quick Actions -->
      <div class="panel">
        <h2>Quick Actions</h2>
        <div class="desc">Common tasks to get started</div>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 16px;">
          <button class="btn-primary" style="height: 100px; flex-direction: column; gap: 8px; font-size: 13px;" onclick="showView('movement')">
            <svg viewBox="0 0 24 24" style="width: 28px; height: 28px;"><path d="M7 10l-4 4 4 4M3 14h14M17 14l4-4-4-4M21 10H7"/></svg>
            Record Stock In/Out
          </button>
          <button class="btn-primary" style="height: 100px; flex-direction: column; gap: 8px; font-size: 13px; background: var(--cream-2); color: var(--ink); border: 2px solid var(--line);" onclick="showView('request')">
            <svg viewBox="0 0 24 24" style="width: 28px; height: 28px;"><path d="M4 17h16M4 17l4-4M4 17l4 4M20 7H4M20 7l-4-4M20 7l-4 4"/></svg>
            Request from Warehouse
          </button>
          <button class="btn-primary" style="height: 100px; flex-direction: column; gap: 8px; font-size: 13px; background: var(--cream-2); color: var(--ink); border: 2px solid var(--line);" onclick="showView('logs')">
            <svg viewBox="0 0 24 24" style="width: 28px; height: 28px;"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            View Stock Logs
          </button>
          <button class="btn-primary" style="height: 100px; flex-direction: column; gap: 8px; font-size: 13px; background: var(--cream-2); color: var(--ink); border: 2px solid var(--line);" onclick="showView('stocks')">
            <svg viewBox="0 0 24 24" style="width: 28px; height: 28px;"><path d="M3 3h18v18H3zM3 9h18M3 15h18"/></svg>
            Current Stocks
          </button>
        </div>
      </div>

      <!-- Right: Needs Attention -->
      <div class="panel">
        <h2>⚠ Needs Attention</h2>
        <div class="desc">Items at or below reorder level</div>
        <?php $attention = array_filter($stocks, function($s) { return $s['qty'] <= $s['reorder']; }); ?>
        <?php if ($attention): ?>
        <div style="margin-top: 16px;">
          <?php foreach ($attention as $s): ?>
          <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: var(--cream-2); border-radius: 10px; margin-bottom: 10px; border-left: 4px solid var(--warn);">
            <div>
              <div style="font-weight: 700; font-size: 14px;"><?= htmlspecialchars($s['name']) ?></div>
              <div style="font-size: 12px; color: var(--muted);"><?= $s['qty'] ?> <?= $s['unit'] ?> / <?= $s['reorder'] ?> <?= $s['unit'] ?></div>
            </div>
            <span class="pill warn">Low stock</span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty" style="padding: 40px 20px;">
          <svg viewBox="0 0 24 24" style="width: 48px; height: 48px; stroke: var(--ok); opacity: 0.5; margin-bottom: 12px;"><path d="M20 6L9 17l-5-5"/></svg>
          <div>All items are healthy! 👍</div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Recent Movements (Full Width) -->
    <div class="panel" style="margin-top: 20px;">
      <h2>Recent Movements</h2>
      <div class="desc">Latest stock activity</div>
      <?php $recent = array_slice($logs, 0, 5); ?>
      <?php if ($recent): ?>
      <div class="table-wrap" style="margin-top: 16px;">
        <table class="dt">
          <thead><tr><th>Item</th><th>Action</th><th>Qty</th><th>Date</th><th>By</th></tr></thead>
          <tbody>
            <?php foreach ($recent as $l): ?>
            <tr>
              <td><b><?= htmlspecialchars($l['item']) ?></b></td>
              <td><span class="tag <?= $l['action'] ?>"><?= strtoupper($l['action']) ?></span></td>
              <td><?= htmlspecialchars($l['qty']) ?></td>
              <td><?= htmlspecialchars($l['date']) ?></td>
              <td><?= htmlspecialchars($l['by']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="empty" style="padding: 40px;">No recent movements.</div>
      <?php endif; ?>
    </div>
  </section>

  <section class="view" id="view-movement">
    <div class="grid">
      <div class="panel">
        <h2>Record Stock Movement</h2>
        <div class="desc">Stock in, stock out, or remove.</div>
        <form id="movForm" method="post" action="inventory-clerk.php" novalidate onsubmit="return submitMov(event)"><?= token_field() ?>
          <div class="seg" role="radiogroup" aria-label="Movement type">
            <label><input type="radio" name="action" value="in" checked><span>Stock In</span></label>
            <label><input type="radio" name="action" value="out"><span>Stock Out</span></label>
            <label><input type="radio" name="action" value="remove"><span>Remove</span></label>
          </div>
          <div class="field" id="f-item">
            <label for="i-item">Item <span class="req">*</span></label>
            <select id="i-item" name="item" required>
              <option value="">— Select item —</option>
              <?php foreach ($stocks as $s): ?>
              <option value="<?= htmlspecialchars($s['name']) ?>" data-unit="<?= htmlspecialchars($s['unit']) ?>"><?= htmlspecialchars($s['name']) ?> (<?= $s['qty'].' '.$s['unit'] ?>)</option>
              <?php endforeach; ?>
            </select>
            <div class="err">⚠ Please select an item.</div>
          </div>
          <div class="row2">
            <div class="field" id="f-qty">
              <label for="i-qty">Quantity <span class="req">*</span></label>
              <input id="i-qty" type="number" name="qty" min="1" max="500" step="1" placeholder="e.g. 10">
              <div class="err">⚠ Enter a quantity greater than 0.</div>
            </div>
            <div class="field" id="f-unit">
              <label for="i-unit">Unit <span class="req">*</span></label>
              <input id="i-unit" name="unit" required readonly placeholder="Auto from item" title="Unit comes from the item setup (Manager side)">
              <div class="err">⚠ Select a unit.</div>
            </div>
          </div>
          <div class="field" id="f-date">
            <label for="i-date">Date <span class="req">*</span></label>
            <input id="i-date" type="date" name="date" min="<?= date('Y-m-d') ?>" required>
            <div class="err">⚠ Please choose a date.</div>
          </div>
          <div class="field" id="f-note">
            <label for="i-note">Note / Reason</label>
            <textarea id="i-note" name="note" rows="2" placeholder="e.g. Delivery received, wastage, expired..."></textarea>
          </div>
          <button type="submit" class="btn-primary" id="movBtn"><span class="spinner"></span><span class="btxt">Save Movement</span></button>
        </form>
      </div>
      <div class="panel">
        <h2>Quick Tips</h2>
        <div class="desc">Keep records accurate.</div>
        <ul style="font-size:13.5px;color:var(--muted);line-height:1.9;padding-left:18px">
          <li><b>Stock In</b> — new deliveries received.</li>
          <li><b>Stock Out</b> — used during shifts.</li>
          <li><b>Remove</b> — expired/spoiled (asks for confirmation).</li>
        </ul>
      </div>
    </div>
  </section>

  <section class="view" id="view-stocks">
    <div class="panel">
      <h2>Current Stocks</h2>
      <div class="desc">Live quantity of ingredients &amp; supplies.</div>
      <div class="tools">
        <div class="search"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg><input type="text" id="stockSearch" placeholder="Search item or category..." aria-label="Search stocks" onkeyup="filterStocks()"></div>
        <select class="filter" id="stockCat" aria-label="Filter by category" onchange="filterStocks()">
          <option value="">All categories</option><option>Coffee</option><option>Dairy</option><option>Syrup</option><option>Supplies</option>
        </select>
      </div>
      <div class="table-wrap">
        <table id="stockTbl">
          <thead><tr><th>Item</th><th>Category</th><th>Qty</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($stocks as $s): [$label,$cls] = stockStatus($s['qty'],$s['reorder']); ?>
            <tr>
              <td><b><?= htmlspecialchars($s['name']) ?></b><br><small style="color:var(--muted)"><?= $s['id'] ?></small></td>
              <td><?= htmlspecialchars($s['category']) ?></td>
              <td><?= $s['qty'] ?> <?= $s['unit'] ?></td>
              <td><span class="pill <?= $cls ?>"><?= $label ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="empty hide" id="stockEmpty"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg><div>No items match your search.</div></div>
    </div>
  </section>

  <section class="view" id="view-logs">
    <div class="panel">
      <h2>Stock Logs</h2>
      <div class="desc">Every stock-in, stock-out, reduction &amp; removal — with who &amp; when.</div>
      <div class="tools">
        <div class="search"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg><input type="text" id="logSearch" placeholder="Search logs..." aria-label="Search logs" onkeyup="filterLog()"></div>
        <select class="filter" id="logAction" aria-label="Filter by action" onchange="filterLog()">
          <option value="">All actions</option><option value="in">Stock In</option><option value="out">Stock Out</option><option value="reduce">Reduced</option><option value="remove">Removed</option><option value="adjust">Adjusted</option>
        </select>
      </div>
      <div class="table-wrap">
        <table id="logTbl">
          <thead><tr><th>Date</th><th>Item</th><th>Action</th><th>Qty</th><th>By</th><th>Note</th></tr></thead>
          <tbody>
            <?php foreach ($logs as $l): ?>
            <tr data-action="<?= $l['action'] ?>">
              <td><?= htmlspecialchars($l['date']) ?></td>
              <td><?= htmlspecialchars($l['item']) ?></td>
              <td><span class="tag <?= $l['action'] ?>"><?= strtoupper($l['action']) ?></span></td>
              <td><?= htmlspecialchars($l['qty']) ?></td>
              <td><?= htmlspecialchars($l['by']) ?></td>
              <td style="color:var(--muted)"><?= htmlspecialchars($l['note']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="empty hide" id="logEmpty"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><div>No logs match your filters.</div></div>
      <?php if ($totalPages > 1): ?>
      <div class="pager" style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;flex-wrap:wrap;gap:10px">
        <span style="font-size:13px;color:var(--muted)">Showing <?= (($logPage-1)*$perPage)+1 ?>–<?= min($logPage*$perPage, $totalLogs) ?> of <?= $totalLogs ?> records</span>
        <div style="display:flex;gap:8px">
          <?php if ($logPage > 1): ?><a class="btn-primary" style="width:auto;padding:8px 14px;text-decoration:none" href="?view=logs&logpage=<?= $logPage-1 ?>#view-logs">← Prev</a><?php endif; ?>
          <span style="padding:8px 14px;border:1px solid var(--line);border-radius:10px;font-size:13px;font-weight:700;background:#fff">Page <?= $logPage ?> / <?= $totalPages ?></span>
          <?php if ($logPage < $totalPages): ?><a class="btn-primary" style="width:auto;padding:8px 14px;text-decoration:none" href="?view=logs&logpage=<?= $logPage+1 ?>#view-logs">Next →</a><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="view" id="view-request">
    <div class="grid">
      <div class="panel">
        <h2>Request Stock from Warehouse</h2>
        <div class="desc">Running low? Ask for how many units you need. The Manager will approve it and it will be moved from the warehouse to the cafe.</div>
        <?php if (isset($_GET['msg'])): ?><div class="alert <?= isset($_GET['bad'])?'bad':'' ?>" style="padding:11px 13px;border-radius:10px;font-size:13px;margin-bottom:14px"><?= htmlspecialchars($_GET['msg']) ?></div><?php endif; ?>
        <form method="post" action="inventory-clerk.php" onsubmit="return validateWhRequest(event)"><?= token_field() ?>
          <input type="hidden" name="wh_action" value="clerk_request">
          <div class="field"><label>Item <span class="req">*</span></label>
            <select name="item_id" id="rItem" required onchange="updateWhHint()"><option value="">— Select item —</option>
              <?php foreach($whAvail as $w): ?>
              <option value="<?= (int)$w['id'] ?>" data-qty="<?= (float)$w['wqty'] ?>" data-unit="<?= htmlspecialchars($w['unit']) ?>"><?= htmlspecialchars($w['name']) ?> (<?= $w['wqty'] ?> <?= htmlspecialchars($w['unit']) ?> in warehouse)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div id="whHint" style="display:none;background:var(--cream-2);border:1px solid var(--line);border-radius:8px;padding:9px 11px;font-size:13px;color:var(--muted);margin:-6px 0 14px"></div>
          <div class="field"><label>Quantity needed <span class="req">*</span></label><input name="rqty" type="number" min="1" step="any" required placeholder="e.g. 20"></div>
          <div class="field"><label>Note / Reason</label><textarea name="rnote" rows="2" placeholder="e.g. Running low, need more for the week"></textarea></div>
          <button class="btn-primary" type="submit">+ Send Request to Manager</button>
        </form>
      </div>
      <div class="panel">
        <h2>My Requests</h2>
        <div class="desc">Status of stock you asked for from the warehouse.</div>
        <?php if ($myRequests): ?>
        <div class="table-wrap"><table class="dt">
          <thead><tr><th>Ref</th><th>Item</th><th>Qty</th><th>Status</th><th>Date</th></tr></thead>
          <tbody><?php foreach($myRequests as $mr): ?>
            <tr>
              <td><b><?= $mr['ref'] ?></b></td>
              <td><?= htmlspecialchars($mr['item']) ?></td>
              <td><?= $mr['qty'] ?> <?= htmlspecialchars($mr['unit']) ?>
                <?php if($mr['status']==='approved' && (float)$mr['fulfilled_qty'] < (float)$mr['qty']): ?>
                  <div style="font-size:11px;color:var(--warn);font-weight:700">only <?= $mr['fulfilled_qty'] ?> <?= htmlspecialchars($mr['unit']) ?> moved</div>
                <?php elseif($mr['status']==='approved'): ?>
                  <div style="font-size:11px;color:var(--ok)"><?= $mr['fulfilled_qty'] ?> <?= htmlspecialchars($mr['unit']) ?> moved</div>
                <?php endif; ?>
              </td>
              <?php list($gl,$gc) = reqGateLabel($mr); ?>
              <td><span class="pill <?= $gc ?>"><?= $gl ?></span></td>
              <td><?= $mr['date'] ?></td>
            </tr>
          <?php endforeach; ?></tbody>
        </table></div>
        <?php else: ?><div class="empty">You haven't made any warehouse requests yet.</div><?php endif; ?>
      </div>
    </div>
  </section>

  <section class="view" id="view-profile">
    <div class="panel">
      <div class="profile-card">
        <div class="big"><?= $initials ?></div>
        <div>
          <h2><?= htmlspecialchars($user['firstName'].' '.$user['lastName']) ?></h2>
          <div class="desc" style="margin:0"><?= htmlspecialchars($user['role']) ?></div>
        </div>
      </div>
      <div class="prow"><span class="k">First name</span><span><?= htmlspecialchars($user['firstName']) ?></span></div>
      <div class="prow"><span class="k">Last name</span><span><?= htmlspecialchars($user['lastName']) ?></span></div>
      <div class="prow"><span class="k">Email</span><span><?= htmlspecialchars($u['email'] ?? '—') ?></span></div>
      <div class="prow"><span class="k">Role</span><span><?= htmlspecialchars($user['role']) ?></span></div>
    </div>
    <?= inv_signature_panel($u, 'inventory-clerk.php') ?>
    <?= change_password_panel('inventory-clerk.php') ?>
  </section>
</main>

<div class="toast" id="toast" role="status" aria-live="polite"><svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg> <span id="toastMsg">Saved!</span></div>

<div class="modal-bg" id="modalBg">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="mi"><svg viewBox="0 0 24 24"><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg></div>
    <h3 id="modalTitle">Confirm removal</h3>
    <p id="modalMsg">Are you sure you want to remove this stock? This action will be logged.</p>
    <div class="actions">
      <button onclick="closeModal()">Cancel</button>
      <button class="danger" id="modalOk">Yes, remove</button>
    </div>
  </div>
</div>

<script>
const VIEW_META = {
  dashboard:{title:'Inventory Dashboard', sub:'Stock replenishment, movements &amp; records'},
  movement: {title:'Stock In / Out',      sub:'Record a new stock movement'},
  stocks:   {title:'Current Stocks',      sub:'Live ingredient & supply levels'},
  logs:     {title:'Stock Logs',          sub:'History of every stock movement'},
  request:  {title:'Request from Warehouse', sub:'Ask the Manager to restock you'},
  profile:  {title:'My Profile',          sub:'Your account details'}
};
function updateWhHint(){
  const sel=document.getElementById('rItem');
  const hint=document.getElementById('whHint');
  const opt=sel.options[sel.selectedIndex];
  if(!opt || !opt.value){ hint.style.display='none'; return; }
  hint.style.display='block';
  hint.textContent='Available in warehouse: '+opt.dataset.qty+' '+opt.dataset.unit+'.';
}
function validateWhRequest(e){
  const sel=document.getElementById('rItem');
  const qtyInput=document.querySelector('[name="rqty"]');
  const opt=sel.options[sel.selectedIndex];
  if(!opt || !opt.value){ swalAlert('Please select an item.','error'); return false; }
  const avail=parseFloat(opt.dataset.qty);
  const qty=parseFloat(qtyInput.value);
  if(!qty || qty<=0){ swalAlert('Enter a valid quantity.','error'); return false; }
  if(qty > avail){
    swalAlert('Cannot request '+qty+' '+opt.dataset.unit+' — warehouse only has '+avail+' '+opt.dataset.unit+' available.','error');
    return false;
  }
  return true;
}
function showView(name){
  document.querySelectorAll('.view').forEach(v=>v.classList.remove('active'));
  const el=document.getElementById('view-'+name); if(el)el.classList.add('active');
  document.querySelectorAll('.nav a[data-view]').forEach(a=>{
    a.classList.toggle('active', a.dataset.view===name);
    if(a.dataset.view===name) a.setAttribute('aria-current','page'); else a.removeAttribute('aria-current');
  });
  const m=VIEW_META[name]; if(m){document.getElementById('pageTitle').textContent=m.title;document.getElementById('pageSub').innerHTML=m.sub;}
  location.hash=name;
  closeSidebar();
  window.scrollTo({top:0,behavior:'smooth'});
}
document.querySelectorAll('.nav a[data-view]').forEach(a=>{
  a.addEventListener('click',e=>{e.preventDefault();showView(a.dataset.view);});
});
window.addEventListener('DOMContentLoaded',()=>{
  const h=location.hash.replace('#','');
  const urlParams = new URLSearchParams(location.search);
  const viewFromQuery = urlParams.get('view');
  const viewToShow = h || viewFromQuery || 'dashboard';
  
  if(viewToShow && VIEW_META[viewToShow]) showView(viewToShow);
  
  if(location.search.includes('saved=1')) toast('Changes saved!');
  if(location.search.includes('bad=1')) toast('Something went wrong. Please try again.', true);
});

function goHome(e){ if(e) e.preventDefault(); showView('dashboard'); }
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('backdrop').classList.add('show');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('backdrop').classList.remove('show');}
function toggleNoti(e){e.stopPropagation();document.getElementById('notiBox').classList.toggle('show');}
document.addEventListener('click',()=>document.getElementById('notiBox').classList.remove('show'));
function toast(msg,bad){const t=document.getElementById('toast');t.style.background=bad?'#a23232':'#256b4d';document.getElementById('toastMsg').textContent=msg;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),2800);}

function filterStocks(){
  const q=document.getElementById('stockSearch').value.toLowerCase();
  const c=document.getElementById('stockCat').value.toLowerCase();
  let shown=0;
  document.querySelectorAll('#stockTbl tbody tr').forEach(r=>{
    const cat=r.children[1].textContent.toLowerCase();
    const match=r.textContent.toLowerCase().includes(q) && (!c||cat===c);
    r.style.display=match?'':'none'; if(match)shown++;
  });
  document.getElementById('stockEmpty').classList.toggle('hide',shown>0);
}
function filterLog(){
  const q=document.getElementById('logSearch').value.toLowerCase();
  const a=document.getElementById('logAction').value;
  let shown=0;
  document.querySelectorAll('#logTbl tbody tr').forEach(r=>{
    const match=r.textContent.toLowerCase().includes(q) && (!a||r.dataset.action===a);
    r.style.display=match?'':'none'; if(match)shown++;
  });
  document.getElementById('logEmpty').classList.toggle('hide',shown>0);
}

let modalCallback=null;
function askConfirm(msg,cb){document.getElementById('modalMsg').textContent=msg;modalCallback=cb;document.getElementById('modalBg').classList.add('show');}
function closeModal(){document.getElementById('modalBg').classList.remove('show');modalCallback=null;}
document.getElementById('modalOk').onclick=()=>{if(modalCallback)modalCallback();closeModal();};
document.getElementById('modalBg').addEventListener('click',e=>{if(e.target.id==='modalBg')closeModal();});
document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeModal();closeSidebar();}});

function setErr(id,bad){document.getElementById(id).classList.toggle('invalid',bad);}
/* Unit follows whatever the Manager typed when the item was created */
document.getElementById('i-item').addEventListener('change', function(){
  const opt = this.selectedOptions[0];
  const u = (opt && opt.dataset.unit) ? opt.dataset.unit : '';
  const ui = document.getElementById('i-unit');
  ui.value = u; ui.readOnly = !!u;
  ui.placeholder = u ? '' : 'Item has no unit — type one (kg, L, pcs…)';
});
function submitMov(e){
  e.preventDefault();
  const f=e.target; let ok=true;
  const item=f.item.value.trim(); setErr('f-item',!item); if(!item) ok=false;
  const qty=parseInt(f.qty.value,10); const badQty=!qty||qty<1; setErr('f-qty',badQty); if(badQty) ok=false;
  const unit=f.unit.value; setErr('f-unit',!unit); if(!unit) ok=false;
  const date=f.date.value; setErr('f-date',!date); if(!date) ok=false;
  if(!ok){toast('Please fix the highlighted fields.',true);return false;}
  // Really submit so the server validates, prevents negative stock & saves
  const doSave=()=>{ f.submit(); };
  if(f.action.value==='remove'){ askConfirm('Remove '+qty+' '+unit+' of '+item+'? This will be logged.',doSave); }
  else{ doSave(); }
  return false;
}
document.querySelector('input[name=date]').valueAsDate=new Date();
</script>
<script src="input-guard.js"></script>
<script src="datatable.js"></script>
<script src="alerts.js"></script>
<script src="signature-pad.js"></script>
<script src="logout-confirm.js"></script>
<script>window.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('.sidebar nav').forEach(function(n){n.scrollTop=0;});});</script>
<script src="ux-improvements.js"></script>

<script src="responsive-ui.js" defer></script>
<script src="dashboard-navigation.js" defer></script>
<script src="inventory-ui.js" defer></script>
<script src="warehouse-operations.js" defer></script>
</body>
</html>