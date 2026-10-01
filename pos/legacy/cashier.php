<?php
define('BREWCO_NO_GUARD', true);
require __DIR__ . '/../../db.php';
// POS cashier side. Roles: cashier, barista, cleaner, admin, superadmin.
define('POS_ROLES', ['cashier','barista','cleaner','admin','superadmin']);
if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], POS_ROLES)) {
    if (db_ok()) { header('Location: ../../login.php'); exit; }
    $_SESSION['user'] = ['id'=>8,'firstName'=>'Rica','lastName'=>'Diaz','email'=>'rica@brewco.ph','role'=>'cashier'];
}
$u = $_SESSION['user'];
$user = ['firstName'=>$u['firstName'],'lastName'=>$u['lastName'],'role'=>ucfirst($u['role'])];
$initials = strtoupper(substr($u['firstName'],0,1).substr($u['lastName'],0,1));

// ---- Process payment: finalize a placed order, deduct ingredients from inventory ----
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['pay'])) {
    if (!verify_token($_POST['token'] ?? '')) { header('Location: cashier.php?err=token'); exit; }
    if (db_ok()) {
        $oid = (int)($_POST['order_id'] ?? 0);
        $paid = (float)($_POST['amount_paid'] ?? 0);
        $ord = $oid ? db_one("SELECT id, order_no, total, status FROM pos_orders WHERE id=? AND status='placed'", [$oid]) : null;
        if (!$ord) { header('Location: cashier.php?err=notfound'); exit; }
        if ($paid < (float)$ord['total']) { header('Location: cashier.php?err=short&oid='.$oid); exit; }
        $change = $paid - (float)$ord['total'];
        $items = db_all('SELECT oi.menu_item_id, oi.qty FROM pos_order_items oi WHERE oi.order_id=?', [$oid]);
        // Check ingredient availability for ALL lines first; if any short, block.
        $shortMsg = null;
        foreach ($items as $it) {
            $ings = db_all('SELECT i.id, i.name, i.unit, i.current_qty, mi.qty ing_qty
                            FROM menu_ingredients mi JOIN items i ON i.id=mi.item_id
                            WHERE mi.menu_item_id=?', [$it['menu_item_id']]);
            foreach ($ings as $g) {
                $need = (float)$g['ing_qty'] * (int)$it['qty'];
                if ((float)$g['current_qty'] < $need) {
                    $shortMsg = $g['name'].' is short — need '.$need.' '.$g['unit'].', only '.$g['current_qty'].' in stock.';
                    break 2;
                }
            }
        }
        if ($shortMsg) { header('Location: cashier.php?err=stock&msg='.urlencode($shortMsg).'&oid='.$oid); exit; }
        $ok = tx(function() use ($oid,$paid,$change,$items,$u,$ord) {
            // Deduct ingredients
            foreach ($items as $it) {
                $ings = db_all('SELECT i.id, i.name, i.unit, mi.qty ing_qty
                                FROM menu_ingredients mi JOIN items i ON i.id=mi.item_id
                                WHERE mi.menu_item_id=?', [$it['menu_item_id']]);
                foreach ($ings as $g) {
                    $deduct = (float)$g['ing_qty'] * (int)$it['qty'];
                    inv_record_movement((int)$g['id'], 'out', $deduct, $g['unit'],
                                        'POS sale '.$ord['order_no'].' — '.$it['qty'].'x '.$g['name'], (int)$u['id']);
                }
                // increment menu sold count
                db_exec('UPDATE menu_items SET sold = sold + ? WHERE id=?', [(int)$it['qty'], (int)$it['menu_item_id']]);
            }
            db_exec('UPDATE pos_orders SET status="paid", amount_paid=?, change_due=?, cashier_id=?, paid_at=NOW() WHERE id=?',
                    [$paid, $change, (int)$u['id'], $oid]);
            audit($u, 'pos.paid', 'Order '.$ord['order_no'].' paid (₱'.number_format($paid).', change ₱'.number_format($change).')');
            return true;
        });
        if ($ok) inv_refresh_menu_stock();
        header('Location: cashier.php?paid='.$ord['order_no'].($ok?'':'&err=tx')); exit;
    }
}

// ---- Cancel a placed order ----
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['cancel'])) {
    if (db_ok()) {
        $oid = (int)($_POST['order_id'] ?? 0);
        db_exec("UPDATE pos_orders SET status='cancelled' WHERE id=? AND status='placed'", [$oid]);
        header('Location: cashier.php?saved=1'); exit;
    }
}

