<?php
define('BREWCO_NO_GUARD', true);
require __DIR__ . '/../../db.php';
// POS admin side. Roles: admin + superadmin.
if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin','superadmin'])) {
    if (db_ok()) { header('Location: ../../login.php'); exit; }
    $_SESSION['user'] = ['id'=>10,'firstName'=>'POS','lastName'=>'Admin','email'=>'posadmin@brewco.ph','role'=>'admin'];
}
$u = $_SESSION['user'];
$user = ['firstName'=>$u['firstName'],'lastName'=>$u['lastName'],'role'=>'Admin'];
$initials = strtoupper(substr($u['firstName'],0,1).substr($u['lastName'],0,1));

// ---- Category add ----
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['cat_action'])) {
    if (!verify_token($_POST['token'] ?? '')) { header('Location: admin.php?err=token'); exit; }
    if (db_ok() && $_POST['cat_action']==='add_cat') {
        $name = trim($_POST['cname'] ?? '');
        $sort = (int)($_POST['csort'] ?? 0);
        if ($name && !db_one('SELECT id FROM menu_categories WHERE name=?', [$name])) {
            db_exec('INSERT INTO menu_categories (name,sort_order) VALUES (?,?)', [$name,$sort]);
            audit($u,'pos.cat_add','Added POS category '.$name);
            header('Location: admin.php?saved=1'); exit;
        }
        header('Location: admin.php?err=cat&msg='.urlencode('Category exists or name empty.')); exit;
    }
}

// ---- Menu item add / edit / delete / toggle + recipe ----
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['menu_action'])) {
    if (!verify_token($_POST['token'] ?? '')) { header('Location: admin.php?err=token'); exit; }
    if (db_ok()) {
        $ma = $_POST['menu_action'];
        if ($ma === 'add') {
            $name = trim($_POST['mname'] ?? ''); $price = (float)($_POST['mprice'] ?? 0);
            $cost = (float)($_POST['mcost'] ?? 0); $cat = (int)($_POST['mcat'] ?? 0);
            $ingIds = $_POST['ing_item'] ?? []; $ingQtys = $_POST['ing_qty'] ?? [];
            if ($name && $price > 0) {
                db_exec('INSERT INTO menu_items (category_id,name,price,cost,sold,is_active) VALUES (?,?,?,?,0,1)',
                        [$cat ?: null, $name, $price, $cost]);
                $mid = db_last_id();
                foreach ($ingIds as $i => $iid) {
                    $q = (float)($ingQtys[$i] ?? 0);
                    if ((int)$iid > 0 && $q > 0) db_exec('INSERT INTO menu_ingredients (menu_item_id,item_id,qty) VALUES (?,?,?)', [$mid,(int)$iid,$q]);
                }
                audit($u,'pos.menu_add','Added menu item '.$name.' (₱'.number_format($price).')');
                header('Location: admin.php?saved=1'); exit;
            }
        } elseif ($ma === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['mname'] ?? ''); $price = (float)($_POST['mprice'] ?? 0);
            $cost = (float)($_POST['mcost'] ?? 0); $cat = (int)($_POST['mcat'] ?? 0);
            $ingIds = $_POST['ing_item'] ?? []; $ingQtys = $_POST['ing_qty'] ?? [];
            if ($id && $name && $price > 0) {
                db_exec('UPDATE menu_items SET category_id=?, name=?, price=?, cost=? WHERE id=?',
                        [$cat ?: null, $name, $price, $cost, $id]);
                db_exec('DELETE FROM menu_ingredients WHERE menu_item_id=?', [$id]);
                foreach ($ingIds as $i => $iid) {
                    $q = (float)($ingQtys[$i] ?? 0);
                    if ((int)$iid > 0 && $q > 0) db_exec('INSERT INTO menu_ingredients (menu_item_id,item_id,qty) VALUES (?,?,?)', [$id,(int)$iid,$q]);
                }
                audit($u,'pos.menu_edit','Edited menu item '.$name);
                header('Location: admin.php?saved=1'); exit;
            }
        } elseif ($ma === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id) { db_exec('UPDATE menu_items SET is_active=IF(is_active=1,0,1) WHERE id=?', [$id]); header('Location: admin.php?saved=1'); exit; }
        } elseif ($ma === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                // If this item has order history (FK), we can't hard-delete it.
                $used = db_one('SELECT id FROM pos_order_items WHERE menu_item_id=? LIMIT 1', [$id]);
                if ($used) {
                    db_exec('UPDATE menu_items SET is_active=0 WHERE id=?', [$id]); // soft-disable instead
                    audit($u,'pos.menu_disable','Menu item #'.$id.' hidden (has order history, cannot delete)');
                    header('Location: admin.php?err=1&msg='.urlencode('This item has order history and cannot be deleted, so it was hidden instead.')); exit;
                }
                db_exec('DELETE FROM menu_ingredients WHERE menu_item_id=?', [$id]);
                db_exec('DELETE FROM menu_items WHERE id=?', [$id]);
                audit($u,'pos.menu_delete','Deleted menu item #'.$id);
                header('Location: admin.php?saved=1'); exit;
            }
        }
        header('Location: admin.php?err=1'); exit;
    }
}

