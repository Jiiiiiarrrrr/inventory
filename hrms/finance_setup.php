<?php
// =====================================================================
//  HRMS FINANCE SETUP  —  run ONCE, then DELETE this file.
//
//  Place this file inside your hrms/ folder and open it in the browser:
//      http://localhost/INVENTORY/hrms/finance_setup.php
//
//  What it does:
//   1. Adds the 'finance' role to hrms_users.role
//   2. Adds payroll approval columns (finance_comment, finance_acted_by,
//      finance_acted_at, submitted_at) to hrms_payroll
//   3. Creates the hrms_budget table (monthly payroll budget)
//   4. Creates the Finance login account (if it does not exist yet):
//        username: finance
//        email:    lavish@brewco.ph
//        password: finance123   (change it after first login)
// =====================================================================
require_once __DIR__ . '/api/common.php';
$pdo = conn();

$lines = [];

// 1) role enum
try {
    $col = $pdo->query("SHOW COLUMNS FROM hrms_users LIKE 'role'")->fetch();
    if ($col && stripos((string)($col['Type'] ?? ''), 'finance') !== false) {
        $lines[] = ['ok', "hrms_users.role already includes 'finance'"];
    } else {
        $pdo->exec("ALTER TABLE hrms_users MODIFY role ENUM('superadmin','admin','staff','finance') NOT NULL DEFAULT 'staff'");
        $lines[] = ['ok', "hrms_users.role updated — now includes 'finance'"];
    }
} catch (Exception $e) {
    $lines[] = ['err', 'role enum: ' . $e->getMessage()];
}

// 2) payroll approval columns
$cols = [
    'finance_comment'  => 'TEXT NULL',
    'finance_acted_by' => 'VARCHAR(150) NULL',
    'finance_acted_at' => 'TIMESTAMP NULL DEFAULT NULL',
    'submitted_at'     => 'TIMESTAMP NULL DEFAULT NULL'
];
foreach ($cols as $name => $def) {
    try {
        if (!$pdo->query("SHOW COLUMNS FROM hrms_payroll LIKE '" . $name . "'")->fetch()) {
            $pdo->exec("ALTER TABLE hrms_payroll ADD $name $def");
            $lines[] = ['ok', "hrms_payroll.$name added"];
        } else {
            $lines[] = ['ok', "hrms_payroll.$name already exists"];
        }
    } catch (Exception $e) {
        $lines[] = ['err', "hrms_payroll.$name: " . $e->getMessage()];
    }
}

// 3) budget table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS hrms_budget (
        id INT AUTO_INCREMENT PRIMARY KEY,
        month CHAR(7) NOT NULL UNIQUE,
        total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        note VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $lines[] = ['ok', 'hrms_budget table ready'];
} catch (Exception $e) {
    $lines[] = ['err', 'hrms_budget: ' . $e->getMessage()];
}

// 4) finance login account
$finEmail = 'lavish@brewco.ph';
$finUser = 'finance';
$finPass = 'finance123';
try {
    $st = $pdo->prepare("SELECT id FROM hrms_users WHERE username = ? OR email = ? LIMIT 1");
    $st->execute([$finUser, $finEmail]);
    if ($st->fetch()) {
        $lines[] = ['ok', "Finance account already exists (username '$finUser' or email $finEmail) — left untouched"];
    } else {
        $st = $pdo->prepare("INSERT INTO hrms_users (username, full_name, email, password, role, must_change_password) VALUES (?, ?, ?, ?, 'finance', 0)");
        $st->execute([$finUser, 'Lavish Herzicm Ancero', $finEmail, password_hash($finPass, PASSWORD_DEFAULT)]);
        $lines[] = ['ok', "Finance account created — username: $finUser · email: $finEmail · password: $finPass (change it after first login)"];
    }
} catch (Exception $e) {
    $lines[] = ['err', 'finance account: ' . $e->getMessage()];
}

// Existing hrms_users for reference
$existing = [];
try {
    $existing = $pdo->query("SELECT username, full_name, email, role FROM hrms_users ORDER BY id")->fetchAll();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html>
<head>
<link rel="icon" type="image/png" href="../favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="../favicon.ico?v=2">
<meta charset="UTF-8">
<title>HRMS Finance Setup</title>
<style>
body{font-family:'Segoe UI',system-ui,sans-serif;background:#faf6f0;color:#3b2313;padding:30px;max-width:820px;margin:0 auto}
h1{font-size:22px}
.ok{background:#e2ecdf;color:#4f7a4a;border-radius:8px;padding:10px 14px;margin-bottom:8px;font-size:14px;font-weight:600}
.err{background:#fce4dc;color:#a8492f;border-radius:8px;padding:10px 14px;margin-bottom:8px;font-size:14px;font-weight:600}
table{border-collapse:collapse;margin-top:16px;width:100%}
td,th{border:1px solid #e8ddd0;padding:8px 10px;font-size:13px;text-align:left}
th{background:#f5ede3}
.warn-box{background:#fff4e0;border:1px solid #c99a5b;border-radius:10px;padding:14px;margin-top:20px;font-size:14px}
code{background:#eee6da;padding:2px 6px;border-radius:4px}
</style>

<link rel="stylesheet" href="../responsive-ui.css">
<link rel="stylesheet" href="../dashboard-navigation.css">
<link rel="stylesheet" href="../hrms-responsive.css">
</head>
<body>
<h1>HRMS Finance Setup</h1>
<?php foreach ($lines as $l): ?>
  <div class="<?= $l[0] ?>"><?= htmlspecialchars($l[1]) ?></div>
<?php endforeach; ?>

<?php if ($existing): ?>
<h2 style="margin-top:24px;font-size:16px">Current hrms_users</h2>
<table>
<tr><th>Username</th><th>Name</th><th>Email</th><th>Role</th></tr>
<?php foreach ($existing as $u): ?>
<tr><td><?= htmlspecialchars($u['username'] ?? '') ?></td><td><?= htmlspecialchars($u['full_name'] ?? '') ?></td><td><?= htmlspecialchars($u['email'] ?? '') ?></td><td><?= htmlspecialchars($u['role'] ?? '') ?></td></tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<div class="warn-box">
  <b>Done! Now:</b><br>
  1. <b>Delete this file</b> (<code>hrms/finance_setup.php</code>) — it is only needed once.<br>
  2. Copy the files from the <code>finance-feature/</code> folder into your project (see INSTALL.txt).<br>
  3. Log in at <code>login.php</code> with <b>finance / finance123</b> to try the new Finance module, then change the password (Profile → Change Password).
</div>

<script src="../responsive-ui.js" defer></script>
<script src="../dashboard-navigation.js" defer></script>
<script src="../hrms-responsive.js" defer></script>
</body>
</html>
