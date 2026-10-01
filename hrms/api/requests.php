<?php
// =====================================================================
// Requests endpoint (list + approve/reject) with formal-letter and
// signature support. Every list returns the document columns
// (letter_path, letter_name, signature_path, signed_at, signed_by)
// plus a `source` flag ('approval' | 'leave') so the UI can link the
// files and route approve/reject to the right table.
//
// GET  ?mine=1        -> my own requests (both tables)
// GET  ?to_process=1  -> pending requests routed to my role (+ pending leaves)
// GET  ?status=X      -> filtered merged list (superadmin view)
// GET  (no params)    -> merged list, latest 200
// POST {action:approve|reject, id, source?, comment?}
// NOTE: unsigned JSON creation is no longer accepted here. New requests
//       must go through request_submit.php (letter + signature).
// =====================================================================
require_once __DIR__ . "/common.php";
require_once __DIR__ . "/security.php";
require_once __DIR__ . "/feature_init.php";
require_once __DIR__ . "/request_documents.php";

$pdo = conn();
hrms_feature_init($pdo);
hrms_ensure_request_doc_columns($pdo);
hrms_ensure_leave_submitted_by($pdo);

$u = require_roles(['staff', 'admin', 'superadmin']);
$method = $_SERVER['REQUEST_METHOD'];

$APPROVAL_COLS = "id, employee_id, employee_name, kind, detail, leave_type, start_date, end_date, days, status, admin_comment, acted_by, created_at, routed_to, letter_path, letter_name, signature_path, signed_at, signed_by, signer_name, signer_photo_path, 'approval' AS source";
$LEAVE_COLS    = "id, employee_id, employee_name, 'Leave' AS kind, CONCAT(leave_type,' leave: ',start_date,' to ',end_date,' - ',reason) AS detail, leave_type, start_date, end_date, days, status, admin_comment, NULL AS acted_by, created_at, NULL AS routed_to, letter_path, letter_name, signature_path, signed_at, signed_by, signer_name, signer_photo_path, 'leave' AS source";

if ($method === 'GET') {

    // ---- My own requests ----
    if (isset($_GET['mine'])) {
        $emp = employee_for_user($pdo, $u);
        $eid = (int)($emp['id'] ?? 0);
        $uid = (int)($u['id'] ?? 0);
        $rows = [];
        $s = $pdo->prepare("SELECT $APPROVAL_COLS FROM hrms_approval_requests WHERE employee_id = ? OR submitted_by = ? ORDER BY created_at DESC");
        $s->execute([$eid, $uid]);
        $rows = array_merge($rows, $s->fetchAll());
        $s = $pdo->prepare("SELECT $LEAVE_COLS FROM hrms_leave_requests WHERE employee_id = ? OR submitted_by = ? ORDER BY created_at DESC");
        $s->execute([$eid, $uid]);
        $rows = array_merge($rows, $s->fetchAll());
        usort($rows, fn($a, $b) => strcmp((string)$b['created_at'], (string)$a['created_at']));
        response(['success' => true, 'data' => $rows]);
    }

    // ---- Pending requests I need to process ----
    if (isset($_GET['to_process'])) {
        $route = ($u['role'] === 'admin') ? 'hr' : 'superadmin';
        $s = $pdo->prepare("SELECT $APPROVAL_COLS FROM hrms_approval_requests WHERE status = 'Pending' AND routed_to = ? ORDER BY created_at DESC");
        $s->execute([$route]);
        $rows = $s->fetchAll();
        // Leave requests are processable by both HR and superadmin.
        $s = $pdo->query("SELECT $LEAVE_COLS FROM hrms_leave_requests WHERE status = 'Pending' ORDER BY created_at DESC");
        $rows = array_merge($rows, $s->fetchAll());
        usort($rows, fn($a, $b) => strcmp((string)$b['created_at'], (string)$a['created_at']));
        response(['success' => true, 'data' => $rows]);
    }

    // ---- Merged list (optional status filter) ----
    $status = trim($_GET['status'] ?? '');
    $allowed = ['Pending', 'Approved', 'Rejected', 'Cancelled'];
    if ($status !== '' && in_array($status, $allowed, true)) {
        $s = $pdo->prepare("SELECT $APPROVAL_COLS FROM hrms_approval_requests WHERE status = ? ORDER BY created_at DESC LIMIT 200");
        $s->execute([$status]);
        $rows = $s->fetchAll();
        $s = $pdo->prepare("SELECT $LEAVE_COLS FROM hrms_leave_requests WHERE status = ? ORDER BY created_at DESC LIMIT 200");
        $s->execute([$status]);
        $rows = array_merge($rows, $s->fetchAll());
    } else {
        $rows = array_merge(
            $pdo->query("SELECT $APPROVAL_COLS FROM hrms_approval_requests ORDER BY created_at DESC LIMIT 200")->fetchAll(),
            $pdo->query("SELECT $LEAVE_COLS FROM hrms_leave_requests ORDER BY created_at DESC LIMIT 200")->fetchAll()
        );
    }
    usort($rows, fn($a, $b) => strcmp((string)$b['created_at'], (string)$a['created_at']));
    response(['success' => true, 'data' => $rows]);
}