$cats = db_ok() ? db_all('SELECT id, name, sort_order, is_active FROM menu_categories ORDER BY sort_order, name') : [];
$menu = db_ok() ? db_all('SELECT m.id, m.name, m.price, m.cost, m.sold, m.is_active, c.name cat_name
                          FROM menu_items m LEFT JOIN menu_categories c ON c.id=m.category_id ORDER BY m.name') : [];
$ingredients = db_ok() ? db_all("SELECT id, name, unit, current_qty FROM items WHERE is_active=1 ORDER BY name") : [];
$recipes = [];
if (db_ok()) {
    $rows = db_all('SELECT mi.menu_item_id, i.name ing_name, i.unit, mi.qty FROM menu_ingredients mi JOIN items i ON i.id=mi.item_id ORDER BY mi.menu_item_id');
    foreach ($rows as $r) $recipes[(int)$r['menu_item_id']][] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="../../favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="../../favicon.ico?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Brew &amp; Co. — POS Admin</title>
<style>
  :root{--brown-900:#faf6f0;--accent:#a9714a;--accent-dark:#8f5c39;--cream:#faf6f0;--cream-2:#f5ede3;--ink:#3b2313;--muted:#7a6055;--line:#e8ddd0;--ok:#256b4d;--danger:#a23232;--radius:16px;--shadow:0 8px 24px rgba(74,47,34,.1)}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--cream);color:var(--ink);min-height:100vh}
  header{background:var(--brown-900);color:#fff;padding:14px 24px;display:flex;align-items:center;justify-content:space-between}
  header .brand{font-size:19px;font-weight:800}header .brand span{color:#d8a066}
  header .who{display:flex;align-items:center;gap:10px;font-size:14px}
  header .av{width:34px;height:34px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800}
  header a{color:#fff;text-decoration:none;font-size:13px;font-weight:600;border:1px solid rgba(255,255,255,.4);padding:7px 12px;border-radius:8px;margin-left:8px}
  .wrap{max-width:1200px;margin:0 auto;padding:22px}
  .banner{background:#e2f0e8;color:var(--ok);border-radius:12px;padding:14px 18px;margin-bottom:16px;font-size:14px}
  .banner.bad{background:#f6e0e0;color:var(--danger)}
  h1{font-size:22px}
  .grid{display:grid;grid-template-columns:1.4fr 1fr;gap:20px}
  .panel{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:20px;margin-top:16px}
  .panel h2{font-size:17px;margin-bottom:12px}
  .table-wrap{overflow-x:auto}
  table{width:100%;border-collapse:collapse;font-size:14px}
  thead th{text-align:left;font-size:11.5px;letter-spacing:.05em;text-transform:uppercase;color:var(--muted);padding:0 8px 10px}
  tbody td{padding:11px 8px;border-top:1px solid var(--line);vertical-align:middle}
  .pill{display:inline-flex;font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px}
  .pill.ok{background:#e2f0e8;color:var(--ok)}.pill.no{background:#f6e0e0;color:var(--danger)}
  .btn{border:none;border-radius:8px;padding:8px 11px;font-size:12.5px;font-weight:700;cursor:pointer;margin:2px}
  .btn.sm{background:#fff;border:1px solid var(--line);color:var(--ink)}
  .btn.sm.warn{color:var(--danger);border-color:#e6c3c3}
  .btn.add{background:var(--accent);color:#fff;width:100%;padding:12px;font-size:15px;border-radius:10px;margin-top:6px}
  .field{margin-bottom:12px}.field label{display:block;font-size:13px;font-weight:600;margin-bottom:5px}
  .field input,.field select{width:100%;border:1px solid var(--line);border-radius:9px;padding:10px 11px;font-size:14px}
  .row2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  .rec-line{display:flex;gap:6px;margin-bottom:6px;align-items:center}
  .rec-line select{flex:1}.rec-line input{width:80px}.rec-line button{width:28px;height:32px}
  .modal-bg{position:fixed;inset:0;background:rgba(40,26,18,.5);display:none;align-items:center;justify-content:center;z-index:100;padding:20px}
  .modal-bg.show{display:flex}
  .modal{background:#fff;border-radius:16px;padding:24px;max-width:520px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.3);max-height:90vh;overflow:auto}
  .modal h3{font-size:18px;margin-bottom:14px}
  .actions{display:flex;gap:10px;margin-top:14px}.actions button{flex:1;padding:11px;border-radius:9px;font-weight:700;font-size:14px;cursor:pointer;border:1px solid var(--line);background:#fff}
  .actions button.ok{background:var(--accent);color:#fff;border-color:var(--accent)}
  .actions button.danger{background:var(--danger);color:#fff;border-color:var(--danger)}
  @media(max-width:1000px){.grid{grid-template-columns:1fr}}
</style>

<link rel="stylesheet" href="../../responsive-ui.css">
<link rel="stylesheet" href="../../dashboard-navigation.css">
<link rel="stylesheet" href="../../inventory-ui.css">
<link rel="stylesheet" href="../../pos-responsive.css">
</head>
<body>
<header>
  <div><div class="brand">☕ Brew <span>&amp;</span> Co. <span style="font-weight:500;font-size:14px;color:#d8a066">| POS Admin</span></div></div>
  <div class="who">
    <div class="av"><?= $initials ?></div><span><?= htmlspecialchars($user['firstName'].' '.$user['lastName']) ?> (Admin)</span>
    <a href="cashier.php">Cashier</a><a href="client.php">Kiosk</a>
    <a href="../../user-management.php">Staff accounts</a>
    <a href="../../logout.php">Logout</a>
  </div>
</header>

<div class="wrap">
  <?php if (isset($_GET['saved'])): ?><div class="banner">✅ Saved.</div><?php endif; ?>
  <?php if (isset($_GET['err'])): ?><div class="banner bad">⚠ <?= isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : 'Something went wrong.' ?></div><?php endif; ?>
  <h1>Menu management</h1>

  <div class="grid">
    <div class="panel">
      <h2>Menu items</h2>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Item</th><th>Category</th><th>Price</th><th>Sold</th><th>Recipe</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($menu as $m): ?>
            <tr>
              <td><b><?= htmlspecialchars($m['name']) ?></b></td>
              <td><?= htmlspecialchars($m['cat_name'] ?? '—') ?></td>
              <td>₱<?= number_format($m['price']) ?></td>
              <td><?= (int)$m['sold'] ?></td>
              <td style="font-size:12px;color:var(--muted)">
                <?php if (isset($recipes[(int)$m['id']])): ?>
                  <?php foreach ($recipes[(int)$m['id']] as $r): ?><div><?= htmlspecialchars($r['ing_name']) ?> ×<?= rtrim(rtrim($r['qty'],'0'),'.') ?> <?= htmlspecialchars($r['unit']) ?></div><?php endforeach; ?>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td><span class="pill <?= $m['is_active']?'ok':'no' ?>"><?= $m['is_active']?'Active':'Off' ?></span></td>
              <td style="white-space:nowrap">
                <button class="btn sm" onclick="openEdit(<?= (int)$m['id'] ?>,<?= htmlspecialchars(json_encode($m['name'])) ?>,<?= (float)$m['price'] ?>,<?= (float)$m['cost'] ?>,<?= (int)$m['category_id'] ?>)">Edit</button>
                <form method="post" style="display:inline"><?= token_field() ?><input type="hidden" name="menu_action" value="toggle"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn sm"><?= $m['is_active']?'Off':'On' ?></button></form>
                <form method="post" style="display:inline" onsubmit="return sweetConfirmSubmit(event,'Delete this item permanently?')"><?= token_field() ?><input type="hidden" name="menu_action" value="delete"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn sm warn">Del</button></form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div>
      <div class="panel">
        <h2>Add new item</h2>
        <form method="post"><?= token_field() ?>
          <input type="hidden" name="menu_action" value="add">
          <div class="field"><label>Item name</label><input name="mname" required placeholder="e.g. Vanilla Latte"></div>
          <div class="row2">
            <div class="field"><label>Selling price (₱)</label><input name="mprice" type="number" min="1" step="0.5" required></div>
            <div class="field"><label>Cost (₱)</label><input name="mcost" type="number" min="0" step="0.5" value="0"></div>
          </div>
          <div class="field"><label>Category</label><select name="mcat"><option value="0">— None —</option><?php foreach($cats as $c): ?><option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label>Recipe (ingredients per serving)</label>
            <div id="newRec">
              <div class="rec-line"><select name="ing_item[]"><option value="0">— ingredient —</option><?php foreach($ingredients as $ing): ?><option value="<?= (int)$ing['id'] ?>"><?= htmlspecialchars($ing['name']) ?> (<?= $ing['unit'] ?>)</option><?php endforeach; ?></select><input type="number" name="ing_qty[]" min="0" step="0.001" placeholder="qty"><button type="button" onclick="this.parentElement.remove()">×</button></div>
            </div>
            <button type="button" class="btn sm" onclick="addRecLine('newRec')">+ add ingredient</button>
          </div>
          <button class="btn add" type="submit">+ Add item</button>
        </form>
      </div>

      <div class="panel">
        <h2>Categories</h2>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">
          <?php foreach ($cats as $c): ?><span class="pill <?= $c['is_active']?'ok':'no' ?>" style="padding:6px 11px"><?= htmlspecialchars($c['name']) ?></span><?php endforeach; ?>
        </div>
        <form method="post" style="display:flex;gap:8px"><?= token_field() ?>
          <input type="hidden" name="cat_action" value="add_cat">
          <input name="cname" placeholder="New category name" required style="flex:1;border:1px solid var(--line);border-radius:9px;padding:10px">
          <input name="csort" type="number" value="1" style="width:60px;border:1px solid var(--line);border-radius:9px;padding:10px">
          <button class="btn add" style="width:auto;padding:10px 16px;margin:0" type="submit">+ Add</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Edit modal -->
<div class="modal-bg" id="editBg">
  <div class="modal">
    <h3>Edit item</h3>
    <form method="post"><?= token_field() ?>
      <input type="hidden" name="menu_action" value="edit"><input type="hidden" name="id" id="mid">
      <div class="field"><label>Item name</label><input name="mname" id="mname" required></div>
      <div class="row2">
        <div class="field"><label>Price (₱)</label><input name="mprice" id="mprice" type="number" min="1" step="0.5" required></div>
        <div class="field"><label>Cost (₱)</label><input name="mcost" id="mcost" type="number" min="0" step="0.5"></div>
      </div>
      <div class="field"><label>Category</label><select name="mcat" id="mcat"><option value="0">— None —</option><?php foreach($cats as $c): ?><option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Recipe (ingredients per serving)</label><div id="editRec"></div><button type="button" class="btn sm" onclick="addRecLine('editRec')">+ add ingredient</button></div>
      <div class="actions"><button type="button" onclick="closeEdit()">Cancel</button><button class="ok" type="submit">Save</button></div>
    </form>
  </div>
</div>

<script>
const ING = <?= json_encode(array_map(function($i){ return ['id'=>(int)$i['id'],'name'=>$i['name'],'unit'=>$i['unit']]; }, $ingredients)) ?>;
const RECIPES = <?= json_encode($recipes) ?>;
const MENU = <?= json_encode(array_map(function($m){ return ['id'=>(int)$m['id'],'cat'=>(int)$m['category_id']]; }, $menu)) ?>;
function addRecLine(boxId, itemId, qty){
  const box = document.getElementById(boxId);
  const div = document.createElement('div');
  div.className='rec-line';
  let opts='<option value="0">— ingredient —</option>';
  ING.forEach(i=>{ opts += '<option value="'+i.id+'"'+(itemId==i.id?' selected':'')+'>'+i.name+' ('+i.unit+')</option>'; });
  div.innerHTML = '<select name="ing_item[]">'+opts+'</select><input type="number" name="ing_qty[]" min="0" step="0.001" placeholder="qty" value="'+(qty||'')+'"><button type="button" onclick="this.parentElement.remove()">×</button>';
  box.appendChild(div);
}
function openEdit(id,name,price,cost,cat){
  document.getElementById('mid').value=id;
  document.getElementById('mname').value=name;
  document.getElementById('mprice').value=price;
  document.getElementById('mcost').value=cost||0;
  document.getElementById('mcat').value=cat||0;
  const box=document.getElementById('editRec'); box.innerHTML='';
  (RECIPES[id]||[]).forEach(r=>{ addRecLine('editRec', r.menu_item_id? ingIdFromName(r.ing_name):0, r.qty); });
  document.getElementById('editBg').classList.add('show');
}
function ingIdFromName(n){ const f=ING.find(i=>i.name===n); return f?f.id:0; }
function closeEdit(){ document.getElementById('editBg').classList.remove('show'); }
document.getElementById('editBg').addEventListener('click',e=>{ if(e.target.id==='editBg') closeEdit(); });
</script>
<script src="../../input-guard.js"></script>
<script src="../../datatable.js"></script>
<script src="../../alerts.js"></script>
<script src="../../logout-confirm.js"></script>
<script src="../../ux-improvements.js"></script>

<script src="../../responsive-ui.js" defer></script>
<script src="../../dashboard-navigation.js" defer></script>
<script src="../../inventory-ui.js" defer></script>
<script src="../../pos-responsive.js" defer></script>
</body>
</html>
