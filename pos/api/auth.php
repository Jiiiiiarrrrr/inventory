<?php
// ===== AUTH API (staff sign-in for pos.php landing) =====
// POST JSON {username, password} (email also accepted) -> {success, data:{id, full_name, email, role}}
// Mirrors account.php?action=login: same pos_users table, same session keys
// ($_SESSION['user_id'] / $_SESSION['role']) so every other endpoint and the
// staff app shell keep working unchanged. Only Cashier and Admin may sign in
// here; Member (public sign-up) accounts use the customer flow.
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/require_login.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$conn = getDB();
$data = json_decode(file_get_contents('php://input'), true);

$email    = strtolower(trim($data['username'] ?? ($data['email'] ?? '')));
$password = $data['password'] ?? '';

$conn->query("CREATE TABLE IF NOT EXISTS pos_login_attempts (acct VARCHAR(190) NOT NULL PRIMARY KEY, fails INT NOT NULL DEFAULT 0, last_fail DATETIME NULL)");
$lk = $conn->prepare("SELECT fails, last_fail FROM pos_login_attempts WHERE acct=?");
$lk->bind_param('s', $email); $lk->execute(); $lkr = $lk->get_result()->fetch_assoc();
if ($lkr && (int)$lkr['fails'] >= 6 && $lkr['last_fail'] && strtotime($lkr['last_fail']) > time() - 600) {
    echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Please try again in 10 minutes.']);
    exit;
}
if ($email === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

$stmt = $conn->prepare('SELECT id, full_name, email, password, role FROM pos_users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || !password_verify($password, $user['password'])) {
    $fi = $conn->prepare("INSERT INTO pos_login_attempts (acct, fails, last_fail) VALUES (?,1,NOW()) ON DUPLICATE KEY UPDATE fails=fails+1, last_fail=NOW()");
    $fi->bind_param('s', $email); $fi->execute();
    echo json_encode(['success' => false, 'message' => 'Incorrect email or password.']);
    exit;
}

unset($user['password']);
if (empty($user['role'])) $user['role'] = 'Staff';
$conn->query("UPDATE pos_users SET role = 'Member' WHERE source = 'signup' AND role = 'Staff'");
if ($user['role'] === 'Staff') {
    $recheck = $conn->prepare('SELECT role, source FROM pos_users WHERE id = ?');
    $recheck->bind_param('i', $user['id']);
    $recheck->execute();
    $fresh = $recheck->get_result()->fetch_assoc();
    if ($fresh && $fresh['source'] === 'signup') $user['role'] = 'Member';
}

if ($user['role'] !== 'Cashier' && $user['role'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Only Cashier and Admin accounts can sign in here.']);
    exit;
}

session_regenerate_id(true); // new session id on login (defeats session fixation)
$rd2 = $conn->prepare("DELETE FROM pos_login_attempts WHERE acct=?"); $rd2->bind_param('s', $email); $rd2->execute();
$_SESSION['user_id'] = $user['id'];
$_SESSION['role']    = $user['role'];

// name alias kept for the pos.php landing shell; full_name is what app.js reads.
$user['name'] = $user['full_name'];

echo json_encode(['success' => true, 'data' => $user]);
$conn->close();
