<?php
require __DIR__ . '/db.php';
define('PAGE_ROLE', 'manager'); // warehouse module is run by the manager (and superadmin)

// Role guard: manager or superadmin.
if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], [PAGE_ROLE, 'superadmin'])) {
    if (db_ok()) { header('Location: login.php'); exit; }
    $_SESSION['user'] = ['id'=>2,'firstName'=>'Jayr','lastName'=>'Fabon','email'=>'jayr@brewco.ph','role'=>'manager'];
}
$u = $_SESSION['user'];
$user = ['firstName'=>$u['firstName'],'lastName'=>$u['lastName'],'role'=>'Warehouse'];
inv_ensure_purchase_flow();
$initials = strtoupper(substr($u['firstName'],0,1).substr($u['lastName'],0,1));

// ---- Handle: Receive a delivered shipment into the warehouse ----
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['wh_action']) && $_POST['wh_action']==='receive_shipment') {
    if (!verify_token($_POST['token'] ?? '')) { header('Location: warehouse.php?view=receive&bad=1&msg='.urlencode('Invalid form token.')); exit; }
    if (db_ok()) {
        $shipId = (int)($_POST['ship_id'] ?? 0);
        $ship = $shipId ? db_one('SELECT id, ref, item_id, item_desc, qty, received_qty, seller_id, status FROM shipments WHERE id=? AND status="Delivered"', [$shipId]) : null;
        if (!$ship) { header('Location: warehouse.php?view=receive&bad=1&msg='.urlencode('Invalid or already received shipment.')); exit; }
        
        $ok = tx(function() use ($ship, $u) {
            // Generate WHR reference
            $max = (int)(db_one("SELECT MAX(CAST(SUBSTRING(ref,5) AS UNSIGNED)) m FROM warehouse_receipts")['m'] ?? 0);
            $ref = 'WHR-' . str_pad($max + 1, 4, '0', STR_PAD_LEFT);
            
            // Create warehouse receipt
            db_exec('INSERT INTO warehouse_receipts (ref,item_id,qty,unit,seller_id,note,received_by) VALUES (?,?,?,?,?,?,?)',
                    [$ref, $ship['item_id'], $ship['qty'], '', $ship['seller_id'] ?: null, 'From shipment '.$ship['ref'], (int)$u['id']]);
            
            // Upsert warehouse stock
            $ws = db_one('SELECT id FROM warehouse_stock WHERE item_id=?', [$ship['item_id']]);
            if ($ws) db_exec('UPDATE warehouse_stock SET qty = qty + ? WHERE id=?', [$ship['qty'], $ws['id']]);
            else     db_exec('INSERT INTO warehouse_stock (item_id,qty) VALUES (?,?)', [$ship['item_id'], $ship['qty']]);
            
            // Mark shipment as Received
            db_exec('UPDATE shipments SET status="Received", received_qty=? WHERE id=?', [$ship['qty'], $ship['id']]);
            
            audit($u, 'warehouse.receive', 'Received '.$ref.' from shipment '.$ship['ref'].': '.$ship['item_desc']);
            return true;
        });
        
        header('Location: warehouse.php?view=receive&'.($ok?'saved=1&msg='.urlencode('Received! Ref: '.$ref):'bad=1&msg='.urlencode('Failed to receive.'))); exit;
    }
}

