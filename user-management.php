<?php
require __DIR__ . '/db.php';
define('PAGE_ROLE', 'superadmin');

// Role guard: only a logged-in superadmin may manage users.
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== PAGE_ROLE) {
    header('Location: login.php'); exit;
}
$u = $_SESSION['user'];
$user = ['firstName' => $u['firstName'], 'lastName' => $u['lastName'], 'role' => 'Superadmin'];
$initials = strtoupper(substr($user['firstName'],0,1).substr($user['lastName'],0,1));

$roles = ['clerk'=>'Clerk','manager'=>'Manager','finance'=>'Finance','superadmin'=>'Superadmin',
          'cashier'=>'Cashier','barista'=>'Barista','cleaner'=>'Cleaner','admin'=>'Admin'];
$defaultPass = 'password123'; // automatic default password for new accounts
$flash = $_GET['msg'] ?? '';
$flashBad = isset($_GET['bad']);

// ---- Handle form actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_token($_POST['token'] ?? '')) { header('Location: user-management.php?msg='.urlencode('Invalid form token.').'&bad=1'); exit; }
    if (db_ok()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $fn = trim($_POST['first_name'] ?? ''); $ln = trim($_POST['last_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? '')); $role = $_POST['role'] ?? 'clerk';
        $pass = $_POST['password'] ?? '';
        if ($pass === '') $pass = $defaultPass; // automatic default password
        if ($fn && $ln && filter_var($email, FILTER_VALIDATE_EMAIL) && $pass && array_key_exists($role,$roles)) {
            if (db_one('SELECT id FROM users WHERE email=?', [$email])) {
                header('Location: user-management.php?msg='.urlencode('That email is already in use.').'&bad=1'); exit;
            }
            db_exec('INSERT INTO users (first_name,last_name,email,password_hash,role,is_active)
                     VALUES (?,?,?,?,?,1)',
                    [$fn,$ln,$email, password_hash($pass, PASSWORD_DEFAULT), $role]);
            audit($u, 'user.add', 'Created user '.$fn.' '.$ln.' ('.$email.', '.$role.')');
            header('Location: user-management.php?msg='.urlencode('User added successfully.')); exit;
        }
        header('Location: user-management.php?msg='.urlencode('Please fill all fields correctly.').'&bad=1'); exit;
    }

    elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $fn = trim($_POST['first_name'] ?? ''); $ln = trim($_POST['last_name'] ?? '');
        $role = $_POST['role'] ?? '';
        if ($id && $fn && $ln && array_key_exists($role,$roles)) {
            db_exec('UPDATE users SET first_name=?, last_name=?, role=? WHERE id=?', [$fn,$ln,$role,$id]);
            audit($u, 'user.edit', 'Edited user #'.$id.' ('.$fn.' '.$ln.', '.$role.')');
            header('Location: user-management.php?msg='.urlencode('User updated.')); exit;
        }
        header('Location: user-management.php?msg='.urlencode('Update failed.').'&bad=1'); exit;
    }

    elseif ($action === 'reset') {
        $id = (int)($_POST['id'] ?? 0);
        $pass = $_POST['password'] ?? '';
        if ($id && strlen($pass) >= 4) {
            db_exec('UPDATE users SET password_hash=? WHERE id=?', [password_hash($pass, PASSWORD_DEFAULT), $id]);
            audit($u, 'user.reset_pw', 'Reset password for user #'.$id);
            header('Location: user-management.php?msg='.urlencode('Password reset.')); exit;
        }
        header('Location: user-management.php?msg='.urlencode('Password must be at least 4 characters.').'&bad=1'); exit;
    }

    elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id && $id !== (int)$u['id']) { // never let admin deactivate themselves
            db_exec('UPDATE users SET is_active = IF(is_active=1,0,1) WHERE id=?', [$id]);
            audit($u, 'user.toggle', 'Toggled access for user #'.$id);
            header('Location: user-management.php?msg='.urlencode('User status toggled.')); exit;
        }
        header('Location: user-management.php?msg='.urlencode('You cannot deactivate your own account.').'&bad=1'); exit;
    }
    }
}

