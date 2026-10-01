<?php
// ===== PROFILE API =====
// GET  : current user info + linked employee record
// POST : {action:'change_password', current_password, new_password}
require_once __DIR__ . "/common.php";
require_once __DIR__ . "/security.php";
require_once __DIR__ . "/feature_init.php";
$pdo = conn();
hrms_feature_init($pdo);

$u = require_login();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $emp = employee_for_user($pdo, $u);
    response([
        'success' => true,
        'user' => [
            'id'    => $u['id'] ?? null,
            'name'  => $u['name'] ?? $u['full_name'] ?? '',
            'email' => $u['email'] ?? '',
            'role'  => $u['role'] ?? '',
        ],
        'employee' => $emp,
    ]);
}

if ($method === 'POST') {
    $d = getBody();
    if (($d['action'] ?? '') !== 'change_password') {
        response(['success' => false, 'message' => 'Unknown action'], 400);
    }
    $current = (string)($d['current_password'] ?? '');
    $new     = (string)($d['new_password'] ?? '');
    if ($current === '' || $new === '') {
        response(['success' => false, 'message' => 'Current and new password are required'], 400);
    }
    if (strlen($new) < 6) {
        response(['success' => false, 'message' => 'New password must be at least 6 characters'], 400);
    }

    $st = $pdo->prepare("SELECT * FROM hrms_users WHERE id = ? LIMIT 1");
    $st->execute([$u['id'] ?? 0]);
    $row = $st->fetch();
    if (!$row) {
        // Fallback: match by email (older sessions without id).
        $st = $pdo->prepare("SELECT * FROM hrms_users WHERE email = ? LIMIT 1");
        $st->execute([$u['email'] ?? '']);
        $row = $st->fetch();
    }
    if (!$row) response(['success' => false, 'message' => 'Account not found'], 404);
    if (!password_ok($current, $row['password'])) {
        response(['success' => false, 'message' => 'Current password is incorrect'], 400);
    }

    $pdo->prepare("UPDATE hrms_users SET password = ?, must_change_password = 0 WHERE id = ?")
        ->execute([secure_hash($new), $row['id']]);
    addLog($row['full_name'] ?? ($u['name'] ?? ''), 'Password Changed', 'User changed their own password');
    response(['success' => true, 'message' => 'Password updated successfully.']);
}

response(['success' => false, 'message' => 'Method not allowed'], 405);
