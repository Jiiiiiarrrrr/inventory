<?php
// ===== POSITIONS API =====
// GET  (public): list open positions with live slot counts, for the
//                apply.php job board. No login required.
// POST (admin/hr): add a position or edit slots_total / is_open.
define('HRMS_NO_AUTH_GATE', true); // GET must be reachable by anonymous applicants
require_once __DIR__ . "/common.php";
require_once __DIR__ . "/security.php";
require_once __DIR__ . "/feature_init.php";
if (session_status() === PHP_SESSION_NONE) session_start();
$pdo = conn();
hrms_feature_init($pdo);

// Roles a position is allowed to provision when its applicant is hired.
// Matches api/applicants.php's 'hire' action, which only ever inserts
// hrms_users rows with role='staff' — admin/superadmin are reserved for
// accounts HR creates directly and are never reachable by applying here.
const POSITION_ALLOWED_ROLES = ['staff'];

// An applicant "occupies" a slot for their position from the moment they
// apply until they're Rejected — Pending/Accepted/any interview stage and
// Hired all still count, so HR doesn't over-book interviews for a role
// that's effectively already full.
const POSITION_HOLDING_STATUSES_SQL = "status <> 'Rejected'";

function positions_with_counts($pdo) {
    $sql = "SELECT p.id, p.title, p.description, p.slots_total, p.is_open, p.linked_role, p.created_at,
                   (SELECT COUNT(*) FROM hrms_applicants a
                     WHERE a.position = p.title AND " . POSITION_HOLDING_STATUSES_SQL . ") AS slots_filled
            FROM hrms_positions p
            ORDER BY p.title ASC";
    $rows = $pdo->query($sql)->fetchAll();
    foreach ($rows as &$r) {
        $r['slots_total'] = (int)$r['slots_total'];
        $r['slots_filled'] = (int)$r['slots_filled'];
        $r['slots_remaining'] = max(0, $r['slots_total'] - $r['slots_filled']);
        $r['is_open'] = (int)$r['is_open'];
        // is_open is HR's own on/off switch for the listing; is_available
        // additionally goes false once slots_filled reaches slots_total,
        // even if HR left is_open on — that's the "reaches threshold ->
        // unavailable" rule. apply.php and applicants_upload.php both key
        // off is_available, not is_open, to decide if applying is allowed.
        $r['is_available'] = ($r['is_open'] && $r['slots_remaining'] > 0) ? 1 : 0;
    }
    return $rows;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    response(['success' => true, 'data' => positions_with_counts($pdo)]);
}

if ($method === 'POST') {
    // Everything below changes hiring configuration, so it needs a real
    // HR/admin session (this is the one part of the file the auth gate
    // would normally protect, so we enforce it ourselves since the gate
    // is disabled above for the public GET).
    if (empty($_SESSION['user'])) response(['success' => false, 'message' => 'Login required'], 401);
    $u = $_SESSION['user'];
    if (!in_array($u['role'] ?? '', ['admin', 'superadmin', 'hr'], true)) {
        response(['success' => false, 'message' => 'Permission denied'], 403);
    }

    $d = getBody();
    $action = $d['action'] ?? '';

    if ($action === 'add' || $action === 'update') {
        $title = trim($d['title'] ?? '');
        $slotsTotal = $d['slots_total'] ?? null;
        $description = trim($d['description'] ?? '');
        $isOpen = isset($d['is_open']) ? (int)!!$d['is_open'] : 1;
        $linkedRole = trim($d['linked_role'] ?? 'staff');

        if ($title === '') response(['success' => false, 'message' => 'Position title is required'], 400);
        if (!is_numeric($slotsTotal) || (int)$slotsTotal < 1) {
            response(['success' => false, 'message' => 'Total slots must be at least 1'], 400);
        }
        if (!in_array($linkedRole, POSITION_ALLOWED_ROLES, true)) {
            response(['success' => false, 'message' => 'Positions can only be linked to the following roles: ' . implode(', ', POSITION_ALLOWED_ROLES)], 400);
        }
        $slotsTotal = (int)$slotsTotal;

        if ($action === 'add') {
            $existing = $pdo->prepare("SELECT id FROM hrms_positions WHERE title = ?");
            $existing->execute([$title]);
            if ($existing->fetch()) response(['success' => false, 'message' => 'A position with this title already exists'], 409);

            $pdo->prepare("INSERT INTO hrms_positions (title, description, slots_total, is_open, linked_role) VALUES (?,?,?,?,?)")
                ->execute([$title, $description ?: null, $slotsTotal, $isOpen, $linkedRole]);
            addLog($u['name'] ?? 'HR', 'Position Added', "Added position $title ($slotsTotal slots, role: $linkedRole)");
            response(['success' => true]);
        }

        $id = intval($d['id'] ?? 0);
        $st = $pdo->prepare("SELECT * FROM hrms_positions WHERE id = ?");
        $st->execute([$id]);
        $pos = $st->fetch();
        if (!$pos) response(['success' => false, 'message' => 'Position not found'], 404);

        $pdo->prepare("UPDATE hrms_positions SET title=?, description=?, slots_total=?, is_open=?, linked_role=? WHERE id=?")
            ->execute([$title, $description ?: null, $slotsTotal, $isOpen, $linkedRole, $id]);
        addLog($u['name'] ?? 'HR', 'Position Updated', "Updated position {$pos['title']} -> $title ($slotsTotal slots, role: $linkedRole)");
        response(['success' => true]);
    }

    if ($action === 'delete') {
        $id = intval($d['id'] ?? 0);
        $st = $pdo->prepare("SELECT * FROM hrms_positions WHERE id = ?");
        $st->execute([$id]);
        $pos = $st->fetch();
        if (!$pos) response(['success' => false, 'message' => 'Position not found'], 404);

        $pdo->prepare("DELETE FROM hrms_positions WHERE id = ?")->execute([$id]);
        addLog($u['name'] ?? 'HR', 'Position Removed', "Removed position {$pos['title']}");
        response(['success' => true]);
    }

    response(['success' => false, 'message' => 'Unknown action'], 400);
}

response(['success' => false, 'message' => 'Method not allowed'], 405);