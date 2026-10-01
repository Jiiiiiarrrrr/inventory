<?php
// ===== ACCOUNT API =====
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/require_login.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        // Staff account list/archive is Admin-only. Viewing a single account (your own
        // profile page) just requires being logged in. "me" is how the frontend restores
        // login state on page refresh (the session cookie survives; JS state doesn't).
        if ($action === 'list') { requireRole(['Admin']); listAccounts(); }
        elseif ($action === 'archived') { requireRole(['Admin']); listArchivedAccounts(); }
        elseif ($action === 'me') { requireLogin(); getCurrentAccount(); }
        else { requireLogin(); getAccount(); }
        break;
    case 'POST':
        // login and registerAccount (public sign-up) are the only unauthenticated actions.
        if ($action === 'login') { loginAccount(); }
        elseif ($action === 'admin_create') { requireRole(['Admin']); adminCreateAccount(); }
        elseif ($action === 'set_pin') { requireRole(['Admin']); setAdminPin(); }
        elseif ($action === 'verify_pin') { requireLogin(); verifyAdminPin(); }
        else { registerAccount(); }
        break;
    case 'PUT':
        if ($action === 'restore_staff') { requireRole(['Admin']); restoreStaffAccount(); }
        else { requireLogin(); updateAccount(); }
        break;
    case 'DELETE':
        if ($action === 'archive') { requireRole(['Admin']); archiveAccount(); }
        else { requireRole(['Admin']); deleteAccount(); }
        break;
    default:
        echo json_encode(['error' => 'Method not allowed']);
}

// ===== LOGIN =====
function loginAccount() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $conn = getDB();
    $data = json_decode(file_get_contents('php://input'), true);

    $email    = $data['email']    ?? '';
    $password = $data['password'] ?? '';

    if (!$email || !$password) {
        echo json_encode(['error' => 'Email and password are required.']);
        return;
    }

    $stmt = $conn->prepare('SELECT id, full_name, email, password, role FROM pos_users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();

    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode(['error' => 'Incorrect email or password.']);
        return;
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

    // This endpoint is for POS staff sign-in (same as auth.php) — Member accounts
    // (public sign-ups) authenticate through a separate customer flow, not here.
    if ($user['role'] !== 'Cashier' && $user['role'] !== 'Admin') {
        echo json_encode(['error' => 'Only Cashier and Admin accounts can sign in here.']);
        return;
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role']    = $user['role'];

    echo json_encode(['success' => true, 'data' => $user]);
    $conn->close();
}

// ===== REGISTER (public sign-up) =====
function registerAccount() {
    $conn = getDB();
    $conn->query("ALTER TABLE pos_users ADD COLUMN IF NOT EXISTS source VARCHAR(20) NOT NULL DEFAULT 'signup'");
    $conn->query("UPDATE pos_users SET role = 'Member' WHERE source = 'signup' AND role = 'Staff'");
    $data = json_decode(file_get_contents('php://input'), true);

    $full_name = trim($data['full_name'] ?? '');
    $email     = trim($data['email']     ?? '');
    $password  = $data['password']  ?? '';
    $role      = 'Member';

    if (!$full_name || !$email || !$password) {
        echo json_encode(['error' => 'All fields are required.']);
        return;
    }
    if (strlen($password) < 6) {
        echo json_encode(['error' => 'Password must be at least 6 characters.']);
        return;
    }

    $check = $conn->prepare('SELECT id FROM pos_users WHERE email = ?');
    $check->bind_param('s', $email);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        echo json_encode(['error' => 'An account with this email already exists.']);
        return;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $source = 'signup';
    $stmt   = $conn->prepare('INSERT INTO pos_users (full_name, email, password, role, source) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('sssss', $full_name, $email, $hashed, $role, $source);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Account created successfully.']);
    } else {
        echo json_encode(['error' => 'Failed to create account.']);
    }
    $conn->close();
}

