<?php
define('BREWCO_NO_GUARD', true);
require __DIR__ . '/../../db.php';
// Client-side POS (kiosk). Public: no login needed. Client builds an order
// and "places" it; the cashier then finalizes it (payment/change) and it
// deducts ingredients from inventory.

// Recipe-linked stock: how many whole drinks can still be made from inventory
inv_refresh_menu_stock();
$srvMap = inv_recipe_servings_map();

// Place an order
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['place_order'])) {
    if (!verify_token($_POST['token'] ?? '')) { header('Location: client.php?err=1'); exit; }
    if (db_ok()) {
        $items = isset($_POST['item']) && is_array($_POST['item']) ? $_POST['item'] : [];
        $qtys  = isset($_POST['qty']) && is_array($_POST['qty']) ? $_POST['qty'] : [];
        $client = trim($_POST['client_name'] ?? '');
        $lines = [];
        foreach ($items as $i => $menuId) {
            $menuId = (int)$menuId; $qty = (int)($qtys[$i] ?? 0);
            if ($menuId <= 0 || $qty <= 0) continue;
            $m = db_one('SELECT id, name, price FROM menu_items WHERE id=? AND is_active=1', [$menuId]);
            if ($m) {
                $srv = $srvMap[$m['id']] ?? null;
                if ($srv !== null && $qty > $srv) {
                    header('Location: client.php?err=stock&msg='.urlencode('Only '.$srv.' whole '.$m['name'].' can be made from the stock left in inventory.')); exit;
                }
                $lines[] = ['id'=>$m['id'],'name'=>$m['name'],'price'=>(float)$m['price'],'qty'=>$qty];
            }
        }
        if ($lines) {
            $total = 0; foreach ($lines as $l) $total += $l['price']*$l['qty'];
            $max = (int)(db_one("SELECT MAX(CAST(SUBSTRING(order_no,5) AS UNSIGNED)) m FROM pos_orders")['m'] ?? 1000);
            $on = 'POS-' . ($max + 1);
            $ok = tx(function() use ($lines,$total,$client,$on) {
                db_exec('INSERT INTO pos_orders (order_no, client_name, status, total) VALUES (?,?,?,?)',
                        [$on, $client ?: 'Walk-in', 'placed', $total]);
                $oid = db_last_id();
                foreach ($lines as $l) {
                    db_exec('INSERT INTO pos_order_items (order_id, menu_item_id, item_name, qty, price) VALUES (?,?,?,?,?)',
                            [$oid, $l['id'], $l['name'], $l['qty'], $l['price']]);
                }
                audit(['id'=>0,'role'=>'client'], 'pos.order_placed', 'Order '.$on.' placed (₱'.number_format($total).')');
                return $oid;
            });
            if ($ok) { header('Location: client.php?done='.$on); exit; }
        }
    }
    header('Location: client.php?err=1'); exit;
}

