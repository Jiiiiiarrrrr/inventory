<?php
// ===== ARCHIVE API =====
// GET  : list archived employees / applicants / items (hrms_archived_items)
// POST : {action:'restore', id} -> put the original record back and mark restored
require_once __DIR__ . "/common.php";
require_once __DIR__ . "/security.php";
require_once __DIR__ . "/feature_init.php";
$pdo = conn();
hrms_feature_init($pdo);

$u = require_roles(['admin', 'superadmin']);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $pdo->query("SELECT * FROM hrms_archived_items ORDER BY removed_at DESC LIMIT 300")->fetchAll();
    response(['success' => true, 'data' => $rows]);
}

if ($method === 'POST') {
    $d = getBody();
    $action = $d['action'] ?? '';
    $id = intval($d['id'] ?? 0);
    if ($action !== 'restore' || !$id) response(['success' => false, 'message' => 'Invalid action'], 400);

    $st = $pdo->prepare("SELECT * FROM hrms_archived_items WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) response(['success' => false, 'message' => 'Archived item not found'], 404);
    if (!empty($row['restored_at'])) response(['success' => false, 'message' => 'Item is already restored'], 400);

    $by = $u['name'] ?? $u['email'] ?? '';
    $data = json_decode((string)$row['data_json'], true);

    $pdo->beginTransaction();
    try {
        // Put the original record back when we have its snapshot.
        if (is_array($data) && $row['item_type'] === 'employee') {
            $chk = $pdo->prepare("SELECT id FROM hrms_employees WHERE id = ? OR email = ? LIMIT 1");
            $chk->execute([$row['original_id'], $row['email']]);
            if (!$chk->fetch()) {
                $pdo->prepare("INSERT INTO hrms_employees (id, name, email, role, department, position, daily_rate, monthly_salary, schedule_days, schedule_start, schedule_end, status, hire_date, account_type)
                               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([
                        $row['original_id'],
                        $data['name'] ?? $row['name'],
                        $data['email'] ?? $row['email'],
                        $data['role'] ?? $row['role'] ?? 'Staff',
                        $data['department'] ?? $row['department'],
                        $data['position'] ?? null,
                        $data['daily_rate'] ?? 0,
                        $data['monthly_salary'] ?? 0,
                        $data['schedule_days'] ?? null,
                        $data['schedule_start'] ?? '09:00',
                        $data['schedule_end'] ?? '17:00',
                        $data['status'] ?? 'active',
                        $data['hire_date'] ?? null,
                        $data['account_type'] ?? 'Staff',
                    ]);
            }
        }
        if (is_array($data) && $row['item_type'] === 'applicant') {
            $chk = $pdo->prepare("SELECT id FROM hrms_applicants WHERE id = ? OR email = ? LIMIT 1");
            $chk->execute([$row['original_id'], $row['email']]);
            if (!$chk->fetch()) {
                $pdo->prepare("INSERT INTO hrms_applicants (id, name, first_name, last_name, email, position, interview_mode, status, stage, cv_path, notes)
                               VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([
                        $row['original_id'],
                        $data['name'] ?? $row['name'],
                        $data['first_name'] ?? null,
                        $data['last_name'] ?? null,
                        $data['email'] ?? $row['email'],
                        $data['position'] ?? ($row['role'] ?? ''),
                        $data['interview_mode'] ?? null,
                        $data['status'] ?? 'Pending',
                        $data['stage'] ?? 'Pending',
                        $data['cv_path'] ?? null,
                        $data['notes'] ?? null,
                    ]);
            }
        }
        $pdo->prepare("UPDATE hrms_archived_items SET restored_at = NOW() WHERE id = ?")->execute([$id]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        response(['success' => false, 'message' => 'Failed to restore item: ' . $e->getMessage()], 500);
    }

    addLog($row['name'], 'Archive Restore', ucfirst($row['item_type']) . " #{$row['original_id']} restored by $by");
    response(['success' => true, 'message' => 'Item restored.']);
}

response(['success' => false, 'message' => 'Method not allowed'], 405);
