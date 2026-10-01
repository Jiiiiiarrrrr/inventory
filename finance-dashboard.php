<?php
require __DIR__ . '/db.php';
define('PAGE_ROLE', 'finance');

if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['finance','superadmin'])) {
    header('Location: login.php'); exit;
}
$u = $_SESSION['user'];
$roleLabel = ['clerk'=>'Inventory Clerk','manager'=>'Inventory Manager','finance'=>'Finance Officer','superadmin'=>'Superadmin'];
$user = ['firstName'=>$u['firstName'], 'lastName'=>$u['lastName'], 'role'=>$roleLabel[$u['role']] ?? $u['role']];
$initials = strtoupper(substr($u['firstName'],0,1).substr($u['lastName'],0,1));
inv_ensure_purchase_flow();

$goto = function($msg, $bad = false, $view = 'dashboard') {
    header('Location: finance-dashboard.php?'.($bad ? 'msg='.urlencode($msg).'&bad=1' : 'saved=1').'&view='.urlencode($view)); exit;
};

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!verify_token($_POST['token'] ?? '')) $goto('Invalid form token — please try again.', true);

    // ---- Budget Approvals: approve / decline a purchase request ----
    if (isset($_POST['pr_id'])) {
        $id = (int)$_POST['pr_id'];
        $decision = $_POST['decision'] ?? '';
        if (!$id || !in_array($decision, ['approve','decline'], true)) $goto('Invalid request.', true, 'approvals');
        if (!db_ok()) $goto('Database is not available.', true, 'approvals');
        $pr = db_one("SELECT id, pr_code, item_id, seller_id, item_desc, qty, unit, est_cost, status FROM purchase_requests WHERE id=?", [$id]);
        if (!$pr) $goto('Purchase request not found.', true, 'approvals');
        if ($pr['status'] !== 'pending') $goto('That request was already '.$pr['status'].'.', true, 'approvals');
        $newStatus = $decision === 'approve' ? 'approved' : 'declined';
        tx(function() use ($id, $newStatus, $u, $pr) {
            db_exec("UPDATE purchase_requests SET status=?, approved_by=?, decided_at=NOW() WHERE id=? AND status='pending'",
                    [$newStatus, (int)$u['id'], $id]);
            db_exec("INSERT INTO budget_log (pr_id, decision, amount, decided_by) VALUES (?,?,?,?)",
                    [$id, $newStatus, (float)$pr['est_cost'], (int)$u['id']]);
            if ($newStatus === 'approved') {
                $month = date('Y-m');
                $b = db_one("SELECT id FROM budget WHERE month=?", [$month]);
                if ($b) db_exec("UPDATE budget SET used = used + ? WHERE id=?", [(float)$pr['est_cost'], (int)$b['id']]);
                else db_exec("INSERT INTO budget (month, total, used) VALUES (?,0,?)", [$month, (float)$pr['est_cost']]);
            }
            if ($newStatus === 'approved') {
                // Approved requests go straight to Shipment Tracking; the manager sets the delivery date.
                $exists = db_one("SELECT id FROM shipments WHERE pr_id=?", [$id]);
                if (!$exists) {
                    $max = (int)(db_one("SELECT MAX(CAST(SUBSTRING(ref,5) AS UNSIGNED)) m FROM shipments")['m'] ?? 770);
                    $ref = 'SHP-' . ($max + 1);
                    db_exec('INSERT INTO shipments (ref,item_id,item_desc,qty,seller_id,eta,status,pr_id)
                             VALUES (?,?,?,?,?,?,?,?)',
                            [$ref, $pr['item_id'] ?: null,
                             $pr['item_desc'].' ('.number_format($pr['qty']).' '.$pr['unit'].')',
                             (float)$pr['qty'], $pr['seller_id'] ?: null, null, 'In transit', $id]);
                    audit($u, 'shipment.auto', 'Auto-created '.$ref.' from approved '.$pr['pr_code'].' — waiting for manager to set the delivery date');
                }
            }
            audit($u, 'pr.'.$newStatus, $newStatus.' '.$pr['pr_code'].': '.$pr['item_desc'].' x'.$pr['qty'].' '.$pr['unit'].' (₱'.number_format($pr['est_cost']).')');
            return true;
        });
        $goto('', false, 'approvals');
    }

    // ---- Item Price Approvals ----
    if (isset($_POST['price_id'])) {
        $id = (int)$_POST['price_id'];
        $decision = $_POST['price_decision'] ?? '';
        if (!$id || !in_array($decision, ['approve','decline'], true)) $goto('Invalid item.', true, 'prices');
        $it = db_one("SELECT id, code, name, cost, old_cost FROM items WHERE id=? AND cost_status='pending'", [$id]);
        if (!$it) $goto('That price change was already decided.', true, 'prices');
        if ($decision === 'approve') {
            db_exec("UPDATE items SET cost_status='approved', old_cost=NULL WHERE id=?", [$id]);
            audit($u, 'price.approve', 'Approved new price for '.$it['code'].' '.$it['name'].': ₱'.number_format($it['cost']));
        } else {
            db_exec("UPDATE items SET cost=COALESCE(old_cost,cost), cost_status='approved', old_cost=NULL WHERE id=?", [$id]);
            audit($u, 'price.decline', 'Declined new price for '.$it['code'].' '.$it['name'].' — kept ₱'.number_format($it['old_cost'] ?? $it['cost']));
        }
        $goto('', false, 'prices');
    }

    // ---- Budget Settings ----
    if (isset($_POST['budget_save'])) {
        $month = $_POST['month'] ?? '';
        $total = (float)($_POST['total'] ?? 0);
        if (!preg_match('/^\d{4}-\d{2}$/', $month) || $total < 0) $goto('Enter a valid month and budget amount.', true, 'budget');
        $b = db_one("SELECT id FROM budget WHERE month=?", [$month]);
        if ($b) db_exec("UPDATE budget SET total=? WHERE id=?", [$total, (int)$b['id']]);
        else db_exec("INSERT INTO budget (month, total, used) VALUES (?,?,0)", [$month, $total]);
        audit($u, 'budget.set', 'Set budget for '.$month.' to ₱'.number_format($total));
        $goto('', false, 'budget');
    }

    // ---- Menu-Level Costs: recompute a menu item's cost from its ingredients ----
    if (isset($_POST['menu_sync'])) {
        $mid = (int)$_POST['menu_sync'];
        $row = db_one("SELECT SUM(mi.qty * i.cost) c FROM menu_ingredients mi JOIN items i ON i.id=mi.item_id WHERE mi.menu_item_id=?", [$mid]);
        $calc = round((float)($row['c'] ?? 0), 2);
        db_exec("UPDATE menu_items SET cost=? WHERE id=?", [$calc, $mid]);
        $m = db_one("SELECT name FROM menu_items WHERE id=?", [$mid]);
        audit($u, 'menu.cost_sync', 'Synced cost of '.$m['name'].' from ingredients: ₱'.number_format($calc, 2));
        $goto('', false, 'menu');
    }

    // ---- Menu recipe editor (Finance / Superadmin) ----
    // A recipe is the source of truth for both the calculated cost and how many
    // complete drinks the POS can make from the available ingredient stock.
    if (isset($_POST['recipe_save'])) {
        if (!db_ok()) $goto('Database is not available.', true, 'menu');
        $mid = (int)($_POST['menu_id'] ?? 0);
        $menu = db_one("SELECT id, name FROM menu_items WHERE id=? AND deleted_at IS NULL AND is_active=1", [$mid]);
        if (!$menu) $goto('Menu item not found or inactive.', true, 'menu');

        $submittedIds = $_POST['recipe_item_id'] ?? [];
        $submittedQty = $_POST['recipe_qty'] ?? [];
        if (!is_array($submittedIds) || !is_array($submittedQty)) $goto('Invalid recipe lines.', true, 'menu');

        // Consolidate repeated ingredient selections before inserting; the table
        // correctly permits an ingredient only once per menu item.
        $recipe = [];
        foreach ($submittedIds as $index => $rawId) {
            $itemId = (int)$rawId;
            $qty = (float)($submittedQty[$index] ?? 0);
            if ($itemId <= 0 || $qty <= 0) $goto('Each recipe line needs an ingredient and a quantity above zero.', true, 'menu');
            $recipe[$itemId] = ($recipe[$itemId] ?? 0) + $qty;
        }
        if (!$recipe) $goto('Add at least one ingredient to the recipe.', true, 'menu');

        $totalCost = 0.0;
        $validLines = [];
        foreach ($recipe as $itemId => $qty) {
            $ingredient = db_one("SELECT id, name, cost FROM items WHERE id=? AND is_active=1", [$itemId]);
            if (!$ingredient) $goto('One of the selected ingredients is no longer active.', true, 'menu');
            $validLines[] = ['id'=>(int)$ingredient['id'], 'qty'=>round($qty, 3), 'name'=>$ingredient['name']];
            $totalCost += $qty * (float)$ingredient['cost'];
        }
        $totalCost = round($totalCost, 2);

        $savedRecipe = tx(function() use ($mid, $validLines, $totalCost) {
            db_exec("DELETE FROM menu_ingredients WHERE menu_item_id=?", [$mid]);
            foreach ($validLines as $line) {
                db_exec("INSERT INTO menu_ingredients (menu_item_id,item_id,qty) VALUES (?,?,?)", [$mid, $line['id'], $line['qty']]);
            }
            db_exec("UPDATE menu_items SET cost=? WHERE id=?", [$totalCost, $mid]);
            // The POS stock badge for a recipe item is based on its limiting
            // ingredient; recalculate it immediately after saving the recipe.
            inv_refresh_menu_stock();
            return true;
        });
        if (!$savedRecipe) $goto('Could not save the recipe. No changes were applied.', true, 'menu');

        audit($u, 'menu.recipe_save', 'Saved recipe for '.$menu['name'].' ('.count($validLines).' ingredient(s), calculated cost ₱'.number_format($totalCost, 2).')');
        $goto('Recipe saved and menu cost synced.', false, 'menu');
    }
}

