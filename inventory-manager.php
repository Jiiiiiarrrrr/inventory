<?php
require __DIR__ . '/db.php';
require __DIR__ . '/signature-panel.php';
define('PAGE_ROLE', 'manager');

// Role guard: only a logged-in manager may view this page.
if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], [PAGE_ROLE, 'superadmin'])) {
    if (db_ok()) { header('Location: login.php'); exit; }
    $_SESSION['user'] = ['id'=>0,'firstName'=>'Jayr','lastName'=>'Fabon','email'=>'jayr@brewco.ph','role'=>'manager'];
}
$u = $_SESSION['user'];
$user = ['firstName' => $u['firstName'], 'lastName' => $u['lastName'], 'role' => 'Inventory Manager'];
inv_ensure_purchase_flow();
$initials = strtoupper(substr($user['firstName'],0,1).substr($user['lastName'],0,1));
change_password_handler($u, 'inventory-manager.php'); // allow manager to change their own password

// ---- Demo data (used only when the database is unreachable) ----
$demoRequests = [
    ['id'=>'PR-0042','item'=>'Arabica Beans','qty'=>'50 kg','seller'=>'Kalinga Coffee Co.','est'=>18500,'status'=>'pending','date'=>'2026-07-29'],
    ['id'=>'PR-0041','item'=>'Fresh Milk','qty'=>'80 L','seller'=>'Dairy Fresh PH','est'=>7200,'status'=>'approved','date'=>'2026-07-27'],
    ['id'=>'PR-0040','item'=>'Caramel Syrup','qty'=>'24 bottles','seller'=>'SweetLine Supply','est'=>9600,'status'=>'declined','date'=>'2026-07-25'],
];
$demoSellers = [
    ['id'=>1,'name'=>'Kalinga Coffee Co.','contact'=>'Ms. Rowena','phone'=>'0917-555-2013','goods'=>'Arabica, Robusta beans','rating'=>4.8,'is_active'=>1],
    ['id'=>2,'name'=>'Dairy Fresh PH','contact'=>'Mr. Luis','phone'=>'0918-221-4400','goods'=>'Fresh milk, cream','rating'=>4.5,'is_active'=>1],
    ['id'=>3,'name'=>'SweetLine Supply','contact'=>'Ms. Ana','phone'=>'0920-778-9910','goods'=>'Syrups, sauces','rating'=>4.1,'is_active'=>1],
    ['id'=>4,'name'=>'PackRight Trading','contact'=>'Mr. Dan','phone'=>'0915-330-1200','goods'=>'Cups, lids, napkins','rating'=>4.6,'is_active'=>1],
];
$demoQc = [
    ['id'=>1,'item'=>'Arabica Beans','seller'=>'Kalinga Coffee Co.','received'=>'2026-07-29','status'=>'pending'],
    ['id'=>2,'item'=>'Fresh Milk','seller'=>'Dairy Fresh PH','received'=>'2026-07-28','status'=>'passed'],
    ['id'=>3,'item'=>'Oat Milk','seller'=>'Dairy Fresh PH','received'=>'2026-07-28','status'=>'failed'],
];
$demoShipments = [
    ['ref'=>'SHP-771','item'=>'Arabica Beans (50kg)','seller'=>'Kalinga Coffee Co.','eta'=>'2026-08-01','status'=>'In transit'],
    ['ref'=>'SHP-770','item'=>'Paper Cups (10k pcs)','seller'=>'PackRight Trading','eta'=>'2026-07-31','status'=>'Out for delivery'],
    ['ref'=>'SHP-769','item'=>'Fresh Milk (80L)','seller'=>'Dairy Fresh PH','eta'=>'2026-07-30','status'=>'Delivered'],
];
$demoClerkLogs = [
    ['date'=>'2026-07-28 09:14','clerk'=>'Jenwin Docabo','action'=>'Stock In','item'=>'Arabica Beans +20kg'],
    ['date'=>'2026-07-28 11:02','clerk'=>'Jenwin Docabo','action'=>'Stock Out','item'=>'Fresh Milk -6L'],
    ['date'=>'2026-07-27 16:45','clerk'=>'Jayr Fabon','action'=>'Reduced','item'=>'Caramel Syrup -1'],
];

// ---- Handle Admin approval gate for clerk stock requests ----
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['gate_id'])) {
    if (!verify_token($_POST['token'] ?? '')) { header('Location: inventory-manager.php?view=reqs&msg='.urlencode('Invalid form token.').'&bad=1'); exit; }
    $gid = (int)$_POST['gate_id'];
    $gact = $_POST['gate_action'] ?? '';
    if (db_ok() && $gid && in_array($gact, ['approve','reject'], true)) {
        if ($gact === 'approve') {
            db_exec("UPDATE warehouse_requests SET admin_status='approved', admin_decided_at=NOW() WHERE id=? AND admin_status='pending'", [$gid]);
            audit($u, 'stockgate.approve', 'Approved stock request #'.$gid.' - released to the warehouse queue');
        } else {
            db_exec("UPDATE warehouse_requests SET admin_status='rejected', status='declined', admin_decided_at=NOW() WHERE id=? AND admin_status='pending'", [$gid]);
            audit($u, 'stockgate.reject', 'Rejected stock request #'.$gid.' - returned to clerk, never sent to warehouse');
        }
        header('Location: inventory-manager.php?view=reqs&saved=1'); exit;
    }
    header('Location: inventory-manager.php?view=reqs&msg='.urlencode('Nothing to update.').'&bad=1'); exit;
}

// ---- Handle "New Purchase Request" form ----
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['item'])) {
    if (!verify_token($_POST['token'] ?? '')) { header('Location: inventory-manager.php?view=procure&msg='.urlencode('Invalid form token.').'&bad=1'); exit; }
    if (db_ok()) {
        $item = trim($_POST['item'] ?? '');
        // If "Other needs…" was chosen, use the typed value instead
        if ($item === '__other__') {
            $item = trim($_POST['other_item'] ?? '');
        }
        $qty = (float)($_POST['qty']??0);
        $unit = trim($_POST['unit'] ?? $_POST['unit_display'] ?? ''); $sellerName = trim($_POST['seller']??'');
        $cost = (float)($_POST['cost']??0);
        if ($qty <= 0 || $cost <= 0) { header('Location: inventory-manager.php?view=procure&msg='.urlencode('Enter a valid quantity and cost.').'&bad=1'); exit; }
        $seller = $sellerName ? db_one('SELECT id FROM sellers WHERE name=? LIMIT 1',[$sellerName]) : null;
        $it = db_one('SELECT id FROM items WHERE name=? LIMIT 1',[$item]);
        $prCode = tx(function() use ($it,$seller,$item,$qty,$unit,$cost,$u) {
            $max = (int)(db_one("SELECT MAX(CAST(SUBSTRING(pr_code,4) AS UNSIGNED)) m FROM purchase_requests")['m'] ?? 42);
            $prCode = 'PR-' . str_pad($max + 1, 4, '0', STR_PAD_LEFT);
            db_exec('INSERT INTO purchase_requests
                     (pr_code,item_id,item_desc,qty,unit,seller_id,est_cost,status,requested_by)
                     VALUES (?,?,?,?,?,?,?,?,?)',
                    [$prCode, $it?$it['id']:null, $item, $qty, $unit,
                     $seller?$seller['id']:null, $cost, 'pending', (int)$u['id']]);
            audit($u, 'pr.create', 'Created '.$prCode.': '.$item.' x'.$qty.' '.$unit.' (₱'.number_format($cost).') + formal letter to Finance');
            return $prCode;
        });
        // ---- save the auto-generated formal letter (Word .docx) + e-signature + security photo ----
        $dir = __DIR__ . '/uploads/purchase_letters';
        if ($prCode && !empty($_FILES['letter']['name']) && (int)($_FILES['letter']['error'] ?? 1) === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['letter']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['docx','doc','pdf'], true)) {
                if (!is_dir($dir)) @mkdir($dir, 0775, true);
                $safe = preg_replace('/[^A-Za-z0-9_-]/', '', $prCode) . '.' . $ext;
                if (@move_uploaded_file($_FILES['letter']['tmp_name'], $dir . '/' . $safe)) {
                    db_exec('UPDATE purchase_requests SET letter_path=? WHERE pr_code=?',
                            ['uploads/purchase_letters/' . $safe, $prCode]);
                    audit($u, 'pr.letter', 'Formal letter attached to '.$prCode);
                }
            }
        }
        if ($prCode) {
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $extras = [
                ['signature', ['png'],          '_sig',    'signature_path'],
                ['selfie',    ['jpg','jpeg'],   '_selfie', 'selfie_path'],
            ];
            foreach ($extras as $ex) {
                list($fkey, $fexts, $fsuffix, $fcol) = $ex;
                if (!empty($_FILES[$fkey]['name']) && (int)($_FILES[$fkey]['error'] ?? 1) === UPLOAD_ERR_OK) {
                    $fext = strtolower(pathinfo($_FILES[$fkey]['name'], PATHINFO_EXTENSION));
                    if (in_array($fext, $fexts, true)) {
                        $fsafe = preg_replace('/[^A-Za-z0-9_-]/', '', $prCode) . $fsuffix . '.' . $fext;
                        if (@move_uploaded_file($_FILES[$fkey]['tmp_name'], $dir . '/' . $fsafe)) {
                            db_exec('UPDATE purchase_requests SET ' . $fcol . '=? WHERE pr_code=?',
                                    ['uploads/purchase_letters/' . $fsafe, $prCode]);
                        }
                    }
                }
            }
        }
        header('Location: inventory-manager.php?view=procure&'.($prCode?'saved=1':'msg='.urlencode('Failed to create request.').'&bad=1')); exit;
    }
}

// ---- Handle Quality Check Pass / Fail ----
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['qc_id'])) {
    if (!verify_token($_POST['token'] ?? '')) { header('Location: inventory-manager.php?view=qc&msg='.urlencode('Invalid form token.').'&bad=1'); exit; }
    if (db_ok()) {
    $id  = (int)$_POST['qc_id'];
    $res = ($_POST['qc_result']==='passed') ? 'passed' : 'failed';
    db_exec('UPDATE quality_checks SET status=?, checked_by=?, checked_at=NOW() WHERE id=?',
            [$res, (int)$u['id'], $id]);
    audit($u, 'qc.'.($res==='passed'?'pass':'fail'), 'Quality check #'.$id.' '.$res);
    header('Location: inventory-manager.php?view=qc&saved=1'); exit;
    }
}