// ===== ADMIN CREATE STAFF =====
function adminCreateAccount() {
    $conn = getDB();
    $conn->query("ALTER TABLE pos_users ADD COLUMN IF NOT EXISTS source VARCHAR(20) NOT NULL DEFAULT 'signup'");
    $data = json_decode(file_get_contents('php://input'), true);

    $full_name = trim($data['full_name'] ?? '');
    $email     = trim($data['email']     ?? '');
    $password  = $data['password']  ?? '';
    $role      = $data['role']      ?? 'Cashier';

    if (!$full_name || !$email || !$password) {
        echo json_encode(['error' => 'All fields are required.']);
        return;
    }
    if (strlen($password) < 6) {
        echo json_encode(['error' => 'Password must be at least 6 characters.']);
        return;
    }

    $check = $conn->prepare('SELECT id, source FROM pos_users WHERE email = ?');
    $check->bind_param('s', $email);
    $check->execute();
    $checkResult = $check->get_result();
    $existing    = $checkResult->fetch_assoc();

    if ($existing) {
        if ($existing['source'] === 'archived') {
            $hashed      = password_hash($password, PASSWORD_DEFAULT);
            $source      = 'admin_created';
            $existingId  = (int)$existing['id'];
            $stmt        = $conn->prepare('UPDATE pos_users SET full_name=?, password=?, role=?, source=? WHERE id=?');
            $stmt->bind_param('ssssi', $full_name, $hashed, $role, $source, $existingId);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Staff account reactivated.']);
            } else {
                echo json_encode(['error' => 'Failed to reactivate account.']);
            }
        } else {
            echo json_encode(['error' => 'An active account with this email already exists.']);
        }
        $conn->close();
        return;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $source = 'admin_created';
    $stmt   = $conn->prepare('INSERT INTO pos_users (full_name, email, password, role, source) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('sssss', $full_name, $email, $hashed, $role, $source);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Staff account created.']);
    } else {
        echo json_encode(['error' => 'Failed to create account.']);
    }
    $conn->close();
}

// Returns the currently logged-in user (from the session, never a client-supplied id).
// Used by the frontend on page load to restore login state after a refresh.
function getCurrentAccount() {
    $conn = getDB();
    $id   = $_SESSION['user_id'];

    $stmt = $conn->prepare('SELECT id, full_name, email, role FROM pos_users WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();

    if ($user) {
        echo json_encode(['success' => true, 'data' => $user]);
    } else {
        echo json_encode(['error' => 'User not found.']);
    }
    $conn->close();
}

function getAccount() {
    $conn = getDB();
    $id   = $_GET['id'] ?? 1;

    $stmt = $conn->prepare('SELECT id, full_name, email, role FROM pos_users WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();

    if ($user) {
        echo json_encode(['success' => true, 'data' => $user]);
    } else {
        echo json_encode(['error' => 'User not found.']);
    }
    $conn->close();
}

function listAccounts() {
    $conn = getDB();
    $conn->query("ALTER TABLE pos_users ADD COLUMN IF NOT EXISTS source VARCHAR(20) NOT NULL DEFAULT 'signup'");
    $stmt = $conn->prepare("SELECT id, full_name, email, role, created_at FROM pos_users WHERE source = 'admin_created' ORDER BY created_at ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    $users  = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'data' => $users]);
    $conn->close();
}

function listArchivedAccounts() {
    $conn = getDB();
    $conn->query("ALTER TABLE pos_users ADD COLUMN IF NOT EXISTS source VARCHAR(20) NOT NULL DEFAULT 'signup'");
    $stmt = $conn->prepare("SELECT id, full_name, email, role, created_at FROM pos_users WHERE source = 'archived' ORDER BY created_at DESC");
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'data' => $users]);
    $conn->close();
}

function restoreStaffAccount() {
    $conn   = getDB();
    $id     = intval($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'User ID required.']); return; }
    $stmt = $conn->prepare("UPDATE pos_users SET source = 'admin_created' WHERE id = ? AND source = 'archived'");
    $stmt->bind_param('i', $id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to restore staff or account not found.']);
    }
    $conn->close();
}

