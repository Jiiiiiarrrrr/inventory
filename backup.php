<?php
/**
 * backup.php — Database Backup & Restore (superadmin only)
 *
 * - "Backup"  : dumps the whole brewco_inventory database to a downloadable
 *               .sql file you can keep as a safety copy.
 * - "Restore" : lets you upload a .sql backup to bring the database back to
 *               a previous state.
 *
 * Best practice: take a backup BEFORE major changes and on a schedule.
 */
require __DIR__ . '/db.php';

if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'superadmin') {
    header('Location: login.php'); exit;
}
$u = $_SESSION['user'];
$initials = strtoupper(substr($u['firstName'],0,1).substr($u['lastName'],0,1));
$flash = $_GET['msg'] ?? ''; $flashBad = isset($_GET['bad']);

// ---- BACKUP: dump DB to a downloadable .sql file ----
if (isset($_GET['do']) && $_GET['do'] === 'backup' && db_ok()) {
    $pdo = db();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    $sql = "-- Brew & Co. Inventory Database Backup\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Database : " . DB_NAME . "\n\n";
    $sql .= "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
    $sql .= "USE `" . DB_NAME . "`;\n\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

    foreach ($tables as $t) {
        // CREATE
        $sql .= "DROP TABLE IF EXISTS `$t`;\n";
        $row = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM);
        $sql .= $row[1] . ";\n\n";
        // DATA
        $rows = $pdo->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $cols = implode(',', array_map(fn($c) => "`$c`", array_keys($r)));
            $vals = implode(',', array_map(function($v){
                if ($v === null) return 'NULL';
                return "'" . addslashes($v) . "'";
            }, array_values($r)));
            $sql .= "INSERT INTO `$t` ($cols) VALUES ($vals);\n";
        }
        $sql .= "\n";
    }
    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="brewco-backup-'.date('Y-m-d-His').'.sql"');
    echo $sql; exit;
}

// ---- RESTORE: upload a .sql backup and run it ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['backup_file'])) {
    if (!verify_token($_POST['token'] ?? '')) { header('Location: backup.php?msg='.urlencode('Invalid form token.').'&bad=1'); exit; }
    $file = $_FILES['backup_file'];
    if ($file['error'] === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'])) {
        $sql = file_get_contents($file['tmp_name']);
        // Validate it's a backup for THIS database
        if (stripos($sql, 'USE `' . DB_NAME . '`') === false) {
            header('Location: backup.php?msg='.urlencode('That file does not look like a valid backup for this database.').'&bad=1'); exit;
        }
        $ok = true; $err = '';
        try {
            $pdo = db_server();            // connect without needing the DB to exist
            if (!$pdo) throw new Exception('Cannot connect to the database server.');
            // Split into statements and run them
            foreach (explode(";\n", $sql) as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '' || $stmt === ';') continue;
                $pdo->exec($stmt);
            }
            if ($u) audit($u, 'db.restore', 'Restored database from backup');
        } catch (Throwable $e) {
            $ok = false; $err = $e->getMessage();
        }
        header('Location: backup.php?msg='.urlencode($ok ? 'Database restored successfully.' : 'Restore failed: '.$err).($ok?'':'&bad=1')); exit;
    }
    header('Location: backup.php?msg='.urlencode('No file uploaded or upload failed.').'&bad=1'); exit;
}

