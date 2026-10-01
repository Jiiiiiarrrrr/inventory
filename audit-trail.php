<?php
require __DIR__ . '/db.php';
define('PAGE_ROLE', 'superadmin');

// Role guard: audit trail is for superadmin (security-sensitive).
if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['superadmin'])) {
    header('Location: login.php'); exit;
}
$u = $_SESSION['user'];
$user = ['firstName'=>$u['firstName'],'lastName'=>$u['lastName'],'role'=>'Superadmin'];
$initials = strtoupper(substr($u['firstName'],0,1).substr($u['lastName'],0,1));

// Optional CSV export
if (isset($_GET['export']) && $_GET['export']==='csv' && db_ok()) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit-trail-'.date('Y-m-d').'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out, ['Date','User','Role','Action','Detail','IP']);
    $rows = db_all("SELECT a.created_at, CONCAT(u.first_name,' ',u.last_name) uname, a.role,
                           a.action, a.detail, a.ip
                    FROM audit_log a LEFT JOIN users u ON a.user_id=u.id
                    ORDER BY a.created_at DESC LIMIT 5000");
    foreach ($rows as $r) fputcsv($out, [$r['created_at'],$r['uname'],$r['role'],$r['action'],$r['detail'],$r['ip']]);
    fclose($out); exit;
}

// Filters
$fUser = (int)($_GET['user'] ?? 0);
$fAction = trim($_GET['action'] ?? '');
$fDate = trim($_GET['date'] ?? '');

$where = " WHERE 1=1 "; $params = [];
if ($fUser) { $where .= " AND a.user_id=?"; $params[] = $fUser; }
if ($fAction) { $where .= " AND a.action LIKE ?"; $params[] = $fAction.'%'; }
if ($fDate) { $where .= " AND DATE(a.created_at)=?"; $params[] = $fDate; }