function updateAccount() {
    $conn = getDB();
    $data = json_decode(file_get_contents('php://input'), true);
    $id   = $_GET['id'] ?? 0;

    if (!$id) {
        echo json_encode(['error' => 'User ID is required.']);
        return;
    }

    $full_name = trim($data['full_name'] ?? '');
    $email     = trim($data['email']     ?? '');
    $password  = $data['password']  ?? '';
    $role      = $data['role']      ?? null;

    if (!$full_name || !$email) {
        echo json_encode(['error' => 'Name and email are required.']);
        return;
    }

    if ($password && strlen($password) < 6) {
        echo json_encode(['error' => 'Password must be at least 6 characters.']);
        return;
    }

    if ($password && $role) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt   = $conn->prepare('UPDATE pos_users SET full_name=?, email=?, password=?, role=? WHERE id=?');
        $stmt->bind_param('ssssi', $full_name, $email, $hashed, $role, $id);
    } elseif ($password) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt   = $conn->prepare('UPDATE pos_users SET full_name=?, email=?, password=? WHERE id=?');
        $stmt->bind_param('sssi', $full_name, $email, $hashed, $id);
    } elseif ($role) {
        $stmt = $conn->prepare('UPDATE pos_users SET full_name=?, email=?, role=? WHERE id=?');
        $stmt->bind_param('sssi', $full_name, $email, $role, $id);
    } else {
        $stmt = $conn->prepare('UPDATE pos_users SET full_name=?, email=? WHERE id=?');
        $stmt->bind_param('ssi', $full_name, $email, $id);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Account updated.']);
    } else {
        echo json_encode(['error' => 'Failed to update account.']);
    }
    $conn->close();
}

function archiveAccount() {
    $conn = getDB();
    $conn->query("ALTER TABLE pos_users ADD COLUMN IF NOT EXISTS source VARCHAR(20) NOT NULL DEFAULT 'signup'");
    $id   = $_GET['id'] ?? 0;

    if (!$id) {
        echo json_encode(['error' => 'User ID is required.']);
        return;
    }

    $stmt = $conn->prepare("UPDATE pos_users SET source = 'archived' WHERE id = ?");
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Account archived.']);
    } else {
        echo json_encode(['error' => 'Failed to archive account.']);
    }
    $conn->close();
}

function deleteAccount() {
    $conn = getDB();
    $id   = $_GET['id'] ?? 0;

    if (!$id) {
        echo json_encode(['error' => 'User ID is required.']);
        return;
    }

    $stmt = $conn->prepare('DELETE FROM pos_users WHERE id = ?');
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Account deleted.']);
    } else {
        echo json_encode(['error' => 'Failed to delete account.']);
    }
    $conn->close();
}

function ensurePinColumn($conn) {
    $conn->query("ALTER TABLE pos_users ADD COLUMN IF NOT EXISTS admin_pin VARCHAR(255) DEFAULT NULL");
}

function setAdminPin() {
    $conn = getDB();
    ensurePinColumn($conn);
    $data = json_decode(file_get_contents('php://input'), true);
    $id   = intval($data['id']  ?? 0);
    $pin  = trim($data['pin']   ?? '');

    if (!$id) { echo json_encode(['error' => 'User ID required.']); return; }
    if (!preg_match('/^\d{4}$/', $pin)) {
        echo json_encode(['error' => 'PIN must be exactly 4 digits.']);
        return;
    }

    $check = $conn->prepare("SELECT role FROM pos_users WHERE id = ?");
    $check->bind_param('i', $id);
    $check->execute();
    $row = $check->get_result()->fetch_assoc();
    if (!$row || $row['role'] !== 'Admin') {
        echo json_encode(['error' => 'Only Admin accounts can set a PIN.']);
        return;
    }

    $hashed = password_hash($pin, PASSWORD_DEFAULT);
    $stmt   = $conn->prepare("UPDATE pos_users SET admin_pin = ? WHERE id = ?");
    $stmt->bind_param('si', $hashed, $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'PIN set successfully.']);
    } else {
        echo json_encode(['error' => 'Failed to set PIN.']);
    }
    $conn->close();
}

function verifyAdminPin() {
    $conn = getDB();
    ensurePinColumn($conn);
    $data = json_decode(file_get_contents('php://input'), true);
    $id   = intval($data['id']  ?? 0);
    $pin  = trim($data['pin']   ?? '');

    if (!$id || !$pin) {
        echo json_encode(['error' => 'User ID and PIN required.']);
        return;
    }

    $stmt = $conn->prepare("SELECT admin_pin FROM pos_users WHERE id = ? AND role = 'Admin'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        echo json_encode(['error' => 'Admin account not found.']);
        return;
    }
    if (empty($row['admin_pin'])) {
        echo json_encode(['error' => 'No PIN set. Please set your Admin PIN in Account settings first.']);
        return;
    }
    if (!password_verify($pin, $row['admin_pin'])) {
        echo json_encode(['error' => 'Incorrect PIN.']);
        return;
    }

    echo json_encode(['success' => true]);
    $conn->close();
}
?>