// Basic stats to show
$stats = ['tables'=>0,'users'=>0,'items'=>0,'movements'=>0];
if (db_ok()) {
    $stats['tables'] = count(db()->query('SHOW TABLES')->fetchAll());
    foreach (['users','items','stock_movements'] as $t) {
        $stats[$t] = (int)db_one("SELECT COUNT(*) n FROM $t")['n'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="favicon.ico?v=2">
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Brew &amp; Co. — Backup &amp; Restore</title>
<style>
  :root{--brown-900:#faf6f0;--brown-700:#7a5040;--brown-500:#a9714a;--accent:#a9714a;--accent-dark:#8f5c39;
    --cream:#faf6f0;--cream-2:#f5ede3;--card:#fff;--ink:#3b2313;--muted:#7a6055;--line:#e8ddd0;
    --ok:#256b4d;--warn:#96690a;--danger:#a23232;--radius:16px;--shadow:0 8px 24px rgba(74,47,34,.08);}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--cream);color:var(--ink);display:flex;min-height:100vh}
  a{text-decoration:none;color:inherit}
  svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
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
  .topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}
  .topbar h1{font-size:26px;font-weight:800}.topbar .sub{color:var(--muted);font-size:13px;margin-top:2px}
  .who{display:flex;align-items:center;gap:11px}.avatar{width:44px;height:44px;border-radius:50%;background:var(--brown-500);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700}
  .who .n{font-weight:700;font-size:14px}.who .r{font-size:12px;color:var(--muted)}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
  .panel{background:var(--card);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow)}
  .panel h2{font-size:18px;font-weight:800;margin-bottom:4px}.panel .desc{color:var(--muted);font-size:13px;margin-bottom:18px}
  .stats{display:flex;gap:14px;margin-bottom:18px;flex-wrap:wrap}
  .mini{background:var(--cream-2);border:1px solid var(--line);border-radius:12px;padding:12px 18px;text-align:center}
  .mini .v{font-size:24px;font-weight:800}.mini .t{font-size:11.5px;color:var(--muted);text-transform:uppercase;letter-spacing:.03em}
  .btn{display:inline-flex;align-items:center;gap:10px;border:none;border-radius:10px;padding:14px 20px;font-size:15px;font-weight:700;cursor:pointer;text-decoration:none}
  .btn.primary{background:var(--accent);color:#fff}.btn.primary:hover{background:var(--accent-dark)}
  .btn.warn{background:var(--danger);color:#fff}.btn.warn:hover{background:#8a2a2a}
  .alert{background:#e2f0e8;color:var(--ok);border-radius:10px;padding:12px 14px;font-size:13px;margin-bottom:18px}
  .alert.bad{background:#f6e0e0;color:var(--danger)}
  .hint{font-size:12.5px;color:var(--muted);margin-top:12px;line-height:1.7}
  @media(max-width:820px){.grid{grid-template-columns:1fr}.sidebar{position:fixed;left:0;top:0;transform:translateX(-100%)}.main{margin-left:0;padding:18px}}
.icon-btn{position:relative;width:44px;height:44px;border-radius:50%;background:#fff;box-shadow:0 8px 24px rgba(74,47,34,.08);display:flex;align-items:center;justify-content:center;cursor:pointer;border:none;color:#33261d}
  .user-area{display:flex;align-items:center;gap:18px}
  .pos{position:relative}
</style>

<link rel="stylesheet" href="responsive-ui.css">
<link rel="stylesheet" href="dashboard-navigation.css">
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
    <a href="audit-trail.php"><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg> Audit Trail</a>
    <a class="active" aria-current="page"><svg viewBox="0 0 24 24"><path d="M21 12v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-3M12 3v12M12 15l-4-4M12 15l4-4"/></svg> Backup &amp; Restore</a>

    <div class="nav-divider"></div>
    <div class="nav-section-label">Account</div>
    <a href="logout.php"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg> Logout</a>
  </nav></aside>

<main class="main">
  <div class="topbar">
    <div><h1>Backup &amp; Restore</h1><div class="sub">Safeguard your inventory data</div></div>
    <div class="user-area">
      <?= notification_bell($u["role"]) ?>
      <div class="who"><div class="avatar"><?= $initials ?></div>
    </div><div><div class="n"><?= htmlspecialchars($u['firstName'].' '.$u['lastName']) ?></div><div class="r">Superadmin</div></div></div>
  </div>

  <?php if ($flash): ?><div class="alert <?= $flashBad ? 'bad' : '' ?>"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

  <div class="panel" style="margin-bottom:18px">
    <div class="stats">
      <div class="mini"><div class="v"><?= $stats['tables'] ?></div><div class="t">Tables</div></div>
      <div class="mini"><div class="v"><?= $stats['users'] ?></div><div class="t">Users</div></div>
      <div class="mini"><div class="v"><?= $stats['items'] ?></div><div class="t">Items</div></div>
      <div class="mini"><div class="v"><?= $stats['movements'] ?></div><div class="t">Movements</div></div>
    </div>
  </div>

  <div class="grid">
    <div class="panel">
      <h2>⬇️ Download Backup</h2>
      <div class="desc">Create a downloadable .sql copy of the entire database.</div>
      <a class="btn primary" href="?do=backup"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg> Backup Now</a>
      <p class="hint">Save the downloaded file somewhere safe (external drive or cloud). Take a backup before making major changes.</p>
    </div>

    <div class="panel">
      <h2>⬆️ Restore from Backup</h2>
      <div class="desc">Upload a previous .sql backup to restore it.</div>
      <form method="post" enctype="multipart/form-data" action="backup.php" onsubmit="return sweetConfirmSubmit(event,'Restoring will OVERWRITE the current database with the backup. Continue?')"><?= token_field() ?>
        <input type="file" name="backup_file" accept=".sql" required style="width:100%;padding:10px;border:1px solid var(--line);border-radius:10px;background:var(--cream-2);margin-bottom:14px">
        <button class="btn warn" type="submit"><svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-9-9M21 3v6h-6"/></svg> Restore</button>
      </form>
      <p class="hint">⚠ This replaces current data with the backup. Only upload .sql backups created by this page.</p>
    </div>
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
</body>
</html>