$placed = [];
if (db_ok()) {
    $placed = db_all("SELECT o.id, o.order_no, o.client_name, o.total, o.created_at
                      FROM pos_orders o WHERE o.status='placed' ORDER BY o.created_at ASC");
}
// load order items map
$orderItems = [];
if (db_ok()) {
    $rows = db_all('SELECT oi.order_id, oi.item_name, oi.qty, oi.price FROM pos_order_items oi ORDER BY oi.id');
    foreach ($rows as $r) $orderItems[(int)$r['order_id']][] = $r;
}
$flash = isset($_GET['err']) ? $_GET['err'] : '';
$flashMsg = isset($_GET['msg']) ? urldecode($_GET['msg']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="../../favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="../../favicon.ico?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Brew &amp; Co. — Cashier</title>
<style>
  :root{--brown-900:#faf6f0;--accent:#a9714a;--accent-dark:#8f5c39;--cream:#faf6f0;--cream-2:#f5ede3;--ink:#3b2313;--muted:#7a6055;--line:#e8ddd0;--ok:#256b4d;--danger:#a23232;--radius:16px;--shadow:0 8px 24px rgba(74,47,34,.1)}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--cream);color:var(--ink);min-height:100vh}
  header{background:var(--brown-900);color:#fff;padding:14px 24px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:20}
  header .brand{font-size:19px;font-weight:800}header .brand span{color:#d8a066}
  header .who{display:flex;align-items:center;gap:10px;font-size:14px}
  header .av{width:34px;height:34px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800}
  header a{color:#fff;text-decoration:none;font-size:13px;font-weight:600;border:1px solid rgba(255,255,255,.4);padding:7px 12px;border-radius:8px;margin-left:8px}
  .wrap{max-width:1100px;margin:0 auto;padding:22px}
  .banner{background:#e2f0e8;color:var(--ok);border-radius:12px;padding:14px 18px;margin-bottom:16px;font-size:14px}
  .banner.bad{background:#f6e0e0;color:var(--danger)}
  h1{font-size:22px;margin-bottom:4px}h1 .sub{color:var(--muted);font-size:13px;font-weight:500}
  .queue{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;margin-top:16px}
  .ticket{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:18px;border-top:4px solid var(--accent)}
  .ticket .top{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
  .ticket .no{font-size:16px;font-weight:800}.ticket .client{color:var(--muted);font-size:12px}
  .ticket .row{display:flex;justify-content:space-between;padding:7px 0;border-top:1px solid var(--line);font-size:14px}
  .ticket .tot{font-size:18px;font-weight:800;margin:12px 0 4px;border-top:2px solid var(--line);padding-top:12px}
  .pay{display:flex;gap:8px;margin-top:8px}
  .pay input{flex:1;padding:11px;border:1px solid var(--line);border-radius:9px;font-size:15px}
  .btn{border:none;border-radius:9px;padding:11px 14px;font-size:14px;font-weight:700;cursor:pointer}
  .btn.pay{background:var(--ok);color:#fff}.btn.pay:hover{opacity:.9}
  .btn.cancel{background:#fff;color:var(--danger);border:1px solid #e6c3c3}
  .btn.other{background:var(--accent);color:#fff;text-decoration:none;display:inline-block;margin-top:6px}
  .empty{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:40px;text-align:center;color:var(--muted);margin-top:16px}
  .quick{position:fixed;bottom:20px;right:20px;display:flex;flex-direction:column;gap:8px}
  .calc{background:#fff;border-radius:12px;box-shadow:var(--shadow);padding:12px;text-align:center}
  .calc input{width:100%;padding:10px;border:1px solid var(--line);border-radius:8px;font-size:22px;font-weight:800;text-align:center}
  .calc div{font-size:13px;color:var(--muted);margin-top:4px}.calc b{color:var(--ok)}
</style>

<link rel="stylesheet" href="../../responsive-ui.css">
<link rel="stylesheet" href="../../dashboard-navigation.css">
<link rel="stylesheet" href="../../pos-responsive.css">
</head>
<body>
<header>
  <div><div class="brand">☕ Brew <span>&amp;</span> Co. <span style="font-weight:500;font-size:14px;color:#d8a066">| Cashier</span></div></div>
  <div class="who">
    <div class="av"><?= $initials ?></div><span><?= htmlspecialchars($user['firstName'].' '.$user['lastName']) ?> (<?= htmlspecialchars($user['role']) ?>)</span>
    <a href="client.php">Client kiosk</a>
    <?php if(in_array($u['role'],['admin','superadmin'])): ?><a href="admin.php">Admin</a><?php endif; ?>
    <a href="../../logout.php">Logout</a>
  </div>
</header>

<div class="wrap">
  <?php if (isset($_GET['paid'])): ?><div class="banner">✅ Order <b><?= htmlspecialchars($_GET['paid']) ?></b> paid &amp; ingredients deducted from stock. Change given.</div><?php endif; ?>
  <?php if ($flash==='stock'): ?><div class="banner bad">⚠ Cannot complete — <?= htmlspecialchars($flashMsg) ?></div><?php endif; ?>
  <?php if ($flash==='short'): ?><div class="banner bad">⚠ Amount paid is less than the total. Please enter enough to cover it.</div><?php endif; ?>
  <?php if ($flash==='token'): ?><div class="banner bad">⚠ Invalid session — please refresh.</div><?php endif; ?>
  <?php if ($flash==='notfound'): ?><div class="banner bad">⚠ Order not found or already processed.</div><?php endif; ?>
  <?php if (isset($_GET['saved'])): ?><div class="banner">Order cancelled.</div><?php endif; ?>

  <h1>Incoming orders <span class="sub">— finalize payment here, then ingredients are deducted from stock automatically.</span></h1>

  <?php if ($placed): ?>
  <div class="queue">
    <?php foreach ($placed as $o): ?>
    <div class="ticket">
      <div class="top"><span class="no">#<?= htmlspecialchars($o['order_no']) ?></span><span class="client"><?= htmlspecialchars($o['client_name']) ?></span></div>
      <?php foreach ($orderItems[(int)$o['id']] ?? [] as $it): ?>
        <div class="row"><span><?= $it['qty'] ?>× <?= htmlspecialchars($it['item_name']) ?></span><span>₱<?= number_format($it['qty']*$it['price']) ?></span></div>
      <?php endforeach; ?>
      <div class="tot"><span>Total: ₱<?= number_format($o['total']) ?></span></div>
      <form method="post" action="cashier.php">
        <?= token_field() ?>
        <input type="hidden" name="pay" value="1"><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
        <div class="pay">
          <input type="number" name="amount_paid" min="<?= (float)$o['total'] ?>" step="0.5" placeholder="Cash received" required>
          <button class="btn pay" type="submit" onclick="return payConfirm(<?= (float)$o['total'] ?>)">Pay</button>
        </div>
      </form>
      <form method="post" action="cashier.php" style="margin-top:6px" onsubmit="return sweetConfirmSubmit(event,'Cancel this order?')">
        <?= token_field() ?>
        <input type="hidden" name="cancel" value="1"><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
        <button class="btn cancel" type="submit" style="width:100%">Cancel order</button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="empty">No incoming orders right now. 🎉<br><br><a class="btn other" href="client.php">Open client kiosk</a></div>
  <?php endif; ?>
</div>

<script>
function payConfirm(total){
  const input = event.target.closest('form').querySelector('input[name=amount_paid]');
  const paid = parseFloat(input.value);
  if(!paid || paid < total){ swalAlert('Enter an amount at least ₱'+total.toLocaleString()+'.', 'error'); return false; }
  const change = paid - total;
  swalConfirm('Customer pays ₱'+paid.toLocaleString()+'. Change: ₱'+change.toLocaleString()+'. Proceed?', function(){
    input.closest('form').submit();
  });
  return false;
}
</script>
<script src="../../input-guard.js"></script>
<script src="../../datatable.js"></script>
<script src="../../alerts.js"></script>
<script src="../../logout-confirm.js"></script>
<script src="../../ux-improvements.js"></script>

<script src="../../responsive-ui.js" defer></script>
<script src="../../dashboard-navigation.js" defer></script>
<script src="../../pos-responsive.js" defer></script>
</body>
</html>