$cats = db_ok() ? db_all('SELECT id, name FROM menu_categories WHERE is_active=1 ORDER BY sort_order, name') : [];
$catIcons = ['Hot drinks'=>'☕','Cold drinks'=>'🥤','Pastries'=>'🥐','Desserts'=>'🍰','Meals'=>'🍱','default'=>'☕'];
foreach ($cats as &$c) { $c['icon'] = $catIcons[$c['name']] ?? $catIcons['default']; } unset($c);
$menu = [];
if (db_ok()) {
    $rows = db_all('SELECT id, category_id, name, price FROM menu_items WHERE is_active=1 ORDER BY name');
    foreach ($rows as $r) $menu[(int)$r['category_id']][] = $r;
}
$done = isset($_GET['done']) ? trim($_GET['done']) : '';
$stockErr = isset($_GET['err']) && $_GET['err']==='stock' ? ($_GET['msg'] ?? 'Not enough ingredient stock for that order.') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="../../favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="../../favicon.ico?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Brew &amp; Co. — Order</title>
<style>
  :root{--brown-900:#faf6f0;--accent:#a9714a;--accent-dark:#8f5c39;--cream:#faf6f0;--cream-2:#f5ede3;--ink:#3b2313;--muted:#7a6055;--line:#e8ddd0;--ok:#256b4d;--danger:#a23232;--radius:16px}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--cream);color:var(--ink);min-height:100vh}
  header{background:var(--brown-900);color:#fff;padding:16px 26px;display:flex;align-items:center;justify-content:space-between}
  header .brand{font-size:20px;font-weight:800}header .brand span{color:#d8a066}
  header .tag{font-size:13px;color:#cbb8a6}
  header a.admin{color:#fff;font-size:13px;font-weight:600;text-decoration:none;border:1px solid rgba(255,255,255,.4);padding:7px 12px;border-radius:8px}
  header a.admin:hover{background:rgba(255,255,255,.15)}
  .wrap{display:flex;gap:20px;padding:22px;max-width:1200px;margin:0 auto}
  .cats{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;background:#fff;border-radius:var(--radius);padding:12px;box-shadow:0 4px 14px rgba(74,47,34,.06)}
  .cats button{border:none;background:#f6ecdd;color:#5c3a29;padding:10px 18px;border-radius:20px;font-size:14px;font-weight:700;cursor:pointer}
  .cats button:hover{background:#eaded0}
  .cats button.active{background:var(--accent);color:#fff}
  .main{flex:1}
  .griditems{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px}
  .card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:16px;cursor:pointer;transition:.15s;box-shadow:0 4px 14px rgba(74,47,34,.05)}
  .card:hover{border-color:var(--accent);transform:translateY(-2px)}
  .card{text-align:center;padding:20px 14px}
  .card .ic{font-size:34px;margin-bottom:8px;line-height:1}
  .card .nm{font-weight:700;font-size:14px}
  .card .pr{color:var(--accent);font-weight:800;margin-top:6px;font-size:16px}
  .stockbadge{margin-top:8px;font-size:11px;font-weight:700;border-radius:20px;padding:3px 10px;display:inline-block}
  .stockbadge.ok{background:#e2f0e8;color:var(--ok)}
  .stockbadge.warn{background:#f8eecf;color:#96690a}
  .stockbadge.bad{background:#f6e0e0;color:var(--danger)}
  .card.soldout{opacity:.55;cursor:not-allowed;filter:grayscale(.4)}
  .card.soldout:hover{transform:none;border-color:var(--line)}
  .order{width:300px;flex-shrink:0;background:#fff;border-radius:var(--radius);box-shadow:0 8px 24px rgba(74,47,34,.1);padding:18px;align-self:flex-start}
  .order h2{font-size:17px;margin-bottom:12px}
  .olist{max-height:360px;overflow:auto}
  .oline{display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-top:1px solid var(--line);font-size:14px;gap:8px}
  .oline .nm{flex:1}
  .oline .ctr{display:flex;align-items:center;gap:6px}
  .oline .ctr button{width:24px;height:24px;border-radius:6px;border:1px solid var(--line);background:#fff;cursor:pointer;font-weight:800}
  .oline .ctr button:hover{background:var(--cream-2)}
  .oline .amt{width:52px;text-align:right;font-weight:700}
  .total{display:flex;justify-content:space-between;font-size:19px;font-weight:800;margin:14px 0 4px;border-top:2px solid var(--line);padding-top:12px}
  .btns{display:flex;flex-direction:column;gap:9px;margin-top:10px}
  .btn{width:100%;border:none;border-radius:10px;padding:13px;font-size:15px;font-weight:700;cursor:pointer}
  .btn.place{background:var(--accent);color:#fff}.btn.place:hover{background:var(--accent-dark)}
  .btn.clear{background:#fff;color:var(--danger);border:1px solid #e6c3c3}
  .empty{color:var(--muted);font-size:14px;padding:20px;text-align:center}
  .banner{background:#e2f0e8;color:var(--ok);border-radius:12px;padding:14px 18px;margin:18px 22px 0;font-size:14px;display:flex;justify-content:space-between;align-items:center}
  .banner button{background:var(--ok);color:#fff;border:none;border-radius:8px;padding:8px 14px;font-weight:700;cursor:pointer}
  .banner.bad{background:#f6e0e0;color:var(--danger)}
  .err{background:#f6e0e0;color:var(--danger);border-radius:12px;padding:12px 18px;margin:18px 22px 0;font-size:14px}
  @media(max-width:900px){.wrap{flex-direction:column}.order{width:100%}}
</style>

<link rel="stylesheet" href="../../responsive-ui.css">
<link rel="stylesheet" href="../../dashboard-navigation.css">
<link rel="stylesheet" href="../../pos-responsive.css">
</head>
<body>
<header>
  <div><div class="brand">☕ Brew <span>&amp;</span> Co.</div><div class="tag">Order at the counter</div></div>
  <a class="admin" href="../../login.php">Staff login →</a>
</header>

<?php if ($stockErr): ?><div class="banner bad">⚠ <?= htmlspecialchars($stockErr) ?></div><?php endif; ?>
    <?php if ($done): ?>
<div class="banner"><span>✅ Order <b><?= htmlspecialchars($done) ?></b> placed! Please proceed to the counter for payment.</span><button onclick="location.href='client.php'">New order</button></div>
<?php endif; ?>
<?php if (isset($_GET['err'])): ?><div class="err">⚠ Could not place order — please pick at least one item.</div><?php endif; ?>

<div style="max-width:1200px;margin:0 auto;padding:22px">
  <div class="cats">
    <?php foreach ($cats as $c): ?>
      <button data-cat="<?= (int)$c['id'] ?>" onclick="showCat(<?= (int)$c['id'] ?>,this)"><?= htmlspecialchars($c['name']) ?></button>
    <?php endforeach; ?>
  </div>

  <div class="wrap">
  <div class="main">
    <?php foreach ($cats as $c): ?>
    <div class="catblock" data-cat="<?= (int)$c['id'] ?>" style="display:none">
      <div class="griditems">
        <?php foreach ($menu[(int)$c['id']] ?? [] as $m): $srv = $srvMap[$m['id']] ?? null; $out = ($srv === 0); ?>
        <div class="card<?= $out ? ' soldout' : '' ?>"<?= $out ? '' : ' onclick="addItem('.(int)$m['id'].', '.htmlspecialchars(json_encode($m['name'])).', '.(float)$m['price'].')"' ?>>
          <div class="ic"><?= htmlspecialchars($c['icon'] ?? '☕') ?></div>
          <div class="nm"><?= htmlspecialchars($m['name']) ?></div>
          <div class="pr">₱<?= number_format($m['price']) ?></div>
          <?php if($srv !== null): ?>
            <div class="stockbadge <?= $out ? 'bad' : ($srv <= 5 ? 'warn' : 'ok') ?>"><?= $out ? 'Sold out — no ingredient stock' : '🥤 '.$srv.' whole left in stock' ?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (empty($menu[(int)$c['id']])): ?><div class="empty">No items in this category yet.</div><?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="order">
    <h2>Current order</h2>
    <div class="olist" id="olist"></div>
    <div class="total"><span>Total</span><span id="total">₱0</span></div>
    <form method="post" action="client.php" id="placeForm">
      <?= token_field() ?>
      <input type="hidden" name="place_order" value="1">
      <input type="text" name="client_name" id="clientName" placeholder="Customer name (optional)" style="width:100%;padding:11px;border:1px solid var(--line);border-radius:10px;font-size:14px;margin-bottom:10px">
      <div id="lineInputs"></div>
      <div class="btns">
        <button type="submit" class="btn place" onclick="return validateOrder()">Place order</button>
        <button type="button" class="btn clear" onclick="clearOrder()">Clear order</button>
      </div>
    </form>
  </div>
  </div>
</div>

<script>
let cart = {}; // menuId -> {name, price, qty}
function showCat(id, btn){
  document.querySelectorAll('.catblock').forEach(b=>b.style.display = b.dataset.cat==id ? '' : 'none');
  document.querySelectorAll('.cats button').forEach(b=>b.classList.remove('active'));
  if(btn) btn.classList.add('active');
}
function renderLines(){
  const box = document.getElementById('olist');
  const hidden = document.getElementById('lineInputs');
  let html='', hid='', total=0, i=0;
  for(const id in cart){
    const c = cart[id];
    total += c.price * c.qty;
    html += '<div class="oline"><span class="nm">'+c.name+'</span>'+
      '<span class="ctr"><button onclick="chg('+id+',-1)">−</button><b>'+c.qty+'</b><button onclick="chg('+id+',1)">+</button></span>'+
      '<span class="amt">₱'+(c.price*c.qty).toLocaleString()+'</span></div>';
    hid += '<input type="hidden" name="item[]" value="'+id+'"><input type="hidden" name="qty[]" value="'+c.qty+'">';
  }
  box.innerHTML = html || '<div class="empty">No items yet — tap a menu item.</div>';
  hidden.innerHTML = hid;
  document.getElementById('total').textContent = '₱'+total.toLocaleString();
}
const MENU_STOCK = <?= json_encode($srvMap) ?>;
function addItem(id,name,price){
  const next = (cart[id]?cart[id].qty+1:1);
  if (MENU_STOCK[id] !== undefined && next > MENU_STOCK[id]) { swalAlert('Only '+MENU_STOCK[id]+' whole '+name+' can be made from the stock left in inventory.', 'warning'); return; }
  cart[id] = {name, price, qty:next}; renderLines();
}
function chg(id,delta){ if(cart[id]){ let q = cart[id].qty + delta;
  if (MENU_STOCK[id] !== undefined && q > MENU_STOCK[id]) { swalAlert('Only '+MENU_STOCK[id]+' whole '+cart[id].name+' can be made from the stock left in inventory.', 'warning'); q = MENU_STOCK[id]; }
  cart[id].qty = q; if(cart[id].qty<=0) delete cart[id]; renderLines(); } }
function clearOrder(){ cart={}; renderLines(); }
function validateOrder(){ if(Object.keys(cart).length===0){ swalAlert('Please add at least one item.', 'error'); return false; } return true; }
// show first category by default
document.addEventListener('DOMContentLoaded',function(){
  const first = document.querySelector('.cats button'); if(first) showCat(first.dataset.cat, first);
});
</script>
<script src="../../input-guard.js"></script>
<script src="../../alerts.js"></script>
<script src="../../ux-improvements.js"></script>

<script src="../../responsive-ui.js" defer></script>
<script src="../../dashboard-navigation.js" defer></script>
<script src="../../pos-responsive.js" defer></script>
</body>
</html>
