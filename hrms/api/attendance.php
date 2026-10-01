<?php
// ===== ATTENDANCE API =====
// GET  : attendance records with employee name + photo path.
//        admin/superadmin/finance -> everyone (optional ?from=&to= filters)
//        staff                    -> only their own records
// POST : time in / time out from the staff portal {type, photo(dataURL)}
require_once __DIR__ . "/common.php";
require_once __DIR__ . "/security.php";
require_once __DIR__ . "/feature_init.php";
$pdo = conn();
hrms_feature_init($pdo);

$u = require_roles(['staff', 'admin', 'superadmin', 'finance']);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $from = trim($_GET['from'] ?? '');
    $to   = trim($_GET['to'] ?? '');
    $where = []; $params = [];
    if ($from !== '') { $where[] = "DATE(a.created_at) >= ?"; $params[] = $from; }
    if ($to   !== '') { $where[] = "DATE(a.created_at) <= ?"; $params[] = $to; }
    if (in_array($u['role'], ['staff'], true)) {
        $emp = employee_for_user($pdo, $u);
        $where[] = "a.employee_id = ?";
        $params[] = (int)($emp['id'] ?? 0);
    }
    $sql = "SELECT a.id, a.employee_id, e.name AS employee_name, a.type, a.photo_path, a.created_at
            FROM hrms_attendance_records a
            LEFT JOIN hrms_employees e ON e.id = a.employee_id";
    if ($where) $sql .= " WHERE " . implode(' AND ', $where);
    $sql .= " ORDER BY a.created_at DESC LIMIT 1000";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    response(['success' => true, 'data' => $st->fetchAll()]);
}

if ($method === 'POST') {
    $emp = employee_for_user($pdo, $u);
    if (!$emp) response(['success' => false, 'message' => 'No employee profile linked'], 403);

    $d = getBody();
    $type = $d['type'] ?? '';
    if (!in_array($type, ['time_in', 'time_out'], true)) {
        response(['success' => false, 'message' => 'Invalid attendance type'], 400);
    }
    $today = date('Y-m-d');

    if ($type === 'time_in') {
        $st = $pdo->prepare("SELECT id FROM hrms_attendance_records WHERE employee_id = ? AND type = 'time_in' AND DATE(created_at) = ? LIMIT 1");
        $st->execute([$emp['id'], $today]);
        if ($st->fetch()) response(['success' => false, 'message' => 'You already timed in today.'], 400);
    } else {
        $st = $pdo->prepare("SELECT id FROM hrms_attendance_records WHERE employee_id = ? AND type = 'time_in' AND DATE(created_at) = ? LIMIT 1");
        $st->execute([$emp['id'], $today]);
        if (!$st->fetch()) response(['success' => false, 'message' => 'Time in first before timing out.'], 400);
        $st = $pdo->prepare("SELECT id FROM hrms_attendance_records WHERE employee_id = ? AND type = 'time_out' AND DATE(created_at) = ? LIMIT 1");
        $st->execute([$emp['id'], $today]);
        if ($st->fetch()) response(['success' => false, 'message' => 'You already timed out today.'], 400);
    }

    // Save the captured photo (jpeg/png data URL).
    $photoPath = null;
    $photo = trim((string)($d['photo'] ?? ''));
    if ($photo !== '') {
        $ext = null;
        if (strpos($photo, 'data:image/jpeg;base64,') === 0) $ext = 'jpg';
        elseif (strpos($photo, 'data:image/png;base64,') === 0) $ext = 'png';
        if ($ext === null) response(['success' => false, 'message' => 'Photo must be a JPEG or PNG image.'], 400);
        $bin = base64_decode(substr($photo, strpos($photo, ',') + 1), true);
        if ($bin === false || strlen($bin) < 64 || strlen($bin) > 2 * 1024 * 1024) {
            response(['success' => false, 'message' => 'Photo image is invalid.'], 400);
        }
        $dir = __DIR__ . '/../uploads/attendance';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $safe = 'att_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (@file_put_contents("$dir/$safe", $bin) === false) {
            response(['success' => false, 'message' => 'Failed to save attendance photo.'], 500);
        }
        $photoPath = 'uploads/attendance/' . $safe;
    }

    $pdo->prepare("INSERT INTO hrms_attendance_records (employee_id, type, photo_path) VALUES (?,?,?)")
        ->execute([$emp['id'], $type, $photoPath]);
    addLog($emp['name'], 'Attendance', ($type === 'time_in' ? 'Time in' : 'Time out') . ' recorded');
    response(['success' => true, 'message' => ($type === 'time_in' ? 'Time in recorded.' : 'Time out recorded.')]);
}

response(['success' => false, 'message' => 'Method not allowed'], 405);