// ---- Data ----
$pending = []; $decided = []; $pricePend = []; $menuRows = []; $menuIng = []; $menuCalc = []; $recipeIngredients = [];
$budgetRow = null; $budgetLog = [];
if (db_ok()) {
    $pending = db_all("SELECT pr.id, pr.pr_code, pr.item_desc, pr.qty, pr.unit, pr.est_cost, pr.letter_path,
                              pr.signature_path, pr.selfie_path,
                              CONCAT(m.first_name,' ',m.last_name) requester, DATE(pr.created_at) date
                       FROM purchase_requests pr
                       LEFT JOIN users m ON m.id=pr.requested_by
                       WHERE pr.status='pending'
                       ORDER BY pr.created_at ASC");
    $decided = db_all("SELECT pr.pr_code, pr.item_desc, bl.decision, bl.amount,
                              CONCAT(a.first_name,' ',a.last_name) approver, DATE(bl.decided_at) date
                       FROM budget_log bl
                       JOIN purchase_requests pr ON pr.id=bl.pr_id
                       LEFT JOIN users a ON a.id=bl.decided_by
                       ORDER BY bl.decided_at DESC LIMIT 100");
    $pricePend = db_all("SELECT id, code, name, unit, cost, old_cost FROM items WHERE cost_status='pending' ORDER BY name");
    $budgetRow = db_one("SELECT month, total, used FROM budget WHERE month=DATE_FORMAT(CURDATE(),'%Y-%m')");
    $budgetLog = db_all("SELECT month, total, used FROM budget ORDER BY month DESC LIMIT 12");
    $menuRows = db_all("SELECT id, name, emoji, price, cost, sold, stock FROM menu_items WHERE deleted_at IS NULL AND is_active=1 ORDER BY name");
    $servings = inv_recipe_servings_map();
    $menuIng  = db_all("SELECT mi.menu_item_id, mi.item_id, i.name ing, mi.qty, i.unit, i.cost FROM menu_ingredients mi JOIN items i ON i.id=mi.item_id ORDER BY mi.menu_item_id, i.name");
    $recipeIngredients = db_all("SELECT id, name, unit, cost, current_qty FROM items WHERE is_active=1 ORDER BY name");
}
foreach ($menuIng as $mi) $menuCalc[(int)$mi['menu_item_id']] = round(($menuCalc[(int)$mi['menu_item_id']] ?? 0) + (float)$mi['qty'] * (float)$mi['cost'], 2);
$pendingTotal = 0; foreach ($pending as $r) $pendingTotal += (float)$r['est_cost'];
$budgetLeft = $budgetRow ? (float)$budgetRow['total'] - (float)$budgetRow['used'] : null;
$outOfSync = 0;
foreach ($menuRows as $m) if (abs(($menuCalc[(int)$m['id']] ?? 0) - (float)$m['cost']) > 0.01) $outOfSync++;
$flash = $_GET['msg'] ?? ''; $flashBad = isset($_GET['bad']); $saved = isset($_GET['saved']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="favicon.ico?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Finance Dashboard — Brew &amp; Co.</title>
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
<style>
  .btn.ok{background:var(--ok);color:#fff;border-color:var(--ok)}
  .btn.ok:hover{background:#1d5a40}
  .btn.dngr:hover{background:#f6e0e0}
  .flash{border-radius:10px;padding:10px 14px;margin-bottom:16px;font-weight:600;font-size:14px}
  .flash.ok{background:#e2f0e8;color:var(--ok)}
  .flash.bad{background:#f6e0e0;color:var(--danger)}
  .decide-forms{display:flex;gap:6px;white-space:nowrap}
  .ing-list{font-size:12px;color:var(--muted);margin-top:4px}
  .kv{display:flex;justify-content:space-between;padding:11px 0;border-top:1px solid var(--line);font-size:14px}
  .kv span:first-child{color:var(--muted)}
  .sum-link{display:inline-flex;align-items:center;gap:8px;margin-top:12px;cursor:pointer}
  .recipe-editor{display:none;margin:0 0 18px;padding:18px;border:1px solid var(--line);border-radius:14px;background:linear-gradient(180deg,#fffdf9,#faf6f0)}
  .recipe-editor.open{display:block}
  .recipe-editor-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:12px}
  .recipe-editor h3{font-size:17px;margin:0 0 3px}.recipe-editor p{color:var(--muted);font-size:13px;line-height:1.45}
  .recipe-lines{display:grid;gap:8px;margin:12px 0}.recipe-line{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(112px,.55fr) minmax(70px,.32fr) auto;gap:8px;align-items:end;padding:10px;border:1px solid #eee3d7;border-radius:10px;background:#fff}
  .recipe-line label{display:block;margin:0 0 5px;font-size:11px;font-weight:800;color:var(--muted);letter-spacing:.03em;text-transform:uppercase}
  .recipe-line select,.recipe-line input{width:100%;min-height:40px;margin:0;padding:8px 10px;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--ink);font:inherit}
  .recipe-line-total{min-height:40px;display:flex;align-items:center;justify-content:flex-end;color:var(--ok);font-size:12px;font-weight:800;white-space:nowrap}
  .recipe-remove{min-width:40px;min-height:40px;padding:7px 10px;border:1px solid #e5bcbc;background:#fff5f4;color:var(--danger);border-radius:8px;font-weight:800;cursor:pointer}
  .recipe-foot{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:12px}.recipe-cost-preview{color:var(--ink);font-size:14px}.recipe-cost-preview b{color:var(--ok);font-size:16px}.menu-actions{display:flex;align-items:center;gap:7px;flex-wrap:wrap}.menu-actions form{margin:0}
  @media(max-width:640px){.recipe-line{grid-template-columns:1fr 1fr}.recipe-line .recipe-ing{grid-column:1/-1}.recipe-line-total{justify-content:flex-start}.recipe-remove{grid-column:2;grid-row:3}.recipe-editor-head{flex-direction:column}.recipe-foot{align-items:stretch}.recipe-foot .btn{width:100%;justify-content:center}}
</style>

<link rel="stylesheet" href="responsive-ui.css">
<link rel="stylesheet" href="dashboard-navigation.css">
<link rel="stylesheet" href="procurement-finance.css">
</head>
<body>
<aside class="sidebar" id="sidebar">
    <a href="#" class="logo" onclick="goHome(event)" title="Go to dashboard" style="text-decoration:none;cursor:pointer;">
    <img src="brewco-logo.svg" alt="Brew & Co.">
    <span class="brand-name">Brew &amp; Co.</span>
    <span class="brand-tagline">Coffee Shop</span>
  </a>
  <nav class="nav fin-nav" aria-label="Main navigation">
    <div class="nav-section-label">Overview</div>
    <a href="finance-dashboard.php" data-view="dashboard" class="active" aria-current="page"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg> Finance Dashboard</a>

    <div class="nav-divider"></div>
    <div class="nav-section-label">Approvals</div>
    <a href="finance-dashboard.php?view=approvals" data-view="approvals"><svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg> Budget Approvals<?= count($pending) ? ' <span class="pill warn" style="margin-left:6px">'.count($pending).'</span>' : '' ?></a>
    <a href="finance-dashboard.php?view=prices" data-view="prices"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg> Item Price Approvals<?= count($pricePend) ? ' <span class="pill warn" style="margin-left:6px">'.count($pricePend).'</span>' : '' ?></a>

    <div class="nav-divider"></div>
    <div class="nav-section-label">Settings</div>
    <a href="finance-dashboard.php?view=budget" data-view="budget"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg> Budget Settings</a>
    <a href="finance-dashboard.php?view=menu" data-view="menu"><svg viewBox="0 0 24 24"><path d="M18 8h1a4 4 0 0 1 0 8h-1M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4zM6 1v3M10 1v3M14 1v3"/></svg> Menu-Level Costs<?= $outOfSync ? ' <span class="pill warn" style="margin-left:6px">'.$outOfSync.'</span>' : '' ?></a>

    <div class="nav-divider"></div>
    <div class="nav-section-label">Reports</div>
    <a href="reports.php"><svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="M7 15l4-6 4 3 4-5"/></svg> Reports</a>

    <div class="nav-divider"></div>
    <div class="nav-section-label">Account</div>
    <a href="logout.php"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg> Logout</a>
  </nav></aside>

<main class="main">
  <div class="topbar">
    <div><h1 id="pageTitle">Finance Dashboard</h1><div class="sub" id="pageSub">Summary of approvals, budget, prices &amp; menu costs</div></div>
    <div class="user-area">
      <?= notification_bell($u["role"]) ?>
      <div class="who"><div class="avatar"><?= $initials ?></div><div><div class="n"><?= htmlspecialchars($user['firstName'].' '.$user['lastName']) ?></div><div class="r"><?= htmlspecialchars($user['role']) ?></div></div></div>
    </div>
  </div>

  <?php if($saved): ?><div class="flash ok">✅ Saved.</div><?php endif; ?>
  <?php if($flash): ?><div class="flash <?= $flashBad?'bad':'ok' ?>"><?= $flashBad?'⚠':'✅' ?> <?= htmlspecialchars($flash) ?></div><?php endif; ?>

  <!-- ============ DASHBOARD: SUMMARY ONLY ============ -->
  <section class="view active" id="view-dashboard">
    <div class="stats">
      <div class="stat"><div class="t">Pending purchase requests</div><div class="v <?= count($pending)?'warn':'ok' ?>"><?= count($pending) ?></div><div class="sm">₱<?= number_format($pendingTotal, 2) ?> estimated total</div></div>
      <div class="stat"><div class="t">Budget this month</div><div class="v"><?= $budgetRow ? '₱'.number_format((float)$budgetRow['total']) : '—' ?></div><div class="sm"><?= $budgetRow ? 'Used ₱'.number_format((float)$budgetRow['used']).' · Left ₱'.number_format(max(0,$budgetLeft)) : 'No budget set yet' ?></div></div>
      <div class="stat"><div class="t">Price changes waiting</div><div class="v <?= count($pricePend)?'warn':'ok' ?>"><?= count($pricePend) ?></div><div class="sm">item cost updates to review</div></div>
      <div class="stat"><div class="t">Menu costs out of sync</div><div class="v <?= $outOfSync?'warn':'ok' ?>"><?= $outOfSync ?></div><div class="sm">of <?= count($menuRows) ?> menu items</div></div>
    </div>

    <div class="panel">
      <h2>Needs your action</h2>
      <div class="desc">Everything waiting on Finance right now.</div>
      <?php if(!count($pending) && !count($pricePend)): ?><div class="empty">🎉 Nothing is waiting for approval.</div><?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Type</th><th>Reference</th><th>Details</th><th>Amount</th><th></th></tr></thead>
        <tbody>
        <?php foreach($pending as $r): ?>
          <tr>
            <td><span class="pill warn">Purchase request</span></td>
            <td><b><?= htmlspecialchars($r['pr_code']) ?></b></td>
            <td><?= htmlspecialchars($r['item_desc']) ?> · <?= (float)$r['qty'] ?> <?= htmlspecialchars($r['unit']) ?> · <?= htmlspecialchars($r['requester'] ?: '—') ?></td>
            <td>₱<?= number_format((float)$r['est_cost'], 2) ?></td>
            <td><button class="btn" onclick="showView('approvals')">Review →</button></td>
          </tr>
        <?php endforeach; ?>
        <?php foreach($pricePend as $r): $diff = (float)$r['cost'] - (float)($r['old_cost'] ?? $r['cost']); ?>
          <tr>
            <td><span class="pill warn">Price change</span></td>
            <td><b><?= htmlspecialchars($r['code']) ?></b></td>
            <td><?= htmlspecialchars($r['name']) ?> · ₱<?= number_format((float)($r['old_cost'] ?? $r['cost']), 2) ?> → ₱<?= number_format((float)$r['cost'], 2) ?></td>
            <td><span class="pill <?= $diff>0?'warn':'ok' ?>"><?= $diff>0?'+':'' ?><?= number_format($diff, 2) ?></span></td>
            <td><button class="btn" onclick="showView('prices')">Review →</button></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </div>

    <div class="grid2">
      <div class="panel">
        <h2>Recent decisions</h2>
        <div class="desc">Last 5 budget decisions.</div>
        <?php if(!$decided): ?><div class="empty">No decisions yet.</div><?php else: ?>
        <div class="table-wrap"><table>
          <thead><tr><th>PR</th><th>Decision</th><th>Amount</th><th>Date</th></tr></thead>
          <tbody>
          <?php foreach(array_slice($decided, 0, 5) as $r): ?>
            <tr>
              <td><b><?= htmlspecialchars($r['pr_code']) ?></b></td>
              <td><span class="pill <?= $r['decision']==='approved'?'ok':'danger' ?>"><?= $r['decision'] ?></span></td>
              <td>₱<?= number_format((float)$r['amount'], 2) ?></td>
              <td><?= htmlspecialchars($r['date']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <?php endif; ?>
        <button class="btn sum-link" onclick="showView('approvals')">Open Budget Approvals →</button>
      </div>
      <div class="panel">
        <h2>Budget pulse</h2>
        <div class="desc">This month at a glance.</div>
        <?php if($budgetRow): ?>
        <div class="kv"><span>Month</span><span><?= htmlspecialchars($budgetRow['month']) ?></span></div>
        <div class="kv"><span>Total budget</span><span>₱<?= number_format((float)$budgetRow['total'], 2) ?></span></div>
        <div class="kv"><span>Used</span><span>₱<?= number_format((float)$budgetRow['used'], 2) ?></span></div>
        <div class="kv"><span>Remaining</span><span style="color:<?= $budgetLeft>=0?'var(--ok)':'var(--danger)' ?>;font-weight:800">₱<?= number_format($budgetLeft, 2) ?></span></div>
        <?php else: ?><div class="empty">No budget set for this month yet.</div><?php endif; ?>
        <button class="btn sum-link" onclick="showView('budget')">Open Budget Settings →</button>
      </div>
    </div>
  </section>

  <!-- ============ BUDGET APPROVALS ============ -->
  <section class="view" id="view-approvals">
    <div class="panel">
      <h2>Budget Approvals</h2>
      <div class="desc">Purchase requests from the Inventory Manager, charged against the monthly budget. Each one carries its formal letter, e-signature and security photo — review them before deciding.</div>
      <?php if(!$pending): ?><div class="empty">🎉 No purchase requests waiting for approval.</div><?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>PR Code</th><th>Item</th><th>Qty</th><th>Est. Cost</th><th>Requested by</th><th>Date</th><th>Formal Letter</th><th>Signed / Photo</th><th>Decision</th></tr></thead>
        <tbody>
        <?php foreach($pending as $r): ?>
          <tr>
            <td><b><?= htmlspecialchars($r['pr_code']) ?></b></td>
            <td><?= htmlspecialchars($r['item_desc']) ?></td>
            <td><?= (float)$r['qty'] ?> <?= htmlspecialchars($r['unit']) ?></td>
            <td>₱<?= number_format((float)$r['est_cost'], 2) ?></td>
            <td><?= htmlspecialchars($r['requester'] ?: '—') ?></td>
            <td><?= htmlspecialchars($r['date']) ?></td>
            <td>
              <?php if(!empty($r['letter_path'])): ?>
                <a class="btn" href="<?= htmlspecialchars($r['letter_path']) ?>" target="_blank" rel="noopener">📄 View Letter</a>
              <?php else: ?><span style="color:var(--muted)">no letter</span><?php endif; ?>
            </td>
            <td style="white-space:nowrap">
              <?php if(!empty($r['signature_path'])): ?>
                <a class="btn" href="<?= htmlspecialchars($r['signature_path']) ?>" target="_blank" rel="noopener" title="View e-signature">✍️</a>
              <?php else: ?><span style="color:var(--muted)">—</span><?php endif; ?>
              <?php if(!empty($r['selfie_path'])): ?>
                <img src="<?= htmlspecialchars($r['selfie_path']) ?>" alt="Security photo" title="Security photo of the signer" onclick="window.open(this.src,'_blank')" style="width:34px;height:34px;object-fit:cover;border-radius:50%;border:2px solid var(--line);vertical-align:middle;cursor:pointer;margin-left:6px;background:#fff">
              <?php endif; ?>
            </td>
            <td>
              <div class="decide-forms">
                <form method="post" action="finance-dashboard.php"><?= token_field() ?>
                  <input type="hidden" name="pr_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="decision" value="approve">
                  <button class="btn ok" type="submit" onclick="return sweetConfirmSubmit(event,'Approve <?= htmlspecialchars($r['pr_code'], ENT_QUOTES) ?> for ₱<?= number_format((float)$r['est_cost']) ?>? It will be charged to this month\'s budget and sent to Shipment Tracking.')">Approve</button>
                </form>
                <form method="post" action="finance-dashboard.php"><?= token_field() ?>
                  <input type="hidden" name="pr_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="decision" value="decline">
                  <button class="btn dngr" type="submit" onclick="return sweetConfirmSubmit(event,'Decline <?= htmlspecialchars($r['pr_code'], ENT_QUOTES) ?>?')">Decline</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </div>
    <div class="panel">
      <h2>Approval Log</h2>
      <div class="desc">Every budget decision, newest first.</div>
      <?php if(!$decided): ?><div class="empty">No decisions yet.</div><?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>PR Code</th><th>Item</th><th>Amount</th><th>Decision</th><th>Decided by</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach($decided as $r): ?>
          <tr>
            <td><b><?= htmlspecialchars($r['pr_code']) ?></b></td>
            <td><?= htmlspecialchars($r['item_desc']) ?></td>
            <td>₱<?= number_format((float)$r['amount'], 2) ?></td>
            <td><span class="pill <?= $r['decision']==='approved'?'ok':'danger' ?>"><?= $r['decision'] ?></span></td>
            <td><?= htmlspecialchars($r['approver'] ?: '—') ?></td>
            <td><?= htmlspecialchars($r['date']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ============ ITEM PRICE APPROVALS ============ -->
  <section class="view" id="view-prices">
    <div class="panel">
      <h2>Item Price Approvals</h2>
      <div class="desc">When the Inventory Manager changes an item's unit cost, the new price waits here for your approval. Declining restores the previous approved price.</div>
      <?php if(!$pricePend): ?><div class="empty">No item price changes waiting.</div><?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Code</th><th>Item</th><th>Current approved</th><th>Proposed</th><th>Change</th><th>Decision</th></tr></thead>
        <tbody>
        <?php foreach($pricePend as $r): $diff = (float)$r['cost'] - (float)($r['old_cost'] ?? $r['cost']); ?>
          <tr>
            <td><b><?= htmlspecialchars($r['code']) ?></b></td>
            <td><?= htmlspecialchars($r['name']) ?> (per <?= htmlspecialchars($r['unit']) ?>)</td>
            <td>₱<?= number_format((float)($r['old_cost'] ?? $r['cost']), 2) ?></td>
            <td>₱<?= number_format((float)$r['cost'], 2) ?></td>
            <td><span class="pill <?= $diff>0?'warn':($diff<0?'ok':'') ?>"><?= $diff>0?'+':'' ?><?= number_format($diff, 2) ?></span></td>
            <td>
              <div class="decide-forms">
                <form method="post" action="finance-dashboard.php"><?= token_field() ?>
                  <input type="hidden" name="price_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="price_decision" value="approve">
                  <button class="btn ok" type="submit" onclick="return sweetConfirmSubmit(event,'Approve the new price for <?= htmlspecialchars($r['name'], ENT_QUOTES) ?>?')">Approve</button>
                </form>
                <form method="post" action="finance-dashboard.php"><?= token_field() ?>
                  <input type="hidden" name="price_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="price_decision" value="decline">
                  <button class="btn dngr" type="submit" onclick="return sweetConfirmSubmit(event,'Decline the new price and keep the old one?')">Decline</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ============ BUDGET SETTINGS ============ -->
  <section class="view" id="view-budget">
    <div class="grid2">
      <div class="panel">
        <h2>Budget Settings</h2>
        <div class="desc">Set the monthly purchase budget. Approved purchase requests are deducted from it automatically.</div>
        <form method="post" action="finance-dashboard.php"><?= token_field() ?>
          <input type="hidden" name="budget_save" value="1">
          <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <div><label style="display:block;font-weight:700;font-size:13px;margin-bottom:6px">Month</label>
              <input type="month" name="month" value="<?= htmlspecialchars($budgetRow['month'] ?? date('Y-m')) ?>" style="padding:9px 12px;border:1.5px solid var(--line);border-radius:10px;font:inherit;background:#faf6f0"></div>
            <div><label style="display:block;font-weight:700;font-size:13px;margin-bottom:6px">Total budget (₱)</label>
              <input type="number" name="total" min="0" step="0.01" value="<?= htmlspecialchars((string)($budgetRow['total'] ?? '')) ?>" placeholder="e.g. 150000" style="padding:9px 12px;border:1.5px solid var(--line);border-radius:10px;font:inherit;background:#faf6f0;width:180px"></div>
            <button class="btn ok" type="submit" onclick="return sweetConfirmSubmit(event,'Save this budget setting?')">💾 Save Budget</button>
          </div>
        </form>
        <?php if($budgetRow): ?>
        <div style="margin-top:16px">
          <div class="kv"><span>This month (<?= htmlspecialchars($budgetRow['month']) ?>)</span><span>₱<?= number_format((float)$budgetRow['total'], 2) ?></span></div>
          <div class="kv"><span>Used by approved requests</span><span>₱<?= number_format((float)$budgetRow['used'], 2) ?></span></div>
          <div class="kv"><span>Remaining</span><span style="color:<?= $budgetLeft>=0?'var(--ok)':'var(--danger)' ?>;font-weight:800">₱<?= number_format($budgetLeft, 2) ?></span></div>
        </div>
        <?php endif; ?>
      </div>
      <div class="panel">
        <h2>Budget by Month</h2>
        <div class="desc">Recent months at a glance.</div>
        <?php if(!$budgetLog): ?><div class="empty">No budget records yet.</div><?php else: ?>
        <div class="table-wrap"><table>
          <thead><tr><th>Month</th><th>Total</th><th>Used</th><th>Remaining</th></tr></thead>
          <tbody>
          <?php foreach($budgetLog as $b): $left = (float)$b['total'] - (float)$b['used']; ?>
            <tr>
              <td><b><?= htmlspecialchars($b['month']) ?></b></td>
              <td>₱<?= number_format((float)$b['total'], 2) ?></td>
              <td>₱<?= number_format((float)$b['used'], 2) ?></td>
              <td><span class="pill <?= $left>=0?'ok':'danger' ?>">₱<?= number_format($left, 2) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- ============ MENU-LEVEL COSTS ============ -->
  <section class="view" id="view-menu">
    <div class="panel">
      <h2>Menu-Level Costs</h2>
      <div class="desc">What each menu item costs to make, computed from its ingredient recipe at current approved prices. Add or edit the recipe here; saving immediately updates the calculated menu cost and the whole-drinks-from-stock count used by POS.</div>
      <div class="recipe-editor" id="recipeEditor" aria-live="polite">
        <div class="recipe-editor-head">
          <div><h3 id="recipeEditorTitle">Recipe</h3><p>Set the quantity of each inventory ingredient needed for one serving. The calculated cost is saved automatically.</p></div>
          <button type="button" class="btn" onclick="closeRecipeEditor()">Close</button>
        </div>
        <form method="post" action="finance-dashboard.php?view=menu" id="recipeForm">
          <?= token_field() ?>
          <input type="hidden" name="recipe_save" value="1">
          <input type="hidden" name="menu_id" id="recipeMenuId" value="">
          <div class="recipe-lines" id="recipeLines"></div>
          <button type="button" class="btn" onclick="addRecipeLine()">＋ Add ingredient</button>
          <div class="recipe-foot">
            <div class="recipe-cost-preview">Calculated recipe cost: <b id="recipeCostPreview">₱0.00</b></div>
            <button type="submit" class="btn primary" onclick="return sweetConfirmSubmit(event,'Save this recipe and sync the menu cost?')">Save recipe &amp; sync cost</button>
          </div>
        </form>
      </div>
      <?php if(!$menuRows): ?><div class="empty">No menu items.</div><?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Menu item</th><th>Price</th><th>Recorded cost</th><th>Computed from recipe</th><th>Whole drinks from stock</th><th>Margin</th><th>Recipe</th><th></th></tr></thead>
        <tbody>
        <?php foreach($menuRows as $m):
          $calc = $menuCalc[(int)$m['id']] ?? 0;
          $margin = (float)$m['price'] > 0 ? round(((float)$m['price'] - $calc) / (float)$m['price'] * 100, 1) : 0;
          $ings = array_values(array_filter($menuIng, function($x) use ($m){ return (int)$x['menu_item_id'] === (int)$m['id']; }));
        ?>
          <tr>
            <td><b><?= htmlspecialchars($m['emoji'].' '.$m['name']) ?></b></td>
            <td>₱<?= number_format((float)$m['price'], 2) ?></td>
            <td>₱<?= number_format((float)$m['cost'], 2) ?></td>
            <td>₱<?= number_format($calc, 2) ?><?= abs($calc - (float)$m['cost']) > 0.01 ? ' <span class="pill warn">out of sync</span>' : '' ?></td>
            <td><?php $srv = $servings[(int)$m['id']] ?? null; if ($srv === null): ?><span style="color:var(--muted)">no recipe</span><?php else: ?><span class="pill <?= $srv<=0?'danger':($srv<=5?'warn':'ok') ?>"><?= $srv ?> whole drink<?= $srv==1?'':'s' ?></span><?php endif; ?></td>
            <td><span class="pill <?= $margin>=50?'ok':($margin>=30?'warn':'danger') ?>"><?= $margin ?>%</span></td>
            <td>
              <details><summary style="cursor:pointer;font-size:12.5px;color:var(--muted)"><?= count($ings) ?> ingredient(s)</summary>
                <div class="ing-list"><?php foreach($ings as $ig): ?>• <?= htmlspecialchars($ig['ing']) ?> — <?= (float)$ig['qty'] ?> <?= htmlspecialchars($ig['unit']) ?> × ₱<?= number_format((float)$ig['cost'], 2) ?><br><?php endforeach; ?></div>
              </details>
            </td>
            <td>
              <div class="menu-actions">
                <button type="button" class="btn" onclick="openRecipeEditor(<?= (int)$m['id'] ?>)"><?= $ings ? 'Edit recipe' : 'Add recipe' ?></button>
                <form method="post" action="finance-dashboard.php?view=menu"><?= token_field() ?>
                  <button class="btn" name="menu_sync" value="<?= (int)$m['id'] ?>" type="submit" onclick="return sweetConfirmSubmit(event,'Sync the cost of <?= htmlspecialchars($m['name'], ENT_QUOTES) ?> to ₱<?= number_format($calc, 2) ?>?')">Sync cost</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </div>
  </section>
</main>

<script src="input-guard.js"></script>
<script>function goHome(e){ if(e) e.preventDefault(); showView('dashboard'); }</script>
<script src="alerts.js"></script>
<script src="logout-confirm.js"></script>
<?= notification_script() ?>
<script src="ux-improvements.js"></script>
<script>
// Recipe editor data is rendered server-side so it works with the current
// finance session and requires no extra API route.
const FIN_RECIPE_INGREDIENTS = <?= json_encode(array_map(function($i){ return ['id'=>(int)$i['id'], 'name'=>$i['name'], 'unit'=>$i['unit'], 'cost'=>(float)$i['cost'], 'stock'=>(float)$i['current_qty']]; }, $recipeIngredients), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const FIN_MENU_RECIPES = <?= json_encode(array_reduce($menuIng, function($out, $line){ $id=(int)$line['menu_item_id']; if(!isset($out[$id])) $out[$id]=[]; $out[$id][]=['item_id'=>(int)$line['item_id'], 'qty'=>(float)$line['qty']]; return $out; }, []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const FIN_MENU_NAMES = <?= json_encode(array_reduce($menuRows, function($out, $item){ $out[(int)$item['id']]=$item['name']; return $out; }, []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function recipeMoney(value){ return '₱' + Number(value || 0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); }
function recipeIngredientById(id){ return FIN_RECIPE_INGREDIENTS.find(item => Number(item.id) === Number(id)); }
function recipeOptions(selectedId){
  const placeholder = '<option value="">— Select inventory ingredient —</option>';
  return placeholder + FIN_RECIPE_INGREDIENTS.map(item => `<option value="${item.id}" ${Number(item.id)===Number(selectedId)?'selected':''}>${String(item.name).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')} (${item.unit}; ₱${Number(item.cost).toFixed(2)}/${item.unit})</option>`).join('');
}
function recipeLineMarkup(line){
  const selected = line || {};
  return `<div class="recipe-line">
    <div class="recipe-ing"><label>Ingredient</label><select name="recipe_item_id[]" onchange="updateRecipePreview()">${recipeOptions(selected.item_id)}</select></div>
    <div><label>Qty / serving</label><input name="recipe_qty[]" type="number" min="0.001" step="0.001" value="${selected.qty || ''}" placeholder="e.g. 0.020" oninput="updateRecipePreview()"></div>
    <div class="recipe-line-total">₱0.00</div>
    <button class="recipe-remove" type="button" title="Remove ingredient" onclick="this.closest('.recipe-line').remove();updateRecipePreview()">×</button>
  </div>`;
}
function addRecipeLine(line){
  document.getElementById('recipeLines').insertAdjacentHTML('beforeend', recipeLineMarkup(line));
  updateRecipePreview();
}
function openRecipeEditor(menuId){
  const editor=document.getElementById('recipeEditor');
  const lines=document.getElementById('recipeLines');
  document.getElementById('recipeMenuId').value=menuId;
  document.getElementById('recipeEditorTitle').textContent='Recipe — ' + (FIN_MENU_NAMES[menuId] || 'Menu item');
  lines.innerHTML='';
  const recipe=FIN_MENU_RECIPES[menuId] || [];
  if(recipe.length) recipe.forEach(addRecipeLine); else addRecipeLine();
  editor.classList.add('open');
  editor.scrollIntoView({behavior:'smooth',block:'start'});
}
function closeRecipeEditor(){ document.getElementById('recipeEditor').classList.remove('open'); }
function updateRecipePreview(){
  let total=0;
  document.querySelectorAll('#recipeLines .recipe-line').forEach(row => {
    const ingredient=recipeIngredientById(row.querySelector('[name="recipe_item_id[]"]').value);
    const qty=Number(row.querySelector('[name="recipe_qty[]"]').value || 0);
    const amount=ingredient && qty>0 ? qty * Number(ingredient.cost || 0) : 0;
    row.querySelector('.recipe-line-total').textContent=recipeMoney(amount);
    total+=amount;
  });
  document.getElementById('recipeCostPreview').textContent=recipeMoney(total);
}
</script>
<script>
const FIN_META = {
  dashboard:{t:'Finance Dashboard', s:'Summary of approvals, budget, prices & menu costs'},
  approvals:{t:'Budget Approvals', s:'Purchase requests charged against the monthly budget'},
  prices:{t:'Item Price Approvals', s:'Approve or decline proposed item cost changes'},
  budget:{t:'Budget Settings', s:'Monthly purchase budget & usage'},
  menu:{t:'Menu-Level Costs', s:'Recipe-based cost per menu item'}
};
function showView(name){
  if(!FIN_META[name]) name = 'dashboard';
  document.querySelectorAll('main .view').forEach(v => v.classList.remove('active'));
  const el = document.getElementById('view-' + name);
  if (el) el.classList.add('active');
  document.querySelectorAll('.fin-nav a[data-view]').forEach(a => {
    a.classList.toggle('active', a.dataset.view === name);
    if (a.dataset.view === name) a.setAttribute('aria-current','page'); else a.removeAttribute('aria-current');
  });
  document.getElementById('pageTitle').textContent = FIN_META[name].t;
  document.getElementById('pageSub').innerHTML = FIN_META[name].s;
  window.scrollTo({top:0, behavior:'smooth'});
}
document.querySelectorAll('.fin-nav a[data-view]').forEach(a => a.addEventListener('click', e => { e.preventDefault(); showView(a.dataset.view); history.replaceState(null,'','?view='+a.dataset.view); }));
document.addEventListener('DOMContentLoaded', () => {
  const params = new URLSearchParams(location.search);
  let v = params.get('view') || location.hash.replace('#','');
  const map = {approvals:'approvals', prices:'prices', budget:'budget', menu:'menu'};
  showView(map[v] || (FIN_META[v] ? v : 'dashboard'));
});
</script>

<script src="responsive-ui.js" defer></script>
<script src="dashboard-navigation.js" defer></script>
<script src="procurement-finance.js" defer></script>
</body>
</html>