// ---- Search + pagination ----
$q = trim($_GET['q'] ?? '');
$uPage = max(1, (int)($_GET['up'] ?? 1));
$uPer = 15;
$uWhere = " 1=1 "; $uParams = [];
if ($q) { $uWhere .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)"; $uParams = ["%$q%","%$q%","%$q%"]; }
$totalUsers = db_ok() ? (int)db_one("SELECT COUNT(*) n FROM users WHERE $uWhere", $uParams)['n'] : 0;
$uTotalPages = max(1, (int)ceil($totalUsers / $uPer));
$uPage = min($uPage, $uTotalPages);
$uOff = ($uPage - 1) * $uPer;

$users = db_ok()
    ? db_all("SELECT id, first_name, last_name, email, role, is_active, created_at
              FROM users WHERE $uWhere ORDER BY role, first_name LIMIT $uPer OFFSET $uOff", $uParams)
    : [];

// Counts reflect all matching users (for the stat cards)
$allUsers = db_ok() ? db_all("SELECT role FROM users WHERE $uWhere", $uParams) : [];
$counts = ['clerk'=>0,'manager'=>0,'finance'=>0,'superadmin'=>0,'cashier'=>0,'barista'=>0,'cleaner'=>0,'admin'=>0];
foreach ($allUsers as $usr) if (isset($counts[$usr['role']])) $counts[$usr['role']]++;
$matchingTotal = count($allUsers);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="favicon.ico?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Brew &amp; Co. — User Management</title>
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
  .nav a{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:10px;color:var(--ink);font-weight:500;font-size:14.5px;margin-bottom:4px}
  .nav a:hover{background:rgba(169,113,74,.08);color:var(--accent)}.nav a.active{background:var(--accent);color:#fff;font-weight:600}
  .nav-section-label{font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);padding:14px 14px 6px;opacity:0.7;font-weight:700}
  .nav-divider{height:1px;background:var(--line);margin:6px 14px}

  .main{flex:1;margin-left:250px;padding:26px 34px;min-width:0}
  .topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;gap:12px}
  .topbar h1{font-size:26px;font-weight:800}.topbar .sub{color:var(--muted);font-size:13px;margin-top:2px}
  .who{display:flex;align-items:center;gap:11px}.avatar{width:44px;height:44px;border-radius:50%;background:var(--brown-500);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700}
  .who .n{font-weight:700;font-size:14px}.who .r{font-size:12px;color:var(--muted)}
  .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:22px}
  .stat{background:var(--card);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow)}
  .stat .t{font-size:13px;color:var(--muted);margin-bottom:10px}.stat .v{font-size:32px;font-weight:800}.stat .v.warn{color:var(--warn)}
  .grid{display:grid;grid-template-columns:1.15fr .85fr;gap:18px}
  .panel{background:var(--card);border-radius:var(--radius);padding:22px;box-shadow:var(--shadow);margin-bottom:18px}
  .panel h2{font-size:18px;font-weight:800;margin-bottom:4px}.panel .desc{color:var(--muted);font-size:13px;margin-bottom:16px}
  .search{flex:1;min-width:180px;display:flex;align-items:center;gap:8px;background:var(--cream-2);border:1px solid var(--line);border-radius:10px;padding:9px 12px;color:var(--muted);margin-bottom:14px}
  .search input{border:none;background:transparent;outline:none;width:100%;font-size:14px;color:var(--ink)}
  .table-wrap{overflow-x:auto}
  table{width:100%;border-collapse:collapse;font-size:14px;min-width:520px}
  thead th{text-align:left;font-size:11.5px;letter-spacing:.05em;text-transform:uppercase;color:var(--muted);padding:0 10px 12px}
  tbody td{padding:13px 10px;border-top:1px solid var(--line);vertical-align:middle}
  tbody tr:hover{background:var(--cream-2)}
  .pill{display:inline-flex;font-size:12px;font-weight:700;padding:4px 10px;border-radius:20px}
  .pill.ok{background:#e2f0e8;color:var(--ok)}.pill.warn{background:#f8eecf;color:var(--warn)}.pill.super{background:#efe4da;color:var(--brown-700)}
  .pill.clerk{background:#e2f0e8;color:var(--ok)}.pill.manager{background:#e6e9f5;color:#3f4f9e}.pill.finance{background:#f6e0e0;color:var(--danger)}
  .field{margin-bottom:14px}.field label{display:block;font-size:13px;font-weight:600;margin-bottom:6px}.field .req{color:var(--danger)}
  .field input,.field select{width:100%;border:1px solid var(--line);border-radius:10px;padding:11px 12px;font-size:14px;background:#fff;color:var(--ink);outline:none;font-family:inherit}
  .field input:focus,.field select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(169,113,74,.15)}
  .row2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .btn-primary{width:100%;display:flex;align-items:center;justify-content:center;gap:8px;background:var(--accent);color:#fff;border:none;border-radius:10px;padding:13px;font-size:15px;font-weight:700;cursor:pointer;margin-top:4px}
  .btn-primary:hover{background:var(--accent-dark)}
  .btn-sm{border:1px solid var(--line);background:#fff;border-radius:8px;padding:6px 10px;font-size:12px;font-weight:700;cursor:pointer;color:var(--ink)}
  .btn-sm.ok{color:var(--ok);border-color:#bfe0cd}.btn-sm.no{color:var(--danger);border-color:#e6c3c3}.btn-sm.warn{color:var(--warn);border-color:#ecd9a8}
  .empty{padding:34px 20px;text-align:center;color:var(--muted);font-size:14px}
  .alert{background:#e2f0e8;color:var(--ok);border-radius:10px;padding:12px 14px;font-size:13px;margin-bottom:18px}
  .alert.bad{background:#f6e0e0;color:var(--danger)}
  .modal-bg{position:fixed;inset:0;background:rgba(40,26,18,.5);display:none;align-items:center;justify-content:center;z-index:130;padding:20px}
  .modal-bg.show{display:flex}.modal{background:#fff;border-radius:16px;padding:26px;max-width:400px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.3)}
  .modal h3{font-size:18px;margin-bottom:8px}.modal p{font-size:14px;color:var(--muted);margin-bottom:20px}
  .modal .actions{display:flex;gap:10px}.modal button{flex:1;padding:11px;border-radius:10px;font-weight:700;font-size:14px;cursor:pointer;border:1px solid var(--line);background:#fff;color:var(--ink)}
  .toast{position:fixed;top:22px;right:22px;background:var(--ok);color:#fff;padding:14px 18px;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);font-size:14px;font-weight:600;display:flex;align-items:center;gap:10px;transform:translateX(140%);transition:.4s;z-index:120}
  .toast.show{transform:translateX(0)}
  @media(max-width:1050px){.stats{grid-template-columns:repeat(2,1fr)}.grid{grid-template-columns:1fr}}
  @media(max-width:820px){.sidebar{position:fixed;left:0;top:0;transform:translateX(-100%);transition:.3s}.sidebar.open{transform:translateX(0)}.main{margin-left:0;padding:18px}.who .n,.who .r{display:none}}
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
    <a data-view="users" class="active" aria-current="page"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg> User Management</a>
    <a data-view="add"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg> Add User</a>

    <div class="nav-divider"></div>
    <div class="nav-section-label">System</div>
    <a href="audit-trail.php"><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg> Audit Trail</a>
    <a href="backup.php"><svg viewBox="0 0 24 24"><path d="M21 12v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-3M12 3v12M12 15l-4-4M12 15l4-4"/></svg> Backup &amp; Restore</a>

    <div class="nav-divider"></div>
    <div class="nav-section-label">Account</div>
    <a href="logout.php"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg> Logout</a>
  </nav></aside>

<main class="main">
  <div class="topbar">
    <div><h1>User Management</h1><div class="sub">Create accounts, assign roles, reset passwords &amp; control access</div></div>
    <div class="user-area">
      <?= notification_bell($u["role"]) ?>
      <div class="who">
      <div class="avatar"><?= $initials ?></div>
    </div>
      <div><div class="n"><?= htmlspecialchars($user['firstName'].' '.$user['lastName']) ?></div><div class="r"><?= htmlspecialchars($user['role']) ?></div></div>
    </div>
  </div>

  <?php if ($flash): ?><div class="alert <?= $flashBad ? 'bad' : '' ?>"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

  <!-- Stats -->
  <div class="stats">
    <div class="stat"><div class="t">Total Users</div><div class="v"><?= $matchingTotal ?></div></div>
    <div class="stat"><div class="t">Clerks</div><div class="v"><?= $counts['clerk'] ?></div></div>
    <div class="stat"><div class="t">Managers</div><div class="v"><?= $counts['manager'] ?></div></div>
    <div class="stat"><div class="t">Finance</div><div class="v warn"><?= $counts['finance'] ?></div></div>
  </div>

  <!-- Users list -->
  <div class="panel" id="view-users">
    <h2>All Accounts</h2>
    <div class="desc">Click Edit to change details, or use the row actions to reset a password or toggle access.</div>
      <form class="search" method="get" action="user-management.php"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg><input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search name or email..." aria-label="Search users"><button class="btn-sm" type="submit">Search</button><?php if($q): ?><a href="user-management.php" class="btn-sm">Clear</a><?php endif; ?></form>
    <div class="table-wrap">
      <table id="userTbl">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($users as $usr): $me = (int)$usr['id'] === (int)$u['id']; ?>
          <tr>
            <td><b><?= htmlspecialchars($usr['first_name'].' '.$usr['last_name']) ?></b><?= $me ? ' <span style="color:var(--muted);font-size:11px">(you)</span>' : '' ?></td>
            <td><?= htmlspecialchars($usr['email']) ?></td>
            <td><span class="pill <?= $usr['role'] ?>"><?= $roles[$usr['role']] ?? $usr['role'] ?></span></td>
            <td><span class="pill <?= $usr['is_active'] ? 'ok' : 'warn' ?>"><?= $usr['is_active'] ? 'Active' : 'Inactive' ?></span></td>
            <td style="white-space:nowrap">
              <button class="btn-sm" onclick="openEdit(<?= (int)$usr['id'] ?>, <?= htmlspecialchars(json_encode($usr['first_name'])) ?>, <?= htmlspecialchars(json_encode($usr['last_name'])) ?>, '<?= $usr['role'] ?>')">Edit</button>
              <button class="btn-sm warn" onclick="openReset(<?= (int)$usr['id'] ?>, <?= htmlspecialchars(json_encode($usr['first_name'].' '.$usr['last_name'])) ?>)">Reset PW</button>
              <?php if (!$me): ?>
              <form method="post" action="user-management.php" style="display:inline"><?= token_field() ?>
                <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$usr['id'] ?>">
                <button class="btn-sm <?= $usr['is_active'] ? 'no' : 'ok' ?>" type="submit"><?= $usr['is_active'] ? 'Deactivate' : 'Activate' ?></button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (!$users): ?>
    <div class="empty"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg><div>No users match your search.</div></div>
    <?php endif; ?>
    <?php if ($uTotalPages > 1): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;flex-wrap:wrap;gap:10px">
      <span style="font-size:13px;color:var(--muted)">Showing <?= (($uPage-1)*$uPer)+1 ?>–<?= min($uPage*$uPer, $totalUsers) ?> of <?= $totalUsers ?> users</span>
      <div style="display:flex;gap:8px">
        <?php if ($uPage > 1): ?><a class="btn-sm" href="?q=<?= urlencode($q) ?>&up=<?= $uPage-1 ?>">← Prev</a><?php endif; ?>
        <span style="padding:6px 10px;border:1px solid var(--line);border-radius:8px;font-size:12px;font-weight:700;background:#fff">Page <?= $uPage ?> / <?= $uTotalPages ?></span>
        <?php if ($uPage < $uTotalPages): ?><a class="btn-sm" href="?q=<?= urlencode($q) ?>&up=<?= $uPage+1 ?>">Next →</a><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Add user -->
  <div class="grid" id="view-add" style="display:none">
    <div class="panel">
      <h2>Add New User</h2>
      <div class="desc">Create a new account. The password is set <b>automatically</b> to the default — <b><?= htmlspecialchars($defaultPass) ?></b> — so there's nothing to type.</div>
      <form method="post" action="user-management.php"><?= token_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="row2">
          <div class="field"><label for="fn">First name <span class="req">*</span></label><input id="fn" name="first_name" required placeholder="e.g. Maria"></div>
          <div class="field"><label for="ln">Last name <span class="req">*</span></label><input id="ln" name="last_name" required placeholder="e.g. Santos"></div>
        </div>
        <div class="field"><label for="em">Email <span class="req">*</span></label><input id="em" name="email" type="email" required placeholder="name@brewco.ph"></div>
        <div class="field"><label for="rl">Role <span class="req">*</span></label>
          <select id="rl" name="role">
            <option value="clerk">Clerk</option><option value="manager">Manager</option>
            <option value="finance">Finance</option><option value="superadmin">Superadmin</option><option value="cashier">Cashier</option><option value="barista">Barista</option><option value="cleaner">Cleaner</option><option value="admin">Admin</option>
          </select>
        </div>
        <button class="btn-primary" type="submit">+ Create User</button>
      </form>
      <p style="font-size:12px;color:var(--muted);margin-top:12px">Every new account uses the default password <b><?= htmlspecialchars($defaultPass) ?></b>. You can change it later with the <b>Reset PW</b> action.</p>
      </form>
    </div>
    <div class="panel">
      <h2>Role guide</h2>
      <div class="desc">What each role can access.</div>
      <ul style="font-size:13.5px;color:var(--muted);line-height:1.9;padding-left:18px">
        <li><b>Clerk</b> — records stock movements, views stocks &amp; logs.</li>
        <li><b>Manager</b> — procurement, quality checks, shipment tracking.</li>
        <li><b>Finance</b> — budget approvals, profitability &amp; DIO.</li>
        <li><b>Superadmin</b> — manages all users &amp; their access.</li>
      </ul>
    </div>
  </div>
</main>

<!-- Edit modal -->
<div class="modal-bg" id="editBg">
  <div class="modal">
    <h3>Edit User</h3>
    <form method="post" action="user-management.php"><?= token_field() ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="eid">
      <div class="row2">
        <div class="field"><label>First name</label><input name="first_name" id="efn" required></div>
        <div class="field"><label>Last name</label><input name="last_name" id="eln" required></div>
      </div>
      <div class="field"><label>Role</label>
        <select name="role" id="erole">
          <option value="clerk">Clerk</option><option value="manager">Manager</option>
          <option value="finance">Finance</option><option value="superadmin">Superadmin</option><option value="cashier">Cashier</option><option value="barista">Barista</option><option value="cleaner">Cleaner</option><option value="admin">Admin</option>
        </select>
      </div>
      <div class="actions"><button type="button" onclick="closeModal('editBg')">Cancel</button><button type="submit" style="background:var(--accent);color:#fff;border-color:var(--accent)">Save</button></div>
    </form>
  </div>
</div>

<!-- Reset password modal -->
<div class="modal-bg" id="resetBg">
  <div class="modal">
    <h3>Reset Password</h3>
    <p id="resetWho"></p>
    <form method="post" action="user-management.php"><?= token_field() ?>
      <input type="hidden" name="action" value="reset">
      <input type="hidden" name="id" id="rid">
      <div class="field"><label>New password</label><input name="password" type="text" id="rnew" value="<?= htmlspecialchars($defaultPass) ?>" required placeholder="min 4 chars"><small style="color:var(--muted);font-size:11px">Pre-filled with the default <?= htmlspecialchars($defaultPass) ?> — change if you want.</small></div>
      <div class="actions"><button type="button" onclick="closeModal('resetBg')">Cancel</button><button type="submit" style="background:var(--accent);color:#fff;border-color:var(--accent)">Reset</button></div>
    </form>
  </div>
</div>

<script>
function showView(name){
  document.getElementById('view-users').style.display = name==='users' ? '' : 'none';
  document.getElementById('view-add').style.display = name==='add' ? '' : 'none';
  document.querySelectorAll('.nav a[data-view]').forEach(a=>a.classList.toggle('active', a.dataset.view===name));
}
document.querySelectorAll('.nav a[data-view]').forEach(a=>a.addEventListener('click',e=>{e.preventDefault();showView(a.dataset.view);}));
// Open the requested view (e.g. ?view=add) if provided
(function(){
  var v = new URLSearchParams(location.search).get('view');
  if (v && ['users','add'].indexOf(v) !== -1) showView(v);
})();
function openEdit(id,fn,ln,role){document.getElementById('eid').value=id;document.getElementById('efn').value=fn;document.getElementById('eln').value=ln;document.getElementById('erole').value=role;document.getElementById('editBg').classList.add('show');}
function openReset(id,name){document.getElementById('rid').value=id;document.getElementById('resetWho').textContent='Set a new password for '+name+'.';document.getElementById('rnew').value='<?= htmlspecialchars($defaultPass) ?>';document.getElementById('resetBg').classList.add('show');}
function closeModal(id){document.getElementById(id).classList.remove('show');}
</script>
<script src="input-guard.js"></script>
<script src="datatable.js"></script>
<script src="alerts.js"></script>
<script src="logout-confirm.js"></script>
<script>window.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('.sidebar nav').forEach(function(n){n.scrollTop=0;});});</script>
<?= notification_script() ?>
<script src="ux-improvements.js"></script>

<script src="responsive-ui.js" defer></script>
<script src="dashboard-navigation.js" defer></script>
<script src="final-operations.js" defer></script>
</body>
</html>
