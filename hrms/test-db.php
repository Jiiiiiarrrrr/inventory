<?php
require_once __DIR__ . '/api/common.php';
session_start();
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
<link rel="icon" type="image/png" href="../favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="../favicon.ico?v=2"><title>HRMS Database Test</title>
<style>
body{font-family:monospace;padding:20px;background:#1a1a1a;color:#0f0}
h2{color:#fff;margin-top:30px}
.ok{color:#0f0}.err{color:#f00}.warn{color:#ff0}
table{border-collapse:collapse;margin:10px 0}
td,th{border:1px solid #333;padding:8px;text-align:left}
th{background:#333;color:#fff}
pre{background:#000;padding:10px;border:1px solid #333;overflow-x:auto}
</style>

<link rel="stylesheet" href="../responsive-ui.css">
<link rel="stylesheet" href="../dashboard-navigation.css">
</head>
<body>
<h1>HRMS Database Diagnostic</h1>

<h2>1. Session Status</h2>
<?php
if (isset($_SESSION['user'])) {
    echo '<div class="ok">✓ Session exists</div><pre>';
    print_r($_SESSION['user']);
    echo '</pre>';
} else {
    echo '<div class="err">✗ No session found. Please login first at <a href="login.php" style="color:#0ff">login.php</a></div>';
}
?>

<h2>2. Database Connection</h2>
<?php
try {
    $pdo = conn();
    echo '<div class="ok">✓ Database connected</div>';
} catch (Exception $e) {
    echo '<div class="err"> Database error: ' . $e->getMessage() . '</div>';
    exit;
}
?>

<h2>3. Table Check</h2>
<table>
<tr><th>Table</th><th>Exists?</th><th>Row Count</th></tr>
<?php
$tables = ['hrms_users','hrms_employees','hrms_applicants','hrms_approval_requests','hrms_schedules','hrms_attendance_records','hrms_payroll','hrms_audit_logs','hrms_notifications','hrms_archived_items'];
foreach ($tables as $t) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        echo "<tr><td>$t</td><td class='ok'>✓</td><td>$count</td></tr>";
    } catch (Exception $e) {
        echo "<tr><td>$t</td><td class='err'>✗</td><td>" . $e->getMessage() . "</td></tr>";
    }
}
?>
</table>

<h2>4. Sample Data Check</h2>
<?php
// Check employees
try {
    $emps = $pdo->query("SELECT id, name, email, role FROM hrms_employees LIMIT 5")->fetchAll();
    if (count($emps) > 0) {
        echo '<div class="ok">✓ Found ' . count($emps) . ' employees</div><table><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th></tr>';
        foreach ($emps as $e) {
            echo "<tr><td>{$e['id']}</td><td>{$e['name']}</td><td>{$e['email']}</td><td>{$e['role']}</td></tr>";
        }
        echo '</table>';
    } else {
        echo '<div class="warn">⚠ hrms_employees table is empty. Run hrms_seed_data.sql</div>';
    }
} catch (Exception $e) {
    echo '<div class="err">✗ Error: ' . $e->getMessage() . '</div>';
}
?>

<h2>5. API Test (employees.php)</h2>
<?php
// Simulate API call
try {
    $testSql = "SELECT id, name, email, role, department, position, status FROM hrms_employees ORDER BY name";
    $rows = $pdo->query($testSql)->fetchAll();
    echo '<div class="ok">✓ Query successful: ' . count($rows) . ' rows</div>';
    echo '<pre>';
    echo json_encode(['success' => true, 'data' => $rows], JSON_PRETTY_PRINT);
    echo '</pre>';
} catch (Exception $e) {
    echo '<div class="err">✗ Query failed: ' . $e->getMessage() . '</div>';
    echo '<pre>' . $testSql . '</pre>';
}
?>

<h2>6. hrms_users (Login Accounts)</h2>
<?php
try {
    $users = $pdo->query("SELECT username, full_name, email, role FROM hrms_users")->fetchAll();
    echo '<table><tr><th>Username</th><th>Name</th><th>Email</th><th>Role</th></tr>';
    foreach ($users as $u) {
        echo "<tr><td>{$u['username']}</td><td>{$u['full_name']}</td><td>{$u['email']}</td><td>{$u['role']}</td></tr>";
    }
    echo '</table>';
} catch (Exception $e) {
    echo '<div class="err">✗ ' . $e->getMessage() . '</div>';
}
?>

<h2>Instructions</h2>
<div style="color:#fff">
<p><strong>If "No session found":</strong> Login at <code style="color:#0ff">localhost/INVENTORY/hrms/login.php</code> with username <code>admin</code> password <code>admin123</code></p>

<p><strong>If tables are empty:</strong> Run <code>hrms_seed_data.sql</code> in phpMyAdmin</p>

<p><strong>If query fails with "Unknown column":</strong> Your table schema is outdated. The seed SQL file now includes ALTER TABLE statements to fix this.</p>
</div>


<script src="../responsive-ui.js" defer></script>
<script src="../dashboard-navigation.js" defer></script>
</body>
</html>
