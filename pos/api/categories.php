<?php
// ===== CATEGORIES API =====
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once 'config.php';
require_once 'require_login.php';

// menu_categories already exists (created by the migration) and is pre-seeded with the
// 5 defaults, so this API no longer needs to create the table or seed it itself.
// is_default is a real column here (added via add_is_default_column.sql) — mirrors the
// old pos_categories.is_default flag and marks the 5 built-in categories as undeletable.

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Plain category listing stays public (customers/Cashier need it to browse the menu).
// Viewing archived categories and every write is category management: Admin only.
if ($method !== 'GET' || $action === 'archived') {
    requireRole(['Admin']);
}

$conn   = getDB();

switch ($method) {
    case 'GET':    if ($action === 'archived') listArchivedCategories($conn); else listCategories($conn); break;
    case 'POST':   addCategory($conn);     break;
    case 'PUT':    restoreCategory($conn); break;
    case 'DELETE': deleteCategory($conn);  break;
    default: echo json_encode(['error' => 'Method not allowed']);
}

function listCategories($conn) {
    $result = $conn->query("SELECT id, name, emoji, sort_order, is_default FROM menu_categories WHERE deleted_at IS NULL ORDER BY sort_order ASC, id ASC");
    $rows   = $result->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$r) { $r['is_default'] = (int)$r['is_default']; }
    echo json_encode(['success' => true, 'data' => $rows]);
    $conn->close();
}

function listArchivedCategories($conn) {
    $result = $conn->query("SELECT id, name, emoji, sort_order, is_default, deleted_at FROM menu_categories WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
    $rows   = $result->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$r) { $r['is_default'] = (int)$r['is_default']; }
    echo json_encode(['success' => true, 'data' => $rows]);
    $conn->close();
}

function addCategory($conn) {
    $data  = json_decode(file_get_contents('php://input'), true);
    $name  = trim($data['name']  ?? '');
    $emoji = trim($data['emoji'] ?? '');
    if (!$emoji) $emoji = '🏷️';

    if (!$name || strlen($name) > 80) {
        echo json_encode(['error' => 'Category name is required (max 80 chars).']);
        return;
    }

    $check = $conn->prepare("SELECT id FROM menu_categories WHERE TRIM(LOWER(name)) = TRIM(LOWER(?)) AND deleted_at IS NOT NULL");
    $check->bind_param('s', $name);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    if ($existing) {
        $stmt = $conn->prepare("UPDATE menu_categories SET emoji = ?, deleted_at = NULL WHERE id = ?");
        $stmt->bind_param('si', $emoji, $existing['id']);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'id' => $existing['id'], 'name' => $name, 'emoji' => $emoji]);
        } else {
            echo json_encode(['error' => 'Failed to reactivate category.']);
        }
        $conn->close();
        return;
    }

    // New (non-default) categories are sorted after existing ones.
    $maxRow = $conn->query("SELECT COALESCE(MAX(sort_order), 0) AS max_sort FROM menu_categories")->fetch_assoc();
    $nextSort = (int)$maxRow['max_sort'] + 1;

    $stmt = $conn->prepare("INSERT INTO menu_categories (name, emoji, sort_order, is_active, is_default) VALUES (?, ?, ?, 1, 0)");
    $stmt->bind_param('ssi', $name, $emoji, $nextSort);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'id' => $stmt->insert_id, 'name' => $name, 'emoji' => $emoji]);
    } else {
        echo json_encode(['error' => 'Category already exists.']);
    }
    $conn->close();
}

function deleteCategory($conn) {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'Category ID required.']); return; }

    $check = $conn->prepare("SELECT id, name, is_default FROM menu_categories WHERE id = ? AND deleted_at IS NULL");
    $check->bind_param('i', $id);
    $check->execute();
    $row = $check->get_result()->fetch_assoc();

    if (!$row) { echo json_encode(['error' => 'Category not found.']); return; }
    if ((int)$row['is_default'] === 1) {
        echo json_encode(['error' => 'Built-in categories cannot be deleted.']);
        return;
    }

    $archive = $conn->prepare("UPDATE menu_items SET deleted_at = NOW() WHERE category_id = ? AND deleted_at IS NULL");
    $archive->bind_param('i', $id);
    $archive->execute();
    $archivedCount = $archive->affected_rows;

    $del = $conn->prepare("UPDATE menu_categories SET deleted_at = NOW() WHERE id = ?");
    $del->bind_param('i', $id);
    $del->execute();

    echo json_encode([
        'success'        => true,
        'archived_items' => $archivedCount,
        'category_name'  => $row['name'],
        'message'        => "Category archived. $archivedCount item(s) moved to archive.",
    ]);
    $conn->close();
}

function restoreCategory($conn) {
    $id          = intval($_GET['id'] ?? 0);
    $data        = json_decode(file_get_contents('php://input'), true);
    $restoreItems = ($data['restore_items'] ?? false) === true;

    if (!$id) { echo json_encode(['error' => 'Category ID required.']); return; }

    $check = $conn->prepare("SELECT id, name FROM menu_categories WHERE id = ? AND deleted_at IS NOT NULL");
    $check->bind_param('i', $id);
    $check->execute();
    $row = $check->get_result()->fetch_assoc();
    if (!$row) { echo json_encode(['error' => 'Archived category not found.']); return; }

    $stmt = $conn->prepare("UPDATE menu_categories SET deleted_at = NULL WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    $restoredItems = 0;
    if ($restoreItems) {
        $restoreStmt = $conn->prepare("UPDATE menu_items SET deleted_at = NULL WHERE category_id = ? AND deleted_at IS NOT NULL");
        $restoreStmt->bind_param('i', $id);
        $restoreStmt->execute();
        $restoredItems = $restoreStmt->affected_rows;
    }

    echo json_encode([
        'success'        => true,
        'category_name'  => $row['name'],
        'restored_items' => $restoredItems,
    ]);
    $conn->close();
}
?>