// ---- Handle Seller add / edit / toggle ----
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['seller_action'])) {
    if (!verify_token($_POST['token'] ?? '')) { header('Location: inventory-manager.php?view=sellers&msg='.urlencode('Invalid form token.').'&bad=1'); exit; }
    if (db_ok()) {
    $sa = $_POST['seller_action'];
    if ($sa === 'add_seller') {
        $name = trim($_POST['sname'] ?? ''); $contact = trim($_POST['scontact'] ?? '');
        $phone = trim($_POST['sphone'] ?? ''); $goods = trim($_POST['sgoods'] ?? '');
        $rating = ($_POST['srating'] ?? '') !== '' ? (float)$_POST['srating'] : null;
        if ($name && !db_one('SELECT id FROM sellers WHERE name=?', [$name])) {
            db_exec('INSERT INTO sellers (name,contact,phone,goods,rating,is_active) VALUES (?,?,?,?,?,1)',
                    [$name,$contact,$phone,$goods,$rating]);
            audit($u, 'seller.add', 'Added seller '.$name);
            header('Location: inventory-manager.php?view=sellers&saved=1'); exit;
        }
    }
    elseif ($sa === 'edit_seller') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['sname'] ?? ''); $contact = trim($_POST['scontact'] ?? '');
        $phone = trim($_POST['sphone'] ?? ''); $goods = trim($_POST['sgoods'] ?? '');
        $rating = ($_POST['srating'] ?? '') !== '' ? (float)$_POST['srating'] : null;
        if ($id && $name) {
            db_exec('UPDATE sellers SET name=?,contact=?,phone=?,goods=?,rating=? WHERE id=?',
                    [$name,$contact,$phone,$goods,$rating,$id]);
            audit($u, 'seller.edit', 'Edited seller #'.$id.' ('.$name.')');
            header('Location: inventory-manager.php?view=sellers&saved=1'); exit;
        }
    }
    elseif ($sa === 'toggle_seller') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            db_exec('UPDATE sellers SET is_active = IF(is_active=1,0,1) WHERE id=?', [$id]);
            audit($u, 'seller.toggle', 'Toggled seller #'.$id.' status');
            header('Location: inventory-manager.php?view=sellers&saved=1'); exit;
        }
    }
    }
}
$sellers = db_ok() ? db_all("SELECT id, name, contact, phone, goods, rating, is_active
                             FROM sellers ORDER BY is_active DESC, name") : [];
$activeSellers = 0; foreach ($sellers as $s) if ($s['is_active']) $activeSellers++;

// ---- Load data from the DB (fallback to demo) ----
$requests = $demoRequests;
if (db_ok()) {
    $rows = db_all("SELECT pr.pr_code id, pr.item_desc item,
                           CONCAT(pr.qty,' ',pr.unit) qty,
                           COALESCE(s.name,'—') seller, pr.est_cost est,
                           pr.status, pr.letter_path, DATE(pr.created_at) date
                    FROM purchase_requests pr LEFT JOIN sellers s ON pr.seller_id=s.id
                    ORDER BY pr.created_at DESC");
    if ($rows) $requests = $rows;
}

// ---- Clerk stock requests waiting for ADMIN approval (gate) ----
$gateRequests = []; $gateDecided = []; $gateCount = 0;
if (db_ok()) {
    $gateRequests = db_all("SELECT r.id, r.ref, COALESCE(i.name,'(deleted item)') item, r.qty, r.unit, r.note,
                                   CONCAT(cl.first_name,' ',cl.last_name) clerk, DATE(r.created_at) date
                            FROM warehouse_requests r
                            LEFT JOIN items i ON i.id=r.item_id
                            JOIN users cl ON cl.id=r.requested_by
                            WHERE r.admin_status='pending'
                            ORDER BY r.created_at DESC");
    $gateCount = count($gateRequests);
    $gateDecided = db_all("SELECT r.id, r.ref, COALESCE(i.name,'(deleted item)') item, r.qty, r.unit,
                                  CONCAT(cl.first_name,' ',cl.last_name) clerk, r.admin_status,
                                  DATE(r.admin_decided_at) date
                           FROM warehouse_requests r
                           LEFT JOIN items i ON i.id=r.item_id
                           JOIN users cl ON cl.id=r.requested_by
                           WHERE r.admin_status IN ('approved','rejected')
                           ORDER BY r.admin_decided_at DESC LIMIT 50");
}

$sellers = $demoSellers;
if (db_ok()) {
    $rows = db_all("SELECT s.id, s.name, s.contact, s.phone, s.goods, s.rating, s.is_active,
                           COALESCE((SELECT COUNT(*) FROM shipments sh WHERE sh.seller_id=s.id AND sh.eta IS NOT NULL),0) ship_count,
                           COALESCE((SELECT COUNT(*) FROM shipments sh WHERE sh.seller_id=s.id AND sh.eta IS NOT NULL AND sh.received_qty IS NOT NULL AND sh.eta >= sh.created_at),0) ontime_count,
                           COALESCE((SELECT COUNT(*) FROM quality_checks q WHERE q.seller_id=s.id AND q.status='failed'),0) reject_count,
                           COALESCE((SELECT COUNT(*) FROM quality_checks q WHERE q.seller_id=s.id AND q.status IN ('passed','failed')),0) qc_count
                    FROM sellers s ORDER BY s.is_active DESC, s.name");
    if ($rows) {
        $sellers = array_map(function($r){
            $r['ontime_pct'] = $r['ship_count'] > 0 ? round($r['ontime_count'] / $r['ship_count'] * 100) : null;
            $r['reject_pct'] = $r['qc_count'] > 0 ? round($r['reject_count'] / $r['qc_count'] * 100) : null;
            return $r;
        }, $rows);
    }
}

$qc = $demoQc;
if (db_ok()) {
    $rows = db_all("SELECT qc.id, i.name item, COALESCE(s.name,'—') seller,
                           qc.received, qc.status
                    FROM quality_checks qc
                    JOIN items i ON qc.item_id=i.id
                    LEFT JOIN sellers s ON qc.seller_id=s.id
                    ORDER BY qc.created_at DESC");
    if ($rows) $qc = $rows;
}

$shipments = $demoShipments;
if (db_ok()) {
    $rows = db_all("SELECT sh.id, sh.ref, sh.item_desc item, COALESCE(s.name,'—') seller,
                           sh.eta, sh.status, sh.qty, sh.received_qty, sh.shortage_qty, sh.shortage_status, sh.item_id
                    FROM shipments sh LEFT JOIN sellers s ON sh.seller_id=s.id
                    ORDER BY sh.id DESC");
    if ($rows) $shipments = $rows;
}

$clerkLogs = $demoClerkLogs;
if (db_ok()) {
    $rows = db_all("SELECT sm.created_at, CONCAT(u.first_name,' ',u.last_name) clerk,
                           sm.action, i.name iname, sm.qty, sm.unit
                    FROM stock_movements sm
                    JOIN users u ON sm.performed_by=u.id
                    JOIN items i ON sm.item_id=i.id
                    ORDER BY sm.created_at DESC LIMIT 50");
    if ($rows) {
        $labels = ['in'=>'Stock In','out'=>'Stock Out','reduce'=>'Reduced','remove'=>'Removed','adjust'=>'Adjusted'];
        $clerkLogs = array_map(function($r) use ($labels) {
            $sign = $r['action']==='in' ? '+' : '-';
            return ['date'=>date('Y-m-d H:i', strtotime($r['created_at'])),
                    'clerk'=>$r['clerk'],
                    'action'=>$labels[$r['action']] ?? ucfirst($r['action']),
                    'item'=>$r['iname'].' '.$sign.$r['qty'].' '.$r['unit']];
        }, $rows);
    }
}

$pending = 0; foreach($requests as $r){ if($r['status']==='pending') $pending++; }

// ---- Load ITEMS & CATEGORIES from the DB ----
$items = db_ok()
    ? db_all("SELECT i.id, i.code, i.name, i.unit, i.current_qty, i.reorder_level, i.cost, i.cost_status,
                     c.name AS category, i.is_active
              FROM items i JOIN categories c ON i.category_id=c.id
              ORDER BY i.is_active DESC, i.name")
    : [];
$categories = db_ok()
    ? db_all("SELECT id, name FROM categories ORDER BY name")
    : [];

// ---- Handle Item add / edit / toggle ----
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['item_action'])) {
    if (!verify_token($_POST['token'] ?? '')) { header('Location: inventory-manager.php?view=items&msg='.urlencode('Invalid form token.').'&bad=1'); exit; }
    if (db_ok()) {
    $ia = $_POST['item_action'];
    if ($ia === 'add_item') {
        $name = trim($_POST['iname'] ?? ''); $unit = trim($_POST['iunit'] ?? '');
        $catId = (int)($_POST['icat'] ?? 0); $reorder = (float)($_POST['ireorder'] ?? 0);
        $qty = (float)($_POST['iqty'] ?? 0); $cost = (float)($_POST['icost'] ?? 0);
        if ($name && $unit && $catId) {
            $code = 'ING-' . str_pad((int)(db_one("SELECT COUNT(*) n FROM items")['n']) + 1, 3, '0', STR_PAD_LEFT);
            // New items' price starts as 'pending' until Finance approves it.
            db_exec('INSERT INTO items (code,name,category_id,unit,current_qty,reorder_level,cost,cost_status,is_active)
                     VALUES (?,?,?,?,?,?,?,?,1)', [$code,$name,$catId,$unit,$qty,$reorder,$cost,'pending']);
            // Every item lives in the warehouse too (starts at 0 on-hand)
            $newId = db_last_id();
            if (!db_one('SELECT id FROM warehouse_stock WHERE item_id=?', [$newId])) {
                db_exec('INSERT INTO warehouse_stock (item_id,qty) VALUES (?,0)', [$newId]);
            }
            audit($u, 'item.add', 'Added item '.$code.' '.$name.' (price ₱'.number_format($cost).' pending Finance approval)');
            header('Location: inventory-manager.php?view=items&msg='.urlencode('Item added. Its price awaits Finance approval.')); exit;
        }
    }
    elseif ($ia === 'edit_item') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['iname'] ?? ''); $unit = trim($_POST['iunit'] ?? '');
        $catId = (int)($_POST['icat'] ?? 0); $reorder = (float)($_POST['ireorder'] ?? 0);
        $cost = (float)($_POST['icost'] ?? 0);
        if ($id && $name && $unit && $catId) {
            // If the price changed, mark it pending for Finance approval (and remember the old approved price).
            $old = db_one('SELECT cost, old_cost FROM items WHERE id=?', [$id]);
            $priceChanged = $old && abs((float)$old['cost'] - $cost) > 0.001;
            $status = $priceChanged ? 'pending' : 'approved';
            // old_cost = the previous approved price (either existing old_cost or current cost)
            $oldApproved = $priceChanged ? ((float)($old['old_cost'] ?? $old['cost'])) : null;
            db_exec('UPDATE items SET name=?, unit=?, category_id=?, reorder_level=?, cost=?, cost_status=?, old_cost=? WHERE id=?',
                    [$name,$unit,$catId,$reorder,$cost,$status,$oldApproved,$id]);
            audit($u, 'item.edit', 'Edited item #'.$id.' ('.$name.')'.($status==='pending'?' — price changed, awaits Finance approval':''));
            header('Location: inventory-manager.php?view=items&saved=1'); exit;
        }
    }
    elseif ($ia === 'toggle_item') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            db_exec('UPDATE items SET is_active = IF(is_active=1,0,1) WHERE id=?', [$id]);
            audit($u, 'item.toggle', 'Toggled item #'.$id.' status');
            header('Location: inventory-manager.php?view=items&saved=1'); exit;
        }
    }
    elseif ($ia === 'add_category') {
        $name = trim($_POST['cname'] ?? '');
        if ($name && !db_one('SELECT id FROM categories WHERE name=?', [$name])) {
            db_exec('INSERT INTO categories (name) VALUES (?)', [$name]);
            audit($u, 'category.add', 'Added category '.$name);
            header('Location: inventory-manager.php?view=items&saved=1'); exit;
        }
    }
    }
}

// Reload items/categories after any write (so the freshly-added row shows)
$items = db_ok() ? db_all("SELECT i.id, i.code, i.name, i.unit, i.current_qty, i.reorder_level, i.cost, i.cost_status,
                                  c.name AS category, i.is_active
                           FROM items i JOIN categories c ON i.category_id=c.id
                           ORDER BY i.is_active DESC, i.name") : [];
$categories = db_ok() ? db_all("SELECT id, name FROM categories ORDER BY name") : [];

// ---- Handle Shipment add / advance / delivered / receive ----
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ship_action'])) {
    if (!verify_token($_POST['token'] ?? '')) { header('Location: inventory-manager.php?view=ship&msg='.urlencode('Invalid form token.').'&bad=1'); exit; }
    if (db_ok()) {
    $shp = $_POST['ship_action'];

    if ($shp === 'add_shipment') {
        $itemName = trim($_POST['ship_item'] ?? '');
        $sellerName = trim($_POST['ship_seller'] ?? '');
        $qty = (float)($_POST['ship_qty'] ?? 0);
        $eta = trim($_POST['ship_eta'] ?? '');

        // Validate ETA: must be provided and must be tomorrow or later
        if (!$eta) {
            header('Location: inventory-manager.php?view=ship&bad=1&msg='.urlencode('ETA date is required.')); exit;
        }
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        if ($eta < $tomorrow) {
            header('Location: inventory-manager.php?view=ship&bad=1&msg='.urlencode('ETA must be tomorrow or later.')); exit;
        }

        if ($itemName && $sellerName && $qty > 0) {
            $it = db_one('SELECT id, name, unit FROM items WHERE name=? AND is_active=1 LIMIT 1', [$itemName]);
            $seller = db_one('SELECT id, name FROM sellers WHERE name=? AND is_active=1 LIMIT 1', [$sellerName]);
            if ($it && $seller) {
                $max = (int)(db_one("SELECT MAX(CAST(SUBSTRING(ref,5) AS UNSIGNED)) m FROM shipments")['m'] ?? 770);
                $ref = 'SHP-' . ($max + 1);
                db_exec('INSERT INTO shipments (ref,item_id,item_desc,qty,seller_id,eta,status)
                         VALUES (?,?,?,?,?,?,?)',
                        [$ref,$it['id'],$it['name'].' ('.number_format($qty).' '.$it['unit'].')',
                         $qty,$seller['id'],$eta ?: null,'In transit']);
                audit($u, 'shipment.add', 'Added '.$ref.' '.$it['name'].' x'.$qty.' '.$it['unit']);
                header('Location: inventory-manager.php?view=ship&saved=1'); exit;
            }
        }
        header('Location: inventory-manager.php?view=ship&saved=1&bad=1'); exit;
    }

    elseif ($shp === 'set_eta') {
        $id = (int)($_POST['id'] ?? 0);
        $eta = trim($_POST['eta'] ?? '');
        $sh = $id ? db_one('SELECT id, ref FROM shipments WHERE id=?', [$id]) : null;
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        if (!$sh || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eta) || $eta < $tomorrow) {
            header('Location: inventory-manager.php?view=ship&bad=1&msg='.urlencode('ETA must be tomorrow or later.')); exit;
        }
        db_exec('UPDATE shipments SET eta=? WHERE id=?', [$eta, $id]);
        audit($u, 'shipment.eta', 'Set delivery date of '.$sh['ref'].' to '.$eta);
        header('Location: inventory-manager.php?view=ship&saved=1'); exit;
    }
    elseif ($shp === 'advance_shipment') {
        $id = (int)($_POST['id'] ?? 0);
        $sh = $id ? db_one('SELECT id, item_id, seller_id, qty, item_desc, status FROM shipments WHERE id=?', [$id]) : null;
        if ($sh) {
            $next = $sh['status'] === 'In transit' ? 'Out for delivery'
                  : ($sh['status'] === 'Out for delivery' ? 'Delivered' : $sh['status']);
            db_exec('UPDATE shipments SET status=? WHERE id=?', [$next, $id]);
            // When delivered, auto-create a QC record so the manager verifies it
            if ($next === 'Delivered' && $sh['item_id']) {
                $exists = db_one('SELECT id FROM quality_checks WHERE item_id=? AND status="pending"', [$sh['item_id']]);
                if (!$exists) {
                    db_exec('INSERT INTO quality_checks (item_id,seller_id,received,status)
                             VALUES (?,?,CURDATE(),"pending")', [$sh['item_id'], $sh['seller_id']]);
                }
            }
            audit($u, 'shipment.advance', $sh['ref'].' moved to '.$next);
            header('Location: inventory-manager.php?view=ship&saved=1'); exit;
        }
        header('Location: inventory-manager.php?view=ship&saved=1&bad=1'); exit;
    }

    elseif ($shp === 'receive_shipment') {
        // GOODS RECEIVING (GRN): the manager receives the order ONLY after the
        // quality check has passed. The received goods go to the WAREHOUSE first
        // (not straight to the cafe), because every item is stored in the warehouse.
        $id = (int)($_POST['id'] ?? 0);
        $recv = (float)($_POST['recv_qty'] ?? 0);
        $sh = $id ? db_one('SELECT id, ref, item_id, seller_id, qty, item_desc, status FROM shipments WHERE id=?', [$id]) : null;
        if ($sh && $sh['status'] === 'Delivered' && $recv > 0) {
            // Require a PASSED quality check before accepting the goods
            $qcPassed = db_one("SELECT id FROM quality_checks
                                WHERE item_id=? AND seller_id=? AND status='passed'
                                ORDER BY checked_at DESC LIMIT 1", [$sh['item_id'], $sh['seller_id']]);
            if (!$qcPassed) {
                header('Location: inventory-manager.php?view=ship&saved=1&bad=1&msg='.urlencode('Quality check must pass before goods go to the warehouse.')); exit;
            }
            $it = db_one('SELECT id, unit FROM items WHERE id=?', [$sh['item_id']]);
            $unit = $it['unit'] ?? 'unit';
            // Determine shortage / overage
            $diff = $recv - (float)$sh['qty'];
            $shortage = $diff < 0 ? abs($diff) : 0;
            $shortStatus = $shortage > 0 ? 'pending' : 'none';
            // Update shipment received qty, status + shortage info
            db_exec('UPDATE shipments SET received_qty=?, status="Received", shortage_qty=?, shortage_status=? WHERE id=?',
                    [$recv, $shortage, $shortStatus, $id]);
            // The received goods go into the WAREHOUSE, not the cafe
            $ws = db_one('SELECT id FROM warehouse_stock WHERE item_id=?', [$sh['item_id']]);
            if ($ws) db_exec('UPDATE warehouse_stock SET qty = qty + ? WHERE id=?', [$recv, $ws['id']]);
            else      db_exec('INSERT INTO warehouse_stock (item_id,qty) VALUES (?,?)', [$sh['item_id'], $recv]);
            // Also record it as a warehouse receipt so it appears in warehouse history
            $max = (int)(db_one("SELECT MAX(CAST(SUBSTRING(ref,5) AS UNSIGNED)) m FROM warehouse_receipts")['m'] ?? 0);
            $whr = 'WHR-' . str_pad($max + 1, 4, '0', STR_PAD_LEFT);
            db_exec('INSERT INTO warehouse_receipts (ref,item_id,qty,unit,seller_id,note,received_by)
                     VALUES (?,?,?,?,?,?,?)',
                    [$whr, $sh['item_id'], $recv, $unit, $sh['seller_id'], 'From shipment '.$sh['ref'].' (QC passed)', (int)$u['id']]);
            $note = 'Received '.$recv.' '.$unit.' from '.$sh['ref'].' into warehouse ('.$whr.')';
            if ($diff < 0) $note .= ' — SHORT by '.number_format($shortage).' '.$unit.' (pending resolution)';
            elseif ($diff > 0) $note .= ' — OVER by '.number_format($diff).' '.$unit;
            else $note .= ' — full quantity.';
            audit($u, 'shipment.receive', $note);
            header('Location: inventory-manager.php?view=ship&saved=1'); exit;
        }
        header('Location: inventory-manager.php?view=ship&saved=1&bad=1'); exit;
    }

    elseif ($shp === 'resolve_shortage') {
        // Resolve an incomplete delivery: backorder (supplier will send the rest)
        // or credit (supplier deducts the missing amount from the invoice).
        $id = (int)($_POST['id'] ?? 0);
        $resolution = $_POST['resolution'] ?? '';
        $sh = $id ? db_one('SELECT id, ref, item_desc, shortage_qty FROM shipments WHERE id=?', [$id]) : null;
        if ($sh && $sh['shortage_qty'] > 0 && in_array($resolution, ['backordered','credited'])) {
            db_exec('UPDATE shipments SET shortage_status=? WHERE id=?', [$resolution, $id]);
            $label = $resolution === 'backordered' ? 'backordered (supplier will send the rest)' : 'credited (deducted from invoice)';
            audit($u, 'shipment.'.($resolution==='backordered'?'backorder':'credit'),
                  $sh['ref'].' shortage of '.$sh['shortage_qty'].' marked as '.$label);
            header('Location: inventory-manager.php?view=ship&saved=1'); exit;
        }
        header('Location: inventory-manager.php?view=ship&saved=1&bad=1'); exit;
    }
    }
}
$shipments = db_ok() ? db_all("SELECT sh.id, sh.ref, sh.item_desc item, COALESCE(s.name,'—') seller,
                               sh.eta, sh.status, sh.qty, sh.received_qty, sh.shortage_qty, sh.shortage_status, sh.item_id,
                               pr.pr_code
                        FROM shipments sh LEFT JOIN sellers s ON sh.seller_id=s.id
                        LEFT JOIN purchase_requests pr ON pr.id=sh.pr_id
                        ORDER BY (sh.eta IS NULL) DESC, sh.id DESC") : [];

// ---- Handle Auto-Reorder (create PRs for low-stock items) ----
// ---- Handle Low-Stock Email Alert ----
$qcPending = 0; foreach($qc as $q){ if($q['status']==='pending') $qcPending++; }
$incoming = 0; foreach($shipments as $sh){ if($sh['status']!=='Delivered') $incoming++; }
$notiCount = $pending + $qcPending;
function badgeCls($s){ return in_array($s,['approved','passed','Delivered'])?'ok':(in_array($s,['declined','failed'])?'danger':'warn'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="favicon.ico?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Brew &amp; Co. Inventory — Procurement</title>
<style>
  :root{
    --brown-900:#faf6f0;--brown-800:#5c3a29;--brown-700:#7a5040;
    --brown-500:#a9714a;--accent:#a9714a;--accent-dark:#8f5c39;
    --cream:#faf6f0;--cream-2:#f5ede3;--card:#fff;--ink:#3b2313;--muted:#7a6055;--line:#e8ddd0;
    --ok:#256b4d;--warn:#96690a;--danger:#a23232;--radius:16px;--shadow:0 8px 24px rgba(74,47,34,.08);
  }
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
  .nav{flex:1;min-height:0;overflow-y:auto}

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
  .who{display:flex;align-items:center;gap:11px}
  .avatar{width:44px;height:44px;border-radius:50%;background:var(--brown-500);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700}
  .who .n{font-weight:700;font-size:14px}.who .r{font-size:12px;color:var(--muted)}
  .view{display:none}.view.active{display:block;animation:fade .25s ease}
  @keyframes fade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
  .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:22px}
  .stat{background:var(--card);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow)}
  .stat .t{font-size:13px;color:var(--muted);margin-bottom:10px}.stat .v{font-size:34px;font-weight:800}.stat .v.warn{color:var(--warn)}
  .grid{display:grid;grid-template-columns:1.1fr .9fr;gap:18px}
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
  .field{margin-bottom:14px}
  .field label{display:block;font-size:13px;font-weight:600;margin-bottom:6px}.field .req{color:var(--danger)}
  .field input,.field select,.field textarea{width:100%;border:1px solid var(--line);border-radius:10px;padding:11px 12px;font-size:14px;background:#fff;color:var(--ink);outline:none;font-family:inherit}
  .field input:focus,.field select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(169,113,74,.15)}
  .field .err{display:none;color:var(--danger);font-size:12px;margin-top:5px}
  .field.invalid input,.field.invalid select{border-color:var(--danger);background:#fff8f8}.field.invalid .err{display:flex;gap:5px}
  .row2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .btn-primary{width:100%;display:flex;align-items:center;justify-content:center;gap:8px;background:var(--accent);color:#fff;border:none;border-radius:10px;padding:13px;font-size:15px;font-weight:700;cursor:pointer}
  .btn-primary:hover{background:var(--accent-dark)}.btn-primary:disabled{opacity:.7;cursor:not-allowed}
  .spinner{width:16px;height:16px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:none}
  @keyframes spin{to{transform:rotate(360deg)}}.btn-primary.loading .spinner{display:block}
  .btn-sm{border:1px solid var(--line);background:#fff;border-radius:8px;padding:6px 10px;font-size:12px;font-weight:700;cursor:pointer;color:var(--ink)}
  .btn-sm.ok{color:var(--ok);border-color:#bfe0cd}.btn-sm.no{color:var(--danger);border-color:#e6c3c3}
  .seller{display:flex;justify-content:space-between;align-items:center;padding:13px 0;border-top:1px solid var(--line)}
  .seller:first-child{border-top:none}.seller .nm{font-weight:700}.seller .meta{font-size:12.5px;color:var(--muted)}
  .star{color:var(--warn);font-weight:700;font-size:13px;white-space:nowrap}
  .empty{padding:30px 20px;text-align:center;color:var(--muted);font-size:14px}
  .empty svg{width:32px;height:32px;opacity:.6;margin-bottom:8px}.empty.hide{display:none}
  .toast{position:fixed;top:22px;right:22px;background:var(--ok);color:#fff;padding:14px 18px;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);font-size:14px;font-weight:600;display:flex;align-items:center;gap:10px;transform:translateX(140%);transition:.4s;z-index:120}
  .toast.show{transform:translateX(0)}
  .dropdown{position:absolute;top:52px;right:0;width:300px;background:#fff;border-radius:14px;box-shadow:0 14px 40px rgba(74,47,34,.18);padding:8px;display:none;z-index:50}
  .dropdown.show{display:block}.dropdown h4{font-size:13px;color:var(--muted);padding:10px 12px 6px}
  .noti{display:flex;gap:10px;padding:11px 12px;border-radius:10px}.noti:hover{background:var(--cream-2)}
  .noti .dot{width:8px;height:8px;border-radius:50%;background:var(--warn);margin-top:6px;flex-shrink:0}
  .noti .txt{font-size:13px}.noti .txt small{color:var(--muted);display:block;margin-top:2px}
  .pos{position:relative}
  .modal-bg{position:fixed;inset:0;background:rgba(40,26,18,.5);display:none;align-items:center;justify-content:center;z-index:130;padding:20px}
  .modal-bg.show{display:flex}
  .modal{background:#fff;border-radius:16px;padding:26px;max-width:400px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.3)}
  .modal .mi{width:48px;height:48px;border-radius:50%;background:#f6e0e0;color:var(--danger);display:flex;align-items:center;justify-content:center;margin-bottom:14px}
  .modal h3{font-size:18px;margin-bottom:8px}.modal p{font-size:14px;color:var(--muted);margin-bottom:20px}
  .modal .actions{display:flex;gap:10px}
  .modal button{flex:1;padding:11px;border-radius:10px;font-weight:700;font-size:14px;cursor:pointer;border:1px solid var(--line);background:#fff;color:var(--ink)}
  .modal button.danger{background:var(--danger);color:#fff;border-color:var(--danger)}
  @media(max-width:1050px){.stats{grid-template-columns:repeat(2,1fr)}.grid{grid-template-columns:1fr}}
  @media(max-width:820px){
    .sidebar{position:fixed;left:0;top:0;transform:translateX(-100%);transition:.3s;box-shadow:0 0 40px rgba(0,0,0,.3)}
    .sidebar.open{transform:translateX(0)}
    .backdrop.show{display:block;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:55}
    .hamburger{display:flex}.main{margin-left:0;padding:18px}.who .n,.who .r{display:none}
  }
</style>

<link rel="stylesheet" href="responsive-ui.css">
<link rel="stylesheet" href="dashboard-navigation.css">
<link rel="stylesheet" href="inventory-ui.css">
<link rel="stylesheet" href="procurement-finance.css">
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
    <a data-view="dashboard" class="active" aria-current="page"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg> Manager Dashboard</a>

    <div class="nav-divider"></div>
    <div class="nav-section-label">Procurement</div>
    <a data-view="procure"><svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/></svg> Procurement</a>
    <a data-view="reqs"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 15l2 2 4-4"/></svg> Stock Approvals<?= $gateCount > 0 ? ' <span class="pill warn" style="margin-left:6px">'.$gateCount.'</span>' : '' ?></a>
    <a data-view="ship"><svg viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg> Shipment Tracking</a>
    <a data-view="qc"><svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg> Quality Check</a>

    <div class="nav-divider"></div>
    <div class="nav-section-label">Manage</div>
    <a data-view="sellers"><svg viewBox="0 0 24 24"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.6A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg> Seller Contacts</a>
    <a data-view="items"><svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/></svg> Items &amp; Categories</a>
    <a href="returns.php"><svg viewBox="0 0 24 24"><path d="M3 3v18h18M7 12l4-4 4 4M11 8v8"/></svg> Returns to Supplier</a>
    <a href="stocktake.php"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4M11 8v6M8 11h6"/></svg> Stocktaking</a>
    <a href="warehouse.php"><svg viewBox="0 0 24 24"><path d="M3 21V8l9-5 9 5v13M3 21h18M7 21v-8M12 21v-8M17 21v-8"/></svg> Warehouse</a>

    <div class="nav-divider"></div>
    <div class="nav-section-label">Monitor</div>
    <a data-view="oversight"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> Clerk Oversight</a>
    <a data-view="profile"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></svg> My Profile</a>

    <div class="nav-divider"></div>
    <div class="nav-section-label">Account</div>
    <a href="logout.php"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg> Logout</a>
  </nav></aside>

<main class="main">
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:14px">
      <button class="hamburger" onclick="openSidebar()" aria-label="Open menu"><svg viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button>
      <div><h1 id="pageTitle">Manager Dashboard</h1><div class="sub" id="pageSub">Procurement, quality &amp; oversight</div></div>
    </div>
    <div class="user-area">
      <?= notification_bell($u['role']) ?>
      <div class="who">
        <div class="avatar"><?= $initials ?></div>
        <div><div class="n"><?= htmlspecialchars($user['firstName'].' '.$user['lastName']) ?></div><div class="r"><?= htmlspecialchars($user['role']) ?></div></div>
      </div>
    </div>
  </div>

  <section class="view active" id="view-dashboard">
    <!-- Stat Cards Row -->
    <div class="stats">
      <div class="stat"><div class="t">Pending Requests</div><div class="v warn"><?= $pending ?></div></div>
      <div class="stat"><div class="t">Active Sellers</div><div class="v"><?= $activeSellers ?></div></div>
      <div class="stat"><div class="t">QC Pending</div><div class="v warn"><?= $qcPending ?></div></div>
      <div class="stat"><div class="t">Incoming Shipments</div><div class="v"><?= $incoming ?></div></div>
    </div>

    <!-- 2-Column Grid: Quick Actions + Needs Attention -->
    <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 20px;">
      <!-- Left: Quick Actions -->
      <div class="panel">
        <h2>Quick Actions</h2>
        <div class="desc">Common tasks to get started</div>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 16px;">
          <button class="btn-primary" style="height: 100px; flex-direction: column; gap: 8px; font-size: 13px;" onclick="showView('procure')">
            <svg viewBox="0 0 24 24" style="width: 28px; height: 28px;"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/></svg>
            New Purchase Request
          </button>
          <button class="btn-primary" style="height: 100px; flex-direction: column; gap: 8px; font-size: 13px; background: var(--cream-2); color: var(--ink); border: 2px solid var(--line);" onclick="showView('qc')">
            <svg viewBox="0 0 24 24" style="width: 28px; height: 28px;"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            Quality Check
          </button>
          <button class="btn-primary" style="height: 100px; flex-direction: column; gap: 8px; font-size: 13px; background: var(--cream-2); color: var(--ink); border: 2px solid var(--line);" onclick="showView('ship')">
            <svg viewBox="0 0 24 24" style="width: 28px; height: 28px;"><rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            Shipment Tracking
          </button>
          <button class="btn-primary" style="height: 100px; flex-direction: column; gap: 8px; font-size: 13px; background: var(--cream-2); color: var(--ink); border: 2px solid var(--line);" onclick="showView('items')">
            <svg viewBox="0 0 24 24" style="width: 28px; height: 28px;"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
            Items & Categories
          </button>
        </div>
      </div>

      <!-- Right: Low Stock Alerts -->
      <div class="panel">
        <h2>⚠ Low Stock Alerts</h2>
        <div class="desc">Items at or below reorder level</div>
        <?php $attention = array_filter($items, function($it) { return $it['is_active'] && $it['current_qty'] <= $it['reorder_level']; }); ?>
        <?php if ($attention): ?>
        <div style="margin-top: 16px;">
          <?php foreach ($attention as $it): ?>
          <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: var(--cream-2); border-radius: 10px; margin-bottom: 10px; border-left: 4px solid var(--warn);">
            <div>
              <div style="font-weight: 700; font-size: 14px;"><?= htmlspecialchars($it['name']) ?></div>
              <div style="font-size: 12px; color: var(--muted);"><?= $it['current_qty'] ?> <?= $it['unit'] ?> / <?= $it['reorder_level'] ?> <?= $it['unit'] ?></div>
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

    <!-- Recent Activity (Full Width) -->
    <div class="panel" style="margin-top: 20px;">
      <h2>Recent Stock Movements</h2>
      <div class="desc">Latest activity from your clerks</div>
      <?php if (!empty($clerkLogs)): ?>
      <div class="table-wrap" style="margin-top: 16px;">
        <table>
          <thead><tr><th>Date</th><th>Clerk</th><th>Action</th><th>Item</th></tr></thead>
          <tbody>
            <?php foreach (array_slice($clerkLogs, 0, 5) as $c): ?>
            <tr>
              <td><?= htmlspecialchars($c['date']) ?></td>
              <td><?= htmlspecialchars($c['clerk']) ?></td>
              <td><span class="tag <?= strtolower(str_replace(' ', '', $c['action'])) ?>"><?= htmlspecialchars($c['action']) ?></span></td>
              <td><?= htmlspecialchars($c['item']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="empty" style="padding: 40px;">No recent activity.</div>
      <?php endif; ?>
    </div>
  </section>

  <section class="view" id="view-procure">
    <div class="grid">
      <div class="panel">
        <h2>Purchase Requests</h2>
        <div class="desc">Requests routed to the Finance Team for budget approval.</div>
        <div class="table-wrap">
          <table class="dt">
            <thead><tr><th>Ref</th><th>Item</th><th>Qty</th><th>Seller</th><th>Est. Cost</th><th>Status</th><th>Letter</th><th></th></tr></thead>
            <tbody>
              <?php foreach($requests as $r): ?>
              <tr><td><b><?= $r['id'] ?></b></td><td><?= htmlspecialchars($r['item']) ?></td><td><?= htmlspecialchars($r['qty']) ?></td>
              <td><?= htmlspecialchars($r['seller']) ?></td><td>₱<?= number_format($r['est']) ?></td>
              <td><span class="pill <?= badgeCls($r['status']) ?>"><?= $r['status'] ?></span></td>
              <td><?php if(!empty($r['letter_path'])): ?><a class="btn-sm" href="<?= htmlspecialchars($r['letter_path']) ?>" target="_blank" rel="noopener" style="text-decoration:none;display:inline-block">📄 Letter</a><?php else: ?><span style="color:var(--muted)">—</span><?php endif; ?></td>
              <td><button class="btn-sm" onclick="showPR('<?= htmlspecialchars($r['id']) ?>','<?= htmlspecialchars($r['item']) ?>','<?= htmlspecialchars($r['qty']) ?>','<?= htmlspecialchars($r['seller']) ?>',<?= $r['est'] ?>,<?= (int)$r['est'] ?>,'<?= $r['status'] ?>')">Details</button></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="panel">
        <h2>New Purchase Request</h2>
        <div class="desc">Send to Finance for budget approval.</div>
        <form id="prForm" method="post" action="inventory-manager.php" enctype="multipart/form-data" novalidate onsubmit="return submitPR(event)"><?= token_field() ?>
          <div class="field" id="p-item"><label for="pi">Item <span class="req">*</span></label>
            <select id="pi" name="item" required onchange="toggleOtherItem(); fillItemSuggestion()"><option value="">— Select item —</option>
              <?php foreach($items as $it): if(!$it['is_active']) continue; $sugg = max(1, ($it['reorder_level']*2) - $it['current_qty']); ?>
              <option value="<?= htmlspecialchars($it['name']) ?>" data-qty="<?= (int)$sugg ?>" data-unit="<?= htmlspecialchars($it['unit']) ?>" data-cur="<?= (float)$it['current_qty'] ?>" data-reo="<?= (float)$it['reorder_level'] ?>" data-cost="<?= (float)$it['cost'] ?>"><?= htmlspecialchars($it['name']) ?> (<?= (float)$it['current_qty'] ?> <?= htmlspecialchars($it['unit']) ?> left, reorder <?= (float)$it['reorder_level'] ?>)</option>
              <?php endforeach; ?>
              <option value="__other__">Other needs…</option>
            </select>
            <div id="suggHint" style="display:none;margin-top:8px;font-size:13px;color:var(--ok);background:#e2f0e8;border-radius:8px;padding:8px 10px"></div>
            <div id="otherItemWrap" style="display:none;margin-top:8px">
              <input type="text" name="other_item" id="otherItem" placeholder="Type the item you need...">
            </div>
            <div class="err">⚠ Please select or type an item.</div></div>
          <div class="row2">
            <div class="field" id="p-qty"><label for="pq">Quantity to request (kg / L / pcs) <span class="req">*</span></label><input id="pq" type="number" name="qty" min="1" max="500" placeholder="Type how many you need, e.g. 50"><div class="err">⚠ Enter a quantity greater than 0.</div></div>
            <div class="field" id="p-unit"><label for="pu">Unit <span class="req">*</span></label><select id="pu" name="unit_display"><option value="">—</option><option>kg</option><option>L</option><option>pcs</option><option>bottle</option></select>
              <input type="hidden" name="unit" id="puHidden"><div class="err">⚠ Select a unit.</div></div>
          </div>
          <div class="field" id="p-seller"><label for="ps">Seller <span class="req">*</span></label><select id="ps" name="seller"><option value="">— Select seller —</option><?php foreach($sellers as $s): if(!$s['is_active']) continue; ?><option><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?></select><div class="err">⚠ Please choose a seller.</div></div>
          <div class="field" id="p-cost"><label for="pc">Estimated Cost (₱) <span class="req">*</span></label><input id="pc" type="number" name="cost" min="1" placeholder="e.g. 18500"><div class="err">⚠ Enter an estimated cost.</div></div>
          <div class="field" id="p-letter">
            <label>Formal Letter to Finance <span class="req">*</span></label>
            <div class="desc" style="margin:-2px 0 8px">Auto-generated from the fields above as a Word document (.docx). When you submit, you will sign it — your e-signature is placed on the letter above your full name and a security photo is captured with it.</div>
            <div id="prLetterPaper" style="background:#fff;border:1px solid var(--line,#e5dcc8);border-radius:8px;padding:20px 24px;font-family:Georgia,'Times New Roman',serif;font-size:12.5px;line-height:1.6;color:#1f1a12;white-space:pre-line;max-height:260px;overflow:auto"></div>
            <div style="display:flex;gap:8px;margin-top:8px">
              <button type="button" class="btn-sm" onclick="openPRLetter()">👁 View Full Letter</button>
              <button type="button" class="btn-sm" onclick="downloadPRLetter()">⬇ Download .docx</button>
            </div>
            <input type="file" name="letter" id="prLetterFile" accept=".docx,.doc,.pdf" style="display:none">
            <input type="file" name="signature" id="prSigFile" accept=".png" style="display:none">
            <input type="file" name="selfie" id="prSelfieFile" accept=".jpg,.jpeg" style="display:none">
          </div>
          <button type="submit" class="btn-primary" id="prBtn"><span class="spinner"></span><span class="btxt">Submit to Finance</span></button>
        </form>
      </div>
    </div>
  </section>

  <section class="view" id="view-reqs">
    <div class="grid">
      <div class="panel">
        <h2>Clerk Stock Requests — Admin Approval Gate</h2>
        <div class="desc">Clerk stock requests must be approved here FIRST. Approved requests are released to the warehouse queue; rejected ones go back to the clerk and never reach the warehouse.</div>
        <?php if(!$gateRequests): ?><div class="empty">🎉 No stock requests waiting for admin approval.</div><?php else: ?>
        <div class="table-wrap"><table>
          <thead><tr><th>Ref</th><th>Item</th><th>Qty</th><th>Requested by</th><th>Date</th><th style="white-space:nowrap">Action</th></tr></thead>
          <tbody>
          <?php foreach($gateRequests as $gr): ?>
            <tr>
              <td><b><?= htmlspecialchars($gr['ref']) ?></b></td>
              <td><?= htmlspecialchars($gr['item']) ?><?= $gr['note'] ? '<br><small style="color:var(--muted)">'.htmlspecialchars($gr['note']).'</small>' : '' ?></td>
              <td><?= (float)$gr['qty'] ?> <?= htmlspecialchars($gr['unit']) ?></td>
              <td><?= htmlspecialchars($gr['clerk']) ?></td>
              <td><?= htmlspecialchars($gr['date']) ?></td>
              <td style="white-space:nowrap;display:flex;gap:6px">
                <form method="post" action="inventory-manager.php" style="display:inline"><?= token_field() ?>
                  <input type="hidden" name="gate_id" value="<?= (int)$gr['id'] ?>"><input type="hidden" name="gate_action" value="approve">
                  <button class="btn-sm ok" type="submit" onclick="return sweetConfirmSubmit(event,'Approve this stock request? It will be released to the warehouse queue.')">Approve</button>
                </form>
                <form method="post" action="inventory-manager.php" style="display:inline"><?= token_field() ?>
                  <input type="hidden" name="gate_id" value="<?= (int)$gr['id'] ?>"><input type="hidden" name="gate_action" value="reject">
                  <button class="btn-sm" type="submit" onclick="return sweetConfirmSubmit(event,'Reject this stock request? It will go back to the clerk and will NOT reach the warehouse.')">Reject</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <?php endif; ?>
      </div>
      <div class="panel">
        <h2>Gate History</h2>
        <div class="desc">Last 50 admin decisions on clerk stock requests.</div>
        <?php if(!$gateDecided): ?><div class="empty">No decisions yet.</div><?php else: ?>
        <div class="table-wrap"><table>
          <thead><tr><th>Ref</th><th>Item</th><th>Qty</th><th>Clerk</th><th>Result</th><th>Date</th></tr></thead>
          <tbody>
          <?php foreach($gateDecided as $gd): ?>
            <tr>
              <td><b><?= htmlspecialchars($gd['ref']) ?></b></td>
              <td><?= htmlspecialchars($gd['item']) ?></td>
              <td><?= (float)$gd['qty'] ?> <?= htmlspecialchars($gd['unit']) ?></td>
              <td><?= htmlspecialchars($gd['clerk']) ?></td>
              <td><span class="pill <?= $gd['admin_status']==='approved' ? 'ok' : 'danger' ?>"><?= $gd['admin_status']==='approved' ? 'Sent to warehouse' : 'Returned to clerk' ?></span></td>
              <td><?= htmlspecialchars($gd['date']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="view" id="view-sellers">
    <div class="grid">
      <div class="panel">
        <h2>Seller Contacts</h2>
        <div class="desc">Manage your suppliers. Inactive sellers are hidden from the Purchase Request form.</div>
        <div class="tools"><div class="search"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg><input id="sellSearch" placeholder="Search seller..." aria-label="Search sellers" onkeyup="filterSellers()"></div></div>
        <div class="table-wrap">
          <table id="sellerTbl">
            <thead><tr><th>Seller</th><th>Contact</th><th>Goods</th><th>Rating</th><th>On-time</th><th>Rejects</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach($sellers as $s): ?>
              <tr>
                <td><b><?= htmlspecialchars($s['name']) ?></b><br><small style="color:var(--muted)"><?= htmlspecialchars($s['phone']) ?></small></td>
                <td><?= htmlspecialchars($s['contact']) ?></td>
                <td><?= htmlspecialchars($s['goods']) ?></td>
                <td class="star">★ <?= $s['rating'] ?></td>
                <td>
                  <?php if($s['ontime_pct'] !== null): ?>
                    <span class="pill <?= $s['ontime_pct']>=80?'ok':($s['ontime_pct']>=50?'warn':'danger') ?>"><?= $s['ontime_pct'] ?>%</span>
                  <?php else: ?><span style="color:var(--muted)">—</span><?php endif; ?>
                </td>
                <td>
                  <?php if($s['reject_pct'] !== null): ?>
                    <span class="pill <?= $s['reject_pct']<=10?'ok':($s['reject_pct']<=25?'warn':'danger') ?>"><?= $s['reject_pct'] ?>%</span>
                  <?php else: ?><span style="color:var(--muted)">—</span><?php endif; ?>
                </td>
                <td><span class="pill <?= $s['is_active'] ? 'ok' : 'warn' ?>"><?= $s['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                <td style="white-space:nowrap">
                  <button class="btn-sm" onclick="openSellerEdit(<?= (int)$s['id'] ?>,<?= htmlspecialchars(json_encode($s['name'])) ?>,<?= htmlspecialchars(json_encode($s['contact'])) ?>,<?= htmlspecialchars(json_encode($s['phone'])) ?>,<?= htmlspecialchars(json_encode($s['goods'])) ?>,<?= $s['rating'] ?: 'null' ?>)">Edit</button>
                  <form method="post" action="inventory-manager.php" style="display:inline" onsubmit="return sweetConfirmSubmit(event,'Are you sure?')"><?= token_field() ?>
                    <input type="hidden" name="seller_action" value="toggle_seller"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                    <button class="btn-sm <?= $s['is_active'] ? 'no' : 'ok' ?>"><?= $s['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="empty hide" id="sellerEmpty"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg><div>No sellers match your search.</div></div>
      </div>
      <div class="panel">
        <h2>Add New Seller</h2>
        <div class="desc">Register a supplier for procurement.</div>
        <form method="post" action="inventory-manager.php"><?= token_field() ?>
          <input type="hidden" name="seller_action" value="add_seller">
          <div class="field"><label>Seller name <span class="req">*</span></label><input name="sname" required placeholder="e.g. GreenFields Trading"></div>
          <div class="field"><label>Contact person</label><input name="scontact" placeholder="e.g. Ms. Anna"></div>
          <div class="field"><label>Phone</label><input name="sphone" placeholder="e.g. 0917-555-0000"></div>
          <div class="field"><label>Goods / products</label><input name="sgoods" placeholder="e.g. Sugars, syrups"></div>
          <div class="field"><label>Rating (1-5)</label><input name="srating" type="number" min="0" max="5" step="0.1" placeholder="e.g. 4.5"></div>
          <button class="btn-primary" type="submit">+ Add Seller</button>
        </form>
      </div>
    </div>
  </section>

  <section class="view" id="view-qc">
    <div class="panel">
      <h2>Quality Verification</h2>
      <div class="desc">Verify goods received from sellers before they reach the clerk's stock.</div>
      <div class="table-wrap">
        <table class="dt">
          <thead><tr><th>Item</th><th>Seller</th><th>Received</th><th>Result</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach($qc as $q): ?>
            <tr><td><b><?= htmlspecialchars($q['item']) ?></b></td><td><?= htmlspecialchars($q['seller']) ?></td><td><?= $q['received'] ?></td>
            <td><span class="pill <?= badgeCls($q['status']) ?>"><?= $q['status'] ?></span></td>
            <td><?php if($q['status']==='pending'): ?>
              <form method="post" action="inventory-manager.php" style="display:inline"><?= token_field() ?>
                <input type="hidden" name="qc_id" value="<?= (int)$q['id'] ?>">
                <input type="hidden" name="qc_result" value="passed">
                <button class="btn-sm ok" type="submit">Pass</button>
              </form>
              <form method="post" action="inventory-manager.php" style="display:inline"><?= token_field() ?>
                <input type="hidden" name="qc_id" value="<?= (int)$q['id'] ?>">
                <input type="hidden" name="qc_result" value="failed">
                <button class="btn-sm no" type="submit">Fail</button>
              </form>
            <?php else: ?><span style="color:var(--muted);font-size:12px">Done</span><?php endif; ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="view" id="view-ship">
    <div class="grid">
      <div class="panel">
        <h2>Shipment Tracking</h2>
        <div class="desc">Purchase requests approved by Finance appear here automatically — set their delivery date, then advance each shipment through its journey. Marking it Delivered creates a Quality Check.</div>
        <div class="table-wrap">
          <table class="dt">
            <thead><tr><th>Ref</th><th>Item</th><th>Seller</th><th>ETA</th><th>Status</th><th>Shortage</th><th>Action</th></tr></thead>
            <tbody>
              <?php foreach($shipments as $s): ?>
              <tr>
                <td><b><?= $s['ref'] ?></b><?= !empty($s['pr_code']) ? '<br><small style="color:var(--accent);font-weight:700">from '.htmlspecialchars($s['pr_code']).'</small>' : '' ?></td>
                <td><?= htmlspecialchars($s['item']) ?></td>
                <td><?= htmlspecialchars($s['seller']) ?></td>
                <td>
                  <?php if($s['eta']): ?><?= $s['eta'] ?>
                  <?php else: ?>
                    <form method="post" action="inventory-manager.php" style="display:flex;gap:4px;align-items:center;flex-wrap:wrap"><?= token_field() ?>
                      <input type="hidden" name="ship_action" value="set_eta">
                      <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                      <input type="date" name="eta" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required style="padding:4px 6px;border:1px solid var(--line);border-radius:8px;font:inherit;font-size:12px;background:#fff">
                      <button class="btn-sm" type="submit" onclick="return sweetConfirmSubmit(event,'Set this delivery date?')">Set date</button>
                    </form>
                    <div style="font-size:11px;color:var(--warn);font-weight:700;margin-top:2px">approved — set delivery date</div>
                  <?php endif; ?>
                </td>
                <td><span class="pill <?= badgeCls($s['status']) ?>"><?= $s['status'] ?></span></td>
                <td>
                  <?php if($s['shortage_qty'] > 0): ?>
                    <span class="pill <?= $s['shortage_status']==='pending'?'warn':($s['shortage_status']==='backordered'?'ok':'danger') ?>">
                      <?= $s['shortage_qty'] ?> — <?= ucfirst($s['shortage_status']) ?>
                    </span>
                    <?php if($s['shortage_status']==='pending'): ?>
                    <div style="margin-top:4px;display:flex;gap:4px">
                      <form method="post" action="inventory-manager.php" style="display:inline"><?= token_field() ?>
                        <input type="hidden" name="ship_action" value="resolve_shortage"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="resolution" value="backordered">
                        <button class="btn-sm" type="submit" title="Supplier will send the rest">Backorder</button>
                      </form>
                      <form method="post" action="inventory-manager.php" style="display:inline"><?= token_field() ?>
                        <input type="hidden" name="ship_action" value="resolve_shortage"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="resolution" value="credited">
                        <button class="btn-sm" type="submit" title="Supplier deducts the missing amount">Credit</button>
                      </form>
                    </div>
                    <?php endif; ?>
                  <?php else: ?><span style="color:var(--muted)">—</span><?php endif; ?>
                </td>
                <td>
                  <?php if($s['status'] === 'Delivered'): ?>
                    <button class="btn-sm ok" onclick="openReceive(<?= (int)$s['id'] ?>,<?= htmlspecialchars(json_encode($s['ref'])) ?>,<?= htmlspecialchars(json_encode($s['item'])) ?>,<?= $s['qty'] ?>)">Receive</button>
                  <?php elseif($s['status'] === 'Received'): ?>
                    <span style="color:var(--muted);font-size:12px">✓ Received</span>
                  <?php else: ?>
                    <form method="post" action="inventory-manager.php" style="display:inline"><?= token_field() ?>
                      <input type="hidden" name="ship_action" value="advance_shipment">
                      <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                      <button class="btn-sm ok" type="submit">
                        <?= $s['status']==='In transit' ? '→ Out for delivery' : '→ Delivered' ?>
                      </button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="panel">
        <h2>Add Shipment</h2>
        <div class="desc">Register incoming goods from a seller (starts In transit).</div>
        <form method="post" action="inventory-manager.php"><?= token_field() ?>
          <input type="hidden" name="ship_action" value="add_shipment">
          <div class="field"><label>Item <span class="req">*</span></label>
            <select name="ship_item" required><option value="">— Select item —</option>
              <?php foreach($items as $it): if(!$it['is_active']) continue; ?>
              <option value="<?= htmlspecialchars($it['name']) ?>"><?= htmlspecialchars($it['name']) ?> (<?= $it['unit'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label>Seller <span class="req">*</span></label>
            <select name="ship_seller" required><option value="">— Select seller —</option>
              <?php foreach($sellers as $s): if(!$s['is_active']) continue; ?>
              <option value="<?= htmlspecialchars($s['name']) ?>"><?= htmlspecialchars($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row2">
            <div class="field"><label>Quantity <span class="req">*</span></label><input name="ship_qty" type="number" min="1" max="500" required placeholder="e.g. 50"></div>
            <div class="field"><label>ETA <span class="req">*</span></label><input name="ship_eta" type="date" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required></div>
          </div>
          <button class="btn-primary" type="submit">+ Add Shipment</button>
        </form>
      </div>
    </div>
  </section>

  <section class="view" id="view-items">
    <div class="grid">
      <div class="panel">
        <h2>Stock Items</h2>
        <div class="desc">Add, edit, or deactivate the ingredients &amp; supplies in your inventory.</div>
        <div class="table-wrap">
          <table class="dt">
            <thead><tr><th>Code</th><th>Item</th><th>Category</th><th>Qty</th><th>Unit Cost</th><th>Stock Value</th><th>Reorder</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php $stockValue = 0; foreach($items as $it): $stockValue += $it['current_qty'] * $it['cost']; ?>
              <tr>
                <td><small style="color:var(--muted)"><?= htmlspecialchars($it['code']) ?></small></td>
                <td><b><?= htmlspecialchars($it['name']) ?></b></td>
                <td><?= htmlspecialchars($it['category']) ?></td>
                <td><?= $it['current_qty'] ?> <?= htmlspecialchars($it['unit']) ?></td>
                <td>₱<?= number_format($it['cost']) ?><?= $it['cost']>0 ? '/'.htmlspecialchars($it['unit']) : '' ?>
                  <?php if(($it['cost_status'] ?? 'approved')==='pending'): ?><span class="pill warn" style="margin-left:6px">Price pending</span><?php endif; ?>
                </td>
                <td><b>₱<?= number_format($it['current_qty'] * $it['cost']) ?></b></td>
                <td><?= $it['reorder_level'] ?> <?= htmlspecialchars($it['unit']) ?></td>
                <td><span class="pill <?= $it['is_active'] ? 'ok' : 'danger' ?>"><?= $it['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                <td style="white-space:nowrap">
                  <button class="btn-sm" onclick="openItemEdit(<?= (int)$it['id'] ?>,<?= htmlspecialchars(json_encode($it['name'])) ?>,<?= htmlspecialchars(json_encode($it['unit'])) ?>,<?= (int)$it['reorder_level'] ?>,<?= htmlspecialchars(json_encode($it['category'])) ?>,<?= $it['cost'] ?>)">Edit</button>
                  <form method="post" action="inventory-manager.php" style="display:inline" onsubmit="return sweetConfirmSubmit(event,'Are you sure?')"><?= token_field() ?>
                    <input type="hidden" name="item_action" value="toggle_item"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                    <button class="btn-sm <?= $it['is_active'] ? 'no' : 'ok' ?>"><?= $it['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot><tr style="border-top:2px solid var(--line);font-weight:800"><td colspan="5">Total Stock Value</td><td>₱<?= number_format($stockValue) ?></td><td colspan="3"></td></tr></tfoot>
          </table>
        </div>
      </div>
      <div>
        <div class="panel">
          <h2>Add New Item</h2>
          <div class="desc">Register a new stock item.</div>
          <form method="post" action="inventory-manager.php"><?= token_field() ?>
            <input type="hidden" name="item_action" value="add_item">
            <div class="field"><label>Item name <span class="req">*</span></label><input name="iname" required placeholder="e.g. Vanilla Syrup"></div>
            <div class="row2">
              <div class="field"><label>Category <span class="req">*</span></label><select name="icat" required><option value="">—</option><?php foreach($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select></div>
              <div class="field"><label>Unit <span class="req">*</span></label><select name="iunit" required><option value="">—</option><option>kg</option><option>L</option><option>pcs</option><option>bottle</option></select></div>
            </div>
            <div class="row2">
              <div class="field"><label>Starting qty</label><input name="iqty" type="number" min="0" max="500" value="0"></div>
              <div class="field"><label>Unit cost (₱)</label><input name="icost" type="number" min="0" step="0.5" value="0" placeholder="e.g. 120"></div>
            </div>
            <div class="field"><label>Reorder level</label><input name="ireorder" type="number" min="0" value="0"></div>
            <button class="btn-primary" type="submit">+ Add Item</button>
          </form>
        </div>
        <div class="panel">
          <h2>Add Category</h2>
          <div class="desc">Create a new product category (e.g. Bakery, Toppings).</div>
          <form method="post" action="inventory-manager.php"><?= token_field() ?>
            <input type="hidden" name="item_action" value="add_category">
            <div class="field"><label>Category name <span class="req">*</span></label><input name="cname" required placeholder="e.g. Bakery"></div>
            <button class="btn-primary" type="submit">+ Add Category</button>
          </form>
        </div>
      </div>
    </div>
  </section>

  <section class="view" id="view-oversight">
    <div class="panel">
      <h2>Clerk Activity Oversight</h2>
      <div class="desc">Recent actions taken by inventory staff.</div>
      <div class="table-wrap">
        <table class="dt">
          <thead><tr><th>Date</th><th>Clerk</th><th>Action</th><th>Details</th></tr></thead>
          <tbody>
            <?php foreach($clerkLogs as $c): ?>
            <tr><td><?= $c['date'] ?></td><td><?= htmlspecialchars($c['clerk']) ?></td><td><?= htmlspecialchars($c['action']) ?></td><td style="color:var(--muted)"><?= htmlspecialchars($c['item']) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="view" id="view-profile">
    <div class="panel">
      <div style="display:flex;align-items:center;gap:18px;margin-bottom:20px">
        <div style="width:72px;height:72px;border-radius:50%;background:var(--brown-500);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:26px"><?= $initials ?></div>
        <div><h2><?= htmlspecialchars($user['firstName'].' '.$user['lastName']) ?></h2><div class="desc" style="margin:0"><?= htmlspecialchars($user['role']) ?></div></div>
      </div>
      <div style="display:flex;justify-content:space-between;padding:13px 0;border-top:1px solid var(--line);font-size:14px"><span style="color:var(--muted)">First name</span><span><?= htmlspecialchars($user['firstName']) ?></span></div>
      <div style="display:flex;justify-content:space-between;padding:13px 0;border-top:1px solid var(--line);font-size:14px"><span style="color:var(--muted)">Last name</span><span><?= htmlspecialchars($user['lastName']) ?></span></div>
      <div style="display:flex;justify-content:space-between;padding:13px 0;border-top:1px solid var(--line);font-size:14px"><span style="color:var(--muted)">Email</span><span><?= htmlspecialchars($u['email'] ?? '—') ?></span></div>
      <div style="display:flex;justify-content:space-between;padding:13px 0;border-top:1px solid var(--line);font-size:14px"><span style="color:var(--muted)">Role</span><span><?= htmlspecialchars($user['role']) ?></span></div>
    </div>
    <?= inv_signature_panel($u, 'inventory-manager.php') ?>
    <?= change_password_panel('inventory-manager.php') ?>
  </section>
</main>

<div class="toast" id="toast" role="status" aria-live="polite"><svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg> <span id="toastMsg">Saved!</span></div>

<div class="modal-bg" id="modalBg">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="mi"><svg viewBox="0 0 24 24"><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg></div>
    <h3 id="modalTitle">Please confirm</h3>
    <p id="modalMsg"></p>
    <div class="actions"><button onclick="closeModal()">Cancel</button><button class="danger" id="modalOk">Confirm</button></div>
  </div>
</div>

<!-- Edit item modal -->
<div class="modal-bg" id="itemEditBg">
  <div class="modal">
    <h3>Edit Item</h3>
    <form method="post" action="inventory-manager.php"><?= token_field() ?>
      <input type="hidden" name="item_action" value="edit_item">
      <input type="hidden" name="id" id="ieid">
      <div class="field"><label>Item name</label><input name="iname" id="iename" required></div>
      <div class="row2">
        <div class="field"><label>Category</label>
          <select name="icat" id="iecat" required><?php foreach($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="field"><label>Unit</label><select name="iunit" id="ieunit"><option>kg</option><option>L</option><option>pcs</option><option>bottle</option></select></div>
      </div>
      <div class="field"><label>Unit cost (₱)</label><input name="icost" id="iecost" type="number" min="0" step="0.5"></div>
      <div class="field"><label>Reorder level</label><input name="ireorder" id="iereorder" type="number" min="0"></div>
      <div class="actions"><button type="button" onclick="document.getElementById('itemEditBg').classList.remove('show')">Cancel</button><button type="submit" style="background:var(--accent);color:#fff;border-color:var(--accent)">Save</button></div>
    </form>
  </div>
</div>

<!-- Purchase request detail modal -->
<div class="modal-bg" id="prBg">
  <div class="modal">
    <h3>Purchase Request <span id="prRef"></span></h3>
    <div id="prBody" style="font-size:14px;color:var(--ink);line-height:1.9;margin:14px 0">
      <div><b>Item:</b> <span id="prItem"></span></div>
      <div><b>Quantity:</b> <span id="prQty"></span></div>
      <div><b>Seller:</b> <span id="prSeller"></span></div>
      <div><b>Estimated cost:</b> <span id="prCost"></span></div>
      <div><b>Status:</b> <span id="prStatus"></span></div>
      <div style="font-size:12px;color:var(--muted);margin-top:6px">Lifecycle: Created by Manager → sent to Finance for approval → approved/declined against budget.</div>
    </div>
    <div class="actions"><button onclick="document.getElementById('prBg').classList.remove('show')">Close</button></div>
  </div>
</div>

<!-- Receive shipment modal (GRN) -->
<div class="modal-bg" id="recvBg">
  <div class="modal">
    <h3>Receive Shipment</h3>
    <p id="recvInfo"></p>
    <form method="post" action="inventory-manager.php"><?= token_field() ?>
      <input type="hidden" name="ship_action" value="receive_shipment">
      <input type="hidden" name="id" id="reid">
      <div class="field"><label>Qty received <span class="req">*</span></label><input name="recv_qty" id="rqty" type="number" min="1" max="500" step="any" required></div>
      <p style="font-size:12px;color:var(--muted);margin-bottom:14px">This adds the quantity to your stock and records a GRN. Any shortage/overage is flagged.</p>
      <div class="actions"><button type="button" onclick="document.getElementById('recvBg').classList.remove('show')">Cancel</button><button type="submit" style="background:var(--ok);color:#fff;border-color:var(--ok)">Receive &amp; Add to Stock</button></div>
    </form>
  </div>
</div>

<!-- Edit seller modal -->
<div class="modal-bg" id="sellerEditBg">
  <div class="modal">
    <h3>Edit Seller</h3>
    <form method="post" action="inventory-manager.php"><?= token_field() ?>
      <input type="hidden" name="seller_action" value="edit_seller">
      <input type="hidden" name="id" id="seid">
      <div class="field"><label>Seller name</label><input name="sname" id="sename" required></div>
      <div class="field"><label>Contact person</label><input name="scontact" id="secontact"></div>
      <div class="field"><label>Phone</label><input name="sphone" id="sephone"></div>
      <div class="field"><label>Goods / products</label><input name="sgoods" id="segoods"></div>
      <div class="field"><label>Rating (1-5)</label><input name="srating" id="serating" type="number" min="0" max="5" step="0.1"></div>
      <div class="actions"><button type="button" onclick="document.getElementById('sellerEditBg').classList.remove('show')">Cancel</button><button type="submit" style="background:var(--accent);color:#fff;border-color:var(--accent)">Save</button></div>
    </form>
  </div>
</div>

<script>
const VIEW_META={
  dashboard:{title:'Manager Dashboard',sub:'Procurement, quality &amp; oversight'},
  procure:{title:'Procurement',sub:'Purchase requests to Finance'},
  reqs:{title:'Stock Approvals',sub:'Admin gate — clerk stock requests must be approved before they reach the warehouse'},
  sellers:{title:'Seller Contacts',sub:'Your saved suppliers'},
  qc:{title:'Quality Check',sub:'Verify goods from sellers'},
  ship:{title:'Shipment Tracking',sub:'Incoming ingredient shipments'},
  items:{title:'Items & Categories',sub:'Manage inventory items and product categories'},
  oversight:{title:'Clerk Oversight',sub:'Recent inventory staff activity'},
  profile:{title:'My Profile',sub:'Your account details'}
};
function showView(name){
  document.querySelectorAll('.view').forEach(v=>v.classList.remove('active'));
  const el=document.getElementById('view-'+name); if(el)el.classList.add('active');
  document.querySelectorAll('.nav a[data-view]').forEach(a=>{
    a.classList.toggle('active',a.dataset.view===name);
    if(a.dataset.view===name)a.setAttribute('aria-current','page');else a.removeAttribute('aria-current');
  });
  const m=VIEW_META[name];if(m){document.getElementById('pageTitle').textContent=m.title;document.getElementById('pageSub').innerHTML=m.sub;}
  location.hash=name;closeSidebar();window.scrollTo({top:0,behavior:'smooth'});
}
document.querySelectorAll('.nav a[data-view]').forEach(a=>a.addEventListener('click',e=>{e.preventDefault();showView(a.dataset.view);}));
window.addEventListener('DOMContentLoaded',()=>{
  // Check hash first, then query parameter
  const h=location.hash.replace('#','');
  const urlParams = new URLSearchParams(location.search);
  const viewFromQuery = urlParams.get('view');
  const viewToShow = h || viewFromQuery || 'dashboard';
  
  if(viewToShow && VIEW_META[viewToShow]) showView(viewToShow);
  
  if(location.search.includes('bad=1') && location.search.includes('msg=')){
    toast(decodeURIComponent(location.search.split('msg=')[1].split('&')[0]), true);
  } else if(location.search.includes('saved=1')) toast('Changes saved!');
  else if(location.search.includes('bad=1')) toast('Something went wrong. Please try again.', true);
});

function goHome(e){ if(e) e.preventDefault(); showView('dashboard'); }
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('backdrop').classList.add('show');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('backdrop').classList.remove('show');}
function toggleNoti(e){e.stopPropagation();document.getElementById('notiBox').classList.toggle('show');}
document.addEventListener('click',()=>document.getElementById('notiBox').classList.remove('show'));
function toast(msg,bad){const t=document.getElementById('toast');t.style.background=bad?'#a23232':'#256b4d';document.getElementById('toastMsg').textContent=msg;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),2800);}
let modalCallback=null;
function askConfirm(msg,cb){document.getElementById('modalMsg').textContent=msg;modalCallback=cb;document.getElementById('modalBg').classList.add('show');}
function closeModal(){document.getElementById('modalBg').classList.remove('show');modalCallback=null;}
document.getElementById('modalOk').onclick=()=>{if(modalCallback)modalCallback();closeModal();};
document.getElementById('modalBg').addEventListener('click',e=>{if(e.target.id==='modalBg')closeModal();});
document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeModal();closeSidebar();}});
function setErr(id,bad){document.getElementById(id).classList.toggle('invalid',bad);}
function submitPR(e){
  e.preventDefault();const f=e.target;let ok=true;
  // Validate item: must be selected, or "Other needs" must have a typed value
  const itemSel=f.item.value;
  const isOther = itemSel==='__other__';
  const otherVal = isOther ? (f.other_item?f.other_item.value.trim():'') : '';
  setErr('p-item', (!itemSel) || (isOther && !otherVal));
  if(!itemSel || (isOther && !otherVal)) ok=false;
  // If Other needs, put the typed text into the item field so the server gets it
  if(isOther && otherVal) f.item.value = otherVal;
  const qty=parseInt(f.qty.value,10);const bq=!qty||qty<1;setErr('p-qty',bq);if(bq)ok=false;
  setErr('p-unit',!f.unit.value);if(!f.unit.value)ok=false;
  // if unit select is locked (disabled), read the hidden field instead
  if(ok && f.unit.disabled && f.unit.value==='' && document.getElementById('puHidden') && document.getElementById('puHidden').value){ f.unit.value=document.getElementById('puHidden').value; }
  setErr('p-seller',!f.seller.value);if(!f.seller.value)ok=false;
  const cost=parseFloat(f.cost.value);const bc=!cost||cost<1;setErr('p-cost',bc);if(bc)ok=false;
  if(!ok){toast('Please fix the highlighted fields.',true);return false;}
  // Valid -> nothing is sent yet: the manager must e-sign the letter first (signature + security photo)
  openPRSignModal();
  return false;
}
// ---- Auto formal letter for purchase requests (Finance) ----
const PR_AUTHOR = <?= json_encode(trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''))) ?>;
function prEsc(t){ return String(t == null ? '' : t).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function prGatherLetterData(){
  const f = document.getElementById('prForm');
  const sel = f.item;
  let itemName = '';
  if (sel.value === '__other__') itemName = (f.other_item ? f.other_item.value.trim() : '') || 'other item';
  else if (sel.value && sel.options[sel.selectedIndex]) itemName = sel.options[sel.selectedIndex].text;
  const qty = f.qty.value || '0';
  let unit = f.unit_display ? f.unit_display.value : '';
  if (!unit) { const h = document.getElementById('puHidden'); if (h) unit = h.value; }
  const seller = f.seller ? f.seller.value : '';
  const cost = parseFloat(f.cost.value || '0');
  const d = new Date();
  const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  return {
    dateLabel: months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear(),
    addressee: 'The Finance Officer',
    name: PR_AUTHOR,
    roleLabel: 'Inventory Manager',
    subject: 'Purchase Request \u2014 ' + (itemName || 'Inventory Item'),
    bodyParas: [
      'I am respectfully requesting approval to purchase the following inventory item for Brew & Co. Coffee Shop:',
      'Item: ' + (itemName || '(not yet selected)') + '\nQuantity: ' + qty + ' ' + unit + '\nPreferred Seller: ' + (seller || '(to be canvassed)') + '\nEstimated Cost: \u20b1' + (cost > 0 ? cost.toLocaleString('en-PH', {minimumFractionDigits: 2}) : '0.00'),
      'This purchase is necessary to maintain sufficient stock levels for daily operations. Upon your approval, the Finance Office may release the estimated amount indicated above, and the item will be received and recorded by the warehouse team.'
    ]
  };
}
function prLetterPreviewHtml(){
  const d = prGatherLetterData();
  const L = LetterGen.lines(d);
  L.push('Respectfully yours,');
  L.push('');
  L.push(String(d.name || '').toUpperCase());
  L.push(d.roleLabel || '');
  return prEsc(L.join('\n'));
}
function prRefreshLetterPreview(){
  const box = document.getElementById('prLetterPaper');
  if (box && window.LetterGen) box.innerHTML = prLetterPreviewHtml();
}
function openPRLetter(){
  document.getElementById('prLetterModalBody').innerHTML = prLetterPreviewHtml();
  document.getElementById('prLetterModal').style.display = 'flex';
}
function downloadPRLetter(){
  if (!window.LetterGen) { toast('Letter generator is still loading. Please try again.', true); return; }
  const d = prGatherLetterData();
  const blob = LetterGen.buildDocx(d, null);
  const slug = (d.subject || 'Purchase_Request').replace(/[^A-Za-z0-9]+/g,'_').replace(/^_+|_+$/g,'');
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'Formal_Letter_' + slug + '.docx';
  document.body.appendChild(a); a.click();
  setTimeout(function(){ URL.revokeObjectURL(a.href); a.remove(); }, 400);
}
document.addEventListener('DOMContentLoaded', function(){
  ['pi','pq','pu','ps','pc','otherItem','puHidden'].forEach(function(id){
    const el = document.getElementById(id);
    if (el) { el.addEventListener('input', prRefreshLetterPreview); el.addEventListener('change', prRefreshLetterPreview); }
  });
  prRefreshLetterPreview();
});
function toggleOtherItem(){
  const sel=document.getElementById('pi');
  const wrap=document.getElementById('otherItemWrap');
  const inp=document.getElementById('otherItem');
  if(sel.value==='__other__'){ wrap.style.display='block'; inp.focus(); }
  else{ wrap.style.display='none'; }
}
function fillItemSuggestion(){
  const sel=document.getElementById('pi');
  const qty=document.getElementById('pq');
  const unit=document.getElementById('pu');
  const cost=document.getElementById('pc');
  const hint=document.getElementById('suggHint');
  const opt=sel.options[sel.selectedIndex];
  if(sel.value==='__other__'){
    hint.style.display='none';
    if(unit){ unit.disabled=false; unit.style.opacity='1'; }
    return;
  }
  if(!opt || !opt.value){
    hint.style.display='none';
    if(unit){ unit.disabled=false; unit.style.opacity='1'; }
    return;
  }
  const sugg=parseInt(opt.dataset.qty,10);
  const cur=parseFloat(opt.dataset.cur);
  const reo=parseFloat(opt.dataset.reo);
  const unitCost=parseFloat(opt.dataset.cost);
  // Auto-fill suggested qty + unit
  qty.value = sugg || '';
  if(opt.dataset.unit && unit){ unit.value = opt.dataset.unit; }
  // mirror the locked unit into the hidden field that actually submits
  const hidden=document.getElementById('puHidden');
  if(hidden && opt.dataset.unit){ hidden.value = opt.dataset.unit; }
  // LOCK the unit to the item's unit so it can't be changed (e.g. beans = kg only)
  if(unit){ unit.disabled=true; unit.style.opacity='0.7'; }
  // Auto-fill estimated cost = qty x unit cost
  if(unitCost && qty.value){ cost.value = Math.round(unitCost * parseFloat(qty.value)); }
  hint.style.display='block';
  hint.innerHTML = 'Suggested order: <b>'+sugg+' '+opt.dataset.unit+'</b> (to reach 2× reorder level). Current stock: '+cur+' '+opt.dataset.unit+', reorder at '+reo+'. Unit cost: ₱'+unitCost+'/'+opt.dataset.unit+'. <b>Type the amount you need in the Quantity field below.</b>';
}
function recalcCost(){
  const sel=document.getElementById('pi');
  const qty=document.getElementById('pq');
  const cost=document.getElementById('pc');
  const opt=sel.options[sel.selectedIndex];
  if(!opt || !opt.dataset.cost){ return; }
  const unitCost=parseFloat(opt.dataset.cost);
  const q=parseFloat(qty.value);
  if(unitCost && q){ cost.value = Math.round(unitCost * q); }
}
// recalculate cost when the quantity changes
document.addEventListener('input', function(e){
  if(e.target && e.target.id==='pq'){ recalcCost(); }
});
// Keep the hidden unit field in sync with the visible unit dropdown
document.addEventListener('DOMContentLoaded', function(){
  var unitSel = document.getElementById('pu');
  var unitHidden = document.getElementById('puHidden');
  if(unitSel && unitHidden){
    unitSel.addEventListener('change', function(){ unitHidden.value = this.value; });
  }
});
function filterSellers(){
  const q=document.getElementById('sellSearch').value.toLowerCase();let shown=0;
  document.querySelectorAll('#sellerTbl tbody tr').forEach(r=>{const m=r.textContent.toLowerCase().includes(q);r.style.display=m?'':'none';if(m)shown++;});
  document.getElementById('sellerEmpty').classList.toggle('hide',shown>0);
}
function openSellerEdit(id,name,contact,phone,goods,rating){
  document.getElementById('seid').value=id;
  document.getElementById('sename').value=name;
  document.getElementById('secontact').value=contact;
  document.getElementById('sephone').value=phone;
  document.getElementById('segoods').value=goods;
  document.getElementById('serating').value= rating!==null ? rating : '';
  document.getElementById('sellerEditBg').classList.add('show');
}
function showPR(ref,item,qty,seller,cost,est,status){
  document.getElementById('prRef').textContent=ref;
  document.getElementById('prItem').textContent=item;
  document.getElementById('prQty').textContent=qty;
  document.getElementById('prSeller').textContent=seller;
  document.getElementById('prCost').textContent='₱'+Number(cost).toLocaleString();
  const s=document.getElementById('prStatus');s.textContent=status;
  s.style.textTransform='capitalize';
  document.getElementById('prBg').classList.add('show');
}
function openReceive(id,ref,item,ordered){
  document.getElementById('reid').value=id;
  document.getElementById('recvInfo').textContent='Receiving '+ref+' — '+item+' (ordered '+ordered+').';
  document.getElementById('rqty').value=ordered;
  document.getElementById('recvBg').classList.add('show');
}
function openItemEdit(id,name,unit,reorder,cat,cost){
  document.getElementById('ieid').value=id;
  document.getElementById('iename').value=name;
  document.getElementById('ieunit').value=unit;
  document.getElementById('iecost').value= cost||0;
  document.getElementById('iereorder').value=reorder;
  const sel=document.getElementById('iecat');
  for(const o of sel.options){ if(o.textContent===cat) sel.value=o.value; }
  document.getElementById('itemEditBg').classList.add('show');
}
</script>
<script src="input-guard.js"></script>
<script src="datatable.js"></script>
<script src="alerts.js"></script>
<script src="hrms/js/letter-gen.js"></script>
<script src="signature-pad.js"></script>
<script src="logout-confirm.js"></script>
<script>window.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('.sidebar nav').forEach(function(n){n.scrollTop=0;});});</script>
<script src="ux-improvements.js"></script>
<div id="prLetterModal" style="display:none;position:fixed;inset:0;background:rgba(30,20,10,.55);z-index:1200;align-items:center;justify-content:center;padding:20px">
  <div style="background:var(--cream-1,#fffaf0);max-width:760px;width:100%;max-height:90vh;overflow:auto;border-radius:14px;padding:18px;border:1px solid var(--line,#e5dcc8)">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <h3 style="margin:0">Formal Letter Preview</h3>
      <button type="button" class="btn-sm" onclick="document.getElementById('prLetterModal').style.display='none'">✕ Close</button>
    </div>
    <div id="prLetterModalBody" style="background:#fff;border:1px solid var(--line,#e5dcc8);border-radius:8px;padding:36px 44px;font-family:Georgia,'Times New Roman',serif;font-size:14px;line-height:1.7;color:#1f1a12;white-space:pre-line"></div>
    <div style="display:flex;gap:8px;margin-top:12px;justify-content:flex-end">
      <button type="button" class="btn-sm" onclick="downloadPRLetter()">⬇ Download .docx</button>
    </div>
  </div>
</div>
<style>
  .sig-overlay{position:fixed;inset:0;background:rgba(59,35,19,.45);display:flex;align-items:center;justify-content:center;z-index:1300;padding:20px}
  .sig-box{background:#fff;border-radius:16px;padding:24px;max-width:600px;width:100%;box-shadow:0 12px 40px rgba(59,35,19,.25);max-height:92vh;overflow:auto}
  .sig-box h3{font-size:18px;font-weight:800;margin-bottom:4px;color:var(--accent,#a9714a)}
  .sig-box .sig-sub{color:var(--muted,#7a6055);font-size:13px;margin-bottom:12px}
  .sig-as{font-size:13px;margin-bottom:8px}.sig-as b{color:var(--accent,#a9714a)}
  .req-label{display:block;font-weight:700;margin:10px 0 6px;font-size:13px}
  #prSigPad{width:100%;height:180px;border:1.5px solid var(--line,#e8ddd0);border-radius:12px;background:#fff;cursor:crosshair;touch-action:none;display:block}
  .sig-hint{font-size:12px;color:var(--muted,#7a6055);margin-top:6px}
  .sig-grid{display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap}
  .sig-grid .sig-left{flex:1;min-width:240px}
  .cam-box{width:170px;flex:none;text-align:center}
  .cam-box video{width:170px;height:128px;object-fit:cover;border-radius:12px;border:1.5px solid var(--line,#e8ddd0);background:#000;display:block}
  .cam-box .cam-label{font-size:11px;color:var(--muted,#7a6055);margin-top:5px;font-weight:700}
  .cam-err{color:#a8492f;font-size:12px;margin-top:6px;display:none}
  .reg-sig-box{border:1.5px dashed var(--line,#e8ddd0);border-radius:10px;padding:8px;background:#fff;min-height:70px;display:flex;align-items:center;justify-content:center;font-size:12.5px;color:var(--muted,#7a6055);text-align:center;margin-bottom:10px}
  .reg-sig-box img{max-height:80px;max-width:100%;object-fit:contain}
  .sig-actions{display:flex;gap:10px;margin-top:14px;flex-wrap:wrap}
  .msg.err{color:#a8492f;font-weight:600;font-size:13px}
  .msg.ok{color:#256b4d;font-weight:600;font-size:13px}
</style>
<div class="sig-overlay" id="prSigModal" style="display:none">
  <div class="sig-box">
    <h3>Sign Your Purchase Request</h3>
    <p class="sig-sub">Draw your signature below. It is placed on the formal letter above your full name, and a security photo is captured with it. The request is only sent to Finance after you sign.</p>
    <p class="sig-as">Signing as: <b id="prSigAs">—</b></p>
    <div class="sig-grid">
      <div class="sig-left">
        <label class="req-label" style="margin:0 0 4px">Your registered signature — draw it the same way</label>
        <div class="reg-sig-box" id="prRegSigBox">Loading…</div>
        <canvas id="prSigPad" width="1000" height="360"></canvas>
        <div class="sig-hint">Draw your signature with your mouse, finger, or stylus.</div>
      </div>
      <div class="cam-box">
        <video id="prSigCam" autoplay playsinline muted></video>
        <div class="cam-label">📸 Security photo (auto-captured when you sign)</div>
        <div class="cam-err" id="prCamErr">Camera access is required for signed requests.</div>
      </div>
    </div>
    <p class="msg" id="prSigMsg"></p>
    <div class="sig-actions">
      <button type="button" class="btn-sm ok" id="prSigSubmit" disabled>✍️ Sign &amp; Send to Finance</button>
      <button type="button" class="btn-sm" id="prSigClear">Clear Signature</button>
      <button type="button" class="btn-sm" id="prSigCancel">Cancel</button>
    </div>
  </div>
</div>
<script>
/* ===== E-signature pad + security photo (same protocol as HRMS requests) ===== */
let prSigStrokes = 0, prSigStream = null, prCamOk = false, prRegSigGrid = null, prNoRegSig = false;
const prSigCanvas = document.getElementById('prSigPad');
const prSigCtx = prSigCanvas.getContext('2d');
prSigCtx.lineWidth = 3; prSigCtx.lineCap = 'round'; prSigCtx.lineJoin = 'round'; prSigCtx.strokeStyle = '#22303f';
let prSigDrawing = false;
function prSigPos(e){ const r = prSigCanvas.getBoundingClientRect(); return { x:(e.clientX-r.left)*(prSigCanvas.width/r.width), y:(e.clientY-r.top)*(prSigCanvas.height/r.height) }; }
prSigCanvas.addEventListener('pointerdown', e => { prSigDrawing = true; prSigStrokes++; if(!prNoRegSig) document.getElementById('prSigSubmit').disabled = false; const p = prSigPos(e); prSigCtx.beginPath(); prSigCtx.moveTo(p.x, p.y); prSigCanvas.setPointerCapture(e.pointerId); e.preventDefault(); });
prSigCanvas.addEventListener('pointermove', e => { if(!prSigDrawing) return; const p = prSigPos(e); prSigCtx.lineTo(p.x, p.y); prSigCtx.stroke(); e.preventDefault(); });
['pointerup','pointercancel','pointerleave'].forEach(ev => prSigCanvas.addEventListener(ev, () => { prSigDrawing = false; }));
function prSigClear(){ prSigCtx.clearRect(0,0,prSigCanvas.width,prSigCanvas.height); prSigStrokes = 0; document.getElementById('prSigSubmit').disabled = true; document.getElementById('prSigMsg').textContent = ''; }
async function prStartSigCam(){
  prCamOk = false; document.getElementById('prCamErr').style.display = 'none';
  const vid = document.getElementById('prSigCam');
  try { prSigStream = await navigator.mediaDevices.getUserMedia({ video:{width:480,height:360}, audio:false }); vid.srcObject = prSigStream; prCamOk = true; }
  catch(e){ document.getElementById('prCamErr').style.display = 'block'; }
}
function prStopSigCam(){ if(prSigStream){ prSigStream.getTracks().forEach(t=>t.stop()); prSigStream = null; } const vid = document.getElementById('prSigCam'); if(vid) vid.srcObject = null; prCamOk = false; }
function prCapturePhoto(){
  const vid = document.getElementById('prSigCam');
  if(!prCamOk || !vid.videoWidth) return '';
  const c = document.createElement('canvas'); c.width = vid.videoWidth; c.height = vid.videoHeight;
  c.getContext('2d').drawImage(vid, 0, 0, c.width, c.height);
  return c.toDataURL('image/jpeg', 0.85);
}
function prInkGridFromCanvas(src){
  const w = src.width, h = src.height, d = src.getContext('2d').getImageData(0,0,w,h).data;
  let minx=w, miny=h, maxx=-1, maxy=-1;
  const mask = new Uint8Array(w*h);
  for(let y=0;y<h;y++) for(let x=0;x<w;x++){
    const i=(y*w+x)*4, a=d[i+3], lum=(d[i]+d[i+1]+d[i+2])/3;
    const ink = a>40 && lum<160;
    mask[y*w+x] = ink?1:0;
    if(ink){ if(x<minx)minx=x; if(x>maxx)maxx=x; if(y<miny)miny=y; if(y>maxy)maxy=y; }
  }
  if(maxx<0) return null;
  const S=48, grid=new Uint8Array(S*S), gw=maxx-minx+1, gh=maxy-miny+1;
  for(let gy=0;gy<S;gy++) for(let gx=0;gx<S;gx++){
    const sx=Math.min(maxx,Math.max(minx,minx+Math.floor((gx+0.5)*gw/S)));
    const sy=Math.min(maxy,Math.max(miny,miny+Math.floor((gy+0.5)*gh/S)));
    grid[gy*S+gx]=mask[sy*w+sx];
  }
  return grid;
}
function prInkGridFromImage(img){
  const c = document.createElement('canvas');
  c.width = img.naturalWidth || img.width; c.height = img.naturalHeight || img.height;
  const cx = c.getContext('2d'); cx.fillStyle = '#fff'; cx.fillRect(0,0,c.width,c.height); cx.drawImage(img,0,0);
  return prInkGridFromCanvas(c);
}
function prDilate(g,S){
  const out = new Uint8Array(g);
  for(let y=0;y<S;y++) for(let x=0;x<S;x++) if(g[y*S+x]){
    for(let dy=-1;dy<=1;dy++) for(let dx=-1;dx<=1;dx++){
      const ny=y+dy, nx=x+dx;
      if(ny>=0&&ny<S&&nx>=0&&nx<S) out[ny*S+nx]=1;
    }
  }
  return out;
}
function prSigSimilarity(a,b){
  const S=48;
  if(!a||!b) return 0;
  const da=prDilate(a,S), db=prDilate(b,S);
  let ca=0,cb=0,ha=0,hb=0;
  for(let i=0;i<S*S;i++){ if(a[i]){ca++; if(db[i])ha++;} if(b[i]){cb++; if(da[i])hb++;} }
  if(!ca||!cb) return 0;
  const p=ha/ca, r=hb/cb;
  return (p+r)>0 ? 2*p*r/(p+r) : 0;
}
const PR_REG_SIG_PATH = <?= json_encode(inv_registered_signature((int)$u['id'])['path']) ?>;
function prLoadRegSig(){
  const box = document.getElementById('prRegSigBox');
  prRegSigGrid = null; prNoRegSig = !PR_REG_SIG_PATH;
  if(!PR_REG_SIG_PATH){
    box.textContent = '⚠️ You have no registered e-signature yet. Register it in My Profile → Registered E-Signature first — requests cannot be signed without it.';
    return;
  }
  box.innerHTML = '';
  const img = new Image();
  img.onerror = () => { prRegSigGrid = null; prNoRegSig = true; box.textContent = 'Your registered signature file is missing on the server — re-save it in My Profile → Registered E-Signature.'; };
  img.onload = () => { prRegSigGrid = prInkGridFromImage(img); };
  img.src = PR_REG_SIG_PATH;
  box.appendChild(img);
}
function openPRSignModal(){
  swalAsk('Confirm e-sign: you are about to enter your official e-signature for this purchase request. It will be placed on the formal letter above your name and used for signature security. Continue?').then(function(ok){
    if(!ok) return;
    document.getElementById('prSigAs').textContent = PR_AUTHOR || '—';
    prSigClear();
    prLoadRegSig();
    document.getElementById('prSigModal').style.display = 'flex';
    prStartSigCam();
  });
}
document.getElementById('prSigClear').addEventListener('click', prSigClear);
document.getElementById('prSigCancel').addEventListener('click', function(){
  document.getElementById('prSigModal').style.display = 'none';
  prSigClear(); prStopSigCam();
});
document.getElementById('prSigSubmit').addEventListener('click', function(){
  const msg = document.getElementById('prSigMsg');
  if(prNoRegSig){ msg.textContent = 'You have no registered e-signature yet. Register it in My Profile → Registered E-Signature, then sign here with the same signature.'; msg.className = 'msg err'; return; }
  if(prSigStrokes === 0){ msg.textContent = 'Please draw your signature first.'; msg.className = 'msg err'; return; }
  // Security pre-check: drawn signature must resemble the registered one (when on file).
  if(prRegSigGrid){
    const score = prSigSimilarity(prRegSigGrid, prInkGridFromCanvas(prSigCanvas));
    if(score < 0.45){
      msg.textContent = 'Security check: your signature matches only ' + Math.round(score*100) + '% of your registered signature. Sign again the same way.';
      msg.className = 'msg err';
      return;
    }
  }
  // Security: capture the selfie from the camera.
  const selfie = prCapturePhoto();
  if(!selfie){
    document.getElementById('prCamErr').style.display = 'block';
    msg.textContent = 'Camera photo is required. Allow camera access and try again.';
    msg.className = 'msg err';
    return;
  }
  const btn = this; btn.disabled = true;
  try{
    const d = prGatherLetterData();
    const sigJpeg = LetterGen.sigJpegFromCanvas(prSigCanvas);
    const blob = LetterGen.buildDocx(d, sigJpeg);
    const slug = (d.subject || 'Purchase_Request').replace(/[^A-Za-z0-9]+/g,'_').replace(/^_+|_+$/g,'');
    const dt = new DataTransfer();
    dt.items.add(new File([blob], 'Formal_Letter_' + slug + '.docx', { type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' }));
    document.getElementById('prLetterFile').files = dt.files;
    const dtSig = new DataTransfer();
    dtSig.items.add(prDataUrlFile(prSigCanvas.toDataURL('image/png'), 'signature.png', 'image/png'));
    document.getElementById('prSigFile').files = dtSig.files;
    const dtSelf = new DataTransfer();
    dtSelf.items.add(prDataUrlFile(selfie, 'selfie.jpg', 'image/jpeg'));
    document.getElementById('prSelfieFile').files = dtSelf.files;
    document.getElementById('prSigModal').style.display = 'none';
    prStopSigCam();
    document.getElementById('prForm').submit();
  }catch(err){
    console.error('Signed letter failed', err);
    msg.textContent = 'Could not generate the signed letter. Please try again.';
    msg.className = 'msg err';
  }finally{ btn.disabled = false; }
});
function prDataUrlFile(dataUrl, name, mime){
  const bin = atob(dataUrl.split(',')[1]);
  const arr = new Uint8Array(bin.length);
  for(let i=0;i<bin.length;i++) arr[i] = bin.charCodeAt(i);
  return new File([arr], name, { type: mime });
}
</script>

<script src="responsive-ui.js" defer></script>
<script src="dashboard-navigation.js" defer></script>
<script src="inventory-ui.js" defer></script>
<script src="procurement-finance.js" defer></script>
<script src="warehouse-operations.js" defer></script>
</body>
</html>