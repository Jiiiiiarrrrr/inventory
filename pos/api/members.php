<?php
error_reporting(0); ini_set('display_errors', 0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: GET, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/require_login.php';

function ensureSourceColumn($conn) {
    $conn->query("ALTER TABLE pos_users ADD COLUMN IF NOT EXISTS source VARCHAR(20) NOT NULL DEFAULT 'signup'");
    $conn->query("UPDATE pos_users SET source = 'signup' WHERE source = '' OR source IS NULL");
}

// Member management (viewing/archiving public sign-ups) is Admin-only.
requireRole(['Admin']);

$method = $_SERVER['REQUEST_METHOD'];
$conn = getDB(); ensureSourceColumn($conn);

switch ($method) {
    case 'GET':    listMembers($conn);  break;
    case 'DELETE': deleteMember($conn); break;
    default: echo json_encode(['error' => 'Method not allowed']);
}

function listMembers($conn) {
    $stmt = $conn->prepare("SELECT id, full_name, email, role, created_at FROM pos_users WHERE source = 'signup' ORDER BY created_at DESC");
    $stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'data' => $rows]);
    $conn->close();
}

function deleteMember($conn) {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'Member ID required.']); return; }
    $check = $conn->prepare("SELECT id FROM pos_users WHERE id = ? AND source = 'signup'");
    $check->bind_param('i', $id); $check->execute(); $check->store_result();
    if ($check->num_rows === 0) { echo json_encode(['error' => 'Member not found.']); return; }
    $stmt = $conn->prepare("UPDATE pos_users SET source = 'archived' WHERE id = ? AND source = 'signup'");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) echo json_encode(['success' => true]); else echo json_encode(['error' => 'Failed to archive member.']);
    $conn->close();
}
?>