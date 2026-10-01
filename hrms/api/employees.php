<?php
// ===== EMPLOYEES API =====
// GET  : list all employees (admin / superadmin / finance)
// POST : create employee + staff login account (JSON from staff-create.php)
//        {name, email, role, department, fixed_salary, password, work_hours}
require_once __DIR__ . "/common.php";
require_once __DIR__ . "/security.php";
require_once __DIR__ . "/feature_init.php";
$pdo = conn();
hrms_feature_init($pdo);

$u = require_roles(['admin', 'superadmin', 'finance']);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $pdo->query("SELECT * FROM hrms_employees ORDER BY name ASC")->fetchAll();
    response(['success' => true, 'data' => $rows]);
}

if ($method === 'POST') {
    if (!in_array($u['role'], ['admin', 'superadmin'], true)) {
        response(['success' => false, 'message' => 'Permission denied'], 403);
    }
    $d = getBody();
    $name       = trim($d['name'] ?? '');
    $email      = trim($d['email'] ?? '');
    $role       = trim($d['role'] ?? '') !== '' ? trim($d['role'] ?? '') : 'Staff';
    $department = trim($d['department'] ?? '') !== '' ? trim($d['department'] ?? '') : null;
    $salary     = floatval($d['fixed_salary'] ?? 0);
    $password   = (string)($d['password'] ?? '');
    $hours      = intval($d['work_hours'] ?? 8);

    if ($name === '' || $email === '' || $salary <= 0 || $password === '') {
        response(['success' => false, 'message' => 'Name, email, salary, and password are required'], 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        response(['success' => false, 'message' => 'Invalid email address'], 400);
    }
    $chk = $pdo->prepare("SELECT id FROM hrms_employees WHERE email = ? LIMIT 1");
    $chk->execute([$email]);
    if ($chk->fetch()) response(['success' => false, 'message' => 'An employee with this email already exists'], 409);
    $chk = $pdo->prepare("SELECT id FROM hrms_users WHERE email = ? LIMIT 1");
    $chk->execute([$email]);
    if ($chk->fetch()) response(['success' => false, 'message' => 'A login account with this email already exists'], 409);

    // Fixed 8-hours/day setup: Monday-Friday, 9:00-17:00 by default.
    $days  = 'Mon,Tue,Wed,Thu,Fri';
    $start = '09:00';
    $end   = $hours > 0 ? sprintf('%02d:%02d', (9 + $hours) % 24, 0) : '17:00';

    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO hrms_employees (name, email, role, department, monthly_salary, schedule_days, schedule_start, schedule_end, status, hire_date, account_type) VALUES (?,?,?,?,?,?,?,?, 'active', CURDATE(), 'Staff')")
            ->execute([$name, $email, $role, $department, $salary, $days, $start, $end]);
        $empId = (int)$pdo->lastInsertId();

        // Unique username from the email prefix.
        $base = preg_replace('/[^a-z0-9._-]/', '', strtolower(explode('@', $email)[0])) ?: 'staff';
        $username = $base; $n = 1;
        while (true) {
            $chk = $pdo->prepare("SELECT id FROM hrms_users WHERE username = ? LIMIT 1");
            $chk->execute([$username]);
            if (!$chk->fetch()) break;
            $username = $base . (++$n);
        }
        $pdo->prepare("INSERT INTO hrms_users (username, full_name, email, password, role, employee_id, must_change_password) VALUES (?,?,?,?, 'staff', ?, 0)")
            ->execute([$username, $name, $email, secure_hash($password), $empId]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        response(['success' => false, 'message' => 'Failed to create staff account: ' . $e->getMessage()], 500);
    }

    addLog($name, 'Employee Created', "Staff account created for $name ($role, $department)");
    notify_hrms($pdo, 'New staff account', "$name was added as $role (" . ($department ?? 'no department') . ').', 'superadmin');
    response(['success' => true, 'message' => 'Staff account created.', 'username' => $username, 'employee_id' => $empId]);
}

response(['success' => false, 'message' => 'Method not allowed'], 405);