// ---- Handle: Manager approves/declines a clerk's stock request ----
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['wh_action']) && $_POST['wh_action']==='decide_request') {
    if (!verify_token($_POST['token'] ?? '')) { header('Location: warehouse.php?view=requests&bad=1&msg='.urlencode('Invalid form token.')); exit; }
    if (db_ok()) {
        $id = (int)($_POST['id'] ?? 0);
        $decision = $_POST['decision'] ?? ''; // approve | decline
        $req = $id ? db_one("SELECT r.id, r.ref, r.item_id, r.qty, r.unit, r.note,
                                    i.name item, i.unit iunit
                             FROM warehouse_requests r JOIN items i ON i.id=r.item_id
                             WHERE r.id=? AND r.status='pending' AND r.admin_status='approved'", [$id]) : null;
        if (!$req || !in_array($decision, ['approve','decline'])) {
            header('Location: warehouse.php?view=requests&bad=1&msg='.urlencode('Invalid request.')); exit;
        }
        if ($decision === 'approve') {
            $ws = db_one('SELECT id, qty FROM warehouse_stock WHERE item_id=?', [$req['item_id']]);
            $avail = $ws ? (float)$ws['qty'] : 0;
            // Partial fulfillment: you can only move out what's actually in the warehouse.
            $fulfill = min((float)$req['qty'], $avail);
            if ($fulfill <= 0) {
                header('Location: warehouse.php?view=requests&bad=1&msg='.urlencode('Out of stock in warehouse — nothing to fulfill. Declined.')); exit;
            }
            $partial = $fulfill < (float)$req['qty'];
            $ok = tx(function() use ($req,$ws,$fulfill,$partial,$u) {
                $unit = $req['iunit'];
                // deduct warehouse by the fulfilled amount
                db_exec('UPDATE warehouse_stock SET qty = qty - ? WHERE id=?', [$fulfill, $ws['id']]);
                // add the fulfilled amount to cafe stock (logged as stock-in movement)
                inv_record_movement((int)$req['item_id'], 'in', $fulfill, $unit, 'Approved request '.$req['ref'].($partial ? ' (partial — warehouse only had '.$fulfill.')' : '').' (from warehouse)', (int)$u['id']);
                // log a warehouse transfer for the fulfilled amount
                $max = (int)(db_one("SELECT MAX(CAST(SUBSTRING(ref,5) AS UNSIGNED)) m FROM warehouse_transfers")['m'] ?? 0);
                $wht = 'WHT-' . str_pad($max + 1, 4, '0', STR_PAD_LEFT);
                db_exec('INSERT INTO warehouse_transfers (ref,item_id,qty,unit,note,transferred_by) VALUES (?,?,?,?,?,?)',
                        [$wht, $req['item_id'], $fulfill, $unit, 'Fulfilled '.$req['ref'].($partial ? ' (partial)':''), (int)$u['id']]);
                // mark request approved with the fulfilled qty
                db_exec('UPDATE warehouse_requests SET status="approved", fulfilled_qty=?, decided_by=?, decided_at=NOW() WHERE id=?',
                        [$fulfill, (int)$u['id'], $req['id']]);
                audit($u,'warehouse.approve_request','Approved '.$req['ref'].': '.$req['item'].' x'.$fulfill.' '.$unit.' -> cafe'.($partial ? ' (partial: requested '.$req['qty'].')' : ''));
                return true;
            });
            header('Location: warehouse.php?view=requests&'.($ok?'saved=1':'bad=1&msg='.urlencode('Failed to approve.'))); exit;
        } else {
            db_exec('UPDATE warehouse_requests SET status="declined", decided_by=?, decided_at=NOW() WHERE id=?', [(int)$u['id'], $id]);
            audit($u,'warehouse.decline_request','Declined '.$req['ref'].': '.$req['item'].' x'.$req['qty'].' '.$req['iunit']);
            header('Location: warehouse.php?view=requests&saved=1'); exit;
        }
    }
}

// ---- Handle: Transfer warehouse -> cafe (storefront) ----
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['wh_action']) && $_POST['wh_action']==='transfer') {
    if (!verify_token($_POST['token'] ?? '')) { header('Location: warehouse.php?view=transfer&bad=1&msg='.urlencode('Invalid form token.')); exit; }
    if (db_ok()) {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $qty    = (float)($_POST['tqty'] ?? 0);
        $note   = trim($_POST['tnote'] ?? '');
        $item = $itemId ? db_one('SELECT id, name, unit FROM items WHERE id=? AND is_active=1', [$itemId]) : null;
        $ws   = $itemId ? db_one('SELECT id, qty FROM warehouse_stock WHERE item_id=?', [$itemId]) : null;
        if (!$item || $qty <= 0) { header('Location: warehouse.php?view=transfer&bad=1&msg='.urlencode('Select an item and enter a quantity.')); exit; }
        if (!$ws || (float)$ws['qty'] < $qty) { header('Location: warehouse.php?view=transfer&bad=1&msg='.urlencode('Not enough stock in the warehouse.')); exit; }
        $unit = $item['unit'];
        $ok = tx(function() use ($itemId,$qty,$unit,$note,$item,$ws,$u) {
            $max = (int)(db_one("SELECT MAX(CAST(SUBSTRING(ref,5) AS UNSIGNED)) m FROM warehouse_transfers")['m'] ?? 0);
            $ref = 'WHT-' . str_pad($max + 1, 4, '0', STR_PAD_LEFT);
            // decrement warehouse
            db_exec('UPDATE warehouse_stock SET qty = qty - ? WHERE id=?', [$qty,$ws['id']]);
            // add to cafe stock (records a movement + updates items.current_qty)
            inv_record_movement($itemId, 'in', $qty, $unit, 'Transferred from warehouse '.$ref.($note ? ' — '.$note : ''), (int)$u['id']);
            db_exec('INSERT INTO warehouse_transfers (ref,item_id,qty,unit,note,transferred_by) VALUES (?,?,?,?,?,?)',
                    [$ref,$itemId,$qty,$unit,$note,(int)$u['id']]);
            audit($u,'warehouse.transfer','Transferred '.$ref.': '.$item['name'].' x'.$qty.' '.$unit.' warehouse -> cafe');
            return true;
        });
        header('Location: warehouse.php?view=transfer&'.($ok?'saved=1':'bad=1&msg='.urlencode('Failed to save.'))); exit;
    }
}