if ($method === 'POST') {
    $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ctype, 'multipart/form-data') !== false) {
        response(['success' => false, 'message' => 'Use request_submit.php for signed request uploads.'], 400);
    }
    $d = getBody();

    // Block the old unsigned creation path.
    if (isset($d['kind'])) {
        response(['success' => false, 'message' => 'Requests must include a signed formal letter. Please use the request form (upload letter, then sign).'], 400);
    }
    if (!in_array($u['role'], ['admin', 'superadmin'], true)) {
        response(['success' => false, 'message' => 'Permission denied'], 403);
    }

    $action = $d['action'] ?? '';
    $id     = intval($d['id'] ?? 0);
    $source = $d['source'] ?? '';
    $comment = trim($d['comment'] ?? '');
    if (!in_array($action, ['approve', 'reject'], true) || !$id) {
        response(['success' => false, 'message' => 'Invalid action'], 400);
    }
    $status = ($action === 'approve') ? 'Approved' : 'Rejected';
    $by = $u['name'] ?? $u['email'] ?? '';

    // Resolve the row (source hint preferred, fallback to lookup).
    $row = null;
    if ($source === 'leave' || $source === '') {
        $s = $pdo->prepare("SELECT * FROM hrms_leave_requests WHERE id = ? LIMIT 1");
        $s->execute([$id]);
        $row = $s->fetch();
        if ($row) $source = 'leave';
    }
    if (!$row && ($source === 'approval' || $source === '')) {
        $s = $pdo->prepare("SELECT * FROM hrms_approval_requests WHERE id = ? LIMIT 1");
        $s->execute([$id]);
        $row = $s->fetch();
        if ($row) $source = 'approval';
    }
    if (!$row) response(['success' => false, 'message' => 'Request not found'], 404);
    if ($row['status'] !== 'Pending') response(['success' => false, 'message' => 'Request is already ' . $row['status']], 400);

    if ($source === 'leave') {
        $pdo->prepare("UPDATE hrms_leave_requests SET status = ?, admin_comment = ? WHERE id = ?")->execute([$status, $comment, $id]);
    } else {
        $pdo->prepare("UPDATE hrms_approval_requests SET status = ?, admin_comment = ?, acted_by = ? WHERE id = ?")->execute([$status, $comment, $by, $id]);
    }

    $typeLabel = ($source === 'leave') ? 'leave' : strtolower($row['kind'] ?? 'general');
    history_row($pdo, $id, ($source === 'leave') ? 'Leave' : ($row['kind'] ?? 'General'), $row['employee_name'], $row['status'], $status, $comment, $by);
    addLog($row['employee_name'], 'Request ' . $status, ($source === 'leave' ? 'Leave' : 'General') . " request #$id marked $status by $by");

    // Notify the employee by email (matches their login account).
    $s = $pdo->prepare("SELECT email FROM hrms_employees WHERE id = ? LIMIT 1");
    $s->execute([$row['employee_id']]);
    $empEmail = $s->fetch()['email'] ?? null;
    if ($empEmail) {
        notify_user($pdo, 'Request ' . $status, 'Your ' . $typeLabel . " request was $status." . ($comment !== '' ? ' Comment: ' . $comment : ''), null, $empEmail);
    }
    response(['success' => true, 'message' => 'Request ' . strtolower($status) . '.']);
}

response(['success' => false, 'message' => 'Method not allowed'], 405);
