<?php
// ===== HRMS NOTIFICATIONS API =====
// Returns notifications addressed to the logged-in user:
//   - staff/applicant : by their email or user id
//   - admin/superadmin/finance : by their role, email, or user id
// POST actions:
//   {action:'mark_read', id}
//   {action:'mark_all_read'}
require_once __DIR__ . "/common.php";
require_once __DIR__ . "/feature_init.php";
session_start();

if (!isset($_SESSION['user'])) {
    response(['success' => false, 'message' => 'Login required'], 401);
}
$pdo = conn();
hrms_feature_init($pdo);
$u = $_SESSION['user'];
$method = $_SERVER['REQUEST_METHOD'];

// Build the WHERE scope for the current user.
$scopeWhere = '';
$scopeParams = [];
$role = (string)($u['role'] ?? '');
$email = (string)($u['email'] ?? '');
$userId = intval($u['id'] ?? 0);

if (in_array($role, ['admin', 'superadmin', 'finance'], true)) {
    $scopeWhere = "(role = ? OR employee_email = ? OR user_id = ?)";
    $scopeParams = [$role, $email, $userId];
} else {
    // staff / applicant: only what is addressed to them personally
    $scopeWhere = "(employee_email = ? OR user_id = ?)";
    $scopeParams = [$email, $userId];
}

if ($method === 'POST') {
    $d = getBody();
    $action = $d['action'] ?? '';

    if ($action === 'mark_read') {
        $id = intval($d['id'] ?? 0);
        if ($id > 0) {
            $st = $pdo->prepare("UPDATE hrms_notifications SET is_read = 1 WHERE id = ? AND " . $scopeWhere);
            $st->execute(array_merge([$id], $scopeParams));
        }
        response(['success' => true]);
    }

    if ($action === 'mark_all_read') {
        $st = $pdo->prepare("UPDATE hrms_notifications SET is_read = 1 WHERE " . $scopeWhere);
        $st->execute($scopeParams);
        response(['success' => true]);
    }

    response(['success' => false, 'message' => 'Unknown action'], 400);
}

if ($method === 'GET') {
    $st = $pdo->prepare("SELECT * FROM hrms_notifications WHERE " . $scopeWhere . " ORDER BY created_at DESC LIMIT 60");
    $st->execute($scopeParams);
    response(['success' => true, 'data' => $st->fetchAll()]);
}

response(['success' => false, 'message' => 'Method not allowed'], 405);