// ---- Load data ----
$stock = [];
if (db_ok()) {
    $stock = db_all("SELECT i.id, i.code, i.name, i.unit, i.reorder_level, i.cost, i.current_qty,
                            COALESCE(ws.qty,0) wqty, COALESCE(ws.id,0) ws_id
                     FROM items i
                     LEFT JOIN warehouse_stock ws ON ws.item_id = i.id
                     WHERE i.is_active = 1
                     ORDER BY i.name");
}
$whItems = [];
if (db_ok()) {
    $whItems = db_all("SELECT i.id, i.name, i.unit, ws.qty
                       FROM warehouse_stock ws JOIN items i ON i.id = ws.item_id
                       WHERE ws.qty > 0 ORDER BY i.name");
}
$receipts = [];
if (db_ok()) {
    $receipts = db_all("SELECT r.ref, i.name item, r.qty, r.unit, COALESCE(s.name,'—') seller,
                               r.note, DATE(r.created_at) date, CONCAT(u.first_name,' ',u.last_name) who
                        FROM warehouse_receipts r
                        JOIN items i ON i.id=r.item_id
                        LEFT JOIN sellers s ON s.id=r.seller_id
                        JOIN users u ON u.id=r.received_by
                        ORDER BY r.created_at DESC LIMIT 100");
}
$transfers = [];
if (db_ok()) {
    $transfers = db_all("SELECT t.ref, i.name item, t.qty, t.unit, t.note, DATE(t.created_at) date,
                                CONCAT(u.first_name,' ',u.last_name) who
                         FROM warehouse_transfers t
                         JOIN items i ON i.id=t.item_id
                         JOIN users u ON u.id=t.transferred_by
                         ORDER BY t.created_at DESC LIMIT 100");
}
$sellers = db_ok() ? db_all("SELECT id,name FROM sellers WHERE is_active=1 ORDER BY name") : [];

// Delivered shipments waiting to be received into warehouse
$pendingReceive = [];
if (db_ok()) {
    $pendingReceive = db_all("SELECT s.id, s.ref, s.item_desc, s.qty, s.eta,\n                                     COALESCE(seller.name,'—') seller\n                              FROM shipments s\n                              LEFT JOIN sellers seller ON seller.id=s.seller_id\n                              WHERE s.status='Delivered'\n                              ORDER BY s.created_at DESC");
}

// Clerk stock requests (pending + history)
$whRequests = [];
if (db_ok()) {
    $whRequests = db_all("SELECT r.id, r.ref, i.name item, r.qty, r.unit, r.note, r.status, r.fulfilled_qty,
                                 r.created_at, CONCAT(cl.first_name,' ',cl.last_name) clerk,
                                 DATE(r.created_at) date
                          FROM warehouse_requests r
                          JOIN items i ON i.id=r.item_id
                          JOIN users cl ON cl.id=r.requested_by
                          WHERE r.admin_status='approved'
                          ORDER BY FIELD(r.status,'pending','approved','declined'), r.created_at DESC");
}
$pendingReqs = 0; foreach ($whRequests as $r) if ($r['status']==='pending') $pendingReqs++;

$totalWhQty = 0; $whItemCount = 0; $lowWh = 0;
foreach ($stock as $s) { $totalWhQty += $s['wqty']; if ($s['wqty']>0) $whItemCount++; if ($s['wqty'] <= $s['reorder_level']) $lowWh++; }
$recThisMonth = db_ok() ? (int)db_one("SELECT COUNT(*) n FROM warehouse_receipts WHERE DATE_FORMAT(created_at,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')")['n'] : 0;
$trfThisMonth = db_ok() ? (int)db_one("SELECT COUNT(*) n FROM warehouse_transfers WHERE DATE_FORMAT(created_at,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')")['n'] : 0;

function whStatus($qty,$reorder){ if($qty<=0)return ['Empty','danger']; if($qty<=$reorder)return ['Low','warn']; return ['Stocked','ok']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="favicon.ico?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Brew &amp; Co. — Warehouse</title>
<style>
  :root{--brown-900:#faf6f0;--brown-800:#5c3a29;--brown-700:#7a5040;--brown-500:#a9714a;--accent:#a9714a;--accent-dark:#8f5c39;--cream:#faf6f0;--cream-2:#f5ede3;--card:#fff;--ink:#3b2313;--muted:#7a6055;--line:#e8ddd0;--ok:#256b4d;--warn:#96690a;--danger:#a23232;--radius:16px;--shadow:0 8px 24px rgba(74,47,34,.08);}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--cream);color:var(--ink);display:flex;min-height:100vh}
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
  .nav a{display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:10px;color:var(--ink);font-weight:500;font-size:14px;margin-bottom:2px;cursor:pointer;transition:all .15s ease}
  .nav a:hover{background:rgba(169,113,74,.08);color:var(--accent)}.nav a.active{background:var(--accent);color:#fff;font-weight:600}
  .nav-section-label{font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);padding:14px 14px 6px;opacity:0.7;font-weight:700}
  .nav-divider{height:1px;background:var(--line);margin:6px 14px}
  .backdrop{display:none}
  .main{flex:1;margin-left:250px;padding:26px 34px;min-width:0}
  .topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;gap:12px}
  .topbar h1{font-size:26px;font-weight:800}.topbar .sub{color:var(--muted);font-size:13px;margin-top:2px}
  .hamburger{display:none;background:#fff;border:1px solid var(--line);border-radius:10px;width:44px;height:44px;align-items:center;justify-content:center;cursor:pointer;color:var(--ink)}
  .user-area{display:flex;align-items:center;gap:18px}
  .icon-btn{position:relative;width:44px;height:44px;border-radius:50%;background:#fff;box-shadow:var(--shadow);display:flex;align-items:center;justify-content:center;cursor:pointer;border:none;color:var(--ink)}
  .badge{position:absolute;top:-4px;right:-4px;background:var(--danger);color:#fff;font-size:11px;min-width:19px;height:19px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:700;padding:0 4px}
  .who{display:flex;align-items:center;gap:11px}.avatar{width:44px;height:44px;border-radius:50%;background:var(--brown-500);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700}
  .who .n{font-weight:700;font-size:14px}.who .r{font-size:12px;color:var(--muted)}
  .view{display:none}.view.active{display:block;animation:fade .25s ease}
  @keyframes fade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
  .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:22px}
  .stat{background:var(--card);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow)}
  .stat .t{font-size:13px;color:var(--muted);margin-bottom:10px}.stat .v{font-size:32px;font-weight:800}.stat .v.warn{color:var(--warn)}.stat .v.danger{color:var(--danger)}.stat .sm{font-size:12px;color:var(--muted);margin-top:6px}
  .grid{display:grid;grid-template-columns:1.15fr .85fr;gap:18px}
  .panel{background:var(--card);border-radius:var(--radius);padding:22px;box-shadow:var(--shadow);margin-bottom:18px}
  .panel h2{font-size:18px;font-weight:800;margin-bottom:4px}.panel .desc{color:var(--muted);font-size:13px;margin-bottom:16px}
  .tools{display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap}
  .search{flex:1;min-width:170px;display:flex;align-items:center;gap:8px;background:var(--cream-2);border:1px solid var(--line);border-radius:10px;padding:9px 12px;color:var(--muted)}
  .search input{border:none;background:transparent;outline:none;width:100%;font-size:14px;color:var(--ink)}
  .table-wrap{overflow-x:auto}
  table{width:100%;border-collapse:collapse;font-size:14px;min-width:480px}
  thead th{text-align:left;font-size:11.5px;letter-spacing:.05em;text-transform:uppercase;color:var(--muted);padding:0 10px 12px}
  tbody td{padding:12px 10px;border-top:1px solid var(--line);vertical-align:middle}
  tbody tr:hover{background:var(--cream-2)}
  .pill{display:inline-flex;font-size:12px;font-weight:700;padding:4px 10px;border-radius:20px;text-transform:capitalize}
  .pill.ok{background:#e2f0e8;color:var(--ok)}.pill.warn{background:#f8eecf;color:var(--warn)}.pill.danger{background:#f6e0e0;color:var(--danger)}
  .field{margin-bottom:14px}.field label{display:block;font-size:13px;font-weight:600;margin-bottom:6px}.field .req{color:var(--danger)}
  .field input,.field select,.field textarea{width:100%;border:1px solid var(--line);border-radius:10px;padding:11px 12px;font-size:14px;background:#fff;color:var(--ink);outline:none;font-family:inherit}
  .field input:focus,.field select:focus,.field textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(169,113,74,.15)}
  .row2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .btn-primary{width:100%;display:flex;align-items:center;justify-content:center;gap:8px;background:var(--accent);color:#fff;border:none;border-radius:10px;padding:13px;font-size:15px;font-weight:700;cursor:pointer}
  .btn-primary:hover{background:var(--accent-dark)}
  .btn-sm{border:1px solid var(--line);background:#fff;border-radius:8px;padding:6px 10px;font-size:12px;font-weight:700;cursor:pointer;color:var(--ink)}
  .btn-sm.ok{color:#fff;background:var(--ok);border-color:var(--ok)}
  .empty{padding:30px 20px;text-align:center;color:var(--muted);font-size:14px}
  .empty svg{width:32px;height:32px;opacity:.6;margin-bottom:8px}
  .toast{position:fixed;top:22px;right:22px;background:var(--ok);color:#fff;padding:14px 18px;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);font-size:14px;font-weight:600;display:flex;align-items:center;gap:10px;transform:translateX(140%);transition:.4s;z-index:120}
  .toast.show{transform:translateX(0)}
  .alert{background:#e2f0e8;color:var(--ok);border-radius:10px;padding:12px 14px;font-size:13px;margin-bottom:18px}
  .alert.bad{background:#f6e0e0;color:var(--danger)}
  .dropdown{position:absolute;top:52px;right:0;width:300px;background:#fff;border-radius:14px;box-shadow:0 14px 40px rgba(74,47,34,.18);padding:8px;display:none;z-index:50}
  .dropdown.show{display:block}.dropdown h4{font-size:13px;color:var(--muted);padding:10px 12px 6px}
  .noti{display:flex;gap:10px;padding:11px 12px;border-radius:10px}.noti:hover{background:var(--cream-2)}
  .noti .dot{width:8px;height:8px;border-radius:50%;background:var(--warn);margin-top:6px;flex-shrink:0}
  .noti .txt{font-size:13px}.noti .txt small{color:var(--muted);display:block;margin-top:2px}
  .pos{position:relative}
  @media(max-width:1050px){.stats{grid-template-columns:repeat(2,1fr)}.grid{grid-template-columns:1fr}}
  @media(max-width:820px){.sidebar{position:fixed;left:0;top:0;transform:translateX(-100%);transition:.3s;box-shadow:0 0 40px rgba(0,0,0,.3)}.sidebar.open{transform:translateX(0)}.backdrop.show{display:block;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:55}.hamburger{display:flex}.main{margin-left:0;padding:18px}.who .n,.who .r{display:none}}
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
  <nav class="nav" aria-label="Warehouse navigation">
    <div class="nav-section-label">Overview</div>
    <a data-view="dashboard" class="active" aria-current="page"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg> Warehouse Dashboard</a>
    <div class="nav-divider"></div>
    <div class="nav-section-label">Operations</div>
    <a data-view="receive"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg> Receive into Warehouse</a>
    <a data-view="transfer"><svg viewBox="0 0 24 24"><path d="M4 17h16M4 17l4-4M4 17l4 4M20 7H4M20 7l-4-4M20 7l-4 4"/></svg> Transfer to Cafe</a>
    <a data-view="requests"><svg viewBox="0 0 24 24"><path d="M3 3v18h18M7 12l4-4 4 4M11 8v8"/></svg> Stock Requests <?php if($pendingReqs): ?><span style="margin-left:6px;background:#a23232;color:#fff;border-radius:10px;font-size:11px;padding:1px 7px"><?= $pendingReqs ?></span><?php endif; ?></a>
    <div class="nav-divider"></div>
    <div class="nav-section-label">Monitor</div>
    <a data-view="stock"><svg viewBox="0 0 24 24"><path d="M3 3h18v18H3zM3 9h18M3 15h18"/></svg> Warehouse Stock</a>
    <a data-view="reports"><svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="M7 15l4-6 4 3 4-5"/></svg> Reports</a>
    <div class="nav-divider"></div>
    <div class="nav-section-label">Account</div>
    <a href="inventory-manager.php"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg> Back to Manager</a>
    <a href="logout.php"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg> Logout</a>
  </nav>
</aside>

<main class="main">
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:14px">
      <button class="hamburger" onclick="openSidebar()" aria-label="Open menu"><svg viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button>
      <div><h1 id="pageTitle">Warehouse Dashboard</h1><div class="sub" id="pageSub">Bulk receiving, storage &amp; transfers to the cafe</div></div>
    </div>
    <div class="user-area">
      <?= notification_bell($u['role']) ?>
      <div class="who"><div class="avatar"><?= $initials ?></div><div><div class="n"><?= htmlspecialchars($user['firstName'].' '.$user['lastName']) ?></div><div class="r"><?= htmlspecialchars($user['role']) ?></div></div></div>
    </div>
  </div>

  <?php if (isset($_GET['msg'])): ?><div class="alert <?= isset($_GET['bad'])?'bad':'' ?>"><?= htmlspecialchars($_GET['msg']) ?></div><?php endif; ?>

  <section class="view active" id="view-dashboard">
    <div class="stats">
      <div class="stat"><div class="t">Warehouse Items</div><div class="v"><?= $whItemCount ?></div></div>
      <div class="stat"><div class="t">Total Units Held</div><div class="v"><?= number_format($totalWhQty) ?></div></div>
      <div class="stat"><div class="t">Receipts This Month</div><div class="v"><?= $recThisMonth ?></div></div>
      <div class="stat"><div class="t">Transfers This Month</div><div class="v warn"><?= $trfThisMonth ?></div></div>
    </div>
    <div class="panel">
      <h2>Welcome to the Warehouse, <?= htmlspecialchars($user['firstName']) ?>!</h2>
      <div class="desc">Receive large deliveries here, store them, then transfer stock out to the cafe as needed.</div>
      <div class="tools" style="margin-top:6px">
        <button class="btn-primary" style="width:auto;padding:11px 18px" onclick="showView('receive')">+ Receive Goods</button>
        <button class="btn-primary" style="width:auto;padding:11px 18px;background:#fff;color:var(--ink);border:1px solid var(--line)" onclick="showView('transfer')">Transfer to Cafe</button>
      </div>
    </div>
    <div class="grid">
      <div class="panel">
        <h2>⚠ Warehouse Stock to Watch</h2>
        <div class="desc">Items empty or at/below reorder level in the warehouse.</div>
        <?php $watch = array_filter($stock, function($s) { return $s['wqty'] <= $s['reorder_level']; }); ?>
        <?php if ($watch): ?>
        <div class="table-wrap"><table class="dt">
          <thead><tr><th>Item</th><th>Warehouse</th><th>Reorder</th><th>Status</th></tr></thead>
          <tbody><?php foreach($watch as $s): [$l,$c]=whStatus($s['wqty'],$s['reorder_level']); ?>
            <tr><td><b><?= htmlspecialchars($s['name']) ?></b></td><td><?= $s['wqty'] ?> <?= htmlspecialchars($s['unit']) ?></td><td><?= $s['reorder_level'] ?></td><td><span class="pill <?= $c ?>"><?= $l ?></span></td></tr>
          <?php endforeach; ?></tbody></table></div>
        <?php else: ?><div class="empty"><svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg><div>All warehouse items are healthy. 👍</div></div><?php endif; ?>
      </div>
      <div class="panel">
        <h2>Recent Activity</h2>
        <div class="desc">Latest warehouse movement.</div>
        <?php $recent = array_slice($transfers,0,4); ?>
        <?php if ($recent): ?><div class="table-wrap"><table class="dt">
          <thead><tr><th>Ref</th><th>Item</th><th>Qty</th></tr></thead>
          <tbody><?php foreach($recent as $t): ?><tr><td><b><?= $t['ref'] ?></b></td><td><?= htmlspecialchars($t['item']) ?></td><td><?= $t['qty'] ?> <?= $t['unit'] ?></td></tr><?php endforeach; ?></tbody>
        </table></div>
        <?php else: ?><div class="empty">No transfers yet.</div><?php endif; ?>
      </div>
    </div>
  </section>

  <section class="view" id="view-receive">
    <div class="grid">
      <div class="panel">
        <h2>Pending Deliveries</h2>
        <div class="desc">Shipments marked "Delivered" awaiting warehouse receipt. Click Receive to generate a WHR reference and add stock.</div>
        <?php if (isset($_GET['msg'])): ?><div class="alert <?= isset($_GET['bad'])?'bad':'' ?>"><?= htmlspecialchars($_GET['msg']) ?></div><?php endif; ?>
        <?php if ($pendingReceive): ?>
        <div class="table-wrap"><table class="dt">
          <thead><tr><th>Shipment</th><th>Item</th><th>Qty</th><th>Seller</th><th>ETA</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach($pendingReceive as $sh): ?>
            <tr>
              <td><b><?= $sh['ref'] ?></b></td>
              <td><?= htmlspecialchars($sh['item_desc']) ?></td>
              <td><?= $sh['qty'] ?></td>
              <td><?= htmlspecialchars($sh['seller']) ?></td>
              <td><?= $sh['eta'] ? date('Y-m-d', strtotime($sh['eta'])) : '—' ?></td>
              <td>
                <form method="post" action="warehouse.php" style="display:inline"><?= token_field() ?>
                  <input type="hidden" name="wh_action" value="receive_shipment">
                  <input type="hidden" name="ship_id" value="<?= (int)$sh['id'] ?>">
                  <button class="btn-sm ok" type="submit" onclick="return sweetConfirmSubmit(event,'Receive this shipment into the warehouse? A WHR reference will be generated.')">Receive</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
        <?php else: ?>
        <div class="empty">
          <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
          <div>No pending deliveries. All shipments have been received. 👍</div>
        </div>
        <?php endif; ?>
      </div>
      <div class="panel">
        <h2>Recent Receipts</h2>
        <div class="desc">Warehouse receipt log (WHR references).</div>
        <?php if ($receipts): ?>
        <div class="table-wrap"><table class="dt">
          <thead><tr><th>Ref</th><th>Item</th><th>Qty</th><th>Seller</th><th>Date</th></tr></thead>
          <tbody><?php foreach($receipts as $r): ?><tr><td><b><?= $r['ref'] ?></b></td><td><?= htmlspecialchars($r['item']) ?></td><td><?= $r['qty'] ?> <?= htmlspecialchars($r['unit']) ?></td><td><?= htmlspecialchars($r['seller']) ?></td><td><?= $r['date'] ?></td></tr><?php endforeach; ?></tbody>
        </table></div>
        <?php else: ?><div class="empty">No receipts yet.</div><?php endif; ?>
      </div>
    </div>
  </section>

  <section class="view" id="view-stock">
    <div class="panel">
      <h2>Warehouse Stock</h2>
      <div class="desc">Bulk quantity held in the warehouse for each item.</div>
      <div class="tools">
        <div class="search"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg><input id="wsSearch" placeholder="Search item..." onkeyup="filterStock()"></div>
      </div>
      <div class="table-wrap">
        <table class="dt" id="wsTbl">
          <thead><tr><th>Code</th><th>Item</th><th>Unit</th><th>Warehouse Qty</th><th>Cafe Qty</th><th>Reorder</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach($stock as $s): [$l,$c]=whStatus($s['wqty'],$s['reorder_level']); ?>
            <tr><td><small style="color:var(--muted)"><?= htmlspecialchars($s['code']) ?></small></td><td><b><?= htmlspecialchars($s['name']) ?></b></td><td><?= htmlspecialchars($s['unit']) ?></td><td><b><?= $s['wqty'] ?></b></td><td><?= $s['current_qty'] ?? 0 ?></td><td><?= $s['reorder_level'] ?></td><td><span class="pill <?= $c ?>"><?= $l ?></span></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="empty hide" id="wsEmpty">No items match.</div>
    </div>
  </section>

  <section class="view" id="view-transfer">
    <div class="grid">
      <div class="panel">
        <h2>Transfer Warehouse → Cafe</h2>
        <div class="desc">Move stock out of the warehouse and into the cafe's current stock.</div>
        <form method="post" action="warehouse.php"><?= token_field() ?>
          <input type="hidden" name="wh_action" value="transfer">
          <div class="field"><label>Item <span class="req">*</span></label>
            <select name="item_id" required><option value="">— Select item —</option>
              <?php foreach($whItems as $w): ?><option value="<?= (int)$w['id'] ?>" data-qty="<?= (float)$w['qty'] ?>"><?= htmlspecialchars($w['name']) ?> (<?= $w['qty'] ?> <?= htmlspecialchars($w['unit']) ?> in warehouse)</option><?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label>Quantity to transfer <span class="req">*</span></label><input name="tqty" type="number" min="1" step="any" required placeholder="e.g. 50"></div>
          <div class="field"><label>Note / Reason</label><textarea name="tnote" rows="2" placeholder="e.g. Replenish cafe stock"></textarea></div>
          <button class="btn-primary" type="submit">Transfer to Cafe</button>
        </form>
        <p style="font-size:12px;color:var(--muted);margin-top:12px">This removes the quantity from warehouse stock and adds it to the cafe's current stock (logged as a stock-in movement).</p>
      </div>
      <div class="panel">
        <h2>Transfer History</h2>
        <div class="desc">Stock moved out of the warehouse.</div>
        <?php if ($transfers): ?>
        <div class="table-wrap"><table class="dt">
          <thead><tr><th>Ref</th><th>Item</th><th>Qty</th><th>By</th><th>Date</th></tr></thead>
          <tbody><?php foreach($transfers as $t): ?><tr><td><b><?= $t['ref'] ?></b></td><td><?= htmlspecialchars($t['item']) ?></td><td><?= $t['qty'] ?> <?= htmlspecialchars($t['unit']) ?></td><td><?= htmlspecialchars($t['who']) ?></td><td><?= $t['date'] ?></td></tr><?php endforeach; ?></tbody>
        </table></div>
        <?php else: ?><div class="empty">No transfers yet.</div><?php endif; ?>
      </div>
    </div>
  </section>

  <section class="view" id="view-requests">
    <div class="panel">
      <h2>Stock Requests from Clerks</h2>
      <div class="desc">When a clerk is running low, they ask for stock here. Approve to move it from the warehouse to the cafe (deducted from warehouse).</div>
      <?php if (isset($_GET['msg'])): ?><div class="alert <?= isset($_GET['bad'])?'bad':'' ?>"><?= htmlspecialchars($_GET['msg']) ?></div><?php endif; ?>
      <div class="table-wrap">
        <table class="dt">
          <thead><tr><th>Ref</th><th>Item</th><th>Qty</th><th>Requested by</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach($whRequests as $r): ?>
            <tr>
              <td><b><?= $r['ref'] ?></b></td>
              <td><?= htmlspecialchars($r['item']) ?><?= $r['note'] ? '<br><small style="color:var(--muted)">'.$r['note'].'</small>' : '' ?></td>
              <td><?= $r['qty'] ?> <?= htmlspecialchars($r['unit']) ?>
                <?php if($r['status']==='approved' && (float)$r['fulfilled_qty'] < (float)$r['qty']): ?>
                  <span class="pill warn" style="margin-left:4px">fulfilled <?= $r['fulfilled_qty'] ?></span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($r['clerk']) ?></td>
              <td><span class="pill <?= $r['status']==='approved'?'ok':($r['status']==='declined'?'danger':'warn') ?>"><?= $r['status'] ?></span></td>
              <td style="white-space:nowrap">
                <?php if($r['status']==='pending'): ?>
                <form method="post" action="warehouse.php" style="display:inline"><?= token_field() ?>
                  <input type="hidden" name="wh_action" value="decide_request"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="decision" value="approve">
                  <button class="btn-sm ok" type="submit" onclick="return sweetConfirmSubmit(event,'Approve this request? Warehouse will be deducted and it goes to the cafe.')">Approve</button>
                </form>
                <form method="post" action="warehouse.php" style="display:inline"><?= token_field() ?>
                  <input type="hidden" name="wh_action" value="decide_request"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="decision" value="decline">
                  <button class="btn-sm" type="submit" onclick="return sweetConfirmSubmit(event,'Decline this request?')">Decline</button>
                </form>
                <?php else: ?><span style="color:var(--muted);font-size:12px">—</span><?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if(!$whRequests): ?><tr><td colspan="6" class="empty">No stock requests yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="view" id="view-reports">
    <div class="panel">
      <h2>Warehouse Reports</h2>
      <div class="desc">Summary of warehouse inflow (receipts) vs outflow (transfers to cafe).</div>
      <div class="stats">
        <div class="stat"><div class="t">Total Received (all time)</div><div class="v"><?= db_ok()?number_format((float)(db_one("SELECT COALESCE(SUM(qty),0) s FROM warehouse_receipts")['s'])):0 ?></div></div>
        <div class="stat"><div class="t">Total Transferred Out</div><div class="v"><?= db_ok()?number_format((float)(db_one("SELECT COALESCE(SUM(qty),0) s FROM warehouse_transfers")['s'])):0 ?></div></div>
        <div class="stat"><div class="t">Receipts This Month</div><div class="v"><?= $recThisMonth ?></div></div>
        <div class="stat"><div class="t">Transfers This Month</div><div class="v warn"><?= $trfThisMonth ?></div></div>
      </div>
      <div class="table-wrap">
        <table class="dt">
          <thead><tr><th>Item</th><th>Warehouse On Hand</th><th>Cafe On Hand</th><th>Unit</th></tr></thead>
          <tbody>
            <?php foreach($stock as $s): ?><tr><td><b><?= htmlspecialchars($s['name']) ?></b></td><td><?= $s['wqty'] ?></td><td><?= $s['current_qty'] ?? 0 ?></td><td><?= htmlspecialchars($s['unit']) ?></td></tr><?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</main>

<div class="toast" id="toast" role="status" aria-live="polite"><svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg> <span id="toastMsg">Saved!</span></div>

<script>
const VIEW_META={
  dashboard:{title:'Warehouse Dashboard',sub:'Bulk receiving, storage &amp; transfers to the cafe'},
  receive:{title:'Receive into Warehouse',sub:'Record bulk deliveries'},
  stock:{title:'Warehouse Stock',sub:'Bulk quantity held per item'},
  transfer:{title:'Transfer to Cafe',sub:'Move warehouse stock to the storefront'},
  requests:{title:'Stock Requests',sub:'Clerks asking for stock from the warehouse'},
  reports:{title:'Warehouse Reports',sub:'Inflow vs outflow summary'}
};
function showView(name){
  document.querySelectorAll('.view').forEach(v=>v.classList.remove('active'));
  const el=document.getElementById('view-'+name); if(el)el.classList.add('active');
  document.querySelectorAll('.nav a[data-view]').forEach(a=>{
    a.classList.toggle('active',a.dataset.view===name);
    if(a.dataset.view===name)a.setAttribute('aria-current','page');else a.removeAttribute('aria-current');
  });
  const m=VIEW_META[name]; if(m){document.getElementById('pageTitle').textContent=m.title;document.getElementById('pageSub').innerHTML=m.sub;}
  location.hash=name; closeSidebar(); window.scrollTo({top:0,behavior:'smooth'});
}
document.querySelectorAll('.nav a[data-view]').forEach(a=>a.addEventListener('click',e=>{e.preventDefault();showView(a.dataset.view);}));
window.addEventListener('DOMContentLoaded',()=>{const h=location.hash.replace('#','');if(h&&VIEW_META[h])showView(h);
  if(location.search.includes('saved=1')) toast('Changes saved!');
});
function goHome(e){ if(e) e.preventDefault(); showView('dashboard'); }
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('backdrop').classList.add('show');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('backdrop').classList.remove('show');}
function toggleNoti(e){e.stopPropagation();document.getElementById('notiBox').classList.toggle('show');}
document.addEventListener('click',()=>document.getElementById('notiBox').classList.remove('show'));
function toast(msg,bad){const t=document.getElementById('toast');t.style.background=bad?'#a23232':'#256b4d';document.getElementById('toastMsg').textContent=msg;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),2800);}
function filterStock(){
  const q=document.getElementById('wsSearch').value.toLowerCase();let shown=0;
  document.querySelectorAll('#wsTbl tbody tr').forEach(r=>{const m=r.textContent.toLowerCase().includes(q);r.style.display=m?'':'none';if(m)shown++;});
  document.getElementById('wsEmpty').classList.toggle('hide',shown>0);
}
</script>
<script src="input-guard.js"></script>
<script src="datatable.js"></script>
<script src="alerts.js"></script>
<script>
// Patch sweetConfirmSubmit: get the real form, not the button
window.sweetConfirmSubmit = function (event, message) {
    event.preventDefault();
    var btn = event.currentTarget;
    var form = btn.form || btn.closest('form');
    if (!form) return false;
    swalConfirm(message, function () { form.submit(); });
    return false;
};
</script>
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