// Pagination
$perPage = 25;
$totalLogs = db_ok() ? (int)db_one("SELECT COUNT(*) n FROM audit_log a LEFT JOIN users u ON a.user_id=u.id $where", $params)['n'] : 0;
$totalPages = max(1, (int)ceil($totalLogs / $perPage));
$page = max(1, (int)($_GET['p'] ?? 1));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$logs = db_ok() ? db_all("SELECT a.id, a.created_at, a.user_id,
                                 CONCAT(u.first_name,' ',u.last_name) uname, a.role,
                                 a.action, a.detail, a.ip
                          FROM audit_log a LEFT JOIN users u ON a.user_id=u.id
                          $where ORDER BY a.created_at DESC LIMIT $perPage OFFSET $offset", $params) : [];

$users = db_ok() ? db_all("SELECT id, first_name, last_name FROM users ORDER BY first_name") : [];
$allActions = db_ok() ? db_all("SELECT DISTINCT action FROM audit_log ORDER BY action") : [];
function actionColor($a){
    if (strpos($a,'approve')!==false || $a==='login' || strpos($a,'.add')!==false) return 'ok';
    if (strpos($a,'decline')!==false || strpos($a,'fail')!==false || strpos($a,'remove')!==false) return 'danger';
    if (strpos($a,'.edit')!==false || strpos($a,'.toggle')!==false || strpos($a,'.reset')!==false || strpos($a,'.advance')!==false) return 'warn';
    return 'neutral';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="favicon.ico?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Brew &amp; Co. — Audit Trail</title>
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
  .panel{background:var(--card);border-radius:var(--radius);padding:22px;box-shadow:var(--shadow)}
  .panel h2{font-size:18px;font-weight:800;margin-bottom:4px}.panel .desc{color:var(--muted);font-size:13px;margin-bottom:16px}
  .filters{display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;align-items:flex-end}
  .filters select,.filters input{border:1px solid var(--line);background:#fff;border-radius:10px;padding:9px 12px;font-size:13px;color:var(--ink);outline:none}
  .filters label{font-size:12px;color:var(--muted);display:block;margin-bottom:4px}
  .btn{display:inline-flex;align-items:center;gap:8px;border:1px solid var(--line);background:#fff;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:700;cursor:pointer;color:var(--ink)}
  .btn.primary{background:var(--accent);color:#fff;border-color:var(--accent)}.btn:hover{background:var(--cream-2)}
  .table-wrap{overflow-x:auto}
  table{width:100%;border-collapse:collapse;font-size:13.5px;min-width:760px}
  thead th{text-align:left;font-size:11.5px;letter-spacing:.05em;text-transform:uppercase;color:var(--muted);padding:0 10px 12px}
  tbody td{padding:11px 10px;border-top:1px solid var(--line);vertical-align:middle}
  tbody tr:hover{background:var(--cream-2)}
  .pill{display:inline-flex;font-size:11.5px;font-weight:700;padding:3px 9px;border-radius:20px;font-family:monospace}
  .pill.ok{background:#e2f0e8;color:var(--ok)}.pill.warn{background:#f8eecf;color:var(--warn)}.pill.danger{background:#f6e0e0;color:var(--danger)}.pill.neutral{background:#eee6dd;color:var(--brown-700)}
  .empty{padding:40px 20px;text-align:center;color:var(--muted);font-size:14px}
  @media(max-width:820px){.sidebar{position:fixed;left:0;top:0;transform:translateX(-100%)}.main{margin-left:0;padding:18px}.who .n,.who .r{display:none}}
.icon-btn{position:relative;width:44px;height:44px;border-radius:50%;background:#fff;box-shadow:0 8px 24px rgba(74,47,34,.08);display:flex;align-items:center;justify-content:center;cursor:pointer;border:none;color:#33261d}
  .user-area{display:flex;align-items:center;gap:18px}
  .pos{position:relative}
</style>

<link rel="stylesheet" href="responsive-ui.css">
<link rel="stylesheet" href="dashboard-navigation.css">
<link rel="stylesheet" href="final-operations.css">
</head>
<body>
<aside class="sidebar" id="sidebar">
    <a href="#" class="logo" onclick="goHome(event)" title="Go to dashboard" style="text-decoration:none;cursor:pointer;">
    <img src="brewco-logo.svg" alt="Brew & Co.">
    <span class="brand-name">Brew &amp; Co.</span>
    <span class="brand-tagline">Coffee Shop</span>
  </a>
  <nav class="nav" aria-label="Main navigation">
    <div class="nav-section-label">Users</div>
    <a href="user-management.php"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg> User Management</a>
    <a href="user-management.php?view=add"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg> Add User</a>

    <div class="nav-divider"></div>
    <div class="nav-section-label">System</div>
    <a data-view="audit" class="active" aria-current="page"><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg> Audit Trail</a>
    <a href="backup.php"><svg viewBox="0 0 24 24"><path d="M21 12v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-3M12 3v12M12 15l-4-4M12 15l4-4"/></svg> Backup &amp; Restore</a>

    <div class="nav-divider"></div>
    <div class="nav-section-label">Account</div>
    <a href="logout.php"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg> Logout</a>
  </nav></aside>

<main class="main">
  <div class="topbar">
    <div><h1>Audit Trail</h1><div class="sub">Every significant action, who did it, and when</div></div>
    <div class="user-area">
      <?= notification_bell($u["role"]) ?>
      <div class="who"><div class="avatar"><?= $initials ?></div>
    </div><div><div class="n"><?= htmlspecialchars($user['firstName'].' '.$user['lastName']) ?></div><div class="r"><?= htmlspecialchars($user['role']) ?></div></div></div>
  </div>

  <div class="panel">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
      <h2>Activity Log</h2>
      <?php if (db_ok()): ?><a class="btn primary" href="audit-trail.php?export=csv"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg> Export CSV</a><?php endif; ?>
    </div>
    <div class="desc">Use the filters to narrow down activity. Showing page <?= $page ?> of <?= $totalPages ?> (<?= $totalLogs ?> total entries).</div>

    <form class="filters" method="get" action="audit-trail.php">
      <div><label>User</label>
        <select name="user"><option value="0">All users</option><?php foreach($users as $usr): ?><option value="<?= (int)$usr['id'] ?>" <?= $fUser==$usr['id']?'selected':'' ?>><?= htmlspecialchars($usr['first_name'].' '.$usr['last_name']) ?></option><?php endforeach; ?></select>
      </div>
      <div><label>Action</label>
        <select name="action"><option value="">All actions</option><?php foreach($allActions as $ac): ?><option value="<?= htmlspecialchars($ac['action']) ?>" <?= $fAction===$ac['action']?'selected':'' ?>><?= htmlspecialchars($ac['action']) ?></option><?php endforeach; ?></select>
      </div>
      <div><label>Date</label><input type="date" name="date" value="<?= htmlspecialchars($fDate) ?>"></div>
      <div><button class="btn" type="submit">Filter</button>
      <?php if($fUser||$fAction||$fDate): ?><a class="btn" href="audit-trail.php">Clear</a><?php endif; ?></div>
    </form>

    <div class="table-wrap">
      <table>
        <thead><tr><th>Date</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
        <tbody>
          <?php foreach($logs as $l): ?>
          <tr>
            <td style="white-space:nowrap"><?= date('Y-m-d H:i:s', strtotime($l['created_at'])) ?></td>
            <td><b><?= htmlspecialchars($l['uname'] ?: 'System') ?></b><br><small style="color:var(--muted)"><?= htmlspecialchars($l['role'] ?? '') ?></small></td>
            <td><span class="pill <?= actionColor($l['action']) ?>"><?= htmlspecialchars($l['action']) ?></span></td>
            <td><?= htmlspecialchars($l['detail']) ?></td>
            <td style="color:var(--muted)"><?= htmlspecialchars($l['ip'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(!$logs): ?><tr><td colspan="5" class="empty">No activity found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;flex-wrap:wrap;gap:10px">
      <span style="font-size:13px;color:var(--muted)">Showing <?= (($page-1)*$perPage)+1 ?>–<?= min($page*$perPage, $totalLogs) ?> of <?= $totalLogs ?> records</span>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php if ($page > 1): ?><a class="btn" href="?user=<?= $fUser ?>&action=<?= urlencode($fAction) ?>&date=<?= urlencode($fDate) ?>&p=<?= $page-1 ?>">← Prev</a><?php endif; ?>
        <span style="padding:8px 14px;border:1px solid var(--line);border-radius:8px;font-size:13px;font-weight:700;background:#fff">Page <?= $page ?> / <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?><a class="btn" href="?user=<?= $fUser ?>&action=<?= urlencode($fAction) ?>&date=<?= urlencode($fDate) ?>&p=<?= $page+1 ?>">Next →</a><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
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
<script src="final-operations.js" defer></script>
</body>
</html>
